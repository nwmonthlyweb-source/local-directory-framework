<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register the business details meta box.
 */
function nwmd_directory_add_business_meta_box() {

    add_meta_box(
        'nwmd_business_details',
        'Business Details',
        'nwmd_directory_render_business_meta_box',
        'nwmd_business',
        'normal',
        'high'
    );
}

add_action(
    'add_meta_boxes_nwmd_business',
    'nwmd_directory_add_business_meta_box'
);

/**
 * Render a text field used by the business meta box.
 *
 * @param int    $post_id Post ID.
 * @param string $key     Meta key.
 * @param string $label   Field label.
 * @param string $type    Input type.
 */
function nwmd_directory_render_business_field(
    $post_id,
    $key,
    $label,
    $type = 'text'
) {

    $value = get_post_meta(
        $post_id,
        $key,
        true
    );

    if (
        'datetime-local' === $type &&
        !empty($value)
    ) {
        $value = substr(
            str_replace(' ', 'T', $value),
            0,
            16
        );
    }

    ?>
    <tr>
        <th scope="row">
            <label for="<?php echo esc_attr($key); ?>">
                <?php echo esc_html($label); ?>
            </label>
        </th>
        <td>
            <input
                type="<?php echo esc_attr($type); ?>"
                id="<?php echo esc_attr($key); ?>"
                name="<?php echo esc_attr($key); ?>"
                value="<?php echo esc_attr($value); ?>"
                class="regular-text"
            >
        </td>
    </tr>
    <?php
}

/**
 * Render a select field used by the business meta box.
 *
 * @param int    $post_id Post ID.
 * @param string $key     Meta key.
 * @param string $label   Field label.
 * @param array  $options Allowed options.
 */
