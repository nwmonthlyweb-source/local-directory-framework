<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return the canonical Business write projection schema.
 *
 * Roles control preparation, preview, execution, rollback, and verification.
 *
 * @return array
 */
function nwmd_directory_get_ai_state_import_business_projection_schema() {

    return [
        'business_name' => [
            'role'    => 'writable',
            'storage' => 'post',
            'column'  => 'post_title',
        ],
        'business_slug' => [
            'role'    => 'identity',
            'storage' => 'post',
            'column'  => 'post_name',
        ],
        'post_status' => [
            'role'    => 'safety',
            'storage' => 'post',
            'column'  => 'post_status',
        ],
        'state_slugs' => [
            'role'     => 'taxonomy',
            'storage'  => 'taxonomy',
            'taxonomy' => 'nwmd_state',
            'index_column' => 'state_term_id',
        ],
        'city_slugs' => [
            'role'     => 'taxonomy',
            'storage'  => 'taxonomy',
            'taxonomy' => 'nwmd_city',
            'index_column' => 'city_term_id',
        ],
        'category_slugs' => [
            'role'     => 'taxonomy',
            'storage'  => 'taxonomy',
            'taxonomy' => 'nwmd_category',
        ],
        'specialty_slugs' => [
            'role'     => 'taxonomy',
            'storage'  => 'taxonomy',
            'taxonomy' => 'nwmd_specialty',
        ],
        'description' => [
            'role'    => 'writable',
            'storage' => 'post',
            'column'  => 'post_content',
        ],
        'excerpt' => [
            'role'    => 'writable',
            'storage' => 'post',
            'column'  => 'post_excerpt',
        ],
        'public_name' => [
            'role'    => 'writable',
            'storage' => 'meta',
            'key'     => 'nwmd_public_name',
            'index_column' => 'public_name',
        ],
        'legal_name' => [
            'role'    => 'writable',
            'storage' => 'meta',
            'key'     => 'nwmd_legal_name',
            'index_column' => 'legal_name',
        ],
        'website_url' => [
            'role'    => 'writable',
            'storage' => 'meta',
            'key'     => 'nwmd_website_url',
            'index_column' => 'website_url',
        ],
        'public_email' => [
            'role'    => 'writable',
            'storage' => 'meta',
            'key'     => 'nwmd_public_email',
            'index_column' => 'public_email',
        ],
        'public_phone' => [
            'role'    => 'writable',
            'storage' => 'meta',
            'key'     => 'nwmd_public_phone',
            'index_column' => 'public_phone',
        ],
        'street_address' => [
            'role'    => 'writable',
            'storage' => 'meta',
            'key'     => 'nwmd_street_address',
            'index_column' => 'street_address',
        ],
        'postal_code' => [
            'role'    => 'writable',
            'storage' => 'meta',
            'key'     => 'nwmd_postal_code',
            'index_column' => 'postal_code',
        ],
        'latitude' => [
            'role'    => 'writable',
            'storage' => 'meta',
            'key'     => 'nwmd_latitude',
            'index_column' => 'latitude',
        ],
        'longitude' => [
            'role'    => 'writable',
            'storage' => 'meta',
            'key'     => 'nwmd_longitude',
            'index_column' => 'longitude',
        ],
        'registration_number' => [
            'role'    => 'writable',
            'storage' => 'meta',
            'key'     => 'nwmd_registration_number',
            'index_column' => 'registration_number',
        ],
        'license_number' => [
            'role'    => 'writable',
            'storage' => 'meta',
            'key'     => 'nwmd_license_number',
            'index_column' => 'license_number',
        ],
        'license_status' => [
            'role'    => 'writable',
            'storage' => 'meta',
            'key'     => 'nwmd_license_status',
            'index_column' => 'license_status',
        ],
        'verification_status' => [
            'role'    => 'writable',
            'storage' => 'meta',
            'key'     => 'nwmd_verification_status',
            'index_column' => 'verification_status',
        ],
        'claimed_status' => [
            'role'    => 'writable',
            'storage' => 'meta',
            'key'     => 'nwmd_claimed_status',
            'index_column' => 'claimed_status',
        ],
        'ranking_eligible' => [
            'role'    => 'writable',
            'storage' => 'meta',
            'key'     => 'nwmd_ranking_eligible',
            'index_column' => 'ranking_eligible',
        ],
        'last_verified_at' => [
            'role'    => 'writable',
            'storage' => 'meta',
            'key'     => 'nwmd_last_verified_at',
            'index_column' => 'last_verified_at',
        ],
    ];
}

/**
 * Return the canonical Business Source write projection schema.
 *
 * @return array
 */
