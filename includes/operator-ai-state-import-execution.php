<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Convert one importer WP_Error into a bounded Throwable.
 *
 * @param mixed $result Result to require.
 *
 * @return mixed
 * @throws RuntimeException When the result is a WP_Error or false.
 */
function nwmd_directory_require_ai_state_import_result($result) {

    if (is_wp_error($result)) {
        throw new RuntimeException(
            sanitize_key((string) $result->get_error_code())
        );
    }

    if (false === $result) {
        throw new RuntimeException('nwmd_ai_state_import_operation_failed');
    }

    return $result;
}

/**
 * Return all database tables that must participate atomically.
 *
 * @return array
 */
function nwmd_directory_get_ai_state_import_transaction_tables() {

    global $wpdb;

    return array_values(
        array_unique(
            [
                $wpdb->posts,
                $wpdb->postmeta,
                $wpdb->term_relationships,
                $wpdb->term_taxonomy,
                $wpdb->terms,
                $wpdb->termmeta,
                $wpdb->prefix . 'nwmd_business_index',
                $wpdb->prefix . 'nwmd_business_sources',
                $wpdb->prefix . 'nwmd_business_deals',
                $wpdb->prefix . 'nwmd_ranking_periods',
                $wpdb->prefix . 'nwmd_ranking_entries',
                $wpdb->prefix . 'nwmd_business_requests',
                $wpdb->prefix . 'nwmd_ads',
                $wpdb->prefix . 'nwmd_operator_jobs',
                $wpdb->prefix . 'nwmd_operator_specialties',
                $wpdb->prefix . 'nwmd_operator_runs',
                $wpdb->prefix . 'nwmd_operator_usage',
            ]
        )
    );
}

/**
 * Require transactional storage before any directory write.
 *
 * @return true|WP_Error
 */
function nwmd_directory_validate_ai_state_import_transaction_tables() {

    global $wpdb;

    foreach (nwmd_directory_get_ai_state_import_transaction_tables() as $table) {
        $engine = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT ENGINE
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = %s
                LIMIT 1',
                $table
            )
        );

        if (
            '' !== (string) $wpdb->last_error
            || !in_array(strtolower((string) $engine), ['innodb', 'xtradb'], true)
        ) {
            return new WP_Error(
                'nwmd_ai_state_import_transaction_unsupported',
                __('Controlled import requires transactional storage for every affected table.', 'local-directory-framework')
            );
        }
    }

    return true;
}

/**
 * Return Business index rows keyed by Business post ID.
 *
 * @param bool $lock Lock rows for controlled execution.
 *
 * @return array|WP_Error
 */
function nwmd_directory_get_ai_state_import_business_index($lock = false) {

    global $wpdb;

    $locking = $lock ? ' FOR UPDATE' : '';
    $rows = $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}nwmd_business_index
        ORDER BY id ASC{$locking}",
        ARRAY_A
    );

    if (null === $rows || '' !== (string) $wpdb->last_error) {
        return new WP_Error(
            'nwmd_ai_state_import_index_query_failed',
            __('The Business index could not be verified.', 'local-directory-framework')
        );
    }

    $index = [];

    foreach ($rows as $row) {
        $post_id = absint($row['business_post_id'] ?? 0);

        if (!isset($index[$post_id])) {
            $index[$post_id] = [];
        }

        $index[$post_id][] = $row;
    }

    return $index;
}

/**
 * Return all taxonomy relationships for Business posts.
 *
 * @param bool $lock Lock relationship rows for execution.
 *
 * @return array|WP_Error
 */
function nwmd_directory_get_ai_state_import_business_relationships(
    $lock = false
) {

    global $wpdb;

    $locking = $lock ? ' FOR UPDATE' : '';
    $rows = $wpdb->get_results(
        "SELECT
            relationships.object_id,
            relationships.term_taxonomy_id,
            relationships.term_order,
            taxonomy.taxonomy,
            terms.slug
        FROM {$wpdb->term_relationships} AS relationships
        INNER JOIN {$wpdb->term_taxonomy} AS taxonomy
            ON taxonomy.term_taxonomy_id = relationships.term_taxonomy_id
        INNER JOIN {$wpdb->terms} AS terms
            ON terms.term_id = taxonomy.term_id
        INNER JOIN {$wpdb->posts} AS posts
            ON posts.ID = relationships.object_id
        WHERE posts.post_type = 'nwmd_business'
        ORDER BY
            relationships.object_id ASC,
            taxonomy.taxonomy ASC,
            terms.slug ASC,
            relationships.term_taxonomy_id ASC{$locking}",
        ARRAY_A
    );

    if (null === $rows || '' !== (string) $wpdb->last_error) {
        return new WP_Error(
            'nwmd_ai_state_import_relationship_query_failed',
            __('Business taxonomy relationships could not be verified.', 'local-directory-framework')
        );
    }

    $relationships = [];

    foreach ($rows as $row) {
        $post_id = absint($row['object_id'] ?? 0);

        if (!isset($relationships[$post_id])) {
            $relationships[$post_id] = [];
        }

        $relationships[$post_id][] = $row;
    }

    return $relationships;
}

/**
 * Return a fresh authoritative importer state.
 *
 * @param bool $lock_rows Lock Business and source rows for execution.
 *
 * @return array|WP_Error
 */
