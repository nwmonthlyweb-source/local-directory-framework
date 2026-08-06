<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Limit one sanitized Deal value to a database-safe length.
 *
 * @param string $value  Sanitized value.
 * @param int    $length Maximum characters.
 *
 * @return string
 */
function nwmd_directory_limit_business_deal_text(
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
 * Return one valid Business post for Deal administration.
 *
 * @param int $business_post_id Business post ID.
 *
 * @return WP_Post|null
 */
function nwmd_directory_get_business_deal_admin_business(
    $business_post_id
) {

    $business = get_post(
        absint($business_post_id)
    );

    if (
        !$business instanceof WP_Post ||
        'nwmd_business' !== $business->post_type ||
        in_array(
            $business->post_status,
            [
                'trash',
                'auto-draft',
            ],
            true
        )
    ) {
        return null;
    }

    return $business;
}

/**
 * Parse a Deal date into WordPress-local MySQL format.
 *
 * Expiration dates are stored at 23:59:59 so the Deal remains
 * available for the entire selected expiration day.
 *
 * @param mixed $value      Raw date input.
 * @param bool  $end_of_day Whether to use 23:59:59.
 *
 * @return string|null|false
 */
function nwmd_directory_parse_business_deal_admin_date(
    $value,
    $end_of_day = false
) {

    $value = sanitize_text_field(
        wp_unslash($value)
    );

    if ('' === $value) {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $value,
        wp_timezone()
    );

    $errors = DateTimeImmutable::getLastErrors();

    if (
        !$date instanceof DateTimeImmutable ||
        (
            is_array($errors) &&
            (
                $errors['warning_count'] > 0 ||
                $errors['error_count'] > 0
            )
        )
    ) {
        return false;
    }

    if ($end_of_day) {
        $date = $date->setTime(
            23,
            59,
            59
        );
    }

    return $date->format('Y-m-d H:i:s');
}

/**
 * Return the date part of a stored Deal datetime.
 *
 * @param mixed $value Stored value.
 *
 * @return string
 */
function nwmd_directory_get_business_deal_admin_date_value(
    $value
) {

    $value = sanitize_text_field(
        (string) $value
    );

    if (
        !preg_match(
            '/^\d{4}-\d{2}-\d{2}/',
            $value,
            $matches
        )
    ) {
        return '';
    }

    return $matches[0];
}

/**
 * Redirect to the Deals admin page.
 *
 * @param string $notice           Notice identifier.
 * @param int    $business_post_id Business post ID.
 * @param int    $deal_id          Optional Deal ID.
 */
function nwmd_directory_redirect_business_deals_admin(
    $notice,
    $business_post_id = 0,
    $deal_id = 0
) {

    $arguments = [
        'post_type'   => 'nwmd_business',
        'page'        => 'nwmd-business-deals',
        'nwmd_notice' => sanitize_key($notice),
    ];

    if ($business_post_id > 0) {
        $arguments['business_id'] = absint(
            $business_post_id
        );
    }

    if ($deal_id > 0) {
        $arguments['deal_id'] = absint(
            $deal_id
        );
    }

    wp_safe_redirect(
        add_query_arg(
            $arguments,
            admin_url('edit.php')
        )
    );

    exit;
}

/**
 * Save one Business Deal.
 */
function nwmd_directory_save_business_deal() {

    if (!current_user_can('manage_options')) {
        wp_die(
            esc_html__(
                'You are not allowed to manage business deals.',
                'local-directory-framework'
            )
        );
    }

    check_admin_referer(
        'nwmd_save_business_deal',
        'nwmd_business_deal_nonce'
    );

    $business_post_id = isset($_POST['business_post_id'])
        ? absint($_POST['business_post_id'])
        : 0;

    $deal_id = isset($_POST['deal_id'])
        ? absint($_POST['deal_id'])
        : 0;

    $business =
        nwmd_directory_get_business_deal_admin_business(
            $business_post_id
        );

    if (!$business instanceof WP_Post) {
        nwmd_directory_redirect_business_deals_admin(
            'invalid-business'
        );
    }

    $existing = null;

    if ($deal_id > 0) {
        $existing = nwmd_directory_get_business_deal(
            $deal_id
        );

        if (
            !is_object($existing) ||
            absint($existing->business_post_id) !==
                $business_post_id
        ) {
            nwmd_directory_redirect_business_deals_admin(
                'deal-not-found',
                $business_post_id
            );
        }
    }

    $title = isset($_POST['title'])
        ? sanitize_text_field(
            wp_unslash($_POST['title'])
        )
        : '';

    $title = nwmd_directory_limit_business_deal_text(
        $title,
        255
    );

    $deal_slug = is_object($existing)
        ? sanitize_title($existing->deal_slug)
        : (
            isset($_POST['deal_slug'])
                ? sanitize_title(
                    wp_unslash($_POST['deal_slug'])
                )
                : ''
        );

    if (
        '' === $deal_slug &&
        '' !== $title
    ) {
        $deal_slug = sanitize_title($title);
    }

    $card_text = isset($_POST['card_text'])
        ? sanitize_text_field(
            wp_unslash($_POST['card_text'])
        )
        : '';

    $card_text = nwmd_directory_limit_business_deal_text(
        $card_text,
        255
    );

    if ('' === $card_text) {
        $card_text = $title;
    }

    $description = isset($_POST['description'])
        ? sanitize_textarea_field(
            wp_unslash($_POST['description'])
        )
        : '';

    $promo_code = isset($_POST['promo_code'])
        ? sanitize_text_field(
            wp_unslash($_POST['promo_code'])
        )
        : '';

    $promo_code = nwmd_directory_limit_business_deal_text(
        $promo_code,
        100
    );

    $source_url = isset($_POST['source_url'])
        ? esc_url_raw(
            wp_unslash($_POST['source_url'])
        )
        : '';

    $status = isset($_POST['status'])
        ? sanitize_key(
            wp_unslash($_POST['status'])
        )
        : 'draft';

    $status_choices =
        nwmd_directory_get_business_deal_status_choices();

    $is_featured = isset($_POST['is_featured'])
        ? 1
        : 0;

    $starts_at =
        nwmd_directory_parse_business_deal_admin_date(
            $_POST['starts_at'] ?? ''
        );

    $expires_at =
        nwmd_directory_parse_business_deal_admin_date(
            $_POST['expires_at'] ?? '',
            true
        );

    $verified_at =
        nwmd_directory_parse_business_deal_admin_date(
            $_POST['verified_at'] ?? ''
        );

    if (
        '' === $title ||
        '' === $deal_slug ||
        '' === $description ||
        !isset($status_choices[$status])
    ) {
        nwmd_directory_redirect_business_deals_admin(
            'invalid-values',
            $business_post_id,
            $deal_id
        );
    }

    if (
        false === $starts_at ||
        false === $expires_at ||
        false === $verified_at ||
        (
            null !== $starts_at &&
            null !== $expires_at &&
            $expires_at < $starts_at
        ) ||
        (
            null !== $verified_at &&
            $verified_at > current_time('mysql')
        ) ||
        (
            'active' === $status &&
            null !== $expires_at &&
            $expires_at < current_time('mysql')
        )
    ) {
        nwmd_directory_redirect_business_deals_admin(
            'invalid-dates',
            $business_post_id,
            $deal_id
        );
    }

    if ('' !== $source_url) {
        $scheme = strtolower(
            (string) wp_parse_url(
                $source_url,
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
            !wp_http_validate_url($source_url)
        ) {
            nwmd_directory_redirect_business_deals_admin(
                'invalid-source-url',
                $business_post_id,
                $deal_id
            );
        }
    }

    if (
        'active' === $status &&
        (
            '' === $source_url ||
            null === $verified_at
        )
    ) {
        nwmd_directory_redirect_business_deals_admin(
            'verification-required',
            $business_post_id,
            $deal_id
        );
    }

    if (1 === $is_featured && 'active' !== $status) {
        nwmd_directory_redirect_business_deals_admin(
            'invalid-values',
            $business_post_id,
            $deal_id
        );
    }

    $identity =
        nwmd_directory_get_business_deal_by_identity(
            $business_post_id,
            $deal_slug
        );

    if (
        is_object($identity) &&
        absint($identity->id) !== $deal_id
    ) {
        nwmd_directory_redirect_business_deals_admin(
            'deal-exists',
            $business_post_id,
            $deal_id
        );
    }

    global $wpdb;

    $table =
        nwmd_directory_get_business_deals_table_name();

    $now = current_time('mysql');
    $user_id = get_current_user_id();
    $using_transaction = 1 === $is_featured;

    if (
        $using_transaction &&
        false === $wpdb->query('START TRANSACTION')
    ) {
        nwmd_directory_redirect_business_deals_admin(
            'deal-save-failed',
            $business_post_id,
            $deal_id
        );
    }

    $data = [
        'business_post_id' => $business_post_id,
        'deal_slug'        => $deal_slug,
        'title'            => $title,
        'card_text'        => $card_text,
        'description'      => $description,
        'promo_code'       => $promo_code,
        'source_url'       => $source_url,
        'starts_at'        => $starts_at,
        'expires_at'       => $expires_at,
        'verified_at'      => $verified_at,
        'status'           => $status,
        'is_featured'      => $is_featured,
        'updated_by'       => $user_id,
        'updated_at'       => $now,
        'archived_at'      => (
            'archived' === $status
                ? $now
                : null
        ),
    ];

    $formats = [
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
        '%s',
        '%d',
        '%d',
        '%s',
        '%s',
    ];

    if ($deal_id > 0) {
        $saved = $wpdb->update(
            $table,
            $data,
            [
                'id' => $deal_id,
            ],
            $formats,
            [
                '%d',
            ]
        );

        $saved_id = $deal_id;
        $notice = 'deal-updated';
    } else {
        $data['created_by'] = $user_id;
        $data['created_at'] = $now;

        $formats[] = '%d';
        $formats[] = '%s';

        $saved = $wpdb->insert(
            $table,
            $data,
            $formats
        );

        $saved_id = absint(
            $wpdb->insert_id
        );

        $notice = 'deal-created';
    }

    if (false === $saved || 0 === $saved_id) {
        if ($using_transaction) {
            $wpdb->query('ROLLBACK');
        }

        nwmd_directory_redirect_business_deals_admin(
            'deal-save-failed',
            $business_post_id,
            $deal_id
        );
    }

    if (1 === $is_featured) {
        $unfeatured = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table}
                SET
                    is_featured = 0,
                    updated_by = %d,
                    updated_at = %s
                WHERE business_post_id = %d
                AND id <> %d",
                $user_id,
                $now,
                $business_post_id,
                $saved_id
            )
        );

        if (false === $unfeatured) {
            $wpdb->query('ROLLBACK');

            nwmd_directory_redirect_business_deals_admin(
                'deal-save-failed',
                $business_post_id,
                $deal_id
            );
        }

        if (false === $wpdb->query('COMMIT')) {
            $wpdb->query('ROLLBACK');

            nwmd_directory_redirect_business_deals_admin(
                'deal-save-failed',
                $business_post_id,
                $deal_id
            );
        }
    }

    nwmd_directory_redirect_business_deals_admin(
        $notice,
        $business_post_id,
        $saved_id
    );
}

