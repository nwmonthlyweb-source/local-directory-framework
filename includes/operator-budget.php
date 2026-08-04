<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return default Data Operator research safeguards.
 *
 * @return array
 */
function nwmd_directory_get_operator_budget_defaults() {

    return [
        'test_mode'            => 1,
        'monthly_budget_cents' => 500,
        'per_run_budget_cents' => 25,
        'max_runs_per_day'     => 1,
    ];
}

/**
 * Convert a submitted dollar value to whole cents.
 *
 * @param mixed $value          Submitted value.
 * @param int   $fallback_cents Fallback value.
 *
 * @return int
 */
function nwmd_directory_operator_dollars_to_cents(
    $value,
    $fallback_cents
) {

    $value = is_scalar($value)
        ? sanitize_text_field((string) $value)
        : '';

    $value = str_replace(',', '.', $value);

    if ('' === $value || !is_numeric($value)) {
        return absint($fallback_cents);
    }

    return absint(round(((float) $value) * 100));
}

/**
 * Sanitize Data Operator research safeguards.
 *
 * @param mixed $input Submitted settings.
 *
 * @return array
 */
function nwmd_directory_sanitize_operator_budget_settings($input) {

    $defaults = nwmd_directory_get_operator_budget_defaults();
    $input    = is_array($input) ? $input : [];

    $monthly_budget_cents =
        nwmd_directory_operator_dollars_to_cents(
            $input['monthly_budget_dollars'] ?? '',
            $defaults['monthly_budget_cents']
        );

    $monthly_budget_cents = max(
        100,
        min(100000, $monthly_budget_cents)
    );

    $per_run_budget_cents =
        nwmd_directory_operator_dollars_to_cents(
            $input['per_run_budget_dollars'] ?? '',
            $defaults['per_run_budget_cents']
        );

    $per_run_budget_cents = max(
        1,
        min($monthly_budget_cents, $per_run_budget_cents)
    );

    $max_runs_per_day = isset($input['max_runs_per_day'])
        ? absint($input['max_runs_per_day'])
        : $defaults['max_runs_per_day'];

    return [
        'test_mode'            => !empty($input['test_mode'])
            ? 1
            : 0,
        'monthly_budget_cents' => $monthly_budget_cents,
        'per_run_budget_cents' => $per_run_budget_cents,
        'max_runs_per_day'     => max(
            1,
            min(100, $max_runs_per_day)
        ),
    ];
}

/**
 * Register Data Operator research safeguards.
 */
function nwmd_directory_register_operator_budget_settings() {

    register_setting(
        'nwmd_directory_operator_budget_group',
        'nwmd_directory_operator_budget',
        [
            'type'              => 'array',
            'sanitize_callback' =>
                'nwmd_directory_sanitize_operator_budget_settings',
            'default'           =>
                nwmd_directory_get_operator_budget_defaults(),
            'show_in_rest'      => false,
        ]
    );
}

add_action(
    'admin_init',
    'nwmd_directory_register_operator_budget_settings'
);

/**
 * Return normalized Data Operator research safeguards.
 *
 * @return array
 */
function nwmd_directory_get_operator_budget_settings() {

    $defaults = nwmd_directory_get_operator_budget_defaults();
    $settings = get_option(
        'nwmd_directory_operator_budget',
        []
    );

    if (!is_array($settings)) {
        $settings = [];
    }

    $settings = wp_parse_args($settings, $defaults);

    $monthly_budget_cents = max(
        100,
        min(
            100000,
            absint($settings['monthly_budget_cents'])
        )
    );

    return [
        'test_mode'            => !empty($settings['test_mode'])
            ? 1
            : 0,
        'monthly_budget_cents' => $monthly_budget_cents,
        'per_run_budget_cents' => max(
            1,
            min(
                $monthly_budget_cents,
                absint($settings['per_run_budget_cents'])
            )
        ),
        'max_runs_per_day'     => max(
            1,
            min(
                100,
                absint($settings['max_runs_per_day'])
            )
        ),
    ];
}

/**
 * Return the current WordPress billing month.
 *
 * @return string
 */
function nwmd_directory_get_operator_billing_month() {

    return current_time('Y-m');
}

