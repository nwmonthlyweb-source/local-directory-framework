<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return controlled business-record status choices.
 *
 * @param string $field Status field.
 *
 * @return array
 */
function nwmd_directory_get_business_record_choices($field) {

    $choices = [
        'license_status' => [
            'unknown'      => __('Unknown', 'local-directory-framework'),
            'active'       => __('Active', 'local-directory-framework'),
            'inactive'     => __('Inactive', 'local-directory-framework'),
            'expired'      => __('Expired', 'local-directory-framework'),
            'suspended'    => __('Suspended', 'local-directory-framework'),
            'revoked'      => __('Revoked', 'local-directory-framework'),
            'not-required' => __('Not Required', 'local-directory-framework'),
        ],
        'verification_status' => [
            'unverified'   => __('Unverified', 'local-directory-framework'),
            'pending'      => __('Pending Review', 'local-directory-framework'),
            'verified'     => __('Verified', 'local-directory-framework'),
            'needs-review' => __('Needs Review', 'local-directory-framework'),
        ],
        'claimed_status' => [
            'unclaimed' => __('Unclaimed', 'local-directory-framework'),
            'claimed'   => __('Claimed', 'local-directory-framework'),
            'disputed'  => __('Disputed', 'local-directory-framework'),
        ],
    ];

    return $choices[$field] ?? [];
}

/**
 * Return a stored business-record value.
 *
 * @param int    $post_id Business post ID.
 * @param string $field   Field name without the nwmd_ prefix.
 *
 * @return mixed
 */
function nwmd_directory_get_business_details_value(
    $post_id,
    $field
) {

    return get_post_meta(
        $post_id,
        'nwmd_' . $field,
        true
    );
}

/**
 * Register the Business Record meta box.
 */
function nwmd_directory_register_business_details_box() {

    add_meta_box(
        'nwmd_business_details',
        __('Business Record', 'local-directory-framework'),
        'nwmd_directory_render_business_details_box',
        'nwmd_business',
        'normal',
        'high'
    );
}

add_action(
    'add_meta_boxes',
    'nwmd_directory_register_business_details_box'
);

/**
 * Render one controlled status select.
 *
 * @param string $field         Field name.
 * @param string $current_value Current value.
 */
function nwmd_directory_render_business_status_select(
    $field,
    $current_value
) {

    $choices = nwmd_directory_get_business_record_choices(
        $field
    );

    ?>
    <select
        id="<?php echo esc_attr('nwmd_' . $field); ?>"
        name="<?php echo esc_attr('nwmd_' . $field); ?>"
    >
        <?php foreach ($choices as $value => $label) : ?>
            <option
                value="<?php echo esc_attr($value); ?>"
                <?php selected($current_value, $value); ?>
            >
                <?php echo esc_html($label); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php
}

/**
 * Render the Business Record meta box.
 *
 * @param WP_Post $post Current business post.
 */
