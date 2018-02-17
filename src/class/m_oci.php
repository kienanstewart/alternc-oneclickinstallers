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

class m_oci {

    function hook_menu() {
        global $hooks, $L_ALTERNC_WPCLI_BIN, $L_ALTERNC_DRUSH_BIN;
        $menu = array(
            'title' => _('Quick Install'),
            'ico' => 'images/ocilogo.png',
            'link' => 'toggle',
            'pos' => 11,
            'links' => array(),
        );
        // @TODO invoke a hook to get supported applications
        // need: id (eg. wordpress), weight (to order), ico, and
        // optionally - a different action path.
        // @TODO required binaries for installation: eg, wp-cli, drush
        if ($this->app_is_installable('wordpress')) {
            $menu['links'][] = array(
                'txt' => _('WordPress'),
                'url' => 'oci_install.php?app=wordpress',
                'ico' => 'images/wordpress.png',
            );
        }
        if ($this->app_is_installable('drupal')) {
            $menu['links'][] = array(
                'txt' => _('Drupal'),
                'url' => 'oci_install.php?app=drupal',
                'ico' => 'images/drupal.png',
            );
        }
        if ($menu['links']) {
            return $menu;
        }
    }

    /**
     * Checks to see if requirements are met to install an application.
     *
     * @param $app string
     *  The string identifier of the application (eg. drupal, wordpress).
     */
    function app_is_installable($app) {
        global $hooks;
        $vals = $hooks->invoke('hook_oci_is_installable', array($app));
        foreach ($vals as $class => $rval) {
            if ($rval) {
                return TRUE;
            }
        }
        return FALSE;
    }

    /**
     * Implements hook_oci_is_installable().
     */
    function hook_oci_is_installable($app) {
        global $L_ALTERNC_DRUSH_BIN, $L_ALTERNC_WP_BIN;
        switch ($app) {
            case 'drupal':
                if ($L_ALTERNC_DRUSH_BIN) {
                    return TRUE;
                }
                break;
            case 'wordpress':
                if ($L_ALTERNC_WP_BIN) {
                    return TRUE;
                }
                break;
        }
        return FALSE;
    }

    /**
     * Gets which fields should be loaded for the one click install form.
     *
     * @param $app string
     *
     * @returns array
     *   Array of fields indexed by parameter name. Each value is an array
     *   of ["fetch_type", "data_type", "default"].
     *   fetch_type may be "get" or "post".
     */
    function oci_form_fields($app) {
        global $hooks;
        $fields = array(
            'application' => array('post', 'string', ''), // Used in confirmation.

            'domain' => array('post', 'string', ''),
            'new_domain_name' => array('post', 'string', ''),

            'sub_domain' => array('post', 'string', ''),
            'new_sub_domain_name' => array('post', 'string'),
            'new_sub_domain_path' => array('post', 'string', ''),
            'new_sub_domain_type' => array('post', 'string', 'vhost'),

            'db_name' => array('post', 'string', ''),
            'new_db_name' => array('post', 'string', ''),
            'db_prefix' => array('post', 'string', ''),
        );
        $vals = $hooks->invoke('hook_oci_form_fields', array($app));
        foreach ($vals as $v) {
            $fields = $fields + $v;
        }
        return $fields;
    }

    /**
     * Implements hook_oci_form_fields().
     */
    function hook_oci_form_fields($app) {
        global $mem;
        if ($app == 'drupal') {
            $fields = array(
                'drupal_install_source' => array('post', 'string', ''),
                'drupal_makefile' => array('post', 'string', ''),
                'drupal_core_version' => array('post', 'string', ''),
                'drupal_title' => array('post', 'string', ''),
                'drupal_admin_name' => array('post', 'string', 'admin'),
                // @TODO use user e-mail as a default
                'drupal_admin_mail' => array('post', 'string', ''),
                'drupal_site_name' => array('post', 'string', ''),
                'drupal_site_mail' => array('post', 'string', ''),
            );
            return $fields;
        }
        if ($app == 'wordpress') {
            $fields = array(
                'wordpress_title' => array('post', 'string', ''),
                'wordpress_admin_mail' => array('post', 'string', ''),
                'wordpress_admin_name' => array('post', 'string', 'admin'),
                'wordpress_locale' => array('post', 'string', ''),
            );
            return $fields;
        }
        return array();
    }

