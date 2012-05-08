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

class PL_Plugins {
    var $plugins = array();

    function __construct()
    {
        global $PROLIB;
        $this->EE = &get_instance();
        $this->prolib = &$PROLIB;
    }

    function init()
    {
        if(count($this->plugins)) return;
        
        // find installed plugins - the main library of a plugin
        // always has the same name as the plugin filename + _plg
        $plugins = array();
        $fieldtypes = array();

        $plugins_dirs = array(
            $this->prolib->package_path.'plugins/',
            $this->prolib->package_path.'../'.$this->prolib->package_name.'_plugins/'
        );

        $plugins = array();
        foreach($plugins_dirs as $dir)
        {
            $plugins = $plugins + $this->load_dir($dir);
        }
        
        // check each plugin for a main class
        foreach($plugins as $plugin => $info)
        {
            if(file_exists($info['file']) && isset($info['class']))
            {
                include $info['file'];
                $class = $info['class'];
                if(class_exists($class))
                {
                    $this->plugins[strtolower($plugin)] = new $class;
                    $this->plugins[strtolower($plugin)]->plugin_file = $info['file'];
                    $this->plugins[strtolower($plugin)]->init();
                } else {
                    show_error("Plugin exists but class does not exist or does not match filename: ".$info['file']);
                }
            }

        }

    } // function __contruct()


    function load_dir($dir)
    {
        $plugins = array();
        
        if (file_exists($dir) && $dh = opendir($dir))
        {
            while (($file = readdir($dh)) !== false)
            {
                if($file[0] != '.')
                {
                    if(is_dir($dir.'/'.$file))
                    {
                        $plugins = $plugins + $this->load_dir($dir.'/'.$file);
                    } else {
                        if(substr($file, -8) == '_plg.php')
                        {
                            $plugins[$file] = array(
                                'file' => $dir.'/'.$file
                            );

                            $info = pathinfo($plugins[$file]['file']);
                            if(isset($info['extension']))
                            {
                                // remove the extension from the filename, then uppercase the first letter to get the class name
                                $class = ucwords(str_replace('.php', '', $info['basename']));
                                $plugins[$file]['class'] = $class;
                            }
                        }
                    }
                }
            }
        }
        
        return $plugins;
    }

    function plugin_is_installed($plugin)
    {
        return array_key_exists($plugin, $this->plugins);
    }

    // handle any call to a method (hook) on this class and try to send it to any plugins
    // that provide a definition for it
    function __call($method, $params)
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

        // now send the hook to installed plugins
        foreach($this->plugins as $plugin)
        {
            if(method_exists($plugin, $method))
            {
                // see comments above
                $params[] = $result;
                $result = call_user_func_array(array($plugin, $method), $params);
                array_pop($params);
            }
        }

        // return the last (or only) result we got from a hook method
        return $result;
    }  // function __class
}



