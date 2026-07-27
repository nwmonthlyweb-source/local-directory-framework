<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return the branded name used for business-request emails.
 *
 * @return string
 */
function nwmd_directory_get_business_request_mail_name() {

    $mail_name = apply_filters(
        'nwmd_directory_business_request_mail_name',
        __(
            'Northwest Monthly',
            'local-directory-framework'
        )
    );

    return sanitize_text_field(
        (string) $mail_name
    );
}

/**
 * Filter the sender name while sending a business-request email.
 *
 * @param string $from_name Existing sender name.
 *
 * @return string
 */
function nwmd_directory_filter_business_request_mail_from_name(
    $from_name
) {

    unset($from_name);

    return nwmd_directory_get_business_request_mail_name();
}

/**
 * Send one branded business-request email.
 *
 * @param string $to      Recipient email.
 * @param string $subject Email subject.
 * @param string $message Plain-text message.
 *
 * @return bool
 */
function nwmd_directory_send_branded_business_request_mail(
    $to,
    $subject,
    $message
) {

    add_filter(
        'wp_mail_from_name',
        'nwmd_directory_filter_business_request_mail_from_name',
        99
    );

    $sent = wp_mail(
        $to,
        $subject,
        $message,
        [
            'Content-Type: text/plain; charset=UTF-8',
        ]
    );

    remove_filter(
        'wp_mail_from_name',
        'nwmd_directory_filter_business_request_mail_from_name',
        99
    );

    return $sent;
}

/**
 * Return controlled public business-request types.
 *
 * @return array
 */
function nwmd_directory_get_business_request_type_choices() {

    return [
        'add' => __(
            'Add a Business',
            'local-directory-framework'
        ),
        'update' => __(
            'Claim or Update a Business',
            'local-directory-framework'
        ),
        'correction' => __(
            'Request a Correction',
            'local-directory-framework'
        ),
        'removal' => __(
            'Request Removal',
            'local-directory-framework'
        ),
    ];
}

/**
 * Return controlled business-request statuses.
 *
 * @return array
 */
function nwmd_directory_get_business_request_status_choices() {

    return [
        'pending_email' => __(
            'Pending Email Verification',
            'local-directory-framework'
        ),
        'pending_review' => __(
            'Pending Review',
            'local-directory-framework'
        ),
        'approved' => __(
            'Approved',
            'local-directory-framework'
        ),
        'rejected' => __(
            'Rejected',
            'local-directory-framework'
        ),
        'completed' => __(
            'Completed',
            'local-directory-framework'
        ),
        'archived' => __(
            'Archived',
            'local-directory-framework'
        ),
    ];
}

/**
 * Limit a sanitized request value to a safe character length.
 *
 * @param string $value  Sanitized value.
 * @param int    $length Maximum character length.
 *
 * @return string
 */
function nwmd_directory_limit_business_request_text(
    $value,
    $length
) {

    if (function_exists('mb_substr')) {
        return mb_substr(
            $value,
            0,
            $length
        );
    }

    return substr(
        $value,
        0,
        $length
    );
}

/**
 * Return the public Manage a Business URL.
 *
 * @param array $arguments Optional query arguments.
 *
 * @return string
 */
function nwmd_directory_get_business_request_url(
    $arguments = []
) {

    $url = home_url(
        '/manage-a-business/'
    );

    if (!empty($arguments)) {
        $url = add_query_arg(
            $arguments,
            $url
        );
    }

    return $url;
}

/**
 * Register the public Manage a Business route.
 */
function nwmd_directory_register_business_request_rewrite() {

    add_rewrite_rule(
        '^manage-a-business/?$',
        'index.php?nwmd_business_request=1',
        'top'
    );
}

add_action(
    'init',
    'nwmd_directory_register_business_request_rewrite',
    15
);

/**
 * Register the Manage a Business query variable.
 *
 * @param array $query_vars Public query variables.
 *
 * @return array
 */
function nwmd_directory_register_business_request_query_var(
    $query_vars
) {

    $query_vars[] = 'nwmd_business_request';

    return $query_vars;
}

add_filter(
    'query_vars',
    'nwmd_directory_register_business_request_query_var'
);

/**
 * Return whether the current request is the public request page.
 *
 * @return bool
 */
function nwmd_directory_is_business_request_page() {

    return '1' === (string) get_query_var(
        'nwmd_business_request'
    );
}

/**
 * Use the plugin request template unless the theme overrides it.
 *
 * @param string $template Current template path.
 *
 * @return string
 */
