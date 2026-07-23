<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return one ranking period.
 *
 * @param int $period_id Ranking period ID.
 *
 * @return object|null
 */
function nwmd_directory_get_ranking_period_by_id($period_id) {

    global $wpdb;

    $periods_table = $wpdb->prefix
        . 'nwmd_ranking_periods';

    $period = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT
                id,
                period_key,
                period_label,
                status,
                published_at,
                created_at,
                updated_at
            FROM {$periods_table}
            WHERE id = %d
            LIMIT 1",
            $period_id
        )
    );

    return is_object($period)
        ? $period
        : null;
}

/**
 * Determine whether ranking entries may be changed.
 *
 * Published and archived periods remain immutable.
 *
 * @param object|null $period Ranking period record.
 *
 * @return bool
 */
function nwmd_directory_ranking_period_is_editable($period) {

    if (!is_object($period)) {
        return false;
    }

    return in_array(
        $period->status,
        [
            'draft',
            'review',
        ],
        true
    );
}

/**
 * Redirect to a selected ranking period with a notice.
 *
 * @param string $notice   Notice identifier.
 * @param int    $period_id Ranking period ID.
 */
function nwmd_directory_redirect_ranking_entry_admin(
    $notice,
    $period_id
) {

    $url = add_query_arg(
        [
            'post_type'    => 'nwmd_business',
            'page'         => 'nwmd-monthly-rankings',
            'period_id'    => absint($period_id),
            'nwmd_notice'  => sanitize_key($notice),
        ],
        admin_url('edit.php')
    );

    wp_safe_redirect($url);
    exit;
}

/**
 * Return a validated taxonomy term.
 *
 * @param int    $term_id  Term ID.
 * @param string $taxonomy Taxonomy name.
 *
 * @return WP_Term|null
 */
function nwmd_directory_get_valid_ranking_term(
    $term_id,
    $taxonomy
) {

    if ($term_id < 1) {
        return null;
    }

    $term = get_term(
        $term_id,
        $taxonomy
    );

    if (
        is_wp_error($term) ||
        !$term instanceof WP_Term
    ) {
        return null;
    }

    return $term;
}

/**
 * Return a validated business post.
 *
 * @param int $business_post_id Business post ID.
 *
 * @return WP_Post|null
 */
