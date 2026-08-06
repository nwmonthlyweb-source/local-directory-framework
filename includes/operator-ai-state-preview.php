<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return portable identities and conflict rules for AI state records.
 *
 * @return array
 */
function nwmd_directory_get_ai_state_comparison_definitions() {

    return [
        'businesses' => [
            'label' =>
                __('Businesses', 'local-directory-framework'),
            'identity_fields' => [
                'business_slug',
            ],
        ],
        'sources' => [
            'label' =>
                __('Business sources', 'local-directory-framework'),
            'identity_fields' => [
                'business_slug',
                'source_type',
                'source_identifier',
                'source_url',
            ],
        ],
        'deals' => [
            'label' =>
                __('Deals', 'local-directory-framework'),
            'identity_fields' => [
                'business_slug',
                'deal_slug',
            ],
        ],
        'queue' => [
            'label' =>
                __('Queue checkpoints', 'local-directory-framework'),
            'identity_fields' => [
                'job_key',
                'specialty_slug',
            ],
            'group_identity_fields' => [
                'job_key',
            ],
            'group_linkage_fields' => [
                'state_slug',
                'city_slug',
                'category_slug',
            ],
            'alternate_identity_fields' => [
                'state_slug',
                'city_slug',
                'category_slug',
                'specialty_slug',
            ],
            'conflict_fields' => [
                'state_slug',
                'city_slug',
                'category_slug',
                'specialty_slug',
            ],
        ],
        'runs' => [
            'label' =>
                __('Operator runs', 'local-directory-framework'),
            'identity_fields' => [
                'run_uuid',
            ],
            'conflict_fields' => [
                'job_key',
                'state_slug',
                'city_slug',
                'category_slug',
                'specialty_slug',
            ],
        ],
        'taxonomy_terms' => [
            'label' =>
                __('Taxonomy terms', 'local-directory-framework'),
            'identity_fields' => [
                'taxonomy',
                'term_slug',
            ],
        ],
    ];
}

/**
 * Normalize rows to their ordered AI state CSV representation.
 *
 * @param string $record_name Record group name.
 * @param array  $rows        Rows to normalize.
 * @param array  $columns     Schema columns in portable order.
 *
 * @return array|WP_Error
 */
function nwmd_directory_normalize_ai_state_comparison_rows(
    $record_name,
    array $rows,
    array $columns
) {

    $normalized_rows = [];

    foreach ($rows as $row_number => $row) {
        if (!is_array($row)) {
            return new WP_Error(
                'nwmd_ai_state_preview_row_invalid',
                sprintf(
                    __(
                        'The AI state preview received an invalid %1$s row at position %2$d.',
                        'local-directory-framework'
                    ),
                    sanitize_key($record_name),
                    absint($row_number) + 1
                )
            );
        }

        $normalized_row = [];

        foreach ($columns as $column) {
            if (!array_key_exists($column, $row)) {
                return new WP_Error(
                    'nwmd_ai_state_preview_column_missing',
                    sprintf(
                        __(
                            'The AI state preview is missing a required %s field.',
                            'local-directory-framework'
                        ),
                        sanitize_key($record_name)
                    )
                );
            }

            $value = nwmd_directory_get_ai_state_csv_value(
                $row[$column]
            );

            if (is_wp_error($value)) {
                return $value;
            }

            $normalized_row[$column] = $value;
        }

        $normalized_rows[] = $normalized_row;
    }

    return $normalized_rows;
}

/**
 * Build a collision-safe key from portable identity fields.
 *
 * Identity values are trimmed consistently with the ZIP validator.
 *
 * @param array $row    Normalized record row.
 * @param array $fields Ordered identity fields.
 *
 * @return string
 */
function nwmd_directory_get_ai_state_comparison_key(
    array $row,
    array $fields
) {

    $key = '';

    foreach ($fields as $field) {
        $value = trim(
            (string) ($row[$field] ?? '')
        );

        $key .= strlen($value)
            . ':'
            . $value
            . ';';
    }

    return $key;
}

