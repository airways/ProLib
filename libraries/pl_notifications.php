<?php


class PL_Notifications {
    /**
     * Handle manager for accessing template data
     * @var PL_handler_mgr
     */
    protected $template_mgr;
    public $debug = TRUE;
    public $debug_str = '<b>Debug Output</b><br/>';
    public $var_pairs = array();
    public $special_attachments = array();
    public $parse_ee_tags = TRUE;
    protected $template_group_name;
    protected $default_from_address;
    protected $default_from_name;
    protected $default_reply_to_address;
    protected $default_reply_to_name;
    protected $hook_prefix = 'prolib';
    
    function __construct()
    {
        prolib($this, $this->hook_prefix);
        $this->mgr = new PL_handle_mgr();
    } // function __construct()

    private function init() {
        $this->EE->load->library('parser');
        $this->EE->load->library('template');
        $this->EE->load->helper('text');
    }

    protected function _debug($msg, $escape=TRUE)
    {

        $this->debug_str .= ($escape ? htmlentities($msg) : $msg ) . '<br/>';
    }

    public function clear_attachments()
    {
        $this->special_attachments = array();
    }
    
    public function special_attachment($filename)
    {
        $this->special_attachments[] = $filename;
    }
    
    public function enabled_parse_ee_tags($val)
    {
        $this->parse_ee_tags = $val;
    }
    
    public function send_notification($template_name, &$model, &$data, $subject, $notification_list, $reply_to=FALSE, $reply_to_name=FALSE, $send_attachments=FALSE, $driver=FALSE)
    {
        return $this->send_notification_email('custom', $template_name, $model, $data, $subject, $notification_list, $reply_to, $reply_to_name, $send_attachments, $driver);
    }
    