function nwmd_directory_get_fresh_ai_state_import_state($lock_rows = false) {

    $business_posts = nwmd_directory_get_ai_state_import_business_posts(
        $lock_rows
    );
    $source_records = nwmd_directory_get_ai_state_import_source_records(
        $lock_rows
    );
    $business_index = nwmd_directory_get_ai_state_import_business_index(
        $lock_rows
    );
    $relationships = nwmd_directory_get_ai_state_import_business_relationships(
        $lock_rows
    );

    if (
        is_wp_error($business_posts)
        || is_wp_error($source_records)
        || is_wp_error($business_index)
        || is_wp_error($relationships)
    ) {
        return is_wp_error($business_posts)
            ? $business_posts
            : (
                is_wp_error($source_records)
                    ? $source_records
                    : (
                        is_wp_error($business_index)
                            ? $business_index
                            : $relationships
                    )
            );
    }

    $export = nwmd_directory_get_ai_state_export_records();

    if (is_wp_error($export)) {
        return $export;
    }

    $business_rows = nwmd_directory_normalize_ai_state_import_group(
        'businesses',
        (array) ($export['records']['businesses']['rows'] ?? [])
    );
    $source_rows = nwmd_directory_normalize_ai_state_import_group(
        'sources',
        (array) ($export['records']['sources']['rows'] ?? [])
    );

    if (is_wp_error($business_rows) || is_wp_error($source_rows)) {
        return is_wp_error($business_rows) ? $business_rows : $source_rows;
    }

    $businesses = nwmd_directory_project_ai_state_import_business_rows($business_rows);
    $sources = nwmd_directory_project_ai_state_import_source_rows($source_rows);
    $protected = nwmd_directory_get_ai_state_import_protected_fingerprints(
        (array) ($export['records'] ?? [])
    );
    $auxiliary = nwmd_directory_get_ai_state_import_auxiliary_fingerprint();

    foreach (
        [$businesses, $sources, $business_posts, $source_records, $protected, $auxiliary]
        as $value
    ) {
        if (is_wp_error($value)) {
            return $value;
        }
    }

    return [
        'export'             => $export,
        'export_fingerprint' => nwmd_directory_get_ai_state_import_fingerprint(
            (array) ($export['records'] ?? [])
        ),
        'businesses'         => $businesses,
        'sources'            => $sources,
        'business_posts'     => $business_posts,
        'source_records'     => $source_records,
        'business_index'     => $business_index,
        'relationships'      => $relationships,
        'protected'          => $protected,
        'auxiliary'          => $auxiliary,
    ];
}

/**
 * Return one uniquely indexed projection.
 *
 * @param array  $group Projected row group.
 * @param string $key   Portable identity.
 *
 * @return array|WP_Error
 */
function nwmd_directory_get_ai_state_import_unique_projection(
    array $group,
    $key
) {

    $matches = (array) ($group['index'][$key] ?? []);

    if (1 !== count($matches)) {
        return new WP_Error(
            'nwmd_ai_state_import_identity_not_unique',
            __('A current portable identity is no longer unique.', 'local-directory-framework')
        );
    }

    return (array) ($group['rows'][$matches[0]] ?? []);
}

/**
 * Verify optimistic locks and protected state before any write.
 *
 * @param array $plan  Approved plan.
 * @param array $state Fresh state.
 *
 * @return true|WP_Error
 */
function nwmd_directory_verify_ai_state_import_preconditions(
    array $plan,
    array $state
) {

    if (
        (array) ($state['protected'] ?? [])
            !== (array) ($plan['expected']['protected'] ?? [])
        || (string) ($state['auxiliary'] ?? '')
            !== (string) ($plan['expected']['auxiliary'] ?? '')
    ) {
        return new WP_Error(
            'nwmd_ai_state_import_protected_stale',
            __('Protected records changed after preparation.', 'local-directory-framework')
        );
    }

    foreach ((array) ($plan['expected']['businesses'] ?? []) as $slug => $expected) {
        $matches = (array) ($state['businesses']['index'][$slug] ?? []);
        $posts = (array) ($state['business_posts'][$slug] ?? []);

        if ('absent' === ($expected['state'] ?? '')) {
            if (!empty($matches) || !empty($posts)) {
                return new WP_Error(
                    'nwmd_ai_state_import_business_create_stale',
                    __('A new Business identity is no longer available.', 'local-directory-framework')
                );
            }

            continue;
        }

        if (1 !== count($matches) || 1 !== count($posts)) {
            return new WP_Error(
                'nwmd_ai_state_import_business_identity_stale',
                __('A Business identity changed after preparation.', 'local-directory-framework')
            );
        }

        $projection = (array) $state['businesses']['rows'][$matches[0]];
        $post = $posts[0];

        if (
            absint($post['id'] ?? 0) !== absint($expected['post_id'] ?? 0)
            || 'draft' !== (string) ($post['status'] ?? '')
            || (string) ($post['modified'] ?? '')
                !== (string) ($expected['lock_version'] ?? '')
            || nwmd_directory_get_ai_state_import_fingerprint($projection)
                !== (string) ($expected['fingerprint'] ?? '')
        ) {
            return new WP_Error(
                'nwmd_ai_state_import_business_stale',
                __('A Business changed after preparation.', 'local-directory-framework')
            );
        }
    }

    foreach ((array) ($plan['expected']['sources'] ?? []) as $key => $expected) {
        $matches = (array) ($state['sources']['index'][$key] ?? []);
        $records = (array) ($state['source_records'][$key] ?? []);

        if ('absent' === ($expected['state'] ?? '')) {
            if (!empty($matches) || !empty($records)) {
                return new WP_Error(
                    'nwmd_ai_state_import_source_create_stale',
                    __('A new Business Source identity is no longer available.', 'local-directory-framework')
                );
            }

            continue;
        }

        if (1 !== count($matches) || 1 !== count($records)) {
            return new WP_Error(
                'nwmd_ai_state_import_source_identity_stale',
                __('A Business Source identity changed after preparation.', 'local-directory-framework')
            );
        }

        $projection = (array) $state['sources']['rows'][$matches[0]];
        $record = $records[0];

        if (
            absint($record['id'] ?? 0) !== absint($expected['source_id'] ?? 0)
            || (string) ($record['updated_at'] ?? '')
                !== (string) ($expected['lock_version'] ?? '')
            || nwmd_directory_get_ai_state_import_fingerprint($projection)
                !== (string) ($expected['fingerprint'] ?? '')
        ) {
            return new WP_Error(
                'nwmd_ai_state_import_source_stale',
                __('A Business Source changed after preparation.', 'local-directory-framework')
            );
        }
    }

    return true;
}

/**
 * Validate a stored plan entirely from canonical projections.
 *
 * @param array $plan  Approved plan.
 * @param array $state Fresh pre-write state.
 *
 * @return true|WP_Error
 */
