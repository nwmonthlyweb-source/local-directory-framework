<?php

if (!defined('ABSPATH')) {
    exit;
}

$categories = nwmd_directory_get_launch_categories();

$manage_url = nwmd_directory_get_business_request_url();

$app_settings = nwmd_directory_get_app_settings();

$first_page_enabled = !empty(
    $app_settings['first_page_enabled']
);

$first_page_title =
    $app_settings['first_page_title'];

$first_page_subtitle =
    $app_settings['first_page_subtitle'];

$first_page_region =
    $app_settings['first_page_region'];

$first_page_duration_ms = max(
    300,
    (int) round(
        (float) $app_settings['first_page_duration']
        * 1000
    )
);

$first_page_frequency =
    $app_settings['first_page_frequency'];

$splash_icon_url =
    nwmd_directory_get_app_setting_image_url(
        'first_page_icon_id',
        'assets/icons/nw-monthly-192.png'
    );


$icons = [
    'restaurants' => '
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M7 3v8M4 3v5a3 3 0 0 0 6 0V3M7 11v10M17 3v18M17 3c3 3 3 7 0 10"/>
        </svg>
    ',
    'contractors' => '
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="m14 6 4 4M5 19l9-9M3 21l4-1-3-3-1 4ZM13 5l2-2 6 6-2 2"/>
        </svg>
    ',
    'dentists' => '
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M12 5c-2-3-7-2-7 3 0 3 2 4 2 8 0 3 1 5 3 5 1 0 1-5 2-5s1 5 2 5c2 0 3-2 3-5 0-4 2-5 2-8 0-5-5-6-7-3Z"/>
        </svg>
    ',
    'auto-services' => '
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="m5 16-1 3M19 16l1 3M4 16h16M6 16v2M18 16v2M5 12l2-5h10l2 5"/>
            <circle cx="7" cy="14" r="1"/>
            <circle cx="17" cy="14" r="1"/>
        </svg>
    ',
    'health-wellness' => '
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M20 8c0 6-8 11-8 11S4 14 4 8a4 4 0 0 1 7-3 4 4 0 0 1 9 3Z"/>
            <path d="M8 11h2l1-3 2 6 1-3h2"/>
        </svg>
    ',
    'realtors' => '
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="m3 11 9-8 9 8M5 10v11h14V10M9 21v-7h6v7"/>
        </svg>
    ',
    'beauty-personal-care' => '
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="m12 3 1.3 4.2L17 9l-3.7 1.8L12 15l-1.3-4.2L7 9l3.7-1.8L12 3Z"/>
            <path d="m19 14 .8 2.2L22 17l-2.2.8L19 20l-.8-2.2L16 17l2.2-.8L19 14ZM5 13l.7 1.8L7.5 16l-1.8.7L5 18.5l-.7-1.8L2.5 16l1.8-.7L5 13Z"/>
        </svg>
    ',
    'pet-services' => '
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <circle cx="7" cy="8" r="2"/>
            <circle cx="17" cy="8" r="2"/>
            <circle cx="5" cy="13" r="2"/>
            <circle cx="19" cy="13" r="2"/>
            <path d="M12 11c-4 0-6 4-4 7 1 2 3 1 4 1s3 1 4-1c2-3 0-7-4-7Z"/>
        </svg>
    ',
];

$allowed_svg = [
    'svg' => [
        'viewBox'    => true,
        'aria-hidden' => true,
    ],
    'path' => [
        'd' => true,
    ],
    'circle' => [
        'cx' => true,
        'cy' => true,
        'r'  => true,
    ],
];
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >
    <?php if ($first_page_enabled) : ?>

        <script id="nwmd-first-page-bootstrap">
            (function () {
                'use strict';

                var root = document.documentElement;

                root.classList.add('nwmd-js');

                <?php if ('session' === $first_page_frequency) : ?>
                try {
                    if (
                        window.sessionStorage.getItem(
                            'nwmd_app_first_page_seen'
                        ) === '1'
                    ) {
                        root.classList.add(
                            'nwmd-first-page-seen'
                        );
                    }
                } catch (error) {
                    // Continue when browser storage is unavailable.
                }
                <?php endif; ?>
            })();
        </script>

        <style id="nwmd-first-page-critical">
            .nwmd-app-first-page {
                display: none;
            }

            html.nwmd-js:not(.nwmd-first-page-seen)
            body.nwmd-first-page-enabled {
                overflow: hidden;
                background: #f4f7fb;
                color: #172033;
            }

            html.nwmd-js:not(.nwmd-first-page-seen)
            body.nwmd-first-page-enabled
            .nwmd-app-home {
                display: none;
            }

            html.nwmd-js:not(.nwmd-first-page-seen)
            body.nwmd-first-page-enabled
            .nwmd-app-first-page {
                display: grid;
                width: 100%;
                min-height: 100vh;
                min-height: 100dvh;
                place-items: center;
                padding: 24px;
                box-sizing: border-box;
                background: #f4f7fb;
                color: #172033;
                text-align: center;
            }

            .nwmd-app-first-page__content {
                display: grid;
                justify-items: center;
                width: min(640px, 100%);
            }

            .nwmd-app-first-page__icon {
                display: block;
                width: 82px;
                height: 82px;
                margin: 0 0 24px;
                border: 1px solid #bfdbfe;
                border-radius: 21px;
                background: #ffffff;
                object-fit: cover;
            }

            .nwmd-app-first-page__title {
                margin: 0;
                color: #172033;
                font-size: clamp(2.7rem, 11vw, 5rem);
                font-weight: 900;
                letter-spacing: -0.055em;
                line-height: 1;
            }

            .nwmd-app-first-page__subtitle {
                margin: 20px 0 0;
                color: #526078;
                font-size: clamp(1.25rem, 5vw, 2rem);
                font-weight: 800;
                line-height: 1.2;
            }

            .nwmd-app-first-page__region {
                margin: 18px 0 0;
                color: #2563eb;
                font-size: clamp(0.85rem, 3.5vw, 1.05rem);
                font-weight: 800;
                letter-spacing: 0.1em;
                line-height: 1.2;
                text-transform: uppercase;
            }

            html.nwmd-first-page-seen
            body.nwmd-first-page-enabled
            .nwmd-app-first-page {
                display: none;
            }
        </style>
    <?php endif; ?>

    <?php wp_head(); ?>
