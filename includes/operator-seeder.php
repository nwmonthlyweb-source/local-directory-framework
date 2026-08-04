<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return specialties grouped by their linked category.
 *
 * @return array|WP_Error
 */
function nwmd_directory_get_operator_specialty_map() {

    $specialties = get_terms(
        [
            'taxonomy'   => 'nwmd_specialty',
            'hide_empty' => false,
        ]
    );

    if (is_wp_error($specialties)) {
        return $specialties;
    }

    $map = [];

    foreach ($specialties as $specialty) {
        if (!$specialty instanceof WP_Term) {
            continue;
        }

        $category_term_id = absint(
            get_term_meta(
                $specialty->term_id,
                'nwmd_category_term_id',
                true
            )
        );

        if ($category_term_id < 1) {
            continue;
        }

        $map[$category_term_id][] = [
            'term'  => $specialty,
            'order' => absint(
                get_term_meta(
                    $specialty->term_id,
                    'nwmd_display_order',
                    true
                )
            ),
        ];
    }

    foreach ($map as &$category_specialties) {
        usort(
            $category_specialties,
            static function ($left, $right) {

                $order_compare = $left['order'] <=> $right['order'];

                if (0 !== $order_compare) {
                    return $order_compare;
                }

                $name_compare = strcasecmp(
                    $left['term']->name,
                    $right['term']->name
                );

                if (0 !== $name_compare) {
                    return $name_compare;
                }

                return $left['term']->term_id
                    <=> $right['term']->term_id;
            }
        );
    }
    unset($category_specialties);

    return $map;
}

/**
 * Seed missing city-category jobs and specialty checkpoints.
 *
 * Existing progress and result counters are preserved.
 *
 * @return array|WP_Error
 */
