<?php

class Bm_forms {
    function create_cp_form($object, $types)
    {
        $form = array();

        
        foreach($object as $key => $value)
        {
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
                
                switch($type)
                {
                    case 'read_only':
                        $form[] = array('lang_field' => $key, 'control' => htmlentities(strip_tags($value)));
                        break;
                    case 'textarea':
                        $form[] = array('lang_field' => $key, 'control' => form_textarea($key, $value));
                        break;
                    case 'dropdown':
                        $control = form_dropdown($key, $options, $value);
                        foreach($option_settings as $k => $settings)
                        {
                            $control .= '<div id="'.$key.'_'.$k.'" class="edit_settings">';
                            
                            if(isset($object->settings))
                            {
                                if(array_key_exists($key.'_'.$k, $object->settings))
                                {
                                    $setting_value = $object->settings[$key.'_'.$k];
                                } else {
                                    $setting_value = '';
                                }
                            } else {
                                echo 'No settings array exists for object of type ' . get_class($object);
                                var_dump($object);
                                exit;
                            }

                            foreach($settings as $settings_field)
                            {
                                $control .= '<div><label>'.$settings_field['label'].'</label>';
                                switch($settings_field['type'])
                                {
                                    case 'textarea':
                                        $control .= form_textarea($key.'_'.$k, $setting_value);
                                        break;
                                    case 'input':
                                        $control .= form_input($key.'_'.$k, $setting_value);
                                        break;
                                }
                                $control .= '</div>';
                            }
                            $control .= '</div>';
                        }
                        $form[] = array('lang_field' => $key, 'control' => $control);
                        break;
                    case 'grid':
                        $field = array('lang_field' => $key, 'control' => $this->render_grid($key, $options['headings'], $options['options'], $value));
                        if(array_key_exists('flags', $options) && strpos($options['flags'], 'has_param'))
                        {
                            
                        }
                        
                        $form[] = $field;
                        break;
                    case 'checkbox':
                        $form[] = array('lang_field' => $key, 'control' => form_checkbox($key, 'y', $value == 'y'));
                        break;
                    default:
                        $form[] = array('lang_field' => $key, 'control' => form_input($key, $value));
                        break;
                }
            }
        }
        
        return $form;
    } // function _create_cp_form
    
    
    function render_grid($key, $headings, $options, $value)
    {
        $out = '';
        
        $dropdown_options = array();
        $help = array();
        foreach($options as $option => $opts)
        {
            $dropdown_options[$option] = $opts['label'];
            if(isset($opts['help']))
            {
                $help[$option] = $opts['help'];
            } else {
                $help[$option] = '';
            }
        }
        $dropdown = form_dropdown('addgridrow_'.$key, $dropdown_options, array(), 'id="'.'addgridrow_'.$key.'"');
        
        $out .= '<div id="field_'.$key.'" class="bm_grid" data-key="'.$key.'">';
        
        $out .= '<table id="gridrow_'.$key.'" class="plain"><tbody><tr>';
        
        foreach($headings as $heading)
        {
            $out .= '<th>'.$heading.'</th>';
        }
        $out .= '</tr>';
        
        $rows = explode('|', $value);
        $i = 1;
        $grid = array();
        
        foreach($rows as $row)
        {
            $cells = explode('[',$row);
            
            if(count($cells) > 1)
            {
                $cells[1] = str_replace(']', '', $cells[1]);
            }
            
            if($cells[0] != 'none' && $cells[0] != '')
            {
                $grid[] = $cells;
                
                $out .= '<tr class="grid_row"><td>'.$options[$cells[0]]['label'].'</td>';
                
                if(isset($options[$cells[0]]['flags']) && strpos($options[$cells[0]]['flags'], 'has_param') !== FALSE)
                {
                    $out .=  '<td><input data-key="'.$key.'" data-opt="'.$cells[0].'" class="grid_param" type="text" size="5" value="'.(isset($cells[1])?$cells[1]:'').'"/><span class="help">'
                        .(isset($options[$cells[0]]['flags']['help']) ? $options[$cells[0]]['flags']['help'] : '').'</span></td>';
                } else {
                    $out .= '<td>&nbsp;<span class="help">'
                        .(isset($options[$cells[0]]['flags']['help']) ? $options[$cells[0]]['flags']['help'] : '').'</span></td>';
                }
                
                // $out .= '<td>'.form_button('remove_'.$key.'_'.$i, 'X', 'class="remove_grid_row" data-key="'.$key.'" data-opt="'.$cells[0].'" ').'</tr>';
                $out .= '<td><a href="#" class="remove_grid_row" name="remove_'. $key .'_'. $i .'" data-key="'. $key .'" data-opt="'.$cells[0].'">X</a></td></tr>';
            }
            
            $i++;
        }
        
        $out .= '</tbody></table>';

        // $out .= '<h4>Add another rule</h4><br/>'.$dropdown.' '.form_button('addgridrow_'.$key, 'Add', 'id="addgridrow_'.$key.'" class="add_grid_row"');
        $out .= '<h4>Add another rule</h4>'.$dropdown;
        $out .= '<a href="#" name="addgridrow_'. $key .' id="addgridrow_'.$key.' class="add_grid_row">Add</a>';
        
        $out .= '<input type="hidden" name="'.$key.'" value="'.$value.'" />';
        
        $out .= '<script type="text/javascript">';
        $out .= 'bm_grid.options["'.$key.'"] = ' . json_encode($options) . ';';
        $out .= 'bm_grid.help["'.$key.'"] = ' . json_encode($help) . ';';
        $out .= 'bm_grid.data["'.$key.'"] = ' . json_encode($grid) . ';';
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
        $out .= 'bm_grid.bind_events("'.$key.'", "gridrow_'.$key.'");</script>';
        $out .= '</div>';
        
        return $out;
    } // function _render_grid

}