/**
 * Convert whole cents to integer micro-dollars.
 *
 * @param int $cents Whole cents.
 *
 * @return int
 */
function nwmd_directory_operator_cents_to_micros($cents) {

    return absint($cents) * 10000;
}

/**
 * Format whole cents as dollars.
 *
 * @param int $cents Whole cents.
 *
 * @return string
 */
function nwmd_directory_format_operator_cents($cents) {

    return '$' . number_format(absint($cents) / 100, 2);
}

/**
 * Format integer micro-dollars as dollars.
 *
 * @param int $micros Integer micro-dollars.
 *
 * @return string
 */
function nwmd_directory_format_operator_micros($micros) {

    return '$' . number_format(absint($micros) / 1000000, 4);
}

/**
 * Return usage totals for one billing month.
 *
 * @param string $billing_month Optional YYYY-MM key.
 *
 * @return array
 */
function nwmd_directory_get_operator_usage_summary(
    $billing_month = ''
) {

    global $wpdb;

    $summary = [
        'request_count'        => 0,
        'input_tokens'         => 0,
        'cached_input_tokens'  => 0,
        'output_tokens'        => 0,
        'web_search_calls'     => 0,
        'reserved_cost_micros' => 0,
        'recorded_cost_micros' => 0,
        'guarded_cost_micros'  => 0,
    ];

    $tables      = nwmd_directory_get_operator_table_names();
    $usage_table = isset($tables['usage'])
        ? (string) $tables['usage']
        : '';

    if (
        '' === $usage_table
        || !nwmd_directory_operator_table_exists($usage_table)
    ) {
        return $summary;
    }

    $billing_month = preg_match(
        '/^\d{4}-\d{2}$/',
        (string) $billing_month
    )
        ? (string) $billing_month
        : nwmd_directory_get_operator_billing_month();

    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT
                COUNT(*) AS request_count,
                COALESCE(SUM(input_tokens), 0) AS input_tokens,
                COALESCE(SUM(cached_input_tokens), 0)
                    AS cached_input_tokens,
                COALESCE(SUM(output_tokens), 0) AS output_tokens,
                COALESCE(SUM(web_search_calls), 0)
                    AS web_search_calls,
                COALESCE(SUM(reserved_cost_micros), 0)
                    AS reserved_cost_micros,
                COALESCE(SUM(recorded_cost_micros), 0)
                    AS recorded_cost_micros,
                COALESCE(
                    SUM(
                        GREATEST(
                            reserved_cost_micros,
                            recorded_cost_micros
                        )
                    ),
                    0
                ) AS guarded_cost_micros
            FROM {$usage_table}
            WHERE billing_month = %s
                AND status <> %s",
            $billing_month,
            'cancelled'
        ),
        ARRAY_A
    );

    if (!is_array($row)) {
        return $summary;
    }

    foreach ($summary as $key => $value) {
        $summary[$key] = absint($row[$key] ?? 0);
    }

    return $summary;
}

/**
 * Return guarded research requests created today.
 *
 * @return int
 */
function nwmd_directory_get_operator_usage_count_today() {

    global $wpdb;

    $tables      = nwmd_directory_get_operator_table_names();
    $usage_table = isset($tables['usage'])
        ? (string) $tables['usage']
        : '';

    if (
        '' === $usage_table
        || !nwmd_directory_operator_table_exists($usage_table)
    ) {
        return 0;
    }

    return absint(
        $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*)
                FROM {$usage_table}
                WHERE created_at BETWEEN %s AND %s
                    AND status <> %s",
                current_time('Y-m-d 00:00:00'),
                current_time('Y-m-d 23:59:59'),
                'cancelled'
            )
        )
    );
}

/**
 * Check hard limits before paid research is reserved.
 *
 * @param int $estimated_cost_micros Planned maximum cost.
 *
 * @return true|WP_Error
 */
