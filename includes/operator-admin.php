<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register the Data Operator status page.
 */
function nwmd_directory_register_operator_admin_page() {

    add_submenu_page(
        'edit.php?post_type=nwmd_business',
        __('Data Operator', 'local-directory-framework'),
        __('Data Operator', 'local-directory-framework'),
        'manage_options',
        'nwmd-data-operator',
        'nwmd_directory_render_operator_admin_page'
    );
}

add_action(
    'admin_menu',
    'nwmd_directory_register_operator_admin_page',
    40
);

/**
 * Render the latest queue seeding notice.
 */
function nwmd_directory_render_operator_seed_notice() {

    if (
        !isset($_GET['seeded']) ||
        '1' !== sanitize_text_field(wp_unslash($_GET['seeded']))
    ) {
        return;
    }

    $notice_key = 'nwmd_operator_seed_' . get_current_user_id();
    $notice     = get_transient($notice_key);

    delete_transient($notice_key);

    if (!is_array($notice)) {
        return;
    }

    if (empty($notice['success'])) {
        ?>
        <div class="notice notice-error">
            <p>
                <?php
                echo esc_html(
                    (string) (
                        $notice['message']
                        ?? __(
                            'The queue could not be refreshed.',
                            'local-directory-framework'
                        )
                    )
                );
                ?>
            </p>
        </div>
        <?php

        return;
    }

    $result = isset($notice['result'])
        && is_array($notice['result'])
        ? $notice['result']
        : [];

    ?>
    <div class="notice notice-success is-dismissible">
        <p>
            <?php
            echo esc_html(
                sprintf(
                    __(
                        'Queue refreshed. Added %1$d jobs and %2$d specialty checkpoints. Reviewed %3$d jobs and %4$d checkpoints. Skipped %5$d cities without a valid state link.',
                        'local-directory-framework'
                    ),
                    absint($result['jobs_created'] ?? 0),
                    absint($result['specialties_created'] ?? 0),
                    absint($result['jobs_seen'] ?? 0),
                    absint($result['specialties_seen'] ?? 0),
                    absint($result['cities_skipped'] ?? 0)
                )
            );
            ?>
        </p>
    </div>
    <?php
}

/**
 * Render the latest operator action notice.
 */
function nwmd_directory_render_operator_action_notice() {

    if (
        !isset($_GET['operator_action']) ||
        '1' !== sanitize_text_field(
            wp_unslash($_GET['operator_action'])
        )
    ) {
        return;
    }

    $notice_key = 'nwmd_operator_action_' . get_current_user_id();
    $notice     = get_transient($notice_key);

    delete_transient($notice_key);

    if (!is_array($notice)) {
        return;
    }

    if (empty($notice['success'])) {
        ?>
        <div class="notice notice-error">
            <p>
                <?php
                echo esc_html(
                    (string) (
                        $notice['message']
                        ?? __(
                            'The operator action could not be completed.',
                            'local-directory-framework'
                        )
                    )
                );
                ?>
            </p>
        </div>
        <?php

        return;
    }

    $result = isset($notice['result'])
        && is_array($notice['result'])
        ? $notice['result']
        : [];
    $action = sanitize_key((string) ($result['action'] ?? ''));

    $heading = __('Updated', 'local-directory-framework');
    $message = __(
        'The operator state was updated.',
        'local-directory-framework'
    );
    $class   = 'notice notice-success is-dismissible';

    if ('started' === $action) {
        $heading = __('Started', 'local-directory-framework');
        $message = __(
            'The checkpoint and run were recorded. No business or Deal data was changed.',
            'local-directory-framework'
        );
    } elseif ('resumed' === $action) {
        $heading = __('Resumed', 'local-directory-framework');
        $message = __(
            'The existing checkpoint and run were resumed. No business or Deal data was changed.',
            'local-directory-framework'
        );
    } elseif ('completed' === $action) {
        $heading = __('Completed', 'local-directory-framework');
        $message = __(
            'The checkpoint and run were completed. No business or Deal data was changed.',
            'local-directory-framework'
        );
    } elseif ('released' === $action) {
        $heading = __('Released', 'local-directory-framework');
        $message = __(
            'The checkpoint returned to pending and its run history was preserved. No business or Deal data was changed.',
            'local-directory-framework'
        );
        $class = 'notice notice-warning is-dismissible';
    }

    ?>
    <div class="<?php echo esc_attr($class); ?>">
        <p>
            <strong><?php echo esc_html($heading); ?>:</strong>
            <?php
            echo esc_html(
                sprintf(
                    '%1$s | %2$s | %3$s | %4$s',
                    (string) ($result['state_name'] ?? ''),
                    (string) ($result['city_name'] ?? ''),
                    (string) ($result['category_name'] ?? ''),
                    (string) ($result['specialty_name'] ?? '')
                )
            );
            ?>
        </p>
        <p><?php echo esc_html($message); ?></p>
    </div>
    <?php
}

