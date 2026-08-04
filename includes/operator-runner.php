<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Roll back the current operator database transaction.
 */
function nwmd_directory_rollback_operator_transaction() {

    global $wpdb;

    $wpdb->query('ROLLBACK');
}

/**
 * Return a readable taxonomy label without failing on deleted terms.
 *
 * @param int    $term_id  Term ID.
 * @param string $taxonomy Taxonomy name.
 *
 * @return string
 */
function nwmd_directory_get_operator_term_label(
    $term_id,
    $taxonomy
) {

    $term_id = absint($term_id);
    $term    = $term_id > 0
        ? get_term($term_id, $taxonomy)
        : null;

    if ($term instanceof WP_Term && !is_wp_error($term)) {
        return $term->name;
    }

    return sprintf(
        /* translators: %d: Missing taxonomy term ID. */
        __('Missing term #%d', 'local-directory-framework'),
        $term_id
    );
}

/**
 * Add readable taxonomy labels to one claimed checkpoint.
 *
 * @param object $checkpoint Joined checkpoint and job record.
 *
 * @return array
 */
function nwmd_directory_get_operator_checkpoint_context(
    $checkpoint
) {

    return [
        'checkpoint_id'     => absint($checkpoint->checkpoint_id ?? 0),
        'job_id'            => absint($checkpoint->job_id ?? 0),
        'run_id'            => absint($checkpoint->run_id ?? 0),
        'run_uuid'          => sanitize_text_field(
            (string) ($checkpoint->run_uuid ?? '')
        ),
        'state_term_id'     => absint($checkpoint->state_term_id ?? 0),
        'city_term_id'      => absint($checkpoint->city_term_id ?? 0),
        'category_term_id'  => absint($checkpoint->category_term_id ?? 0),
        'specialty_term_id' => absint(
            $checkpoint->specialty_term_id ?? 0
        ),
        'state_name'        =>
            nwmd_directory_get_operator_term_label(
                $checkpoint->state_term_id ?? 0,
                'nwmd_state'
            ),
        'city_name'         =>
            nwmd_directory_get_operator_term_label(
                $checkpoint->city_term_id ?? 0,
                'nwmd_city'
            ),
        'category_name'     =>
            nwmd_directory_get_operator_term_label(
                $checkpoint->category_term_id ?? 0,
                'nwmd_category'
            ),
        'specialty_name'    =>
            nwmd_directory_get_operator_term_label(
                $checkpoint->specialty_term_id ?? 0,
                'nwmd_specialty'
            ),
    ];
}

/**
 * Create a run record for one claimed specialty checkpoint.
 *
 * @param object $checkpoint Joined checkpoint and job record.
 * @param string $now        Current WordPress MySQL time.
 *
 * @return object|WP_Error
 */
function nwmd_directory_create_operator_run(
    $checkpoint,
    $now
) {

    global $wpdb;

    $tables   = nwmd_directory_get_operator_table_names();
    $run_uuid = wp_generate_uuid4();

    $inserted = $wpdb->insert(
        $tables['runs'],
        [
            'run_uuid'                 => $run_uuid,
            'job_id'                   => absint($checkpoint->job_id),
            'specialty_term_id'        =>
                absint($checkpoint->specialty_term_id),
            'status'                   => 'started',
            'businesses_created'       => 0,
            'businesses_updated'       => 0,
            'businesses_without_deals' => 0,
            'deals_created'            => 0,
            'deals_updated'            => 0,
            'exclusions'               => 0,
            'result_summary'           => '',
            'error_message'            => '',
            'created_by'               => get_current_user_id(),
            'started_at'               => $now,
            'completed_at'             => null,
            'created_at'               => $now,
            'updated_at'               => $now,
        ],
        [
            '%s',
            '%d',
            '%d',
            '%s',
            '%d',
            '%d',
            '%d',
            '%d',
            '%d',
            '%d',
            '%s',
            '%s',
            '%d',
            '%s',
            '%s',
            '%s',
            '%s',
        ]
    );

    if (false === $inserted || absint($wpdb->insert_id) < 1) {
        return new WP_Error(
            'nwmd_operator_run_insert_failed',
            __(
                'The operator run could not be created.',
                'local-directory-framework'
            )
        );
    }

    $checkpoint->run_id   = absint($wpdb->insert_id);
    $checkpoint->run_uuid = $run_uuid;

    return $checkpoint;
}

