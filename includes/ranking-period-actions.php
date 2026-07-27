<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return the number of entries in one ranking period.
 *
 * @param int $period_id Ranking period ID.
 *
 * @return int
 */
function nwmd_directory_get_ranking_period_entry_count($period_id) {

    global $wpdb;

    $rankings_table = $wpdb->prefix
        . 'nwmd_ranking_entries';

    return absint(
        $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*)
                FROM {$rankings_table}
                WHERE ranking_period_id = %d",
                absint($period_id)
            )
        )
    );
}

/**
 * Return the number of ranking entries whose businesses are not public.
 *
 * @param int $period_id Ranking period ID.
 *
 * @return int
 */
function nwmd_directory_get_unpublished_ranking_business_count(
    $period_id
) {

    global $wpdb;

    $rankings_table = $wpdb->prefix
        . 'nwmd_ranking_entries';

    $posts_table = $wpdb->posts;

    return absint(
        $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*)
                FROM {$rankings_table} AS ranking
                LEFT JOIN {$posts_table} AS business
                    ON business.ID = ranking.business_post_id
                    AND business.post_type = %s
                    AND business.post_status = %s
                WHERE ranking.ranking_period_id = %d
                    AND business.ID IS NULL",
                'nwmd_business',
                'publish',
                absint($period_id)
            )
        )
    );
}

/**
 * Redirect to one ranking period with an administrator notice.
 *
 * @param string $notice   Notice identifier.
 * @param int    $period_id Ranking period ID.
 */
function nwmd_directory_redirect_ranking_period_admin(
    $notice,
    $period_id
) {

    $url = add_query_arg(
        [
            'post_type'   => 'nwmd_business',
            'page'        => 'nwmd-monthly-rankings',
            'period_id'   => absint($period_id),
            'nwmd_notice' => sanitize_key($notice),
        ],
        admin_url('edit.php')
    );

    wp_safe_redirect($url);
    exit;
}

/**
 * Update one ranking period status.
 */
function nwmd_directory_update_ranking_period_status() {

    if (!current_user_can('manage_options')) {
        wp_die(
            esc_html__(
                'You are not allowed to publish ranking periods.',
                'local-directory-framework'
            )
        );
    }

    $period_id = isset($_POST['ranking_period_id'])
        ? absint($_POST['ranking_period_id'])
        : 0;

    check_admin_referer(
        'nwmd_update_ranking_period_status_' . $period_id,
        'nwmd_ranking_period_status_nonce'
    );

    $target_status = isset($_POST['target_status'])
        ? sanitize_key(
            wp_unslash($_POST['target_status'])
        )
        : '';

    $period = nwmd_directory_get_ranking_period_by_id(
        $period_id
    );

    if (!$period) {
        nwmd_directory_redirect_ranking_period_admin(
            'ranking-period-not-found',
            $period_id
        );
    }

    $allowed_transitions = [
        'draft' => [
            'review',
        ],
        'review' => [
            'draft',
            'published',
        ],
        'published' => [
            'archived',
        ],
        'archived' => [],
    ];

    if (
        !isset($allowed_transitions[$period->status]) ||
        !in_array(
            $target_status,
            $allowed_transitions[$period->status],
            true
        )
    ) {
        nwmd_directory_redirect_ranking_period_admin(
            'invalid-period-transition',
            $period_id
        );
    }

    if (
        in_array(
            $target_status,
            [
                'review',
                'published',
            ],
            true
        ) &&
        nwmd_directory_get_ranking_period_entry_count(
            $period_id
        ) < 1
    ) {
        nwmd_directory_redirect_ranking_period_admin(
            'ranking-period-empty',
            $period_id
        );
    }

    if (
        'published' === $target_status &&
        nwmd_directory_get_unpublished_ranking_business_count(
            $period_id
        ) > 0
    ) {
        nwmd_directory_redirect_ranking_period_admin(
            'ranking-period-unpublished-business',
            $period_id
        );
    }

    global $wpdb;

    $periods_table = $wpdb->prefix
        . 'nwmd_ranking_periods';

    $rankings_table = $wpdb->prefix
        . 'nwmd_ranking_entries';

    $current_time = current_time('mysql');

    if ('published' === $target_status) {

        $wpdb->query('START TRANSACTION');

        $archived = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$periods_table}
                SET
                    status = %s,
                    updated_at = %s
                WHERE status = %s
                    AND id <> %d",
                'archived',
                $current_time,
                'published',
                $period_id
            )
        );

        if (false === $archived) {
            $wpdb->query('ROLLBACK');

            nwmd_directory_redirect_ranking_period_admin(
                'ranking-period-update-failed',
                $period_id
            );
        }

        $period_updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$periods_table}
                SET
                    status = %s,
                    published_at = %s,
                    updated_at = %s
                WHERE id = %d
                    AND status = %s",
                'published',
                $current_time,
                $current_time,
                $period_id,
                'review'
            )
        );

        if (1 !== $period_updated) {
            $wpdb->query('ROLLBACK');

            nwmd_directory_redirect_ranking_period_admin(
                'ranking-period-update-failed',
                $period_id
            );
        }

        $entries_updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$rankings_table}
                SET published_at = %s
                WHERE ranking_period_id = %d",
                $current_time,
                $period_id
            )
        );

        if (false === $entries_updated) {
            $wpdb->query('ROLLBACK');

            nwmd_directory_redirect_ranking_period_admin(
                'ranking-period-update-failed',
                $period_id
            );
        }

        $wpdb->query('COMMIT');

        nwmd_directory_redirect_ranking_period_admin(
            'ranking-period-published',
            $period_id
        );
    }

    $updated = $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$periods_table}
            SET
                status = %s,
                updated_at = %s
            WHERE id = %d
                AND status = %s",
            $target_status,
            $current_time,
            $period_id,
            $period->status
        )
    );

    if (1 !== $updated) {
        nwmd_directory_redirect_ranking_period_admin(
            'ranking-period-update-failed',
            $period_id
        );
    }

    $notices = [
        'draft'     => 'ranking-period-draft',
        'review'    => 'ranking-period-review',
        'archived'  => 'ranking-period-archived',
    ];

    nwmd_directory_redirect_ranking_period_admin(
        $notices[$target_status]
            ?? 'ranking-period-update-failed',
        $period_id
    );
}

