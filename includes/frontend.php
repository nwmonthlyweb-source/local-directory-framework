<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return whether the current request uses the app interface.
 *
 * @return bool
 */
function nwmd_directory_is_app_surface() {

    return (
        is_front_page() ||
        is_post_type_archive('nwmd_business') ||
        is_singular('nwmd_business') ||
        nwmd_directory_is_business_request_page()
    );
}

/**
 * Return a public URL for an app resource.
 *
 * @param string $resource Resource name.
 *
 * @return string
 */
function nwmd_directory_get_app_resource_url($resource) {

    return add_query_arg(
        'nwmd_app_resource',
        sanitize_key($resource),
        home_url('/')
    );
}

/**
 * Serve the web app manifest and service worker.
 */
function nwmd_directory_maybe_serve_app_resource() {

    if (
        !isset($_GET['nwmd_app_resource']) ||
        !is_string($_GET['nwmd_app_resource'])
    ) {
        return;
    }

    $resource = sanitize_key(
        wp_unslash($_GET['nwmd_app_resource'])
    );

    if (
        'manifest' !== $resource &&
        'service-worker' !== $resource
    ) {
        return;
    }

    $app_url = trailingslashit(home_url('/'));

    $scope_path = wp_parse_url(
        $app_url,
        PHP_URL_PATH
    );

    if (
        !is_string($scope_path) ||
        '' === $scope_path
    ) {
        $scope_path = '/';
    }

    $scope_path = trailingslashit($scope_path);

    status_header(200);
    nocache_headers();

    header('X-Content-Type-Options: nosniff');

    if ('manifest' === $resource) {
        $manifest = [
            'id'               => $app_url,
            'name'             => 'NW Monthly Directory',
            'short_name'       => 'NW Monthly',
            'description'      => 'Local business directory for Washington and Oregon.',
            'lang'             => get_bloginfo('language'),
            'start_url'        => $app_url,
            'scope'            => $app_url,
            'display'          => 'standalone',
            'background_color' => '#f4f7fb',
            'theme_color'      => '#2563eb',
            'icons'            => [
                [
                    'src'     => NWMD_DIRECTORY_URL
                        . 'assets/icons/nw-monthly-192.png',
                    'sizes'   => '192x192',
                    'type'    => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src'     => NWMD_DIRECTORY_URL
                        . 'assets/icons/nw-monthly-512.png',
                    'sizes'   => '512x512',
                    'type'    => 'image/png',
                    'purpose' => 'any maskable',
                ],
            ],
        ];

        header(
            'Content-Type: application/manifest+json; charset=utf-8'
        );

        echo wp_json_encode(
            $manifest,
            JSON_UNESCAPED_SLASHES |
            JSON_UNESCAPED_UNICODE
        );

        exit;
    }

    header(
        'Content-Type: application/javascript; charset=utf-8'
    );

    header(
        'Service-Worker-Allowed: ' . $scope_path
    );

    $service_worker = <<<'JS'
self.addEventListener('install', function () {
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', function (event) {
    if ('GET' !== event.request.method) {
        return;
    }

    event.respondWith(
        fetch(event.request)
    );
});
JS;

    echo $service_worker;

    exit;
}

add_action(
    'template_redirect',
    'nwmd_directory_maybe_serve_app_resource',
    0
);

/**
 * Print app metadata in plugin-owned page heads.
 */
function nwmd_directory_render_app_head_meta() {

    if (!nwmd_directory_is_app_surface()) {
        return;
    }

    $manifest_url =
        nwmd_directory_get_app_resource_url('manifest');

    $apple_icon_url =
        NWMD_DIRECTORY_URL
        . 'assets/icons/nw-monthly-180.png';
    ?>

    <link
        rel="manifest"
        href="<?php echo esc_url($manifest_url); ?>"
    >

    <link
        rel="apple-touch-icon"
        sizes="180x180"
        href="<?php echo esc_url($apple_icon_url); ?>"
    >

    <meta
        name="theme-color"
        content="#2563eb"
    >

    <meta
        name="apple-mobile-web-app-capable"
        content="yes"
    >

    <meta
        name="apple-mobile-web-app-status-bar-style"
        content="default"
    >

    <meta
        name="apple-mobile-web-app-title"
        content="NW Monthly"
    >

    <?php
}

