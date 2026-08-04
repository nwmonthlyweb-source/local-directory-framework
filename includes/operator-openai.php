<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return the configured OpenAI API key without storing it in WordPress.
 *
 * @return string
 */
function nwmd_directory_get_openai_api_key() {

    $api_key = '';

    if (defined('NWMD_OPENAI_API_KEY')) {
        $api_key = (string) constant('NWMD_OPENAI_API_KEY');
    }

    if ('' === trim($api_key)) {
        $environment_key = getenv('OPENAI_API_KEY');

        if (false !== $environment_key) {
            $api_key = (string) $environment_key;
        }
    }

    return trim($api_key);
}

/**
 * Return the configured OpenAI model ID.
 *
 * @return string
 */
function nwmd_directory_get_openai_model() {

    $default_model = 'gpt-5.6-terra';
    $model         = '';

    if (defined('NWMD_OPENAI_MODEL')) {
        $model = (string) constant('NWMD_OPENAI_MODEL');
    }

    if ('' === trim($model)) {
        $environment_model = getenv('OPENAI_MODEL');

        if (false !== $environment_model) {
            $model = (string) $environment_model;
        }
    }

    $model = trim($model);

    if (
        '' === $model
        || 1 !== preg_match('/^[A-Za-z0-9._:-]+$/', $model)
    ) {
        return $default_model;
    }

    return $model;
}

/**
 * Return safe OpenAI configuration details for the admin page.
 *
 * The API key itself is never returned.
 *
 * @return array
 */
function nwmd_directory_get_openai_configuration_status() {

    $api_key = nwmd_directory_get_openai_api_key();
    $source  = 'none';

    if ('' !== $api_key) {
        $source = defined('NWMD_OPENAI_API_KEY')
            ? 'wp-config'
            : 'environment';
    }

    return [
        'configured' => '' !== $api_key,
        'source'     => $source,
        'model'      => nwmd_directory_get_openai_model(),
    ];
}

/**
 * Return a readable credential-source label.
 *
 * @param string $source Credential source key.
 *
 * @return string
 */
function nwmd_directory_get_openai_source_label($source) {

    if ('wp-config' === $source) {
        return __(
            'NWMD_OPENAI_API_KEY in wp-config.php',
            'local-directory-framework'
        );
    }

    if ('environment' === $source) {
        return __(
            'OPENAI_API_KEY server environment variable',
            'local-directory-framework'
        );
    }

    return __('Not configured', 'local-directory-framework');
}

/**
 * Test OpenAI authentication and model availability.
 *
 * This request retrieves model metadata only. It does not start research,
 * create content, or modify directory data.
 *
 * @return array|WP_Error
 */
