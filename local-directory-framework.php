<?php
/**
 * Plugin Name: Local Directory Framework
 * Description: Structured local business directory, monthly rankings, business requests, and advertising management.
 * Version: 0.1.0
 * Author: Northwest Monthly
 * Text Domain: local-directory-framework
 */

if (!defined('ABSPATH')) {
    exit;
}

define('NWMD_DIRECTORY_VERSION', '0.1.0');
define('NWMD_DIRECTORY_PATH', plugin_dir_path(__FILE__));
define('NWMD_DIRECTORY_URL', plugin_dir_url(__FILE__));

require_once NWMD_DIRECTORY_PATH . 'includes/database-schema.php';

/**
 * Install or upgrade the directory database tables.
 */
function nwmd_directory_activate() {

    nwmd_directory_install_schema();

    update_option(
        'nwmd_directory_version',
        NWMD_DIRECTORY_VERSION,
        false
    );
}

register_activation_hook(
    __FILE__,
    'nwmd_directory_activate'
);

/**
 * Apply safe schema upgrades after plugin updates.
 */
function nwmd_directory_maybe_upgrade() {

    $installed_version = get_option(
        'nwmd_directory_version',
        '0.0.0'
    );

    if (
        version_compare(
            $installed_version,
            NWMD_DIRECTORY_VERSION,
            '<'
        )
    ) {
        nwmd_directory_activate();
    }
}

add_action(
    'plugins_loaded',
    'nwmd_directory_maybe_upgrade'
);