function nwmd_directory_render_business_details_box($post) {

    $fields = [
        'public_name',
        'legal_name',
        'website_url',
        'public_email',
        'public_phone',
        'street_address',
        'postal_code',
        'latitude',
        'longitude',
        'registration_number',
        'license_number',
        'license_status',
        'verification_status',
        'claimed_status',
        'ranking_eligible',
        'last_verified_at',
        'archived_at',
    ];

    $values = [];

    foreach ($fields as $field) {
        $values[$field]
            = nwmd_directory_get_business_details_value(
                $post->ID,
                $field
            );
    }

    if ('' === $values['license_status']) {
        $values['license_status'] = 'unknown';
    }

    if ('' === $values['verification_status']) {
        $values['verification_status'] = 'unverified';
    }

    if ('' === $values['claimed_status']) {
        $values['claimed_status'] = 'unclaimed';
    }

    $last_verified_date = '';

    if (
        is_string($values['last_verified_at']) &&
        preg_match(
            '/^\d{4}-\d{2}-\d{2}/',
            $values['last_verified_at'],
            $matches
        )
    ) {
        $last_verified_date = $matches[0];
    }

    wp_nonce_field(
        'nwmd_save_business_details',
        'nwmd_business_details_nonce'
    );

    ?>
    <p>
        <?php
        echo esc_html__(
            'Maintain the verified public record for this business. Category, specialty, state, and city remain managed in their WordPress taxonomy panels.',
            'local-directory-framework'
        );
        ?>
    </p>

    <table class="form-table" role="presentation">
        <tr>
            <th scope="row">
                <label for="nwmd_public_name">
                    <?php echo esc_html__('Public Business Name', 'local-directory-framework'); ?>
                </label>
            </th>
            <td>
                <input
                    type="text"
                    id="nwmd_public_name"
                    name="nwmd_public_name"
                    value="<?php echo esc_attr($values['public_name']); ?>"
                    class="regular-text"
                >
                <p class="description">
                    <?php
                    echo esc_html__(
                        'Leave blank to use the WordPress business title.',
                        'local-directory-framework'
                    );
                    ?>
                </p>
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="nwmd_legal_name">
                    <?php echo esc_html__('Legal Business Name', 'local-directory-framework'); ?>
                </label>
            </th>
            <td>
                <input
                    type="text"
                    id="nwmd_legal_name"
                    name="nwmd_legal_name"
                    value="<?php echo esc_attr($values['legal_name']); ?>"
                    class="regular-text"
                >
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="nwmd_website_url">
                    <?php echo esc_html__('Website URL', 'local-directory-framework'); ?>
                </label>
            </th>
            <td>
                <input
                    type="url"
                    id="nwmd_website_url"
                    name="nwmd_website_url"
                    value="<?php echo esc_attr($values['website_url']); ?>"
                    class="regular-text code"
                    placeholder="https://example.com"
                >
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="nwmd_public_email">
                    <?php echo esc_html__('Public Email', 'local-directory-framework'); ?>
                </label>
            </th>
            <td>
                <input
                    type="email"
                    id="nwmd_public_email"
                    name="nwmd_public_email"
                    value="<?php echo esc_attr($values['public_email']); ?>"
                    class="regular-text"
                >
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="nwmd_public_phone">
                    <?php echo esc_html__('Public Phone', 'local-directory-framework'); ?>
                </label>
            </th>
            <td>
                <input
                    type="text"
                    id="nwmd_public_phone"
                    name="nwmd_public_phone"
                    value="<?php echo esc_attr($values['public_phone']); ?>"
                    class="regular-text"
                >
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="nwmd_street_address">
                    <?php echo esc_html__('Street Address', 'local-directory-framework'); ?>
                </label>
            </th>
            <td>
                <input
                    type="text"
                    id="nwmd_street_address"
                    name="nwmd_street_address"
                    value="<?php echo esc_attr($values['street_address']); ?>"
                    class="regular-text"
                >
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="nwmd_postal_code">
                    <?php echo esc_html__('Postal Code', 'local-directory-framework'); ?>
                </label>
            </th>
            <td>
                <input
                    type="text"
                    id="nwmd_postal_code"
                    name="nwmd_postal_code"
                    value="<?php echo esc_attr($values['postal_code']); ?>"
                    class="small-text"
                >
            </td>
        </tr>

        <tr>
            <th scope="row">
                <?php echo esc_html__('Coordinates', 'local-directory-framework'); ?>
            </th>
            <td>
                <label for="nwmd_latitude">
                    <?php echo esc_html__('Latitude', 'local-directory-framework'); ?>
                </label>
                <input
                    type="number"
                    id="nwmd_latitude"
                    name="nwmd_latitude"
                    value="<?php echo esc_attr($values['latitude']); ?>"
                    min="-90"
                    max="90"
                    step="0.0000001"
                >

                &nbsp;

                <label for="nwmd_longitude">
                    <?php echo esc_html__('Longitude', 'local-directory-framework'); ?>
                </label>
                <input
                    type="number"
                    id="nwmd_longitude"
                    name="nwmd_longitude"
                    value="<?php echo esc_attr($values['longitude']); ?>"
                    min="-180"
                    max="180"
                    step="0.0000001"
                >
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="nwmd_registration_number">
                    <?php echo esc_html__('Registration Number', 'local-directory-framework'); ?>
                </label>
            </th>
            <td>
                <input
                    type="text"
                    id="nwmd_registration_number"
                    name="nwmd_registration_number"
                    value="<?php echo esc_attr($values['registration_number']); ?>"
                    class="regular-text"
                >
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="nwmd_license_number">
                    <?php echo esc_html__('License Number', 'local-directory-framework'); ?>
                </label>
            </th>
            <td>
                <input
                    type="text"
                    id="nwmd_license_number"
                    name="nwmd_license_number"
                    value="<?php echo esc_attr($values['license_number']); ?>"
                    class="regular-text"
                >
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="nwmd_license_status">
                    <?php echo esc_html__('License Status', 'local-directory-framework'); ?>
                </label>
            </th>
            <td>
                <?php
                nwmd_directory_render_business_status_select(
                    'license_status',
                    $values['license_status']
                );
                ?>
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="nwmd_verification_status">
                    <?php echo esc_html__('Verification Status', 'local-directory-framework'); ?>
                </label>
            </th>
            <td>
                <?php
                nwmd_directory_render_business_status_select(
                    'verification_status',
                    $values['verification_status']
                );
                ?>
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="nwmd_claimed_status">
                    <?php echo esc_html__('Claimed Status', 'local-directory-framework'); ?>
                </label>
            </th>
            <td>
                <?php
                nwmd_directory_render_business_status_select(
                    'claimed_status',
                    $values['claimed_status']
                );
                ?>
            </td>
        </tr>

        <tr>
            <th scope="row">
                <?php echo esc_html__('Ranking Eligibility', 'local-directory-framework'); ?>
            </th>
            <td>
                <label for="nwmd_ranking_eligible">
                    <input
                        type="checkbox"
                        id="nwmd_ranking_eligible"
                        name="nwmd_ranking_eligible"
                        value="1"
                        <?php checked(!empty($values['ranking_eligible'])); ?>
                    >
                    <?php
                    echo esc_html__(
                        'This business is eligible for editorial ranking consideration.',
                        'local-directory-framework'
                    );
                    ?>
                </label>
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="nwmd_last_verified_at">
                    <?php echo esc_html__('Last Verified Date', 'local-directory-framework'); ?>
                </label>
            </th>
            <td>
                <input
                    type="date"
                    id="nwmd_last_verified_at"
                    name="nwmd_last_verified_at"
                    value="<?php echo esc_attr($last_verified_date); ?>"
                >
            </td>
        </tr>

        <?php if (!empty($values['archived_at'])) : ?>
            <tr>
                <th scope="row">
                    <?php echo esc_html__('Archived At', 'local-directory-framework'); ?>
                </th>
                <td>
                    <?php echo esc_html($values['archived_at']); ?>
                    <p class="description">
                        <?php
                        echo esc_html__(
                            'This value is maintained automatically when the business is moved to Trash.',
                            'local-directory-framework'
                        );
                        ?>
                    </p>
                </td>
            </tr>
        <?php endif; ?>
    </table>
    <?php
}

