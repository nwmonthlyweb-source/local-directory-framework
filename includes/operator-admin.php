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
 * Render the read-only Data Operator storage status page.
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

        <table class="widefat striped" style="max-width: 720px;">
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
                    <td><?php echo esc_html__('City-category jobs', 'local-directory-framework'); ?></td>
                    <td><?php echo esc_html((string) ($counts['jobs'] ?? 0)); ?></td>
                </tr>
                <tr>
                    <td><?php echo esc_html__('Specialty checkpoints', 'local-directory-framework'); ?></td>
                    <td><?php echo esc_html((string) ($counts['specialties'] ?? 0)); ?></td>
                </tr>
                <tr>
                    <td><?php echo esc_html__('Operator runs', 'local-directory-framework'); ?></td>
                    <td><?php echo esc_html((string) ($counts['runs'] ?? 0)); ?></td>
                </tr>
            </tbody>
        </table>

        <p>
            <?php
            echo esc_html__(
                'The next stage will seed the queue from the existing taxonomy.',
                'local-directory-framework'
            );
            ?>
        </p>
    </div>
    <?php
}