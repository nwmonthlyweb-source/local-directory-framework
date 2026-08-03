<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return the controlled Business Deal CSV columns.
 *
 * @return array
 */
function nwmd_directory_get_business_deal_csv_import_columns() {

    return [
        'business_slug',
        'deal_slug',
        'title',
        'card_text',
        'description',
        'promo_code',
        'source_url',
        'starts_at',
        'expires_at',
        'verified_at',
        'status',
        'is_featured',
    ];
}

/**
 * Return the required Business Deal CSV headers.
 *
 * Values may be blank where the field rules permit, but every
 * header must remain present so monthly update files are stable.
 *
 * @return array
 */
function nwmd_directory_get_required_business_deal_csv_columns() {

    return nwmd_directory_get_business_deal_csv_import_columns();
}

/**
 * Return the current administrator's one-time result key.
 *
 * @return string
 */
function nwmd_directory_get_business_deal_csv_result_key() {

    return 'nwmd_business_deal_csv_' . get_current_user_id();
}

/**
 * Store one Deal CSV result.
 *
 * @param array $result Process result.
 */
function nwmd_directory_store_business_deal_csv_result($result) {

    set_transient(
        nwmd_directory_get_business_deal_csv_result_key(),
        is_array($result) ? $result : [],
        10 * MINUTE_IN_SECONDS
    );
}

/**
 * Return and remove one Deal CSV result.
 *
 * @return array
 */
function nwmd_directory_take_business_deal_csv_result() {

    $key = nwmd_directory_get_business_deal_csv_result_key();
    $result = get_transient($key);

    delete_transient($key);

    return is_array($result)
        ? $result
        : [];
}

/**
 * Redirect to the Deal CSV importer.
 */
function nwmd_directory_redirect_business_deal_csv_import() {

    wp_safe_redirect(
        add_query_arg(
            [
                'post_type' => 'nwmd_business',
                'page'      => 'nwmd-business-deals-csv-import',
            ],
            admin_url('edit.php')
        )
    );

    exit;
}

/**
 * Store a result and return to the Deal CSV importer.
 *
 * @param array $result Result data.
 */
function nwmd_directory_finish_business_deal_csv_process(
    $result
) {

    nwmd_directory_store_business_deal_csv_result(
        $result
    );

    nwmd_directory_redirect_business_deal_csv_import();
}

/**
 * Add one validation error while limiting transient size.
 *
 * @param array  $errors      Displayable errors.
 * @param int    $error_count Total error count.
 * @param string $message     Error message.
 */
function nwmd_directory_add_business_deal_csv_error(
    &$errors,
    &$error_count,
    $message
) {

    $error_count++;

    if (count($errors) < 200) {
        $errors[] = sanitize_text_field(
            $message
        );
    }
}

/**
 * Add one line-specific validation error.
 *
 * @param array  $errors      Displayable errors.
 * @param int    $error_count Total error count.
 * @param int    $line_number CSV line number.
 * @param string $message     Error message.
 */
function nwmd_directory_add_business_deal_csv_line_error(
    &$errors,
    &$error_count,
    $line_number,
    $message
) {

    nwmd_directory_add_business_deal_csv_error(
        $errors,
        $error_count,
        sprintf(
            /* translators: 1: CSV line number, 2: validation message. */
            __(
                'Line %1$d: %2$s',
                'local-directory-framework'
            ),
            absint($line_number),
            $message
        )
    );
}

/**
 * Return the length of one UTF-8 text value.
 *
 * @param string $value Text value.
 *
 * @return int
 */
function nwmd_directory_business_deal_csv_text_length(
    $value
) {

    if (function_exists('mb_strlen')) {
        return mb_strlen(
            $value
        );
    }

    return strlen(
        $value
    );
}

/**
 * Return whether a value is a strict WordPress-style slug.
 *
 * @param string $value Slug value.
 *
 * @return bool
 */
