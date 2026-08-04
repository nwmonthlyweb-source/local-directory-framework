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
 * Return the controlled automation cron hook.
 *
 * @return string
 */
function nwmd_directory_get_operator_automation_hook() {

    return 'nwmd_directory_operator_automation_heartbeat';
}

/**
 * Calculate the next configured weekly scheduler time.
 *
 * @param array $settings Automation settings.
 *
 * @return int
 */
function nwmd_directory_get_next_operator_automation_timestamp(
    $settings
) {

    $weekday = min(
        6,
        absint($settings['weekday'] ?? 2)
    );

    $hour = min(
        23,
        absint($settings['hour'] ?? 3)
    );

    $now = current_datetime();

    $target = $now->setTime(
        $hour,
        0,
        0
    );

    $days_ahead = (
        $weekday
        - absint($now->format('w'))
        + 7
    ) % 7;

    if ($days_ahead > 0) {
        $target = $target->modify(
            sprintf(
                '+%d days',
                $days_ahead
            )
        );
    }

    if ($target <= $now) {
        $target = $target->modify('+7 days');
    }

    return $target->getTimestamp();
}

/**
 * Store one scheduler configuration error.
 *
 * @param string $message Safe error message.
 *
 * @return WP_Error
 */
function nwmd_directory_record_operator_schedule_error(
    $message
) {

    $message = sanitize_text_field((string) $message);
    $now     = current_time('mysql');

    nwmd_directory_update_operator_automation_status(
        [
            'last_error_at' => $now,
            'last_result'   =>
                __(
                    'Scheduler configuration failed.',
                    'local-directory-framework'
                ),
            'last_error'    => $message,
        ]
    );

    return new WP_Error(
        'nwmd_operator_schedule_failed',
        $message
    );
}

/**
 * Clear every controlled automation heartbeat.
 *
 * @return true|WP_Error
 */
function nwmd_directory_clear_operator_automation_schedule() {

    $cleared = wp_clear_scheduled_hook(
        nwmd_directory_get_operator_automation_hook(),
        [],
        true
    );

    if (is_wp_error($cleared)) {
        return nwmd_directory_record_operator_schedule_error(
            $cleared->get_error_message()
        );
    }

    if (false === $cleared) {
        return nwmd_directory_record_operator_schedule_error(
            __(
                'The controlled automation schedule could not be cleared.',
                'local-directory-framework'
            )
        );
    }

    return true;
}

/**
 * Rebuild the weekly heartbeat from current settings.
 *
 * This schedules only a no-cost heartbeat. It does not claim a queue
 * item, contact OpenAI, reserve budget, or change directory data.
 *
 * @return true|WP_Error
 */
function nwmd_directory_sync_operator_automation_schedule() {

    $cleared =
        nwmd_directory_clear_operator_automation_schedule();

    if (is_wp_error($cleared)) {
        return $cleared;
    }

    $settings =
        nwmd_directory_get_operator_automation_settings();

    $budget =
        nwmd_directory_get_operator_budget_settings();

    if (
        empty($settings['enabled'])
        || !empty($budget['test_mode'])
    ) {
        return true;
    }

    $scheduled = wp_schedule_event(
        nwmd_directory_get_next_operator_automation_timestamp(
            $settings
        ),
        'weekly',
        nwmd_directory_get_operator_automation_hook(),
        [],
        true
    );

    if (is_wp_error($scheduled)) {
        return nwmd_directory_record_operator_schedule_error(
            $scheduled->get_error_message()
        );
    }

    if (true !== $scheduled) {
        return nwmd_directory_record_operator_schedule_error(
            __(
                'The controlled weekly heartbeat could not be scheduled.',
                'local-directory-framework'
            )
        );
    }

    return true;
}

/**
 * Ensure scheduler state matches the saved settings.
 */
