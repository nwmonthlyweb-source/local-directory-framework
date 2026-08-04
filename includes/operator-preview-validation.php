<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Normalize one website host for duplicate-review comparisons.
 *
 * @param mixed $url Raw URL.
 *
 * @return string
 */
function nwmd_directory_normalize_operator_match_domain($url) {

    $url = trim((string) $url);

    if ('' === $url) {
        return '';
    }

    $host = strtolower(
        (string) wp_parse_url($url, PHP_URL_HOST)
    );

    $host = rtrim($host, '.');

    if (0 === strpos($host, 'www.')) {
        $host = substr($host, 4);
    }

    return sanitize_text_field($host);
}

/**
 * Normalize one public phone number for duplicate-review comparisons.
 *
 * @param mixed $phone Raw phone.
 *
 * @return string
 */
function nwmd_directory_normalize_operator_match_phone($phone) {

    $digits = preg_replace(
        '/\D+/',
        '',
        (string) $phone
    );

    if (!is_string($digits) || '' === $digits) {
        return '';
    }

    if (
        11 === strlen($digits)
        && '1' === substr($digits, 0, 1)
    ) {
        $digits = substr($digits, 1);
    }

    if (strlen($digits) > 10) {
        $digits = substr($digits, -10);
    }

    return sanitize_text_field($digits);
}

/**
 * Normalize one business name for duplicate-review comparisons.
 *
 * @param mixed $name Raw name.
 *
 * @return string
 */
function nwmd_directory_normalize_operator_match_name($name) {

    $name = remove_accents(
        html_entity_decode(
            wp_strip_all_tags((string) $name),
            ENT_QUOTES,
            'UTF-8'
        )
    );

    $name = strtolower($name);
    $name = preg_replace('/[^a-z0-9]+/', '', $name);

    return is_string($name)
        ? sanitize_text_field($name)
        : '';
}

/**
 * Normalize one source URL for review-only comparisons.
 *
 * Query strings and fragments are intentionally omitted because tracking
 * parameters should not create separate source identities during review.
 *
 * @param mixed $url Raw URL.
 *
 * @return string
 */
function nwmd_directory_normalize_operator_match_url($url) {

    $url = trim((string) $url);

    if ('' === $url) {
        return '';
    }

    $parts = wp_parse_url($url);

    if (
        !is_array($parts)
        || empty($parts['host'])
    ) {
        return '';
    }

    $host = strtolower((string) $parts['host']);
    $host = rtrim($host, '.');

    if (0 === strpos($host, 'www.')) {
        $host = substr($host, 4);
    }

    $port = isset($parts['port'])
        ? ':' . absint($parts['port'])
        : '';

    $path = isset($parts['path'])
        ? '/' . ltrim((string) $parts['path'], '/')
        : '';

    $path = '/' === $path
        ? ''
        : untrailingslashit($path);

    return sanitize_text_field($host . $port . $path);
}

/**
 * Return existing Business records used by duplicate review.
 *
 * @return array
 */
function nwmd_directory_get_operator_existing_business_records() {

    global $wpdb;

    $posts_table = $wpdb->posts;
    $index_table = $wpdb->prefix . 'nwmd_business_index';

    $rows = $wpdb->get_results(
        "SELECT
            posts.ID AS business_post_id,
            posts.post_title,
            posts.post_name,
            posts.post_status,
            business_index.website_url,
            business_index.public_phone
        FROM {$posts_table} AS posts
        LEFT JOIN {$index_table} AS business_index
            ON business_index.business_post_id = posts.ID
        WHERE posts.post_type = 'nwmd_business'
            AND posts.post_status <> 'auto-draft'
        ORDER BY posts.ID ASC",
        ARRAY_A
    );

    return is_array($rows) ? $rows : [];
}

/**
 * Return existing source URL identities keyed by normalized URL.
 *
 * @return array
 */
function nwmd_directory_get_operator_existing_source_url_index() {

    global $wpdb;

    $table = $wpdb->prefix . 'nwmd_business_sources';

    $rows = $wpdb->get_results(
        "SELECT business_post_id, source_url
        FROM {$table}
        WHERE source_url <> ''
        ORDER BY id ASC",
        ARRAY_A
    );

    if (!is_array($rows)) {
        return [];
    }

    $index = [];

    foreach ($rows as $row) {
        $normalized =
            nwmd_directory_normalize_operator_match_url(
                $row['source_url'] ?? ''
            );

        $post_id = absint(
            $row['business_post_id'] ?? 0
        );

        if ('' === $normalized || $post_id < 1) {
            continue;
        }

        if (!isset($index[$normalized])) {
            $index[$normalized] = [];
        }

        $index[$normalized][$post_id] = true;
    }

    return $index;
}

