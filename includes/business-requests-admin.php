<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register the Business Requests submenu.
 */
function nwmd_directory_register_business_requests_admin_page() {

    add_submenu_page(
        'edit.php?post_type=nwmd_business',
        __(
            'Business Requests',
            'local-directory-framework'
        ),
        __(
            'Business Requests',
            'local-directory-framework'
        ),
        'manage_options',
        'nwmd-business-requests',
        'nwmd_directory_render_business_requests_admin_page'
    );
}

add_action(
    'admin_menu',
    'nwmd_directory_register_business_requests_admin_page',
    30
);

/**
 * Redirect to the Business Requests page with a notice.
 *
 * @param string $notice    Notice identifier.
 * @param int    $request_id Optional selected request ID.
 */
function nwmd_directory_redirect_business_requests_admin(
    $notice,
    $request_id = 0
) {

    $arguments = [
        'post_type'   => 'nwmd_business',
        'page'        => 'nwmd-business-requests',
        'nwmd_notice' => sanitize_key($notice),
    ];

    if ($request_id > 0) {
        $arguments['request_id'] = absint(
            $request_id
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
 * Render one Business Requests admin notice.
 */
function nwmd_directory_render_business_requests_admin_notice() {

    $notice = isset($_GET['nwmd_notice'])
        ? sanitize_key(
            wp_unslash($_GET['nwmd_notice'])
        )
        : '';

    $messages = [
        'request-updated' => [
            'success',
            __(
                'Business request updated.',
                'local-directory-framework'
            ),
        ],
        'request-not-found' => [
            'error',
            __(
                'The business request could not be found.',
                'local-directory-framework'
            ),
        ],
        'invalid-status' => [
            'error',
            __(
                'Choose a valid request status.',
                'local-directory-framework'
            ),
        ],
        'email-not-verified' => [
            'warning',
            __(
                'This request cannot be reviewed, approved, or completed until its email is verified.',
                'local-directory-framework'
            ),
        ],
        'request-update-failed' => [
            'error',
            __(
                'The business request could not be updated.',
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
 * Decode stored request details.
 *
 * @param mixed $submitted_data Stored JSON.
 *
 * @return array
 */
function nwmd_directory_decode_business_request_data(
    $submitted_data
) {

    $data = json_decode(
        (string) $submitted_data,
        true
    );

    return is_array($data)
        ? $data
        : [];
}

/**
 * Return the selected request-status filter.
 *
 * @return string
 */
function nwmd_directory_get_business_request_admin_status_filter() {

    $status = isset($_GET['request_status'])
        ? sanitize_key(
            wp_unslash($_GET['request_status'])
        )
        : '';

    $statuses = nwmd_directory_get_business_request_status_choices();

    return isset($statuses[$status])
        ? $status
        : '';
}

/**
 * Render one selected request.
 *
 * @param object $request Request record.
 */
function nwmd_directory_render_business_request_admin_details(
    $request
) {

    $type_choices = nwmd_directory_get_business_request_type_choices();
    $status_choices = nwmd_directory_get_business_request_status_choices();
    $submitted_data = nwmd_directory_decode_business_request_data(
        $request->submitted_data
    );

    $business = $request->business_post_id > 0
        ? get_post(
            absint($request->business_post_id)
        )
        : null;

    $business_edit_url = $business instanceof WP_Post
        ? get_edit_post_link(
            $business->ID,
            'raw'
        )
        : '';

    $public_business_url = (
        $business instanceof WP_Post &&
        'publish' === $business->post_status
    )
        ? get_permalink($business->ID)
        : '';

    $admin_statuses = [
        'pending_review',
        'approved',
        'rejected',
        'completed',
        'archived',
    ];

    $data_labels = [
        'business_website' => __(
            'Business Website',
            'local-directory-framework'
        ),
        'business_address' => __(
            'Business Address',
            'local-directory-framework'
        ),
        'request_details' => __(
            'Request Details',
            'local-directory-framework'
        ),
        'authorization_confirmed' => __(
            'Authorization Confirmed',
            'local-directory-framework'
        ),
        'submitted_from' => __(
            'Submitted From',
            'local-directory-framework'
        ),
    ];

    ?>
    <div class="card" style="max-width: 1100px; margin-top: 24px;">
        <h2>
            <?php
            echo esc_html(
                sprintf(
                    /* translators: %d: Request ID. */
                    __(
                        'Request #%d',
                        'local-directory-framework'
                    ),
                    absint($request->id)
                )
            );
            ?>
        </h2>

        <table class="widefat striped">
            <tbody>
                <tr>
                    <th scope="row">
                        <?php echo esc_html__('Type', 'local-directory-framework'); ?>
                    </th>
                    <td>
                        <?php
                        echo esc_html(
                            $type_choices[$request->request_type]
                                ?? $request->request_type
                        );
                        ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <?php echo esc_html__('Status', 'local-directory-framework'); ?>
                    </th>
                    <td>
                        <?php
                        echo esc_html(
                            $status_choices[$request->status]
                                ?? $request->status
                        );
                        ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <?php echo esc_html__('Business', 'local-directory-framework'); ?>
                    </th>
                    <td>
                        <strong><?php echo esc_html($request->business_name); ?></strong>

                        <?php if (!empty($business_edit_url)) : ?>
                            &nbsp;
                            <a href="<?php echo esc_url($business_edit_url); ?>">
                                <?php echo esc_html__('Edit Business', 'local-directory-framework'); ?>
                            </a>
                        <?php endif; ?>

                        <?php if (!empty($public_business_url)) : ?>
                            &nbsp;|&nbsp;
                            <a
                                href="<?php echo esc_url($public_business_url); ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <?php echo esc_html__('View Profile', 'local-directory-framework'); ?>
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <?php echo esc_html__('Requester', 'local-directory-framework'); ?>
                    </th>
                    <td>
                        <?php echo esc_html($request->requester_name); ?>
                        <br>
                        <a
                            href="<?php echo esc_url(
                                'mailto:' . $request->requester_email
                            ); ?>"
                        >
                            <?php echo esc_html($request->requester_email); ?>
                        </a>

                        <?php if (!empty($request->requester_phone)) : ?>
                            <br>
                            <?php echo esc_html($request->requester_phone); ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <?php echo esc_html__('Email Verified', 'local-directory-framework'); ?>
                    </th>
                    <td>
                        <?php
                        echo esc_html(
                            !empty($request->email_verified_at)
                                ? $request->email_verified_at
                                : __(
                                    'Not verified',
                                    'local-directory-framework'
                                )
                        );
                        ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <?php echo esc_html__('Created', 'local-directory-framework'); ?>
                    </th>
                    <td><?php echo esc_html($request->created_at); ?></td>
                </tr>
                <tr>
                    <th scope="row">
                        <?php echo esc_html__('Last Updated', 'local-directory-framework'); ?>
                    </th>
                    <td><?php echo esc_html($request->updated_at); ?></td>
                </tr>

                <?php foreach ($data_labels as $key => $label) : ?>
                    <?php
                    $value = $submitted_data[$key] ?? '';

                    if (
                        'authorization_confirmed' === $key &&
                        !empty($value)
                    ) {
                        $value = __(
                            'Yes',
                            'local-directory-framework'
                        );
                    }

                    if (
                        is_bool($value) ||
                        is_int($value) ||
                        is_float($value)
                    ) {
                        $value = (string) $value;
                    }

                    if (
                        !is_string($value) ||
                        '' === $value
                    ) {
                        continue;
                    }
                    ?>
                    <tr>
                        <th scope="row"><?php echo esc_html($label); ?></th>
                        <td>
                            <?php if (
                                in_array(
                                    $key,
                                    [
                                        'business_website',
                                        'submitted_from',
                                    ],
                                    true
                                )
                            ) : ?>
                                <a
                                    href="<?php echo esc_url($value); ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <?php echo esc_html($value); ?>
                                </a>
                            <?php elseif ('request_details' === $key) : ?>
                                <?php echo nl2br(esc_html($value)); ?>
                            <?php else : ?>
                                <?php echo esc_html($value); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (!empty($request->reviewed_at)) : ?>
                    <tr>
                        <th scope="row">
                            <?php echo esc_html__('Reviewed', 'local-directory-framework'); ?>
                        </th>
                        <td>
                            <?php echo esc_html($request->reviewed_at); ?>
                            <?php if ($request->reviewed_by > 0) : ?>
                                <?php
                                $reviewer = get_user_by(
                                    'id',
                                    absint($request->reviewed_by)
                                );
                                ?>

                                <?php if ($reviewer instanceof WP_User) : ?>
                                    <?php
                                    echo esc_html(
                                        sprintf(
                                            /* translators: %s: Administrator display name. */
                                            __(
                                                'by %s',
                                                'local-directory-framework'
                                            ),
                                            $reviewer->display_name
                                        )
                                    );
                                    ?>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <h3>
            <?php echo esc_html__('Review Request', 'local-directory-framework'); ?>
        </h3>

        <?php if (empty($request->email_verified_at)) : ?>
            <div class="notice notice-warning inline">
                <p>
                    <?php
                    echo esc_html__(
                        'The requester has not verified their email. Only Rejected or Archived may be selected.',
                        'local-directory-framework'
                    );
                    ?>
                </p>
            </div>
        <?php endif; ?>

        <form
            action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
            method="post"
        >
            <input
                type="hidden"
                name="action"
                value="nwmd_update_business_request"
            >
            <input
                type="hidden"
                name="request_id"
                value="<?php echo esc_attr($request->id); ?>"
            >

            <?php
            wp_nonce_field(
                'nwmd_update_business_request_' . absint($request->id),
                'nwmd_business_request_admin_nonce'
            );
            ?>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="nwmd_request_status">
                            <?php echo esc_html__('Status', 'local-directory-framework'); ?>
                        </label>
                    </th>
                    <td>
                        <select
                            id="nwmd_request_status"
                            name="request_status"
                            required
                        >
                            <?php foreach ($admin_statuses as $status) : ?>
                                <option
                                    value="<?php echo esc_attr($status); ?>"
                                    <?php selected($request->status, $status); ?>
                                >
                                    <?php
                                    echo esc_html(
                                        $status_choices[$status]
                                            ?? $status
                                    );
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="nwmd_admin_notes">
                            <?php echo esc_html__('Administrator Notes', 'local-directory-framework'); ?>
                        </label>
                    </th>
                    <td>
                        <textarea
                            id="nwmd_admin_notes"
                            name="admin_notes"
                            rows="6"
                            class="large-text"
                        ><?php echo esc_textarea($request->admin_notes); ?></textarea>
                        <p class="description">
                            <?php
                            echo esc_html__(
                                'Internal notes are not shown to the requester.',
                                'local-directory-framework'
                            );
                            ?>
                        </p>
                    </td>
                </tr>
            </table>

            <?php
            submit_button(
                __(
                    'Update Request',
                    'local-directory-framework'
                )
            );
            ?>
        </form>
    </div>
    <?php
}

/**
 * Render the Business Requests admin page.
 */
function nwmd_directory_render_business_requests_admin_page() {

    if (!current_user_can('manage_options')) {
        wp_die(
            esc_html__(
                'You are not allowed to manage business requests.',
                'local-directory-framework'
            )
        );
    }

    global $wpdb;

    $table = $wpdb->prefix
        . 'nwmd_business_requests';

    $status_filter
        = nwmd_directory_get_business_request_admin_status_filter();

    $base_query = "SELECT
        id,
        request_type,
        business_post_id,
        requester_name,
        requester_email,
        requester_phone,
        business_name,
        submitted_data,
        email_verified_at,
        status,
        admin_notes,
        reviewed_by,
        reviewed_at,
        created_at,
        updated_at
    FROM {$table}";

    if ('' !== $status_filter) {
        $requests = $wpdb->get_results(
            $wpdb->prepare(
                $base_query
                    . " WHERE status = %s
                    ORDER BY created_at DESC, id DESC
                    LIMIT 200",
                $status_filter
            )
        );
    } else {
        $requests = $wpdb->get_results(
            $base_query
                . " ORDER BY created_at DESC, id DESC
                LIMIT 200"
        );
    }

    $selected_request_id = isset($_GET['request_id'])
        ? absint($_GET['request_id'])
        : 0;

    $selected_request = $selected_request_id > 0
        ? nwmd_directory_get_business_request(
            $selected_request_id
        )
        : null;

    $type_choices = nwmd_directory_get_business_request_type_choices();
    $status_choices = nwmd_directory_get_business_request_status_choices();

    ?>
    <div class="wrap">
        <h1>
            <?php echo esc_html__('Business Requests', 'local-directory-framework'); ?>
        </h1>

        <?php nwmd_directory_render_business_requests_admin_notice(); ?>

        <p>
            <?php
            echo esc_html__(
                'Review verified add, claim, update, correction, and removal requests. No request changes a business profile automatically.',
                'local-directory-framework'
            );
            ?>
        </p>

        <form method="get">
            <input
                type="hidden"
                name="post_type"
                value="nwmd_business"
            >
            <input
                type="hidden"
                name="page"
                value="nwmd-business-requests"
            >

            <label for="nwmd_request_status_filter">
                <strong>
                    <?php echo esc_html__('Status', 'local-directory-framework'); ?>
                </strong>
            </label>

            <select
                id="nwmd_request_status_filter"
                name="request_status"
            >
                <option value="">
                    <?php echo esc_html__('All statuses', 'local-directory-framework'); ?>
                </option>

                <?php foreach ($status_choices as $value => $label) : ?>
                    <option
                        value="<?php echo esc_attr($value); ?>"
                        <?php selected($status_filter, $value); ?>
                    >
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <?php
            submit_button(
                __('Filter', 'local-directory-framework'),
                'secondary',
                '',
                false
            );
            ?>
        </form>

        <?php if ($selected_request_id > 0 && !$selected_request) : ?>
            <div class="notice notice-error inline">
                <p>
                    <?php echo esc_html__('The selected request could not be found.', 'local-directory-framework'); ?>
                </p>
            </div>
        <?php elseif ($selected_request) : ?>
            <?php
            nwmd_directory_render_business_request_admin_details(
                $selected_request
            );
            ?>
        <?php endif; ?>

        <h2>
            <?php echo esc_html__('Recent Requests', 'local-directory-framework'); ?>
        </h2>

        <?php if (empty($requests)) : ?>
            <p>
                <?php echo esc_html__('No business requests were found.', 'local-directory-framework'); ?>
            </p>
        <?php else : ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('ID', 'local-directory-framework'); ?></th>
                        <th><?php echo esc_html__('Type', 'local-directory-framework'); ?></th>
                        <th><?php echo esc_html__('Business', 'local-directory-framework'); ?></th>
                        <th><?php echo esc_html__('Requester', 'local-directory-framework'); ?></th>
                        <th><?php echo esc_html__('Status', 'local-directory-framework'); ?></th>
                        <th><?php echo esc_html__('Email Verified', 'local-directory-framework'); ?></th>
                        <th><?php echo esc_html__('Created', 'local-directory-framework'); ?></th>
                        <th><?php echo esc_html__('Actions', 'local-directory-framework'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $request) : ?>
                        <?php
                        $view_url = add_query_arg(
                            [
                                'post_type'  => 'nwmd_business',
                                'page'       => 'nwmd-business-requests',
                                'request_id' => absint($request->id),
                            ],
                            admin_url('edit.php')
                        );

                        if ('' !== $status_filter) {
                            $view_url = add_query_arg(
                                'request_status',
                                $status_filter,
                                $view_url
                            );
                        }
                        ?>
                        <tr>
                            <td><?php echo esc_html($request->id); ?></td>
                            <td>
                                <?php
                                echo esc_html(
                                    $type_choices[$request->request_type]
                                        ?? $request->request_type
                                );
                                ?>
                            </td>
                            <td>
                                <?php echo esc_html($request->business_name); ?>
                            </td>
                            <td>
                                <?php echo esc_html($request->requester_name); ?>
                                <br>
                                <?php echo esc_html($request->requester_email); ?>
                            </td>
                            <td>
                                <?php
                                echo esc_html(
                                    $status_choices[$request->status]
                                        ?? $request->status
                                );
                                ?>
                            </td>
                            <td>
                                <?php
                                echo esc_html(
                                    !empty($request->email_verified_at)
                                        ? $request->email_verified_at
                                        : __(
                                            'No',
                                            'local-directory-framework'
                                        )
                                );
                                ?>
                            </td>
                            <td><?php echo esc_html($request->created_at); ?></td>
                            <td>
                                <a href="<?php echo esc_url($view_url); ?>">
                                    <?php echo esc_html__('Review', 'local-directory-framework'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Update one business request after administrator review.
 */
function nwmd_directory_update_business_request() {

    if (!current_user_can('manage_options')) {
        wp_die(
            esc_html__(
                'You are not allowed to update business requests.',
                'local-directory-framework'
            )
        );
    }

    $request_id = isset($_POST['request_id'])
        ? absint($_POST['request_id'])
        : 0;

    check_admin_referer(
        'nwmd_update_business_request_' . $request_id,
        'nwmd_business_request_admin_nonce'
    );

    $request = nwmd_directory_get_business_request(
        $request_id
    );

    if (!$request) {
        nwmd_directory_redirect_business_requests_admin(
            'request-not-found'
        );
    }

    $status = isset($_POST['request_status'])
        ? sanitize_key(
            wp_unslash($_POST['request_status'])
        )
        : '';

    $allowed_statuses = [
        'pending_review',
        'approved',
        'rejected',
        'completed',
        'archived',
    ];

    if (!in_array($status, $allowed_statuses, true)) {
        nwmd_directory_redirect_business_requests_admin(
            'invalid-status',
            $request_id
        );
    }

    if (
        empty($request->email_verified_at) &&
        !in_array(
            $status,
            [
                'rejected',
                'archived',
            ],
            true
        )
    ) {
        nwmd_directory_redirect_business_requests_admin(
            'email-not-verified',
            $request_id
        );
    }

    $admin_notes = isset($_POST['admin_notes'])
        ? sanitize_textarea_field(
            wp_unslash($_POST['admin_notes'])
        )
        : '';

    $admin_notes = nwmd_directory_limit_business_request_text(
        $admin_notes,
        10000
    );

    global $wpdb;

    $table = $wpdb->prefix
        . 'nwmd_business_requests';

    $current_time = current_time('mysql');

    $updated = $wpdb->update(
        $table,
        [
            'status'      => $status,
            'admin_notes' => $admin_notes,
            'reviewed_by' => get_current_user_id(),
            'reviewed_at' => $current_time,
            'updated_at'  => $current_time,
        ],
        [
            'id' => $request_id,
        ],
        [
            '%s',
            '%s',
            '%d',
            '%s',
            '%s',
        ],
        [
            '%d',
        ]
    );

    if (false === $updated) {
        nwmd_directory_redirect_business_requests_admin(
            'request-update-failed',
            $request_id
        );
    }

    nwmd_directory_redirect_business_requests_admin(
        'request-updated',
        $request_id
    );
}

add_action(
    'admin_post_nwmd_update_business_request',
    'nwmd_directory_update_business_request'
);
