<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register the Advertising submenu.
 */
function nwmd_directory_register_advertising_admin_page() {

    add_submenu_page(
        'edit.php?post_type=nwmd_business',
        __(
            'Advertising',
            'local-directory-framework'
        ),
        __(
            'Advertising',
            'local-directory-framework'
        ),
        'manage_options',
        'nwmd-advertising',
        'nwmd_directory_render_advertising_admin_page'
    );
}

add_action(
    'admin_menu',
    'nwmd_directory_register_advertising_admin_page',
    40
);

/**
 * Load the media selector on the Advertising admin page.
 *
 * @param string $hook_suffix Current admin-page hook.
 */
function nwmd_directory_enqueue_advertising_admin_assets(
    $hook_suffix
) {

    if (
        'nwmd_business_page_nwmd-advertising'
        !== $hook_suffix
    ) {
        return;
    }

    wp_enqueue_media();

    $script_relative_path = 'assets/js/admin-advertising.js';
    $script_file_path = NWMD_DIRECTORY_PATH
        . $script_relative_path;

    $script_version = is_readable($script_file_path)
        ? (string) filemtime($script_file_path)
        : NWMD_DIRECTORY_VERSION;

    wp_enqueue_script(
        'nwmd-directory-advertising-admin',
        NWMD_DIRECTORY_URL
            . $script_relative_path,
        [],
        $script_version,
        true
    );
}

add_action(
    'admin_enqueue_scripts',
    'nwmd_directory_enqueue_advertising_admin_assets'
);

/**
 * Redirect to the Advertising page with a notice.
 *
 * @param string $notice Notice identifier.
 * @param int    $ad_id  Optional selected advertisement ID.
 */
