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

if(!class_exists('PL_prefs')) {
class PL_prefs extends PL_handle_mgr {
    /* ------------------------------------------------------------
     * Preferences manager interface
     * Wraps PL_handle_mgr for this module's preference values.
     *
     * Initialize this class in your main library with the table name
     * you wish to use for your module's preferences.
     * ------------------------------------------------------------ */

    var $default_prefs = array();
    
    function __construct($table = FALSE, $class = FALSE, $default_prefs = FALSE, $site_id = FALSE)
    {
        global $PROLIB;
        $this->prolib = &$PROLIB;
        
        $singular = "preference";
        if(!$class)
        {
            $class = "PL_Preference";
        }

        if($default_prefs)
        {
            $this->default_prefs = $default_prefs;
        }
        
        if($site_id)
        {
            $this->site_id = $site_id;
        }

        parent::__construct($table, $singular, $class);
    }

    function new_preference($data)
    {
        return $this->new_object($data);
    }

    private function decode_value($name, $value) {
        $result = NULL;

        if(isset($this->default_prefs[$name]) && is_object($this->default_prefs[$name])) $result = json_decode($value);
        else if(isset($this->default_prefs[$name]) && is_array($this->default_prefs[$name])) {
            foreach(json_decode($value) as $k => $v) {
                if(is_numeric($k)) $k = (int)$k;
                $result[$k] = $v;
            }
        } else {
            $result = $value;
        }
        
        return $result;
    }

    /**
     * Get a preference object by name.
     *
     * @param  $handle
     * @return PL_preference
     */
    function get_preference($name)
    {
        if(is_numeric($name))
        {
            echo "<div>Error: get_preference() cannot be called with an ID - name param must not be numeric.</div>";
            return FALSE;
        }

        $result = $this->get_object($name, FALSE);

        if($result)
        {
            $result->value = $this->decode_value($name, $result->value);
        } else {
            // if there is no result, check for a default preference value
            if(array_key_exists($name, $this->default_prefs))
            {
                // create a new preference object for the default preference
                $row = array('preference_name' => $name, 'value' => $this->default_prefs[$name]);
                $result = new PL_Preference($row);
                $result->__mgr = $this;
            }
        }
        return $result;
    }

    /**
     * Get a preference setting from the database, or return the default if the preference
     * is not found. Alias for get().
     *
     * @param  $key
     * @param bool $default
     * @return mixed
     */
    function ini($key, $default = FALSE)
    {
        return $this->get($key, $default);
    }

    /**
     * Get a preference setting from the database, or return the default if the preference
     * is not found.
     *
     * @param $key
     * @param $default
     * @return mixed
     */
    function get($key, $default = FALSE)
    {
        if(is_numeric($key))
        {
            echo "<div>Error: ini() or get() cannot be called with an ID - key param must not be numeric.</div>";
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
     * Set a preference to a given value
     *
     * @param $key
     * @param $value
     * @return PL_Preference or FALSE
     */
    function set($key, $value = FALSE)
    {
        $pref = $this->get_preference($key);
        
        if(is_object($value) || is_array($value)) $value = json_encode($value);

        if($pref)
        {
            $pref->value = $value;
            // echo '<b>'.$key.'</b><br/>';
            // var_dump($pref);
            $pref->__mgr = $this;
            if(!$pref->save())
            {
                // return FALSE to indicate error
                $pref = FALSE;
            }
        } else {
            $data = array('preference_name' => $key, 'value' => $value);
            // new_preference returns the new PL_Preference object or FALSE on failure
            $pref = $this->new_preference($data);
        }

        // return PL_Preference object or FALSE
        return $pref;
    }
    
    /**
     * Delete a preference setting from the database.
     *
     * @param $key
     */
    function del($key)
    {
        if(is_numeric($key))
        {
            echo "<div>Error: del() cannot be called with an ID - key param must not be numeric.</div>";
            return FALSE;
        }
        $result = $this->get_preference($key);
        if($result) {
            $result = $result->delete();
        }
    }


    /**
     * Get a map of all preference values from the database, including default preference values
     * if they are not already set in the database table.
     *
     * @return array(preference_name => value)
     */
    function get_preferences()
    {
        $prefs = array();
        
        // get a map of preference objects, collapse into a single key/value array
        foreach($this->get_objects(FALSE, 'name') as $i => $pref)
        {
            $prefs[$pref->preference_name] = $pref->value;
        }

        // check for default values, if they are not set - add them to the results
        foreach($this->default_prefs as $k => $v)
        {
            if(!array_key_exists($k, $prefs))
            {
                $prefs[$k] = $this->default_prefs[$k];
            } else {
                $prefs[$k] = $this->decode_value($k, $prefs[$k]);
            }
        }

        if(count($this->default_prefs) > 0)
        {
            $prefs = array_presort($prefs, array_keys($this->default_prefs));
        }

        return $prefs;
    }

    function save_preference($object) {
        $val = NULL;
        if(is_object($object->value) || is_array($object->value)) {
            $val = $object->value;
            $object->value = json_encode($object->value);
        }
        $result = $this->save_object($object);
        if(!is_null($val)) {
            $object->value = $val;
        }
        return $result;
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
                $value = $this->default_prefs[$k];
                if(is_object($value) || is_array($value)) $value = json_encode($value);
                $objects[$k] = new PL_Preference(array('preference_name' => $k, 'value' => $value));
                $objects[$k]->__mgr = $this;
            }
        }
        
        return $this->save_objects($objects);
    }

    function delete_preference($object) {
        return $this->delete_object($object);
    }
    
    /**
     * Used by the Prolib_base_mcp::preferences() action to generate a prefs form. Any prefs
     * not given control markup by this function will get input fields automatically.
     */
    function prefs_form($prefs) {
        return array();
    }
}} // class PL_prefs

if(!class_exists('PL_Preference')) {
class PL_Preference extends PL_RowInitialized
{
    var $preference_id = FALSE;
    var $preference_name = FALSE;
    var $value = FALSE;

    function save()
    {
        $this->__mgr->save_object($this);
    }

}} // class Mason_preference
