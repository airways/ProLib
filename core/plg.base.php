<?php

class PL_base_plg {
    var $plugin_file;
    
    public function init()
    {
        $this->EE = &get_instance();
        $info = pathinfo($this->plugin_file);
        $this->view_dir = $info['dirname'].'/views/';
    }

    public function view($view, $vars)
    {
        extract($vars);
        ob_start();
        include $this->view_dir.$view.'.php';
        $result = ob_get_contents();
        ob_end_clean();
        return $result;
    }
    
    public function form_cp_action_fields()
    {
        $result = '';
        
        // Preserve GET parameters so that the forms reload the actual action page they
        // are presented on properly.
        foreach($_GET as $k => $v)
        {
            if($k != 'workflow_status' && $k != 'workflow_assignment')
            {
                $result .= form_hidden($k, $v);
            }
        }
        return $result;
    }

}
