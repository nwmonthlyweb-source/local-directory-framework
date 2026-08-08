<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return the controlled-import plan lifetime.
 *
 * @return int
 */
function nwmd_directory_get_ai_state_import_plan_ttl() {

    return 30 * MINUTE_IN_SECONDS;
}

/**
 * Return the current administrator's plan transient key.
 *
 * @return string
 */
function nwmd_directory_get_ai_state_import_plan_key() {

    return 'nwmd_ai_state_import_plan_'
        . get_current_user_id();
}

/**
 * Return the current administrator's result transient key.
 *
 * @return string
 */
function nwmd_directory_get_ai_state_import_result_key() {

    return 'nwmd_ai_state_import_result_'
        . get_current_user_id();
}

/**
 * Return the one-time-use marker for a prepared plan.
 *
 * @param string $plan_id Prepared plan UUID.
 *
 * @return string
 */
function nwmd_directory_get_ai_state_import_consumed_key(
    $plan_id
) {

    return 'nwmd_ai_state_import_used_'
        . get_current_user_id()
        . '_'
        . substr(hash('sha256', (string) $plan_id), 0, 24);
}

/**
 * Return an authenticated integrity value for a plan.
 *
 * @param array $plan Prepared plan.
 *
 * @return string
 */
function nwmd_directory_get_ai_state_import_integrity(
    array $plan
) {

    unset($plan['integrity']);

    $encoded = wp_json_encode(
        $plan,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );

    if (!is_string($encoded)) {
        return '';
    }

    return hash_hmac(
        'sha256',
        $encoded,
        wp_salt('auth')
    );
}

/**
 * Store one authenticated, per-user import plan.
 *
 * @param array $plan Prepared plan.
 *
 * @return bool
 */
function nwmd_directory_store_ai_state_import_plan(
    array $plan
) {

    $plan['integrity'] =
        nwmd_directory_get_ai_state_import_integrity($plan);

    if ('' === $plan['integrity']) {
        return false;
    }

    return set_transient(
        nwmd_directory_get_ai_state_import_plan_key(),
        $plan,
        nwmd_directory_get_ai_state_import_plan_ttl()
    );
}

/**
 * Return the current authenticated plan or an empty array.
 *
 * @return array
 */
function nwmd_directory_get_ai_state_import_plan() {

    $key  = nwmd_directory_get_ai_state_import_plan_key();
    $plan = get_transient($key);

    if (!is_array($plan)) {
        return [];
    }

    $integrity = (string) ($plan['integrity'] ?? '');
    $expected =
        nwmd_directory_get_ai_state_import_integrity($plan);

    if (
        '' === $integrity
        || '' === $expected
        || !hash_equals($expected, $integrity)
        || 2 !== absint($plan['plan_version'] ?? 0)
        || absint($plan['user_id'] ?? 0)
            !== get_current_user_id()
        || absint($plan['expires_at'] ?? 0) < time()
    ) {
        delete_transient($key);

        return [];
    }

    return $plan;
}

/**
 * Remove the current administrator's prepared plan.
 */
function nwmd_directory_delete_ai_state_import_plan() {

    delete_transient(
        nwmd_directory_get_ai_state_import_plan_key()
    );
}

/**
 * Store a short-lived safe operational result.
 *
 * @param array $result Safe result metadata.
 */
function nwmd_directory_store_ai_state_import_result(
    array $result
) {

    set_transient(
        nwmd_directory_get_ai_state_import_result_key(),
        $result,
        nwmd_directory_get_ai_state_import_plan_ttl()
    );
}

/**
 * Read and remove the current administrator's result.
 *
 * @return array
 */
function nwmd_directory_take_ai_state_import_result() {

    $key    = nwmd_directory_get_ai_state_import_result_key();
    $result = get_transient($key);

    delete_transient($key);

    return is_array($result)
        ? $result
        : [];
}

/**
 * Redirect to the Data Operator page.
 *
 * @param string $argument Safe result query argument.
 */
function nwmd_directory_redirect_ai_state_import($argument) {

    $url = add_query_arg(
        [
            'post_type' => 'nwmd_business',
            'page'      => 'nwmd-data-operator',
            sanitize_key($argument) => '1',
        ],
        admin_url('edit.php')
    );

    wp_safe_redirect($url);
    exit;
}

/**
 * Acquire the one global controlled-import execution lock.
 *
 * @return string|WP_Error
 */
function nwmd_directory_acquire_ai_state_import_lock() {

    global $wpdb;

    $lock_name = 'nwmd_ai_import_'
        . substr(hash('sha256', home_url('/')), 0, 32);
    $acquired = $wpdb->get_var(
        $wpdb->prepare(
            'SELECT GET_LOCK(%s, 0)',
            $lock_name
        )
    );

    if ('1' !== (string) $acquired) {
        return new WP_Error(
            'nwmd_ai_state_import_lock_failed',
            __(
                'Another controlled AI State import is already running.',
                'local-directory-framework'
            )
        );
    }

    return $lock_name;
}

/**
 * Release the global controlled-import execution lock.
 *
 * @param string $lock_name Acquired lock name.
 */
function nwmd_directory_release_ai_state_import_lock(
    $lock_name
) {

    global $wpdb;

    if ('' === (string) $lock_name) {
        return;
    }

    $wpdb->get_var(
        $wpdb->prepare(
            'SELECT RELEASE_LOCK(%s)',
            (string) $lock_name
        )
    );
}

/**
 * Log only bounded operational failure identifiers.
 *
 * @param string $code         Safe failure code.
 * @param string $package_hash Package SHA-256.
 * @param string $rollback     Rollback result.
 */
function nwmd_directory_log_ai_state_import_failure(
    $code,
    $package_hash,
    $rollback
) {

    error_log(
        '[Local Directory Framework] Controlled AI State import failed; code='
        . substr(sanitize_key($code), 0, 96)
        . '; package='
        . substr(
            preg_replace(
                '/[^a-f0-9]/',
                '',
                strtolower((string) $package_hash)
            ),
            0,
            12
        )
        . '; rollback='
        . sanitize_key($rollback)
    );
}
