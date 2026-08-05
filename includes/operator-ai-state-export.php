<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return the portable Business fields for an AI state export.
 *
 * Research sources are exported separately because one Business can
 * have multiple source records.
 *
 * @return array
 */
function nwmd_directory_get_ai_state_business_columns() {

    return [
        'business_name',
        'business_slug',
        'post_status',
        'state_slugs',
        'city_slugs',
        'category_slugs',
        'specialty_slugs',
        'description',
        'excerpt',
        'public_name',
        'legal_name',
        'website_url',
        'public_email',
        'public_phone',
        'street_address',
        'postal_code',
        'latitude',
        'longitude',
        'registration_number',
        'license_number',
        'license_status',
        'verification_status',
        'claimed_status',
        'ranking_eligible',
        'last_verified_at',
        'created_at',
        'updated_at',
    ];
}

/**
 * Return the portable Business source fields.
 *
 * @return array
 */
function nwmd_directory_get_ai_state_source_columns() {

    return [
        'business_slug',
        'source_type',
        'source_name',
        'source_url',
        'source_identifier',
        'source_notes',
        'retrieved_at',
        'verified_at',
        'verification_result',
        'created_at',
        'updated_at',
    ];
}

/**
 * Return the portable Business Deal fields.
 *
 * @return array
 */
function nwmd_directory_get_ai_state_deal_columns() {

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
        'created_at',
        'updated_at',
        'archived_at',
    ];
}

/**
 * Return the portable queue checkpoint fields.
 *
 * Numeric WordPress term IDs are intentionally excluded.
 *
 * @return array
 */
function nwmd_directory_get_ai_state_queue_columns() {

    return [
        'job_key',
        'state_slug',
        'city_slug',
        'category_slug',
        'specialty_slug',
        'job_status',
        'checkpoint_status',
        'sort_order',
        'businesses_created',
        'businesses_updated',
        'businesses_without_deals',
        'deals_created',
        'deals_updated',
        'exclusions',
        'last_error',
        'started_at',
        'completed_at',
        'created_at',
        'updated_at',
    ];
}

/**
 * Return the portable Operator run fields.
 *
 * @return array
 */
function nwmd_directory_get_ai_state_run_columns() {

    return [
        'run_uuid',
        'job_key',
        'state_slug',
        'city_slug',
        'category_slug',
        'specialty_slug',
        'status',
        'businesses_created',
        'businesses_updated',
        'businesses_without_deals',
        'deals_created',
        'deals_updated',
        'exclusions',
        'result_summary',
        'error_message',
        'started_at',
        'completed_at',
        'created_at',
        'updated_at',
    ];
}

/**
 * Return the portable taxonomy term fields.
 *
 * @return array
 */
function nwmd_directory_get_ai_state_taxonomy_columns() {

    return [
        'taxonomy',
        'term_slug',
        'term_name',
        'parent_taxonomy',
        'parent_slug',
        'description',
    ];
}

/**
 * Return the provisional AI state record schemas.
 *
 * This defines record structure only. It does not query the database,
 * create files, register hooks, or produce a download.
 *
 * @return array
 */
function nwmd_directory_get_ai_state_export_schema() {

    return [
        'format_version'      => '3.1',
        'taxonomy_separator'  => '|',
        'records'             => [
            'businesses' => [
                'filename' => 'businesses.csv',
                'columns' =>
                    nwmd_directory_get_ai_state_business_columns(),
            ],
            'sources' => [
                'filename' => 'business-sources.csv',
                'columns' =>
                    nwmd_directory_get_ai_state_source_columns(),
            ],
            'deals' => [
                'filename' => 'business-deals.csv',
                'columns' =>
                    nwmd_directory_get_ai_state_deal_columns(),
            ],
            'queue' => [
                'filename' => 'research-queue.csv',
                'columns' =>
                    nwmd_directory_get_ai_state_queue_columns(),
            ],
            'runs' => [
                'filename' => 'operator-runs.csv',
                'columns' =>
                    nwmd_directory_get_ai_state_run_columns(),
            ],
            'taxonomy_terms' => [
                'filename' => 'taxonomy-terms.csv',
                'columns' =>
                    nwmd_directory_get_ai_state_taxonomy_columns(),
            ],
        ],
    ];
}

/**
 * Return one Business metadata value.
 *
 * @param int    $post_id Business post ID.
 * @param string $key     Metadata key without the nwmd_ prefix.
 *
 * @return mixed
 */
function nwmd_directory_get_ai_state_business_meta(
    $post_id,
    $key
) {

    return get_post_meta(
        absint($post_id),
        'nwmd_' . sanitize_key($key),
        true
    );
}

/**
 * Return every assigned taxonomy slug as a stable list.
 *
 * Slugs are sorted and separated with the export schema separator.
 *
 * @param int    $post_id  Business post ID.
 * @param string $taxonomy Business taxonomy.
 *
 * @return string|WP_Error
 */
function nwmd_directory_get_ai_state_business_term_slugs(
    $post_id,
    $taxonomy
) {

    $allowed_taxonomies = [
        'nwmd_state',
        'nwmd_city',
        'nwmd_category',
        'nwmd_specialty',
    ];

    $taxonomy = sanitize_key($taxonomy);

    if (!in_array($taxonomy, $allowed_taxonomies, true)) {
        return new WP_Error(
            'nwmd_ai_state_invalid_taxonomy',
            __(
                'The AI state export received an unsupported taxonomy.',
                'local-directory-framework'
            )
        );
    }

    $terms = wp_get_object_terms(
        absint($post_id),
        $taxonomy,
        [
            'fields' => 'all',
        ]
    );

    if (is_wp_error($terms)) {
        return $terms;
    }

    $slugs = [];

    foreach ($terms as $term) {
        if (
            $term instanceof WP_Term
            && '' !== (string) $term->slug
        ) {
            $slugs[] = sanitize_title(
                (string) $term->slug
            );
        }
    }

    $slugs = array_values(
        array_unique(
            array_filter($slugs)
        )
    );

    sort($slugs, SORT_STRING);

    return implode('|', $slugs);
}

/**
 * Return a portable coordinate value.
 *
 * @param mixed $value Raw coordinate value.
 *
 * @return string
 */
function nwmd_directory_get_ai_state_coordinate($value) {

    $value = trim((string) $value);

    return is_numeric($value)
        ? $value
        : '';
}

/**
 * Return all portable Business export rows.
 *
 * This is read-only and does not modify posts, metadata, taxonomy
 * assignments, or the synchronized Business index.
 *
 * @return array|WP_Error
 */
