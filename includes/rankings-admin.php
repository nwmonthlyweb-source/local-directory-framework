<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register the Monthly Rankings submenu.
 */
function nwmd_directory_register_rankings_admin_page() {

    add_submenu_page(
        'edit.php?post_type=nwmd_business',
        'Monthly Rankings',
        'Monthly Rankings',
        'manage_options',
        'nwmd-monthly-rankings',
        'nwmd_directory_render_rankings_admin_page'
    );
}

add_action(
    'admin_menu',
    'nwmd_directory_register_rankings_admin_page'
);

/**
 * Load dependent ranking-entry filters on the Monthly Rankings page.
 *
 * @param string $hook_suffix Current admin-page hook.
 */
function nwmd_directory_enqueue_rankings_admin_assets(
    $hook_suffix
) {

    if (
        'nwmd_business_page_nwmd-monthly-rankings'
        !== $hook_suffix
    ) {
        return;
    }

    $script_relative_path = 'assets/js/admin-rankings.js';
    $script_file_path = NWMD_DIRECTORY_PATH
        . $script_relative_path;

    $script_version = is_readable($script_file_path)
        ? (string) filemtime($script_file_path)
        : NWMD_DIRECTORY_VERSION;

    wp_enqueue_script(
        'nwmd-directory-rankings-admin',
        NWMD_DIRECTORY_URL . $script_relative_path,
        [],
        $script_version,
        true
    );
}

add_action(
    'admin_enqueue_scripts',
    'nwmd_directory_enqueue_rankings_admin_assets'
);

/**
 * Return batched taxonomy memberships for ranking Businesses.
 *
 * @param array $businesses Eligible Business posts.
 *
 * @return array
 */
function nwmd_directory_get_ranking_business_term_memberships(
    $businesses
) {

    $taxonomy_keys = [
        'nwmd_state'     => 'state_term_ids',
        'nwmd_city'      => 'city_term_ids',
        'nwmd_category'  => 'category_term_ids',
        'nwmd_specialty' => 'specialty_term_ids',
    ];

    $memberships = [];
    $business_ids = [];

    foreach ($businesses as $business) {
        if (
            !$business instanceof WP_Post ||
            'nwmd_business' !== $business->post_type
        ) {
            continue;
        }

        $business_id = absint($business->ID);

        if ($business_id < 1) {
            continue;
        }

        $business_ids[] = $business_id;
        $memberships[$business_id] = [
            'state_term_ids'     => [],
            'city_term_ids'      => [],
            'category_term_ids'  => [],
            'specialty_term_ids' => [],
        ];
    }

    if (empty($business_ids)) {
        return $memberships;
    }

    foreach ($taxonomy_keys as $taxonomy => $membership_key) {
        $terms = wp_get_object_terms(
            $business_ids,
            $taxonomy,
            [
                'fields' => 'all_with_object_id',
            ]
        );

        if (is_wp_error($terms)) {
            continue;
        }

        foreach ($terms as $term) {
            if (
                !$term instanceof WP_Term ||
                !isset($term->object_id)
            ) {
                continue;
            }

            $business_id = absint($term->object_id);
            $term_id = absint($term->term_id);

            if (
                $business_id < 1 ||
                $term_id < 1 ||
                !isset($memberships[$business_id])
            ) {
                continue;
            }

            $memberships[$business_id][$membership_key][]
                = $term_id;
        }
    }

    foreach ($memberships as &$business_memberships) {
        foreach ($taxonomy_keys as $membership_key) {
            $business_memberships[$membership_key] = array_values(
                array_unique(
                    array_map(
                        'absint',
                        $business_memberships[$membership_key]
                    )
                )
            );

            sort(
                $business_memberships[$membership_key],
                SORT_NUMERIC
            );
        }
    }

    unset($business_memberships);

    return $memberships;
}

/**
 * Return a human-readable ranking period label.
 *
 * @param string $period_key Period in YYYY-MM format.
 *
 * @return string
 */