function nwmd_directory_business_request_template_include(
    $template
) {

    if (!nwmd_directory_is_business_request_page()) {
        return $template;
    }

    $theme_template = locate_template(
        ['business-request.php']
    );

    if (!empty($theme_template)) {
        return $theme_template;
    }

    $plugin_template = NWMD_DIRECTORY_PATH
        . 'templates/business-request.php';

    return is_readable($plugin_template)
        ? $plugin_template
        : $template;
}

add_filter(
    'template_include',
    'nwmd_directory_business_request_template_include',
    30
);

/**
 * Send a successful response for the virtual request page.
 */
function nwmd_directory_prepare_business_request_page() {

    if (!nwmd_directory_is_business_request_page()) {
        return;
    }

    global $wp_query;

    if ($wp_query instanceof WP_Query) {
        $wp_query->is_404 = false;
    }

    status_header(200);
    nocache_headers();
}

add_action(
    'template_redirect',
    'nwmd_directory_prepare_business_request_page',
    5
);

/**
 * Set the public request-page document title.
 *
 * @param array $title_parts Document title parts.
 *
 * @return array
 */
function nwmd_directory_business_request_document_title(
    $title_parts
) {

    if (!nwmd_directory_is_business_request_page()) {
        return $title_parts;
    }

    $title_parts['title'] = __(
        'Manage a Business',
        'local-directory-framework'
    );

    return $title_parts;
}

add_filter(
    'document_title_parts',
    'nwmd_directory_business_request_document_title'
);

/**
 * Return one request record.
 *
 * @param int $request_id Request record ID.
 *
 * @return object|null
 */
function nwmd_directory_get_business_request(
    $request_id
) {

    global $wpdb;

    $table = $wpdb->prefix
        . 'nwmd_business_requests';

    $request = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT *
            FROM {$table}
            WHERE id = %d
            LIMIT 1",
            absint($request_id)
        )
    );

    return is_object($request)
        ? $request
        : null;
}

/**
 * Return the published business selected in the public request URL.
 *
 * @return WP_Post|null
 */
function nwmd_directory_get_public_business_request_business() {

    $business_post_id = isset($_GET['business_id'])
        ? absint($_GET['business_id'])
        : 0;

    if ($business_post_id < 1) {
        return null;
    }

    $business = get_post(
        $business_post_id
    );

    if (
        !$business instanceof WP_Post ||
        'nwmd_business' !== $business->post_type ||
        'publish' !== $business->post_status
    ) {
        return null;
    }

    return $business;
}

/**
 * Return the selected public request type.
 *
 * @param string $default Default request type.
 *
 * @return string
 */
function nwmd_directory_get_public_business_request_type(
    $default = 'add'
) {

    $request_type = isset($_GET['request_type'])
        ? sanitize_key(
            wp_unslash($_GET['request_type'])
        )
        : '';

    $choices = nwmd_directory_get_business_request_type_choices();

    if (isset($choices[$request_type])) {
        return $request_type;
    }

    return isset($choices[$default])
        ? $default
        : 'add';
}

/**
 * Return a public HTTP or HTTPS URL, or an empty value.
 *
 * @param mixed $value Raw URL.
 *
 * @return string
 */
function nwmd_directory_sanitize_business_request_url(
    $value
) {

    $value = trim(
        (string) wp_unslash($value)
    );

    if ('' === $value) {
        return '';
    }

    $url = esc_url_raw(
        $value,
        [
            'http',
            'https',
        ]
    );

    if ('' === $url) {
        return '';
    }

    $scheme = strtolower(
        (string) wp_parse_url(
            $url,
            PHP_URL_SCHEME
        )
    );

    if (
        !in_array(
            $scheme,
            [
                'http',
                'https',
            ],
            true
        ) ||
        false === wp_http_validate_url($url)
    ) {
        return '';
    }

    return $url;
}

/**
 * Return salted request-rate transient keys.
 *
 * Raw email and IP values are not stored in transient names.
 *
 * @param string $email Sanitized requester email.
 *
 * @return array
 */
function nwmd_directory_get_business_request_rate_keys(
    $email
) {

    $remote_address = isset($_SERVER['REMOTE_ADDR'])
        ? sanitize_text_field(
            wp_unslash($_SERVER['REMOTE_ADDR'])
        )
        : '';

    $salt = wp_salt('nonce');

    return [
        'email' => 'nwmd_business_request_email_'
            . hash_hmac(
                'sha256',
                strtolower($email),
                $salt
            ),
        'ip' => 'nwmd_business_request_ip_'
            . hash_hmac(
                'sha256',
                $remote_address,
                $salt
            ),
    ];
}