add_action(
    'wp_head',
    'nwmd_directory_render_app_head_meta',
    1
);

/**
 * Load only the public styles needed for the current screen.
 */
function nwmd_directory_enqueue_frontend_assets() {

    $is_app_home = is_front_page();

    $is_business_archive =
        is_post_type_archive('nwmd_business');

    $is_business_profile =
        is_singular('nwmd_business');

    $is_business_request =
        nwmd_directory_is_business_request_page();

    $is_public_rankings =
        nwmd_directory_is_public_rankings_page();

    $is_other_directory_page =
        $is_business_request ||
        $is_public_rankings;

    if (
        !$is_app_home &&
        !$is_business_archive &&
        !$is_business_profile &&
        !$is_other_directory_page
    ) {
        return;
    }

    if (
        $is_app_home ||
        $is_business_archive ||
        $is_business_profile ||
        $is_business_request
    ) {
        wp_enqueue_style(
            'nwmd-directory-app-common',
            NWMD_DIRECTORY_URL . 'assets/css/app-common.css',
            [],
            NWMD_DIRECTORY_VERSION
        );

        wp_enqueue_script(
            'nwmd-directory-app-common',
            NWMD_DIRECTORY_URL . 'assets/js/app-common.js',
            [],
            NWMD_DIRECTORY_VERSION,
            true
        );

        wp_localize_script(
            'nwmd-directory-app-common',
            'nwmdDirectoryApp',
            [
                'serviceWorkerUrl' =>
                    nwmd_directory_get_app_resource_url(
                        'service-worker'
                    ),
                'serviceWorkerScope' =>
                    trailingslashit(home_url('/')),
            ]
        );
    }
    if ($is_app_home) {
        wp_enqueue_style(
            'nwmd-directory-app-home',
            NWMD_DIRECTORY_URL . 'assets/css/app-home.css',
            [],
            NWMD_DIRECTORY_VERSION
        );
    }

    if ($is_app_home) {
        wp_enqueue_script(
            'nwmd-directory-app-home-script',
            NWMD_DIRECTORY_URL . 'assets/js/app-home.js',
            [],
            NWMD_DIRECTORY_VERSION,
            true
        );
    }
    if ($is_business_archive) {
        wp_enqueue_style(
            'nwmd-directory-app-list',
            NWMD_DIRECTORY_URL . 'assets/css/app-directory.css',
            [],
            NWMD_DIRECTORY_VERSION
        );
    }

    if ($is_business_profile) {
        wp_enqueue_style(
            'nwmd-directory-app-profile',
            NWMD_DIRECTORY_URL . 'assets/css/app-profile.css',
            [],
            NWMD_DIRECTORY_VERSION
        );
    }

    if ($is_other_directory_page) {
        wp_enqueue_style(
            'nwmd-directory-frontend',
            NWMD_DIRECTORY_URL . 'assets/css/frontend.css',
            [],
            NWMD_DIRECTORY_VERSION
        );
    }

    if ($is_business_request) {
        wp_enqueue_style(
            'nwmd-directory-app-request',
            NWMD_DIRECTORY_URL . 'assets/css/app-request.css',
            ['nwmd-directory-frontend'],
            NWMD_DIRECTORY_VERSION
        );
    }
}

add_action(
    'wp_enqueue_scripts',
    'nwmd_directory_enqueue_frontend_assets'
);

/**
 * Return the business archive URL.
 *
 * @return string
 */
function nwmd_directory_get_app_archive_url() {

    $archive_url = get_post_type_archive_link(
        'nwmd_business'
    );

    if (!$archive_url) {
        return home_url('/business/');
    }

    return $archive_url;
}

