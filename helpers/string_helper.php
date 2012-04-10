<?php


/**
 * Strip a string to be appropriate for use in an ID.
 */
function pf_strip_id($str)
{
    return preg_replace("/[^\:-_a-zA-Z0-9\s]/", "", strip_tags($str));
}
