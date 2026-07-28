<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register the First Page Settings submenu.
 */
function nwmd_directory_register_app_settings_admin_page() {

    add_submenu_page(
        'edit.php?post_type=nwmd_business',
        __(
            'First Page Settings',
            'local-directory-framework'
        ),
        __(
            'First Page',
            'local-directory-framework'
        ),
        'manage_options',
        'nwmd-app-settings',
        'nwmd_directory_render_app_settings_admin_page'
    );
}

add_action(
    'admin_menu',
    'nwmd_directory_register_app_settings_admin_page',
    45
);

/**
 * Load WordPress Media Library on the app settings page.
 *
 * @param string $hook_suffix Current admin page hook.
 */
function nwmd_directory_enqueue_app_settings_admin_assets(
    $hook_suffix
) {

    if (
        'nwmd_business_page_nwmd-app-settings'
        !== $hook_suffix
    ) {
        return;
    }

    wp_enqueue_media();

    $script_relative_path =
        'assets/js/admin-app-settings.js';

    $script_file_path = NWMD_DIRECTORY_PATH
        . $script_relative_path;

    $script_version = is_readable($script_file_path)
        ? (string) filemtime($script_file_path)
        : NWMD_DIRECTORY_VERSION;

    wp_enqueue_script(
        'nwmd-directory-app-settings-admin',
        NWMD_DIRECTORY_URL . $script_relative_path,
        [],
        $script_version,
        true
    );
}

add_action(
    'admin_enqueue_scripts',
    'nwmd_directory_enqueue_app_settings_admin_assets'
);

/**
 * Render one image selector field.
 *
 * @param string $field_key   Option array key.
 * @param int    $attachment_id Saved attachment ID.
 * @param string $default_url Plugin fallback URL.
 * @param string $description Field description.
 */
function nwmd_directory_render_app_image_setting(
    $field_key,
    $attachment_id,
    $default_url,
    $description
) {

    $attachment_id = absint($attachment_id);

    $preview_url = $attachment_id > 0
        ? wp_get_attachment_image_url(
            $attachment_id,
            'medium'
        )
        : '';

    if (!is_string($preview_url) || '' === $preview_url) {
        $preview_url = $default_url;
    }

    ?>
    <div
        class="nwmd-app-media-field"
        data-nwmd-media-field
        data-default-src="<?php echo esc_url($default_url); ?>"
    >
        <input
            type="hidden"
            name="nwmd_directory_app_settings[<?php
                echo esc_attr($field_key);
            ?>]"
            value="<?php echo esc_attr((string) $attachment_id); ?>"
            data-nwmd-media-id
        >

        <div style="margin: 0 0 12px;">
            <img
                src="<?php echo esc_url($preview_url); ?>"
                alt=""
                data-nwmd-media-preview
                style="
                    display: block;
                    width: 180px;
                    max-width: 100%;
                    height: 120px;
                    object-fit: cover;
                    border: 1px solid #c3c4c7;
                    border-radius: 6px;
                    background: #ffffff;
                "
            >
        </div>

        <button
            type="button"
            class="button"
            data-nwmd-media-select
        >
            <?php
            echo esc_html__(
                'Select Image',
                'local-directory-framework'
            );
            ?>
        </button>

        <button
            type="button"
            class="button"
            data-nwmd-media-remove
            <?php disabled(0, $attachment_id); ?>
        >
            <?php
            echo esc_html__(
                'Use Plugin Default',
                'local-directory-framework'
            );
            ?>
        </button>

        <p class="description">
            <?php echo esc_html($description); ?>
        </p>
    </div>
    <?php
}

/**
 * Render the First Page Settings screen.
 */