function nwmd_directory_get_ai_state_business_rows() {

    $posts = get_posts(
        [
            'post_type'      => 'nwmd_business',
            'post_status'    => [
                'publish',
                'future',
                'draft',
                'pending',
                'private',
                'trash',
            ],
            'posts_per_page' => -1,
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'no_found_rows'  => true,
        ]
    );

    if (!is_array($posts)) {
        return new WP_Error(
            'nwmd_ai_state_business_query_failed',
            __(
                'Business records could not be read for the AI state export.',
                'local-directory-framework'
            )
        );
    }

    $taxonomy_columns = [
        'state_slugs'     => 'nwmd_state',
        'city_slugs'      => 'nwmd_city',
        'category_slugs'  => 'nwmd_category',
        'specialty_slugs' => 'nwmd_specialty',
    ];

    $rows = [];

    foreach ($posts as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }

        $taxonomy_values = [];

        foreach ($taxonomy_columns as $column => $taxonomy) {
            $value =
                nwmd_directory_get_ai_state_business_term_slugs(
                    $post->ID,
                    $taxonomy
                );

            if (is_wp_error($value)) {
                return new WP_Error(
                    'nwmd_ai_state_business_terms_failed',
                    sprintf(
                        __(
                            'Taxonomy assignments could not be read for Business "%s".',
                            'local-directory-framework'
                        ),
                        (string) $post->post_name
                    ),
                    [
                        'business_post_id' => absint($post->ID),
                        'taxonomy'         => $taxonomy,
                    ]
                );
            }

            $taxonomy_values[$column] = $value;
        }

        $public_name = sanitize_text_field(
            nwmd_directory_get_ai_state_business_meta(
                $post->ID,
                'public_name'
            )
        );

        if ('' === $public_name) {
            $public_name = sanitize_text_field(
                (string) $post->post_title
            );
        }

        $license_status = sanitize_key(
            nwmd_directory_get_ai_state_business_meta(
                $post->ID,
                'license_status'
            )
        );

        if ('' === $license_status) {
            $license_status = 'unknown';
        }

        $verification_status = sanitize_key(
            nwmd_directory_get_ai_state_business_meta(
                $post->ID,
                'verification_status'
            )
        );

        if ('' === $verification_status) {
            $verification_status = 'unverified';
        }

        $claimed_status = sanitize_key(
            nwmd_directory_get_ai_state_business_meta(
                $post->ID,
                'claimed_status'
            )
        );

        if ('' === $claimed_status) {
            $claimed_status = 'unclaimed';
        }

        $last_verified_at = trim(
            (string)
            nwmd_directory_get_ai_state_business_meta(
                $post->ID,
                'last_verified_at'
            )
        );

        if (
            '' !== $last_verified_at
            && !preg_match(
                '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
                $last_verified_at
            )
        ) {
            $last_verified_at = '';
        }

        $rows[] = [
            'business_name'      =>
                sanitize_text_field($post->post_title),
            'business_slug'      =>
                sanitize_title($post->post_name),
            'post_status'        =>
                sanitize_key($post->post_status),
            'state_slugs'        =>
                $taxonomy_values['state_slugs'],
            'city_slugs'         =>
                $taxonomy_values['city_slugs'],
            'category_slugs'     =>
                $taxonomy_values['category_slugs'],
            'specialty_slugs'    =>
                $taxonomy_values['specialty_slugs'],
            'description'        =>
                (string) $post->post_content,
            'excerpt'            =>
                (string) $post->post_excerpt,
            'public_name'        =>
                $public_name,
            'legal_name'         =>
                sanitize_text_field(
                    nwmd_directory_get_ai_state_business_meta(
                        $post->ID,
                        'legal_name'
                    )
                ),
            'website_url'        =>
                esc_url_raw(
                    nwmd_directory_get_ai_state_business_meta(
                        $post->ID,
                        'website_url'
                    )
                ),
            'public_email'       =>
                sanitize_email(
                    nwmd_directory_get_ai_state_business_meta(
                        $post->ID,
                        'public_email'
                    )
                ),
            'public_phone'       =>
                sanitize_text_field(
                    nwmd_directory_get_ai_state_business_meta(
                        $post->ID,
                        'public_phone'
                    )
                ),
            'street_address'     =>
                sanitize_text_field(
                    nwmd_directory_get_ai_state_business_meta(
                        $post->ID,
                        'street_address'
                    )
                ),
            'postal_code'        =>
                sanitize_text_field(
                    nwmd_directory_get_ai_state_business_meta(
                        $post->ID,
                        'postal_code'
                    )
                ),
            'latitude'           =>
                nwmd_directory_get_ai_state_coordinate(
                    nwmd_directory_get_ai_state_business_meta(
                        $post->ID,
                        'latitude'
                    )
                ),
            'longitude'          =>
                nwmd_directory_get_ai_state_coordinate(
                    nwmd_directory_get_ai_state_business_meta(
                        $post->ID,
                        'longitude'
                    )
                ),
            'registration_number' =>
                sanitize_text_field(
                    nwmd_directory_get_ai_state_business_meta(
                        $post->ID,
                        'registration_number'
                    )
                ),
            'license_number'     =>
                sanitize_text_field(
                    nwmd_directory_get_ai_state_business_meta(
                        $post->ID,
                        'license_number'
                    )
                ),
            'license_status'     =>
                $license_status,
            'verification_status' =>
                $verification_status,
            'claimed_status'     =>
                $claimed_status,
            'ranking_eligible'   =>
                rest_sanitize_boolean(
                    nwmd_directory_get_ai_state_business_meta(
                        $post->ID,
                        'ranking_eligible'
                    )
                )
                    ? 1
                    : 0,
            'last_verified_at'   =>
                $last_verified_at,
            'created_at'         =>
                (string) $post->post_date,
            'updated_at'         =>
                (string) $post->post_modified,
        ];
    }

    return $rows;
}

/**
 * Return all portable Business source export rows.
 *
 * Sources are joined to Business posts so numeric post IDs are replaced
 * with stable Business slugs.
 *
 * @return array|WP_Error
 */
function nwmd_directory_get_ai_state_source_rows() {

    global $wpdb;

    $sources_table = $wpdb->prefix
        . 'nwmd_business_sources';
    $posts_table = $wpdb->posts;

    $rows = $wpdb->get_results(
        "SELECT
            posts.post_name AS business_slug,
            sources.source_type,
            sources.source_name,
            sources.source_url,
            sources.source_identifier,
            sources.source_notes,
            sources.retrieved_at,
            sources.verified_at,
            sources.verification_result,
            sources.created_at,
            sources.updated_at
        FROM {$sources_table} AS sources
        INNER JOIN {$posts_table} AS posts
            ON posts.ID = sources.business_post_id
        WHERE posts.post_type = 'nwmd_business'
        ORDER BY
            posts.post_name ASC,
            sources.id ASC",
        ARRAY_A
    );

    if (null === $rows || '' !== (string) $wpdb->last_error) {
        return new WP_Error(
            'nwmd_ai_state_source_query_failed',
            __(
                'Business source records could not be read for the AI state export.',
                'local-directory-framework'
            )
        );
    }

    $export_rows = [];

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $business_slug = sanitize_title(
            (string) ($row['business_slug'] ?? '')
        );

        if ('' === $business_slug) {
            return new WP_Error(
                'nwmd_ai_state_source_business_missing',
                __(
                    'A Business source record does not have a valid Business slug.',
                    'local-directory-framework'
                )
            );
        }

        $export_rows[] = [
            'business_slug'      => $business_slug,
            'source_type'        =>
                sanitize_key(
                    (string) ($row['source_type'] ?? '')
                ),
            'source_name'        =>
                sanitize_text_field(
                    (string) ($row['source_name'] ?? '')
                ),
            'source_url'         =>
                esc_url_raw(
                    (string) ($row['source_url'] ?? '')
                ),
            'source_identifier'  =>
                sanitize_text_field(
                    (string) ($row['source_identifier'] ?? '')
                ),
            'source_notes'       =>
                (string) ($row['source_notes'] ?? ''),
            'retrieved_at'       =>
                (string) ($row['retrieved_at'] ?? ''),
            'verified_at'        =>
                (string) ($row['verified_at'] ?? ''),
            'verification_result' =>
                sanitize_key(
                    (string) ($row['verification_result'] ?? '')
                ),
            'created_at'         =>
                (string) ($row['created_at'] ?? ''),
            'updated_at'         =>
                (string) ($row['updated_at'] ?? ''),
        ];
    }

    return $export_rows;
}

