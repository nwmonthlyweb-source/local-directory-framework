<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add one bounded preparation issue.
 *
 * @param array  $plan     Prepared plan.
 * @param string $type     blocked or conflict.
 * @param string $identity Portable identity.
 * @param string $message  Safe message.
 * @param int    $count    Affected record count.
 */
function nwmd_directory_add_ai_state_import_issue(
    array &$plan,
    $type,
    $identity,
    $message,
    $count = 1
) {

    $type = 'conflict' === $type ? 'conflict' : 'blocked';
    $plan['issues'][] = [
        'type'     => $type,
        'identity' => sanitize_text_field($identity),
        'message'  => sanitize_text_field($message),
    ];
    $plan['counts'][
        'conflict' === $type ? 'conflicts' : 'blocked'
    ] += max(1, absint($count));
}

/**
 * Return a compact portable identity label.
 *
 * @param array $row    Canonical projection.
 * @param array $fields Identity fields.
 *
 * @return string
 */
function nwmd_directory_get_ai_state_import_identity_label(
    array $row,
    array $fields
) {

    $parts = [];

    foreach ($fields as $field) {
        $parts[] = $field . '=' . (string) ($row[$field] ?? '');
    }

    return implode(' | ', $parts);
}

/**
 * Normalize one export record group with its canonical schema columns.
 *
 * @param string $record_name Record group name.
 * @param array  $rows        Raw records.
 *
 * @return array|WP_Error
 */
function nwmd_directory_normalize_ai_state_import_group(
    $record_name,
    array $rows
) {

    $schema = nwmd_directory_get_ai_state_export_schema();
    $columns = (array) (
        $schema['records'][$record_name]['columns'] ?? []
    );

    if (empty($columns)) {
        return new WP_Error(
            'nwmd_ai_state_import_schema_missing',
            __(
                'An AI State record schema is unavailable.',
                'local-directory-framework'
            )
        );
    }

    return nwmd_directory_normalize_ai_state_comparison_rows(
        $record_name,
        $rows,
        $columns
    );
}

/**
 * Project and index Business rows by portable slug.
 *
 * @param array $rows Normalized Business rows.
 *
 * @return array|WP_Error
 */
function nwmd_directory_project_ai_state_import_business_rows(
    array $rows
) {

    $projected = [];
    $index     = [];

    foreach ($rows as $row_number => $row) {
        $projection =
            nwmd_directory_project_ai_state_import_business($row);

        if (is_wp_error($projection)) {
            return $projection;
        }

        $projected[$row_number] = $projection;
        $slug = (string) $projection['business_slug'];

        if (!isset($index[$slug])) {
            $index[$slug] = [];
        }

        $index[$slug][] = $row_number;
    }

    return [
        'rows'  => $projected,
        'index' => $index,
    ];
}

/**
 * Project and index source rows by exact portable identity.
 *
 * @param array $rows Normalized source rows.
 *
 * @return array|WP_Error
 */
function nwmd_directory_project_ai_state_import_source_rows(
    array $rows
) {

    $projected = [];
    $index     = [];
    $semantic  = [];

    foreach ($rows as $row_number => $row) {
        $projection =
            nwmd_directory_project_ai_state_import_source($row);

        if (is_wp_error($projection)) {
            return $projection;
        }

        $projected[$row_number] = $projection;
        $key =
            nwmd_directory_get_ai_state_import_source_identity_key(
                $projection
            );
        $url_key =
            nwmd_directory_get_ai_state_import_source_url_key(
                $projection
            );

        if (!isset($index[$key])) {
            $index[$key] = [];
        }

        $index[$key][] = $row_number;

        if ('' !== $url_key) {
            if (!isset($semantic[$url_key])) {
                $semantic[$url_key] = [];
            }

            $semantic[$url_key][] = $row_number;
        }
    }

    return [
        'rows'     => $projected,
        'index'    => $index,
        'semantic' => $semantic,
    ];
}

