<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * @package CELL CMS
 * @author Isaac Raway (MetaSushi, LLC) <isaac@metasushi.com>
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

class PL_Drivers {
    var $drivers = array();

    function __construct()
    {
        global $PROLIB;
        $this->EE = &get_instance();
        $this->prolib = &$PROLIB;
    }

    function init()
    {
        if(count($this->drivers)) return;

        // find installed drivers - the main library of a driver
        // always has the same name as the driver filename + _plg
        $drivers = array();
        $fieldtypes = array();

        $drivers_dirs = array(
            $this->prolib->package_path.'drivers/',
            $this->prolib->package_path.'../'.$this->prolib->package_name.'_drivers/'
        );

        $drivers = array();
        foreach($drivers_dirs as $dir)
        {
            $drivers = $drivers + $this->load_dir($dir);
        }

        // check each driver for a main class
        foreach($drivers as $driver => $info)
        {
            if(file_exists($info['file']) && isset($info['class']))
            {
                include $info['file'];
                $class = $info['class'];
                if(class_exists($class))
                {
                    $this->drivers[strtolower($driver)] = new $class;
                    $this->drivers[strtolower($driver)]->driver_file = $info['file'];
                    $this->drivers[strtolower($driver)]->init();
                } else {
                    show_error("Driver exists but class does not exist or does not match filename: ".$info['file']);
                }
            }

        }

    } // function __contruct()


    function load_dir($dir)
    {
        $drivers = array();

        if (file_exists($dir) && $dh = opendir($dir))
        {
            while (($file = readdir($dh)) !== false)
            {
                if($file[0] != '.')
                {
                    if(is_dir($dir.'/'.$file))
                    {
                        $drivers = $drivers + $this->load_dir($dir.'/'.$file);
                    } else {
                        if(substr($file, -11) == '_driver.php')
                        {
                            $drivers[$file] = array(
                                'file' => $dir.'/'.$file
                            );

                            $info = pathinfo($drivers[$file]['file']);
                            if(isset($info['extension']))
                            {
                                // remove the extension from the filename, then uppercase the first letter to get the class name
                                $class = ucwords(str_replace('.php', '', $info['basename']));
                                $drivers[$file]['class'] = $class;
                            }
                        }
                    }
                }
            }
        }

        return $drivers;
    }

    function driver_is_installed($driver)
    {
        return array_key_exists($driver, $this->drivers);
    }

    function get_drivers($type=false)
    {
        $result = array();

        foreach($this->drivers as $driver)
        {
            if($type === false || in_array($type, $driver->type))
            {
                $result[] = $driver;
            }
        }

        return $result;
    }
    
    function get_driver($key)
    {
        $result = FALSE;

        foreach($this->drivers as $driver)
        {
            if(isset($driver->meta['key']) && $driver->meta['key'] == $key)
            {
                $result = $driver;
                break;
            }
        }

        return $result;
    }
    
    function lang($key)
    {
        foreach($this->drivers as $driver)
        {
            if(isset($driver->lang) && isset($driver->lang[$key]))
            {
                return $driver->lang[$key];
            }
        }
        return lang($key);
    }

    // handle any call to a method (hook) on this class and try to send it to any drivers
    // that provide a definition for it
    function __call($method, $params)
    {
        return $this->call(FALSE, $method, $params);
    }
    
    function call($driver_key, $method, $params=array())
    {
        $CI = &get_instance();

        // get the default result as the last parameter sent to the hook
        $result = array_pop($params);

        // give core libraries a chance to handle the hook
        foreach($CI as $k => $lib)
        {
            if(substr($k, 0, 3) == 'ce_' && method_exists($lib, $method))
            {
                // add previous result (or FALSE if this is the first method that defines this
                // hook) to the params as the last param
                $params[] = $result;

                // call the hook method
                $result = call_user_func_array(array($lib, $method), $params);

                // remove the old result from the params array so we can add the new one on the
                // next loop
                array_pop($params);
            }
        }

        // now send the hook to installed drivers
        foreach($this->drivers as $driver)
        {
            // if we don't care what driver handles this method, or we found the right one, run the
            // hook
            if(!$driver_key || (isset($driver->meta['key']) && $driver->meta['key'] == $driver_key))
            {
                if(method_exists($driver, $method))
                {
                    // see comments above
                    $params[] = $result;
                    $result = call_user_func_array(array($driver, $method), $params);
                    array_pop($params);
                }
            }
        }

        // return the last (or only) result we got from a hook method
        return $result;
    }  // function __call



}