/**
 * Return all portable Business Deal export rows.
 *
 * Every Deal status is included. Numeric Business post IDs and user IDs
 * are intentionally excluded from the portable export.
 *
 * @return array|WP_Error
 */
function nwmd_directory_get_ai_state_deal_rows() {

    global $wpdb;

    $deals_table = $wpdb->prefix
        . 'nwmd_business_deals';
    $posts_table = $wpdb->posts;

    $rows = $wpdb->get_results(
        "SELECT
            posts.post_name AS business_slug,
            deals.deal_slug,
            deals.title,
            deals.card_text,
            deals.description,
            deals.promo_code,
            deals.source_url,
            deals.starts_at,
            deals.expires_at,
            deals.verified_at,
            deals.status,
            deals.is_featured,
            deals.created_at,
            deals.updated_at,
            deals.archived_at
        FROM {$deals_table} AS deals
        INNER JOIN {$posts_table} AS posts
            ON posts.ID = deals.business_post_id
        WHERE posts.post_type = 'nwmd_business'
        ORDER BY
            posts.post_name ASC,
            deals.deal_slug ASC,
            deals.id ASC",
        ARRAY_A
    );

    if (!is_array($rows)) {
        return new WP_Error(
            'nwmd_ai_state_deal_query_failed',
            __(
                'Business Deal records could not be read for the AI state export.',
                'local-directory-framework'
            )
        );
    }

    $export_rows = [];

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $business_slug = sanitize_title(
            (string) ($row['business_slug'] ?? '')
        );

        $deal_slug = sanitize_title(
            (string) ($row['deal_slug'] ?? '')
        );

        if ('' === $business_slug || '' === $deal_slug) {
            return new WP_Error(
                'nwmd_ai_state_deal_identity_missing',
                __(
                    'A Business Deal record does not have a valid portable identity.',
                    'local-directory-framework'
                )
            );
        }

        $export_rows[] = [
            'business_slug' => $business_slug,
            'deal_slug'     => $deal_slug,
            'title'         =>
                sanitize_text_field(
                    (string) ($row['title'] ?? '')
                ),
            'card_text'     =>
                sanitize_text_field(
                    (string) ($row['card_text'] ?? '')
                ),
            'description'   =>
                (string) ($row['description'] ?? ''),
            'promo_code'    =>
                sanitize_text_field(
                    (string) ($row['promo_code'] ?? '')
                ),
            'source_url'    =>
                esc_url_raw(
                    (string) ($row['source_url'] ?? '')
                ),
            'starts_at'     =>
                (string) ($row['starts_at'] ?? ''),
            'expires_at'    =>
                (string) ($row['expires_at'] ?? ''),
            'verified_at'   =>
                (string) ($row['verified_at'] ?? ''),
            'status'        =>
                sanitize_key(
                    (string) ($row['status'] ?? '')
                ),
            'is_featured'   =>
                !empty($row['is_featured'])
                    ? 1
                    : 0,
            'created_at'    =>
                (string) ($row['created_at'] ?? ''),
            'updated_at'    =>
                (string) ($row['updated_at'] ?? ''),
            'archived_at'   =>
                (string) ($row['archived_at'] ?? ''),
        ];
    }

    return $export_rows;
}

/**
 * Return all portable Operator queue checkpoint rows.
 *
 * Numeric job, checkpoint, and taxonomy IDs are replaced with stable
 * job keys and taxonomy slugs.
 *
 * @return array|WP_Error
 */
function nwmd_directory_get_ai_state_queue_rows() {

    global $wpdb;

    $tables = nwmd_directory_get_operator_table_names();
    $terms_table = $wpdb->terms;

    $rows = $wpdb->get_results(
        "SELECT
            jobs.job_key,
            state_terms.slug AS state_slug,
            city_terms.slug AS city_slug,
            category_terms.slug AS category_slug,
            specialty_terms.slug AS specialty_slug,
            jobs.status AS job_status,
            checkpoints.status AS checkpoint_status,
            checkpoints.sort_order,
            checkpoints.businesses_created,
            checkpoints.businesses_updated,
            checkpoints.businesses_without_deals,
            checkpoints.deals_created,
            checkpoints.deals_updated,
            checkpoints.exclusions,
            checkpoints.last_error,
            checkpoints.started_at,
            checkpoints.completed_at,
            checkpoints.created_at,
            checkpoints.updated_at
        FROM {$tables['specialties']} AS checkpoints
        INNER JOIN {$tables['jobs']} AS jobs
            ON jobs.id = checkpoints.job_id
        LEFT JOIN {$terms_table} AS state_terms
            ON state_terms.term_id = jobs.state_term_id
        LEFT JOIN {$terms_table} AS city_terms
            ON city_terms.term_id = jobs.city_term_id
        LEFT JOIN {$terms_table} AS category_terms
            ON category_terms.term_id = jobs.category_term_id
        LEFT JOIN {$terms_table} AS specialty_terms
            ON specialty_terms.term_id =
                checkpoints.specialty_term_id
        ORDER BY
            jobs.job_key ASC,
            checkpoints.sort_order ASC,
            specialty_terms.slug ASC,
            checkpoints.id ASC",
        ARRAY_A
    );

    if (!is_array($rows)) {
        return new WP_Error(
            'nwmd_ai_state_queue_query_failed',
            __(
                'Operator queue records could not be read for the AI state export.',
                'local-directory-framework'
            )
        );
    }

    $export_rows = [];

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $job_key = sanitize_text_field(
            (string) ($row['job_key'] ?? '')
        );

        $state_slug = sanitize_title(
            (string) ($row['state_slug'] ?? '')
        );

        $city_slug = sanitize_title(
            (string) ($row['city_slug'] ?? '')
        );

        $category_slug = sanitize_title(
            (string) ($row['category_slug'] ?? '')
        );

        $specialty_slug = sanitize_title(
            (string) ($row['specialty_slug'] ?? '')
        );

        if (
            '' === $job_key
            || '' === $state_slug
            || '' === $city_slug
            || '' === $category_slug
            || '' === $specialty_slug
        ) {
            return new WP_Error(
                'nwmd_ai_state_queue_identity_missing',
                __(
                    'An Operator checkpoint does not have a complete portable identity.',
                    'local-directory-framework'
                ),
                [
                    'job_key' => $job_key,
                ]
            );
        }

        $export_rows[] = [
            'job_key'                 => $job_key,
            'state_slug'              => $state_slug,
            'city_slug'               => $city_slug,
            'category_slug'           => $category_slug,
            'specialty_slug'          => $specialty_slug,
            'job_status'              =>
                sanitize_key(
                    (string) ($row['job_status'] ?? '')
                ),
            'checkpoint_status'       =>
                sanitize_key(
                    (string) ($row['checkpoint_status'] ?? '')
                ),
            'sort_order'              =>
                absint($row['sort_order'] ?? 0),
            'businesses_created'      =>
                absint($row['businesses_created'] ?? 0),
            'businesses_updated'      =>
                absint($row['businesses_updated'] ?? 0),
            'businesses_without_deals' =>
                absint(
                    $row['businesses_without_deals'] ?? 0
                ),
            'deals_created'           =>
                absint($row['deals_created'] ?? 0),
            'deals_updated'           =>
                absint($row['deals_updated'] ?? 0),
            'exclusions'              =>
                absint($row['exclusions'] ?? 0),
            'last_error'              =>
                (string) ($row['last_error'] ?? ''),
            'started_at'              =>
                (string) ($row['started_at'] ?? ''),
            'completed_at'            =>
                (string) ($row['completed_at'] ?? ''),
            'created_at'              =>
                (string) ($row['created_at'] ?? ''),
            'updated_at'              =>
                (string) ($row['updated_at'] ?? ''),
        ];
    }

    return $export_rows;
}

