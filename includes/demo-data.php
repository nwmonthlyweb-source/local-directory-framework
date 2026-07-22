<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Find a directory term by slug.
 *
 * @param string $slug     Term slug.
 * @param string $taxonomy Taxonomy name.
 *
 * @return int
 */
function nwmd_directory_get_term_id_by_slug($slug, $taxonomy) {

    $term = get_term_by(
        'slug',
        $slug,
        $taxonomy
    );

    if (!$term instanceof WP_Term) {
        return 0;
    }

    return (int) $term->term_id;
}

/**
 * Create one fictional demo business when it does not already exist.
 *
 * Demo businesses remain drafts and are never published automatically.
 *
 * @param array $business Business data.
 *
 * @return int
 */
function nwmd_directory_create_demo_business($business) {

    $existing = get_page_by_path(
        $business['slug'],
        OBJECT,
        'nwmd_business'
    );

    if ($existing instanceof WP_Post) {
        return (int) $existing->ID;
    }

    $post_id = wp_insert_post(
        [
            'post_type'    => 'nwmd_business',
            'post_status'  => 'draft',
            'post_title'   => $business['title'],
            'post_name'    => $business['slug'],
            'post_content' => $business['description'],
            'post_excerpt' => $business['excerpt'],
            'meta_input'   => [
                'nwmd_legal_name'  => $business['legal_name'],
                'nwmd_demo_record' => 1,
            ],
        ],
        true
    );

    if (is_wp_error($post_id)) {
        return 0;
    }

    $taxonomy_terms = [
        'nwmd_category'  => $business['category'],
        'nwmd_specialty' => $business['specialty'],
        'nwmd_state'     => $business['state'],
        'nwmd_city'      => $business['city'],
    ];

    foreach ($taxonomy_terms as $taxonomy => $slug) {

        $term_id = nwmd_directory_get_term_id_by_slug(
            $slug,
            $taxonomy
        );

        if ($term_id > 0) {
            wp_set_object_terms(
                $post_id,
                [$term_id],
                $taxonomy,
                false
            );
        }
    }

    return (int) $post_id;
}

/**
 * Install demo specialties and fictional Portland business drafts.
 */
function nwmd_directory_install_demo_data() {

    $specialties = [
        'General Contractor'    => 'general-contractor',
        'Roofing'               => 'roofing',
        'Plumbing'              => 'plumbing',
        'Electrical'            => 'electrical',
        'HVAC'                  => 'hvac',
        'Kitchen Remodeling'    => 'kitchen-remodeling',
        'Bathroom Remodeling'   => 'bathroom-remodeling',
        'Residential Realtor'   => 'residential-realtor',
        'First-Time Buyers'     => 'first-time-buyers',
        'Luxury Homes'          => 'luxury-homes',
        'Investment Properties' => 'investment-properties',
        'Italian Restaurant'    => 'italian-restaurant',
        'Coffee Shop'           => 'coffee-shop',
        'Family Dining'         => 'family-dining',
    ];

    foreach ($specialties as $name => $slug) {
        nwmd_directory_get_or_create_term(
            $name,
            $slug,
            'nwmd_specialty'
        );
    }

    $businesses = [
        [
            'title'       => 'Rose City Home Advisors',
            'legal_name'  => 'Rose City Home Advisors LLC',
            'slug'        => 'demo-rose-city-home-advisors',
            'description' => 'Fictional demonstration profile for a Portland residential real estate business.',
            'excerpt'     => 'Demo Portland residential real estate profile.',
            'category'    => 'realtors',
            'specialty'   => 'residential-realtor',
            'state'       => 'oregon',
            'city'        => 'portland',
        ],
        [
            'title'       => 'Bridgeview Realty Group',
            'legal_name'  => 'Bridgeview Realty Group LLC',
            'slug'        => 'demo-bridgeview-realty-group',
            'description' => 'Fictional demonstration profile focused on helping first-time home buyers in Portland.',
            'excerpt'     => 'Demo Portland first-time buyer real estate profile.',
            'category'    => 'realtors',
            'specialty'   => 'first-time-buyers',
            'state'       => 'oregon',
            'city'        => 'portland',
        ],
        [
            'title'       => 'Portland Neighborhood Realty',
            'legal_name'  => 'Portland Neighborhood Realty LLC',
            'slug'        => 'demo-portland-neighborhood-realty',
            'description' => 'Fictional demonstration profile for Portland residential and investment property services.',
            'excerpt'     => 'Demo Portland investment property real estate profile.',
            'category'    => 'realtors',
            'specialty'   => 'investment-properties',
            'state'       => 'oregon',
            'city'        => 'portland',
        ],
    ];

    foreach ($businesses as $business) {
        nwmd_directory_create_demo_business($business);
    }
}