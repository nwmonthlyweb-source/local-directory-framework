<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register the Business Deals submenu.
 */
function nwmd_directory_register_business_deals_admin_page() {

    add_submenu_page(
        'edit.php?post_type=nwmd_business',
        __(
            'Business Deals',
            'local-directory-framework'
        ),
        __(
            'Deals',
            'local-directory-framework'
        ),
        'manage_options',
        'nwmd-business-deals',
        'nwmd_directory_render_business_deals_admin_page'
    );
}

add_action(
    'admin_menu',
    'nwmd_directory_register_business_deals_admin_page',
    25
);

/**
 * Return a Deals admin URL.
 *
 * @param int $business_post_id Optional business ID.
 * @param int $deal_id          Optional Deal ID.
 *
 * @return string
 */
function nwmd_directory_get_business_deals_admin_url(
    $business_post_id = 0,
    $deal_id = 0
) {

    $arguments = [
        'post_type' => 'nwmd_business',
        'page'      => 'nwmd-business-deals',
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

    return add_query_arg(
        $arguments,
        admin_url('edit.php')
    );
}

/**
 * Add a Deals link to each Business row.
 *
 * @param array   $actions Existing row actions.
 * @param WP_Post $post    Current post.
 *
 * @return array
 */
function nwmd_directory_add_business_deals_row_action(
    $actions,
    $post
) {

    if (
        !$post instanceof WP_Post ||
        'nwmd_business' !== $post->post_type ||
        !current_user_can('manage_options')
    ) {
        return $actions;
    }

    $actions['nwmd_deals'] = sprintf(
        '<a href="%1$s">%2$s</a>',
        esc_url(
            nwmd_directory_get_business_deals_admin_url(
                $post->ID
            )
        ),
        esc_html__(
            'Deals',
            'local-directory-framework'
        )
    );

    return $actions;
}

add_filter(
    'post_row_actions',
    'nwmd_directory_add_business_deals_row_action',
    20,
    2
);

/**
 * Render one Deals admin notice.
 */
function nwmd_directory_render_business_deals_admin_notice() {

    $notice = isset($_GET['nwmd_notice'])
        ? sanitize_key(
            wp_unslash($_GET['nwmd_notice'])
        )
        : '';

    $messages = [
        'deal-created' => [
            'success',
            __(
                'Deal created.',
                'local-directory-framework'
            ),
        ],
        'deal-updated' => [
            'success',
            __(
                'Deal updated.',
                'local-directory-framework'
            ),
        ],
        'deal-archived' => [
            'success',
            __(
                'Deal archived.',
                'local-directory-framework'
            ),
        ],
        'invalid-business' => [
            'error',
            __(
                'Choose a valid business.',
                'local-directory-framework'
            ),
        ],
        'deal-not-found' => [
            'error',
            __(
                'The Deal could not be found.',
                'local-directory-framework'
            ),
        ],
        'invalid-values' => [
            'error',
            __(
                'Enter a Deal title, slug, and full description.',
                'local-directory-framework'
            ),
        ],
        'featured-requires-active' => [
            'error',
            __(
                'Only active Deals can be featured.',
                'local-directory-framework'
            ),
        ],
        'invalid-dates' => [
            'error',
            __(
                'Enter valid dates. Expiration must not precede the start date, and verification cannot be in the future.',
                'local-directory-framework'
            ),
        ],
        'invalid-source-url' => [
            'error',
            __(
                'Enter a valid HTTP or HTTPS official source URL.',
                'local-directory-framework'
            ),
        ],
        'verification-required' => [
            'error',
            __(
                'An active Deal requires an official source URL and verification date.',
                'local-directory-framework'
            ),
        ],
        'deal-exists' => [
            'error',
            __(
                'That Deal slug already exists for this business.',
                'local-directory-framework'
            ),
        ],
        'deal-save-failed' => [
            'error',
            __(
                'The Deal could not be saved.',
                'local-directory-framework'
            ),
        ],
        'deal-archive-failed' => [
            'error',
            __(
                'The Deal could not be archived.',
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
 * Return Deals belonging to one Business.
 *
 * @param int $business_post_id Business post ID.
 *
 * @return array
 */
function nwmd_directory_get_business_deals_admin_records(
    $business_post_id
) {

    global $wpdb;

    $table =
        nwmd_directory_get_business_deals_table_name();

    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT *
            FROM {$table}
            WHERE business_post_id = %d
            ORDER BY
                CASE
                    WHEN status = 'archived' THEN 1
                    ELSE 0
                END ASC,
                is_featured DESC,
                updated_at DESC,
                id DESC",
            absint($business_post_id)
        )
    );
}

/**
 * Render the Business selector.
 */
function nwmd_directory_render_business_deals_selector() {

    $search = isset($_GET['s'])
        ? sanitize_text_field(
            wp_unslash($_GET['s'])
        )
        : '';

    $arguments = [
        'post_type'      => 'nwmd_business',
        'post_status'    => [
            'publish',
            'draft',
            'pending',
            'private',
        ],
        'posts_per_page' => 30,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ];

    if ('' !== $search) {
        $arguments['s'] = $search;
    }

    $businesses = get_posts($arguments);

    ?>
    <h1>
        <?php
        echo esc_html__(
            'Business Deals',
            'local-directory-framework'
        );
        ?>
    </h1>

    <p>
        <?php
        echo esc_html__(
            'Choose a business to create or maintain its verified offers.',
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
            value="nwmd-business-deals"
        >

        <label
            class="screen-reader-text"
            for="nwmd-business-deal-search"
        >
            <?php
            echo esc_html__(
                'Search businesses',
                'local-directory-framework'
            );
            ?>
        </label>

        <input
            type="search"
            id="nwmd-business-deal-search"
            name="s"
            value="<?php echo esc_attr($search); ?>"
            placeholder="<?php echo esc_attr__(
                'Search businesses',
                'local-directory-framework'
            ); ?>"
        >

        <?php
        submit_button(
            __(
                'Search',
                'local-directory-framework'
            ),
            'secondary',
            '',
            false
        );
        ?>
    </form>

    <table class="widefat striped">
        <thead>
            <tr>
                <th>
                    <?php
                    echo esc_html__(
                        'Business',
                        'local-directory-framework'
                    );
                    ?>
                </th>
                <th>
                    <?php
                    echo esc_html__(
                        'Status',
                        'local-directory-framework'
                    );
                    ?>
                </th>
                <th>
                    <?php
                    echo esc_html__(
                        'Action',
                        'local-directory-framework'
                    );
                    ?>
                </th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($businesses)) : ?>
                <tr>
                    <td colspan="3">
                        <?php
                        echo esc_html__(
                            'No businesses found.',
                            'local-directory-framework'
                        );
                        ?>
                    </td>
                </tr>
            <?php else : ?>
                <?php foreach ($businesses as $business) : ?>
                    <tr>
                        <td>
                            <strong>
                                <?php
                                echo esc_html(
                                    get_the_title($business)
                                );
                                ?>
                            </strong>
                        </td>
                        <td>
                            <?php
                            echo esc_html(
                                get_post_status_object(
                                    $business->post_status
                                )->label ?? $business->post_status
                            );
                            ?>
                        </td>
                        <td>
                            <a
                                class="button button-secondary"
                                href="<?php echo esc_url(
                                    nwmd_directory_get_business_deals_admin_url(
                                        $business->ID
                                    )
                                ); ?>"
                            >
                                <?php
                                echo esc_html__(
                                    'Manage Deals',
                                    'local-directory-framework'
                                );
                                ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <?php
}

/**
 * Render the Deal editing form.
 *
 * @param WP_Post     $business Business post.
 * @param object|null $deal     Optional Deal.
 */
function nwmd_directory_render_business_deal_form(
    $business,
    $deal = null
) {

    $is_editing = is_object($deal);

    $deal_slug = $is_editing
        ? (string) $deal->deal_slug
        : '';

    $status = $is_editing
        ? (string) $deal->status
        : 'draft';

    ?>
    <hr>

    <h2>
        <?php
        echo esc_html(
            $is_editing
                ? __(
                    'Edit Deal',
                    'local-directory-framework'
                )
                : __(
                    'Add Deal',
                    'local-directory-framework'
                )
        );
        ?>
    </h2>

    <form
        method="post"
        action="<?php echo esc_url(
            admin_url('admin-post.php')
        ); ?>"
    >
        <input
            type="hidden"
            name="action"
            value="nwmd_save_business_deal"
        >
        <input
            type="hidden"
            name="business_post_id"
            value="<?php echo esc_attr($business->ID); ?>"
        >
        <input
            type="hidden"
            name="deal_id"
            value="<?php echo esc_attr(
                $is_editing
                    ? $deal->id
                    : 0
            ); ?>"
        >

        <?php
        wp_nonce_field(
            'nwmd_save_business_deal',
            'nwmd_business_deal_nonce'
        );
        ?>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="nwmd-deal-slug">
                        <?php
                        echo esc_html__(
                            'Deal slug',
                            'local-directory-framework'
                        );
                        ?>
                    </label>
                </th>
                <td>
                    <input
                        type="text"
                        class="regular-text"
                        id="nwmd-deal-slug"
                        name="deal_slug"
                        value="<?php echo esc_attr(
                            $deal_slug
                        ); ?>"
                        <?php
                        echo $is_editing
                            ? 'readonly'
                            : '';
                        ?>
                    >
                    <p class="description">
                        <?php
                        echo esc_html(
                            $is_editing
                                ? __(
                                    'The stable slug is locked after creation.',
                                    'local-directory-framework'
                                )
                                : __(
                                    'Leave blank to create it from the title.',
                                    'local-directory-framework'
                                )
                        );
                        ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="nwmd-deal-title">
                        <?php
                        echo esc_html__(
                            'Title',
                            'local-directory-framework'
                        );
                        ?>
                    </label>
                </th>
                <td>
                    <input
                        type="text"
                        class="regular-text"
                        id="nwmd-deal-title"
                        name="title"
                        maxlength="255"
                        required
                        value="<?php echo esc_attr(
                            $is_editing
                                ? $deal->title
                                : ''
                        ); ?>"
                    >
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="nwmd-deal-card-text">
                        <?php
                        echo esc_html__(
                            'Short card text',
                            'local-directory-framework'
                        );
                        ?>
                    </label>
                </th>
                <td>
                    <input
                        type="text"
                        class="large-text"
                        id="nwmd-deal-card-text"
                        name="card_text"
                        maxlength="255"
                        value="<?php echo esc_attr(
                            $is_editing
                                ? $deal->card_text
                                : ''
                        ); ?>"
                    >
                    <p class="description">
                        <?php
                        echo esc_html__(
                            'Shown on directory cards. Leave blank to use the title.',
                            'local-directory-framework'
                        );
                        ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="nwmd-deal-description">
                        <?php
                        echo esc_html__(
                            'Full terms',
                            'local-directory-framework'
                        );
                        ?>
                    </label>
                </th>
                <td>
                    <textarea
                        class="large-text"
                        rows="6"
                        id="nwmd-deal-description"
                        name="description"
                        required
                    ><?php echo esc_textarea(
                        $is_editing
                            ? $deal->description
                            : ''
                    ); ?></textarea>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="nwmd-deal-promo-code">
                        <?php
                        echo esc_html__(
                            'Promo code',
                            'local-directory-framework'
                        );
                        ?>
                    </label>
                </th>
                <td>
                    <input
                        type="text"
                        class="regular-text"
                        id="nwmd-deal-promo-code"
                        name="promo_code"
                        maxlength="100"
                        value="<?php echo esc_attr(
                            $is_editing
                                ? $deal->promo_code
                                : ''
                        ); ?>"
                    >
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="nwmd-deal-source-url">
                        <?php
                        echo esc_html__(
                            'Official source URL',
                            'local-directory-framework'
                        );
                        ?>
                    </label>
                </th>
                <td>
                    <input
                        type="url"
                        class="large-text"
                        id="nwmd-deal-source-url"
                        name="source_url"
                        value="<?php echo esc_attr(
                            $is_editing
                                ? $deal->source_url
                                : ''
                        ); ?>"
                    >
                    <p class="description">
                        <?php
                        echo esc_html__(
                            'Required before the Deal can be active.',
                            'local-directory-framework'
                        );
                        ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <?php
                    echo esc_html__(
                        'Schedule',
                        'local-directory-framework'
                    );
                    ?>
                </th>
                <td>
                    <label for="nwmd-deal-starts-at">
                        <?php
                        echo esc_html__(
                            'Starts',
                            'local-directory-framework'
                        );
                        ?>
                    </label>
                    <input
                        type="date"
                        id="nwmd-deal-starts-at"
                        name="starts_at"
                        value="<?php echo esc_attr(
                            $is_editing
                                ? nwmd_directory_get_business_deal_admin_date_value(
                                    $deal->starts_at
                                )
                                : ''
                        ); ?>"
                    >

                    &nbsp;

                    <label for="nwmd-deal-expires-at">
                        <?php
                        echo esc_html__(
                            'Expires',
                            'local-directory-framework'
                        );
                        ?>
                    </label>
                    <input
                        type="date"
                        id="nwmd-deal-expires-at"
                        name="expires_at"
                        value="<?php echo esc_attr(
                            $is_editing
                                ? nwmd_directory_get_business_deal_admin_date_value(
                                    $deal->expires_at
                                )
                                : ''
                        ); ?>"
                    >
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="nwmd-deal-verified-at">
                        <?php
                        echo esc_html__(
                            'Last verified',
                            'local-directory-framework'
                        );
                        ?>
                    </label>
                </th>
                <td>
                    <input
                        type="date"
                        id="nwmd-deal-verified-at"
                        name="verified_at"
                        value="<?php echo esc_attr(
                            $is_editing
                                ? nwmd_directory_get_business_deal_admin_date_value(
                                    $deal->verified_at
                                )
                                : ''
                        ); ?>"
                    >
                    <p class="description">
                        <?php
                        echo esc_html__(
                            'Required before the Deal can be active.',
                            'local-directory-framework'
                        );
                        ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="nwmd-deal-status">
                        <?php
                        echo esc_html__(
                            'Status',
                            'local-directory-framework'
                        );
                        ?>
                    </label>
                </th>
                <td>
                    <select
                        id="nwmd-deal-status"
                        name="status"
                    >
                        <?php
                        foreach (
                            nwmd_directory_get_business_deal_status_choices()
                            as $status_key => $status_label
                        ) :
                            ?>
                            <option
                                value="<?php echo esc_attr(
                                    $status_key
                                ); ?>"
                                <?php selected(
                                    $status,
                                    $status_key
                                ); ?>
                            >
                                <?php
                                echo esc_html(
                                    $status_label
                                );
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <?php
                    echo esc_html__(
                        'Featured',
                        'local-directory-framework'
                    );
                    ?>
                </th>
                <td>
                    <label>
                        <input
                            type="checkbox"
                            name="is_featured"
                            value="1"
                            <?php checked(
                                $is_editing
                                    ? absint(
                                        $deal->is_featured
                                    )
                                    : 0,
                                1
                            ); ?>
                        >
                        <?php
                        echo esc_html__(
                            'Feature this Deal on the business card.',
                            'local-directory-framework'
                        );
                        ?>
                    </label>
                </td>
            </tr>
        </table>

        <?php
        submit_button(
            $is_editing
                ? __(
                    'Update Deal',
                    'local-directory-framework'
                )
                : __(
                    'Add Deal',
                    'local-directory-framework'
                )
        );
        ?>
    </form>
    <?php
}

