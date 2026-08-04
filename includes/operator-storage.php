<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return Data Operator table names.
 *
 * @return array
 */
function nwmd_directory_get_operator_table_names() {

    global $wpdb;

    return [
        'jobs'        => $wpdb->prefix . 'nwmd_operator_jobs',
        'specialties' => $wpdb->prefix . 'nwmd_operator_specialties',
        'runs'        => $wpdb->prefix . 'nwmd_operator_runs',
    ];
}

/**
 * Determine whether one database table exists.
 *
 * @param string $table_name Full database table name.
 *
 * @return bool
 */
function nwmd_directory_operator_table_exists($table_name) {

    global $wpdb;

    $table_name = (string) $table_name;

    if ('' === $table_name) {
        return false;
    }

    return $table_name === $wpdb->get_var(
        $wpdb->prepare(
            'SHOW TABLES LIKE %s',
            $wpdb->esc_like($table_name)
        )
    );
}

/**
 * Return Data Operator storage readiness and record counts.
 *
 * @return array
 */
function nwmd_directory_get_operator_storage_status() {

    global $wpdb;

    $tables  = nwmd_directory_get_operator_table_names();
    $missing = [];
    $counts  = [];

    foreach ($tables as $key => $table_name) {
        if (!nwmd_directory_operator_table_exists($table_name)) {
            $missing[]    = $key;
            $counts[$key] = 0;
            continue;
        }

        $counts[$key] = absint(
            $wpdb->get_var(
                "SELECT COUNT(*) FROM {$table_name}"
            )
        );
    }

    return [
        'ready'   => empty($missing),
        'missing' => $missing,
        'counts'  => $counts,
    ];
}