function nwmd_directory_validate_ai_state_import_execution_plan(
    array $plan,
    array $state
) {

    if (
        2 !== absint($plan['plan_version'] ?? 0)
        || empty($plan['executable'])
        || !empty($plan['issues'])
        || '3.2' !== (string) ($plan['format_version'] ?? '')
        || !preg_match('/^[a-f0-9]{64}$/', (string) ($plan['package_hash'] ?? ''))
    ) {
        return new WP_Error(
            'nwmd_ai_state_import_plan_not_executable',
            __('The prepared controlled-import plan is not executable.', 'local-directory-framework')
        );
    }

    $writes = (array) ($plan['writes'] ?? []);
    $write_count = 0;

    foreach (
        ['businesses_create', 'businesses_update', 'sources_create', 'sources_update']
        as $group
    ) {
        if (!isset($writes[$group]) || !is_array($writes[$group])) {
            return new WP_Error(
                'nwmd_ai_state_import_plan_structure_invalid',
                __('The controlled-import write plan is incomplete.', 'local-directory-framework')
            );
        }

        $write_count += count($writes[$group]);
    }

    if ($write_count < 1 || $write_count > 500) {
        return new WP_Error(
            'nwmd_ai_state_import_write_count_invalid',
            __('The controlled-import write count is invalid.', 'local-directory-framework')
        );
    }

    $business_schema = nwmd_directory_get_ai_state_import_business_projection_schema();
    $source_schema = nwmd_directory_get_ai_state_import_source_projection_schema();
    $business_keys = [];
    $source_keys = [];
    $business_writes = [];

    foreach (['businesses_create', 'businesses_update'] as $group) {
        $creating = 'businesses_create' === $group;

        foreach ($writes[$group] as $item) {
            $target = (array) ($item['target'] ?? []);
            $canonical = nwmd_directory_project_ai_state_import_business($target);
            $slug = (string) ($target['business_slug'] ?? '');

            if (
                is_wp_error($canonical)
                || $canonical !== $target
                || 'draft' !== ($target['post_status'] ?? '')
                || isset($business_keys[$slug])
            ) {
                return new WP_Error(
                    'nwmd_ai_state_import_business_plan_invalid',
                    __('A planned Business write is not canonical.', 'local-directory-framework')
                );
            }

            $business_keys[$slug] = true;
            $current = [];

            if (!$creating) {
                $current = nwmd_directory_get_ai_state_import_unique_projection(
                    (array) $state['businesses'],
                    $slug
                );

                if (is_wp_error($current)) {
                    return $current;
                }

                $protected_changes = nwmd_directory_get_ai_state_import_projection_changes(
                    $target,
                    $current,
                    $business_schema,
                    ['identity', 'safety', 'taxonomy']
                );

                if (!empty($protected_changes)) {
                    return new WP_Error(
                        'nwmd_ai_state_import_business_protected_change',
                        __('A planned Business protected field changed.', 'local-directory-framework')
                    );
                }
            } elseif ('0' !== (string) ($target['ranking_eligible'] ?? '')) {
                return new WP_Error(
                    'nwmd_ai_state_import_business_ranking_unsafe',
                    __('A new Business must be ranking-ineligible.', 'local-directory-framework')
                );
            }

            $roles = $creating
                ? ['identity', 'safety', 'taxonomy', 'writable']
                : ['writable'];
            $changes = nwmd_directory_get_ai_state_import_projection_changes(
                $target,
                $current,
                $business_schema,
                $roles
            );

            if ($changes !== (array) ($item['changed_fields'] ?? [])) {
                return new WP_Error(
                    'nwmd_ai_state_import_business_changes_invalid',
                    __('A planned Business change set is not canonical.', 'local-directory-framework')
                );
            }

            $terms = nwmd_directory_resolve_ai_state_import_terms($target, $creating);

            if (is_wp_error($terms)) {
                return $terms;
            }

            $business_writes[] = $item;
        }
    }

    foreach (['sources_create', 'sources_update'] as $group) {
        $creating = 'sources_create' === $group;

        foreach ($writes[$group] as $item) {
            $target = (array) ($item['target'] ?? []);
            $canonical = nwmd_directory_project_ai_state_import_source($target);
            $key = is_wp_error($canonical)
                ? ''
                : nwmd_directory_get_ai_state_import_source_identity_key($canonical);

            if (
                is_wp_error($canonical)
                || $canonical !== $target
                || isset($source_keys[$key])
            ) {
                return new WP_Error(
                    'nwmd_ai_state_import_source_plan_invalid',
                    __('A planned Business Source write is not canonical.', 'local-directory-framework')
                );
            }

            $source_keys[$key] = true;
            $slug = (string) $target['business_slug'];
            $posts = (array) ($state['business_posts'][$slug] ?? []);
            $business_is_created = isset($business_keys[$slug])
                && isset($plan['expected']['businesses'][$slug])
                && 'absent' === ($plan['expected']['businesses'][$slug]['state'] ?? '');

            if (
                !$business_is_created
                && (
                    1 !== count($posts)
                    || 'draft' !== (string) ($posts[0]['status'] ?? '')
                )
            ) {
                return new WP_Error(
                    'nwmd_ai_state_import_source_parent_invalid',
                    __('A planned Business Source parent is not an approved Draft.', 'local-directory-framework')
                );
            }

            $current = [];

            if (!$creating) {
                $current = nwmd_directory_get_ai_state_import_unique_projection(
                    (array) $state['sources'],
                    $key
                );

                if (is_wp_error($current)) {
                    return $current;
                }
            }

            $changes = nwmd_directory_get_ai_state_import_projection_changes(
                $target,
                $current,
                $source_schema,
                $creating ? ['identity', 'writable'] : ['writable']
            );

            if ($changes !== (array) ($item['changed_fields'] ?? [])) {
                return new WP_Error(
                    'nwmd_ai_state_import_source_changes_invalid',
                    __('A planned Business Source change set is not canonical.', 'local-directory-framework')
                );
            }
        }
    }

    return nwmd_directory_validate_ai_state_import_business_uniqueness(
        $business_writes,
        true
    );
}

/**
 * Convert a projected group to a unique, sorted identity map.
 *
 * @param array $group Projected group.
 *
 * @return array|WP_Error
 */
