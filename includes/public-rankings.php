<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return the public Top 10 rankings URL.
 *
 * @param array $arguments Optional query arguments.
 *
 * @return string
 */
function nwmd_directory_get_public_rankings_url(
    $arguments = []
) {

    $url = home_url(
        '/top-businesses/'
    );

    if (!empty($arguments)) {
        $url = add_query_arg(
            $arguments,
            $url
        );
    }

    return $url;
}

/**
 * Register the public Top 10 rankings route.
 */
function nwmd_directory_register_public_rankings_rewrite() {

    add_rewrite_rule(
        '^top-businesses/?$',
        'index.php?nwmd_public_rankings=1',
        'top'
    );
}

add_action(
    'init',
    'nwmd_directory_register_public_rankings_rewrite',
    15
);

/**
 * Register the public rankings query variable.
 *
 * @param array $query_vars Public query variables.
 *
 * @return array
 */
function nwmd_directory_register_public_rankings_query_var(
    $query_vars
) {

    $query_vars[] = 'nwmd_public_rankings';

    return $query_vars;
}

add_filter(
    'query_vars',
    'nwmd_directory_register_public_rankings_query_var'
);

/**
 * Return whether the current request is the public rankings page.
 *
 * @return bool
 */
function nwmd_directory_is_public_rankings_page() {

    return '1' === (string) get_query_var(
        'nwmd_public_rankings'
    );
}

/**
 * Use the plugin public rankings template unless the theme overrides it.
 *
 * @param string $template Current template path.
 *
 * @return string
 */
function nwmd_directory_public_rankings_template_include(
    $template
) {

    if (!nwmd_directory_is_public_rankings_page()) {
        return $template;
    }

    $theme_template = locate_template(
        ['public-rankings.php']
    );

    if (!empty($theme_template)) {
        return $theme_template;
    }

    $plugin_template = NWMD_DIRECTORY_PATH
        . 'templates/public-rankings.php';

    return is_readable($plugin_template)
        ? $plugin_template
        : $template;
}

add_filter(
    'template_include',
    'nwmd_directory_public_rankings_template_include',
    30
);

/**
 * Send a successful response for the virtual rankings page.
 */
function nwmd_directory_prepare_public_rankings_page() {

    if (!nwmd_directory_is_public_rankings_page()) {
        return;
    }

    global $wp_query;

    if ($wp_query instanceof WP_Query) {
        $wp_query->is_404 = false;
    }

    status_header(200);
}

add_action(
    'template_redirect',
    'nwmd_directory_prepare_public_rankings_page',
    5
);

/**
 * Set the public rankings document title.
 *
 * @param array $title_parts Document title parts.
 *
 * @return array
 */
function nwmd_directory_public_rankings_document_title(
    $title_parts
) {

    if (!nwmd_directory_is_public_rankings_page()) {
        return $title_parts;
    }

    $title_parts['title'] = __(
        'Top Local Businesses',
        'local-directory-framework'
    );

    return $title_parts;
}

add_filter(
    'document_title_parts',
    'nwmd_directory_public_rankings_document_title'
);

/**
 * Return the newest published ranking period.
 *
 * @return object|null
 */
function nwmd_directory_get_current_published_ranking_period() {

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
                published_at
            FROM {$periods_table}
            WHERE status = %s
                AND published_at IS NOT NULL
            ORDER BY published_at DESC, period_key DESC
            LIMIT 1",
            'published'
        )
    );

    return is_object($period)
        ? $period
        : null;
}

/**
 * Return a sanitized public ranking-filter slug.
 *
 * @param string $key Query-string key.
 *
 * @return string
 */
function nwmd_directory_get_public_ranking_filter_value($key) {

    $allowed_keys = [
        'ranking_state',
        'ranking_city',
        'ranking_category',
        'ranking_specialty',
    ];

    if (
        !in_array($key, $allowed_keys, true) ||
        !isset($_GET[$key]) ||
        !is_string($_GET[$key])
    ) {
        return '';
    }

    return sanitize_title(
        wp_unslash($_GET[$key])
    );
}

/**
 * Return published ranking terms used by one period.
 *
 * @param int    $period_id Ranking period ID.
 * @param string $taxonomy Taxonomy name.
 *
 * @return array
 */
