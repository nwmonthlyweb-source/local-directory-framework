<?php

if (!defined('ABSPATH')) {
    exit;
}

$categories = nwmd_directory_get_launch_categories();
$specialty_map = nwmd_directory_get_launch_specialties();
$regions = nwmd_directory_get_launch_regions();

$get_slug = static function ($key) {

    if (
        !isset($_GET[$key]) ||
        !is_string($_GET[$key])
    ) {
        return '';
    }

    return sanitize_title(
        wp_unslash($_GET[$key])
    );
};

$current_state = $get_slug('nwmd_app_state');
$current_city = $get_slug('nwmd_app_city');
$current_category = $get_slug('nwmd_app_category');

$selected_region = null;

foreach ($regions as $region) {
    if ($region['slug'] === $current_state) {
        $selected_region = $region;
        break;
    }
}

if (!is_array($selected_region)) {
    $current_state = '';
    $current_city = '';
    $current_category = '';
}

$selected_city = null;

if (is_array($selected_region)) {
    foreach ($selected_region['cities'] as $city) {
        if ($city['slug'] === $current_city) {
            $selected_city = $city;
            break;
        }
    }
}

if (!is_array($selected_city)) {
    $current_city = '';
    $current_category = '';
}

$selected_category = null;

if (is_array($selected_city)) {
    foreach ($categories as $category) {
        if ($category['slug'] === $current_category) {
            $selected_category = $category;
            break;
        }
    }
}

if (!is_array($selected_category)) {
    $current_category = '';
}

$specialties = [];

if (is_array($selected_category)) {
    $specialties =
        $specialty_map[$selected_category['slug']]
        ?? [];
}

$home_url = home_url('/');

$state_url = static function ($state_slug) use ($home_url) {

    return add_query_arg(
        [
            'nwmd_app_state' => sanitize_title($state_slug),
        ],
        $home_url
    );
};

$city_url = static function (
    $state_slug,
    $city_slug
) use ($home_url) {

    return add_query_arg(
        [
            'nwmd_app_state' => sanitize_title($state_slug),
            'nwmd_app_city'  => sanitize_title($city_slug),
        ],
        $home_url
    );
};

$category_url = static function (
    $state_slug,
    $city_slug,
    $category_slug
) use ($home_url) {

    return add_query_arg(
        [
            'nwmd_app_state'    => sanitize_title($state_slug),
            'nwmd_app_city'     => sanitize_title($city_slug),
            'nwmd_app_category' => sanitize_title($category_slug),
        ],
        $home_url
    );
};

$back_url = $home_url;

if (is_array($selected_category)) {
    $back_url = $city_url(
        $selected_region['slug'],
        $selected_city['slug']
    );
} elseif (is_array($selected_city)) {
    $back_url = $state_url(
        $selected_region['slug']
    );
}
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

<main
    class="nwmd-app-home"
    id="primary"
    data-nwmd-app-home
