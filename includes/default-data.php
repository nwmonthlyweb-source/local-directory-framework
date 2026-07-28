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
 * Return the ordered launch specialties grouped by category.
 *
 * The public app uses this map for its service-selection screen.
 *
 * @return array
 */
function nwmd_directory_get_launch_specialties() {

    return [
        'restaurants' => [
            [
                'name' => 'American Restaurants',
                'slug' => 'american-restaurants',
            ],
            [
                'name' => 'Italian Restaurants',
                'slug' => 'italian-restaurants',
            ],
            [
                'name' => 'Mexican Restaurants',
                'slug' => 'mexican-restaurants',
            ],
            [
                'name' => 'Asian Restaurants',
                'slug' => 'asian-restaurants',
            ],
            [
                'name' => 'Coffee Shops & Cafes',
                'slug' => 'coffee-shops-cafes',
            ],
            [
                'name' => 'Bakeries',
                'slug' => 'bakeries',
            ],
            [
                'name' => 'Family Dining',
                'slug' => 'family-dining',
            ],
            [
                'name' => 'Bars & Pubs',
                'slug' => 'bars-pubs',
            ],
        ],
        'contractors' => [
            [
                'name' => 'General Contractors',
                'slug' => 'general-contractors',
            ],
            [
                'name' => 'Plumbers',
                'slug' => 'plumbers',
            ],
            [
                'name' => 'Roofers',
                'slug' => 'roofers',
            ],
            [
                'name' => 'Electricians',
                'slug' => 'electricians',
            ],
            [
                'name' => 'HVAC Contractors',
                'slug' => 'hvac-contractors',
            ],
            [
                'name' => 'Remodeling Contractors',
                'slug' => 'remodeling-contractors',
            ],
            [
                'name' => 'Painters',
                'slug' => 'painters',
            ],
            [
                'name' => 'Flooring Contractors',
                'slug' => 'flooring-contractors',
            ],
            [
                'name' => 'Concrete Contractors',
                'slug' => 'concrete-contractors',
            ],
            [
                'name' => 'Landscapers',
                'slug' => 'landscapers',
            ],
        ],
        'dentists' => [
            [
                'name' => 'General Dentistry',
                'slug' => 'general-dentistry',
            ],
            [
                'name' => 'Cosmetic Dentistry',
                'slug' => 'cosmetic-dentistry',
            ],
            [
                'name' => 'Pediatric Dentistry',
                'slug' => 'pediatric-dentistry',
            ],
            [
                'name' => 'Orthodontics',
                'slug' => 'orthodontics',
            ],
            [
                'name' => 'Oral Surgery',
                'slug' => 'oral-surgery',
            ],
            [
                'name' => 'Emergency Dentistry',
                'slug' => 'emergency-dentistry',
            ],
        ],
        'auto-services' => [
            [
                'name' => 'Auto Repair',
                'slug' => 'auto-repair',
            ],
            [
                'name' => 'Oil Change Services',
                'slug' => 'oil-change-services',
            ],
            [
                'name' => 'Tire Shops',
                'slug' => 'tire-shops',
            ],
            [
                'name' => 'Auto Body Shops',
                'slug' => 'auto-body-shops',
            ],
            [
                'name' => 'Towing Services',
                'slug' => 'towing-services',
            ],
            [
                'name' => 'Car Detailing',
                'slug' => 'car-detailing',
            ],
            [
                'name' => 'Transmission Repair',
                'slug' => 'transmission-repair',
            ],
        ],
        'health-wellness' => [
            [
                'name' => 'Primary Care',
                'slug' => 'primary-care',
            ],
            [
                'name' => 'Chiropractors',
                'slug' => 'chiropractors',
            ],
            [
                'name' => 'Physical Therapy',
                'slug' => 'physical-therapy',
            ],
            [
                'name' => 'Mental Health Services',
                'slug' => 'mental-health-services',
            ],
            [
                'name' => 'Massage Therapy',
                'slug' => 'massage-therapy',
            ],
            [
                'name' => 'Fitness & Gyms',
                'slug' => 'fitness-gyms',
            ],
            [
                'name' => 'Nutrition Services',
                'slug' => 'nutrition-services',
            ],
        ],
        'realtors' => [
            [
                'name' => 'Buyer Agents',
                'slug' => 'buyer-agents',
            ],
            [
                'name' => 'Seller Agents',
                'slug' => 'seller-agents',
            ],
            [
                'name' => 'Investment Property Firms',
                'slug' => 'investment-property-firms',
            ],
            [
                'name' => 'Property Management',
                'slug' => 'property-management',
            ],
            [
                'name' => 'Mortgage Lenders',
                'slug' => 'mortgage-lenders',
            ],
            [
                'name' => 'Title & Escrow Companies',
                'slug' => 'title-escrow-companies',
            ],
            [
                'name' => 'Home Inspectors',
                'slug' => 'home-inspectors',
            ],
            [
                'name' => 'Real Estate Appraisers',
                'slug' => 'real-estate-appraisers',
            ],
        ],
        'beauty-personal-care' => [
            [
                'name' => 'Hair Salons',
                'slug' => 'hair-salons',
            ],
            [
                'name' => 'Barbers',
                'slug' => 'barbers',
            ],
            [
                'name' => 'Nail Salons',
                'slug' => 'nail-salons',
            ],
            [
                'name' => 'Skin Care',
                'slug' => 'skin-care',
            ],
            [
                'name' => 'Spas',
                'slug' => 'spas',
            ],
            [
                'name' => 'Makeup Artists',
                'slug' => 'makeup-artists',
            ],
        ],
        'pet-services' => [
            [
                'name' => 'Veterinarians',
                'slug' => 'veterinarians',
            ],
            [
                'name' => 'Pet Grooming',
                'slug' => 'pet-grooming',
            ],
            [
                'name' => 'Dog Walking',
                'slug' => 'dog-walking',
            ],
            [
                'name' => 'Pet Boarding',
                'slug' => 'pet-boarding',
            ],
            [
                'name' => 'Pet Training',
                'slug' => 'pet-training',
            ],
            [
                'name' => 'Pet Sitting',
                'slug' => 'pet-sitting',
            ],
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
        nwmd_directory_get_launch_specialties()
        as $category_slug => $specialties
    ) {
        $category_term = get_term_by(
            'slug',
            $category_slug,
            'nwmd_category'
        );

        if (!$category_term instanceof WP_Term) {
            continue;
        }

        foreach ($specialties as $specialty) {
            $specialty_term_id =
                nwmd_directory_get_or_create_term(
                    $specialty['name'],
                    $specialty['slug'],
                    'nwmd_specialty'
                );

            if ($specialty_term_id < 1) {
                continue;
            }

            update_term_meta(
                $specialty_term_id,
                'nwmd_category_term_id',
                absint($category_term->term_id)
            );
        }
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
