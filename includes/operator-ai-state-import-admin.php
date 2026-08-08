<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render and consume the latest controlled-import result notice.
 */
function nwmd_directory_render_ai_state_import_result_notice() {

    if (!nwmd_directory_controlled_ai_state_import_is_enabled()) {
        return;
    }

    $result = nwmd_directory_take_ai_state_import_result();

    if (empty($result)) {
        return;
    }

    $success = !empty($result['success']);
    $class = $success ? 'notice notice-success' : 'notice notice-error';
    $message = sanitize_text_field((string) ($result['message'] ?? ''));
    $rollback = sanitize_key((string) ($result['rollback_result'] ?? ''));
    $counts = (array) ($result['counts'] ?? []);
    ?>
    <div class="<?php echo esc_attr($class); ?> is-dismissible">
        <p><?php echo esc_html($message); ?></p>

        <?php if ($success && !empty($counts)) : ?>
            <p>
                <?php
                echo esc_html(
                    sprintf(
                        /* translators: 1: Businesses created, 2: Businesses updated, 3: sources created, 4: sources updated. */
                        __(
                            'Businesses created: %1$d; Businesses updated: %2$d; Sources created: %3$d; Sources updated: %4$d.',
                            'local-directory-framework'
                        ),
                        absint($counts['businesses_created'] ?? 0),
                        absint($counts['businesses_updated'] ?? 0),
                        absint($counts['sources_created'] ?? 0),
                        absint($counts['sources_updated'] ?? 0)
                    )
                );
                ?>
            </p>
        <?php elseif ('not_required' !== $rollback && '' !== $rollback) : ?>
            <p>
                <?php
                echo esc_html(
                    sprintf(
                        /* translators: %s: safe rollback status identifier. */
                        __('Rollback status: %s', 'local-directory-framework'),
                        $rollback
                    )
                );
                ?>
            </p>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Render a compact table of planned canonical field changes.
 *
 * @param array  $items Planned items.
 * @param string $label Record label.
 */
function nwmd_directory_render_ai_state_import_change_table(
    array $items,
    $label
) {

    if (empty($items)) {
        return;
    }
    ?>
    <h4><?php echo esc_html($label); ?></h4>
    <table class="widefat striped" style="max-width: 1100px; margin-bottom: 20px;">
        <thead>
            <tr>
                <th scope="col"><?php echo esc_html__('Identity', 'local-directory-framework'); ?></th>
                <th scope="col"><?php echo esc_html__('Field', 'local-directory-framework'); ?></th>
                <th scope="col"><?php echo esc_html__('Current', 'local-directory-framework'); ?></th>
                <th scope="col"><?php echo esc_html__('Approved target', 'local-directory-framework'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item) : ?>
                <?php
                $identity = (string) ($item['identity'] ?? '');
                $changes = (array) ($item['changed_fields'] ?? []);
                ?>
                <?php foreach ($changes as $field => $change) : ?>
                    <tr>
                        <td><code><?php echo esc_html($identity); ?></code></td>
                        <td><code><?php echo esc_html((string) $field); ?></code></td>
                        <td><?php echo esc_html((string) ($change['from'] ?? '')); ?></td>
                        <td><?php echo esc_html((string) ($change['to'] ?? '')); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

/**
 * Render the prepared controlled-import plan.
 *
 * @param array $plan Authenticated per-user plan.
 */
function nwmd_directory_render_ai_state_import_plan(array $plan) {

    if (!nwmd_directory_controlled_ai_state_import_is_enabled()) {
        return;
    }

    $counts = (array) ($plan['counts'] ?? []);
    $plan_id = (string) ($plan['plan_id'] ?? '');
    ?>
    <div class="notice notice-warning inline" style="max-width: 1060px; padding: 12px;">
        <h3><?php echo esc_html__('Controlled import approval', 'local-directory-framework'); ?></h3>
        <p>
            <?php
            echo esc_html__(
                'Review this server-generated plan carefully. Approval is one-time, expires after 30 minutes, and applies only to the canonical Draft Business and Business Source changes shown below.',
                'local-directory-framework'
            );
            ?>
        </p>
        <p>
            <strong><?php echo esc_html__('Package:', 'local-directory-framework'); ?></strong>
            <?php echo esc_html((string) ($plan['source_filename'] ?? '')); ?>
            <br>
            <strong><?php echo esc_html__('SHA-256:', 'local-directory-framework'); ?></strong>
            <code><?php echo esc_html((string) ($plan['package_hash'] ?? '')); ?></code>
            <br>
            <strong><?php echo esc_html__('AI State format:', 'local-directory-framework'); ?></strong>
            <?php echo esc_html((string) ($plan['format_version'] ?? '')); ?>
        </p>
    </div>

    <table class="widefat striped" style="max-width: 900px; margin: 16px 0 20px;">
        <thead>
            <tr>
                <th scope="col"><?php echo esc_html__('Plan result', 'local-directory-framework'); ?></th>
                <th scope="col"><?php echo esc_html__('Records', 'local-directory-framework'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            $count_labels = [
                'businesses_new'             => __('Businesses to create', 'local-directory-framework'),
                'businesses_updated'         => __('Businesses to update', 'local-directory-framework'),
                'businesses_unchanged'       => __('Businesses unchanged', 'local-directory-framework'),
                'businesses_missing_ignored' => __('Businesses missing from upload (ignored)', 'local-directory-framework'),
                'sources_new'                => __('Sources to create', 'local-directory-framework'),
                'sources_updated'            => __('Sources to update', 'local-directory-framework'),
                'sources_unchanged'          => __('Sources unchanged', 'local-directory-framework'),
                'sources_missing_ignored'    => __('Sources missing from upload (ignored)', 'local-directory-framework'),
                'blocked'                    => __('Blocked records', 'local-directory-framework'),
                'conflicts'                  => __('Conflicting records', 'local-directory-framework'),
            ];
            ?>
            <?php foreach ($count_labels as $key => $label) : ?>
                <tr>
                    <td><?php echo esc_html($label); ?></td>
                    <td><?php echo esc_html((string) absint($counts[$key] ?? 0)); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if (!empty($plan['protected'])) : ?>
        <h4><?php echo esc_html__('Protected record groups', 'local-directory-framework'); ?></h4>
        <table class="widefat striped" style="max-width: 900px; margin-bottom: 20px;">
            <thead>
                <tr>
                    <th scope="col"><?php echo esc_html__('Group', 'local-directory-framework'); ?></th>
                    <th scope="col"><?php echo esc_html__('Status', 'local-directory-framework'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ((array) $plan['protected'] as $protected) : ?>
                    <tr>
                        <td><?php echo esc_html((string) ($protected['label'] ?? '')); ?></td>
                        <td><?php echo esc_html((string) ($protected['status'] ?? '')); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php if (!empty($plan['issues'])) : ?>
        <h4><?php echo esc_html__('Blocking issues', 'local-directory-framework'); ?></h4>
        <ul class="ul-disc">
            <?php foreach ((array) $plan['issues'] as $issue) : ?>
                <li>
                    <strong><?php echo esc_html((string) ($issue['type'] ?? '')); ?>:</strong>
                    <code><?php echo esc_html((string) ($issue['identity'] ?? '')); ?></code>
                    — <?php echo esc_html((string) ($issue['message'] ?? '')); ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php
    $writes = (array) ($plan['writes'] ?? []);
    nwmd_directory_render_ai_state_import_change_table(
        (array) ($writes['businesses_create'] ?? []),
        __('New Draft Businesses', 'local-directory-framework')
    );
    nwmd_directory_render_ai_state_import_change_table(
        (array) ($writes['businesses_update'] ?? []),
        __('Draft Business updates', 'local-directory-framework')
    );
    nwmd_directory_render_ai_state_import_change_table(
        (array) ($writes['sources_create'] ?? []),
        __('New Business Sources', 'local-directory-framework')
    );
    nwmd_directory_render_ai_state_import_change_table(
        (array) ($writes['sources_update'] ?? []),
        __('Business Source updates', 'local-directory-framework')
    );
    ?>

    <?php if (!empty($plan['executable']) && '' !== $plan_id) : ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-bottom: 28px;">
            <input type="hidden" name="action" value="nwmd_directory_execute_controlled_ai_state_import">
            <input type="hidden" name="plan_id" value="<?php echo esc_attr($plan_id); ?>">
            <?php
            wp_nonce_field(
                'nwmd_directory_execute_controlled_ai_state_import_' . $plan_id
            );
            ?>
            <button type="submit" class="button button-primary" onclick="return confirm('<?php echo esc_js(__('Execute only the approved Draft Business and Business Source changes?', 'local-directory-framework')); ?>');">
                <?php echo esc_html__('Approve Controlled Import', 'local-directory-framework'); ?>
            </button>
        </form>
    <?php elseif (
        0 === absint($counts['blocked'] ?? 0)
        && 0 === absint($counts['conflicts'] ?? 0)
    ) : ?>
        <p>
            <strong>
                <?php echo esc_html__('This package contains no approved Business or Business Source changes to execute.', 'local-directory-framework'); ?>
            </strong>
        </p>
    <?php else : ?>
        <p>
            <strong>
                <?php echo esc_html__('This plan cannot be approved until every conflict and blocked record is resolved in a new ZIP.', 'local-directory-framework'); ?>
            </strong>
        </p>
    <?php endif; ?>
    <?php
}

/**
 * Render controlled AI State import preparation and approval controls.
 */
function nwmd_directory_render_controlled_ai_state_import_section() {

    if (
        !nwmd_directory_controlled_ai_state_import_is_enabled()
        || !current_user_can('manage_options')
    ) {
        return;
    }

    $plan = nwmd_directory_get_ai_state_import_plan();
    ?>
    <h3><?php echo esc_html__('Controlled AI State Import', 'local-directory-framework'); ?></h3>
    <p>
        <?php
        echo esc_html__(
            'Prepare a one-time controlled plan from a fully valid AI State ZIP. Only new Draft Businesses, writable fields on existing Draft Businesses, and their Business Sources can be changed. Missing records are never deleted, and Deals, taxonomy, queue, Operator runs, rankings, and advertising remain protected.',
            'local-directory-framework'
        );
        ?>
    </p>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" style="max-width: 760px; margin-bottom: 24px;">
        <input type="hidden" name="action" value="nwmd_directory_prepare_controlled_ai_state_import">
        <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo esc_attr((string) (10 * MB_IN_BYTES)); ?>">
        <?php wp_nonce_field('nwmd_directory_prepare_controlled_ai_state_import'); ?>

        <label class="screen-reader-text" for="nwmd-ai-state-controlled-import-zip">
            <?php echo esc_html__('AI State ZIP for controlled import', 'local-directory-framework'); ?>
        </label>
        <input type="file" id="nwmd-ai-state-controlled-import-zip" name="nwmd_ai_state_controlled_import_zip" accept=".zip,application/zip" required>
        <button type="submit" class="button button-secondary">
            <?php echo esc_html__('Prepare Controlled Import', 'local-directory-framework'); ?>
        </button>
    </form>

    <?php if (!empty($plan)) : ?>
        <?php nwmd_directory_render_ai_state_import_plan($plan); ?>
    <?php endif; ?>
    <?php
}
