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
 * Return the database-specific operator advisory lock name.
 *
 * @return string
 */
function nwmd_directory_get_operator_lock_name() {

    global $wpdb;

    $database = defined('DB_NAME') ? (string) DB_NAME : '';

    return 'nwmd_operator_' . sha1($database . '|' . $wpdb->prefix);
}

/**
 * Acquire the single-worker operator advisory lock.
 *
 * @return true|WP_Error
 */
function nwmd_directory_acquire_operator_lock() {

    global $wpdb;

    $acquired = $wpdb->get_var(
        $wpdb->prepare(
            'SELECT GET_LOCK(%s, %d)',
            nwmd_directory_get_operator_lock_name(),
            5
        )
    );

    if ('1' !== (string) $acquired) {
        return new WP_Error(
            'nwmd_operator_busy',
            __(
                'Another operator action is already running. Try again in a few seconds.',
                'local-directory-framework'
            )
        );
    }

    return true;
}

/**
 * Release the single-worker operator advisory lock.
 */
function nwmd_directory_release_operator_lock() {

    global $wpdb;

    $wpdb->get_var(
        $wpdb->prepare(
            'SELECT RELEASE_LOCK(%s)',
            nwmd_directory_get_operator_lock_name()
        )
    );
}

/**
 * Run one callback while holding the operator advisory lock.
 *
 * @param callable $callback Locked callback.
 *
 * @return mixed|WP_Error
 */
function nwmd_directory_with_operator_lock($callback) {

    $locked = nwmd_directory_acquire_operator_lock();

    if (is_wp_error($locked)) {
        return $locked;
    }

    try {
        return call_user_func($callback);
    } finally {
        nwmd_directory_release_operator_lock();
    }
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
 * Add readable taxonomy labels to one checkpoint.
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
 * Return the number of active specialty checkpoints.
 *
 * @return int
 */
function nwmd_directory_get_operator_active_checkpoint_count() {

    global $wpdb;

    $tables = nwmd_directory_get_operator_table_names();

    if (
        !nwmd_directory_operator_table_exists(
            $tables['specialties']
        )
    ) {
        return 0;
    }

    return absint(
        $wpdb->get_var(
            "SELECT COUNT(*)
            FROM {$tables['specialties']}
            WHERE status = 'in_progress'"
        )
    );
}

/**
 * Return the active specialty checkpoint.
 *
 * @param bool $for_update Lock the selected row for update.
 *
 * @return object|null
 */
function nwmd_directory_get_active_operator_checkpoint(
    $for_update = false
) {

    global $wpdb;

    $tables = nwmd_directory_get_operator_table_names();
    $lock   = $for_update ? ' FOR UPDATE' : '';

    $checkpoint = $wpdb->get_row(
        "SELECT
            specialties.id AS checkpoint_id,
            specialties.job_id,
            specialties.specialty_term_id,
            specialties.businesses_created,
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
        LIMIT 1{$lock}"
    );

    return is_object($checkpoint) ? $checkpoint : null;
}

/**
 * Return the current checkpoint context for the admin page.
 *
 * @return array
 */
function nwmd_directory_get_current_operator_checkpoint_context() {

    $checkpoint = nwmd_directory_get_active_operator_checkpoint(false);

    if (!is_object($checkpoint)) {
        return [];
    }

    $run = nwmd_directory_get_started_operator_run(
        $checkpoint,
        false
    );

    if (is_object($run)) {
        $checkpoint->run_id   = absint($run->id);
        $checkpoint->run_uuid = sanitize_text_field(
            (string) $run->run_uuid
        );
    }

    return nwmd_directory_get_operator_checkpoint_context($checkpoint);
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
 * @param bool   $for_update Lock the selected row for update.
 *
 * @return object|null
 */
function nwmd_directory_get_started_operator_run(
    $checkpoint,
    $for_update = true
) {

    global $wpdb;

    $tables = nwmd_directory_get_operator_table_names();
    $lock   = $for_update ? ' FOR UPDATE' : '';

    $run = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT
                id,
                run_uuid,
                result_summary,
                businesses_created,
                businesses_without_deals
            FROM {$tables['runs']}
            WHERE job_id = %d
                AND specialty_term_id = %d
                AND status = %s
            ORDER BY id DESC
            LIMIT 1{$lock}",
            absint($checkpoint->job_id),
            absint($checkpoint->specialty_term_id),
            'started'
        )
    );

    return is_object($run) ? $run : null;
}

