<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return the operator usage table name.
 *
 * @return string
 */
function nwmd_directory_get_operator_usage_table_name() {

    $tables = nwmd_directory_get_operator_table_names();

    return isset($tables['usage'])
        ? (string) $tables['usage']
        : '';
}

/**
 * Return whether one run already has a non-cancelled research preview.
 *
 * @param int $run_id Operator run ID.
 *
 * @return bool
 */
function nwmd_directory_operator_run_has_research_preview($run_id) {

    global $wpdb;

    $run_id      = absint($run_id);
    $usage_table =
        nwmd_directory_get_operator_usage_table_name();

    if (
        $run_id < 1
        || '' === $usage_table
        || !nwmd_directory_operator_table_exists($usage_table)
    ) {
        return false;
    }

    $count = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*)
            FROM {$usage_table}
            WHERE run_id = %d
                AND operation_type = %s
                AND status <> %s",
            $run_id,
            'research_preview',
            'cancelled'
        )
    );

    return absint($count) > 0;
}

/**
 * Return the latest usage record for one research preview.
 *
 * @param int $run_id Operator run ID.
 *
 * @return object|null
 */
function nwmd_directory_get_operator_research_preview_usage($run_id) {

    global $wpdb;

    $run_id      = absint($run_id);
    $usage_table =
        nwmd_directory_get_operator_usage_table_name();

    if (
        $run_id < 1
        || '' === $usage_table
        || !nwmd_directory_operator_table_exists($usage_table)
    ) {
        return null;
    }

    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT *
            FROM {$usage_table}
            WHERE run_id = %d
                AND operation_type = %s
            ORDER BY id DESC
            LIMIT 1",
            $run_id,
            'research_preview'
        )
    );

    return is_object($row) ? $row : null;
}

/**
 * Return the current run result summary.
 *
 * @param int $run_id Operator run ID.
 *
 * @return array
 */
function nwmd_directory_get_operator_research_preview_result($run_id) {

    global $wpdb;

    $run_id = absint($run_id);

    if ($run_id < 1) {
        return [];
    }

    $tables = nwmd_directory_get_operator_table_names();

    $json = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT result_summary
            FROM {$tables['runs']}
            WHERE id = %d
            LIMIT 1",
            $run_id
        )
    );

    if (!is_string($json) || '' === trim($json)) {
        return [];
    }

    $decoded = json_decode($json, true);

    return is_array($decoded) ? $decoded : [];
}

/**
 * Return the strict JSON schema for one research preview.
 *
 * @return array
 */
function nwmd_directory_get_operator_research_preview_schema() {

    $business_properties = [
        'business_name' => [
            'type'        => 'string',
            'description' => 'Public business name.',
        ],
        'business_slug' => [
            'type'        => 'string',
            'description' => 'Lowercase WordPress-style slug.',
        ],
        'website_url' => [
            'type'        => 'string',
            'description' => 'Official public business website URL.',
        ],
        'public_phone' => [
            'type'        => 'string',
            'description' => 'Public phone or an empty string.',
        ],
        'street_address' => [
            'type'        => 'string',
            'description' => 'Public street address or an empty string.',
        ],
        'postal_code' => [
            'type'        => 'string',
            'description' => 'Postal code or an empty string.',
        ],
        'description' => [
            'type'        => 'string',
            'description' => 'Original factual summary in one or two sentences.',
        ],
        'source_name' => [
            'type'        => 'string',
            'description' => 'Readable name of the supporting source.',
        ],
        'source_url' => [
            'type'        => 'string',
            'description' => 'Public HTTP or HTTPS source URL.',
        ],
        'source_notes' => [
            'type'        => 'string',
            'description' => 'Short note describing what the source supports.',
        ],
        'confidence' => [
            'type' => 'string',
            'enum' => [
                'high',
                'medium',
                'low',
            ],
        ],
    ];

    return [
        'type'                 => 'object',
        'additionalProperties' => false,
        'properties'           => [
            'research_status' => [
                'type' => 'string',
                'enum' => [
                    'complete',
                    'partial',
                    'no_results',
                ],
            ],
            'summary' => [
                'type' => 'string',
            ],
            'businesses' => [
                'type'  => 'array',
                'items' => [
                    'type'                 => 'object',
                    'additionalProperties' => false,
                    'properties'           => $business_properties,
                    'required'             =>
                        array_keys($business_properties),
                ],
            ],
        ],
        'required' => [
            'research_status',
            'summary',
            'businesses',
        ],
    ];
}

