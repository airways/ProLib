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

if(!class_exists('Bm_prefs')) {
class Bm_prefs extends Bm_handle_mgr {
    /* ------------------------------------------------------------
     * Preferences manager interface 
     * Wraps bm_handle_mgr for this module's preference values.
     * 
     * Initialize this class in your main library with the table name
     * you wish to use for your module's preferences.
     * ------------------------------------------------------------ */

    var $default_prefs = array();
    
    function __construct($table = FALSE, $class = FALSE, $default_prefs = FALSE)
    {
        $singular = "preference";
        if(!$class)
        {
            $class = "BM_Preference";
        }
        
        if($default_prefs)
        {
            $this->default_prefs = $default_prefs;
        }
        
        parent::__construct($table, $singular, $class);
    }
    
    function new_preference($data)
    {
        return $this->new_object($data);
    }

    /**
     * Get a preference object by name.
     *
     * @param  $handle
     * @return Bm_preference
     */
    function get_preference($name)
    {
        if(is_numeric($name))
        {
            echo "<div>Error: get_preference() cannot be called with an ID - name param must not be numeric.</div>";
            return FALSE;
        }
        
        $result = $this->get_object($name);
        
        // if there is no result, check for a default preference value
        if(!$result)
        {
            if(array_key_exists($name, $this->default_prefs))
            {
                // create a new preference object for the default preference
                $row = array('preference_name' => $name, 'value' => $this->default_prefs[$name]);
                $result = new BM_Preference($row);
            }
        }
        return $result;
    }

    /**
     * Get a preference setting from the database, or return the default if the preference
     * is not found.
     * 
     * @param  $key
     * @param bool $default
     * @return mixed
     */
    function ini($key, $default = FALSE)
    {
        if(is_numeric($name))
        {
            echo "<div>Error: ini() cannot be called with an ID - key param must not be numeric.</div>";
            return FALSE;
        }
        
        $result = $this->get_preference($key);

        if($result) {
            $result = $result->value;
        } else {
            $result = $default;
        }

        return $result;
    }
    
    /**
     * Get a map of all preference values from the database, including default preference values
     * if they are not already set in the database table.
     * 
     * @return array(preference_name => value)
     */
    function get_preferences()
    { 
        // get a map of preference objects, collapse into a single key/value array
        $prefs = $this->get_objects(FALSE, 'name');
        foreach($prefs as $k => $v)
        {
            $prefs[$k] = $v->value;
        }
        
        // check for default values, if they are not set - add them to the results
        foreach($this->default_prefs as $k => $v)
        {
            if(!array_key_exists($k, $prefs))
            {
                $prefs[$k] = $this->default_prefs[$k];
            }
        }
        
        return $prefs;
    }
    
    function save_preference($object) {
        return $this->save_object($object);
    }
    
    function save_preferences($prefs)
    {
        // get existing preference objects, map new values
        $objects = $this->get_objects(FALSE, 'name');
        foreach($prefs as $k => $v)
        {
            $objects[$k] = $v;
        }
        
        // check for default values, if they are not set - add them to the objects to save
        foreach($this->default_prefs as $k => $v)
        {
            if(!array_key_exists($k, $objects))
            {
                $objects[$k] = new BM_Preference(array('preference_name' => $k, 'value' => $this->default_prefs[$k]));
            }
        }
        return $this->save_objects($objects);
    }
    
    function delete_preference($object) {
        return $this->delete_object($object);
    }
}} // class Bm_prefs

if(!class_exists('BM_Preference')) {
class BM_Preference extends BM_RowInitialized
{
    var $preference_id = FALSE;
    var $preference_name = FALSE;
    var $value = FALSE;

    function save()
    {
        $this->__mgr->save_object($this);
    }

}} // class Mason_preference