/**
 * Return aggregate counters for one city-category job.
 *
 * @param int $job_id Operator job ID.
 *
 * @return array
 */
function nwmd_directory_get_operator_job_totals($job_id) {

    global $wpdb;

    $tables = nwmd_directory_get_operator_table_names();
    $row    = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT
                COUNT(*) AS specialty_total,
                SUM(
                    CASE WHEN status = 'complete' THEN 1 ELSE 0 END
                ) AS specialty_completed,
                SUM(businesses_created) AS businesses_created,
                SUM(businesses_updated) AS businesses_updated,
                SUM(deals_created) AS deals_created,
                SUM(deals_updated) AS deals_updated
            FROM {$tables['specialties']}
            WHERE job_id = %d",
            absint($job_id)
        ),
        ARRAY_A
    );

    return [
        'specialty_total'     => absint($row['specialty_total'] ?? 0),
        'specialty_completed' => absint(
            $row['specialty_completed'] ?? 0
        ),
        'businesses_created'  => absint(
            $row['businesses_created'] ?? 0
        ),
        'businesses_updated'  => absint(
            $row['businesses_updated'] ?? 0
        ),
        'deals_created'       => absint($row['deals_created'] ?? 0),
        'deals_updated'       => absint($row['deals_updated'] ?? 0),
    ];
}

/**
 * Synchronize one parent job from its specialty checkpoints.
 *
 * @param int    $job_id     Operator job ID.
 * @param string $now        Current WordPress MySQL time.
 * @param bool   $was_active Whether the job should remain in progress.
 *
 * @return array|WP_Error
 */
function nwmd_directory_sync_operator_job(
    $job_id,
    $now,
    $was_active = true
) {

    global $wpdb;

    $tables = nwmd_directory_get_operator_table_names();
    $totals = nwmd_directory_get_operator_job_totals($job_id);

    $job_complete =
        $totals['specialty_total'] > 0
        && $totals['specialty_completed'] >= $totals['specialty_total'];

    if ($job_complete) {
        $status       = 'complete';
        $completed_at = $now;
    } elseif ($totals['specialty_completed'] > 0 || $was_active) {
        $status       = 'in_progress';
        $completed_at = null;
    } else {
        $status       = 'pending';
        $completed_at = null;
    }

    $updated = $wpdb->update(
        $tables['jobs'],
        [
            'status'                      => $status,
            'current_specialty_term_id'   => 0,
            'specialty_total'             => $totals['specialty_total'],
            'specialty_completed'         =>
                $totals['specialty_completed'],
            'businesses_created'          =>
                $totals['businesses_created'],
            'businesses_updated'          =>
                $totals['businesses_updated'],
            'deals_created'                => $totals['deals_created'],
            'deals_updated'                => $totals['deals_updated'],
            'last_error'                   => '',
            'completed_at'                 => $completed_at,
            'last_run_at'                  => $now,
            'updated_at'                   => $now,
        ],
        [
            'id' => absint($job_id),
        ],
        [
            '%s',
            '%d',
            '%d',
            '%d',
            '%d',
            '%d',
            '%d',
            '%d',
            '%s',
            '%s',
            '%s',
            '%s',
        ],
        [
            '%d',
        ]
    );

    if (false === $updated) {
        return new WP_Error(
            'nwmd_operator_job_sync_failed',
            __(
                'The parent operator job could not be synchronized.',
                'local-directory-framework'
            )
        );
    }

    $totals['job_complete'] = $job_complete;
    $totals['job_status']   = $status;

    return $totals;
}

/**
 * Verify that no more than one checkpoint is active.
 *
 * @return true|WP_Error
 */