function nwmd_directory_redirect_advertising_admin(
    $notice,
    $ad_id = 0
) {

    $arguments = [
        'post_type' => 'nwmd_business',
        'page' => 'nwmd-advertising',
        'nwmd_notice' => sanitize_key($notice),
    ];

    if ($ad_id > 0) {
        $arguments['ad_id'] = absint(
            $ad_id
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
 * Render one Advertising admin notice.
 */
function nwmd_directory_render_advertising_admin_notice() {

    $notice = isset($_GET['nwmd_notice'])
        ? sanitize_key(
            wp_unslash($_GET['nwmd_notice'])
        )
        : '';

    $messages = [
        'ad-created' => [
            'success',
            __(
                'Advertisement created.',
                'local-directory-framework'
            ),
        ],
        'ad-updated' => [
            'success',
            __(
                'Advertisement updated.',
                'local-directory-framework'
            ),
        ],
        'ad-archived' => [
            'success',
            __(
                'Advertisement archived.',
                'local-directory-framework'
            ),
        ],
        'ad-not-found' => [
            'error',
            __(
                'The advertisement could not be found.',
                'local-directory-framework'
            ),
        ],
        'invalid-ad-values' => [
            'error',
            __(
                'Enter valid advertising values.',
                'local-directory-framework'
            ),
        ],
        'invalid-destination-url' => [
            'error',
            __(
                'Enter a valid HTTP or HTTPS destination URL.',
                'local-directory-framework'
            ),
        ],
        'invalid-ad-dates' => [
            'error',
            __(
                'Choose a valid schedule. The end must be after the start, and an active advertisement cannot already be expired.',
                'local-directory-framework'
            ),
        ],
        'city-state-mismatch' => [
            'error',
            __(
                'The selected city does not belong to the selected state.',
                'local-directory-framework'
            ),
        ],
        'invalid-image' => [
            'error',
            __(
                'Choose a valid Media Library image.',
                'local-directory-framework'
            ),
        ],
        'ad-save-failed' => [
            'error',
            __(
                'The advertisement could not be saved.',
                'local-directory-framework'
            ),
        ],
        'ad-archive-failed' => [
            'error',
            __(
                'The advertisement could not be archived.',
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
            'notice notice-' . $type . ' is-dismissible'
        ); ?>"
    >
        <p><?php echo esc_html($message); ?></p>
    </div>
    <?php
}

/**
 * Limit one short advertising text field.
 *
 * @param string $value  Raw value.
 * @param int    $length Maximum characters.
 *
 * @return string
 */
function nwmd_directory_limit_ad_text(
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
 * Parse a datetime-local admin value into WordPress-local MySQL format.
 *
 * @param string $value Datetime-local value.
 *
 * @return string|null|false
 */
function nwmd_directory_parse_ad_admin_datetime($value) {

    $value = sanitize_text_field(
        $value
    );

    if ('' === $value) {
        return null;
    }

    $timezone = wp_timezone();

    $date = DateTimeImmutable::createFromFormat(
        '!Y-m-d\TH:i',
        $value,
        $timezone
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

    return $date->format(
        'Y-m-d H:i:s'
    );
}

/**
 * Format a stored MySQL datetime for a datetime-local input.
 *
 * @param string|null $value Stored datetime.
 *
 * @return string
 */
function nwmd_directory_format_ad_admin_datetime($value) {

    if (empty($value)) {
        return '';
    }

    $date = DateTimeImmutable::createFromFormat(
        'Y-m-d H:i:s',
        (string) $value,
        wp_timezone()
    );

    return $date instanceof DateTimeImmutable
        ? $date->format('Y-m-d\TH:i')
        : '';
}

/**
 * Return whether one selected term is valid.
 *
 * @param int    $term_id  Selected term ID.
 * @param string $taxonomy Taxonomy.
 *
 * @return bool
 */
function nwmd_directory_ad_term_is_valid(
    $term_id,
    $taxonomy
) {

    $term_id = absint($term_id);

    if (0 === $term_id) {
        return true;
    }

    $term = get_term(
        $term_id,
        $taxonomy
    );

    return $term instanceof WP_Term;
}

/**
 * Return one targeting summary for an advertisement.
 *
 * @param object $ad Advertisement record.
 *
 * @return string
 */
function nwmd_directory_get_ad_targeting_summary($ad) {

    $targeting = [
        'state_term_id' => [
            'taxonomy' => 'nwmd_state',
            'label' => __(
                'State',
                'local-directory-framework'
            ),
        ],
        'city_term_id' => [
            'taxonomy' => 'nwmd_city',
            'label' => __(
                'City',
                'local-directory-framework'
            ),
        ],
        'category_term_id' => [
            'taxonomy' => 'nwmd_category',
            'label' => __(
                'Category',
                'local-directory-framework'
            ),
        ],
        'specialty_term_id' => [
            'taxonomy' => 'nwmd_specialty',
            'label' => __(
                'Specialty',
                'local-directory-framework'
            ),
        ],
    ];

    $parts = [];

    foreach ($targeting as $key => $configuration) {
        $term_id = absint(
            $ad->{$key}
        );

        if (0 === $term_id) {
            continue;
        }

        $term = get_term(
            $term_id,
            $configuration['taxonomy']
        );

        if (!$term instanceof WP_Term) {
            continue;
        }

        $parts[] = sprintf(
            '%1$s: %2$s',
            $configuration['label'],
            $term->name
        );
    }

    return empty($parts)
        ? __(
            'All matching visitors',
            'local-directory-framework'
        )
        : implode(
            ' | ',
            $parts
        );
}

/**
 * Pause active advertisements with the exact same placement and targeting.
 *
 * @param array $data  Saved advertisement data.
 * @param int   $ad_id Advertisement being saved.
 */
function nwmd_directory_pause_exact_ad_conflicts(
    $data,
    $ad_id
) {

    if ('active' !== $data['status']) {
        return;
    }

    global $wpdb;

    $table = $wpdb->prefix
        . 'nwmd_ads';

    $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$table}
            SET
                status = %s,
                updated_at = %s
            WHERE status = %s
                AND placement = %s
                AND state_term_id = %d
                AND city_term_id = %d
                AND category_term_id = %d
                AND specialty_term_id = %d
                AND id <> %d",
            'paused',
            $data['updated_at'],
            'active',
            $data['placement'],
            $data['state_term_id'],
            $data['city_term_id'],
            $data['category_term_id'],
            $data['specialty_term_id'],
            absint($ad_id)
        )
    );
}

/**
 * Create or update one advertisement.
 */
function nwmd_directory_save_advertisement() {

    if (!current_user_can('manage_options')) {
        wp_die(
            esc_html__(
                'You are not allowed to manage advertising.',
                'local-directory-framework'
            )
        );
    }

    $ad_id = isset($_POST['ad_id'])
        ? absint($_POST['ad_id'])
        : 0;

    check_admin_referer(
        'nwmd_save_advertisement',
        'nwmd_advertisement_nonce'
    );

    if (
        $ad_id > 0 &&
        !nwmd_directory_get_ad($ad_id)
    ) {
        nwmd_directory_redirect_advertising_admin(
            'ad-not-found'
        );
    }

    $advertiser_name = isset($_POST['advertiser_name'])
        ? sanitize_text_field(
            wp_unslash($_POST['advertiser_name'])
        )
        : '';

    $campaign_name = isset($_POST['campaign_name'])
        ? sanitize_text_field(
            wp_unslash($_POST['campaign_name'])
        )
        : '';

    $placement = isset($_POST['placement'])
        ? sanitize_key(
            wp_unslash($_POST['placement'])
        )
        : '';

    $status = isset($_POST['status'])
        ? sanitize_key(
            wp_unslash($_POST['status'])
        )
        : '';

    $placements = nwmd_directory_get_ad_placement_choices();
    $statuses = nwmd_directory_get_ad_status_choices();

    $advertiser_name = nwmd_directory_limit_ad_text(
        $advertiser_name,
        255
    );

    $campaign_name = nwmd_directory_limit_ad_text(
        $campaign_name,
        255
    );

    if (
        '' === $advertiser_name ||
        '' === $campaign_name ||
        !isset($placements[$placement]) ||
        !isset($statuses[$status])
    ) {
        nwmd_directory_redirect_advertising_admin(
            'invalid-ad-values',
            $ad_id
        );
    }

    $destination_url = isset($_POST['destination_url'])
        ? esc_url_raw(
            wp_unslash($_POST['destination_url'])
        )
        : '';

    $validated_destination_url = wp_http_validate_url(
        $destination_url
    );

    $scheme = wp_parse_url(
        $destination_url,
        PHP_URL_SCHEME
    );

    if (
        !$validated_destination_url ||
        !in_array(
            strtolower((string) $scheme),
            [
                'http',
                'https',
            ],
            true
        )
    ) {
        nwmd_directory_redirect_advertising_admin(
            'invalid-destination-url',
            $ad_id
        );
    }

    $image_attachment_id = isset(
        $_POST['image_attachment_id']
    )
        ? absint($_POST['image_attachment_id'])
        : 0;

    if ($image_attachment_id > 0) {
        $attachment = get_post(
            $image_attachment_id
        );

        if (
            !($attachment instanceof WP_Post) ||
            'attachment' !== $attachment->post_type ||
            !wp_attachment_is_image($image_attachment_id)
        ) {
            nwmd_directory_redirect_advertising_admin(
                'invalid-image',
                $ad_id
            );
        }
    }

    $term_fields = [
        'state_term_id' => 'nwmd_state',
        'city_term_id' => 'nwmd_city',
        'category_term_id' => 'nwmd_category',
        'specialty_term_id' => 'nwmd_specialty',
    ];

    $term_values = [];

    foreach ($term_fields as $field => $taxonomy) {
        $term_values[$field] = isset($_POST[$field])
            ? absint($_POST[$field])
            : 0;

        if (
            !nwmd_directory_ad_term_is_valid(
                $term_values[$field],
                $taxonomy
            )
        ) {
            nwmd_directory_redirect_advertising_admin(
                'invalid-ad-values',
                $ad_id
            );
        }
    }

    if (
        $term_values['state_term_id'] > 0 &&
        $term_values['city_term_id'] > 0
    ) {
        $city_state_term_id = absint(
            get_term_meta(
                $term_values['city_term_id'],
                'nwmd_state_term_id',
                true
            )
        );

        if (
            $city_state_term_id !==
            $term_values['state_term_id']
        ) {
            nwmd_directory_redirect_advertising_admin(
                'city-state-mismatch',
                $ad_id
            );
        }
    }

    if (
        !nwmd_directory_ad_specialty_is_guided(
            $term_values['specialty_term_id']
        )
    ) {
        nwmd_directory_redirect_advertising_admin(
            'invalid-ad-values',
            $ad_id
        );
    }

    if (
        $term_values['category_term_id'] > 0 &&
        $term_values['specialty_term_id'] > 0
    ) {
        $specialty_category_term_id = absint(
            get_term_meta(
                $term_values['specialty_term_id'],
                'nwmd_category_term_id',
                true
            )
        );

        if (
            $specialty_category_term_id !==
            $term_values['category_term_id']
        ) {
            nwmd_directory_redirect_advertising_admin(
                'invalid-ad-values',
                $ad_id
            );
        }
    }

    $starts_at = isset($_POST['starts_at'])
        ? nwmd_directory_parse_ad_admin_datetime(
            wp_unslash($_POST['starts_at'])
        )
        : null;

    $ends_at = isset($_POST['ends_at'])
        ? nwmd_directory_parse_ad_admin_datetime(
            wp_unslash($_POST['ends_at'])
        )
        : null;

    if (
        false === $starts_at ||
        false === $ends_at ||
        (
            null !== $starts_at &&
            null !== $ends_at &&
            $ends_at <= $starts_at
        ) ||
        (
            'active' === $status &&
            null !== $ends_at &&
            $ends_at < current_time('mysql')
        )
    ) {
        nwmd_directory_redirect_advertising_admin(
            'invalid-ad-dates',
            $ad_id
        );
    }

    $current_time = current_time(
        'mysql'
    );

    $data = [
        'advertiser_name' => $advertiser_name,
        'campaign_name' => $campaign_name,
        'placement' => $placement,
        'image_attachment_id' => $image_attachment_id,
        'destination_url' => $validated_destination_url,
        'state_term_id' => $term_values['state_term_id'],
        'city_term_id' => $term_values['city_term_id'],
        'category_term_id' => $term_values['category_term_id'],
        'specialty_term_id' => $term_values['specialty_term_id'],
        'starts_at' => $starts_at,
        'ends_at' => $ends_at,
        'status' => $status,
        'updated_at' => $current_time,
    ];

    $formats = [
        '%s',
        '%s',
        '%s',
        '%d',
        '%s',
        '%d',
        '%d',
        '%d',
        '%d',
        '%s',
        '%s',
        '%s',
        '%s',
    ];

    global $wpdb;

    $table = $wpdb->prefix
        . 'nwmd_ads';

    if ($ad_id > 0) {
        $saved = $wpdb->update(
            $table,
            $data,
            [
                'id' => $ad_id,
            ],
            $formats,
            [
                '%d',
            ]
        );
    } else {
        $data['impression_count'] = 0;
        $data['click_count'] = 0;
        $data['created_at'] = $current_time;

        $formats[] = '%d';
        $formats[] = '%d';
        $formats[] = '%s';

        $saved = $wpdb->insert(
            $table,
            $data,
            $formats
        );

        if (false !== $saved) {
            $ad_id = absint(
                $wpdb->insert_id
            );
        }
    }

    if (false === $saved) {
        nwmd_directory_redirect_advertising_admin(
            'ad-save-failed',
            $ad_id
        );
    }

    nwmd_directory_pause_exact_ad_conflicts(
        $data,
        $ad_id
    );

    delete_transient(
        'nwmd_directory_ads_expiry_checked'
    );

    nwmd_directory_redirect_advertising_admin(
        $ad_id > 0 && isset($_POST['ad_id']) && absint($_POST['ad_id']) > 0
            ? 'ad-updated'
            : 'ad-created',
        $ad_id
    );
}

add_action(
    'admin_post_nwmd_save_advertisement',
    'nwmd_directory_save_advertisement'
);

/**
 * Archive one advertisement.
 */
function nwmd_directory_archive_advertisement() {

    if (!current_user_can('manage_options')) {
        wp_die(
            esc_html__(
                'You are not allowed to manage advertising.',
                'local-directory-framework'
            )
        );
    }

    $ad_id = isset($_GET['ad_id'])
        ? absint($_GET['ad_id'])
        : 0;

    check_admin_referer(
        'nwmd_archive_advertisement_' . $ad_id
    );

    $ad = nwmd_directory_get_ad(
        $ad_id
    );

    if (!$ad) {
        nwmd_directory_redirect_advertising_admin(
            'ad-not-found'
        );
    }

    global $wpdb;

    $table = $wpdb->prefix
        . 'nwmd_ads';

    $updated = $wpdb->update(
        $table,
        [
            'status' => 'archived',
            'updated_at' => current_time('mysql'),
        ],
        [
            'id' => $ad_id,
        ],
        [
            '%s',
            '%s',
        ],
        [
            '%d',
        ]
    );

    if (false === $updated) {
        nwmd_directory_redirect_advertising_admin(
            'ad-archive-failed',
            $ad_id
        );
    }

    nwmd_directory_redirect_advertising_admin(
        'ad-archived'
    );
}

add_action(
    'admin_post_nwmd_archive_advertisement',
    'nwmd_directory_archive_advertisement'
);

/**
 * Return selectable taxonomy terms for the Advertising admin form.
 *
 * @param string $taxonomy Taxonomy name.
 *
 * @return array
 */
function nwmd_directory_get_advertising_admin_terms($taxonomy) {

    $terms = get_terms(
        [
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ]
    );

    return is_wp_error($terms)
        ? []
        : $terms;
}

/**
 * Return guided specialty slugs and their category slugs.
 *
 * @return array
 */
function nwmd_directory_get_guided_specialty_slug_map() {

    static $slug_map = null;

    if (is_array($slug_map)) {
        return $slug_map;
    }

    $slug_map = [];

    foreach (
        nwmd_directory_get_launch_specialties()
        as $category_slug => $specialties
    ) {
        $category_slug = sanitize_title(
            $category_slug
        );

        foreach ($specialties as $specialty) {
            $specialty_slug = isset($specialty['slug'])
                ? sanitize_title($specialty['slug'])
                : '';

            if ('' === $specialty_slug) {
                continue;
            }

            $slug_map[$specialty_slug] =
                $category_slug;
        }
    }

    return $slug_map;
}

/**
 * Return whether one specialty belongs to the guided flow.
 *
 * @param int $term_id Specialty term ID.
 *
 * @return bool
 */
function nwmd_directory_ad_specialty_is_guided($term_id) {

    $term_id = absint($term_id);

    if (0 === $term_id) {
        return true;
    }

    $specialty = get_term(
        $term_id,
        'nwmd_specialty'
    );

    if (
        !$specialty instanceof WP_Term ||
        is_wp_error($specialty)
    ) {
        return false;
    }

    $slug_map =
        nwmd_directory_get_guided_specialty_slug_map();

    if (!isset($slug_map[$specialty->slug])) {
        return false;
    }

    $category_term_id = absint(
        get_term_meta(
            $specialty->term_id,
            'nwmd_category_term_id',
            true
        )
    );

    if ($category_term_id < 1) {
        return false;
    }

    $category = get_term(
        $category_term_id,
        'nwmd_category'
    );

    return (
        $category instanceof WP_Term &&
        !is_wp_error($category) &&
        $category->slug === $slug_map[$specialty->slug]
    );
}

/**
 * Return only guided-flow specialties for ad targeting.
 *
 * Each result includes its guided parent category name.
 *
 * @return array
 */
function nwmd_directory_get_guided_advertising_specialties() {

    $terms =
        nwmd_directory_get_advertising_admin_terms(
            'nwmd_specialty'
        );

    $specialties = [];

    foreach ($terms as $term) {
        if (
            !$term instanceof WP_Term ||
            !nwmd_directory_ad_specialty_is_guided(
                $term->term_id
            )
        ) {
            continue;
        }

        $category_term_id = absint(
            get_term_meta(
                $term->term_id,
                'nwmd_category_term_id',
                true
            )
        );

        $category = get_term(
            $category_term_id,
            'nwmd_category'
        );

        if (
            !$category instanceof WP_Term ||
            is_wp_error($category)
        ) {
            continue;
        }

        $specialties[] = (object) [
            'term_id' => absint($term->term_id),
            'name' => sanitize_text_field($term->name),
            'slug' => sanitize_title($term->slug),
            'category_name' =>
                sanitize_text_field($category->name),
        ];
    }

    usort(
        $specialties,
        static function ($first, $second) {

            $category_comparison = strcasecmp(
                $first->category_name,
                $second->category_name
            );

            if (0 !== $category_comparison) {
                return $category_comparison;
            }

            return strcasecmp(
                $first->name,
                $second->name
            );
        }
    );

    return $specialties;
}

/**
 * Render the Advertising admin page.
 */
function nwmd_directory_render_advertising_admin_page() {

    if (!current_user_can('manage_options')) {
        wp_die(
            esc_html__(
                'You are not allowed to manage advertising.',
                'local-directory-framework'
            )
        );
    }

    global $wpdb;

    $table = $wpdb->prefix
        . 'nwmd_ads';

    $ads = $wpdb->get_results(
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
        ORDER BY created_at DESC, id DESC
        LIMIT 500"
    );

    $selected_ad_id = isset($_GET['ad_id'])
        ? absint($_GET['ad_id'])
        : 0;

    $selected_ad = $selected_ad_id > 0
        ? nwmd_directory_get_ad(
            $selected_ad_id
        )
        : null;

    $placements = nwmd_directory_get_ad_placement_choices();
    $statuses = nwmd_directory_get_ad_status_choices();

    $states = nwmd_directory_get_advertising_admin_terms(
        'nwmd_state'
    );

    $cities = nwmd_directory_get_advertising_admin_terms(
        'nwmd_city'
    );

    $categories = nwmd_directory_get_advertising_admin_terms(
        'nwmd_category'
    );

    $specialties =
        nwmd_directory_get_guided_advertising_specialties();

    $form_ad = $selected_ad ?: (object) [
        'id' => 0,
        'advertiser_name' => '',
        'campaign_name' => '',
        'placement' => 'results_sponsored',
        'image_attachment_id' => 0,
        'destination_url' => '',
        'state_term_id' => 0,
        'city_term_id' => 0,
        'category_term_id' => 0,
        'specialty_term_id' => 0,
        'starts_at' => null,
        'ends_at' => null,
        'status' => 'draft',
    ];

    $selected_specialty_is_guided =
        nwmd_directory_ad_specialty_is_guided(
            $form_ad->specialty_term_id
        );

    $image_preview = $form_ad->image_attachment_id > 0
        ? wp_get_attachment_image(
            absint($form_ad->image_attachment_id),
            'medium',
            false,
            [
                'style' => 'display:block;max-width:320px;height:auto;margin-bottom:12px;',
            ]
        )
        : '';

    $new_ad_url = add_query_arg(
        [
            'post_type' => 'nwmd_business',
            'page' => 'nwmd-advertising',
        ],
        admin_url('edit.php')
    );
    ?>
    <div class="wrap">
        <h1>
            <?php echo esc_html__('Advertising', 'local-directory-framework'); ?>
        </h1>

        <?php nwmd_directory_render_advertising_admin_notice(); ?>

        <p>
            <?php
            echo esc_html__(
                'Manage direct sponsored placements separately from editorial rankings. The most specific active campaign wins for each placement and visitor selection.',
                'local-directory-framework'
            );
            ?>
        </p>

        <?php if ($selected_ad_id > 0 && !$selected_ad) : ?>
            <div class="notice notice-error inline">
                <p>
                    <?php echo esc_html__('The selected advertisement could not be found.', 'local-directory-framework'); ?>
                </p>
            </div>
        <?php endif; ?>

        <h2>
            <?php
            echo esc_html(
                $selected_ad
                    ? __('Edit Advertisement', 'local-directory-framework')
                    : __('Add Advertisement', 'local-directory-framework')
            );
            ?>
        </h2>

        <form
            action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
            method="post"
        >
            <input
                type="hidden"
                name="action"
                value="nwmd_save_advertisement"
            >

            <input
                type="hidden"
                name="ad_id"
                value="<?php echo esc_attr($form_ad->id); ?>"
            >

            <?php
            wp_nonce_field(
                'nwmd_save_advertisement',
                'nwmd_advertisement_nonce'
            );
            ?>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="nwmd_advertiser_name">
                            <?php echo esc_html__('Advertiser Name', 'local-directory-framework'); ?>
                        </label>
                    </th>
                    <td>
                        <input
                            type="text"
                            id="nwmd_advertiser_name"
                            name="advertiser_name"
                            class="regular-text"
                            maxlength="255"
                            value="<?php echo esc_attr($form_ad->advertiser_name); ?>"
                            required
                        >
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="nwmd_campaign_name">
                            <?php echo esc_html__('Campaign / Public Headline', 'local-directory-framework'); ?>
                        </label>
                    </th>
                    <td>
                        <input
                            type="text"
                            id="nwmd_campaign_name"
                            name="campaign_name"
                            class="regular-text"
                            maxlength="255"
                            value="<?php echo esc_attr($form_ad->campaign_name); ?>"
                            required
                        >
                        <p class="description">
                            <?php
                            echo esc_html__(
                                'This headline appears in the public sponsored placement.',
                                'local-directory-framework'
                            );
                            ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="nwmd_ad_placement">
                            <?php echo esc_html__('Placement', 'local-directory-framework'); ?>
                        </label>
                    </th>
                    <td>
                        <select
                            id="nwmd_ad_placement"
                            name="placement"
                            required
                        >
                            <?php foreach ($placements as $value => $label) : ?>
                                <option
                                    value="<?php echo esc_attr($value); ?>"
                                    <?php selected($form_ad->placement, $value); ?>
                                >
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <?php echo esc_html__('Creative Image', 'local-directory-framework'); ?>
                    </th>
                    <td>
                        <input
                            type="hidden"
                            id="nwmd_ad_image_attachment_id"
                            name="image_attachment_id"
                            value="<?php echo esc_attr($form_ad->image_attachment_id); ?>"
                        >

                        <div id="nwmd_ad_image_preview">
                            <?php echo wp_kses_post($image_preview); ?>
                        </div>

                        <button
                            type="button"
                            class="button"
                            id="nwmd_ad_choose_image"
                        >
                            <?php echo esc_html__('Choose Image', 'local-directory-framework'); ?>
                        </button>

                        <button
                            type="button"
                            class="button"
                            id="nwmd_ad_remove_image"
                            <?php if ($form_ad->image_attachment_id < 1) : ?>
                                hidden
                                style="display: none;"
                            <?php endif; ?>
                        >
                            <?php echo esc_html__('Remove Image', 'local-directory-framework'); ?>
                        </button>

                        <p class="description">
                            <?php
                            echo esc_html__(
                                'Optional. Text remains visible when no image is selected.',
                                'local-directory-framework'
                            );
                            ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="nwmd_ad_destination_url">
                            <?php echo esc_html__('Destination URL', 'local-directory-framework'); ?>
                        </label>
                    </th>
                    <td>
                        <input
                            type="url"
                            id="nwmd_ad_destination_url"
                            name="destination_url"
                            class="large-text"
                            value="<?php echo esc_attr($form_ad->destination_url); ?>"
                            placeholder="https://example.com/"
                            required
                        >
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="nwmd_ad_state_term_id">
                            <?php echo esc_html__('State Target', 'local-directory-framework'); ?>
                        </label>
                    </th>
                    <td>
                        <select
                            id="nwmd_ad_state_term_id"
                            name="state_term_id"
                        >
                            <option value="0">
                                <?php echo esc_html__('All states', 'local-directory-framework'); ?>
                            </option>
                            <?php foreach ($states as $state) : ?>
                                <option
                                    value="<?php echo esc_attr($state->term_id); ?>"
                                    <?php selected($form_ad->state_term_id, $state->term_id); ?>
                                >
                                    <?php echo esc_html($state->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="nwmd_ad_city_term_id">
                            <?php echo esc_html__('City Target', 'local-directory-framework'); ?>
                        </label>
                    </th>
                    <td>
                        <select
                            id="nwmd_ad_city_term_id"
                            name="city_term_id"
                        >
                            <option value="0">
                                <?php echo esc_html__('All cities', 'local-directory-framework'); ?>
                            </option>
                            <?php foreach ($cities as $city) : ?>
                                <option
                                    value="<?php echo esc_attr($city->term_id); ?>"
                                    <?php selected($form_ad->city_term_id, $city->term_id); ?>
                                >
                                    <?php echo esc_html($city->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="nwmd_ad_category_term_id">
                            <?php echo esc_html__('Category Target', 'local-directory-framework'); ?>
                        </label>
                    </th>
                    <td>
                        <select
                            id="nwmd_ad_category_term_id"
                            name="category_term_id"
                        >
                            <option value="0">
                                <?php echo esc_html__('All categories', 'local-directory-framework'); ?>
                            </option>
                            <?php foreach ($categories as $category) : ?>
                                <option
                                    value="<?php echo esc_attr($category->term_id); ?>"
                                    <?php selected($form_ad->category_term_id, $category->term_id); ?>
                                >
                                    <?php echo esc_html($category->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="nwmd_ad_specialty_term_id">
                            <?php echo esc_html__('Specialty Target', 'local-directory-framework'); ?>
                        </label>
                    </th>
                    <td>
                        <select
                            id="nwmd_ad_specialty_term_id"
                            name="specialty_term_id"
                            required
                        >
                            <?php if (
                                absint($form_ad->specialty_term_id) > 0 &&
                                !$selected_specialty_is_guided
                            ) : ?>
                                <option
                                    value=""
                                    selected
                                    disabled
                                >
                                    <?php
                                    echo esc_html__(
                                        'Legacy specialty — choose a valid target',
                                        'local-directory-framework'
                                    );
                                    ?>
                                </option>
                            <?php endif; ?>

                            <option
                                value="0"
                                <?php selected(
                                    absint($form_ad->specialty_term_id),
                                    0
                                ); ?>
                            >
                                <?php
                                echo esc_html__(
                                    'All specialties',
                                    'local-directory-framework'
                                );
                                ?>
                            </option>

                            <?php foreach ($specialties as $specialty) : ?>
                                <option
                                    value="<?php echo esc_attr(
                                        $specialty->term_id
                                    ); ?>"
                                    <?php selected(
                                        $form_ad->specialty_term_id,
                                        $specialty->term_id
                                    ); ?>
                                >
                                    <?php
                                    echo esc_html(
                                        $specialty->category_name
                                        . ' — '
                                        . $specialty->name
                                    );
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <p class="description">
                            <?php
                            echo esc_html__(
                                'Only guided-flow specialties are shown. Choose a specialty from the same category target, or leave All specialties selected.',
                                'local-directory-framework'
                            );
                            ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="nwmd_ad_starts_at">
                            <?php echo esc_html__('Starts At', 'local-directory-framework'); ?>
                        </label>
                    </th>
                    <td>
                        <input
                            type="datetime-local"
                            id="nwmd_ad_starts_at"
                            name="starts_at"
                            value="<?php echo esc_attr(
                                nwmd_directory_format_ad_admin_datetime(
                                    $form_ad->starts_at
                                )
                            ); ?>"
                        >
                        <p class="description">
                            <?php echo esc_html__('Leave blank to start immediately when Active.', 'local-directory-framework'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="nwmd_ad_ends_at">
                            <?php echo esc_html__('Ends At', 'local-directory-framework'); ?>
                        </label>
                    </th>
                    <td>
                        <input
                            type="datetime-local"
                            id="nwmd_ad_ends_at"
                            name="ends_at"
                            value="<?php echo esc_attr(
                                nwmd_directory_format_ad_admin_datetime(
                                    $form_ad->ends_at
                                )
                            ); ?>"
                        >
                        <p class="description">
                            <?php echo esc_html__('Leave blank for no scheduled end.', 'local-directory-framework'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="nwmd_ad_status">
                            <?php echo esc_html__('Status', 'local-directory-framework'); ?>
                        </label>
                    </th>
                    <td>
                        <select
                            id="nwmd_ad_status"
                            name="status"
                            required
                        >
                            <?php foreach ($statuses as $value => $label) : ?>
                                <option
                                    value="<?php echo esc_attr($value); ?>"
                                    <?php selected($form_ad->status, $value); ?>
                                >
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>

            <?php
            submit_button(
                $selected_ad
                    ? __('Update Advertisement', 'local-directory-framework')
                    : __('Create Advertisement', 'local-directory-framework')
            );
            ?>

            <?php if ($selected_ad) : ?>
                <a
                    class="button"
                    href="<?php echo esc_url($new_ad_url); ?>"
                >
                    <?php echo esc_html__('Cancel Edit', 'local-directory-framework'); ?>
                </a>
            <?php endif; ?>
        </form>

        <hr>

        <h2>
            <?php echo esc_html__('Advertisements', 'local-directory-framework'); ?>
        </h2>

        <?php if (empty($ads)) : ?>
            <p>
                <?php echo esc_html__('No advertisements have been created.', 'local-directory-framework'); ?>
            </p>
        <?php else : ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Campaign', 'local-directory-framework'); ?></th>
                        <th><?php echo esc_html__('Placement', 'local-directory-framework'); ?></th>
                        <th><?php echo esc_html__('Targeting', 'local-directory-framework'); ?></th>
                        <th><?php echo esc_html__('Schedule', 'local-directory-framework'); ?></th>
                        <th><?php echo esc_html__('Status', 'local-directory-framework'); ?></th>
                        <th><?php echo esc_html__('Impressions', 'local-directory-framework'); ?></th>
                        <th><?php echo esc_html__('Clicks', 'local-directory-framework'); ?></th>
                        <th><?php echo esc_html__('Actions', 'local-directory-framework'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ads as $ad) : ?>
                        <?php
                        $edit_url = add_query_arg(
                            [
                                'post_type' => 'nwmd_business',
                                'page' => 'nwmd-advertising',
                                'ad_id' => absint($ad->id),
                            ],
                            admin_url('edit.php')
                        );

                        $archive_url = wp_nonce_url(
                            add_query_arg(
                                [
                                    'action' => 'nwmd_archive_advertisement',
                                    'ad_id' => absint($ad->id),
                                ],
                                admin_url('admin-post.php')
                            ),
                            'nwmd_archive_advertisement_' . absint($ad->id)
                        );

                        $schedule_parts = [];

                        if (!empty($ad->starts_at)) {
                            $schedule_parts[] = sprintf(
                                /* translators: %s: Start datetime. */
                                __('Starts %s', 'local-directory-framework'),
                                $ad->starts_at
                            );
                        }

                        if (!empty($ad->ends_at)) {
                            $schedule_parts[] = sprintf(
                                /* translators: %s: End datetime. */
                                __('Ends %s', 'local-directory-framework'),
                                $ad->ends_at
                            );
                        }
                        ?>
                        <tr>
                            <td>
                                <strong>
                                    <?php echo esc_html($ad->campaign_name); ?>
                                </strong>
                                <br>
                                <?php echo esc_html($ad->advertiser_name); ?>
                            </td>
                            <td>
                                <?php
                                echo esc_html(
                                    $placements[$ad->placement]
                                        ?? $ad->placement
                                );
                                ?>
                            </td>
                            <td>
                                <?php
                                echo esc_html(
                                    nwmd_directory_get_ad_targeting_summary(
                                        $ad
                                    )
                                );
                                ?>
                            </td>
                            <td>
                                <?php
                                echo esc_html(
                                    empty($schedule_parts)
                                        ? __('Always', 'local-directory-framework')
                                        : implode(' | ', $schedule_parts)
                                );
                                ?>
                            </td>
                            <td>
                                <?php
                                echo esc_html(
                                    $statuses[$ad->status]
                                        ?? $ad->status
                                );
                                ?>
                            </td>
                            <td>
                                <?php echo esc_html(number_format_i18n($ad->impression_count)); ?>
                            </td>
                            <td>
                                <?php echo esc_html(number_format_i18n($ad->click_count)); ?>
                            </td>
                            <td>
                                <a href="<?php echo esc_url($edit_url); ?>">
                                    <?php echo esc_html__('Edit', 'local-directory-framework'); ?>
                                </a>

                                <?php if ('archived' !== $ad->status) : ?>
                                    &nbsp;|&nbsp;
                                    <a
                                        class="submitdelete"
                                        href="<?php echo esc_url($archive_url); ?>"
                                        onclick="return confirm('<?php echo esc_js(__('Archive this advertisement?', 'local-directory-framework')); ?>');"
                                    >
                                        <?php echo esc_html__('Archive', 'local-directory-framework'); ?>
                                    </a>
                                <?php else : ?>
                                    &nbsp;|&nbsp;
                                    <?php echo esc_html__('Archived', 'local-directory-framework'); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}
