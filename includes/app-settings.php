<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return default app and first-page settings.
 *
 * @return array
 */
function nwmd_directory_get_app_settings_defaults() {

    return [
        'first_page_enabled'       => 1,
        'first_page_title'         => 'NW Monthly',
        'first_page_subtitle'      => 'Local Business Directory',
        'first_page_region'        => 'Washington • Oregon',
        'first_page_duration'      => 1.2,
        'first_page_frequency'     => 'session',
        'first_page_background_id' => 0,
        'first_page_icon_id'       => 0,
    ];
}

/**
 * Return saved app settings merged with defaults.
 *
 * @return array
 */
function nwmd_directory_get_app_settings() {

    $settings = get_option(
        'nwmd_directory_app_settings',
        []
    );

    if (!is_array($settings)) {
        $settings = [];
    }

    return wp_parse_args(
        $settings,
        nwmd_directory_get_app_settings_defaults()
    );
}

/**
 * Sanitize app settings before saving.
 *
 * @param mixed $input Submitted settings.
 *
 * @return array
 */
function nwmd_directory_sanitize_app_settings($input) {

    $defaults = nwmd_directory_get_app_settings_defaults();

    if (!is_array($input)) {
        $input = [];
    }

    $settings = $defaults;

    $settings['first_page_enabled'] =
        !empty($input['first_page_enabled'])
            ? 1
            : 0;

    $text_fields = [
        'first_page_title',
        'first_page_subtitle',
        'first_page_region',
    ];

    foreach ($text_fields as $field) {
        $value = isset($input[$field])
            ? sanitize_text_field($input[$field])
            : '';

        $settings[$field] = '' !== $value
            ? $value
            : $defaults[$field];
    }

    $duration = isset($input['first_page_duration'])
        && is_numeric($input['first_page_duration'])
            ? (float) $input['first_page_duration']
            : (float) $defaults['first_page_duration'];

    $settings['first_page_duration'] = round(
        min(
            10,
            max(
                0.3,
                $duration
            )
        ),
        1
    );

    $frequency = isset($input['first_page_frequency'])
        ? sanitize_key($input['first_page_frequency'])
        : $defaults['first_page_frequency'];

    $allowed_frequencies = [
        'session',
        'always',
    ];

    $settings['first_page_frequency'] = in_array(
        $frequency,
        $allowed_frequencies,
        true
    )
        ? $frequency
        : $defaults['first_page_frequency'];

    $image_fields = [
        'first_page_background_id',
        'first_page_icon_id',
    ];

    foreach ($image_fields as $field) {
        $attachment_id = isset($input[$field])
            ? absint($input[$field])
            : 0;

        $settings[$field] = (
            $attachment_id > 0 &&
            wp_attachment_is_image($attachment_id)
        )
            ? $attachment_id
            : 0;
    }

    return $settings;
}

/**
 * Register app settings.
 */
function nwmd_directory_register_app_settings() {

    register_setting(
        'nwmd_directory_app_settings_group',
        'nwmd_directory_app_settings',
        [
            'type'              => 'array',
            'sanitize_callback' =>
                'nwmd_directory_sanitize_app_settings',
            'default'           =>
                nwmd_directory_get_app_settings_defaults(),
        ]
    );
}

add_action(
    'admin_init',
    'nwmd_directory_register_app_settings'
);

/**
 * Return an app setting image URL or plugin default.
 *
 * @param string $setting_key          Attachment setting key.
 * @param string $default_relative_url Plugin-relative fallback.
 *
 * @return string
 */
function nwmd_directory_get_app_setting_image_url(
    $setting_key,
    $default_relative_url
) {

    $settings = nwmd_directory_get_app_settings();

    $attachment_id = isset($settings[$setting_key])
        ? absint($settings[$setting_key])
        : 0;

    if ($attachment_id > 0) {
        $attachment_url = wp_get_attachment_image_url(
            $attachment_id,
            'full'
        );

        if (is_string($attachment_url) && '' !== $attachment_url) {
            return $attachment_url;
        }
    }

    return NWMD_DIRECTORY_URL
        . ltrim($default_relative_url, '/');
}
