<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return the current administrator's one-time validation result key.
 *
 * @return string
 */
function nwmd_directory_get_business_csv_validation_transient_key() {

    return 'nwmd_business_csv_validation_' . get_current_user_id();
}

/**
 * Store one CSV validation result for the current administrator.
 *
 * @param array $result Validation result.
 */
function nwmd_directory_store_business_csv_validation_result($result) {

    set_transient(
        nwmd_directory_get_business_csv_validation_transient_key(),
        $result,
        10 * MINUTE_IN_SECONDS
    );
}

/**
 * Return and remove the current administrator's validation result.
 *
 * @return array
 */
function nwmd_directory_take_business_csv_validation_result() {

    $key = nwmd_directory_get_business_csv_validation_transient_key();
    $result = get_transient($key);

    delete_transient($key);

    return is_array($result) ? $result : [];
}

/**
 * Redirect back to the Business CSV Import page.
 */
function nwmd_directory_redirect_business_csv_import() {

    $url = add_query_arg(
        [
            'post_type' => 'nwmd_business',
            'page'      => 'nwmd-business-csv-import',
        ],
        admin_url('edit.php')
    );

    wp_safe_redirect($url);
    exit;
}

/**
 * Save a validation result and return to the importer page.
 *
 * @param bool  $valid       Whether validation passed.
 * @param int   $row_count   Number of non-empty data rows.
 * @param array $errors      Displayable validation errors.
 * @param int   $error_count Total validation error count.
 */
function nwmd_directory_finish_business_csv_validation(
    $valid,
    $row_count,
    $errors,
    $error_count
) {

    nwmd_directory_store_business_csv_validation_result(
        [
            'valid'       => (bool) $valid,
            'row_count'   => absint($row_count),
            'errors'      => array_values($errors),
            'error_count' => absint($error_count),
        ]
    );

    nwmd_directory_redirect_business_csv_import();
}

/**
 * Add a validation error while limiting transient size.
 *
 * @param array  $errors      Displayable errors.
 * @param int    $error_count Total error count.
 * @param string $message     Error message.
 */
function nwmd_directory_add_business_csv_validation_error(
    &$errors,
    &$error_count,
    $message
) {

    $error_count++;

    if (count($errors) < 200) {
        $errors[] = sanitize_text_field($message);
    }
}

/**
 * Add a line-specific CSV validation error.
 *
 * @param array  $errors      Displayable errors.
 * @param int    $error_count Total error count.
 * @param int    $line_number CSV line number.
 * @param string $message     Error message.
 */
function nwmd_directory_add_business_csv_line_error(
    &$errors,
    &$error_count,
    $line_number,
    $message
) {

    nwmd_directory_add_business_csv_validation_error(
        $errors,
        $error_count,
        sprintf(
            /* translators: 1: CSV line number, 2: validation message. */
            __('Line %1$d: %2$s', 'local-directory-framework'),
            absint($line_number),
            $message
        )
    );
}

/**
 * Render the latest CSV validation result.
 */
