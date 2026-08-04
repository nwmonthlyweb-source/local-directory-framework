<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return one exact taxonomy slug for an operator context term.
 *
 * @param int    $term_id  Term ID.
 * @param string $taxonomy Taxonomy name.
 *
 * @return string|WP_Error
 */
function nwmd_directory_get_operator_draft_term_slug(
    $term_id,
    $taxonomy
) {

    $term_id = absint($term_id);
    $term    = $term_id > 0
        ? get_term($term_id, $taxonomy)
        : null;

    if (!$term instanceof WP_Term || is_wp_error($term)) {
        return new WP_Error(
            'nwmd_operator_draft_term_missing',
            sprintf(
                /* translators: %s: Taxonomy name. */
                __(
                    'The current operator %s term is unavailable.',
                    'local-directory-framework'
                ),
                sanitize_text_field($taxonomy)
            )
        );
    }

    $slug = sanitize_title((string) $term->slug);

    if ('' === $slug) {
        return new WP_Error(
            'nwmd_operator_draft_term_slug_missing',
            __(
                'A current operator taxonomy term has no usable slug.',
                'local-directory-framework'
            )
        );
    }

    return $slug;
}

/**
 * Resolve exact taxonomy slugs from the active checkpoint term IDs.
 *
 * @param array $context Active checkpoint context.
 *
 * @return array|WP_Error
 */
function nwmd_directory_get_operator_draft_taxonomy_slugs($context) {

    $settings = [
        'state_slug' => [
            'term_id'  => absint($context['state_term_id'] ?? 0),
            'taxonomy' => 'nwmd_state',
        ],
        'city_slug' => [
            'term_id'  => absint($context['city_term_id'] ?? 0),
            'taxonomy' => 'nwmd_city',
        ],
        'category_slug' => [
            'term_id'  => absint($context['category_term_id'] ?? 0),
            'taxonomy' => 'nwmd_category',
        ],
        'specialty_slug' => [
            'term_id'  => absint($context['specialty_term_id'] ?? 0),
            'taxonomy' => 'nwmd_specialty',
        ],
    ];

    $slugs = [];

    foreach ($settings as $field => $term_settings) {
        $slug = nwmd_directory_get_operator_draft_term_slug(
            $term_settings['term_id'],
            $term_settings['taxonomy']
        );

        if (is_wp_error($slug)) {
            return $slug;
        }

        $slugs[$field] = $slug;
    }

    return $slugs;
}

/**
 * Build a decision index from the latest duplicate validation.
 *
 * @param array $validation Duplicate validation result.
 *
 * @return array
 */
function nwmd_directory_get_operator_draft_decision_index($validation) {

    $rows = isset($validation['businesses'])
        && is_array($validation['businesses'])
        ? $validation['businesses']
        : [];

    $index = [];

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $slug = sanitize_title(
            (string) ($row['business_slug'] ?? '')
        );

        if ('' === $slug) {
            continue;
        }

        $index[$slug] = sanitize_key(
            (string) ($row['decision'] ?? '')
        );
    }

    return $index;
}

/**
 * Convert validated preview businesses to importer row items.
 *
 * @param array $context    Active checkpoint context.
 * @param array $preview    Stored preview.
 * @param array $validation Fresh duplicate validation.
 *
 * @return array|WP_Error
 */