function nwmd_directory_get_ai_state_import_source_projection_schema() {

    return [
        'business_slug' => [
            'role'    => 'identity',
            'storage' => 'relation',
        ],
        'source_type' => [
            'role'    => 'identity',
            'storage' => 'column',
            'column'  => 'source_type',
        ],
        'source_name' => [
            'role'    => 'writable',
            'storage' => 'column',
            'column'  => 'source_name',
        ],
        'source_url' => [
            'role'    => 'identity',
            'storage' => 'column',
            'column'  => 'source_url',
        ],
        'source_identifier' => [
            'role'    => 'identity',
            'storage' => 'column',
            'column'  => 'source_identifier',
        ],
        'source_notes' => [
            'role'    => 'writable',
            'storage' => 'column',
            'column'  => 'source_notes',
        ],
        'retrieved_at' => [
            'role'    => 'writable',
            'storage' => 'column',
            'column'  => 'retrieved_at',
        ],
        'verified_at' => [
            'role'    => 'writable',
            'storage' => 'column',
            'column'  => 'verified_at',
        ],
        'verification_result' => [
            'role'    => 'writable',
            'storage' => 'column',
            'column'  => 'verification_result',
        ],
    ];
}

/**
 * Return projection fields matching one or more roles.
 *
 * @param array $schema Projection schema.
 * @param array $roles  Accepted roles.
 *
 * @return array
 */
function nwmd_directory_get_ai_state_import_projection_fields(
    array $schema,
    array $roles
) {

    $fields = [];

    foreach ($schema as $field => $definition) {
        if (in_array($definition['role'] ?? '', $roles, true)) {
            $fields[] = $field;
        }
    }

    return $fields;
}

/**
 * Return a stable projection fingerprint.
 *
 * @param array $projection Canonical projection.
 *
 * @return string
 */
function nwmd_directory_get_ai_state_import_fingerprint(
    array $projection
) {

    $encoded = wp_json_encode(
        $projection,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );

    return is_string($encoded)
        ? hash('sha256', $encoded)
        : '';
}

/**
 * Return whether a full MySQL datetime is valid.
 *
 * @param mixed $value       Raw datetime.
 * @param bool  $allow_empty Whether an empty value is valid.
 *
 * @return bool
 */
function nwmd_directory_is_valid_ai_state_import_datetime(
    $value,
    $allow_empty = true
) {

    $value = trim((string) $value);

    if ('' === $value) {
        return (bool) $allow_empty;
    }

    $date = DateTimeImmutable::createFromFormat(
        '!Y-m-d H:i:s',
        $value,
        wp_timezone()
    );
    $errors = DateTimeImmutable::getLastErrors();

    return $date instanceof DateTimeImmutable
        && $date->format('Y-m-d H:i:s') === $value
        && (
            !is_array($errors)
            || (
                0 === $errors['warning_count']
                && 0 === $errors['error_count']
            )
        );
}

/**
 * Normalize one pipe-delimited taxonomy slug list.
 *
 * @param mixed $value Raw slug list.
 *
 * @return string|WP_Error
 */
function nwmd_directory_normalize_ai_state_import_slug_list(
    $value
) {

    $value = trim((string) $value);

    if ('' === $value) {
        return '';
    }

    $slugs = [];

    foreach (array_map('trim', explode('|', $value)) as $slug) {
        if (
            '' === $slug
            || !nwmd_directory_is_valid_business_csv_slug($slug)
            || sanitize_title($slug) !== $slug
            || isset($slugs[$slug])
        ) {
            return new WP_Error(
                'nwmd_ai_state_import_taxonomy_slug_invalid',
                __(
                    'A Business contains an invalid or duplicate taxonomy slug.',
                    'local-directory-framework'
                )
            );
        }

        $slugs[$slug] = true;
    }

    $slugs = array_keys($slugs);
    sort($slugs, SORT_STRING);

    return implode('|', $slugs);
}

/**
 * Return a bounded sanitized text value using the source helper.
 *
 * @param mixed $value  Raw value.
 * @param int   $length Maximum characters.
 *
 * @return string
 */
function nwmd_directory_normalize_ai_state_import_text(
    $value,
    $length
) {

    return nwmd_directory_limit_business_source_text(
        sanitize_text_field((string) $value),
        absint($length)
    );
}

/**
 * Return a canonical Business write projection.
 *
 * Uploaded creation and update timestamps are intentionally excluded.
 *
 * @param array $row Normalized export row.
 *
 * @return array|WP_Error
 */