/**
 * Return normalized protected-group fingerprints.
 *
 * @param array $records Export record groups.
 *
 * @return array|WP_Error
 */
function nwmd_directory_get_ai_state_import_protected_fingerprints(
    array $records
) {

    $fingerprints = [];

    foreach (
        [
            'deals',
            'queue',
            'runs',
            'taxonomy_terms',
        ] as $record_name
    ) {
        $rows = isset($records[$record_name]['rows'])
            ? (array) $records[$record_name]['rows']
            : (array) ($records[$record_name] ?? []);
        $normalized = nwmd_directory_normalize_ai_state_import_group(
            $record_name,
            $rows
        );

        if (is_wp_error($normalized)) {
            return $normalized;
        }

        $fingerprints[$record_name] =
            nwmd_directory_get_ai_state_import_fingerprint(
                $normalized
            );
    }

    return $fingerprints;
}

/**
 * Fingerprint every protected custom table not intentionally written.
 *
 * @return string|WP_Error
 */
function nwmd_directory_get_ai_state_import_auxiliary_fingerprint() {

    global $wpdb;

    $tables = [
        $wpdb->prefix . 'nwmd_business_deals',
        $wpdb->prefix . 'nwmd_ranking_periods',
        $wpdb->prefix . 'nwmd_ranking_entries',
        $wpdb->prefix . 'nwmd_business_requests',
        $wpdb->prefix . 'nwmd_ads',
        $wpdb->prefix . 'nwmd_operator_jobs',
        $wpdb->prefix . 'nwmd_operator_specialties',
        $wpdb->prefix . 'nwmd_operator_runs',
        $wpdb->prefix . 'nwmd_operator_usage',
    ];
    $state = [];

    foreach ($tables as $table) {
        $rows = $wpdb->get_results(
            "SELECT * FROM {$table} ORDER BY id ASC",
            ARRAY_A
        );

        if (null === $rows || '' !== (string) $wpdb->last_error) {
            return new WP_Error(
                'nwmd_ai_state_import_auxiliary_read_failed',
                __(
                    'Protected custom-table data could not be read.',
                    'local-directory-framework'
                )
            );
        }

        $state[$table] = $rows;
    }

    return nwmd_directory_get_ai_state_import_fingerprint($state);
}

/**
 * Build a controlled import plan from a fully validated package.
 *
 * @param array $package        Validated package.
 * @param array $current_export Current canonical export.
 * @param array $metadata       Safe package metadata.
 *
 * @return array|WP_Error
 */