/**
 * Render the Deals table for one Business.
 *
 * @param WP_Post $business Business post.
 * @param array   $deals     Deal records.
 */
function nwmd_directory_render_business_deals_table(
    $business,
    $deals
) {

    $status_choices =
        nwmd_directory_get_business_deal_status_choices();

    ?>
    <h2>
        <?php
        echo esc_html__(
            'Existing Deals',
            'local-directory-framework'
        );
        ?>
    </h2>

    <table class="widefat striped">
        <thead>
            <tr>
                <th>
                    <?php echo esc_html__('Deal', 'local-directory-framework'); ?>
                </th>
                <th>
                    <?php echo esc_html__('Status', 'local-directory-framework'); ?>
                </th>
                <th>
                    <?php echo esc_html__('Schedule', 'local-directory-framework'); ?>
                </th>
                <th>
                    <?php echo esc_html__('Verified', 'local-directory-framework'); ?>
                </th>
                <th>
                    <?php echo esc_html__('Actions', 'local-directory-framework'); ?>
                </th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($deals)) : ?>
                <tr>
                    <td colspan="5">
                        <?php
                        echo esc_html__(
                            'No Deals have been added.',
                            'local-directory-framework'
                        );
                        ?>
                    </td>
                </tr>
            <?php else : ?>
                <?php foreach ($deals as $deal) : ?>
                    <tr>
                        <td>
                            <strong>
                                <?php echo esc_html($deal->title); ?>
                            </strong>

                            <?php if (absint($deal->is_featured)) : ?>
                                <span class="dashicons dashicons-star-filled"></span>
                            <?php endif; ?>

                            <br>

                            <code>
                                <?php echo esc_html($deal->deal_slug); ?>
                            </code>

                            <?php if ('' !== (string) $deal->promo_code) : ?>
                                <br>
                                <?php
                                echo esc_html__(
                                    'Code:',
                                    'local-directory-framework'
                                );
                                ?>
                                <code>
                                    <?php echo esc_html($deal->promo_code); ?>
                                </code>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php
                            echo esc_html(
                                $status_choices[$deal->status] ??
                                $deal->status
                            );
                            ?>

                            <br>

                            <?php if (
                                nwmd_directory_business_deal_is_current(
                                    $deal
                                )
                            ) : ?>
                                <strong>
                                    <?php
                                    echo esc_html__(
                                        'Currently visible',
                                        'local-directory-framework'
                                    );
                                    ?>
                                </strong>
                            <?php else : ?>
                                <?php
                                echo esc_html__(
                                    'Not visible',
                                    'local-directory-framework'
                                );
                                ?>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php
                            $starts = nwmd_directory_format_business_deal_date(
                                $deal->starts_at
                            );

                            $expires = nwmd_directory_format_business_deal_date(
                                $deal->expires_at
                            );
                            ?>

                            <?php if ('' !== $starts) : ?>
                                <?php
                                echo esc_html__(
                                    'Starts:',
                                    'local-directory-framework'
                                );
                                ?>
                                <?php echo esc_html($starts); ?>
                                <br>
                            <?php endif; ?>

                            <?php
                            echo esc_html__(
                                'Expires:',
                                'local-directory-framework'
                            );
                            ?>
                            <?php
                            echo esc_html(
                                '' !== $expires
                                    ? $expires
                                    : __(
                                        'No expiration',
                                        'local-directory-framework'
                                    )
                            );
                            ?>
                        </td>

                        <td>
                            <?php
                            $verified =
                                nwmd_directory_format_business_deal_date(
                                    $deal->verified_at
                                );

                            echo esc_html(
                                '' !== $verified
                                    ? $verified
                                    : __(
                                        'Not verified',
                                        'local-directory-framework'
                                    )
                            );
                            ?>

                            <?php if ('' !== (string) $deal->source_url) : ?>
                                <br>
                                <a
                                    href="<?php echo esc_url(
                                        $deal->source_url
                                    ); ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <?php
                                    echo esc_html__(
                                        'Official source',
                                        'local-directory-framework'
                                    );
                                    ?>
                                </a>
                            <?php endif; ?>
                        </td>

                        <td>
                            <a
                                class="button button-secondary"
                                href="<?php echo esc_url(
                                    nwmd_directory_get_business_deals_admin_url(
                                        $business->ID,
                                        $deal->id
                                    )
                                ); ?>"
                            >
                                <?php
                                echo esc_html__(
                                    'Edit',
                                    'local-directory-framework'
                                );
                                ?>
                            </a>

                            <?php if ('archived' !== $deal->status) : ?>
                                <form
                                    method="post"
                                    action="<?php echo esc_url(
                                        admin_url('admin-post.php')
                                    ); ?>"
                                    style="display:inline"
                                >
                                    <input
                                        type="hidden"
                                        name="action"
                                        value="nwmd_archive_business_deal"
                                    >
                                    <input
                                        type="hidden"
                                        name="deal_id"
                                        value="<?php echo esc_attr(
                                            $deal->id
                                        ); ?>"
                                    >

                                    <?php
                                    wp_nonce_field(
                                        'nwmd_archive_business_deal_' .
                                            $deal->id,
                                        'nwmd_business_deal_archive_nonce'
                                    );
                                    ?>

                                    <?php
                                    submit_button(
                                        __(
                                            'Archive',
                                            'local-directory-framework'
                                        ),
                                        'secondary',
                                        '',
                                        false,
                                        [
                                            'onclick' =>
                                                "return confirm('" .
                                                esc_js(
                                                    __(
                                                        'Archive this Deal?',
                                                        'local-directory-framework'
                                                    )
                                                ) .
                                                "');",
                                        ]
                                    );
                                    ?>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <?php
}

