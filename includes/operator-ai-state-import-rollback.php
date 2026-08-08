<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clear post, metadata, and taxonomy caches for one Business.
 *
 * @param int $post_id Business post ID.
 */
function nwmd_directory_clean_ai_state_import_business_cache($post_id) {

    $post_id = absint($post_id);
    clean_post_cache($post_id);
    clean_object_term_cache($post_id, 'nwmd_business');
}

/**
 * Return an exact, consistently ordered Business database snapshot.
 *
 * The canonical projection controls what the importer may write. The broader
 * snapshot detects and restores hook-induced changes to the same Business.
 *
 * @param int $post_id Business post ID.
 *
 * @return array|WP_Error
 */
function nwmd_directory_snapshot_ai_state_import_business($post_id) {

    global $wpdb;

    $post_id = absint($post_id);
    $post = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->posts} WHERE ID = %d LIMIT 1",
            $post_id
        ),
        ARRAY_A
    );

    if ('' !== (string) $wpdb->last_error) {
        return new WP_Error(
            'nwmd_ai_state_import_business_snapshot_failed',
            __('A complete Business rollback snapshot could not be read.', 'local-directory-framework')
        );
    }

    if (!is_array($post)) {
        return new WP_Error(
            'nwmd_ai_state_import_business_snapshot_missing',
            __('A Business rollback snapshot is unavailable.', 'local-directory-framework')
        );
    }

    $meta = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->postmeta}
            WHERE post_id = %d
            ORDER BY meta_id ASC",
            $post_id
        ),
        ARRAY_A
    );

    if (null === $meta || '' !== (string) $wpdb->last_error) {
        return new WP_Error(
            'nwmd_ai_state_import_business_snapshot_failed',
            __('A complete Business rollback snapshot could not be read.', 'local-directory-framework')
        );
    }

    $terms = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->term_relationships}
            WHERE object_id = %d
            ORDER BY term_taxonomy_id ASC, term_order ASC",
            $post_id
        ),
        ARRAY_A
    );

    if (null === $terms || '' !== (string) $wpdb->last_error) {
        return new WP_Error(
            'nwmd_ai_state_import_business_snapshot_failed',
            __('A complete Business rollback snapshot could not be read.', 'local-directory-framework')
        );
    }

    $index = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}nwmd_business_index
            WHERE business_post_id = %d
            ORDER BY id ASC",
            $post_id
        ),
        ARRAY_A
    );

    if (
        null === $index
        || '' !== (string) $wpdb->last_error
    ) {
        return new WP_Error(
            'nwmd_ai_state_import_business_snapshot_failed',
            __('A complete Business rollback snapshot could not be read.', 'local-directory-framework')
        );
    }

    return [
        'post'  => $post,
        'meta'  => array_values($meta),
        'terms' => array_values($terms),
        'index' => array_values($index),
    ];
}

/**
 * Return an exact Business Source row snapshot.
 *
 * @param int $source_id Source row ID.
 *
 * @return array|WP_Error
 */
function nwmd_directory_snapshot_ai_state_import_source($source_id) {

    global $wpdb;

    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}nwmd_business_sources
            WHERE id = %d LIMIT 1",
            absint($source_id)
        ),
        ARRAY_A
    );

    if ('' !== (string) $wpdb->last_error) {
        return new WP_Error(
            'nwmd_ai_state_import_source_snapshot_failed',
            __('A Business Source rollback snapshot could not be read.', 'local-directory-framework')
        );
    }

    if (!is_array($row)) {
        return new WP_Error(
            'nwmd_ai_state_import_source_snapshot_missing',
            __('A Business Source rollback snapshot is unavailable.', 'local-directory-framework')
        );
    }

    return $row;
}

/**
 * Initialize rollback state before the first write.
 *
 * @param array $plan Approved plan.
 *
 * @return array|WP_Error
 */