/**
 * Build the live research prompt for one checkpoint.
 *
 * @param array $context Operator checkpoint context.
 *
 * @return string
 */
function nwmd_directory_build_operator_research_preview_prompt(
    $context
) {

    return sprintf(
        implode(
            "\n",
            [
                'Research local businesses for an editorial directory preview.',
                '',
                'Location:',
                'State: %1$s',
                'City: %2$s',
                'Category: %3$s',
                'Specialty: %4$s',
                '',
                'Rules:',
                '- Use live web search.',
                '- Return no more than 3 businesses.',
                '- Include only businesses that clearly serve the named city and match the specialty.',
                '- Prefer the official business website and official public records.',
                '- Every returned business must have a public source_url.',
                '- Do not use review text, copied descriptions, or unsupported claims.',
                '- Write an original factual description.',
                '- Do not invent missing contact details.',
                '- Use an empty string for an optional field that cannot be verified.',
                '- Exclude a business when identity, location, specialty, or source support is uncertain.',
                '- If no sufficiently supported businesses are found, return no_results with an empty businesses array.',
                '- This is a preview only. Do not imply that records were published or saved.',
            ]
        ),
        sanitize_text_field(
            (string) ($context['state_name'] ?? '')
        ),
        sanitize_text_field(
            (string) ($context['city_name'] ?? '')
        ),
        sanitize_text_field(
            (string) ($context['category_name'] ?? '')
        ),
        sanitize_text_field(
            (string) ($context['specialty_name'] ?? '')
        )
    );
}

/**
 * Return a stable privacy-preserving API safety identifier.
 *
 * @return string
 */
function nwmd_directory_get_operator_safety_identifier() {

    return 'nwmd_'
        . substr(
            hash_hmac(
                'sha256',
                home_url('/'),
                wp_salt('auth')
            ),
            0,
            32
        );
}

/**
 * Extract the structured output text from one Responses API result.
 *
 * @param array $response_body Decoded API response.
 *
 * @return string|WP_Error
 */
function nwmd_directory_extract_operator_response_text(
    $response_body
) {

    $output = isset($response_body['output'])
        && is_array($response_body['output'])
        ? $response_body['output']
        : [];

    foreach ($output as $item) {
        if (
            !is_array($item)
            || 'message' !== (string) ($item['type'] ?? '')
        ) {
            continue;
        }

        $content = isset($item['content'])
            && is_array($item['content'])
            ? $item['content']
            : [];

        foreach ($content as $part) {
            if (
                !is_array($part)
                || 'output_text' !==
                    (string) ($part['type'] ?? '')
            ) {
                continue;
            }

            $text = (string) ($part['text'] ?? '');

            if ('' !== trim($text)) {
                return $text;
            }
        }
    }

    return new WP_Error(
        'nwmd_operator_preview_output_missing',
        __(
            'OpenAI returned no structured preview text.',
            'local-directory-framework'
        )
    );
}

/**
 * Count web search calls in one Responses API result.
 *
 * @param array $response_body Decoded API response.
 *
 * @return int
 */
function nwmd_directory_count_operator_web_search_calls(
    $response_body
) {

    $output = isset($response_body['output'])
        && is_array($response_body['output'])
        ? $response_body['output']
        : [];

    $count = 0;

    foreach ($output as $item) {
        if (
            is_array($item)
            && 'web_search_call' ===
                (string) ($item['type'] ?? '')
        ) {
            $count++;
        }
    }

    return $count;
}

/**
 * Calculate a conservative recorded cost for the configured pilot model.
 *
 * Current rates are isolated here so they can be updated without changing
 * the research workflow.
 *
 * @param int $input_tokens        Total input tokens.
 * @param int $cached_input_tokens Cached input tokens.
 * @param int $output_tokens       Output tokens.
 * @param int $web_search_calls    Web search calls.
 *
 * @return int Cost in micro-dollars.
 */
function nwmd_directory_calculate_operator_preview_cost_micros(
    $input_tokens,
    $cached_input_tokens,
    $output_tokens,
    $web_search_calls
) {

    $input_tokens        = absint($input_tokens);
    $cached_input_tokens = min(
        $input_tokens,
        absint($cached_input_tokens)
    );
    $uncached_tokens     =
        $input_tokens - $cached_input_tokens;
    $output_tokens       = absint($output_tokens);
    $web_search_calls    = absint($web_search_calls);

    $uncached_cost = (int) ceil(
        ($uncached_tokens * 2000000) / 1000000
    );
    $cached_cost = (int) ceil(
        ($cached_input_tokens * 200000) / 1000000
    );
    $output_cost = (int) ceil(
        ($output_tokens * 12000000) / 1000000
    );
    $search_cost = $web_search_calls * 10000;

    return $uncached_cost
        + $cached_cost
        + $output_cost
        + $search_cost;
}