function nwmd_directory_project_ai_state_import_business(
    array $row
) {

    $slug = trim((string) ($row['business_slug'] ?? ''));

    if (
        !nwmd_directory_is_valid_business_csv_slug($slug)
        || sanitize_title($slug) !== $slug
    ) {
        return new WP_Error(
            'nwmd_ai_state_import_business_slug_invalid',
            __(
                'A Business has an invalid portable slug.',
                'local-directory-framework'
            )
        );
    }

    $name = sanitize_text_field(
        (string) ($row['business_name'] ?? '')
    );

    if ('' === $name) {
        return new WP_Error(
            'nwmd_ai_state_import_business_name_missing',
            __(
                'A Business name is required.',
                'local-directory-framework'
            )
        );
    }

    $taxonomy = [];

    foreach (
        [
            'state_slugs',
            'city_slugs',
            'category_slugs',
            'specialty_slugs',
        ] as $field
    ) {
        $taxonomy[$field] =
            nwmd_directory_normalize_ai_state_import_slug_list(
                $row[$field] ?? ''
            );

        if (is_wp_error($taxonomy[$field])) {
            return $taxonomy[$field];
        }
    }

    $website_raw = trim((string) ($row['website_url'] ?? ''));

    if (!nwmd_directory_is_valid_business_csv_url($website_raw)) {
        return new WP_Error(
            'nwmd_ai_state_import_business_url_invalid',
            __(
                'A Business website URL is invalid or not public.',
                'local-directory-framework'
            )
        );
    }

    $website = esc_url_raw($website_raw, ['http', 'https']);
    $email_raw = trim((string) ($row['public_email'] ?? ''));
    $email = sanitize_email($email_raw);

    if (
        ('' !== $website_raw && $website !== $website_raw)
        || (
            '' !== $email_raw
            && (
                false === is_email($email_raw)
                || $email !== $email_raw
            )
        )
    ) {
        return new WP_Error(
            'nwmd_ai_state_import_business_contact_invalid',
            __(
                'A Business contains a noncanonical URL or email address.',
                'local-directory-framework'
            )
        );
    }

    $latitude_raw = trim((string) ($row['latitude'] ?? ''));
    $longitude_raw = trim((string) ($row['longitude'] ?? ''));
    $latitude = nwmd_directory_sanitize_coordinate(
        $latitude_raw,
        -90,
        90
    );
    $longitude = nwmd_directory_sanitize_coordinate(
        $longitude_raw,
        -180,
        180
    );

    if (
        ('' !== $latitude_raw && '' === $latitude)
        || ('' !== $longitude_raw && '' === $longitude)
    ) {
        return new WP_Error(
            'nwmd_ai_state_import_business_coordinate_invalid',
            __(
                'A Business coordinate is outside its accepted range.',
                'local-directory-framework'
            )
        );
    }

    $statuses = [];

    foreach (
        [
            'license_status',
            'verification_status',
            'claimed_status',
        ] as $field
    ) {
        $value = sanitize_key((string) ($row[$field] ?? ''));

        if (
            !isset(
                nwmd_directory_get_business_record_choices($field)[
                    $value
                ]
            )
        ) {
            return new WP_Error(
                'nwmd_ai_state_import_business_status_invalid',
                __(
                    'A Business contains an unsupported status.',
                    'local-directory-framework'
                )
            );
        }

        $statuses[$field] = $value;
    }

    $ranking = trim((string) ($row['ranking_eligible'] ?? ''));
    $verified = trim((string) ($row['last_verified_at'] ?? ''));

    if (
        !in_array($ranking, ['0', '1'], true)
        || !nwmd_directory_is_valid_ai_state_import_datetime(
            $verified,
            true
        )
    ) {
        return new WP_Error(
            'nwmd_ai_state_import_business_state_invalid',
            __(
                'A Business contains invalid ranking or verification data.',
                'local-directory-framework'
            )
        );
    }

    $public_name =
        nwmd_directory_normalize_ai_state_import_text(
            $row['public_name'] ?? '',
            255
        );

    if ('' === $public_name) {
        $public_name = $name;
    }

    return [
        'business_name'       => $name,
        'business_slug'       => $slug,
        'post_status'         => sanitize_key(
            (string) ($row['post_status'] ?? '')
        ),
        'state_slugs'         => $taxonomy['state_slugs'],
        'city_slugs'          => $taxonomy['city_slugs'],
        'category_slugs'      => $taxonomy['category_slugs'],
        'specialty_slugs'     => $taxonomy['specialty_slugs'],
        'description'         => wp_kses_post(
            (string) ($row['description'] ?? '')
        ),
        'excerpt'             => sanitize_textarea_field(
            (string) ($row['excerpt'] ?? '')
        ),
        'public_name'         => $public_name,
        'legal_name'          =>
            nwmd_directory_normalize_ai_state_import_text(
                $row['legal_name'] ?? '',
                255
            ),
        'website_url'         => $website,
        'public_email'        => $email,
        'public_phone'        =>
            nwmd_directory_normalize_ai_state_import_text(
                $row['public_phone'] ?? '',
                100
            ),
        'street_address'      =>
            nwmd_directory_normalize_ai_state_import_text(
                $row['street_address'] ?? '',
                255
            ),
        'postal_code'         =>
            nwmd_directory_normalize_ai_state_import_text(
                $row['postal_code'] ?? '',
                20
            ),
        'latitude'            => $latitude,
        'longitude'           => $longitude,
        'registration_number' =>
            nwmd_directory_normalize_ai_state_import_text(
                $row['registration_number'] ?? '',
                100
            ),
        'license_number'      =>
            nwmd_directory_normalize_ai_state_import_text(
                $row['license_number'] ?? '',
                100
            ),
        'license_status'      => $statuses['license_status'],
        'verification_status' => $statuses['verification_status'],
        'claimed_status'      => $statuses['claimed_status'],
        'ranking_eligible'    => $ranking,
        'last_verified_at'    => $verified,
    ];
}

