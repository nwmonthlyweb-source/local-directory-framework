<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Read mapped rows from a validated Deal CSV upload.
 *
 * @param array $file Uploaded file information.
 *
 * @return array|WP_Error
 */
function nwmd_directory_read_business_deal_csv_rows(
    $file
) {

    $tmp_name = isset($file['tmp_name'])
        ? (string) $file['tmp_name']
        : '';

    if (
        '' === $tmp_name ||
        !is_uploaded_file($tmp_name) ||
        !is_readable($tmp_name)
    ) {
        return new WP_Error(
            'nwmd_deal_csv_unreadable',
            __(
                'The validated Deal CSV could not be read for import.',
                'local-directory-framework'
            )
        );
    }

    $handle = fopen(
        $tmp_name,
        'rb'
    );

    if (false === $handle) {
        return new WP_Error(
            'nwmd_deal_csv_open_failed',
            __(
                'The validated Deal CSV could not be opened.',
                'local-directory-framework'
            )
        );
    }

    $first_bytes = fread(
        $handle,
        3
    );

    if ("\xEF\xBB\xBF" !== $first_bytes) {
        rewind($handle);
    }

    $headers = fgetcsv(
        $handle,
        0,
        ',',
        '"',
        '\\'
    );

    if (
        !is_array($headers) ||
        empty($headers)
    ) {
        fclose($handle);

        return new WP_Error(
            'nwmd_deal_csv_header_missing',
            __(
                'The validated Deal CSV header could not be read.',
                'local-directory-framework'
            )
        );
    }

    $headers = array_map(
        static function ($header) {
            return trim(
                (string) $header
            );
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

    while (
        (
            $values = fgetcsv(
                $handle,
                0,
                ',',
                '"',
                '\\'
            )
        ) !== false
    ) {
        $line_number++;

        $non_empty_values = array_filter(
            $values,
            static function ($value) {
                return '' !== trim(
                    (string) $value
                );
            }
        );

        if (empty($non_empty_values)) {
            continue;
        }

        if (count($values) !== count($headers)) {
            fclose($handle);

            return new WP_Error(
                'nwmd_deal_csv_row_changed',
                sprintf(
                    /* translators: %d: CSV line number. */
                    __(
                        'Line %d changed after validation.',
                        'local-directory-framework'
                    ),
                    $line_number
                )
            );
        }

        $row = array_combine(
            $headers,
            $values
        );

        if (!is_array($row)) {
            fclose($handle);

            return new WP_Error(
                'nwmd_deal_csv_row_map_failed',
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
            'nwmd_deal_csv_read_failed',
            __(
                'The validated Deal CSV could not be read completely.',
                'local-directory-framework'
            )
        );
    }

    fclose($handle);

    return $rows;
}

/**
 * Normalize one previously validated Deal CSV row.
 *
 * @param array $row CSV row.
 *
 * @return array|WP_Error
 */
function nwmd_directory_normalize_business_deal_csv_row(
    $row
) {

    $business_slug = sanitize_title(
        (string) ($row['business_slug'] ?? '')
    );

    $business =
        nwmd_directory_get_business_deal_csv_business(
            $business_slug
        );

    if (!$business instanceof WP_Post) {
        return new WP_Error(
            'nwmd_deal_csv_business_missing',
            __(
                'The Business could not be found during import.',
                'local-directory-framework'
            )
        );
    }

    $deal_slug = sanitize_title(
        (string) ($row['deal_slug'] ?? '')
    );

    $title = sanitize_text_field(
        (string) ($row['title'] ?? '')
    );

    $card_text = sanitize_text_field(
        (string) ($row['card_text'] ?? '')
    );

    if ('' === $card_text) {
        $card_text = $title;
    }

    $description = sanitize_textarea_field(
        (string) ($row['description'] ?? '')
    );

    $promo_code = sanitize_text_field(
        (string) ($row['promo_code'] ?? '')
    );

    $source_url = esc_url_raw(
        (string) ($row['source_url'] ?? '')
    );

    $starts_at =
        nwmd_directory_parse_business_deal_admin_date(
            $row['starts_at'] ?? ''
        );

    $expires_at =
        nwmd_directory_parse_business_deal_admin_date(
            $row['expires_at'] ?? '',
            true
        );

    $verified_at =
        nwmd_directory_parse_business_deal_admin_date(
            $row['verified_at'] ?? ''
        );

    $status = sanitize_key(
        (string) ($row['status'] ?? '')
    );

    $is_featured = (
        '1' === trim(
            (string) ($row['is_featured'] ?? '')
        )
    )
        ? 1
        : 0;

    if ('archived' === $status) {
        $is_featured = 0;
    }

    return [
        'business_post_id' => $business->ID,
        'business_slug'    => $business_slug,
        'deal_slug'        => $deal_slug,
        'title'            => $title,
        'card_text'        => $card_text,
        'description'      => $description,
        'promo_code'       => $promo_code,
        'source_url'       => $source_url,
        'starts_at'        => $starts_at,
        'expires_at'       => $expires_at,
        'verified_at'      => $verified_at,
        'status'           => $status,
        'is_featured'      => $is_featured,
    ];
}

/**
 * Return snapshots of all Deals belonging to affected Businesses.
 *
 * Featured updates can alter another Deal for the same Business,
 * so all Deal rows for affected Businesses are preserved.
 *
 * @param array $business_post_ids Business post IDs.
 *
 * @return array
 */
function nwmd_directory_get_business_deal_csv_snapshots(
    $business_post_ids
) {

    global $wpdb;

    $business_post_ids = array_values(
        array_unique(
            array_filter(
                array_map(
                    'absint',
                    (array) $business_post_ids
                )
            )
        )
    );

    if (empty($business_post_ids)) {
        return [];
    }

    $placeholders = implode(
        ', ',
        array_fill(
            0,
            count($business_post_ids),
            '%d'
        )
    );

    $table =
        nwmd_directory_get_business_deals_table_name();

    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT *
            FROM {$table}
            WHERE business_post_id IN ({$placeholders})",
            ...$business_post_ids
        ),
        ARRAY_A
    );
}

/**
 * Restore Deal records after a failed CSV import.
 *
 * @param array $snapshots  Original Deal rows.
 * @param array $created_ids Deal IDs created during this import.
 *
 * @return bool
 */
function nwmd_directory_restore_business_deal_csv_import(
    $snapshots,
    $created_ids
) {

    global $wpdb;

    $table =
        nwmd_directory_get_business_deals_table_name();

    $success = true;

    $created_ids = array_values(
        array_unique(
            array_filter(
                array_map(
                    'absint',
                    (array) $created_ids
                )
            )
        )
    );

    if (!empty($created_ids)) {
        $placeholders = implode(
            ', ',
            array_fill(
                0,
                count($created_ids),
                '%d'
            )
        );

        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table}
                WHERE id IN ({$placeholders})",
                ...$created_ids
            )
        );

        if (false === $deleted) {
            $success = false;
        }
    }

    foreach ((array) $snapshots as $snapshot) {
        if (!is_array($snapshot)) {
            $success = false;
            continue;
        }

        $restored = $wpdb->replace(
            $table,
            $snapshot
        );

        if (false === $restored) {
            $success = false;
        }
    }

    return $success;
}

