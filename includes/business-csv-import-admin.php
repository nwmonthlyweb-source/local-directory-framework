<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return CSV columns in the required template order.
 *
 * @return array
 */
function nwmd_directory_get_business_csv_import_columns() {

    return [
        'business_name',
        'business_slug',
        'state_slug',
        'city_slug',
        'category_slug',
        'description',
        'excerpt',
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
        'specialty_slug',
        'source_type',
        'source_name',
        'source_url',
        'source_identifier',
        'source_notes',
        'source_retrieved_at',
        'source_verified_at',
        'source_verification_result',
    ];
}

/**
 * Register the Business CSV Import submenu.
 */
function nwmd_directory_register_business_csv_import_page() {

    add_submenu_page(
        'edit.php?post_type=nwmd_business',
        __('Business CSV Import', 'local-directory-framework'),
        __('CSV Import', 'local-directory-framework'),
        'manage_options',
        'nwmd-business-csv-import',
        'nwmd_directory_render_business_csv_import_page'
    );
}

add_action(
    'admin_menu',
    'nwmd_directory_register_business_csv_import_page',
    25
);

/**
 * Render the Business CSV Import admin page.
 */
function nwmd_directory_render_business_csv_import_page() {

    if (!current_user_can('manage_options')) {
        wp_die(
            esc_html__(
                'You are not allowed to import business records.',
                'local-directory-framework'
            )
        );
    }

    $template_url = wp_nonce_url(
        add_query_arg(
            [
                'action' => 'nwmd_download_business_csv_template',
            ],
            admin_url('admin-post.php')
        ),
        'nwmd_download_business_csv_template'
    );

    $columns = nwmd_directory_get_business_csv_import_columns();
    ?>
    <div class="wrap">
        <h1>
            <?php
            echo esc_html__(
                'Business CSV Import',
                'local-directory-framework'
            );
            ?>
        </h1>

        <p>
            <?php
            echo esc_html__(
                'Import researched business records into the directory using a controlled CSV template.',
                'local-directory-framework'
            );
            ?>
        </p>

        <?php nwmd_directory_render_business_csv_validation_result(); ?>

        <div class="notice notice-info inline">
            <p>
                <strong>
                    <?php
                    echo esc_html__(
                        'Validation mode:',
                        'local-directory-framework'
                    );
                    ?>
                </strong>

                <?php
                echo esc_html__(
                    'CSV validation is active. Record creation remains disabled until the import and rollback phase is complete.',
                    'local-directory-framework'
                );
                ?>
            </p>
        </div>

        <div class="card" style="max-width: 900px;">
            <h2>
                <?php
                echo esc_html__(
                    '1. Download the CSV template',
                    'local-directory-framework'
                );
                ?>
            </h2>

            <p>
                <?php
                echo esc_html__(
                    'Use the official template so every column is named and ordered correctly.',
                    'local-directory-framework'
                );
                ?>
            </p>

            <p>
                <a
                    class="button button-secondary"
                    href="<?php echo esc_url($template_url); ?>"
                >
                    <?php
                    echo esc_html__(
                        'Download CSV Template',
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
                    '2. Select the completed CSV',
                    'local-directory-framework'
                );
                ?>
            </h2>

            <form
                method="post"
                action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                enctype="multipart/form-data"
            >
                <input
                    type="hidden"
                    name="action"
                    value="nwmd_validate_business_csv"
                >
                <?php
                wp_nonce_field(
                    'nwmd_import_business_csv',
                    'nwmd_business_csv_nonce'
                );
                ?>

                <p>
                    <label for="nwmd_business_csv_file">
                        <strong>
                            <?php
                            echo esc_html__(
                                'CSV file',
                                'local-directory-framework'
                            );
                            ?>
                        </strong>
                    </label>
                </p>

                <p>
                    <input
                        type="file"
                        id="nwmd_business_csv_file"
                        name="business_csv_file"
                        accept=".csv,text/csv"
                    >
                </p>

                <p class="description">
                    <?php
                    echo esc_html__(
                        'Maximum file size: 2 MB. Maximum import size: 500 business rows.',
                        'local-directory-framework'
                    );
                    ?>
                </p>

                <p>
                    <button
                        type="submit"
                        class="button button-primary"
                    >
                        <?php
                        echo esc_html__(
                            'Validate CSV',
                            'local-directory-framework'
                        );
                        ?>
                    </button>
                </p>

                <p class="description">
                    <?php
                    echo esc_html__(
                        'Validation checks the complete file and does not create or modify records.',
                        'local-directory-framework'
                    );
                    ?>
                </p>
            </form>
        </div>

        <div class="card" style="max-width: 900px;">
            <h2>
                <?php
                echo esc_html__(
                    'Import protections',
                    'local-directory-framework'
                );
                ?>
            </h2>

            <ul style="list-style: disc; padding-left: 22px;">
                <li>
                    <?php
                    echo esc_html__(
                        'Imported businesses are created as drafts.',
                        'local-directory-framework'
                    );
                    ?>
                </li>
                <li>
                    <?php
                    echo esc_html__(
                        'Existing businesses are never overwritten.',
                        'local-directory-framework'
                    );
                    ?>
                </li>
                <li>
                    <?php
                    echo esc_html__(
                        'Only existing category, specialty, state, and city slugs are accepted.',
                        'local-directory-framework'
                    );
                    ?>
                </li>
                <li>
                    <?php
                    echo esc_html__(
                        'The complete file is validated before any records are created.',
                        'local-directory-framework'
                    );
                    ?>
                </li>
                <li>
                    <?php
                    echo esc_html__(
                        'Failed imports attempt to roll back all records created during that import.',
                        'local-directory-framework'
                    );
                    ?>
                </li>
            </ul>
        </div>

        <details style="max-width: 900px; margin-top: 20px;">
            <summary>
                <strong>
                    <?php
                    echo esc_html__(
                        'View supported CSV columns',
                        'local-directory-framework'
                    );
                    ?>
                </strong>
            </summary>

            <p>
                <?php foreach ($columns as $column) : ?>
                    <code><?php echo esc_html($column); ?></code>
                <?php endforeach; ?>
            </p>
        </details>
    </div>
    <?php
}

/**
 * Download the header-only UTF-8 CSV template.
 */
function nwmd_directory_download_business_csv_template() {

    if (!current_user_can('manage_options')) {
        wp_die(
            esc_html__(
                'You are not allowed to download this template.',
                'local-directory-framework'
            )
        );
    }

    check_admin_referer(
        'nwmd_download_business_csv_template'
    );

    $filename = 'local-directory-business-import-template.csv';
    $output = fopen('php://output', 'wb');

    if (false === $output) {
        wp_die(
            esc_html__(
                'The CSV template could not be generated.',
                'local-directory-framework'
            )
        );
    }

    nocache_headers();

    header('Content-Type: text/csv; charset=utf-8');
    header(
        'Content-Disposition: attachment; filename="' .
        $filename .
        '"'
    );
    header('X-Content-Type-Options: nosniff');

    fwrite($output, "\xEF\xBB\xBF");
    fputcsv(
        $output,
        nwmd_directory_get_business_csv_import_columns()
    );

    fclose($output);
    exit;
}

add_action(
    'admin_post_nwmd_download_business_csv_template',
    'nwmd_directory_download_business_csv_template'
);