function nwmd_directory_maybe_sync_operator_automation_schedule() {

    $settings =
        nwmd_directory_get_operator_automation_settings();

    $budget =
        nwmd_directory_get_operator_budget_settings();

    $next = wp_next_scheduled(
        nwmd_directory_get_operator_automation_hook()
    );

    $should_run = (
        !empty($settings['enabled'])
        && empty($budget['test_mode'])
    );

    if ($should_run && false === $next) {
        nwmd_directory_sync_operator_automation_schedule();
        return;
    }

    if (!$should_run && false !== $next) {
        nwmd_directory_clear_operator_automation_schedule();
    }
}

add_action(
    'init',
    'nwmd_directory_maybe_sync_operator_automation_schedule',
    30
);

/**
 * Rebuild the heartbeat after relevant settings change.
 */
function nwmd_directory_handle_operator_automation_settings_change() {

    nwmd_directory_sync_operator_automation_schedule();
}

add_action(
    'update_option_nwmd_directory_operator_automation',
    'nwmd_directory_handle_operator_automation_settings_change',
    10,
    3
);

add_action(
    'add_option_nwmd_directory_operator_automation',
    'nwmd_directory_handle_operator_automation_settings_change',
    10,
    2
);

add_action(
    'update_option_nwmd_directory_operator_budget',
    'nwmd_directory_handle_operator_automation_settings_change',
    10,
    3
);

add_action(
    'add_option_nwmd_directory_operator_budget',
    'nwmd_directory_handle_operator_automation_settings_change',
    10,
    2
);

/**
 * Build one no-cost plan for controlled automated research.
 *
 * This function does not claim a checkpoint, create a run, contact OpenAI,
 * reserve budget, create drafts, publish records, create Deals, change
 * rankings, or complete checkpoints.
 *
 * @return array|WP_Error
 */
function nwmd_directory_get_operator_automation_research_plan() {

    $required_functions = [
        'nwmd_directory_get_operator_automation_settings',
        'nwmd_directory_get_operator_budget_settings',
        'nwmd_directory_get_operator_storage_status',
        'nwmd_directory_get_openai_configuration_status',
        'nwmd_directory_validate_single_active_checkpoint',
        'nwmd_directory_get_current_operator_checkpoint_context',
        'nwmd_directory_operator_run_has_research_preview',
        'nwmd_directory_get_operator_checkpoint_counts',
        'nwmd_directory_can_reserve_operator_usage',
    ];

    foreach ($required_functions as $required_function) {
        if (!function_exists($required_function)) {
            return new WP_Error(
                'nwmd_operator_automation_plan_unavailable',
                __(
                    'One or more required automation planning functions are unavailable.',
                    'local-directory-framework'
                )
            );
        }
    }

    $settings =
        nwmd_directory_get_operator_automation_settings();

    if (empty($settings['enabled'])) {
        return new WP_Error(
            'nwmd_operator_automation_plan_disabled',
            __(
                'Controlled automation is disabled.',
                'local-directory-framework'
            )
        );
    }

    $budget =
        nwmd_directory_get_operator_budget_settings();

    if (!empty($budget['test_mode'])) {
        return new WP_Error(
            'nwmd_operator_automation_plan_test_mode',
            __(
                'Controlled automation is unavailable while supervised test mode is enabled.',
                'local-directory-framework'
            )
        );
    }

    $storage =
        nwmd_directory_get_operator_storage_status();

    if (empty($storage['ready'])) {
        return new WP_Error(
            'nwmd_operator_automation_plan_storage_incomplete',
            __(
                'Operator storage is incomplete.',
                'local-directory-framework'
            )
        );
    }

    $configuration =
        nwmd_directory_get_openai_configuration_status();

    if (empty($configuration['configured'])) {
        return new WP_Error(
            'nwmd_operator_automation_plan_openai_missing',
            __(
                'OpenAI is not configured.',
                'local-directory-framework'
            )
        );
    }

    if (
        'gpt-5.6-terra'
        !== (string) ($configuration['model'] ?? '')
    ) {
        return new WP_Error(
            'nwmd_operator_automation_plan_model_unsupported',
            __(
                'Controlled automated research currently requires gpt-5.6-terra.',
                'local-directory-framework'
            )
        );
    }

    $valid =
        nwmd_directory_validate_single_active_checkpoint();

    if (is_wp_error($valid)) {
        return $valid;
    }

    $context =
        nwmd_directory_get_current_operator_checkpoint_context();

    $run_id = absint($context['run_id'] ?? 0);

    if (!empty($context)) {
        if ($run_id < 1) {
            return new WP_Error(
                'nwmd_operator_automation_plan_run_missing',
                __(
                    'The active checkpoint does not have a valid started run.',
                    'local-directory-framework'
                )
            );
        }

        if (
            nwmd_directory_operator_run_has_research_preview(
                $run_id
            )
        ) {
            return [
                'action'               => 'awaiting_review',
                'claim_required'       => false,
                'research_required'    => false,
                'run_id'               => $run_id,
                'planned_cost_micros'  => 0,
                'context'              => $context,
            ];
        }

        $action         = 'resume_and_research';
        $claim_required = false;
    } else {
        $counts =
            nwmd_directory_get_operator_checkpoint_counts();

        if (absint($counts['pending'] ?? 0) < 1) {
            return new WP_Error(
                'nwmd_operator_automation_plan_queue_empty',
                __(
                    'No pending specialty checkpoints remain.',
                    'local-directory-framework'
                )
            );
        }

        $action         = 'claim_and_research';
        $claim_required = true;
    }

    $planned_cost_micros = 250000;

    $eligible =
        nwmd_directory_can_reserve_operator_usage(
            $planned_cost_micros
        );

    if (is_wp_error($eligible)) {
        return $eligible;
    }

    return [
        'action'               => $action,
        'claim_required'       => $claim_required,
        'research_required'    => true,
        'run_id'               => $run_id,
        'planned_cost_micros'  => $planned_cost_micros,
        'context'              => $context,
    ];
}