function nwmd_directory_validate_single_active_checkpoint() {

    $active_count = nwmd_directory_get_operator_active_checkpoint_count();

    if ($active_count > 1) {
        return new WP_Error(
            'nwmd_operator_multiple_active',
            __(
                'More than one checkpoint is in progress. No changes were made.',
                'local-directory-framework'
            )
        );
    }

    return true;
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

    return nwmd_directory_with_operator_lock(
        function () {

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

            $valid = nwmd_directory_validate_single_active_checkpoint();

            if (is_wp_error($valid)) {
                nwmd_directory_rollback_operator_transaction();

                return $valid;
            }

            $checkpoint = nwmd_directory_get_active_operator_checkpoint(
                true
            );
            $resumed    = is_object($checkpoint);

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
                        'status'       => 'in_progress',
                        'last_error'   => '',
                        'started_at'   => $now,
                        'completed_at' => null,
                        'updated_at'   => $now,
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
                        completed_at = NULL,
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

            $run = nwmd_directory_get_started_operator_run(
                $checkpoint,
                true
            );

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
                nwmd_directory_get_operator_checkpoint_context(
                    $checkpoint
                );
            $context['action']  = $resumed ? 'resumed' : 'started';
            $context['resumed'] = $resumed;

            return $context;
        }
    );
}

/**
 * Validate the active run and prepare its preserved completion summary.
 *
 * Supervised runs with created Business drafts must retain valid JSON audit
 * data, matching counters, and existing unpublished Business posts.
 *
 * @param object $checkpoint Active specialty checkpoint.
 * @param object $run        Started operator run.
 *
 * @return string|WP_Error
 */