/**
 * Index normalized rows by a portable identity.
 *
 * @param array $rows   Normalized rows.
 * @param array $fields Ordered identity fields.
 *
 * @return array
 */
function nwmd_directory_index_ai_state_comparison_rows(
    array $rows,
    array $fields
) {

    $index = [];

    foreach ($rows as $row_number => $row) {
        $key = nwmd_directory_get_ai_state_comparison_key(
            $row,
            $fields
        );

        if (!isset($index[$key])) {
            $index[$key] = [];
        }

        $index[$key][] = $row_number;
    }

    return $index;
}

/**
 * Group unmatched primary identities by a secondary identity.
 *
 * @param array $pending Pending primary key to row-number map.
 * @param array $rows    Normalized rows.
 * @param array $fields  Secondary identity fields.
 *
 * @return array
 */
function nwmd_directory_index_ai_state_pending_rows(
    array $pending,
    array $rows,
    array $fields
) {

    $index = [];

    foreach ($pending as $primary_key => $row_number) {
        $key = nwmd_directory_get_ai_state_comparison_key(
            $rows[$row_number],
            $fields
        );

        if (!isset($index[$key])) {
            $index[$key] = [];
        }

        $index[$key][] = $primary_key;
    }

    return $index;
}

/**
 * Compare one uploaded record group with its current record group.
 *
 * @param array $uploaded_rows Uploaded normalized rows.
 * @param array $current_rows  Current normalized rows.
 * @param array $definition    Portable identity definition.
 *
 * @return array
 */
