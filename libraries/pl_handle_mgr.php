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

if(!class_exists('PL_handle_mgr')) {
class PL_handle_mgr
{
    var $table = "";
    var $singular = "";
    var $class = "";
    var $serialized = array('settings');
    var $edit_hidden = array();

    var $plural = "";
    var $plural_label = "";
    var $children = array();                    // Names of child managers
    var $lib = null;
    var $prolib = null;
    var $site_id = FALSE;
    
    var $object_cache_enabled = false;
    var $object_cache_prefetch = false;
    var $object_cache = array();                // Cache of objects with numeric object ID keys mapped to objects
    var $object_names = array();                // Cache of object names with object name keys mapped to object IDs
    var $cached_all = false;
    
    function __construct($table = FALSE, $singular = FALSE, $class = FALSE, $serialized = FALSE, &$lib = NULL, $site_id = FALSE)
    {
        global $PROLIB;
        $this->prolib = &$PROLIB;
        $this->EE = &get_instance();
        $this->EE->db->cache_off();
        

        if($table) $this->table = $table;
        if($singular) $this->singular = $singular;
        if($class) $this->class = $class;
        if($serialized) $this->serialized = $serialized;
        if($lib) $this->lib = &$lib;
        if($site_id) $this->site_id = $site_id;
        
        if($this->object_cache_enabled && $this->object_cache_prefetch)
        {
            if(is_array($this->object_cache_prefetch))
            {
                $this->get_objects($this->object_cache_prefetch);
            } else {
                $this->get_all();
            }
        }
    }

    function count()
    {
        if($this->site_id)
        {
            $result = $this->EE->db->where('site_id', $this->site_id)->count_all_results($this->table);
        } else {
            $result = $this->EE->db->count_all($this->table);
        }
        return $result;
    }

    function create($data)
    {
        return $this->new_object($data);
    }

    function new_object($data)
    {
        // Create new table for the form
        $this->EE->load->dbforge();
        $forge = &$this->EE->dbforge;

        foreach($this->serialized as $field) {
            if(isset($data[$field])) {
                $data[$field] = serialize($data[$field]);
            }
        }

        foreach($data as $k => $v)
        {
            if(is_array($v) OR $v == 'Array')
            {
                xdebug_print_function_stack('Attempting to save array as field value: '.$k);
            }
        }
        
        if(!isset($data['site_id']))
        {
            $data['site_id'] = $this->site_id;
        }

        $this->EE->db->insert($this->table, $data);
        $insert_id = $this->EE->db->insert_id();

        $object = $this->get_object($insert_id);
        if(method_exists($object, 'init'))
        {
            $object->init($this);
        }

        return $object;
    }

    function get($handle, $show_error = TRUE)
    {
        return $this->get_object($handle, $show_error);
    }
    
    function _load_object($row)
    {
        $class = $this->class;
        $object = new $class($row);
        $object_id = $object->{$this->singular . '_id'};
        foreach($this->serialized as $field) {
            if(isset($object->$field) and $object->$field)
            {
                $object->$field = unserialize($object->$field);
            }
        }
        $object->__mgr = $this;
        return $object;
    }
    
    function get_object($handle, $show_error = TRUE)
    {
        
        
        $template = FALSE;
        $object = FALSE;

        if(is_numeric($handle))
        {
            if($this->object_cache_enabled && isset($this->object_cache[$handle]))
            {
                return $this->object_cache[$handle];
            }
            
            $query = $this->EE->db->select('*')
                                  ->where($this->singular . '_id', $handle);
        }
        else
        {
            if($this->object_cache_enabled && isset($this->object_names[$handle]) && isset($this->object_cache[$this->object_names[$handle]]))
            {
                return $this->object_cache[$this->object_names[$handle]];
            }
            
            $query = $this->EE->db->select('*')
                                  ->where($this->singular . '_name', $handle);
        }

        if($this->site_id)
        {
            $this->EE->db->where('site_id', $this->site_id);
        }

        $query = $this->EE->db->get($this->table);

        if($query->num_rows > 0)
        {
            $object = $this->_load_object($query->row());
        }

        if(!$object && $show_error) {
            if(function_exists('xdebug_print_function_stack'))
            {
                xdebug_print_function_stack('Object not found: ' . $this->class . ' #' . $handle);
                exit;
            } else {
                exit('<b>Object not found: ' . $this->class . ' #' . $handle . '</b>');
            }
        } else {
            if(is_object($object))
            {
                $object->__mgr = $this;
            }
        }

        if(method_exists($object, 'post_get'))
        {
            $object->post_get($this);
        }

        if($this->object_cache_enabled)
        {
            if(is_object($object))
            {
                $this->object_cache[$object->{$this->singular . '_id'}] = $object;
                $this->object_names[$object->{$this->singular . '_name'}] = $object->{$this->singular . '_id'};
            }
        }
        
        return $object;
    }

    function get_all($where = FALSE, $array_type = FALSE, $order = FALSE, $offset = FALSE, $perpage = FALSE)
    {
        return $this->get_objects($where, $array_type, $order, $offset, $perpage);
    }

    function get_objects($where = FALSE, $array_type = FALSE, $order = FALSE, $offset = FALSE, $perpage = FALSE)
    {
        $result = array();
        
        if($this->cached_all)
        {
            if($array_type == 'handle')
            {
                return $this->object_cache;
            } else {
                foreach($this->object_cache as $obj)
                {
                    $result[$obj->{$this->singular . '_name'}] = $obj;
                }
                return $result;
            }
        }
        
        $this->EE->db->select('*');

        if($where && is_array($where))
        {
            $this->EE->db->where($where);
        } else {
            if($this->object_cache_enabled)
            {
                $this->cached_all = true;
            }
        }
        
        if($this->site_id)
        {
            $this->EE->db->where('site_id', $this->site_id);
        }

        if(!$order && property_exists($this->class, 'order_no'))
        {
            $order = 'order_no ASC';
        }
        
        if($order)
        {
            if(!is_array($order)) $order = array($order);

            foreach($order as $field)
            {
                if(is_array($field))
                {
                    $this->EE->db->order_by($field[0], $field[1]);
                } else {
                    $this->EE->db->order_by($field);
                }
            }

        }

        if($offset || $perpage)
        {
            $this->EE->db->limit($perpage, $offset);
        }


        $query = $this->EE->db->get($this->table);

        if($query->num_rows > 0)
        {
            foreach($query->result() as $row)
            {
                //$obj = $this->get_object($row->{$this->singular . '_id'});
                $obj = $this->_load_object($row);
                
                switch($array_type)
                {
                    case 'handle':
                        $result[$obj->{$this->singular . '_id'}] = $obj;
                        break;
                    case 'name':
                        $result[$obj->{$this->singular . '_name'}] = $obj;
                        break;
                    default:
                        $result[] = $obj;
                }
                
                if($this->object_cache_enabled)
                {
                    $this->object_cache[$obj->{$this->singular . '_id'}] = $obj;
                    $this->object_names[$obj->{$this->singular . '_name'}] = $obj->{$this->singular . '_id'};
                }
                
            }
        }
        return $result;
    }

    function save($object, $where = false)
    {
        return $this->save_object($object, $where);
    }

    function save_object($object, $where = false)
    {
        foreach($this->serialized as $field) {
            if(isset($object->$field)) {
                $object->$field = serialize($object->$field);
            }
        }

        $o = $this->remove_transitory($object);
        
        if((!isset($o['site_id']) || $o['site_id'] == 0) && $this->site_id != 0)
        {
            $o['site_id'] = $this->site_id;
        }

        if(method_exists($object, 'pre_save'))
        {
            $object->pre_save($this, $o);
        }

        // First see if what we are about to update
        $this->EE->db->where($this->singular . '_id', $object->{$this->singular . '_id'});
        if($where) $this->EE->db->where($where);
        $query = $this->EE->db->get($this->table);

        // echo '<pre>';
        // var_dump($query->result_array());
        // var_dump($where);
        // die;

        //No records were found, so insert
        if($query->num_rows() == 0)
        {
            unset($o[$this->singular . '_id']);
            $this->EE->db->insert($this->table, $o);
        }
        // Update existing record
        elseif($query->num_rows() == 1)
        {
            $this->EE->db->where($this->singular . '_id', $object->{$this->singular . '_id'});

            if($where) $this->EE->db->where($where);

            $this->EE->db->update($this->table, $o);
        }

        foreach($this->serialized as $field) {
            if(isset($object->$field)) {
                $object->$field = unserialize($object->$field);
            }
        }

        if(method_exists($object, 'post_save'))
        {
            $object->post_save($this, $o);
        }

        return $object;
    }

    function save_objects($objects)
    {
        $result = TRUE;
        foreach($objects as $object)
        {
            $result = $this->save_object($object) AND $result;
        }
        return $result;
    }

    function delete($object)
    {
        return $this->delete_object($object);
    }

    function delete_object($object)
    {
        $result = FALSE;
        $abort = FALSE;
        if(method_exists($object, 'pre_delete'))
        {
            $abort = $object->pre_delete($this);
        }

        if(!$abort && $object->{$this->singular . '_id'} != 0)
        {
            $result = $query = $this->EE->db->where($this->singular . '_id', $object->{$this->singular . '_id'})
                                            ->delete($this->table);
        }

        if(method_exists($object, 'post_delete'))
        {
            $object->post_delete($this);
        }

        return $result;
    }

    function delete_objects($where)
    {
        if($where && is_array($where))
        {
            return $query = $this->EE->db->where($where)
                                         ->delete($this->table);
        } else {
            exit('delete_objects cannot be run without a where array.');
        }
    }

    function remove_transitory(&$object)
    {
        $data_array = array();
        foreach($object as $field => $value) {
            if(strpos($field, '__') !== 0 AND $field != 'EE') {
                if(!is_object($value))
                    $data_array[$field] = $value;
            }
        }

        if(method_exists($object, 'post_remove_transitory'))
        {
            $data_array = $object->post_remove_transitory($data_array);
        }

        return $data_array;
    }

}} // class PL_handle_mgr