/**
 * Return all portable Operator run rows.
 *
 * Run UUIDs remain the stable identities. Numeric job, taxonomy, and
 * WordPress user IDs are intentionally excluded.
 *
 * @return array|WP_Error
 */
function nwmd_directory_get_ai_state_run_rows() {

    global $wpdb;

    $tables = nwmd_directory_get_operator_table_names();
    $terms_table = $wpdb->terms;

    $rows = $wpdb->get_results(
        "SELECT
            runs.run_uuid,
            jobs.job_key,
            state_terms.slug AS state_slug,
            city_terms.slug AS city_slug,
            category_terms.slug AS category_slug,
            specialty_terms.slug AS specialty_slug,
            runs.status,
            runs.businesses_created,
            runs.businesses_updated,
            runs.businesses_without_deals,
            runs.deals_created,
            runs.deals_updated,
            runs.exclusions,
            runs.result_summary,
            runs.error_message,
            runs.started_at,
            runs.completed_at,
            runs.created_at,
            runs.updated_at
        FROM {$tables['runs']} AS runs
        LEFT JOIN {$tables['jobs']} AS jobs
            ON jobs.id = runs.job_id
        LEFT JOIN {$terms_table} AS state_terms
            ON state_terms.term_id = jobs.state_term_id
        LEFT JOIN {$terms_table} AS city_terms
            ON city_terms.term_id = jobs.city_term_id
        LEFT JOIN {$terms_table} AS category_terms
            ON category_terms.term_id = jobs.category_term_id
        LEFT JOIN {$terms_table} AS specialty_terms
            ON specialty_terms.term_id =
                runs.specialty_term_id
        ORDER BY runs.id ASC",
        ARRAY_A
    );

    if (!is_array($rows)) {
        return new WP_Error(
            'nwmd_ai_state_run_query_failed',
            __(
                'Operator run records could not be read for the AI state export.',
                'local-directory-framework'
            )
        );
    }

    $export_rows = [];

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $run_uuid = sanitize_text_field(
            (string) ($row['run_uuid'] ?? '')
        );

        if ('' === $run_uuid) {
            return new WP_Error(
                'nwmd_ai_state_run_identity_missing',
                __(
                    'An Operator run does not have a valid run UUID.',
                    'local-directory-framework'
                )
            );
        }

        $export_rows[] = [
            'run_uuid'                => $run_uuid,
            'job_key'                 =>
                sanitize_text_field(
                    (string) ($row['job_key'] ?? '')
                ),
            'state_slug'              =>
                sanitize_title(
                    (string) ($row['state_slug'] ?? '')
                ),
            'city_slug'               =>
                sanitize_title(
                    (string) ($row['city_slug'] ?? '')
                ),
            'category_slug'           =>
                sanitize_title(
                    (string) ($row['category_slug'] ?? '')
                ),
            'specialty_slug'          =>
                sanitize_title(
                    (string) ($row['specialty_slug'] ?? '')
                ),
            'status'                  =>
                sanitize_key(
                    (string) ($row['status'] ?? '')
                ),
            'businesses_created'      =>
                absint($row['businesses_created'] ?? 0),
            'businesses_updated'      =>
                absint($row['businesses_updated'] ?? 0),
            'businesses_without_deals' =>
                absint(
                    $row['businesses_without_deals'] ?? 0
                ),
            'deals_created'           =>
                absint($row['deals_created'] ?? 0),
            'deals_updated'           =>
                absint($row['deals_updated'] ?? 0),
            'exclusions'              =>
                absint($row['exclusions'] ?? 0),
            'result_summary'          =>
                (string) ($row['result_summary'] ?? ''),
            'error_message'           =>
                (string) ($row['error_message'] ?? ''),
            'started_at'              =>
                (string) ($row['started_at'] ?? ''),
            'completed_at'            =>
                (string) ($row['completed_at'] ?? ''),
            'created_at'              =>
                (string) ($row['created_at'] ?? ''),
            'updated_at'              =>
                (string) ($row['updated_at'] ?? ''),
        ];
    }

    return $export_rows;
}

/**
 * Return all portable directory taxonomy term rows.
 *
 * Numeric term IDs and parent IDs are replaced with stable taxonomy
 * names and term slugs.
 *
 * @return array|WP_Error
 */
