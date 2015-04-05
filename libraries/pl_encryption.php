<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * @package ProLib
 * @author Isaac Raway <isaac.raway@gmail.com>
 * @version 0.1
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

class PL_Encryption {

    var $field_encryption_disabled = array();

    /* ------------------------------------------------------------
     * Encryption API
     *
     * Ensures uniform use of the encrypt library
     * ------------------------------------------------------------ */

    function __construct()
    {
        $this->EE = &get_instance();
    }

    /**
     * Encrypt an array of values through the CI encrypt class.
     *
     * @param  $data - simple string values to encrypt
     * @return array
     */
    function encrypt_values($data)
    {
        if(is_array($data))
        {
            $result = array();
        } else {
            $result = new stdClass();
        }

        $this->EE->load->library('encrypt');
        foreach($data as $k => $v)
        {
            if(array_search($k, $this->field_encryption_disabled) !== FALSE)
            {
                if(is_array($data))
                {
                    $result[$k] = $v;
                } else {
                    $result->{$k} = $v;
                }
            } else {
                if(is_array($data))
                {
                    $result[$k] = $this->EE->encrypt->encode($v);
                } else {
                    $result->{$k} = $this->EE->encrypt->encode($v);
                }
            }
        }
        return $result;
    } // function encrypt_values()

    /**
     * Decrypt an array of values through the CI encrypt class.
     *
     * @param  $data - simple string values to decrypt
     * @return array
     */
    function decrypt_values($data)
    {
        if(is_array($data))
        {
            $result = array();
        } else {
            $result = new stdClass();
        }

        $this->EE->load->library('encrypt');
        $mcrypt_installed = function_exists('mcrypt_encrypt');
        foreach($data as $k => $v)
        {
            if(array_search($k, $this->field_encryption_disabled) !== FALSE)
            {
                if(is_array($data))
                {
                    $result[$k] = $v;
                } else {
                    $result->{$k} = $v;
                }
            } else {
                // properly encrypted strings should have == at the end of their values
                // unless they are XOR encoded, which is only used if mcrypt isn't installed
                if(($mcrypt_installed && substr($v, -1) == '=') || !$mcrypt_installed)
                {
                    if(is_array($data))
                    {
                        $result[$k] = $this->EE->encrypt->decode($v);
                    } else {
                        $result->{$k} = $this->EE->encrypt->decode($v);
                    }
                } else {
                    if(is_array($data))
                    {
                        $result[$k] = $v;
                    } else {
                        $result->{$k} = $v;
                    }
                }
            }
        }
        return $result;
    } // function decrypt_values()
}

/**
 * Vault API
 *
 * Store data in a temporary table, with a SHA1 hash representing it. Note that this
 * class should not be considered secure storage unless the application using it
 * forces the encrypt library to be loaded and forces a unique encryption_key to
 * be set.
 *
 * If encryption is not available, the values of objects stored in the vault will
 * simply be Base64 encoded.
 *
 * Also note, new with ProLib 0.87, the vault table will NOT be created unless
 * something needs to be written into it. You should NEVER query the table directly,
 * always use this class to access it's contents.
 **/

class PL_Vault {
    private $checked_table = FALSE;

    public function __construct($package)
    {
        $this->EE = &get_instance();
        $this->package = $package;
        
    }

    private function check_table($create=TRUE)
    {
        // We already created the table, or already found it once, return true immediately
        if($this->checked_table) return TRUE;

        $this->table = $this->package.'_vault';

        if(!$this->EE->db->table_exists($this->table))
        {
            if($create)
            {
                $this->EE->load->dbforge();
                $forge = &$this->EE->dbforge;

                $fields = array(
                    'vault_id'          => array('type' => 'int', 'constraint' => '10', 'unsigned' => TRUE, 'auto_increment' => TRUE),
                    'site_id'           => array('type' => 'int', 'constraint' => '4', 'default' => '1'),
                    'author_id'         => array('type' => 'int', 'constraint' => '11'),
                    'hash'              => array('type' => 'varchar', 'constraint' => '128'),
                    'data'              => array('type' => 'blob'),
                    'expire'            => array('type' => 'int'),
                );

                $forge->add_field($fields);
                $forge->add_key('vault_id', TRUE);
                $forge->add_key('hash');

                $forge->create_table($this->table);

                // Table didn't exist but we created it, return true
                $result = TRUE;
            } else {
                // Table doesn't exist and we were asked not to create it, return false
                $result = FALSE;
            }
        } else {
            // Table already existed, return true
            $result = TRUE;
        }

        // If the table existed or we just created it...
        if($result)
        {
            // delete expired items - if expire is 0, then the item never expires!
            $this->EE->db->where(array('expire <' => time(), 'expire !=' => 0))->delete($this->table);

            // Don't do it again
            $this->checked_table = TRUE;
        }

        return $result;
    }

    /**
     * Store a data object in the vault.
     *
     * @param  $data - array of simple strings to store
     * @return $string
     */
    function put($data, $expires=TRUE, $hash=FALSE)
    {
        $this->check_table();

        if(isset($this->EE->encrypt))
        {
            $data = base64_encode($this->EE->encrypt->encode(serialize($data)));
        } else {
            $data = base64_encode(serialize($data));
        }

        if($hash === FALSE)
        {
            $hash = sha1($data);
        }
        
        if($expires)
        {
            $expire = strtotime(date("Y-m-d h:i", time()) . " +1 day");
        } else {
            $expire = 0;
        }
        $this->EE->db->insert($this->table, array('hash' => $hash, 'data' => $data, 'expire' => $expire));

        return $hash;
    }

    /**
     * Get an object from the store.
     *
     * @param  $hash - hash of data to get
     * @return array / FALSE
     */
    function get($hash)
    {
        // We can only get a value if the table exists, but we don't want to create a blank table
        // just to find nothing in it, return false immediately if the table isn't already there
        if(!$this->check_table(FALSE)) return FALSE;

        $query = $this->EE->db->where('hash', $hash)->get($this->table);
        if($query->num_rows() > 0)
        {
            $data = $query->row();
            if(isset($this->EE->encrypt))
            {
                $data = unserialize($this->EE->encrypt->decode(base64_decode($data->data)));
            } else {
                $data = unserialize(base64_decode($data->data));
            }
            return $data;
        } else {
            return FALSE;
        }
    }

    /**
     * Delete an object from the store.
     *
     * @param  $hash - hash of data to get
     * @return none
     */
    function delete($hash)
    {
        // We can only delete if the table exists, but don't want to create it now if it doesn't
        if($this->check_table(FALSE))
        {
            $this->EE->db->where(array('hash' => $hash))->delete($this->table);
        }
    }
}