/**
 * Execute one controlled automated research plan.
 *
 * This function may claim or resume one checkpoint, create or resume its
 * operator run, reserve guarded budget, and contact OpenAI once. It does not
 * create Business drafts, publish records, create Deals, change rankings, or
 * complete the checkpoint.
 *
 * This function is not connected to the scheduler yet.
 *
 * @return array|WP_Error
 */
function nwmd_directory_run_operator_automation_research() {

    $required_functions = [
        'nwmd_directory_get_operator_automation_research_plan',
        'nwmd_directory_claim_next_operator_checkpoint',
        'nwmd_directory_run_operator_research_preview',
    ];

    foreach ($required_functions as $required_function) {
        if (!function_exists($required_function)) {
            return new WP_Error(
                'nwmd_operator_automation_execution_unavailable',
                __(
                    'One or more required automated research functions are unavailable.',
                    'local-directory-framework'
                )
            );
        }
    }

    $plan =
        nwmd_directory_get_operator_automation_research_plan();

    if (is_wp_error($plan)) {
        return $plan;
    }

    $action = sanitize_key(
        (string) ($plan['action'] ?? '')
    );

    if ('awaiting_review' === $action) {
        return [
            'action'             => 'awaiting_review',
            'research_performed' => false,
            'run_id'             =>
                absint($plan['run_id'] ?? 0),
        ];
    }

    if ('claim_and_research' === $action) {
        $context =
            nwmd_directory_claim_next_operator_checkpoint();

        if (is_wp_error($context)) {
            return $context;
        }
    } elseif ('resume_and_research' === $action) {
        $context = isset($plan['context'])
            && is_array($plan['context'])
                ? $plan['context']
                : [];
    } else {
        return new WP_Error(
            'nwmd_operator_automation_action_invalid',
            __(
                'The automated research plan returned an unsupported action.',
                'local-directory-framework'
            )
        );
    }

    $run_id = absint(
        $context['run_id']
            ?? $plan['run_id']
            ?? 0
    );

    if ($run_id < 1) {
        return new WP_Error(
            'nwmd_operator_automation_run_missing',
            __(
                'The automated research checkpoint does not have a valid run.',
                'local-directory-framework'
            )
        );
    }

    $checkpoint_action = sanitize_key(
        (string) (
            $context['action']
                ?? (
                    'claim_and_research' === $action
                        ? 'started'
                        : 'resumed'
                )
        )
    );

    $result =
        nwmd_directory_run_operator_research_preview(
            'automated'
        );

    if (is_wp_error($result)) {
        if (
            'nwmd_operator_preview_already_exists'
            === $result->get_error_code()
        ) {
            return [
                'action'             => 'awaiting_review',
                'research_performed' => false,
                'run_id'             => $run_id,
                'checkpoint_action'  => $checkpoint_action,
            ];
        }

        return $result;
    }

    return array_merge(
        $result,
        [
            'research_performed' => true,
            'run_id'             => $run_id,
            'checkpoint_action'  => $checkpoint_action,
        ]
    );
}