    /**
     * Helper function to get the action script path.
     *
     * @returns string
     *   String containing the name of the php script which should be
     *   invoked to run the installation.
     */
    function get_app_action($app) {
        return 'oci_doinstall_' . $app . '.php';
    }


    /**
     * Returns a list of subdomain types which can be used for
     * installing applications into.
     *
     * @returns array
     *   Array of vhost types (strings).
     */
    function allowed_subdomain_types() {
        // Maybe just there the type has target DIRECTORY.
        global $dom;
        $dtypes = $dom->domains_type_lst();
        $types = array();
        foreach ($dtypes as $name => $record) {
            if ($record['target'] == 'DIRECTORY') {
                $types[] = $name;
            }
        }
        return $types;
    }

    /**
     * Provides the a base form for installation of applications.
     *
     * @param $app string
     *   The string identifier of the application (eg. drupal, wordpress).
     */
    function oci_form($app) {
        global $hooks, $quota, $dom, $mysql;
        $form = "<form method=\"post\" action=\"oci_confirm.php\" id=\"oci-install-$app\" name=\"oci-install-$app\" autocomplete=\"off\">";
        ob_start();
        csrf_get();
        $csrf = ob_get_contents();
        ob_end_clean();
        $form .= $csrf;
        $form .= '<h4>' . _('common options') . '</h4>';
        $form .= '<input type="hidden" name="application" value="' . $app . '"/>';

        // Domain
        $new_domain_possible = $quota->cancreate('dom');
        $current_domains = $dom->enum_domains();
        if ($new_domain_possible) {
            $current_domains[] = '*new*';
        }
        if ($current_domains) {
            $form .= '<div id="domain-wrapper">';
            // @TODO on select change which sub-domains are listed.
            $form .= '<label for="domain">' . _('Choose domain to create the site in&nbsp;') . '</label>';
            $form .= '<select name="domain" id="domain">';
            foreach ($current_domains as $d) {
                $form .= "<option value=\"$d\">$d</option>";
            }
            $form .= '</select></div>';
        }
        if ($new_domain_possible) {
            $form .= '<label for="new-domain-name">' . _('Choose new domain name') . '</label>';
            $form .= '<input type="text" name="new_domain_name" id="new-domain-name"/>';
        }

        // Choose a sub-domain or new
        $allowed_subdomain_types = $this->allowed_subdomain_types();
        $form .= '<div id="sub-domain-wrapper">';
        $form .= '<label for="sub_domain">' . _('Choose an existing sub-domain or select "<strong>*new*</strong>" to create a new sub-domain') . '</label>';
        $form .= '<select name="sub_domain" id="sub_domain">';
        foreach ($current_domains as $domain) {
            if ($current_domains == '*new*') {
                continue;
            }
            $dom->lock();
            $d = $dom->get_domain_all($domain);
            $dom->unlock();
            if (!$d) {
                continue;
            }
            foreach ($d['sub'] as $delta => $sub_data) {
                if (!in_array($sub_data['type'], $allowed_subdomain_types)) {
                    continue;
                }
                $form .= "<option value=\"$domain;${sub_data['name']}\">";
                $form .= "${sub_data['name']} (${sub_data['type']} in $domain)</option>";
            }
        }
        $form .= '<option value="*new*">' . _('*new*') . '</div>';
        $form .= '</select></div>';

        // new sub_domain name ; new sub_domain type ; new sub_domain path
        $form .= '<div id="new-sub-domain-name-wrapper">';
        $form .= '<label for="new-sub-domain-name">' . _('Choose new sub-domain name (eg. www)') . '</label>';
        $form .= '<input type="text" name="new_sub_domain_name" id="new-sub-domain-name"/>';
        $form .= '</div>';

        $form .= '<div id="new-sub-domain-type-wrapper">';
        $form .= '<label for="new-sub-domain-type">' . _('Choose a type for the new sub-domain') . '</label>';
        $form .= '<select name="sub_domain_type" id="new-sub-domain-type">';
        foreach ($this->allowed_subdomain_types() as $t) {
            $form .= "<option value=\"$t\">$t</option>";
        }
        $form .= '</select></div>';

        $form .= '<div id="new-sub-domain-path-wrapper">';
        $form .= '<label for="new-sub-domain-path">' . _('Choose a path for the new sub-domain') . '</label>';
        $form .= '<input type="text" name="new_sub_domain_path" id="new-sub-domain-path"/>';
        $form .= '</div>';

        // Database
        $new_db_possible = $quota->cancreate('mysql');

        $dbs = $mysql->get_dblist();
        $form .= '<div id="database-wrapper">';
        $form .= '<label for="db_name">' . _('Choose a database to use') . '</label>';
        $form .= '<select name="db_name" id="db_name">';
        foreach ($dbs as $d) {
            $form .= '<option value="' . $d['db'] . '">' . $d['db'] . '</option>';
        }
        if ($new_db_possible) {
            $form .= '<option value ="*new*">' . _('*new*') . '</option>';
        }
        $form .= '</select>';
        $form .= '</div>';
        $form .= '<div id="new-db-name-wrapper">';
        $form .= '<label for="new-db-name">' . _('Choose name for new database') . '</label>';
        $form .= '<input type="text" name="new_db_name" id="new-db-name"/>';
        $form .= '</div>';

        $form .= '<div id="db-prefix-wrapper">';
        $form .= '<label for="db-prefix">' . _('Choose a database prefix (optional)') . '</label>';
        $form .= '<input type="text" name="db_prefix" id="db-prefix"/>';
        $form .= '</div>';

        // Invoke hook to get app-specific form fields.
        $extra = '';
        $vals = $hooks->invoke('hook_oic_form', array($app));
        foreach ($vals as $v) {
            if ($v) {
                $extra .= $v;
            }
        }
        if ($extra) {
            $form .= '<div class="app-' . $app . '" id="app-wrapper">';
            $form .= '<h4>' . $app . ' ' . _('installation options') . '</h3>';
            $form .= $extra;
            $form .= '</div>';
        }

        // Submit
        // @TODO option to disable confirmation screen?
        $form .= '<input type="submit" class="inb" name="submit" value="' . _('Continue') . '"/>';
        $form  .= "</form>";
        return $form;
    }

