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

class Bm_parser {
    function __construct()
    {
        $this->EE = &get_instance();
    }
    
    function parse_variables($rowdata, &$row_vars, $pairs, $backspace = 0)
    {
        // sadly we cannot use parse_variables_row because it only parses each tag_pair once! WTF!
        // we *have* to have multiple tag pairs support so that you can have fun stuff like a row
        // of headers for a table, as well as each row of data, powered by {fields}...{/fields}.

        // prep basic conditionals
        $rowdata = $this->EE->functions->prep_conditionals($rowdata, $row_vars);

        // parse single variables
        foreach ($this->EE->TMPL->var_single as $key => $val)
        {
            // prevent array to string errors
            if(array_search($key, $pairs) === FALSE)
            {
                if(array_key_exists($key, $row_vars))
                {
                    $rowdata = $this->EE->TMPL->swap_var_single($key, $row_vars[$key], $rowdata);
                }
            }
        }

        foreach($pairs as $var_pair)
        {
            foreach ($this->EE->TMPL->var_pair as $key => $val)
            {
                if(strpos($key, $var_pair) === 0)
                {
                    $count = preg_match_all($pattern = "/".LD.$key.RD."(.*?)".LD."\/".$var_pair.RD."/s", $rowdata, $matches);
                    // if we got some matches
                    if($count > 0)
                    {
                        // $matches[0] is an array of the full pattern matches
                        // $matches[1] is an array of the contents of the inside of each variable pair
                        for($i = 0; $i < count($matches[0]); $i++)
                        {
                            $pair_tag = &$matches[0][$i];
                            $pair_row_template = &$matches[1][$i];

                            $pair_data = '';
                            foreach($row_vars[$var_pair] as $data)
                            {
                                $pair_row_data = $pair_row_template;
                                $pair_row_data = $this->EE->functions->prep_conditionals($pair_row_data, $data);
                                foreach($data as $k => $v)
                                {
                                    // prevent array to string errors
                                    if(is_array($v)) {
                                        $pair_row_data = $this->parse_variables($pair_row_data, $data, array($k));
                                        /*echo $k;
                                        var_dump($v);
                                        die;*/
                                    } else {
                                        if(array_key_exists($k, $row_vars) === FALSE)
                                        {
                                            $pair_row_data  = $this->EE->TMPL->swap_var_single($k, $v, $pair_row_data);
                                        }
                                    }
                                }
                                $pair_data .= $pair_row_data;
                            }
                            ///echo $fields_data;

                            $rowdata = str_replace($pair_tag, $pair_data, $rowdata);
                        }
                    }

                }
            }
        }

        if($backspace)
        {
            $rowdata = substr($rowdata, 0, - $backspace);
        }
        
        return $rowdata;
    } // function parse_variables

    function parse_variables_legacy($rowdata, &$row_vars, $backspace = 0)
    {
        $rowdata = $this->EE->TMPL->parse_variables($rowdata, $row_vars);

        if($backspace)
        {
            $rowdata = substr($rowdata, 0, - $backspace);
        }

        return $rowdata;
    }
}
