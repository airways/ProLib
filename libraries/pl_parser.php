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

class PL_parser {
    function __construct()
    {
        $this->EE = &get_instance();
    }

    function fetch_param_group($group_name, $default=array())
    {
        $result = $default;
        foreach($this->EE->TMPL->tagparams as $param => $val)
        {
            if(strtolower(substr($param, 0, strlen($group_name)+1)) == $group_name.':')
            {
                $param = substr($param, strlen($group_name)+1);
                $result[$param] = $val;
            }
        }
        return $result;
    }

    function parse_variables($rowdata, &$row_vars, $pairs, $backspace = 0,
        $options = array(), $reparse_vars = array())
    {
        // sadly we cannot use parse_variables_row because it only parses each tag_pair once! WTF!
        // we *have* to have multiple tag pairs support so that you can have fun stuff like a row
        // of headers for a table, as well as each row of data, powered by {fields}...{/fields}.

        return $this->parse_variables_ex(array(
            'rowdata'       => $rowdata,
            'row_vars'      => &$row_vars,
            'pairs'         => $pairs,
            'backspace'     => $backspace,
            'options'       => $options,
            'reparse_vars'  => $reparse_vars,
        ));
    }

    function parse_variables_ex($params=array())
    {
        // Setup default parameters
        $param_defaults = array(
            'rowdata'           => '',
            'row_vars'          => '',
            'pairs'             => array(),
            'backspace'         => 0,
            'options'           => array(),
            'reparse_vars'      => array(),
            'dst_enabled'       => FALSE,
            'variable_prefix'   => '',
        );

        // Check for invalid parameters
        foreach($params as $k => $v)
        {
            if(!array_key_exists($k, $param_defaults))
            {
                exit('Invalid parameter provided to parse_variables_ex: '.$k);
            }
        }

        // Load parameters from combined defaults and provided values
        $params = array_merge($param_defaults, $params);

        foreach($params as $k => $v)
        {
            $this->$k = $v;
        }

        // Load into local variables
        extract($params);

        if(is_object($row_vars))
        {
            $row_vars = (array)$row_vars;
        }

        // prep basic conditionals
        $rowdata = $this->EE->functions->prep_conditionals($rowdata, $this->_make_conditionals($row_vars));

        $custom_date_fields = array();
        $this->date_vars_params = array();

        // parse single variables
        foreach($row_vars as $key => $val)
        {
            if (preg_match_all("/".LD.$variable_prefix.$key."\s+format=[\"'](.*?)[\"']".RD."/s", $rowdata, $matches))
            {
                for ($j = 0; $j < count($matches[0]); $j++)
                {
                    $matches[0][$j] = str_replace(array(LD,RD), '', $matches[0][$j]);
                    $this->date_vars_params[$matches[0][$j]] = $this->EE->localize->fetch_date_params($matches[1][$j]);
                    $this->date_vars_vals[$matches[0][$j]] = $matches[1][$j];
                }
            }
        }

        foreach($row_vars as $key => $val)
        {
            //$key = $this->_remove_prefix($key);

            // prevent array to string errors
            if(array_search($key, $pairs) === FALSE)
            {
                $var = explode(' ', $key);
                $var = $var[0];
                if(array_key_exists($var, $row_vars))
                {
                    if(is_object($row_vars[$var]) AND is_callable($row_vars[$var]))
                    {
                        $rowdata = $this->_swap_var_single($variable_prefix.$key, $row_vars[$var]($row_vars), $rowdata);
                    } else {
                        if(!is_object($row_vars[$var]) AND !is_array($row_vars[$var]))
                        {
                            $rowdata = $this->_swap_var_single($variable_prefix.$key, $row_vars[$var], $rowdata);
                        }
                    }
                }
            }
        }

        // If a variable is not parsing, remember to add it to the list of valid var pairs!
        foreach($pairs as $var_pair)
        {

            // The variable pair will only match if the ending is exactly like {/tag_name}, which means that we found either
            // a plain pair or possibly a pair with arguments inside, although this pattern currently has this detection
            // turned off - that is the basic formula for finding tags with parameters.
            $count = preg_match_all($pattern = "/".LD.$variable_prefix.$var_pair.RD."(.*?)".LD."\/".$variable_prefix.$var_pair.RD."/s", $rowdata, $matches);

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

                    // The PL_Parser_ArrayWrapper class allows us to wrap some meta info around an array so we don't need to do
                    // any special parsing for simple cases such as changing {row} to {rule}
                    if($row_vars[$var_pair] instanceof PL_Parser_ArrayWrapper)
                    {
                        $key_var_name = $row_vars[$var_pair]->key_var_name;
                        $row_var_name = $row_vars[$var_pair]->row_var_name;
                        $parse_pair   = &$row_vars[$var_pair]->array;
                    } else {
                        // If we just have a real array or an object, set the default parsing variable names and parse it
                        // normally.
                        $key_var_name = 'key';
                        $row_var_name = 'row';
                        $parse_pair   = &$row_vars[$var_pair];
                    }

                    // Loop over the data in the variable pair, and parse it into our template code
                    foreach($parse_pair as $data_key => $data)
                    {
                        $pair_row_data = $pair_row_template;

                        if(!is_array($data))
                        {
                            if(is_object($data) AND is_callable($data))
                            {
                                $data = $data($row_vars[$var_pair], $data_key, $pair_row_data);
                            } else {
                                // The array item is a string which we want to repalce into the template

                                // Replace and process conditionals for the key variable - often literally {key} - with the array
                                // index we are currently at in the variable pair
                                $pair_row_data = $this->EE->functions->prep_conditionals($pair_row_data, array($variable_prefix.$key_var_name => $data_key));
                                $pair_row_data  = $this->EE->TMPL->swap_var_single($variable_prefix.$key_var_name, $data_key, $pair_row_data);

                                // Replace and process conditionals for the value variable - usually {row} - with the value at
                                // this position
                                $pair_row_data = $this->EE->functions->prep_conditionals($pair_row_data, array($variable_prefix.$row_var_name => $data));
                                $pair_row_data  = $this->EE->TMPL->swap_var_single($variable_prefix.$row_var_name, $data, $pair_row_data);
                            }
                        } else {
                            $pair_row_data = $this->EE->functions->prep_conditionals($pair_row_data, $this->_make_conditionals($data));
                            $prepped_conditionals = array();

                            foreach($data as $k => $v) {
                                if(is_object($v) AND is_callable($v)) {
                                    if ($v instanceof PL_Callback_Interface) {
                                        $value = $v->getData();
                                    } else {
                                        $value = true;
                                    }
                                } else {
                                    $value = $v;
                                }
                                $prepped_conditionals[$k] = $value;
                            }

                            foreach($data as $k => $v)
                            {
                                // handle data callback
                                if(is_object($v) AND is_callable($v))
                                {
                                    // find matches on this subpair (this is used for celltype substitution in mason, for instance)
                                    //match pair, preventing matches like
                                    //{file:ul} ...{/file}
                                    $f_count = preg_match_all($f_pattern =
                                        "/".LD.$k."((?::[^ ]+?)?)(?: ((?:[a-zA-Z0-9_-]+=[\"'].*?[\"'] ?)*?))?".RD."(.*?)".LD."\/".$k.'\1'.RD."/s",
                                        $pair_row_data, $f_matches);
                                    // $f_matches[0] is an array of the full pattern matches - replace this with the results in the tagdata
                                    // $f_matches[1] is an array of segments for each tag
                                    // $f_matches[2] is an array of the parameters for each tag
                                    // $f_matches[3] is an array of the contents of the inside of each variable pair

                                    // did we find any pairs for the tag?
                                    // find matches on this subpair (this is used for celltype substitution in mason, for instance)
                                    if($f_count != 0)
                                    {
                                        //parse tag pairs
                                        for($fi = 0; $fi < count($f_matches[0]); $fi++)
                                        {
                                            $f_pair_match = $f_matches[0][$fi];
                                            $f_segments = $f_matches[1][$fi];
                                            $f_segments = $this->parse_segments($f_segments);
                                            $f_pair_params = $f_matches[2][$fi];
                                            $f_pair_params = $this->parse_params($f_pair_params);
                                            $f_pair_data = $f_matches[3][$fi];

                                            //echo $k.'='.$v->celltype->name.'<br/>';
                                            $pair_row_data = str_replace($f_pair_match, $v($k, $f_pair_data, $f_pair_params, $f_segments), $pair_row_data);
                                        }
                                    }

                                    // find single tags, not mutually exclusive

                                    $f_count = preg_match_all($f_pattern =
                                        "/".LD.$k."(:[^ ]+?)?(?: ((?:[a-zA-Z0-9_-]+=[\"'].*?[\"'] ?)*?))?".RD."/s",
                                        $pair_row_data, $f_matches);
                                    // $f_matches[0] is an array of the full pattern matches - replace this with the results in the tagdata
                                    // $f_matches[1] is an array of segments for each tag
                                    // $f_matches[2] is an array of the parameters for each tag
                                    if($f_count > 0)
                                    {
                                        for($fi = 0; $fi < count($f_matches[0]); $fi++)
                                        {
                                            $f_var_match = $f_matches[0][$fi];
                                            $f_segments = $f_matches[1][$fi];
                                            $f_segments = $this->parse_segments($f_segments);
                                            $f_params = $f_matches[2][$fi];
                                            $f_params = $this->parse_params($f_params);

                                            //echo $k.'='.$v->celltype->name.'<br/>';
                                            $pair_row_data = str_replace($f_var_match, $v($k, $f_var_match, $f_params, $f_segments), $pair_row_data);
                                        }
                                    }
                                } else {
                                    if(is_array($v) OR $v instanceof PL_Parser_ArrayWrapper) {
                                        $pair_row_data = $this->parse_variables_ex(array_merge(
                                            $params,
                                            array(
                                                'rowdata' => $pair_row_data,
                                                'row_vars' => $data,
                                                'pairs' => array($k)
                                            )
                                        ));
                                    } else {
                                        if(array_key_exists($k, $row_vars) === FALSE)
                                        {
                                            $pair_row_data  = $this->_swap_var_single($variable_prefix.$k, $v, $pair_row_data);
                                            if (in_array($k, $reparse_vars)) {
                                                $pair_row_data = $this->EE->functions->prep_conditionals($pair_row_data, $this->_make_conditionals($prepped_conditionals));
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        if(isset($this->row_callback))
                        {
                            $callback = &$this->row_callback;
                            $pair_row_data = $callback($pair_row_data);
                        }

                        $pair_data .= $pair_row_data;
                    }

                    $rowdata = str_replace($pair_tag, $pair_data, $rowdata);
                }
            }
        }

        if($backspace)
        {
            $rowdata = substr($rowdata, 0, - $backspace);
        }

        return $rowdata;
    } // function parse_variables

    function _remove_prefix($key)
    {
        if(substr($key, 0, strlen($this->variable_prefix)) == $this->variable_prefix) {
            $key = substr($key, strlen($this->variable_prefix), strlen($key));
        }
        return $key;
    }

    function _make_conditionals($row_vars)
    {
        $conditionals = array();
        foreach($row_vars as $k => $v)
        {
            if($v instanceof PL_Parser_ArrayWrapper)
            {
                $conditionals[$this->variable_prefix.$k] = count($v->array);
            } else {
                $conditionals[$this->variable_prefix.$k] = $v;
            }
        }
        return $conditionals;
    }

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
                if($dst_enabled)
                {
                    $temp_val = $this->EE->localize->simpl_offset($temp_val, $row['field_dt_'.$dval]);
                    $localize = FALSE;
                }
            //}
            */

            // convert to a timestamp
            $time = strtotime($temp_val);

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

    /**
     * Parse segments from matched tag
     *
     * @param $code
     * @return array
     */
    function parse_segments($code)
    {
        $result = array();

        if(strlen($code) == 0 || strpos($code,':') === false) {
            return $result;
        }
        preg_match('/^:([^\s}]+)/', $code, $matches);
        if ( ! $matches) {
            return $result;
        }
        return explode(':', $matches[1]);
    }

    function parse_params($code)
    {
        $result = array();

        if(strlen($code) == 0) {
            return $result;
        }

        $code .= ' '; // add extra space to trigger name adding so we only have to do it when state 1 switches to state 0

        $name = '';
        $value = '';
        $state = 0;

        for($i = 0; $i < strlen($code); $i++)
        {
            $c = $code[$i];
            switch($state)
            {
                case 0: // name
                    switch($c)
                    {
                        case '=':
                            $state = 1;
                            break;
                        default:
                            // skip white space
                            if($c != ' ' AND $c != "\t")
                            {
                                $name .= $c;
                            }
                    }
                    break;
                case 1: // value
                    switch($c)
                    {
                        case ' ':
                        case "\t":
                            $state = 0; // back to name
                            break;
                        case '"':
                            $state = 2; // quote
                            $quote = 2;
                            break;
                        case '\'':
                            $state = 2; // quote
                            $quote = 1;
                            break;
                        default:
                            $value .= $c;
                            break;
                    }

                    // done with the value?
                    if($state == 0 OR $i == strlen($code)-1)
                    {
                        $result[$name] = $value;
                        $name = '';
                        $value = '';
                    }
                    break;
                case 2: // quote
                    if($quote == 1 AND $c == '\'')
                    {
                        $state = 1;
                    } elseif($quote == 2 AND $c == '"') {
                        $state = 1;
                    } else {
                        $value .= $c;
                    }
                    break;
            }
        }

        return $result;
    }

    public function wrap_array(&$array, $key_var_name = 'key', $row_var_name = 'row')
    {
        return new PL_Parser_ArrayWrapper($array, $key_var_name, $row_var_name);
    }


    /**
     * No Results
     *
     * If a tag/class has no results to show, it can call this method.  Any no_results variable in
     * the tag will be followed.  May be 404 page, content, or even a redirect.
     *
     * @return  void
     */
    public function parse_no_results_ex($params=array())
    {
        // Setup default parameters
        extract($this->make_params($params, array(
            'variable_prefix'   => '',
        )));

        if(preg_match("/".LD."if ".$variable_prefix."no_results".RD."(.*?)".LD.'\/'."if".RD."/s", $this->EE->TMPL->tagdata, $match))
        {
            $chunk = $match[0];
            if (stristr($match[1], LD.'if'))
            {
                // There are other conditionals inside the chunk, recursively search to find the matching {/if} for the no_results
                // conditional.
                $chunk = $this->EE->functions->full_tag($match[0], $this->EE->TMPL->tagdata, LD.'if', LD.'\/'."if".RD);
            }

            // Strip off the opening and closing conditional tags. Because of the above madness we can't just use
            // the inside marked region of the tag.
            $this->no_results = substr($match[0], strlen(LD."if ".$variable_prefix."no_results".RD), -strlen(LD.'/'."if".RD));

            // Remove the no_results conditional chunk from the template tagdata
            $this->EE->TMPL->tagdata = str_replace($chunk, '', $this->EE->TMPL->tagdata);
        } else {
            $this->no_results = $this->EE->TMPL->no_results;
        }


    }

    public function no_results()
    {
        return $this->no_results;
    }

    public function make_params(&$params, $param_defaults)
    {
        // Check for invalid parameters
        foreach($params as $k => $v)
        {
            if(!array_key_exists($k, $param_defaults))
            {
                exit('Invalid parameter provided to no_results_ex: '.$k);
            }
        }

        // Load parameters from combined defaults and provided values
        $params = array_merge($param_defaults, $params);

        foreach($params as $k => $v)
        {
            $this->$k = $v;
        }

        return $params;
    }
}

class PL_Parser_ArrayWrapper {
    var $array = array();
    var $key_var_name = 'key';
    var $row_var_name = 'row';

    public function __construct(&$array, $key_var_name, $row_var_name)
    {
        $this->array = &$array;
        $this->key_var_name = $key_var_name;
        $this->row_var_name = $row_var_name;
    }

    public function __toString() {
        xdebug_print_function_stack('PL_Parser_ArrayWrapper::__toString()!');
        exit;
    }
}
