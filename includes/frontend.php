<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Load only the public styles needed for the current screen.
 */
function nwmd_directory_enqueue_frontend_assets() {

    $is_app_home = is_front_page();

    $is_business_archive =
        is_post_type_archive('nwmd_business');

    $is_other_directory_page =
        is_singular('nwmd_business') ||
        nwmd_directory_is_business_request_page() ||
        nwmd_directory_is_public_rankings_page();

    if (
        !$is_app_home &&
        !$is_business_archive &&
        !$is_other_directory_page
    ) {
        return;
    }

    if ($is_app_home) {
        wp_enqueue_style(
            'nwmd-directory-app-home',
            NWMD_DIRECTORY_URL . 'assets/css/app-home.css',
            [],
            NWMD_DIRECTORY_VERSION
        );
    }

    if ($is_business_archive) {
        wp_enqueue_style(
            'nwmd-directory-app-list',
            NWMD_DIRECTORY_URL . 'assets/css/app-directory.css',
            [],
            NWMD_DIRECTORY_VERSION
        );
    }

    if ($is_other_directory_page) {
        wp_enqueue_style(
            'nwmd-directory-frontend',
            NWMD_DIRECTORY_URL . 'assets/css/frontend.css',
            [],
            NWMD_DIRECTORY_VERSION
        );
    }
}

add_action(
    'wp_enqueue_scripts',
    'nwmd_directory_enqueue_frontend_assets'
);

/**
 * Return the business archive URL.
 *
 * @return string
 */
function nwmd_directory_get_app_archive_url() {

    $archive_url = get_post_type_archive_link(
        'nwmd_business'
    );

    if (!$archive_url) {
        return home_url('/business/');
    }

    return $archive_url;
}

/**
 * Return the business archive URL for one category.
 *
 * @param string $category_slug Category slug.
 *
 * @return string
 */
function nwmd_directory_get_app_category_url(
    $category_slug
) {

    return add_query_arg(
        [
            'filter_category' => sanitize_title(
                $category_slug
            ),
        ],
        nwmd_directory_get_app_archive_url()
    );
}

/**
 * Return the business archive URL for one category and city.
 *
 * @param string $category_slug Category slug.
 * @param string $city_slug     City slug.
 *
 * @return string
 */
function nwmd_directory_get_app_city_url(
    $category_slug,
    $city_slug
) {

    return add_query_arg(
        [
            'filter_category' => sanitize_title(
                $category_slug
            ),
            'filter_city' => sanitize_title(
                $city_slug
            ),
        ],
        nwmd_directory_get_app_archive_url()
    );
}

/**
 * Use plugin-owned public templates.
 *
 * @param string $template Current WordPress template path.
 *
 * @return string
 */
function nwmd_directory_template_include($template) {

    if (is_front_page()) {
        $app_template = NWMD_DIRECTORY_PATH
            . 'templates/app-home.php';

        if (is_readable($app_template)) {
            return $app_template;
        }
    }

    if (is_post_type_archive('nwmd_business')) {
        $plugin_template = NWMD_DIRECTORY_PATH
            . 'templates/archive-nwmd_business.php';

        if (is_readable($plugin_template)) {
            return $plugin_template;
        }
    }

    if (is_singular('nwmd_business')) {

        $theme_template = locate_template(
            ['single-nwmd_business.php']
        );

        if (!empty($theme_template)) {
            return $theme_template;
        }

        $plugin_template = NWMD_DIRECTORY_PATH
            . 'templates/single-nwmd_business.php';

        if (is_readable($plugin_template)) {
            return $plugin_template;
        }
    }

    return $template;
}

add_filter(
    'template_include',
    'nwmd_directory_template_include'
);

/**
 * Return business term names.
 *
 * @param int    $post_id  Business post ID.
 * @param string $taxonomy Directory taxonomy.
 *
 * @return array
 */
function nwmd_directory_get_business_term_names(
    $post_id,
    $taxonomy
) {

    $terms = get_the_terms(
        $post_id,
        $taxonomy
    );

    if (is_wp_error($terms) || empty($terms)) {
        return [];
    }

    return array_values(
        wp_list_pluck(
            $terms,
            'name'
        )
    );
}

/**
 * Return one sanitized archive filter value.
 *
 * @param string $key Filter query-string key.
 *
 * @return string
 */
function nwmd_directory_get_archive_filter_value($key) {

    $allowed_keys = [
        'filter_category',
        'filter_specialty',
        'filter_city',
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
 * Return selectable terms that have published businesses.
 *
 * @param string $taxonomy Directory taxonomy.
 *
 * @return array
 */
function nwmd_directory_get_archive_filter_terms($taxonomy) {

    if (!taxonomy_exists($taxonomy)) {
        return [];
    }

    $terms = get_terms(
        [
            'taxonomy'   => $taxonomy,
            'hide_empty' => true,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ]
    );

    if (is_wp_error($terms)) {
        return [];
    }

    return $terms;
}

/**
 * Filter the public business archive.
 *
 * @param WP_Query $query Current WordPress query.
 */
function nwmd_directory_filter_archive_query($query) {

    if (
        is_admin() ||
        !$query->is_main_query() ||
        !$query->is_post_type_archive('nwmd_business')
    ) {
        return;
    }

    $filters = [
        'filter_category'  => 'nwmd_category',
        'filter_specialty' => 'nwmd_specialty',
        'filter_city'      => 'nwmd_city',
    ];

    $tax_query = [];

    foreach ($filters as $filter_key => $taxonomy) {

        $slug = nwmd_directory_get_archive_filter_value(
            $filter_key
        );

        if ('' === $slug) {
            continue;
        }

        $term = get_term_by(
            'slug',
            $slug,
            $taxonomy
        );

        if (!$term instanceof WP_Term) {
            continue;
        }

        $tax_query[] = [
            'taxonomy' => $taxonomy,
            'field'    => 'slug',
            'terms'    => [$slug],
        ];
    }

    if (empty($tax_query)) {
        return;
    }

    if (count($tax_query) > 1) {
        $tax_query['relation'] = 'AND';
    }

    $query->set(
        'tax_query',
        $tax_query
    );
}

add_action(
    'pre_get_posts',
    'nwmd_directory_filter_archive_query',
    20
);