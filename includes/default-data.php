<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return an existing term ID or create the term.
 *
 * @param string $name     Term name.
 * @param string $slug     Term slug.
 * @param string $taxonomy Taxonomy name.
 *
 * @return int
 */
function nwmd_directory_get_or_create_term($name, $slug, $taxonomy) {

    $existing_term = get_term_by(
        'slug',
        $slug,
        $taxonomy
    );

    if ($existing_term instanceof WP_Term) {
        return (int) $existing_term->term_id;
    }

    $created_term = wp_insert_term(
        $name,
        $taxonomy,
        [
            'slug' => $slug,
        ]
    );

    if (is_wp_error($created_term)) {
        return 0;
    }

    return (int) $created_term['term_id'];
}

/**
 * Install the initial Northwest Monthly directory terms.
 */
function nwmd_directory_install_default_terms() {

    $oregon_term_id = nwmd_directory_get_or_create_term(
        'Oregon',
        'oregon',
        'nwmd_state'
    );

    $categories = [
        'Contractors' => 'contractors',
        'Realtors'    => 'realtors',
        'Restaurants' => 'restaurants',
    ];

    foreach ($categories as $name => $slug) {
        nwmd_directory_get_or_create_term(
            $name,
            $slug,
            'nwmd_category'
        );
    }

    $cities = [
        'Portland'    => 'portland',
        'Beaverton'   => 'beaverton',
        'Hillsboro'   => 'hillsboro',
        'Lake Oswego' => 'lake-oswego',
        'Gresham'     => 'gresham',
    ];

    foreach ($cities as $name => $slug) {

        $city_term_id = nwmd_directory_get_or_create_term(
            $name,
            $slug,
            'nwmd_city'
        );

        if (
            $city_term_id > 0 &&
            $oregon_term_id > 0
        ) {
            update_term_meta(
                $city_term_id,
                'nwmd_state_term_id',
                $oregon_term_id
            );
        }
    }
}