/**
 * Render a secure operator action form.
 *
 * @param string $action       Admin-post action.
 * @param string $nonce_action Nonce action.
 * @param string $label        Button label.
 * @param string $class        WordPress button class.
 * @param string $confirm      Optional confirmation text.
 */
function nwmd_directory_render_operator_action_form(
    $action,
    $nonce_action,
    $label,
    $class = 'button button-secondary',
    $confirm = ''
) {

    ?>
    <form
        method="post"
        action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
        style="display: inline-block; margin: 0 8px 8px 0;"
    >
        <input
            type="hidden"
            name="action"
            value="<?php echo esc_attr($action); ?>"
        >
        <?php wp_nonce_field($nonce_action); ?>
        <button
            type="submit"
            class="<?php echo esc_attr($class); ?>"
            <?php if ('' !== $confirm) : ?>
                onclick="return confirm('<?php echo esc_js($confirm); ?>');"
            <?php endif; ?>
        >
            <?php echo esc_html($label); ?>
        </button>
    </form>
    <?php
}

/**
 * Render the Data Operator storage and queue page.
 */
function nwmd_directory_render_operator_admin_page() {

    if (!current_user_can('manage_options')) {
        wp_die(
            esc_html__(
                'You do not have permission to view this page.',
                'local-directory-framework'
            )
        );
    }

    $status = nwmd_directory_get_operator_storage_status();
    $counts = isset($status['counts']) && is_array($status['counts'])
        ? $status['counts']
        : [];

    $checkpoint_counts = function_exists(
        'nwmd_directory_get_operator_checkpoint_counts'
    )
        ? nwmd_directory_get_operator_checkpoint_counts()
        : [];

    $active_count = function_exists(
        'nwmd_directory_get_operator_active_checkpoint_count'
    )
        ? nwmd_directory_get_operator_active_checkpoint_count()
        : 0;

    $current = function_exists(
        'nwmd_directory_get_current_operator_checkpoint_context'
    )
        ? nwmd_directory_get_current_operator_checkpoint_context()
        : [];

    ?>
    <div class="wrap">
        <h1>
            <?php
            echo esc_html__(
                'NW Monthly Data Operator',
                'local-directory-framework'
            );
            ?>
        </h1>

        <?php nwmd_directory_render_operator_seed_notice(); ?>
        <?php nwmd_directory_render_operator_action_notice(); ?>

        <?php if (!empty($status['ready'])) : ?>
            <div class="notice notice-success inline">
                <p>
                    <?php
                    echo esc_html__(
                        'Operator storage is ready.',
                        'local-directory-framework'
                    );
                    ?>
                </p>
            </div>
        <?php else : ?>
            <div class="notice notice-error inline">
                <p>
                    <?php
                    echo esc_html__(
                        'Operator storage is incomplete. Reactivate the plugin to run the database upgrade.',
                        'local-directory-framework'
                    );
                    ?>
                </p>
            </div>
        <?php endif; ?>

        <?php if ($active_count > 1) : ?>
            <div class="notice notice-error inline">
                <p>
                    <?php
                    echo esc_html__(
                        'More than one checkpoint is in progress. Operator actions are blocked to protect queue integrity.',
                        'local-directory-framework'
                    );
                    ?>
                </p>
            </div>
        <?php endif; ?>

        <table class="widefat striped" style="max-width: 760px;">
            <thead>
                <tr>
                    <th scope="col">
                        <?php echo esc_html__('Storage', 'local-directory-framework'); ?>
                    </th>
                    <th scope="col">
                        <?php echo esc_html__('Records', 'local-directory-framework'); ?>
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <?php echo esc_html__('City-category jobs', 'local-directory-framework'); ?>
                    </td>
                    <td><?php echo esc_html((string) ($counts['jobs'] ?? 0)); ?></td>
                </tr>
                <tr>
                    <td>
                        <?php echo esc_html__('Specialty checkpoints', 'local-directory-framework'); ?>
                    </td>
                    <td><?php echo esc_html((string) ($counts['specialties'] ?? 0)); ?></td>
                </tr>
                <tr>
                    <td>
                        <?php echo esc_html__('Operator runs', 'local-directory-framework'); ?>
                    </td>
                    <td><?php echo esc_html((string) ($counts['runs'] ?? 0)); ?></td>
                </tr>
            </tbody>
        </table>

        <?php if (!empty($status['ready'])) : ?>
            <h2><?php echo esc_html__('Queue progress', 'local-directory-framework'); ?></h2>

            <table class="widefat striped" style="max-width: 760px;">
                <thead>
                    <tr>
                        <th scope="col">
                            <?php echo esc_html__('Status', 'local-directory-framework'); ?>
                        </th>
                        <th scope="col">
                            <?php echo esc_html__('Checkpoints', 'local-directory-framework'); ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php echo esc_html__('Pending', 'local-directory-framework'); ?></td>
                        <td><?php echo esc_html((string) ($checkpoint_counts['pending'] ?? 0)); ?></td>
                    </tr>
                    <tr>
                        <td><?php echo esc_html__('In progress', 'local-directory-framework'); ?></td>
                        <td><?php echo esc_html((string) ($checkpoint_counts['in_progress'] ?? 0)); ?></td>
                    </tr>
                    <tr>
                        <td><?php echo esc_html__('Complete', 'local-directory-framework'); ?></td>
                        <td><?php echo esc_html((string) ($checkpoint_counts['complete'] ?? 0)); ?></td>
                    </tr>
                    <tr>
                        <td><?php echo esc_html__('Error', 'local-directory-framework'); ?></td>
                        <td><?php echo esc_html((string) ($checkpoint_counts['error'] ?? 0)); ?></td>
                    </tr>
                </tbody>
            </table>

            <?php if (!empty($current)) : ?>
                <h2><?php echo esc_html__('Current item', 'local-directory-framework'); ?></h2>

                <table class="widefat striped" style="max-width: 760px;">
                    <tbody>
                        <tr>
                            <th scope="row" style="width: 220px;">
                                <?php echo esc_html__('State', 'local-directory-framework'); ?>
                            </th>
                            <td><?php echo esc_html((string) ($current['state_name'] ?? '')); ?></td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <?php echo esc_html__('City', 'local-directory-framework'); ?>
                            </th>
                            <td><?php echo esc_html((string) ($current['city_name'] ?? '')); ?></td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <?php echo esc_html__('Category', 'local-directory-framework'); ?>
                            </th>
                            <td><?php echo esc_html((string) ($current['category_name'] ?? '')); ?></td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <?php echo esc_html__('Specialty', 'local-directory-framework'); ?>
                            </th>
                            <td><?php echo esc_html((string) ($current['specialty_name'] ?? '')); ?></td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <?php echo esc_html__('Run ID', 'local-directory-framework'); ?>
                            </th>
                            <td><?php echo esc_html((string) ($current['run_id'] ?? 0)); ?></td>
                        </tr>
                    </tbody>
                </table>
            <?php endif; ?>

            <?php
            if (
                function_exists(
                    'nwmd_directory_render_openai_operator_section'
                )
            ) {
                nwmd_directory_render_openai_operator_section();
            }
            ?>
            <?php
            if (
                function_exists(
                    'nwmd_directory_render_operator_budget_section'
                )
            ) {
                nwmd_directory_render_operator_budget_section();
            }

            if (
                function_exists(
                    'nwmd_directory_render_operator_budget_test_section'
                )
            ) {
                nwmd_directory_render_operator_budget_test_section();
            }
            if (
                function_exists(
                    'nwmd_directory_render_operator_research_preview_section'
                )
            ) {
                nwmd_directory_render_operator_research_preview_section();
            }
            ?>
            <h2><?php echo esc_html__('Run operator', 'local-directory-framework'); ?></h2>

            <p>
                <?php
                echo esc_html__(
                    'Use these controls to start, complete, or release one supervised operator item. Business drafts are created only through the separate reviewed draft action, and Deals or rankings are not changed.',
                    'local-directory-framework'
                );
                ?>
            </p>

            <?php if (empty($current) && 0 === $active_count) : ?>
                <?php
                nwmd_directory_render_operator_action_form(
                    'nwmd_directory_operator_run_next',
                    'nwmd_directory_operator_run_next',
                    __('Run Next Item', 'local-directory-framework'),
                    'button button-primary'
                );
                ?>
            <?php else : ?>
                <p>
                    <?php
                    echo esc_html__(
                        'Finish or release the current operator item before starting another one.',
                        'local-directory-framework'
                    );
                    ?>
                </p>
            <?php endif; ?>

            <?php if (!empty($current) && 1 === $active_count) : ?>
                <?php
                nwmd_directory_render_operator_action_form(
                    'nwmd_directory_operator_complete_current',
                    'nwmd_directory_operator_complete_current',
                    __('Complete Current Item', 'local-directory-framework'),
                    'button button-secondary',
                    __(
                        'Complete this reviewed checkpoint and preserve its stored audit record?',
                        'local-directory-framework'
                    )
                );

                nwmd_directory_render_operator_action_form(
                    'nwmd_directory_operator_release_current',
                    'nwmd_directory_operator_release_current',
                    __('Release Current Item', 'local-directory-framework'),
                    'button button-secondary',
                    __(
                        'Return this checkpoint to pending and cancel its current run?',
                        'local-directory-framework'
                    )
                );
                ?>
            <?php endif; ?>

            <h2><?php echo esc_html__('Queue setup', 'local-directory-framework'); ?></h2>

            <p>
                <?php
                echo esc_html__(
                    'Create any missing city-category jobs and specialty checkpoints from the current taxonomy. Existing progress and counters are preserved.',
                    'local-directory-framework'
                );
                ?>
            </p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input
                    type="hidden"
                    name="action"
                    value="nwmd_directory_seed_operator_queue"
                >
                <?php
                wp_nonce_field(
                    'nwmd_directory_seed_operator_queue'
                );
                submit_button(
                    __('Seed or Refresh Queue', 'local-directory-framework'),
                    'secondary',
                    'submit',
                    false
                );
                ?>
            </form>
        <?php endif; ?>
    </div>
    <?php
}