function nwmd_directory_prepare_operator_completion_summary(
    $checkpoint,
    $run
) {

    $checkpoint_count = absint(
        $checkpoint->businesses_created ?? 0
    );
    $run_count = absint($run->businesses_created ?? 0);
    $without_deals = absint(
        $run->businesses_without_deals ?? 0
    );

    if (
        $checkpoint_count !== $run_count
        || $without_deals > $run_count
    ) {
        return new WP_Error(
            'nwmd_operator_completion_counter_mismatch',
            __(
                'The operator counters do not match and the checkpoint cannot be completed safely.',
                'local-directory-framework'
            )
        );
    }

    $run_id = absint($run->id ?? 0);

    $preview =
        nwmd_directory_get_operator_research_preview_result(
            $run_id
        );

    if (empty($preview)) {
        return new WP_Error(
            'nwmd_operator_completion_preview_missing',
            __(
                'A completed supervised research preview is required before this checkpoint can be completed.',
                'local-directory-framework'
            )
        );
    }

    $businesses = isset($preview['businesses'])
        && is_array($preview['businesses'])
            ? array_values($preview['businesses'])
            : [];

    $preview_count = count($businesses);

    $validation = isset($preview['duplicate_validation'])
        && is_array($preview['duplicate_validation'])
            ? $preview['duplicate_validation']
            : [];

    if (empty($validation)) {
        return new WP_Error(
            'nwmd_operator_completion_validation_missing',
            __(
                'Complete the supervised duplicate review before completing this checkpoint.',
                'local-directory-framework'
            )
        );
    }

    $validation_rows = isset($validation['businesses'])
        && is_array($validation['businesses'])
            ? array_values($validation['businesses'])
            : [];

    $validation_totals = isset($validation['totals'])
        && is_array($validation['totals'])
            ? $validation['totals']
            : [];

    $ready_count = absint(
        $validation_totals['ready_for_draft'] ?? 0
    );

    $review_count = absint(
        $validation_totals['review_required'] ?? 0
    );

    $blocked_count = absint(
        $validation_totals['blocked_duplicate'] ?? 0
    );

    if (
        count($validation_rows) !== $preview_count
        || (
            $ready_count
            + $review_count
            + $blocked_count
        ) !== $preview_count
    ) {
        return new WP_Error(
            'nwmd_operator_completion_validation_mismatch',
            __(
                'The stored duplicate-review counts do not match the research preview.',
                'local-directory-framework'
            )
        );
    }

    if (
        $review_count > 0
        || $blocked_count > 0
        || $ready_count !== $preview_count
        || $run_count !== $preview_count
    ) {
        return new WP_Error(
            'nwmd_operator_completion_not_ready',
            __(
                'Every researched business must pass duplicate review and exist as a supervised draft before completion.',
                'local-directory-framework'
            )
        );
    }

    $raw_summary = isset($run->result_summary)
        ? (string) $run->result_summary
        : '';

    $summary = json_decode($raw_summary, true);

    if ($run_count > 0) {
        if (!is_array($summary)) {
            return new WP_Error(
                'nwmd_operator_completion_audit_missing',
                __(
                    'The supervised run audit record is missing or invalid.',
                    'local-directory-framework'
                )
            );
        }

        $draft_creation = isset($summary['draft_creation'])
            && is_array($summary['draft_creation'])
            ? $summary['draft_creation']
            : [];

        $draft_count = absint(
            $draft_creation['business_count'] ?? 0
        );

        $post_ids = isset(
            $draft_creation['business_post_ids']
        ) && is_array(
            $draft_creation['business_post_ids']
        )
            ? array_values(
                array_unique(
                    array_filter(
                        array_map(
                            'absint',
                            $draft_creation[
                                'business_post_ids'
                            ]
                        )
                    )
                )
            )
            : [];

        if (
            'created' !== (
                (string) ($draft_creation['status'] ?? '')
            )
            || $draft_count !== $run_count
            || count($post_ids) !== $run_count
        ) {
            return new WP_Error(
                'nwmd_operator_completion_draft_audit_invalid',
                __(
                    'The supervised Business draft audit does not match the operator counters.',
                    'local-directory-framework'
                )
            );
        }

        foreach ($post_ids as $post_id) {
            $post = get_post($post_id);

            if (
                !$post instanceof WP_Post
                || 'nwmd_business' !== $post->post_type
                || 'draft' !== $post->post_status
            ) {
                return new WP_Error(
                    'nwmd_operator_completion_draft_missing',
                    __(
                        'Every supervised Business record must still exist as an unpublished draft before completion.',
                        'local-directory-framework'
                    )
                );
            }
        }
    }

    if (!is_array($summary)) {
        return $raw_summary;
    }

    $summary['completion'] = [
        'completion_version'       => 1,
        'status'                   => 'complete',
        'completed_at'             => current_time('mysql'),
        'completed_by'             => get_current_user_id(),
        'businesses_created'       => $run_count,
        'businesses_without_deals' => $without_deals,
    ];

    $encoded = wp_json_encode(
        $summary,
        JSON_UNESCAPED_SLASHES
    );

    if (!is_string($encoded) || '' === $encoded) {
        return new WP_Error(
            'nwmd_operator_completion_summary_encode_failed',
            __(
                'The preserved operator audit record could not be encoded.',
                'local-directory-framework'
            )
        );
    }

    return $encoded;
}
/**
 * Atomically complete the current operator checkpoint.
 *
 * This action preserves the stored run audit summary and validates any
 * supervised Business drafts before completion.
 *
 * @return array|WP_Error
 */