function nwmd_directory_get_valid_ranking_business(
    $business_post_id
) {

    $business = get_post(
        $business_post_id
    );

    if (
        !$business instanceof WP_Post ||
        'nwmd_business' !== $business->post_type ||
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
 * Save one draft or review ranking entry.
 */
function nwmd_directory_save_ranking_entry() {

    if (!current_user_can('manage_options')) {
        wp_die(
            esc_html__(
                'You are not allowed to manage ranking entries.',
                'local-directory-framework'
            )
        );
    }

    check_admin_referer(
        'nwmd_save_ranking_entry',
        'nwmd_ranking_entry_nonce'
    );

    $period_id = isset($_POST['ranking_period_id'])
        ? absint($_POST['ranking_period_id'])
        : 0;

    $period = nwmd_directory_get_ranking_period_by_id(
        $period_id
    );

    if (
        !$period ||
        !nwmd_directory_ranking_period_is_editable($period)
    ) {
        nwmd_directory_redirect_ranking_entry_admin(
            'ranking-period-locked',
            $period_id
        );
    }

    $state_term_id = isset($_POST['state_term_id'])
        ? absint($_POST['state_term_id'])
        : 0;

    $city_term_id = isset($_POST['city_term_id'])
        ? absint($_POST['city_term_id'])
        : 0;

    $category_term_id = isset($_POST['category_term_id'])
        ? absint($_POST['category_term_id'])
        : 0;

    $specialty_term_id = isset($_POST['specialty_term_id'])
        ? absint($_POST['specialty_term_id'])
        : 0;

    $business_post_id = isset($_POST['business_post_id'])
        ? absint($_POST['business_post_id'])
        : 0;

    $rank_position = isset($_POST['rank_position'])
        ? absint($_POST['rank_position'])
        : 0;

    $editorial_score_raw = isset($_POST['editorial_score'])
        ? sanitize_text_field(
            wp_unslash($_POST['editorial_score'])
        )
        : '';

    $editorial_note = isset($_POST['editorial_note'])
        ? sanitize_textarea_field(
            wp_unslash($_POST['editorial_note'])
        )
        : '';

    $evidence_summary = isset($_POST['evidence_summary'])
        ? sanitize_textarea_field(
            wp_unslash($_POST['evidence_summary'])
        )
        : '';

    $state = nwmd_directory_get_valid_ranking_term(
        $state_term_id,
        'nwmd_state'
    );

    $city = nwmd_directory_get_valid_ranking_term(
        $city_term_id,
        'nwmd_city'
    );

    $category = nwmd_directory_get_valid_ranking_term(
        $category_term_id,
        'nwmd_category'
    );

    $specialty = null;

    if ($specialty_term_id > 0) {
        $specialty = nwmd_directory_get_valid_ranking_term(
            $specialty_term_id,
            'nwmd_specialty'
        );
    }

    $business = nwmd_directory_get_valid_ranking_business(
        $business_post_id
    );

    if (
        !$state ||
        !$city ||
        !$category ||
        (
            $specialty_term_id > 0 &&
            !$specialty
        ) ||
        !$business
    ) {
        nwmd_directory_redirect_ranking_entry_admin(
            'invalid-ranking-entry',
            $period_id
        );
    }

    $city_state_term_id = absint(
        get_term_meta(
            $city_term_id,
            'nwmd_state_term_id',
            true
        )
    );

    if ($city_state_term_id !== $state_term_id) {
        nwmd_directory_redirect_ranking_entry_admin(
            'city-state-mismatch',
            $period_id
        );
    }

    if (
        !has_term(
            $state_term_id,
            'nwmd_state',
            $business_post_id
        ) ||
        !has_term(
            $city_term_id,
            'nwmd_city',
            $business_post_id
        ) ||
        !has_term(
            $category_term_id,
            'nwmd_category',
            $business_post_id
        ) ||
        (
            $specialty_term_id > 0 &&
            !has_term(
                $specialty_term_id,
                'nwmd_specialty',
                $business_post_id
            )
        )
    ) {
        nwmd_directory_redirect_ranking_entry_admin(
            'business-classification-mismatch',
            $period_id
        );
    }

    if (
        $rank_position < 1 ||
        $rank_position > 10
    ) {
        nwmd_directory_redirect_ranking_entry_admin(
            'invalid-rank-position',
            $period_id
        );
    }

    if (
        '' === $editorial_score_raw ||
        !is_numeric($editorial_score_raw) ||
        (float) $editorial_score_raw < 0
    ) {
        nwmd_directory_redirect_ranking_entry_admin(
            'invalid-editorial-score',
            $period_id
        );
    }

    if ('' === $evidence_summary) {
        nwmd_directory_redirect_ranking_entry_admin(
            'evidence-required',
            $period_id
        );
    }

    $editorial_score = round(
        (float) $editorial_score_raw,
        2
    );

    global $wpdb;

    $rankings_table = $wpdb->prefix
        . 'nwmd_ranking_entries';

    $existing_position = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id
            FROM {$rankings_table}
            WHERE ranking_period_id = %d
                AND city_term_id = %d
                AND category_term_id = %d
                AND specialty_term_id = %d
                AND rank_position = %d
            LIMIT 1",
            $period_id,
            $city_term_id,
            $category_term_id,
            $specialty_term_id,
            $rank_position
        )
    );

    if (!empty($existing_position)) {
        nwmd_directory_redirect_ranking_entry_admin(
            'rank-position-exists',
            $period_id
        );
    }

    $existing_business = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id
            FROM {$rankings_table}
            WHERE ranking_period_id = %d
                AND city_term_id = %d
                AND category_term_id = %d
                AND specialty_term_id = %d
                AND business_post_id = %d
            LIMIT 1",
            $period_id,
            $city_term_id,
            $category_term_id,
            $specialty_term_id,
            $business_post_id
        )
    );

    if (!empty($existing_business)) {
        nwmd_directory_redirect_ranking_entry_admin(
            'business-already-ranked',
            $period_id
        );
    }

    $inserted = $wpdb->insert(
        $rankings_table,
        [
            'ranking_period_id' => $period_id,
            'state_term_id'     => $state_term_id,
            'city_term_id'      => $city_term_id,
            'category_term_id'  => $category_term_id,
            'specialty_term_id' => $specialty_term_id,
            'business_post_id'  => $business_post_id,
            'rank_position'     => $rank_position,
            'editorial_score'   => $editorial_score,
            'editorial_note'    => $editorial_note,
            'evidence_summary'  => $evidence_summary,
            'created_at'        => current_time('mysql'),
        ],
        [
            '%d',
            '%d',
            '%d',
            '%d',
            '%d',
            '%d',
            '%d',
            '%f',
            '%s',
            '%s',
            '%s',
        ]
    );

    if (false === $inserted) {
        nwmd_directory_redirect_ranking_entry_admin(
            'ranking-entry-save-failed',
            $period_id
        );
    }

    nwmd_directory_redirect_ranking_entry_admin(
        'ranking-entry-created',
        $period_id
    );
}

add_action(
    'admin_post_nwmd_save_ranking_entry',
    'nwmd_directory_save_ranking_entry'
);

/**
 * Delete one ranking entry from an editable period.
 */
function nwmd_directory_delete_ranking_entry() {

    if (!current_user_can('manage_options')) {
        wp_die(
            esc_html__(
                'You are not allowed to manage ranking entries.',
                'local-directory-framework'
            )
        );
    }

    $entry_id = isset($_GET['entry_id'])
        ? absint($_GET['entry_id'])
        : 0;

    check_admin_referer(
        'nwmd_delete_ranking_entry_' . $entry_id
    );

    global $wpdb;

    $rankings_table = $wpdb->prefix
        . 'nwmd_ranking_entries';

    $entry = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT
                id,
                ranking_period_id
            FROM {$rankings_table}
            WHERE id = %d
            LIMIT 1",
            $entry_id
        )
    );

    if (!is_object($entry)) {
        nwmd_directory_redirect_ranking_entry_admin(
            'ranking-entry-not-found',
            0
        );
    }

    $period_id = absint(
        $entry->ranking_period_id
    );

    $period = nwmd_directory_get_ranking_period_by_id(
        $period_id
    );

    if (
        !$period ||
        !nwmd_directory_ranking_period_is_editable($period)
    ) {
        nwmd_directory_redirect_ranking_entry_admin(
            'ranking-period-locked',
            $period_id
        );
    }

    $deleted = $wpdb->delete(
        $rankings_table,
        [
            'id' => $entry_id,
        ],
        [
            '%d',
        ]
    );

    if (false === $deleted) {
        nwmd_directory_redirect_ranking_entry_admin(
            'ranking-entry-delete-failed',
            $period_id
        );
    }

    nwmd_directory_redirect_ranking_entry_admin(
        'ranking-entry-deleted',
        $period_id
    );
}

add_action(
    'admin_post_nwmd_delete_ranking_entry',
    'nwmd_directory_delete_ranking_entry'
);