if(!class_exists('PL_RowInitialized')) {
class PL_RowInitialized
{
    var $__mgr = NULL;

    function __construct($row, &$mgr=NULL)
    {
        global $PROLIB;
        $this->EE = &get_instance();
        $this->__EE = &get_instance();
        $this->__CI = &get_instance();
        $this->__prolib = &$PROLIB;
        $this->__mgr = &$mgr;
        if($row)
        {
            foreach($row as $key => $value)
            {
                $this->$key = $value;
            }
        }
    }

    function save()
    {
        $this->__mgr->save($this);
    }


    function delete()
    {
        $this->__mgr->delete($this);
    }

    function data_array()
    {
        return $this->__mgr->remove_transitory($this);
    }

    function get_obj_id()
    {
        return $this->{$this->__mgr->singular.'_id'};
    }

    function get_obj_name()
    {
        return isset($this->{$this->__mgr->singular.'_name'}) 
            ? $this->{$this->__mgr->singular.'_name'} 
                : (isset($this->name)
                    ? $this->name
                        : ($this->__mgr->singular . ' #' . $this->get_obj_id()));
    }

    function get($key)
    {
        if(isset($this->__mgr->dynamic) && in_array($key, $this->__mgr->dynamic))
        {
            return $this->$key();
        } else {
            return $this->$key;
        }
    }

    function dump()
    {
        echo "<b>" . get_class($this)  . "</b><br/>";
        foreach($this as $key => $value)
        {
            echo '&nbsp;&nbsp;-&nbsp;&nbsp;'.$key.'='.(is_object($value) ? 'OBJECT: ' . get_class($value) : $value).'<br/>';
        }
        echo "<br/>";
    }
    
}} // class PL_RowInitialized