    /**
     * Implements hook_oic_form.
     */
    function hook_oic_form($app) {
        $f = '';
        switch ($app) {
        case 'drupal':
            // These fields should be defined in hook_oic_form_fields
            // Choose the install source
            $f .= '<div id="drupal-install-source-wrapper">';
            $f .= '<label for="drupal-install-source">' . _('Choose installation source') . '</label>';
            $f .= '<select name="drupal_install_source" id="drupal-install-source">';
            $f .= '<option value="drupal-core">' . _('Core Version') . '</option>';
            $f .= '<option value="makefile">' . _('Makefile') . '</option>';
            $f .= '</select></div>';
            // Core Version
            $f .= '<div id="drupal-core-version-wrapper">';
            $f .= '<label for="drupal-core-version">' . _('Choose Drupal Core Version') . '</label>';
            $f .= '<select name="drupal_core_version" id="drupal-core-version">';
            $f .= '<option value="8">Drupal 8</option>';
            $f .= '<option value="7">Drupal 7</option>';
            $f .= '</select></div>';
            // Makefile
            $f .= '<div id="drupal-makefile-wrapper">';
            $f .= '<label for="drupal-makefile">' . _('Makefile location (eg. an URL)') . '</label>';
            $f .= '<input type="text" name="drupal_makefile" id="drupal-makefile"/>';
            $f .= '</div>';
            // Site Title
            $f .= '<div id="drupal-title-wrapper">';
            $f .= '<label for="drupal-title">' . _('Site Title') . '</label>';
            $f .= '<input type="text" name="drupal_title" id="drupal-title">';
            $f .= '</div>';
            // Drupal Admin Name
            $f .= '<div id="drupal-admin-name-wrapper">';
            $f .= '<label for="drupal-admin-name">' . _('Admin username') . '</label>';
            $f .= '<input type="text" name="drupal_admin_name" id="drupal-admin-name"/>';
            $f .= '</div>';
            // Drupal Admin Mail
            $f .= '<div id="drupal-admin-maik-wrapper">';
            $f .= '<label for="drupal-admin-mail">' . _('Admin e-mail') . '</label>';
            $f .= '<input type="text" name="drupal_admin_mail" id="drupal-admin-mail"/>';
            $f .= '</div>';
            // Drupal Site Mail
            $f .= '<div id="drupal-site-mail-wrapper">';
            $f .= '<label for="drupal-site-mail">' . _('Site e-mail (used when sending e-mail)') . '</label>';
            $f .= '<input type="text" name="drupal_site_mail" id="drupal-site-mail"/>';
            $f .= '</div>';
            // Drupal Site Name
            $f .= '<div id="drupal-site-name-wrapper">';
            $f .= '<label for="drupal-site-name">' . _('Short site name') . '</label>';
            $f .= '<input type="text" name="drupal_site_name" id="drupal-site-name"/>';
            $f .= '</div>';
            break;
        case 'wordpress':
            // Site Title
            $f .= '<div id="wordpress-title-wrapper">';
            $f .= '<label for="wordpress-title">' . _('Site Title') . '</label>';
            $f .= '<input type="text" name="wordpress_title" id="wordpress-title">';
            $f .= '</div>';
            // wordpress Admin Name
            $f .= '<div id="wordpress-admin-name-wrapper">';
            $f .= '<label for="wordpress-admin-name">' . _('Admin username') . '</label>';
            $f .= '<input type="text" name="wordpress_admin_name" id="wordpress-admin-name"/>';
            $f .= '</div>';
            // wordpress Admin Mail
            $f .= '<div id="wordpress-admin-maik-wrapper">';
            $f .= '<label for="wordpress-admin-mail">' . _('Admin e-mail') . '</label>';
            $f .= '<input type="text" name="wordpress_admin_mail" id="wordpress-admin-mail"/>';
            $f .= '</div>';
            // wordpress locale
            $f .= '<div id="wordpress-locale">';
            $f .= '<label for="wordpress-locale">' . _('Locale (eg. en_CA, fr_CA, or en_US)') . '</label>';
            $f .= '<input type="text" name="wordpress_locale" id="wordpress-locale"/>';
            $f .= '</div>';
            break;
        default:
            break;
        }
        return $f;
    }