/**
 * Return whether the current visitor has reached a request limit.
 *
 * @param string $email Sanitized requester email.
 *
 * @return bool
 */
function nwmd_directory_business_request_is_rate_limited(
    $email
) {

    $keys = nwmd_directory_get_business_request_rate_keys(
        $email
    );

    $email_count = absint(
        get_transient($keys['email'])
    );

    $ip_count = absint(
        get_transient($keys['ip'])
    );

    return $email_count >= 5 || $ip_count >= 10;
}

/**
 * Increment request-rate counters.
 *
 * @param string $email Sanitized requester email.
 */
function nwmd_directory_increment_business_request_rate(
    $email
) {

    $keys = nwmd_directory_get_business_request_rate_keys(
        $email
    );

    foreach ($keys as $key) {
        $count = absint(
            get_transient($key)
        );

        set_transient(
            $key,
            $count + 1,
            HOUR_IN_SECONDS
        );
    }
}

/**
 * Redirect to the public request page with a notice.
 *
 * @param string $notice    Notice identifier.
 * @param array  $arguments Optional preserved query arguments.
 */
function nwmd_directory_redirect_business_request(
    $notice,
    $arguments = []
) {

    $arguments['nwmd_request_notice'] = sanitize_key(
        $notice
    );

    wp_safe_redirect(
        nwmd_directory_get_business_request_url(
            $arguments
        )
    );

    exit;
}

/**
 * Render one public request notice.
 */
function nwmd_directory_render_business_request_notice() {

    $notice = isset($_GET['nwmd_request_notice'])
        ? sanitize_key(
            wp_unslash($_GET['nwmd_request_notice'])
        )
        : '';

    $messages = [
        'submitted' => [
            'success',
            __(
                'Check your email and use the verification link to submit your request for administrator review.',
                'local-directory-framework'
            ),
        ],
        'verified' => [
            'success',
            __(
                'Your email was verified. Your request is now waiting for administrator review.',
                'local-directory-framework'
            ),
        ],
        'already-verified' => [
            'success',
            __(
                'This request was already verified and is in the review workflow.',
                'local-directory-framework'
            ),
        ],
        'invalid-request' => [
            'error',
            __(
                'Complete every required field and try again.',
                'local-directory-framework'
            ),
        ],
        'invalid-email' => [
            'error',
            __(
                'Enter a valid email address.',
                'local-directory-framework'
            ),
        ],
        'invalid-url' => [
            'error',
            __(
                'Enter a valid public HTTP or HTTPS business website URL.',
                'local-directory-framework'
            ),
        ],
        'rate-limited' => [
            'error',
            __(
                'Too many requests were submitted recently. Wait one hour and try again.',
                'local-directory-framework'
            ),
        ],
        'mail-failed' => [
            'error',
            __(
                'The verification email could not be sent. No request was saved. Try again later.',
                'local-directory-framework'
            ),
        ],
        'save-failed' => [
            'error',
            __(
                'The request could not be saved. Try again later.',
                'local-directory-framework'
            ),
        ],
        'invalid-verification' => [
            'error',
            __(
                'The verification link is invalid.',
                'local-directory-framework'
            ),
        ],
        'expired-verification' => [
            'error',
            __(
                'The verification link expired. Submit a new request.',
                'local-directory-framework'
            ),
        ],
        'verification-failed' => [
            'error',
            __(
                'The request could not be verified. Try again or submit a new request.',
                'local-directory-framework'
            ),
        ],
    ];

    if (!isset($messages[$notice])) {
        return;
    }

    [$type, $message] = $messages[$notice];

    ?>
    <div
        class="<?php echo esc_attr(
            'nwmd-request-notice nwmd-request-notice--' . $type
        ); ?>"
        role="<?php echo esc_attr(
            'error' === $type
                ? 'alert'
                : 'status'
        ); ?>"
    >
        <p><?php echo esc_html($message); ?></p>
    </div>
    <?php
}

/**
 * Send the requester an email-verification link.
 *
 * @param int    $request_id    Request record ID.
 * @param string $token         Raw verification token.
 * @param string $requester     Requester name.
 * @param string $email         Requester email.
 * @param string $request_type  Request type.
 * @param string $business_name Business name.
 *
 * @return bool
 */