/**
 * Render the Business Deals admin page.
 */
function nwmd_directory_render_business_deals_admin_page() {

    if (!current_user_can('manage_options')) {
        wp_die(
            esc_html__(
                'You are not allowed to manage business deals.',
                'local-directory-framework'
            )
        );
    }

    $business_post_id = isset($_GET['business_id'])
        ? absint($_GET['business_id'])
        : 0;

    $deal_id = isset($_GET['deal_id'])
        ? absint($_GET['deal_id'])
        : 0;

    ?>
    <div class="wrap">
        <?php
        nwmd_directory_render_business_deals_admin_notice();

        if (0 === $business_post_id) {
            nwmd_directory_render_business_deals_selector();
            ?>
            </div>
            <?php
            return;
        }

        $business =
            nwmd_directory_get_business_deal_admin_business(
                $business_post_id
            );

        if (!$business instanceof WP_Post) {
            ?>
            <h1>
                <?php
                echo esc_html__(
                    'Business Deals',
                    'local-directory-framework'
                );
                ?>
            </h1>

            <div class="notice notice-error">
                <p>
                    <?php
                    echo esc_html__(
                        'The selected business could not be found.',
                        'local-directory-framework'
                    );
                    ?>
                </p>
            </div>
            </div>
            <?php
            return;
        }

        $selected_deal = null;

        if ($deal_id > 0) {
            $selected_deal =
                nwmd_directory_get_business_deal(
                    $deal_id
                );

            if (
                !is_object($selected_deal) ||
                absint($selected_deal->business_post_id) !==
                    $business_post_id
            ) {
                $selected_deal = null;
            }
        }

        $deals =
            nwmd_directory_get_business_deals_admin_records(
                $business_post_id
            );
        ?>

        <h1>
            <?php
            echo esc_html(
                sprintf(
                    __(
                        'Deals — %s',
                        'local-directory-framework'
                    ),
                    get_the_title($business)
                )
            );
            ?>
        </h1>

        <p>
            <a
                href="<?php echo esc_url(
                    nwmd_directory_get_business_deals_admin_url()
                ); ?>"
            >
                <?php
                echo esc_html__(
                    'Choose another business',
                    'local-directory-framework'
                );
                ?>
            </a>

            &nbsp;|&nbsp;

            <a
                href="<?php echo esc_url(
                    get_edit_post_link(
                        $business->ID,
                        'raw'
                    )
                ); ?>"
            >
                <?php
                echo esc_html__(
                    'Edit business',
                    'local-directory-framework'
                );
                ?>
            </a>
        </p>

        <?php
        nwmd_directory_render_business_deals_table(
            $business,
            $deals
        );

        nwmd_directory_render_business_deal_form(
            $business,
            $selected_deal
        );
        ?>
    </div>
    <?php
}
