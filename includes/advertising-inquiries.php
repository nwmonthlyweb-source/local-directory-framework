<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Limit one advertising-inquiry text value.
 *
 * @param string $value  Sanitized text.
 * @param int    $length Maximum character count.
 *
 * @return string
 */
function nwmd_directory_limit_advertising_inquiry_text(
    $value,
    $length
) {

    $value = trim((string) $value);
    $length = absint($length);

    if ($length < 1) {
        return '';
    }

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
 * Return salted advertising-inquiry rate-limit keys.
 *
 * Raw email and IP values are not stored in transient names.
 *
 * @param string $email Sanitized contact email.
 *
 * @return array
 */
function nwmd_directory_get_advertising_inquiry_rate_keys(
    $email
) {

    $remote_address = isset($_SERVER['REMOTE_ADDR'])
        ? sanitize_text_field(
            wp_unslash($_SERVER['REMOTE_ADDR'])
        )
        : '';

    $salt = wp_salt('nonce');

    return [
        'email' => 'nwmd_ad_inquiry_email_'
            . hash_hmac(
                'sha256',
                strtolower($email),
                $salt
            ),
        'ip' => 'nwmd_ad_inquiry_ip_'
            . hash_hmac(
                'sha256',
                $remote_address,
                $salt
            ),
    ];
}

/**
 * Return whether an advertising inquiry is rate limited.
 *
 * @param string $email Sanitized contact email.
 *
 * @return bool
 */
function nwmd_directory_advertising_inquiry_is_rate_limited(
    $email
) {

    $keys =
        nwmd_directory_get_advertising_inquiry_rate_keys(
            $email
        );

    $email_count = absint(
        get_transient($keys['email'])
    );

    $ip_count = absint(
        get_transient($keys['ip'])
    );

    return $email_count >= 3 || $ip_count >= 8;
}

/**
 * Increment advertising-inquiry rate counters.
 *
 * @param string $email Sanitized contact email.
 */
function nwmd_directory_increment_advertising_inquiry_rate(
    $email
) {

    $keys =
        nwmd_directory_get_advertising_inquiry_rate_keys(
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
 * Return a safe same-site advertising-inquiry return URL.
 *
 * @param string $raw_url Submitted return URL.
 *
 * @return string
 */
function nwmd_directory_get_advertising_inquiry_return_url(
    $raw_url = ''
) {

    $archive_url = get_post_type_archive_link(
        'nwmd_business'
    );

    $fallback = $archive_url
        ? $archive_url
        : home_url('/');

    $raw_url = trim(
        (string) wp_unslash($raw_url)
    );

    if ('' === $raw_url) {
        return $fallback;
    }

    $candidate = esc_url_raw(
        $raw_url,
        [
            'http',
            'https',
        ]
    );

    if ('' === $candidate) {
        return $fallback;
    }

    $validated = wp_validate_redirect(
        $candidate,
        $fallback
    );

    return remove_query_arg(
        'nwmd_ad_inquiry_notice',
        $validated
    );
}

/**
 * Redirect after an advertising-inquiry submission.
 *
 * @param string $notice     Controlled notice identifier.
 * @param string $return_url Submitted return URL.
 */
function nwmd_directory_redirect_advertising_inquiry(
    $notice,
    $return_url = ''
) {

    $target =
        nwmd_directory_get_advertising_inquiry_return_url(
            $return_url
        );

    $target = add_query_arg(
        'nwmd_ad_inquiry_notice',
        sanitize_key($notice),
        $target
    );

    wp_safe_redirect($target);
    exit;
}

/**
 * Render the current advertising-inquiry status notice.
 */
function nwmd_directory_render_advertising_inquiry_notice() {

    $notice = isset($_GET['nwmd_ad_inquiry_notice'])
        ? sanitize_key(
            wp_unslash($_GET['nwmd_ad_inquiry_notice'])
        )
        : '';

    $messages = [
        'submitted' => [
            'success',
            __(
                'Your advertising inquiry was sent. NW Monthly will contact you using the information provided.',
                'local-directory-framework'
            ),
        ],
        'invalid-request' => [
            'error',
            __(
                'Complete every required advertising inquiry field and try again.',
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
        'rate-limited' => [
            'error',
            __(
                'Too many advertising inquiries were submitted recently. Wait one hour and try again.',
                'local-directory-framework'
            ),
        ],
        'mail-failed' => [
            'error',
            __(
                'The advertising inquiry could not be sent. Try again later.',
                'local-directory-framework'
            ),
        ],
    ];

    if (!isset($messages[$notice])) {
        return;
    }

    [$type, $message] = $messages[$notice];
    ?>
    <section
        class="<?php echo esc_attr(
            'nwmd-ad-inquiry-notice '
            . 'nwmd-ad-inquiry-notice--'
            . sanitize_html_class($type)
        ); ?>"
        role="<?php echo esc_attr(
            'error' === $type
                ? 'alert'
                : 'status'
        ); ?>"
    >
        <?php echo esc_html($message); ?>
    </section>
    <?php
}

/**
 * Handle one public advertising inquiry.
 */
function nwmd_directory_handle_advertising_inquiry() {

    check_admin_referer(
        'nwmd_submit_advertising_inquiry',
        'nwmd_advertising_inquiry_nonce'
    );

    $return_url = isset($_POST['return_url'])
        ? (string) $_POST['return_url']
        : '';

    $honeypot = isset($_POST['company_website'])
        ? sanitize_text_field(
            wp_unslash($_POST['company_website'])
        )
        : '';

    if ('' !== trim($honeypot)) {
        nwmd_directory_redirect_advertising_inquiry(
            'submitted',
            $return_url
        );
    }

    $contact_name = isset($_POST['contact_name'])
        ? sanitize_text_field(
            wp_unslash($_POST['contact_name'])
        )
        : '';

    $contact_name =
        nwmd_directory_limit_advertising_inquiry_text(
            $contact_name,
            120
        );

    $contact_email = isset($_POST['contact_email'])
        ? sanitize_email(
            wp_unslash($_POST['contact_email'])
        )
        : '';

    $contact_phone = isset($_POST['contact_phone'])
        ? sanitize_text_field(
            wp_unslash($_POST['contact_phone'])
        )
        : '';

    $contact_phone =
        nwmd_directory_limit_advertising_inquiry_text(
            $contact_phone,
            50
        );

    $business_name = isset($_POST['business_name'])
        ? sanitize_text_field(
            wp_unslash($_POST['business_name'])
        )
        : '';

    $business_name =
        nwmd_directory_limit_advertising_inquiry_text(
            $business_name,
            160
        );

    $message = isset($_POST['inquiry_message'])
        ? sanitize_textarea_field(
            wp_unslash($_POST['inquiry_message'])
        )
        : '';

    $message =
        nwmd_directory_limit_advertising_inquiry_text(
            $message,
            3000
        );

    if (
        '' === $contact_name ||
        '' === $business_name ||
        '' === $message
    ) {
        nwmd_directory_redirect_advertising_inquiry(
            'invalid-request',
            $return_url
        );
    }

    if (
        '' === $contact_email ||
        false === is_email($contact_email)
    ) {
        nwmd_directory_redirect_advertising_inquiry(
            'invalid-email',
            $return_url
        );
    }

    if (
        nwmd_directory_advertising_inquiry_is_rate_limited(
            $contact_email
        )
    ) {
        nwmd_directory_redirect_advertising_inquiry(
            'rate-limited',
            $return_url
        );
    }

    $context_fields = [
        'placement' => __('Placement', 'local-directory-framework'),
        'category_name' => __('Category', 'local-directory-framework'),
        'specialty_name' => __('Service', 'local-directory-framework'),
        'state_name' => __('State', 'local-directory-framework'),
        'city_name' => __('City', 'local-directory-framework'),
    ];

    $context_lines = [];

    foreach ($context_fields as $field => $label) {
        $value = isset($_POST[$field])
            ? sanitize_text_field(
                wp_unslash($_POST[$field])
            )
            : '';

        $value =
            nwmd_directory_limit_advertising_inquiry_text(
                $value,
                160
            );

        if ('' !== $value) {
            $context_lines[] = $label . ': ' . $value;
        }
    }

    $safe_return_url =
        nwmd_directory_get_advertising_inquiry_return_url(
            $return_url
        );

    $admin_email = sanitize_email(
        apply_filters(
            'nwmd_directory_advertising_inquiry_admin_email',
            get_option('admin_email')
        )
    );

    if (
        '' === $admin_email ||
        false === is_email($admin_email)
    ) {
        nwmd_directory_redirect_advertising_inquiry(
            'mail-failed',
            $return_url
        );
    }

    $subject = sprintf(
        /* translators: %s: Business name. */
        __(
            '[NW Monthly] Advertising inquiry from %s',
            'local-directory-framework'
        ),
        $business_name
    );

    $mail_lines = [
        __(
            'A new advertising inquiry was submitted.',
            'local-directory-framework'
        ),
        '',
        __('Contact name', 'local-directory-framework')
            . ': ' . $contact_name,
        __('Contact email', 'local-directory-framework')
            . ': ' . $contact_email,
        __('Contact phone', 'local-directory-framework')
            . ': ' . (
                '' !== $contact_phone
                    ? $contact_phone
                    : __('Not provided', 'local-directory-framework')
            ),
        __('Business name', 'local-directory-framework')
            . ': ' . $business_name,
    ];

    if (!empty($context_lines)) {
        $mail_lines[] = '';
        $mail_lines[] = __(
            'Directory context',
            'local-directory-framework'
        );

        foreach ($context_lines as $context_line) {
            $mail_lines[] = $context_line;
        }
    }

    $mail_lines[] = '';
    $mail_lines[] = __(
        'Message',
        'local-directory-framework'
    );
    $mail_lines[] = $message;
    $mail_lines[] = '';
    $mail_lines[] = __(
        'Submitted from',
        'local-directory-framework'
    ) . ': ' . $safe_return_url;

    $from_name_filter = static function ($from_name) {
        return 'NW Monthly';
    };

    add_filter(
        'wp_mail_from_name',
        $from_name_filter,
        999
    );

    $mail_sent = wp_mail(
        $admin_email,
        $subject,
        implode("\n", $mail_lines),
        [
            'Content-Type: text/plain; charset=UTF-8',
            'Reply-To: ' . $contact_email,
        ]
    );

    remove_filter(
        'wp_mail_from_name',
        $from_name_filter,
        999
    );

    if (!$mail_sent) {
        nwmd_directory_redirect_advertising_inquiry(
            'mail-failed',
            $return_url
        );
    }

    nwmd_directory_increment_advertising_inquiry_rate(
        $contact_email
    );

    nwmd_directory_redirect_advertising_inquiry(
        'submitted',
        $return_url
    );
}

/**
 * Return the current same-site URL for an inquiry form.
 *
 * @return string
 */
function nwmd_directory_get_current_advertising_inquiry_url() {

    $request_uri = isset($_SERVER['REQUEST_URI'])
        ? (string) wp_unslash($_SERVER['REQUEST_URI'])
        : '/';

    $request_uri = '/' . ltrim(
        $request_uri,
        '/'
    );

    $current_url = esc_url_raw(
        home_url($request_uri)
    );

    if ('' === $current_url) {
        $current_url = home_url('/');
    }

    return remove_query_arg(
        'nwmd_ad_inquiry_notice',
        $current_url
    );
}

/**
 * Return the first valid term name from advertising context.
 *
 * @param array  $context     Advertising context.
 * @param string $context_key Term-ID context key.
 * @param string $taxonomy    Expected taxonomy.
 *
 * @return string
 */
function nwmd_directory_get_advertising_context_term_name(
    $context,
    $context_key,
    $taxonomy
) {

    if (
        !isset($context[$context_key]) ||
        !is_array($context[$context_key])
    ) {
        return '';
    }

    foreach ($context[$context_key] as $term_id) {
        $term = get_term(
            absint($term_id),
            $taxonomy
        );

        if (
            $term instanceof WP_Term &&
            !is_wp_error($term)
        ) {
            return sanitize_text_field(
                $term->name
            );
        }
    }

    return '';
}

/**
 * Render the public advertising-inquiry dialog.
 *
 * @param string $dialog_id Unique dialog element ID.
 * @param string $placement Advertising placement key.
 * @param array  $context   Advertising targeting context.
 */
function nwmd_directory_render_advertising_inquiry_dialog(
    $dialog_id,
    $placement,
    $context = []
) {

    $dialog_id = sanitize_html_class($dialog_id);

    if ('' === $dialog_id) {
        return;
    }

    $placement_label = 'results_sponsored' === $placement
        ? __(
            'Top sponsored result',
            'local-directory-framework'
        )
        : sanitize_text_field($placement);

    $category_name =
        nwmd_directory_get_advertising_context_term_name(
            $context,
            'category_term_ids',
            'nwmd_category'
        );

    $specialty_name =
        nwmd_directory_get_advertising_context_term_name(
            $context,
            'specialty_term_ids',
            'nwmd_specialty'
        );

    if (
        '' === $specialty_name &&
        'results_sponsored' === $placement
    ) {
        $specialty_name = __(
            'All services',
            'local-directory-framework'
        );
    }

    $state_name =
        nwmd_directory_get_advertising_context_term_name(
            $context,
            'state_term_ids',
            'nwmd_state'
        );

    $city_name =
        nwmd_directory_get_advertising_context_term_name(
            $context,
            'city_term_ids',
            'nwmd_city'
        );

    $return_url =
        nwmd_directory_get_current_advertising_inquiry_url();
    ?>
    <dialog
        class="nwmd-info-dialog nwmd-ad-inquiry-dialog"
        id="<?php echo esc_attr($dialog_id); ?>"
        data-nwmd-dialog
    >
        <div class="nwmd-info-dialog__header">
            <h2>
                <?php
                echo esc_html__(
                    'Advertise on NW Monthly',
                    'local-directory-framework'
                );
                ?>
            </h2>

            <button
                type="button"
                class="nwmd-info-dialog__close"
                data-nwmd-dialog-close
                aria-label="<?php
                    echo esc_attr__(
                        'Close advertising inquiry',
                        'local-directory-framework'
                    );
                ?>"
            >
                &times;
            </button>
        </div>

        <div class="nwmd-info-dialog__content">
            <p>
                <?php
                echo esc_html__(
                    'Tell us about your business and the sponsored placement you are interested in.',
                    'local-directory-framework'
                );
                ?>
            </p>

            <form
                class="nwmd-ad-inquiry-form"
                action="<?php echo esc_url(
                    admin_url('admin-post.php')
                ); ?>"
                method="post"
            >
                <input
                    type="hidden"
                    name="action"
                    value="nwmd_submit_advertising_inquiry"
                >

                <input
                    type="hidden"
                    name="return_url"
                    value="<?php echo esc_attr($return_url); ?>"
                >

                <input
                    type="hidden"
                    name="placement"
                    value="<?php echo esc_attr(
                        $placement_label
                    ); ?>"
                >

                <input
                    type="hidden"
                    name="category_name"
                    value="<?php echo esc_attr(
                        $category_name
                    ); ?>"
                >

                <input
                    type="hidden"
                    name="specialty_name"
                    value="<?php echo esc_attr(
                        $specialty_name
                    ); ?>"
                >

                <input
                    type="hidden"
                    name="state_name"
                    value="<?php echo esc_attr(
                        $state_name
                    ); ?>"
                >

                <input
                    type="hidden"
                    name="city_name"
                    value="<?php echo esc_attr(
                        $city_name
                    ); ?>"
                >

                <?php
                wp_nonce_field(
                    'nwmd_submit_advertising_inquiry',
                    'nwmd_advertising_inquiry_nonce'
                );
                ?>

                <div
                    class="nwmd-ad-inquiry-form__honeypot"
                    aria-hidden="true"
                >
                    <label for="<?php echo esc_attr(
                        $dialog_id . '-website'
                    ); ?>">
                        Website
                    </label>

                    <input
                        type="text"
                        id="<?php echo esc_attr(
                            $dialog_id . '-website'
                        ); ?>"
                        name="company_website"
                        tabindex="-1"
                        autocomplete="off"
                    >
                </div>

                <div class="nwmd-ad-inquiry-form__field">
                    <label for="<?php echo esc_attr(
                        $dialog_id . '-name'
                    ); ?>">
                        <?php
                        echo esc_html__(
                            'Contact Name',
                            'local-directory-framework'
                        );
                        ?>
                        <span aria-hidden="true">*</span>
                    </label>

                    <input
                        type="text"
                        id="<?php echo esc_attr(
                            $dialog_id . '-name'
                        ); ?>"
                        name="contact_name"
                        maxlength="120"
                        autocomplete="name"
                        required
                    >
                </div>

                <div class="nwmd-ad-inquiry-form__field">
                    <label for="<?php echo esc_attr(
                        $dialog_id . '-email'
                    ); ?>">
                        <?php
                        echo esc_html__(
                            'Email',
                            'local-directory-framework'
                        );
                        ?>
                        <span aria-hidden="true">*</span>
                    </label>

                    <input
                        type="email"
                        id="<?php echo esc_attr(
                            $dialog_id . '-email'
                        ); ?>"
                        name="contact_email"
                        maxlength="320"
                        autocomplete="email"
                        required
                    >
                </div>

                <div class="nwmd-ad-inquiry-form__field">
                    <label for="<?php echo esc_attr(
                        $dialog_id . '-phone'
                    ); ?>">
                        <?php
                        echo esc_html__(
                            'Phone',
                            'local-directory-framework'
                        );
                        ?>
                    </label>

                    <input
                        type="tel"
                        id="<?php echo esc_attr(
                            $dialog_id . '-phone'
                        ); ?>"
                        name="contact_phone"
                        maxlength="50"
                        autocomplete="tel"
                    >
                </div>

                <div class="nwmd-ad-inquiry-form__field">
                    <label for="<?php echo esc_attr(
                        $dialog_id . '-business'
                    ); ?>">
                        <?php
                        echo esc_html__(
                            'Business Name',
                            'local-directory-framework'
                        );
                        ?>
                        <span aria-hidden="true">*</span>
                    </label>

                    <input
                        type="text"
                        id="<?php echo esc_attr(
                            $dialog_id . '-business'
                        ); ?>"
                        name="business_name"
                        maxlength="160"
                        autocomplete="organization"
                        required
                    >
                </div>

                <div class="nwmd-ad-inquiry-form__field">
                    <label for="<?php echo esc_attr(
                        $dialog_id . '-message'
                    ); ?>">
                        <?php
                        echo esc_html__(
                            'Message',
                            'local-directory-framework'
                        );
                        ?>
                        <span aria-hidden="true">*</span>
                    </label>

                    <textarea
                        id="<?php echo esc_attr(
                            $dialog_id . '-message'
                        ); ?>"
                        name="inquiry_message"
                        rows="5"
                        maxlength="3000"
                        required
                    ></textarea>
                </div>

                <button
                    class="nwmd-ad-inquiry-form__submit"
                    type="submit"
                >
                    <?php
                    echo esc_html__(
                        'Send Inquiry',
                        'local-directory-framework'
                    );
                    ?>
                </button>
            </form>
        </div>
    </dialog>
    <?php
}
add_action(
    'admin_post_nopriv_nwmd_submit_advertising_inquiry',
    'nwmd_directory_handle_advertising_inquiry'
);

add_action(
    'admin_post_nwmd_submit_advertising_inquiry',
    'nwmd_directory_handle_advertising_inquiry'
);