function nwmd_directory_get_period_label($period_key) {

    $timezone = wp_timezone();

    $date = DateTimeImmutable::createFromFormat(
        '!Y-m',
        $period_key,
        $timezone
    );

    $errors = DateTimeImmutable::getLastErrors();

    if (
        !$date instanceof DateTimeImmutable ||
        (
            is_array($errors) &&
            (
                $errors['warning_count'] > 0 ||
                $errors['error_count'] > 0
            )
        )
    ) {
        return '';
    }

    return wp_date(
        'F Y',
        $date->getTimestamp(),
        $timezone
    );
}

/**
 * Create a draft monthly ranking period.
 */
function nwmd_directory_save_ranking_period() {

    if (!current_user_can('manage_options')) {
        wp_die(
            esc_html__(
                'You are not allowed to manage ranking periods.',
                'local-directory-framework'
            )
        );
    }

    check_admin_referer(
        'nwmd_save_ranking_period',
        'nwmd_ranking_period_nonce'
    );

    $period_key = isset($_POST['period_key'])
        ? sanitize_text_field(
            wp_unslash($_POST['period_key'])
        )
        : '';

    if (
        !preg_match(
            '/^\d{4}-(0[1-9]|1[0-2])$/',
            $period_key
        )
    ) {
        nwmd_directory_redirect_rankings_admin(
            'invalid-period'
        );
    }

    $period_label = nwmd_directory_get_period_label(
        $period_key
    );

    if ('' === $period_label) {
        nwmd_directory_redirect_rankings_admin(
            'invalid-period'
        );
    }

    global $wpdb;

    $periods_table = $wpdb->prefix
        . 'nwmd_ranking_periods';

    $existing_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id
            FROM {$periods_table}
            WHERE period_key = %s
            LIMIT 1",
            $period_key
        )
    );

    if (!empty($existing_id)) {
        nwmd_directory_redirect_rankings_admin(
            'period-exists'
        );
    }

    $current_time = current_time(
        'mysql'
    );

    $inserted = $wpdb->insert(
        $periods_table,
        [
            'period_key'   => $period_key,
            'period_label' => $period_label,
            'status'       => 'draft',
            'published_at' => null,
            'created_by'   => get_current_user_id(),
            'created_at'   => $current_time,
            'updated_at'   => $current_time,
        ],
        [
            '%s',
            '%s',
            '%s',
            '%s',
            '%d',
            '%s',
            '%s',
        ]
    );

    if (false === $inserted) {
        nwmd_directory_redirect_rankings_admin(
            'save-failed'
        );
    }

    nwmd_directory_redirect_rankings_admin(
        'period-created'
    );
}

add_action(
    'admin_post_nwmd_save_ranking_period',
    'nwmd_directory_save_ranking_period'
);

/**
 * Redirect back to the ranking page with a status notice.
 *
 * @param string $notice Notice identifier.
 */
function nwmd_directory_redirect_rankings_admin($notice) {

    $url = add_query_arg(
        [
            'post_type' => 'nwmd_business',
            'page'      => 'nwmd-monthly-rankings',
            'nwmd_notice' => sanitize_key($notice),
        ],
        admin_url('edit.php')
    );

    wp_safe_redirect($url);
    exit;
}

/**
 * Render one ranking admin notice.
 */