function nwmd_directory_send_business_request_verification_email(
    $request_id,
    $token,
    $requester,
    $email,
    $request_type,
    $business_name
) {

    $type_choices = nwmd_directory_get_business_request_type_choices();
    $type_label = $type_choices[$request_type]
        ?? $request_type;

    $verification_url = add_query_arg(
        [
            'action'     => 'nwmd_verify_business_request',
            'request_id' => absint($request_id),
            'token'      => $token,
        ],
        admin_url('admin-post.php')
    );

    $site_name = nwmd_directory_get_business_request_mail_name();

    $subject = sprintf(
        /* translators: %s: Website name. */
        __(
            '[%s] Verify your business request',
            'local-directory-framework'
        ),
        $site_name
    );

    $message = sprintf(
        /* translators: 1: Requester name, 2: Request type, 3: Business name, 4: Verification URL. */
        __(
            "Hello %1\$s,\n\nConfirm your email to submit this %2\$s request for %3\$s:\n\n%4\$s\n\nThis link expires in 7 days. If you did not submit this request, you can ignore this email.",
            'local-directory-framework'
        ),
        $requester,
        $type_label,
        $business_name,
        $verification_url
    );

    return nwmd_directory_send_branded_business_request_mail(
        $email,
        $subject,
        $message
    );
}

/**
 * Notify the administrator after a request is verified.
 *
 * @param object $request Verified request record.
 */
function nwmd_directory_send_business_request_admin_notification(
    $request
) {

    if (!is_object($request)) {
        return;
    }

    $admin_email = sanitize_email(
        apply_filters(
            'nwmd_directory_business_request_admin_email',
            get_option('admin_email')
        )
    );

    if ('' === $admin_email) {
        return;
    }

    $type_choices = nwmd_directory_get_business_request_type_choices();
    $type_label = $type_choices[$request->request_type]
        ?? $request->request_type;

    $admin_url = add_query_arg(
        [
            'post_type'  => 'nwmd_business',
            'page'       => 'nwmd-business-requests',
            'request_id' => absint($request->id),
        ],
        admin_url('edit.php')
    );

    $site_name = nwmd_directory_get_business_request_mail_name();

    $subject = sprintf(
        /* translators: %s: Website name. */
        __(
            '[%s] Verified business request',
            'local-directory-framework'
        ),
        $site_name
    );

    $message = sprintf(
        /* translators: 1: Request ID, 2: Request type, 3: Business name, 4: Requester name, 5: Requester email, 6: Admin URL. */
        __(
            "A business request was verified.\n\nRequest ID: %1\$d\nType: %2\$s\nBusiness: %3\$s\nRequester: %4\$s\nEmail: %5\$s\n\nReview the request:\n%6\$s",
            'local-directory-framework'
        ),
        absint($request->id),
        $type_label,
        $request->business_name,
        $request->requester_name,
        $request->requester_email,
        $admin_url
    );

    nwmd_directory_send_branded_business_request_mail(
        $admin_email,
        $subject,
        $message
    );
}

/**
 * Handle one public business-request submission.
 */