function nwmd_directory_initialize_ai_state_import_journal(array $plan) {

    $journal = [
        'businesses' => [],
        'sources'    => [],
    ];

    foreach ((array) ($plan['writes']['businesses_update'] ?? []) as $item) {
        $post_id = absint($item['post_id'] ?? 0);
        $before = nwmd_directory_snapshot_ai_state_import_business($post_id);

        if (is_wp_error($before)) {
            return $before;
        }

        $journal['businesses'][$post_id] = [
            'kind'    => 'existing',
            'slug'    => (string) ($item['business_slug'] ?? ''),
            'before'  => $before,
            'after'   => null,
            'touched' => false,
        ];
    }

    foreach ((array) ($plan['writes']['sources_update'] ?? []) as $item) {
        $source_id = absint($item['source_id'] ?? 0);
        $before = nwmd_directory_snapshot_ai_state_import_source($source_id);

        if (is_wp_error($before)) {
            return $before;
        }

        $journal['sources'][$source_id] = [
            'kind'             => 'existing',
            'business_post_id' => absint($before['business_post_id'] ?? 0),
            'before'           => $before,
            'after'            => null,
            'touched'          => false,
        ];
    }

    return $journal;
}

/**
 * Record one exact newly created Business ID.
 *
 * @param array  $journal Rollback journal.
 * @param int    $post_id Created post ID.
 * @param string $slug    Portable Business slug.
 */
function nwmd_directory_record_ai_state_import_created_business(
    array &$journal,
    $post_id,
    $slug
) {

    $post_id = absint($post_id);
    $journal['businesses'][$post_id] = [
        'kind'    => 'created',
        'slug'    => (string) $slug,
        'before'  => null,
        'after'   => null,
        'touched' => true,
    ];
}

/**
 * Record one exact newly created Business Source ID.
 *
 * @param array $journal    Rollback journal.
 * @param int   $source_id  Created source ID.
 * @param int   $business_id Parent Business ID.
 */
function nwmd_directory_record_ai_state_import_created_source(
    array &$journal,
    $source_id,
    $business_id
) {

    $source_id = absint($source_id);
    $journal['sources'][$source_id] = [
        'kind'             => 'created',
        'business_post_id' => absint($business_id),
        'before'           => null,
        'after'            => null,
        'touched'          => true,
    ];
}

/**
 * Mark an existing Business as touched.
 *
 * @param array $journal Rollback journal.
 * @param int   $post_id Business post ID.
 */
function nwmd_directory_mark_ai_state_import_business_touched(
    array &$journal,
    $post_id
) {

    $post_id = absint($post_id);

    if (isset($journal['businesses'][$post_id])) {
        $journal['businesses'][$post_id]['touched'] = true;
    }
}

/**
 * Mark an existing Business Source as touched.
 *
 * @param array $journal   Rollback journal.
 * @param int   $source_id Source ID.
 */
function nwmd_directory_mark_ai_state_import_source_touched(
    array &$journal,
    $source_id
) {

    $source_id = absint($source_id);

    if (isset($journal['sources'][$source_id])) {
        $journal['sources'][$source_id]['touched'] = true;
    }
}

/**
 * Refresh one post-write Business snapshot.
 *
 * @param array $journal Rollback journal.
 * @param int   $post_id Business post ID.
 *
 * @return true|WP_Error
 */
function nwmd_directory_refresh_ai_state_import_business_after(
    array &$journal,
    $post_id
) {

    $post_id = absint($post_id);
    $after = nwmd_directory_snapshot_ai_state_import_business($post_id);

    if (is_wp_error($after)) {
        return $after;
    }

    if (!isset($journal['businesses'][$post_id])) {
        return new WP_Error(
            'nwmd_ai_state_import_business_journal_missing',
            __('A Business rollback journal entry is missing.', 'local-directory-framework')
        );
    }

    $journal['businesses'][$post_id]['after'] = $after;
    $journal['businesses'][$post_id]['touched'] = true;

    return true;
}

