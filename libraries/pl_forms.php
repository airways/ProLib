<?php

class PL_forms {
    public function PL_forms()
    {
        $this->EE = &get_instance();
    }
    
    function create_cp_form($object, $types, $extra=array(), $current_settings = false)
    {
        $form = array();

        if($current_settings === false)
        {
            $current_settings = isset($object->settings) ? $object->settings : array();
        }

        if(isset($extra['order']) && $extra['order'] == 'type')
        {
            $fields = array_keys($types);
        } else {
            $fields = array_keys((array)$object);
        }
        
        foreach($fields as $key)
        {
            if(is_array($object))
            {
                $value = &$object[$key];
            } else {
                $value = &$object->$key;
            }
            
            if(is_object($value)) continue;
            
            if(substr($key, 0, 2) != "__") {

                if(array_key_exists($key, $types)) {
                    $type = $types[$key];
                } else {
                    $type = "input";
                }

                if(is_array($type)) {
                    if(count($type) == 3)
                        $option_settings = $type[2];
                    else
                        $option_settings = array();
                    $options = $type[1];
                    $type = $type[0];
                } else {
                    $options = array();
                }

                $lang_field = $key;
                $input_name = $key;
                if(isset($extra['array_name']))
                {
                    $input_name = $extra['array_name'].'['.$input_name.']';
                }
                
                switch($type)
                {
                    case 'hidden':
                        $form[] = array('lang_field' => '', 'control' => form_hidden($input_name, $value));
                        break;
                    case 'read_only':
                        $form[] = array('lang_field' => $lang_field, 'control' => nl2br(htmlentities(strip_tags($value), 0, "UTF-8")));
                        break;
                    case 'read_only_member':
                        $value = (int)$value;
                        $query = $this->EE->db->where('member_id', $value)->get('exp_members');
                        if($query->num_rows() > 0)
                        {
                            $link = $query->row()->screen_name;
                        } else {
                            $link = '[Unknown Member]';
                        }
                        $form[] = array('lang_field' => $lang_field, 'control' => $value.' '.$link);
                        break;
                    case 'static':
                        $form[] = array('lang_field' => $lang_field, 'control' => $value);
                        break;
                    case 'heading':
                        $form[] = array('lang_field' => '!heading', 'control' => $options);
                        break;
                    case 'read_only_checkbox':
                        $form[] = array('lang_field' => $lang_field, 'control' => '<b>'.($value == 'y' ? 'On' : 'Off') . '</b>: ' . $options/*.'<br/><br/>'.form_checkbox(array('name' => $input_name, 'value' => 'y', 'checked' => $value == 'y', 'disabled' => 'disabled'))*/);
                        break;
                    case 'textarea':
                        $form[] = array('lang_field' => $lang_field, 'control' => form_textarea($input_name, $value));
                        break;
                    case 'dropdown':
                        $control = form_dropdown($input_name, $options, $value);
                        foreach($option_settings as $k => $settings)
                        {
                            $control .= '<div id="'.$key.'_'.$k.'" class="edit_settings">';

                            if($current_settings === false)
                            {
                                echo 'No settings array exists for object of type ' . get_class($object);
                                var_dump($object);
                                exit;
                            }

                            foreach($settings as $settings_field)
                            {
                                if(array_key_exists($key.'_'.str_replace('[]','',$settings_field['name']), $current_settings))
                                {
                                    $setting_value = $current_settings[$key.'_'.str_replace('[]','',$settings_field['name'])];
                                } else {
                                    $setting_value = '';
                                }

                                $control .= '<div><label>'.$settings_field['label'].'</label> ';
                                switch($settings_field['type'])
                                {
                                    case 'textarea':
                                        $control .= form_textarea($key.'_'.$settings_field['name'], $setting_value);
                                        break;
                                    case 'input':
                                        $control .= form_input($key.'_'.$settings_field['name'], $setting_value);
                                        break;
                                    case 'dropdown':
                                        $control .= form_dropdown($key.'_'.$settings_field['name'],
                                                    isset($settings_field['options']) ? $settings_field['options'] : array(),
                                                    $setting_value);
                                        break;
                                    case 'multiselect':
                                        $control .= form_multiselect($key.'_'.$settings_field['name'],
                                                    isset($settings_field['options']) ? $settings_field['options'] : array(),
                                                    $setting_value);
                                        break;
                                }
                                $control .= '</div>';
                            }
                            $control .= '</div>';
                        }
                        $form[] = array('lang_field' => $lang_field, 'control' => $control);
                        break;
                    case 'grid':
                        $field = array('lang_field' => $lang_field, 'control' => $this->render_grid($key, $options['headings'], $options['options'], $value, 
                            isset($options['form']) ? $options['form'] : array(),
                            isset($options['allow_duplicate']) ? $options['allow_duplicate'] : false
                        ));
                        if(array_key_exists('flags', $options) && strpos($options['flags'], 'has_param'))
                        {

                        }

                        $form[] = $field;
                        break;
                    case 'checkbox':
                        $form[] = array('lang_field' => $lang_field, 'control' => form_checkbox($input_name, 'y', $value == 'y'));
                        break;
                    default:
                        if(!is_array($value) && !is_object($value))
                        {
                            $form[] = array('lang_field' => $lang_field, 'control' => form_input($input_name, $value));
                        }
                        break;
                } // switch($type)

                if(array_key_exists('after', $extra) AND array_key_exists($key, $extra['after']))
                {
                    foreach($extra['after'][$key] as $after)
                    {
                        $form[] = $after;
                    }
                }
            }
        }

        return $form;
    } // function _create_cp_form

