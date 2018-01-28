<?php

/*
 ----------------------------------------------------------------------
 LICENSE

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License (GPL)
 as published by the Free Software Foundation; either version 2
 of the License, or (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 To read the license please visit http://www.gnu.org/copyleft/gpl.html
 ----------------------------------------------------------------------
*/

/**
 * Form for the quick installation of Drupal
 */

require_once("../class/config.php");
include_once("head.php");

$oci = new m_oci();
$dom = new m_dom();
$sql = new m_mysql();
$quota = new m_quota();

$fields = array(
    'application' => array('post', 'string', ''),
);
getFields($fields);


$fields = $oci->oci_form_fields($application);
getFields($fields);
$all_vars = get_defined_vars();
$oci_vars = array();
foreach ($all_vars as $name => $value) {
    if (in_array($name, array_keys($fields))) {
        $oci_vars[$name] = $value;
    }
}

if (!$application) {
    __('No application chosen.');
    include_once('foot.php');
    exit();
}

if (!$oci->app_is_installable($application)) {
    __('Application not supported: '); print(ehe($application));
    include_once('foot.php');
    exit();
}

$r = $oci->install($application, $oci_vars);
printf('<div class="oci-installation-results"><pre>%s</pre></div>', print_r($r, TRUE));
include_once('foot.php');