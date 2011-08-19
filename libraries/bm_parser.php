<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * @package ProLib
 * @author Isaac Raway <isaac.raway@gmail.com>
 *
 * Copyright (c)2009, 2010, 2011. Isaac Raway and MetaSushi, LLC. All rights reserved.
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
    var $dst_enabled = FALSE;
    
    function __construct()
    {
        $this->EE = &get_instance();
    }
    
    function parse_variables($rowdata, &$row_vars, $pairs, $backspace = 0, 
        $options = array('dst_enabled' => FALSE))
    {
        // sadly we cannot use parse_variables_row because it only parses each tag_pair once! WTF!
        // we *have* to have multiple tag pairs support so that you can have fun stuff like a row
        // of headers for a table, as well as each row of data, powered by {fields}...{/fields}.

        // set options as given
        foreach($options as $key => $val)
        {
            $this->$key = $val;
        }
        
        if(is_object($row_vars))
        {
            $row_vars = (array)$row_vars;
        }
        
        // prep basic conditionals
        $rowdata = $this->EE->functions->prep_conditionals($rowdata, $row_vars);

        $custom_date_fields = array();
        $this->date_vars_params = array();
        

        foreach($row_vars as $key => $val)
        {
            //if (strpos($rowdata, LD.$key) !== FALSE)
            //{
                if (preg_match_all("/".LD.$key."\s+format=[\"'](.*?)[\"']".RD."/s", $rowdata, $matches))
                {
                    for ($j = 0; $j < count($matches[0]); $j++)
                    {
                        $matches[0][$j] = str_replace(array(LD,RD), '', $matches[0][$j]);
                        $this->date_vars_params[$matches[0][$j]] = $this->EE->localize->fetch_date_params($matches[1][$j]);
                        $this->date_vars_vals[$matches[0][$j]] = $matches[1][$j];
                    }
                }
            //}
        }
        
        //var_dump($date_vars);
        //var_dump($this->date_vars_params);
        
        // parse single variables
        foreach ($this->EE->TMPL->var_single as $key => $val)
        {
            // prevent array to string errors
            if(array_search($key, $pairs) === FALSE)
            {
                $var = explode(' ', $key);
                $var = $var[0];
                if(array_key_exists($var, $row_vars))
                {
                    if(is_callable($row_vars[$var]))
                    {
                        $rowdata = $this->_swap_var_single($key, $row_vars[$var]($row_vars), $rowdata);
                    } else {
                        $rowdata = $this->_swap_var_single($key, $row_vars[$var], $rowdata);
                    }
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
                            foreach($row_vars[$var_pair] as $i => $data)
                            {
                                $pair_row_data = $pair_row_template;

                                if(!is_array($data))
                                {
                                    if(is_callable($data))
                                    {
                                        $data = $data($row_vars[$var_pair], $i);
                                    }
                                    
                                    $pair_row_data = $this->EE->functions->prep_conditionals($pair_row_data, array('row' => $data));
                                    
                                    $pair_row_data  = $this->EE->TMPL->swap_var_single('row', $data, $pair_row_data);
                                } else {
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
                                                if(is_callable($v))
                                                {
                                                    $pair_row_data  = $this->_swap_var_single($k, $v($data, $k), $pair_row_data);
                                                } else {
                                                    $pair_row_data  = $this->_swap_var_single($k, $v, $pair_row_data);
                                                }
                                            }
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

    function _swap_var_single($key, &$val, &$data)
    {
        //  parse custom date fields
        if(isset($this->date_vars_params[$key]))
        {
            // use a temporary variable in case the custom date variable is used
            // multiple times with different formats; prevents localization from
            // occurring multiple times on the same value
            $temp_val = $val;

            // TODO: localize values
            $localize = TRUE;
            /*
            //if (isset($row['field_dt_'.$dval]) AND $row['field_dt_'.$dval] != '')
            //{
            //    $localize = TRUE;
            //    if ($row['field_dt_'.$dval] != '')
            //    {
                if($this->dst_enabled)
                {
                    $temp_val = $this->EE->localize->simpl_offset($temp_val, $row['field_dt_'.$dval]);
                    $localize = FALSE;
                }
            //}
            */
            
            // convert to a timestamp
            $time = strtotime($temp_val);
            //var_dump($time, date('h', $time));
            
            $val = str_replace($this->date_vars_params[$key], $this->EE->localize->convert_timestamp($this->date_vars_params[$key], $time, $localize, FALSE), $this->date_vars_vals[$key]);
        }
        
        return $this->EE->TMPL->swap_var_single($key, $val, $data);
    }
    
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
