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
    
    public function __construct()
    {
        $this->EE->load->library('javascript');
        $this->EE->load->library('table');
        $this->EE->load->library('formslib');
        $this->EE->load->helper('form');
    }

    function __call($method, $params)
    {
        $managers = $this->lib->get_managers();
        
        // Determine what kind of request it is
        foreach($managers as $mgr)
        {
            if($method == $mgr->plural)
            {
                // Listing for this manager
                return $this->listing($mgr);
            } elseif(substr($method, 0-strlen($mgr->singular)) == $mgr->singular) {
                $tokens = explode('_', $method);
                switch($tokens[0])
                {
                    case 'new':
                        return $this->new_object($mgr);
                        break;
                    case 'edit':
                        return $this->edit_object($mgr);
                        break;
                    case 'delete':
                        return $this->delete_object($mgr);
                        break;
                }
            }
        }
        
        throw new Exception('Undefined method called: '.$method);
    }

    function listing($mgr)
    {
        var_dump($mgr->get_objects());
    }
    
    function pagination_config($method, $total_rows)
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

    function sub_page($page, $added_title = '')
    {
        $this->EE->cp->set_breadcrumb(ACTION_BASE.AMP.'module='.$this->prolib->package_name.AMP, $this->EE->lang->line($this->prolib->package_name.'_module_name'));
        $this->EE->cp->set_variable('cp_page_title', lang($this->prolib->package_name.'_title') . ' ' . lang($page) . ($added_title != '' ? ' - ' . $added_title : ''));

    }

    function error($msg) {
        show_error($msg);
        return FALSE;
    }

    function debug($msg)
    {
        $this->debug_msg .= htmlentities($msg).'<br/>';
    }

    function get_flashdata(&$vars)
    {
        $vars['message'] = $this->EE->session->flashdata('message') ? $this->EE->session->flashdata('message') : false;
        $vars['error'] = $this->EE->session->flashdata('error') ? $this->EE->session->flashdata('error') : false;
    }
}