/**
 * Add one possible existing record and match type.
 *
 * @param array  $matches    Match collection.
 * @param array  $record     Existing Business record.
 * @param string $match_type Match type.
 */
function nwmd_directory_add_operator_duplicate_match(
    &$matches,
    $record,
    $match_type
) {

    $post_id = absint(
        $record['business_post_id'] ?? 0
    );

    if ($post_id < 1) {
        return;
    }

    if (!isset($matches[$post_id])) {
        $matches[$post_id] = [
            'business_post_id' => $post_id,
            'post_title'       => sanitize_text_field(
                (string) ($record['post_title'] ?? '')
            ),
            'post_status'      => sanitize_key(
                (string) ($record['post_status'] ?? '')
            ),
            'match_types'      => [],
        ];
    }

    $match_type = sanitize_key($match_type);

    if (
        '' !== $match_type
        && !in_array(
            $match_type,
            $matches[$post_id]['match_types'],
            true
        )
    ) {
        $matches[$post_id]['match_types'][] =
            $match_type;
    }
}

/**
 * Build lookup maps for existing Business records.
 *
 * @param array $records Existing Business records.
 *
 * @return array
 */
function nwmd_directory_build_operator_duplicate_indexes(
    $records
) {

    $indexes = [
        'slug'   => [],
        'domain' => [],
        'phone'  => [],
        'name'   => [],
        'records'=> [],
    ];

    foreach ($records as $record) {
        if (!is_array($record)) {
            continue;
        }

        $post_id = absint(
            $record['business_post_id'] ?? 0
        );

        if ($post_id < 1) {
            continue;
        }

        $indexes['records'][$post_id] = $record;

        $values = [
            'slug' => sanitize_title(
                (string) ($record['post_name'] ?? '')
            ),
            'domain' =>
                nwmd_directory_normalize_operator_match_domain(
                    $record['website_url'] ?? ''
                ),
            'phone' =>
                nwmd_directory_normalize_operator_match_phone(
                    $record['public_phone'] ?? ''
                ),
            'name' =>
                nwmd_directory_normalize_operator_match_name(
                    $record['post_title'] ?? ''
                ),
        ];

        foreach ($values as $type => $value) {
            if ('' === $value) {
                continue;
            }

            if (!isset($indexes[$type][$value])) {
                $indexes[$type][$value] = [];
            }

            $indexes[$type][$value][$post_id] = true;
        }
    }

    return $indexes;
}

/**
 * Return matched existing records for one lookup value.
 *
 * @param array  $indexes    Existing-record indexes.
 * @param string $type       Lookup type.
 * @param string $value      Normalized lookup value.
 * @param array  $matches    Current match collection.
 * @param string $match_type Stored match label.
 *
 * @return array
 */
function nwmd_directory_apply_operator_duplicate_index(
    $indexes,
    $type,
    $value,
    $matches,
    $match_type
) {

    if (
        '' === $value
        || empty($indexes[$type][$value])
        || !is_array($indexes[$type][$value])
    ) {
        return $matches;
    }

    foreach (
        array_keys($indexes[$type][$value])
        as $post_id
    ) {
        $post_id = absint($post_id);

        if (
            $post_id < 1
            || empty($indexes['records'][$post_id])
        ) {
            continue;
        }

        nwmd_directory_add_operator_duplicate_match(
            $matches,
            $indexes['records'][$post_id],
            $match_type
        );
    }

    return $matches;
}

/**
 * Validate one stored research preview against existing WordPress records.
 *
 * This review does not create, update, merge, publish, or delete any
 * directory record.
 *
 * @param int $run_id Operator run ID.
 *
 * @return array|WP_Error
 */
