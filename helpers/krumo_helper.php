<?php

if(!function_exists('krumo')) {
    include('krumo/class.krumo.php');
}

function kexit($data) {
    $_ = debug_backtrace();
    while($d = array_pop($_))
    {
        if ((strToLower($d['function']) == 'kexit'))
        {
            break;
        }
    }
    krumo($data);
    echo '<b>kexit() called from '.$d['file'].' line '.$d['line'].'</b>';
    exit;
}

/**
 * Helper function to remove transient objects that are sometimes attached to objects created by prolib modules.
 * 
 * @param  mixed $data Data to dump out
 */
function pl_krumo($data) {
    $data = pl_krumo_sanitize($data);
    var_dump($data);
}

function pl_krumo_sanitize($data) {
    if(is_array($data)) {
        if(isset($data['__mgr'])) $data['__mgr'] = 'REMOVED';
        if(isset($data['EE'])) $data['EE'] = 'REMOVED';
        if(isset($data['__EE'])) $data['__EE'] = 'REMOVED';
        if(isset($data['__CI'])) $data['__CI'] = 'REMOVED';
        if(isset($data['__prolib'])) $data['__prolib'] = 'REMOVED';
    } elseif(is_object($data)) {
        $data = clone $data;
        if(isset($data->__mgr)) $data->__mgr = 'REMOVED';
        if(isset($data->EE)) $data->EE = 'REMOVED';
        if(isset($data->__EE)) $data->__EE = 'REMOVED';
        if(isset($data->__CI)) $data->__CI = 'REMOVED';
        if(isset($data->__prolib)) $data->__prolib = 'REMOVED';
    }

    if(is_array($data) || is_object($data)) {
        foreach($data as $k => $v) {
            if(is_object($data)) {
               $v = clone $v;
            }
            $data[$k] = pl_krumo_sanitize($v);
        }
    }

    return $data;
}