    function simple_select_options($options)
    {
        $result = array();
        foreach($options as $k)
        {
            $result[$k] = $k;
        }
        return $result;
    }

    function render_grid($key, $headings, $options, $value, $form = array(), $allow_duplicate = false)
    {
        $out = '';
        
        $dropdown_options = array();
        $help = array();
        foreach($options as $option => $opts)
        {
            if(!is_array($opts))
            {
                //$option = $opts;
                $opts = array(
                    'label' => $opts
                );
            }
            $dropdown_options[$option] = $opts['label'];
            if(isset($opts['help']))
            {
                $help[$option] = $opts['help'];
            } else {
                $help[$option] = '';
            }
        }
        $dropdown = form_dropdown('addgridrow_'.$key, $dropdown_options, array(), 'id="'.'addgridrow_'.$key.'"');

        $out .= '<div id="field_'.$key.'" class="pl_grid" data-key="'.$key.'">';

        $out .= '<table id="gridrow_'.$key.'" class="plain"><tbody><tr>';

        foreach($headings as $heading)
        {
            $out .= '<th>'.$heading.'</th>';
        }
        $out .= '</tr>';

        if($value && $value[0] == '[')
        {
            // Load new JSON grid syntax
            $rows = json_decode($value);
        } else {
            // Load old CI validation syntax
            $rows = explode('|', $value);
        }
        $i = 0;
        $grid = array();

        foreach($rows as $row)
        {
            if($row) {
                if(is_object($row) || is_array($row)) {
                    $cells = $row;
                } else {
                    // Load old CI validation syntax
                    if(strpos($row, '[') !== FALSE)
                    {
                        $arr = explode('[',$row);
                    } else {
                        $arr = array($row);
                    }
                    $cells = (object)array();
                    $cells->_ = $arr[0];
                    if(count($arr) > 1)
                    {
                        $cells->value = str_replace(']', '', $arr[1]);
                    }
                    if($cells->_ == 'none' || $cells->_ == '') continue;
                }
                
                $grid[] = $cells;

                $out .= '<tr class="grid_row"><td>'.$options[$cells->_]['label'].'</td>';

                if(count($form) > 0)
                {
                    $cell_i = 0;
                    foreach($form as $field_name => $form_field)
                    {
                        /*
                        echo '<b>'.$field_name.'</b><br/>';
                        var_dump($form_field);
                        // */
                        if(is_array($form_field))
                        {
                            $field_type = $form_field[0];
                            $fld_options = isset($form_field['options']) ? $form_field['options'] : (isset($form_field[1]) ? $form_field[1] : array());
                            $raw_options = array();
                            foreach($fld_options as $opt_key => $val)
                            {
                                if(is_array($val) && isset($val['label'])) $raw_options[$opt_key] = $val['label'];
                                else $raw_options[$opt_key] = $val;
                            }
                        } else {
                            $field_type = $form_field;
                            $raw_options = array();
                        }
                        
                        $default = array_pop(array_keys($raw_options));
                        $extra = 'data-key="'.$key.'" data-opt="'.$field_name.'" data-row="'.$i.'" class="grid_param"';
                        switch($field_type)
                        {
                            case 'textarea':
                                $out .= '<td>'.form_textarea($key.'_'.$field_name, $cells->$field_name, $extra).'</td>';
                                break;
                            case 'input':
                                $out .= '<td>'.form_input($key.'_'.$field_name, $cells->$field_name, $extra).'</td>';
                                break;
                            case 'dropdown':
                                $out .= '<td>'.form_dropdown($key.'_'.$field_name,
                                            $raw_options, isset($cells->$field_name) ? $cells->$field_name : $default, $extra).'</td>';
                                break;
                            case 'multiselect':
                                $out .= '<td>'.form_multiselect($key.'_'.$field_name,
                                            $raw_options, isset($cells->$field_name) ? $cells->$field_name : $default, $extra).'</td>';
                                break;
                        }
                        
                        $cell_i++;
                    }
                } else {
                    if(isset($options[$cells->_]['flags']) && strpos($options[$cells->_]['flags'], 'has_param') !== FALSE)
                    {
                        $out .=  '<td><input data-key="'.$key.'" data-opt="'.$cells->_.'" data-row="'.$i.'" class="grid_param" type="text" size="5" value="'.htmlentities(isset($cells->value) ? $cells->value : '').'"/></td>';
                        //isset($cells->{$cells->_})?$cells->{$cells->_}:''
                        //<span class="help">'.((is_array($options[$cells->_]['flags']) && isset($options[$cells->_]['flags']['help'])) ? $options[$cells->_]['flags']['help'] : '').'</span>
                    } else {
                        $out .= '<td>&nbsp;</td>';
                    }
                }

                // $out .= '<td>'.form_button('remove_'.$key.'_'.$i, 'X', 'class="remove_grid_row" data-key="'.$key.'" data-opt="'.$cells[0].'" ').'</tr>';
                $out .= '<td><a href="#" class="remove_grid_row" name="remove_'. $key .'_'. $i .'" data-key="'. $key .'" data-opt="'.$cells->_.'" data-row="'.$i.'">X</a></td></tr>';
            }
            
            $i++;
        }

        $out .= '</tbody></table>';

        // $out .= '<h4>Add another rule</h4><br/>'.$dropdown.' '.form_button('addgridrow_'.$key, 'Add', 'id="addgridrow_'.$key.'" class="add_grid_row"');
        $out .= '<h4>Add another rule</h4>'.$dropdown;
        $out .= '<a href="#" name="addgridrow_'. $key .' id="addgridrow_'.$key.' class="add_grid_row">Add</a>';

        $out .= '<input type="hidden" name="'.$key.'" value="'.$value.'" />';

        $out .= '<script type="text/javascript">';
        $out .= 'pl_grid.options["'.$key.'"] = ' . json_encode($options) . ';';
        $out .= 'pl_grid.forms["'.$key.'"] = ' . json_encode($form ? $form : false) . ';';
        $out .= 'pl_grid.help["'.$key.'"] = ' . json_encode($help) . ';';
        if($form)
        {
            $out .= 'pl_grid.data["'.$key.'"] = ' . json_encode($grid) . ';';
        } else {
            $old_grid = array();
            foreach($grid as $grid_key => $grid_row) {
                $old_row = array($grid_row->_);
                if(isset($grid_row->value)) $old_row[] = $grid_row->value;
                $old_grid[] = $old_row;
            }
            $out .= 'pl_grid.data["'.$key.'"] = ' . json_encode($old_grid) . ';';
        }
        
        /*$out .= 'var options = {';
        foreach($options as $option => $opts)
        {
            $out .= $option.': {';
            foreach($opts as $k => $v)
            {
                $out .= $k.': "'.$v.'",';
            }
            $out = substr($out, 0, -1);
            $out .= '},';
        }
        $out = substr($out, 0, -1);
        $out .= '};';*/
        
        $out .= 'pl_grid.bind_events("'.$key.'", "gridrow_'.$key.'", '.json_encode($allow_duplicate).');</script>';
        $out .= '</div>';

        return $out;
    } // function _render_grid

}
