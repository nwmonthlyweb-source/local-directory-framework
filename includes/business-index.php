<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return one business meta value.
 *
 * @param int    $post_id Business post ID.
 * @param string $key     Meta key without the nwmd_ prefix.
 *
 * @return mixed
 */
function nwmd_directory_get_business_record_meta($post_id, $key) {

    return get_post_meta(
        $post_id,
        'nwmd_' . $key,
        true
    );
}

/**
 * Return the first assigned term ID for a business taxonomy.
 *
 * @param int    $post_id  Business post ID.
 * @param string $taxonomy Taxonomy name.
 *
 * @return int
 */
function nwmd_directory_get_business_term_id(
    $post_id,
    $taxonomy
) {

    $term_ids = wp_get_object_terms(
        $post_id,
        $taxonomy,
        [
            'fields' => 'ids',
        ]
    );

    if (
        is_wp_error($term_ids) ||
        empty($term_ids)
    ) {
        return 0;
    }

    return absint($term_ids[0]);
}

/**
 * Return a nullable decimal value.
 *
 * @param mixed $value Raw value.
 *
 * @return float|null
 */
function nwmd_directory_get_nullable_decimal($value) {

    if (
        '' === $value ||
        null === $value ||
        !is_numeric($value)
    ) {
        return null;
    }

    return (float) $value;
}

/**
 * Synchronize one Business post into the business index table.
 *
 * @param int $post_id Business post ID.
 *
 * @return bool
 */
function nwmd_directory_sync_business_index($post_id) {

    $post_id = absint($post_id);

    if ($post_id < 1) {
        return false;
    }

    $post = get_post($post_id);

    if (
        !$post instanceof WP_Post ||
        'nwmd_business' !== $post->post_type ||
        'auto-draft' === $post->post_status
    ) {
        return false;
    }

    global $wpdb;

    $table = $wpdb->prefix
        . 'nwmd_business_index';

    $existing = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT
                id,
                created_at,
                archived_at
            FROM {$table}
            WHERE business_post_id = %d
            LIMIT 1",
            $post_id
        )
    );

    $current_time = current_time('mysql');

    $public_name = sanitize_text_field(
        nwmd_directory_get_business_record_meta(
            $post_id,
            'public_name'
        )
    );

    if ('' === $public_name) {
        $public_name = sanitize_text_field(
            get_the_title($post_id)
        );
    }

    $legal_name = sanitize_text_field(
        nwmd_directory_get_business_record_meta(
            $post_id,
            'legal_name'
        )
    );

    $website_url = esc_url_raw(
        nwmd_directory_get_business_record_meta(
            $post_id,
            'website_url'
        )
    );

    $public_email = sanitize_email(
        nwmd_directory_get_business_record_meta(
            $post_id,
            'public_email'
        )
    );

    $public_phone = sanitize_text_field(
        nwmd_directory_get_business_record_meta(
            $post_id,
            'public_phone'
        )
    );

    $street_address = sanitize_text_field(
        nwmd_directory_get_business_record_meta(
            $post_id,
            'street_address'
        )
    );

    $postal_code = sanitize_text_field(
        nwmd_directory_get_business_record_meta(
            $post_id,
            'postal_code'
        )
    );

    $registration_number = sanitize_text_field(
        nwmd_directory_get_business_record_meta(
            $post_id,
            'registration_number'
        )
    );

    $license_number = sanitize_text_field(
        nwmd_directory_get_business_record_meta(
            $post_id,
            'license_number'
        )
    );

    $license_status = sanitize_key(
        nwmd_directory_get_business_record_meta(
            $post_id,
            'license_status'
        )
    );

    if ('' === $license_status) {
        $license_status = 'unknown';
    }

    $verification_status = sanitize_key(
        nwmd_directory_get_business_record_meta(
            $post_id,
            'verification_status'
        )
    );

    if ('' === $verification_status) {
        $verification_status = 'unverified';
    }

    $claimed_status = sanitize_key(
        nwmd_directory_get_business_record_meta(
            $post_id,
            'claimed_status'
        )
    );

    if ('' === $claimed_status) {
        $claimed_status = 'unclaimed';
    }

    $ranking_eligible = rest_sanitize_boolean(
        nwmd_directory_get_business_record_meta(
            $post_id,
            'ranking_eligible'
        )
    )
        ? 1
        : 0;

    $last_verified_at = sanitize_text_field(
        nwmd_directory_get_business_record_meta(
            $post_id,
            'last_verified_at'
        )
    );

    if (
        '' !== $last_verified_at &&
        !preg_match(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
            $last_verified_at
        )
    ) {
        $last_verified_at = '';
    }

    $archived_at = null;

    if ('trash' === $post->post_status) {
        $archived_at = (
            is_object($existing) &&
            !empty($existing->archived_at)
        )
            ? $existing->archived_at
            : $current_time;
    }

    $data = [
        'business_post_id'    => $post_id,
        'legal_name'          => $legal_name,
        'public_name'         => $public_name,
        'website_url'         => $website_url,
        'public_email'        => $public_email,
        'public_phone'        => $public_phone,
        'street_address'      => $street_address,
        'city_term_id'        => nwmd_directory_get_business_term_id(
            $post_id,
            'nwmd_city'
        ),
        'state_term_id'       => nwmd_directory_get_business_term_id(
            $post_id,
            'nwmd_state'
        ),
        'postal_code'         => $postal_code,
        'latitude'            => nwmd_directory_get_nullable_decimal(
            nwmd_directory_get_business_record_meta(
                $post_id,
                'latitude'
            )
        ),
        'longitude'           => nwmd_directory_get_nullable_decimal(
            nwmd_directory_get_business_record_meta(
                $post_id,
                'longitude'
            )
        ),
        'registration_number' => $registration_number,
        'license_number'      => $license_number,
        'license_status'      => $license_status,
        'verification_status' => $verification_status,
        'claimed_status'      => $claimed_status,
        'ranking_eligible'    => $ranking_eligible,
        'last_verified_at'    => '' !== $last_verified_at
            ? $last_verified_at
            : null,
        'updated_at'          => $current_time,
        'archived_at'         => $archived_at,
    ];

    $formats = [
        '%d',
        '%s',
        '%s',
        '%s',
        '%s',
        '%s',
        '%s',
        '%d',
        '%d',
        '%s',
        '%f',
        '%f',
        '%s',
        '%s',
        '%s',
        '%s',
        '%s',
        '%d',
        '%s',
        '%s',
        '%s',
    ];

    if (is_object($existing)) {
        $updated = $wpdb->update(
            $table,
            $data,
            [
                'business_post_id' => $post_id,
            ],
            $formats,
            [
                '%d',
            ]
        );

        return false !== $updated;
    }

    $data['created_at'] = $current_time;
    $formats[] = '%s';

    $inserted = $wpdb->insert(
        $table,
        $data,
        $formats
    );

    return false !== $inserted;
}