function nwmd_directory_build_operator_draft_items(
    $context,
    $preview,
    $validation
) {

    $slugs = nwmd_directory_get_operator_draft_taxonomy_slugs(
        $context
    );

    if (is_wp_error($slugs)) {
        return $slugs;
    }

    $businesses = isset($preview['businesses'])
        && is_array($preview['businesses'])
        ? $preview['businesses']
        : [];

    if (empty($businesses)) {
        return new WP_Error(
            'nwmd_operator_draft_businesses_missing',
            __(
                'The stored preview has no businesses to create.',
                'local-directory-framework'
            )
        );
    }

    $decisions = nwmd_directory_get_operator_draft_decision_index(
        $validation
    );

    $items      = [];
    $seen_slugs = [];
    $today      = current_time('Y-m-d');

    foreach ($businesses as $index => $business) {
        if (!is_array($business)) {
            return new WP_Error(
                'nwmd_operator_draft_business_invalid',
                __(
                    'The stored preview contains an invalid business row.',
                    'local-directory-framework'
                )
            );
        }

        $business_name = sanitize_text_field(
            (string) ($business['business_name'] ?? '')
        );
        $business_slug = sanitize_title(
            (string) ($business['business_slug'] ?? '')
        );
        $source_url = esc_url_raw(
            (string) ($business['source_url'] ?? ''),
            [
                'http',
                'https',
            ]
        );

        if (
            '' === $business_name
            || '' === $business_slug
            || '' === $source_url
        ) {
            return new WP_Error(
                'nwmd_operator_draft_required_data_missing',
                __(
                    'A preview business is missing required draft data.',
                    'local-directory-framework'
                )
            );
        }

        if (isset($seen_slugs[$business_slug])) {
            return new WP_Error(
                'nwmd_operator_draft_preview_slug_duplicate',
                __(
                    'The preview contains a duplicate business slug.',
                    'local-directory-framework'
                )
            );
        }

        $seen_slugs[$business_slug] = true;

        $decision = sanitize_key(
            (string) ($decisions[$business_slug] ?? '')
        );

        if ('blocked_duplicate' === $decision) {
            continue;
        }

        if ('ready_for_draft' !== $decision) {
            return new WP_Error(
                'nwmd_operator_draft_review_required',
                __(
                    'Every possible duplicate must be reviewed before draft creation.',
                    'local-directory-framework'
                )
            );
        }

        $source_name = sanitize_text_field(
            (string) ($business['source_name'] ?? '')
        );

        if ('' === $source_name) {
            $source_name = sprintf(
                /* translators: %s: Business name. */
                __('%s research source', 'local-directory-framework'),
                $business_name
            );
        }

        $confidence = sanitize_key(
            (string) ($business['confidence'] ?? '')
        );

        if (
            !in_array(
                $confidence,
                [
                    'high',
                    'medium',
                    'low',
                ],
                true
            )
        ) {
            $confidence = 'low';
        }

        $description = sanitize_textarea_field(
            (string) ($business['description'] ?? '')
        );

        $row = [
            'business_name'             => $business_name,
            'business_slug'             => $business_slug,
            'state_slug'                => $slugs['state_slug'],
            'city_slug'                 => $slugs['city_slug'],
            'category_slug'             => $slugs['category_slug'],
            'specialty_slug'            => $slugs['specialty_slug'],
            'public_name'               => $business_name,
            'legal_name'                => '',
            'public_phone'              => sanitize_text_field(
                (string) ($business['public_phone'] ?? '')
            ),
            'public_email'              => '',
            'website_url'               => esc_url_raw(
                (string) ($business['website_url'] ?? ''),
                [
                    'http',
                    'https',
                ]
            ),
            'street_address'            => sanitize_text_field(
                (string) ($business['street_address'] ?? '')
            ),
            'postal_code'               => sanitize_text_field(
                (string) ($business['postal_code'] ?? '')
            ),
            'description'               => $description,
            'excerpt'                   => $description,
            'latitude'                  => '',
            'longitude'                 => '',
            'registration_number'       => '',
            'license_number'            => '',
            'license_status'            => 'unknown',
            'verification_status'       => 'unverified',
            'claimed_status'            => 'unclaimed',
            'ranking_eligible'          => '0',
            'last_verified_at'          => '',
            'source_type'               => 'editorial_research',
            'source_name'               => $source_name,
            'source_url'                => $source_url,
            'source_identifier'         => '',
            'source_notes'              => sprintf(
                /* translators: %s: Confidence label. */
                __(
                    'Created from a supervised operator preview. Research confidence: %s.',
                    'local-directory-framework'
                ),
                $confidence
            ),
            'source_retrieved_at'       => $today,
            'source_verified_at'        => '',
            'source_verification_result' => 'pending',
        ];

        $items[] = [
            'line_number' => absint($index) + 2,
            'data'        => $row,
        ];
    }

    return $items;
}

/**
 * Validate mapped draft items with the existing Business CSV validator.
 *
 * @param array $items Mapped importer row items.
 *
 * @return true|WP_Error
 */
function nwmd_directory_validate_operator_draft_items($items) {

    $seen_business_slugs    = [];
    $seen_registration_nums = [];
    $seen_license_nums      = [];
    $errors                 = [];
    $error_count            = 0;

    foreach ($items as $item) {
        $row = isset($item['data']) && is_array($item['data'])
            ? $item['data']
            : [];

        nwmd_directory_validate_business_csv_row(
            $row,
            absint($item['line_number'] ?? 0),
            $seen_business_slugs,
            $seen_registration_nums,
            $seen_license_nums,
            $errors,
            $error_count
        );
    }

    if ($error_count < 1) {
        return true;
    }

    $messages = [];

    foreach (array_slice($errors, 0, 5) as $error) {
        $message = sanitize_text_field((string) $error);

        if ('' !== $message) {
            $messages[] = $message;
        }
    }

    return new WP_Error(
        'nwmd_operator_draft_validation_failed',
        sprintf(
            /* translators: 1: Error count, 2: First validation errors. */
            __(
                'Draft validation found %1$d error(s): %2$s',
                'local-directory-framework'
            ),
            absint($error_count),
            implode(' ', $messages)
        )
    );
}