function nwmd_directory_business_deal_csv_is_valid_slug(
    $value
) {

    return (
        '' !== $value &&
        strlen($value) <= 191 &&
        1 === preg_match(
            '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            $value
        )
    );
}

/**
 * Return a Business post using its stable slug.
 *
 * @param string $business_slug Business slug.
 *
 * @return WP_Post|null
 */
function nwmd_directory_get_business_deal_csv_business(
    $business_slug
) {

    $business = get_page_by_path(
        $business_slug,
        OBJECT,
        'nwmd_business'
    );

    if (
        !$business instanceof WP_Post ||
        in_array(
            $business->post_status,
            [
                'trash',
                'auto-draft',
            ],
            true
        )
    ) {
        return null;
    }

    return $business;
}

/**
 * Return whether a Deal source URL is valid.
 *
 * @param string $value URL value.
 *
 * @return bool
 */
function nwmd_directory_business_deal_csv_is_valid_url(
    $value
) {

    if ('' === $value) {
        return true;
    }

    $scheme = strtolower(
        (string) wp_parse_url(
            $value,
            PHP_URL_SCHEME
        )
    );

    return (
        in_array(
            $scheme,
            [
                'http',
                'https',
            ],
            true
        ) &&
        false !== wp_http_validate_url($value)
    );
}

/**
 * Validate one Business Deal CSV row.
 *
 * @param array $row                 CSV row.
 * @param int   $line_number         CSV line number.
 * @param array $seen_identities     Seen business/deal identities.
 * @param array $featured_businesses Businesses with a featured row.
 * @param array $errors              Displayable errors.
 * @param int   $error_count         Total error count.
 * @param int   $create_count        Predicted creates.
 * @param int   $update_count        Predicted updates.
 */