function nwmd_directory_compare_ai_state_record_group(
    array $uploaded_rows,
    array $current_rows,
    array $definition
) {

    $identity_fields = (array) (
        $definition['identity_fields'] ?? []
    );

    $conflict_fields = (array) (
        $definition['conflict_fields'] ?? []
    );

    $alternate_fields = (array) (
        $definition['alternate_identity_fields'] ?? []
    );

    $group_identity_fields = (array) (
        $definition['group_identity_fields'] ?? []
    );

    $group_linkage_fields = (array) (
        $definition['group_linkage_fields'] ?? []
    );

    $uploaded_index =
        nwmd_directory_index_ai_state_comparison_rows(
            $uploaded_rows,
            $identity_fields
        );

    $current_index =
        nwmd_directory_index_ai_state_comparison_rows(
            $current_rows,
            $identity_fields
        );

    $counts = [
        'current'             => count($current_rows),
        'uploaded'            => count($uploaded_rows),
        'added'               => 0,
        'changed'             => 0,
        'unchanged'           => 0,
        'missing_from_upload' => 0,
        'conflicting'         => 0,
    ];

    /*
     * Resolve shared job linkage conflicts before checkpoint matching.
     * Removing their primary keys here prevents a second outcome later.
     */
    if (
        !empty($group_identity_fields)
        && !empty($group_linkage_fields)
    ) {
        $uploaded_group_index =
            nwmd_directory_index_ai_state_comparison_rows(
                $uploaded_rows,
                $group_identity_fields
            );

        $current_group_index =
            nwmd_directory_index_ai_state_comparison_rows(
                $current_rows,
                $group_identity_fields
            );

        $shared_group_keys = array_intersect(
            array_keys($uploaded_group_index),
            array_keys($current_group_index)
        );

        foreach ($shared_group_keys as $group_key) {
            $uploaded_linkages = [];

            foreach (
                $uploaded_group_index[$group_key]
                as $row_number
            ) {
                $uploaded_linkages[] =
                    nwmd_directory_get_ai_state_comparison_key(
                        $uploaded_rows[$row_number],
                        $group_linkage_fields
                    );
            }

            $current_linkages = [];

            foreach (
                $current_group_index[$group_key]
                as $row_number
            ) {
                $current_linkages[] =
                    nwmd_directory_get_ai_state_comparison_key(
                        $current_rows[$row_number],
                        $group_linkage_fields
                    );
            }

            $uploaded_linkages = array_values(
                array_unique($uploaded_linkages)
            );
            $current_linkages = array_values(
                array_unique($current_linkages)
            );

            sort($uploaded_linkages, SORT_STRING);
            sort($current_linkages, SORT_STRING);

            if ($uploaded_linkages === $current_linkages) {
                continue;
            }

            $conflicting_primary_keys = [];

            foreach (
                $uploaded_group_index[$group_key]
                as $row_number
            ) {
                $conflicting_primary_keys[] =
                    nwmd_directory_get_ai_state_comparison_key(
                        $uploaded_rows[$row_number],
                        $identity_fields
                    );
            }

            foreach (
                $current_group_index[$group_key]
                as $row_number
            ) {
                $conflicting_primary_keys[] =
                    nwmd_directory_get_ai_state_comparison_key(
                        $current_rows[$row_number],
                        $identity_fields
                    );
            }

            $conflicting_primary_keys = array_values(
                array_unique($conflicting_primary_keys)
            );

            foreach (
                $conflicting_primary_keys as $primary_key
            ) {
                $counts['conflicting'] += max(
                    count($uploaded_index[$primary_key] ?? []),
                    count($current_index[$primary_key] ?? [])
                );

                unset($uploaded_index[$primary_key]);
                unset($current_index[$primary_key]);
            }
        }
    }

    $pending_uploaded = [];
    $pending_current  = [];

    $primary_keys = array_values(
        array_unique(
            array_merge(
                array_keys($uploaded_index),
                array_keys($current_index)
            )
        )
    );

    sort($primary_keys, SORT_STRING);

    foreach ($primary_keys as $primary_key) {
        $uploaded_matches =
            $uploaded_index[$primary_key] ?? [];
        $current_matches =
            $current_index[$primary_key] ?? [];

        $uploaded_count = count($uploaded_matches);
        $current_count  = count($current_matches);

        if ($uploaded_count > 1 || $current_count > 1) {
            $counts['conflicting'] += max(
                $uploaded_count,
                $current_count
            );
            continue;
        }

        if (1 === $uploaded_count && 1 === $current_count) {
            $uploaded_row =
                $uploaded_rows[$uploaded_matches[0]];
            $current_row =
                $current_rows[$current_matches[0]];

            if (
                !empty($conflict_fields)
                && nwmd_directory_get_ai_state_comparison_key(
                    $uploaded_row,
                    $conflict_fields
                ) !== nwmd_directory_get_ai_state_comparison_key(
                    $current_row,
                    $conflict_fields
                )
            ) {
                $counts['conflicting']++;
            } elseif ($uploaded_row === $current_row) {
                $counts['unchanged']++;
            } else {
                $counts['changed']++;
            }

            continue;
        }

        if (1 === $uploaded_count) {
            $pending_uploaded[$primary_key] =
                $uploaded_matches[0];
        }

        if (1 === $current_count) {
            $pending_current[$primary_key] =
                $current_matches[0];
        }
    }

    if (
        !empty($alternate_fields)
        && !empty($pending_uploaded)
        && !empty($pending_current)
    ) {
        $uploaded_alternates =
            nwmd_directory_index_ai_state_pending_rows(
                $pending_uploaded,
                $uploaded_rows,
                $alternate_fields
            );

        $current_alternates =
            nwmd_directory_index_ai_state_pending_rows(
                $pending_current,
                $current_rows,
                $alternate_fields
            );

        $shared_alternates = array_intersect(
            array_keys($uploaded_alternates),
            array_keys($current_alternates)
        );

        foreach ($shared_alternates as $alternate_key) {
            $uploaded_primary_keys =
                $uploaded_alternates[$alternate_key];
            $current_primary_keys =
                $current_alternates[$alternate_key];

            $counts['conflicting'] += max(
                count($uploaded_primary_keys),
                count($current_primary_keys)
            );

            foreach ($uploaded_primary_keys as $primary_key) {
                unset($pending_uploaded[$primary_key]);
            }

            foreach ($current_primary_keys as $primary_key) {
                unset($pending_current[$primary_key]);
            }
        }
    }

    $counts['added'] = count($pending_uploaded);
    $counts['missing_from_upload'] =
        count($pending_current);

    return $counts;
}