/**
 * Return one existing started run for a checkpoint, if available.
 *
 * @param object $checkpoint Joined checkpoint and job record.
 *
 * @return object|null
 */
function nwmd_directory_get_started_operator_run(
    $checkpoint
) {

    global $wpdb;

    $tables = nwmd_directory_get_operator_table_names();

    $run = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT id, run_uuid
            FROM {$tables['runs']}
            WHERE job_id = %d
                AND specialty_term_id = %d
                AND status = %s
            ORDER BY id DESC
            LIMIT 1
            FOR UPDATE",
            absint($checkpoint->job_id),
            absint($checkpoint->specialty_term_id),
            'started'
        )
    );

    return is_object($run) ? $run : null;
}

/**
 * Atomically claim or resume the next specialty checkpoint.
 *
 * This milestone only records queue progress. It does not change
 * businesses, research sources, Deals, or rankings.
 *
 * @return array|WP_Error
 */
function nwmd_directory_claim_next_operator_checkpoint() {

    global $wpdb;

    $storage = nwmd_directory_get_operator_storage_status();

    if (empty($storage['ready'])) {
        return new WP_Error(
            'nwmd_operator_storage_incomplete',
            __(
                'Operator storage is incomplete.',
                'local-directory-framework'
            )
        );
    }

    $tables = nwmd_directory_get_operator_table_names();
    $now    = current_time('mysql');

    if (false === $wpdb->query('START TRANSACTION')) {
        return new WP_Error(
            'nwmd_operator_transaction_failed',
            __(
                'The operator could not start a database transaction.',
                'local-directory-framework'
            )
        );
    }

    $checkpoint = $wpdb->get_row(
        "SELECT
            specialties.id AS checkpoint_id,
            specialties.job_id,
            specialties.specialty_term_id,
            jobs.state_term_id,
            jobs.city_term_id,
            jobs.category_term_id
        FROM {$tables['specialties']} AS specialties
        INNER JOIN {$tables['jobs']} AS jobs
            ON jobs.id = specialties.job_id
        WHERE specialties.status = 'in_progress'
        ORDER BY
            specialties.started_at ASC,
            specialties.id ASC
        LIMIT 1
        FOR UPDATE"
    );

    $resumed = is_object($checkpoint);

    if (!$resumed) {
        $checkpoint = $wpdb->get_row(
            "SELECT
                specialties.id AS checkpoint_id,
                specialties.job_id,
                specialties.specialty_term_id,
                jobs.state_term_id,
                jobs.city_term_id,
                jobs.category_term_id
            FROM {$tables['specialties']} AS specialties
            INNER JOIN {$tables['jobs']} AS jobs
                ON jobs.id = specialties.job_id
            WHERE specialties.status = 'pending'
                AND jobs.status IN ('pending', 'in_progress')
            ORDER BY
                jobs.id ASC,
                specialties.sort_order ASC,
                specialties.id ASC
            LIMIT 1
            FOR UPDATE"
        );
    }

    if (!is_object($checkpoint)) {
        $wpdb->query('COMMIT');

        return new WP_Error(
            'nwmd_operator_queue_empty',
            __(
                'No pending specialty checkpoints remain.',
                'local-directory-framework'
            )
        );
    }

    if (!$resumed) {
        $checkpoint_updated = $wpdb->update(
            $tables['specialties'],
            [
                'status'     => 'in_progress',
                'last_error' => '',
                'started_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id'     => absint($checkpoint->checkpoint_id),
                'status' => 'pending',
            ],
            [
                '%s',
                '%s',
                '%s',
                '%s',
            ],
            [
                '%d',
                '%s',
            ]
        );

        if (1 !== $checkpoint_updated) {
            nwmd_directory_rollback_operator_transaction();

            return new WP_Error(
                'nwmd_operator_checkpoint_claim_failed',
                __(
                    'The next checkpoint could not be claimed safely.',
                    'local-directory-framework'
                )
            );
        }
    }

    $job_updated = $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$tables['jobs']}
            SET
                status = %s,
                current_specialty_term_id = %d,
                last_error = %s,
                started_at = COALESCE(started_at, %s),
                last_run_at = %s,
                updated_at = %s
            WHERE id = %d",
            'in_progress',
            absint($checkpoint->specialty_term_id),
            '',
            $now,
            $now,
            $now,
            absint($checkpoint->job_id)
        )
    );

    if (false === $job_updated) {
        nwmd_directory_rollback_operator_transaction();

        return new WP_Error(
            'nwmd_operator_job_claim_failed',
            __(
                'The parent operator job could not be updated.',
                'local-directory-framework'
            )
        );
    }

    $run = nwmd_directory_get_started_operator_run($checkpoint);

    if (is_object($run)) {
        $checkpoint->run_id   = absint($run->id);
        $checkpoint->run_uuid = sanitize_text_field(
            (string) $run->run_uuid
        );
    } else {
        $checkpoint = nwmd_directory_create_operator_run(
            $checkpoint,
            $now
        );

        if (is_wp_error($checkpoint)) {
            nwmd_directory_rollback_operator_transaction();

            return $checkpoint;
        }
    }

    if (false === $wpdb->query('COMMIT')) {
        nwmd_directory_rollback_operator_transaction();

        return new WP_Error(
            'nwmd_operator_commit_failed',
            __(
                'The operator checkpoint could not be committed.',
                'local-directory-framework'
            )
        );
    }

    $context            =
        nwmd_directory_get_operator_checkpoint_context($checkpoint);
    $context['resumed'] = $resumed;

    return $context;
}

