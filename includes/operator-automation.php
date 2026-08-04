<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return default controlled automation settings.
 *
 * Automation remains disabled by default.
 *
 * @return array
 */
function nwmd_directory_get_operator_automation_defaults() {

    return [
        'enabled' => 0,
        'weekday' => 2,
        'hour'    => 3,
    ];
}

/**
 * Sanitize controlled automation settings.
 *
 * Automation cannot be enabled while supervised test mode is active.
 *
 * @param mixed $input Submitted settings.
 *
 * @return array
 */
function nwmd_directory_sanitize_operator_automation_settings(
    $input
) {

    $defaults = nwmd_directory_get_operator_automation_defaults();
    $input    = is_array($input) ? $input : [];

    $weekday = isset($input['weekday'])
        ? absint($input['weekday'])
        : $defaults['weekday'];

    $hour = isset($input['hour'])
        ? absint($input['hour'])
        : $defaults['hour'];

    $budget_settings =
        nwmd_directory_get_operator_budget_settings();

    $requested_enabled = !empty($input['enabled']);

    if (
        $requested_enabled
        && !empty($budget_settings['test_mode'])
    ) {
        add_settings_error(
            'nwmd_directory_operator_automation',
            'nwmd_operator_automation_test_mode',
            __(
                'Automation remains disabled while supervised test mode is enabled.',
                'local-directory-framework'
            ),
            'warning'
        );
    }

    return [
        'enabled' => (
            $requested_enabled
            && empty($budget_settings['test_mode'])
        ) ? 1 : 0,
        'weekday' => min(6, $weekday),
        'hour'    => min(23, $hour),
    ];
}

/**
 * Register controlled automation settings.
 */
function nwmd_directory_register_operator_automation_settings() {

    register_setting(
        'nwmd_directory_operator_automation_group',
        'nwmd_directory_operator_automation',
        [
            'type'              => 'array',
            'sanitize_callback' =>
                'nwmd_directory_sanitize_operator_automation_settings',
            'default'           =>
                nwmd_directory_get_operator_automation_defaults(),
            'show_in_rest'      => false,
        ]
    );
}

add_action(
    'admin_init',
    'nwmd_directory_register_operator_automation_settings'
);

/**
 * Return normalized controlled automation settings.
 *
 * @return array
 */
function nwmd_directory_get_operator_automation_settings() {

    $defaults = nwmd_directory_get_operator_automation_defaults();

    $settings = get_option(
        'nwmd_directory_operator_automation',
        []
    );

    if (!is_array($settings)) {
        $settings = [];
    }

    $settings = wp_parse_args(
        $settings,
        $defaults
    );

    return [
        'enabled' => !empty($settings['enabled']) ? 1 : 0,
        'weekday' => min(
            6,
            absint($settings['weekday'])
        ),
        'hour'    => min(
            23,
            absint($settings['hour'])
        ),
    ];
}

/**
 * Return default automation health values.
 *
 * @return array
 */
function nwmd_directory_get_operator_automation_status_defaults() {

    return [
        'last_attempt_at' => '',
        'last_success_at' => '',
        'last_error_at'   => '',
        'last_result'     => '',
        'last_error'      => '',
        'last_run_id'     => 0,
    ];
}

/**
 * Return normalized automation health values.
 *
 * @return array
 */
function nwmd_directory_get_operator_automation_status() {

    $defaults =
        nwmd_directory_get_operator_automation_status_defaults();

    $status = get_option(
        'nwmd_directory_operator_automation_status',
        []
    );

    if (!is_array($status)) {
        $status = [];
    }

    $status = wp_parse_args(
        $status,
        $defaults
    );

    return [
        'last_attempt_at' => sanitize_text_field(
            (string) $status['last_attempt_at']
        ),
        'last_success_at' => sanitize_text_field(
            (string) $status['last_success_at']
        ),
        'last_error_at' => sanitize_text_field(
            (string) $status['last_error_at']
        ),
        'last_result' => sanitize_text_field(
            (string) $status['last_result']
        ),
        'last_error' => sanitize_text_field(
            (string) $status['last_error']
        ),
        'last_run_id' => absint(
            $status['last_run_id']
        ),
    ];
}

/**
 * Update automation health values.
 *
 * This is prepared for the later scheduling milestone. It does not run
 * automation or contact OpenAI.
 *
 * @param array $data Health changes.
 *
 * @return bool
 */
