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

    function get_object($handle, $show_error = TRUE)
    {
        $template = FALSE;
        $object = FALSE;

        if(is_numeric($handle))
        {
            $query = $this->EE->db->select('*')
                                  ->where($this->singular . '_id', $handle);
        }
        else
        {
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
            $class = $this->class;
            // echo '<b>____ new '.$class.'</b> '.$handle.'<br/>';
            $object = new $class($query->row());
            // echo get_class($object).'<br/>';
            // var_dump($query->row());


            $object_id = $object->{$this->singular . '_id'};

            foreach($this->serialized as $field) {
                if(isset($object->$field) and $object->$field)
                {
                    $object->$field = unserialize($object->$field);
                //} else {
                //    $object->$field = array();
                }
            }

            //$query = $this->EE->db->get_where($this->table, array($this->singular . '_id' => $object_id));
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
            // if(!is_object($object) OR get_class($object) == 'stdClass')
            // {
            //     echo 'Invalid object for'.$handle.':';
            //     var_dump($object);
            //     exit;
            // }
            // var_dump($object);
            if(is_object($object))
            {
                $object->__mgr = $this;
            }
        }

        if(method_exists($object, 'post_get'))
        {
            $object->post_get($this);
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

        $this->EE->db->select("{$this->singular}_id");

        if($where && is_array($where))
        {
            $this->EE->db->where($where);
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

                $obj = $this->get_object($row->{$this->singular . '_id'});

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