add_action(
    'admin_post_nwmd_update_ranking_period_status',
    'nwmd_directory_update_ranking_period_status'
);

/**
 * Render one ranking-period status action form.
 *
 * @param object $period Ranking period.
 * @param string $target_status Target status.
 * @param string $label Button label.
 * @param string $button_class WordPress button class.
 * @param string $confirmation Optional confirmation message.
 */
function nwmd_directory_render_ranking_period_status_form(
    $period,
    $target_status,
    $label,
    $button_class = 'secondary',
    $confirmation = ''
) {

    if (!is_object($period)) {
        return;
    }

    ?>
    <form
        action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
        method="post"
        style="display: inline-block; margin: 0 8px 8px 0;"
        <?php if ('' !== $confirmation) : ?>
            onsubmit="return confirm('<?php echo esc_js($confirmation); ?>');"
        <?php endif; ?>
    >
        <input
            type="hidden"
            name="action"
            value="nwmd_update_ranking_period_status"
        >
        <input
            type="hidden"
            name="ranking_period_id"
            value="<?php echo esc_attr($period->id); ?>"
        >
        <input
            type="hidden"
            name="target_status"
            value="<?php echo esc_attr($target_status); ?>"
        >

        <?php
        wp_nonce_field(
            'nwmd_update_ranking_period_status_'
                . absint($period->id),
            'nwmd_ranking_period_status_nonce'
        );
        ?>

        <button
            type="submit"
            class="<?php echo esc_attr(
                'button button-' . $button_class
            ); ?>"
        >
            <?php echo esc_html($label); ?>
        </button>
    </form>
    <?php
}

/**
 * Render status controls for one selected ranking period.
 *
 * @param object $period Ranking period.
 */
function nwmd_directory_render_ranking_period_actions($period) {

    if (!is_object($period)) {
        return;
    }

    ?>
    <div style="margin: 16px 0 24px;">
        <strong>
            <?php echo esc_html__('Period Actions', 'local-directory-framework'); ?>
        </strong>
        <div style="margin-top: 10px;">
            <?php
            if ('draft' === $period->status) {
                nwmd_directory_render_ranking_period_status_form(
                    $period,
                    'review',
                    __(
                        'Send to Review',
                        'local-directory-framework'
                    ),
                    'primary'
                );
            } elseif ('review' === $period->status) {
                nwmd_directory_render_ranking_period_status_form(
                    $period,
                    'draft',
                    __(
                        'Return to Draft',
                        'local-directory-framework'
                    )
                );

                nwmd_directory_render_ranking_period_status_form(
                    $period,
                    'published',
                    __(
                        'Publish Rankings',
                        'local-directory-framework'
                    ),
                    'primary',
                    __(
                        'Publish this ranking period? The currently published period will be archived and this snapshot will become read-only.',
                        'local-directory-framework'
                    )
                );
            } elseif ('published' === $period->status) {
                nwmd_directory_render_ranking_period_status_form(
                    $period,
                    'archived',
                    __(
                        'Archive Rankings',
                        'local-directory-framework'
                    ),
                    'secondary',
                    __(
                        'Archive this published ranking period? Public ranking results will be unavailable until another period is published.',
                        'local-directory-framework'
                    )
                );
            } else {
                echo esc_html__(
                    'Archived ranking periods are read-only.',
                    'local-directory-framework'
                );
            }
            ?>
        </div>
    </div>
    <?php
}
