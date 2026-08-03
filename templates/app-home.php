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

$current_public_url =
    nwmd_directory_get_current_public_url();

$results_url = static function (
    $category_slug,
    $specialty_slug,
    $state_slug,
    $city_slug
) use ($current_public_url) {

    return add_query_arg(
        [
            'return_to' => $current_public_url,
        ],
        nwmd_directory_get_app_city_url(
            $category_slug,
            $specialty_slug,
            $state_slug,
            $city_slug
        )
    );
};



$landing_regions = $regions;

$landing_order = [
    'washington' => 0,
    'oregon'     => 1,
];

usort(
    $landing_regions,
    static function ($first, $second) use ($landing_order) {

        $first_rank =
            $landing_order[$first['slug']]
            ?? PHP_INT_MAX;

        $second_rank =
            $landing_order[$second['slug']]
            ?? PHP_INT_MAX;

        if ($first_rank === $second_rank) {
            return strcasecmp(
                $first['name'],
                $second['name']
            );
        }

        return $first_rank <=> $second_rank;
    }
);

$is_landing = !is_array($selected_region);

$body_classes = 'nwmd-app-body';

if ($is_landing) {
    $body_classes .= ' nwmd-app-body--landing';
}

$hero_image = NWMD_DIRECTORY_URL
    . 'assets/images/nw-monthly-hero.webp';
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

<body <?php body_class($body_classes); ?>>
<?php wp_body_open(); ?>

<main
    class="nwmd-app-home"
    id="primary"
    data-nwmd-app-home
>
    <section
        class="nwmd-app-home__panel<?php
            echo $is_landing
                ? ' nwmd-app-home__panel--landing'
                : '';
        ?>"
    >
        <header
            class="nwmd-app-topbar<?php
                echo $is_landing
                    ? ' nwmd-app-topbar--landing'
                    : '';
            ?>"
        >
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

        <?php if ($is_landing) : ?>

            <div class="nwmd-home-landing">
                <div class="nwmd-home-landing__visual">
                    <img
                        src="<?php echo esc_url($hero_image); ?>"
                        alt="<?php
                            echo esc_attr__(
                                'NW Monthly local directory preview',
                                'local-directory-framework'
                            );
                        ?>"
                        loading="eager"
                        fetchpriority="high"
                    >
                </div>

                <div class="nwmd-home-landing__content">
                    <div class="nwmd-home-landing__content-inner">
                        <div class="nwmd-home-landing__copy">
                            <h1>
                                <?php
                                echo wp_kses_post(
                                    __(
                                        'Top local businesses<br>across the Northwest.',
                                        'local-directory-framework'
                                    )
                                );
                                ?>
                            </h1>

                            <p class="nwmd-home-intro">
                                <?php
                                echo wp_kses_post(
                                    __(
                                        'Find trusted services, current deals,<br>and local coupons all in one place.',
                                        'local-directory-framework'
                                    )
                                );
                                ?>
                            </p>
                        </div>

                        <section
                            class="nwmd-state-selector"
                            aria-labelledby="nwmd-state-title"
                        >
                            <h2
                                class="nwmd-visually-hidden"
                                id="nwmd-state-title"
                            >
                                <?php
                                echo esc_html__(
                                    'Choose your state',
                                    'local-directory-framework'
                                );
                                ?>
                            </h2>

                            <div class="nwmd-state-list">
                                <?php foreach ($landing_regions as $region) : ?>
                                    <a
                                        class="nwmd-state-button"
                                        href="<?php echo esc_url(
                                            $state_url($region['slug'])
                                        ); ?>"
                                    >
                                        <strong>
                                            <?php
                                            echo esc_html(
                                                $region['name']
                                            );
                                            ?>
                                        </strong>

                                        <span aria-hidden="true">
                                            <?php
                                            echo esc_html(
                                                $region['abbreviation']
                                            );
                                            ?>
                                        </span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </section>

                        <?php
                        nwmd_directory_render_app_footer();
                        ?>
                    </div>
                </div>
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
                        'Choose a city',
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

            <?php nwmd_directory_render_app_footer(); ?>

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
                        'Choose a category',
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

            <?php nwmd_directory_render_app_footer(); ?>

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
                        $results_url(
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
                            $results_url(
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

            <?php nwmd_directory_render_app_footer(); ?>

        <?php endif; ?>
    </section>
</main>

<?php wp_footer(); ?>
</body>
</html>
