<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return the business Deals table name.
 *
 * @return string
 */
function nwmd_directory_get_business_deals_table_name() {

    global $wpdb;

    return $wpdb->prefix . 'nwmd_business_deals';
}

/**
 * Return controlled Deal status choices.
 *
 * @return array
 */
function nwmd_directory_get_business_deal_status_choices() {

    return [
        'draft' => __(
            'Draft',
            'local-directory-framework'
        ),
        'active' => __(
            'Active',
            'local-directory-framework'
        ),
        'inactive' => __(
            'Inactive',
            'local-directory-framework'
        ),
        'archived' => __(
            'Archived',
            'local-directory-framework'
        ),
    ];
}

/**
 * Return one Deal by database ID.
 *
 * @param int $deal_id Deal database ID.
 *
 * @return object|null
 */
function nwmd_directory_get_business_deal($deal_id) {

    global $wpdb;

    $deal_id = absint($deal_id);

    if (0 === $deal_id) {
        return null;
    }

    $table = nwmd_directory_get_business_deals_table_name();

    return $wpdb->get_row(
        $wpdb->prepare(
            "SELECT *
            FROM {$table}
            WHERE id = %d
            LIMIT 1",
            $deal_id
        )
    );
}

/**
 * Return one Deal using its stable business and Deal identity.
 *
 * This identity will be used by the monthly CSV update process.
 *
 * @param int    $business_post_id Business post ID.
 * @param string $deal_slug        Stable Deal slug.
 *
 * @return object|null
 */
function nwmd_directory_get_business_deal_by_identity(
    $business_post_id,
    $deal_slug
) {

    global $wpdb;

    $business_post_id = absint($business_post_id);
    $deal_slug = sanitize_title($deal_slug);

    if (
        0 === $business_post_id ||
        '' === $deal_slug
    ) {
        return null;
    }

    $table = nwmd_directory_get_business_deals_table_name();

    return $wpdb->get_row(
        $wpdb->prepare(
            "SELECT *
            FROM {$table}
            WHERE business_post_id = %d
            AND deal_slug = %s
            LIMIT 1",
            $business_post_id,
            $deal_slug
        )
    );
}

/**
 * Return whether a business post is valid for a Deal.
 *
 * @param int $business_post_id Business post ID.
 *
 * @return bool
 */
function nwmd_directory_business_deal_has_valid_business(
    $business_post_id
) {

    $business_post_id = absint($business_post_id);

    return (
        $business_post_id > 0 &&
        'nwmd_business' === get_post_type($business_post_id)
    );
}

/**
 * Return whether one stored Deal is currently visible.
 *
 * Active Deals are automatically hidden before their start date,
 * after their expiration date, or after being archived.
 *
 * @param object|array $deal Deal record.
 * @param string       $now  Optional WordPress-local MySQL datetime.
 *
 * @return bool
 */
function nwmd_directory_business_deal_is_current(
    $deal,
    $now = ''
) {

    if (is_array($deal)) {
        $deal = (object) $deal;
    }

    if (!is_object($deal)) {
        return false;
    }

    if (
        'active' !== ($deal->status ?? '') ||
        !empty($deal->archived_at)
    ) {
        return false;
    }

    if ('' === $now) {
        $now = current_time('mysql');
    }

    $starts_at = $deal->starts_at ?? null;
    $expires_at = $deal->expires_at ?? null;

    if (
        !empty($starts_at) &&
        $starts_at > $now
    ) {
        return false;
    }

    if (
        !empty($expires_at) &&
        $expires_at < $now
    ) {
        return false;
    }

    return true;
}

/**
 * Return active public Deals for one business.
 *
 * Featured Deals appear first. Expiring Deals appear before
 * Deals without an expiration date.
 *
 * @param int $business_post_id Business post ID.
 * @param int $limit            Maximum results. Zero means no limit.
 *
 * @return array
 */
