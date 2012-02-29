<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
    
class PL_Hooks {
    
    var $hooks = array();
    
    function __construct()
    {
        $this->EE = &get_instance();
        $this->CI = &get_instance();
    }
    
    /**
     * Shorthand to call hooks implemented by the module. Don't include the package name - it is
     * prefixed automatically. So, calling prolib($this, 'mason')->hook('parse'); would trigger
     * a hook named mason_parse.
     *
     * If used outside of ExpressionEngine, this will call a basic hook registration on the
     * library in order to provide something similar.
     **/
    function hook($hook, &$data)
    {
        global $PROLIB;
        
        $hook = ($PROLIB->package_name ? $PROLIB->package_name.'_' : '').$hook;
        
        if(isset($this->EE) && isset($this->EE->extensions))
        {
            if ($this->EE->extensions->active_hook($hook) === TRUE)
            {
                return $this->EE->extensions->call($hook, $data);
            }
        } else {
            if(isset($this->hooks[$hook]))
            {
                
                foreach($this->hooks[$hook] as $callback)
                {
                    call_user_func_array($callback, array(&$data));
                }
            }
        }
        
        return $data;
    }
    
    /**
     * If used outside of ExpressionEngine, this registers a basic array of hooks to be
     * triggered by the hook() method.
     **/
    function register($hook, $callback)
    {
        if(!isset($this->hooks[$hook]))
        {
            $this->hooks[$hook] = array();
        }
        
        $this->hooks[$hook][] = $callback;
    }

}
