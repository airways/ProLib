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

if(!class_exists('Bm_prefs')) {
class Bm_prefs extends Bm_handle_mgr {
    /* ------------------------------------------------------------
     * Preferences manager interface 
     * Wraps bm_handle_mgr for this module's preference values.
     * 
     * Initialize this class in your main library with the table name
     * you wish to use for your module's preferences.
     * ------------------------------------------------------------ */

    function __construct($table = FALSE, $class = FALSE) {
        $singular = "preference";
        if(!$class)
        {
            $class = "BM_Preference";
        }
        
        parent::__construct($table, $singular, $class);
    }
    
    function new_preference($data) { return $this->prefs_mgr->new_object($data); }

    /**
     * @param  $handle
     * @return Bm_preference
     */
    function get_preference($handle) { return $this->prefs_mgr->get_object($handle); }

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
        $result = $this->get_preference($key);

        if($result) {
            $result = $result->value;
        } else {
            $result = $default;
        }

        return $result;
    }
    function get_preferences()
    { 
		// get a map of preferences, collapse into a single key/value array
		$prefs = $this->prefs_mgr->get_objects(FALSE, 'name');
		foreach($prefs as $k => $v)
		{
			$prefs[$k] = $v->value;
		}
		return $prefs;
	}
    function save_preference($object)  { return $this->prefs_mgr->save_object($object); }
    function save_preferences($prefs)
    {
		// get existing preference objects, map new values
		$objects = $this->prefs_mgr->get_objects(FALSE, 'name');
		foreach($prefs as $k => $v)
		{
			$objects[$k] = $v;
		}
		return $this->prefs_mgr->save_objects($objects);
	}
    function delete_preference($object)  { return $this->prefs_mgr->delete_object($object); }
}} // class Bm_prefs

if(!class_exists('BM_Preference')) {
class BM_Preference extends BM_RowInitialized
{
    var $preference_id = FALSE;
    var $preference_name = FALSE;
    var $value = FALSE;

    function save()
    {
        $this->__mgr->save_preference($this);
    }

}} // class Mason_preference