/**
 * Refresh one post-write source snapshot.
 *
 * @param array $journal   Rollback journal.
 * @param int   $source_id Source ID.
 *
 * @return true|WP_Error
 */
function nwmd_directory_refresh_ai_state_import_source_after(
    array &$journal,
    $source_id
) {

    $source_id = absint($source_id);
    $after = nwmd_directory_snapshot_ai_state_import_source($source_id);

    if (is_wp_error($after)) {
        return $after;
    }

    if (!isset($journal['sources'][$source_id])) {
        return new WP_Error(
            'nwmd_ai_state_import_source_journal_missing',
            __('A Business Source rollback journal entry is missing.', 'local-directory-framework')
        );
    }

    $journal['sources'][$source_id]['after'] = $after;
    $journal['sources'][$source_id]['touched'] = true;

    return true;
}

/**
 * Refresh all post-write journal snapshots after an exception.
 *
 * @param array $journal Rollback journal.
 */
function nwmd_directory_refresh_ai_state_import_journal_after(
    array &$journal
) {

    foreach ((array) ($journal['sources'] ?? []) as $source_id => $entry) {
        if (empty($entry['touched'])) {
            continue;
        }

        $after = nwmd_directory_snapshot_ai_state_import_source($source_id);
        $journal['sources'][$source_id]['after'] = is_wp_error($after)
            ? null
            : $after;
    }

    foreach ((array) ($journal['businesses'] ?? []) as $post_id => $entry) {
        if (empty($entry['touched'])) {
            continue;
        }

        $after = nwmd_directory_snapshot_ai_state_import_business($post_id);
        $journal['businesses'][$post_id]['after'] = is_wp_error($after)
            ? null
            : $after;
    }
}

/**
 * Restore exact post metadata rows.
 *
 * @param int   $post_id Business post ID.
 * @param array $rows    Original rows.
 *
 * @return bool
 */
function nwmd_directory_restore_ai_state_import_postmeta(
    $post_id,
    array $rows
) {

    global $wpdb;

    if (false === $wpdb->delete($wpdb->postmeta, ['post_id' => absint($post_id)], ['%d'])) {
        return false;
    }

    foreach ($rows as $row) {
        if (
            false === $wpdb->insert(
                $wpdb->postmeta,
                [
                    'meta_id'    => absint($row['meta_id'] ?? 0),
                    'post_id'    => absint($post_id),
                    'meta_key'   => (string) ($row['meta_key'] ?? ''),
                    'meta_value' => $row['meta_value'] ?? null,
                ],
                ['%d', '%d', '%s', '%s']
            )
        ) {
            return false;
        }
    }

    return true;
}

/**
 * Restore exact taxonomy relationship rows without firing mutation hooks.
 *
 * @param int   $post_id Business post ID.
 * @param array $rows    Original relationships.
 *
 * @return bool
 */
function nwmd_directory_restore_ai_state_import_terms($post_id, array $rows) {

    global $wpdb;

    if (
        false === $wpdb->delete(
            $wpdb->term_relationships,
            ['object_id' => absint($post_id)],
            ['%d']
        )
    ) {
        return false;
    }

    foreach ($rows as $row) {
        if (
            false === $wpdb->insert(
                $wpdb->term_relationships,
                [
                    'object_id'        => absint($post_id),
                    'term_taxonomy_id' => absint($row['term_taxonomy_id'] ?? 0),
                    'term_order'       => absint($row['term_order'] ?? 0),
                ],
                ['%d', '%d', '%d']
            )
        ) {
            return false;
        }
    }

    return true;
}

/**
 * Recalculate counts for every relationship touched by guarded rollback.
 *
 * @param array $rows Relationship rows from before and after snapshots.
 *
 * @return bool
 */
