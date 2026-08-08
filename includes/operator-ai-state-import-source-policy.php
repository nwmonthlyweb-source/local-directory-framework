<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return the canonical Business Source portable identity key.
 *
 * @param array $source Canonical source projection.
 *
 * @return string
 */
function nwmd_directory_get_ai_state_import_source_identity_key(
    array $source
) {

    $schema =
        nwmd_directory_get_ai_state_import_source_projection_schema();
    $fields = nwmd_directory_get_ai_state_import_projection_fields(
        $schema,
        ['identity']
    );

    return nwmd_directory_get_ai_state_comparison_key(
        $source,
        $fields
    );
}

/**
 * Return the semantic Business Source URL key.
 *
 * @param array $source Canonical source projection.
 *
 * @return string
 */
function nwmd_directory_get_ai_state_import_source_url_key(
    array $source
) {

    if ('' === (string) ($source['source_url'] ?? '')) {
        return '';
    }

    return nwmd_directory_get_ai_state_comparison_key(
        $source,
        [
            'business_slug',
            'source_url',
        ]
    );
}

/**
 * Return current Business Source rows indexed by portable identity.
 *
 * @param bool $lock Lock rows for controlled execution.
 *
 * @return array|WP_Error
 */
function nwmd_directory_get_ai_state_import_source_records($lock = false) {

    global $wpdb;

    $table = $wpdb->prefix . 'nwmd_business_sources';
    $locking = $lock ? ' FOR UPDATE' : '';
    $rows = $wpdb->get_results(
        "SELECT sources.*, posts.post_name AS business_slug
        FROM {$table} AS sources
        INNER JOIN {$wpdb->posts} AS posts
            ON posts.ID = sources.business_post_id
        WHERE posts.post_type = 'nwmd_business'
        ORDER BY sources.id ASC{$locking}",
        ARRAY_A
    );

    if (null === $rows || '' !== (string) $wpdb->last_error) {
        return new WP_Error(
            'nwmd_ai_state_import_source_query_failed',
            __(
                'Current Business Source identities could not be read.',
                'local-directory-framework'
            )
        );
    }

    $index = [];

    foreach ($rows as $row) {
        $projection = nwmd_directory_project_ai_state_import_source(
            $row
        );

        if (is_wp_error($projection)) {
            return $projection;
        }

        $key =
            nwmd_directory_get_ai_state_import_source_identity_key(
                $projection
            );

        if (!isset($index[$key])) {
            $index[$key] = [];
        }

        $index[$key][] = [
            'id'               => absint($row['id'] ?? 0),
            'business_post_id' => absint(
                $row['business_post_id'] ?? 0
            ),
            'updated_at'       => (string) ($row['updated_at'] ?? ''),
            'row'              => $row,
            'projection'       => $projection,
        ];
    }

    return $index;
}

/**
 * Return exact and semantic source matches for one target.
 *
 * @param int   $business_id Draft Business ID.
 * @param array $target      Canonical source target.
 * @param int   $excluded_id Optional current source ID.
 * @param bool  $lock        Lock matching rows.
 *
 * @return array|WP_Error
 */
function nwmd_directory_get_ai_state_import_source_conflicts(
    $business_id,
    array $target,
    $excluded_id = 0,
    $lock = false
) {

    global $wpdb;

    $table = $wpdb->prefix . 'nwmd_business_sources';
    $locking = $lock ? ' FOR UPDATE' : '';
    $exact = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT id
            FROM {$table}
            WHERE business_post_id = %d
                AND source_type = %s
                AND source_identifier = %s
                AND source_url = %s
                AND id <> %d{$locking}",
            absint($business_id),
            (string) $target['source_type'],
            (string) $target['source_identifier'],
            (string) $target['source_url'],
            absint($excluded_id)
        )
    );

    if ('' !== (string) $wpdb->last_error) {
        return new WP_Error(
            'nwmd_ai_state_import_source_conflict_query_failed',
            __('Business Source uniqueness could not be verified.', 'local-directory-framework')
        );
    }

    $semantic = [];

    if ('' !== (string) $target['source_url']) {
        $semantic = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id
                FROM {$table}
                WHERE business_post_id = %d
                    AND source_url = %s
                    AND id <> %d{$locking}",
                absint($business_id),
                (string) $target['source_url'],
                absint($excluded_id)
            )
        );
    }

    if ('' !== (string) $wpdb->last_error) {
        return new WP_Error(
            'nwmd_ai_state_import_source_conflict_query_failed',
            __('Business Source uniqueness could not be verified.', 'local-directory-framework')
        );
    }

    return [
        'exact'    => array_map('absint', (array) $exact),
        'semantic' => array_map('absint', (array) $semantic),
    ];
}