function nwmd_directory_get_ai_state_import_projection_map(array $group) {

    $map = [];

    foreach ((array) ($group['index'] ?? []) as $key => $matches) {
        if (1 !== count((array) $matches)) {
            return new WP_Error(
                'nwmd_ai_state_import_postwrite_duplicate',
                __('A portable identity became duplicated.', 'local-directory-framework')
            );
        }

        $map[$key] = (array) ($group['rows'][$matches[0]] ?? []);
    }

    ksort($map, SORT_STRING);

    return $map;
}

/**
 * Verify one derived Business index row against its canonical target.
 *
 * @param int   $post_id Business post ID.
 * @param array $target  Canonical Business target.
 * @param array $rows    Index rows for the Business.
 *
 * @return bool
 */
function nwmd_directory_ai_state_import_index_matches_target(
    $post_id,
    array $target,
    array $rows
) {

    if (1 !== count($rows)) {
        return false;
    }

    $row = $rows[0];
    $expected = [
        'business_post_id' => absint($post_id),
        'archived_at'      => '',
    ];
    $schema = nwmd_directory_get_ai_state_import_business_projection_schema();

    foreach ($schema as $field => $definition) {
        $column = (string) ($definition['index_column'] ?? '');

        if ('' === $column) {
            continue;
        }

        $expected[$column] = 'taxonomy' === ($definition['storage'] ?? '')
            ? nwmd_directory_get_business_term_id(
                $post_id,
                (string) $definition['taxonomy']
            )
            : ($target[$field] ?? '');
    }

    foreach ($expected as $field => $value) {
        if (in_array($field, ['latitude', 'longitude'], true)) {
            continue;
        }

        if ((string) ($row[$field] ?? '') !== (string) $value) {
            return false;
        }
    }

    foreach (['latitude', 'longitude'] as $field) {
        $target_value = '' === (string) $target[$field]
            ? null
            : (float) $target[$field];
        $row_value = null === ($row[$field] ?? null)
            ? null
            : (float) $row[$field];

        if ($target_value !== $row_value) {
            return false;
        }
    }

    return nwmd_directory_is_valid_ai_state_import_datetime(
        (string) ($row['created_at'] ?? ''),
        false
    ) && nwmd_directory_is_valid_ai_state_import_datetime(
        (string) ($row['updated_at'] ?? ''),
        false
    );
}

/**
 * Flatten authoritative source records by exact database ID.
 *
 * @param array $records Source records indexed by portable identity.
 *
 * @return array|WP_Error
 */
function nwmd_directory_get_ai_state_import_source_id_map(array $records) {

    $map = [];

    foreach ($records as $matches) {
        foreach ((array) $matches as $record) {
            $source_id = absint($record['id'] ?? 0);

            if ($source_id < 1 || isset($map[$source_id])) {
                return new WP_Error(
                    'nwmd_ai_state_import_source_id_duplicate',
                    __('A Business Source database identity is invalid.', 'local-directory-framework')
                );
            }

            $map[$source_id] = (array) ($record['row'] ?? []);
        }
    }

    ksort($map, SORT_NUMERIC);

    return $map;
}

/**
 * Return expected full relationships for one new canonical Business.
 *
 * @param int   $post_id Business post ID.
 * @param array $target  Canonical target.
 *
 * @return array|WP_Error
 */
function nwmd_directory_get_expected_ai_state_import_relationships(
    $post_id,
    array $target
) {

    global $wpdb;

    $schema = nwmd_directory_get_ai_state_import_business_projection_schema();
    $expected = [];

    foreach ($schema as $field => $definition) {
        if ('taxonomy' !== ($definition['storage'] ?? '')) {
            continue;
        }

        $taxonomy = (string) ($definition['taxonomy'] ?? '');
        $slugs = '' === (string) ($target[$field] ?? '')
            ? []
            : explode('|', (string) $target[$field]);

        foreach ($slugs as $slug) {
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT taxonomy.term_taxonomy_id, terms.slug
                    FROM {$wpdb->term_taxonomy} AS taxonomy
                    INNER JOIN {$wpdb->terms} AS terms
                        ON terms.term_id = taxonomy.term_id
                    WHERE taxonomy.taxonomy = %s
                        AND terms.slug = %s
                    LIMIT 1 FOR UPDATE",
                    $taxonomy,
                    $slug
                ),
                ARRAY_A
            );

            if (!is_array($row) || '' !== (string) $wpdb->last_error) {
                return new WP_Error(
                    'nwmd_ai_state_import_relationship_target_missing',
                    __('A planned taxonomy relationship could not be verified.', 'local-directory-framework')
                );
            }

            $expected[] = [
                'object_id'        => (string) absint($post_id),
                'term_taxonomy_id' => (string) absint($row['term_taxonomy_id'] ?? 0),
                'term_order'       => '0',
                'taxonomy'         => $taxonomy,
                'slug'             => (string) ($row['slug'] ?? ''),
            ];
        }
    }

    usort(
        $expected,
        static function ($left, $right) {
            return [
                (string) ($left['taxonomy'] ?? ''),
                (string) ($left['slug'] ?? ''),
                absint($left['term_taxonomy_id'] ?? 0),
            ] <=> [
                (string) ($right['taxonomy'] ?? ''),
                (string) ($right['slug'] ?? ''),
                absint($right['term_taxonomy_id'] ?? 0),
            ];
        }
    );

    return $expected;
}

/**
 * Verify that affected Business storage changed only through the projection.
 *
 * @param array $journal          Exact rollback journal.
 * @param array $business_results Applied Business items.
 *
 * @return true|WP_Error
 */