function nwmd_directory_render_rankings_notice() {

    $notice = isset($_GET['nwmd_notice'])
        ? sanitize_key(
            wp_unslash($_GET['nwmd_notice'])
        )
        : '';

    $messages = [
        'period-created' => [
            'success',
            'Ranking period created.',
        ],
        'period-exists' => [
            'warning',
            'That ranking period already exists.',
        ],
        'invalid-period' => [
            'error',
            'Choose a valid month and year.',
        ],
        'save-failed' => [
            'error',
            'The ranking period could not be saved.',
        ],
        'ranking-entry-created' => [
            'success',
            'Ranking entry created.',
        ],
        'ranking-entry-deleted' => [
            'success',
            'Ranking entry deleted.',
        ],
        'ranking-period-locked' => [
            'warning',
            'Published and archived ranking periods cannot be changed.',
        ],
        'invalid-ranking-entry' => [
            'error',
            'Choose valid ranking entry values.',
        ],
        'city-state-mismatch' => [
            'error',
            'The selected city does not belong to the selected state.',
        ],
        'business-classification-mismatch' => [
            'error',
            'The selected business does not match the ranking classifications.',
        ],
        'invalid-rank-position' => [
            'error',
            'Rank position must be between 1 and 10.',
        ],
        'invalid-editorial-score' => [
            'error',
            'Enter a valid non-negative editorial score.',
        ],
        'evidence-required' => [
            'error',
            'An evidence summary is required.',
        ],
        'rank-position-exists' => [
            'warning',
            'That rank position is already assigned for this ranking group.',
        ],
        'business-already-ranked' => [
            'warning',
            'That business is already included in this ranking group.',
        ],
        'ranking-entry-save-failed' => [
            'error',
            'The ranking entry could not be saved.',
        ],
        'ranking-entry-not-found' => [
            'error',
            'The ranking entry could not be found.',
        ],
        'ranking-entry-delete-failed' => [
            'error',
            'The ranking entry could not be deleted.',
        ],
        'ranking-period-review' => [
            'success',
            'The ranking period is ready for final review.',
        ],
        'ranking-period-draft' => [
            'success',
            'The ranking period was returned to draft.',
        ],
        'ranking-period-published' => [
            'success',
            'The ranking period was published. Any previously published period was archived.',
        ],
        'ranking-period-archived' => [
            'success',
            'The ranking period was archived.',
        ],
        'ranking-period-not-found' => [
            'error',
            'The ranking period could not be found.',
        ],
        'invalid-period-transition' => [
            'error',
            'That ranking period status change is not allowed.',
        ],
        'ranking-period-empty' => [
            'warning',
            'Add at least one ranking entry before review or publication.',
        ],
        'ranking-period-unpublished-business' => [
            'error',
            'Every ranked business must be published before this period can be published.',
        ],
        'ranking-period-update-failed' => [
            'error',
            'The ranking period status could not be updated.',
        ],
    ];

    if (!isset($messages[$notice])) {
        return;
    }

    [$type, $message] = $messages[$notice];

    ?>
    <div class="notice notice-<?php echo esc_attr($type); ?> is-dismissible">
        <p>
            <?php echo esc_html($message); ?>
        </p>
    </div>
    <?php
}

/**
 * Render the Monthly Rankings admin page.
 */
