<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return controlled research-source types.
 *
 * @return array
 */
function nwmd_directory_get_business_source_type_choices() {

    return [
        'official_registration' => __(
            'Official Registration',
            'local-directory-framework'
        ),
        'professional_license' => __(
            'Professional License',
            'local-directory-framework'
        ),
        'public_inspection' => __(
            'Public Inspection',
            'local-directory-framework'
        ),
        'business_website' => __(
            'Business Website',
            'local-directory-framework'
        ),
        'business_submission' => __(
            'Business Submission',
            'local-directory-framework'
        ),
        'editorial_research' => __(
            'Editorial Research',
            'local-directory-framework'
        ),
        'licensed_data_provider' => __(
            'Licensed Data Provider',
            'local-directory-framework'
        ),
    ];
}

/**
 * Return controlled verification results.
 *
 * @return array
 */
function nwmd_directory_get_business_source_result_choices() {

    return [
        'pending' => __(
            'Pending Review',
            'local-directory-framework'
        ),
        'verified' => __(
            'Verified',
            'local-directory-framework'
        ),
        'partial' => __(
            'Partially Verified',
            'local-directory-framework'
        ),
        'mismatch' => __(
            'Information Mismatch',
            'local-directory-framework'
        ),
        'unavailable' => __(
            'Source Unavailable',
            'local-directory-framework'
        ),
    ];
}

/**
 * Limit a sanitized value to a database-safe length.
 *
 * @param string $value  Sanitized value.
 * @param int    $length Maximum character length.
 *
 * @return string
 */
function nwmd_directory_limit_business_source_text(
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
 * Convert an HTML date value to a MySQL datetime.
 *
 * @param mixed $value Raw date.
 *
 * @return string
 */
function nwmd_directory_sanitize_business_source_date($value) {

    $value = sanitize_text_field(
        wp_unslash($value)
    );

    if ('' === $value) {
        return '';
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
        return '';
    }

    return $date->format('Y-m-d 00:00:00');
}

/**
 * Return the date portion of a MySQL datetime.
 *
 * @param mixed $value Stored datetime.
 *
 * @return string
 */
function nwmd_directory_get_business_source_date_value($value) {

    $value = sanitize_text_field($value);

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
 * Return one valid Business post.
 *
 * @param int $business_post_id Business post ID.
 *
 * @return WP_Post|null
 */
function nwmd_directory_get_business_source_business(
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
 * Return one business-source record.
 *
 * @param int $source_id        Source record ID.
 * @param int $business_post_id Optional business post ID.
 *
 * @return object|null
 */
function nwmd_directory_get_business_source(
    $source_id,
    $business_post_id = 0
) {

    global $wpdb;

    $sources_table = $wpdb->prefix
        . 'nwmd_business_sources';

    if ($business_post_id > 0) {
        $source = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
                FROM {$sources_table}
                WHERE id = %d
                    AND business_post_id = %d
                LIMIT 1",
                absint($source_id),
                absint($business_post_id)
            )
        );
    } else {
        $source = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
                FROM {$sources_table}
                WHERE id = %d
                LIMIT 1",
                absint($source_id)
            )
        );
    }

    return is_object($source)
        ? $source
        : null;
}

/**
 * Return all research sources for one business.
 *
 * @param int $business_post_id Business post ID.
 *
 * @return array
 */
function nwmd_directory_get_business_sources(
    $business_post_id
) {

    global $wpdb;

    $sources_table = $wpdb->prefix
        . 'nwmd_business_sources';

    $sources = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT *
            FROM {$sources_table}
            WHERE business_post_id = %d
            ORDER BY retrieved_at DESC, id DESC",
            absint($business_post_id)
        )
    );

    return is_array($sources)
        ? $sources
        : [];
}

/**
 * Redirect to a Business edit screen with a source notice.
 *
 * @param int    $business_post_id Business post ID.
 * @param string $notice           Notice identifier.
 * @param int    $source_id        Optional source ID to edit.
 */
