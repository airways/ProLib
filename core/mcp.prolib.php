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

class Prolib_base_mcp {
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

        // If there isn't a type parameter in the URL - the subclass MUST call find_manager,
        // or almost nothing in here will work properly (this applies mainly to
        // mapping the index() action to a listing).
        if($this->EE->input->get_post('G'))
        {
            $this->find_manager($this->EE->input->get_post('G'));
        }


    }

    function set_page_title($title)
    {
        if (version_compare(APP_VER, '2.6', '>=') >= 0) {
            $this->EE->view->cp_page_title = $this->EE->lang->line($title);
        } else {
            $this->EE->cp->set_variable('cp_page_title', $this->EE->lang->line($title));
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

    /**
     * Handle a Driver action method
     * The GET parameter "action" should be the name of a method to call in any driver that defines it.
     */
    public function driver()
    {
//         if(!isset($this->type)) throw new Exception('Type not specified in URL or logic');
        $this->prolib->pl_drivers->init();
        
        $vars = array(
            'type'          => $this->type,
            'package_name'  => $this->prolib->package_name,
            'mgr'           => $this->mgr,
            'help'          => $this->get_help($this->type),
            'create_item'   => $this->lang('item_create'),
        );
        
        if(isset($this->add_vars)) $vars += $this->add_vars;

        $this->get_flashdata($vars);
        $action = $this->EE->input->get('action');
        if($action)
        {
            $output = $this->prolib->pl_drivers->$action($this, $vars, '');
            if($output == '')
            {
                $output = '<b>Driver action not found!</b><br/>Be sure you have installed the driver you are trying to use.';
            }
        } else {
            $output = '<b>Driver action not specified!</b><br/>The driver method requires an action parameter.';
        }
        return $output;
    }




    public function listing($params=array('set_title' => true))
    {
        if(!isset($this->type)) throw new Exception('Type not specified in URL or logic');

        if($params['set_title'])
        {
            $this->sub_page($this->mgr->singular.'_list');
        }

        $filters = array();
        if(isset($this->mgr->filters))
        {
            foreach($this->mgr->filters as $field)
            {
                if($value = $this->EE->input->get('filter_'.$field))
                {
                    $filters[$field] = $value;
                }
            }
        }

        if(count($filters) == 0)
        {
            $items = $this->mgr->get_all();
        } else {
            $items = $this->mgr->get_objects($filters);
        }

        $vars = array(
            'type'          => $this->type,
            'filters'       => $filters,
            'package_name'  => $this->prolib->package_name,
            'mgr'           => $this->mgr,
            'help'          => $this->get_help($this->type),
            'items'         => $items,
            'create_item'   => $this->lang('item_create'),
            'edit_url'      => ACTION_BASE.AMP.'method=edit'.AMP.'G='.$this->type.AMP.'item_id=%s',
            'delete_url'    => ACTION_BASE.AMP.'method=delete'.AMP.'G='.$this->type.AMP.'item_id=%s',
        );
        
        $vars = $this->EE->pl_drivers->listing_data($vars);

        $this->get_flashdata($vars);
        
        return $this->auto_view('listing', $vars);
    }


    public function create()
    {
        return $this->edit(FALSE);
    }


    public function process_create()
    {
        $return_type = $this->EE->input->get('return_type');
        $return_item_id = $this->EE->input->get('return_item_id');

        // Initialize a data array from the POST values, based on the model object's fields
        $data = array();
        $this->prolib->copy_post($data, $this->mgr->class);

        if($return_type)
        {
            $data[$return_type.'_id'] = $return_item_id;
        }
        
        $return = '';
        foreach($this->mgr->filters as $field)
        {
            $return .= AMP.'filter_'.$field.'='.$data[$field];
        }

        // Create the model object
        $data = $this->EE->pl_drivers->process_create_data($data);
        $item = $this->mgr->create($data);

        // Go back to the list of item
        $this->EE->session->set_flashdata('message', $this->lang('msg_item_created'));
        $this->EE->functions->redirect(ACTION_BASE.AMP
            .($return_type ? 'method=edit'.AMP.'G='.$return_type.AMP.'item_id='.$return_item_id
                           : 'method=listing'.AMP.'G='.$this->type.$return));

        return TRUE;
    }


    public function edit($editing=TRUE, $vars=array())
    {
        $op = $editing ? 'edit' : 'create';
        $return_type = $this->EE->input->get('return_type');
        $return_item_id = $this->EE->input->get('return_item_id');

        // Initialize the editing form, and handle process_ dispatch on POST
        list($done, $item_id, $item, $vars, $types) =
            $this->init_edit(
                $editing ? 'edit' : 'create'
        );

        if(!$editing && $return_type)
        {
            $item->{$return_type.'_id'} = $return_item_id;
        }
        
        $form_name = $this->mgr->singular.'_'.$op;
        $this->sub_page($form_name, $op == 'edit' ? $item->get_obj_name() : '');

        // Nothing left to do - process_ was dispatched and save was successful
        if($done) return;


        $child_items = array();
        $managers = $this->lib->get_managers();
        if($item_id)
        {
            foreach($this->mgr->children as $child_mgr_name)
            {
                if(!isset($managers[$child_mgr_name]) || !is_object($managers[$child_mgr_name]))
                {
                    var_dump(array_keys($managers));
                    pl_show_error('Invalid child manager: '.$child_mgr_name);
                    exit;
                }
                $child_mgr = $managers[$child_mgr_name];
                $child_items[$child_mgr_name] = $child_mgr->get_objects(array($this->mgr->singular.'_id' => $item_id));
            }
        }

        $this->EE->load->library('table');

        // Fields that aren't shown in the form at all
        $hidden_fields = array_merge(array($this->mgr->singular.'_id', 'item_id', 'settings'), $this->mgr->edit_hidden);
        // Fields that are set as hidden input elements
        $hidden = array('G' => $this->type);

        // Filter values can also be set as presets, since a Create button will have those values populated
        // on it if used from a filtered view.
        if(isset($this->mgr->filters))
        {
            foreach($this->mgr->filters as $field)
            {
                $hidden[$field] = $this->EE->input->get($field);
            }
        }
        
        $vars = array(
            'form_name'         => $this->lang($form_name),
            'package_name'      => $this->prolib->package_name,
            'type'              => $this->type,
            'item_id'           => $item_id,
            'mgr'               => $this->mgr,
            'help'              => $this->get_help($this->type),
            'managers'          => $managers,
            'child_items'       => $child_items,
            'hidden_fields'     => $hidden_fields,
            'mcp'               => &$this,
            'form'              => $this->prolib->pl_forms->create_cp_form($item->data_array(), $types),
            'action_url'        => FORM_ACTION_BASE.'method='.($editing ? 'edit'.AMP.'item_id='.$item_id : 'create').AMP.'G='.$this->type
                .($return_type ? AMP.'return_type='.$return_type.AMP.'return_item_id='.$return_item_id : ''),
            'hidden'            => $hidden,
        );


        if(is_callable(array($this, 'pre_edit_view_'.$this->type)))
        {
            $result = $this->{'pre_edit_view_'.$this->type()};
            if(isset($result) && $result)
            {
                return $result;
            }
        }

        if(isset($this->type_vars[$this->type][$op])) $vars += $this->type_vars[$this->type][$op];
        if(isset($this->add_vars)) $vars += $this->add_vars;

        $this->get_flashdata($vars);
        $vars = $this->EE->pl_drivers->edit_data($vars);
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
        $return_type = $this->EE->input->get('return_type');
        $return_item_id = $this->EE->input->get('return_item_id');

        $this->prolib->copy_post($item, $this->mgr->class);

        $item = $this->EE->pl_drivers->process_edit_data($item);

        $item->save();

        $return = '';
        foreach($this->mgr->filters as $field)
        {
            $return .= AMP.'filter_'.$field.'='.$item->$field;
        }

        // Go back to the listing of items
        $this->EE->session->set_flashdata('message', $this->lang('msg_item_edited'));
        $this->EE->functions->redirect(ACTION_BASE.AMP
            .($return_type ? 'method=edit'.AMP.'G='.$return_type.AMP.'item_id='.$return_item_id
                           : 'method=listing'.AMP.'G='.$this->type.$return));

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
        $return_type = $this->EE->input->get('return_type');
        $return_item_id = $this->EE->input->get('return_item_id');

        $vars = array(
            'type'          => $this->type,
            'mgr'           => $this->mgr,
            'help'          => $this->get_help($this->type),
            'action_url'    => FORM_ACTION_BASE.'method=delete'.AMP.'G='.$this->type.AMP.'item_id='.$item_id
                .($return_type ? AMP.'return_type='.$return_type.AMP.'return_item_id='.$return_item_id : ''),
            'object_name'   => 
                isset($item->{$this->type.'_name'}) 
                    ? $item->{$this->type.'_name'} 
                        : (isset($item->name)
                            ? $item->name
                                : $this->type . ' ' . $item->{$this->type.'_id'}),
            'hidden'        => array('G' => $this->type, 'item_id' => $item->{$this->type.'_id'}),
        );

        $vars = $this->EE->pl_drivers->delete_data($vars);
        return $this->auto_view('delete', $vars);
    }


    public function process_delete()
    {
        $item_id = trim($this->EE->input->post('item_id'));

        if(is_numeric($item_id))
        {
            $item = $this->mgr->get($item_id);
            $this->EE->pl_drivers->process_delete_data($item);
            $item->delete();

            $return_type = $this->EE->input->get('return_type');
            $return_item_id = $this->EE->input->get('return_item_id');

            // Go back to the listing of items
            $this->EE->session->set_flashdata('message', $this->lang('msg_item_deleted'));
            $this->EE->functions->redirect(ACTION_BASE.AMP
                .($return_type ? 'method=edit'.AMP.'G='.$return_type.AMP.'item_id='.$return_item_id
                           : 'method=listing'.AMP.'G='.$this->type));
            return TRUE;
        }
        else
        {
            show_error($this->lang('invalid_item_id').' [10]');
            return FALSE;
        }
    }


    function preferences()
    {
        $this->find_manager('preference');
        
        if($_SERVER['REQUEST_METHOD'] == 'POST')
        {
            if($this->process_preferences()) return;
        }

        $vars = array();
        $this->sub_page('tab_preferences');

        $form = array();

        $prefs = $this->lib->prefs->get_preferences();

        if(is_callable(array($this->lib->prefs, 'prefs_form')))
        {
            $types = $this->lib->prefs->prefs_form($prefs);
            
            foreach($prefs as $pref => $value)
            {
                $f_name = 'pref_' . $pref;
    
                if(isset($types[$f_name]))
                {
                    $control = $types[$f_name];
                } else {
                    $control = form_input($f_name, $value);
                }
                
                $form[] = array('lang_field' => $f_name, 'label' => lang($f_name), 'control' => $control);
            }
        }

        $vars = array(
            'form_name'         => $this->lang('preferences'),
            'package_name'      => $this->prolib->package_name,
            'type'              => 'preference',
            'mgr'               => $this->mgr,
            'help'              => $this->get_help('preference'),
            'form'              => $form,
            'action_url'        => FORM_ACTION_BASE.'method=preferences'.AMP.'G=preference',
            'editing'           => FALSE,
        );

        $this->get_flashdata($vars);
        $vars = $this->EE->pl_drivers->preferences_data($vars);
        return $this->auto_view('edit', $vars);
    }

    function process_preferences()
    {
        // returns an array of preferences as name => value pairs
        $prefs = $this->lib->prefs->get_preferences();
        foreach($prefs as $pref => $existing_value)
        {
            $f_name = 'pref_' . $pref;
            $value = $this->EE->input->post($f_name);
            if($value != $existing_value)
            {
//                 if($value)
//                 {
                    $value = $this->EE->input->post($f_name);
                    $this->lib->prefs->set($pref, $value);
//                 } else {
//                     switch($f_name)
//                     {
//                         case 'pref_safecracker_integration_on':
//                         case 'pref_safecracker_separate_channels_on':
//                             $this->EE->formslib->prefs->set($pref, 'n');
//                     }
//                 }
            }
        }


        $this->EE->session->set_flashdata('message', $this->lang('msg_preferences_edited'));
        
        $this->EE->pl_drivers->process_preferences();
        
        $this->EE->functions->redirect(ACTION_BASE.AMP.'method=preferences');
        return TRUE;
    }

    function init_edit($op, $field_types=array())
    {
        $object = FALSE;
        if($op != 'create')
        {
            $item_id = (int)$this->EE->input->get_post('item_id');
            if($item_id)
            {
                $object = $this->mgr->get($item_id);
            }
        }

        if(is_callable(array($this->mgr, 'model_form')))
        {
            $field_types = $field_types + $this->mgr->model_form($object);
        }

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
            'action_url'    => CP_ACTION.$op.AMP.'G='.$this->type.AMP.($op!='create' ? 'item_id='.$item_id : '')
        );

        return array($done, $item_id, $object, $vars, $field_types);
    }


    public function auto_view($action, $vars)
    {
        if(isset($this->add_vars)) $vars += $this->add_vars;
        $vars['mcp'] = &$this;
        
        if(isset($this->mgr))
        {
            $path = PATH_THIRD.$this->prolib->package_name.'/views/'.$this->mgr->plural.'/'.$action.'.php';
        }
        
        if(isset($path) && file_exists($path))
        {
            $result = $this->EE->load->view($this->mgr->plural.'/'.$action, $vars, TRUE);
            //return $this->EE->load->view($path, $vars, TRUE);
        } else {
            $path = PATH_THIRD.$this->prolib->package_name.'/views/generic/'.$action.'.php';
            if(file_exists($path))
            {
                $result = $this->EE->load->view('generic/'.$action, $vars, TRUE);
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
        
        $result = $this->EE->pl_drivers->auto_view($action, $vars, $result);
        return $result;
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


    public function lang($msg, $type=false)
    {
        if(!$type) $type = $this->type;
        $mgr = false;
        if($this->managers)
        {
            foreach($this->managers as $mgr)
            {
                if($type == $mgr->singular)
                {
                    break;
                }
            }
        }
        if($mgr)
        {
            $item_msg = str_replace('item', $mgr->singular, $msg);
        } else {
            $item_msg = $msg;
        }
        if(lang($item_msg) != $item_msg)
        {
            return lang($item_msg);
        } else {
            return lang($msg);
        }
    }
    
    public function get_help($type)
    {
        $help = $this->lang($type.'_help');
        if($help != $type.'_help')
        {
            return $help;
        } else {
            return '';
        }
    }


    public function sub_page($page, $added_title = '')
    {
        $this->EE->cp->set_breadcrumb(ACTION_BASE.AMP.'module='.$this->prolib->package_name.AMP, $this->EE->lang->line($this->prolib->package_name.'_module_name'));
        $this->EE->view->cp_page_title = $this->lang($this->prolib->package_name.'_title') . ' - ' . $this->lang($page) . ($added_title != '' ? ' - ' . $added_title : '');
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
        $message = $this->EE->session->flashdata('message') ? $this->EE->session->flashdata('message') : '';
        $error = $this->EE->session->flashdata('error') ? $this->EE->session->flashdata('error') : '';

        if(isset($vars['message'])) $vars['message'] .= '<br/>'.$message;
        if(isset($vars['error'])) $vars['error'] .= '<br/>'.$error;
    }
}
