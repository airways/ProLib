<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * @package ProLib
 * @author Isaac Raway (MetaSushi, LLC) <isaac.raway@gmail.com>
 *
 * Copyright (c)2009, 2010, 2011, 2012. Isaac Raway and MetaSushi, LLC.
 * All rights reserved.
 *
 * This source is commercial software. Use of this software requires a
 * site license for each domain it is used on. Use of this software or any
 * of its source code without express written permission in the form of
 * a purchased commercial or other license is prohibited.
 *
 * THIS CODE AND INFORMATION ARE PROVIDED "AS IS" WITHOUT WARRANTY OF ANY
 * KIND, EITHER EXPRESSED OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND/OR FITNESS FOR A
 * PARTICULAR PURPOSE.
 *
 * As part of the license agreement for this software, all modifications
 * to this source must be submitted to the original author for review and
 * possible inclusion in future releases. No compensation will be provided
 * for patches, although where possible we will attribute each contribution
 * in file revision notes. Submitting such modifications constitutes
 * assignment of copyright to the original author (Isaac Raway and
 * MetaSushi, LLC) for such modifications. If you do not wish to assign
 * copyright to the original author, your license to  use and modify this
 * source is null and void. Use of this software constitutes your agreement
 * to this clause.
 *
 **/

class Prolib_mcp {
    private $debug_str = '';
    var $lib = null;                // Must be set to a subclass of PL_base_lib
    var $type = null;
    var $managers = null;
    var $mgr = null;

    public function __construct()
    {
        $this->EE->load->library('javascript');
        $this->EE->load->library('table');
        $this->EE->load->helper('form');

        // If there isn't a G parameter in the URL - the subclass MUST call find_manager,
        // or almost nothing in here will work properly (this applies mainly to
        // mapping the index() action to a listing).
        if($this->EE->input->get_post('type'))
        {
            $this->find_manager($this->EE->input->get_post('type'));
        }


    }
    
    public function find_manager($type)
    {
        $this->type = $type;
        $this->managers = $this->lib->get_managers();
        
        foreach($this->managers as $mgr)
        {
            if($this->type == $mgr->singular)
            {
                $this->mgr = $mgr;
                break;
            }
        }
        
        if(!$this->mgr)
        {
            throw new Exception('Invalid manager specified: '.htmlentities($type));
        }
    }

//     public function __call($method, $params)
//     {
//         $managers = $this->lib->get_managers();
// 
//         // Determine what kind of request it is
//         foreach($managers as $mgr)
//         {
//             if($method == $mgr->plural)
//             {
//                 // Listing for this manager
//                 return $this->_list($mgr);
//             } elseif(substr($method, 0-strlen($mgr->singular)) == $mgr->singular) {
//                 $tokens = explode('_', $method);
//                 switch($tokens[0])
//                 {
//                     case 'create':
//                         return $this->_create($mgr);
//                         break;
//                     case 'edit':
//                         return $this->_edit($mgr);
//                         break;
//                     case 'delete':
//                         return $this->_delete($mgr);
//                         break;
//                 }
//             }
//         }
// //
//         throw new Exception('Undefined method called: '.$method);
//     }


    public function listing()
    {
        if(!isset($this->type)) throw new Exception('Type not specified in URL or logic');
        $items = $this->mgr->get_all();

        $vars = array(
            'type'          => $this->type,
            'mgr'           => $this->mgr,
            'items'         => $items,
            'edit_url'      => ACTION_BASE.AMP.'method=edit'.AMP.'type='.$this->type.AMP.'item_id=%s',
            'delete_url'      => ACTION_BASE.AMP.'method=delete'.AMP.'type='.$this->type.AMP.'item_id=%s',
        );

        $this->get_flashdata($vars);

        return $this->auto_view('listing', $vars);
    }


    public function create()
    {
        return $this->edit(FALSE);
    }


    public function process_create()
    {
        // Initialize a data array from the POST values, based on the model object's fields
        $data = array();
        $this->prolib->copy_post($data, $this->mgr->class);

        // Create the model object
        $item = $this->mgr->create($data);

        // Go back to the list of item
        $this->EE->session->set_flashdata('message', $this->lang('msg_item_created'));
        $this->EE->functions->redirect(ACTION_BASE.AMP.'method=listing'.AMP.'type='.$this->type);

        return TRUE;
    }


