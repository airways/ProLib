<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * @package ProLib
 * @author Isaac Raway <isaac.raway@gmail.com>
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

require_once 'mason_db_shim.php';

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
            //echo $fieldtype->name."<br/>";
            if(array_key_exists($fieldtype->name, $this->cache['celltypes']))
            {
                $celltype = $this->cache['celltypes'][$fieldtype->name];
            } else {
                $celltype = new BM_CellType($fieldtype);
                $this->cache['celltypes'][$fieldtype->name] = $celltype;
            }

            if(!$celltype->name) {
                echo "<b>Invalid celltype:</b>";
                unset($fieldtype->EE);
                var_dump($fieldtype);
                exit;
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
            
            //echo $celltype->name.': '.$celltype->provider.'<br/>';
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
        
        // Check for a language file in the package
        if(file_exists(PATH_THIRD.$name.'/language/english/lang.'.$name.EXT))
        {
            $EE->lang->load($name, '', FALSE, FALSE, PATH_THIRD.$name.'/');
        }
        
        // // save the current ci_view_path so we can get it back later
        // array_push(BM_CellTypes::$_ci_view_paths, $EE->load->_ci_view_path);
        // $EE->load->_ci_view_path = $path.'views/';
    }

    /*
     * Remove current package paths and restore previous ones
     */
    static function pop_package_path()
    {
        $EE = &get_instance();
        $EE->load->remove_package_path();
        // // pop the old ci_view_path off our stack
        // $EE->load->_ci_view_path = array_pop(BM_CellTypes::$_ci_view_paths);
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
    var $_apply_mason_shim = TRUE;
    
    static $mason_celltypes = array('simple_text', 'simple_file');
    static $matrix_celltypes = array('text', 'date');

    function __construct($fieldtype)
    {
        $this->EE = &get_instance();
        $this->EE->load->library('api');
        $this->EE->api->instantiate('channel_fields');

        // find the celltype class, if possible
        if (in_array($fieldtype->name, BM_CellType::$mason_celltypes))
        {
            $this->provider = "mason";
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
            $this->provider = "matrix";
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
            $this->provider = "fieldtype";
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

    function _settings($field_id, $field_name, $row_id, $col_id, &$settings, $entry_id=FALSE)
    {
        if($entry_id)
        {
            $settings['entry_id'] = $entry_id;
        }
        
        $settings['field_id'] = $field_id;
        $settings['field_name'] = $field_name;
        $settings['row_id'] = $row_id;
        $settings['row_name'] = 'block_'.$row_id;
        $settings['col_id'] = $col_id;
        $settings['col_name'] = 'column_'.$col_id;
        $this->settings = $settings;
        $this->instance->settings = $settings;
        $this->instance->field_id = $field_id;
        $this->instance->field_name = $field_name;
        $this->instance->row_id = $row_id;
        $this->instance->col_id = $col_id;
        
        $this->instance->cell_name = 'mason_'.$field_name.'_column_'.$col_id.'['.$row_id.']';
        // if(!isset($settings['playa']))
        // {
        //     // Playa always adds a [] to the end of it's field_name, so we don't want to double add it
        //     $this->instance->cell_name .= '[]';
        // }
        
    }
    
    /**
     * Return whether the field type instance contains a 
     * callable method beginning with $methodPrefix
     * 
     * @param string $methodPrefix
     * @return bool
     */
    function has_callable_method_like($methodPrefix)
    {
        $methods = get_class_methods($this->instance);
        foreach ($methods as $method) {
            if (strpos($method, $methodPrefix) === 0 && is_callable(array($this->instance, $method))) {
                return true;
            }
        }
        return false;
    }
    
    function has_method($method)
    {
        return method_exists($this->instance, $method);
    }
    
    function display_cell($field_id, $field_name, $row_id, $col_id, $settings, $data, $template=FALSE)
    {
        $this->_settings($field_id, $field_name, $row_id, $col_id, $settings);
        
        if($this->matrix_celltype)
        {
            $this->EE->session->cache['matrix']['theme_url'] = $this->EE->config->slash_item('theme_folder_url').'third_party/matrix/';
        }

        
        Bm_celltypes::push_package_path($this->instance);
        
        if(method_exists($this->instance, 'display_cell'))
        {
            $result = $this->instance->display_cell($data);
        } else {
            $result = array();
        }

        if(!is_array($result))
        {
            $result = array('data' => $result);
        }
        Bm_celltypes::pop_package_path();
        
        unset($settings['__EE']);
        unset($settings['__mgr']);

        $result['settings_js'] = $this->EE->javascript->generate_json($settings);
        /*if($col_id == 2) {
            echo $result;
            exit;
        }*/
        $result['data'] = preg_replace('/name="([^\[\]]+?)\[\]"/', 'name="\1['.$row_id.']"', $result['data']);
        $result['data'] = preg_replace('/name="([^\[\]]+?)\[\]\[\]"/', 'name="\1['.$row_id.'][]"', $result['data']);
        if($this->name == 'pt_multiselect')
        {
            //echo "result:".$result['data'];
            //exit;
        }
        
        return $result;
    }

    function save_cell($field_id, $field_name, $row_id, $col_id, $settings, $data, $apply_shim = FALSE)
    {
        $this->_settings($field_id, $field_name, $row_id, $col_id, $settings);
        
        $this->instance->settings = $this->settings;
        if(method_exists($this->instance, 'save_cell'))
        {
            #echo get_class($this->instance).'->save_call()<br/>';
            if($apply_shim) $this->EE->db = new DB_MasonData_Shim($this->EE->db);
            $result = $this->instance->save_cell($data);
            if($apply_shim) $this->EE->db->_remove_shim();
            return $result;
        } else {
            return $data;
        }
    }
    
    function post_save_cell($entry_id, $field_id, $field_name, $row_id, $col_id, $settings, $data, $apply_shim = FALSE)
    {
        $this->_settings($field_id, $field_name, $row_id, $col_id, $settings, $entry_id);
        
        $this->instance->settings = $this->settings;
        if(method_exists($this->instance, 'post_save_cell'))
        {
            #echo get_class($this->instance).'->post_save_cell()<br/>';
            if($apply_shim) $this->EE->db = new DB_MasonData_Shim($this->EE->db);
            $result = $this->instance->post_save_cell($data);
            if($apply_shim) $this->EE->db->_remove_shim();
            return $result;
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
            //show_error('Unable to display settings for cell type '.$this->name);
            return array(array('', ''));
        }
    }

    function save_cell_settings(&$settings)
    {
        if (method_exists($this->instance, 'save_cell_settings'))
        {
            $settings = $this->instance->save_cell_settings($settings);
        /*} else {
            show_error('Unable to save settings for cell type '.$this->name);*/
        }
    }
    
    function pre_process(&$entry_row, $field_id, $row_id, $col_id, $data, $params = array(), $tagdata = FALSE)
    {
        if (method_exists($this->instance, 'pre_process'))
        {
            $this->instance->row = &$entry_row;
            $this->instance->field_id = $field_id;
            $this->instance->row_id = $row_id;
            $this->instance->col_id = $col_id;
            return $this->instance->pre_process($data, $params, $tagdata);
        } else {
            return $data;
        }
    }
    
    function replace_tag(&$entry_row, $field_id, $row_id, $col_id, $data, $params = array(), $tagdata = FALSE)
    {
        return $this->replaceType('replace_tag', $entry_row, $field_id, $row_id, $col_id, $data, $params, $tagdata);
    }
    
    /**
     * Support method for additional replace tag methods
     * 
     * @param string $replaceMethod
     * @param $entry_row
     * @param $field_id
     * @param $row_id
     * @param $col_id
     * @param $data
     * @param array $params
     * @param bool|string $tagdata
     * @return bool|string
     */
    function replaceType($replaceMethod, &$entry_row, $field_id, $row_id, $col_id, $data, $params = array(), $tagdata = FALSE)
    {
        if (method_exists($this->instance, 'save_cell_settings'))
        {
            $this->instance->row = &$entry_row;
            $this->instance->field_id = $field_id;
            $this->instance->row_id = $row_id;
            $this->instance->col_id = $col_id;

            $tagdata = $this->instance->$replaceMethod($data, $params, $tagdata);
        }
        return $tagdata;
    }
}