/**
 * Compare a validated upload with the current read-only export records.
 *
 * @param array $uploaded_package Fully validated uploaded package.
 * @param array $current_export   Current normalized export records.
 *
 * @return array|WP_Error
 */
function nwmd_directory_compare_ai_state_records(
    array $uploaded_package,
    array $current_export
) {

    $schema = nwmd_directory_get_ai_state_export_schema();
    $definitions =
        nwmd_directory_get_ai_state_comparison_definitions();

    $uploaded_groups = isset($uploaded_package['records'])
        && is_array($uploaded_package['records'])
            ? $uploaded_package['records']
            : [];

    $current_groups = isset($current_export['records'])
        && is_array($current_export['records'])
            ? $current_export['records']
            : [];

    $comparisons = [];

    foreach ($definitions as $record_name => $definition) {
        $columns = isset(
            $schema['records'][$record_name]['columns']
        ) && is_array(
            $schema['records'][$record_name]['columns']
        )
            ? $schema['records'][$record_name]['columns']
            : [];

        $uploaded_rows = isset(
            $uploaded_groups[$record_name]
        ) && is_array($uploaded_groups[$record_name])
            ? $uploaded_groups[$record_name]
            : null;

        $current_rows = isset(
            $current_groups[$record_name]['rows']
        ) && is_array(
            $current_groups[$record_name]['rows']
        )
            ? $current_groups[$record_name]['rows']
            : null;

        if (
            empty($columns)
            || null === $uploaded_rows
            || null === $current_rows
        ) {
            return new WP_Error(
                'nwmd_ai_state_preview_records_missing',
                sprintf(
                    __(
                        'The AI state preview could not read the %s record group.',
                        'local-directory-framework'
                    ),
                    sanitize_key($record_name)
                )
            );
        }

        $uploaded_rows =
            nwmd_directory_normalize_ai_state_comparison_rows(
                $record_name,
                $uploaded_rows,
                $columns
            );

        if (is_wp_error($uploaded_rows)) {
            return $uploaded_rows;
        }

        $current_rows =
            nwmd_directory_normalize_ai_state_comparison_rows(
                $record_name,
                $current_rows,
                $columns
            );

        if (is_wp_error($current_rows)) {
            return $current_rows;
        }

        $comparisons[$record_name] =
            nwmd_directory_compare_ai_state_record_group(
                $uploaded_rows,
                $current_rows,
                $definition
            );
    }

    return $comparisons;
}

/**
 * Return the current user's AI state preview result key.
 *
 * @return string
 */
function nwmd_directory_get_ai_state_preview_result_key() {

    return 'nwmd_ai_state_preview_'
        . get_current_user_id();
}

/**
 * Store a short-lived AI state preview summary.
 *
 * @param array $result Preview result containing counts only.
 */
function nwmd_directory_store_ai_state_preview_result(
    array $result
) {

    set_transient(
        nwmd_directory_get_ai_state_preview_result_key(),
        $result,
        5 * MINUTE_IN_SECONDS
    );
}

/**
 * Read and remove the current user's AI state preview summary.
 *
 * @return array
 */
function nwmd_directory_take_ai_state_preview_result() {

    $key = nwmd_directory_get_ai_state_preview_result_key();
    $result = get_transient($key);

    delete_transient($key);

    return is_array($result)
        ? $result
        : [];
}

/**
 * Store a preview result and redirect to the Data Operator page.
 *
 * @param array $result Preview result.
 */
