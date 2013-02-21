<?php

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
 
class PL_base_driver {
    var $driver_file;
    var $lang = array();
    var $type = array();
    var $meta = array();
    
    public function init()
    {
        $this->EE = &get_instance();
        $info = pathinfo($this->driver_file);
        $this->view_path = $info['dirname'].'/views/';
    }

    public function view($view, $vars)
    {
        if(!file_exists($this->view_path.$view.'.php'))
        {
            return "Driver view file does not exist: ".$view.".php";
        }
        
        extract($vars);
        ob_start();
        include $this->view_path.$view.'.php';
        $result = ob_get_contents();
        ob_end_clean();
        return $result;
    }
    
    public function form_cp_action_fields($type='fields', $ignore_fields=array())
    {
        $result = '';
        
        // Preserve GET parameters so that the forms reload the actual action page they
        // are presented on properly.
        foreach($_GET as $k => $v)
        {
            if(!in_array($k, $ignore_fields))
            {
                if($type == 'fields')
                {
                    $result .= form_hidden($k, $v);
                } else {
                    $result .= AMP.$k.'='.urlencode($v);
                }
            }
        }
        return $result;
    }
    
    public function lang($key)
    {
        // First see if there is a real lang entry for the key, if so return it.
        // This allows users and translators to override the built-in entries very
        // easily by simply providing a lang file.
        if(lang($key) != $key)
        {
            return lang($key);
        } else {
            if(isset($this->lang[$key]))
            {
                return $this->lang[$key];
            } else {
                return $key;
            }
        }
    }
    
    public function call($method, $params)
    {
        if(method_exists($this, $method))
        {
            return call_user_func_array($method, $params);
        }
        return FALSE;
    }
    
    public function __call($method, $params)
    {
        if(method_exists($this, $method))
        {
            return call_user_func_array($method, $params);
        }
        return FALSE;
    }

}
