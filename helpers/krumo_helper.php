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