function nwmd_directory_validate_business_deal_csv_row(
    $row,
    $line_number,
    &$seen_identities,
    &$featured_businesses,
    &$errors,
    &$error_count,
    &$create_count,
    &$update_count
) {

    $row_error_start = $error_count;

    $business_slug = trim(
        (string) ($row['business_slug'] ?? '')
    );

    $deal_slug = trim(
        (string) ($row['deal_slug'] ?? '')
    );

    $title = trim(
        (string) ($row['title'] ?? '')
    );

    $card_text = trim(
        (string) ($row['card_text'] ?? '')
    );

    $description = trim(
        (string) ($row['description'] ?? '')
    );

    $promo_code = trim(
        (string) ($row['promo_code'] ?? '')
    );

    $source_url = trim(
        (string) ($row['source_url'] ?? '')
    );

    $starts_value = trim(
        (string) ($row['starts_at'] ?? '')
    );

    $expires_value = trim(
        (string) ($row['expires_at'] ?? '')
    );

    $verified_value = trim(
        (string) ($row['verified_at'] ?? '')
    );

    $status = sanitize_key(
        trim(
            (string) ($row['status'] ?? '')
        )
    );

    $featured_value = trim(
        (string) ($row['is_featured'] ?? '')
    );

    if ('' === $featured_value) {
        $featured_value = '0';
    }

    if (
        !nwmd_directory_business_deal_csv_is_valid_slug(
            $business_slug
        )
    ) {
        nwmd_directory_add_business_deal_csv_line_error(
            $errors,
            $error_count,
            $line_number,
            __(
                'business_slug must be a lowercase WordPress-style slug.',
                'local-directory-framework'
            )
        );
    }

    $business = null;

    if (
        nwmd_directory_business_deal_csv_is_valid_slug(
            $business_slug
        )
    ) {
        $business =
            nwmd_directory_get_business_deal_csv_business(
                $business_slug
            );

        if (!$business instanceof WP_Post) {
            nwmd_directory_add_business_deal_csv_line_error(
                $errors,
                $error_count,
                $line_number,
                __(
                    'business_slug does not match an existing Business.',
                    'local-directory-framework'
                )
            );
        }
    }

    if (
        !nwmd_directory_business_deal_csv_is_valid_slug(
            $deal_slug
        )
    ) {
        nwmd_directory_add_business_deal_csv_line_error(
            $errors,
            $error_count,
            $line_number,
            __(
                'deal_slug must be a lowercase WordPress-style slug.',
                'local-directory-framework'
            )
        );
    }

    if (
        nwmd_directory_business_deal_csv_is_valid_slug(
            $business_slug
        ) &&
        nwmd_directory_business_deal_csv_is_valid_slug(
            $deal_slug
        )
    ) {
        $identity = $business_slug . '|' . $deal_slug;

        if (isset($seen_identities[$identity])) {
            nwmd_directory_add_business_deal_csv_line_error(
                $errors,
                $error_count,
                $line_number,
                __(
                    'business_slug and deal_slug are duplicated within this CSV.',
                    'local-directory-framework'
                )
            );
        } else {
            $seen_identities[$identity] = true;
        }
    }

    if ('' === $title) {
        nwmd_directory_add_business_deal_csv_line_error(
            $errors,
            $error_count,
            $line_number,
            __(
                'title is required.',
                'local-directory-framework'
            )
        );
    } elseif (
        nwmd_directory_business_deal_csv_text_length(
            $title
        ) > 255
    ) {
        nwmd_directory_add_business_deal_csv_line_error(
            $errors,
            $error_count,
            $line_number,
            __(
                'title exceeds 255 characters.',
                'local-directory-framework'
            )
        );
    }

    if (
        nwmd_directory_business_deal_csv_text_length(
            $card_text
        ) > 255
    ) {
        nwmd_directory_add_business_deal_csv_line_error(
            $errors,
            $error_count,
            $line_number,
            __(
                'card_text exceeds 255 characters.',
                'local-directory-framework'
            )
        );
    }

    if ('' === $description) {
        nwmd_directory_add_business_deal_csv_line_error(
            $errors,
            $error_count,
            $line_number,
            __(
                'description is required.',
                'local-directory-framework'
            )
        );
    } elseif (
        nwmd_directory_business_deal_csv_text_length(
            $description
        ) > 10000
    ) {
        nwmd_directory_add_business_deal_csv_line_error(
            $errors,
            $error_count,
            $line_number,
            __(
                'description exceeds 10,000 characters.',
                'local-directory-framework'
            )
        );
    }

    if (
        nwmd_directory_business_deal_csv_text_length(
            $promo_code
        ) > 100
    ) {
        nwmd_directory_add_business_deal_csv_line_error(
            $errors,
            $error_count,
            $line_number,
            __(
                'promo_code exceeds 100 characters.',
                'local-directory-framework'
            )
        );
    }

    if (
        nwmd_directory_business_deal_csv_text_length(
            $source_url
        ) > 2048 ||
        !nwmd_directory_business_deal_csv_is_valid_url(
            $source_url
        )
    ) {
        nwmd_directory_add_business_deal_csv_line_error(
            $errors,
            $error_count,
            $line_number,
            __(
                'source_url must be a valid HTTP or HTTPS URL.',
                'local-directory-framework'
            )
        );
    }

    $starts_at =
        nwmd_directory_parse_business_deal_admin_date(
            $starts_value
        );

    $expires_at =
        nwmd_directory_parse_business_deal_admin_date(
            $expires_value,
            true
        );

    $verified_at =
        nwmd_directory_parse_business_deal_admin_date(
            $verified_value
        );

    if (false === $starts_at) {
        nwmd_directory_add_business_deal_csv_line_error(
            $errors,
            $error_count,
            $line_number,
            __(
                'starts_at must be blank or use YYYY-MM-DD.',
                'local-directory-framework'
            )
        );
    }

    if (false === $expires_at) {
        nwmd_directory_add_business_deal_csv_line_error(
            $errors,
            $error_count,
            $line_number,
            __(
                'expires_at must be blank or use YYYY-MM-DD.',
                'local-directory-framework'
            )
        );
    }

    if (false === $verified_at) {
        nwmd_directory_add_business_deal_csv_line_error(
            $errors,
            $error_count,
            $line_number,
            __(
                'verified_at must be blank or use YYYY-MM-DD.',
                'local-directory-framework'
            )
        );
    }

    if (
        false !== $starts_at &&
        false !== $expires_at &&
        null !== $starts_at &&
        null !== $expires_at &&
        $expires_at < $starts_at
    ) {
        nwmd_directory_add_business_deal_csv_line_error(
            $errors,
            $error_count,
            $line_number,
            __(
                'expires_at must not precede starts_at.',
                'local-directory-framework'
            )
        );
    }

    if (
        false !== $verified_at &&
        null !== $verified_at &&
        $verified_at > current_time('mysql')
    ) {
        nwmd_directory_add_business_deal_csv_line_error(
            $errors,
            $error_count,
            $line_number,
            __(
                'verified_at cannot be in the future.',
                'local-directory-framework'
            )
        );
    }

    $status_choices =
        nwmd_directory_get_business_deal_status_choices();

    if (!isset($status_choices[$status])) {
        nwmd_directory_add_business_deal_csv_line_error(
            $errors,
            $error_count,
            $line_number,
            __(
                'status must be draft, active, inactive, or archived.',
                'local-directory-framework'
            )
        );
    }

    if (
        !in_array(
            $featured_value,
            [
                '0',
                '1',
            ],
            true
        )
    ) {
        nwmd_directory_add_business_deal_csv_line_error(
            $errors,
            $error_count,
            $line_number,
            __(
                'is_featured must be 0 or 1.',
                'local-directory-framework'
            )
        );
    }

    if (
        'active' === $status &&
        (
            '' === $source_url ||
            null === $verified_at ||
            false === $verified_at
        )
    ) {
        nwmd_directory_add_business_deal_csv_line_error(
            $errors,
            $error_count,
            $line_number,
            __(
                'An active Deal requires source_url and verified_at.',
                'local-directory-framework'
            )
        );
    }

    if (
        'active' === $status &&
        false !== $expires_at &&
        null !== $expires_at &&
        $expires_at < current_time('mysql')
    ) {
        nwmd_directory_add_business_deal_csv_line_error(
            $errors,
            $error_count,
            $line_number,
            __(
                'An expired Deal cannot be imported as active.',
                'local-directory-framework'
            )
        );
    }

    if (
        '1' === $featured_value &&
        'active' !== $status
    ) {
        nwmd_directory_add_business_deal_csv_line_error(
            $errors,
            $error_count,
            $line_number,
            __(
                'Only an active Deal may be featured.',
                'local-directory-framework'
            )
        );
    }

    if (
        '1' === $featured_value &&
        nwmd_directory_business_deal_csv_is_valid_slug(
            $business_slug
        )
    ) {
        if (isset($featured_businesses[$business_slug])) {
            nwmd_directory_add_business_deal_csv_line_error(
                $errors,
                $error_count,
                $line_number,
                __(
                    'Only one Deal per business may be featured in one CSV.',
                    'local-directory-framework'
                )
            );
        } else {
            $featured_businesses[$business_slug] = true;
        }
    }

    if (
        $error_count === $row_error_start &&
        $business instanceof WP_Post
    ) {
        $existing =
            nwmd_directory_get_business_deal_by_identity(
                $business->ID,
                $deal_slug
            );

        if (is_object($existing)) {
            $update_count++;
        } else {
            $create_count++;
        }
    }
}

