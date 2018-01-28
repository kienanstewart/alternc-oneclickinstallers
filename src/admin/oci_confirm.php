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

// @TODO validations
$errors = $oci->oci_form_validate($oci_vars);
if (!empty($errors)) {
    print('<div class="validation-wrapper">');
    print('<h3>' . _('Validation Errors') . '</h3>');
    print('<div class="validation-error-summary">' . count($errors) . ' ' . _('validation errors in form') . '</div>');
    foreach ($errors as $delta => $error) {
        printf('<div class="validation-error-wrapper"><span class="validation-error-module">%s</span>&nbsp;:&nbsp;<span class="validation-error-message">%s</span>', $error['module'], $error['message']);
        if (isset($error['data'])) {
            print('<dl class="validation-error-data">');
            foreach ($error['data'] as $name => $value) {
                printf('<dt>%s</dt>', $name);
                printf('<dd>%s</dd>', htmlentities(print_r($value, TRUE)));
            }
            print('</dl>');
        }
        print('</div>');
    }
    print('</div>');
    include_once('foot.php');
    exit();
}
?>
<h3><?php __("Confirm installation of ${application}"); ?></h3>
<?php
if (isset($fatal) && $fatal) {
    include_once("foot.php");
    exit();
}
?>
<form method="post" action="oci_doinstall.php" id="oci-install-<?php print($application); ?>"
        name="oci-install-<?php print($application); ?>" autocomplete="off">
<?php csrf_get(); ?>
<h4><?php __('Summary'); ?></h4>
<ul class="oci-install-summary">
<?php if ($oci_vars['domain'] == '*new*'): ?>
    <li>The domain <?php echo $oci_vars['new_domain_name']; ?> will be created</li>
<?php endif; ?>
<?php if ($oci_vars['sub_domain'] == '*new*'): ?>
    <li>A new sub-domain <?php echo $oci_vars['new_sub_domain_name']; ?> of type&nbsp;
    <?php echo $oci_vars['new_sub_domain_type']; ?> at the directory
    <?php echo $oci_vars['new_sub_domain_path']; ?> will be created.</li>
<?php endif; ?>
<?php if ($oci_vars['db_name'] == '*new*'): ?>
    <li>A new database named <?php echo $oci_vars['new_db_name']; ?> will be created.</li>
<?php endif; ?>
<?php
$d = ($oci_vars['domain'] == '*new*') ? $oci_vars['new_domain_name'] : $oci_vars['domain'] ;
$s = ($oci_vars['sub_domain'] == '*new*') ? $oci_vars['new_sub_domain_name'] : explode(';', $oci_vars['sub_domain'])[1];
?>
<li><?php echo $oci_vars['application']; ?> will be installed at <?php printf('%s.%s', $s, $d); ?>.</li>
<?php // @TODO: Get application specific summary information ?>
</ul>

<h4><?php __('Submitted details'); ?></h4>
<?php
print('<dl>');
foreach($oci_vars as $name => $value) {
    print("<dt>$name</dt>");
    if (isset($value)) {
        $a = htmlentities($value);
    }
    else {
        $a = "<strong>***" . __('undefined') . '</strong>***';
    }
    print("<dd>$a</dd>");
}
print('</dl>');
?>
<h4><?php __('Confirmation'); ?></h4>
<?php foreach ($oci_vars as $name => $value) {
    printf('<input type="hidden" name="%s" value="%s"/>', $name, $value);
}
?>
<input type="button" class="inb cancel" name="cancel" value="<?php __('Cancel'); ?>"/>
<input type="submit" class="inb ok" name="submit" value="<?php __('Install'); ?>"/>
</form>

<?php include_once("foot.php"); ?>
