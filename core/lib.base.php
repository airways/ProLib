<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * @package Process
 * @author Isaac Raway <isaac.raway@gmail.com>
 * 
 * Copyright (c)2012. Isaac Raway and MetaSushi, LLC.
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
 
 
class PL_base_lib {
//     private static $get_instance = FALSE;
//     private static $instance = null;
    private $managers = FALSE;
    
    public function __construct()
    {
        // Throw an exception if the instance already exists, or if the constructor
        // is not being called inside of get_instance().
//         if(isset(self::$instance) || !self::$get_instance)
//         {
//             throw new Exception('Invalid direct call to new instance of '.__CLASS__.' - use '.__CLASS__.'::get_instance() instead of creating an instance directly.');
//         }
    }
    
//     public static function get_instance()
//     {
//         if(!isset(self::$instance))
//         {
//             self::$get_instance = TRUE;         // Prevent the exception from being thrown
//             self::$instance = new self();       // Create the single instance
//             self::$get_instance = FALSE;        // Turn back on exception throwing
//         }
//         return self::$instance;             // Return the instance
//     }
    
    public function get_managers()
    {
        if(!$this->managers)
        {
            $this->managers = array();
            foreach($this as $k => $v)
            {
                if($v instanceof PL_handle_mgr)
                {
                    $this->managers[$k] = $v;
                }
            }
        }
        return $this->managers;
    }
}