function nwmd_directory_refresh_ai_state_import_term_counts(array $rows) {

    global $wpdb;

    $taxonomies = [];

    foreach ($rows as $row) {
        $term_taxonomy_id = absint($row['term_taxonomy_id'] ?? 0);

        if ($term_taxonomy_id < 1) {
            continue;
        }

        $taxonomy = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT taxonomy FROM {$wpdb->term_taxonomy}
                WHERE term_taxonomy_id = %d LIMIT 1 FOR UPDATE",
                $term_taxonomy_id
            )
        );

        if ('' === (string) $taxonomy || '' !== (string) $wpdb->last_error) {
            return false;
        }

        $taxonomies[(string) $taxonomy][$term_taxonomy_id] = true;
    }

    foreach ($taxonomies as $taxonomy => $term_ids) {
        wp_update_term_count(
            array_map('absint', array_keys($term_ids)),
            $taxonomy,
            true
        );

        if ('' !== (string) $wpdb->last_error) {
            return false;
        }
    }

    return true;
}

/**
 * Restore exact Business index rows.
 *
 * @param int   $post_id Business post ID.
 * @param array $rows    Original index rows.
 *
 * @return bool
 */
function nwmd_directory_restore_ai_state_import_index($post_id, array $rows) {

    global $wpdb;

    $table = $wpdb->prefix . 'nwmd_business_index';

    if (false === $wpdb->delete($table, ['business_post_id' => absint($post_id)], ['%d'])) {
        return false;
    }

    foreach ($rows as $row) {
        if (false === $wpdb->insert($table, $row)) {
            return false;
        }
    }

    return true;
}

/**
 * Restore one guarded existing source row.
 *
 * @param int   $source_id Source ID.
 * @param array $entry     Journal entry.
 *
 * @return bool
 */
function nwmd_directory_rollback_ai_state_import_existing_source(
    $source_id,
    array $entry
) {

    global $wpdb;

    $current = nwmd_directory_snapshot_ai_state_import_source($source_id);

    if (!is_wp_error($current) && $current === $entry['before']) {
        return true;
    }

    if (
        is_wp_error($current)
        || !is_array($entry['after'])
        || $current !== $entry['after']
    ) {
        return false;
    }

    if (
        false === $wpdb->replace(
            $wpdb->prefix . 'nwmd_business_sources',
            (array) $entry['before']
        )
    ) {
        return false;
    }

    $restored = nwmd_directory_snapshot_ai_state_import_source($source_id);

    return !is_wp_error($restored) && $restored === $entry['before'];
}

/**
 * Delete one exact guarded newly created source row.
 *
 * @param int   $source_id Source ID.
 * @param array $entry     Journal entry.
 *
 * @return bool
 */
function nwmd_directory_rollback_ai_state_import_created_source(
    $source_id,
    array $entry
) {

    global $wpdb;

    $current = nwmd_directory_snapshot_ai_state_import_source($source_id);

    if (is_wp_error($current)) {
        return 'nwmd_ai_state_import_source_snapshot_missing'
            === $current->get_error_code();
    }

    if (!is_array($entry['after']) || $current !== $entry['after']) {
        return false;
    }

    return 1 === $wpdb->delete(
        $wpdb->prefix . 'nwmd_business_sources',
        ['id' => absint($source_id)],
        ['%d']
    );
}

/**
 * Restore one guarded existing Business database state.
 *
 * @param int   $post_id Business post ID.
 * @param array $entry   Journal entry.
 *
 * @return bool
 */
