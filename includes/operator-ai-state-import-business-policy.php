<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return Business posts indexed by exact portable slug.
 *
 * @param bool $lock Lock rows for controlled execution.
 *
 * @return array|WP_Error
 */
function nwmd_directory_get_ai_state_import_business_posts($lock = false) {

    global $wpdb;

    $locking = $lock ? ' FOR UPDATE' : '';
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT ID, post_name, post_status, post_modified
            FROM {$wpdb->posts}
            WHERE post_type = %s
            ORDER BY ID ASC{$locking}",
            'nwmd_business'
        ),
        ARRAY_A
    );

    if (null === $rows || '' !== (string) $wpdb->last_error) {
        return new WP_Error(
            'nwmd_ai_state_import_business_query_failed',
            __(
                'Current Business identities could not be read.',
                'local-directory-framework'
            )
        );
    }

    $index = [];

    foreach ($rows as $row) {
        $slug = (string) ($row['post_name'] ?? '');

        if ('' === $slug) {
            continue;
        }

        if (!isset($index[$slug])) {
            $index[$slug] = [];
        }

        $index[$slug][] = [
            'id'       => absint($row['ID'] ?? 0),
            'status'   => sanitize_key(
                (string) ($row['post_status'] ?? '')
            ),
            'modified' => (string) ($row['post_modified'] ?? ''),
        ];
    }

    return $index;
}

/**
 * Resolve a canonical Business projection to existing term IDs.
 *
 * @param array $projection Canonical Business projection.
 * @param bool  $require_core Require State, City, and Category.
 *
 * @return array|WP_Error
 */
function nwmd_directory_resolve_ai_state_import_terms(
    array $projection,
    $require_core
) {

    $schema =
        nwmd_directory_get_ai_state_import_business_projection_schema();
    $resolved = [];

    foreach ($schema as $field => $definition) {
        if ('taxonomy' !== ($definition['storage'] ?? '')) {
            continue;
        }

        $taxonomy = (string) ($definition['taxonomy'] ?? '');
        $slugs = '' === (string) ($projection[$field] ?? '')
            ? []
            : explode('|', (string) $projection[$field]);

        if (
            $require_core
            && 'specialty_slugs' !== $field
            && empty($slugs)
        ) {
            return new WP_Error(
                'nwmd_ai_state_import_business_terms_required',
                __(
                    'A new Business must have a State, City, and Category.',
                    'local-directory-framework'
                )
            );
        }

        $resolved[$taxonomy] = [];

        foreach ($slugs as $slug) {
            $term = nwmd_directory_get_business_csv_import_term(
                $slug,
                $taxonomy
            );

            if (is_wp_error($term)) {
                return new WP_Error(
                    'nwmd_ai_state_import_term_missing',
                    __(
                        'A planned taxonomy term no longer exists.',
                        'local-directory-framework'
                    )
                );
            }

            $resolved[$taxonomy][] = absint($term->term_id);
        }
    }

    foreach ((array) ($resolved['nwmd_city'] ?? []) as $city_id) {
        if (
            !in_array(
                absint(
                    get_term_meta(
                        $city_id,
                        'nwmd_state_term_id',
                        true
                    )
                ),
                (array) ($resolved['nwmd_state'] ?? []),
                true
            )
        ) {
            return new WP_Error(
                'nwmd_ai_state_import_city_state_invalid',
                __(
                    'A planned City does not belong to the selected State.',
                    'local-directory-framework'
                )
            );
        }
    }

    foreach (
        (array) ($resolved['nwmd_specialty'] ?? []) as $specialty_id
    ) {
        if (
            !in_array(
                absint(
                    get_term_meta(
                        $specialty_id,
                        'nwmd_category_term_id',
                        true
                    )
                ),
                (array) ($resolved['nwmd_category'] ?? []),
                true
            )
        ) {
            return new WP_Error(
                'nwmd_ai_state_import_specialty_category_invalid',
                __(
                    'A planned Specialty does not belong to the selected Category.',
                    'local-directory-framework'
                )
            );
        }
    }

    return $resolved;
}

/**
 * Return another Business using a unique metadata value.
 *
 * @param string $meta_key        Exact metadata key.
 * @param string $value           Non-empty value.
 * @param int    $excluded_post_id Current Business ID.
 * @param bool   $lock            Lock the matching row when possible.
 *
 * @return int|WP_Error
 */
function nwmd_directory_get_ai_state_import_meta_conflict(
    $meta_key,
    $value,
    $excluded_post_id = 0,
    $lock = false
) {

    global $wpdb;

    $locking = $lock ? ' FOR UPDATE' : '';
    $post_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT postmeta.post_id
            FROM {$wpdb->postmeta} AS postmeta
            INNER JOIN {$wpdb->posts} AS posts
                ON posts.ID = postmeta.post_id
            WHERE postmeta.meta_key = %s
                AND postmeta.meta_value = %s
                AND postmeta.post_id <> %d
                AND posts.post_type = %s
            LIMIT 1{$locking}",
            sanitize_key($meta_key),
            (string) $value,
            absint($excluded_post_id),
            'nwmd_business'
        )
    );

    if ('' !== (string) $wpdb->last_error) {
        return new WP_Error(
            'nwmd_ai_state_import_unique_query_failed',
            __('Business uniqueness could not be verified.', 'local-directory-framework')
        );
    }

    return absint($post_id);
}