    public function edit($editing=TRUE, $vars=array())
    {
        $op = $editing ? 'edit' : 'create';
        
        // Initialize the editing form, and handle process_ dispatch on POST
        list($done, $item_id, $item, $vars) =
            $this->init_edit(
                $editing ? 'edit' : 'create',
                $types = array(
                )
        );

        // Nothing left to do - process_ was dispatched and save was successful
        if($done) return;

        $vars = array(
            'type'              => $this->type,
            'mgr'               => $this->mgr,
            'hidden_fields'     => array($this->mgr->singular.'_id', 'item_id', 'settings'),
            'form'              => $this->prolib->pl_forms->create_cp_form($item->data_array(), $types),
            'action_url'        => FORM_ACTION_BASE.'method='.($editing ? 'edit'.AMP.'item_id='.$item_id : 'create').AMP.'type='.$this->type
        );


        if(is_callable(array($this, 'pre_edit_view_'.$this->type)))
        {
            $result = $this->{'pre_edit_view_'.$this->type()};
            if(isset($result) && $result)
            {
                return $result;
            }
        }

        if(isset($this->type_vars[$this->type][$op]))
        {
            $vars += $this->type_vars[$this->type][$op];
        }

        return $this->EE->load->view('generic/edit', $vars, TRUE);
    }


    public function process_edit($item_id, $item)
    {
        // echo 'process_edit';
        //
        // var_dump($item_id);
        // var_dump($item);
        // exit;
        // Copy new values from the POST, and save it
        $this->prolib->copy_post($item, $this->mgr->class);
        $item->save();

        // Go back to the listing of items
        $this->EE->session->set_flashdata('message', $this->lang('msg_item_edited'));
        $this->EE->functions->redirect(ACTION_BASE.AMP.'method=listing'.AMP.'type='.$this->type);

        return TRUE;
    }


    public function delete()
    {
        if($this->EE->input->post('item_id') !== FALSE)
        {
            if($this->process_delete()) return;
        }

        $item_id = $this->EE->input->get('item_id');
        $item = $this->mgr->get($item_id);

        $vars = array(
            'type'          => $this->type,
            'mgr'           => $this->mgr,
            'action_url'    => FORM_ACTION_BASE.'method=delete'.AMP.'type='.$this->type.AMP.'item_id='.$item_id,
            'object_name'   => $item->{$this->type.'_name'} ? $item->{$this->type.'_name'} : $this->type . ' ' . $item->{$this->type.'_id'},
            'hidden'        => array('type' => $this->type, 'item_id' => $item->{$this->type.'_id'}),
        );

        return $this->EE->load->view('delete', $vars, TRUE);
    }


    public function process_delete()
    {
        $item_id = trim($this->EE->input->post('item_id'));

        if(is_numeric($item_id))
        {
            $item = $this->mgr->get($item_id);
            $item->delete();

            // Go back to the listing of items
            $this->EE->session->set_flashdata('message', $this->lang('msg_item_deleted'));
            $this->EE->functions->redirect(ACTION_BASE.AMP.'method=listing'.AMP.'type='.$this->type);
            return TRUE;
        }
        else
        {
            show_error($this->lang('invalid_item_id').' [10]');
            return FALSE;
        }
    }


    public function lang($msg)
    {
        $item_msg = str_replace('item', $this->mgr->singular, $msg);
        if(lang($item_msg) != $item_msg)
        {
            return lang($item_msg);
        } else {
            lang($msg);
        }
    }