/**
 * Return the business archive URL for one category.
 *
 * @param string $category_slug Category slug.
 *
 * @return string
 */
function nwmd_directory_get_app_category_url(
    $category_slug
) {

    return add_query_arg(
        [
            'filter_category' => sanitize_title(
                $category_slug
            ),
        ],
        nwmd_directory_get_app_archive_url()
    );
}

/**
 * Return the business archive URL for one category and specialty.
 *
 * Use the special value "all" when visitors choose all services.
 *
 * @param string $category_slug  Category slug.
 * @param string $specialty_slug Specialty slug or "all".
 *
 * @return string
 */
function nwmd_directory_get_app_specialty_url(
    $category_slug,
    $specialty_slug = 'all'
) {

    return add_query_arg(
        [
            'filter_category' => sanitize_title(
                $category_slug
            ),
            'filter_specialty' => sanitize_title(
                $specialty_slug
            ),
        ],
        nwmd_directory_get_app_archive_url()
    );
}

/**
 * Return the business archive URL for one category, specialty, and city.
 *
 * @param string $category_slug  Category slug.
 * @param string $specialty_slug Specialty slug or "all".
 * @param string $city_slug      City slug.
 *
 * @return string
 */
function nwmd_directory_get_app_city_url(
    $category_slug,
    $specialty_slug,
    $city_slug
) {

    return add_query_arg(
        [
            'filter_category' => sanitize_title(
                $category_slug
            ),
            'filter_specialty' => sanitize_title(
                $specialty_slug
            ),
            'filter_city' => sanitize_title(
                $city_slug
            ),
        ],
        nwmd_directory_get_app_archive_url()
    );
}

/**
 * Use plugin-owned public templates.
 *
 * @param string $template Current WordPress template path.
 *
 * @return string
 */
function nwmd_directory_template_include($template) {

    if (is_front_page()) {
        $app_template = NWMD_DIRECTORY_PATH
            . 'templates/app-home.php';

        if (is_readable($app_template)) {
            return $app_template;
        }
    }

    if (is_post_type_archive('nwmd_business')) {
        $plugin_template = NWMD_DIRECTORY_PATH
            . 'templates/archive-nwmd_business.php';

        if (is_readable($plugin_template)) {
            return $plugin_template;
        }
    }

    if (is_singular('nwmd_business')) {
        $plugin_template = NWMD_DIRECTORY_PATH
            . 'templates/single-nwmd_business.php';

        if (is_readable($plugin_template)) {
            return $plugin_template;
        }
    }

    return $template;
}

add_filter(
    'template_include',
    'nwmd_directory_template_include'
);

/**
 * Return business term names.
 *
 * @param int    $post_id  Business post ID.
 * @param string $taxonomy Directory taxonomy.
 *
 * @return array
 */
function nwmd_directory_get_business_term_names(
    $post_id,
    $taxonomy
) {

    $terms = get_the_terms(
        $post_id,
        $taxonomy
    );

    if (is_wp_error($terms) || empty($terms)) {
        return [];
    }

    return array_values(
        wp_list_pluck(
            $terms,
            'name'
        )
    );
}

/**
 * Return one sanitized archive filter value.
 *
 * @param string $key Filter query-string key.
 *
 * @return string
 */
function nwmd_directory_get_archive_filter_value($key) {

    $allowed_keys = [
        'filter_category',
        'filter_specialty',
        'filter_city',
    ];

    if (
        !in_array($key, $allowed_keys, true) ||
        !isset($_GET[$key]) ||
        !is_string($_GET[$key])
    ) {
        return '';
    }

    return sanitize_title(
        wp_unslash($_GET[$key])
    );
}

/**
 * Return selectable terms that have published businesses.
 *
 * @param string $taxonomy Directory taxonomy.
 *
 * @return array
 */