/**
 * Import all draft items and roll back every created record on failure.
 *
 * @param array $items            Mapped importer row items.
 * @param array $created_post_ids Created post IDs.
 *
 * @return true|WP_Error
 */
function nwmd_directory_import_operator_draft_items(
    $items,
    &$created_post_ids
) {

    $created_post_ids = [];

    foreach ($items as $item) {
        $imported = nwmd_directory_import_business_csv_row(
            $item,
            $created_post_ids
        );

        if (!is_wp_error($imported)) {
            continue;
        }

        $rolled_back =
            nwmd_directory_rollback_business_csv_import(
                $created_post_ids
            );

        return new WP_Error(
            'nwmd_operator_draft_import_failed',
            sprintf(
                /* translators: 1: Import error, 2: Rollback status. */
                __(
                    'Business draft creation failed: %1$s Rollback: %2$s.',
                    'local-directory-framework'
                ),
                $imported->get_error_message(),
                $rolled_back
                    ? __('complete', 'local-directory-framework')
                    : __('incomplete', 'local-directory-framework')
            )
        );
    }

    return true;
}

/**
 * Reset draft counters after a later operation fails.
 *
 * @param array $context Active checkpoint context.
 */
function nwmd_directory_reset_operator_draft_counters($context) {

    global $wpdb;

    $tables = nwmd_directory_get_operator_table_names();
    $now    = current_time('mysql');

    $wpdb->update(
        $tables['runs'],
        [
            'businesses_created'       => 0,
            'businesses_without_deals' => 0,
            'updated_at'               => $now,
        ],
        [
            'id'     => absint($context['run_id'] ?? 0),
            'status' => 'started',
        ],
        [
            '%d',
            '%d',
            '%s',
        ],
        [
            '%d',
            '%s',
        ]
    );

    $wpdb->update(
        $tables['specialties'],
        [
            'businesses_created' => 0,
            'updated_at'         => $now,
        ],
        [
            'id'     => absint($context['checkpoint_id'] ?? 0),
            'status' => 'in_progress',
        ],
        [
            '%d',
            '%s',
        ],
        [
            '%d',
            '%s',
        ]
    );
}

/**
 * Record draft counts on the active run and checkpoint.
 *
 * @param array $context Active checkpoint context.
 * @param int   $count   Created Business draft count.
 *
 * @return true|WP_Error
 */
function nwmd_directory_record_operator_draft_counters(
    $context,
    $count
) {

    global $wpdb;

    $tables        = nwmd_directory_get_operator_table_names();
    $run_id        = absint($context['run_id'] ?? 0);
    $checkpoint_id = absint($context['checkpoint_id'] ?? 0);
    $count         = absint($count);
    $now           = current_time('mysql');

    $run = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT status, businesses_created, businesses_without_deals
            FROM {$tables['runs']}
            WHERE id = %d
            LIMIT 1",
            $run_id
        )
    );

    $checkpoint = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT status, businesses_created
            FROM {$tables['specialties']}
            WHERE id = %d
            LIMIT 1",
            $checkpoint_id
        )
    );

    if (
        !is_object($run)
        || 'started' !== (string) $run->status
        || 0 !== absint($run->businesses_created)
        || 0 !== absint($run->businesses_without_deals)
        || !is_object($checkpoint)
        || 'in_progress' !== (string) $checkpoint->status
        || 0 !== absint($checkpoint->businesses_created)
    ) {
        return new WP_Error(
            'nwmd_operator_draft_counter_state_invalid',
            __(
                'The operator counters are not in a safe state for first-time draft creation.',
                'local-directory-framework'
            )
        );
    }

    $run_updated = $wpdb->update(
        $tables['runs'],
        [
            'businesses_created'       => $count,
            'businesses_without_deals' => $count,
            'updated_at'               => $now,
        ],
        [
            'id'                       => $run_id,
            'status'                   => 'started',
            'businesses_created'       => 0,
            'businesses_without_deals' => 0,
        ],
        [
            '%d',
            '%d',
            '%s',
        ],
        [
            '%d',
            '%s',
            '%d',
            '%d',
        ]
    );

    if (1 !== $run_updated) {
        return new WP_Error(
            'nwmd_operator_draft_run_counter_failed',
            __(
                'The operator run could not record the created drafts.',
                'local-directory-framework'
            )
        );
    }

    $checkpoint_updated = $wpdb->update(
        $tables['specialties'],
        [
            'businesses_created' => $count,
            'updated_at'         => $now,
        ],
        [
            'id'                 => $checkpoint_id,
            'status'             => 'in_progress',
            'businesses_created' => 0,
        ],
        [
            '%d',
            '%s',
        ],
        [
            '%d',
            '%s',
            '%d',
        ]
    );

    if (1 !== $checkpoint_updated) {
        nwmd_directory_reset_operator_draft_counters($context);

        return new WP_Error(
            'nwmd_operator_draft_checkpoint_counter_failed',
            __(
                'The operator checkpoint could not record the created drafts.',
                'local-directory-framework'
            )
        );
    }

    return true;
}

