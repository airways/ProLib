<?php
/**
 * MasonData shim. This basically rewrites all queries that would have gone to exp_matrix_data,
 * directing them to exp_mason_data instead.
 *
 * @package default
 * @author Isaac Raway
 **/
class DB_MasonData_Shim {
    /**
     * Create the shim. Remember to assign this object to the local EE db reference:
     *
     *   $this->EE->db = new DB_MasonData_Shim($this->EE->db);
     *
     * Call $this->EE->db->_remove_shim() to restore the original DB object.
     *
     * @return void
     * @author Isaac Raway
     **/
    function __construct()
    {
        $EE = &get_instance();
        $this->__db = &$EE->db;
        $EE->db = &$this;
    }
    
    /**
     * Remove the shim from the system.
     *
     * @return void
     * @author Isaac Raway
     **/
    function _remove_shim()
    {
        $EE = &get_instance();
        $EE->db = &$this->__db;
    }
    
    function where($key, $value = NULL, $escape = TRUE)
    {
        $this->__db->where($key, $value, $escape);
        // playa calls where()->update() so we need to return the shim from where, but not elsewhere
        return $this;
    }
    
    function select($select = '*', $escape = NULL)
    {
        return $this->__db->select($select, $escape);
    }
    
    function where_in($key = NULL, $values = NULL)
    {
        return $this->__db->where_in($key, $values);
    }
    
    function get($table = '', $limit = null, $offset = null)
    {
        return $this->__db->get($table, $limit, $offset);
    }
    
    function delete($table = '', $where = '', $limit = NULL, $reset_data = TRUE)
    {
        return $this->__db->delete($table, $where, $limit, $reset_data);
    }
    
    function update($table = '', $set = NULL, $where = NULL, $limit = NULL)
    {
        if($table == 'matrix_data') {
            $table = 'mason_data';
            foreach($this->__db->ar_where as $i => $w)
            {
                $this->__db->ar_where[$i] = str_replace('row_id', 'block_id', $this->__db->ar_where[$i]);
            }
        }
        return $this->__db->update($table, $set, $where, $limit);
    }
    
    function insert($table = '', $set = NULL)
    {
        return $this->__db->insert($table, $set);
    }
} // END DB_MasonData_Shim