function nwmd_directory_get_ai_state_taxonomy_rows() {

    $taxonomies = [
        'nwmd_state',
        'nwmd_city',
        'nwmd_category',
        'nwmd_specialty',
    ];

    $export_rows = [];

    foreach ($taxonomies as $taxonomy) {
        $terms = get_terms(
            [
                'taxonomy'   => $taxonomy,
                'hide_empty' => false,
                'orderby'    => 'term_id',
                'order'      => 'ASC',
            ]
        );

        if (is_wp_error($terms)) {
            return new WP_Error(
                'nwmd_ai_state_taxonomy_query_failed',
                sprintf(
                    __(
                        'Taxonomy terms could not be read for "%s".',
                        'local-directory-framework'
                    ),
                    $taxonomy
                ),
                [
                    'taxonomy' => $taxonomy,
                ]
            );
        }

        $terms_by_id = [];

        foreach ($terms as $term) {
            if ($term instanceof WP_Term) {
                $terms_by_id[absint($term->term_id)] = $term;
            }
        }

        foreach ($terms as $term) {
            if (!$term instanceof WP_Term) {
                continue;
            }

            $term_slug = sanitize_title(
                (string) $term->slug
            );

            if ('' === $term_slug) {
                return new WP_Error(
                    'nwmd_ai_state_taxonomy_identity_missing',
                    __(
                        'A taxonomy term does not have a valid portable slug.',
                        'local-directory-framework'
                    ),
                    [
                        'taxonomy' => $taxonomy,
                        'term_id'  => absint($term->term_id),
                    ]
                );
            }

            $parent_taxonomy = '';
            $parent_slug = '';
            $parent_id = absint($term->parent);

            if ($parent_id > 0) {
                if (
                    !isset($terms_by_id[$parent_id])
                    || !$terms_by_id[$parent_id] instanceof WP_Term
                ) {
                    return new WP_Error(
                        'nwmd_ai_state_taxonomy_parent_missing',
                        __(
                            'A taxonomy term references a missing parent term.',
                            'local-directory-framework'
                        ),
                        [
                            'taxonomy' => $taxonomy,
                            'term_slug' => $term_slug,
                            'parent_id' => $parent_id,
                        ]
                    );
                }

                $parent_taxonomy = $taxonomy;
                $parent_slug = sanitize_title(
                    (string) $terms_by_id[$parent_id]->slug
                );
            }

            $export_rows[] = [
                'taxonomy'        => $taxonomy,
                'term_slug'       => $term_slug,
                'term_name'       =>
                    sanitize_text_field(
                        (string) $term->name
                    ),
                'parent_taxonomy' => $parent_taxonomy,
                'parent_slug'     => $parent_slug,
                'description'     =>
                    (string) $term->description,
            ];
        }
    }

    return $export_rows;
}

/**
 * Return the collector assigned to each AI state record group.
 *
 * @return array
 */
function nwmd_directory_get_ai_state_record_collectors() {

    return [
        'businesses'     =>
            'nwmd_directory_get_ai_state_business_rows',
        'sources'        =>
            'nwmd_directory_get_ai_state_source_rows',
        'deals'          =>
            'nwmd_directory_get_ai_state_deal_rows',
        'queue'          =>
            'nwmd_directory_get_ai_state_queue_rows',
        'runs'           =>
            'nwmd_directory_get_ai_state_run_rows',
        'taxonomy_terms' =>
            'nwmd_directory_get_ai_state_taxonomy_rows',
    ];
}

/**
 * Validate and order one AI state record group.
 *
 * Every exported row must contain exactly the columns declared by its
 * schema. This prevents accidental missing fields or undocumented data.
 *
 * @param string $record_name Record group name.
 * @param array  $columns     Declared columns.
 * @param array  $rows        Collected rows.
 *
 * @return array|WP_Error
 */
function nwmd_directory_validate_ai_state_rows(
    $record_name,
    array $columns,
    array $rows
) {

    $validated_rows = [];

    foreach ($rows as $index => $row) {
        if (!is_array($row)) {
            return new WP_Error(
                'nwmd_ai_state_row_invalid',
                sprintf(
                    __(
                        'AI state record "%1$s" contains an invalid row at position %2$d.',
                        'local-directory-framework'
                    ),
                    sanitize_key($record_name),
                    absint($index) + 1
                )
            );
        }

        $missing_columns = array_values(
            array_diff(
                $columns,
                array_keys($row)
            )
        );

        $unexpected_columns = array_values(
            array_diff(
                array_keys($row),
                $columns
            )
        );

        if (
            !empty($missing_columns)
            || !empty($unexpected_columns)
        ) {
            return new WP_Error(
                'nwmd_ai_state_row_schema_mismatch',
                sprintf(
                    __(
                        'AI state record "%1$s" does not match its schema at row %2$d.',
                        'local-directory-framework'
                    ),
                    sanitize_key($record_name),
                    absint($index) + 1
                ),
                [
                    'record_name'       =>
                        sanitize_key($record_name),
                    'row_number'        =>
                        absint($index) + 1,
                    'missing_columns'   =>
                        $missing_columns,
                    'unexpected_columns' =>
                        $unexpected_columns,
                ]
            );
        }

        $ordered_row = [];

        foreach ($columns as $column) {
            $ordered_row[$column] = $row[$column];
        }

        $validated_rows[] = $ordered_row;
    }

    return $validated_rows;
}

/**
 * Collect and validate every AI state export record group.
 *
 * This function is read-only. It does not create files or change
 * WordPress data.
 *
 * @return array|WP_Error
 */
function nwmd_directory_get_ai_state_export_records() {

    $schema = nwmd_directory_get_ai_state_export_schema();
    $collectors =
        nwmd_directory_get_ai_state_record_collectors();

    if (
        empty($schema['records'])
        || !is_array($schema['records'])
    ) {
        return new WP_Error(
            'nwmd_ai_state_schema_missing',
            __(
                'The AI state export schema is unavailable.',
                'local-directory-framework'
            )
        );
    }

    $records = [];

    foreach ($schema['records'] as $record_name => $definition) {
        $collector = $collectors[$record_name] ?? '';

        if (
            !is_string($collector)
            || !is_callable($collector)
        ) {
            return new WP_Error(
                'nwmd_ai_state_collector_missing',
                sprintf(
                    __(
                        'The AI state collector for "%s" is unavailable.',
                        'local-directory-framework'
                    ),
                    sanitize_key($record_name)
                )
            );
        }

        $filename = sanitize_file_name(
            (string) ($definition['filename'] ?? '')
        );

        if (
            '' === $filename
            || 'csv' !== strtolower(
                (string) pathinfo(
                    $filename,
                    PATHINFO_EXTENSION
                )
            )
        ) {
            return new WP_Error(
                'nwmd_ai_state_filename_invalid',
                sprintf(
                    __(
                        'The AI state filename for "%s" is invalid.',
                        'local-directory-framework'
                    ),
                    sanitize_key($record_name)
                )
            );
        }

        $columns = $definition['columns'] ?? [];

        if (empty($columns) || !is_array($columns)) {
            return new WP_Error(
                'nwmd_ai_state_columns_missing',
                sprintf(
                    __(
                        'The AI state columns for "%s" are unavailable.',
                        'local-directory-framework'
                    ),
                    sanitize_key($record_name)
                )
            );
        }

        $rows = call_user_func($collector);

        if (is_wp_error($rows)) {
            return $rows;
        }

        if (!is_array($rows)) {
            return new WP_Error(
                'nwmd_ai_state_collection_invalid',
                sprintf(
                    __(
                        'The AI state collector for "%s" returned invalid data.',
                        'local-directory-framework'
                    ),
                    sanitize_key($record_name)
                )
            );
        }

        $validated_rows =
            nwmd_directory_validate_ai_state_rows(
                $record_name,
                $columns,
                $rows
            );

        if (is_wp_error($validated_rows)) {
            return $validated_rows;
        }

        $records[$record_name] = [
            'filename' => $filename,
            'columns'  => array_values($columns),
            'rows'     => $validated_rows,
            'count'    => count($validated_rows),
        ];
    }

    return [
        'format_version'     =>
            (string) ($schema['format_version'] ?? ''),
        'taxonomy_separator' =>
            (string) ($schema['taxonomy_separator'] ?? '|'),
        'records'            =>
            $records,
    ];
}

/**
 * Convert one AI state value into a safe CSV field value.
 *
 * @param mixed $value Export value.
 *
 * @return string|WP_Error
 */