add_action(
    'admin_post_nwmd_save_business_deal',
    'nwmd_directory_save_business_deal'
);

/**
 * Archive one Business Deal without deleting its history.
 */
function nwmd_directory_archive_business_deal() {

    if (!current_user_can('manage_options')) {
        wp_die(
            esc_html__(
                'You are not allowed to archive business deals.',
                'local-directory-framework'
            )
        );
    }

    $deal_id = isset($_POST['deal_id'])
        ? absint($_POST['deal_id'])
        : 0;

    check_admin_referer(
        'nwmd_archive_business_deal_' . $deal_id,
        'nwmd_business_deal_archive_nonce'
    );

    $deal = nwmd_directory_get_business_deal(
        $deal_id
    );

    if (!is_object($deal)) {
        nwmd_directory_redirect_business_deals_admin(
            'deal-not-found'
        );
    }

    $business_post_id = absint(
        $deal->business_post_id
    );

    global $wpdb;

    $updated = $wpdb->update(
        nwmd_directory_get_business_deals_table_name(),
        [
            'status'      => 'archived',
            'is_featured' => 0,
            'updated_by'  => get_current_user_id(),
            'updated_at'  => current_time('mysql'),
            'archived_at' => current_time('mysql'),
        ],
        [
            'id' => $deal_id,
        ],
        [
            '%s',
            '%d',
            '%d',
            '%s',
            '%s',
        ],
        [
            '%d',
        ]
    );

    if (false === $updated) {
        nwmd_directory_redirect_business_deals_admin(
            'deal-archive-failed',
            $business_post_id,
            $deal_id
        );
    }

    nwmd_directory_redirect_business_deals_admin(
        'deal-archived',
        $business_post_id
    );
}

add_action(
    'admin_post_nwmd_archive_business_deal',
    'nwmd_directory_archive_business_deal'
);