function nwmd_directory_redirect_business_source_admin(
    $business_post_id,
    $notice,
    $source_id = 0
) {

    $arguments = [
        'page'               => 'nwmd-business-sources',
        'business_post_id'   => absint($business_post_id),
        'nwmd_source_notice' => sanitize_key($notice),
    ];

    if ($source_id > 0) {
        $arguments['nwmd_source_id'] = absint($source_id);
    }

    $url = add_query_arg(
        $arguments,
        admin_url('edit.php?post_type=nwmd_business')
    );

    wp_safe_redirect($url);
    exit;
}

/**
 * Register the Research Sources meta box.
 */
function nwmd_directory_register_business_sources_box() {

    add_meta_box(
        'nwmd_business_sources',
        __('Research Sources', 'local-directory-framework'),
        'nwmd_directory_render_business_sources_box_summary',
        'nwmd_business',
        'normal',
        'default'
    );
}

add_action(
    'add_meta_boxes',
    'nwmd_directory_register_business_sources_box'
);

/**
 * Render a business-source notice.
 */
function nwmd_directory_render_business_source_notice() {

    $notice = isset($_GET['nwmd_source_notice'])
        ? sanitize_key(
            wp_unslash($_GET['nwmd_source_notice'])
        )
        : '';

    $notices = [
        'source-created' => [
            'success',
            __('Research source created.', 'local-directory-framework'),
        ],
        'source-updated' => [
            'success',
            __('Research source updated.', 'local-directory-framework'),
        ],
        'source-deleted' => [
            'success',
            __('Research source deleted.', 'local-directory-framework'),
        ],
        'invalid-source' => [
            'error',
            __('The research-source information is invalid.', 'local-directory-framework'),
        ],
        'source-name-required' => [
            'error',
            __('Source name is required.', 'local-directory-framework'),
        ],
        'source-location-required' => [
            'error',
            __('Enter a source URL or source identifier.', 'local-directory-framework'),
        ],
        'invalid-source-url' => [
            'error',
            __('The source URL is invalid.', 'local-directory-framework'),
        ],
        'retrieved-date-required' => [
            'error',
            __('A valid retrieval date is required.', 'local-directory-framework'),
        ],
        'verified-date-required' => [
            'error',
            __('A verification date is required for this result.', 'local-directory-framework'),
        ],
        'duplicate-source' => [
            'error',
            __('This source is already recorded for the business.', 'local-directory-framework'),
        ],
        'source-save-failed' => [
            'error',
            __('The research source could not be saved.', 'local-directory-framework'),
        ],
        'source-delete-failed' => [
            'error',
            __('The research source could not be deleted.', 'local-directory-framework'),
        ],
        'source-not-found' => [
            'error',
            __('The research source was not found.', 'local-directory-framework'),
        ],
    ];

    if (!isset($notices[$notice])) {
        return;
    }

    [$type, $message] = $notices[$notice];

    ?>
    <div
        class="<?php echo esc_attr('notice notice-' . $type . ' inline'); ?>"
    >
        <p><?php echo esc_html($message); ?></p>
    </div>
    <?php
}

/**
 * Register the hidden Research Sources management page.
 */
function nwmd_directory_register_business_sources_page() {

    $parent_slug = 'edit.php?post_type=nwmd_business';
    $menu_slug = 'nwmd-business-sources';

    add_submenu_page(
        $parent_slug,
        __('Research Sources', 'local-directory-framework'),
        __('Research Sources', 'local-directory-framework'),
        'edit_posts',
        $menu_slug,
        'nwmd_directory_render_business_sources_page'
    );

    remove_submenu_page(
        $parent_slug,
        $menu_slug
    );
}

add_action(
    'admin_menu',
    'nwmd_directory_register_business_sources_page',
    20
);

/**
 * Render the Research Sources meta-box summary.
 *
 * @param WP_Post $post Current business post.
 */