/**
 * Validate uniqueness for all planned Business projections.
 *
 * @param array $items Planned Business writes.
 * @param bool  $lock  Lock matching rows during execution.
 *
 * @return true|WP_Error
 */
function nwmd_directory_validate_ai_state_import_business_uniqueness(
    array $items,
    $lock = false
) {

    $seen = [
        'registration_number' => [],
        'license_number'      => [],
    ];

    foreach ($items as $item) {
        $target  = (array) ($item['target'] ?? []);
        $post_id = absint($item['post_id'] ?? 0);

        foreach (array_keys($seen) as $field) {
            $value = (string) ($target[$field] ?? '');

            if ('' === $value) {
                continue;
            }

            if (isset($seen[$field][$value])) {
                return new WP_Error(
                    'nwmd_ai_state_import_unique_duplicate',
                    sprintf(
                        /* translators: %s: unique Business field. */
                        __(
                            'The proposed %s value is duplicated.',
                            'local-directory-framework'
                        ),
                        $field
                    )
                );
            }

            $seen[$field][$value] = true;

            $conflict = nwmd_directory_get_ai_state_import_meta_conflict(
                    'nwmd_' . $field,
                    $value,
                    $post_id,
                    $lock
                );

            if (is_wp_error($conflict)) {
                return $conflict;
            }

            if ($conflict > 0) {
                return new WP_Error(
                    'nwmd_ai_state_import_unique_conflict',
                    sprintf(
                        /* translators: %s: unique Business field. */
                        __(
                            'The proposed %s value belongs to another Business.',
                            'local-directory-framework'
                        ),
                        $field
                    )
                );
            }
        }
    }

    return true;
}

/**
 * Write one canonical Business metadata field.
 *
 * @param int    $post_id Business post ID.
 * @param string $key     Exact metadata key.
 * @param mixed  $value   Canonical value.
 *
 * @return bool
 */
function nwmd_directory_write_ai_state_import_business_meta(
    $post_id,
    $key,
    $value
) {

    if ('' === $value || null === $value) {
        delete_post_meta(absint($post_id), $key);

        return [] === get_post_meta(
            absint($post_id),
            $key,
            false
        );
    }

    update_post_meta(absint($post_id), $key, $value);

    return (string) get_post_meta(
        absint($post_id),
        $key,
        true
    ) === (string) $value;
}

/**
 * Create one new Draft Business from a canonical projection.
 *
 * @param array $item    Planned Business creation.
 * @param array $journal Rollback journal.
 *
 * @return int|WP_Error
 */
function nwmd_directory_apply_ai_state_import_business_create(
    array $item,
    array &$journal
) {

    $target = (array) ($item['target'] ?? []);
    $canonical =
        nwmd_directory_project_ai_state_import_business($target);

    if (
        is_wp_error($canonical)
        || $canonical !== $target
        || 'draft' !== ($target['post_status'] ?? '')
        || '0' !== (string) ($target['ranking_eligible'] ?? '')
    ) {
        return new WP_Error(
            'nwmd_ai_state_import_create_not_draft',
            __(
                'A new Business failed its final Draft projection check.',
                'local-directory-framework'
            )
        );
    }

    $terms = nwmd_directory_resolve_ai_state_import_terms(
        $target,
        true
    );

    if (is_wp_error($terms)) {
        return $terms;
    }

    $schema = nwmd_directory_get_ai_state_import_business_projection_schema();
    $post_data = [
        'post_type'   => 'nwmd_business',
        'post_author' => get_current_user_id(),
    ];

    foreach ($schema as $field => $definition) {
        if ('post' === ($definition['storage'] ?? '')) {
            $post_data[(string) $definition['column']] =
                (string) ($target[$field] ?? '');
        }
    }

    $post_id = wp_insert_post($post_data, true);

    if (is_wp_error($post_id) || absint($post_id) < 1) {
        return new WP_Error(
            'nwmd_ai_state_import_business_create_failed',
            __(
                'A planned Business Draft could not be created.',
                'local-directory-framework'
            )
        );
    }

    $post_id = absint($post_id);
    nwmd_directory_record_ai_state_import_created_business(
        $journal,
        $post_id,
        (string) $target['business_slug']
    );

    $post = get_post($post_id);

    if (
        !$post instanceof WP_Post
        || 'draft' !== $post->post_status
        || (string) $target['business_slug'] !== $post->post_name
    ) {
        return new WP_Error(
            'nwmd_ai_state_import_business_identity_changed',
            __(
                'A new Business did not preserve its Draft identity.',
                'local-directory-framework'
            )
        );
    }

    foreach ($terms as $taxonomy => $term_ids) {
        $assigned = wp_set_object_terms(
            $post_id,
            array_map('absint', (array) $term_ids),
            $taxonomy,
            false
        );

        if (is_wp_error($assigned)) {
            return new WP_Error(
                'nwmd_ai_state_import_business_terms_failed',
                __(
                    'A new Business taxonomy assignment failed.',
                    'local-directory-framework'
                )
            );
        }
    }

    foreach ($schema as $field => $definition) {
        if ('meta' !== ($definition['storage'] ?? '')) {
            continue;
        }

        $value = 'ranking_eligible' === $field
            ? 0
            : ($target[$field] ?? '');

        if (
            !nwmd_directory_write_ai_state_import_business_meta(
                $post_id,
                (string) $definition['key'],
                $value
            )
        ) {
            return new WP_Error(
                'nwmd_ai_state_import_business_meta_failed',
                __(
                    'A new Business metadata value could not be saved.',
                    'local-directory-framework'
                )
            );
        }
    }

    if (!nwmd_directory_sync_business_index($post_id)) {
        return new WP_Error(
            'nwmd_ai_state_import_business_index_failed',
            __(
                'A new Business index row could not be synchronized.',
                'local-directory-framework'
            )
        );
    }

    nwmd_directory_refresh_ai_state_import_business_after(
        $journal,
        $post_id
    );

    return $post_id;
}

