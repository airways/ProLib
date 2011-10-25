<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
interface Bm_Callback_Interface {
    /**
     * Get simple callback data
     * 
     * Used for conditional prepping.
     * 
     * @abstract
     * @returns string
     */
    public function getData();
}