function nwmd_directory_update_operator_automation_status(
    $data
) {

    $current = nwmd_directory_get_operator_automation_status();

    $status = wp_parse_args(
        is_array($data) ? $data : [],
        $current
    );

    $normalized = [
        'last_attempt_at' => sanitize_text_field(
            (string) $status['last_attempt_at']
        ),
        'last_success_at' => sanitize_text_field(
            (string) $status['last_success_at']
        ),
        'last_error_at' => sanitize_text_field(
            (string) $status['last_error_at']
        ),
        'last_result' => sanitize_text_field(
            (string) $status['last_result']
        ),
        'last_error' => sanitize_text_field(
            (string) $status['last_error']
        ),
        'last_run_id' => absint(
            $status['last_run_id']
        ),
    ];

    return update_option(
        'nwmd_directory_operator_automation_status',
        $normalized,
        false
    );
}

/**
 * Format one stored automation date.
 *
 * @param string $value Stored MySQL date.
 *
 * @return string
 */
function nwmd_directory_format_operator_automation_date(
    $value
) {

    $value = sanitize_text_field((string) $value);

    if ('' === $value) {
        return __(
            'Never',
            'local-directory-framework'
        );
    }

    $date = date_create_immutable(
        $value,
        wp_timezone()
    );

    if (!$date instanceof DateTimeImmutable) {
        return $value;
    }

    return wp_date(
        'M j, Y g:i a',
        $date->getTimestamp(),
        wp_timezone()
    );
}

/**
 * Render controlled automation settings and health.
 *
 * Version 0.1.88 adds configuration and health visibility only.
 * No scheduled execution is installed by this milestone.
 */