function nwmd_directory_render_business_select(
    $post_id,
    $key,
    $label,
    $options
) {

    $value = get_post_meta(
        $post_id,
        $key,
        true
    );

    ?>
    <tr>
        <th scope="row">
            <label for="<?php echo esc_attr($key); ?>">
                <?php echo esc_html($label); ?>
            </label>
        </th>
        <td>
            <select
                id="<?php echo esc_attr($key); ?>"
                name="<?php echo esc_attr($key); ?>"
            >
                <?php foreach ($options as $option_value => $option_label) : ?>
                    <option
                        value="<?php echo esc_attr($option_value); ?>"
                        <?php selected($value, $option_value); ?>
                    >
                        <?php echo esc_html($option_label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
    </tr>
    <?php
}

/**
 * Render the business details meta box.
 *
 * @param WP_Post $post Current post.
 */
function nwmd_directory_render_business_meta_box($post) {

    wp_nonce_field(
        'nwmd_save_business_details',
        'nwmd_business_details_nonce'
    );

    $ranking_eligible = (bool) get_post_meta(
        $post->ID,
        'nwmd_ranking_eligible',
        true
    );

    ?>
    <table class="form-table" role="presentation">
        <tbody>
            <?php
            nwmd_directory_render_business_field(
                $post->ID,
                'nwmd_legal_name',
                'Legal Business Name'
            );

            nwmd_directory_render_business_field(
                $post->ID,
                'nwmd_website_url',
                'Website',
                'url'
            );

            nwmd_directory_render_business_field(
                $post->ID,
                'nwmd_public_email',
                'Public Email',
                'email'
            );

            nwmd_directory_render_business_field(
                $post->ID,
                'nwmd_public_phone',
                'Public Phone',
                'tel'
            );

            nwmd_directory_render_business_field(
                $post->ID,
                'nwmd_street_address',
                'Street Address'
            );

            nwmd_directory_render_business_field(
                $post->ID,
                'nwmd_postal_code',
                'ZIP Code'
            );

            nwmd_directory_render_business_field(
                $post->ID,
                'nwmd_registration_number',
                'Registration Number'
            );

            nwmd_directory_render_business_field(
                $post->ID,
                'nwmd_license_number',
                'License Number'
            );

            nwmd_directory_render_business_field(
                $post->ID,
                'nwmd_license_status',
                'License Status'
            );

            nwmd_directory_render_business_select(
                $post->ID,
                'nwmd_verification_status',
                'Verification Status',
                [
                    'unverified' => 'Unverified',
                    'pending'    => 'Pending',
                    'verified'   => 'Verified',
                    'rejected'   => 'Rejected',
                ]
            );

            nwmd_directory_render_business_select(
                $post->ID,
                'nwmd_claimed_status',
                'Claimed Status',
                [
                    'unclaimed' => 'Unclaimed',
                    'pending'   => 'Pending',
                    'claimed'   => 'Claimed',
                    'rejected'  => 'Rejected',
                ]
            );

            nwmd_directory_render_business_field(
                $post->ID,
                'nwmd_last_verified_at',
                'Last Verified',
                'datetime-local'
            );
            ?>
            <tr>
                <th scope="row">Ranking Eligibility</th>
                <td>
                    <label for="nwmd_ranking_eligible">
                        <input
                            type="checkbox"
                            id="nwmd_ranking_eligible"
                            name="nwmd_ranking_eligible"
                            value="1"
                            <?php checked($ranking_eligible); ?>
                        >
                        Eligible for editorial rankings
                    </label>
                </td>
            </tr>
        </tbody>
    </table>
    <?php
}

/**
 * Save business details.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Current post.
 */
function nwmd_directory_save_business_details(
    $post_id,
    $post
) {

    if (
        !isset($_POST['nwmd_business_details_nonce']) ||
        !wp_verify_nonce(
            sanitize_text_field(
                wp_unslash(
                    $_POST['nwmd_business_details_nonce']
                )
            ),
            'nwmd_save_business_details'
        )
    ) {
        return;
    }

    if (
        defined('DOING_AUTOSAVE') &&
        DOING_AUTOSAVE
    ) {
        return;
    }

    if (
        'nwmd_business' !== $post->post_type ||
        !current_user_can('edit_post', $post_id)
    ) {
        return;
    }

    $text_fields = [
        'nwmd_legal_name',
        'nwmd_public_phone',
        'nwmd_street_address',
        'nwmd_postal_code',
        'nwmd_registration_number',
        'nwmd_license_number',
        'nwmd_license_status',
    ];

    foreach ($text_fields as $field) {

        $value = isset($_POST[$field])
            ? sanitize_text_field(
                wp_unslash($_POST[$field])
            )
            : '';

        update_post_meta(
            $post_id,
            $field,
            $value
        );
    }

    $website_url = isset($_POST['nwmd_website_url'])
        ? esc_url_raw(
            wp_unslash($_POST['nwmd_website_url'])
        )
        : '';

    update_post_meta(
        $post_id,
        'nwmd_website_url',
        $website_url
    );

    $public_email = isset($_POST['nwmd_public_email'])
        ? sanitize_email(
            wp_unslash($_POST['nwmd_public_email'])
        )
        : '';

    update_post_meta(
        $post_id,
        'nwmd_public_email',
        $public_email
    );

    $verification_statuses = [
        'unverified',
        'pending',
        'verified',
        'rejected',
    ];

    $verification_status = isset($_POST['nwmd_verification_status'])
        ? sanitize_key(
            wp_unslash($_POST['nwmd_verification_status'])
        )
        : 'unverified';

    if (
        !in_array(
            $verification_status,
            $verification_statuses,
            true
        )
    ) {
        $verification_status = 'unverified';
    }

    update_post_meta(
        $post_id,
        'nwmd_verification_status',
        $verification_status
    );

    $claimed_statuses = [
        'unclaimed',
        'pending',
        'claimed',
        'rejected',
    ];

    $claimed_status = isset($_POST['nwmd_claimed_status'])
        ? sanitize_key(
            wp_unslash($_POST['nwmd_claimed_status'])
        )
        : 'unclaimed';

    if (
        !in_array(
            $claimed_status,
            $claimed_statuses,
            true
        )
    ) {
        $claimed_status = 'unclaimed';
    }

    update_post_meta(
        $post_id,
        'nwmd_claimed_status',
        $claimed_status
    );

    $last_verified_at = '';

    if (isset($_POST['nwmd_last_verified_at'])) {

        $submitted_date = sanitize_text_field(
            wp_unslash(
                $_POST['nwmd_last_verified_at']
            )
        );

        if (
            preg_match(
                '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/',
                $submitted_date
            )
        ) {
            $last_verified_at =
                str_replace('T', ' ', $submitted_date) .
                ':00';
        }
    }

    update_post_meta(
        $post_id,
        'nwmd_last_verified_at',
        $last_verified_at
    );

    update_post_meta(
        $post_id,
        'nwmd_ranking_eligible',
        isset($_POST['nwmd_ranking_eligible'])
            ? 1
            : 0
    );

}

add_action(
    'save_post_nwmd_business',
    'nwmd_directory_save_business_details',
    20,
    2
);

/**
 * Synchronize the index after every normal business save.
 *
 * This keeps title, status, and programmatic changes synchronized even
 * when the Business Details meta-box nonce is not submitted.
 *
 * @param int     $post_id Business post ID.
 * @param WP_Post $post    Current post.
 */
function nwmd_directory_sync_business_after_save(
    $post_id,
    $post
) {

    if (
        !$post instanceof WP_Post ||
        'nwmd_business' !== $post->post_type ||
        wp_is_post_revision($post_id) ||
        wp_is_post_autosave($post_id)
    ) {
        return;
    }

    nwmd_directory_sync_business_index($post_id);
}

add_action(
    'save_post_nwmd_business',
    'nwmd_directory_sync_business_after_save',
    30,
    2
);

/**
 * Synchronize a business with the indexed directory table.
 *
 * @param int $post_id Business post ID.
 */
function nwmd_directory_sync_business_index($post_id) {

    global $wpdb;

    $post = get_post($post_id);

    if (
        !$post instanceof WP_Post ||
        'nwmd_business' !== $post->post_type
    ) {
        return;
    }

    $table_name = $wpdb->prefix . 'nwmd_business_index';

    $city_ids = wp_get_post_terms(
        $post_id,
        'nwmd_city',
        [
            'fields' => 'ids',
        ]
    );

    $state_ids = wp_get_post_terms(
        $post_id,
        'nwmd_state',
        [
            'fields' => 'ids',
        ]
    );

    $city_term_id = (
        !is_wp_error($city_ids) &&
        !empty($city_ids)
    )
        ? (int) $city_ids[0]
        : 0;

    $state_term_id = (
        !is_wp_error($state_ids) &&
        !empty($state_ids)
    )
        ? (int) $state_ids[0]
        : 0;

    $existing = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT id, created_at
            FROM {$table_name}
            WHERE business_post_id = %d",
            $post_id
        )
    );

    $now = current_time('mysql');

    $data = [
        'business_post_id'     => $post_id,
        'legal_name'           => get_post_meta(
            $post_id,
            'nwmd_legal_name',
            true
        ),
        'public_name'          => get_the_title($post_id),
        'website_url'          => get_post_meta(
            $post_id,
            'nwmd_website_url',
            true
        ),
        'public_email'         => get_post_meta(
            $post_id,
            'nwmd_public_email',
            true
        ),
        'public_phone'         => get_post_meta(
            $post_id,
            'nwmd_public_phone',
            true
        ),
        'street_address'       => get_post_meta(
            $post_id,
            'nwmd_street_address',
            true
        ),
        'city_term_id'         => $city_term_id,
        'state_term_id'        => $state_term_id,
        'postal_code'          => get_post_meta(
            $post_id,
            'nwmd_postal_code',
            true
        ),
        'registration_number'  => get_post_meta(
            $post_id,
            'nwmd_registration_number',
            true
        ),
        'license_number'       => get_post_meta(
            $post_id,
            'nwmd_license_number',
            true
        ),
        'license_status'       => get_post_meta(
            $post_id,
            'nwmd_license_status',
            true
        ),
        'verification_status'  => get_post_meta(
            $post_id,
            'nwmd_verification_status',
            true
        ),
        'claimed_status'       => get_post_meta(
            $post_id,
            'nwmd_claimed_status',
            true
        ),
        'ranking_eligible'     => (int) get_post_meta(
            $post_id,
            'nwmd_ranking_eligible',
            true
        ),
        'last_verified_at'     => get_post_meta(
            $post_id,
            'nwmd_last_verified_at',
            true
        ) ?: null,
        'updated_at'           => $now,
        'archived_at'          => (
            'trash' === $post->post_status
        )
            ? $now
            : null,
    ];

    $formats = [
        '%d',
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
        '%s',
        '%s',
        '%s',
        '%s',
        '%d',
        '%s',
        '%s',
        '%s',
    ];

    if ($existing) {

        $wpdb->update(
            $table_name,
            $data,
            [
                'business_post_id' => $post_id,
            ],
            $formats,
            [
                '%d',
            ]
        );

        return;
    }

    $data['created_at'] = $now;
    $formats[] = '%s';

    $wpdb->insert(
        $table_name,
        $data,
        $formats
    );
}

/**
 * Refresh the index after business taxonomies change.
 *
 * @param int    $object_id Business post ID.
 * @param array  $terms     Submitted terms.
 * @param array  $term_ids  Term taxonomy IDs.
 * @param string $taxonomy  Taxonomy name.
 */
function nwmd_directory_sync_business_terms(
    $object_id,
    $terms,
    $term_ids,
    $taxonomy
) {

    if (
        'nwmd_business' !== get_post_type($object_id) ||
        !in_array(
            $taxonomy,
            [
                'nwmd_city',
                'nwmd_state',
            ],
            true
        )
    ) {
        return;
    }

    nwmd_directory_sync_business_index($object_id);
}

add_action(
    'set_object_terms',
    'nwmd_directory_sync_business_terms',
    20,
    4
);