function nwmd_directory_render_business_sources_box_summary($post) {

    $business_post_id = absint($post->ID);

    if (
        $business_post_id < 1 ||
        'auto-draft' === $post->post_status
    ) {
        ?>
        <p>
            <?php
            echo esc_html__(
                'Save the business before adding research sources.',
                'local-directory-framework'
            );
            ?>
        </p>
        <?php

        return;
    }

    $sources = nwmd_directory_get_business_sources(
        $business_post_id
    );

    $manage_url = add_query_arg(
        [
            'page'             => 'nwmd-business-sources',
            'business_post_id' => $business_post_id,
        ],
        admin_url('edit.php?post_type=nwmd_business')
    );

    ?>
    <p>
        <?php
        echo esc_html(
            sprintf(
                _n(
                    '%d research source is recorded.',
                    '%d research sources are recorded.',
                    count($sources),
                    'local-directory-framework'
                ),
                count($sources)
            )
        );
        ?>
    </p>

    <p>
        <a
            class="button button-secondary"
            href="<?php echo esc_url($manage_url); ?>"
        >
            <?php
            echo esc_html__(
                'Manage Research Sources',
                'local-directory-framework'
            );
            ?>
        </a>
    </p>
    <?php
}

/**
 * Render the full Research Sources management page.
 */
