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

if(!class_exists('Bm_handle_mgr')) {
class Bm_handle_mgr 
{
    var $table = "";
    var $singular = "";
    var $class = "";
    var $serialized = array('settings');
    
    function __construct($table = FALSE, $singular = FALSE, $class = FALSE, $serialized = FALSE)
    {
        $this->EE = &get_instance();
        $this->EE->db->cache_off();
        
        if($table) $this->table = $table;
        if($singular) $this->singular = $singular;
        if($class) $this->class = $class;
        if($serialized) $this->serialized = $serialized;
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

        $this->EE->db->insert($this->table, $data);
        $insert_id = $this->EE->db->insert_id();

        $object = $this->get_object($insert_id);
        if(method_exists($object, 'init'))
        {
            $object->init($this);
        }
        
        return $object;
    }
    
    function get_object($handle, $show_error = TRUE)
    {
        $template = FALSE;
        $object = FALSE;
        
        if(is_numeric($handle)) 
        {
            $query = $this->EE->db->select('*')
                                  ->where($this->singular . '_id', $handle)
                                  ->get($this->table);
        } 
        else
        {
            $query = $this->EE->db->select('*')
                                  ->where($this->singular . '_name', $handle)
                                  ->get($this->table);
        }
        
        if($query->num_rows > 0) 
        {
            $class = $this->class;
            $object = new $class($query->row());
            
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
            exit('Object not found: ' . $this->class . ' #' . $handle);
        } else {
            $object->__mgr = $this;
        }
        
        
        return $object;
    }
    
    function get_objects($where = FALSE, $array_type = FALSE, $order = FALSE)
    {
        $result = array();
        
        $this->EE->db->select("{$this->singular}_id");
        
        if($where && is_array($where))
        {
            $this->EE->db->where($where);
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
        
        $query = $this->EE->db->get($this->table);

        if($query->num_rows > 0)
        {
            foreach($query->result() as $row) 
            {
                $obj = $this->get_object($row->{$this->singular . '_id'});
                
                switch($array_type)
                {
                    case 'handle':
                        $result[$row->{$this->singular . '_id'}] = $obj;
                        break;
                    case 'name':
                        $result[$row->{$this->singular . '_name'}] = $obj;
                        break;
                    default:
                        $result[] = $obj;
                }
            }
        }
        return $result;
    }
    
    function save_object($object, $where = false) 
    {
        foreach($this->serialized as $field) {
            if(isset($object->$field)) {
                $object->$field = serialize($object->$field);
            }
        }
        
        $o = $this->remove_transitory($object);

        // $this->EE->db->where($this->singular . '_id', $object->{$this->singular . '_id'});
        //  if($where) $this->EE->db->where($where);
        //  $query = $this->EE->db->update($this->table, $o);
        
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
    
    function delete_object($object) 
    {
        return $query = $this->EE->db->where($this->singular . '_id', $object->{$this->singular . '_id'})
                                     ->delete($this->table);
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


    function remove_transitory($object)
    {
        $o = array();
        foreach($object as $field => $value) {
            if(strpos($field, '__') !== 0) {
                if(!is_object($value))
                    $o[$field] = $value;
            }
        }
        return $o;
    }
    
}} // class Bm_handle_mgr

if(!class_exists('BM_RowInitialized')) {
class BM_RowInitialized 
{
    var $__mgr = NULL;
    
    function __construct(&$row, &$mgr=NULL)
    {
        $this->__EE = &get_instance();
        $this->__mgr = &$mgr;
        if($row)
        {
            foreach($row as $key => $value) 
            {
                $this->$key = $value;
            }
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
}} // class BM_RowInitialized