function nwmd_directory_get_ai_state_csv_value($value) {

    if (null === $value) {
        return '';
    }

    if (is_bool($value)) {
        return $value
            ? '1'
            : '0';
    }

    if (is_scalar($value)) {
        return (string) $value;
    }

    return new WP_Error(
        'nwmd_ai_state_csv_value_invalid',
        __(
            'An AI state CSV field contains an unsupported value.',
            'local-directory-framework'
        )
    );
}

/**
 * Write one validated AI state record group to a CSV file.
 *
 * The destination directory must already exist and be writable.
 *
 * @param string $path    Destination file path.
 * @param array  $columns Ordered CSV columns.
 * @param array  $rows    Validated export rows.
 *
 * @return true|WP_Error
 */
function nwmd_directory_write_ai_state_csv(
    $path,
    array $columns,
    array $rows
) {

    $path = wp_normalize_path(
        trim((string) $path)
    );

    if ('' === $path || empty($columns)) {
        return new WP_Error(
            'nwmd_ai_state_csv_destination_invalid',
            __(
                'The AI state CSV destination is invalid.',
                'local-directory-framework'
            )
        );
    }

    $directory = dirname($path);

    if (
        !is_dir($directory)
        || !is_writable($directory)
    ) {
        return new WP_Error(
            'nwmd_ai_state_csv_directory_unavailable',
            __(
                'The AI state CSV directory is unavailable.',
                'local-directory-framework'
            ),
            [
                'directory' => $directory,
            ]
        );
    }

    $handle = @fopen($path, 'wb');

    if (false === $handle) {
        return new WP_Error(
            'nwmd_ai_state_csv_open_failed',
            __(
                'The AI state CSV file could not be opened for writing.',
                'local-directory-framework'
            )
        );
    }

    $header_written = fputcsv(
        $handle,
        array_values($columns),
        ',',
        '"',
        ''
    );

    if (false === $header_written) {
        fclose($handle);
        wp_delete_file($path);

        return new WP_Error(
            'nwmd_ai_state_csv_header_failed',
            __(
                'The AI state CSV header could not be written.',
                'local-directory-framework'
            )
        );
    }

    foreach ($rows as $row_number => $row) {
        if (!is_array($row)) {
            fclose($handle);
            wp_delete_file($path);

            return new WP_Error(
                'nwmd_ai_state_csv_row_invalid',
                sprintf(
                    __(
                        'AI state CSV row %d is invalid.',
                        'local-directory-framework'
                    ),
                    absint($row_number) + 1
                )
            );
        }

        $csv_row = [];

        foreach ($columns as $column) {
            $value =
                nwmd_directory_get_ai_state_csv_value(
                    $row[$column] ?? ''
                );

            if (is_wp_error($value)) {
                fclose($handle);
                wp_delete_file($path);

                return $value;
            }

            $csv_row[] = $value;
        }

        $row_written = fputcsv(
            $handle,
            $csv_row,
            ',',
            '"',
            ''
        );

        if (false === $row_written) {
            fclose($handle);
            wp_delete_file($path);

            return new WP_Error(
                'nwmd_ai_state_csv_write_failed',
                sprintf(
                    __(
                        'AI state CSV row %d could not be written.',
                        'local-directory-framework'
                    ),
                    absint($row_number) + 1
                )
            );
        }
    }

    if (!fclose($handle)) {
        wp_delete_file($path);

        return new WP_Error(
            'nwmd_ai_state_csv_close_failed',
            __(
                'The AI state CSV file could not be finalized.',
                'local-directory-framework'
            )
        );
    }

    return true;
}

/**
 * Return the managed base directory for temporary AI state files.
 *
 * @return string
 */
function nwmd_directory_get_ai_state_temp_base_directory() {

    return wp_normalize_path(
        trailingslashit(get_temp_dir())
        . 'nwmd-ai-state'
    );
}

/**
 * Create one private, unpredictable AI state working directory.
 *
 * @return string|WP_Error
 */
function nwmd_directory_create_ai_state_working_directory() {

    $base_directory =
        nwmd_directory_get_ai_state_temp_base_directory();

    if (
        !is_dir($base_directory)
        && !wp_mkdir_p($base_directory)
    ) {
        return new WP_Error(
            'nwmd_ai_state_temp_base_failed',
            __(
                'The AI state temporary base directory could not be created.',
                'local-directory-framework'
            )
        );
    }

    if (!is_writable($base_directory)) {
        return new WP_Error(
            'nwmd_ai_state_temp_base_unwritable',
            __(
                'The AI state temporary base directory is not writable.',
                'local-directory-framework'
            )
        );
    }

    $directory = wp_normalize_path(
        trailingslashit($base_directory)
        . 'export-'
        . wp_generate_uuid4()
    );

    if (!wp_mkdir_p($directory)) {
        return new WP_Error(
            'nwmd_ai_state_temp_directory_failed',
            __(
                'The AI state working directory could not be created.',
                'local-directory-framework'
            )
        );
    }

    if (
        !is_dir($directory)
        || !is_writable($directory)
    ) {
        return new WP_Error(
            'nwmd_ai_state_temp_directory_unavailable',
            __(
                'The AI state working directory is unavailable.',
                'local-directory-framework'
            )
        );
    }

    return trailingslashit($directory);
}

/**
 * Remove one AI state working directory and its generated files.
 *
 * Removal is restricted to direct children of the managed temporary
 * base directory.
 *
 * @param string $directory Working directory path.
 *
 * @return true|WP_Error
 */
function nwmd_directory_remove_ai_state_working_directory(
    $directory
) {

    $base_directory = untrailingslashit(
        nwmd_directory_get_ai_state_temp_base_directory()
    );

    $directory = untrailingslashit(
        wp_normalize_path(
            trim((string) $directory)
        )
    );

    if (
        '' === $directory
        || $directory === $base_directory
        || 0 !== strpos(
            $directory,
            $base_directory . '/'
        )
    ) {
        return new WP_Error(
            'nwmd_ai_state_cleanup_path_invalid',
            __(
                'The AI state cleanup path is invalid.',
                'local-directory-framework'
            )
        );
    }

    if (!file_exists($directory)) {
        return true;
    }

    if (!is_dir($directory)) {
        return new WP_Error(
            'nwmd_ai_state_cleanup_not_directory',
            __(
                'The AI state cleanup path is not a directory.',
                'local-directory-framework'
            )
        );
    }

    $items = scandir($directory);

    if (false === $items) {
        return new WP_Error(
            'nwmd_ai_state_cleanup_scan_failed',
            __(
                'The AI state working directory could not be inspected.',
                'local-directory-framework'
            )
        );
    }

    foreach ($items as $item) {
        if ('.' === $item || '..' === $item) {
            continue;
        }

        $item_path = wp_normalize_path(
            trailingslashit($directory)
            . $item
        );

        if (is_dir($item_path) && !is_link($item_path)) {
            return new WP_Error(
                'nwmd_ai_state_cleanup_nested_directory',
                __(
                    'The AI state working directory contains an unexpected nested directory.',
                    'local-directory-framework'
                )
            );
        }

        wp_delete_file($item_path);

        if (file_exists($item_path)) {
            return new WP_Error(
                'nwmd_ai_state_cleanup_file_failed',
                __(
                    'A temporary AI state file could not be removed.',
                    'local-directory-framework'
                ),
                [
                    'filename' => basename($item_path),
                ]
            );
        }
    }

    if (!rmdir($directory)) {
        return new WP_Error(
            'nwmd_ai_state_cleanup_directory_failed',
            __(
                'The AI state working directory could not be removed.',
                'local-directory-framework'
            )
        );
    }

    return true;
}