>
    <section class="nwmd-app-home__panel">

        <?php if (is_array($selected_region)) : ?>
            <header class="nwmd-app-topbar">
                <a
                    class="nwmd-app-back"
                    href="<?php echo esc_url($back_url); ?>"
                >
                    <span aria-hidden="true">&larr;</span>
                    <span>
                        <?php
                        echo esc_html__(
                            'Back',
                            'local-directory-framework'
                        );
                        ?>
                    </span>
                </a>

                <a
                    class="nwmd-app-brand"
                    href="<?php echo esc_url($home_url); ?>"
                >
                    <span
                        class="nwmd-app-brand__mark"
                        aria-hidden="true"
                    >
                        NW
                    </span>

                    <span>NW Monthly</span>
                </a>
            </header>
        <?php endif; ?>

        <?php if (!is_array($selected_region)) : ?>

            <div class="nwmd-home-landing">
                <div class="nwmd-home-landing__copy">
                    <div class="nwmd-app-brand nwmd-app-brand--large">
                        <span
                            class="nwmd-app-brand__mark"
                            aria-hidden="true"
                        >
                            NW
                        </span>

                        <span>NW Monthly</span>
                    </div>

                    <p class="nwmd-home-eyebrow">
                        <?php
                        echo esc_html__(
                            'Oregon and Washington',
                            'local-directory-framework'
                        );
                        ?>
                    </p>

                    <h1>
                        <?php
                        echo esc_html__(
                            'Discover trusted local businesses.',
                            'local-directory-framework'
                        );
                        ?>
                    </h1>

                    <p class="nwmd-home-intro">
                        <?php
                        echo esc_html__(
                            'Find local trades, services, restaurants, advertisements, deals, and coupons.',
                            'local-directory-framework'
                        );
                        ?>
                    </p>
                </div>

                <section
                    class="nwmd-state-selector"
                    aria-labelledby="nwmd-state-title"
                >
                    <h2 id="nwmd-state-title">
                        <?php
                        echo esc_html__(
                            'Choose your state',
                            'local-directory-framework'
                        );
                        ?>
                    </h2>

                    <div class="nwmd-state-list">
                        <?php foreach ($regions as $region) : ?>
                            <a
                                class="nwmd-state-button"
                                href="<?php echo esc_url(
                                    $state_url($region['slug'])
                                ); ?>"
                            >
                                <span>
                                    <strong>
                                        <?php
                                        echo esc_html(
                                            $region['name']
                                        );
                                        ?>
                                    </strong>

                                    <small>
                                        <?php
                                        echo esc_html(
                                            $region['abbreviation']
                                        );
                                        ?>
                                    </small>
                                </span>

                                <span aria-hidden="true">&rarr;</span>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <p>
                        <?php
                        echo esc_html__(
                            'Select a state to view available cities.',
                            'local-directory-framework'
                        );
                        ?>
                    </p>
                </section>
            </div>

        <?php elseif (!is_array($selected_city)) : ?>

            <header class="nwmd-step-heading">
                <p>
                    <?php
                    echo esc_html(
                        $selected_region['name']
                        . ' - '
                        . $selected_region['abbreviation']
                    );
                    ?>
                </p>

                <h1>
                    <?php
                    echo esc_html__(
                        'Choose your city',
                        'local-directory-framework'
                    );
                    ?>
                </h1>
            </header>

            <nav
                class="nwmd-choice-grid"
                aria-label="<?php
                    echo esc_attr__(
                        'Cities',
                        'local-directory-framework'
                    );
                ?>"
            >
                <?php foreach ($selected_region['cities'] as $city) : ?>
                    <a
                        class="nwmd-choice-button"
                        href="<?php echo esc_url(
                            $city_url(
                                $selected_region['slug'],
                                $city['slug']
                            )
                        ); ?>"
                    >
                        <span>
                            <strong>
                                <?php echo esc_html($city['name']); ?>
                            </strong>

                            <small>
                                <?php
                                echo esc_html(
                                    $selected_region['abbreviation']
                                );
                                ?>
                            </small>
                        </span>

                        <span aria-hidden="true">&rarr;</span>
                    </a>
                <?php endforeach; ?>
            </nav>

        <?php elseif (!is_array($selected_category)) : ?>

            <header class="nwmd-step-heading">
                <p>
                    <?php
                    echo esc_html(
                        $selected_city['name']
                        . ', '
                        . $selected_region['abbreviation']
                    );
                    ?>
                </p>

                <h1>
                    <?php
                    echo esc_html__(
                        'Choose a trade or category',
                        'local-directory-framework'
                    );
                    ?>
                </h1>
            </header>

            <nav
                class="nwmd-choice-grid"
                aria-label="<?php
                    echo esc_attr__(
                        'Business categories',
                        'local-directory-framework'
                    );
                ?>"
            >
                <?php foreach ($categories as $category) : ?>
                    <a
                        class="nwmd-choice-button"
                        href="<?php echo esc_url(
                            $category_url(
                                $selected_region['slug'],
                                $selected_city['slug'],
                                $category['slug']
                            )
                        ); ?>"
                    >
                        <strong>
                            <?php echo esc_html($category['name']); ?>
                        </strong>

                        <span aria-hidden="true">&rarr;</span>
                    </a>
                <?php endforeach; ?>
            </nav>

        <?php else : ?>

            <header class="nwmd-step-heading">
                <p>
                    <?php
                    echo esc_html(
                        $selected_city['name']
                        . ', '
                        . $selected_region['abbreviation']
                        . ' - '
                        . $selected_category['name']
                    );
                    ?>
                </p>

                <h1>
                    <?php
                    echo esc_html__(
                        'Choose a service',
                        'local-directory-framework'
                    );
                    ?>
                </h1>

                <p class="nwmd-step-heading__intro">
                    <?php
                    echo esc_html__(
                        'Select one service or view every business in this category.',
                        'local-directory-framework'
                    );
                    ?>
                </p>
            </header>

            <nav
                class="nwmd-choice-grid"
                aria-label="<?php
                    echo esc_attr__(
                        'Services',
                        'local-directory-framework'
                    );
                ?>"
            >
                <a
                    class="nwmd-choice-button"
                    href="<?php echo esc_url(
                        nwmd_directory_get_app_city_url(
                            $selected_category['slug'],
                            'all',
                            $selected_region['slug'],
                            $selected_city['slug']
                        )
                    ); ?>"
                >
                    <strong>
                        <?php
                        echo esc_html(
                            sprintf(
                                'All %s',
                                $selected_category['name']
                            )
                        );
                        ?>
                    </strong>

                    <span aria-hidden="true">&rarr;</span>
                </a>

                <?php foreach ($specialties as $specialty) : ?>
                    <a
                        class="nwmd-choice-button"
                        href="<?php echo esc_url(
                            nwmd_directory_get_app_city_url(
                                $selected_category['slug'],
                                $specialty['slug'],
                                $selected_region['slug'],
                                $selected_city['slug']
                            )
                        ); ?>"
                    >
                        <strong>
                            <?php echo esc_html($specialty['name']); ?>
                        </strong>

                        <span aria-hidden="true">&rarr;</span>
                    </a>
                <?php endforeach; ?>
            </nav>

        <?php endif; ?>

        <?php nwmd_directory_render_app_footer(); ?>
    </section>
</main>

<?php wp_footer(); ?>
</body>
</html>