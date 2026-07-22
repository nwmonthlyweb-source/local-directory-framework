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

    ?>
    <div class="wrap">
        <h1>
            <?php echo esc_html__('Monthly Rankings', 'local-directory-framework'); ?>
        </h1>

        <?php nwmd_directory_render_rankings_notice(); ?>

        <p>
            <?php
            echo esc_html__(
                'Create one ranking period for each month. New periods remain drafts until publishing is added.',
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
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($periods as $period) : ?>
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
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

        <?php endif; ?>
    </div>
    <?php
}