function nwmd_directory_validate_operator_preview_duplicates(
    $run_id
) {

    $run_id = absint($run_id);

    if ($run_id < 1) {
        return new WP_Error(
            'nwmd_operator_duplicate_run_invalid',
            __(
                'The operator run is invalid.',
                'local-directory-framework'
            )
        );
    }

    $preview =
        nwmd_directory_get_operator_research_preview_result(
            $run_id
        );

    if (empty($preview)) {
        return new WP_Error(
            'nwmd_operator_duplicate_preview_missing',
            __(
                'No stored research preview is available for validation.',
                'local-directory-framework'
            )
        );
    }

    $businesses = isset($preview['businesses'])
        && is_array($preview['businesses'])
        ? $preview['businesses']
        : [];

    $records =
        nwmd_directory_get_operator_existing_business_records();

    $indexes =
        nwmd_directory_build_operator_duplicate_indexes(
            $records
        );

    $source_index =
        nwmd_directory_get_operator_existing_source_url_index();

    $validation_rows = [];
    $totals = [
        'ready_for_draft'   => 0,
        'review_required'   => 0,
        'blocked_duplicate' => 0,
    ];

    foreach ($businesses as $business) {
        if (!is_array($business)) {
            continue;
        }

        $slug = sanitize_title(
            (string) ($business['business_slug'] ?? '')
        );
        $domain =
            nwmd_directory_normalize_operator_match_domain(
                $business['website_url'] ?? ''
            );
        $phone =
            nwmd_directory_normalize_operator_match_phone(
                $business['public_phone'] ?? ''
            );
        $name =
            nwmd_directory_normalize_operator_match_name(
                $business['business_name'] ?? ''
            );
        $source_url =
            nwmd_directory_normalize_operator_match_url(
                $business['source_url'] ?? ''
            );

        $matches = [];

        $matches =
            nwmd_directory_apply_operator_duplicate_index(
                $indexes,
                'slug',
                $slug,
                $matches,
                'slug'
            );

        $matches =
            nwmd_directory_apply_operator_duplicate_index(
                $indexes,
                'domain',
                $domain,
                $matches,
                'website_domain'
            );

        $matches =
            nwmd_directory_apply_operator_duplicate_index(
                $indexes,
                'phone',
                $phone,
                $matches,
                'phone'
            );

        $matches =
            nwmd_directory_apply_operator_duplicate_index(
                $indexes,
                'name',
                $name,
                $matches,
                'name'
            );

        if (
            '' !== $source_url
            && !empty($source_index[$source_url])
        ) {
            foreach (
                array_keys($source_index[$source_url])
                as $post_id
            ) {
                $post_id = absint($post_id);

                if (
                    $post_id < 1
                    || empty($indexes['records'][$post_id])
                ) {
                    continue;
                }

                nwmd_directory_add_operator_duplicate_match(
                    $matches,
                    $indexes['records'][$post_id],
                    'source_url'
                );
            }
        }

        $decision = 'ready_for_draft';

        foreach ($matches as $match) {
            if (
                in_array(
                    'slug',
                    $match['match_types'] ?? [],
                    true
                )
            ) {
                $decision = 'blocked_duplicate';
                break;
            }
        }

        if (
            'ready_for_draft' === $decision
            && !empty($matches)
        ) {
            $decision = 'review_required';
        }

        $totals[$decision]++;

        $validation_rows[] = [
            'business_name' => sanitize_text_field(
                (string) ($business['business_name'] ?? '')
            ),
            'business_slug' => $slug,
            'decision'      => $decision,
            'matches'       => array_values($matches),
        ];
    }

    $validation = [
        'validation_version' => 1,
        'validated_at'       => current_time('mysql'),
        'totals'             => $totals,
        'businesses'         => $validation_rows,
    ];

    $preview['duplicate_validation'] = $validation;

    $saved = nwmd_directory_save_operator_research_preview(
        $run_id,
        $preview
    );

    if (is_wp_error($saved)) {
        return $saved;
    }

    return $validation;
}

/**
 * Store one duplicate-validation notice.
 *
 * @param array $notice Notice data.
 */
function nwmd_directory_store_operator_duplicate_notice(
    $notice
) {

    set_transient(
        'nwmd_operator_duplicate_validation_'
            . get_current_user_id(),
        is_array($notice) ? $notice : [],
        MINUTE_IN_SECONDS
    );
}

/**
 * Return and delete the current duplicate-validation notice.
 *
 * @return array
 */
function nwmd_directory_get_operator_duplicate_notice() {

    $key = 'nwmd_operator_duplicate_validation_'
        . get_current_user_id();

    $notice = get_transient($key);

    delete_transient($key);

    return is_array($notice) ? $notice : [];
}

/**
 * Handle the no-cost duplicate validation action.
 */
function nwmd_directory_handle_operator_duplicate_validation() {

    if (!current_user_can('manage_options')) {
        wp_die(
            esc_html__(
                'You do not have permission to perform this action.',
                'local-directory-framework'
            )
        );
    }

    check_admin_referer(
        'nwmd_directory_operator_validate_preview_duplicates'
    );

    $context =
        nwmd_directory_get_current_operator_checkpoint_context();

    $run_id = absint($context['run_id'] ?? 0);

    $result =
        nwmd_directory_validate_operator_preview_duplicates(
            $run_id
        );

    if (is_wp_error($result)) {
        nwmd_directory_store_operator_duplicate_notice(
            [
                'success' => false,
                'message' => $result->get_error_message(),
            ]
        );
    } else {
        nwmd_directory_store_operator_duplicate_notice(
            [
                'success' => true,
                'result'  => $result,
            ]
        );
    }

    $redirect_url = add_query_arg(
        [
            'post_type'          => 'nwmd_business',
            'page'               => 'nwmd-data-operator',
            'preview_validation' => '1',
        ],
        admin_url('edit.php')
    );

    wp_safe_redirect($redirect_url);
    exit;
}