/**
 * Write one JSON document to a managed export file.
 *
 * @param string $path Destination path.
 * @param array  $data JSON document data.
 *
 * @return true|WP_Error
 */
function nwmd_directory_write_ai_state_json(
    $path,
    array $data
) {

    $path = wp_normalize_path(
        trim((string) $path)
    );

    $directory = dirname($path);

    if (
        '' === $path
        || !is_dir($directory)
        || !is_writable($directory)
    ) {
        return new WP_Error(
            'nwmd_ai_state_json_destination_invalid',
            __(
                'The AI state JSON destination is invalid.',
                'local-directory-framework'
            )
        );
    }

    $encoded = wp_json_encode(
        $data,
        JSON_PRETTY_PRINT
        | JSON_UNESCAPED_SLASHES
    );

    if (!is_string($encoded)) {
        return new WP_Error(
            'nwmd_ai_state_json_encode_failed',
            __(
                'The AI state manifest could not be encoded.',
                'local-directory-framework'
            )
        );
    }

    $written = @file_put_contents(
        $path,
        $encoded . PHP_EOL,
        LOCK_EX
    );

    if (false === $written) {
        wp_delete_file($path);

        return new WP_Error(
            'nwmd_ai_state_json_write_failed',
            __(
                'The AI state manifest could not be written.',
                'local-directory-framework'
            )
        );
    }

    return true;
}

/**
 * Create the validated CSV files and manifest for one AI state export.
 *
 * The returned working directory must be removed after the ZIP download
 * finishes or when a later package-building step fails.
 *
 * @return array|WP_Error
 */
function nwmd_directory_create_ai_state_export_files() {

    $export =
        nwmd_directory_get_ai_state_export_records();

    if (is_wp_error($export)) {
        return $export;
    }

    $directory =
        nwmd_directory_create_ai_state_working_directory();

    if (is_wp_error($directory)) {
        return $directory;
    }

    $manifest_files = [];

    foreach ($export['records'] as $record_name => $record) {
        $filename = sanitize_file_name(
            (string) ($record['filename'] ?? '')
        );

        $columns = $record['columns'] ?? [];
        $rows = $record['rows'] ?? [];

        if (
            '' === $filename
            || !is_array($columns)
            || !is_array($rows)
        ) {
            nwmd_directory_remove_ai_state_working_directory(
                $directory
            );

            return new WP_Error(
                'nwmd_ai_state_file_definition_invalid',
                sprintf(
                    __(
                        'The AI state file definition for "%s" is invalid.',
                        'local-directory-framework'
                    ),
                    sanitize_key($record_name)
                )
            );
        }

        $file_path = wp_normalize_path(
            $directory . $filename
        );

        $written =
            nwmd_directory_write_ai_state_csv(
                $file_path,
                $columns,
                $rows
            );

        if (is_wp_error($written)) {
            nwmd_directory_remove_ai_state_working_directory(
                $directory
            );

            return $written;
        }

        clearstatcache(true, $file_path);

        $checksum = hash_file(
            'sha256',
            $file_path
        );

        $file_size = filesize($file_path);

        if (
            !is_string($checksum)
            || '' === $checksum
            || false === $file_size
        ) {
            nwmd_directory_remove_ai_state_working_directory(
                $directory
            );

            return new WP_Error(
                'nwmd_ai_state_file_metadata_failed',
                sprintf(
                    __(
                        'Metadata could not be created for "%s".',
                        'local-directory-framework'
                    ),
                    $filename
                )
            );
        }

        $manifest_files[$filename] = [
            'record_name' =>
                sanitize_key($record_name),
            'sha256'      =>
                $checksum,
            'bytes'       =>
                absint($file_size),
            'rows'        =>
                absint($record['count'] ?? count($rows)),
            'columns'     =>
                array_values($columns),
        ];
    }

    $manifest = [
        'format_version' =>
            (string) ($export['format_version'] ?? ''),
        'plugin_version' =>
            defined('NWMD_DIRECTORY_VERSION')
                ? (string) NWMD_DIRECTORY_VERSION
                : '',
        'generated_at_utc' =>
            gmdate('Y-m-d\TH:i:s\Z'),
        'source_site' =>
            esc_url_raw(home_url('/')),
        'taxonomy_separator' =>
            (string) (
                $export['taxonomy_separator'] ?? '|'
            ),
        'files' =>
            $manifest_files,
    ];

    $manifest_path = wp_normalize_path(
        $directory . 'manifest.json'
    );

    $manifest_written =
        nwmd_directory_write_ai_state_json(
            $manifest_path,
            $manifest
        );

    if (is_wp_error($manifest_written)) {
        nwmd_directory_remove_ai_state_working_directory(
            $directory
        );

        return $manifest_written;
    }

    return [
        'directory'     => $directory,
        'manifest_path' => $manifest_path,
        'manifest'      => $manifest,
        'files'         => $manifest_files,
    ];
}

/**
 * Create the downloadable ZIP for one generated AI state package.
 *
 * ZipArchive is preferred. WordPress PclZip is used as a fallback when
 * the PHP ZIP extension is unavailable.
 *
 * @param array $package Generated export package information.
 *
 * @return string|WP_Error ZIP file path on success.
 */