/**
 * Update one existing Draft Business from a canonical projection.
 *
 * @param array $item    Planned Business update.
 * @param array $journal Rollback journal.
 *
 * @return true|WP_Error
 */
function nwmd_directory_apply_ai_state_import_business_update(
    array $item,
    array &$journal
) {

    global $wpdb;

    $post_id = absint($item['post_id'] ?? 0);
    $target  = (array) ($item['target'] ?? []);
    $changes = (array) ($item['changed_fields'] ?? []);
    $post    = get_post($post_id);

    if (
        !$post instanceof WP_Post
        || 'nwmd_business' !== $post->post_type
        || 'draft' !== $post->post_status
        || 'draft' !== ($target['post_status'] ?? '')
        || $post->post_name !== ($target['business_slug'] ?? '')
    ) {
        return new WP_Error(
            'nwmd_ai_state_import_business_update_unsafe',
            __(
                'An existing Business no longer passes its Draft identity check.',
                'local-directory-framework'
            )
        );
    }

    $schema =
        nwmd_directory_get_ai_state_import_business_projection_schema();
    $modified = current_datetime();

    if (
        $modified->format('Y-m-d H:i:s')
        === (string) ($item['lock_version'] ?? '')
    ) {
        $modified = $modified->modify('+1 second');
    }

    $post_data = [
        'post_modified' => $modified->format('Y-m-d H:i:s'),
        'post_modified_gmt' => $modified
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s'),
    ];
    $post_formats = ['%s', '%s'];

    foreach ($changes as $field => $change) {
        unset($change);
        $definition = $schema[$field] ?? [];

        if (
            'writable' !== ($definition['role'] ?? '')
            || 'post' !== ($definition['storage'] ?? '')
        ) {
            continue;
        }

        $post_data[(string) $definition['column']] =
            (string) ($target[$field] ?? '');
        $post_formats[] = '%s';
    }

    $updated = $wpdb->update(
        $wpdb->posts,
        $post_data,
        [
            'ID'            => $post_id,
            'post_type'     => 'nwmd_business',
            'post_status'   => 'draft',
            'post_name'     => (string) $target['business_slug'],
            'post_modified' => (string) ($item['lock_version'] ?? ''),
        ],
        $post_formats,
        ['%d', '%s', '%s', '%s', '%s']
    );

    if (1 !== $updated) {
        return new WP_Error(
            'nwmd_ai_state_import_business_post_stale',
            __(
                'An existing Business changed before its approved write.',
                'local-directory-framework'
            )
        );
    }

    nwmd_directory_mark_ai_state_import_business_touched(
        $journal,
        $post_id
    );
    clean_post_cache($post_id);

    foreach ($changes as $field => $change) {
        unset($change);
        $definition = $schema[$field] ?? [];

        if (
            'writable' !== ($definition['role'] ?? '')
            || 'meta' !== ($definition['storage'] ?? '')
        ) {
            continue;
        }

        $value = 'ranking_eligible' === $field
            ? absint($target[$field] ?? 0)
            : ($target[$field] ?? '');

        if (
            !nwmd_directory_write_ai_state_import_business_meta(
                $post_id,
                (string) $definition['key'],
                $value
            )
        ) {
            return new WP_Error(
                'nwmd_ai_state_import_business_meta_failed',
                __(
                    'An existing Business metadata value could not be updated.',
                    'local-directory-framework'
                )
            );
        }
    }

    if (!nwmd_directory_sync_business_index($post_id)) {
        return new WP_Error(
            'nwmd_ai_state_import_business_index_failed',
            __(
                'An existing Business index row could not be synchronized.',
                'local-directory-framework'
            )
        );
    }

    nwmd_directory_refresh_ai_state_import_business_after(
        $journal,
        $post_id
    );

    return true;
}