add_action(
    'admin_post_nwmd_directory_operator_validate_preview_duplicates',
    'nwmd_directory_handle_operator_duplicate_validation'
);

/**
 * Return a readable duplicate decision.
 *
 * @param string $decision Decision key.
 *
 * @return string
 */
function nwmd_directory_get_operator_duplicate_decision_label(
    $decision
) {

    $labels = [
        'ready_for_draft' => __(
            'Ready for draft review',
            'local-directory-framework'
        ),
        'review_required' => __(
            'Possible match â€” review required',
            'local-directory-framework'
        ),
        'blocked_duplicate' => __(
            'Blocked duplicate slug',
            'local-directory-framework'
        ),
    ];

    return $labels[$decision]
        ?? sanitize_text_field($decision);
}

/**
 * Return a readable match-type label.
 *
 * @param string $type Match type.
 *
 * @return string
 */
function nwmd_directory_get_operator_duplicate_match_label(
    $type
) {

    $labels = [
        'slug' => __(
            'same slug',
            'local-directory-framework'
        ),
        'website_domain' => __(
            'same website domain',
            'local-directory-framework'
        ),
        'phone' => __(
            'same phone',
            'local-directory-framework'
        ),
        'name' => __(
            'same normalized name',
            'local-directory-framework'
        ),
        'source_url' => __(
            'same source URL',
            'local-directory-framework'
        ),
    ];

    return $labels[$type]
        ?? sanitize_text_field($type);
}

/**
 * Render the duplicate-validation result.
 *
 * @param array $validation Validation result.
 */
function nwmd_directory_render_operator_duplicate_validation_result(
    $validation
) {

    $rows = isset($validation['businesses'])
        && is_array($validation['businesses'])
        ? $validation['businesses']
        : [];

    $totals = isset($validation['totals'])
        && is_array($validation['totals'])
        ? $validation['totals']
        : [];

    ?>
    <p>
        <?php
        echo esc_html(
            sprintf(
                /* translators: 1: ready, 2: review, 3: blocked. */
                __(
                    'Validation result: %1$d ready for draft review, %2$d requiring possible-match review, and %3$d blocked by an existing slug.',
                    'local-directory-framework'
                ),
                absint($totals['ready_for_draft'] ?? 0),
                absint($totals['review_required'] ?? 0),
                absint($totals['blocked_duplicate'] ?? 0)
            )
        );
        ?>
    </p>

    <?php if (!empty($rows)) : ?>
        <table class="widefat striped" style="max-width: 1100px;">
            <thead>
                <tr>
                    <th>
                        <?php
                        echo esc_html__(
                            'Preview business',
                            'local-directory-framework'
                        );
                        ?>
                    </th>
                    <th>
                        <?php
                        echo esc_html__(
                            'Decision',
                            'local-directory-framework'
                        );
                        ?>
                    </th>
                    <th>
                        <?php
                        echo esc_html__(
                            'Possible existing records',
                            'local-directory-framework'
                        );
                        ?>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row) : ?>
                    <tr>
                        <td>
                            <strong>
                                <?php
                                echo esc_html(
                                    (string) (
                                        $row['business_name'] ?? ''
                                    )
                                );
                                ?>
                            </strong>
                            <br>
                            <code>
                                <?php
                                echo esc_html(
                                    (string) (
                                        $row['business_slug'] ?? ''
                                    )
                                );
                                ?>
                            </code>
                        </td>
                        <td>
                            <?php
                            echo esc_html(
                                nwmd_directory_get_operator_duplicate_decision_label(
                                    sanitize_key(
                                        (string) (
                                            $row['decision'] ?? ''
                                        )
                                    )
                                )
                            );
                            ?>
                        </td>
                        <td>
                            <?php
                            $matches = isset($row['matches'])
                                && is_array($row['matches'])
                                ? $row['matches']
                                : [];
                            ?>
                            <?php if (empty($matches)) : ?>
                                <?php
                                echo esc_html__(
                                    'No current match signals.',
                                    'local-directory-framework'
                                );
                                ?>
                            <?php else : ?>
                                <?php foreach ($matches as $match) : ?>
                                    <?php
                                    $post_id = absint(
                                        $match['business_post_id']
                                            ?? 0
                                    );
                                    $edit_url = $post_id > 0
                                        ? get_edit_post_link(
                                            $post_id,
                                            'raw'
                                        )
                                        : '';
                                    $types = isset(
                                        $match['match_types']
                                    ) && is_array(
                                        $match['match_types']
                                    )
                                        ? $match['match_types']
                                        : [];
                                    $type_labels = [];

                                    foreach ($types as $type) {
                                        $type_labels[] =
                                            nwmd_directory_get_operator_duplicate_match_label(
                                                sanitize_key($type)
                                            );
                                    }
                                    ?>
                                    <div style="margin-bottom: 8px;">
                                        <?php if ('' !== $edit_url) : ?>
                                            <a
                                                href="<?php
                                                    echo esc_url(
                                                        $edit_url
                                                    );
                                                ?>"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                            >
                                                <?php
                                                echo esc_html(
                                                    (string) (
                                                        $match['post_title']
                                                        ?? ''
                                                    )
                                                );
                                                ?>
                                            </a>
                                        <?php else : ?>
                                            <?php
                                            echo esc_html(
                                                (string) (
                                                    $match['post_title']
                                                    ?? ''
                                                )
                                            );
                                            ?>
                                        <?php endif; ?>

                                        <?php
                                        echo esc_html(
                                            sprintf(
                                                ' â€” %1$s (%2$s)',
                                                implode(
                                                    ', ',
                                                    $type_labels
                                                ),
                                                sanitize_key(
                                                    (string) (
                                                        $match['post_status']
                                                        ?? ''
                                                    )
                                                )
                                            )
                                        );
                                        ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <?php
}

