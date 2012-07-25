<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * @package ProLib
 * @author Isaac Raway <isaac.raway@gmail.com>
 *
 * Copyright (c)2009, 2010. Isaac Raway and MetaSushi, LLC. All rights reserved.
 *
 * This source is commercial software. Use of this software requires a site license for each
 * domain it is used on. Use of this software or any of it's source code without express
 * written permission in the form of a purchased commercial or other license is prohibited.
 *
 * THIS CODE AND INFORMATION ARE PROVIDED "AS IS" WITHOUT WARRANTY OF ANY
 * KIND, EITHER EXPRESSED OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND/OR FITNESS FOR A
 * PARTICULAR PURPOSE.
 *
 **/

class PL_channel_fields {
    /**
     * Class constructor
     */
    function __construct()
    {
        $this->EE = &get_instance();
    }

    /**
     * Get all custom fields defined for a particular field group and site
     *
     * @param $group_id
     * @param  $site_id
     * @return array(field_id => field_object)
     */
    function get_fields($group_id=0, $site_id=0)
    {
        if(!$site_id)
        {
            $site_id = $this->EE->config->item('site_id');
        }

        $result = array();

        if($group_id)
        {
            $this->EE->db->where('group_id', $group_id);
        }

        $fields_query = $this->EE->db
                            ->where('site_id', $site_id)
                            ->get('exp_channel_fields');

        foreach($fields_query->result() as $field)
        {
            $result[$field->field_id] = new PL_ChannelField($field);
        }

        return $result;
    }

    /**
     * Get field
     *
     * @param $group_id
     * @param $field_name
     * @param $site_id
     * @return TRUE/FALSE
     */
    function get_field($group_id, $field_name, $site_id=0)
    {
        if(!$site_id)
        {
            $site_id = $this->EE->config->item('site_id');
        }

        $result = FALSE;

        if($group_id)
        {
            $this->EE->db->where('group_id', $group_id);
        }

        $fields_query = $this->EE->db
                            ->where('site_id', $site_id)
                            ->where('field_name', $field_name)
                            ->get('exp_channel_fields');

        if($fields_query->num_rows() > 0)
        {
            $result = new PL_ChannelField($fields_query->row());
        }

        return $result;
    }



    /**
     * Create a new custom field in the given group
     *
     * @param $group_id
     * @param  $data field data with keys matching the fields of PL_ChannelField
     * @param $site_id
     * @return PL_ChannelField or FALSE on error
     */
    function new_field($group_id, $data, $site_id=0)
    {
        if(!$site_id)
        {
            $site_id = $this->EE->config->item('site_id');
        }

        // Create new field object
        $result = new PL_ChannelField($data);
        $result->group_id = $group_id;
        $result->site_id = $site_id;

        // Remove the field_id so we won't try to insert a record with a bad ID
        unset($result->field_id);

        // Insert the new field record
        $this->EE->db->insert('exp_channel_fields', $result);

        if($this->EE->db->affected_rows() == 1)
        {
            // Save the inserted row ID
            $result->field_id = $this->EE->db->insert_id();

            // Create the needed columns on the exp_channel_data table for this new custom field
            $fields = array(
                'field_id_'.$result->field_id => array('type' => 'text'),
                'field_ft_'.$result->field_id => array('type' => 'tinytext'),
            );

            $this->EE->load->dbforge();
            $forge = &$this->EE->dbforge;

            $forge->add_column('channel_data', $fields);

        } else {
            $result = FALSE;
        }

        return $result;
    }

    /**
     * Check if a field with the given name exists in the field group
     *
     * @param $group_id
     * @param $field_name
     * @return TRUE/FALSE
     */
    function field_exists($group_id, $field_name)
    {
        return $this->EE->db->where(array('group_id' => $group_id, 'field_name' => $field_name))->count_all_results('exp_channel_fields');
    }

}

class PL_ChannelField {
    var $field_id = FALSE;
    var $site_id = FALSE;
    var $group_id = FALSE;
    var $field_name = FALSE;
    var $field_label = FALSE;
    var $field_instructions = FALSE;
    var $field_type = FALSE;
    var $field_list_items = FALSE;
    var $field_pre_populate = FALSE;
    var $field_pre_channel_id = FALSE;
    var $field_related_to = FALSE;
    var $field_related_id = FALSE;
    var $field_related_orderby = FALSE;
    var $field_related_sort = FALSE;
    var $field_related_max = FALSE;
    var $field_ta_rows = FALSE;
    var $field_maxl = FALSE;
    var $field_required = FALSE;
    var $field_text_direction = FALSE;
    var $field_search = FALSE;
    var $field_is_hidden = FALSE;
    var $field_fmt = FALSE;
    var $field_show_fmt = FALSE;
    var $field_order = FALSE;
    var $field_content_type = FALSE;
    var $field_settings = FALSE;

    /**
     * Class constructor
     *
     * @param $row CI database row object
     */
    function __construct($row)
    {
        $this->EE = &get_instance();
        foreach($row as $k => $v)
        {
            if(isset($this->$k))
            {
                $this->$k = $v;
            }
        }
    }

    /**
     * Save the field object back to it's database table
     *
     * @return void
     */
    function save()
    {
        // Save a local reference to the EE object, then remove it prior to saving to prevent
        // the database class from getting confused
        $EE = &$this->EE;
        unset($this->EE);

        // Update the record in the database
        $EE->db->update('exp_channel_fields', $this, array('field_id' => $this->field_id));

        // Restore the EE object reference
        $this->EE = &$EE;
    }
}