/**
 * Validate and normalize one structured research preview.
 *
 * @param mixed $preview Decoded structured output.
 *
 * @return array|WP_Error
 */
function nwmd_directory_validate_operator_research_preview(
    $preview
) {

    if (!is_array($preview)) {
        return new WP_Error(
            'nwmd_operator_preview_invalid',
            __(
                'The structured research preview is invalid.',
                'local-directory-framework'
            )
        );
    }

    $allowed_statuses = [
        'complete',
        'partial',
        'no_results',
    ];

    $status = sanitize_key(
        (string) ($preview['research_status'] ?? '')
    );

    if (!in_array($status, $allowed_statuses, true)) {
        return new WP_Error(
            'nwmd_operator_preview_status_invalid',
            __(
                'The research preview returned an invalid status.',
                'local-directory-framework'
            )
        );
    }

    $businesses = isset($preview['businesses'])
        && is_array($preview['businesses'])
        ? array_slice($preview['businesses'], 0, 3)
        : [];

    $normalized = [];

    foreach ($businesses as $business) {
        if (!is_array($business)) {
            continue;
        }

        $name = sanitize_text_field(
            (string) ($business['business_name'] ?? '')
        );
        $slug = sanitize_title(
            (string) ($business['business_slug'] ?? '')
        );
        $website_url = esc_url_raw(
            (string) ($business['website_url'] ?? ''),
            [
                'http',
                'https',
            ]
        );
        $source_url = esc_url_raw(
            (string) ($business['source_url'] ?? ''),
            [
                'http',
                'https',
            ]
        );

        if (
            '' === $name
            || '' === $slug
            || '' === $source_url
        ) {
            continue;
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

        $normalized[] = [
            'business_name' => $name,
            'business_slug' => $slug,
            'website_url'   => $website_url,
            'public_phone'  => sanitize_text_field(
                (string) ($business['public_phone'] ?? '')
            ),
            'street_address' => sanitize_text_field(
                (string) ($business['street_address'] ?? '')
            ),
            'postal_code' => sanitize_text_field(
                (string) ($business['postal_code'] ?? '')
            ),
            'description' => sanitize_textarea_field(
                (string) ($business['description'] ?? '')
            ),
            'source_name' => sanitize_text_field(
                (string) ($business['source_name'] ?? '')
            ),
            'source_url' => $source_url,
            'source_notes' => sanitize_textarea_field(
                (string) ($business['source_notes'] ?? '')
            ),
            'confidence' => $confidence,
        ];
    }

    if ('no_results' === $status) {
        $normalized = [];
    }

    return [
        'research_status' => $status,
        'summary'         => sanitize_textarea_field(
            (string) ($preview['summary'] ?? '')
        ),
        'businesses'      => $normalized,
    ];
}

/**
 * Save a preview result to the current operator run.
 *
 * @param int   $run_id  Operator run ID.
 * @param array $preview Preview data.
 *
 * @return true|WP_Error
 */
function nwmd_directory_save_operator_research_preview(
    $run_id,
    $preview
) {

    global $wpdb;

    $run_id = absint($run_id);

    if ($run_id < 1 || !is_array($preview)) {
        return new WP_Error(
            'nwmd_operator_preview_save_invalid',
            __(
                'The research preview could not be saved.',
                'local-directory-framework'
            )
        );
    }

    $tables = nwmd_directory_get_operator_table_names();
    $now    = current_time('mysql');

    $updated = $wpdb->update(
        $tables['runs'],
        [
            'result_summary' => wp_json_encode(
                $preview,
                JSON_UNESCAPED_SLASHES
            ),
            'error_message'  => '',
            'updated_at'     => $now,
        ],
        [
            'id'     => $run_id,
            'status' => 'started',
        ],
        [
            '%s',
            '%s',
            '%s',
        ],
        [
            '%d',
            '%s',
        ]
    );

    if (false === $updated) {
        return new WP_Error(
            'nwmd_operator_preview_save_failed',
            __(
                'The operator run could not store the research preview.',
                'local-directory-framework'
            )
        );
    }

    return true;
}

/**
 * Save one preview error to the current operator run.
 *
 * @param int    $run_id  Operator run ID.
 * @param string $message Safe error message.
 */
function nwmd_directory_save_operator_research_preview_error(
    $run_id,
    $message
) {

    global $wpdb;

    $tables = nwmd_directory_get_operator_table_names();

    $wpdb->update(
        $tables['runs'],
        [
            'error_message' =>
                sanitize_textarea_field($message),
            'updated_at'    => current_time('mysql'),
        ],
        [
            'id'     => absint($run_id),
            'status' => 'started',
        ],
        [
            '%s',
            '%s',
        ],
        [
            '%d',
            '%s',
        ]
    );
}

/**
 * Reserve the current checkpoint for one supervised live preview.
 *
 * @return array|WP_Error
 */
function nwmd_directory_prepare_operator_research_preview() {

    return nwmd_directory_with_operator_lock(
        function () {

            $settings =
                nwmd_directory_get_operator_budget_settings();

            if (empty($settings['test_mode'])) {
                return new WP_Error(
                    'nwmd_operator_preview_test_mode_required',
                    __(
                        'Test mode must remain enabled for the live preview.',
                        'local-directory-framework'
                    )
                );
            }

            $configuration =
                nwmd_directory_get_openai_configuration_status();

            if (empty($configuration['configured'])) {
                return new WP_Error(
                    'nwmd_operator_preview_openai_missing',
                    __(
                        'OpenAI is not configured.',
                        'local-directory-framework'
                    )
                );
            }

            if (
                'gpt-5.6-terra'
                !== (string) ($configuration['model'] ?? '')
            ) {
                return new WP_Error(
                    'nwmd_operator_preview_model_unsupported',
                    __(
                        'The supervised preview currently requires gpt-5.6-terra.',
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
                    'nwmd_operator_preview_run_missing',
                    __(
                        'Start one operator item before running a live preview.',
                        'local-directory-framework'
                    )
                );
            }

            if (
                nwmd_directory_operator_run_has_research_preview(
                    $run_id
                )
            ) {
                return new WP_Error(
                    'nwmd_operator_preview_already_exists',
                    __(
                        'This operator run already has a research preview.',
                        'local-directory-framework'
                    )
                );
            }

            $reservation =
                nwmd_directory_reserve_operator_usage(
                    [
                        'run_id'                => $run_id,
                        'model'                 =>
                            (string) $configuration['model'],
                        'operation_type'        =>
                            'research_preview',
                        'estimated_cost_micros' => 250000,
                    ]
                );

            if (is_wp_error($reservation)) {
                return $reservation;
            }

            return [
                'context'       => $context,
                'reservation'   => $reservation,
                'model'         =>
                    (string) $configuration['model'],
            ];
        }
    );
}

/**
 * Execute one supervised live research preview.
 *
 * No Business, source, Deal, ranking, or checkpoint record is created,
 * completed, or changed by this function.
 *
 * @return array|WP_Error
 */
function nwmd_directory_run_operator_research_preview() {

    $prepared = nwmd_directory_prepare_operator_research_preview();

    if (is_wp_error($prepared)) {
        return $prepared;
    }

    $context = $prepared['context'];
    $reservation = $prepared['reservation'];
    $model = $prepared['model'];
    $request_uuid = sanitize_text_field(
        (string) ($reservation['request_uuid'] ?? '')
    );
    $run_id = absint($context['run_id'] ?? 0);

    nwmd_directory_update_operator_usage(
        $request_uuid,
        [
            'model'  => $model,
            'status' => 'started',
        ]
    );

    $payload = [
        'model'             => $model,
        'store'             => false,
        'reasoning'         => [
            'effort' => 'low',
        ],
        'tools'             => [
            [
                'type' => 'web_search',
            ],
        ],
        'tool_choice'       => 'required',
        'include'           => [
            'web_search_call.action.sources',
        ],
        'max_output_tokens' => 2500,
        'safety_identifier' =>
            nwmd_directory_get_operator_safety_identifier(),
        'input'             =>
            nwmd_directory_build_operator_research_preview_prompt(
                $context
            ),
        'text'              => [
            'verbosity' => 'low',
            'format'    => [
                'type'        => 'json_schema',
                'name'        => 'nwmd_business_research_preview',
                'strict'      => true,
                'schema'      =>
                    nwmd_directory_get_operator_research_preview_schema(),
            ],
        ],
    ];

    $response = wp_remote_post(
        'https://api.openai.com/v1/responses',
        [
            'headers' => [
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer '
                    . nwmd_directory_get_openai_api_key(),
                'Content-Type'  => 'application/json',
            ],
            'body'        => wp_json_encode($payload),
            'timeout'     => 120,
            'redirection' => 0,
            'sslverify'   => true,
            'user-agent'  => 'NW-Monthly-Data-Operator/'
                . NWMD_DIRECTORY_VERSION,
        ]
    );

    if (is_wp_error($response)) {
        $message = sprintf(
            /* translators: %s: WordPress HTTP error. */
            __(
                'The live research request failed: %s',
                'local-directory-framework'
            ),
            $response->get_error_message()
        );

        nwmd_directory_update_operator_usage(
            $request_uuid,
            [
                'model'         => $model,
                'status'        => 'error',
                'error_message' => $message,
            ]
        );

        nwmd_directory_save_operator_research_preview_error(
            $run_id,
            $message
        );

        return new WP_Error(
            'nwmd_operator_preview_http_failed',
            $message
        );
    }

    $status_code = absint(
        wp_remote_retrieve_response_code($response)
    );
    $body = wp_remote_retrieve_body($response);
    $decoded = json_decode($body, true);

    if (!is_array($decoded)) {
        $message = __(
            'OpenAI returned an unreadable response.',
            'local-directory-framework'
        );

        nwmd_directory_update_operator_usage(
            $request_uuid,
            [
                'model'         => $model,
                'status'        => 'error',
                'error_message' => $message,
            ]
        );

        nwmd_directory_save_operator_research_preview_error(
            $run_id,
            $message
        );

        return new WP_Error(
            'nwmd_operator_preview_response_invalid',
            $message
        );
    }

    $input_tokens = absint(
        $decoded['usage']['input_tokens'] ?? 0
    );
    $cached_input_tokens = absint(
        $decoded['usage']['input_tokens_details']['cached_tokens']
            ?? 0
    );
    $output_tokens = absint(
        $decoded['usage']['output_tokens'] ?? 0
    );
    $web_search_calls =
        nwmd_directory_count_operator_web_search_calls(
            $decoded
        );

    $recorded_cost_micros =
        nwmd_directory_calculate_operator_preview_cost_micros(
            $input_tokens,
            $cached_input_tokens,
            $output_tokens,
            $web_search_calls
        );

    if (
        200 !== $status_code
        || 'completed' !== (string) ($decoded['status'] ?? '')
    ) {
        $message = '';

        if (
            isset($decoded['error']['message'])
            && is_string($decoded['error']['message'])
        ) {
            $message = sanitize_text_field(
                $decoded['error']['message']
            );
        }

        if ('' === $message) {
            $message = sprintf(
                /* translators: %d: HTTP status code. */
                __(
                    'OpenAI did not complete the preview. HTTP status: %d.',
                    'local-directory-framework'
                ),
                $status_code
            );
        }

        nwmd_directory_update_operator_usage(
            $request_uuid,
            [
                'response_id'          =>
                    sanitize_text_field(
                        (string) ($decoded['id'] ?? '')
                    ),
                'model'                => $model,
                'status'               => 'error',
                'input_tokens'         => $input_tokens,
                'cached_input_tokens'  =>
                    $cached_input_tokens,
                'output_tokens'        => $output_tokens,
                'web_search_calls'     => $web_search_calls,
                'recorded_cost_micros' =>
                    $recorded_cost_micros,
                'error_message'        => $message,
            ]
        );

        nwmd_directory_save_operator_research_preview_error(
            $run_id,
            $message
        );

        return new WP_Error(
            'nwmd_operator_preview_incomplete',
            $message
        );
    }

    $output_text =
        nwmd_directory_extract_operator_response_text($decoded);

    if (is_wp_error($output_text)) {
        nwmd_directory_update_operator_usage(
            $request_uuid,
            [
                'response_id'          =>
                    sanitize_text_field(
                        (string) ($decoded['id'] ?? '')
                    ),
                'model'                => $model,
                'status'               => 'error',
                'input_tokens'         => $input_tokens,
                'cached_input_tokens'  =>
                    $cached_input_tokens,
                'output_tokens'        => $output_tokens,
                'web_search_calls'     => $web_search_calls,
                'recorded_cost_micros' =>
                    $recorded_cost_micros,
                'error_message'        =>
                    $output_text->get_error_message(),
            ]
        );

        nwmd_directory_save_operator_research_preview_error(
            $run_id,
            $output_text->get_error_message()
        );

        return $output_text;
    }

    $preview = json_decode($output_text, true);
    $preview =
        nwmd_directory_validate_operator_research_preview(
            $preview
        );

    if (is_wp_error($preview)) {
        nwmd_directory_update_operator_usage(
            $request_uuid,
            [
                'response_id'          =>
                    sanitize_text_field(
                        (string) ($decoded['id'] ?? '')
                    ),
                'model'                => $model,
                'status'               => 'error',
                'input_tokens'         => $input_tokens,
                'cached_input_tokens'  =>
                    $cached_input_tokens,
                'output_tokens'        => $output_tokens,
                'web_search_calls'     => $web_search_calls,
                'recorded_cost_micros' =>
                    $recorded_cost_micros,
                'error_message'        =>
                    $preview->get_error_message(),
            ]
        );

        nwmd_directory_save_operator_research_preview_error(
            $run_id,
            $preview->get_error_message()
        );

        return $preview;
    }

    $saved = nwmd_directory_save_operator_research_preview(
        $run_id,
        [
            'preview_version'      => 1,
            'response_id'          =>
                sanitize_text_field(
                    (string) ($decoded['id'] ?? '')
                ),
            'model'                => $model,
            'research_status'      =>
                $preview['research_status'],
            'summary'              => $preview['summary'],
            'businesses'           => $preview['businesses'],
            'input_tokens'         => $input_tokens,
            'cached_input_tokens'  =>
                $cached_input_tokens,
            'output_tokens'        => $output_tokens,
            'web_search_calls'     => $web_search_calls,
            'recorded_cost_micros' =>
                $recorded_cost_micros,
            'completed_at'         => current_time('mysql'),
        ]
    );

    if (is_wp_error($saved)) {
        nwmd_directory_update_operator_usage(
            $request_uuid,
            [
                'response_id'          =>
                    sanitize_text_field(
                        (string) ($decoded['id'] ?? '')
                    ),
                'model'                => $model,
                'status'               => 'error',
                'input_tokens'         => $input_tokens,
                'cached_input_tokens'  =>
                    $cached_input_tokens,
                'output_tokens'        => $output_tokens,
                'web_search_calls'     => $web_search_calls,
                'recorded_cost_micros' =>
                    $recorded_cost_micros,
                'error_message'        =>
                    $saved->get_error_message(),
            ]
        );

        return $saved;
    }

    nwmd_directory_update_operator_usage(
        $request_uuid,
        [
            'response_id'          =>
                sanitize_text_field(
                    (string) ($decoded['id'] ?? '')
                ),
            'model'                => $model,
            'status'               => 'complete',
            'input_tokens'         => $input_tokens,
            'cached_input_tokens'  =>
                $cached_input_tokens,
            'output_tokens'        => $output_tokens,
            'web_search_calls'     => $web_search_calls,
            'recorded_cost_micros' =>
                $recorded_cost_micros,
        ]
    );

    return [
        'action'          => 'research_preview',
        'state_name'      =>
            (string) ($context['state_name'] ?? ''),
        'city_name'       =>
            (string) ($context['city_name'] ?? ''),
        'category_name'   =>
            (string) ($context['category_name'] ?? ''),
        'specialty_name'  =>
            (string) ($context['specialty_name'] ?? ''),
        'business_count'  =>
            count($preview['businesses']),
        'recorded_cost_micros' =>
            $recorded_cost_micros,
    ];
}

/**
 * Store one short-lived live-preview notice.
 *
 * @param array $notice Notice payload.
 */
function nwmd_directory_store_operator_research_preview_notice(
    $notice
) {

    set_transient(
        'nwmd_operator_research_preview_'
            . get_current_user_id(),
        is_array($notice) ? $notice : [],
        MINUTE_IN_SECONDS
    );
}

/**
 * Return and delete the current user's live-preview notice.
 *
 * @return array
 */
function nwmd_directory_get_operator_research_preview_notice() {

    $key = 'nwmd_operator_research_preview_'
        . get_current_user_id();

    $notice = get_transient($key);

    delete_transient($key);

    return is_array($notice) ? $notice : [];
}

/**
 * Handle the supervised live research preview.
 */
function nwmd_directory_handle_operator_research_preview() {

    if (!current_user_can('manage_options')) {
        wp_die(
            esc_html__(
                'You do not have permission to perform this action.',
                'local-directory-framework'
            )
        );
    }

    check_admin_referer(
        'nwmd_directory_operator_research_preview'
    );

    $result = nwmd_directory_run_operator_research_preview();

    if (is_wp_error($result)) {
        nwmd_directory_store_operator_research_preview_notice(
            [
                'success' => false,
                'message' => $result->get_error_message(),
            ]
        );
    } else {
        nwmd_directory_store_operator_research_preview_notice(
            [
                'success' => true,
                'result'  => $result,
            ]
        );
    }

    $redirect_url = add_query_arg(
        [
            'post_type'       => 'nwmd_business',
            'page'            => 'nwmd-data-operator',
            'research_preview'=> '1',
        ],
        admin_url('edit.php')
    );

    wp_safe_redirect($redirect_url);
    exit;
}

add_action(
    'admin_post_nwmd_directory_operator_research_preview',
    'nwmd_directory_handle_operator_research_preview'
);

/**
 * Render one live-preview result table.
 *
 * @param array $preview Preview result.
 */
function nwmd_directory_render_operator_research_preview_result(
    $preview
) {

    $businesses = isset($preview['businesses'])
        && is_array($preview['businesses'])
        ? $preview['businesses']
        : [];

    ?>
    <h4>
        <?php
        echo esc_html__(
            'Latest preview result',
            'local-directory-framework'
        );
        ?>
    </h4>

    <p>
        <strong>
            <?php
            echo esc_html__(
                'Status:',
                'local-directory-framework'
            );
            ?>
        </strong>
        <?php
        echo esc_html(
            (string) ($preview['research_status'] ?? '')
        );
        ?>
    </p>

    <?php if ('' !== (string) ($preview['summary'] ?? '')) : ?>
        <p>
            <?php
            echo esc_html(
                (string) $preview['summary']
            );
            ?>
        </p>
    <?php endif; ?>

    <?php if (!empty($businesses)) : ?>
        <table class="widefat striped" style="max-width: 1100px;">
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
                            'Website',
                            'local-directory-framework'
                        );
                        ?>
                    </th>
                    <th>
                        <?php
                        echo esc_html__(
                            'Source',
                            'local-directory-framework'
                        );
                        ?>
                    </th>
                    <th>
                        <?php
                        echo esc_html__(
                            'Confidence',
                            'local-directory-framework'
                        );
                        ?>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($businesses as $business) : ?>
                    <tr>
                        <td>
                            <strong>
                                <?php
                                echo esc_html(
                                    (string) (
                                        $business['business_name']
                                        ?? ''
                                    )
                                );
                                ?>
                            </strong>
                            <?php
                            $description = (string) (
                                $business['description'] ?? ''
                            );
                            ?>
                            <?php if ('' !== $description) : ?>
                                <br>
                                <?php
                                echo esc_html($description);
                                ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $website_url = (string) (
                                $business['website_url'] ?? ''
                            );
                            ?>
                            <?php if ('' !== $website_url) : ?>
                                <a
                                    href="<?php
                                        echo esc_url($website_url);
                                    ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <?php
                                    echo esc_html($website_url);
                                    ?>
                                </a>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $source_url = (string) (
                                $business['source_url'] ?? ''
                            );
                            ?>
                            <a
                                href="<?php
                                    echo esc_url($source_url);
                                ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <?php
                                echo esc_html(
                                    (string) (
                                        $business['source_name']
                                        ?? $source_url
                                    )
                                );
                                ?>
                            </a>
                        </td>
                        <td>
                            <?php
                            echo esc_html(
                                (string) (
                                    $business['confidence'] ?? ''
                                )
                            );
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <p>
        <?php
        echo esc_html(
            sprintf(
                /* translators: 1: input, 2: output, 3: searches, 4: cost. */
                __(
                    'Usage: %1$d input tokens, %2$d output tokens, %3$d web search calls. Recorded estimated cost: %4$s.',
                    'local-directory-framework'
                ),
                absint($preview['input_tokens'] ?? 0),
                absint($preview['output_tokens'] ?? 0),
                absint($preview['web_search_calls'] ?? 0),
                nwmd_directory_format_operator_micros(
                    absint(
                        $preview['recorded_cost_micros'] ?? 0
                    )
                )
            )
        );
        ?>
    </p>
    <?php
}

/**
 * Render the supervised live research preview section.
 */
function nwmd_directory_render_operator_research_preview_section() {

    $context =
        nwmd_directory_get_current_operator_checkpoint_context();

    $notice = [];

    if (
        isset($_GET['research_preview'])
        && '1' === sanitize_text_field(
            wp_unslash($_GET['research_preview'])
        )
    ) {
        $notice =
            nwmd_directory_get_operator_research_preview_notice();
    }

    ?>
    <h2>
        <?php
        echo esc_html__(
            'Supervised live research preview',
            'local-directory-framework'
        );
        ?>
    </h2>

    <div class="notice notice-warning inline">
        <p>
            <?php
            echo esc_html__(
                'This pilot performs one paid web-research request and stores only a preview in the operator run. It does not create Business drafts, sources, Deals, rankings, or complete the checkpoint.',
                'local-directory-framework'
            );
            ?>
        </p>
    </div>

    <?php if (!empty($notice)) : ?>
        <div class="notice <?php
            echo !empty($notice['success'])
                ? 'notice-success'
                : 'notice-error';
        ?> inline">
            <p>
                <?php if (!empty($notice['success'])) : ?>
                    <?php
                    $result = isset($notice['result'])
                        && is_array($notice['result'])
                        ? $notice['result']
                        : [];
                    ?>
                    <?php
                    echo esc_html(
                        sprintf(
                            /* translators: 1: business count, 2: cost. */
                            __(
                                'Live preview completed with %1$d supported businesses. Recorded estimated cost: %2$s.',
                                'local-directory-framework'
                            ),
                            absint(
                                $result['business_count'] ?? 0
                            ),
                            nwmd_directory_format_operator_micros(
                                absint(
                                    $result[
                                        'recorded_cost_micros'
                                    ] ?? 0
                                )
                            )
                        )
                    );
                    ?>
                <?php else : ?>
                    <?php
                    echo esc_html(
                        (string) (
                            $notice['message']
                            ?? __(
                                'The live research preview failed.',
                                'local-directory-framework'
                            )
                        )
                    );
                    ?>
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>

    <?php if (empty($context)) : ?>
        <p>
            <?php
            echo esc_html__(
                'Start one operator item first. Then return here to run its supervised preview.',
                'local-directory-framework'
            );
            ?>
        </p>
        <?php
        return;
    endif;

    $run_id = absint($context['run_id'] ?? 0);
    $preview =
        nwmd_directory_get_operator_research_preview_result(
            $run_id
        );
    $usage =
        nwmd_directory_get_operator_research_preview_usage(
            $run_id
        );

    if (!empty($preview)) {
        nwmd_directory_render_operator_research_preview_result(
            $preview
        );
        ?>
        <p>
            <strong>
                <?php
                echo esc_html__(
                    'Review required:',
                    'local-directory-framework'
                );
                ?>
            </strong>
            <?php
            echo esc_html__(
                'Do not complete this checkpoint. The next plugin version will validate the preview against existing WordPress records before any drafts can be created.',
                'local-directory-framework'
            );
            ?>
        </p>
        <?php
        return;
    }

    if (is_object($usage)) {
        ?>
        <p>
            <?php
            echo esc_html(
                sprintf(
                    /* translators: %s: usage status. */
                    __(
                        'A preview attempt already exists for this run with status: %s. Release the current item before starting another run.',
                        'local-directory-framework'
                    ),
                    sanitize_text_field(
                        (string) $usage->status
                    )
                )
            );
            ?>
        </p>
        <?php
        return;
    }

    $settings =
        nwmd_directory_get_operator_budget_settings();

    if (empty($settings['test_mode'])) {
        ?>
        <p>
            <?php
            echo esc_html__(
                'Enable test mode before running the supervised preview.',
                'local-directory-framework'
            );
            ?>
        </p>
        <?php
        return;
    }

    ?>
    <p>
        <?php
        echo esc_html(
            sprintf(
                /* translators: 1: state, 2: city, 3: category, 4: specialty. */
                __(
                    'Ready for: %1$s | %2$s | %3$s | %4$s',
                    'local-directory-framework'
                ),
                (string) ($context['state_name'] ?? ''),
                (string) ($context['city_name'] ?? ''),
                (string) ($context['category_name'] ?? ''),
                (string) ($context['specialty_name'] ?? '')
            )
        );
        ?>
    </p>

    <form
        method="post"
        action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
    >
        <input
            type="hidden"
            name="action"
            value="nwmd_directory_operator_research_preview"
        >
        <?php
        wp_nonce_field(
            'nwmd_directory_operator_research_preview'
        );

        submit_button(
            __(
                'Run One Live Research Preview',
                'local-directory-framework'
            ),
            'primary',
            'submit',
            false,
            [
                'onclick' => "return confirm('Run one paid OpenAI web-research preview with a hard reservation of $0.25? No directory records will be created.');",
            ]
        );
        ?>
    </form>
    <?php
}
