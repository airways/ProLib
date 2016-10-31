<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * @package ProLib
 * @author Isaac Raway <isaac.raway@gmail.com>
 *
 * Copyright (c)2012. Isaac Raway and MetaSushi, LLC. All rights reserved.
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

if(!class_exists('PL_Members')) {
class PL_Members {
    function __construct()
    {
        global $PROLIB;
        $this->EE = &get_instance();
        $this->prolib = &$PROLIB;
    }
    
    public function get_member($member_id)
    {
        $result = array();
        $query = $this->EE->db->get_where('exp_members', array('member_id' => $member_id));
        if($query->num_rows() > 0) {
            return $query->row();
        } else {
            return NULL;
        }
    }

    /**
     * param $mode full | username - full returns full object, username (or any other
     *                               field) returns only that field
     */
    function get_members($mode='full')
    {
        $result = array();
        $members = $this->EE->db->get_where('exp_members');
        foreach($members->result() as $member)
        {
            if($mode == 'full')
            {
                $result[$member->member_id] = $member;
            } else {
                $result[$member->member_id] = $member->$mode;
            }
        }
        return $result;
    }
    
    /**
     * param $mode full | group_title - full returns full object, group_title (or any 
     *                                  other field) returns only that field
     */
    function get_groups($mode='full')
    {
        $result = array();
        $groups = $this->EE->db->get_where('exp_member_groups');
        foreach($groups->result() as $group)
        {
            if($mode == 'full')
            {
                $result[$group->group_id] = $group;
            } else {
                $result[$group->group_id] = $group->$mode;
            }
        }
        return $result;
    }

    /**
     * param $group_id - group to get list of members from
     * param $mode full | username - full returns full object, username (or any other
     *                               field) returns only that field
     */
    function get_group_members($group_id, $mode='full')
    {
        $result = array();
        $members = $this->EE->db->where('group_id', $group_id)->get('exp_members');
        foreach($members->result() as $member)
        {
            if($mode == 'full')
            {
                $result[$member->member_id] = $member;
            } else {
                $result[$member->member_id] = $member->$mode;
            }
        }
        return $result;
    }

}} // class PL_Members