function nwmd_directory_get_archive_filter_terms($taxonomy) {

    if (!taxonomy_exists($taxonomy)) {
        return [];
    }

    $terms = get_terms(
        [
            'taxonomy'   => $taxonomy,
            'hide_empty' => true,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ]
    );

    if (is_wp_error($terms)) {
        return [];
    }

    return $terms;
}

/**
 * Filter the public business archive.
 *
 * @param WP_Query $query Current WordPress query.
 */
function nwmd_directory_filter_archive_query($query) {

    if (
        is_admin() ||
        !$query->is_main_query() ||
        !$query->is_post_type_archive('nwmd_business')
    ) {
        return;
    }

    $filters = [
        'filter_category'  => 'nwmd_category',
        'filter_specialty' => 'nwmd_specialty',
        'filter_city'      => 'nwmd_city',
    ];

    $tax_query = [];

    foreach ($filters as $filter_key => $taxonomy) {

        $slug = nwmd_directory_get_archive_filter_value(
            $filter_key
        );

        if ('' === $slug) {
            continue;
        }

        if (
            'filter_specialty' === $filter_key &&
            'all' === $slug
        ) {
            continue;
        }

        $term = get_term_by(
            'slug',
            $slug,
            $taxonomy
        );

        if (!$term instanceof WP_Term) {
            continue;
        }

        $tax_query[] = [
            'taxonomy' => $taxonomy,
            'field'    => 'slug',
            'terms'    => [$slug],
        ];
    }

    if (empty($tax_query)) {
        return;
    }

    if (count($tax_query) > 1) {
        $tax_query['relation'] = 'AND';
    }

    $query->set(
        'tax_query',
        $tax_query
    );
}

add_action(
    'pre_get_posts',
    'nwmd_directory_filter_archive_query',
    20
);
/**
 * Render the shared NW Monthly app footer.
 *
 * @param bool $show_manage Whether to show the Manage a Business link.
 */