function nwmd_directory_render_app_settings_admin_page() {

    if (!current_user_can('manage_options')) {
        wp_die(
            esc_html__(
                'You are not allowed to manage app settings.',
                'local-directory-framework'
            )
        );
    }

    $settings = nwmd_directory_get_app_settings();

    $default_icon_url = NWMD_DIRECTORY_URL
        . 'assets/icons/nw-monthly-192.png';

    ?>
    <div class="wrap">
        <h1>
            <?php
            echo esc_html__(
                'First Page Settings',
                'local-directory-framework'
            );
            ?>
        </h1>

        <p>
            <?php
            echo esc_html__(
                'The first page is displayed before the directory. The directory remains hidden until the first page finishes.',
                'local-directory-framework'
            );
            ?>
        </p>

        <?php settings_errors(); ?>

        <form method="post" action="options.php">
            <?php
            settings_fields(
                'nwmd_directory_app_settings_group'
            );
            ?>

            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <?php
                            echo esc_html__(
                                'Enable First Page',
                                'local-directory-framework'
                            );
                            ?>
                        </th>
                        <td>
                            <label>
                                <input
                                    type="checkbox"
                                    name="nwmd_directory_app_settings[first_page_enabled]"
                                    value="1"
                                    <?php
                                    checked(
                                        1,
                                        absint(
                                            $settings[
                                                'first_page_enabled'
                                            ]
                                        )
                                    );
                                    ?>
                                >
                                <?php
                                echo esc_html__(
                                    'Show the branded first page before the directory.',
                                    'local-directory-framework'
                                );
                                ?>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="nwmd-first-page-title">
                                <?php
                                echo esc_html__(
                                    'Main Title',
                                    'local-directory-framework'
                                );
                                ?>
                            </label>
                        </th>
                        <td>
                            <input
                                id="nwmd-first-page-title"
                                class="regular-text"
                                type="text"
                                name="nwmd_directory_app_settings[first_page_title]"
                                value="<?php
                                    echo esc_attr(
                                        $settings[
                                            'first_page_title'
                                        ]
                                    );
                                ?>"
                            >
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="nwmd-first-page-subtitle">
                                <?php
                                echo esc_html__(
                                    'Subtitle',
                                    'local-directory-framework'
                                );
                                ?>
                            </label>
                        </th>
                        <td>
                            <input
                                id="nwmd-first-page-subtitle"
                                class="regular-text"
                                type="text"
                                name="nwmd_directory_app_settings[first_page_subtitle]"
                                value="<?php
                                    echo esc_attr(
                                        $settings[
                                            'first_page_subtitle'
                                        ]
                                    );
                                ?>"
                            >
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="nwmd-first-page-region">
                                <?php
                                echo esc_html__(
                                    'Region Text',
                                    'local-directory-framework'
                                );
                                ?>
                            </label>
                        </th>
                        <td>
                            <input
                                id="nwmd-first-page-region"
                                class="regular-text"
                                type="text"
                                name="nwmd_directory_app_settings[first_page_region]"
                                value="<?php
                                    echo esc_attr(
                                        $settings[
                                            'first_page_region'
                                        ]
                                    );
                                ?>"
                            >
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="nwmd-first-page-duration">
                                <?php
                                echo esc_html__(
                                    'Display Time',
                                    'local-directory-framework'
                                );
                                ?>
                            </label>
                        </th>
                        <td>
                            <input
                                id="nwmd-first-page-duration"
                                type="number"
                                min="0.3"
                                max="10"
                                step="0.1"
                                name="nwmd_directory_app_settings[first_page_duration]"
                                value="<?php
                                    echo esc_attr(
                                        (string) $settings[
                                            'first_page_duration'
                                        ]
                                    );
                                ?>"
                            >
                            <span>
                                <?php
                                echo esc_html__(
                                    'seconds',
                                    'local-directory-framework'
                                );
                                ?>
                            </span>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="nwmd-first-page-frequency">
                                <?php
                                echo esc_html__(
                                    'Display Frequency',
                                    'local-directory-framework'
                                );
                                ?>
                            </label>
                        </th>
                        <td>
                            <select
                                id="nwmd-first-page-frequency"
                                name="nwmd_directory_app_settings[first_page_frequency]"
                            >
                                <option
                                    value="session"
                                    <?php
                                    selected(
                                        'session',
                                        $settings[
                                            'first_page_frequency'
                                        ]
                                    );
                                    ?>
                                >
                                    <?php
                                    echo esc_html__(
                                        'Once per browser session',
                                        'local-directory-framework'
                                    );
                                    ?>
                                </option>

                                <option
                                    value="always"
                                    <?php
                                    selected(
                                        'always',
                                        $settings[
                                            'first_page_frequency'
                                        ]
                                    );
                                    ?>
                                >
                                    <?php
                                    echo esc_html__(
                                        'Every homepage visit',
                                        'local-directory-framework'
                                    );
                                    ?>
                                </option>
                            </select>
                        </td>
                    </tr>



                    <tr>
                        <th scope="row">
                            <?php
                            echo esc_html__(
                                'App Icon',
                                'local-directory-framework'
                            );
                            ?>
                        </th>
                        <td>
                            <?php
                            nwmd_directory_render_app_image_setting(
                                'first_page_icon_id',
                                $settings[
                                    'first_page_icon_id'
                                ],
                                $default_icon_url,
                                __(
                                    'Leaving this empty uses the packaged NW Monthly app icon.',
                                    'local-directory-framework'
                                )
                            );
                            ?>
                        </td>
                    </tr>
                </tbody>
            </table>

            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