function nwmd_directory_render_business_sources_page() {

    $business_post_id = isset($_GET['business_post_id'])
        ? absint($_GET['business_post_id'])
        : 0;

    $post = nwmd_directory_get_business_source_business(
        $business_post_id
    );

    if (
        !$post ||
        !current_user_can('edit_post', $business_post_id)
    ) {
        wp_die(
            esc_html__(
                'You are not allowed to manage research sources for this business.',
                'local-directory-framework'
            )
        );
    }

    $back_url = get_edit_post_link(
        $business_post_id,
        'raw'
    );

    ?>
    <div class="wrap">
        <h1>
            <?php
            echo esc_html(
                sprintf(
                    __(
                        'Research Sources: %s',
                        'local-directory-framework'
                    ),
                    get_the_title($business_post_id)
                )
            );
            ?>
        </h1>

        <?php if (!empty($back_url)) : ?>
            <p>
                <a href="<?php echo esc_url($back_url); ?>">
                    <?php
                    echo esc_html__(
                        '← Back to Business',
                        'local-directory-framework'
                    );
                    ?>
                </a>
            </p>
        <?php endif; ?>
    <?php

    $source_types = nwmd_directory_get_business_source_type_choices();
    $result_choices = nwmd_directory_get_business_source_result_choices();
    $sources = nwmd_directory_get_business_sources(
        $business_post_id
    );

    $source_id = isset($_GET['nwmd_source_id'])
        ? absint($_GET['nwmd_source_id'])
        : 0;

    $editing_source = $source_id > 0
        ? nwmd_directory_get_business_source(
            $source_id,
            $business_post_id
        )
        : null;

    $values = [
        'id'                  => 0,
        'source_type'         => 'business_website',
        'source_name'         => '',
        'source_url'          => '',
        'source_identifier'   => '',
        'source_notes'        => '',
        'retrieved_at'        => current_time('Y-m-d'),
        'verified_at'         => '',
        'verification_result' => 'pending',
    ];

    if (is_object($editing_source)) {
        $values = [
            'id'                  => absint($editing_source->id),
            'source_type'         => sanitize_key($editing_source->source_type),
            'source_name'         => sanitize_text_field($editing_source->source_name),
            'source_url'          => esc_url_raw($editing_source->source_url),
            'source_identifier'   => sanitize_text_field($editing_source->source_identifier),
            'source_notes'        => sanitize_textarea_field($editing_source->source_notes),
            'retrieved_at'        => nwmd_directory_get_business_source_date_value(
                $editing_source->retrieved_at
            ),
            'verified_at'         => nwmd_directory_get_business_source_date_value(
                $editing_source->verified_at
            ),
            'verification_result' => sanitize_key(
                $editing_source->verification_result
            ),
        ];
    }

    nwmd_directory_render_business_source_notice();

    ?>
    <p>
        <?php
        echo esc_html__(
            'Record where researched business information came from. Do not copy third-party descriptions or reviews without permission.',
            'local-directory-framework'
        );
        ?>
    </p>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input
            type="hidden"
            name="action"
            value="nwmd_save_business_source"
        >
        <input
            type="hidden"
            name="business_post_id"
            value="<?php echo esc_attr($business_post_id); ?>"
        >
        <input
            type="hidden"
            name="source_id"
            value="<?php echo esc_attr($values['id']); ?>"
        >

        <?php
        wp_nonce_field(
            'nwmd_save_business_source_' . $business_post_id,
            'nwmd_business_source_nonce'
        );
        ?>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="nwmd_source_type">
                        <?php echo esc_html__('Source Type', 'local-directory-framework'); ?>
                    </label>
                </th>
                <td>
                    <select
                        id="nwmd_source_type"
                        name="source_type"
                        required
                    >
                        <?php foreach ($source_types as $value => $label) : ?>
                            <option
                                value="<?php echo esc_attr($value); ?>"
                                <?php selected($values['source_type'], $value); ?>
                            >
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="nwmd_source_name">
                        <?php echo esc_html__('Source Name', 'local-directory-framework'); ?>
                    </label>
                </th>
                <td>
                    <input
                        type="text"
                        id="nwmd_source_name"
                        name="source_name"
                        value="<?php echo esc_attr($values['source_name']); ?>"
                        class="regular-text"
                        maxlength="255"
                        required
                    >
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="nwmd_source_url">
                        <?php echo esc_html__('Source URL', 'local-directory-framework'); ?>
                    </label>
                </th>
                <td>
                    <input
                        type="url"
                        id="nwmd_source_url"
                        name="source_url"
                        value="<?php echo esc_attr($values['source_url']); ?>"
                        class="large-text code"
                        placeholder="https://example.gov/record"
                    >
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="nwmd_source_identifier">
                        <?php echo esc_html__('Source Identifier', 'local-directory-framework'); ?>
                    </label>
                </th>
                <td>
                    <input
                        type="text"
                        id="nwmd_source_identifier"
                        name="source_identifier"
                        value="<?php echo esc_attr($values['source_identifier']); ?>"
                        class="regular-text"
                        maxlength="255"
                    >
                    <p class="description">
                        <?php
                        echo esc_html__(
                            'Examples: registry number, license number, inspection ID, or provider record ID.',
                            'local-directory-framework'
                        );
                        ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="nwmd_retrieved_at">
                        <?php echo esc_html__('Retrieved Date', 'local-directory-framework'); ?>
                    </label>
                </th>
                <td>
                    <input
                        type="date"
                        id="nwmd_retrieved_at"
                        name="retrieved_at"
                        value="<?php echo esc_attr($values['retrieved_at']); ?>"
                        required
                    >
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="nwmd_verification_result">
                        <?php echo esc_html__('Verification Result', 'local-directory-framework'); ?>
                    </label>
                </th>
                <td>
                    <select
                        id="nwmd_verification_result"
                        name="verification_result"
                        required
                    >
                        <?php foreach ($result_choices as $value => $label) : ?>
                            <option
                                value="<?php echo esc_attr($value); ?>"
                                <?php selected($values['verification_result'], $value); ?>
                            >
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="nwmd_verified_at">
                        <?php echo esc_html__('Verified Date', 'local-directory-framework'); ?>
                    </label>
                </th>
                <td>
                    <input
                        type="date"
                        id="nwmd_verified_at"
                        name="verified_at"
                        value="<?php echo esc_attr($values['verified_at']); ?>"
                    >
                    <p class="description">
                        <?php
                        echo esc_html__(
                            'Required when the result is not Pending Review.',
                            'local-directory-framework'
                        );
                        ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="nwmd_source_notes">
                        <?php echo esc_html__('Research Notes', 'local-directory-framework'); ?>
                    </label>
                </th>
                <td>
                    <textarea
                        id="nwmd_source_notes"
                        name="source_notes"
                        rows="5"
                        class="large-text"
                    ><?php echo esc_textarea($values['source_notes']); ?></textarea>
                </td>
            </tr>
        </table>

        <?php
        submit_button(
            is_object($editing_source)
                ? __('Update Research Source', 'local-directory-framework')
                : __('Add Research Source', 'local-directory-framework'),
            'secondary'
        );
        ?>

        <?php if (is_object($editing_source)) : ?>
            <a
                href="<?php echo esc_url(
                    get_edit_post_link(
                        $business_post_id,
                        'raw'
                    )
                ); ?>"
            >
                <?php echo esc_html__('Cancel Edit', 'local-directory-framework'); ?>
            </a>
        <?php endif; ?>
    </form>

    <hr>

    <h3>
        <?php echo esc_html__('Recorded Sources', 'local-directory-framework'); ?>
    </h3>

    <?php if (empty($sources)) : ?>
        <p>
            <?php echo esc_html__('No research sources recorded yet.', 'local-directory-framework'); ?>
        </p>
    <?php else : ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php echo esc_html__('Type', 'local-directory-framework'); ?></th>
                    <th><?php echo esc_html__('Source', 'local-directory-framework'); ?></th>
                    <th><?php echo esc_html__('Identifier', 'local-directory-framework'); ?></th>
                    <th><?php echo esc_html__('Retrieved', 'local-directory-framework'); ?></th>
                    <th><?php echo esc_html__('Result', 'local-directory-framework'); ?></th>
                    <th><?php echo esc_html__('Verified', 'local-directory-framework'); ?></th>
                    <th><?php echo esc_html__('Actions', 'local-directory-framework'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sources as $source) : ?>
                    <?php
                    $edit_url = add_query_arg(
                        [
                            'page'             => 'nwmd-business-sources',
                            'business_post_id' => $business_post_id,
                            'nwmd_source_id'   => absint($source->id),
                        ],
                        admin_url('edit.php?post_type=nwmd_business')
                    );

                    $delete_url = wp_nonce_url(
                        add_query_arg(
                            [
                                'action'           => 'nwmd_delete_business_source',
                                'business_post_id' => $business_post_id,
                                'source_id'        => absint($source->id),
                            ],
                            admin_url('admin-post.php')
                        ),
                        'nwmd_delete_business_source_'
                            . absint($source->id)
                            . '_'
                            . $business_post_id
                    );

                    $source_type = sanitize_key($source->source_type);
                    $result = sanitize_key($source->verification_result);
                    ?>
                    <tr>
                        <td>
                            <?php
                            echo esc_html(
                                $source_types[$source_type]
                                    ?? $source_type
                            );
                            ?>
                        </td>
                        <td>
                            <?php if (!empty($source->source_url)) : ?>
                                <a
                                    href="<?php echo esc_url($source->source_url); ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <?php echo esc_html($source->source_name); ?>
                                </a>
                            <?php else : ?>
                                <?php echo esc_html($source->source_name); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo esc_html($source->source_identifier); ?>
                        </td>
                        <td>
                            <?php
                            echo esc_html(
                                nwmd_directory_get_business_source_date_value(
                                    $source->retrieved_at
                                )
                            );
                            ?>
                        </td>
                        <td>
                            <?php
                            echo esc_html(
                                $result_choices[$result]
                                    ?? $result
                            );
                            ?>
                        </td>
                        <td>
                            <?php
                            echo esc_html(
                                nwmd_directory_get_business_source_date_value(
                                    $source->verified_at
                                )
                            );
                            ?>
                        </td>
                        <td>
                            <a href="<?php echo esc_url($edit_url); ?>">
                                <?php echo esc_html__('Edit', 'local-directory-framework'); ?>
                            </a>
                            |
                            <a
                                href="<?php echo esc_url($delete_url); ?>"
                                onclick="return confirm('<?php echo esc_js(
                                    __('Delete this research source?', 'local-directory-framework')
                                ); ?>');"
                            >
                                <?php echo esc_html__('Delete', 'local-directory-framework'); ?>
                            </a>
                        </td>
                    </tr>

                    <?php if (!empty($source->source_notes)) : ?>
                        <tr>
                            <td colspan="7">
                                <strong>
                                    <?php echo esc_html__('Notes:', 'local-directory-framework'); ?>
                                </strong>
                                <?php echo esc_html($source->source_notes); ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    </div>
    <?php
}