function nwmd_directory_verify_ai_state_import_business_write_scope(
    array $journal,
    array $business_results
) {

    $items = [];

    foreach ($business_results as $item) {
        $items[absint($item['post_id'] ?? 0)] = $item;
    }

    $schema = nwmd_directory_get_ai_state_import_business_projection_schema();
    $all_meta_keys = [];

    foreach ($schema as $definition) {
        if ('meta' === ($definition['storage'] ?? '')) {
            $all_meta_keys[(string) $definition['key']] = true;
        }
    }

    foreach ((array) ($journal['businesses'] ?? []) as $post_id => $entry) {
        if (empty($entry['touched']) || !isset($items[absint($post_id)])) {
            continue;
        }

        $after = $entry['after'] ?? null;

        if (!is_array($after)) {
            return new WP_Error(
                'nwmd_ai_state_import_business_after_missing',
                __('An affected Business post-write snapshot is missing.', 'local-directory-framework')
            );
        }

        if ('created' === ($entry['kind'] ?? '')) {
            foreach ((array) ($after['meta'] ?? []) as $row) {
                if (!isset($all_meta_keys[(string) ($row['meta_key'] ?? '')])) {
                    return new WP_Error(
                        'nwmd_ai_state_import_business_hook_meta',
                        __('A hook added unapproved metadata to a new Business.', 'local-directory-framework')
                    );
                }
            }

            continue;
        }

        $before = $entry['before'] ?? null;

        if (!is_array($before)) {
            return new WP_Error(
                'nwmd_ai_state_import_business_before_missing',
                __('An affected Business pre-write snapshot is missing.', 'local-directory-framework')
            );
        }

        $allowed_post = [
            'post_modified'     => true,
            'post_modified_gmt' => true,
        ];
        $allowed_meta = [];

        foreach ((array) ($items[$post_id]['changed_fields'] ?? []) as $field => $change) {
            unset($change);
            $definition = (array) ($schema[$field] ?? []);

            if ('post' === ($definition['storage'] ?? '')) {
                $allowed_post[(string) $definition['column']] = true;
            } elseif ('meta' === ($definition['storage'] ?? '')) {
                $allowed_meta[(string) $definition['key']] = true;
            }
        }

        foreach ((array) $before['post'] as $column => $value) {
            if (
                !isset($allowed_post[$column])
                && ($after['post'][$column] ?? null) !== $value
            ) {
                return new WP_Error(
                    'nwmd_ai_state_import_business_post_scope',
                    __('An unapproved Business post field changed.', 'local-directory-framework')
                );
            }
        }

        if ((array) $before['terms'] !== (array) $after['terms']) {
            return new WP_Error(
                'nwmd_ai_state_import_business_terms_scope',
                __('An existing Business taxonomy relationship changed.', 'local-directory-framework')
            );
        }

        $before_meta = [];
        $after_meta = [];

        foreach ((array) $before['meta'] as $row) {
            $before_meta[(string) ($row['meta_key'] ?? '')][] = $row;
        }

        foreach ((array) $after['meta'] as $row) {
            $after_meta[(string) ($row['meta_key'] ?? '')][] = $row;
        }

        foreach (array_unique(array_merge(array_keys($before_meta), array_keys($after_meta))) as $key) {
            if (
                !isset($allowed_meta[$key])
                && (array) ($before_meta[$key] ?? [])
                    !== (array) ($after_meta[$key] ?? [])
            ) {
                return new WP_Error(
                    'nwmd_ai_state_import_business_meta_scope',
                    __('Unapproved Business metadata changed.', 'local-directory-framework')
                );
            }
        }
    }

    return true;
}

/**
 * Verify all intended and protected state before commit.
 *
 * @param array $plan             Approved plan.
 * @param array $before           Pre-write state.
 * @param array $after            Post-write state.
 * @param array $business_results Applied Business items with final IDs.
 * @param array $source_results   Applied source items with final IDs.
 * @param array $journal          Exact affected-record journal.
 *
 * @return true|WP_Error
 */