/**
 * Execute one controlled automation heartbeat.
 *
 * When automation is enabled and supervised test mode is disabled, this
 * function may claim or resume one checkpoint, reserve guarded budget, and
 * contact OpenAI once. It does not create Business drafts, publish records,
 * create Deals, change rankings, or complete the checkpoint.
 */
function nwmd_directory_handle_operator_automation_heartbeat() {

    $settings =
        nwmd_directory_get_operator_automation_settings();

    $budget =
        nwmd_directory_get_operator_budget_settings();

    if (
        empty($settings['enabled'])
        || !empty($budget['test_mode'])
    ) {
        nwmd_directory_clear_operator_automation_schedule();
        return;
    }

    $now    = current_time('mysql');
    $result =
        nwmd_directory_run_operator_automation_research();

    if (is_wp_error($result)) {
        $context = function_exists(
            'nwmd_directory_get_current_operator_checkpoint_context'
        )
            ? nwmd_directory_get_current_operator_checkpoint_context()
            : [];

        $status = [
            'last_attempt_at' => $now,
            'last_error_at'   => $now,
            'last_result'     => __(
                'Automated research stopped safely.',
                'local-directory-framework'
            ),
            'last_error'      =>
                $result->get_error_message(),
        ];

        $run_id = absint($context['run_id'] ?? 0);

        if ($run_id > 0) {
            $status['last_run_id'] = $run_id;
        }

        nwmd_directory_update_operator_automation_status(
            $status
        );

        return;
    }

    $run_id = absint($result['run_id'] ?? 0);

    if (empty($result['research_performed'])) {
        nwmd_directory_update_operator_automation_status(
            [
                'last_attempt_at' => $now,
                'last_error_at'   => '',
                'last_result'     => __(
                    'The active research preview is awaiting supervised review.',
                    'local-directory-framework'
                ),
                'last_error'      => '',
                'last_run_id'     => $run_id,
            ]
        );

        return;
    }

    nwmd_directory_update_operator_automation_status(
        [
            'last_attempt_at' => $now,
            'last_success_at' => $now,
            'last_error_at'   => '',
            'last_result'     => sprintf(
                /* translators: 1: Run ID, 2: candidate business count. */
                __(
                    'Automated research completed for run #%1$d with %2$d candidate businesses. Supervised review is required.',
                    'local-directory-framework'
                ),
                $run_id,
                absint($result['business_count'] ?? 0)
            ),
            'last_error'      => '',
            'last_run_id'     => $run_id,
        ]
    );
}

add_action(
    'nwmd_directory_operator_automation_heartbeat',
    'nwmd_directory_handle_operator_automation_heartbeat'
);

/**
 * Store one short-lived automation self-test notice.
 *
 * @param array $notice Notice data.
 */
function nwmd_directory_store_operator_automation_test_notice(
    $notice
) {

    set_transient(
        'nwmd_operator_automation_test_'
            . get_current_user_id(),
        is_array($notice) ? $notice : [],
        MINUTE_IN_SECONDS
    );
}