/**
 * Save one business research source.
 */
function nwmd_directory_save_business_source() {

    $business_post_id = isset($_POST['business_post_id'])
        ? absint($_POST['business_post_id'])
        : 0;

    $business = nwmd_directory_get_business_source_business(
        $business_post_id
    );

    if (
        !$business ||
        !current_user_can('edit_post', $business_post_id)
    ) {
        wp_die(
            esc_html__(
                'You are not allowed to manage research sources for this business.',
                'local-directory-framework'
            )
        );
    }

    check_admin_referer(
        'nwmd_save_business_source_' . $business_post_id,
        'nwmd_business_source_nonce'
    );

    $source_id = isset($_POST['source_id'])
        ? absint($_POST['source_id'])
        : 0;

    if (
        $source_id > 0 &&
        !nwmd_directory_get_business_source(
            $source_id,
            $business_post_id
        )
    ) {
        nwmd_directory_redirect_business_source_admin(
            $business_post_id,
            'source-not-found'
        );
    }

    $source_types = nwmd_directory_get_business_source_type_choices();
    $result_choices = nwmd_directory_get_business_source_result_choices();

    $source_type = isset($_POST['source_type'])
        ? sanitize_key(
            wp_unslash($_POST['source_type'])
        )
        : '';

    $verification_result = isset($_POST['verification_result'])
        ? sanitize_key(
            wp_unslash($_POST['verification_result'])
        )
        : '';

    if (
        !isset($source_types[$source_type]) ||
        !isset($result_choices[$verification_result])
    ) {
        nwmd_directory_redirect_business_source_admin(
            $business_post_id,
            'invalid-source',
            $source_id
        );
    }

    $source_name = isset($_POST['source_name'])
        ? sanitize_text_field(
            wp_unslash($_POST['source_name'])
        )
        : '';

    $source_name = nwmd_directory_limit_business_source_text(
        $source_name,
        255
    );

    if ('' === $source_name) {
        nwmd_directory_redirect_business_source_admin(
            $business_post_id,
            'source-name-required',
            $source_id
        );
    }

    $source_url_raw = isset($_POST['source_url'])
        ? trim(
            wp_unslash($_POST['source_url'])
        )
        : '';

    $source_url = esc_url_raw(
        $source_url_raw
    );

    if (
        '' !== $source_url_raw &&
        '' === $source_url
    ) {
        nwmd_directory_redirect_business_source_admin(
            $business_post_id,
            'invalid-source-url',
            $source_id
        );
    }

    $source_identifier = isset($_POST['source_identifier'])
        ? sanitize_text_field(
            wp_unslash($_POST['source_identifier'])
        )
        : '';

    $source_identifier = nwmd_directory_limit_business_source_text(
        $source_identifier,
        255
    );

    if (
        '' === $source_url &&
        '' === $source_identifier
    ) {
        nwmd_directory_redirect_business_source_admin(
            $business_post_id,
            'source-location-required',
            $source_id
        );
    }

    $source_notes = isset($_POST['source_notes'])
        ? sanitize_textarea_field(
            wp_unslash($_POST['source_notes'])
        )
        : '';

    $retrieved_at = isset($_POST['retrieved_at'])
        ? nwmd_directory_sanitize_business_source_date(
            $_POST['retrieved_at']
        )
        : '';

    if ('' === $retrieved_at) {
        nwmd_directory_redirect_business_source_admin(
            $business_post_id,
            'retrieved-date-required',
            $source_id
        );
    }

    $verified_at = isset($_POST['verified_at'])
        ? nwmd_directory_sanitize_business_source_date(
            $_POST['verified_at']
        )
        : '';

    if (
        'pending' !== $verification_result &&
        '' === $verified_at
    ) {
        nwmd_directory_redirect_business_source_admin(
            $business_post_id,
            'verified-date-required',
            $source_id
        );
    }

    global $wpdb;

    $sources_table = $wpdb->prefix
        . 'nwmd_business_sources';

    $duplicate_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id
            FROM {$sources_table}
            WHERE business_post_id = %d
                AND source_type = %s
                AND source_url = %s
                AND source_identifier = %s
                AND id <> %d
            LIMIT 1",
            $business_post_id,
            $source_type,
            $source_url,
            $source_identifier,
            $source_id
        )
    );

    if (!empty($duplicate_id)) {
        nwmd_directory_redirect_business_source_admin(
            $business_post_id,
            'duplicate-source',
            $source_id
        );
    }

    $current_time = current_time('mysql');

    $data = [
        'business_post_id'    => $business_post_id,
        'source_type'         => $source_type,
        'source_name'         => $source_name,
        'source_url'          => $source_url,
        'source_identifier'   => $source_identifier,
        'source_notes'        => $source_notes,
        'retrieved_at'        => $retrieved_at,
        'verified_at'         => '' !== $verified_at
            ? $verified_at
            : null,
        'verification_result' => $verification_result,
        'updated_at'          => $current_time,
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
    ];

    if ($source_id > 0) {
        $saved = $wpdb->update(
            $sources_table,
            $data,
            [
                'id'               => $source_id,
                'business_post_id' => $business_post_id,
            ],
            $formats,
            [
                '%d',
                '%d',
            ]
        );

        $notice = 'source-updated';
    } else {
        $data['created_at'] = $current_time;
        $formats[] = '%s';

        $saved = $wpdb->insert(
            $sources_table,
            $data,
            $formats
        );

        $notice = 'source-created';
    }

    if (false === $saved) {
        nwmd_directory_redirect_business_source_admin(
            $business_post_id,
            'source-save-failed',
            $source_id
        );
    }

    nwmd_directory_redirect_business_source_admin(
        $business_post_id,
        $notice
    );
}

