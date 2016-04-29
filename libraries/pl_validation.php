<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * @package ProLib
 * @author Isaac Raway <isaac.raway@gmail.com>
 * @version 0.1
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

if(file_exists(APPPATH.'../codeigniter/system/libraries/Form_validation.php'))
{
    // ExpressionEngine
    require_once(APPPATH.'../codeigniter/system/libraries/Form_validation.php');
} else {
    // CodeIgniter
    require_once(APPPATH.'../system/libraries/Form_validation.php');
}

/**
 * Form Validation Class with custom callbacks
 */
class PL_validation extends CI_Form_validation {
    var $available_rules = array( /* this works nicely as the 'options' part of a MCP grid control */
        'required'                      => array('label' => 'Always Required'),
        'callback_matches_value'        => array('label' => 'Matches Value', 'flags' => 'has_param'),
        'matches'                       => array('label' => 'Matches Field', 'flags' => 'has_param'),
        'min_length'                    => array('label' => 'Min Length', 'flags' => 'has_param'),
        'max_length'                    => array('label' => 'Max Length', 'flags' => 'has_param'),
        'exact_length'                  => array('label' => 'Exact Length', 'flags' => 'has_param'),
        'alpha'                         => array('label' => 'Alpha Characters Only'),
        'alphanumeric'                  => array('label' => 'Alpha Numeric Characters Only'),
        'alpha_dash'                    => array('label' => 'Alpha Numeric Characters, Underscores and Dashes Only'),
        'numeric'                       => array('label' => 'Numeric Characters Only'),
        'callback_numeric_dash'                  => array('label' => 'Numeric Characters and Dashes Only'),
        'integer'                       => array('label' => 'Integer Number'),
        'is_natural'                    => array('label' => 'Natural Number'),
        'is_natural_no_zero'            => array('label' => 'Natural Number other than zero'),
        'valid_email'                   => array('label' => 'Valid E-mail Address'),
        'valid_emails'                  => array('label' => 'Valid E-mail Addresses separated by commas'),
        'valid_ip'                      => array('label' => 'Valid IP Address'),
        'valid_base64'                  => array('label' => 'Valid Base 64 Encoded Value'),
        'strip_tags'                    => array('label' => 'Strip HTML (filter)'),
        'trim'                          => array('label' => 'Trim (filter)'),
        'base64_encode'                 => array('label' => 'Base 64 Encode (filter)'),
        'base64_decode'                 => array('label' => 'Base 64 Decode (filter)'),
        'urlencode'                     => array('label' => 'URL Encode (filter)'),
        'urldecode'                     => array('label' => 'URL Decode (filter)'),
        'parse_url'                     => array('label' => 'Parse URL Component (filter)', 'flags' => 'has_param',
                                                 'help' => 'scheme, host, port, user, pass, path, or query'),
    );
    
    var $is_callback = array();

    var $available_filters = array(
    );

    function __construct()
    {
        global $PROLIB;
        $this->EE = &get_instance();

        if(isset($this->EE->extensions))
        {
            if($this->EE->extensions->active_hook('prolib_register_callbacks_lang') === TRUE)
            {
                $this->EE->extensions->call('prolib_register_callbacks_lang', $this);
            }
        } else {
            $PROLIB->pl_hooks->hook('prolib_register_callbacks_lang', $this);
        }

        parent::__construct();
    }

    function set_error_messages($new_messages)
    {
        $this->_error_messages = array_merge($this->_error_messages, $new_messages);
    }

    function set_rules($field, $label = '', $rules = '')
    {
        /*
        krumo(array($field, $label, $rules));
        // */
        parent::set_rules($field, $label, $rules);
    }
    
