<?php
/**
 * Plugin Name: Local Directory Framework
 * Description: Structured local business directory, monthly rankings, business requests, and advertising management.
 * Version: 0.1.13
 * Author: Northwest Monthly
 * Text Domain: local-directory-framework
 */

if (!defined('ABSPATH')) {
    exit;
}

define('NWMD_DIRECTORY_VERSION', '0.1.13');
define('NWMD_DIRECTORY_PATH', plugin_dir_path(__FILE__));
define('NWMD_DIRECTORY_URL', plugin_dir_url(__FILE__));

require_once NWMD_DIRECTORY_PATH . 'includes/content-types.php';
require_once NWMD_DIRECTORY_PATH . 'includes/default-data.php';
require_once NWMD_DIRECTORY_PATH . 'includes/demo-data.php';
require_once NWMD_DIRECTORY_PATH . 'includes/database-schema.php';
require_once NWMD_DIRECTORY_PATH . 'includes/business-details.php';
require_once NWMD_DIRECTORY_PATH . 'includes/business-index.php';
require_once NWMD_DIRECTORY_PATH . 'includes/business-sources-admin.php';
require_once NWMD_DIRECTORY_PATH . 'includes/business-csv-import-validation.php';
require_once NWMD_DIRECTORY_PATH . 'includes/business-csv-import-execution.php';
require_once NWMD_DIRECTORY_PATH . 'includes/business-csv-import-admin.php';
require_once NWMD_DIRECTORY_PATH . 'includes/frontend.php';
require_once NWMD_DIRECTORY_PATH . 'includes/rankings-admin.php';
require_once NWMD_DIRECTORY_PATH . 'includes/ranking-entries-admin.php';

/**
 * Install or upgrade the directory database tables.
 */
function nwmd_directory_activate() {

    nwmd_directory_register_content_types();
    nwmd_directory_install_schema();
    nwmd_directory_install_default_terms();
    nwmd_directory_install_demo_data();
    flush_rewrite_rules();

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
    'init',
    'nwmd_directory_maybe_upgrade',
    20
);
