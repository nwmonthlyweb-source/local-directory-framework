<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return supported public advertising placements.
 *
 * @return array
 */
function nwmd_directory_get_ad_placement_choices() {

    return [
        'results_sponsored' => __(
            'Sponsored result',
            'local-directory-framework'
        ),
        'results_bottom' => __(
            'Results bottom banner',
            'local-directory-framework'
        ),
        'business_profile_bottom' => __(
            'Business profile bottom banner',
            'local-directory-framework'
        ),
    ];
}

/**
 * Return supported advertising statuses.
 *
 * @return array
 */
function nwmd_directory_get_ad_status_choices() {

    return [
        'draft' => __(
            'Draft',
            'local-directory-framework'
        ),
        'active' => __(
            'Active',
            'local-directory-framework'
        ),
        'paused' => __(
            'Paused',
            'local-directory-framework'
        ),
        'expired' => __(
            'Expired',
            'local-directory-framework'
        ),
        'archived' => __(
            'Archived',
            'local-directory-framework'
        ),
    ];
}

/**
 * Return one advertisement by ID.
 *
 * @param int $ad_id Advertisement ID.
 *
 * @return object|null
 */
function nwmd_directory_get_ad($ad_id) {

    $ad_id = absint($ad_id);

    if ($ad_id < 1) {
        return null;
    }

    global $wpdb;

    $table = $wpdb->prefix
        . 'nwmd_ads';

    $ad = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT
                id,
                advertiser_name,
                campaign_name,
                placement,
                image_attachment_id,
                destination_url,
                state_term_id,
                city_term_id,
                category_term_id,
                specialty_term_id,
                starts_at,
                ends_at,
                status,
                impression_count,
                click_count,
                created_at,
                updated_at
            FROM {$table}
            WHERE id = %d
            LIMIT 1",
            $ad_id
        )
    );

    return is_object($ad)
        ? $ad
        : null;
}

/**
 * Return whether an advertisement is currently eligible for display.
 *
 * @param object      $ad          Advertisement record.
 * @param string|null $current_time Optional WordPress-local MySQL time.
 *
 * @return bool
 */
function nwmd_directory_ad_is_current(
    $ad,
    $current_time = null
) {

    if (
        !is_object($ad) ||
        'active' !== (string) $ad->status
    ) {
        return false;
    }

    if (null === $current_time) {
        $current_time = current_time(
            'mysql'
        );
    }

    if (
        !empty($ad->starts_at) &&
        (string) $ad->starts_at > $current_time
    ) {
        return false;
    }

    if (
        !empty($ad->ends_at) &&
        (string) $ad->ends_at < $current_time
    ) {
        return false;
    }

    return true;
}

/**
 * Normalize advertising context term IDs.
 *
 * @param array $context Raw targeting context.
 *
 * @return array
 */
function nwmd_directory_normalize_ad_context($context) {

    $keys = [
        'state_term_ids',
        'city_term_ids',
        'category_term_ids',
        'specialty_term_ids',
    ];

    $normalized = [];

    foreach ($keys as $key) {
        $values = isset($context[$key])
            ? (array) $context[$key]
            : [];

        $values = array_values(
            array_unique(
                array_filter(
                    array_map(
                        'absint',
                        $values
                    )
                )
            )
        );

        $normalized[$key] = $values;
    }

    return $normalized;
}

/**
 * Return whether an advertisement matches one public context.
 *
 * A zero targeting value means that the advertisement applies to every
 * value for that taxonomy.
 *
 * @param object $ad      Advertisement record.
 * @param array  $context Normalized targeting context.
 *
 * @return bool
 */
function nwmd_directory_ad_matches_context(
    $ad,
    $context
) {

    $targeting = [
        'state_term_id' => 'state_term_ids',
        'city_term_id' => 'city_term_ids',
        'category_term_id' => 'category_term_ids',
        'specialty_term_id' => 'specialty_term_ids',
    ];

    foreach ($targeting as $ad_key => $context_key) {
        $target_term_id = absint(
            $ad->{$ad_key}
        );

        if (0 === $target_term_id) {
            continue;
        }

        if (
            !in_array(
                $target_term_id,
                $context[$context_key],
                true
            )
        ) {
            return false;
        }
    }

    return true;
}

/**
 * Return an advertisement targeting-specificity score.
 *
 * @param object $ad Advertisement record.
 *
 * @return int
 */
function nwmd_directory_get_ad_specificity($ad) {

    $score = 0;

    foreach (
        [
            'state_term_id',
            'city_term_id',
            'category_term_id',
            'specialty_term_id',
        ] as $key
    ) {
        if (absint($ad->{$key}) > 0) {
            $score++;
        }
    }

    return $score;
}

/**
 * Return the best current advertisement for one placement and context.
 *
 * The most specifically targeted advertisement wins. Ties use the newest
 * updated advertisement and then the newest ID.
 *
 * @param string $placement Placement key.
 * @param array  $context   Public targeting context.
 *
 * @return object|null
 */