</head>

<?php
$body_classes = [
    'nwmd-app-body',
];

if ($first_page_enabled) {
    $body_classes[] = 'nwmd-first-page-enabled';
}
?>
<body <?php body_class(implode(' ', $body_classes)); ?>>
<?php wp_body_open(); ?>

<?php if ($first_page_enabled) : ?>
    <section
        class="nwmd-app-first-page"
        data-nwmd-first-page
        data-duration="<?php
            echo esc_attr(
                (string) $first_page_duration_ms
            );
        ?>"
        data-frequency="<?php
            echo esc_attr($first_page_frequency);
        ?>"
        role="status"
        aria-label="<?php
            echo esc_attr__(
                'NW Monthly introduction',
                'local-directory-framework'
            );
        ?>"

    >
        <div class="nwmd-app-first-page__content">
            <img
                class="nwmd-app-first-page__icon"
                src="<?php echo esc_url($splash_icon_url); ?>"
                width="192"
                height="192"
                alt=""
                aria-hidden="true"
                decoding="async"
                fetchpriority="high"
            >

            <p class="nwmd-app-first-page__title">
                <?php echo esc_html($first_page_title); ?>
            </p>

            <p class="nwmd-app-first-page__subtitle">
                <?php echo esc_html($first_page_subtitle); ?>
            </p>

            <p class="nwmd-app-first-page__region">
                <?php echo esc_html($first_page_region); ?>
            </p>
        </div>
    </section>
<?php endif; ?>

<main
    class="nwmd-app-home"
    id="primary"
    data-nwmd-app-home
>
    <section class="nwmd-app-home__panel">
        <header class="nwmd-app-home__header">
            <div class="nwmd-app-home__brand">
                <span
                    class="nwmd-app-home__mark"
                    aria-hidden="true"
                >
                    NW
                </span>

                <span>NW Monthly</span>
            </div>

            <p class="nwmd-app-home__region">
                <?php
                echo esc_html__(
                    'Washington · Oregon',
                    'local-directory-framework'
                );
                ?>
            </p>

            <h1>
                <?php
                echo esc_html__(
                    'Find a business',
                    'local-directory-framework'
                );
                ?>
            </h1>

            <p class="nwmd-app-home__intro">
                <?php
                echo esc_html__(
                    'Choose a category first.',
                    'local-directory-framework'
                );
                ?>
            </p>
        </header>

        <nav
            class="nwmd-app-categories"
            aria-label="<?php
                echo esc_attr__(
                    'Business categories',
                    'local-directory-framework'
                );
            ?>"
        >
            <?php foreach ($categories as $category) : ?>
                <?php
                $category_slug = sanitize_title(
                    $category['slug']
                );

                $icon = $icons[$category_slug] ?? '';
                ?>

                <a
                    class="nwmd-app-category"
                    href="<?php echo esc_url(
                        nwmd_directory_get_app_category_url(
                            $category_slug
                        )
                    ); ?>"
                >
                    <span
                        class="nwmd-app-category__icon"
                        aria-hidden="true"
                    >
                        <?php
                        echo wp_kses(
                            $icon,
                            $allowed_svg
                        );
                        ?>
                    </span>

                    <strong>
                        <?php echo esc_html($category['name']); ?>
                    </strong>

                    <span
                        class="nwmd-app-category__arrow"
                        aria-hidden="true"
                    >
                        →
                    </span>
                </a>
            <?php endforeach; ?>
        </nav>

        <?php nwmd_directory_render_app_footer(); ?>
    </section>
</main>

<?php wp_footer(); ?>
</body>
</html>
