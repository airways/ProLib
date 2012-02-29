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

require_once 'sfYaml/sfYaml.php';
require_once 'sfYaml/sfYamlDumper.php';
require_once 'sfYaml/sfYamlInline.php';
require_once 'sfYaml/sfYamlParser.php';

function yaml_unserialize($source)
{
    try
    {
        $parser = new sfYamlParser();
        return $parser->parse($source);
    } catch(InvalidArgumentException $e)
    {
        error_log($e->getMessage());
        return FALSE;
    }
}

function yaml_serialize($object, $array_level=1)
{
    $dumper = new sfYamlDumper();
    $yaml = $dumper->dump($object, $array_level);
    return $yaml;

}