/**
 * Validate an uploaded Business Deal CSV.
 *
 * @param array $file Uploaded file data.
 *
 * @return array
 */
function nwmd_directory_validate_business_deal_csv_upload(
    $file
) {

    $errors = [];
    $error_count = 0;
    $row_count = 0;
    $create_count = 0;
    $update_count = 0;

    if (!is_array($file)) {
        nwmd_directory_add_business_deal_csv_error(
            $errors,
            $error_count,
            __(
                'Select a Deal CSV file.',
                'local-directory-framework'
            )
        );

        return compact(
            'errors',
            'error_count',
            'row_count',
            'create_count',
            'update_count'
        );
    }

    $upload_error = isset($file['error'])
        ? absint($file['error'])
        : UPLOAD_ERR_NO_FILE;

    if (UPLOAD_ERR_OK !== $upload_error) {
        $upload_messages = [
            UPLOAD_ERR_INI_SIZE =>
                __(
                    'The CSV exceeds the server upload limit.',
                    'local-directory-framework'
                ),
            UPLOAD_ERR_FORM_SIZE =>
                __(
                    'The CSV exceeds the form upload limit.',
                    'local-directory-framework'
                ),
            UPLOAD_ERR_PARTIAL =>
                __(
                    'The CSV upload was incomplete.',
                    'local-directory-framework'
                ),
            UPLOAD_ERR_NO_FILE =>
                __(
                    'Select a Deal CSV file.',
                    'local-directory-framework'
                ),
            UPLOAD_ERR_NO_TMP_DIR =>
                __(
                    'The server upload directory is unavailable.',
                    'local-directory-framework'
                ),
            UPLOAD_ERR_CANT_WRITE =>
                __(
                    'The server could not write the uploaded CSV.',
                    'local-directory-framework'
                ),
            UPLOAD_ERR_EXTENSION =>
                __(
                    'A server extension stopped the CSV upload.',
                    'local-directory-framework'
                ),
        ];

        nwmd_directory_add_business_deal_csv_error(
            $errors,
            $error_count,
            $upload_messages[$upload_error] ??
                __(
                    'The CSV upload failed.',
                    'local-directory-framework'
                )
        );

        return compact(
            'errors',
            'error_count',
            'row_count',
            'create_count',
            'update_count'
        );
    }

    $file_name = isset($file['name'])
        ? sanitize_file_name(
            (string) $file['name']
        )
        : '';

    $file_size = isset($file['size'])
        ? absint($file['size'])
        : 0;

    $tmp_name = isset($file['tmp_name'])
        ? (string) $file['tmp_name']
        : '';

    if (
        'csv' !== strtolower(
            (string) pathinfo(
                $file_name,
                PATHINFO_EXTENSION
            )
        )
    ) {
        nwmd_directory_add_business_deal_csv_error(
            $errors,
            $error_count,
            __(
                'The uploaded file must use the .csv extension.',
                'local-directory-framework'
            )
        );
    }

    if ($file_size < 1) {
        nwmd_directory_add_business_deal_csv_error(
            $errors,
            $error_count,
            __(
                'The uploaded CSV is empty.',
                'local-directory-framework'
            )
        );
    }

    if ($file_size > 2 * MB_IN_BYTES) {
        nwmd_directory_add_business_deal_csv_error(
            $errors,
            $error_count,
            __(
                'The uploaded CSV exceeds the 2 MB limit.',
                'local-directory-framework'
            )
        );
    }

    if (
        '' === $tmp_name ||
        !is_uploaded_file($tmp_name) ||
        !is_readable($tmp_name)
    ) {
        nwmd_directory_add_business_deal_csv_error(
            $errors,
            $error_count,
            __(
                'The uploaded CSV could not be read safely.',
                'local-directory-framework'
            )
        );
    }

    if ($error_count > 0) {
        return compact(
            'errors',
            'error_count',
            'row_count',
            'create_count',
            'update_count'
        );
    }

    $handle = fopen(
        $tmp_name,
        'rb'
    );

    if (false === $handle) {
        nwmd_directory_add_business_deal_csv_error(
            $errors,
            $error_count,
            __(
                'The uploaded CSV could not be opened.',
                'local-directory-framework'
            )
        );

        return compact(
            'errors',
            'error_count',
            'row_count',
            'create_count',
            'update_count'
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

        nwmd_directory_add_business_deal_csv_error(
            $errors,
            $error_count,
            __(
                'The CSV header row is missing or unreadable.',
                'local-directory-framework'
            )
        );

        return compact(
            'errors',
            'error_count',
            'row_count',
            'create_count',
            'update_count'
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

    $allowed_columns =
        nwmd_directory_get_business_deal_csv_import_columns();

    $allowed_lookup = array_fill_keys(
        $allowed_columns,
        true
    );

    $seen_headers = [];

    foreach ($headers as $header) {
        if ('' === $header) {
            nwmd_directory_add_business_deal_csv_error(
                $errors,
                $error_count,
                __(
                    'The CSV contains an empty header name.',
                    'local-directory-framework'
                )
            );

            continue;
        }

        if (isset($seen_headers[$header])) {
            nwmd_directory_add_business_deal_csv_error(
                $errors,
                $error_count,
                sprintf(
                    /* translators: %s: Duplicate header. */
                    __(
                        'Duplicate CSV header: %s.',
                        'local-directory-framework'
                    ),
                    $header
                )
            );
        }

        $seen_headers[$header] = true;

        if (!isset($allowed_lookup[$header])) {
            nwmd_directory_add_business_deal_csv_error(
                $errors,
                $error_count,
                sprintf(
                    /* translators: %s: Unknown header. */
                    __(
                        'Unknown CSV header: %s.',
                        'local-directory-framework'
                    ),
                    $header
                )
            );
        }
    }

    foreach (
        nwmd_directory_get_required_business_deal_csv_columns()
        as $required_column
    ) {
        if (!isset($seen_headers[$required_column])) {
            nwmd_directory_add_business_deal_csv_error(
                $errors,
                $error_count,
                sprintf(
                    /* translators: %s: Missing header. */
                    __(
                        'Missing required CSV header: %s.',
                        'local-directory-framework'
                    ),
                    $required_column
                )
            );
        }
    }

    if ($error_count > 0) {
        fclose($handle);

        return compact(
            'errors',
            'error_count',
            'row_count',
            'create_count',
            'update_count'
        );
    }

    $line_number = 1;
    $seen_identities = [];
    $featured_businesses = [];
    $row_limit_reported = false;

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

        $row_count++;

        if ($row_count > 500) {
            if (!$row_limit_reported) {
                nwmd_directory_add_business_deal_csv_error(
                    $errors,
                    $error_count,
                    __(
                        'The CSV contains more than the 500-row limit.',
                        'local-directory-framework'
                    )
                );

                $row_limit_reported = true;
            }

            continue;
        }

        if (count($values) !== count($headers)) {
            nwmd_directory_add_business_deal_csv_line_error(
                $errors,
                $error_count,
                $line_number,
                sprintf(
                    /* translators: 1: Actual columns, 2: Expected columns. */
                    __(
                        'The row contains %1$d columns; %2$d were expected.',
                        'local-directory-framework'
                    ),
                    count($values),
                    count($headers)
                )
            );

            continue;
        }

        $row = array_combine(
            $headers,
            $values
        );

        if (!is_array($row)) {
            nwmd_directory_add_business_deal_csv_line_error(
                $errors,
                $error_count,
                $line_number,
                __(
                    'The row could not be mapped to the CSV headers.',
                    'local-directory-framework'
                )
            );

            continue;
        }

        nwmd_directory_validate_business_deal_csv_row(
            $row,
            $line_number,
            $seen_identities,
            $featured_businesses,
            $errors,
            $error_count,
            $create_count,
            $update_count
        );
    }

    if (!feof($handle)) {
        nwmd_directory_add_business_deal_csv_error(
            $errors,
            $error_count,
            __(
                'The CSV could not be read completely.',
                'local-directory-framework'
            )
        );
    }

    fclose($handle);

    if (0 === $row_count) {
        nwmd_directory_add_business_deal_csv_error(
            $errors,
            $error_count,
            __(
                'The CSV does not contain any Deal rows.',
                'local-directory-framework'
            )
        );
    }

    return compact(
        'errors',
        'error_count',
        'row_count',
        'create_count',
        'update_count'
    );
}