    /**
     * Run the Validator
     *
     * This function does all the work.
     *
     * @access  public
     * @return  bool
     */
    function run($group = '')
    {
        /*
        krumo($this->_field_data);
        krumo($_POST);
        //exit;
        // */
        
        // Do we even have any data to process?  Mm?
        if (count($_POST) == 0)
        {
            return FALSE;
        }

        // Does the _field_data array containing the validation rules exist?
        // If not, we look to see if they were assigned via a config file
        if (count($this->_field_data) == 0)
        {
            // No validation rules?  We're done...
            if (count($this->_config_rules) == 0)
            {
                return FALSE;
            }

            // Is there a validation rule for the particular URI being accessed?
            $uri = ($group == '') ? trim($this->CI->uri->ruri_string(), '/') : $group;

            if ($uri != '' AND isset($this->_config_rules[$uri]))
            {
                $this->set_rules($this->_config_rules[$uri]);
            }
            else
            {
                $this->set_rules($this->_config_rules);
            }

            // We're we able to set the rules correctly?
            if (count($this->_field_data) == 0)
            {
                log_message('debug', "Unable to find validation rules");
                return FALSE;
            }
        }

        // Load the language file containing error messages
        $this->CI->lang->load('form_validation');

        // Cycle through the rules for each field, match the
        // corresponding $_POST item and test for errors
        foreach ($this->_field_data as $field => $row)
        {
            // Fetch the data from the corresponding $_POST array and cache it in the _field_data array.
            // Depending on whether the field name is an array or a string will determine where we get it from.

            if ($row['is_array'] == TRUE)
            {
                $this->_field_data[$field]['postdata'] = $this->_reduce_array($_POST, $row['keys']);
            }
            else
            {
                if (isset($_POST[$field]) && $_POST[$field] != "")
                {
                    $this->_field_data[$field]['postdata'] = $_POST[$field];
                }
            }

            $this->_execute($row, explode('|', $row['rules']), $this->_field_data[$field]['postdata']);
        }

        // Did we end up with any errors?
        $total_errors = count($this->_error_array);

        if ($total_errors > 0)
        {
            $this->_safe_form_data = TRUE;
        }

        // Now we need to re-set the POST data with the new, processed data
        $this->_reset_post_array();

        // No errors, validation passes!
        if ($total_errors == 0)
        {
            return TRUE;
        }

        // Validation fails
        return FALSE;
    }

    /**
     * Executes the Validation routines
     **/
    function _execute($row, $rules, $postdata = NULL, $cycles = 0)
    {
        $this->EE->lang->loadfile('prolib');
        
        // create a new object that has our custom callbacks on it
        if(!isset($this->CI_callback) || !$this->CI_callback)
        {
            $this->CI_callback = new PL_forms_validation_callbacks($this->CI);
        }

        // save the real CI object, replace with our custom callback class
        $temp_CI = $this->CI;
        $this->CI = $this->CI_callback;

        // call the real method
        $this->_execute_extended($row, $rules, $postdata, $cycles);

        // restore the original CI object
        $this->CI = $temp_CI;
    }