function nwmd_directory_complete_current_operator_checkpoint() {

    return nwmd_directory_with_operator_lock(
        function () {

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

            $valid = nwmd_directory_validate_single_active_checkpoint();

            if (is_wp_error($valid)) {
                nwmd_directory_rollback_operator_transaction();

                return $valid;
            }

            $checkpoint = nwmd_directory_get_active_operator_checkpoint(
                true
            );

            if (!is_object($checkpoint)) {
                nwmd_directory_rollback_operator_transaction();

                return new WP_Error(
                    'nwmd_operator_no_active_checkpoint',
                    __(
                        'There is no active checkpoint to complete.',
                        'local-directory-framework'
                    )
                );
            }

            $run = nwmd_directory_get_started_operator_run(
                $checkpoint,
                true
            );

            if (!is_object($run)) {
                nwmd_directory_rollback_operator_transaction();

                return new WP_Error(
                    'nwmd_operator_no_started_run',
                    __(
                        'The active checkpoint has no started run. Release it and start it again.',
                        'local-directory-framework'
                    )
                );
            }

            $completion_summary =
                nwmd_directory_prepare_operator_completion_summary(
                    $checkpoint,
                    $run
                );

            if (is_wp_error($completion_summary)) {
                nwmd_directory_rollback_operator_transaction();

                return $completion_summary;
            }

            $checkpoint_updated = $wpdb->update(
                $tables['specialties'],
                [
                    'status'       => 'complete',
                    'last_error'   => '',
                    'completed_at' => $now,
                    'updated_at'   => $now,
                ],
                [
                    'id'     => absint($checkpoint->checkpoint_id),
                    'status' => 'in_progress',
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
                    'nwmd_operator_checkpoint_complete_failed',
                    __(
                        'The active checkpoint could not be completed safely.',
                        'local-directory-framework'
                    )
                );
            }

            $run_updated = $wpdb->update(
                $tables['runs'],
                [
                    'status'         => 'complete',
                    'result_summary' => $completion_summary,
                    'error_message'  => '',
                    'completed_at'   => $now,
                    'updated_at'     => $now,
                ],
                [
                    'id'     => absint($run->id),
                    'status' => 'started',
                ],
                [
                    '%s',
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

            if (1 !== $run_updated) {
                nwmd_directory_rollback_operator_transaction();

                return new WP_Error(
                    'nwmd_operator_run_complete_failed',
                    __(
                        'The operator run could not be completed safely.',
                        'local-directory-framework'
                    )
                );
            }

            $totals = nwmd_directory_sync_operator_job(
                $checkpoint->job_id,
                $now,
                true
            );

            if (is_wp_error($totals)) {
                nwmd_directory_rollback_operator_transaction();

                return $totals;
            }

            if (false === $wpdb->query('COMMIT')) {
                nwmd_directory_rollback_operator_transaction();

                return new WP_Error(
                    'nwmd_operator_commit_failed',
                    __(
                        'The completed checkpoint could not be committed.',
                        'local-directory-framework'
                    )
                );
            }

            $checkpoint->run_id   = absint($run->id);
            $checkpoint->run_uuid = sanitize_text_field(
                (string) $run->run_uuid
            );

            $context                      =
                nwmd_directory_get_operator_checkpoint_context(
                    $checkpoint
                );
            $context['action']            = 'completed';
            $context['job_complete']      =
                !empty($totals['job_complete']);
            $context['specialty_total']   =
                absint($totals['specialty_total']);
            $context['specialty_completed'] =
                absint($totals['specialty_completed']);

            return $context;
        }
    );
}

/**
 * Atomically release the current checkpoint back to pending.
 *
 * @return array|WP_Error
 */
function nwmd_directory_release_current_operator_checkpoint() {

    return nwmd_directory_with_operator_lock(
        function () {

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

            $valid = nwmd_directory_validate_single_active_checkpoint();

            if (is_wp_error($valid)) {
                nwmd_directory_rollback_operator_transaction();

                return $valid;
            }

            $checkpoint = nwmd_directory_get_active_operator_checkpoint(
                true
            );

            if (!is_object($checkpoint)) {
                nwmd_directory_rollback_operator_transaction();

                return new WP_Error(
                    'nwmd_operator_no_active_checkpoint',
                    __(
                        'There is no active checkpoint to release.',
                        'local-directory-framework'
                    )
                );
            }

            $run = nwmd_directory_get_started_operator_run(
                $checkpoint,
                true
            );

            if (is_object($run)) {
                $started_usage_found = $wpdb->query(
                    $wpdb->prepare(
                        "SELECT id
                        FROM {$tables['usage']}
                        WHERE run_id = %d
                            AND operation_type = %s
                            AND status = %s
                        LIMIT 1
                        FOR UPDATE",
                        absint($run->id),
                        'research_preview',
                        'started'
                    )
                );

                if (false === $started_usage_found) {
                    nwmd_directory_rollback_operator_transaction();

                    return new WP_Error(
                        'nwmd_operator_started_usage_check_failed',
                        __(
                            'The active research-preview usage state could not be verified safely.',
                            'local-directory-framework'
                        )
                    );
                }

                if ($started_usage_found > 0) {
                    nwmd_directory_rollback_operator_transaction();

                    return new WP_Error(
                        'nwmd_operator_release_preview_started',
                        __(
                            'This checkpoint has a research preview still marked in progress. Wait for it to finish, or use the stale-usage recovery control after 15 minutes.',
                            'local-directory-framework'
                        )
                    );
                }

                $run_summary = json_decode(
                    (string) ($run->result_summary ?? ''),
                    true
                );

                $draft_creation = is_array($run_summary)
                    && isset($run_summary['draft_creation'])
                    && is_array(
                        $run_summary['draft_creation']
                    )
                        ? $run_summary['draft_creation']
                        : [];

                $has_created_drafts =
                    absint($run->businesses_created ?? 0) > 0
                    || absint(
                        $checkpoint->businesses_created ?? 0
                    ) > 0
                    || (
                        'created' === (string) (
                            $draft_creation['status'] ?? ''
                        )
                        && absint(
                            $draft_creation['business_count']
                                ?? 0
                        ) > 0
                    );

                if ($has_created_drafts) {
                    nwmd_directory_rollback_operator_transaction();

                    return new WP_Error(
                        'nwmd_operator_release_drafts_exist',
                        __(
                            'This checkpoint has created Business drafts and cannot be released. Review the drafts and complete the checkpoint instead.',
                            'local-directory-framework'
                        )
                    );
                }
            }

            $checkpoint_updated = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$tables['specialties']}
                    SET
                        status = %s,
                        last_error = %s,
                        started_at = NULL,
                        completed_at = NULL,
                        updated_at = %s
                    WHERE id = %d
                        AND status = %s",
                    'pending',
                    '',
                    $now,
                    absint($checkpoint->checkpoint_id),
                    'in_progress'
                )
            );

            if (1 !== $checkpoint_updated) {
                nwmd_directory_rollback_operator_transaction();

                return new WP_Error(
                    'nwmd_operator_checkpoint_release_failed',
                    __(
                        'The active checkpoint could not be released safely.',
                        'local-directory-framework'
                    )
                );
            }

            if (is_object($run)) {
                $reserved_usage_cancelled = $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$tables['usage']}
                        SET
                            status = %s,
                            error_message = %s,
                            completed_at = %s,
                            updated_at = %s
                        WHERE run_id = %d
                            AND operation_type = %s
                            AND status = %s",
                        'cancelled',
                        'Operator run released before the research preview started.',
                        $now,
                        $now,
                        absint($run->id),
                        'research_preview',
                        'reserved'
                    )
                );

                if (false === $reserved_usage_cancelled) {
                    nwmd_directory_rollback_operator_transaction();

                    return new WP_Error(
                        'nwmd_operator_usage_cancel_failed',
                        __(
                            'The reserved research preview usage could not be cancelled safely.',
                            'local-directory-framework'
                        )
                    );
                }

                $release_summary = isset($run->result_summary)
                    ? (string) $run->result_summary
                    : '';

                $decoded_summary = json_decode(
                    $release_summary,
                    true
                );

                if (is_array($decoded_summary)) {
                    $decoded_summary['release'] = [
                        'release_version' => 1,
                        'status'          => 'cancelled',
                        'released_at'     => $now,
                        'released_by'     => get_current_user_id(),
                        'reason'          =>
                            'Checkpoint released back to pending.',
                    ];

                    $encoded_summary = wp_json_encode(
                        $decoded_summary,
                        JSON_UNESCAPED_SLASHES
                    );

                    if (
                        is_string($encoded_summary)
                        && '' !== $encoded_summary
                    ) {
                        $release_summary = $encoded_summary;
                    }
                }

                if ('' === trim($release_summary)) {
                    $release_summary = __(
                        'Checkpoint released back to pending. No business or Deal data was changed.',
                        'local-directory-framework'
                    );
                }

                $run_updated = $wpdb->update(
                    $tables['runs'],
                    [
                        'status'         => 'cancelled',
                        'result_summary' => $release_summary,
                        'error_message'  => '',
                        'completed_at'   => $now,
                        'updated_at'     => $now,
                    ],
                    [
                        'id'     => absint($run->id),
                        'status' => 'started',
                    ],
                    [
                        '%s',
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

                if (1 !== $run_updated) {
                    nwmd_directory_rollback_operator_transaction();

                    return new WP_Error(
                        'nwmd_operator_run_cancel_failed',
                        __(
                            'The operator run could not be cancelled safely.',
                            'local-directory-framework'
                        )
                    );
                }

                $checkpoint->run_id   = absint($run->id);
                $checkpoint->run_uuid = sanitize_text_field(
                    (string) $run->run_uuid
                );
            }

            $totals = nwmd_directory_sync_operator_job(
                $checkpoint->job_id,
                $now,
                false
            );

            if (is_wp_error($totals)) {
                nwmd_directory_rollback_operator_transaction();

                return $totals;
            }

            if (false === $wpdb->query('COMMIT')) {
                nwmd_directory_rollback_operator_transaction();

                return new WP_Error(
                    'nwmd_operator_commit_failed',
                    __(
                        'The released checkpoint could not be committed.',
                        'local-directory-framework'
                    )
                );
            }

            $context                     =
                nwmd_directory_get_operator_checkpoint_context(
                    $checkpoint
                );
            $context['action']           = 'released';
            $context['run_cancelled']    = is_object($run);
            $context['specialty_total']  =
                absint($totals['specialty_total']);
            $context['specialty_completed'] =
                absint($totals['specialty_completed']);

            return $context;
        }
    );
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
 * Process a secure operator admin action and return to the operator page.
 *
 * @param string   $nonce_action Nonce action.
 * @param callable $callback     Operator callback.
 */