    public function send_notification_email($type, $template_name, &$model, &$data, $subject, $notification_list, $reply_to=FALSE, $reply_to_name=FALSE, $send_attachments=FALSE, $driver=FALSE)
    {
        $this->init();

        $result = FALSE;
        $template = $this->prolib->pl_eetemplates->get_template($this->template_group_name, $template_name);


        if(is_object($data)) {
            $data = (array)$data;
            if($this->mgr) {
                $data = $this->mgr->remove_transitory($data);
            }
        }

        $this->EE->pl_email->clear(TRUE);
        
        if($template)
        {
            // parse data from the entry
            $this->_debug($template);
            // $message = $this->EE->parser->parse_string($template, $data, TRUE);
            // $subject = $this->EE->parser->parse_string($subject, $data, TRUE);
// echo "<b>_send_notifications TEMPLATE PARSING</b>";

            if(!isset($this->EE->TMPL)) {
                if(!class_exists('EE_Template')) {
                    $this->EE->load->helper('text');
                    $this->EE->load->library('Template');
                }
                $this->EE->TMPL = new EE_Template();
                $clearTMPL = TRUE;
            } else {
                $clearTMPL = FALSE;
            }
            
            $message = $this->EE->pl_parser->parse_variables_ex(array(
                'rowdata' => $template,
                'row_vars' => $data,
                'pairs' => $this->var_pairs,
            ));

            $subject = $this->EE->pl_parser->parse_variables_ex(array(
                'rowdata' => $subject,
                'row_vars' => $data,
                'pairs' => $this->var_pairs,
            ));

            // parse the template for EE tags, conditionals, etc.
            if($this->parse_ee_tags)
            {
                $this->_debug('Parsing EE tags...');
                $oldTMPL = $this->EE->TMPL;
                
// var_dump($this->EE->TMPL);
                $this->EE->TMPL = new EE_Template();
                $this->EE->TMPL->template = $message;
                $this->EE->TMPL->template = $this->EE->TMPL->parse_globals($this->EE->TMPL->template);
                $this->EE->TMPL->parse($message);
    
                // final output to send
                $this->EE->TMPL->final_template = $this->EE->TMPL->parse_globals($this->EE->TMPL->final_template);
                $message = $this->EE->TMPL->final_template;
                
                $this->EE->TMPL = $oldTMPL;
                if($clearTMPL) {
                    unset($this->EE->TMPL);
                }
// var_dump($this->EE->pl_parser->variable_prefix);
// var_dump($data);
// echo "<pre>";
// echo htmlentities($message);
// exit;
            } else {
                $this->_debug('Not parsing EE tags');
            }
            $this->_debug($message);
            $result = TRUE;
            
            if($driver)
            {
                $this->_debug('Calling driver->prep_notifications');
                $result = $driver->prep_notifications($this, $type, $template_name, $model, $data, $subject, $notification_list, $reply_to, $reply_to_name, $send_attachments, $result);
                $this->_debug('Result after driver->prep_notifications - ' . ($result ? 'okay' : 'failed'));
            }
            
            if($result)
            {
                foreach($notification_list as $to_email)
                {
                    $this->EE->pl_email->PL_initialize($this->EE->formslib->prefs->ini('mailtype'));
    
                    if($this->default_from_address)
                    {
                        $this->EE->pl_email->from($this->default_from_address, $this->default_from_name);
                    }
    
                    if($reply_to)
                    {
                        if($reply_to_name)
                        {
                            $this->EE->pl_email->reply_to($reply_to, $reply_to_name);
                        } else {
                            $this->EE->pl_email->reply_to($reply_to);
                        }
                    } else {
                        // use the form's reply-to email and name if they have been set
                        if(isset($model) && trim($model->reply_to_address) != '')
                        {
                            if(trim($model->reply_to_name) != '')
                            {
                                $this->EE->pl_email->reply_to($model->reply_to_address, $model->reply_to_name);
                            } else {
                                $this->EE->pl_email->reply_to($model->reply_to_address);
                            }
                        } elseif($this->default_reply_to_address) {
                            // use the default reply-to address if it's been set
                            $this->EE->pl_email->reply_to($this->default_reply_to_address, $this->default_reply_to_name);
                        }
                    }
    
                    // Only normal forms can have files uploaded to them
                    if(isset($model) && (isset($model->form_type) && $model->form_type == 'form')
                                     || (isset($model->has_files) && $model->has_files))
                    {
                        // Attach files
                        if($send_attachments)
                        {
                            foreach($model->fields() as $field)
                            {
                                if($field->type == 'file')
                                {
                                    $upload_pref = $this->EE->pl_uploads->get_upload_pref($field->upload_pref_id);
                                    if ($upload_pref && file_exists($upload_pref['server_path'].$data[$field->field_name]))
                                    {
                                        $this->EE->pl_email->attach($upload_pref['server_path'].$data[$field->field_name]);
                                    }
                                }
                            }
                        }
                        
    
                        foreach($this->special_attachments as $filename)
                        {
                            $this->EE->pl_email->attach($filename);
                        }
                    }
                    
                    $this->_debug('To: '.$to_email);
                    $this->EE->pl_email->to($to_email);
                    $this->EE->pl_email->subject($subject);
    
                    // We need to call entities_to_ascii() for text mode email w/ entry encoded data.
                    // $message will automatically have {if plain_email} and {if html_email} handled inside the pl_email class
                    // The message will also be automatically stripped of markup for the plain text version since we are not
                    // providing an explicit alternative, in which case a lack of a check for either of those variables will
                    // still generate a passable text email if the markup was not totally reliant on images.
                    //$this->EE->pl_email->message(entities_to_ascii($message));
                    $this->EE->pl_email->message($message);
    
                    $this->EE->pl_email->send = TRUE;
                    if ($this->EE->extensions->active_hook($this->hook_prefix.'_notification_message') === TRUE)
                    {
                        $this->EE->extensions->call($this->hook_prefix.'_notification_message', $type, $model, $this->EE->pl_email, $this);
                        if($this->EE->extensions->end_script) return;
                    }
                    
                    $this->_debug('Should I send now? '.($this->EE->pl_email->send ? 'YES' : 'NO'));

                    if($this->EE->pl_email->send)
                    {
                        $this->_debug('Sending...');
                        $this->_debug('Protocol: '.$this->EE->config->item('mail_protocol'));
                        $result = $result && $this->EE->pl_email->Send();
                        if(!$result) {
                            $this->_debug('<b>EE send returned failure</b><br/>', false);
                            $this->_debug($this->EE->pl_email->print_debugger(), false);

                        } else {
                            $this->_debug("***** SENT *****");
                        }
                    }
    
                }
            }
        }

        
            echo $this->debug_str;
        
        return $result;
    } // function send_notification_email()

    

}