/**
 * Synchronize a Business post after it is saved.
 *
 * @param int $post_id Business post ID.
 */
function nwmd_directory_sync_business_index_on_save($post_id) {

    if (
        wp_is_post_revision($post_id) ||
        wp_is_post_autosave($post_id)
    ) {
        return;
    }

    nwmd_directory_sync_business_index($post_id);
}

add_action(
    'save_post_nwmd_business',
    'nwmd_directory_sync_business_index_on_save',
    30
);

/**
 * Synchronize the business index after taxonomy assignments change.
 *
 * @param int    $object_id Object ID.
 * @param array  $terms     Assigned terms.
 * @param array  $tt_ids    Term-taxonomy IDs.
 * @param string $taxonomy  Taxonomy name.
 */
function nwmd_directory_sync_business_index_on_terms(
    $object_id,
    $terms,
    $tt_ids,
    $taxonomy
) {

    unset($terms, $tt_ids);

    if (
        !in_array(
            $taxonomy,
            [
                'nwmd_city',
                'nwmd_state',
            ],
            true
        ) ||
        'nwmd_business' !== get_post_type($object_id)
    ) {
        return;
    }

    nwmd_directory_sync_business_index($object_id);
}

add_action(
    'set_object_terms',
    'nwmd_directory_sync_business_index_on_terms',
    30,
    4
);

/**
 * Synchronize every existing Business post.
 */
function nwmd_directory_sync_all_business_index_records() {

    $business_ids = get_posts(
        [
            'post_type'      => 'nwmd_business',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'orderby'        => 'ID',
            'order'          => 'ASC',
        ]
    );

    foreach ($business_ids as $business_id) {
        nwmd_directory_sync_business_index(
            $business_id
        );
    }
}