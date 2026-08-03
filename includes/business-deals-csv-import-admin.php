<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register the Business Deal CSV Import submenu.
 */
function nwmd_directory_register_business_deal_csv_page() {

    add_submenu_page(
        'edit.php?post_type=nwmd_business',
        __(
            'Business Deal CSV Import',
            'local-directory-framework'
        ),
        __(
            'Deals CSV',
            'local-directory-framework'
        ),
        'manage_options',
        'nwmd-business-deals-csv-import',
        'nwmd_directory_render_business_deal_csv_page'
    );
}

add_action(
    'admin_menu',
    'nwmd_directory_register_business_deal_csv_page',
    26
);

/**
 * Render the latest Deal CSV result.
 */
function nwmd_directory_render_business_deal_csv_result() {

    $result =
        nwmd_directory_take_business_deal_csv_result();

    if (empty($result)) {
        return;
    }

    $mode = isset($result['mode'])
        ? sanitize_key($result['mode'])
        : 'validation';

    $valid = !empty($result['valid']);

    $row_count = isset($result['row_count'])
        ? absint($result['row_count'])
        : 0;

    $created_count = isset($result['created_count'])
        ? absint($result['created_count'])
        : 0;

    $updated_count = isset($result['updated_count'])
        ? absint($result['updated_count'])
        : 0;

    $planned_create = isset($result['planned_create'])
        ? absint($result['planned_create'])
        : 0;

    $planned_update = isset($result['planned_update'])
        ? absint($result['planned_update'])
        : 0;

    $errors = isset($result['errors'])
        && is_array($result['errors'])
        ? $result['errors']
        : [];

    $error_count = isset($result['error_count'])
        ? absint($result['error_count'])
        : count($errors);

    $rolled_back = !empty(
        $result['rolled_back']
    );

    if (
        $valid &&
        'import' === $mode
    ) {
        ?>
        <div class="notice notice-success inline">
            <p>
                <?php
                echo esc_html(
                    sprintf(
                        /* translators: 1: Created Deals, 2: Updated Deals. */
                        __(
                            'Deal CSV import completed. %1$d created and %2$d updated.',
                            'local-directory-framework'
                        ),
                        $created_count,
                        $updated_count
                    )
                );
                ?>
            </p>
        </div>
        <?php

        return;
    }

    if ($valid) {
        ?>
        <div class="notice notice-success inline">
            <p>
                <?php
                echo esc_html(
                    sprintf(
                        /* translators: 1: Rows, 2: Creates, 3: Updates. */
                        __(
                            'Validation passed for %1$d rows: %2$d will be created and %3$d will be updated. No records were changed.',
                            'local-directory-framework'
                        ),
                        $row_count,
                        $planned_create,
                        $planned_update
                    )
                );
                ?>
            </p>
        </div>
        <?php

        return;
    }

    ?>
    <div class="notice notice-error inline">
        <p>
            <?php
            echo esc_html(
                sprintf(
                    /* translators: %d: Number of errors. */
                    _n(
                        'The Deal CSV contains %d error.',
                        'The Deal CSV contains %d errors.',
                        $error_count,
                        'local-directory-framework'
                    ),
                    $error_count
                )
            );
            ?>
        </p>

        <?php if ($rolled_back) : ?>
            <p>
                <?php
                echo esc_html__(
                    'No Deal changes were kept.',
                    'local-directory-framework'
                );
                ?>
            </p>
        <?php endif; ?>

        <?php if (!empty($errors)) : ?>
            <ul>
                <?php foreach ($errors as $error) : ?>
                    <li>
                        <?php echo esc_html($error); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Render the Deal CSV Import page.
 */
function nwmd_directory_render_business_deal_csv_page() {

    if (!current_user_can('manage_options')) {
        wp_die(
            esc_html__(
                'You are not allowed to import Deal CSV files.',
                'local-directory-framework'
            )
        );
    }

    $template_url = wp_nonce_url(
        add_query_arg(
            [
                'action' =>
                    'nwmd_download_business_deal_csv_template',
            ],
            admin_url('admin-post.php')
        ),
        'nwmd_download_business_deal_csv_template'
    );

    $columns =
        nwmd_directory_get_business_deal_csv_import_columns();

    ?>
    <div class="wrap">
        <h1>
            <?php
            echo esc_html__(
                'Business Deal CSV Import',
                'local-directory-framework'
            );
            ?>
        </h1>

        <p>
            <?php
            echo esc_html__(
                'Create new Deals or update existing Deals using business_slug and deal_slug. Business records are not modified.',
                'local-directory-framework'
            );
            ?>
        </p>

        <?php
        nwmd_directory_render_business_deal_csv_result();
        ?>

        <div class="card" style="max-width: 900px;">
            <h2>
                <?php
                echo esc_html__(
                    '1. Download the Deal CSV template',
                    'local-directory-framework'
                );
                ?>
            </h2>

            <p>
                <?php
                echo esc_html__(
                    'Keep every header in the template. Blank optional values are allowed.',
                    'local-directory-framework'
                );
                ?>
            </p>

            <p>
                <a
                    class="button button-secondary"
                    href="<?php echo esc_url(
                        $template_url
                    ); ?>"
                >
                    <?php
                    echo esc_html__(
                        'Download Deal CSV Template',
                        'local-directory-framework'
                    );
                    ?>
                </a>
            </p>
        </div>

        <div class="card" style="max-width: 900px;">
            <h2>
                <?php
                echo esc_html__(
                    '2. Validate or import the Deal CSV',
                    'local-directory-framework'
                );
                ?>
            </h2>

            <form
                method="post"
                action="<?php echo esc_url(
                    admin_url('admin-post.php')
                ); ?>"
                enctype="multipart/form-data"
            >
                <input
                    type="hidden"
                    name="action"
                    value="nwmd_process_business_deal_csv"
                >

                <?php
                wp_nonce_field(
                    'nwmd_import_business_deal_csv',
                    'nwmd_business_deal_csv_nonce'
                );
                ?>

                <p>
                    <label for="nwmd-business-deal-csv-file">
                        <strong>
                            <?php
                            echo esc_html__(
                                'Deal CSV file',
                                'local-directory-framework'
                            );
                            ?>
                        </strong>
                    </label>
                </p>

                <p>
                    <input
                        type="file"
                        id="nwmd-business-deal-csv-file"
                        name="business_deal_csv_file"
                        accept=".csv,text/csv"
                        required
                    >
                </p>

                <p class="description">
                    <?php
                    echo esc_html__(
                        'Maximum file size: 2 MB. Maximum import size: 500 Deal rows.',
                        'local-directory-framework'
                    );
                    ?>
                </p>

                <p>
                    <button
                        type="submit"
                        name="nwmd_business_deal_csv_operation"
                        value="validate"
                        class="button button-secondary"
                    >
                        <?php
                        echo esc_html__(
                            'Validate CSV',
                            'local-directory-framework'
                        );
                        ?>
                    </button>

                    <button
                        type="submit"
                        name="nwmd_business_deal_csv_operation"
                        value="import"
                        class="button button-primary"
                        onclick="return confirm('<?php echo esc_js(
                            __(
                                'Import this CSV? Matching Deals will be updated.',
                                'local-directory-framework'
                            )
                        ); ?>');"
                    >
                        <?php
                        echo esc_html__(
                            'Import and Update Deals',
                            'local-directory-framework'
                        );
                        ?>
                    </button>
                </p>
            </form>
        </div>

        <div class="card" style="max-width: 900px;">
            <h2>
                <?php
                echo esc_html__(
                    'CSV columns',
                    'local-directory-framework'
                );
                ?>
            </h2>

            <p>
                <code>
                    <?php
                    echo esc_html(
                        implode(
                            ',',
                            $columns
                        )
                    );
                    ?>
                </code>
            </p>

            <p>
                <?php
                echo esc_html__(
                    'Monthly updates change only the matching Deal identified by business_slug and deal_slug.',
                    'local-directory-framework'
                );
                ?>
            </p>
        </div>
    </div>
    <?php
}

/**
 * Download the header-only UTF-8 Deal CSV template.
 */
function nwmd_directory_download_business_deal_csv_template() {

    if (!current_user_can('manage_options')) {
        wp_die(
            esc_html__(
                'You are not allowed to download this template.',
                'local-directory-framework'
            )
        );
    }

    check_admin_referer(
        'nwmd_download_business_deal_csv_template'
    );

    $filename =
        'local-directory-business-deals-import-template.csv';

    nocache_headers();

    header(
        'Content-Type: text/csv; charset=utf-8'
    );

    header(
        'Content-Disposition: attachment; filename="' .
        $filename .
        '"'
    );

    $output = fopen(
        'php://output',
        'wb'
    );

    if (false === $output) {
        wp_die(
            esc_html__(
                'The Deal CSV template could not be generated.',
                'local-directory-framework'
            )
        );
    }

    fputcsv(
        $output,
        nwmd_directory_get_business_deal_csv_import_columns(),
        ',',
        '"',
        '\\'
    );

    fclose($output);

    exit;
}

add_action(
    'admin_post_nwmd_download_business_deal_csv_template',
    'nwmd_directory_download_business_deal_csv_template'
);
