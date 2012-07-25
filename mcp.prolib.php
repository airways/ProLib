<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

if(!file_exists(PATH_THIRD.'prolib/prolib.php'))
{
    echo 'Prolib does not appear to be properly installed. Please place Prolib into your third_party folder.';
    exit;
}

require_once PATH_THIRD.'prolib/prolib.php';
require_once(PATH_THIRD.'prolib/config.php');

if(!defined('ACTION_BASE'))
{
    define('ACTION_BASE', BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=prolib'.AMP);
}

class Prolib_mcp {


    function __construct()
    {
        $this->EE = &get_instance();
        $this->EE->cp->set_variable('cp_page_title', PROLIB_NAME);
    }
    
    function index()
    {
        $prolib_name = PROLIB_NAME;
        $prolib_version = PROLIB_VERSION;
        
        $actions = ACTION_BASE;
        
        return <<<END
        
<p>This is $prolib_name $prolib_version.</p>

<h4>Tests</h4>
<p><a href="$actions&method=test_script">Test Script</a></p>

END;

    }
    
    function test_script()
    {
        require_once(PATH_THIRD.'prolib/tests/test_script.php');
        $test = new Test_Script();
        return $test->run();
    }

}
