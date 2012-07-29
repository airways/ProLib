<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once(PATH_THIRD.'prolib/config.php');

class Prolib_upd {
    var $version = PROLIB_VERSION;

    function __construct()
    {
        $this->EE = &get_instance();
    }

    function install()
    {
        $this->EE->db->insert('modules', array(
            'module_name' => PROLIB_NAME,
            'module_version' => PROLIB_VERSION,
            'has_cp_backend' => 'y',
            'has_publish_fields' => 'n',
        ));
        return TRUE;
    }

    function update($current = '')
    {
        return FALSE;
    }

    function uninstall()
    {
        $this->EE->db->where('module_name', PROLIB_NAME)->delete('modules');
    }
}
