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

    $seed_notice = null;

    if (
        isset($_GET['seeded']) &&
        '1' === sanitize_text_field(wp_unslash($_GET['seeded']))
    ) {
        $notice_key  = 'nwmd_operator_seed_' . get_current_user_id();
        $seed_notice = get_transient($notice_key);
        delete_transient($notice_key);
    }

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

        <?php if (is_array($seed_notice)) : ?>
            <?php if (!empty($seed_notice['success'])) : ?>
                <?php
                $seed_result = isset($seed_notice['result'])
                    && is_array($seed_notice['result'])
                    ? $seed_notice['result']
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
                                absint($seed_result['jobs_created'] ?? 0),
                                absint(
                                    $seed_result['specialties_created']
                                    ?? 0
                                ),
                                absint($seed_result['jobs_seen'] ?? 0),
                                absint(
                                    $seed_result['specialties_seen']
                                    ?? 0
                                ),
                                absint(
                                    $seed_result['cities_skipped']
                                    ?? 0
                                )
                            )
                        );
                        ?>
                    </p>
                </div>
            <?php else : ?>
                <div class="notice notice-error">
                    <p>
                        <?php
                        echo esc_html(
                            (string) (
                                $seed_notice['message']
                                ?? __(
                                    'The queue could not be refreshed.',
                                    'local-directory-framework'
                                )
                            )
                        );
                        ?>
                    </p>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <table class="widefat striped" style="max-width: 720px;">
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
                    <td>
                        <?php
                        echo esc_html(
                            (string) ($counts['jobs'] ?? 0)
                        );
                        ?>
                    </td>
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
                    <td>
                        <?php
                        echo esc_html(
                            (string) ($counts['specialties'] ?? 0)
                        );
                        ?>
                    </td>
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
                    <td>
                        <?php
                        echo esc_html(
                            (string) ($counts['runs'] ?? 0)
                        );
                        ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <?php if (!empty($status['ready'])) : ?>
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
                    __(
                        'Seed or Refresh Queue',
                        'local-directory-framework'
                    ),
                    'primary',
                    'submit',
                    false
                );
                ?>
            </form>
        <?php endif; ?>
    </div>
    <?php
}