/**
 * Return and remove the current user's automation self-test notice.
 *
 * @return array
 */
function nwmd_directory_get_operator_automation_test_notice() {

    $key = 'nwmd_operator_automation_test_'
        . get_current_user_id();

    $notice = get_transient($key);

    delete_transient($key);

    return is_array($notice) ? $notice : [];
}

/**
 * Run one deterministic no-cost heartbeat self-test.
 *
 * This test does not claim queue records, contact OpenAI, reserve
 * budget, create drafts, publish records, create Deals, change
 * rankings, or complete checkpoints.
 *
 * @return array|WP_Error
 */
function nwmd_directory_run_operator_automation_self_test() {

    $budget =
        nwmd_directory_get_operator_budget_settings();

    if (empty($budget['test_mode'])) {
        return new WP_Error(
            'nwmd_operator_automation_test_mode_required',
            __(
                'Enable supervised test mode before running this self-test.',
                'local-directory-framework'
            )
        );
    }

    $scheduled = wp_next_scheduled(
        nwmd_directory_get_operator_automation_hook()
    );

    if (false !== $scheduled) {
        return new WP_Error(
            'nwmd_operator_automation_test_schedule_present',
            __(
                'A heartbeat is unexpectedly scheduled while supervised test mode is enabled.',
                'local-directory-framework'
            )
        );
    }

    $now = current_time('mysql');

    $message = __(
        'Manual no-cost heartbeat self-test passed. No queue item, OpenAI request, budget reservation, or directory record was created.',
        'local-directory-framework'
    );

    nwmd_directory_update_operator_automation_status(
        [
            'last_attempt_at' => $now,
            'last_error_at'   => '',
            'last_result'     => $message,
            'last_error'      => '',
        ]
    );

    $status =
        nwmd_directory_get_operator_automation_status();

    if (
        $now !== (string) $status['last_attempt_at']
        || $message !== (string) $status['last_result']
        || '' !== (string) $status['last_error']
    ) {
        return new WP_Error(
            'nwmd_operator_automation_test_status_failed',
            __(
                'The heartbeat self-test could not verify its stored health result.',
                'local-directory-framework'
            )
        );
    }

    return [
        'attempt_at' => $now,
        'message'    => $message,
    ];
}

/**
 * Store one short-lived automation preflight notice.
 *
 * @param array $notice Notice data.
 */
function nwmd_directory_store_operator_automation_preflight_notice(
    $notice
) {

    set_transient(
        'nwmd_operator_automation_preflight_'
            . get_current_user_id(),
        is_array($notice) ? $notice : [],
        MINUTE_IN_SECONDS
    );
}

/**
 * Return and remove the current user's automation preflight notice.
 *
 * @return array
 */
function nwmd_directory_get_operator_automation_preflight_notice() {

    $key = 'nwmd_operator_automation_preflight_'
        . get_current_user_id();

    $notice = get_transient($key);

    delete_transient($key);

    return is_array($notice) ? $notice : [];
}

/**
 * Run one deterministic no-cost automation preflight.
 *
 * This test reads the real storage, queue, OpenAI configuration, and
 * budget safeguards. It does not call OpenAI, reserve budget, claim a
 * queue item, create a run, or change directory data.
 *
 * @return array|WP_Error
 */