/**
 * Update or delete one business-record meta value.
 *
 * @param int    $post_id Business post ID.
 * @param string $field   Field name without the nwmd_ prefix.
 * @param mixed  $value   Sanitized value.
 */
function nwmd_directory_save_business_record_meta(
    $post_id,
    $field,
    $value
) {

    $meta_key = 'nwmd_' . $field;

    if ('' === $value || null === $value) {
        delete_post_meta(
            $post_id,
            $meta_key
        );

        return;
    }

    update_post_meta(
        $post_id,
        $meta_key,
        $value
    );
}

/**
 * Sanitize one optional geographic coordinate.
 *
 * @param mixed $value Raw value.
 * @param float $min   Minimum accepted value.
 * @param float $max   Maximum accepted value.
 *
 * @return string
 */
function nwmd_directory_sanitize_coordinate(
    $value,
    $min,
    $max
) {

    $value = sanitize_text_field(
        wp_unslash($value)
    );

    if (
        '' === $value ||
        !is_numeric($value)
    ) {
        return '';
    }

    $number = (float) $value;

    if (
        $number < $min ||
        $number > $max
    ) {
        return '';
    }

    return (string) $number;
}

/**
 * Convert an HTML date value to a WordPress MySQL datetime.
 *
 * @param mixed $value Raw date value.
 *
 * @return string
 */