    /**
     * Executes the Validation routines
     *
     * The only difference in this version from the core CI version is that we try
     * to call generic_callback() if a given callback is not found. This allows the
     * callback class to provide a hook to allow other extensions to register and
     * respond to callbacks.
     *
     * @access  private
     * @param   array
     * @param   array
     * @param   mixed
     * @param   integer
     * @return  mixed
     */
    function _execute_extended($row, $rules, $postdata = NULL, $cycles = 0)
    {
        // If the $_POST data is an array we will run a recursive call
        if (is_array($postdata))
        {
            foreach ($postdata as $key => $val)
            {
                $this->_execute($row, $rules, $val, $cycles);
                $cycles++;
            }

            return;
        }

        // --------------------------------------------------------------------

        // If the field is blank, but NOT required, no further tests are necessary
        $callback = $this->_is_callback($rules);
        
        if ( ! in_array('required', $rules) AND is_null($postdata))
        {
            // Before we bail out, does the rule contain a callback?
            if (preg_match("/(callback_\w+)/", implode(' ', $rules), $match))
            {
                $callback = TRUE;
                $rules = (array('1' => $match[1]));
            }
            else
            {
                return;
            }
        }

        // --------------------------------------------------------------------

        // Isset Test. Typically this rule will only apply to checkboxes.
        if (is_null($postdata) AND $callback == FALSE)
        {
            if (in_array('isset', $rules, TRUE) OR in_array('required', $rules))
            {
                // Set the message type
                $type = (in_array('required', $rules)) ? 'required' : 'isset';

                if ( ! isset($this->_error_messages[$type]))
                {
                    if (FALSE === ($line = $this->CI->lang->line($type)))
                    {
                        $line = 'The field was not set';
                    }
                }
                else
                {
                    $line = $this->_error_messages[$type];
                }

                // Build the error message
                $message = sprintf($line, $this->_translate_fieldname($row['label']));

                // Save the error message
                $this->_field_data[$row['field']]['error'] = $message;

                if ( ! isset($this->_error_array[$row['field']]))
                {
                    $this->_error_array[$row['field']] = $message;
                }
            }

            return;
        }

        // --------------------------------------------------------------------

        // Cycle through each rule and run it
        foreach ($rules As $rule)
        {
            $_in_array = FALSE;

            // We set the $postdata variable with the current data in our master array so that
            // each cycle of the loop is dealing with the processed data from the last cycle
            if ($row['is_array'] == TRUE AND is_array($this->_field_data[$row['field']]['postdata']))
            {
                // We shouldn't need this safety, but just in case there isn't an array index
                // associated with this cycle we'll bail out
                if ( ! isset($this->_field_data[$row['field']]['postdata'][$cycles]))
                {
                    continue;
                }

                $postdata = $this->_field_data[$row['field']]['postdata'][$cycles];
                $_in_array = TRUE;
            }
            else
            {
                $postdata = $this->_field_data[$row['field']]['postdata'];
            }

            // --------------------------------------------------------------------

            // Is the rule a callback?
            $callback = $this->_is_callback($rule);
            if (substr($rule, 0, 9) == 'callback_')
            {
                $rule = substr($rule, 9);
                $callback = TRUE;
            }

            // Strip the parameter (if exists) from the rule
            // Rules can contain a parameter: max_length[5]
            $param = FALSE;
            if (preg_match("/(.*?)\[(.*?)\]/", $rule, $match))
            {
                $rule   = $match[1];
                $param  = $match[2];
            }

            // Call the function that corresponds to the rule
            if ($callback === TRUE)
            {
                // IRAWAY - added support for generic_callback()
                if ( ! method_exists($this->CI, $rule))
                {
                    if (method_exists($this->CI, "generic_callback"))
                    { // generic_callback
                        $handled = FALSE;
                        $result = $this->CI->generic_callback($rule, $postdata, $param, $handled);
                        if(!$handled)
                        {
                            continue;
                        }
                    } else {
                        continue;
                    }
                } else { // normal callback
                    // Run the function and grab the result
                    $result = $this->CI->$rule($postdata, $param);
                }
                // END IRAWAY

                // Re-assign the result to the master data array
                if ($_in_array == TRUE)
                {
                    $this->_field_data[$row['field']]['postdata'][$cycles] = (is_bool($result)) ? $postdata : $result;
                }
                else
                {
                    $this->_field_data[$row['field']]['postdata'] = (is_bool($result)) ? $postdata : $result;
                }

                // If the field isn't required and we just processed a callback we'll move on...
                if ( ! in_array('required', $rules, TRUE) AND $result !== FALSE)
                {
                    continue;
                }
            }
            else
            {
                if ( ! method_exists($this, $rule))
                {
                    // If our own wrapper function doesn't exist we see if a native PHP function does.
                    // Users can use any native PHP function call that has one param.
                    if (function_exists($rule))
                    {
                        // IRAWAY add support for param to these core/global functions - this won't break anything
                        // that only takes one argument since PHP doesn't care if you give it too many arguments
                        $result = $rule($postdata, $param);
                        // END IRAWAY

                        if ($_in_array == TRUE)
                        {
                            $this->_field_data[$row['field']]['postdata'][$cycles] = (is_bool($result)) ? $postdata : $result;
                        }
                        else
                        {
                            $this->_field_data[$row['field']]['postdata'] = (is_bool($result)) ? $postdata : $result;
                        }
                    }

                    continue;
                }

                $result = $this->$rule($postdata, $param);

                if ($_in_array == TRUE)
                {
                    $this->_field_data[$row['field']]['postdata'][$cycles] = (is_bool($result)) ? $postdata : $result;
                }
                else
                {
                    $this->_field_data[$row['field']]['postdata'] = (is_bool($result)) ? $postdata : $result;
                }
            }

            // Did the rule test negatively?  If so, grab the error.
            if ($result === FALSE)
            {
                if ( ! isset($this->_error_messages[$rule]))
                {
                    if (FALSE === ($line = $this->CI->lang->line($rule)))
                    {
                        $line = 'Unable to access an error message corresponding to your field name.';
                    }
                }
                else
                {
                    $line = $this->_error_messages[$rule];
                }

                // Is the parameter we are inserting into the error message the name
                // of another field?  If so we need to grab its "field label"
                if (isset($this->_field_data[$param]) AND isset($this->_field_data[$param]['label']))
                {
                    $param = $this->_translate_fieldname($this->_field_data[$param]['label']);
                }

                // Build the error message
                $message = sprintf($line, $this->_translate_fieldname($row['label']), $param);

                // Save the error message
                $this->_field_data[$row['field']]['error'] = $message;

                if ( ! isset($this->_error_array[$row['field']]))
                {
                    $this->_error_array[$row['field']] = $message;
                }

                return;
            }
        }
    }
    