function nwmd_directory_get_public_ranking_terms(
    $period_id,
    $taxonomy
) {

    $columns = [
        'nwmd_state'     => 'state_term_id',
        'nwmd_city'      => 'city_term_id',
        'nwmd_category'  => 'category_term_id',
        'nwmd_specialty' => 'specialty_term_id',
    ];

    if (
        !isset($columns[$taxonomy]) ||
        !taxonomy_exists($taxonomy)
    ) {
        return [];
    }

    global $wpdb;

    $rankings_table = $wpdb->prefix
        . 'nwmd_ranking_entries';

    $column = $columns[$taxonomy];

    $term_ids = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT DISTINCT {$column}
            FROM {$rankings_table}
            WHERE ranking_period_id = %d
                AND published_at IS NOT NULL
                AND {$column} > 0",
            absint($period_id)
        )
    );

    $term_ids = array_values(
        array_filter(
            array_map(
                'absint',
                $term_ids
            )
        )
    );

    if (empty($term_ids)) {
        return [];
    }

    $terms = get_terms(
        [
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
            'include'    => $term_ids,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ]
    );

    return is_wp_error($terms)
        ? []
        : $terms;
}

/**
 * Return validated public ranking selections.
 *
 * @return array
 */
function nwmd_directory_get_public_ranking_selection() {

    $slugs = [
        'state' => nwmd_directory_get_public_ranking_filter_value(
            'ranking_state'
        ),
        'city' => nwmd_directory_get_public_ranking_filter_value(
            'ranking_city'
        ),
        'category' => nwmd_directory_get_public_ranking_filter_value(
            'ranking_category'
        ),
        'specialty' => nwmd_directory_get_public_ranking_filter_value(
            'ranking_specialty'
        ),
    ];

    $taxonomies = [
        'state'     => 'nwmd_state',
        'city'      => 'nwmd_city',
        'category'  => 'nwmd_category',
        'specialty' => 'nwmd_specialty',
    ];

    $terms = [
        'state'     => null,
        'city'      => null,
        'category'  => null,
        'specialty' => null,
    ];

    $invalid = false;

    foreach ($taxonomies as $key => $taxonomy) {

        if ('' === $slugs[$key]) {
            continue;
        }

        $term = get_term_by(
            'slug',
            $slugs[$key],
            $taxonomy
        );

        if (!$term instanceof WP_Term) {
            $invalid = true;
            continue;
        }

        $terms[$key] = $term;
    }

    if (
        $terms['state'] instanceof WP_Term &&
        $terms['city'] instanceof WP_Term
    ) {
        $city_state_term_id = absint(
            get_term_meta(
                $terms['city']->term_id,
                'nwmd_state_term_id',
                true
            )
        );

        if (
            $city_state_term_id !==
            absint($terms['state']->term_id)
        ) {
            $invalid = true;
        }
    }

    $ready = (
        !$invalid &&
        $terms['state'] instanceof WP_Term &&
        $terms['city'] instanceof WP_Term &&
        $terms['category'] instanceof WP_Term
    );

    return [
        'slugs'   => $slugs,
        'terms'   => $terms,
        'invalid' => $invalid,
        'ready'   => $ready,
    ];
}

/**
 * Return no more than 10 published ranking entries.
 *
 * @param int   $period_id Ranking period ID.
 * @param array $selection Validated ranking selection.
 *
 * @return array
 */
function nwmd_directory_get_public_ranking_entries(
    $period_id,
    $selection
) {

    if (
        empty($selection['ready']) ||
        empty($selection['terms']) ||
        !($selection['terms']['state'] instanceof WP_Term) ||
        !($selection['terms']['city'] instanceof WP_Term) ||
        !($selection['terms']['category'] instanceof WP_Term)
    ) {
        return [];
    }

    $specialty_term_id = (
        $selection['terms']['specialty'] instanceof WP_Term
    )
        ? absint(
            $selection['terms']['specialty']->term_id
        )
        : 0;

    global $wpdb;

    $rankings_table = $wpdb->prefix
        . 'nwmd_ranking_entries';

    $posts_table = $wpdb->posts;

    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT
                ranking.id,
                ranking.business_post_id,
                ranking.rank_position,
                ranking.editorial_note,
                ranking.published_at
            FROM {$rankings_table} AS ranking
            INNER JOIN {$posts_table} AS business
                ON business.ID = ranking.business_post_id
                AND business.post_type = %s
                AND business.post_status = %s
            WHERE ranking.ranking_period_id = %d
                AND ranking.state_term_id = %d
                AND ranking.city_term_id = %d
                AND ranking.category_term_id = %d
                AND ranking.specialty_term_id = %d
                AND ranking.published_at IS NOT NULL
            ORDER BY ranking.rank_position ASC
            LIMIT 10",
            'nwmd_business',
            'publish',
            absint($period_id),
            absint($selection['terms']['state']->term_id),
            absint($selection['terms']['city']->term_id),
            absint($selection['terms']['category']->term_id),
            $specialty_term_id
        )
    );
}