function nwmd_directory_render_rankings_admin_page() {

    if (!current_user_can('manage_options')) {
        return;
    }

    global $wpdb;

    $periods_table = $wpdb->prefix
        . 'nwmd_ranking_periods';

    $rankings_table = $wpdb->prefix
        . 'nwmd_ranking_entries';

    $periods = $wpdb->get_results(
        "SELECT
            id,
            period_key,
            period_label,
            status,
            published_at,
            created_at,
            updated_at
        FROM {$periods_table}
        ORDER BY period_key DESC"
    );

    $selected_period_id = isset($_GET['period_id'])
        ? absint($_GET['period_id'])
        : 0;

    $selected_period = $selected_period_id > 0
        ? nwmd_directory_get_ranking_period_by_id(
            $selected_period_id
        )
        : null;

    $entries = [];
    $states = [];
    $cities = [];
    $categories = [];
    $specialties = [];
    $businesses = [];
    $business_term_memberships = [];
    $term_names = [];

    if ($selected_period) {
        $entries = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    id,
                    ranking_period_id,
                    state_term_id,
                    city_term_id,
                    category_term_id,
                    specialty_term_id,
                    business_post_id,
                    rank_position,
                    editorial_score,
                    editorial_note,
                    evidence_summary,
                    created_at
                FROM {$rankings_table}
                WHERE ranking_period_id = %d
                ORDER BY
                    state_term_id ASC,
                    city_term_id ASC,
                    category_term_id ASC,
                    specialty_term_id ASC,
                    rank_position ASC",
                $selected_period_id
            )
        );

        $states = get_terms(
            [
                'taxonomy'   => 'nwmd_state',
                'hide_empty' => false,
                'orderby'    => 'name',
                'order'      => 'ASC',
            ]
        );

        $cities = get_terms(
            [
                'taxonomy'   => 'nwmd_city',
                'hide_empty' => false,
                'orderby'    => 'name',
                'order'      => 'ASC',
            ]
        );

        $categories = get_terms(
            [
                'taxonomy'   => 'nwmd_category',
                'hide_empty' => false,
                'orderby'    => 'name',
                'order'      => 'ASC',
            ]
        );

        $specialties = get_terms(
            [
                'taxonomy'   => 'nwmd_specialty',
                'hide_empty' => false,
                'orderby'    => 'name',
                'order'      => 'ASC',
            ]
        );

        if (is_wp_error($states)) {
            $states = [];
        }

        if (is_wp_error($cities)) {
            $cities = [];
        }

        if (is_wp_error($categories)) {
            $categories = [];
        }

        if (is_wp_error($specialties)) {
            $specialties = [];
        }

        $businesses = get_posts(
            [
                'post_type'      => 'nwmd_business',
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'orderby'        => 'title',
                'order'          => 'ASC',
            ]
        );

        if (
            nwmd_directory_ranking_period_is_editable(
                $selected_period
            )
        ) {
            $business_term_memberships =
                nwmd_directory_get_ranking_business_term_memberships(
                    $businesses
                );
        }

        $taxonomy_terms = [
            'nwmd_state'     => $states,
            'nwmd_city'      => $cities,
            'nwmd_category'  => $categories,
            'nwmd_specialty' => $specialties,
        ];

        foreach ($taxonomy_terms as $taxonomy => $terms) {
            $term_names[$taxonomy] = [];

            foreach ($terms as $term) {
                $term_names[$taxonomy][$term->term_id]
                    = $term->name;
            }
        }
    }

    ?>
    <div class="wrap">
        <h1>
            <?php echo esc_html__('Monthly Rankings', 'local-directory-framework'); ?>
        </h1>

        <?php nwmd_directory_render_rankings_notice(); ?>

        <p>
            <?php
            echo esc_html__(
                'Create one ranking period for each month. Ranking entries may be changed while the period is in draft or review.',
                'local-directory-framework'
            );
            ?>
        </p>

        <h2>
            <?php echo esc_html__('Add Ranking Period', 'local-directory-framework'); ?>
        </h2>

        <form
            action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
            method="post"
        >
            <input
                type="hidden"
                name="action"
                value="nwmd_save_ranking_period"
            >

            <?php
            wp_nonce_field(
                'nwmd_save_ranking_period',
                'nwmd_ranking_period_nonce'
            );
            ?>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="nwmd_period_key">
                            <?php echo esc_html__('Month', 'local-directory-framework'); ?>
                        </label>
                    </th>

                    <td>
                        <input
                            type="month"
                            id="nwmd_period_key"
                            name="period_key"
                            required
                        >
                    </td>
                </tr>
            </table>

            <?php
            submit_button(
                __('Create Draft Period', 'local-directory-framework')
            );
            ?>
        </form>

        <hr>

        <h2>
            <?php echo esc_html__('Ranking Periods', 'local-directory-framework'); ?>
        </h2>

        <?php if (empty($periods)) : ?>

            <p>
                <?php echo esc_html__('No ranking periods have been created.', 'local-directory-framework'); ?>
            </p>

        <?php else : ?>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>
                            <?php echo esc_html__('Month', 'local-directory-framework'); ?>
                        </th>
                        <th>
                            <?php echo esc_html__('Status', 'local-directory-framework'); ?>
                        </th>
                        <th>
                            <?php echo esc_html__('Created', 'local-directory-framework'); ?>
                        </th>
                        <th>
                            <?php echo esc_html__('Updated', 'local-directory-framework'); ?>
                        </th>
                        <th>
                            <?php echo esc_html__('Actions', 'local-directory-framework'); ?>
                        </th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($periods as $period) : ?>
                        <?php
                        $manage_url = add_query_arg(
                            [
                                'post_type' => 'nwmd_business',
                                'page'      => 'nwmd-monthly-rankings',
                                'period_id' => absint($period->id),
                            ],
                            admin_url('edit.php')
                        );
                        ?>
                        <tr>
                            <td>
                                <strong>
                                    <?php echo esc_html($period->period_label); ?>
                                </strong>
                            </td>
                            <td>
                                <?php echo esc_html(ucfirst($period->status)); ?>
                            </td>
                            <td>
                                <?php echo esc_html($period->created_at); ?>
                            </td>
                            <td>
                                <?php echo esc_html($period->updated_at); ?>
                            </td>
                            <td>
                                <a href="<?php echo esc_url($manage_url); ?>">
                                    <?php echo esc_html__('Manage Entries', 'local-directory-framework'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

        <?php endif; ?>

        <?php if ($selected_period) : ?>
            <hr>

            <h2>
                <?php
                echo esc_html(
                    sprintf(
                        __('Ranking Entries: %s', 'local-directory-framework'),
                        $selected_period->period_label
                    )
                );
                ?>
            </h2>

            <p>
                <?php
                echo esc_html(
                    sprintf(
                        __('Period status: %s', 'local-directory-framework'),
                        ucfirst($selected_period->status)
                    )
                );
                ?>
            </p>

            <?php
            nwmd_directory_render_ranking_period_actions(
                $selected_period
            );
            ?>

            <?php if (nwmd_directory_ranking_period_is_editable($selected_period)) : ?>

                <h3>
                    <?php echo esc_html__('Add Ranking Entry', 'local-directory-framework'); ?>
                </h3>

                <p>
                    <?php
                    echo esc_html__(
                        'The business must already have the selected state, city, category, and specialty terms.',
                        'local-directory-framework'
                    );
                    ?>
                </p>

                <form
                    action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                    method="post"
                >
                    <input
                        type="hidden"
                        name="action"
                        value="nwmd_save_ranking_entry"
                    >

                    <input
                        type="hidden"
                        name="ranking_period_id"
                        value="<?php echo esc_attr($selected_period_id); ?>"
                    >

                    <?php
                    wp_nonce_field(
                        'nwmd_save_ranking_entry',
                        'nwmd_ranking_entry_nonce'
                    );
                    ?>

                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row">
                                <label for="nwmd_state_term_id">
                                    <?php echo esc_html__('State', 'local-directory-framework'); ?>
                                </label>
                            </th>
                            <td>
                                <select
                                    id="nwmd_state_term_id"
                                    name="state_term_id"
                                    required
                                >
                                    <option value="">
                                        <?php echo esc_html__('Choose a state', 'local-directory-framework'); ?>
                                    </option>

                                    <?php foreach ($states as $state) : ?>
                                        <option value="<?php echo esc_attr($state->term_id); ?>">
                                            <?php echo esc_html($state->name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="nwmd_city_term_id">
                                    <?php echo esc_html__('City', 'local-directory-framework'); ?>
                                </label>
                            </th>
                            <td>
                                <select
                                    id="nwmd_city_term_id"
                                    name="city_term_id"
                                    required
                                >
                                    <option value="">
                                        <?php echo esc_html__('Choose a city', 'local-directory-framework'); ?>
                                    </option>

                                    <?php foreach ($cities as $city) : ?>
                                        <?php
                                        $city_state_term_id = absint(
                                            get_term_meta(
                                                $city->term_id,
                                                'nwmd_state_term_id',
                                                true
                                            )
                                        );
                                        ?>
                                        <option
                                            value="<?php echo esc_attr($city->term_id); ?>"
                                            data-state-term-id="<?php echo esc_attr($city_state_term_id); ?>"
                                        >
                                            <?php echo esc_html($city->name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="nwmd_category_term_id">
                                    <?php echo esc_html__('Category', 'local-directory-framework'); ?>
                                </label>
                            </th>
                            <td>
                                <select
                                    id="nwmd_category_term_id"
                                    name="category_term_id"
                                    required
                                >
                                    <option value="">
                                        <?php echo esc_html__('Choose a category', 'local-directory-framework'); ?>
                                    </option>

                                    <?php foreach ($categories as $category) : ?>
                                        <option value="<?php echo esc_attr($category->term_id); ?>">
                                            <?php echo esc_html($category->name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="nwmd_specialty_term_id">
                                    <?php echo esc_html__('Specialty', 'local-directory-framework'); ?>
                                </label>
                            </th>
                            <td>
                                <select
                                    id="nwmd_specialty_term_id"
                                    name="specialty_term_id"
                                >
                                    <option value="0">
                                        <?php echo esc_html__('All specialties', 'local-directory-framework'); ?>
                                    </option>

                                    <?php foreach ($specialties as $specialty) : ?>
                                        <?php
                                        $specialty_category_term_id = absint(
                                            get_term_meta(
                                                $specialty->term_id,
                                                'nwmd_category_term_id',
                                                true
                                            )
                                        );
                                        ?>
                                        <option
                                            value="<?php echo esc_attr($specialty->term_id); ?>"
                                            data-category-term-id="<?php echo esc_attr($specialty_category_term_id); ?>"
                                        >
                                            <?php echo esc_html($specialty->name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="nwmd_business_post_id">
                                    <?php echo esc_html__('Business', 'local-directory-framework'); ?>
                                </label>
                            </th>
                            <td>
                                <select
                                    id="nwmd_business_post_id"
                                    name="business_post_id"
                                    data-no-matches-label="<?php echo esc_attr__('No matching businesses', 'local-directory-framework'); ?>"
                                    required
                                >
                                    <option value="">
                                        <?php echo esc_html__('Choose a business', 'local-directory-framework'); ?>
                                    </option>

                                    <?php foreach ($businesses as $business) : ?>
                                        <?php
                                        $business_id = absint($business->ID);
                                        $business_memberships =
                                            $business_term_memberships[$business_id]
                                            ?? [
                                                'state_term_ids'     => [],
                                                'city_term_ids'      => [],
                                                'category_term_ids'  => [],
                                                'specialty_term_ids' => [],
                                            ];
                                        ?>
                                        <option
                                            value="<?php echo esc_attr($business_id); ?>"
                                            data-state-term-ids="<?php echo esc_attr(implode(',', $business_memberships['state_term_ids'])); ?>"
                                            data-city-term-ids="<?php echo esc_attr(implode(',', $business_memberships['city_term_ids'])); ?>"
                                            data-category-term-ids="<?php echo esc_attr(implode(',', $business_memberships['category_term_ids'])); ?>"
                                            data-specialty-term-ids="<?php echo esc_attr(implode(',', $business_memberships['specialty_term_ids'])); ?>"
                                        >
                                            <?php
                                            echo esc_html(
                                                sprintf(
                                                    '%1$s (%2$s)',
                                                    $business->post_title,
                                                    $business->post_status
                                                )
                                            );
                                            ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="nwmd_rank_position">
                                    <?php echo esc_html__('Rank Position', 'local-directory-framework'); ?>
                                </label>
                            </th>
                            <td>
                                <input
                                    type="number"
                                    id="nwmd_rank_position"
                                    name="rank_position"
                                    min="1"
                                    max="10"
                                    step="1"
                                    required
                                >
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="nwmd_editorial_score">
                                    <?php echo esc_html__('Editorial Score', 'local-directory-framework'); ?>
                                </label>
                            </th>
                            <td>
                                <input
                                    type="number"
                                    id="nwmd_editorial_score"
                                    name="editorial_score"
                                    min="0"
                                    step="0.01"
                                    required
                                >
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="nwmd_editorial_note">
                                    <?php echo esc_html__('Editorial Note', 'local-directory-framework'); ?>
                                </label>
                            </th>
                            <td>
                                <textarea
                                    id="nwmd_editorial_note"
                                    name="editorial_note"
                                    rows="5"
                                    class="large-text"
                                ></textarea>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="nwmd_evidence_summary">
                                    <?php echo esc_html__('Evidence Summary', 'local-directory-framework'); ?>
                                </label>
                            </th>
                            <td>
                                <textarea
                                    id="nwmd_evidence_summary"
                                    name="evidence_summary"
                                    rows="5"
                                    class="large-text"
                                    required
                                ></textarea>
                            </td>
                        </tr>
                    </table>

                    <?php
                    submit_button(
                        __('Add Ranking Entry', 'local-directory-framework')
                    );
                    ?>
                </form>

            <?php else : ?>

                <div class="notice notice-warning inline">
                    <p>
                        <?php
                        echo esc_html__(
                            'Published and archived ranking periods are read-only.',
                            'local-directory-framework'
                        );
                        ?>
                    </p>
                </div>

            <?php endif; ?>

            <h3>
                <?php echo esc_html__('Current Entries', 'local-directory-framework'); ?>
            </h3>

            <?php if (empty($entries)) : ?>

                <p>
                    <?php echo esc_html__('No ranking entries have been added.', 'local-directory-framework'); ?>
                </p>

            <?php else : ?>

                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>
                                <?php echo esc_html__('Rank', 'local-directory-framework'); ?>
                            </th>
                            <th>
                                <?php echo esc_html__('Business', 'local-directory-framework'); ?>
                            </th>
                            <th>
                                <?php echo esc_html__('Location', 'local-directory-framework'); ?>
                            </th>
                            <th>
                                <?php echo esc_html__('Classification', 'local-directory-framework'); ?>
                            </th>
                            <th>
                                <?php echo esc_html__('Score', 'local-directory-framework'); ?>
                            </th>
                            <th>
                                <?php echo esc_html__('Evidence', 'local-directory-framework'); ?>
                            </th>
                            <th>
                                <?php echo esc_html__('Editorial Note', 'local-directory-framework'); ?>
                            </th>
                            <th>
                                <?php echo esc_html__('Created', 'local-directory-framework'); ?>
                            </th>
                            <th>
                                <?php echo esc_html__('Actions', 'local-directory-framework'); ?>
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($entries as $entry) : ?>
                            <?php
                            $state_name = $term_names['nwmd_state'][$entry->state_term_id]
                                ?? '';

                            $city_name = $term_names['nwmd_city'][$entry->city_term_id]
                                ?? '';

                            $category_name = $term_names['nwmd_category'][$entry->category_term_id]
                                ?? '';

                            $specialty_name = $entry->specialty_term_id > 0
                                ? (
                                    $term_names['nwmd_specialty'][$entry->specialty_term_id]
                                    ?? ''
                                )
                                : __('All specialties', 'local-directory-framework');

                            $business_title = get_the_title(
                                $entry->business_post_id
                            );

                            $business_edit_url = get_edit_post_link(
                                $entry->business_post_id
                            );

                            $delete_url = wp_nonce_url(
                                add_query_arg(
                                    [
                                        'action'   => 'nwmd_delete_ranking_entry',
                                        'entry_id' => absint($entry->id),
                                    ],
                                    admin_url('admin-post.php')
                                ),
                                'nwmd_delete_ranking_entry_' . absint($entry->id)
                            );
                            ?>
                            <tr>
                                <td>
                                    <strong>
                                        <?php echo esc_html($entry->rank_position); ?>
                                    </strong>
                                </td>
                                <td>
                                    <?php if ($business_edit_url) : ?>
                                        <a href="<?php echo esc_url($business_edit_url); ?>">
                                            <?php echo esc_html($business_title); ?>
                                        </a>
                                    <?php else : ?>
                                        <?php echo esc_html($business_title); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    echo esc_html(
                                        trim(
                                            $city_name . ', ' . $state_name,
                                            ', '
                                        )
                                    );
                                    ?>
                                </td>
                                <td>
                                    <?php echo esc_html($category_name); ?>
                                    <br>
                                    <?php echo esc_html($specialty_name); ?>
                                </td>
                                <td>
                                    <?php
                                    echo esc_html(
                                        number_format_i18n(
                                            (float) $entry->editorial_score,
                                            2
                                        )
                                    );
                                    ?>
                                </td>
                                <td>
                                    <?php echo esc_html($entry->evidence_summary); ?>
                                </td>
                                <td>
                                    <?php echo esc_html($entry->editorial_note); ?>
                                </td>
                                <td>
                                    <?php echo esc_html($entry->created_at); ?>
                                </td>
                                <td>
                                    <?php if (nwmd_directory_ranking_period_is_editable($selected_period)) : ?>
                                        <a
                                            href="<?php echo esc_url($delete_url); ?>"
                                            class="submitdelete"
                                            onclick="return confirm('<?php echo esc_js(__('Delete this ranking entry?', 'local-directory-framework')); ?>');"
                                        >
                                            <?php echo esc_html__('Delete', 'local-directory-framework'); ?>
                                        </a>
                                    <?php else : ?>
                                        <?php echo esc_html__('Read only', 'local-directory-framework'); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

            <?php endif; ?>

        <?php elseif ($selected_period_id > 0) : ?>

            <div class="notice notice-error inline">
                <p>
                    <?php echo esc_html__('The selected ranking period could not be found.', 'local-directory-framework'); ?>
                </p>
            </div>

        <?php endif; ?>
    </div>
    <?php
}
