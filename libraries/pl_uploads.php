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

/**
 * Wrapper around exp_upload_prefs table to provide easy to use upload functions.
 *
 */
class PL_uploads {
    var $errors = array();

    function PL_uploads()
    {
        $this->EE = &get_instance();
    }

    /**
     * Get a list of directory upload preferences.
     *
     * @return array of directory upload preferences appropriate for use in form_dropdown()
     */
    function get_upload_prefs()
    {
        $result = array();
        $prefs = $this->_get_upload_preferences();
        foreach($prefs as $upload_pref)
        {
            $result[$upload_pref['id']] = $upload_pref['name'];
        }
        return $result;
    }

    /**
     * Get single upload preferences record.
     *
     * @return object containing pref record
     */
    function get_upload_pref($pref_id)
    {
        $pref_id = (int)$pref_id;
        $upload_pref = $this->_get_upload_preferences(NULL, $pref_id);
        return $upload_pref;
    }

    /**
     * Trigger an upload to an upload preferences directory.
     *
     * @param $pref_id - id of exp_upload_prefs record to upload file to
     * @param $field - name of POST field to upload file from
     *
     * @return FALSE if the operation failed (errors in $this->errors), or an array containing data on the upload:
     *               Array
     *               (
     *                   [filename]    => mypic.jpg
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

        $upload_pref = $this->get_upload_pref($pref_id);

        if ($upload_pref)
        {
            // see http://codeigniter.com/user_guide/libraries/file_uploading.html for more on options we can set here
            // for now just do the minimum to get it to upload a file
            $config = array(
                'upload_path' => $upload_pref['server_path'],
                'allowed_types' => '*',
                'file_name' => $this->make_unique_filename($_FILES[$field]['name'], $upload_pref['server_path']),
                //'max_filename' => '',
                //'encrypt_name' => 'y',
                //'remove_spaces' => 'y',
                //'overwrite' => '',
                'max_size' => $upload_pref['max_size'],
                'max_width' => $upload_pref['max_width'],
                'max_height' => $upload_pref['max_height']
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

    /**
     * Sanitize filename and add a unique time based integer to it.
     */
    function make_unique_filename($filename, $path)
    {
        if(substr($path, -1) != '/') $path .= '/';

        $uniq = floor((time() + rand(1, 500)) / rand(1024, 3897)) + rand(1, 10000);

        $info = pathinfo($filename);
        $filename = preg_replace('/[^A-Za-z0-9]/', '_', $info['filename']);
        $ext = preg_replace('/[^A-Za-z0-9]/', '_', $info['extension']);

        while(file_exists($path.$filename.'_'.$uniq.'.'.$ext))
        {
            $uniq += rand(1,100);
        }

        return $filename.'_'.$uniq.'.'.$ext;
    }

    /**
     * Get Upload Preferences (Cross-compatible between ExpressionEngine 2.0 and 2.4)
     * @param  int $group_id Member group ID specified when returning allowed upload directories only for that member group
     * @param  int $id       Specific ID of upload destination to return
     * @return array         Result array of DB object, possibly merged with custom file upload settings (if on EE 2.4+)
     */
    private function _get_upload_preferences($group_id = NULL, $id = NULL)
    {
        if (version_compare(APP_VER, '2.4', '>='))
        {
            $this->EE->load->model('file_upload_preferences_model');
            return (array)$this->EE->file_upload_preferences_model->get_file_upload_preferences($group_id, $id);
        }

        if (version_compare(APP_VER, '2.1.5', '>='))
        {
            $this->EE->load->model('file_upload_preferences_model');
            $result = $this->EE->file_upload_preferences_model->get_upload_preferences($group_id, $id);
        }
        else
        {
            $this->EE->load->model('tools_model');
            $result = $this->EE->tools_model->get_upload_preferences($group_id, $id);
        }

        // If an $id was passed, just return that directory's preferences
        if ( ! empty($id))
        {
            return $result->row_array();
        }

        // Use upload destination ID as key for row for easy traversing
        $return_array = array();
        foreach ($result->result_array() as $row)
        {
            $return_array[$row['id']] = $row;
        }

        return $return_array;
    }
}