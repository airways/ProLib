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
 
if(!function_exists('array_presort'))
{
    /**
     * Order an array by a predefined order
     * 
     * @param $array array of key => value pairs to sort in the defined order
     * @param $key_order array of strings defining the desired order of the array
     * @return PL_Preference or FALSE
     */
    function array_presort($array, $key_order)
    {
        $result = array();
        foreach($key_order as $key)
        {
            if(array_key_exists($key, $array))
            {
                $result[$key] = $array[$key];
            }
        }
        return $result;
    }
}