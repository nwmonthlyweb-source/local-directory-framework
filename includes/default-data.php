<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return an existing term ID or create the term.
 *
 * Existing terms are never renamed or overwritten.
 *
 * @param string $name     Term name.
 * @param string $slug     Term slug.
 * @param string $taxonomy Taxonomy name.
 *
 * @return int
 */
function nwmd_directory_get_or_create_term(
    $name,
    $slug,
    $taxonomy
) {

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
 * Return the ordered NW Monthly launch categories.
 *
 * The public app uses this list for its category buttons.
 *
 * @return array
 */
function nwmd_directory_get_launch_categories() {

    return [
        [
            'name' => 'Restaurants',
            'slug' => 'restaurants',
        ],
        [
            'name' => 'Contractors',
            'slug' => 'contractors',
        ],
        [
            'name' => 'Dentists',
            'slug' => 'dentists',
        ],
        [
            'name' => 'Auto Services',
            'slug' => 'auto-services',
        ],
        [
            'name' => 'Health & Wellness',
            'slug' => 'health-wellness',
        ],
        [
            'name' => 'Real Estate',
            'slug' => 'realtors',
        ],
        [
            'name' => 'Beauty & Personal Care',
            'slug' => 'beauty-personal-care',
        ],
        [
            'name' => 'Pet Services',
            'slug' => 'pet-services',
        ],
    ];
}

/**
 * Return the ordered NW Monthly launch regions.
 *
 * @return array
 */
function nwmd_directory_get_launch_regions() {

    return [
        [
            'name'         => 'Washington',
            'abbreviation' => 'WA',
            'slug'         => 'washington',
            'cities'       => [
                [
                    'name' => 'Seattle',
                    'slug' => 'seattle',
                ],
                [
                    'name' => 'Spokane',
                    'slug' => 'spokane',
                ],
                [
                    'name' => 'Tacoma',
                    'slug' => 'tacoma',
                ],
                [
                    'name' => 'Vancouver',
                    'slug' => 'vancouver-wa',
                ],
                [
                    'name' => 'Bellevue',
                    'slug' => 'bellevue',
                ],
                [
                    'name' => 'Kent',
                    'slug' => 'kent',
                ],
                [
                    'name' => 'Everett',
                    'slug' => 'everett',
                ],
                [
                    'name' => 'Spokane Valley',
                    'slug' => 'spokane-valley',
                ],
            ],
        ],
        [
            'name'         => 'Oregon',
            'abbreviation' => 'OR',
            'slug'         => 'oregon',
            'cities'       => [
                [
                    'name' => 'Portland',
                    'slug' => 'portland',
                ],
                [
                    'name' => 'Salem',
                    'slug' => 'salem',
                ],
                [
                    'name' => 'Eugene',
                    'slug' => 'eugene',
                ],
                [
                    'name' => 'Gresham',
                    'slug' => 'gresham',
                ],
                [
                    'name' => 'Hillsboro',
                    'slug' => 'hillsboro',
                ],
                [
                    'name' => 'Bend',
                    'slug' => 'bend',
                ],
                [
                    'name' => 'Beaverton',
                    'slug' => 'beaverton',
                ],
                [
                    'name' => 'Medford',
                    'slug' => 'medford',
                ],
            ],
        ],
    ];
}

/**
 * Install the NW Monthly launch categories, states, and cities.
 *
 * Existing terms and business assignments are preserved.
 */
function nwmd_directory_install_default_terms() {

    foreach (
        nwmd_directory_get_launch_categories()
        as $category
    ) {
        nwmd_directory_get_or_create_term(
            $category['name'],
            $category['slug'],
            'nwmd_category'
        );
    }

    foreach (
        nwmd_directory_get_launch_regions()
        as $region
    ) {
        $state_term_id =
            nwmd_directory_get_or_create_term(
                $region['name'],
                $region['slug'],
                'nwmd_state'
            );

        if ($state_term_id < 1) {
            continue;
        }

        update_term_meta(
            $state_term_id,
            'nwmd_abbreviation',
            $region['abbreviation']
        );

        foreach ($region['cities'] as $city) {
            $city_term_id =
                nwmd_directory_get_or_create_term(
                    $city['name'],
                    $city['slug'],
                    'nwmd_city'
                );

            if ($city_term_id < 1) {
                continue;
            }

            update_term_meta(
                $city_term_id,
                'nwmd_state_term_id',
                $state_term_id
            );

            update_term_meta(
                $city_term_id,
                'nwmd_state_abbreviation',
                $region['abbreviation']
            );
        }
    }
}