function nwmd_directory_build_controlled_ai_state_import_plan(
    array $package,
    array $current_export,
    array $metadata
) {

    $manifest = (array) ($package['manifest'] ?? []);
    $uploaded = (array) ($package['records'] ?? []);
    $current  = (array) ($current_export['records'] ?? []);
    $comparison = nwmd_directory_compare_ai_state_records(
        $package,
        $current_export
    );

    if (is_wp_error($comparison)) {
        return $comparison;
    }

    $plan = [
        'plan_version'          => 2,
        'plan_id'               => wp_generate_uuid4(),
        'user_id'               => get_current_user_id(),
        'package_hash'          => sanitize_text_field(
            (string) ($metadata['package_hash'] ?? '')
        ),
        'source_filename'       => sanitize_file_name(
            (string) ($metadata['source_filename'] ?? '')
        ),
        'format_version'        => sanitize_text_field(
            (string) ($manifest['format_version'] ?? '')
        ),
        'export_plugin_version' => sanitize_text_field(
            (string) ($manifest['plugin_version'] ?? '')
        ),
        'generated_at'          => sanitize_text_field(
            (string) ($manifest['generated_at_utc'] ?? '')
        ),
        'prepared_at'           => current_time('mysql', true),
        'expires_at'            => time()
            + nwmd_directory_get_ai_state_import_plan_ttl(),
        'counts'                => [
            'businesses_new'             => 0,
            'businesses_updated'         => 0,
            'businesses_unchanged'       => 0,
            'businesses_missing_ignored' => 0,
            'sources_new'                => 0,
            'sources_updated'            => 0,
            'sources_unchanged'          => 0,
            'sources_missing_ignored'    => 0,
            'blocked'                    => 0,
            'conflicts'                  => 0,
        ],
        'protected'             => [],
        'writes'                => [
            'businesses_create' => [],
            'businesses_update' => [],
            'sources_create'    => [],
            'sources_update'    => [],
        ],
        'expected'              => [
            'businesses' => [],
            'sources'    => [],
            'protected'  => [],
            'auxiliary'  => '',
        ],
        'issues'                => [],
        'executable'            => false,
    ];

    if (
        '3.2' !== $plan['format_version']
        || !preg_match('/^[a-f0-9]{64}$/', $plan['package_hash'])
        || '' === $plan['source_filename']
    ) {
        return new WP_Error(
            'nwmd_ai_state_import_metadata_invalid',
            __(
                'The controlled import package metadata is invalid.',
                'local-directory-framework'
            )
        );
    }

    $protected_labels = [
        'deals'          => __('Deals', 'local-directory-framework'),
        'queue'          => __(
            'Queue checkpoints',
            'local-directory-framework'
        ),
        'runs'           => __(
            'Operator runs',
            'local-directory-framework'
        ),
        'taxonomy_terms' => __(
            'Taxonomy terms',
            'local-directory-framework'
        ),
    ];
    $protected =
        nwmd_directory_get_ai_state_import_protected_fingerprints(
            $current
        );

    if (is_wp_error($protected)) {
        return $protected;
    }

    $plan['expected']['protected'] = $protected;
    $auxiliary =
        nwmd_directory_get_ai_state_import_auxiliary_fingerprint();

    if (is_wp_error($auxiliary)) {
        return $auxiliary;
    }

    $plan['expected']['auxiliary'] = $auxiliary;

    foreach ($protected_labels as $name => $label) {
        $counts = (array) ($comparison[$name] ?? []);
        $changed = absint($counts['added'] ?? 0)
            + absint($counts['changed'] ?? 0)
            + absint($counts['missing_from_upload'] ?? 0)
            + absint($counts['conflicting'] ?? 0);
        $plan['protected'][$name] = [
            'label'  => $label,
            'status' => 0 === $changed ? 'unchanged' : 'blocked',
            'counts' => $counts,
        ];

        if ($changed > 0) {
            nwmd_directory_add_ai_state_import_issue(
                $plan,
                'blocked',
                $label,
                __(
                    'This protected record group is not completely unchanged.',
                    'local-directory-framework'
                ),
                $changed
            );
        }
    }

    $business_rows = [];
    $source_rows   = [];

    foreach (['businesses', 'sources'] as $name) {
        $uploaded_rows = isset($uploaded[$name])
            ? (array) $uploaded[$name]
            : null;
        $current_rows = isset($current[$name]['rows'])
            ? (array) $current[$name]['rows']
            : null;

        if (null === $uploaded_rows || null === $current_rows) {
            return new WP_Error(
                'nwmd_ai_state_import_group_missing',
                __(
                    'A required AI State record group is unavailable.',
                    'local-directory-framework'
                )
            );
        }

        $uploaded_normalized =
            nwmd_directory_normalize_ai_state_import_group(
                $name,
                $uploaded_rows
            );
        $current_normalized =
            nwmd_directory_normalize_ai_state_import_group(
                $name,
                $current_rows
            );

        if (
            is_wp_error($uploaded_normalized)
            || is_wp_error($current_normalized)
        ) {
            return is_wp_error($uploaded_normalized)
                ? $uploaded_normalized
                : $current_normalized;
        }

        $projector = 'businesses' === $name
            ? 'nwmd_directory_project_ai_state_import_business_rows'
            : 'nwmd_directory_project_ai_state_import_source_rows';
        $business_or_source_uploaded = $projector($uploaded_normalized);
        $business_or_source_current = $projector($current_normalized);

        if (
            is_wp_error($business_or_source_uploaded)
            || is_wp_error($business_or_source_current)
        ) {
            return is_wp_error($business_or_source_uploaded)
                ? $business_or_source_uploaded
                : $business_or_source_current;
        }

        if ('businesses' === $name) {
            $business_rows = [
                'uploaded' => $business_or_source_uploaded,
                'current'  => $business_or_source_current,
            ];
        } else {
            $source_rows = [
                'uploaded' => $business_or_source_uploaded,
                'current'  => $business_or_source_current,
            ];
        }
    }

    $business_posts =
        nwmd_directory_get_ai_state_import_business_posts();

    if (is_wp_error($business_posts)) {
        return $business_posts;
    }

    $business_state = [];
    $business_schema =
        nwmd_directory_get_ai_state_import_business_projection_schema();
    $new_business_indexes = [];

    foreach (
        $business_rows['uploaded']['index'] as $slug => $matches
    ) {
        $current_matches =
            $business_rows['current']['index'][$slug] ?? [];
        $target = $business_rows['uploaded']['rows'][$matches[0]];
        $identity = 'business_slug=' . $slug;

        if (1 !== count($matches) || count($current_matches) > 1) {
            nwmd_directory_add_ai_state_import_issue(
                $plan,
                'conflict',
                $identity,
                __(
                    'The Business portable identity is duplicated.',
                    'local-directory-framework'
                ),
                max(count($matches), count($current_matches))
            );
            $business_state[$slug] = ['writable' => false];
            continue;
        }

        $post_matches = $business_posts[$slug] ?? [];

        if (1 === count($current_matches)) {
            if (1 !== count($post_matches)) {
                nwmd_directory_add_ai_state_import_issue(
                    $plan,
                    'conflict',
                    $identity,
                    __(
                        'The current Business slug does not resolve to one post.',
                        'local-directory-framework'
                    )
                );
                $business_state[$slug] = ['writable' => false];
                continue;
            }

            $current_projection = $business_rows['current']['rows'][
                $current_matches[0]
            ];
            $post_state = $post_matches[0];

            if (
                $target['business_name']
                    !== $current_projection['business_name']
                && $current_projection['public_name']
                    === $current_projection['business_name']
                && $target['public_name']
                    === $current_projection['public_name']
            ) {
                $target['public_name'] = $target['business_name'];
            }

            $all_changes =
                nwmd_directory_get_ai_state_import_projection_changes(
                    $target,
                    $current_projection,
                    $business_schema,
                    [
                        'identity',
                        'safety',
                        'taxonomy',
                        'writable',
                    ]
                );

            if (empty($all_changes)) {
                $plan['counts']['businesses_unchanged']++;
                $business_state[$slug] = [
                    'writable'    => 'draft' === $post_state['status'],
                    'post_id'     => absint($post_state['id']),
                    'post_status' => (string) $post_state['status'],
                    'projection'  => $current_projection,
                    'fingerprint' =>
                        nwmd_directory_get_ai_state_import_fingerprint(
                            $current_projection
                        ),
                    'lock_version' => (string) $post_state['modified'],
                ];
                continue;
            }

            $taxonomy_changes =
                nwmd_directory_get_ai_state_import_projection_changes(
                    $target,
                    $current_projection,
                    $business_schema,
                    ['identity', 'safety', 'taxonomy']
                );

            if (
                !empty($taxonomy_changes)
                || 'draft' !== $post_state['status']
                || 'draft' !== $target['post_status']
            ) {
                nwmd_directory_add_ai_state_import_issue(
                    $plan,
                    'blocked',
                    $identity,
                    __(
                        'Only writable fields on an existing Draft Business may change.',
                        'local-directory-framework'
                    )
                );
                $business_state[$slug] = ['writable' => false];
                continue;
            }

            $changes =
                nwmd_directory_get_ai_state_import_projection_changes(
                    $target,
                    $current_projection,
                    $business_schema,
                    ['writable']
                );
            $post_id = absint($post_state['id']);
            $fingerprint =
                nwmd_directory_get_ai_state_import_fingerprint(
                    $current_projection
                );
            $item = [
                'identity'       => $identity,
                'business_slug'  => $slug,
                'post_id'        => $post_id,
                'lock_version'   => (string) $post_state['modified'],
                'target'         => $target,
                'changed_fields' => $changes,
            ];
            $plan['writes']['businesses_update'][] = $item;
            $plan['expected']['businesses'][$slug] = [
                'state'        => 'present',
                'post_id'      => $post_id,
                'lock_version' => (string) $post_state['modified'],
                'fingerprint'  => $fingerprint,
            ];
            $plan['counts']['businesses_updated']++;
            $business_state[$slug] = [
                'writable'     => true,
                'post_id'      => $post_id,
                'post_status'  => 'draft',
                'projection'   => $current_projection,
                'fingerprint'  => $fingerprint,
                'lock_version' => (string) $post_state['modified'],
            ];
            continue;
        }

        if (
            !empty($post_matches)
            || 'draft' !== $target['post_status']
            || '0' !== $target['ranking_eligible']
        ) {
            nwmd_directory_add_ai_state_import_issue(
                $plan,
                'conflict',
                $identity,
                __(
                    'A new Business must have a unique Draft, ranking-ineligible identity.',
                    'local-directory-framework'
                )
            );
            $business_state[$slug] = ['writable' => false];
            continue;
        }

        $terms = nwmd_directory_resolve_ai_state_import_terms(
            $target,
            true
        );

        if (is_wp_error($terms)) {
            nwmd_directory_add_ai_state_import_issue(
                $plan,
                'blocked',
                $identity,
                $terms->get_error_message()
            );
            $business_state[$slug] = ['writable' => false];
            continue;
        }

        $changes =
            nwmd_directory_get_ai_state_import_projection_changes(
                $target,
                [],
                $business_schema,
                ['identity', 'safety', 'taxonomy', 'writable']
            );
        $plan['writes']['businesses_create'][] = [
            'identity'       => $identity,
            'business_slug'  => $slug,
            'post_id'        => 0,
            'lock_version'   => '',
            'target'         => $target,
            'changed_fields' => $changes,
        ];
        $plan['expected']['businesses'][$slug] = [
            'state'        => 'absent',
            'post_id'      => 0,
            'lock_version' => '',
            'fingerprint'  => '',
        ];
        $plan['counts']['businesses_new']++;
        $business_state[$slug] = [
            'writable'     => true,
            'post_id'      => 0,
            'post_status'  => 'draft',
            'projection'   => $target,
            'fingerprint'  => '',
            'lock_version' => '',
        ];
        $new_business_indexes[] = count(
            $plan['writes']['businesses_create']
        ) - 1;
    }

    foreach ($business_rows['current']['index'] as $slug => $matches) {
        if (!isset($business_rows['uploaded']['index'][$slug])) {
            $plan['counts']['businesses_missing_ignored'] +=
                count($matches);
        }
    }

    if (
        $plan['counts']['businesses_missing_ignored'] > 0
        && !empty($new_business_indexes)
    ) {
        foreach ($new_business_indexes as $index) {
            $item = $plan['writes']['businesses_create'][$index];
            nwmd_directory_add_ai_state_import_issue(
                $plan,
                'conflict',
                (string) $item['identity'],
                __(
                    'New and missing Businesses together are an ambiguous slug change.',
                    'local-directory-framework'
                )
            );
        }
    }

    $source_records =
        nwmd_directory_get_ai_state_import_source_records();

    if (is_wp_error($source_records)) {
        return $source_records;
    }

    $source_schema =
        nwmd_directory_get_ai_state_import_source_projection_schema();
    $source_identity_fields =
        nwmd_directory_get_ai_state_import_projection_fields(
            $source_schema,
            ['identity']
        );
    $new_source_indexes = [];
    $missing_sources_by_business = [];

    foreach ($source_rows['current']['index'] as $key => $matches) {
        if (isset($source_rows['uploaded']['index'][$key])) {
            continue;
        }

        $plan['counts']['sources_missing_ignored'] += count($matches);

        foreach ($matches as $row_number) {
            $business_slug = (string) (
                $source_rows['current']['rows'][$row_number][
                    'business_slug'
                ] ?? ''
            );
            $missing_sources_by_business[$business_slug] = true;
        }
    }

    foreach ($source_rows['uploaded']['index'] as $key => $matches) {
        $current_matches = $source_rows['current']['index'][$key] ?? [];
        $target = $source_rows['uploaded']['rows'][$matches[0]];
        $identity =
            nwmd_directory_get_ai_state_import_identity_label(
                $target,
                $source_identity_fields
            );

        if (1 !== count($matches) || count($current_matches) > 1) {
            nwmd_directory_add_ai_state_import_issue(
                $plan,
                'conflict',
                $identity,
                __(
                    'The Business Source portable identity is duplicated.',
                    'local-directory-framework'
                ),
                max(count($matches), count($current_matches))
            );
            continue;
        }

        $record_matches = $source_records[$key] ?? [];

        if (
            1 === count($current_matches)
            && 1 !== count($record_matches)
        ) {
            nwmd_directory_add_ai_state_import_issue(
                $plan,
                'conflict',
                $identity,
                __(
                    'The current source identity does not resolve to one row.',
                    'local-directory-framework'
                )
            );
            continue;
        }

        $current_projection = 1 === count($current_matches)
            ? $source_rows['current']['rows'][$current_matches[0]]
            : [];
        $changes =
            nwmd_directory_get_ai_state_import_projection_changes(
                $target,
                $current_projection,
                $source_schema,
                1 === count($current_matches)
                    ? ['writable']
                    : ['identity', 'writable']
            );

        if (1 === count($current_matches) && empty($changes)) {
            $plan['counts']['sources_unchanged']++;
            continue;
        }

        $business_slug = (string) $target['business_slug'];
        $parent = $business_state[$business_slug] ?? [];

        if (
            empty($parent['writable'])
            || 'draft' !== ($parent['post_status'] ?? '')
        ) {
            nwmd_directory_add_ai_state_import_issue(
                $plan,
                'blocked',
                $identity,
                __(
                    'A source write requires an approved Draft Business.',
                    'local-directory-framework'
                )
            );
            continue;
        }

        $url_key =
            nwmd_directory_get_ai_state_import_source_url_key($target);
        $semantic_conflict = false;

        if ('' !== $url_key) {
            foreach (
                $source_rows['uploaded']['semantic'][$url_key] ?? []
                as $row_number
            ) {
                $row = $source_rows['uploaded']['rows'][$row_number];

                if (
                    nwmd_directory_get_ai_state_import_source_identity_key(
                        $row
                    ) !== $key
                ) {
                    $semantic_conflict = true;
                    break;
                }
            }

            foreach (
                $source_rows['current']['semantic'][$url_key] ?? []
                as $row_number
            ) {
                $row = $source_rows['current']['rows'][$row_number];

                if (
                    nwmd_directory_get_ai_state_import_source_identity_key(
                        $row
                    ) !== $key
                ) {
                    $semantic_conflict = true;
                    break;
                }
            }
        }

        if ($semantic_conflict) {
            nwmd_directory_add_ai_state_import_issue(
                $plan,
                'conflict',
                $identity,
                __(
                    'The source URL creates an ambiguous semantic duplicate.',
                    'local-directory-framework'
                )
            );
            continue;
        }

        if (1 === count($current_matches)) {
            $record = $record_matches[0];
            $item = [
                'identity'       => $identity,
                'source_id'      => absint($record['id']),
                'business_slug'  => $business_slug,
                'lock_version'   => (string) $record['updated_at'],
                'target'         => $target,
                'changed_fields' => $changes,
            ];
            $plan['writes']['sources_update'][] = $item;
            $plan['expected']['sources'][$key] = [
                'state'        => 'present',
                'source_id'    => absint($record['id']),
                'lock_version' => (string) $record['updated_at'],
                'fingerprint'  =>
                    nwmd_directory_get_ai_state_import_fingerprint(
                        $current_projection
                    ),
            ];
            $plan['counts']['sources_updated']++;
        } else {
            if (!empty($record_matches)) {
                nwmd_directory_add_ai_state_import_issue(
                    $plan,
                    'conflict',
                    $identity,
                    __(
                        'The proposed source identity already exists.',
                        'local-directory-framework'
                    )
                );
                continue;
            }

            $plan['writes']['sources_create'][] = [
                'identity'       => $identity,
                'source_id'      => 0,
                'business_slug'  => $business_slug,
                'lock_version'   => '',
                'target'         => $target,
                'changed_fields' => $changes,
            ];
            $plan['expected']['sources'][$key] = [
                'state'        => 'absent',
                'source_id'    => 0,
                'lock_version' => '',
                'fingerprint'  => '',
            ];
            $plan['counts']['sources_new']++;
            $new_source_indexes[] = count(
                $plan['writes']['sources_create']
            ) - 1;
        }

        if (
            !isset($plan['expected']['businesses'][$business_slug])
            && !empty($parent['post_id'])
        ) {
            $plan['expected']['businesses'][$business_slug] = [
                'state'        => 'present',
                'post_id'      => absint($parent['post_id']),
                'lock_version' => (string) $parent['lock_version'],
                'fingerprint'  => (string) $parent['fingerprint'],
            ];
        }
    }

    foreach ($new_source_indexes as $index) {
        $item = $plan['writes']['sources_create'][$index];

        if (!empty($missing_sources_by_business[$item['business_slug']])) {
            nwmd_directory_add_ai_state_import_issue(
                $plan,
                'conflict',
                (string) $item['identity'],
                __(
                    'New and missing sources for one Business are an ambiguous identity change.',
                    'local-directory-framework'
                )
            );
        }
    }

    $business_writes = array_merge(
        $plan['writes']['businesses_create'],
        $plan['writes']['businesses_update']
    );
    $unique =
        nwmd_directory_validate_ai_state_import_business_uniqueness(
            $business_writes
        );

    if (is_wp_error($unique)) {
        nwmd_directory_add_ai_state_import_issue(
            $plan,
            'conflict',
            'Businesses',
            $unique->get_error_message()
        );
    }

    $write_count = count($business_writes)
        + count($plan['writes']['sources_create'])
        + count($plan['writes']['sources_update']);

    if ($write_count > 500) {
        return new WP_Error(
            'nwmd_ai_state_import_plan_limit',
            __(
                'The controlled import exceeds the 500-write limit.',
                'local-directory-framework'
            )
        );
    }

    $plan['executable'] = $write_count > 0
        && 0 === $plan['counts']['blocked']
        && 0 === $plan['counts']['conflicts'];

    if (strlen(maybe_serialize($plan)) > 2 * MB_IN_BYTES) {
        return new WP_Error(
            'nwmd_ai_state_import_plan_size',
            __(
                'The controlled import plan is too large to store safely.',
                'local-directory-framework'
            )
        );
    }

    return $plan;
}