/**
 * Create one canonical Business Source with server timestamps.
 *
 * @param array $item        Planned source creation.
 * @param int   $business_id Draft Business ID.
 * @param array $journal     Rollback journal.
 *
 * @return int|WP_Error
 */
function nwmd_directory_apply_ai_state_import_source_create(
    array $item,
    $business_id,
    array &$journal
) {

    global $wpdb;

    $target = (array) ($item['target'] ?? []);
    $canonical =
        nwmd_directory_project_ai_state_import_source($target);
    $business = get_post(absint($business_id));

    if (
        is_wp_error($canonical)
        || $canonical !== $target
        || !$business instanceof WP_Post
        || 'nwmd_business' !== $business->post_type
        || 'draft' !== $business->post_status
        || $business->post_name
            !== (string) ($target['business_slug'] ?? '')
    ) {
        return new WP_Error(
            'nwmd_ai_state_import_source_business_unsafe',
            __(
                'A Business Source no longer has its approved Draft Business.',
                'local-directory-framework'
            )
        );
    }

    $conflicts =
        nwmd_directory_get_ai_state_import_source_conflicts(
            $business_id,
            $target,
            0,
            true
        );

    if (is_wp_error($conflicts)) {
        return $conflicts;
    }

    if (!empty($conflicts['exact']) || !empty($conflicts['semantic'])) {
        return new WP_Error(
            'nwmd_ai_state_import_source_create_conflict',
            __(
                'A concurrent Business Source identity conflict was detected.',
                'local-directory-framework'
            )
        );
    }

    $now = current_time('mysql');
    $data = [
        'business_post_id' => absint($business_id),
        'created_at'       => $now,
        'updated_at'       => $now,
    ];
    $schema = nwmd_directory_get_ai_state_import_source_projection_schema();

    foreach ($schema as $field => $definition) {
        if ('column' !== ($definition['storage'] ?? '')) {
            continue;
        }

        $data[(string) $definition['column']] =
            'verified_at' === $field
            && '' === (string) ($target[$field] ?? '')
                ? null
                : (string) ($target[$field] ?? '');
    }

    $inserted = $wpdb->insert(
        $wpdb->prefix . 'nwmd_business_sources',
        $data
    );

    if (false === $inserted || absint($wpdb->insert_id) < 1) {
        return new WP_Error(
            'nwmd_ai_state_import_source_create_failed',
            __(
                'A planned Business Source could not be created.',
                'local-directory-framework'
            )
        );
    }

    $source_id = absint($wpdb->insert_id);
    nwmd_directory_record_ai_state_import_created_source(
        $journal,
        $source_id,
        $business_id
    );
    $conflicts =
        nwmd_directory_get_ai_state_import_source_conflicts(
            $business_id,
            $target,
            $source_id,
            true
        );

    if (is_wp_error($conflicts)) {
        return $conflicts;
    }

    if (!empty($conflicts['exact']) || !empty($conflicts['semantic'])) {
        return new WP_Error(
            'nwmd_ai_state_import_source_create_duplicate',
            __(
                'A concurrent Business Source duplicate was detected.',
                'local-directory-framework'
            )
        );
    }

    nwmd_directory_refresh_ai_state_import_source_after(
        $journal,
        $source_id
    );

    return $source_id;
}

