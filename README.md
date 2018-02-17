Adds a panel to quick-install certain CMSs in AlternC. This module provides support for WordPress and Drupal.

Support for other CMSs may be added by other AlternC plugins. See "Extending" for more information.

# Installation

## Pre-requisites

For [WordPress][1]: Install [WP-CLI](https://wp-cli.org/ "WP-CLI home page")

For [Drupal][2]: Install [Drush](https://github.com/drush-ops/drush/ "Drush on GitHub")

## Manual Install

`
make install
`

If AlternC is installed in another directory, use something like: `
ALTERNC_BASE_PATH=/your/path/alternc/panel/ make install
`

# Configuration

Once installed, add the Drush or WP-CLI paths to /etc/alternc/locals.sh. These need to be accessible within the basedir restrictions of the AlternC panel.

```
ALTERNC_DRUSH_BIN=/usr/local/bin/drush
ALTERNC_WPCLI_BIN=/usr/local/bin/wp

# Option to limit the One-Click Installer menu to users who have 'su' on their
# account. Set to 1 to enable that restriction. Default is 0 (no restriction).
OCI_REQUIRE_SU=0
```

If they are not configured, the links for installation will be disabled. If no
CMSs are configured, the quick links menu item will not be displayed.

# Extending

A number of hooks are available to modify the install form and run the actual install. Drupal and WordPress are implemented as examples that could done in another module easily.

# Roadmap

This module is basically at a 'proof of concept' point. For a first proper release, the following should probably be added:

* make sure form content and variables are properly escaped when passed between scripts
* user interface / form cleanup
  * hide form elements based on choices
  * sub-domain list changing based on chosen domain
  * hide un-necessary detail
* fix localization in messages
* tests

Nice to haves:

* threaded install script so user feedback doesn't have to wait X minutes until the shell scripts finish

# Copyright & License

2018 Kienan Stewart <kienan@koumbit.org>

Licensed under the GNU General Public License version 2.0 or later. See LICENSE for the full license text.
