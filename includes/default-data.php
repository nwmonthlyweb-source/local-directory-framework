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
            'order' => 1,
        ],
        [
            'name' => 'Contractors',
            'slug' => 'contractors',
            'order' => 2,
        ],
        [
            'name' => 'Dentists',
            'slug' => 'dentists',
            'order' => 3,
        ],
        [
            'name' => 'Auto Services',
            'slug' => 'auto-services',
            'order' => 4,
        ],
        [
            'name' => 'Health & Wellness',
            'slug' => 'health-wellness',
            'order' => 5,
        ],
        [
            'name' => 'Real Estate',
            'slug' => 'realtors',
            'order' => 6,
        ],
        [
            'name' => 'Beauty & Personal Care',
            'slug' => 'beauty-personal-care',
            'order' => 7,
        ],
        [
            'name' => 'Pet Services',
            'slug' => 'pet-services',
            'order' => 8,
        ],
        [
            'name' => 'Professional Services',
            'slug' => 'professional-services',
            'order' => 9,
        ],
        [
            'name' => 'Entertainment & Recreation',
            'slug' => 'entertainment-recreation',
            'order' => 10,
        ],
        [
            'name' => 'Hotels & Lodging',
            'slug' => 'hotels-lodging',
            'order' => 11,
        ],
        [
            'name' => 'Education & Childcare',
            'slug' => 'education-childcare',
            'order' => 12,
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
                'order' => 1,
            ],
            [
                'name' => 'Italian Restaurants',
                'slug' => 'italian-restaurants',
                'order' => 2,
            ],
            [
                'name' => 'Mexican Restaurants',
                'slug' => 'mexican-restaurants',
                'order' => 3,
            ],
            [
                'name' => 'Asian Restaurants',
                'slug' => 'asian-restaurants',
                'order' => 4,
            ],
            [
                'name' => 'Coffee Shops & Cafes',
                'slug' => 'coffee-shops-cafes',
                'order' => 5,
            ],
            [
                'name' => 'Bakeries',
                'slug' => 'bakeries',
                'order' => 6,
            ],
            [
                'name' => 'Family Dining',
                'slug' => 'family-dining',
                'order' => 7,
            ],
            [
                'name' => 'Bars & Pubs',
                'slug' => 'bars-pubs',
                'order' => 8,
            ],
        ],
        'contractors' => [
            [
                'name' => 'General Contractors',
                'slug' => 'general-contractors',
                'order' => 1,
            ],
            [
                'name' => 'Plumbers',
                'slug' => 'plumbers',
                'order' => 2,
            ],
            [
                'name' => 'Roofers',
                'slug' => 'roofers',
                'order' => 3,
            ],
            [
                'name' => 'Electricians',
                'slug' => 'electricians',
                'order' => 4,
            ],
            [
                'name' => 'HVAC Contractors',
                'slug' => 'hvac-contractors',
                'order' => 5,
            ],
            [
                'name' => 'Remodeling Contractors',
                'slug' => 'remodeling-contractors',
                'order' => 6,
            ],
            [
                'name' => 'Painters',
                'slug' => 'painters',
                'order' => 7,
            ],
            [
                'name' => 'Flooring Contractors',
                'slug' => 'flooring-contractors',
                'order' => 8,
            ],
            [
                'name' => 'Concrete Contractors',
                'slug' => 'concrete-contractors',
                'order' => 9,
            ],
            [
                'name' => 'Landscapers',
                'slug' => 'landscapers',
                'order' => 10,
            ],
        ],
        'dentists' => [
            [
                'name' => 'General Dentistry',
                'slug' => 'general-dentistry',
                'order' => 1,
            ],
            [
                'name' => 'Cosmetic Dentistry',
                'slug' => 'cosmetic-dentistry',
                'order' => 2,
            ],
            [
                'name' => 'Pediatric Dentistry',
                'slug' => 'pediatric-dentistry',
                'order' => 3,
            ],
            [
                'name' => 'Orthodontics',
                'slug' => 'orthodontics',
                'order' => 4,
            ],
            [
                'name' => 'Oral Surgery',
                'slug' => 'oral-surgery',
                'order' => 5,
            ],
            [
                'name' => 'Emergency Dentistry',
                'slug' => 'emergency-dentistry',
                'order' => 6,
            ],
            [
                'name' => 'Endodontics',
                'slug' => 'endodontics',
                'order' => 7,
            ],
            [
                'name' => 'Periodontics',
                'slug' => 'periodontics',
                'order' => 8,
            ],
        ],
        'auto-services' => [
            [
                'name' => 'Auto Repair',
                'slug' => 'auto-repair',
                'order' => 1,
            ],
            [
                'name' => 'Oil Change Services',
                'slug' => 'oil-change-services',
                'order' => 2,
            ],
            [
                'name' => 'Tire Shops',
                'slug' => 'tire-shops',
                'order' => 3,
            ],
            [
                'name' => 'Auto Body Shops',
                'slug' => 'auto-body-shops',
                'order' => 4,
            ],
            [
                'name' => 'Towing Services',
                'slug' => 'towing-services',
                'order' => 5,
            ],
            [
                'name' => 'Car Detailing',
                'slug' => 'car-detailing',
                'order' => 6,
            ],
            [
                'name' => 'Transmission Repair',
                'slug' => 'transmission-repair',
                'order' => 7,
            ],
            [
                'name' => 'Brake Services',
                'slug' => 'brake-services',
                'order' => 8,
            ],
        ],
        'health-wellness' => [
            [
                'name' => 'Primary Care',
                'slug' => 'primary-care',
                'order' => 1,
            ],
            [
                'name' => 'Chiropractors',
                'slug' => 'chiropractors',
                'order' => 2,
            ],
            [
                'name' => 'Physical Therapy',
                'slug' => 'physical-therapy',
                'order' => 3,
            ],
            [
                'name' => 'Mental Health Services',
                'slug' => 'mental-health-services',
                'order' => 4,
            ],
            [
                'name' => 'Massage Therapy',
                'slug' => 'massage-therapy',
                'order' => 5,
            ],
            [
                'name' => 'Fitness & Gyms',
                'slug' => 'fitness-gyms',
                'order' => 6,
            ],
            [
                'name' => 'Nutrition Services',
                'slug' => 'nutrition-services',
                'order' => 7,
            ],
            [
                'name' => 'Acupuncture',
                'slug' => 'acupuncture',
                'order' => 8,
            ],
        ],
        'realtors' => [
            [
                'name' => 'Buyer Agents',
                'slug' => 'buyer-agents',
                'order' => 1,
            ],
            [
                'name' => 'Seller Agents',
                'slug' => 'seller-agents',
                'order' => 2,
            ],
            [
                'name' => 'Investment Property Firms',
                'slug' => 'investment-property-firms',
                'order' => 3,
            ],
            [
                'name' => 'Property Management',
                'slug' => 'property-management',
                'order' => 4,
            ],
            [
                'name' => 'Mortgage Lenders',
                'slug' => 'mortgage-lenders',
                'order' => 5,
            ],
            [
                'name' => 'Title & Escrow Companies',
                'slug' => 'title-escrow-companies',
                'order' => 6,
            ],
            [
                'name' => 'Home Inspectors',
                'slug' => 'home-inspectors',
                'order' => 7,
            ],
            [
                'name' => 'Real Estate Appraisers',
                'slug' => 'real-estate-appraisers',
                'order' => 8,
            ],
        ],
        'beauty-personal-care' => [
            [
                'name' => 'Hair Salons',
                'slug' => 'hair-salons',
                'order' => 1,
            ],
            [
                'name' => 'Barbers',
                'slug' => 'barbers',
                'order' => 2,
            ],
            [
                'name' => 'Nail Salons',
                'slug' => 'nail-salons',
                'order' => 3,
            ],
            [
                'name' => 'Skin Care',
                'slug' => 'skin-care',
                'order' => 4,
            ],
            [
                'name' => 'Spas',
                'slug' => 'spas',
                'order' => 5,
            ],
            [
                'name' => 'Makeup Artists',
                'slug' => 'makeup-artists',
                'order' => 6,
            ],
            [
                'name' => 'Eyebrow & Lash Services',
                'slug' => 'eyebrow-lash-services',
                'order' => 7,
            ],
            [
                'name' => 'Waxing Services',
                'slug' => 'waxing-services',
                'order' => 8,
            ],
        ],
        'pet-services' => [
            [
                'name' => 'Veterinarians',
                'slug' => 'veterinarians',
                'order' => 1,
            ],
            [
                'name' => 'Pet Grooming',
                'slug' => 'pet-grooming',
                'order' => 2,
            ],
            [
                'name' => 'Dog Walking',
                'slug' => 'dog-walking',
                'order' => 3,
            ],
            [
                'name' => 'Pet Boarding',
                'slug' => 'pet-boarding',
                'order' => 4,
            ],
            [
                'name' => 'Pet Training',
                'slug' => 'pet-training',
                'order' => 5,
            ],
            [
                'name' => 'Pet Sitting',
                'slug' => 'pet-sitting',
                'order' => 6,
            ],
            [
                'name' => 'Dog Daycare',
                'slug' => 'dog-daycare',
                'order' => 7,
            ],
            [
                'name' => 'Mobile Pet Services',
                'slug' => 'mobile-pet-services',
                'order' => 8,
            ],
        ],
        'professional-services' => [
            [
                'name' => 'Attorneys',
                'slug' => 'attorneys',
                'order' => 1,
            ],
            [
                'name' => 'Accountants & Tax Services',
                'slug' => 'accountants-tax-services',
                'order' => 2,
            ],
            [
                'name' => 'Insurance Agencies',
                'slug' => 'insurance-agencies',
                'order' => 3,
            ],
            [
                'name' => 'Financial Advisors',
                'slug' => 'financial-advisors',
                'order' => 4,
            ],
            [
                'name' => 'Marketing & Advertising',
                'slug' => 'marketing-advertising',
                'order' => 5,
            ],
            [
                'name' => 'IT Services',
                'slug' => 'it-services',
                'order' => 6,
            ],
            [
                'name' => 'Business Consultants',
                'slug' => 'business-consultants',
                'order' => 7,
            ],
            [
                'name' => 'Staffing & Recruiting',
                'slug' => 'staffing-recruiting',
                'order' => 8,
            ],
        ],
        'entertainment-recreation' => [
            [
                'name' => 'Movie Theaters',
                'slug' => 'movie-theaters',
                'order' => 1,
            ],
            [
                'name' => 'Bowling Alleys',
                'slug' => 'bowling-alleys',
                'order' => 2,
            ],
            [
                'name' => 'Museums & Galleries',
                'slug' => 'museums-galleries',
                'order' => 3,
            ],
            [
                'name' => 'Live Music Venues',
                'slug' => 'live-music-venues',
                'order' => 4,
            ],
            [
                'name' => 'Family Entertainment',
                'slug' => 'family-entertainment',
                'order' => 5,
            ],
            [
                'name' => 'Escape Rooms',
                'slug' => 'escape-rooms',
                'order' => 6,
            ],
            [
                'name' => 'Golf Courses',
                'slug' => 'golf-courses',
                'order' => 7,
            ],
            [
                'name' => 'Event Venues',
                'slug' => 'event-venues',
                'order' => 8,
            ],
        ],
        'hotels-lodging' => [
            [
                'name' => 'Full-Service Hotels',
                'slug' => 'full-service-hotels',
                'order' => 1,
            ],
            [
                'name' => 'Budget Hotels & Motels',
                'slug' => 'budget-hotels-motels',
                'order' => 2,
            ],
            [
                'name' => 'Boutique Hotels',
                'slug' => 'boutique-hotels',
                'order' => 3,
            ],
            [
                'name' => 'Resorts',
                'slug' => 'resorts',
                'order' => 4,
            ],
            [
                'name' => 'Bed & Breakfasts',
                'slug' => 'bed-breakfasts',
                'order' => 5,
            ],
            [
                'name' => 'Vacation Rentals',
                'slug' => 'vacation-rentals',
                'order' => 6,
            ],
            [
                'name' => 'Extended Stay Hotels',
                'slug' => 'extended-stay-hotels',
                'order' => 7,
            ],
            [
                'name' => 'Campgrounds & RV Parks',
                'slug' => 'campgrounds-rv-parks',
                'order' => 8,
            ],
        ],
        'education-childcare' => [
            [
                'name' => 'Preschools',
                'slug' => 'preschools',
                'order' => 1,
            ],
            [
                'name' => 'Daycare Centers',
                'slug' => 'daycare-centers',
                'order' => 2,
            ],
            [
                'name' => 'Private Schools',
                'slug' => 'private-schools',
                'order' => 3,
            ],
            [
                'name' => 'Tutoring Services',
                'slug' => 'tutoring-services',
                'order' => 4,
            ],
            [
                'name' => 'After-School Programs',
                'slug' => 'after-school-programs',
                'order' => 5,
            ],
            [
                'name' => 'Music Schools',
                'slug' => 'music-schools',
                'order' => 6,
            ],
            [
                'name' => 'Dance Schools',
                'slug' => 'dance-schools',
                'order' => 7,
            ],
            [
                'name' => 'Vocational & Trade Schools',
                'slug' => 'vocational-trade-schools',
                'order' => 8,
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
                    'name'       => 'Bellevue',
                    'slug'       => 'bellevue',
                    'group'      => 'top',
                    'state_rank' => 1,
                    'group_rank' => 1,
                ],
                [
                    'name'       => 'Seattle',
                    'slug'       => 'seattle',
                    'group'      => 'top',
                    'state_rank' => 2,
                    'group_rank' => 2,
                ],
                [
                    'name'       => 'Redmond',
                    'slug'       => 'redmond-wa',
                    'group'      => 'top',
                    'state_rank' => 3,
                    'group_rank' => 3,
                ],
                [
                    'name'       => 'Sammamish',
                    'slug'       => 'sammamish',
                    'group'      => 'top',
                    'state_rank' => 4,
                    'group_rank' => 4,
                ],
                [
                    'name'       => 'Kirkland',
                    'slug'       => 'kirkland',
                    'group'      => 'top',
                    'state_rank' => 5,
                    'group_rank' => 5,
                ],
                [
                    'name'       => 'Vancouver',
                    'slug'       => 'vancouver-wa',
                    'group'      => 'top',
                    'state_rank' => 6,
                    'group_rank' => 6,
                ],
                [
                    'name'       => 'Mercer Island',
                    'slug'       => 'mercer-island',
                    'group'      => 'top',
                    'state_rank' => 7,
                    'group_rank' => 7,
                ],
                [
                    'name'       => 'Camas',
                    'slug'       => 'camas',
                    'group'      => 'top',
                    'state_rank' => 8,
                    'group_rank' => 8,
                ],
                [
                    'name'       => 'Renton',
                    'slug'       => 'renton',
                    'group'      => 'top',
                    'state_rank' => 9,
                    'group_rank' => 9,
                ],
                [
                    'name'       => 'Tacoma',
                    'slug'       => 'tacoma',
                    'group'      => 'top',
                    'state_rank' => 10,
                    'group_rank' => 10,
                ],
                [
                    'name'       => 'Spokane',
                    'slug'       => 'spokane',
                    'group'      => 'other',
                    'state_rank' => 11,
                    'group_rank' => 1,
                ],
                [
                    'name'       => 'Shoreline',
                    'slug'       => 'shoreline',
                    'group'      => 'other',
                    'state_rank' => 12,
                    'group_rank' => 2,
                ],
                [
                    'name'       => 'Bothell',
                    'slug'       => 'bothell',
                    'group'      => 'other',
                    'state_rank' => 13,
                    'group_rank' => 3,
                ],
                [
                    'name'       => 'Kent',
                    'slug'       => 'kent',
                    'group'      => 'other',
                    'state_rank' => 14,
                    'group_rank' => 4,
                ],
                [
                    'name'       => 'Everett',
                    'slug'       => 'everett',
                    'group'      => 'other',
                    'state_rank' => 15,
                    'group_rank' => 5,
                ],
                [
                    'name'       => 'Issaquah',
                    'slug'       => 'issaquah',
                    'group'      => 'other',
                    'state_rank' => 16,
                    'group_rank' => 6,
                ],
                [
                    'name'       => 'Bainbridge Island',
                    'slug'       => 'bainbridge-island',
                    'group'      => 'other',
                    'state_rank' => 17,
                    'group_rank' => 7,
                ],
                [
                    'name'       => 'Lake Stevens',
                    'slug'       => 'lake-stevens',
                    'group'      => 'other',
                    'state_rank' => 18,
                    'group_rank' => 8,
                ],
                [
                    'name'       => 'Bellingham',
                    'slug'       => 'bellingham',
                    'group'      => 'other',
                    'state_rank' => 19,
                    'group_rank' => 9,
                ],
                [
                    'name'       => 'Richland',
                    'slug'       => 'richland',
                    'group'      => 'other',
                    'state_rank' => 20,
                    'group_rank' => 10,
                ],
                [
                    'name'       => 'Pasco',
                    'slug'       => 'pasco',
                    'group'      => 'other',
                    'state_rank' => 21,
                    'group_rank' => 11,
                ],
                [
                    'name'       => 'Kennewick',
                    'slug'       => 'kennewick',
                    'group'      => 'other',
                    'state_rank' => 22,
                    'group_rank' => 12,
                ],
                [
                    'name'       => 'Spokane Valley',
                    'slug'       => 'spokane-valley',
                    'group'      => 'other',
                    'state_rank' => 23,
                    'group_rank' => 13,
                ],
                [
                    'name'       => 'Olympia',
                    'slug'       => 'olympia',
                    'group'      => 'other',
                    'state_rank' => 24,
                    'group_rank' => 14,
                ],
            ],
        ],
        [
            'name'         => 'Oregon',
            'abbreviation' => 'OR',
            'slug'         => 'oregon',
            'cities'       => [
                [
                    'name'       => 'Lake Oswego',
                    'slug'       => 'lake-oswego',
                    'group'      => 'top',
                    'state_rank' => 1,
                    'group_rank' => 1,
                ],
                [
                    'name'       => 'Hillsboro',
                    'slug'       => 'hillsboro',
                    'group'      => 'top',
                    'state_rank' => 2,
                    'group_rank' => 2,
                ],
                [
                    'name'       => 'Beaverton',
                    'slug'       => 'beaverton',
                    'group'      => 'top',
                    'state_rank' => 3,
                    'group_rank' => 3,
                ],
                [
                    'name'       => 'Portland',
                    'slug'       => 'portland',
                    'group'      => 'top',
                    'state_rank' => 4,
                    'group_rank' => 4,
                ],
                [
                    'name'       => 'West Linn',
                    'slug'       => 'west-linn',
                    'group'      => 'top',
                    'state_rank' => 5,
                    'group_rank' => 5,
                ],
                [
                    'name'       => 'Bend',
                    'slug'       => 'bend',
                    'group'      => 'top',
                    'state_rank' => 6,
                    'group_rank' => 6,
                ],
                [
                    'name'       => 'Tigard',
                    'slug'       => 'tigard',
                    'group'      => 'top',
                    'state_rank' => 7,
                    'group_rank' => 7,
                ],
                [
                    'name'       => 'Eugene',
                    'slug'       => 'eugene',
                    'group'      => 'top',
                    'state_rank' => 8,
                    'group_rank' => 8,
                ],
                [
                    'name'       => 'Salem',
                    'slug'       => 'salem',
                    'group'      => 'top',
                    'state_rank' => 9,
                    'group_rank' => 9,
                ],
                [
                    'name'       => 'Happy Valley',
                    'slug'       => 'happy-valley',
                    'group'      => 'top',
                    'state_rank' => 10,
                    'group_rank' => 10,
                ],
                [
                    'name'       => 'Tualatin',
                    'slug'       => 'tualatin',
                    'group'      => 'other',
                    'state_rank' => 11,
                    'group_rank' => 1,
                ],
                [
                    'name'       => 'Wilsonville',
                    'slug'       => 'wilsonville',
                    'group'      => 'other',
                    'state_rank' => 12,
                    'group_rank' => 2,
                ],
                [
                    'name'       => 'Corvallis',
                    'slug'       => 'corvallis',
                    'group'      => 'other',
                    'state_rank' => 13,
                    'group_rank' => 3,
                ],
                [
                    'name'       => 'Gresham',
                    'slug'       => 'gresham',
                    'group'      => 'other',
                    'state_rank' => 14,
                    'group_rank' => 4,
                ],
                [
                    'name'       => 'Oregon City',
                    'slug'       => 'oregon-city',
                    'group'      => 'other',
                    'state_rank' => 15,
                    'group_rank' => 5,
                ],
                [
                    'name'       => 'Sherwood',
                    'slug'       => 'sherwood',
                    'group'      => 'other',
                    'state_rank' => 16,
                    'group_rank' => 6,
                ],
                [
                    'name'       => 'Newberg',
                    'slug'       => 'newberg',
                    'group'      => 'other',
                    'state_rank' => 17,
                    'group_rank' => 7,
                ],
                [
                    'name'       => 'Canby',
                    'slug'       => 'canby',
                    'group'      => 'other',
                    'state_rank' => 18,
                    'group_rank' => 8,
                ],
                [
                    'name'       => 'McMinnville',
                    'slug'       => 'mcminnville',
                    'group'      => 'other',
                    'state_rank' => 19,
                    'group_rank' => 9,
                ],
                [
                    'name'       => 'Redmond',
                    'slug'       => 'redmond-or',
                    'group'      => 'other',
                    'state_rank' => 20,
                    'group_rank' => 10,
                ],
                [
                    'name'       => 'Albany',
                    'slug'       => 'albany',
                    'group'      => 'other',
                    'state_rank' => 21,
                    'group_rank' => 11,
                ],
                [
                    'name'       => 'Keizer',
                    'slug'       => 'keizer',
                    'group'      => 'other',
                    'state_rank' => 22,
                    'group_rank' => 12,
                ],
                [
                    'name'       => 'Medford',
                    'slug'       => 'medford',
                    'group'      => 'other',
                    'state_rank' => 23,
                    'group_rank' => 13,
                ],
                [
                    'name'       => 'Springfield',
                    'slug'       => 'springfield',
                    'group'      => 'other',
                    'state_rank' => 24,
                    'group_rank' => 14,
                ],
            ],
        ],
    ];
}

