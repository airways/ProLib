<?php

class PL_EETemplates {
    public function __construct() {
        global $PROLIB;
        $this->prolib = &$PROLIB;
        $this->EE = &get_instance();
    }

    /**
     * Get a list of EE template group names from the database. These will be used as the options
     * for setting the template group in module settings.
     *
     * @return array of template groups suitable for use in form_dropdown()
     */
    public function get_template_group_names()
    {
        $result = array();
        $query = $this->EE->db->where('site_id', $this->prolib->site_id)->get('exp_template_groups');
        foreach($query->result() as $row)
        {
            $result[$row->group_name] = $row->group_name;
        }
        ksort($result);
        return $result;
    } // function get_template_group_names()

    /**
     * Get a list of template names for the given group name. Used on form settings to specify
     * what template should be used to send notifications.
     *
     * @return array of template names suitable for use in form_dropdown()
     */
    public function get_template_names($group_name)
    {
        $result = array();

        $this->EE->db->where('group_name', $this->EE->db->escape_str($group_name));
        $this->EE->db->where('site_id', $this->EE->config->item('site_id'));
        $query = $this->EE->db->get('template_groups');

        if($query->num_rows > 0)
        {
            $group_id = $query->row()->group_id;
            $sql = "SELECT template_id, template_name FROM exp_templates WHERE group_id = $group_id AND site_id = ".$this->prolib->site_id;
            $query = $this->EE->db->query($sql);
            foreach($query->result() as $row)
            {
                if($row->template_name != 'index')
                    $result[$row->template_name] = $row->template_name;
            }
        }
        return $result;
    } // function get_template_names()

    public function get_template($template_group_name, $template_name)
    {
        //$this->_debug($this->template_group_name);

        $query = $this->EE->db->query($sql = "SELECT group_id FROM exp_template_groups WHERE group_name = '" . $this->EE->db->escape_str($template_group_name) . "' AND site_id = ".$this->prolib->site_id);
        if($query->num_rows() > 0)
        {
            $group_id = $query->row()->group_id;

            //$this->_debug('Template group ID: '.$group_id);

            $sql = "SELECT * FROM exp_templates WHERE group_id = {$group_id} AND template_name = '" . $this->EE->db->escape_str($template_name) . "' AND site_id = ".$this->prolib->site_id;
            $query = $this->EE->db->query($sql);
            if($query->num_rows() > 0)
            {
                $row = $query->row();
                if($row->save_template_file == 'y')
                {
                    // we need to load data from the template file
                    $template_file = $this->EE->config->slash_item('tmpl_file_basepath')
                                    . $this->EE->config->slash_item('site_short_name')
                                    . $this->template_group_name.'.group/'
                                    . $template_name.'.html';

                    //$this->_debug('Template saved as file '.$template_file);

                    $template_data = file_get_contents($template_file);
                } else {
                    //$this->_debug('Template from DB');
                    $template_data = $query->row()->template_data;
                }

                //$this->_debug('Template: '.$template_data);

                return $template_data;
            } else {
                return FALSE;
            }
        } else { // if($query->num_rows() > 0)
            return FALSE;
        }
    } // function get_template()
}
