<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return whether controlled AI State imports are explicitly enabled.
 *
 * @return bool
 */
function nwmd_directory_controlled_ai_state_import_is_enabled() {

    if (!function_exists('wp_get_environment_type')) {
        return false;
    }

    return 'staging' === wp_get_environment_type()
        && defined('NWMD_ENABLE_CONTROLLED_AI_STATE_IMPORT')
        && true === NWMD_ENABLE_CONTROLLED_AI_STATE_IMPORT;
}

/**
 * Load the controlled AI State import modules.
 */
require_once NWMD_DIRECTORY_PATH
    . 'includes/operator-ai-state-import-storage.php';
require_once NWMD_DIRECTORY_PATH
    . 'includes/operator-ai-state-import-normalization.php';
require_once NWMD_DIRECTORY_PATH
    . 'includes/operator-ai-state-import-business-policy.php';
require_once NWMD_DIRECTORY_PATH
    . 'includes/operator-ai-state-import-source-policy.php';
require_once NWMD_DIRECTORY_PATH
    . 'includes/operator-ai-state-import-plan.php';
require_once NWMD_DIRECTORY_PATH
    . 'includes/operator-ai-state-import-rollback.php';
require_once NWMD_DIRECTORY_PATH
    . 'includes/operator-ai-state-import-execution.php';
require_once NWMD_DIRECTORY_PATH
    . 'includes/operator-ai-state-import-admin.php';

if (nwmd_directory_controlled_ai_state_import_is_enabled()) {
    add_action(
        'admin_post_nwmd_directory_prepare_controlled_ai_state_import',
        'nwmd_directory_handle_ai_state_import_prepare'
    );

    add_action(
        'admin_post_nwmd_directory_execute_controlled_ai_state_import',
        'nwmd_directory_handle_ai_state_import_execute'
    );
}