function nwmd_directory_can_reserve_operator_usage(
    $estimated_cost_micros
) {

    $settings = nwmd_directory_get_operator_budget_settings();
    $estimated_cost_micros = absint($estimated_cost_micros);

    if ($estimated_cost_micros < 1) {
        return new WP_Error(
            'nwmd_operator_cost_invalid',
            __(
                'The planned research cost must be greater than zero.',
                'local-directory-framework'
            )
        );
    }

    $per_run_limit_micros =
        nwmd_directory_operator_cents_to_micros(
            $settings['per_run_budget_cents']
        );

    if ($estimated_cost_micros > $per_run_limit_micros) {
        return new WP_Error(
            'nwmd_operator_run_budget_exceeded',
            __(
                'The planned request exceeds the per-run hard limit.',
                'local-directory-framework'
            )
        );
    }

    $summary = nwmd_directory_get_operator_usage_summary();

    $monthly_limit_micros =
        nwmd_directory_operator_cents_to_micros(
            $settings['monthly_budget_cents']
        );

    if (
        $summary['guarded_cost_micros']
        + $estimated_cost_micros
        > $monthly_limit_micros
    ) {
        return new WP_Error(
            'nwmd_operator_monthly_budget_exceeded',
            __(
                'The monthly hard spending limit would be exceeded.',
                'local-directory-framework'
            )
        );
    }

    if (
        nwmd_directory_get_operator_usage_count_today()
        >= $settings['max_runs_per_day']
    ) {
        return new WP_Error(
            'nwmd_operator_daily_limit_reached',
            __(
                'The daily research-run limit has been reached.',
                'local-directory-framework'
            )
        );
    }

    return true;
}

/**
 * Reserve one future paid request in the usage ledger.
 *
 * This function does not call OpenAI.
 *
 * @param array $args Reservation data.
 *
 * @return array|WP_Error
 */
function nwmd_directory_reserve_operator_usage($args) {

    global $wpdb;

    $args = wp_parse_args(
        is_array($args) ? $args : [],
        [
            'run_id'                => 0,
            'model'                 => '',
            'operation_type'        => 'research',
            'estimated_cost_micros' => 0,
        ]
    );

    $allowed = nwmd_directory_can_reserve_operator_usage(
        $args['estimated_cost_micros']
    );

    if (is_wp_error($allowed)) {
        return $allowed;
    }

    $tables      = nwmd_directory_get_operator_table_names();
    $usage_table = isset($tables['usage'])
        ? (string) $tables['usage']
        : '';

    if (
        '' === $usage_table
        || !nwmd_directory_operator_table_exists($usage_table)
    ) {
        return new WP_Error(
            'nwmd_operator_usage_table_missing',
            __(
                'The operator usage ledger is unavailable.',
                'local-directory-framework'
            )
        );
    }

    $request_uuid = wp_generate_uuid4();
    $now          = current_time('mysql');

    $inserted = $wpdb->insert(
        $usage_table,
        [
            'request_uuid'         => $request_uuid,
            'response_id'          => '',
            'run_id'               => absint($args['run_id']),
            'billing_month'        =>
                nwmd_directory_get_operator_billing_month(),
            'operation_type'       =>
                sanitize_key($args['operation_type']),
            'model'                =>
                sanitize_text_field($args['model']),
            'status'               => 'reserved',
            'input_tokens'         => 0,
            'cached_input_tokens'  => 0,
            'output_tokens'        => 0,
            'web_search_calls'     => 0,
            'reserved_cost_micros' =>
                absint($args['estimated_cost_micros']),
            'recorded_cost_micros' => 0,
            'error_message'        => '',
            'created_by'           => get_current_user_id(),
            'started_at'           => null,
            'completed_at'         => null,
            'created_at'           => $now,
            'updated_at'           => $now,
        ]
    );

    if (false === $inserted) {
        return new WP_Error(
            'nwmd_operator_usage_reservation_failed',
            __(
                'The research budget could not be reserved.',
                'local-directory-framework'
            )
        );
    }

    return [
        'id'           => absint($wpdb->insert_id),
        'request_uuid' => $request_uuid,
    ];
}

/**
 * Update one usage-ledger record.
 *
 * @param string $request_uuid Request UUID.
 * @param array  $data         Usage data.
 *
 * @return true|WP_Error
 */