function nwmd_directory_verify_ai_state_import_postwrite(
    array $plan,
    array $before,
    array $after,
    array $business_results,
    array $source_results,
    array $journal
) {

    if (
        (array) $after['protected'] !== (array) $before['protected']
        || (string) $after['auxiliary'] !== (string) $before['auxiliary']
    ) {
        return new WP_Error(
            'nwmd_ai_state_import_protected_changed',
            __('A protected record changed during controlled import.', 'local-directory-framework')
        );
    }

    $write_scope = nwmd_directory_verify_ai_state_import_business_write_scope(
        $journal,
        $business_results
    );

    if (is_wp_error($write_scope)) {
        return $write_scope;
    }

    $before_businesses = nwmd_directory_get_ai_state_import_projection_map(
        (array) $before['businesses']
    );
    $after_businesses = nwmd_directory_get_ai_state_import_projection_map(
        (array) $after['businesses']
    );
    $before_sources = nwmd_directory_get_ai_state_import_projection_map(
        (array) $before['sources']
    );
    $after_sources = nwmd_directory_get_ai_state_import_projection_map(
        (array) $after['sources']
    );

    foreach ([$before_businesses, $after_businesses, $before_sources, $after_sources] as $map) {
        if (is_wp_error($map)) {
            return $map;
        }
    }

    $expected_businesses = $before_businesses;

    foreach ($business_results as $item) {
        $slug = (string) ($item['target']['business_slug'] ?? '');
        $expected_businesses[$slug] = (array) ($item['target'] ?? []);
    }

    $expected_sources = $before_sources;

    foreach ($source_results as $item) {
        $target = (array) ($item['target'] ?? []);
        $key = nwmd_directory_get_ai_state_import_source_identity_key($target);
        $expected_sources[$key] = $target;
    }

    ksort($expected_businesses, SORT_STRING);
    ksort($expected_sources, SORT_STRING);

    if ($after_businesses !== $expected_businesses || $after_sources !== $expected_sources) {
        return new WP_Error(
            'nwmd_ai_state_import_postwrite_mismatch',
            __('Authoritative post-write records do not match the approved plan.', 'local-directory-framework')
        );
    }

    $expected_index_count = 0;

    foreach ((array) $before['business_index'] as $rows) {
        $expected_index_count += count((array) $rows);
    }

    foreach ($business_results as $item) {
        $post_id = absint($item['post_id'] ?? 0);

        if (empty($before['business_index'][$post_id])) {
            $expected_index_count++;
        }
    }
    $after_index_count = 0;

    foreach ((array) $after['business_index'] as $rows) {
        $after_index_count += count((array) $rows);
    }

    if ($after_index_count !== $expected_index_count) {
        return new WP_Error(
            'nwmd_ai_state_import_index_count_changed',
            __('The Business index record count changed unexpectedly.', 'local-directory-framework')
        );
    }

    $affected_post_ids = [];

    foreach ($business_results as $item) {
        $affected_post_ids[absint($item['post_id'] ?? 0)] = true;
    }

    foreach ((array) $before['business_index'] as $post_id => $rows) {
        if (
            !isset($affected_post_ids[absint($post_id)])
            && (array) ($after['business_index'][$post_id] ?? []) !== (array) $rows
        ) {
            return new WP_Error(
                'nwmd_ai_state_import_index_unexpected_change',
                __('An unrelated Business index row changed during import.', 'local-directory-framework')
            );
        }
    }

    foreach ((array) $before['relationships'] as $post_id => $rows) {
        if (
            !isset($affected_post_ids[absint($post_id)])
            && (array) ($after['relationships'][$post_id] ?? []) !== (array) $rows
        ) {
            return new WP_Error(
                'nwmd_ai_state_import_relationship_unexpected_change',
                __('An unrelated Business taxonomy relationship changed during import.', 'local-directory-framework')
            );
        }
    }

    $before_source_ids = nwmd_directory_get_ai_state_import_source_id_map(
        (array) $before['source_records']
    );
    $after_source_ids = nwmd_directory_get_ai_state_import_source_id_map(
        (array) $after['source_records']
    );

    if (is_wp_error($before_source_ids) || is_wp_error($after_source_ids)) {
        return is_wp_error($before_source_ids)
            ? $before_source_ids
            : $after_source_ids;
    }

    if (
        count($after_source_ids)
        !== count($before_source_ids)
            + count((array) $plan['writes']['sources_create'])
    ) {
        return new WP_Error(
            'nwmd_ai_state_import_source_count_changed',
            __('The Business Source record count changed unexpectedly.', 'local-directory-framework')
        );
    }

    $affected_source_ids = [];

    foreach ($source_results as $item) {
        $affected_source_ids[absint($item['source_id'] ?? 0)] = true;
    }

    foreach ($before_source_ids as $source_id => $row) {
        if (
            !isset($affected_source_ids[$source_id])
            && (array) ($after_source_ids[$source_id] ?? []) !== $row
        ) {
            return new WP_Error(
                'nwmd_ai_state_import_source_unexpected_change',
                __('An unrelated Business Source row changed during import.', 'local-directory-framework')
            );
        }
    }

    foreach ($business_results as $item) {
        $slug = (string) ($item['target']['business_slug'] ?? '');
        $posts = (array) ($after['business_posts'][$slug] ?? []);
        $post_id = absint($item['post_id'] ?? 0);
        $before_index = (array) ($before['business_index'][$post_id] ?? []);
        $after_index = (array) ($after['business_index'][$post_id] ?? []);
        $index_identity_preserved = empty($before_index)
            || (
                1 === count($before_index)
                && 1 === count($after_index)
                && (string) ($before_index[0]['id'] ?? '')
                    === (string) ($after_index[0]['id'] ?? '')
                && (string) ($before_index[0]['created_at'] ?? '')
                    === (string) ($after_index[0]['created_at'] ?? '')
            );
        $before_relationships = (array) ($before['relationships'][$post_id] ?? []);
        $after_relationships = (array) ($after['relationships'][$post_id] ?? []);
        $expected_relationships = empty($before_relationships)
            && 'absent' === (
                $plan['expected']['businesses'][$slug]['state'] ?? ''
            )
                ? nwmd_directory_get_expected_ai_state_import_relationships(
                    $post_id,
                    (array) ($item['target'] ?? [])
                )
                : $before_relationships;

        if (
            is_wp_error($expected_relationships)
            || $after_relationships !== $expected_relationships
            || 1 !== count($posts)
            || absint($posts[0]['id'] ?? 0) !== $post_id
            || 'draft' !== (string) ($posts[0]['status'] ?? '')
            || !nwmd_directory_is_valid_ai_state_import_datetime(
                (string) ($posts[0]['modified'] ?? ''),
                false
            )
            || (
                '' !== (string) ($item['lock_version'] ?? '')
                && (string) ($posts[0]['modified'] ?? '')
                    === (string) $item['lock_version']
            )
            || !$index_identity_preserved
            || !nwmd_directory_ai_state_import_index_matches_target(
                $post_id,
                (array) ($item['target'] ?? []),
                $after_index
            )
        ) {
            return new WP_Error(
                'nwmd_ai_state_import_business_postwrite_unsafe',
                __('An affected Business did not remain one exact Draft.', 'local-directory-framework')
            );
        }
    }

    foreach ($source_results as $item) {
        $target = (array) ($item['target'] ?? []);
        $key = nwmd_directory_get_ai_state_import_source_identity_key($target);
        $records = (array) ($after['source_records'][$key] ?? []);
        $slug = (string) ($target['business_slug'] ?? '');
        $posts = (array) ($after['business_posts'][$slug] ?? []);
        $source_id = absint($item['source_id'] ?? 0);
        $before_row = (array) ($before_source_ids[$source_id] ?? []);
        $after_row = (array) ($after_source_ids[$source_id] ?? []);
        $created_at = (string) ($after_row['created_at'] ?? '');
        $updated_at = (string) ($after_row['updated_at'] ?? '');

        if (
            1 !== count($records)
            || absint($records[0]['id'] ?? 0) !== $source_id
            || 1 !== count($posts)
            || 'draft' !== (string) ($posts[0]['status'] ?? '')
            || absint($records[0]['business_post_id'] ?? 0)
                !== absint($posts[0]['id'] ?? 0)
            || !nwmd_directory_is_valid_ai_state_import_datetime($created_at, false)
            || !nwmd_directory_is_valid_ai_state_import_datetime($updated_at, false)
            || (
                !empty($before_row)
                && (string) ($before_row['created_at'] ?? '') !== $created_at
            )
            || (
                !empty($before_row)
                && (string) ($before_row['updated_at'] ?? '') === $updated_at
            )
        ) {
            return new WP_Error(
                'nwmd_ai_state_import_source_postwrite_unsafe',
                __('An affected Business Source did not remain uniquely linked to one Draft.', 'local-directory-framework')
            );
        }

        $conflicts = nwmd_directory_get_ai_state_import_source_conflicts(
            absint($posts[0]['id']),
            $target,
            $source_id,
            true
        );

        if (is_wp_error($conflicts)) {
            return $conflicts;
        }

        if (!empty($conflicts['exact']) || !empty($conflicts['semantic'])) {
            return new WP_Error(
                'nwmd_ai_state_import_source_postwrite_duplicate',
                __('A Business Source semantic duplicate was created.', 'local-directory-framework')
            );
        }
    }

    return nwmd_directory_validate_ai_state_import_business_uniqueness(
        $business_results,
        true
    );
}

