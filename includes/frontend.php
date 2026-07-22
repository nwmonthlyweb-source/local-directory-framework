<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Load directory frontend styles only where they are needed.
 */
function nwmd_directory_enqueue_frontend_assets() {

    if (
        !is_post_type_archive('nwmd_business') &&
        !is_singular('nwmd_business')
    ) {
        return;
    }

    wp_enqueue_style(
        'nwmd-directory-frontend',
        NWMD_DIRECTORY_URL . 'assets/css/frontend.css',
        [],
        NWMD_DIRECTORY_VERSION
    );
}

add_action(
    'wp_enqueue_scripts',
    'nwmd_directory_enqueue_frontend_assets'
);

/**
 * Use plugin templates unless the active theme provides an exact override.
 *
 * @param string $template Current WordPress template path.
 *
 * @return string
 */
function nwmd_directory_template_include($template) {

    if (is_post_type_archive('nwmd_business')) {

        $theme_template = locate_template(
            ['archive-nwmd_business.php']
        );

        if (!empty($theme_template)) {
            return $theme_template;
        }

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
function nwmd_directory_get_business_term_names($post_id, $taxonomy) {

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