function nwmd_directory_test_openai_connection() {

    $api_key = nwmd_directory_get_openai_api_key();
    $model   = nwmd_directory_get_openai_model();

    if ('' === $api_key) {
        return new WP_Error(
            'nwmd_openai_key_missing',
            __(
                'OpenAI is not configured. Add NWMD_OPENAI_API_KEY to wp-config.php or OPENAI_API_KEY to the server environment.',
                'local-directory-framework'
            )
        );
    }

    $endpoint = 'https://api.openai.com/v1/models/'
        . rawurlencode($model);

    $response = wp_remote_get(
        $endpoint,
        [
            'headers' => [
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'timeout'     => 20,
            'redirection' => 0,
            'sslverify'   => true,
            'user-agent'  => 'NW-Monthly-Data-Operator/'
                . NWMD_DIRECTORY_VERSION,
        ]
    );

    if (is_wp_error($response)) {
        return new WP_Error(
            'nwmd_openai_request_failed',
            sprintf(
                /* translators: %s: WordPress HTTP error message. */
                __(
                    'OpenAI could not be reached: %s',
                    'local-directory-framework'
                ),
                $response->get_error_message()
            )
        );
    }

    $status_code = absint(
        wp_remote_retrieve_response_code($response)
    );
    $body        = wp_remote_retrieve_body($response);
    $decoded     = json_decode($body, true);

    if (200 !== $status_code) {
        $message = '';

        if (
            is_array($decoded)
            && isset($decoded['error']['message'])
            && is_string($decoded['error']['message'])
        ) {
            $message = sanitize_text_field(
                $decoded['error']['message']
            );
        }

        if ('' === $message) {
            $message = sprintf(
                /* translators: %d: HTTP status code. */
                __(
                    'OpenAI returned HTTP status %d.',
                    'local-directory-framework'
                ),
                $status_code
            );
        }

        return new WP_Error(
            'nwmd_openai_connection_rejected',
            $message
        );
    }

    $returned_model = '';

    if (
        is_array($decoded)
        && isset($decoded['id'])
        && is_string($decoded['id'])
    ) {
        $returned_model = sanitize_text_field($decoded['id']);
    }

    if ('' === $returned_model) {
        return new WP_Error(
            'nwmd_openai_invalid_response',
            __(
                'OpenAI returned an unexpected model response.',
                'local-directory-framework'
            )
        );
    }

    return [
        'model'       => $returned_model,
        'status_code' => $status_code,
    ];
}

/**
 * Store one short-lived connection-test notice.
 *
 * @param array $notice Notice payload.
 */
function nwmd_directory_store_openai_test_notice($notice) {

    set_transient(
        'nwmd_openai_test_' . get_current_user_id(),
        $notice,
        MINUTE_IN_SECONDS
    );
}

/**
 * Return and delete the current user's connection-test notice.
 *
 * @return array
 */
function nwmd_directory_get_openai_test_notice() {

    $key    = 'nwmd_openai_test_' . get_current_user_id();
    $notice = get_transient($key);

    delete_transient($key);

    return is_array($notice) ? $notice : [];
}

/**
 * Handle the secure OpenAI connection test.
 */
function nwmd_directory_handle_openai_connection_test() {

    if (!current_user_can('manage_options')) {
        wp_die(
            esc_html__(
                'You do not have permission to perform this action.',
                'local-directory-framework'
            )
        );
    }

    check_admin_referer(
        'nwmd_directory_test_openai_connection'
    );

    $result = nwmd_directory_test_openai_connection();

    if (is_wp_error($result)) {
        nwmd_directory_store_openai_test_notice(
            [
                'success' => false,
                'message' => $result->get_error_message(),
            ]
        );
    } else {
        nwmd_directory_store_openai_test_notice(
            [
                'success' => true,
                'model'   => sanitize_text_field(
                    (string) ($result['model'] ?? '')
                ),
            ]
        );
    }

    $redirect_url = add_query_arg(
        [
            'post_type'   => 'nwmd_business',
            'page'        => 'nwmd-data-operator',
            'openai_test' => '1',
        ],
        admin_url('edit.php')
    );

    wp_safe_redirect($redirect_url);
    exit;
}

add_action(
    'admin_post_nwmd_directory_test_openai_connection',
    'nwmd_directory_handle_openai_connection_test'
);

/**
 * Render the OpenAI connection section on the Data Operator page.
 */
function nwmd_directory_render_openai_operator_section() {

    $status = nwmd_directory_get_openai_configuration_status();
    $notice = [];

    if (
        isset($_GET['openai_test'])
        && '1' === sanitize_text_field(
            wp_unslash($_GET['openai_test'])
        )
    ) {
        $notice = nwmd_directory_get_openai_test_notice();
    }

    ?>
    <h2>
        <?php
        echo esc_html__(
            'OpenAI connection',
            'local-directory-framework'
        );
        ?>
    </h2>

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
                            /* translators: %s: Verified OpenAI model ID. */
                            __(
                                'Connection verified. Model available: %s',
                                'local-directory-framework'
                            ),
                            (string) ($notice['model'] ?? '')
                        )
                    );
                    ?>
                <?php else : ?>
                    <?php
                    echo esc_html(
                        (string) (
                            $notice['message']
                            ?? __(
                                'The OpenAI connection test failed.',
                                'local-directory-framework'
                            )
                        )
                    );
                    ?>
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>

    <table class="widefat striped" style="max-width: 760px;">
        <tbody>
            <tr>
                <th scope="row" style="width: 220px;">
                    <?php
                    echo esc_html__(
                        'Configuration',
                        'local-directory-framework'
                    );
                    ?>
                </th>
                <td>
                    <?php
                    echo esc_html(
                        !empty($status['configured'])
                            ? __(
                                'Configured',
                                'local-directory-framework'
                            )
                            : __(
                                'Not configured',
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
                        'Credential source',
                        'local-directory-framework'
                    );
                    ?>
                </th>
                <td>
                    <?php
                    echo esc_html(
                        nwmd_directory_get_openai_source_label(
                            (string) ($status['source'] ?? 'none')
                        )
                    );
                    ?>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <?php
                    echo esc_html__(
                        'Model',
                        'local-directory-framework'
                    );
                    ?>
                </th>
                <td>
                    <code><?php
                        echo esc_html(
                            (string) ($status['model'] ?? '')
                        );
                    ?></code>
                </td>
            </tr>
        </tbody>
    </table>

    <p>
        <?php
        echo esc_html__(
            'The API key is read only from wp-config.php or the server environment. It is never saved in WordPress or displayed here.',
            'local-directory-framework'
        );
        ?>
    </p>

    <?php if (empty($status['configured'])) : ?>
        <p>
            <?php
            echo esc_html__(
                'Configure the key first, then return here to test the connection.',
                'local-directory-framework'
            );
            ?>
        </p>
    <?php else : ?>
        <form
            method="post"
            action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
        >
            <input
                type="hidden"
                name="action"
                value="nwmd_directory_test_openai_connection"
            >
            <?php
            wp_nonce_field(
                'nwmd_directory_test_openai_connection'
            );
            submit_button(
                __(
                    'Test OpenAI Connection',
                    'local-directory-framework'
                ),
                'secondary',
                'submit',
                false
            );
            ?>
        </form>
    <?php endif; ?>
    <?php
}