/**
 * Return whether the complete authoritative baseline was restored.
 *
 * @param array $baseline Pre-write state.
 * @param array $journal  Exact affected-record journal.
 *
 * @return bool
 */
function nwmd_directory_ai_state_import_baseline_is_restored(
    array $baseline,
    array $journal = []
) {

    $current = nwmd_directory_get_fresh_ai_state_import_state();

    if (
        is_wp_error($current)
        || (string) ($current['export_fingerprint'] ?? '')
            !== (string) ($baseline['export_fingerprint'] ?? '')
        || (string) ($current['auxiliary'] ?? '')
            !== (string) ($baseline['auxiliary'] ?? '')
        || (array) ($current['business_index'] ?? [])
            !== (array) ($baseline['business_index'] ?? [])
        || (array) ($current['relationships'] ?? [])
            !== (array) ($baseline['relationships'] ?? [])
    ) {
        return false;
    }

    foreach ((array) ($journal['sources'] ?? []) as $source_id => $entry) {
        $snapshot = nwmd_directory_snapshot_ai_state_import_source($source_id);

        if ('created' === ($entry['kind'] ?? '')) {
            if (
                !is_wp_error($snapshot)
                || 'nwmd_ai_state_import_source_snapshot_missing'
                    !== $snapshot->get_error_code()
            ) {
                return false;
            }
        } elseif (
            is_wp_error($snapshot)
            || $snapshot !== ($entry['before'] ?? null)
        ) {
            return false;
        }
    }

    foreach ((array) ($journal['businesses'] ?? []) as $post_id => $entry) {
        $snapshot = nwmd_directory_snapshot_ai_state_import_business($post_id);

        if ('created' === ($entry['kind'] ?? '')) {
            if (
                !is_wp_error($snapshot)
                || 'nwmd_ai_state_import_business_snapshot_missing'
                    !== $snapshot->get_error_code()
            ) {
                return false;
            }
        } elseif (
            is_wp_error($snapshot)
            || $snapshot !== ($entry['before'] ?? null)
        ) {
            return false;
        }
    }

    return true;
}

/**
 * Execute one consumed controlled-import plan.
 *
 * @param array $plan Approved plan.
 *
 * @return array Safe operational result.
 */
function nwmd_directory_execute_ai_state_import_plan(array $plan) {

    global $wpdb;

    $transaction = false;
    $writes_started = false;
    $baseline = [];
    $journal = ['businesses' => [], 'sources' => []];
    $business_results = [];
    $source_results = [];
    $failure_code = 'nwmd_ai_state_import_failed';

    try {
        nwmd_directory_require_ai_state_import_result(
            nwmd_directory_validate_ai_state_import_transaction_tables()
        );

        if (false === $wpdb->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ')) {
            throw new RuntimeException('nwmd_ai_state_import_isolation_failed');
        }

        if (false === $wpdb->query('START TRANSACTION')) {
            throw new RuntimeException('nwmd_ai_state_import_transaction_failed');
        }

        $transaction = true;
        $baseline = nwmd_directory_require_ai_state_import_result(
            nwmd_directory_get_fresh_ai_state_import_state(true)
        );
        nwmd_directory_require_ai_state_import_result(
            nwmd_directory_verify_ai_state_import_preconditions($plan, $baseline)
        );
        nwmd_directory_require_ai_state_import_result(
            nwmd_directory_validate_ai_state_import_execution_plan($plan, $baseline)
        );
        $journal = nwmd_directory_require_ai_state_import_result(
            nwmd_directory_initialize_ai_state_import_journal($plan)
        );

        $business_ids = [];

        foreach ((array) $plan['writes']['businesses_update'] as $item) {
            $writes_started = true;
            nwmd_directory_require_ai_state_import_result(
                nwmd_directory_apply_ai_state_import_business_update($item, $journal)
            );
            $item['post_id'] = absint($item['post_id']);
            $business_ids[(string) $item['business_slug']] = $item['post_id'];
            $business_results[] = $item;
        }

        foreach ((array) $plan['writes']['businesses_create'] as $item) {
            $writes_started = true;
            $post_id = nwmd_directory_require_ai_state_import_result(
                nwmd_directory_apply_ai_state_import_business_create($item, $journal)
            );
            $item['post_id'] = absint($post_id);
            $business_ids[(string) $item['business_slug']] = absint($post_id);
            $business_results[] = $item;
        }

        foreach (['sources_update', 'sources_create'] as $group) {
            foreach ((array) $plan['writes'][$group] as $item) {
                $slug = (string) ($item['business_slug'] ?? '');
                $business_id = absint($business_ids[$slug] ?? 0);

                if ($business_id < 1) {
                    $posts = (array) ($baseline['business_posts'][$slug] ?? []);
                    $business_id = 1 === count($posts)
                        ? absint($posts[0]['id'] ?? 0)
                        : 0;
                }

                if ($business_id < 1) {
                    throw new RuntimeException('nwmd_ai_state_import_source_parent_missing');
                }

                $writes_started = true;

                if ('sources_update' === $group) {
                    nwmd_directory_require_ai_state_import_result(
                        nwmd_directory_apply_ai_state_import_source_update($item, $journal)
                    );
                    $item['source_id'] = absint($item['source_id']);
                } else {
                    $item['source_id'] = absint(
                        nwmd_directory_require_ai_state_import_result(
                            nwmd_directory_apply_ai_state_import_source_create(
                                $item,
                                $business_id,
                                $journal
                            )
                        )
                    );
                }

                $source_results[] = $item;
            }
        }

        nwmd_directory_refresh_ai_state_import_journal_after($journal);
        $after = nwmd_directory_require_ai_state_import_result(
            nwmd_directory_get_fresh_ai_state_import_state(true)
        );
        nwmd_directory_require_ai_state_import_result(
            nwmd_directory_verify_ai_state_import_postwrite(
                $plan,
                $baseline,
                $after,
                $business_results,
                $source_results,
                $journal
            )
        );

        if (false === $wpdb->query('COMMIT')) {
            throw new RuntimeException('nwmd_ai_state_import_commit_failed');
        }

        $transaction = false;

        return [
            'success'         => true,
            'phase'           => 'execute',
            'message'         => __('The controlled AI State import completed successfully.', 'local-directory-framework'),
            'rollback_result' => 'not_required',
            'counts'          => [
                'businesses_created' => count((array) $plan['writes']['businesses_create']),
                'businesses_updated' => count((array) $plan['writes']['businesses_update']),
                'sources_created'    => count((array) $plan['writes']['sources_create']),
                'sources_updated'    => count((array) $plan['writes']['sources_update']),
            ],
        ];
    } catch (Throwable $throwable) {
        $throwable_code = (string) $throwable->getMessage();
        $failure_code = preg_match(
            '/^nwmd_[a-z0-9_]{1,90}$/',
            $throwable_code
        )
            ? $throwable_code
            : 'nwmd_ai_state_import_exception';

        if ($transaction && $writes_started) {
            nwmd_directory_refresh_ai_state_import_journal_after($journal);
        }

        if ($transaction) {
            $wpdb->query('ROLLBACK');
            $transaction = false;
        }

        foreach ((array) ($journal['businesses'] ?? []) as $post_id => $entry) {
            unset($entry);
            nwmd_directory_clean_ai_state_import_business_cache($post_id);
        }

        $rollback = 'not_required';

        if ($writes_started && !empty($baseline)) {
            if (nwmd_directory_ai_state_import_baseline_is_restored($baseline, $journal)) {
                $rollback = 'transaction_restored';
            } else {
                $journal_restored = nwmd_directory_run_ai_state_import_journal_rollback($journal);
                $rollback = $journal_restored
                    && nwmd_directory_ai_state_import_baseline_is_restored($baseline, $journal)
                        ? 'journal_restored'
                        : 'rollback_failed';
            }
        }

        nwmd_directory_log_ai_state_import_failure(
            $failure_code,
            (string) ($plan['package_hash'] ?? ''),
            $rollback
        );

        return [
            'success'         => false,
            'phase'           => 'execute',
            'message'         => 'rollback_failed' === $rollback
                ? __('The controlled import failed and automatic rollback could not be fully verified. Stop and inspect the database before retrying.', 'local-directory-framework')
                : __('The controlled import was stopped safely and no approved data changes were retained.', 'local-directory-framework'),
            'rollback_result' => $rollback,
            'counts'          => [],
        ];
    }
}

