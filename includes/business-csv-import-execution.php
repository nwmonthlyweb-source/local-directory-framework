<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render the latest CSV validation or import result.
 */
function nwmd_directory_render_business_csv_process_result() {

    $result = nwmd_directory_take_business_csv_validation_result();

    if (empty($result)) {
        return;
    }

    $mode = isset($result['mode'])
        ? sanitize_key($result['mode'])
        : 'validation';
    $phase = isset($result['phase'])
        ? sanitize_key($result['phase'])
        : 'validation';
    $valid = !empty($result['valid']);
    $row_count = isset($result['row_count'])
        ? absint($result['row_count'])
        : 0;
    $imported_count = isset($result['imported_count'])
        ? absint($result['imported_count'])
        : 0;
    $errors = isset($result['errors']) && is_array($result['errors'])
        ? $result['errors']
        : [];
    $error_count = isset($result['error_count'])
        ? absint($result['error_count'])
        : count($errors);
    $rolled_back = !empty($result['rolled_back']);

    if ($valid && 'import' === $mode) {
        ?>
        <div class="notice notice-success inline">
            <p>
                <?php
                echo esc_html(
                    sprintf(
                        /* translators: %d: Number of imported business drafts. */
                        _n(
                            'CSV import completed. %d business draft was created.',
                            'CSV import completed. %d business drafts were created.',
                            $imported_count,
                            'local-directory-framework'
                        ),
                        $imported_count
                    )
                );
                ?>
            </p>
        </div>
        <?php

        return;
    }

    if ($valid) {
        ?>
        <div class="notice notice-success inline">
            <p>
                <?php
                echo esc_html(
                    sprintf(
                        /* translators: %d: Number of validated business rows. */
                        _n(
                            'CSV validation passed. %d business row is ready to import. No records were changed.',
                            'CSV validation passed. %d business rows are ready to import. No records were changed.',
                            $row_count,
                            'local-directory-framework'
                        ),
                        $row_count
                    )
                );
                ?>
            </p>
        </div>
        <?php

        return;
    }

    if ('import' === $mode && 'creation' === $phase) {
        $summary = $rolled_back
            ? __(
                'CSV import failed. Records created by this import were rolled back.',
                'local-directory-framework'
            )
            : __(
                'CSV import failed and rollback was incomplete. Review the Businesses list before retrying.',
                'local-directory-framework'
            );
    } elseif ('import' === $mode && 'preparation' === $phase) {
        $summary = __(
            'CSV import could not start. No records were changed.',
            'local-directory-framework'
        );
    } elseif ('import' === $mode) {
        $summary = sprintf(
            /* translators: %d: Total validation error count. */
            _n(
                'CSV import was not started because validation found %d error. No records were changed.',
                'CSV import was not started because validation found %d errors. No records were changed.',
                $error_count,
                'local-directory-framework'
            ),
            $error_count
        );
    } else {
        $summary = sprintf(
            /* translators: %d: Total validation error count. */
            _n(
                'CSV validation found %d error. No records were changed.',
                'CSV validation found %d errors. No records were changed.',
                $error_count,
                'local-directory-framework'
            ),
            $error_count
        );
    }

    ?>
    <div class="notice notice-error inline">
        <p>
            <strong><?php echo esc_html($summary); ?></strong>
        </p>

        <?php if (!empty($errors)) : ?>
            <ul style="list-style: disc; padding-left: 22px;">
                <?php foreach ($errors as $error) : ?>
                    <li><?php echo esc_html($error); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($error_count > count($errors)) : ?>
            <p>
                <?php
                echo esc_html(
                    sprintf(
                        /* translators: %d: Number of additional errors. */
                        __('%d additional errors were not displayed.', 'local-directory-framework'),
                        $error_count - count($errors)
                    )
                );
                ?>
            </p>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Store one process result and redirect to the CSV Import page.
 *
 * @param array $result Process result.
 */
function nwmd_directory_finish_business_csv_process($result) {

    nwmd_directory_store_business_csv_validation_result(
        is_array($result) ? $result : []
    );

    nwmd_directory_redirect_business_csv_import();
}

/**
 * Read mapped rows from an upload that already passed validation.
 *
 * @param array $file Uploaded file information.
 *
 * @return array|WP_Error
 */
function nwmd_directory_read_validated_business_csv_rows($file) {

    $tmp_name = isset($file['tmp_name'])
        ? (string) $file['tmp_name']
        : '';

    if (
        '' === $tmp_name ||
        !is_uploaded_file($tmp_name) ||
        !is_readable($tmp_name)
    ) {
        return new WP_Error(
            'nwmd_csv_unreadable',
            __(
                'The validated CSV could not be read for import.',
                'local-directory-framework'
            )
        );
    }

    $handle = fopen($tmp_name, 'rb');

    if (false === $handle) {
        return new WP_Error(
            'nwmd_csv_open_failed',
            __(
                'The validated CSV could not be opened for import.',
                'local-directory-framework'
            )
        );
    }

    $first_bytes = fread($handle, 3);

    if ("\xEF\xBB\xBF" !== $first_bytes) {
        rewind($handle);
    }

    $headers = fgetcsv($handle, 0, ',', '"', '\\');

    if (!is_array($headers) || empty($headers)) {
        fclose($handle);

        return new WP_Error(
            'nwmd_csv_header_missing',
            __(
                'The validated CSV header could not be read for import.',
                'local-directory-framework'
            )
        );
    }

    $headers = array_map(
        static function ($header) {
            return trim((string) $header);
        },
        $headers
    );

    $headers[0] = preg_replace(
        '/^\xEF\xBB\xBF/',
        '',
        $headers[0]
    );

    $rows = [];
    $line_number = 1;

    while (($values = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
        $line_number++;

        $non_empty_values = array_filter(
            $values,
            static function ($value) {
                return '' !== trim((string) $value);
            }
        );

        if (empty($non_empty_values)) {
            continue;
        }

        if (count($values) !== count($headers)) {
            fclose($handle);

            return new WP_Error(
                'nwmd_csv_row_changed',
                sprintf(
                    /* translators: %d: CSV line number. */
                    __(
                        'Line %d changed after validation and could not be imported.',
                        'local-directory-framework'
                    ),
                    $line_number
                )
            );
        }

        $row = array_combine($headers, $values);

        if (!is_array($row)) {
            fclose($handle);

            return new WP_Error(
                'nwmd_csv_row_map_failed',
                sprintf(
                    /* translators: %d: CSV line number. */
                    __(
                        'Line %d could not be mapped for import.',
                        'local-directory-framework'
                    ),
                    $line_number
                )
            );
        }

        $rows[] = [
            'line_number' => $line_number,
            'data'        => $row,
        ];
    }

    if (!feof($handle)) {
        fclose($handle);

        return new WP_Error(
            'nwmd_csv_read_failed',
            __(
                'The validated CSV could not be read completely for import.',
                'local-directory-framework'
            )
        );
    }

    fclose($handle);

    return $rows;
}

/**
 * Convert one validated ranking eligibility value to 1 or 0.
 *
 * @param mixed $value CSV value.
 *
 * @return int
 */
function nwmd_directory_get_business_csv_ranking_eligible($value) {

    return in_array(
        strtolower(trim((string) $value)),
        [
            '1',
            'yes',
            'true',
        ],
        true
    )
        ? 1
        : 0;
}

/**
 * Return one existing taxonomy term for an import row.
 *
 * @param mixed  $slug     Validated term slug.
 * @param string $taxonomy Taxonomy name.
 *
 * @return WP_Term|WP_Error
 */
function nwmd_directory_get_business_csv_import_term(
    $slug,
    $taxonomy
) {

    $term = get_term_by(
        'slug',
        trim((string) $slug),
        $taxonomy
    );

    if (!$term instanceof WP_Term) {
        return new WP_Error(
            'nwmd_csv_term_missing',
            __(
                'A required directory term no longer exists.',
                'local-directory-framework'
            )
        );
    }

    return $term;
}

/**
 * Insert one optional research source for an imported business.
 *
 * @param int   $post_id Business post ID.
 * @param array $row     Validated CSV row.
 *
 * @return true|WP_Error
 */
function nwmd_directory_insert_business_csv_source(
    $post_id,
    $row
) {

    if (!nwmd_directory_business_csv_row_has_source($row)) {
        return true;
    }

    $source_type = sanitize_key(
        trim((string) ($row['source_type'] ?? ''))
    );
    $source_name = nwmd_directory_limit_business_source_text(
        sanitize_text_field(
            (string) ($row['source_name'] ?? '')
        ),
        255
    );
    $source_url = esc_url_raw(
        trim((string) ($row['source_url'] ?? '')),
        [
            'http',
            'https',
        ]
    );
    $source_identifier = nwmd_directory_limit_business_source_text(
        sanitize_text_field(
            (string) ($row['source_identifier'] ?? '')
        ),
        255
    );
    $source_notes = sanitize_textarea_field(
        (string) ($row['source_notes'] ?? '')
    );
    $retrieved_at = nwmd_directory_sanitize_business_source_date(
        $row['source_retrieved_at'] ?? ''
    );
    $verified_at = nwmd_directory_sanitize_business_source_date(
        $row['source_verified_at'] ?? ''
    );
    $verification_result = sanitize_key(
        trim(
            (string) (
                $row['source_verification_result']
                    ?? ''
            )
        )
    );

    if ('' === $verification_result) {
        $verification_result = 'pending';
    }

    global $wpdb;

    $sources_table = $wpdb->prefix
        . 'nwmd_business_sources';
    $current_time = current_time('mysql');

    $inserted = $wpdb->insert(
        $sources_table,
        [
            'business_post_id'    => absint($post_id),
            'source_type'         => $source_type,
            'source_name'         => $source_name,
            'source_url'          => $source_url,
            'source_identifier'   => $source_identifier,
            'source_notes'        => $source_notes,
            'retrieved_at'        => $retrieved_at,
            'verified_at'         => '' !== $verified_at
                ? $verified_at
                : null,
            'verification_result' => $verification_result,
            'created_at'          => $current_time,
            'updated_at'          => $current_time,
        ],
        [
            '%d',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
        ]
    );

    if (false === $inserted) {
        return new WP_Error(
            'nwmd_csv_source_insert_failed',
            __(
                'The research source could not be saved.',
                'local-directory-framework'
            )
        );
    }

    return true;
}

/**
 * Import one validated Business CSV row.
 *
 * @param array $item             Row data and original line number.
 * @param array $created_post_ids Post IDs created during this import.
 *
 * @return int|WP_Error
 */
function nwmd_directory_import_business_csv_row(
    $item,
    &$created_post_ids
) {

    $row = isset($item['data']) && is_array($item['data'])
        ? $item['data']
        : [];

    $business_name = sanitize_text_field(
        (string) ($row['business_name'] ?? '')
    );
    $business_slug = sanitize_title(
        (string) ($row['business_slug'] ?? '')
    );

    if (
        '' === $business_name ||
        '' === $business_slug
    ) {
        return new WP_Error(
            'nwmd_csv_business_invalid',
            __(
                'The business name or slug became invalid during import.',
                'local-directory-framework'
            )
        );
    }

    $existing_business = get_page_by_path(
        $business_slug,
        OBJECT,
        'nwmd_business'
    );

    if ($existing_business instanceof WP_Post) {
        return new WP_Error(
            'nwmd_csv_business_exists',
            __(
                'The business slug now exists in WordPress.',
                'local-directory-framework'
            )
        );
    }

    $registration_number = sanitize_text_field(
        (string) ($row['registration_number'] ?? '')
    );
    $license_number = sanitize_text_field(
        (string) ($row['license_number'] ?? '')
    );

    if (
        nwmd_directory_business_csv_meta_value_exists(
            'nwmd_registration_number',
            $registration_number
        ) ||
        nwmd_directory_business_csv_meta_value_exists(
            'nwmd_license_number',
            $license_number
        )
    ) {
        return new WP_Error(
            'nwmd_csv_unique_value_exists',
            __(
                'A registration or license number now exists in WordPress.',
                'local-directory-framework'
            )
        );
    }

    $post_id = wp_insert_post(
        [
            'post_type'    => 'nwmd_business',
            'post_status'  => 'draft',
            'post_title'   => $business_name,
            'post_name'    => $business_slug,
            'post_content' => wp_kses_post(
                (string) ($row['description'] ?? '')
            ),
            'post_excerpt' => sanitize_textarea_field(
                (string) ($row['excerpt'] ?? '')
            ),
            'post_author'  => get_current_user_id(),
        ],
        true
    );

    if (is_wp_error($post_id) || absint($post_id) < 1) {
        return new WP_Error(
            'nwmd_csv_post_insert_failed',
            __(
                'The business draft could not be created.',
                'local-directory-framework'
            )
        );
    }

    $post_id = absint($post_id);
    $created_post_ids[] = $post_id;

    $taxonomy_fields = [
        'state_slug' => 'nwmd_state',
        'city_slug' => 'nwmd_city',
        'category_slug' => 'nwmd_category',
        'specialty_slug' => 'nwmd_specialty',
    ];

    foreach ($taxonomy_fields as $field => $taxonomy) {
        $slug = trim((string) ($row[$field] ?? ''));

        if ('' === $slug && 'specialty_slug' === $field) {
            continue;
        }

        $term = nwmd_directory_get_business_csv_import_term(
            $slug,
            $taxonomy
        );

        if (is_wp_error($term)) {
            return $term;
        }

        $assigned = wp_set_object_terms(
            $post_id,
            [
                absint($term->term_id),
            ],
            $taxonomy,
            false
        );

        if (is_wp_error($assigned)) {
            return new WP_Error(
                'nwmd_csv_term_assignment_failed',
                __(
                    'A directory term could not be assigned.',
                    'local-directory-framework'
                )
            );
        }
    }

    $text_fields = [
        'public_name',
        'legal_name',
        'public_phone',
        'street_address',
        'postal_code',
        'registration_number',
        'license_number',
    ];

    foreach ($text_fields as $field) {
        nwmd_directory_save_business_record_meta(
            $post_id,
            $field,
            sanitize_text_field(
                (string) ($row[$field] ?? '')
            )
        );
    }

    nwmd_directory_save_business_record_meta(
        $post_id,
        'website_url',
        esc_url_raw(
            trim((string) ($row['website_url'] ?? '')),
            [
                'http',
                'https',
            ]
        )
    );

    nwmd_directory_save_business_record_meta(
        $post_id,
        'public_email',
        sanitize_email(
            (string) ($row['public_email'] ?? '')
        )
    );

    nwmd_directory_save_business_record_meta(
        $post_id,
        'latitude',
        nwmd_directory_sanitize_coordinate(
            $row['latitude'] ?? '',
            -90,
            90
        )
    );

    nwmd_directory_save_business_record_meta(
        $post_id,
        'longitude',
        nwmd_directory_sanitize_coordinate(
            $row['longitude'] ?? '',
            -180,
            180
        )
    );

    $status_defaults = [
        'license_status'      => 'unknown',
        'verification_status' => 'unverified',
        'claimed_status'      => 'unclaimed',
    ];

    foreach ($status_defaults as $field => $default_value) {
        $value = sanitize_key(
            trim((string) ($row[$field] ?? ''))
        );

        if ('' === $value) {
            $value = $default_value;
        }

        nwmd_directory_save_business_record_meta(
            $post_id,
            $field,
            $value
        );
    }

    update_post_meta(
        $post_id,
        'nwmd_ranking_eligible',
        nwmd_directory_get_business_csv_ranking_eligible(
            $row['ranking_eligible'] ?? ''
        )
    );

    nwmd_directory_save_business_record_meta(
        $post_id,
        'last_verified_at',
        nwmd_directory_sanitize_verified_date(
            $row['last_verified_at'] ?? ''
        )
    );

    if (!nwmd_directory_sync_business_index($post_id)) {
        return new WP_Error(
            'nwmd_csv_index_sync_failed',
            __(
                'The business index could not be synchronized.',
                'local-directory-framework'
            )
        );
    }

    $source_result = nwmd_directory_insert_business_csv_source(
        $post_id,
        $row
    );

    if (is_wp_error($source_result)) {
        return $source_result;
    }

    return $post_id;
}

/**
 * Attempt to remove all records created by one failed import.
 *
 * @param array $post_ids Created Business post IDs.
 *
 * @return bool
 */
function nwmd_directory_rollback_business_csv_import($post_ids) {

    global $wpdb;

    $sources_table = $wpdb->prefix
        . 'nwmd_business_sources';
    $index_table = $wpdb->prefix
        . 'nwmd_business_index';
    $complete = true;

    foreach (array_reverse($post_ids) as $post_id) {
        $post_id = absint($post_id);

        if ($post_id < 1) {
            continue;
        }

        $deleted_sources = $wpdb->delete(
            $sources_table,
            [
                'business_post_id' => $post_id,
            ],
            [
                '%d',
            ]
        );

        if (false === $deleted_sources) {
            $complete = false;
        }

        $deleted_index = $wpdb->delete(
            $index_table,
            [
                'business_post_id' => $post_id,
            ],
            [
                '%d',
            ]
        );

        if (false === $deleted_index) {
            $complete = false;
        }

        if (
            get_post($post_id) instanceof WP_Post &&
            !wp_delete_post($post_id, true)
        ) {
            $complete = false;
        }
    }

    return $complete;
}

/**
 * Import all validated rows or roll back the complete batch.
 *
 * @param array $rows Validated mapped CSV rows.
 *
 * @return array
 */
function nwmd_directory_import_business_csv_rows($rows) {

    $created_post_ids = [];

    foreach ($rows as $item) {
        $line_number = isset($item['line_number'])
            ? absint($item['line_number'])
            : 0;

        $imported = nwmd_directory_import_business_csv_row(
            $item,
            $created_post_ids
        );

        if (is_wp_error($imported)) {
            $rolled_back = nwmd_directory_rollback_business_csv_import(
                $created_post_ids
            );

            return [
                'success'        => false,
                'imported_count' => 0,
                'rolled_back'    => $rolled_back,
                'errors'         => [
                    sprintf(
                        /* translators: 1: CSV line number, 2: safe import error. */
                        __(
                            'Line %1$d: %2$s',
                            'local-directory-framework'
                        ),
                        $line_number,
                        $imported->get_error_message()
                    ),
                ],
                'error_count'    => 1,
            ];
        }
    }

    return [
        'success'        => true,
        'imported_count' => count($created_post_ids),
        'rolled_back'    => false,
        'errors'         => [],
        'error_count'    => 0,
    ];
}

/**
 * Validate or import one uploaded Business CSV.
 */
function nwmd_directory_handle_business_csv_process() {

    if (!current_user_can('manage_options')) {
        wp_die(
            esc_html__(
                'You are not allowed to process business CSV files.',
                'local-directory-framework'
            )
        );
    }

    check_admin_referer(
        'nwmd_import_business_csv',
        'nwmd_business_csv_nonce'
    );

    $operation = isset($_POST['nwmd_business_csv_operation'])
        ? sanitize_key(
            wp_unslash(
                $_POST['nwmd_business_csv_operation']
            )
        )
        : 'validate';

    if (
        !in_array(
            $operation,
            [
                'validate',
                'import',
            ],
            true
        )
    ) {
        $operation = 'validate';
    }

    $file = isset($_FILES['business_csv_file'])
        ? $_FILES['business_csv_file']
        : [];

    $validation = nwmd_directory_validate_business_csv_upload(
        $file
    );
    $error_count = isset($validation['error_count'])
        ? absint($validation['error_count'])
        : 0;
    $row_count = isset($validation['row_count'])
        ? absint($validation['row_count'])
        : 0;
    $errors = isset($validation['errors'])
        && is_array($validation['errors'])
        ? $validation['errors']
        : [];

    if ($error_count > 0) {
        nwmd_directory_finish_business_csv_process(
            [
                'mode'           => 'import' === $operation
                    ? 'import'
                    : 'validation',
                'phase'          => 'validation',
                'valid'          => false,
                'row_count'      => $row_count,
                'imported_count' => 0,
                'rolled_back'    => true,
                'errors'         => $errors,
                'error_count'    => $error_count,
            ]
        );
    }

    if ('validate' === $operation) {
        nwmd_directory_finish_business_csv_process(
            [
                'mode'           => 'validation',
                'phase'          => 'validation',
                'valid'          => true,
                'row_count'      => $row_count,
                'imported_count' => 0,
                'rolled_back'    => false,
                'errors'         => [],
                'error_count'    => 0,
            ]
        );
    }

    $rows = nwmd_directory_read_validated_business_csv_rows(
        $file
    );

    if (is_wp_error($rows)) {
        nwmd_directory_finish_business_csv_process(
            [
                'mode'           => 'import',
                'phase'          => 'preparation',
                'valid'          => false,
                'row_count'      => $row_count,
                'imported_count' => 0,
                'rolled_back'    => true,
                'errors'         => [
                    $rows->get_error_message(),
                ],
                'error_count'    => 1,
            ]
        );
    }

    if (count($rows) !== $row_count) {
        nwmd_directory_finish_business_csv_process(
            [
                'mode'           => 'import',
                'phase'          => 'preparation',
                'valid'          => false,
                'row_count'      => $row_count,
                'imported_count' => 0,
                'rolled_back'    => true,
                'errors'         => [
                    __(
                        'The CSV row count changed after validation.',
                        'local-directory-framework'
                    ),
                ],
                'error_count'    => 1,
            ]
        );
    }

    $import = nwmd_directory_import_business_csv_rows($rows);

    nwmd_directory_finish_business_csv_process(
        [
            'mode'           => 'import',
            'phase'          => 'creation',
            'valid'          => !empty($import['success']),
            'row_count'      => $row_count,
            'imported_count' => isset($import['imported_count'])
                ? absint($import['imported_count'])
                : 0,
            'rolled_back'    => !empty($import['rolled_back']),
            'errors'         => isset($import['errors'])
                && is_array($import['errors'])
                ? $import['errors']
                : [],
            'error_count'    => isset($import['error_count'])
                ? absint($import['error_count'])
                : 0,
        ]
    );
}

add_action(
    'admin_post_nwmd_process_business_csv',
    'nwmd_directory_handle_business_csv_process'
);
