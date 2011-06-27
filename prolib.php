<?php

/**
 * @package ProLib
 * @author Isaac Raway <isaac@metasushi.com>
 *
 * Copyright (c)2009, 2010. Isaac Raway and MetaSushi, LLC. All rights reserved.
 *
 * This source is commercial software. Use of this software requires a site license for each
 * domain it is used on. Use of this software or any of it's source code without express
 * written permission in the form of a purchased commercial or other license is prohibited.
 *
 * THIS CODE AND INFORMATION ARE PROVIDED "AS IS" WITHOUT WARRANTY OF ANY
 * KIND, EITHER EXPRESSED OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND/OR FITNESS FOR A
 * PARTICULAR PURPOSE.
 *
 **/

require_once 'helpers/icons_helper.php';
require_once 'helpers/krumo_helper.php';
require_once 'libraries/bm_debug.php';
require_once 'libraries/bm_email.php';
require_once 'libraries/bm_handle_mgr.php';
require_once 'libraries/bm_parser.php';
require_once 'libraries/bm_uploads.php';
require_once 'libraries/bm_forms.php';
require_once 'libraries/bm_prefs.php';
require_once 'libraries/bm_celltypes.php';
require_once 'libraries/bm_validation.php';

function prolib(&$object, $package_name)
{
    // @version 1.1
    var $version = "1.1";
    
    global $PROLIB;
    
    $object->EE = &get_instance();
    
    if(!isset($PROLIB))
    {
        $PROLIB = new Prolib();
    }
    
    $object->prolib         = $PROLIB;
    $PROLIB->setup($object, $package_name);

    $object->EE->bm_debug       = &$object->prolib->bm_debug;
    $object->EE->bm_email       = &$object->prolib->bm_email;
    $object->EE->bm_parser      = &$object->prolib->bm_parser;
    $object->EE->bm_uploads     = &$object->prolib->bm_uploads;
    $object->EE->bm_forms       = &$object->prolib->bm_forms;
    $object->EE->bm_prefs       = &$object->prolib->bm_prefs;
    $object->EE->bm_celltypes   = &$object->prolib->bm_celltypes;
    $object->EE->bm_validation  = &$object->prolib->bm_validation;

    return $PROLIB;
}

/**
 * Generic prolib class - provides helper functions that don't belong in a larger class.
 * Initializes prolib classes and provides them as properties.
 **/
class Prolib {
    var $package_name = FALSE;
    var $caches = array();
    
    function __construct()
    {
        $this->EE = &get_instance();
        
        // create "library" classes - only called once per-request, these
        // objects are treated as singletons and attached to whatever objects
        // need to use them through their $this->prolib, initialized by prolib()
        
        $this->bm_debug         = new Bm_debug();
        $this->bm_email         = new Bm_email();
        $this->bm_parser        = new Bm_parser();
        $this->bm_uploads       = new Bm_uploads();
        $this->bm_forms         = new Bm_forms();
        $this->bm_prefs         = new Bm_prefs();
        $this->bm_celltypes     = new Bm_celltypes();
        $this->bm_validation    = new Bm_Validation();

        $this->EE = &get_instance();
        
        // initialize caches
        $this->cache['get_fields'] = array();
    }