/**
 * Return operator checkpoint counts grouped by status.
 *
 * @return array
 */
function nwmd_directory_get_operator_checkpoint_counts() {

    global $wpdb;

    $tables = nwmd_directory_get_operator_table_names();
    $counts = [
        'pending'     => 0,
        'in_progress' => 0,
        'complete'    => 0,
        'error'       => 0,
    ];

    if (
        !nwmd_directory_operator_table_exists(
            $tables['specialties']
        )
    ) {
        return $counts;
    }

    $rows = $wpdb->get_results(
        "SELECT status, COUNT(*) AS total
        FROM {$tables['specialties']}
        GROUP BY status"
    );

    foreach ((array) $rows as $row) {
        $status = sanitize_key((string) ($row->status ?? ''));

        if ('' === $status) {
            continue;
        }

        $counts[$status] = absint($row->total ?? 0);
    }

    return $counts;
}

/**
 * Handle the secure Run Next Item admin action.
 */
function nwmd_directory_handle_operator_run_next() {

    if (!current_user_can('manage_options')) {
        wp_die(
            esc_html__(
                'You do not have permission to perform this action.',
                'local-directory-framework'
            )
        );
    }

    check_admin_referer(
        'nwmd_directory_operator_run_next'
    );

    $result = nwmd_directory_claim_next_operator_checkpoint();
    $key    = 'nwmd_operator_run_' . get_current_user_id();

    if (is_wp_error($result)) {
        set_transient(
            $key,
            [
                'success' => false,
                'message' => $result->get_error_message(),
            ],
            MINUTE_IN_SECONDS
        );
    } else {
        set_transient(
            $key,
            [
                'success' => true,
                'result'  => $result,
            ],
            MINUTE_IN_SECONDS
        );
    }

    $redirect_url = add_query_arg(
        [
            'post_type' => 'nwmd_business',
            'page'      => 'nwmd-data-operator',
            'ran_next'  => '1',
        ],
        admin_url('edit.php')
    );

    wp_safe_redirect($redirect_url);
    exit;
}

add_action(
    'admin_post_nwmd_directory_operator_run_next',
    'nwmd_directory_handle_operator_run_next'
);