function nwmd_directory_render_business_csv_validation_result() {

    $result = nwmd_directory_take_business_csv_validation_result();

    if (empty($result)) {
        return;
    }

    $valid = !empty($result['valid']);
    $row_count = isset($result['row_count'])
        ? absint($result['row_count'])
        : 0;
    $errors = isset($result['errors']) && is_array($result['errors'])
        ? $result['errors']
        : [];
    $error_count = isset($result['error_count'])
        ? absint($result['error_count'])
        : count($errors);

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

    ?>
    <div class="notice notice-error inline">
        <p>
            <strong>
                <?php
                echo esc_html(
                    sprintf(
                        /* translators: %d: Total validation error count. */
                        _n(
                            'CSV validation found %d error. No records were changed.',
                            'CSV validation found %d errors. No records were changed.',
                            $error_count,
                            'local-directory-framework'
                        ),
                        $error_count
                    )
                );
                ?>
            </strong>
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
                        /* translators: %d: Number of additional validation errors. */
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
 * Return required Business CSV columns.
 *
 * @return array
 */
function nwmd_directory_get_required_business_csv_import_columns() {

    return [
        'business_name',
        'business_slug',
        'state_slug',
        'city_slug',
        'category_slug',
    ];
}

/**
 * Return controlled optional research-source columns.
 *
 * @return array
 */
function nwmd_directory_get_business_csv_source_columns() {

    return [
        'source_type',
        'source_name',
        'source_url',
        'source_identifier',
        'source_notes',
        'source_retrieved_at',
        'source_verified_at',
        'source_verification_result',
    ];
}

/**
 * Return whether a value is a strict lowercase WordPress-style slug.
 *
 * @param mixed $value Raw slug.
 *
 * @return bool
 */
function nwmd_directory_is_valid_business_csv_slug($value) {

    $value = trim((string) $value);

    return 1 === preg_match(
        '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',
        $value
    );
}

/**
 * Return whether a non-empty value is a valid public HTTP or HTTPS URL.
 *
 * @param mixed $value Raw URL.
 *
 * @return bool
 */
function nwmd_directory_is_valid_business_csv_url($value) {

    $value = trim((string) $value);

    if ('' === $value) {
        return true;
    }

    $url = esc_url_raw(
        $value,
        [
            'http',
            'https',
        ]
    );

    if ('' === $url) {
        return false;
    }

    $scheme = strtolower(
        (string) wp_parse_url($url, PHP_URL_SCHEME)
    );

    return in_array(
        $scheme,
        [
            'http',
            'https',
        ],
        true
    ) && false !== wp_http_validate_url($url);
}

/**
 * Return whether an existing Business already uses a metadata value.
 *
 * @param string $meta_key   Full metadata key.
 * @param string $meta_value Sanitized metadata value.
 *
 * @return bool
 */
function nwmd_directory_business_csv_meta_value_exists(
    $meta_key,
    $meta_value
) {

    if ('' === $meta_value) {
        return false;
    }

    $post_ids = get_posts(
        [
            'post_type'              => 'nwmd_business',
            'post_status'            => array_values(
                get_post_stati([], 'names')
            ),
            'posts_per_page'         => 1,
            'fields'                 => 'ids',
            'meta_key'               => $meta_key,
            'meta_value'             => $meta_value,
            'orderby'                => 'ID',
            'order'                  => 'ASC',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'suppress_filters'       => false,
        ]
    );

    return !empty($post_ids);
}

/**
 * Return whether a CSV row contains any research-source data.
 *
 * @param array $row CSV row keyed by header.
 *
 * @return bool
 */
function nwmd_directory_business_csv_row_has_source($row) {

    foreach (nwmd_directory_get_business_csv_source_columns() as $column) {
        if ('' !== trim((string) ($row[$column] ?? ''))) {
            return true;
        }
    }

    return false;
}

/**
 * Validate one mapped Business CSV row.
 *
 * @param array $row                    CSV row keyed by header.
 * @param int   $line_number            CSV line number.
 * @param array $seen_business_slugs     Slugs already found in this CSV.
 * @param array $seen_registration_nums  Registration numbers found in this CSV.
 * @param array $seen_license_nums       License numbers found in this CSV.
 * @param array $errors                 Displayable validation errors.
 * @param int   $error_count             Total validation error count.
 */
function nwmd_directory_validate_business_csv_row(
    $row,
    $line_number,
    &$seen_business_slugs,
    &$seen_registration_nums,
    &$seen_license_nums,
    &$errors,
    &$error_count
) {

    foreach ($row as $value) {
        $value = (string) $value;

        if (
            '' !== $value &&
            '' === wp_check_invalid_utf8($value, true)
        ) {
            nwmd_directory_add_business_csv_line_error(
                $errors,
                $error_count,
                $line_number,
                __('The row contains invalid UTF-8 text.', 'local-directory-framework')
            );

            return;
        }
    }

    $business_name = sanitize_text_field(
        (string) ($row['business_name'] ?? '')
    );

    if ('' === $business_name) {
        nwmd_directory_add_business_csv_line_error(
            $errors,
            $error_count,
            $line_number,
            __('business_name is required.', 'local-directory-framework')
        );
    }

    $slug_fields = [
        'business_slug' => 'business_slug',
        'state_slug'    => 'state_slug',
        'city_slug'     => 'city_slug',
        'category_slug' => 'category_slug',
    ];

    foreach ($slug_fields as $field => $label) {
        $value = trim((string) ($row[$field] ?? ''));

        if ('' === $value) {
            nwmd_directory_add_business_csv_line_error(
                $errors,
                $error_count,
                $line_number,
                sprintf(
                    /* translators: %s: Required CSV column name. */
                    __('%s is required.', 'local-directory-framework'),
                    $label
                )
            );
        } elseif (!nwmd_directory_is_valid_business_csv_slug($value)) {
            nwmd_directory_add_business_csv_line_error(
                $errors,
                $error_count,
                $line_number,
                sprintf(
                    /* translators: %s: CSV column name. */
                    __('%s must be a lowercase WordPress slug.', 'local-directory-framework'),
                    $label
                )
            );
        }
    }

    $specialty_slug = trim((string) ($row['specialty_slug'] ?? ''));

    if (
        '' !== $specialty_slug &&
        !nwmd_directory_is_valid_business_csv_slug($specialty_slug)
    ) {
        nwmd_directory_add_business_csv_line_error(
            $errors,
            $error_count,
            $line_number,
            __('specialty_slug must be a lowercase WordPress slug.', 'local-directory-framework')
        );
    }

    $business_slug = trim((string) ($row['business_slug'] ?? ''));

    if (nwmd_directory_is_valid_business_csv_slug($business_slug)) {
        if (isset($seen_business_slugs[$business_slug])) {
            nwmd_directory_add_business_csv_line_error(
                $errors,
                $error_count,
                $line_number,
                __('business_slug is duplicated within this CSV.', 'local-directory-framework')
            );
        } else {
            $seen_business_slugs[$business_slug] = true;
        }

        $existing_business = get_page_by_path(
            $business_slug,
            OBJECT,
            'nwmd_business'
        );

        if ($existing_business instanceof WP_Post) {
            nwmd_directory_add_business_csv_line_error(
                $errors,
                $error_count,
                $line_number,
                __('business_slug already exists in WordPress.', 'local-directory-framework')
            );
        }
    }

    $taxonomy_fields = [
        'state_slug' => [
            'taxonomy' => 'nwmd_state',
            'label'    => 'state_slug',
        ],
        'city_slug' => [
            'taxonomy' => 'nwmd_city',
            'label'    => 'city_slug',
        ],
        'category_slug' => [
            'taxonomy' => 'nwmd_category',
            'label'    => 'category_slug',
        ],
        'specialty_slug' => [
            'taxonomy' => 'nwmd_specialty',
            'label'    => 'specialty_slug',
        ],
    ];

    $terms = [];

    foreach ($taxonomy_fields as $field => $settings) {
        $slug = trim((string) ($row[$field] ?? ''));

        if ('' === $slug && 'specialty_slug' === $field) {
            continue;
        }

        if (!nwmd_directory_is_valid_business_csv_slug($slug)) {
            continue;
        }

        $term = get_term_by(
            'slug',
            $slug,
            $settings['taxonomy']
        );

        if (!$term instanceof WP_Term) {
            nwmd_directory_add_business_csv_line_error(
                $errors,
                $error_count,
                $line_number,
                sprintf(
                    /* translators: %s: Taxonomy CSV column name. */
                    __('%s does not match an existing directory term.', 'local-directory-framework'),
                    $settings['label']
                )
            );

            continue;
        }

        $terms[$field] = $term;
    }

    if (
        isset($terms['state_slug'], $terms['city_slug']) &&
        absint(
            get_term_meta(
                $terms['city_slug']->term_id,
                'nwmd_state_term_id',
                true
            )
        ) !== absint($terms['state_slug']->term_id)
    ) {
        nwmd_directory_add_business_csv_line_error(
            $errors,
            $error_count,
            $line_number,
            __('city_slug does not belong to state_slug.', 'local-directory-framework')
        );
    }

    $website_url = trim((string) ($row['website_url'] ?? ''));

    if (!nwmd_directory_is_valid_business_csv_url($website_url)) {
        nwmd_directory_add_business_csv_line_error(
            $errors,
            $error_count,
            $line_number,
            __('website_url must be a valid public HTTP or HTTPS URL.', 'local-directory-framework')
        );
    }

    $public_email = trim((string) ($row['public_email'] ?? ''));

    if ('' !== $public_email && false === is_email($public_email)) {
        nwmd_directory_add_business_csv_line_error(
            $errors,
            $error_count,
            $line_number,
            __('public_email is not a valid email address.', 'local-directory-framework')
        );
    }

    $coordinates = [
        'latitude' => [
            'min' => -90,
            'max' => 90,
        ],
        'longitude' => [
            'min' => -180,
            'max' => 180,
        ],
    ];

    foreach ($coordinates as $field => $limits) {
        $raw_value = trim((string) ($row[$field] ?? ''));

        if ('' === $raw_value) {
            continue;
        }

        $sanitized = nwmd_directory_sanitize_coordinate(
            $raw_value,
            $limits['min'],
            $limits['max']
        );

        if ('' === $sanitized) {
            nwmd_directory_add_business_csv_line_error(
                $errors,
                $error_count,
                $line_number,
                sprintf(
                    /* translators: %s: Coordinate CSV column name. */
                    __('%s is outside its accepted numeric range.', 'local-directory-framework'),
                    $field
                )
            );
        }
    }

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

        $choices = nwmd_directory_get_business_record_choices($field);

        if (!isset($choices[$value])) {
            nwmd_directory_add_business_csv_line_error(
                $errors,
                $error_count,
                $line_number,
                sprintf(
                    /* translators: %s: Status CSV column name. */
                    __('%s contains an unsupported value.', 'local-directory-framework'),
                    $field
                )
            );
        }
    }

    $ranking_eligible = strtolower(
        trim((string) ($row['ranking_eligible'] ?? ''))
    );

    if (
        '' !== $ranking_eligible &&
        !in_array(
            $ranking_eligible,
            [
                '0',
                '1',
                'no',
                'yes',
                'false',
                'true',
            ],
            true
        )
    ) {
        nwmd_directory_add_business_csv_line_error(
            $errors,
            $error_count,
            $line_number,
            __('ranking_eligible contains an unsupported value.', 'local-directory-framework')
        );
    }

    $last_verified_at = trim(
        (string) ($row['last_verified_at'] ?? '')
    );

    if (
        '' !== $last_verified_at &&
        '' === nwmd_directory_sanitize_verified_date($last_verified_at)
    ) {
        nwmd_directory_add_business_csv_line_error(
            $errors,
            $error_count,
            $line_number,
            __('last_verified_at must use YYYY-MM-DD.', 'local-directory-framework')
        );
    }

    $unique_meta_fields = [
        'registration_number' => 'nwmd_registration_number',
        'license_number'      => 'nwmd_license_number',
    ];

    foreach ($unique_meta_fields as $field => $meta_key) {
        $value = sanitize_text_field(
            (string) ($row[$field] ?? '')
        );

        if ('' === $value) {
            continue;
        }

        $seen_values = 'registration_number' === $field
            ? $seen_registration_nums
            : $seen_license_nums;

        if (isset($seen_values[$value])) {
            nwmd_directory_add_business_csv_line_error(
                $errors,
                $error_count,
                $line_number,
                sprintf(
                    /* translators: %s: Unique CSV column name. */
                    __('%s is duplicated within this CSV.', 'local-directory-framework'),
                    $field
                )
            );
        }

        if ('registration_number' === $field) {
            $seen_registration_nums[$value] = true;
        } else {
            $seen_license_nums[$value] = true;
        }

        if (nwmd_directory_business_csv_meta_value_exists($meta_key, $value)) {
            nwmd_directory_add_business_csv_line_error(
                $errors,
                $error_count,
                $line_number,
                sprintf(
                    /* translators: %s: Unique CSV column name. */
                    __('%s already exists in WordPress.', 'local-directory-framework'),
                    $field
                )
            );
        }
    }

    if (!nwmd_directory_business_csv_row_has_source($row)) {
        return;
    }

    $source_type = sanitize_key(
        trim((string) ($row['source_type'] ?? ''))
    );
    $source_name = sanitize_text_field(
        (string) ($row['source_name'] ?? '')
    );
    $source_url = trim((string) ($row['source_url'] ?? ''));
    $source_identifier = sanitize_text_field(
        (string) ($row['source_identifier'] ?? '')
    );
    $source_retrieved_at = trim(
        (string) ($row['source_retrieved_at'] ?? '')
    );
    $source_verified_at = trim(
        (string) ($row['source_verified_at'] ?? '')
    );
    $source_result = sanitize_key(
        trim((string) ($row['source_verification_result'] ?? ''))
    );

    if ('' === $source_result) {
        $source_result = 'pending';
    }

    $source_types = nwmd_directory_get_business_source_type_choices();
    $source_results = nwmd_directory_get_business_source_result_choices();

    if (!isset($source_types[$source_type])) {
        nwmd_directory_add_business_csv_line_error(
            $errors,
            $error_count,
            $line_number,
            __('source_type is required and must contain a supported value.', 'local-directory-framework')
        );
    }

    if ('' === $source_name) {
        nwmd_directory_add_business_csv_line_error(
            $errors,
            $error_count,
            $line_number,
            __('source_name is required when source data is present.', 'local-directory-framework')
        );
    }

    if (!isset($source_results[$source_result])) {
        nwmd_directory_add_business_csv_line_error(
            $errors,
            $error_count,
            $line_number,
            __('source_verification_result contains an unsupported value.', 'local-directory-framework')
        );
    }

    if (!nwmd_directory_is_valid_business_csv_url($source_url)) {
        nwmd_directory_add_business_csv_line_error(
            $errors,
            $error_count,
            $line_number,
            __('source_url must be a valid public HTTP or HTTPS URL.', 'local-directory-framework')
        );
    }

    if ('' === $source_url && '' === $source_identifier) {
        nwmd_directory_add_business_csv_line_error(
            $errors,
            $error_count,
            $line_number,
            __('source_url or source_identifier is required when source data is present.', 'local-directory-framework')
        );
    }

    if (
        '' === $source_retrieved_at ||
        '' === nwmd_directory_sanitize_business_source_date(
            $source_retrieved_at
        )
    ) {
        nwmd_directory_add_business_csv_line_error(
            $errors,
            $error_count,
            $line_number,
            __('source_retrieved_at is required and must use YYYY-MM-DD.', 'local-directory-framework')
        );
    }

    if (
        '' !== $source_verified_at &&
        '' === nwmd_directory_sanitize_business_source_date(
            $source_verified_at
        )
    ) {
        nwmd_directory_add_business_csv_line_error(
            $errors,
            $error_count,
            $line_number,
            __('source_verified_at must use YYYY-MM-DD.', 'local-directory-framework')
        );
    }

    if (
        isset($source_results[$source_result]) &&
        'pending' !== $source_result &&
        '' === $source_verified_at
    ) {
        nwmd_directory_add_business_csv_line_error(
            $errors,
            $error_count,
            $line_number,
            __('source_verified_at is required when source_verification_result is not pending.', 'local-directory-framework')
        );
    }
}

