<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return the operator usage table name.
 *
 * @return string
 */
function nwmd_directory_get_operator_usage_table_name() {

    $tables = nwmd_directory_get_operator_table_names();

    return isset($tables['usage'])
        ? (string) $tables['usage']
        : '';
}

/**
 * Return whether one run already has a non-cancelled research preview.
 *
 * @param int $run_id Operator run ID.
 *
 * @return bool
 */
function nwmd_directory_operator_run_has_research_preview($run_id) {

    global $wpdb;

    $run_id = absint($run_id);
    $usage_table =
        nwmd_directory_get_operator_usage_table_name();

    if (
        $run_id < 1
        || '' === $usage_table
        || !nwmd_directory_operator_table_exists($usage_table)
    ) {
        return false;
    }

    $count = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*)
            FROM {$usage_table}
            WHERE run_id = %d
                AND operation_type = %s
                AND status <> %s",
            $run_id,
            'research_preview',
            'cancelled'
        )
    );

    return absint($count) > 0;
}

/**
 * Return the latest research-preview usage record for one run.
 *
 * @param int $run_id Operator run ID.
 *
 * @return object|null
 */
function nwmd_directory_get_operator_research_preview_usage($run_id) {

    global $wpdb;

    $run_id = absint($run_id);
    $usage_table =
        nwmd_directory_get_operator_usage_table_name();

    if (
        $run_id < 1
        || '' === $usage_table
        || !nwmd_directory_operator_table_exists($usage_table)
    ) {
        return null;
    }

    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT *
            FROM {$usage_table}
            WHERE run_id = %d
                AND operation_type = %s
            ORDER BY id DESC
            LIMIT 1",
            $run_id,
            'research_preview'
        )
    );

    return is_object($row) ? $row : null;
}

/**
 * Return the current run result summary.
 *
 * @param int $run_id Operator run ID.
 *
 * @return array
 */
function nwmd_directory_get_operator_research_preview_result($run_id) {

    global $wpdb;

    $run_id = absint($run_id);

    if ($run_id < 1) {
        return [];
    }

    $usage =
        nwmd_directory_get_operator_research_preview_usage(
            $run_id
        );

    if (
        !is_object($usage)
        || 'complete' !== sanitize_key((string) $usage->status)
    ) {
        return [];
    }

    $tables = nwmd_directory_get_operator_table_names();

    $json = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT result_summary
            FROM {$tables['runs']}
            WHERE id = %d
            LIMIT 1",
            $run_id
        )
    );

    if (!is_string($json) || '' === trim($json)) {
        return [];
    }

    $decoded = json_decode($json, true);

    return is_array($decoded) ? $decoded : [];
}

/**
 * Save a preview result to the current operator run.
 *
 * @param int   $run_id  Operator run ID.
 * @param array $preview Preview data.
 *
 * @return true|WP_Error
 */
function nwmd_directory_save_operator_research_preview(
    $run_id,
    $preview
) {

    global $wpdb;

    $run_id = absint($run_id);

    if ($run_id < 1 || !is_array($preview)) {
        return new WP_Error(
            'nwmd_operator_preview_save_invalid',
            __(
                'The research preview could not be saved.',
                'local-directory-framework'
            )
        );
    }

    $encoded = wp_json_encode(
        $preview,
        JSON_UNESCAPED_SLASHES
    );

    if (!is_string($encoded) || '' === trim($encoded)) {
        return new WP_Error(
            'nwmd_operator_preview_encode_failed',
            __(
                'The research preview audit could not be encoded.',
                'local-directory-framework'
            )
        );
    }

    $tables = nwmd_directory_get_operator_table_names();
    $now = current_time('mysql');

    $updated = $wpdb->update(
        $tables['runs'],
        [
            'result_summary' => $encoded,
            'error_message'  => '',
            'updated_at'     => $now,
        ],
        [
            'id'     => $run_id,
            'status' => 'started',
        ],
        [
            '%s',
            '%s',
            '%s',
        ],
        [
            '%d',
            '%s',
        ]
    );

    if (false === $updated) {
        return new WP_Error(
            'nwmd_operator_preview_save_failed',
            __(
                'The active operator run could not store the research preview safely.',
                'local-directory-framework'
            )
        );
    }

    if (0 === $updated) {
        $current = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT status, result_summary
                FROM {$tables['runs']}
                WHERE id = %d
                LIMIT 1",
                $run_id
            )
        );

        if (
            !is_object($current)
            || 'started' !== (string) $current->status
            || $encoded !== (string) $current->result_summary
        ) {
            return new WP_Error(
                'nwmd_operator_preview_save_failed',
                __(
                    'The active operator run could not store the research preview safely.',
                    'local-directory-framework'
                )
            );
        }
    }

    return true;
}