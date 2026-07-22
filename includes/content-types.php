<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register directory businesses and classifications.
 */
function nwmd_directory_register_content_types() {

    register_post_type(
        'nwmd_business',
        [
            'labels' => [
                'name'               => 'Businesses',
                'singular_name'      => 'Business',
                'add_new'            => 'Add Business',
                'add_new_item'       => 'Add New Business',
                'edit_item'          => 'Edit Business',
                'new_item'           => 'New Business',
                'view_item'          => 'View Business',
                'search_items'       => 'Search Businesses',
                'not_found'          => 'No businesses found.',
                'not_found_in_trash' => 'No businesses found in Trash.',
                'all_items'          => 'All Businesses',
                'menu_name'          => 'Businesses',
            ],
            'public'             => true,
            'show_in_rest'       => true,
            'menu_icon'          => 'dashicons-store',
            'menu_position'      => 25,
            'supports'           => [
                'title',
                'editor',
                'excerpt',
                'thumbnail',
                'revisions',
            ],
            'has_archive'        => true,
            'rewrite'            => [
                'slug'       => 'business',
                'with_front' => false,
            ],
            'show_in_nav_menus'  => true,
            'exclude_from_search' => false,
        ]
    );

    register_taxonomy(
        'nwmd_category',
        ['nwmd_business'],
        [
            'labels' => [
                'name'          => 'Business Categories',
                'singular_name' => 'Business Category',
                'search_items'  => 'Search Business Categories',
                'all_items'     => 'All Business Categories',
                'edit_item'     => 'Edit Business Category',
                'update_item'   => 'Update Business Category',
                'add_new_item'  => 'Add Business Category',
                'new_item_name' => 'New Business Category',
                'menu_name'     => 'Categories',
            ],
            'public'            => true,
            'hierarchical'      => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'rewrite'           => [
                'slug'       => 'directory-category',
                'with_front' => false,
            ],
        ]
    );

    register_taxonomy(
        'nwmd_specialty',
        ['nwmd_business'],
        [
            'labels' => [
                'name'          => 'Specialties',
                'singular_name' => 'Specialty',
                'search_items'  => 'Search Specialties',
                'all_items'     => 'All Specialties',
                'edit_item'     => 'Edit Specialty',
                'update_item'   => 'Update Specialty',
                'add_new_item'  => 'Add Specialty',
                'new_item_name' => 'New Specialty',
                'menu_name'     => 'Specialties',
            ],
            'public'            => true,
            'hierarchical'      => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'rewrite'           => [
                'slug'       => 'directory-specialty',
                'with_front' => false,
            ],
        ]
    );

    register_taxonomy(
        'nwmd_state',
        ['nwmd_business'],
        [
            'labels' => [
                'name'          => 'States',
                'singular_name' => 'State',
                'search_items'  => 'Search States',
                'all_items'     => 'All States',
                'edit_item'     => 'Edit State',
                'update_item'   => 'Update State',
                'add_new_item'  => 'Add State',
                'new_item_name' => 'New State',
                'menu_name'     => 'States',
            ],
            'public'            => true,
            'hierarchical'      => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'rewrite'           => [
                'slug'       => 'directory-state',
                'with_front' => false,
            ],
        ]
    );

    register_taxonomy(
        'nwmd_city',
        ['nwmd_business'],
        [
            'labels' => [
                'name'          => 'Cities',
                'singular_name' => 'City',
                'search_items'  => 'Search Cities',
                'all_items'     => 'All Cities',
                'edit_item'     => 'Edit City',
                'update_item'   => 'Update City',
                'add_new_item'  => 'Add City',
                'new_item_name' => 'New City',
                'menu_name'     => 'Cities',
            ],
            'public'            => true,
            'hierarchical'      => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'rewrite'           => [
                'slug'       => 'directory-city',
                'with_front' => false,
            ],
        ]
    );
}

add_action(
    'init',
    'nwmd_directory_register_content_types'
);