    function setup($object, $package_name)
    {
        // attaches the library to a particular package, call every time
        // prolib() is called.
        
        $this->package_name = $package_name;
        
        $theme = $this->EE->config->item('theme_folder_url');
        if(substr($theme, -1) != '/') $theme .= '/';
        $object->theme_url = $theme.'third_party/'.$package_name.'/';
        
        if(defined('BASE'))
        {
            defined('ACTION_BASE') OR define('ACTION_BASE', BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module='.$package_name);
            defined('TAB_ACTION') OR define('TAB_ACTION', BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module='.$package_name.AMP);
            defined('CP_ACTION') OR define('CP_ACTION', 'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module='.$package_name.AMP.'method=');
        }
        
        return $this;
    }

    function is_debug()
    {
        /* debug may be set to one of:
         *   0 - off
         *   1 - show debug info only to super admins
         *   2 - show debug info to everyone
         */
        
        return ($this->EE->config->item('debug') == 1 AND $this->EE->session->userdata['group_id'] == 1)
             OR $this->EE->config->item('debug') == 2;
    }
    
    function cp_sub_page($page, $added_title = '')
    {
        if(isset($this->_package_name))
        {
            $package_name = $this->package_name;
        } else {
            $package_name = '';
        }
        $this->EE->cp->set_breadcrumb(ACTION_BASE.AMP.'module=bm_forms'.AMP, $this->EE->lang->line($package_name.'_module_name'));
        $this->EE->cp->set_variable('cp_page_title', lang($page) . ($added_title != '' ? ' - ' . $added_title : ''));
    }
    
    function cp_start_edit($mcp, $op, $field_types, $id_field, $method_stub, $class)
    {
        // Automatically get an object to edit and dispatch process_edit_* or process_new_* if request is a POST
        $this->EE->load->library('table');
        
        // usage:
        //   list($done, $block_type_id, $block, $vars) = $this->prolib->cp_start_edit($this, $editing, 'block_type_id', 'block_type', 'Mason_block');
        //   if($done) return;
        
        // find checkboxes and set their values to "n" if not present
        foreach($field_types as $field => $type)
        {
            if($type == 'checkbox')
            {
                if(!$this->EE->input->get_post($field))
                {
                    $_POST[$field] = 'n';
                }
            }
        }
        if($op != 'new')
        {
            $object_id = (int)$this->EE->input->get_post($method_stub.'_id');
            
            $object = $this->EE->masonlib->{'get_'.$method_stub}($object_id);
            #$this->debug($object, TRUE);
        } else {
            $object_id = 0;
            $object = new $class(FALSE);
        }

        $done = FALSE;
        if($_SERVER['REQUEST_METHOD'] == 'POST')
        {
            //if($editing)  {
                if($mcp->{'process_'.$op.'_'.$method_stub}($object_id, $object)) $done = TRUE;
            //} else {
            //    if($mcp->{'process_new_'.$method_stub}($object_id, $object)) $done = TRUE;
            //}
        }
        
        $vars = array(
            $method_stub.'_id' => $object_id,
            //'action_url' => CP_ACTION.($editing?'edit_':'new_').$method_stub.AMP.($editing?$method_stub.'_id='.$object_id:'')
            'action_url' => CP_ACTION.$op.'_'.$method_stub.AMP.($op!='new'?$method_stub.'_id='.$object_id:'')
        );
        
        return array($done, $object_id, $object, $vars);
    }
    
    function copy_post(&$object, $class)
    {
        foreach($this->get_fields($class) as $k)
        {
            if($this->EE->input->post($k) !== FALSE)
            {
                if(is_array($object))
                {
                    $object[$k] = $this->EE->input->post($k);
                } else {
                    $object->$k = $this->EE->input->post($k);
                }
            }
        }
        return $object;
    }
    
    function copy_data(&$to_object, $class, $data)
    {
        foreach($this->get_fields($class) as $k)
        {
            if(isset($data[$k]))
            {
                if(is_array($to_object))
                {
                    $object[$k] = $data[$k];
                } else {
                    $to_object->$k = $data[$k];
                }
            }
        }
        return $to_object;
    }
    
    function get_fields($class)
    {
        if(!isset($this->cache['get_fields'][$class]))
        {
            $this->cache['get_fields'][$class] = array();
            $object = new $class(FALSE);
            foreach($object as $k => $v)
            {
                $this->cache['get_fields'][$class][] = $k;
            }
        }
        
        return $this->cache['get_fields'][$class];
    }

    function get_mailinglists()
    {
        $result = array();
        $query = $this->EE->db->query("SELECT list_id, list_name FROM exp_mailing_lists");
        foreach($query->result() as $row)
        {
            $result[$row->list_id] = $row->list_name;
        }
        return $result;
    }
    
    /**
     * Shorthand to call hooks implemented by the module. Don't include the package name - it is
     * prefixed automatically. So, calling prolib($this, 'mason')->hook('parse'); would trigger
     * a hook named mason_parse.
     **/
    function hook($hook, $data = FALSE)
    {
        $hook = ($this->package_name ? $this->package_name.'_' : '').$hook;
        
        if ($this->EE->extensions->active_hook($hook) === TRUE)
        {
            return $this->EE->extensions->call($hook, $data);
        }
        
        return $data;
    }
    
    /**
     * Generic debug dumper that can use krumo if installed, otherwise prints a
     * <pre> wrapped print_r() outpuit for the given variable. Optionally exits
     * after printing output.
     **/
    function debug($str, $exit=FALSE)
    {
        if(function_exists('krumo')) {
            krumo($str);
        } else {
            echo "<pre>";
            print_r($str);
            echo "</pre>";
        }

        if($exit) exit("[Exit]");
    }

    function make_options($rows, $value_field, $label_field)
    {
        $result = array();
        foreach($rows as $row)
        {
            if(is_object($row))
            {
                $result[$row->$value_field] = $row->$label_field;
            } else {
                $result[$row[$value_field]] = $row[$label_field];
            }
        }
        return $result;
    }
}