function nwmd_directory_get_active_ad(
    $placement,
    $context = []
) {

    $placements = nwmd_directory_get_ad_placement_choices();

    if (!isset($placements[$placement])) {
        return null;
    }

    global $wpdb;

    $table = $wpdb->prefix
        . 'nwmd_ads';

    $current_time = current_time(
        'mysql'
    );

    $ads = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT
                id,
                advertiser_name,
                campaign_name,
                placement,
                image_attachment_id,
                destination_url,
                state_term_id,
                city_term_id,
                category_term_id,
                specialty_term_id,
                starts_at,
                ends_at,
                status,
                impression_count,
                click_count,
                created_at,
                updated_at
            FROM {$table}
            WHERE placement = %s
                AND status = %s
                AND (
                    starts_at IS NULL OR
                    starts_at <= %s
                )
                AND (
                    ends_at IS NULL OR
                    ends_at >= %s
                )
            ORDER BY updated_at DESC, id DESC
            LIMIT 200",
            $placement,
            'active',
            $current_time,
            $current_time
        )
    );

    if (empty($ads)) {
        return null;
    }

    $context = nwmd_directory_normalize_ad_context(
        $context
    );

    $matches = [];

    foreach ($ads as $ad) {
        if (
            !nwmd_directory_ad_matches_context(
                $ad,
                $context
            )
        ) {
            continue;
        }

        $matches[] = $ad;
    }

    if (empty($matches)) {
        return null;
    }

    usort(
        $matches,
        static function ($first, $second) {

            $specificity_difference =
                nwmd_directory_get_ad_specificity($second)
                - nwmd_directory_get_ad_specificity($first);

            if (0 !== $specificity_difference) {
                return $specificity_difference;
            }

            $updated_comparison = strcmp(
                (string) $second->updated_at,
                (string) $first->updated_at
            );

            if (0 !== $updated_comparison) {
                return $updated_comparison;
            }

            return absint($second->id)
                - absint($first->id);
        }
    );

    return $matches[0];
}

/**
 * Return advertising context for a guided ranking selection.
 *
 * @param array $selection Validated ranking selection.
 *
 * @return array
 */
function nwmd_directory_get_ranking_ad_context($selection) {

    $context = [
        'state_term_ids' => [],
        'city_term_ids' => [],
        'category_term_ids' => [],
        'specialty_term_ids' => [],
    ];

    $mapping = [
        'state' => 'state_term_ids',
        'city' => 'city_term_ids',
        'category' => 'category_term_ids',
        'specialty' => 'specialty_term_ids',
    ];

    foreach ($mapping as $selection_key => $context_key) {
        $term = $selection['terms'][$selection_key]
            ?? null;

        if ($term instanceof WP_Term) {
            $context[$context_key][] = absint(
                $term->term_id
            );
        }
    }

    return $context;
}

/**
 * Return advertising context for a public Business profile.
 *
 * @param int $post_id Business post ID.
 *
 * @return array
 */
function nwmd_directory_get_business_ad_context($post_id) {

    $post_id = absint($post_id);

    $mapping = [
        'nwmd_state' => 'state_term_ids',
        'nwmd_city' => 'city_term_ids',
        'nwmd_category' => 'category_term_ids',
        'nwmd_specialty' => 'specialty_term_ids',
    ];

    $context = [];

    foreach ($mapping as $taxonomy => $context_key) {
        $term_ids = wp_get_object_terms(
            $post_id,
            $taxonomy,
            [
                'fields' => 'ids',
            ]
        );

        $context[$context_key] = is_wp_error($term_ids)
            ? []
            : array_map(
                'absint',
                $term_ids
            );
    }

    return $context;
}

/**
 * Increment one advertisement impression once during the current request.
 *
 * @param int $ad_id Advertisement ID.
 */
function nwmd_directory_record_ad_impression($ad_id) {

    static $recorded = [];

    $ad_id = absint($ad_id);

    if (
        $ad_id < 1 ||
        isset($recorded[$ad_id])
    ) {
        return;
    }

    global $wpdb;

    $table = $wpdb->prefix
        . 'nwmd_ads';

    $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$table}
            SET impression_count = impression_count + 1
            WHERE id = %d",
            $ad_id
        )
    );

    $recorded[$ad_id] = true;
}

/**
 * Return the public click-tracking URL for one advertisement.
 *
 * @param int $ad_id Advertisement ID.
 *
 * @return string
 */
function nwmd_directory_get_ad_click_url($ad_id) {

    return home_url(
        '/sponsored-click/'
        . absint($ad_id)
        . '/'
    );
}

/**
 * Render one current advertisement.
 *
 * @param string $placement Placement key.
 * @param array  $context   Public targeting context.
 */
