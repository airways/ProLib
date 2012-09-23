<?php


function pl_show_error($message, $heading = 'Error', $homepage = '')
{

    $message .= '<div class="back_link"><a href="javascript:history.go(-1);">Back</a></div>';

    $EE = &get_instance();
    if($EE->config->item('prolib_error_template'))
    {
        $file = $EE->config->item('prolib_error_template');
    } else {
        $file = APPPATH.'views/errors/error_general.php';
    }
    
    if(file_exists($file) && file_get_contents($file))
    {
        include($file);
    } else {
        echo '<h2>'.$heading.$homepage.'</h2>';
        echo $message;
    }
    
    exit;
}