/**
 * Validate one uploaded Business CSV without changing records.
 *
 * @param array $file Uploaded file information.
 *
 * @return array
 */
function nwmd_directory_validate_business_csv_upload($file) {

    $errors = [];
    $error_count = 0;
    $row_count = 0;

    if (!is_array($file)) {
        nwmd_directory_add_business_csv_validation_error(
            $errors,
            $error_count,
            __('Select a CSV file to validate.', 'local-directory-framework')
        );

        return compact('errors', 'error_count', 'row_count');
    }

    $upload_error = isset($file['error'])
        ? absint($file['error'])
        : UPLOAD_ERR_NO_FILE;

    if (UPLOAD_ERR_OK !== $upload_error) {
        $upload_messages = [
            UPLOAD_ERR_INI_SIZE => __('The CSV exceeds the server upload limit.', 'local-directory-framework'),
            UPLOAD_ERR_FORM_SIZE => __('The CSV exceeds the form upload limit.', 'local-directory-framework'),
            UPLOAD_ERR_PARTIAL => __('The CSV upload was incomplete.', 'local-directory-framework'),
            UPLOAD_ERR_NO_FILE => __('Select a CSV file to validate.', 'local-directory-framework'),
            UPLOAD_ERR_NO_TMP_DIR => __('The server upload directory is unavailable.', 'local-directory-framework'),
            UPLOAD_ERR_CANT_WRITE => __('The server could not write the uploaded CSV.', 'local-directory-framework'),
            UPLOAD_ERR_EXTENSION => __('A server extension stopped the CSV upload.', 'local-directory-framework'),
        ];

        nwmd_directory_add_business_csv_validation_error(
            $errors,
            $error_count,
            $upload_messages[$upload_error]
                ?? __('The CSV upload failed.', 'local-directory-framework')
        );

        return compact('errors', 'error_count', 'row_count');
    }

    $file_name = isset($file['name'])
        ? sanitize_file_name((string) $file['name'])
        : '';
    $file_size = isset($file['size'])
        ? absint($file['size'])
        : 0;
    $tmp_name = isset($file['tmp_name'])
        ? (string) $file['tmp_name']
        : '';

    if ('csv' !== strtolower((string) pathinfo($file_name, PATHINFO_EXTENSION))) {
        nwmd_directory_add_business_csv_validation_error(
            $errors,
            $error_count,
            __('The uploaded file must use the .csv extension.', 'local-directory-framework')
        );
    }

    if ($file_size < 1) {
        nwmd_directory_add_business_csv_validation_error(
            $errors,
            $error_count,
            __('The uploaded CSV is empty.', 'local-directory-framework')
        );
    }

    if ($file_size > 2 * MB_IN_BYTES) {
        nwmd_directory_add_business_csv_validation_error(
            $errors,
            $error_count,
            __('The uploaded CSV exceeds the 2 MB limit.', 'local-directory-framework')
        );
    }

    if (
        '' === $tmp_name ||
        !is_uploaded_file($tmp_name) ||
        !is_readable($tmp_name)
    ) {
        nwmd_directory_add_business_csv_validation_error(
            $errors,
            $error_count,
            __('The uploaded CSV could not be read safely.', 'local-directory-framework')
        );
    }

    if ($error_count > 0) {
        return compact('errors', 'error_count', 'row_count');
    }

    $handle = fopen($tmp_name, 'rb');

    if (false === $handle) {
        nwmd_directory_add_business_csv_validation_error(
            $errors,
            $error_count,
            __('The uploaded CSV could not be opened.', 'local-directory-framework')
        );

        return compact('errors', 'error_count', 'row_count');
    }

    $first_bytes = fread($handle, 3);

    if ("\xEF\xBB\xBF" !== $first_bytes) {
        rewind($handle);
    }

    $headers = fgetcsv($handle, 0, ',', '"', '\\');

    if (!is_array($headers) || empty($headers)) {
        fclose($handle);

        nwmd_directory_add_business_csv_validation_error(
            $errors,
            $error_count,
            __('The CSV header row is missing or unreadable.', 'local-directory-framework')
        );

        return compact('errors', 'error_count', 'row_count');
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

    $allowed_columns = nwmd_directory_get_business_csv_import_columns();
    $allowed_lookup = array_fill_keys($allowed_columns, true);
    $seen_headers = [];

    foreach ($headers as $header) {
        if ('' === $header) {
            nwmd_directory_add_business_csv_validation_error(
                $errors,
                $error_count,
                __('The CSV contains an empty header name.', 'local-directory-framework')
            );

            continue;
        }

        if (isset($seen_headers[$header])) {
            nwmd_directory_add_business_csv_validation_error(
                $errors,
                $error_count,
                sprintf(
                    /* translators: %s: Duplicate CSV header. */
                    __('Duplicate CSV header: %s.', 'local-directory-framework'),
                    $header
                )
            );
        }

        $seen_headers[$header] = true;

        if (!isset($allowed_lookup[$header])) {
            nwmd_directory_add_business_csv_validation_error(
                $errors,
                $error_count,
                sprintf(
                    /* translators: %s: Unknown CSV header. */
                    __('Unknown CSV header: %s.', 'local-directory-framework'),
                    $header
                )
            );
        }
    }

    foreach (nwmd_directory_get_required_business_csv_import_columns() as $required_column) {
        if (!isset($seen_headers[$required_column])) {
            nwmd_directory_add_business_csv_validation_error(
                $errors,
                $error_count,
                sprintf(
                    /* translators: %s: Missing required CSV header. */
                    __('Missing required CSV header: %s.', 'local-directory-framework'),
                    $required_column
                )
            );
        }
    }

    if ($error_count > 0) {
        fclose($handle);

        return compact('errors', 'error_count', 'row_count');
    }

    $line_number = 1;
    $seen_business_slugs = [];
    $seen_registration_nums = [];
    $seen_license_nums = [];
    $row_limit_reported = false;

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

        $row_count++;

        if ($row_count > 500) {
            if (!$row_limit_reported) {
                nwmd_directory_add_business_csv_validation_error(
                    $errors,
                    $error_count,
                    __('The CSV contains more than the 500-row limit.', 'local-directory-framework')
                );

                $row_limit_reported = true;
            }

            continue;
        }

        if (count($values) !== count($headers)) {
            nwmd_directory_add_business_csv_line_error(
                $errors,
                $error_count,
                $line_number,
                sprintf(
                    /* translators: 1: Actual column count, 2: Expected column count. */
                    __('The row contains %1$d columns; %2$d were expected.', 'local-directory-framework'),
                    count($values),
                    count($headers)
                )
            );

            continue;
        }

        $row = array_combine($headers, $values);

        if (!is_array($row)) {
            nwmd_directory_add_business_csv_line_error(
                $errors,
                $error_count,
                $line_number,
                __('The row could not be mapped to the CSV headers.', 'local-directory-framework')
            );

            continue;
        }

        nwmd_directory_validate_business_csv_row(
            $row,
            $line_number,
            $seen_business_slugs,
            $seen_registration_nums,
            $seen_license_nums,
            $errors,
            $error_count
        );
    }

    if (!feof($handle)) {
        nwmd_directory_add_business_csv_validation_error(
            $errors,
            $error_count,
            __('The CSV could not be read completely.', 'local-directory-framework')
        );
    }

    fclose($handle);

    if (0 === $row_count) {
        nwmd_directory_add_business_csv_validation_error(
            $errors,
            $error_count,
            __('The CSV does not contain any business rows.', 'local-directory-framework')
        );
    }

    return compact('errors', 'error_count', 'row_count');
}

/**
 * Validate an uploaded Business CSV without creating records.
 */
function nwmd_directory_handle_business_csv_validation() {

    if (!current_user_can('manage_options')) {
        wp_die(
            esc_html__(
                'You are not allowed to validate business CSV files.',
                'local-directory-framework'
            )
        );
    }

    check_admin_referer(
        'nwmd_import_business_csv',
        'nwmd_business_csv_nonce'
    );

    $file = isset($_FILES['business_csv_file'])
        ? $_FILES['business_csv_file']
        : [];

    $result = nwmd_directory_validate_business_csv_upload($file);
    $error_count = isset($result['error_count'])
        ? absint($result['error_count'])
        : 0;
    $row_count = isset($result['row_count'])
        ? absint($result['row_count'])
        : 0;
    $errors = isset($result['errors']) && is_array($result['errors'])
        ? $result['errors']
        : [];

    nwmd_directory_finish_business_csv_validation(
        0 === $error_count,
        $row_count,
        $errors,
        $error_count
    );
}

add_action(
    'admin_post_nwmd_validate_business_csv',
    'nwmd_directory_handle_business_csv_validation'
);