    /**
     * Perform validation of the form data.
     *
     * @param array $vars
     *   Array of name/value for the submitted data as received in oci_confirm.php
     *
     * @returns array
     *   Array of errors eg. array(0 => array('module' => 'm_oci', 'message' => '...'), ...)
     */
    function oci_form_validate($app, $vars) {
        global $hooks, $dom, $db;
        $errors = array();
        if ($vars['domain'] == '*new*') {
            if (!$vars['new_domain_name']) {
                $errors[] = array(
                    'module' => 'm_oci',
                    'message' => _('new_domain_name must not be empty when trying to create new domain'),
                );
            }
            // Repeat validation done in m_dom::add_domain
            if (checkfqdn(strtolower($vars['new_domain_name']))) {
                $errors[] = array(
                    'module' => 'm_oci',
                    'message' => _('new_domain_name is syntaxically incorrect'),
                );
            }
            $db->query("SELECT domain FROM forbidden_domains WHERE domain= ? ;", array($domain));
            if ($db->num_rows()) {
                $errors[] = array(
                    'module' => 'm_oci',
                    'message' => _('new_domain_name is forbidden on this server'),
                );
            }
            if ($domain == $L_FQDN || $domain == "www.$L_FQDN") {
                $errors[] = array(
                    'module' => 'm_oci',
                    'message' => _('new_domain_name is the server\'s domain. You cannot host it on your account'),
                );
            }
            $db->query("SELECT compte FROM domaines WHERE domaine= ?;", array($domain));
            if ($db->num_rows()) {
                $errors[] = array(
                    'module' => 'm_oci',
                    'message' => _('The domain already exists'),
                );
            }
            $db->query("SELECT compte FROM `sub_domaines` WHERE sub != \"\" AND concat( sub, \".\", domaine )= ? OR domaine= ?;", array($domain, $domain));
            if ($db->num_rows()) {
                $errors[] = array(
                    'module' => 'm_oci',
                    'message' => _('The domain already exists'),
                );
            }
            // There are so many more... whois, dns, quota
        }

        $sub = '';
        if ($vars['sub_domain'] == '*new*') {
            if (!$vars['new_sub_domain_name']) {
                $errors[] = array(
                    'module' => 'm_oci',
                    'message' => _('new_sub_domain_name must not be empty'),
                );
            }
            if (!$vars['new_sub_domain_path']) {
                $errors[] = array(
                    'module' => 'm_oci',
                    'message' => _('new_sub_domain_path must not be empty'),
                );
            }
            if (!$vars['new_sub_domain_type']) {
                $errors[] = array(
                    'module' => 'm_oci',
                    'message' => _('new_sub_domain_type must not be empty'),
                );
            }
        } else {
            // Make sure the sub-domain is in the current domain
            list($d, $s) = explode(';', $vars['sub_domain']);
            if (trim($d) != $vars['domain']) {
                $errors[] = array(
                    'module' => 'm_oci',
                    'message' => _('sub-domain does not belong to selected domain'),
                    'data' => array(
                        'sub_domain' => $vars['sub_domain'],
                        'domain' => $vars['domain'],
                        'd' => $d,
                        's' => $s,
                    ),
                );
            }
        }

        if ($vars['db_name'] == '*new*') {
            if (!preg_match("#^[0-9a-z]*$#", $vars['new_db_name'])) {
                $error[] = array(
                    'module' => 'm_oci',
                    'message' => _('new_database_name can only contain letters and numbers'),
                );
                $msg->raise("ERROR", "mysql", _("Database name can contain only letters and numbers"));
                return false;
            }

            $len=variable_get("sql_max_database_length", 64);
            if (strlen($vars['db_name']) > $len) {
                $error[] = array(
                    'module' => 'm_oci',
                    'message' => _('new_database_name cannot exceed character length') . ': ' . $len,
                );
            }
            $db->query("SELECT * FROM db WHERE db= ? ;", array($dbname));
            if ($db->num_rows()) {
                $error[] = array(
                    'module' => 'm_oci',
                    'message' => _('new_database_name already exists'),
                );
            }
        }
        $vals = $hooks->invoke('hook_oci_form_validate', array($app, $vars));
        foreach ($vals as $v) {
            if ($v && is_array($v) && !is_empty($v)) {
                $errors = $errors + $v;
            }
        }
        return $errors;
    }

