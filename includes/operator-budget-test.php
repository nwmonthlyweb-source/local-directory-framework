<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Store one short-lived safeguard self-test notice.
 *
 * @param array $notice Notice payload.
 */
function nwmd_directory_store_operator_budget_test_notice($notice) {

    set_transient(
        'nwmd_operator_budget_test_' . get_current_user_id(),
        is_array($notice) ? $notice : [],
        MINUTE_IN_SECONDS
    );
}

/**
 * Return and delete the current user's safeguard self-test notice.
 *
 * @return array
 */
function nwmd_directory_get_operator_budget_test_notice() {

    $key = 'nwmd_operator_budget_test_'
        . get_current_user_id();

    $notice = get_transient($key);

    delete_transient($key);

    return is_array($notice) ? $notice : [];
}

/**
 * Run a local no-cost safeguard self-test.
 *
 * The test deliberately checks a planned amount one micro-dollar above
 * the configured per-run hard limit. It must be rejected before any
 * usage reservation or OpenAI request can occur.
 *
 * @return array|WP_Error
 */
function nwmd_directory_run_operator_budget_self_test() {

    if (
        !function_exists(
            'nwmd_directory_can_reserve_operator_usage'
        )
        || !function_exists(
            'nwmd_directory_get_operator_budget_settings'
        )
        || !function_exists(
            'nwmd_directory_get_operator_usage_summary'
        )
        || !function_exists(
            'nwmd_directory_operator_cents_to_micros'
        )
    ) {
        return new WP_Error(
            'nwmd_operator_budget_test_unavailable',
            __(
                'The research safeguard functions are unavailable.',
                'local-directory-framework'
            )
        );
    }

    $tables = nwmd_directory_get_operator_table_names();
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

    $settings = nwmd_directory_get_operator_budget_settings();
    $before = nwmd_directory_get_operator_usage_summary();

    $per_run_limit_micros =
        nwmd_directory_operator_cents_to_micros(
            $settings['per_run_budget_cents']
        );

    $blocked_amount_micros = $per_run_limit_micros + 1;

    $result = nwmd_directory_can_reserve_operator_usage(
        $blocked_amount_micros
    );

    if (!is_wp_error($result)) {
        return new WP_Error(
            'nwmd_operator_budget_test_failed_open',
            __(
                'The per-run hard limit did not reject the test amount.',
                'local-directory-framework'
            )
        );
    }

    if (
        'nwmd_operator_run_budget_exceeded'
        !== $result->get_error_code()
    ) {
        return new WP_Error(
            'nwmd_operator_budget_test_wrong_result',
            sprintf(
                /* translators: %s: Unexpected safeguard error code. */
                __(
                    'The safeguard returned an unexpected result: %s',
                    'local-directory-framework'
                ),
                sanitize_text_field(
                    (string) $result->get_error_code()
                )
            )
        );
    }

    $after = nwmd_directory_get_operator_usage_summary();

    if (
        absint($before['request_count'] ?? 0)
        !== absint($after['request_count'] ?? 0)
    ) {
        return new WP_Error(
            'nwmd_operator_budget_test_wrote_usage',
            __(
                'The no-cost test unexpectedly changed the usage ledger.',
                'local-directory-framework'
            )
        );
    }

    return [
        'per_run_limit_micros' => $per_run_limit_micros,
        'blocked_amount_micros' => $blocked_amount_micros,
        'usage_records' => absint(
            $after['request_count'] ?? 0
        ),
    ];
}

/**
 * Handle the secure no-cost safeguard self-test.
 */
function nwmd_directory_handle_operator_budget_self_test() {

    if (!current_user_can('manage_options')) {
        wp_die(
            esc_html__(
                'You do not have permission to perform this action.',
                'local-directory-framework'
            )
        );
    }

    check_admin_referer(
        'nwmd_directory_operator_budget_self_test'
    );

    $result = nwmd_directory_run_operator_budget_self_test();

    if (is_wp_error($result)) {
        nwmd_directory_store_operator_budget_test_notice(
            [
                'success' => false,
                'message' => $result->get_error_message(),
            ]
        );
    } else {
        nwmd_directory_store_operator_budget_test_notice(
            [
                'success' => true,
                'blocked_amount_micros' => absint(
                    $result['blocked_amount_micros'] ?? 0
                ),
                'usage_records' => absint(
                    $result['usage_records'] ?? 0
                ),
            ]
        );
    }

    $redirect_url = add_query_arg(
        [
            'post_type' => 'nwmd_business',
            'page' => 'nwmd-data-operator',
            'budget_test' => '1',
        ],
        admin_url('edit.php')
    );

    wp_safe_redirect($redirect_url);
    exit;
}

add_action(
    'admin_post_nwmd_directory_operator_budget_self_test',
    'nwmd_directory_handle_operator_budget_self_test'
);

/**
 * Render the no-cost safeguard self-test control.
 */
function nwmd_directory_render_operator_budget_test_section() {

    $notice = [];

    if (
        isset($_GET['budget_test'])
        && '1' === sanitize_text_field(
            wp_unslash($_GET['budget_test'])
        )
    ) {
        $notice =
            nwmd_directory_get_operator_budget_test_notice();
    }

    ?>
    <h3>
        <?php
        echo esc_html__(
            'Safeguard self-test',
            'local-directory-framework'
        );
        ?>
    </h3>

    <?php if (!empty($notice)) : ?>
        <div class="notice <?php
            echo !empty($notice['success'])
                ? 'notice-success'
                : 'notice-error';
        ?> inline">
            <p>
                <?php if (!empty($notice['success'])) : ?>
                    <?php
                    echo esc_html(
                        sprintf(
                            /* translators: 1: blocked amount, 2: usage count. */
                            __(
                                'Self-test passed. A planned request of $%1$s was blocked before any OpenAI call. Usage ledger records remain %2$d.',
                                'local-directory-framework'
                            ),
                            number_format(
                                absint(
                                    $notice[
                                        'blocked_amount_micros'
                                    ] ?? 0
                                ) / 1000000,
                                6
                            ),
                            absint(
                                $notice['usage_records'] ?? 0
                            )
                        )
                    );
                    ?>
                <?php else : ?>
                    <?php
                    echo esc_html(
                        (string) (
                            $notice['message']
                            ?? __(
                                'The safeguard self-test failed.',
                                'local-directory-framework'
                            )
                        )
                    );
                    ?>
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>

    <p>
        <?php
        echo esc_html__(
            'This local test verifies that the per-run hard limit blocks an over-budget request. It does not contact OpenAI, reserve budget, or change directory data.',
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
            value="nwmd_directory_operator_budget_self_test"
        >
        <?php
        wp_nonce_field(
            'nwmd_directory_operator_budget_self_test'
        );

        submit_button(
            __(
                'Run No-Cost Safeguard Test',
                'local-directory-framework'
            ),
            'secondary',
            'submit',
            false
        );
        ?>
    </form>
    <?php
}