add_action(
    'admin_post_nwmd_save_business_source',
    'nwmd_directory_save_business_source'
);

/**
 * Delete one business research source.
 */
function nwmd_directory_delete_business_source() {

    $business_post_id = isset($_GET['business_post_id'])
        ? absint($_GET['business_post_id'])
        : 0;

    $source_id = isset($_GET['source_id'])
        ? absint($_GET['source_id'])
        : 0;

    $business = nwmd_directory_get_business_source_business(
        $business_post_id
    );

    if (
        !$business ||
        !current_user_can('edit_post', $business_post_id)
    ) {
        wp_die(
            esc_html__(
                'You are not allowed to delete research sources for this business.',
                'local-directory-framework'
            )
        );
    }

    check_admin_referer(
        'nwmd_delete_business_source_'
            . $source_id
            . '_'
            . $business_post_id
    );

    if (
        !nwmd_directory_get_business_source(
            $source_id,
            $business_post_id
        )
    ) {
        nwmd_directory_redirect_business_source_admin(
            $business_post_id,
            'source-not-found'
        );
    }

    global $wpdb;

    $sources_table = $wpdb->prefix
        . 'nwmd_business_sources';

    $deleted = $wpdb->delete(
        $sources_table,
        [
            'id'               => $source_id,
            'business_post_id' => $business_post_id,
        ],
        [
            '%d',
            '%d',
        ]
    );

    if (false === $deleted) {
        nwmd_directory_redirect_business_source_admin(
            $business_post_id,
            'source-delete-failed'
        );
    }

    nwmd_directory_redirect_business_source_admin(
        $business_post_id,
        'source-deleted'
    );
}

add_action(
    'admin_post_nwmd_delete_business_source',
    'nwmd_directory_delete_business_source'
);