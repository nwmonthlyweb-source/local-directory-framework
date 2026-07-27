<?php

if (!defined('ABSPATH')) {
    exit;
}

$categories = nwmd_directory_get_launch_categories();

$manage_url = nwmd_directory_get_business_request_url();

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

    <?php wp_head(); ?>
</head>

<body <?php body_class('nwmd-app-body'); ?>>
<?php wp_body_open(); ?>

<main class="nwmd-app-home" id="primary">
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
                    'What are you looking for?',
                    'local-directory-framework'
                );
                ?>
            </h1>

            <p class="nwmd-app-home__intro">
                <?php
                echo esc_html__(
                    'Choose a category, then choose your city.',
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

        <footer class="nwmd-app-home__footer">
            <p>
                <?php
                echo esc_html__(
                    'Own or manage a business?',
                    'local-directory-framework'
                );
                ?>
            </p>

            <a href="<?php echo esc_url($manage_url); ?>">
                <?php
                echo esc_html__(
                    'Add, claim, update, or remove a listing',
                    'local-directory-framework'
                );
                ?>
            </a>
        </footer>
    </section>
</main>

<?php wp_footer(); ?>
</body>
</html>