/**
 * Import or update validated Deal CSV rows.
 *
 * @param array $rows Validated mapped rows.
 *
 * @return array
 */
function nwmd_directory_import_business_deal_csv_rows(
    $rows
) {

    global $wpdb;

    $normalized_rows = [];
    $business_post_ids = [];

    foreach ($rows as $item) {
        $line_number = isset($item['line_number'])
            ? absint($item['line_number'])
            : 0;

        $normalized =
            nwmd_directory_normalize_business_deal_csv_row(
                $item['data'] ?? []
            );

        if (is_wp_error($normalized)) {
            return [
                'success'       => false,
                'created_count' => 0,
                'updated_count' => 0,
                'rolled_back'   => true,
                'errors'        => [
                    sprintf(
                        /* translators: 1: CSV line, 2: safe error. */
                        __(
                            'Line %1$d: %2$s',
                            'local-directory-framework'
                        ),
                        $line_number,
                        $normalized->get_error_message()
                    ),
                ],
                'error_count'   => 1,
            ];
        }

        $normalized_rows[] = [
            'line_number' => $line_number,
            'data'        => $normalized,
        ];

        $business_post_ids[] =
            absint($normalized['business_post_id']);
    }

    $snapshots =
        nwmd_directory_get_business_deal_csv_snapshots(
            $business_post_ids
        );

    $created_ids = [];
    $created_count = 0;
    $updated_count = 0;

    $table =
        nwmd_directory_get_business_deals_table_name();

    foreach ($normalized_rows as $item) {
        $line_number = $item['line_number'];
        $row = $item['data'];

        $existing =
            nwmd_directory_get_business_deal_by_identity(
                $row['business_post_id'],
                $row['deal_slug']
            );

        $now = current_time('mysql');
        $user_id = get_current_user_id();

        $data = [
            'business_post_id' => $row['business_post_id'],
            'deal_slug'        => $row['deal_slug'],
            'title'            => $row['title'],
            'card_text'        => $row['card_text'],
            'description'      => $row['description'],
            'promo_code'       => $row['promo_code'],
            'source_url'       => $row['source_url'],
            'starts_at'        => $row['starts_at'],
            'expires_at'       => $row['expires_at'],
            'verified_at'      => $row['verified_at'],
            'status'           => $row['status'],
            'is_featured'      => $row['is_featured'],
            'updated_by'       => $user_id,
            'updated_at'       => $now,
            'archived_at'      => (
                'archived' === $row['status']
                    ? $now
                    : null
            ),
        ];

        $formats = [
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
            '%d',
            '%d',
            '%s',
            '%s',
        ];

        if (is_object($existing)) {
            $saved = $wpdb->update(
                $table,
                $data,
                [
                    'id' => absint($existing->id),
                ],
                $formats,
                [
                    '%d',
                ]
            );

            $saved_id = absint(
                $existing->id
            );

            $updated_count++;
        } else {
            $data['created_by'] = $user_id;
            $data['created_at'] = $now;

            $formats[] = '%d';
            $formats[] = '%s';

            $saved = $wpdb->insert(
                $table,
                $data,
                $formats
            );

            $saved_id = absint(
                $wpdb->insert_id
            );

            if ($saved_id > 0) {
                $created_ids[] = $saved_id;
            }

            $created_count++;
        }

        if (
            false === $saved ||
            0 === $saved_id
        ) {
            $rolled_back =
                nwmd_directory_restore_business_deal_csv_import(
                    $snapshots,
                    $created_ids
                );

            return [
                'success'       => false,
                'created_count' => 0,
                'updated_count' => 0,
                'rolled_back'   => $rolled_back,
                'errors'        => [
                    sprintf(
                        /* translators: %d: CSV line number. */
                        __(
                            'Line %d could not be saved.',
                            'local-directory-framework'
                        ),
                        $line_number
                    ),
                ],
                'error_count'   => 1,
            ];
        }

        if (1 === $row['is_featured']) {
            $unfeatured = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table}
                    SET
                        is_featured = 0,
                        updated_by = %d,
                        updated_at = %s
                    WHERE business_post_id = %d
                    AND id <> %d",
                    $user_id,
                    $now,
                    $row['business_post_id'],
                    $saved_id
                )
            );

            if (false === $unfeatured) {
                $rolled_back =
                    nwmd_directory_restore_business_deal_csv_import(
                        $snapshots,
                        $created_ids
                    );

                return [
                    'success'       => false,
                    'created_count' => 0,
                    'updated_count' => 0,
                    'rolled_back'   => $rolled_back,
                    'errors'        => [
                        sprintf(
                            /* translators: %d: CSV line number. */
                            __(
                                'Line %d could not apply featured status.',
                                'local-directory-framework'
                            ),
                            $line_number
                        ),
                    ],
                    'error_count'   => 1,
                ];
            }
        }
    }

    return [
        'success'       => true,
        'created_count' => $created_count,
        'updated_count' => $updated_count,
        'rolled_back'   => false,
        'errors'        => [],
        'error_count'   => 0,
    ];
}