function nwmd_directory_rollback_ai_state_import_existing_business(
    $post_id,
    array $entry
) {

    global $wpdb;

    $current = nwmd_directory_snapshot_ai_state_import_business($post_id);

    if (!is_wp_error($current) && $current === $entry['before']) {
        return true;
    }

    if (
        is_wp_error($current)
        || !is_array($entry['after'])
        || $current !== $entry['after']
    ) {
        return false;
    }

    $post = (array) $entry['before']['post'];
    unset($post['ID']);

    if (
        false === $wpdb->update(
            $wpdb->posts,
            $post,
            ['ID' => absint($post_id)]
        )
        || !nwmd_directory_restore_ai_state_import_postmeta(
            $post_id,
            (array) $entry['before']['meta']
        )
        || !nwmd_directory_restore_ai_state_import_terms(
            $post_id,
            (array) $entry['before']['terms']
        )
        || !nwmd_directory_refresh_ai_state_import_term_counts(
            array_merge(
                (array) $entry['before']['terms'],
                (array) $entry['after']['terms']
            )
        )
        || !nwmd_directory_restore_ai_state_import_index(
            $post_id,
            (array) $entry['before']['index']
        )
    ) {
        return false;
    }

    nwmd_directory_clean_ai_state_import_business_cache($post_id);
    $restored = nwmd_directory_snapshot_ai_state_import_business($post_id);

    return !is_wp_error($restored) && $restored === $entry['before'];
}

/**
 * Delete one exact guarded newly created Business and its owned rows.
 *
 * @param int   $post_id Business post ID.
 * @param array $entry   Journal entry.
 *
 * @return bool
 */
function nwmd_directory_rollback_ai_state_import_created_business(
    $post_id,
    array $entry
) {

    global $wpdb;

    $current = nwmd_directory_snapshot_ai_state_import_business($post_id);

    if (is_wp_error($current)) {
        return 'nwmd_ai_state_import_business_snapshot_missing'
            === $current->get_error_code();
    }

    if (!is_array($entry['after']) || $current !== $entry['after']) {
        return false;
    }

    $source_count = absint(
        $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}nwmd_business_sources
                WHERE business_post_id = %d",
                absint($post_id)
            )
        )
    );

    if ('' !== (string) $wpdb->last_error || 0 !== $source_count) {
        return false;
    }

    $deleted = nwmd_directory_restore_ai_state_import_index($post_id, [])
        && nwmd_directory_restore_ai_state_import_terms($post_id, [])
        && nwmd_directory_refresh_ai_state_import_term_counts(
            (array) $entry['after']['terms']
        )
        && nwmd_directory_restore_ai_state_import_postmeta($post_id, [])
        && 1 === $wpdb->delete(
            $wpdb->posts,
            ['ID' => absint($post_id)],
            ['%d']
        );

    nwmd_directory_clean_ai_state_import_business_cache($post_id);

    return $deleted;
}

/**
 * Run guarded journal rollback without broad identity deletes.
 *
 * @param array $journal Rollback journal.
 *
 * @return bool
 */
function nwmd_directory_run_ai_state_import_journal_rollback(
    array $journal
) {

    global $wpdb;

    if (false === $wpdb->query('START TRANSACTION')) {
        return false;
    }

    try {
        foreach (array_reverse((array) ($journal['sources'] ?? []), true) as $source_id => $entry) {
            if (empty($entry['touched'])) {
                continue;
            }

            $restored = 'created' === ($entry['kind'] ?? '')
                ? nwmd_directory_rollback_ai_state_import_created_source($source_id, $entry)
                : nwmd_directory_rollback_ai_state_import_existing_source($source_id, $entry);

            if (!$restored) {
                throw new RuntimeException('nwmd_ai_state_import_source_rollback_failed');
            }
        }

        foreach (array_reverse((array) ($journal['businesses'] ?? []), true) as $post_id => $entry) {
            if (empty($entry['touched'])) {
                continue;
            }

            $restored = 'created' === ($entry['kind'] ?? '')
                ? nwmd_directory_rollback_ai_state_import_created_business($post_id, $entry)
                : nwmd_directory_rollback_ai_state_import_existing_business($post_id, $entry);

            if (!$restored) {
                throw new RuntimeException('nwmd_ai_state_import_business_rollback_failed');
            }
        }

        if (false === $wpdb->query('COMMIT')) {
            throw new RuntimeException('nwmd_ai_state_import_rollback_commit_failed');
        }

        return true;
    } catch (Throwable $throwable) {
        unset($throwable);
        $wpdb->query('ROLLBACK');

        return false;
    }
}