function nwmd_directory_handle_business_request_submission() {

    check_admin_referer(
        'nwmd_submit_business_request',
        'nwmd_business_request_nonce'
    );

    $preserved_arguments = [];

    $request_type = isset($_POST['request_type'])
        ? sanitize_key(
            wp_unslash($_POST['request_type'])
        )
        : '';

    $type_choices = nwmd_directory_get_business_request_type_choices();

    if (isset($type_choices[$request_type])) {
        $preserved_arguments['request_type'] = $request_type;
    }

    $business_post_id = isset($_POST['business_post_id'])
        ? absint($_POST['business_post_id'])
        : 0;

    $business = null;

    if ($business_post_id > 0) {
        $candidate = get_post(
            $business_post_id
        );

        if (
            $candidate instanceof WP_Post &&
            'nwmd_business' === $candidate->post_type &&
            'publish' === $candidate->post_status
        ) {
            $business = $candidate;
            $preserved_arguments['business_id']
                = $business_post_id;
        } else {
            nwmd_directory_redirect_business_request(
                'invalid-request',
                $preserved_arguments
            );
        }
    }

    $honeypot = isset($_POST['company_fax'])
        ? trim(
            (string) wp_unslash($_POST['company_fax'])
        )
        : '';

    if ('' !== $honeypot) {
        nwmd_directory_redirect_business_request(
            'submitted'
        );
    }

    $requester_name = isset($_POST['requester_name'])
        ? sanitize_text_field(
            wp_unslash($_POST['requester_name'])
        )
        : '';

    $requester_name = nwmd_directory_limit_business_request_text(
        $requester_name,
        255
    );

    $requester_email = isset($_POST['requester_email'])
        ? sanitize_email(
            wp_unslash($_POST['requester_email'])
        )
        : '';

    $requester_phone = isset($_POST['requester_phone'])
        ? sanitize_text_field(
            wp_unslash($_POST['requester_phone'])
        )
        : '';

    $requester_phone = nwmd_directory_limit_business_request_text(
        $requester_phone,
        100
    );

    $business_name = isset($_POST['business_name'])
        ? sanitize_text_field(
            wp_unslash($_POST['business_name'])
        )
        : '';

    if ($business instanceof WP_Post) {
        $business_name = sanitize_text_field(
            get_the_title($business->ID)
        );
    }

    $business_name = nwmd_directory_limit_business_request_text(
        $business_name,
        255
    );

    $business_website_raw = isset($_POST['business_website'])
        ? wp_unslash($_POST['business_website'])
        : '';

    $business_website = nwmd_directory_sanitize_business_request_url(
        $business_website_raw
    );

    $business_address = isset($_POST['business_address'])
        ? sanitize_text_field(
            wp_unslash($_POST['business_address'])
        )
        : '';

    $business_address = nwmd_directory_limit_business_request_text(
        $business_address,
        255
    );

    $request_details = isset($_POST['request_details'])
        ? sanitize_textarea_field(
            wp_unslash($_POST['request_details'])
        )
        : '';

    $request_details = nwmd_directory_limit_business_request_text(
        $request_details,
        5000
    );

    $authorization_confirmed = !empty(
        $_POST['authorization_confirmed']
    );

    if (!isset($type_choices[$request_type])) {
        nwmd_directory_redirect_business_request(
            'invalid-request',
            $preserved_arguments
        );
    }

    if (
        '' === $requester_name ||
        '' === $business_name ||
        '' === $request_details ||
        !$authorization_confirmed
    ) {
        nwmd_directory_redirect_business_request(
            'invalid-request',
            $preserved_arguments
        );
    }

    if (
        '' === $requester_email ||
        false === is_email($requester_email)
    ) {
        nwmd_directory_redirect_business_request(
            'invalid-email',
            $preserved_arguments
        );
    }

    if (
        '' !== trim((string) $business_website_raw) &&
        '' === $business_website
    ) {
        nwmd_directory_redirect_business_request(
            'invalid-url',
            $preserved_arguments
        );
    }

    if (
        nwmd_directory_business_request_is_rate_limited(
            $requester_email
        )
    ) {
        nwmd_directory_redirect_business_request(
            'rate-limited',
            $preserved_arguments
        );
    }

    if ('add' === $request_type) {
        $business_post_id = 0;
    }

    try {
        $token = bin2hex(
            random_bytes(32)
        );
    } catch (Throwable $throwable) {
        unset($throwable);

        nwmd_directory_redirect_business_request(
            'save-failed',
            $preserved_arguments
        );
    }

    $submitted_data = wp_json_encode(
        [
            'business_website'       => $business_website,
            'business_address'       => $business_address,
            'request_details'        => $request_details,
            'authorization_confirmed' => true,
            'submitted_from'         => $business instanceof WP_Post
                ? get_permalink($business->ID)
                : '',
        ],
        JSON_UNESCAPED_SLASHES |
        JSON_UNESCAPED_UNICODE
    );

    if (false === $submitted_data) {
        nwmd_directory_redirect_business_request(
            'save-failed',
            $preserved_arguments
        );
    }

    global $wpdb;

    $table = $wpdb->prefix
        . 'nwmd_business_requests';

    $current_time = current_time('mysql');

    $inserted = $wpdb->insert(
        $table,
        [
            'request_type'           => $request_type,
            'business_post_id'       => $business_post_id,
            'requester_name'         => $requester_name,
            'requester_email'        => $requester_email,
            'requester_phone'        => $requester_phone,
            'business_name'          => $business_name,
            'submitted_data'         => $submitted_data,
            'verification_token_hash' => hash(
                'sha256',
                $token
            ),
            'email_verified_at'      => null,
            'status'                 => 'pending_email',
            'admin_notes'            => '',
            'reviewed_by'            => 0,
            'reviewed_at'            => null,
            'created_at'             => $current_time,
            'updated_at'             => $current_time,
        ],
        [
            '%s',
            '%d',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%d',
            '%s',
            '%s',
            '%s',
        ]
    );

    if (false === $inserted) {
        nwmd_directory_redirect_business_request(
            'save-failed',
            $preserved_arguments
        );
    }

    $request_id = absint(
        $wpdb->insert_id
    );

    $mail_sent
        = nwmd_directory_send_business_request_verification_email(
            $request_id,
            $token,
            $requester_name,
            $requester_email,
            $request_type,
            $business_name
        );

    if (!$mail_sent) {
        $wpdb->delete(
            $table,
            [
                'id' => $request_id,
            ],
            [
                '%d',
            ]
        );

        nwmd_directory_redirect_business_request(
            'mail-failed',
            $preserved_arguments
        );
    }

    nwmd_directory_increment_business_request_rate(
        $requester_email
    );

    nwmd_directory_redirect_business_request(
        'submitted'
    );
}