function nwmd_directory_render_app_footer($show_manage = true) {

    $year = wp_date('Y');

    $manage_url =
        nwmd_directory_get_business_request_url();
    ?>

    <footer class="nwmd-site-footer">
        <?php if ($show_manage) : ?>
            <a
                class="nwmd-site-footer__manage"
                href="<?php echo esc_url($manage_url); ?>"
            >
                <?php
                echo esc_html__(
                    'Manage a Business',
                    'local-directory-framework'
                );
                ?>
            </a>
        <?php endif; ?>

        <div class="nwmd-site-footer__install-wrap">
            <button
                type="button"
                class="nwmd-site-footer__install"
                data-nwmd-install
                hidden
            >
                <svg
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <path
                        d="M12 3v11m0 0 4-4m-4 4-4-4M5 17v3h14v-3"
                    />
                </svg>

                <span>
                    <?php
                    echo esc_html__(
                        'Add Directory To Home Screen',
                        'local-directory-framework'
                    );
                    ?>
                </span>
            </button>
        </div>

        <div class="nwmd-site-footer__meta">
            <p>
                <?php
                echo esc_html(
                    sprintf(
                        /* translators: %s is the current year. */
                        __(
                            'Copyright © %s NW Monthly',
                            'local-directory-framework'
                        ),
                        $year
                    )
                );
                ?>
            </p>

            <nav
                class="nwmd-site-footer__links"
                aria-label="<?php
                    echo esc_attr__(
                        'Site information',
                        'local-directory-framework'
                    );
                ?>"
            >
                <button
                    type="button"
                    data-nwmd-dialog-open="nwmd-privacy-terms"
                >
                    <?php
                    echo esc_html__(
                        'Privacy & Terms',
                        'local-directory-framework'
                    );
                    ?>
                </button>

                <button
                    type="button"
                    data-nwmd-dialog-open="nwmd-about"
                >
                    <?php
                    echo esc_html__(
                        'About',
                        'local-directory-framework'
                    );
                    ?>
                </button>
            </nav>
        </div>

        <dialog
            class="nwmd-info-dialog"
            id="nwmd-privacy-terms"
            data-nwmd-dialog
        >
            <div class="nwmd-info-dialog__header">
                <h2>
                    <?php
                    echo esc_html__(
                        'Privacy & Terms',
                        'local-directory-framework'
                    );
                    ?>
                </h2>

                <button
                    type="button"
                    class="nwmd-info-dialog__close"
                    data-nwmd-dialog-close
                    aria-label="<?php
                        echo esc_attr__(
                            'Close Privacy and Terms',
                            'local-directory-framework'
                        );
                    ?>"
                >
                    &times;
                </button>
            </div>

            <div class="nwmd-info-dialog__content">
                <p>
                    <?php
                    echo esc_html__(
                        'NW Monthly publishes local business information for Washington and Oregon. Information submitted through listing and contact forms is used to review requests, manage listings, respond to users, prevent abuse, and operate the service.',
                        'local-directory-framework'
                    );
                    ?>
                </p>

                <p>
                    <?php
                    echo esc_html__(
                        'Public business details may appear on NW Monthly. Do not submit confidential information. Business information can change, so visitors should verify important details directly with the business.',
                        'local-directory-framework'
                    );
                    ?>
                </p>

                <p>
                    <?php
                    echo esc_html__(
                        'Advertising does not influence organic rankings. Sponsored placements are clearly labeled. Listings are provided for general information and are not a guarantee or endorsement.',
                        'local-directory-framework'
                    );
                    ?>
                </p>

                <p>
                    <?php
                    echo esc_html__(
                        'By using NW Monthly, you agree to use the service lawfully and not misuse its listing, contact, advertising, or request features.',
                        'local-directory-framework'
                    );
                    ?>
                </p>
            </div>
        </dialog>

        <dialog
            class="nwmd-info-dialog"
            id="nwmd-about"
            data-nwmd-dialog
        >
            <div class="nwmd-info-dialog__header">
                <h2>
                    <?php
                    echo esc_html__(
                        'About NW Monthly',
                        'local-directory-framework'
                    );
                    ?>
                </h2>

                <button
                    type="button"
                    class="nwmd-info-dialog__close"
                    data-nwmd-dialog-close
                    aria-label="<?php
                        echo esc_attr__(
                            'Close About',
                            'local-directory-framework'
                        );
                    ?>"
                >
                    &times;
                </button>
            </div>

            <div class="nwmd-info-dialog__content">
                <p>
                    <?php
                    echo esc_html__(
                        'NW Monthly is a local business directory app for Washington and Oregon. Choose a category and city to find local businesses, view profiles, and contact businesses directly.',
                        'local-directory-framework'
                    );
                    ?>
                </p>
            </div>
        </dialog>

        <dialog
            class="nwmd-info-dialog"
            id="nwmd-install-help"
            data-nwmd-dialog
        >
            <div class="nwmd-info-dialog__header">
                <h2>
                    <?php
                    echo esc_html__(
                        'Add NW Monthly',
                        'local-directory-framework'
                    );
                    ?>
                </h2>

                <button
                    type="button"
                    class="nwmd-info-dialog__close"
                    data-nwmd-dialog-close
                    aria-label="<?php
                        echo esc_attr__(
                            'Close installation instructions',
                            'local-directory-framework'
                        );
                    ?>"
                >
                    &times;
                </button>
            </div>

            <div class="nwmd-info-dialog__content">
                <p>
                    <?php
                    echo esc_html__(
                        'On iPhone or iPad, open NW Monthly in Safari, tap the Share button, then tap Add to Home Screen.',
                        'local-directory-framework'
                    );
                    ?>
                </p>

                <p>
                    <?php
                    echo esc_html__(
                        'On Android, open your browser menu and choose Install app or Add to Home screen.',
                        'local-directory-framework'
                    );
                    ?>
                </p>
            </div>
        </dialog>
    </footer>

    <?php
}