    private function _is_callback($rules)
    {
        $callback = false;
        if(!is_array($rules)) $rules = array($rules);
        foreach($rules as $rule)
        {
            $rule = explode('[', $rule);
            $rule = $rule[0];
            if(in_array($rule, $this->is_callback)) $callback = TRUE;
        }
        return $callback;
    }
}

/**
 * Callbacks Class
 *
 * This class provides custom BM validation callbacks.
 *
 **/
class PL_forms_validation_callbacks {
    var $callbacks = array();

    function __construct($CI) {
        $this->EE = &get_instance();

        // we need to copy the properties from CI that are used
        // in _execute()
        //$this->lang = $CI->lang;
        foreach($CI as $key => $value)
        {
            $this->$key = $CI->$key;
        }
        
        $this->pl_validation = $CI->pl_validation;

        if (isset($this->EE->extensions) && $this->EE->extensions->active_hook('prolib_register_validation_callbacks') === TRUE)
        {
            $this->EE->extensions->call('prolib_register_validation_callbacks', $this);
        }
    }

    function generic_callback($rule, $postdata, $param, &$handled)
    {
        if(array_key_exists($rule, $this->callbacks))
        {
            // call the callback - this can be a global function or an array(class, method)
            // or an array(instance, method)
            $value = call_user_func($this->callbacks[$rule], $postdata, $param);
            $handled = TRUE;
            return $value;
        }

        $handled = FALSE;
    }

    function matches_value($value, $param)
    {
//      var_dump($value);
//      var_dump($param);
//      exit;
        if($value === $param)
        {
            return TRUE;
        } else {
            $this->pl_validation->set_message('callback_matches_value', $value);
            return FALSE;
        }
    }

    function numeric_dash($value, $param)
    {
        if(preg_match('/^[0-9\-]*$/', $value))
        {
            return TRUE;
        } else {
            $this->pl_validation->set_message('callback_numeric_dash', $value);
            return FALSE;
        }
    }
}