add_action(
    'admin_post_nopriv_nwmd_submit_business_request',
    'nwmd_directory_handle_business_request_submission'
);

add_action(
    'admin_post_nwmd_submit_business_request',
    'nwmd_directory_handle_business_request_submission'
);

/**
 * Verify one public business request.
 */
function nwmd_directory_handle_business_request_verification() {

    $request_id = isset($_GET['request_id'])
        ? absint($_GET['request_id'])
        : 0;

    $token = isset($_GET['token'])
        ? sanitize_text_field(
            wp_unslash($_GET['token'])
        )
        : '';

    if (
        $request_id < 1 ||
        1 !== preg_match(
            '/^[a-f0-9]{64}$/D',
            $token
        )
    ) {
        nwmd_directory_redirect_business_request(
            'invalid-verification'
        );
    }

    $request = nwmd_directory_get_business_request(
        $request_id
    );

    if (!$request) {
        nwmd_directory_redirect_business_request(
            'invalid-verification'
        );
    }

    if (
        !empty($request->email_verified_at) &&
        'pending_email' !== $request->status
    ) {
        nwmd_directory_redirect_business_request(
            'already-verified'
        );
    }

    if ('pending_email' !== $request->status) {
        nwmd_directory_redirect_business_request(
            'invalid-verification'
        );
    }

    $created_at = DateTimeImmutable::createFromFormat(
        '!Y-m-d H:i:s',
        (string) $request->created_at,
        wp_timezone()
    );

    if (
        !$created_at instanceof DateTimeImmutable ||
        (
            current_datetime()->getTimestamp() -
            $created_at->getTimestamp()
        ) > 7 * DAY_IN_SECONDS
    ) {
        global $wpdb;

        $table = $wpdb->prefix
            . 'nwmd_business_requests';

        $wpdb->update(
            $table,
            [
                'verification_token_hash' => '',
                'status'                  => 'archived',
                'updated_at'              => current_time('mysql'),
            ],
            [
                'id' => $request_id,
            ],
            [
                '%s',
                '%s',
                '%s',
            ],
            [
                '%d',
            ]
        );

        nwmd_directory_redirect_business_request(
            'expired-verification'
        );
    }

    $provided_hash = hash(
        'sha256',
        $token
    );

    if (
        '' === (string) $request->verification_token_hash ||
        !hash_equals(
            (string) $request->verification_token_hash,
            $provided_hash
        )
    ) {
        nwmd_directory_redirect_business_request(
            'invalid-verification'
        );
    }

    global $wpdb;

    $table = $wpdb->prefix
        . 'nwmd_business_requests';

    $current_time = current_time('mysql');

    $updated = $wpdb->update(
        $table,
        [
            'verification_token_hash' => '',
            'email_verified_at'       => $current_time,
            'status'                  => 'pending_review',
            'updated_at'              => $current_time,
        ],
        [
            'id'     => $request_id,
            'status' => 'pending_email',
        ],
        [
            '%s',
            '%s',
            '%s',
            '%s',
        ],
        [
            '%d',
            '%s',
        ]
    );

    if (false === $updated || 1 !== $updated) {
        nwmd_directory_redirect_business_request(
            'verification-failed'
        );
    }

    $verified_request = nwmd_directory_get_business_request(
        $request_id
    );

    nwmd_directory_send_business_request_admin_notification(
        $verified_request
    );

    nwmd_directory_redirect_business_request(
        'verified'
    );
}

add_action(
    'admin_post_nopriv_nwmd_verify_business_request',
    'nwmd_directory_handle_business_request_verification'
);

add_action(
    'admin_post_nwmd_verify_business_request',
    'nwmd_directory_handle_business_request_verification'
);