function nwmd_directory_process_operator_admin_action(
    $nonce_action,
    $callback
) {

    if (!current_user_can('manage_options')) {
        wp_die(
            esc_html__(
                'You do not have permission to perform this action.',
                'local-directory-framework'
            )
        );
    }

    check_admin_referer($nonce_action);

    $result = call_user_func($callback);
    $key    = 'nwmd_operator_action_' . get_current_user_id();

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
            'post_type'       => 'nwmd_business',
            'page'            => 'nwmd-data-operator',
            'operator_action' => '1',
        ],
        admin_url('edit.php')
    );

    wp_safe_redirect($redirect_url);
    exit;
}

/**
 * Handle the secure Run Next Item admin action.
 */
function nwmd_directory_handle_operator_run_next() {

    nwmd_directory_process_operator_admin_action(
        'nwmd_directory_operator_run_next',
        'nwmd_directory_claim_next_operator_checkpoint'
    );
}

/**
 * Handle the secure Complete Current Item admin action.
 */
function nwmd_directory_handle_operator_complete_current() {

    nwmd_directory_process_operator_admin_action(
        'nwmd_directory_operator_complete_current',
        'nwmd_directory_complete_current_operator_checkpoint'
    );
}

/**
 * Handle the secure Release Current Item admin action.
 */
function nwmd_directory_handle_operator_release_current() {

    nwmd_directory_process_operator_admin_action(
        'nwmd_directory_operator_release_current',
        'nwmd_directory_release_current_operator_checkpoint'
    );
}

add_action(
    'admin_post_nwmd_directory_operator_run_next',
    'nwmd_directory_handle_operator_run_next'
);

add_action(
    'admin_post_nwmd_directory_operator_complete_current',
    'nwmd_directory_handle_operator_complete_current'
);

add_action(
    'admin_post_nwmd_directory_operator_release_current',
    'nwmd_directory_handle_operator_release_current'
);
