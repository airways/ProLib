<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

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

class Bm_celltypes {
    static $_ci_view_paths = array();

    var $cache = array(
        'celltypes' => array()
    );

    function __construct()
    {
        $this->EE = &get_instance();
    }

    function get_celltypes($full=TRUE)
    {
        $result = array();

        $fieldtypes = $this->EE->db->get('exp_fieldtypes');
        $types = array();
        foreach($fieldtypes->result() as $fieldtype)
        {
            $types[$fieldtype->name] = $fieldtype;
        }

        foreach(array_merge(BM_CellType::$mason_celltypes, BM_CellType::$matrix_celltypes) as $name)
        {
            $types[$name] = new stdClass();
            $types[$name]->fieldtype_id = FALSE;
            $types[$name]->name = $name;
            $types[$name]->settings = base64_encode(serialize(array()));
            $types[$name]->hash_global_settings = 'n';
        }

        foreach($types as $name => $fieldtype)
        {
            // try to get a celltype for the fieldtype. if it is a valid
            // cell type, $celltype->valid will be TRUE, otherwise the field
            // can't be used as a cell

            if(array_key_exists($fieldtype->name, $this->cache['celltypes']))
            {
                $celltype = $this->cache['celltypes'][$fieldtype->name];
            } else {
                $celltype = new BM_CellType($fieldtype);
                $this->cache['celltypes'][$fieldtype->name] = $celltype;
            }

            if($celltype->valid)
            {
                if($full)
                {
                    $result[$celltype->name] = $celltype;
                } else {
                    $result[$celltype->name] = $celltype->name;
                }
            }
        }
        return $result;
    }

    function get_celltype($name)
    {
        $this->get_celltypes();     // refresh cache
        $result = FALSE;
        if(array_key_exists($name, $this->cache['celltypes']))
        {
            $result = $this->cache['celltypes'][$name];
        }
        return $result;
    }


    /*
        * Push package path values on to stack so we can run view scripts in other modules,
        * and so we can remove them later.
        */
    static function push_package_path($celltype)
    {
        $EE = &get_instance();

        $name = strtolower(substr(get_class($celltype), 0, -3));
        $path = PATH_THIRD.$name.'/';

        $EE->load->add_package_path($path);

        // save the current ci_view_path so we can get it back later
        array_push(BM_CellTypes::$_ci_view_paths, $EE->load->_ci_view_path);
        $EE->load->_ci_view_path = $path.'views/';
    }

    /*
        * Remove current package paths and restore previous ones
        */
    static function pop_package_path()
    {
        $EE = &get_instance();
        $EE->load->remove_package_path();
        // pop the old ci_view_path off our stack
        $EE->load->_ci_view_path = array_pop(BM_CellTypes::$_ci_view_paths);
    }

}

class BM_CellType {
    var $valid = FALSE;
    var $name = FALSE;
    var $fieldtype = FALSE;
    var $class_name = FALSE;
    var $mason_celltype = FALSE;
    var $matrix_celltype = FALSE;
    var $settings = FALSE;
    var $instance = FALSE;

    static $mason_celltypes = array('simple_text');
    static $matrix_celltypes = array('text', 'date', 'file');

    function __construct($fieldtype)
    {
        $this->EE = &get_instance();
        $this->EE->load->library('api');
        $this->EE->api->instantiate('channel_fields');

        // find the celltype class, if possible
        if (in_array($fieldtype->name, BM_CellType::$mason_celltypes))
        {
            $class = 'Mason_'.$fieldtype->name.'_ft';
            $this->mason_celltype = TRUE;
            if (!class_exists($class))
            {
                if(file_exists(PATH_THIRD.'mason/celltypes/'.$fieldtype->name.EXT))
                {
                    require_once PATH_THIRD.'mason/celltypes/'.$fieldtype->name.EXT;
                }
            }
        }
        elseif (in_array($fieldtype->name, BM_CellType::$matrix_celltypes))
        {
            $class = 'Matrix_'.$fieldtype->name.'_ft';
            $this->matrix_celltype = TRUE;
            if (!class_exists($class))
            {
                if(file_exists(PATH_THIRD.'matrix/celltypes/'.$fieldtype->name.EXT))
                {
                    require_once PATH_THIRD.'matrix/celltypes/'.$fieldtype->name.EXT;
                }
            }
        }
        else
        {
            $class = ucfirst($fieldtype->name.'_ft');
            $this->EE->api_channel_fields->include_handler($fieldtype->name);
        }

        $this->fieldtype = $fieldtype;
        $this->name = $fieldtype->name;
        $this->class_name = $class;

        if($fieldtype->settings)
        {
            $this->settings = unserialize(base64_decode($fieldtype->settings));
        }

        if(class_exists($class))
        {
            $instance = new $class();
            $this->instance = $instance;
            if(method_exists($instance, 'display_cell'))
            {
                $this->valid = TRUE;
            }
        }
    }

    function display_cell($field_name, $row_id, $col_id, $settings, $data, $template=FALSE)
    {
        $this->instance->settings = $settings;
        $this->instance->row_id = $row_id;
        $this->instance->col_id = $col_id;
        $this->instance->cell_name = 'mason_'.$field_name.'_'.$settings['column_name'].'[]';
        
        if($this->matrix_celltype)
        {
            $this->EE->session->cache['matrix']['theme_url'] = $this->EE->config->slash_item('theme_folder_url').'third_party/matrix/';
        }

        Bm_celltypes::push_package_path($this->instance);
        $result = $this->instance->display_cell($data);
        Bm_celltypes::pop_package_path();
        
        unset($settings['__EE']);
        unset($settings['__mgr']);

        $result['settings_js'] = $this->EE->javascript->generate_json($settings);
        return $result;
    }

    function save_cell($settings, $data)
    {
        $this->instance->settings = $settings;
        if(method_exists($this->instance, 'save_cell'))
        {
            return $this->instance->save_cell($data);
        } else {
            return $data;
        }
    }

    function display_cell_settings($settings)
    {
        if(method_exists($this->instance, 'display_cell_settings'))
        {
            Bm_celltypes::push_package_path($this->instance);
            $result = $this->instance->display_cell_settings($settings);
            if(!is_array($result))
            {
                $result = array(array('', $result));
            }
            Bm_celltypes::pop_package_path();

            if(!is_array($result)) exit('fail: display_cell_settings not returning an array');
            return $result;
        } else {
            return array(array('', ''));
        }
    }

}