function nwmd_directory_finish_ai_state_preview(
    array $result
) {

    nwmd_directory_store_ai_state_preview_result($result);

    $url = add_query_arg(
        [
            'post_type' =>
                'nwmd_business',
            'page' =>
                'nwmd-data-operator',
            'ai_state_preview' =>
                '1',
        ],
        admin_url('edit.php')
    );

    wp_safe_redirect($url);
    exit;
}

/**
 * Render the latest read-only AI state import preview.
 */
function nwmd_directory_render_ai_state_preview_notice() {

    if (
        !isset($_GET['ai_state_preview'])
        || '1' !== sanitize_text_field(
            wp_unslash($_GET['ai_state_preview'])
        )
    ) {
        return;
    }

    $result = nwmd_directory_take_ai_state_preview_result();

    if (empty($result)) {
        return;
    }

    if (empty($result['success'])) {
        ?>
        <div class="notice notice-error is-dismissible">
            <p>
                <strong>
                    <?php
                    echo esc_html__(
                        'AI state import preview failed:',
                        'local-directory-framework'
                    );
                    ?>
                </strong>
                <?php
                echo esc_html(
                    (string) (
                        $result['message']
                        ?? __(
                            'The AI state ZIP could not be previewed.',
                            'local-directory-framework'
                        )
                    )
                );
                ?>
            </p>
        </div>
        <?php

        return;
    }

    $summary = isset($result['summary'])
        && is_array($result['summary'])
            ? $result['summary']
            : [];

    $comparisons = isset($summary['comparisons'])
        && is_array($summary['comparisons'])
            ? $summary['comparisons']
            : [];

    $definitions =
        nwmd_directory_get_ai_state_comparison_definitions();

    ?>
    <div class="notice notice-info is-dismissible">
        <h2>
            <?php
            echo esc_html__(
                'Read-only AI state import preview',
                'local-directory-framework'
            );
            ?>
        </h2>

        <p>
            <strong>
                <?php
                echo esc_html__(
                    'The ZIP passed validation and was compared with current normalized records.',
                    'local-directory-framework'
                );
                ?>
            </strong>
            <?php
            echo esc_html(
                (string) ($summary['filename'] ?? '')
            );
            ?>
        </p>

        <p>
            <?php
            echo esc_html(
                sprintf(
                    __(
                        'Format %1$s | Export plugin %2$s | Generated %3$s',
                        'local-directory-framework'
                    ),
                    (string) (
                        $summary['format_version'] ?? ''
                    ),
                    (string) (
                        $summary['plugin_version'] ?? ''
                    ),
                    (string) (
                        $summary['generated_at_utc'] ?? ''
                    )
                )
            );
            ?>
        </p>

        <table class="widefat striped" style="max-width: 1080px;">
            <thead>
                <tr>
                    <th scope="col">
                        <?php echo esc_html__('Record type', 'local-directory-framework'); ?>
                    </th>
                    <th scope="col">
                        <?php echo esc_html__('Current', 'local-directory-framework'); ?>
                    </th>
                    <th scope="col">
                        <?php echo esc_html__('Upload', 'local-directory-framework'); ?>
                    </th>
                    <th scope="col">
                        <?php echo esc_html__('Added', 'local-directory-framework'); ?>
                    </th>
                    <th scope="col">
                        <?php echo esc_html__('Changed', 'local-directory-framework'); ?>
                    </th>
                    <th scope="col">
                        <?php echo esc_html__('Unchanged', 'local-directory-framework'); ?>
                    </th>
                    <th scope="col">
                        <?php echo esc_html__('Missing from upload', 'local-directory-framework'); ?>
                    </th>
                    <th scope="col">
                        <?php echo esc_html__('Conflicting', 'local-directory-framework'); ?>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($definitions as $record_name => $definition) : ?>
                    <?php
                    $counts = isset($comparisons[$record_name])
                        && is_array($comparisons[$record_name])
                            ? $comparisons[$record_name]
                            : [];
                    ?>
                    <tr>
                        <th scope="row">
                            <?php
                            echo esc_html(
                                (string) ($definition['label'] ?? '')
                            );
                            ?>
                        </th>
                        <td><?php echo esc_html((string) absint($counts['current'] ?? 0)); ?></td>
                        <td><?php echo esc_html((string) absint($counts['uploaded'] ?? 0)); ?></td>
                        <td><?php echo esc_html((string) absint($counts['added'] ?? 0)); ?></td>
                        <td><?php echo esc_html((string) absint($counts['changed'] ?? 0)); ?></td>
                        <td><?php echo esc_html((string) absint($counts['unchanged'] ?? 0)); ?></td>
                        <td><?php echo esc_html((string) absint($counts['missing_from_upload'] ?? 0)); ?></td>
                        <td><?php echo esc_html((string) absint($counts['conflicting'] ?? 0)); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <p>
            <?php
            echo esc_html__(
                'Outcome counts use portable record identities. Conflicts are ambiguous identities or queue/run identity-linkage mismatches. This preview performed no import and changed no directory, Deal, taxonomy, queue, Operator, or ranking data.',
                'local-directory-framework'
            );
            ?>
        </p>
    </div>
    <?php
}