function nwmd_directory_seed_operator_queue() {

    global $wpdb;

    $status = nwmd_directory_get_operator_storage_status();

    if (empty($status['ready'])) {
        return new WP_Error(
            'nwmd_operator_storage_incomplete',
            __(
                'Operator storage is incomplete.',
                'local-directory-framework'
            )
        );
    }

    $cities = get_terms(
        [
            'taxonomy'   => 'nwmd_city',
            'hide_empty' => false,
            'orderby'    => 'term_id',
            'order'      => 'ASC',
        ]
    );

    if (is_wp_error($cities)) {
        return $cities;
    }

    $categories = get_terms(
        [
            'taxonomy'   => 'nwmd_category',
            'hide_empty' => false,
            'orderby'    => 'term_id',
            'order'      => 'ASC',
        ]
    );

    if (is_wp_error($categories)) {
        return $categories;
    }

    $specialty_map = nwmd_directory_get_operator_specialty_map();

    if (is_wp_error($specialty_map)) {
        return $specialty_map;
    }

    $tables = nwmd_directory_get_operator_table_names();
    $now    = current_time('mysql');

    $result = [
        'jobs_created'        => 0,
        'jobs_seen'           => 0,
        'specialties_created' => 0,
        'specialties_seen'    => 0,
        'cities_skipped'      => 0,
    ];

    foreach ($cities as $city) {
        if (!$city instanceof WP_Term) {
            continue;
        }

        $state_term_id = absint(
            get_term_meta(
                $city->term_id,
                'nwmd_state_term_id',
                true
            )
        );

        $state = $state_term_id > 0
            ? get_term($state_term_id, 'nwmd_state')
            : null;

        if (
            !$state instanceof WP_Term ||
            is_wp_error($state)
        ) {
            ++$result['cities_skipped'];
            continue;
        }

        foreach ($categories as $category) {
            if (!$category instanceof WP_Term) {
                continue;
            }

            ++$result['jobs_seen'];

            $job_key = sprintf(
                'state-%d-city-%d-category-%d',
                $state_term_id,
                $city->term_id,
                $category->term_id
            );

            $inserted = $wpdb->query(
                $wpdb->prepare(
                    "INSERT IGNORE INTO {$tables['jobs']}
                    (
                        job_key,
                        state_term_id,
                        city_term_id,
                        category_term_id,
                        status,
                        last_error,
                        created_at,
                        updated_at
                    )
                    VALUES (%s, %d, %d, %d, %s, %s, %s, %s)",
                    $job_key,
                    $state_term_id,
                    $city->term_id,
                    $category->term_id,
                    'pending',
                    '',
                    $now,
                    $now
                )
            );

            if (false === $inserted) {
                return new WP_Error(
                    'nwmd_operator_job_insert_failed',
                    __(
                        'A city-category job could not be saved.',
                        'local-directory-framework'
                    )
                );
            }

            if (1 === $inserted) {
                ++$result['jobs_created'];
            }

            $job_id = absint(
                $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT id
                        FROM {$tables['jobs']}
                        WHERE state_term_id = %d
                            AND city_term_id = %d
                            AND category_term_id = %d
                        LIMIT 1",
                        $state_term_id,
                        $city->term_id,
                        $category->term_id
                    )
                )
            );

            if ($job_id < 1) {
                return new WP_Error(
                    'nwmd_operator_job_lookup_failed',
                    __(
                        'A seeded job could not be retrieved.',
                        'local-directory-framework'
                    )
                );
            }

            $category_specialties =
                $specialty_map[$category->term_id] ?? [];

            $updated = $wpdb->update(
                $tables['jobs'],
                [
                    'specialty_total' => count(
                        $category_specialties
                    ),
                    'updated_at'      => $now,
                ],
                [
                    'id' => $job_id,
                ],
                [
                    '%d',
                    '%s',
                ],
                [
                    '%d',
                ]
            );

            if (false === $updated) {
                return new WP_Error(
                    'nwmd_operator_job_update_failed',
                    __(
                        'A seeded job could not be updated.',
                        'local-directory-framework'
                    )
                );
            }

            foreach (
                $category_specialties as $index => $specialty_data
            ) {
                $specialty = $specialty_data['term'] ?? null;

                if (!$specialty instanceof WP_Term) {
                    continue;
                }

                ++$result['specialties_seen'];

                $checkpoint_inserted = $wpdb->query(
                    $wpdb->prepare(
                        "INSERT IGNORE INTO {$tables['specialties']}
                        (
                            job_id,
                            specialty_term_id,
                            sort_order,
                            status,
                            last_error,
                            created_at,
                            updated_at
                        )
                        VALUES (%d, %d, %d, %s, %s, %s, %s)",
                        $job_id,
                        $specialty->term_id,
                        $index + 1,
                        'pending',
                        '',
                        $now,
                        $now
                    )
                );

                if (false === $checkpoint_inserted) {
                    return new WP_Error(
                        'nwmd_operator_specialty_insert_failed',
                        __(
                            'A specialty checkpoint could not be saved.',
                            'local-directory-framework'
                        )
                    );
                }

                if (1 === $checkpoint_inserted) {
                    ++$result['specialties_created'];
                }

                $checkpoint_updated = $wpdb->update(
                    $tables['specialties'],
                    [
                        'sort_order' => $index + 1,
                        'updated_at' => $now,
                    ],
                    [
                        'job_id'           => $job_id,
                        'specialty_term_id' => $specialty->term_id,
                    ],
                    [
                        '%d',
                        '%s',
                    ],
                    [
                        '%d',
                        '%d',
                    ]
                );

                if (false === $checkpoint_updated) {
                    return new WP_Error(
                        'nwmd_operator_specialty_update_failed',
                        __(
                            'A specialty checkpoint could not be updated.',
                            'local-directory-framework'
                        )
                    );
                }
            }
        }
    }

    return $result;
}

/**
 * Handle the secure queue-seeding admin action.
 */
function nwmd_directory_handle_operator_seed_queue() {

    if (!current_user_can('manage_options')) {
        wp_die(
            esc_html__(
                'You do not have permission to perform this action.',
                'local-directory-framework'
            )
        );
    }

    check_admin_referer(
        'nwmd_directory_seed_operator_queue'
    );

    $result = nwmd_directory_seed_operator_queue();
    $key    = 'nwmd_operator_seed_' . get_current_user_id();

    if (is_wp_error($result)) {
        set_transient(
            $key,
            [
                'success' => false,
                'message' => $result->get_error_message(),
            ],
            MINUTE_IN_SECONDS
        );
    } else {
        set_transient(
            $key,
            [
                'success' => true,
                'result'  => $result,
            ],
            MINUTE_IN_SECONDS
        );
    }

    $redirect_url = add_query_arg(
        [
            'post_type' => 'nwmd_business',
            'page'      => 'nwmd-data-operator',
            'seeded'    => '1',
        ],
        admin_url('edit.php')
    );

    wp_safe_redirect($redirect_url);
    exit;
}

add_action(
    'admin_post_nwmd_directory_seed_operator_queue',
    'nwmd_directory_handle_operator_seed_queue'
);