function nwmd_directory_run_operator_automation_preflight_test() {

    $required_functions = [
        'nwmd_directory_get_operator_storage_status',
        'nwmd_directory_get_openai_configuration_status',
        'nwmd_directory_get_operator_budget_settings',
        'nwmd_directory_get_operator_usage_summary',
        'nwmd_directory_can_reserve_operator_usage',
        'nwmd_directory_get_operator_checkpoint_counts',
        'nwmd_directory_get_operator_active_checkpoint_count',
        'nwmd_directory_validate_single_active_checkpoint',
        'nwmd_directory_get_current_operator_checkpoint_context',
    ];

    foreach ($required_functions as $required_function) {
        if (!function_exists($required_function)) {
            return new WP_Error(
                'nwmd_operator_automation_preflight_unavailable',
                __(
                    'One or more required automation preflight functions are unavailable.',
                    'local-directory-framework'
                )
            );
        }
    }

    $budget =
        nwmd_directory_get_operator_budget_settings();

    if (empty($budget['test_mode'])) {
        return new WP_Error(
            'nwmd_operator_automation_preflight_test_mode_required',
            __(
                'Enable supervised test mode before running the automation preflight.',
                'local-directory-framework'
            )
        );
    }

    $scheduled = wp_next_scheduled(
        nwmd_directory_get_operator_automation_hook()
    );

    if (false !== $scheduled) {
        return new WP_Error(
            'nwmd_operator_automation_preflight_schedule_present',
            __(
                'A heartbeat is unexpectedly scheduled while supervised test mode is enabled.',
                'local-directory-framework'
            )
        );
    }

    $storage_before =
        nwmd_directory_get_operator_storage_status();

    if (empty($storage_before['ready'])) {
        return new WP_Error(
            'nwmd_operator_automation_preflight_storage_incomplete',
            __(
                'Operator storage is incomplete.',
                'local-directory-framework'
            )
        );
    }

    $configuration =
        nwmd_directory_get_openai_configuration_status();

    if (empty($configuration['configured'])) {
        return new WP_Error(
            'nwmd_operator_automation_preflight_openai_missing',
            __(
                'OpenAI is not configured.',
                'local-directory-framework'
            )
        );
    }

    if (
        'gpt-5.6-terra'
        !== (string) ($configuration['model'] ?? '')
    ) {
        return new WP_Error(
            'nwmd_operator_automation_preflight_model_unsupported',
            __(
                'The controlled operator currently requires gpt-5.6-terra.',
                'local-directory-framework'
            )
        );
    }

    $checkpoint_counts_before =
        nwmd_directory_get_operator_checkpoint_counts();

    $active_count_before =
        nwmd_directory_get_operator_active_checkpoint_count();

    $valid =
        nwmd_directory_validate_single_active_checkpoint();

    if (is_wp_error($valid)) {
        return $valid;
    }

    if (1 === $active_count_before) {
        $current =
            nwmd_directory_get_current_operator_checkpoint_context();

        if (absint($current['run_id'] ?? 0) < 1) {
            return new WP_Error(
                'nwmd_operator_automation_preflight_run_missing',
                __(
                    'The active checkpoint does not have a valid started run.',
                    'local-directory-framework'
                )
            );
        }
    }

    $usage_before =
        nwmd_directory_get_operator_usage_summary();

    $planned_cost_micros = 250000;

    $eligibility =
        nwmd_directory_can_reserve_operator_usage(
            $planned_cost_micros
        );

    $storage_after =
        nwmd_directory_get_operator_storage_status();

    $checkpoint_counts_after =
        nwmd_directory_get_operator_checkpoint_counts();

    $active_count_after =
        nwmd_directory_get_operator_active_checkpoint_count();

    $usage_after =
        nwmd_directory_get_operator_usage_summary();

    if (
        wp_json_encode($storage_before['counts'] ?? [])
        !== wp_json_encode($storage_after['counts'] ?? [])
        || wp_json_encode($checkpoint_counts_before)
        !== wp_json_encode($checkpoint_counts_after)
        || $active_count_before !== $active_count_after
        || wp_json_encode($usage_before)
        !== wp_json_encode($usage_after)
    ) {
        return new WP_Error(
            'nwmd_operator_automation_preflight_changed_data',
            __(
                'The no-cost automation preflight unexpectedly changed operator data.',
                'local-directory-framework'
            )
        );
    }

    if (is_wp_error($eligibility)) {
        $eligibility_message = sprintf(
            /* translators: %s: Current paid-run safeguard result. */
            __(
                'Blocked by safeguards: %s',
                'local-directory-framework'
            ),
            sanitize_text_field(
                $eligibility->get_error_message()
            )
        );
    } else {
        $eligibility_message = __(
            'Ready under the current hard limits',
            'local-directory-framework'
        );
    }

    $message = sprintf(
        /* translators: %s: Current paid-run eligibility. */
        __(
            'No-cost automation preflight completed. No OpenAI call, budget reservation, queue claim, or directory record was created. Paid-run eligibility: %s.',
            'local-directory-framework'
        ),
        $eligibility_message
    );

    return [
        'message'          => $message,
        'eligibility'      => is_wp_error($eligibility)
            ? 'blocked'
            : 'ready',
        'pending_count'    => absint(
            $checkpoint_counts_after['pending'] ?? 0
        ),
        'active_count'     => $active_count_after,
        'usage_records'    => absint(
            $usage_after['request_count'] ?? 0
        ),
        'guarded_cost'     => absint(
            $usage_after['guarded_cost_micros'] ?? 0
        ),
    ];
}