/**
 * Create the validated preview businesses as supervised WordPress drafts.
 *
 * This action does not contact OpenAI, reserve budget, publish records,
 * create Deals or rankings, or complete the operator checkpoint.
 *
 * @return array|WP_Error
 */
function nwmd_directory_create_operator_ready_business_drafts() {

    return nwmd_directory_with_operator_lock(
        function () {

            if (
                !function_exists(
                    'nwmd_directory_validate_business_csv_row'
                )
                || !function_exists(
                    'nwmd_directory_import_business_csv_row'
                )
                || !function_exists(
                    'nwmd_directory_rollback_business_csv_import'
                )
            ) {
                return new WP_Error(
                    'nwmd_operator_draft_dependencies_missing',
                    __(
                        'The Business validation or import helpers are unavailable.',
                        'local-directory-framework'
                    )
                );
            }

            $valid =
                nwmd_directory_validate_single_active_checkpoint();

            if (is_wp_error($valid)) {
                return $valid;
            }

            $context =
                nwmd_directory_get_current_operator_checkpoint_context();

            $run_id = absint($context['run_id'] ?? 0);

            if ($run_id < 1) {
                return new WP_Error(
                    'nwmd_operator_draft_run_missing',
                    __(
                        'The active checkpoint has no started run.',
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
                    'nwmd_operator_draft_preview_missing',
                    __(
                        'No stored research preview is available.',
                        'local-directory-framework'
                    )
                );
            }

            if (
                isset($preview['draft_creation'])
                && is_array($preview['draft_creation'])
                && !empty($preview['draft_creation'])
            ) {
                return new WP_Error(
                    'nwmd_operator_draft_already_created',
                    __(
                        'Business drafts were already created for this operator run.',
                        'local-directory-framework'
                    )
                );
            }

            $validation =
                nwmd_directory_validate_operator_preview_duplicates(
                    $run_id
                );

            if (is_wp_error($validation)) {
                return $validation;
            }

            $totals = isset($validation['totals'])
                && is_array($validation['totals'])
                ? $validation['totals']
                : [];

            if (
                absint($totals['review_required'] ?? 0) > 0
                || absint($totals['ready_for_draft'] ?? 0) < 1
            ) {
                return new WP_Error(
                    'nwmd_operator_draft_duplicate_review_failed',
                    __(
                        'Possible duplicate matches must be resolved and at least one business must be ready before draft creation.',
                        'local-directory-framework'
                    )
                );
            }

            $preview =
                nwmd_directory_get_operator_research_preview_result(
                    $run_id
                );

            $items = nwmd_directory_build_operator_draft_items(
                $context,
                $preview,
                $validation
            );

            if (is_wp_error($items)) {
                return $items;
            }

            if (
                count($items)
                !== absint($totals['ready_for_draft'] ?? 0)
            ) {
                return new WP_Error(
                    'nwmd_operator_draft_validation_count_mismatch',
                    __(
                        'The preview and duplicate-review counts do not match.',
                        'local-directory-framework'
                    )
                );
            }

            $validated =
                nwmd_directory_validate_operator_draft_items($items);

            if (is_wp_error($validated)) {
                return $validated;
            }

            $created_post_ids = [];
            $imported =
                nwmd_directory_import_operator_draft_items(
                    $items,
                    $created_post_ids
                );

            if (is_wp_error($imported)) {
                return $imported;
            }

            $counter_result =
                nwmd_directory_record_operator_draft_counters(
                    $context,
                    count($created_post_ids)
                );

            if (is_wp_error($counter_result)) {
                nwmd_directory_rollback_business_csv_import(
                    $created_post_ids
                );

                return $counter_result;
            }

            $preview['draft_creation'] = [
                'draft_creation_version' => 1,
                'status'                 => 'created',
                'created_at'             => current_time('mysql'),
                'created_by'             => get_current_user_id(),
                'business_count'         => count($created_post_ids),
                'business_post_ids'      =>
                    array_values(
                        array_map('absint', $created_post_ids)
                    ),
            ];

            $saved =
                nwmd_directory_save_operator_research_preview(
                    $run_id,
                    $preview
                );

            if (is_wp_error($saved)) {
                nwmd_directory_reset_operator_draft_counters(
                    $context
                );

                nwmd_directory_rollback_business_csv_import(
                    $created_post_ids
                );

                return $saved;
            }

            return $preview['draft_creation'];
        }
    );
}

/**
 * Store one short-lived supervised draft notice.
 *
 * @param array $notice Notice data.
 */
function nwmd_directory_store_operator_draft_notice($notice) {

    set_transient(
        'nwmd_operator_draft_creation_' . get_current_user_id(),
        is_array($notice) ? $notice : [],
        MINUTE_IN_SECONDS
    );
}

/**
 * Return and delete the current supervised draft notice.
 *
 * @return array
 */
function nwmd_directory_get_operator_draft_notice() {

    $key = 'nwmd_operator_draft_creation_'
        . get_current_user_id();

    $notice = get_transient($key);

    delete_transient($key);

    return is_array($notice) ? $notice : [];
}

/**
 * Handle supervised Business draft creation.
 */
function nwmd_directory_handle_operator_draft_creation() {

    if (!current_user_can('manage_options')) {
        wp_die(
            esc_html__(
                'You do not have permission to perform this action.',
                'local-directory-framework'
            )
        );
    }

    check_admin_referer(
        'nwmd_directory_operator_create_business_drafts'
    );

    $result =
        nwmd_directory_create_operator_ready_business_drafts();

    if (is_wp_error($result)) {
        nwmd_directory_store_operator_draft_notice(
            [
                'success' => false,
                'message' => $result->get_error_message(),
            ]
        );
    } else {
        nwmd_directory_store_operator_draft_notice(
            [
                'success' => true,
                'result'  => $result,
            ]
        );
    }

    $redirect_url = add_query_arg(
        [
            'post_type'     => 'nwmd_business',
            'page'          => 'nwmd-data-operator',
            'draft_created' => '1',
        ],
        admin_url('edit.php')
    );

    wp_safe_redirect($redirect_url);
    exit;
}

add_action(
    'admin_post_nwmd_directory_operator_create_business_drafts',
    'nwmd_directory_handle_operator_draft_creation'
);

/**
 * Render created Business draft links.
 *
 * @param array $draft_creation Stored draft-creation result.
 */
function nwmd_directory_render_operator_created_drafts(
    $draft_creation
) {

    $post_ids = isset($draft_creation['business_post_ids'])
        && is_array($draft_creation['business_post_ids'])
        ? array_values(
            array_filter(
                array_map(
                    'absint',
                    $draft_creation['business_post_ids']
                )
            )
        )
        : [];

    ?>
    <p>
        <strong>
            <?php
            echo esc_html(
                sprintf(
                    /* translators: %d: Created Business draft count. */
                    _n(
                        '%d Business draft was created.',
                        '%d Business drafts were created.',
                        count($post_ids),
                        'local-directory-framework'
                    ),
                    count($post_ids)
                )
            );
            ?>
        </strong>
    </p>

    <?php if (!empty($post_ids)) : ?>
        <ul>
            <?php foreach ($post_ids as $post_id) : ?>
                <?php
                $post = get_post($post_id);

                if (!$post instanceof WP_Post) {
                    continue;
                }

                $edit_url = get_edit_post_link($post_id, 'raw');
                ?>
                <li>
                    <?php if (is_string($edit_url) && '' !== $edit_url) : ?>
                        <a
                            href="<?php echo esc_url($edit_url); ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <?php echo esc_html(get_the_title($post_id)); ?>
                        </a>
                    <?php else : ?>
                        <?php echo esc_html(get_the_title($post_id)); ?>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <p>
        <?php
        echo esc_html__(
            'The checkpoint remains in progress. Review every draft before completing it.',
            'local-directory-framework'
        );
        ?>
    </p>
    <?php
}

/**
 * Render the supervised Business draft approval section.
 *
 * @param int   $run_id  Current operator run ID.
 * @param array $preview Stored research preview.
 */
function nwmd_directory_render_operator_draft_approval_section(
    $run_id,
    $preview
) {

    $run_id = absint($run_id);

    if ($run_id < 1 || empty($preview)) {
        return;
    }

    $notice = [];

    if (
        isset($_GET['draft_created'])
        && '1' === sanitize_text_field(
            wp_unslash($_GET['draft_created'])
        )
    ) {
        $notice = nwmd_directory_get_operator_draft_notice();
    }

    $validation = isset($preview['duplicate_validation'])
        && is_array($preview['duplicate_validation'])
        ? $preview['duplicate_validation']
        : [];

    $draft_creation = isset($preview['draft_creation'])
        && is_array($preview['draft_creation'])
        ? $preview['draft_creation']
        : [];

    ?>
    <h4>
        <?php
        echo esc_html__(
            'Supervised Business draft creation',
            'local-directory-framework'
        );
        ?>
    </h4>

    <p>
        <?php
        echo esc_html__(
            'This no-cost action rechecks duplicates, validates every mapped field, and creates unpublished Business drafts with pending research sources. It does not contact OpenAI, publish records, create Deals or rankings, or complete the checkpoint.',
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
                            'The supervised Business drafts were created.',
                            'local-directory-framework'
                        )
                        : (string) (
                            $notice['message']
                            ?? __(
                                'Business draft creation failed.',
                                'local-directory-framework'
                            )
                        )
                );
                ?>
            </p>
        </div>
    <?php endif; ?>

    <?php if (!empty($draft_creation)) : ?>
        <?php
        nwmd_directory_render_operator_created_drafts(
            $draft_creation
        );
        ?>
        <?php return; ?>
    <?php endif; ?>

    <?php if (empty($validation)) : ?>
        <p>
            <?php
            echo esc_html__(
                'Complete the preview duplicate review before creating drafts.',
                'local-directory-framework'
            );
            ?>
        </p>
        <?php return; ?>
    <?php endif; ?>

    <?php
    $totals = isset($validation['totals'])
        && is_array($validation['totals'])
        ? $validation['totals']
        : [];

    $ready   = absint($totals['ready_for_draft'] ?? 0);
    $review  = absint($totals['review_required'] ?? 0);
    $blocked = absint($totals['blocked_duplicate'] ?? 0);
    ?>

    <?php if ($review > 0) : ?>
        <p>
            <?php
            echo esc_html__(
                'Draft creation is blocked because one or more businesses have possible matches that require manual review.',
                'local-directory-framework'
            );
            ?>
        </p>
        <?php return; ?>
    <?php endif; ?>

    <?php if ($ready < 1) : ?>
        <p>
            <?php
            echo esc_html(
                $blocked > 0
                    ? __(
                        'Every preview business is a confirmed duplicate. No Business drafts are needed, and the checkpoint may be completed.',
                        'local-directory-framework'
                    )
                    : __(
                        'The preview returned no businesses. No Business drafts are needed, and the checkpoint may be completed.',
                        'local-directory-framework'
                    )
            );
            ?>
        </p>
        <?php return; ?>
    <?php endif; ?>

    <?php if ($blocked > 0) : ?>
        <p>
            <?php
            echo esc_html(
                sprintf(
                    _n(
                        '%s confirmed duplicate will be skipped. Only the ready business will be created as a draft.',
                        '%s confirmed duplicates will be skipped. Only the ready businesses will be created as drafts.',
                        $blocked,
                        'local-directory-framework'
                    ),
                    number_format_i18n($blocked)
                )
            );
            ?>
        </p>
    <?php endif; ?>

    <form
        method="post"
        action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
    >
        <input
            type="hidden"
            name="action"
            value="nwmd_directory_operator_create_business_drafts"
        >

        <?php
        wp_nonce_field(
            'nwmd_directory_operator_create_business_drafts'
        );

        submit_button(
            sprintf(
                /* translators: %d: Ready Business draft count. */
                _n(
                    'Create %d Business Draft',
                    'Create %d Business Drafts',
                    $ready,
                    'local-directory-framework'
                ),
                $ready
            ),
            'primary',
            'submit',
            false,
            [
                'onclick' => "return confirm('Create the validated Business drafts in WordPress? They will remain unpublished and the checkpoint will stay in progress.');",
            ]
        );
        ?>
    </form>
    <?php
}