function nwmd_directory_render_ad(
    $placement,
    $context = []
) {

    $ad = nwmd_directory_get_active_ad(
        $placement,
        $context
    );

    if (!$ad) {
        return;
    }

    $destination_url = wp_http_validate_url(
        (string) $ad->destination_url
    );

    if (!$destination_url) {
        return;
    }

    $is_sponsored_result =
        'results_sponsored' === $placement;

    $label = $is_sponsored_result
        ? __(
            'Sponsored',
            'local-directory-framework'
        )
        : __(
            'Advertisement',
            'local-directory-framework'
        );

    $classes = [
        'nwmd-ad',
        'nwmd-ad--' . sanitize_html_class(
            $placement
        ),
    ];

    if ($is_sponsored_result) {
        $classes[] = 'nwmd-ad--sponsored-result';
    } else {
        $classes[] = 'nwmd-ad--banner';
    }

    $click_url = nwmd_directory_get_ad_click_url(
        $ad->id
    );

    $image = '';

    if ($ad->image_attachment_id > 0) {
        $image = wp_get_attachment_image(
            absint($ad->image_attachment_id),
            $is_sponsored_result
                ? 'medium_large'
                : 'large',
            false,
            [
                'class' => 'nwmd-ad__image',
                'loading' => 'lazy',
            ]
        );
    }

    if ('' === $image) {
        $classes[] = 'nwmd-ad--no-image';
    }

    nwmd_directory_record_ad_impression(
        $ad->id
    );
    ?>
    <aside
        class="<?php echo esc_attr(
            implode(
                ' ',
                $classes
            )
        ); ?>"
        aria-label="<?php echo esc_attr($label); ?>"
    >
        <a
            class="nwmd-ad__link"
            href="<?php echo esc_url($click_url); ?>"
            target="_blank"
            rel="sponsored noopener noreferrer"
        >
            <?php if ('' !== $image) : ?>
                <span class="nwmd-ad__media">
                    <?php echo wp_kses_post($image); ?>
                </span>
            <?php endif; ?>

            <span class="nwmd-ad__content">
                <span class="nwmd-ad__label">
                    <?php echo esc_html($label); ?>
                </span>

                <strong class="nwmd-ad__title">
                    <?php echo esc_html($ad->campaign_name); ?>
                </strong>

                <span class="nwmd-ad__advertiser">
                    <?php echo esc_html($ad->advertiser_name); ?>
                </span>

                <span class="nwmd-ad__action">
                    <?php echo esc_html__('Visit Sponsor', 'local-directory-framework'); ?>
                </span>
            </span>
        </a>
    </aside>
    <?php
}

/**
 * Register the public advertisement click route.
 */
function nwmd_directory_register_ad_click_rewrite() {

    add_rewrite_rule(
        '^sponsored-click/([0-9]+)/?$',
        'index.php?nwmd_ad_click=$matches[1]',
        'top'
    );
}

add_action(
    'init',
    'nwmd_directory_register_ad_click_rewrite',
    15
);

/**
 * Register the advertisement click query variable.
 *
 * @param array $query_vars Public query variables.
 *
 * @return array
 */
function nwmd_directory_register_ad_click_query_var($query_vars) {

    $query_vars[] = 'nwmd_ad_click';

    return $query_vars;
}

add_filter(
    'query_vars',
    'nwmd_directory_register_ad_click_query_var'
);

/**
 * Count a valid advertisement click and redirect to its destination.
 */
function nwmd_directory_handle_ad_click() {

    $ad_id = absint(
        get_query_var(
            'nwmd_ad_click'
        )
    );

    if ($ad_id < 1) {
        return;
    }

    $ad = nwmd_directory_get_ad(
        $ad_id
    );

    $destination_url = $ad
        ? wp_http_validate_url(
            (string) $ad->destination_url
        )
        : false;

    if (
        !$ad ||
        !$destination_url ||
        !nwmd_directory_ad_is_current($ad)
    ) {
        wp_safe_redirect(
            home_url('/'),
            302
        );
        exit;
    }

    global $wpdb;

    $table = $wpdb->prefix
        . 'nwmd_ads';

    $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$table}
            SET click_count = click_count + 1
            WHERE id = %d",
            $ad_id
        )
    );

    nocache_headers();

    wp_redirect(
        $destination_url,
        302,
        'Local Directory Framework'
    );
    exit;
}

add_action(
    'template_redirect',
    'nwmd_directory_handle_ad_click',
    1
);

/**
 * Mark ended active advertisements as expired at most once per hour.
 */
function nwmd_directory_maybe_expire_ads() {

    if (
        get_transient(
            'nwmd_directory_ads_expiry_checked'
        )
    ) {
        return;
    }

    set_transient(
        'nwmd_directory_ads_expiry_checked',
        '1',
        HOUR_IN_SECONDS
    );

    global $wpdb;

    $table = $wpdb->prefix
        . 'nwmd_ads';

    $current_time = current_time(
        'mysql'
    );

    $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$table}
            SET
                status = %s,
                updated_at = %s
            WHERE status = %s
                AND ends_at IS NOT NULL
                AND ends_at < %s",
            'expired',
            $current_time,
            'active',
            $current_time
        )
    );
}

add_action(
    'init',
    'nwmd_directory_maybe_expire_ads',
    25
);
