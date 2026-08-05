<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clear the retired paid-research heartbeat.
 *
 * @return true|WP_Error
 */
function nwmd_directory_clear_operator_automation_schedule() {

    $hook =
        'nwmd_directory_operator_automation_heartbeat';

    if (false === wp_next_scheduled($hook)) {
        return true;
    }

    $cleared = wp_clear_scheduled_hook(
        $hook,
        [],
        true
    );

    if (is_wp_error($cleared)) {
        return $cleared;
    }

    if (false === $cleared) {
        return new WP_Error(
            'nwmd_operator_retired_schedule_cleanup_failed',
            __(
                'The retired operator automation schedule could not be cleared.',
                'local-directory-framework'
            )
        );
    }

    return true;
}

/**
 * Preserve activation compatibility without scheduling new research.
 *
 * @return true|WP_Error
 */
function nwmd_directory_sync_operator_automation_schedule() {

    return nwmd_directory_clear_operator_automation_schedule();
}

/**
 * Remove the retired heartbeat whenever it is found.
 */
function nwmd_directory_maybe_retire_paid_operator_automation() {

    nwmd_directory_clear_operator_automation_schedule();
}

add_action(
    'init',
    'nwmd_directory_maybe_retire_paid_operator_automation',
    1
);