/**
 * Remove an uploaded ZIP after preparation.
 *
 * @param mixed $file Uploaded file entry.
 */
function nwmd_directory_cleanup_ai_state_import_upload($file) {

    $tmp_name = is_array($file)
        ? (string) ($file['tmp_name'] ?? '')
        : '';

    if (
        '' !== $tmp_name
        && is_uploaded_file($tmp_name)
        && file_exists($tmp_name)
    ) {
        wp_delete_file($tmp_name);
    }
}

/**
 * Handle controlled-import preparation without directory writes.
 */
function nwmd_directory_handle_ai_state_import_prepare() {

    if (!nwmd_directory_controlled_ai_state_import_is_enabled()) {
        wp_die(
            esc_html__('Controlled AI State Import is disabled.', 'local-directory-framework'),
            esc_html__('Access denied', 'local-directory-framework'),
            ['response' => 403]
        );
    }

    if (!current_user_can('manage_options')) {
        wp_die(
            esc_html__(
                'You are not allowed to prepare AI State imports.',
                'local-directory-framework'
            ),
            esc_html__('Access denied', 'local-directory-framework'),
            ['response' => 403]
        );
    }

    check_admin_referer(
        'nwmd_directory_prepare_controlled_ai_state_import'
    );
    nwmd_directory_delete_ai_state_import_plan();

    $file = isset($_FILES['nwmd_ai_state_controlled_import_zip'])
        && is_array($_FILES['nwmd_ai_state_controlled_import_zip'])
            ? $_FILES['nwmd_ai_state_controlled_import_zip']
            : null;
    $error = null;

    try {
        $upload = nwmd_directory_validate_ai_state_upload($file);

        if (is_wp_error($upload)) {
            $error = $upload;
        } else {
            $tmp_name = (string) ($file['tmp_name'] ?? '');
            $package_hash = hash_file('sha256', $tmp_name);

            if (
                !is_string($package_hash)
                || !preg_match('/^[a-f0-9]{64}$/', $package_hash)
            ) {
                $error = new WP_Error(
                    'nwmd_ai_state_import_hash_failed',
                    __(
                        'The uploaded package hash could not be calculated.',
                        'local-directory-framework'
                    )
                );
            } else {
                $current =
                    nwmd_directory_get_ai_state_export_records();

                if (is_wp_error($current)) {
                    $error = $current;
                } else {
                    $plan =
                        nwmd_directory_build_controlled_ai_state_import_plan(
                            (array) ($upload['package'] ?? []),
                            $current,
                            [
                                'package_hash'   => $package_hash,
                                'source_filename' => (string) (
                                    $upload['filename'] ?? ''
                                ),
                            ]
                        );

                    if (is_wp_error($plan)) {
                        $error = $plan;
                    } elseif (
                        !nwmd_directory_store_ai_state_import_plan($plan)
                    ) {
                        $error = new WP_Error(
                            'nwmd_ai_state_import_plan_store_failed',
                            __(
                                'The prepared import plan could not be stored safely.',
                                'local-directory-framework'
                            )
                        );
                    }
                }
            }
        }
    } catch (Throwable $throwable) {
        unset($throwable);
        $error = new WP_Error(
            'nwmd_ai_state_import_prepare_exception',
            __(
                'The controlled import could not be prepared safely.',
                'local-directory-framework'
            )
        );
    } finally {
        nwmd_directory_cleanup_ai_state_import_upload($file);
    }

    if ($error instanceof WP_Error) {
        nwmd_directory_store_ai_state_import_result(
            [
                'success'         => false,
                'phase'           => 'prepare',
                'message'         => sanitize_text_field(
                    $error->get_error_message()
                ),
                'rollback_result' => 'not_required',
                'counts'          => [],
            ]
        );
        nwmd_directory_redirect_ai_state_import(
            'ai_state_import_result'
        );
    }

    nwmd_directory_redirect_ai_state_import(
        'ai_state_import_prepared'
    );
}