/**
 * Return a canonical Business Source write projection.
 *
 * Uploaded creation and update timestamps are intentionally excluded.
 *
 * @param array $row Normalized export row.
 *
 * @return array|WP_Error
 */
function nwmd_directory_project_ai_state_import_source(
    array $row
) {

    $business_slug = trim(
        (string) ($row['business_slug'] ?? '')
    );
    $source_type_raw = trim(
        (string) ($row['source_type'] ?? '')
    );
    $source_type = sanitize_key($source_type_raw);

    if (
        !nwmd_directory_is_valid_business_csv_slug($business_slug)
        || sanitize_title($business_slug) !== $business_slug
        || $source_type !== $source_type_raw
        || !isset(
            nwmd_directory_get_business_source_type_choices()[
                $source_type
            ]
        )
    ) {
        return new WP_Error(
            'nwmd_ai_state_import_source_identity_invalid',
            __(
                'A Business Source contains an invalid Business or source type.',
                'local-directory-framework'
            )
        );
    }

    $source_name = nwmd_directory_normalize_ai_state_import_text(
        $row['source_name'] ?? '',
        255
    );
    $url_raw = trim((string) ($row['source_url'] ?? ''));
    $url = esc_url_raw($url_raw, ['http', 'https']);
    $identifier_raw = trim(
        (string) ($row['source_identifier'] ?? '')
    );
    $identifier = nwmd_directory_normalize_ai_state_import_text(
        $identifier_raw,
        255
    );

    if (
        '' === $source_name
        || !nwmd_directory_is_valid_business_csv_url($url_raw)
        || ('' !== $url_raw && $url !== $url_raw)
        || $identifier !== $identifier_raw
        || ('' === $url && '' === $identifier)
    ) {
        return new WP_Error(
            'nwmd_ai_state_import_source_values_invalid',
            __(
                'A Business Source contains invalid identifying values.',
                'local-directory-framework'
            )
        );
    }

    $result = sanitize_key(
        (string) ($row['verification_result'] ?? '')
    );
    $retrieved = trim((string) ($row['retrieved_at'] ?? ''));
    $verified = trim((string) ($row['verified_at'] ?? ''));

    if (
        !isset(
            nwmd_directory_get_business_source_result_choices()[
                $result
            ]
        )
        || !nwmd_directory_is_valid_ai_state_import_datetime(
            $retrieved,
            false
        )
        || !nwmd_directory_is_valid_ai_state_import_datetime(
            $verified,
            true
        )
        || ('pending' !== $result && '' === $verified)
    ) {
        return new WP_Error(
            'nwmd_ai_state_import_source_verification_invalid',
            __(
                'A Business Source contains invalid verification data.',
                'local-directory-framework'
            )
        );
    }

    return [
        'business_slug'       => $business_slug,
        'source_type'         => $source_type,
        'source_name'         => $source_name,
        'source_url'          => $url,
        'source_identifier'   => $identifier,
        'source_notes'        => sanitize_textarea_field(
            (string) ($row['source_notes'] ?? '')
        ),
        'retrieved_at'        => $retrieved,
        'verified_at'         => $verified,
        'verification_result' => $result,
    ];
}

/**
 * Return projection changes for selected roles.
 *
 * @param array $target  Target projection.
 * @param array $current Current projection.
 * @param array $schema  Projection schema.
 * @param array $roles   Compared roles.
 *
 * @return array
 */
function nwmd_directory_get_ai_state_import_projection_changes(
    array $target,
    array $current,
    array $schema,
    array $roles
) {

    $changes = [];
    $fields = nwmd_directory_get_ai_state_import_projection_fields(
        $schema,
        $roles
    );

    foreach ($fields as $field) {
        $before = (string) ($current[$field] ?? '');
        $after  = (string) ($target[$field] ?? '');

        if ($before !== $after) {
            $changes[$field] = [
                'from' => $before,
                'to'   => $after,
            ];
        }
    }

    return $changes;
}