/**
 * Update canonical mutable fields on one exact source identity.
 *
 * @param array $item    Planned source update.
 * @param array $journal Rollback journal.
 *
 * @return true|WP_Error
 */
function nwmd_directory_apply_ai_state_import_source_update(
    array $item,
    array &$journal
) {

    global $wpdb;

    $source_id = absint($item['source_id'] ?? 0);
    $target    = (array) ($item['target'] ?? []);
    $changes   = (array) ($item['changed_fields'] ?? []);
    $current = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT sources.id, sources.business_post_id
            FROM {$wpdb->prefix}nwmd_business_sources AS sources
            INNER JOIN {$wpdb->posts} AS posts
                ON posts.ID = sources.business_post_id
            WHERE sources.id = %d
                AND posts.post_type = %s
                AND posts.post_status = %s
                AND posts.post_name = %s
                AND sources.source_type = %s
                AND sources.source_identifier = %s
                AND sources.source_url = %s
            LIMIT 1 FOR UPDATE",
            $source_id,
            'nwmd_business',
            'draft',
            (string) $target['business_slug'],
            (string) $target['source_type'],
            (string) $target['source_identifier'],
            (string) $target['source_url']
        )
    );

    if (!is_object($current)) {
        return new WP_Error(
            'nwmd_ai_state_import_source_update_unsafe',
            __(
                'An existing Business Source lost its approved Draft identity.',
                'local-directory-framework'
            )
        );
    }

    $business_id = absint($current->business_post_id ?? 0);
    $conflicts =
        nwmd_directory_get_ai_state_import_source_conflicts(
            $business_id,
            $target,
            $source_id,
            true
        );

    if (is_wp_error($conflicts)) {
        return $conflicts;
    }

    if (!empty($conflicts['exact']) || !empty($conflicts['semantic'])) {
        return new WP_Error(
            'nwmd_ai_state_import_source_update_conflict',
            __(
                'A concurrent Business Source duplicate was detected.',
                'local-directory-framework'
            )
        );
    }

    $updated = current_datetime();

    if (
        $updated->format('Y-m-d H:i:s')
        === (string) ($item['lock_version'] ?? '')
    ) {
        $updated = $updated->modify('+1 second');
    }

    $data = [
        'updated_at' => $updated->format('Y-m-d H:i:s'),
    ];
    $formats = ['%s'];
    $schema =
        nwmd_directory_get_ai_state_import_source_projection_schema();

    foreach ($changes as $field => $change) {
        unset($change);
        $definition = $schema[$field] ?? [];

        if (
            'writable' !== ($definition['role'] ?? '')
            || 'column' !== ($definition['storage'] ?? '')
        ) {
            continue;
        }

        $data[(string) $definition['column']] =
            'verified_at' === $field
            && '' === (string) ($target[$field] ?? '')
                ? null
                : (string) ($target[$field] ?? '');
        $formats[] = '%s';
    }

    $result = $wpdb->update(
        $wpdb->prefix . 'nwmd_business_sources',
        $data,
        [
            'id'                => $source_id,
            'business_post_id'  => $business_id,
            'source_type'       => (string) $target['source_type'],
            'source_identifier' => (string) $target['source_identifier'],
            'source_url'        => (string) $target['source_url'],
            'updated_at'        => (string) ($item['lock_version'] ?? ''),
        ],
        $formats,
        ['%d', '%d', '%s', '%s', '%s', '%s']
    );

    if (1 !== $result) {
        return new WP_Error(
            'nwmd_ai_state_import_source_update_stale',
            __(
                'A Business Source changed before its approved write.',
                'local-directory-framework'
            )
        );
    }

    nwmd_directory_mark_ai_state_import_source_touched(
        $journal,
        $source_id
    );
    nwmd_directory_refresh_ai_state_import_source_after(
        $journal,
        $source_id
    );

    return true;
}