    /**
     * Implements hook_oci_form_validate.
     */
    function hook_oci_form_validate($app, $vars) {
        $errors = array();
        // @TODO Drupal
        // @TODO Wordpress
        return $errors;
    }

    /**
     * Installs an application.
     */
    function install($app, $vars) {
        global $hooks, $dom, $mysql, $msg, $mem, $db, $cuid, $L_ALTERNC_HTML;
        $r = '';

        // Add new domain if necessary.
        if ($vars['domain'] == '*new*') {
            $dom->lock();
            // @TODO don't force dns to on enabled.
            if (!$dom->add_domain($vars['new_domain_name'], 1)) {
                $msg->raise('ERROR', '....');
                return '';
            } else {
                $vars['domain'] = $vars['new_domain_name'];
                unset($vars['new_domain_name']);
            }
            $dom->unlock();
        }

        // Add new sub-domain if necessary.
        if ($vars['sub_domain'] == '*new*') {
            // @TODO special case: handle new_sub_domain_name was a default domain graceully.
            $dom->lock();
            if (!$dom->set_sub_domain($vars['domain'], $vars['new_sub_domain_name'],
                $vars['new_sub_domain_type'], $vars['new_sub_domain_path'])) {
                $msg->raise('ERROR', '......');
                return '';
            } else {
                $vars['sub_domain'] = $vars['new_sub_domain_name'];
                unset($vars['new_sub_domain_name']);
                unset($vars['new_sub_domain_path']);
                unset($vars['new_sub_domain_type']);
            }
            $dom->unlock();
        } else {
            list($d, $s) = explode(';', $vars['sub_domain']);
            if ($d != $vars['domain']) {
                $msg->raise('ERROR', 'm_oci', 'sub_domai %s doesn\'t belong to domain %s',
                            array($vars['sub_domain'], $vars['domain']));
            }
            $vars['sub_domain'] = $s;
        }

        // Add database if necessary.
        if ($vars['db_name'] == '*new*') {
            $login = $mem->user['login'];
            if(!$mysql->add_db("${login}_${vars['new_db_name']}")) {
                $msg->raise('ERROR', '....');
                return '';
            } else {
                $vars['db_name'] = "${login}_${vars['new_db_name']}";
                unset($vars['new_db_name']);
            }
        }

        // Fill out variables to pass on to the install hooks.
        $db->query("SELECT dbu.name,dbu.password, dbs.host FROM dbusers dbu, db_servers dbs, membres m WHERE dbu.uid= ? and enable='ACTIVATED' and dbs.id=m.db_server_id and m.uid= ? and dbu.name = ?;", array($cuid, $cuid, $vars['db_name']));
        if (!$db->num_rows()) {
            $msg->raise('ERROR', 'm_oci', _('Unable to get database information for user "%s", db "%s"'), array($cuid, $vars['db_name']));
            return '';
        }
        $db->next_record();
        $vars['db_user'] = $db->Record['name'];
        $vars['db_pass'] = $db->Record['password'];
        $vars['db_host'] = $db->Record['host'];
        $vars['db_port'] = '3306'; // Seems to be hardcoded in AlternC

        $dom->lock();
        $domain_info = $dom->get_domain_all($vars['domain']);
        $dom->unlock();
        $vars['url'] = ($vars['sub_domain']) ? $vars['sub_domain'] . '.' : '';
        $vars['url'] .= $vars['domain'];
        $msg->raise('INFO', 'm_oci', 'Domain info: %s', print_r($domain_info, TRUE));
        foreach ($domain_info['sub'] as $delta => $sub_info) {
            if($sub_info['name'] != $vars['sub_domain']) {
                continue;
            }
            $vars['path'] = $sub_info['dest'];
        }
        $login = $mem->user['login'];
        $vars['path'] = $L_ALTERNC_HTML . '/' . substr($login, 0, 1) . '/' . $login . $vars['path'];

        // db_user,pass,host,port,name ; url,path
        $msg->raise('INFO', 'm_oci', 'Invoking hook_oci_install for app %s with args %s',
                    array($app, print_r($vars, TRUE)));
        $vals = $hooks->invoke('hook_oci_install', array($app, $vars));
        foreach ($vals as $v) {
            $r .= $v;
        }
        $vals = $hooks->invoke('hook_oci_post_install', array($app, $hook_vars));
        foreach ($vals as $v) {
            $r .= $v;
        }
        return $r;
    }