function nwmd_directory_update_operator_usage(
    $request_uuid,
    $data
) {

    global $wpdb;

    $request_uuid = sanitize_text_field($request_uuid);

    if ('' === $request_uuid) {
        return new WP_Error(
            'nwmd_operator_usage_request_invalid',
            __(
                'The usage request identifier is invalid.',
                'local-directory-framework'
            )
        );
    }

    $data = wp_parse_args(
        is_array($data) ? $data : [],
        [
            'response_id'         => '',
            'model'               => '',
            'status'              => 'complete',
            'input_tokens'        => 0,
            'cached_input_tokens' => 0,
            'output_tokens'       => 0,
            'web_search_calls'    => 0,
            'recorded_cost_micros'=> 0,
            'error_message'       => '',
        ]
    );

    $allowed_statuses = [
        'reserved',
        'started',
        'complete',
        'error',
        'cancelled',
    ];

    $status = sanitize_key($data['status']);

    if (!in_array($status, $allowed_statuses, true)) {
        return new WP_Error(
            'nwmd_operator_usage_status_invalid',
            __(
                'The usage status is invalid.',
                'local-directory-framework'
            )
        );
    }

    $tables      = nwmd_directory_get_operator_table_names();
    $usage_table = isset($tables['usage'])
        ? (string) $tables['usage']
        : '';

    if (
        '' === $usage_table
        || !nwmd_directory_operator_table_exists($usage_table)
    ) {
        return new WP_Error(
            'nwmd_operator_usage_table_missing',
            __(
                'The operator usage ledger is unavailable.',
                'local-directory-framework'
            )
        );
    }

    $now = current_time('mysql');

    $update = [
        'response_id'          =>
            sanitize_text_field($data['response_id']),
        'model'                =>
            sanitize_text_field($data['model']),
        'status'               => $status,
        'input_tokens'         =>
            absint($data['input_tokens']),
        'cached_input_tokens'  =>
            absint($data['cached_input_tokens']),
        'output_tokens'        =>
            absint($data['output_tokens']),
        'web_search_calls'     =>
            absint($data['web_search_calls']),
        'recorded_cost_micros' =>
            absint($data['recorded_cost_micros']),
        'error_message'        =>
            sanitize_textarea_field($data['error_message']),
        'updated_at'           => $now,
    ];

    $where = [
        'request_uuid' => $request_uuid,
        'status'       => 'reserved',
    ];

    if ('started' === $status) {
        $update['started_at'] = $now;
    } elseif (
        in_array(
            $status,
            ['complete', 'error', 'cancelled'],
            true
        )
    ) {
        $where['status']         = 'started';
        $update['completed_at']  = $now;
    }

    $updated = $wpdb->update(
        $usage_table,
        $update,
        $where
    );

    if (1 !== $updated) {
        return new WP_Error(
            'nwmd_operator_usage_update_failed',
            __(
                'The usage record could not complete its expected status transition.',
                'local-directory-framework'
            )
        );
    }

    return true;
}

/**
 * Render Data Operator research safeguards.
 */