    function init_edit($op, $field_types)
    {
        // Automatically get an object to edit and dispatch process_edit or process_create if request is a POST

        // find checkboxes and set their values to "n" if not present
        foreach($field_types as $field => $type)
        {
            if($type == 'checkbox' or is_array($type) and $type[0] == 'checkbox')
            {
                if(!$this->EE->input->get_post($field))
                {
                    $_POST[$field] = 'n';
                }
            }
        }

        $object = FALSE;
        if($op != 'create')
        {
            $item_id = (int)$this->EE->input->get_post('item_id');
            if($item_id)
            {
                $object = $this->mgr->get($item_id);
            }
        }

        if(!$object)
        {
            $item_id = 0;
            $row = FALSE;
            $class = $this->mgr->class;
            $object = new $class($row, $this->mgr);
        }

        $done = FALSE;
        if($_SERVER['REQUEST_METHOD'] == 'POST')
        {
            if($this->{'process_'.$op}($item_id, $object)) $done = TRUE;
        }

        $vars = array(
            'type'          => $this->type,
            'mgr'           => $this->mgr,
            'item_id'       => $item_id,
            'action_url'    => CP_ACTION.$op.AMP.'type='.$this->type.AMP.($op!='create' ? 'item_id='.$item_id : '')
        );

        return array($done, $item_id, $object, $vars);
    }


    public function auto_view($action, $vars)
    {
        $path = PATH_THIRD.$this->prolib->package_name.'/views/'.$this->mgr->plural.'/'.$action.'.php';
        if(file_exists($path))
        {
            return $this->EE->load->view($this->mgr->plural.'/'.$action, $vars, TRUE);
            //return $this->EE->load->view($path, $vars, TRUE);
        } else {
            $path = PATH_THIRD.$this->prolib->package_name.'/views/generic/'.$action.'.php';
            if(file_exists($path))
            {
                return $this->EE->load->view('generic/'.$action, $vars, TRUE);
                //return $this->EE->load->view($path, $vars, TRUE);
            } else {
//                 $path = PATH_THIRD.'prolib/views/generic/'.$action.'.php';
//                 if(file_exists($path))
//                 {
//                     return $this->EE->load->view('generic/'.$action, $vars, TRUE);
//                     //return $this->EE->load->view($path, $vars, TRUE);
//                 } else {
                    throw new Exception('Cannot find view file in any known location: '.htmlentities($action));
//                 }
            }
        }
    }


    public function pagination_config($method, $total_rows)
    {
        // Pass the relevant data to the paginate class
        $config['base_url'] = ACTION_BASE.AMP.'method='.$method;
        $config['total_rows'] = $total_rows;
        $config['per_page'] = $this->perpage;
        $config['page_query_string'] = TRUE;
        $config['query_string_segment'] = 'rownum';
        $config['full_tag_open'] = '<p id="paginationLinks">';
        $config['full_tag_close'] = '</p>';
        $config['prev_link'] = '<img src="'.$this->EE->cp->cp_theme_url.'images/pagination_prev_button.gif" width="13" height="13" alt="<" />';
        $config['next_link'] = '<img src="'.$this->EE->cp->cp_theme_url.'images/pagination_next_button.gif" width="13" height="13" alt=">" />';
        $config['first_link'] = '<img src="'.$this->EE->cp->cp_theme_url.'images/pagination_first_button.gif" width="13" height="13" alt="< <" />';
        $config['last_link'] = '<img src="'.$this->EE->cp->cp_theme_url.'images/pagination_last_button.gif" width="13" height="13" alt="> >" />';

        return $config;
    }


    public function sub_page($page, $added_title = '')
    {
        $this->EE->cp->set_breadcrumb(ACTION_BASE.AMP.'module='.$this->prolib->package_name.AMP, $this->EE->lang->line($this->prolib->package_name.'_module_name'));
        $this->EE->cp->set_variable('cp_page_title', $this->lang($this->prolib->package_name.'_title') . ' ' . $this->lang($page) . ($added_title != '' ? ' - ' . $added_title : ''));
    }


    public function error($msg) {
        show_error($msg);
        return FALSE;
    }


    public function debug($msg)
    {
        $this->debug_msg .= htmlentities($msg).'<br/>';
    }


    public function get_flashdata(&$vars)
    {
        $vars['message'] = $this->EE->session->flashdata('message') ? $this->EE->session->flashdata('message') : false;
        $vars['error'] = $this->EE->session->flashdata('error') ? $this->EE->session->flashdata('error') : false;
    }
}
