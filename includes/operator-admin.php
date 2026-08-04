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
 * Render the latest Run Next Item notice.
 */
function nwmd_directory_render_operator_run_notice() {

    if (
        !isset($_GET['ran_next']) ||
        '1' !== sanitize_text_field(wp_unslash($_GET['ran_next']))
    ) {
        return;
    }

    $notice_key = 'nwmd_operator_run_' . get_current_user_id();
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
                            'The next queue item could not be started.',
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

    $action = !empty($result['resumed'])
        ? __('Resumed', 'local-directory-framework')
        : __('Started', 'local-directory-framework');

    ?>
    <div class="notice notice-success is-dismissible">
        <p>
            <strong><?php echo esc_html($action); ?>:</strong>
            <?php
            echo esc_html(
                sprintf(
                    '%1$s â€” %2$s â€” %3$s â€” %4$s',
                    (string) ($result['state_name'] ?? ''),
                    (string) ($result['city_name'] ?? ''),
                    (string) ($result['category_name'] ?? ''),
                    (string) ($result['specialty_name'] ?? '')
                )
            );
            ?>
        </p>
        <p>
            <?php
            echo esc_html__(
                'The checkpoint and run were recorded. No business or Deal data was changed.',
                'local-directory-framework'
            );
            ?>
        </p>
    </div>
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
        <?php nwmd_directory_render_operator_run_notice(); ?>

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

        <table class="widefat striped" style="max-width: 760px;">
            <thead>
                <tr>
                    <th scope="col">
                        <?php
                        echo esc_html__(
                            'Storage',
                            'local-directory-framework'
                        );
                        ?>
                    </th>
                    <th scope="col">
                        <?php
                        echo esc_html__(
                            'Records',
                            'local-directory-framework'
                        );
                        ?>
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <?php
                        echo esc_html__(
                            'City-category jobs',
                            'local-directory-framework'
                        );
                        ?>
                    </td>
                    <td><?php echo esc_html((string) ($counts['jobs'] ?? 0)); ?></td>
                </tr>
                <tr>
                    <td>
                        <?php
                        echo esc_html__(
                            'Specialty checkpoints',
                            'local-directory-framework'
                        );
                        ?>
                    </td>
                    <td><?php echo esc_html((string) ($counts['specialties'] ?? 0)); ?></td>
                </tr>
                <tr>
                    <td>
                        <?php
                        echo esc_html__(
                            'Operator runs',
                            'local-directory-framework'
                        );
                        ?>
                    </td>
                    <td><?php echo esc_html((string) ($counts['runs'] ?? 0)); ?></td>
                </tr>
            </tbody>
        </table>

        <?php if (!empty($status['ready'])) : ?>
            <h2>
                <?php
                echo esc_html__(
                    'Queue progress',
                    'local-directory-framework'
                );
                ?>
            </h2>

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

            <h2>
                <?php
                echo esc_html__(
                    'Run operator',
                    'local-directory-framework'
                );
                ?>
            </h2>

            <p>
                <?php
                echo esc_html__(
                    'Safely claim or resume one queue item and create its run record. This foundation step does not change business or Deal data.',
                    'local-directory-framework'
                );
                ?>
            </p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input
                    type="hidden"
                    name="action"
                    value="nwmd_directory_operator_run_next"
                >
                <?php
                wp_nonce_field(
                    'nwmd_directory_operator_run_next'
                );
                submit_button(
                    __('Run Next Item', 'local-directory-framework'),
                    'primary',
                    'submit',
                    false
                );
                ?>
            </form>

            <h2>
                <?php
                echo esc_html__(
                    'Queue setup',
                    'local-directory-framework'
                );
                ?>
            </h2>

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