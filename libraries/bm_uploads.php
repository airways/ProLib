<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * @package ProLib
 * @author Isaac Raway <isaac@metasushi.com>
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

/**
 * Wrapper around exp_upload_prefs table to provide easy to use upload functions.
 *
 */
class Bm_uploads {
    var $errors = array();
    
    function Bm_uploads()
    {
        $this->EE = &get_instance();
    }

    /**
     * Get a list of directory upload preferences from exp_upload_prefs.
     *
     * @return array of directory upload preferences appropriate for use in form_dropdown()
     */
    function get_upload_prefs()
    {
        $query = $this->EE->db->query($sql = "SELECT * FROM exp_upload_prefs");

        $result = array();
        foreach($query->result() as $row)
        {
            $result[$row->id] = $row->name; 
        }

        return $result;
    }

    /**
     * Get single record from exp_upload_prefs
     *
     * @return object containing pref record
     */
    function get_upload_pref($pref_id)
    {
        $pref_id = (int)$pref_id;
        
        $query = $this->EE->db->query($sql = "SELECT * FROM exp_upload_prefs WHERE id = $pref_id");

        return $query->row();
    }

    /**
     * Trigger an upload to a exp_upload_prefs directory.
     *
     * @param $pref_id - id of exp_upload_prefs record to upload file to
     * @param $field - name of POST field to upload file from
     *  
     * @return FALSE if the operation failed (errors in $this->errors), or an array containing data on the upload:
     *               Array
     *               (
     *                   [file_name]    => mypic.jpg
     *                   [file_type]    => image/jpeg
     *                   [file_path]    => /path/to/your/upload/
     *                   [full_path]    => /path/to/your/upload/jpg.jpg
     *                   [raw_name]     => mypic
     *                   [orig_name]    => mypic.jpg
     *                   [client_name]  => mypic.jpg
     *                   [file_ext]     => .jpg
     *                   [file_size]    => 22.2
     *                   [is_image]     => 1
     *                   [image_width]  => 800
     *                   [image_height] => 600
     *                   [image_type]   => jpeg
     *                   [image_size_str] => width="800" height="200"
     *               )
     */
    function handle_upload($pref_id, $field='userfile', $required=TRUE)
    {
        $this->errors = array();
        
        if(!isset($_FILES[$field]['name']) || !$_FILES[$field]['name']) {
            // file not provided
            if(!$required) {
                return TRUE;
            }
        }
        
        $query = $this->EE->db->query($sql = "SELECT * FROM exp_upload_prefs WHERE id = '".$this->EE->db->escape_str($pref_id)."'");

        if ($query->num_rows() > 0)
        {
            $dir = $query->row();
            // see http://codeigniter.com/user_guide/libraries/file_uploading.html for more on options we can set here
            // for now just do the minimum to get it to upload a file
            $config = array(
                'upload_path' => $dir->server_path,
                'allowed_types' => '*',
                //'file_name' => '',
                //'max_filename' => '',
                //'encrypt_name' => '',
                //'remove_spaces' => '',
                //'overwrite' => '',
                'max_size' => $dir->max_size,
                'max_width' => $dir->max_width,
                'max_height' => $dir->max_height
            );

            $this->EE->load->library('upload', $config);

            if(!$this->EE->upload->do_upload($field))
            {
                $this->errors = array_merge($this->errors, $this->EE->upload->error_msg);
                return FALSE;
            } else {
                return $this->EE->upload->data();
            }
        } else {
            $this->errors[] = "Invalid upload path";
            return FALSE;
        }

    }

    /**
     * Implode an errors array as an UL.
     * @param $errors array of errors to implode
     * @param $start starting markup, an ul with class fieldErrors by default
     * @param $end ending markup, closing ul tag by default
     * @param $item_start starting markup for each item, starting li tag by default
     * @param $item_end ending markup for each item, ending li tag by default
     * @param $nl code used to insert newlines between each element in the markup, set to a blank
     *        string to prevent newlines; "\n" by default
     */
    function implode_errors_array($errors, $start = '<ul class="fieldErrors">', $end = '</ul>', $item_start = '<li>', $item_end = '</li>', $nl = "\n")
    {
        $result = $start.$nl;

        foreach($errors as $error)
        {
            $result .= $item_start.$error.$item_end.$nl;
        }

        $result .= $end.$nl;

        return $result;
    }
}