function nwmd_directory_sanitize_verified_date($value) {

    $value = sanitize_text_field(
        wp_unslash($value)
    );

    if ('' === $value) {
        return '';
    }

    $timezone = wp_timezone();

    $date = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
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
        return '';
    }

    return $date->format('Y-m-d 00:00:00');
}

/**
 * Save the Business Record fields.
 *
 * @param int $post_id Business post ID.
 */
function nwmd_directory_save_business_details($post_id) {

    if (
        wp_is_post_revision($post_id) ||
        wp_is_post_autosave($post_id)
    ) {
        return;
    }

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

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    if ('nwmd_business' !== get_post_type($post_id)) {
        return;
    }

    $text_fields = [
        'public_name',
        'legal_name',
        'public_phone',
        'street_address',
        'postal_code',
        'registration_number',
        'license_number',
    ];

    foreach ($text_fields as $field) {
        $value = isset($_POST['nwmd_' . $field])
            ? sanitize_text_field(
                wp_unslash($_POST['nwmd_' . $field])
            )
            : '';

        nwmd_directory_save_business_record_meta(
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

    nwmd_directory_save_business_record_meta(
        $post_id,
        'website_url',
        $website_url
    );

    $public_email = isset($_POST['nwmd_public_email'])
        ? sanitize_email(
            wp_unslash($_POST['nwmd_public_email'])
        )
        : '';

    nwmd_directory_save_business_record_meta(
        $post_id,
        'public_email',
        $public_email
    );

    $latitude = isset($_POST['nwmd_latitude'])
        ? nwmd_directory_sanitize_coordinate(
            $_POST['nwmd_latitude'],
            -90,
            90
        )
        : '';

    nwmd_directory_save_business_record_meta(
        $post_id,
        'latitude',
        $latitude
    );

    $longitude = isset($_POST['nwmd_longitude'])
        ? nwmd_directory_sanitize_coordinate(
            $_POST['nwmd_longitude'],
            -180,
            180
        )
        : '';

    nwmd_directory_save_business_record_meta(
        $post_id,
        'longitude',
        $longitude
    );

    $status_fields = [
        'license_status',
        'verification_status',
        'claimed_status',
    ];

    foreach ($status_fields as $field) {
        $choices = nwmd_directory_get_business_record_choices(
            $field
        );

        $value = isset($_POST['nwmd_' . $field])
            ? sanitize_key(
                wp_unslash($_POST['nwmd_' . $field])
            )
            : '';

        if (!isset($choices[$value])) {
            $value = '';
        }

        nwmd_directory_save_business_record_meta(
            $post_id,
            $field,
            $value
        );
    }

    $ranking_eligible = isset(
        $_POST['nwmd_ranking_eligible']
    )
        ? 1
        : 0;

    update_post_meta(
        $post_id,
        'nwmd_ranking_eligible',
        $ranking_eligible
    );

    $last_verified_at = isset(
        $_POST['nwmd_last_verified_at']
    )
        ? nwmd_directory_sanitize_verified_date(
            $_POST['nwmd_last_verified_at']
        )
        : '';

    nwmd_directory_save_business_record_meta(
        $post_id,
        'last_verified_at',
        $last_verified_at
    );
}

add_action(
    'save_post_nwmd_business',
    'nwmd_directory_save_business_details',
    10
);