    /**
     * Implements hook_oci_install.
     */
    function hook_oci_install($app, $vars) {
        if ($app == 'drupal') {
            $r .= $this->_install_drupal($app, $vars);
        } elseif ($app == 'wordpress') {
            $r .= $this->_install_wordpress($app, $vars);
        }
        return '';
    }

    /**
     * Install drupal
     */
    private function _install_drupal($app, $vars) {
        global $L_ALTERNC_DRUSH_BIN, $msg;
        $si_args = array(
            '--site-mail' => $vars['drupal_site_mail'],
            '--site-name' => $vars['drupal_title'],
            '--sites-subdir' => $vars['drupal_site_name'],
            '--root' => $vars['path'],
            '--account-mail' => $vars['drupal_admin_mail'],
            '--account-name' => $vars['drupal_admin_name'],
            '--db-prefix' => $vars['db_prefix'],
            '--db-url' => "mysql://${vars['db_user']}:${vars['db_pass']}@${vars['db_host']}:${vars['db_port']}/${vars['db_name']}",
        );
        $r = '';
        if ($vars['drupal_makefile']) {
            $cmd = sprintf("$_ALTERNC_DRUSH_BIN make --concurrency=5 %s %s",
                           $vars['drupal_makefile'],
                           $vars['path']);
            $msg->raise('INFO', 'm_oci', 'Running command: %s', array($cmd));
            $r .= shell_exec($cmd . ' 2>&1');
        }
        if ($vars['drupal_core_version']) {
            $version = 'drupal-' . $vars['drupal_core_version'];
            $cmd = sprintf("$L_ALTERNC_DRUSH_BIN dl %s %s %s --yes",
                           escapeshellarg($version),
                           '--destination=' . escapeshellarg($vars['path']),
                           '--drupal-project-rename="."'
            );
            $msg->raise('INFO', 'm_oci', 'Running command: %s', array($cmd));
            $r .= shell_exec($cmd . ' 2>&1');
        }

        $si_arg = '';
        foreach ($si_args as $name => $value) {
            if (!$value) {
                continue;
            }
            $si_arg .= "$name=" . escapeshellarg($value) . ' ';
        }
        $si_arg .= ' --yes'; // . ' ' . escapeshellarg($vars['drupal_site_name']);
        $msg->raise('INFO', 'm_oci', _('Starting Drupal installation with arguments: %s'), array($si_arg));
        // @FIXME This seems to use an insane about of memory and gets OOM killed.
        $r .= shell_exec("$L_ALTERNC_DRUSH_BIN si $si_arg 2>&1");
        return $r;
    }