/**
 * Handle the secured no-cost automation preflight.
 */
function nwmd_directory_handle_operator_automation_preflight() {

    if (!current_user_can('manage_options')) {
        wp_die(
            esc_html__(
                'You do not have permission to perform this action.',
                'local-directory-framework'
            )
        );
    }

    check_admin_referer(
        'nwmd_directory_operator_automation_preflight'
    );

    $result =
        nwmd_directory_run_operator_automation_preflight_test();

    if (is_wp_error($result)) {
        nwmd_directory_store_operator_automation_preflight_notice(
            [
                'success' => false,
                'message' => $result->get_error_message(),
            ]
        );
    } else {
        nwmd_directory_store_operator_automation_preflight_notice(
            [
                'success' => true,
                'message' => (string) $result['message'],
            ]
        );
    }

    $redirect_url = add_query_arg(
        [
            'post_type'            => 'nwmd_business',
            'page'                 => 'nwmd-data-operator',
            'automation_preflight' => '1',
        ],
        admin_url('edit.php')
    );

    wp_safe_redirect($redirect_url);
    exit;
}

add_action(
    'admin_post_nwmd_directory_operator_automation_preflight',
    'nwmd_directory_handle_operator_automation_preflight'
);

/**
 * Handle the secured no-cost heartbeat self-test.
 */
function nwmd_directory_handle_operator_automation_self_test() {

    if (!current_user_can('manage_options')) {
        wp_die(
            esc_html__(
                'You do not have permission to perform this action.',
                'local-directory-framework'
            )
        );
    }

    check_admin_referer(
        'nwmd_directory_operator_automation_self_test'
    );

    $result =
        nwmd_directory_run_operator_automation_self_test();

    if (is_wp_error($result)) {
        nwmd_directory_store_operator_automation_test_notice(
            [
                'success' => false,
                'message' => $result->get_error_message(),
            ]
        );
    } else {
        nwmd_directory_store_operator_automation_test_notice(
            [
                'success' => true,
                'message' => (string) $result['message'],
            ]
        );
    }

    $redirect_url = add_query_arg(
        [
            'post_type'            => 'nwmd_business',
            'page'                 => 'nwmd-data-operator',
            'automation_self_test' => '1',
        ],
        admin_url('edit.php')
    );

    wp_safe_redirect($redirect_url);
    exit;
}

add_action(
    'admin_post_nwmd_directory_operator_automation_self_test',
    'nwmd_directory_handle_operator_automation_self_test'
);

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
 * Version 0.1.92 adds controlled weekly automated research with guarded
 * budget enforcement and supervised review.
 */