function nwmd_directory_render_operator_automation_section() {

    $settings =
        nwmd_directory_get_operator_automation_settings();

    $status =
        nwmd_directory_get_operator_automation_status();

    $budget =
        nwmd_directory_get_operator_budget_settings();

    $weekdays = [
        0 => __('Sunday', 'local-directory-framework'),
        1 => __('Monday', 'local-directory-framework'),
        2 => __('Tuesday', 'local-directory-framework'),
        3 => __('Wednesday', 'local-directory-framework'),
        4 => __('Thursday', 'local-directory-framework'),
        5 => __('Friday', 'local-directory-framework'),
        6 => __('Saturday', 'local-directory-framework'),
    ];

    ?>
    <h2>
        <?php
        echo esc_html__(
            'Controlled automation',
            'local-directory-framework'
        );
        ?>
    </h2>

    <div class="notice notice-info inline">
        <p>
            <strong>
                <?php
                echo esc_html__(
                    'Dashboard setup only.',
                    'local-directory-framework'
                );
                ?>
            </strong>
            <?php
            echo esc_html__(
                'Automatic queue processing is not installed or active in this version. Saving these settings does not contact OpenAI, spend budget, claim a checkpoint, or change directory data.',
                'local-directory-framework'
            );
            ?>
        </p>
    </div>

    <?php if (!empty($budget['test_mode'])) : ?>
        <div class="notice notice-warning inline">
            <p>
                <?php
                echo esc_html__(
                    'Automation is locked while supervised test mode is enabled.',
                    'local-directory-framework'
                );
                ?>
            </p>
        </div>
    <?php endif; ?>

    <table class="widefat striped" style="max-width: 760px;">
        <tbody>
            <tr>
                <th scope="row" style="width: 230px;">
                    <?php
                    echo esc_html__(
                        'Automation setting',
                        'local-directory-framework'
                    );
                    ?>
                </th>
                <td>
                    <?php
                    echo esc_html(
                        $settings['enabled']
                            ? __(
                                'Enabled in settings',
                                'local-directory-framework'
                            )
                            : __(
                                'Disabled',
                                'local-directory-framework'
                            )
                    );
                    ?>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <?php
                    echo esc_html__(
                        'Scheduling engine',
                        'local-directory-framework'
                    );
                    ?>
                </th>
                <td>
                    <?php
                    echo esc_html__(
                        'Not installed in this milestone',
                        'local-directory-framework'
                    );
                    ?>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <?php
                    echo esc_html__(
                        'Configured window',
                        'local-directory-framework'
                    );
                    ?>
                </th>
                <td>
                    <?php
                    echo esc_html(
                        sprintf(
                            /* translators: 1: weekday, 2: hour. */
                            __(
                                '%1$s at %2$s:00 in the WordPress site timezone',
                                'local-directory-framework'
                            ),
                            $weekdays[$settings['weekday']],
                            str_pad(
                                (string) $settings['hour'],
                                2,
                                '0',
                                STR_PAD_LEFT
                            )
                        )
                    );
                    ?>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <?php
                    echo esc_html__(
                        'Last attempt',
                        'local-directory-framework'
                    );
                    ?>
                </th>
                <td>
                    <?php
                    echo esc_html(
                        nwmd_directory_format_operator_automation_date(
                            $status['last_attempt_at']
                        )
                    );
                    ?>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <?php
                    echo esc_html__(
                        'Last successful research',
                        'local-directory-framework'
                    );
                    ?>
                </th>
                <td>
                    <?php
                    echo esc_html(
                        nwmd_directory_format_operator_automation_date(
                            $status['last_success_at']
                        )
                    );
                    ?>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <?php
                    echo esc_html__(
                        'Last result',
                        'local-directory-framework'
                    );
                    ?>
                </th>
                <td>
                    <?php
                    echo esc_html(
                        '' !== $status['last_result']
                            ? $status['last_result']
                            : __(
                                'No automated run has occurred.',
                                'local-directory-framework'
                            )
                    );
                    ?>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <?php
                    echo esc_html__(
                        'Last error',
                        'local-directory-framework'
                    );
                    ?>
                </th>
                <td>
                    <?php
                    echo esc_html(
                        '' !== $status['last_error']
                            ? $status['last_error']
                            : __(
                                'None',
                                'local-directory-framework'
                            )
                    );
                    ?>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <?php
                    echo esc_html__(
                        'Last automated run ID',
                        'local-directory-framework'
                    );
                    ?>
                </th>
                <td>
                    <?php
                    echo esc_html(
                        $status['last_run_id'] > 0
                            ? (string) $status['last_run_id']
                            : __(
                                'None',
                                'local-directory-framework'
                            )
                    );
                    ?>
                </td>
            </tr>
        </tbody>
    </table>

    <?php
    settings_errors(
        'nwmd_directory_operator_automation'
    );
    ?>

    <form
        method="post"
        action="<?php echo esc_url(admin_url('options.php')); ?>"
    >
        <?php
        settings_fields(
            'nwmd_directory_operator_automation_group'
        );
        ?>

        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row">
                        <?php
                        echo esc_html__(
                            'Enable automation',
                            'local-directory-framework'
                        );
                        ?>
                    </th>
                    <td>
                        <label>
                            <input
                                type="checkbox"
                                name="nwmd_directory_operator_automation[enabled]"
                                value="1"
                                <?php checked($settings['enabled']); ?>
                                <?php
                                disabled(
                                    !empty($budget['test_mode'])
                                );
                                ?>
                            >
                            <?php
                            echo esc_html__(
                                'Prepare this site for one controlled weekly operator run.',
                                'local-directory-framework'
                            );
                            ?>
                        </label>

                        <p class="description">
                            <?php
                            echo esc_html__(
                                'This setting is stored now, but no scheduled execution exists in version 0.1.88.',
                                'local-directory-framework'
                            );
                            ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="nwmd-operator-automation-weekday">
                            <?php
                            echo esc_html__(
                                'Weekday',
                                'local-directory-framework'
                            );
                            ?>
                        </label>
                    </th>
                    <td>
                        <select
                            id="nwmd-operator-automation-weekday"
                            name="nwmd_directory_operator_automation[weekday]"
                        >
                            <?php
                            foreach ($weekdays as $value => $label) :
                                ?>
                                <option
                                    value="<?php
                                    echo esc_attr(
                                        (string) $value
                                    );
                                    ?>"
                                    <?php
                                    selected(
                                        $settings['weekday'],
                                        $value
                                    );
                                    ?>
                                >
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="nwmd-operator-automation-hour">
                            <?php
                            echo esc_html__(
                                'Hour',
                                'local-directory-framework'
                            );
                            ?>
                        </label>
                    </th>
                    <td>
                        <input
                            id="nwmd-operator-automation-hour"
                            type="number"
                            min="0"
                            max="23"
                            step="1"
                            name="nwmd_directory_operator_automation[hour]"
                            value="<?php
                            echo esc_attr(
                                (string) $settings['hour']
                            );
                            ?>"
                        >

                        <p class="description">
                            <?php
                            echo esc_html__(
                                'Use 0 through 23 in the WordPress site timezone.',
                                'local-directory-framework'
                            );
                            ?>
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>

        <?php
        submit_button(
            __(
                'Save Automation Settings',
                'local-directory-framework'
            )
        );
        ?>
    </form>
    <?php
}