function nwmd_directory_create_ai_state_zip(
    array $package
) {

    $directory = untrailingslashit(
        wp_normalize_path(
            (string) ($package['directory'] ?? '')
        )
    );

    $base_directory = untrailingslashit(
        nwmd_directory_get_ai_state_temp_base_directory()
    );

    if (
        '' === $directory
        || $directory === $base_directory
        || 0 !== strpos(
            $directory,
            $base_directory . '/'
        )
        || !is_dir($directory)
        || !is_writable($directory)
    ) {
        return new WP_Error(
            'nwmd_ai_state_zip_directory_invalid',
            __(
                'The AI state ZIP working directory is invalid.',
                'local-directory-framework'
            )
        );
    }

    $package_files = $package['files'] ?? [];

    if (!is_array($package_files)) {
        return new WP_Error(
            'nwmd_ai_state_zip_files_invalid',
            __(
                'The AI state ZIP file list is invalid.',
                'local-directory-framework'
            )
        );
    }

    $expected_filenames = array_keys($package_files);
    $expected_filenames[] = 'manifest.json';

    $expected_filenames = array_values(
        array_unique(
            array_map(
                'sanitize_file_name',
                $expected_filenames
            )
        )
    );

    sort($expected_filenames, SORT_STRING);

    $source_paths = [];

    foreach ($expected_filenames as $filename) {
        if (
            '' === $filename
            || basename($filename) !== $filename
        ) {
            return new WP_Error(
                'nwmd_ai_state_zip_filename_invalid',
                __(
                    'The AI state ZIP contains an invalid filename.',
                    'local-directory-framework'
                )
            );
        }

        $file_path = wp_normalize_path(
            trailingslashit($directory)
            . $filename
        );

        if (
            !is_file($file_path)
            || is_link($file_path)
            || !is_readable($file_path)
        ) {
            return new WP_Error(
                'nwmd_ai_state_zip_source_missing',
                sprintf(
                    __(
                        'The AI state export file "%s" is unavailable.',
                        'local-directory-framework'
                    ),
                    $filename
                )
            );
        }

        $source_paths[$filename] = $file_path;
    }

    $zip_filename = sanitize_file_name(
        'nwmd-ai-state-'
        . gmdate('Ymd-His')
        . '.zip'
    );

    $zip_path = wp_normalize_path(
        trailingslashit($directory)
        . $zip_filename
    );

    wp_delete_file($zip_path);

    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();

        $opened = $zip->open(
            $zip_path,
            ZipArchive::CREATE
            | ZipArchive::OVERWRITE
        );

        if (true !== $opened) {
            return new WP_Error(
                'nwmd_ai_state_zip_open_failed',
                __(
                    'The AI state ZIP file could not be opened.',
                    'local-directory-framework'
                )
            );
        }

        foreach ($source_paths as $filename => $file_path) {
            if (!$zip->addFile($file_path, $filename)) {
                $zip->close();
                wp_delete_file($zip_path);

                return new WP_Error(
                    'nwmd_ai_state_zip_add_failed',
                    sprintf(
                        __(
                            'The file "%s" could not be added to the AI state ZIP.',
                            'local-directory-framework'
                        ),
                        $filename
                    )
                );
            }
        }

        if (!$zip->close()) {
            wp_delete_file($zip_path);

            return new WP_Error(
                'nwmd_ai_state_zip_close_failed',
                __(
                    'The AI state ZIP file could not be finalized.',
                    'local-directory-framework'
                )
            );
        }
    } else {
        $pclzip_path = ABSPATH
            . 'wp-admin/includes/class-pclzip.php';

        if (!class_exists('PclZip')) {
            if (!is_readable($pclzip_path)) {
                return new WP_Error(
                    'nwmd_ai_state_pclzip_missing',
                    __(
                        'WordPress ZIP support is unavailable.',
                        'local-directory-framework'
                    )
                );
            }

            require_once $pclzip_path;
        }

        if (!class_exists('PclZip')) {
            return new WP_Error(
                'nwmd_ai_state_pclzip_unavailable',
                __(
                    'WordPress ZIP support could not be loaded.',
                    'local-directory-framework'
                )
            );
        }

        $archive = new PclZip($zip_path);

        $created = $archive->create(
            array_values($source_paths),
            PCLZIP_OPT_REMOVE_PATH,
            $directory
        );

        if (0 === $created) {
            wp_delete_file($zip_path);

            return new WP_Error(
                'nwmd_ai_state_pclzip_create_failed',
                __(
                    'The AI state ZIP file could not be created.',
                    'local-directory-framework'
                ),
                [
                    'zip_error' => sanitize_text_field(
                        (string) $archive->errorInfo(true)
                    ),
                ]
            );
        }
    }

    clearstatcache(true, $zip_path);

    $zip_size = is_file($zip_path)
        ? filesize($zip_path)
        : false;

    if (
        false === $zip_size
        || $zip_size < 1
        || !is_readable($zip_path)
    ) {
        wp_delete_file($zip_path);

        return new WP_Error(
            'nwmd_ai_state_zip_invalid',
            __(
                'The completed AI state ZIP file is invalid.',
                'local-directory-framework'
            )
        );
    }

    return $zip_path;
}

/**
 * Handle the authenticated AI state ZIP download.
 *
 * The export is read-only. Generated files are stored temporarily and
 * removed after the download finishes.
 */
function nwmd_directory_handle_ai_state_export() {

    if (!current_user_can('manage_options')) {
        wp_die(
            esc_html__(
                'You are not allowed to export AI state data.',
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
        'nwmd_directory_export_ai_state'
    );

    if (headers_sent()) {
        wp_die(
            esc_html__(
                'The AI state download could not start because output was already sent.',
                'local-directory-framework'
            ),
            esc_html__(
                'Export failed',
                'local-directory-framework'
            ),
            [
                'response' => 500,
            ]
        );
    }

    $package =
        nwmd_directory_create_ai_state_export_files();

    if (is_wp_error($package)) {
        wp_die(
            esc_html($package->get_error_message()),
            esc_html__(
                'Export failed',
                'local-directory-framework'
            ),
            [
                'response' => 500,
            ]
        );
    }

    $working_directory = (string) (
        $package['directory'] ?? ''
    );

    $zip_path =
        nwmd_directory_create_ai_state_zip($package);

    if (is_wp_error($zip_path)) {
        nwmd_directory_remove_ai_state_working_directory(
            $working_directory
        );

        wp_die(
            esc_html($zip_path->get_error_message()),
            esc_html__(
                'Export failed',
                'local-directory-framework'
            ),
            [
                'response' => 500,
            ]
        );
    }

    $zip_path = wp_normalize_path(
        (string) $zip_path
    );

    clearstatcache(true, $zip_path);

    $zip_size = is_file($zip_path)
        ? filesize($zip_path)
        : false;

    if (
        false === $zip_size
        || $zip_size < 1
        || !is_readable($zip_path)
    ) {
        nwmd_directory_remove_ai_state_working_directory(
            $working_directory
        );

        wp_die(
            esc_html__(
                'The completed AI state ZIP is unavailable.',
                'local-directory-framework'
            ),
            esc_html__(
                'Export failed',
                'local-directory-framework'
            ),
            [
                'response' => 500,
            ]
        );
    }

    $handle = @fopen($zip_path, 'rb');

    if (false === $handle) {
        nwmd_directory_remove_ai_state_working_directory(
            $working_directory
        );

        wp_die(
            esc_html__(
                'The completed AI state ZIP could not be opened.',
                'local-directory-framework'
            ),
            esc_html__(
                'Export failed',
                'local-directory-framework'
            ),
            [
                'response' => 500,
            ]
        );
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $download_filename = sanitize_file_name(
        basename($zip_path)
    );

    nocache_headers();

    header('Content-Type: application/zip');
    header(
        'Content-Disposition: attachment; filename="'
        . $download_filename
        . '"'
    );
    header(
        'Content-Length: '
        . (string) $zip_size
    );
    header('X-Content-Type-Options: nosniff');

    while (!feof($handle)) {
        $chunk = fread($handle, 1048576);

        if (false === $chunk) {
            break;
        }

        echo $chunk;

        if (function_exists('flush')) {
            flush();
        }
    }

    fclose($handle);

    nwmd_directory_remove_ai_state_working_directory(
        $working_directory
    );

    exit;
}

add_action(
    'admin_post_nwmd_directory_export_ai_state',
    'nwmd_directory_handle_ai_state_export'
);