function nwmd_directory_render_operator_budget_section() {

    $settings = nwmd_directory_get_operator_budget_settings();
    $summary  = nwmd_directory_get_operator_usage_summary();

    $monthly_limit_micros =
        nwmd_directory_operator_cents_to_micros(
            $settings['monthly_budget_cents']
        );

    $remaining_micros = max(
        0,
        $monthly_limit_micros
        - $summary['guarded_cost_micros']
    );

    ?>
    <h2>
        <?php
        echo esc_html__(
            'Research safeguards',
            'local-directory-framework'
        );
        ?>
    </h2>

    <div class="notice notice-warning inline">
        <p>
            <?php
            echo esc_html__(
                'Paid research is not active yet. This version adds the usage ledger and hard limits before any paid request is allowed.',
                'local-directory-framework'
            );
            ?>
        </p>
    </div>

    <table class="widefat striped" style="max-width: 760px;">
        <tbody>
            <tr>
                <th scope="row" style="width: 220px;">
                    <?php
                    echo esc_html__(
                        'Test mode',
                        'local-directory-framework'
                    );
                    ?>
                </th>
                <td>
                    <?php
                    echo esc_html(
                        $settings['test_mode']
                            ? __(
                                'Enabled',
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
                        'Monthly hard limit',
                        'local-directory-framework'
                    );
                    ?>
                </th>
                <td>
                    <?php
                    echo esc_html(
                        nwmd_directory_format_operator_cents(
                            $settings['monthly_budget_cents']
                        )
                    );
                    ?>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <?php
                    echo esc_html__(
                        'Per-run hard limit',
                        'local-directory-framework'
                    );
                    ?>
                </th>
                <td>
                    <?php
                    echo esc_html(
                        nwmd_directory_format_operator_cents(
                            $settings['per_run_budget_cents']
                        )
                    );
                    ?>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <?php
                    echo esc_html__(
                        'Maximum runs per day',
                        'local-directory-framework'
                    );
                    ?>
                </th>
                <td>
                    <?php
                    echo esc_html(
                        (string) $settings['max_runs_per_day']
                    );
                    ?>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <?php
                    echo esc_html__(
                        'Guarded usage this month',
                        'local-directory-framework'
                    );
                    ?>
                </th>
                <td>
                    <?php
                    echo esc_html(
                        nwmd_directory_format_operator_micros(
                            $summary['guarded_cost_micros']
                        )
                    );
                    ?>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <?php
                    echo esc_html__(
                        'Remaining guarded budget',
                        'local-directory-framework'
                    );
                    ?>
                </th>
                <td>
                    <?php
                    echo esc_html(
                        nwmd_directory_format_operator_micros(
                            $remaining_micros
                        )
                    );
                    ?>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <?php
                    echo esc_html__(
                        'Usage ledger records',
                        'local-directory-framework'
                    );
                    ?>
                </th>
                <td>
                    <?php
                    echo esc_html(
                        (string) $summary['request_count']
                    );
                    ?>
                </td>
            </tr>
        </tbody>
    </table>

    <form method="post" action="options.php">
        <?php
        settings_fields(
            'nwmd_directory_operator_budget_group'
        );
        ?>

        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row">
                        <?php
                        echo esc_html__(
                            'Test mode',
                            'local-directory-framework'
                        );
                        ?>
                    </th>
                    <td>
                        <label>
                            <input
                                type="checkbox"
                                name="nwmd_directory_operator_budget[test_mode]"
                                value="1"
                                <?php
                                checked($settings['test_mode']);
                                ?>
                            >
                            <?php
                            echo esc_html__(
                                'Keep research limited to supervised pilot runs.',
                                'local-directory-framework'
                            );
                            ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="nwmd-monthly-budget">
                            <?php
                            echo esc_html__(
                                'Monthly hard limit',
                                'local-directory-framework'
                            );
                            ?>
                        </label>
                    </th>
                    <td>
                        <input
                            id="nwmd-monthly-budget"
                            type="number"
                            min="1.00"
                            max="1000.00"
                            step="0.01"
                            name="nwmd_directory_operator_budget[monthly_budget_dollars]"
                            value="<?php
                                echo esc_attr(
                                    number_format(
                                        $settings['monthly_budget_cents']
                                        / 100,
                                        2,
                                        '.',
                                        ''
                                    )
                                );
                            ?>"
                        >
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="nwmd-per-run-budget">
                            <?php
                            echo esc_html__(
                                'Per-run hard limit',
                                'local-directory-framework'
                            );
                            ?>
                        </label>
                    </th>
                    <td>
                        <input
                            id="nwmd-per-run-budget"
                            type="number"
                            min="0.01"
                            max="1000.00"
                            step="0.01"
                            name="nwmd_directory_operator_budget[per_run_budget_dollars]"
                            value="<?php
                                echo esc_attr(
                                    number_format(
                                        $settings['per_run_budget_cents']
                                        / 100,
                                        2,
                                        '.',
                                        ''
                                    )
                                );
                            ?>"
                        >
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="nwmd-max-runs-day">
                            <?php
                            echo esc_html__(
                                'Maximum runs per day',
                                'local-directory-framework'
                            );
                            ?>
                        </label>
                    </th>
                    <td>
                        <input
                            id="nwmd-max-runs-day"
                            type="number"
                            min="1"
                            max="100"
                            step="1"
                            name="nwmd_directory_operator_budget[max_runs_per_day]"
                            value="<?php
                                echo esc_attr(
                                    (string) $settings['max_runs_per_day']
                                );
                            ?>"
                        >
                    </td>
                </tr>
            </tbody>
        </table>

        <?php
        submit_button(
            __(
                'Save Research Safeguards',
                'local-directory-framework'
            )
        );
        ?>
    </form>
    <?php
}