function nwmd_directory_render_operator_automation_section() {

    $settings =
        nwmd_directory_get_operator_automation_settings();

    $status =
        nwmd_directory_get_operator_automation_status();

    $budget =
        nwmd_directory_get_operator_budget_settings();

    $next_timestamp = wp_next_scheduled(
        nwmd_directory_get_operator_automation_hook()
    );

    $test_notice = [];

    if (
        isset($_GET['automation_self_test'])
        && '1' === sanitize_text_field(
            wp_unslash($_GET['automation_self_test'])
        )
    ) {
        $test_notice =
            nwmd_directory_get_operator_automation_test_notice();
    }

    $preflight_notice = [];

    if (
        isset($_GET['automation_preflight'])
        && '1' === sanitize_text_field(
            wp_unslash($_GET['automation_preflight'])
        )
    ) {
        $preflight_notice =
            nwmd_directory_get_operator_automation_preflight_notice();
    }
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

    <?php if (!empty($test_notice)) : ?>
        <div class="notice <?php
            echo !empty($test_notice['success'])
                ? 'notice-success'
                : 'notice-error';
        ?> inline">
            <p>
                <?php
                echo esc_html(
                    (string) (
                        $test_notice['message']
                        ?? __(
                            'The heartbeat self-test did not return a result.',
                            'local-directory-framework'
                        )
                    )
                );
                ?>
            </p>
        </div>
    <?php endif; ?>

    <?php if (!empty($budget['test_mode'])) : ?>
        <h3>
            <?php
            echo esc_html__(
                'Heartbeat self-test',
                'local-directory-framework'
            );
            ?>
        </h3>

        <p>
            <?php
            echo esc_html__(
                'This local test verifies the automation health recorder while confirming that no heartbeat is scheduled in supervised test mode.',
                'local-directory-framework'
            );
            ?>
        </p>

        <form
            method="post"
            action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
        >
            <input
                type="hidden"
                name="action"
                value="nwmd_directory_operator_automation_self_test"
            >

            <?php
            wp_nonce_field(
                'nwmd_directory_operator_automation_self_test'
            );

            submit_button(
                __(
                    'Run No-Cost Heartbeat Self-Test',
                    'local-directory-framework'
                ),
                'secondary',
                'submit',
                false
            );
            ?>
        </form>
    <?php endif; ?>

    <?php if (!empty($budget['test_mode'])) : ?>
        <h3>
            <?php
            echo esc_html__(
                'Automation preflight self-test',
                'local-directory-framework'
            );
            ?>
        </h3>

        <?php if (!empty($preflight_notice)) : ?>
            <div class="notice <?php
                echo !empty($preflight_notice['success'])
                    ? 'notice-success'
                    : 'notice-error';
            ?> inline">
                <p>
                    <?php
                    echo esc_html(
                        (string) (
                            $preflight_notice['message']
                            ?? __(
                                'The automation preflight did not return a result.',
                                'local-directory-framework'
                            )
                        )
                    );
                    ?>
                </p>
            </div>
        <?php endif; ?>

        <p>
            <?php
            echo esc_html__(
                'This local test checks storage, queue integrity, OpenAI configuration, and the real budget gate without calling OpenAI, reserving budget, or changing operator data.',
                'local-directory-framework'
            );
            ?>
        </p>

        <form
            method="post"
            action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
        >
            <input
                type="hidden"
                name="action"
                value="nwmd_directory_operator_automation_preflight"
            >

            <?php
            wp_nonce_field(
                'nwmd_directory_operator_automation_preflight'
            );

            submit_button(
                __(
                    'Run No-Cost Automation Preflight',
                    'local-directory-framework'
                ),
                'secondary',
                'submit',
                false
            );
            ?>
        </form>
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
                        'Installed: guarded weekly research',
                        'local-directory-framework'
                    );
                    ?>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <?php
                    echo esc_html__(
                        'Next scheduled heartbeat',
                        'local-directory-framework'
                    );
                    ?>
                </th>
                <td>
                    <?php
                    echo esc_html(
                        $next_timestamp
                            ? wp_date(
                                'M j, Y g:i a',
                                $next_timestamp,
                                wp_timezone()
                            )
                            : __(
                                'Not scheduled',
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
                                'Run one guarded research preview each week.',
                                'local-directory-framework'
                            );
                            ?>
                        </label>

                        <p class="description">
                            <?php
                            echo esc_html__(
                                'Automation runs only when Test Mode is disabled. Every result requires supervised review.',
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