function nwmd_directory_get_active_business_deals(
    $business_post_id,
    $limit = 0
) {

    global $wpdb;

    $business_post_id = absint($business_post_id);
    $limit = absint($limit);

    if (
        !nwmd_directory_business_deal_has_valid_business(
            $business_post_id
        )
    ) {
        return [];
    }

    $table = nwmd_directory_get_business_deals_table_name();
    $now = current_time('mysql');

    $sql = "SELECT *
        FROM {$table}
        WHERE business_post_id = %d
        AND status = 'active'
        AND archived_at IS NULL
        AND (
            starts_at IS NULL
            OR starts_at <= %s
        )
        AND (
            expires_at IS NULL
            OR expires_at >= %s
        )
        ORDER BY
            is_featured DESC,
            CASE
                WHEN expires_at IS NULL THEN 1
                ELSE 0
            END ASC,
            expires_at ASC,
            id ASC";

    $arguments = [
        $business_post_id,
        $now,
        $now,
    ];

    if ($limit > 0) {
        $sql .= ' LIMIT %d';
        $arguments[] = $limit;
    }

    return $wpdb->get_results(
        $wpdb->prepare(
            $sql,
            ...$arguments
        )
    );
}

/**
 * Return one featured active Deal for each requested business.
 *
 * This performs one database query for an entire directory page,
 * preventing one Deal query per business card.
 *
 * @param array $business_post_ids Business post IDs.
 *
 * @return array Deals keyed by business post ID.
 */
function nwmd_directory_get_featured_business_deals_for_businesses(
    $business_post_ids
) {

    global $wpdb;

    $business_post_ids = array_values(
        array_unique(
            array_filter(
                array_map(
                    'absint',
                    (array) $business_post_ids
                )
            )
        )
    );

    if (empty($business_post_ids)) {
        return [];
    }

    $table = nwmd_directory_get_business_deals_table_name();
    $now = current_time('mysql');

    $placeholders = implode(
        ', ',
        array_fill(
            0,
            count($business_post_ids),
            '%d'
        )
    );

    $sql = "SELECT *
        FROM {$table}
        WHERE business_post_id IN ({$placeholders})
        AND status = 'active'
        AND archived_at IS NULL
        AND (
            starts_at IS NULL
            OR starts_at <= %s
        )
        AND (
            expires_at IS NULL
            OR expires_at >= %s
        )
        ORDER BY
            business_post_id ASC,
            is_featured DESC,
            CASE
                WHEN expires_at IS NULL THEN 1
                ELSE 0
            END ASC,
            expires_at ASC,
            id ASC";

    $arguments = array_merge(
        $business_post_ids,
        [
            $now,
            $now,
        ]
    );

    $records = $wpdb->get_results(
        $wpdb->prepare(
            $sql,
            ...$arguments
        )
    );

    $featured_deals = [];

    foreach ($records as $record) {
        $business_post_id = absint(
            $record->business_post_id
        );

        if (!isset($featured_deals[$business_post_id])) {
            $featured_deals[$business_post_id] = $record;
        }
    }

    return $featured_deals;
}
/**
 * Return the featured active Deal for one business.
 *
 * @param int $business_post_id Business post ID.
 *
 * @return object|null
 */
function nwmd_directory_get_featured_business_deal(
    $business_post_id
) {

    $deals = nwmd_directory_get_active_business_deals(
        $business_post_id,
        1
    );

    return $deals[0] ?? null;
}

/**
 * Format one Deal datetime using the site date format.
 *
 * @param string|null $value Stored MySQL datetime.
 *
 * @return string
 */
function nwmd_directory_format_business_deal_date($value) {

    if (empty($value)) {
        return '';
    }

    return mysql2date(
        get_option('date_format'),
        (string) $value,
        true
    );
}

/**
 * Return the public card text for one Deal.
 *
 * @param object|array $deal Deal record.
 *
 * @return string
 */
function nwmd_directory_get_business_deal_card_text($deal) {

    if (is_array($deal)) {
        $deal = (object) $deal;
    }

    if (!is_object($deal)) {
        return '';
    }

    $card_text = sanitize_text_field(
        (string) ($deal->card_text ?? '')
    );

    if ('' !== $card_text) {
        return $card_text;
    }

    return sanitize_text_field(
        (string) ($deal->title ?? '')
    );
}