/**
 * Group one region's cities for public navigation.
 *
 * Unknown or missing groups fall back to Other Cities.
 * City order is preserved from the approved master list.
 *
 * @param array $region Region configuration.
 *
 * @return array
 */
function nwmd_directory_get_region_city_groups($region) {

    $groups = [
        'top'   => [],
        'other' => [],
    ];

    if (
        !is_array($region) ||
        empty($region['cities']) ||
        !is_array($region['cities'])
    ) {
        return $groups;
    }

    foreach ($region['cities'] as $city) {
        if (!is_array($city)) {
            continue;
        }

        $group = isset($city['group'])
            ? sanitize_key($city['group'])
            : 'other';

        if (!array_key_exists($group, $groups)) {
            $group = 'other';
        }

        $groups[$group][] = $city;
    }

    return $groups;
}

/**
 * Install the NW Monthly launch taxonomy terms and metadata.
 *
 * Existing terms and business assignments are preserved.
 */
function nwmd_directory_install_default_terms() {

    foreach (
        nwmd_directory_get_launch_categories()
        as $category
    ) {
        $category_term_id =
            nwmd_directory_get_or_create_term(
                $category['name'],
                $category['slug'],
                'nwmd_category'
            );

        if ($category_term_id < 1) {
            continue;
        }

        update_term_meta(
            $category_term_id,
            'nwmd_display_order',
            absint($category['order'] ?? 0)
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

            update_term_meta(
                $specialty_term_id,
                'nwmd_display_order',
                absint($specialty['order'] ?? 0)
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

            update_term_meta(
                $city_term_id,
                'nwmd_city_group',
                sanitize_key($city['group'] ?? 'other')
            );

            update_term_meta(
                $city_term_id,
                'nwmd_city_state_rank',
                absint($city['state_rank'] ?? 0)
            );

            update_term_meta(
                $city_term_id,
                'nwmd_city_group_rank',
                absint($city['group_rank'] ?? 0)
            );

            update_term_meta(
                $city_term_id,
                'nwmd_display_order',
                absint($city['state_rank'] ?? 0)
            );
        }
    }
}