    /**
     * Install wordpress
     */
    private function _install_wordpress($app, $vars) {
        global $L_ALTERNC_WP_BIN, $msg;
        $dl = array(
            '--path' => $vars['path'],
            '--locale' => $vars['wordpress_locale'],
        );
        $dl_arg = '';
        foreach ($dl as $n => $v) {
            if ($v) {
                $dl_arg .= " $n=" . escapeshellarg($v);
            }
        }
        $msg->raise('INFO', 'm_oci', 'Running command: %s',
                    array("$L_ALTERNC_WP_BIN core download $dl_arg"));
        $r = shell_exec("$L_ALTERNC_WP_BIN core download $dl_arg 2>&1");
        $cfg = array(
            '--path' => $vars['path'],
            '--dbname' => $vars['db_name'],
            '--dbuser' => $vars['db_user'],
            '--dbhost' => $vars['db_host'],
            '--dbpass' => $vars['db_pass'],
            '--locale' => $vars['wordpress_locale'],
        );

        $cfg_arg = '';
        foreach ($cfg as $n => $v) {
            if (!$v) {
                continue;
            }
            $cfg_arg .= ' ' . "$n=" . escapeshellarg($v);
        }
        $msg->raise('INFO', 'm_oci', 'Running command: %s',
                    array("$L_ALTERNC_WP_BIN config create $cfg_arg"));
        $r .= shell_exec("$L_ALTERNC_WP_BIN config create $cfg_arg 2>&1");

        $inst = array(
            '--path' => $vars['path'],
            '--url' => $vars['url'],
            '--title' => $vars['wordpress_title'],
            '--admin_email' => $vars['wordpress_admin_mail'],
            '--admin_name' => $vars['wordpress_admin_name'],
        );
        $inst_arg = '';
        foreach ($inst as $n => $v) {
            if (!$v) {
                continue;
            }
            $inst_arg .= ' ' . "$n=" . escapeshellarg($v);
        }
        $msg->raise('INFO', 'm_oci', 'Running command: %s',
                    array("$L_ALTERNC_WP_BIN core install $inst_arg"));
        $r .= shell_exec("$L_ALTERNC_WP_BIN core install $inst_arg 2>&1");
        return $r;
    }
}
