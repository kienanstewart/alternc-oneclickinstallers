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
 * Form for the quick installation of an application.
 */

require_once("../class/config.php");
include_once("head.php");

$oci = new m_oci();

$fields = array(
    'app' => array('get', 'string', ''),
);
getFields($fields);

if (!$app) {
    __('No application chosen.');
    include_once('foot.php');
    exit();
}

if (!$oci->app_is_installable($app)) {
    __('Application not supported: '); print(ehe($app));
    include_once('foot.php');
    exit();
}

$fields = $oci->oci_form_fields($app);
getFields($fields);

?>
<h3><?php __("Install ${app}"); ?></h3>
<?php
if (isset($fatal) && $fatal) {
    include_once("foot.php");
    exit();
}
?>

<?php

print($oci->oci_form($app));

?>

<?php include_once("foot.php"); ?>