/**
 * Validate and compare an uploaded AI state ZIP without importing it.
 */
function nwmd_directory_handle_ai_state_preview() {

    if (!current_user_can('manage_options')) {
        wp_die(
            esc_html__(
                'You are not allowed to preview AI state data.',
                'local-directory-framework'
            ),
            esc_html__(
                'Access denied',
                'local-directory-framework'
            ),
            [
                'response' => 403,
            ]
        );
    }

    check_admin_referer(
        'nwmd_directory_preview_ai_state'
    );

    $file = isset($_FILES['nwmd_ai_state_preview_zip'])
        && is_array($_FILES['nwmd_ai_state_preview_zip'])
            ? $_FILES['nwmd_ai_state_preview_zip']
            : null;

    $upload = nwmd_directory_validate_ai_state_upload(
        $file
    );

    if (is_wp_error($upload)) {
        nwmd_directory_finish_ai_state_preview(
            [
                'success' => false,
                'message' => sanitize_text_field(
                    $upload->get_error_message()
                ),
            ]
        );
    }

    $validated = isset($upload['package'])
        && is_array($upload['package'])
            ? $upload['package']
            : [];

    $current =
        nwmd_directory_get_ai_state_export_records();

    if (is_wp_error($current)) {
        nwmd_directory_finish_ai_state_preview(
            [
                'success' => false,
                'message' => sanitize_text_field(
                    $current->get_error_message()
                ),
            ]
        );
    }

    $comparisons = nwmd_directory_compare_ai_state_records(
        $validated,
        $current
    );

    if (is_wp_error($comparisons)) {
        nwmd_directory_finish_ai_state_preview(
            [
                'success' => false,
                'message' => sanitize_text_field(
                    $comparisons->get_error_message()
                ),
            ]
        );
    }

    $manifest = isset($validated['manifest'])
        && is_array($validated['manifest'])
            ? $validated['manifest']
            : [];

    nwmd_directory_finish_ai_state_preview(
        [
            'success' => true,
            'summary' => [
                'filename' =>
                    (string) ($upload['filename'] ?? ''),
                'format_version' =>
                    (string) (
                        $manifest['format_version'] ?? ''
                    ),
                'plugin_version' =>
                    (string) (
                        $manifest['plugin_version'] ?? ''
                    ),
                'generated_at_utc' =>
                    (string) (
                        $manifest['generated_at_utc'] ?? ''
                    ),
                'comparisons' =>
                    $comparisons,
            ],
        ]
    );
}

add_action(
    'admin_post_nwmd_directory_preview_ai_state',
    'nwmd_directory_handle_ai_state_preview'
);