/**
 * Handle one authenticated, one-time controlled import approval.
 */
function nwmd_directory_handle_ai_state_import_execute() {

    if (!nwmd_directory_controlled_ai_state_import_is_enabled()) {
        wp_die(
            esc_html__('Controlled AI State Import is disabled.', 'local-directory-framework'),
            esc_html__('Access denied', 'local-directory-framework'),
            ['response' => 403]
        );
    }

    if (!current_user_can('manage_options')) {
        wp_die(
            esc_html__('You are not allowed to execute AI State imports.', 'local-directory-framework'),
            esc_html__('Access denied', 'local-directory-framework'),
            ['response' => 403]
        );
    }

    $plan_id = isset($_POST['plan_id'])
        ? sanitize_text_field(wp_unslash($_POST['plan_id']))
        : '';

    if (
        !preg_match(
            '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i',
            $plan_id
        )
    ) {
        wp_die(
            esc_html__('The controlled-import approval is invalid.', 'local-directory-framework'),
            esc_html__('Invalid request', 'local-directory-framework'),
            ['response' => 400]
        );
    }

    check_admin_referer(
        'nwmd_directory_execute_controlled_ai_state_import_' . $plan_id
    );
    $lock = nwmd_directory_acquire_ai_state_import_lock();

    if (is_wp_error($lock)) {
        nwmd_directory_store_ai_state_import_result(
            [
                'success'         => false,
                'phase'           => 'execute',
                'message'         => $lock->get_error_message(),
                'rollback_result' => 'not_required',
                'counts'          => [],
            ]
        );
        nwmd_directory_redirect_ai_state_import('ai_state_import_result');
    }

    try {
        $consumed_key = nwmd_directory_get_ai_state_import_consumed_key($plan_id);
        $plan = nwmd_directory_get_ai_state_import_plan();

        if (
            get_transient($consumed_key)
            || empty($plan)
            || !hash_equals((string) ($plan['plan_id'] ?? ''), $plan_id)
        ) {
            $result = [
                'success'         => false,
                'phase'           => 'execute',
                'message'         => __('The prepared import plan is missing, expired, or already used.', 'local-directory-framework'),
                'rollback_result' => 'not_required',
                'counts'          => [],
            ];
        } elseif (
            !set_transient(
                $consumed_key,
                1,
                nwmd_directory_get_ai_state_import_plan_ttl()
            )
        ) {
            $result = [
                'success'         => false,
                'phase'           => 'execute',
                'message'         => __('The one-time import approval could not be secured.', 'local-directory-framework'),
                'rollback_result' => 'not_required',
                'counts'          => [],
            ];
        } else {
            nwmd_directory_delete_ai_state_import_plan();
            $result = nwmd_directory_execute_ai_state_import_plan($plan);
        }
    } catch (Throwable $throwable) {
        unset($throwable);
        $result = [
            'success'         => false,
            'phase'           => 'execute',
            'message'         => __('The controlled import could not be executed safely.', 'local-directory-framework'),
            'rollback_result' => 'not_required',
            'counts'          => [],
        ];
    } finally {
        nwmd_directory_release_ai_state_import_lock($lock);
    }

    nwmd_directory_store_ai_state_import_result($result);
    nwmd_directory_redirect_ai_state_import('ai_state_import_result');
}