/**
 * Validate or import one uploaded Business Deal CSV.
 */
function nwmd_directory_handle_business_deal_csv_process() {

    if (!current_user_can('manage_options')) {
        wp_die(
            esc_html__(
                'You are not allowed to process Deal CSV files.',
                'local-directory-framework'
            )
        );
    }

    check_admin_referer(
        'nwmd_import_business_deal_csv',
        'nwmd_business_deal_csv_nonce'
    );

    $operation = isset(
        $_POST['nwmd_business_deal_csv_operation']
    )
        ? sanitize_key(
            wp_unslash(
                $_POST['nwmd_business_deal_csv_operation']
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

    $file = isset($_FILES['business_deal_csv_file'])
        ? $_FILES['business_deal_csv_file']
        : [];

    $validation =
        nwmd_directory_validate_business_deal_csv_upload(
            $file
        );

    $error_count = isset($validation['error_count'])
        ? absint($validation['error_count'])
        : 0;

    $row_count = isset($validation['row_count'])
        ? absint($validation['row_count'])
        : 0;

    $create_count = isset($validation['create_count'])
        ? absint($validation['create_count'])
        : 0;

    $update_count = isset($validation['update_count'])
        ? absint($validation['update_count'])
        : 0;

    $errors = isset($validation['errors'])
        && is_array($validation['errors'])
        ? $validation['errors']
        : [];

    if ($error_count > 0) {
        nwmd_directory_finish_business_deal_csv_process(
            [
                'mode'           => $operation,
                'valid'          => false,
                'row_count'      => $row_count,
                'created_count'  => 0,
                'updated_count'  => 0,
                'planned_create' => $create_count,
                'planned_update' => $update_count,
                'rolled_back'    => true,
                'errors'         => $errors,
                'error_count'    => $error_count,
            ]
        );
    }

    if ('validate' === $operation) {
        nwmd_directory_finish_business_deal_csv_process(
            [
                'mode'           => 'validation',
                'valid'          => true,
                'row_count'      => $row_count,
                'created_count'  => 0,
                'updated_count'  => 0,
                'planned_create' => $create_count,
                'planned_update' => $update_count,
                'rolled_back'    => false,
                'errors'         => [],
                'error_count'    => 0,
            ]
        );
    }

    $rows =
        nwmd_directory_read_business_deal_csv_rows(
            $file
        );

    if (is_wp_error($rows)) {
        nwmd_directory_finish_business_deal_csv_process(
            [
                'mode'           => 'import',
                'valid'          => false,
                'row_count'      => $row_count,
                'created_count'  => 0,
                'updated_count'  => 0,
                'planned_create' => $create_count,
                'planned_update' => $update_count,
                'rolled_back'    => true,
                'errors'         => [
                    $rows->get_error_message(),
                ],
                'error_count'    => 1,
            ]
        );
    }

    if (count($rows) !== $row_count) {
        nwmd_directory_finish_business_deal_csv_process(
            [
                'mode'           => 'import',
                'valid'          => false,
                'row_count'      => $row_count,
                'created_count'  => 0,
                'updated_count'  => 0,
                'planned_create' => $create_count,
                'planned_update' => $update_count,
                'rolled_back'    => true,
                'errors'         => [
                    __(
                        'The Deal CSV row count changed after validation.',
                        'local-directory-framework'
                    ),
                ],
                'error_count'    => 1,
            ]
        );
    }

    $import =
        nwmd_directory_import_business_deal_csv_rows(
            $rows
        );

    nwmd_directory_finish_business_deal_csv_process(
        [
            'mode'           => 'import',
            'valid'          => !empty($import['success']),
            'row_count'      => $row_count,
            'created_count'  => isset(
                $import['created_count']
            )
                ? absint($import['created_count'])
                : 0,
            'updated_count'  => isset(
                $import['updated_count']
            )
                ? absint($import['updated_count'])
                : 0,
            'planned_create' => $create_count,
            'planned_update' => $update_count,
            'rolled_back'    => !empty(
                $import['rolled_back']
            ),
            'errors'         => isset($import['errors'])
                && is_array($import['errors'])
                ? $import['errors']
                : [],
            'error_count'    => isset(
                $import['error_count']
            )
                ? absint($import['error_count'])
                : 0,
        ]
    );
}

add_action(
    'admin_post_nwmd_process_business_deal_csv',
    'nwmd_directory_handle_business_deal_csv_process'
);