/**
 * Render the no-cost duplicate-review section for a stored preview.
 *
 * @param int   $run_id  Operator run ID.
 * @param array $preview Stored preview.
 */
function nwmd_directory_render_operator_preview_validation_section(
    $run_id,
    $preview
) {

    $run_id = absint($run_id);

    $notice = [];

    if (
        isset($_GET['preview_validation'])
        && '1' === sanitize_text_field(
            wp_unslash($_GET['preview_validation'])
        )
    ) {
        $notice =
            nwmd_directory_get_operator_duplicate_notice();
    }

    $validation = isset(
        $preview['duplicate_validation']
    ) && is_array(
        $preview['duplicate_validation']
    )
        ? $preview['duplicate_validation']
        : [];

    ?>
    <h4>
        <?php
        echo esc_html__(
            'Preview duplicate review',
            'local-directory-framework'
        );
        ?>
    </h4>

    <p>
        <?php
        echo esc_html__(
            'This no-cost check compares the preview with existing Business slugs, normalized names, website domains, phones, and source URLs. It creates no directory records and makes no OpenAI request.',
            'local-directory-framework'
        );
        ?>
    </p>

    <?php if (!empty($notice)) : ?>
        <div class="notice <?php
            echo !empty($notice['success'])
                ? 'notice-success'
                : 'notice-error';
        ?> inline">
            <p>
                <?php
                echo esc_html(
                    !empty($notice['success'])
                        ? __(
                            'The preview was checked against current WordPress records.',
                            'local-directory-framework'
                        )
                        : (string) (
                            $notice['message']
                            ?? __(
                                'The duplicate review failed.',
                                'local-directory-framework'
                            )
                        )
                );
                ?>
            </p>
        </div>
    <?php endif; ?>

    <?php if (!empty($validation)) : ?>
        <?php
        nwmd_directory_render_operator_duplicate_validation_result(
            $validation
        );
        ?>
        <p>
            <strong>
                <?php
                echo esc_html__(
                    'Duplicate review stored.',
                    'local-directory-framework'
                );
                ?>
            </strong>
            <?php
            echo esc_html__(
                'Continue to supervised Business draft creation below. Confirmed exact duplicates will be skipped, while possible matches remain blocked for review.',
                'local-directory-framework'
            );
            ?>
        </p>
        <?php
        return;
    endif;
    ?>

    <form
        method="post"
        action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
    >
        <input
            type="hidden"
            name="action"
            value="nwmd_directory_operator_validate_preview_duplicates"
        >
        <?php
        wp_nonce_field(
            'nwmd_directory_operator_validate_preview_duplicates'
        );

        submit_button(
            __(
                'Validate Preview Against WordPress',
                'local-directory-framework'
            ),
            'secondary',
            'submit',
            false
        );
        ?>
    </form>
    <?php
}
