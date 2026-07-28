<?php

if (!defined('ABSPATH')) {
    exit;
}

$current_category =
    nwmd_directory_get_archive_filter_value(
        'filter_category'
    );

$current_specialty =
    nwmd_directory_get_archive_filter_value(
        'filter_specialty'
    );

$current_state =
    nwmd_directory_get_archive_filter_value(
        'filter_state'
    );

$current_city =
    nwmd_directory_get_archive_filter_value(
        'filter_city'
    );

$categories = nwmd_directory_get_launch_categories();
$specialty_map = nwmd_directory_get_launch_specialties();
$regions = nwmd_directory_get_launch_regions();

$category_term = null;

if ('' !== $current_category) {
    $category_term = get_term_by(
        'slug',
        $current_category,
        'nwmd_category'
    );
}

if (!$category_term instanceof WP_Term) {
    $category_term = null;
    $current_category = '';
    $current_specialty = '';
    $current_state = '';
    $current_city = '';
}

$specialties = [];

if ($category_term instanceof WP_Term) {
    $specialties = $specialty_map[$category_term->slug]
        ?? [];
}

$specialty_term = null;
$specialty_selection_complete = false;

if (
    $category_term instanceof WP_Term &&
    'all' === $current_specialty
) {
    $specialty_selection_complete = true;
} elseif (
    $category_term instanceof WP_Term &&
    '' !== $current_specialty
) {
    $candidate_specialty = get_term_by(
        'slug',
        $current_specialty,
        'nwmd_specialty'
    );

    if ($candidate_specialty instanceof WP_Term) {
        $specialty_category_id = absint(
            get_term_meta(
                $candidate_specialty->term_id,
                'nwmd_category_term_id',
                true
            )
        );

        if (
            $specialty_category_id ===
            absint($category_term->term_id)
        ) {
            $specialty_term = $candidate_specialty;
            $specialty_selection_complete = true;
        }
    }
}

if (!$specialty_selection_complete) {
    $current_specialty = '';
    $current_state = '';
    $current_city = '';
}

$selected_region = null;

if (
    $specialty_selection_complete &&
    '' !== $current_state
) {
    foreach ($regions as $region) {
        if ($region['slug'] === $current_state) {
            $selected_region = $region;
            break;
        }
    }
}

$state_term = null;

if (is_array($selected_region)) {
    $candidate_state = get_term_by(
        'slug',
        $current_state,
        'nwmd_state'
    );

    if ($candidate_state instanceof WP_Term) {
        $state_term = $candidate_state;
    }
}

if (!$state_term instanceof WP_Term) {
    $state_term = null;
    $selected_region = null;
    $current_state = '';
    $current_city = '';
}

$city_term = null;

if (
    $state_term instanceof WP_Term &&
    '' !== $current_city
) {
    $candidate_city = get_term_by(
        'slug',
        $current_city,
        'nwmd_city'
    );

    if ($candidate_city instanceof WP_Term) {
        $city_state_term_id = absint(
            get_term_meta(
                $candidate_city->term_id,
                'nwmd_state_term_id',
                true
            )
        );

        if (
            $city_state_term_id ===
            absint($state_term->term_id)
        ) {
            $city_term = $candidate_city;
        }
    }
}

if (!$city_term instanceof WP_Term) {
    $city_term = null;
    $current_city = '';
}

$manage_url =
    nwmd_directory_get_business_request_url();

$back_url = home_url('/');

if (
    $category_term instanceof WP_Term &&
    $specialty_selection_complete
) {
    if (
        $state_term instanceof WP_Term &&
        $city_term instanceof WP_Term
    ) {
        $back_url =
            nwmd_directory_get_app_state_url(
                $category_term->slug,
                $current_specialty,
                $state_term->slug
            );
    } elseif ($state_term instanceof WP_Term) {
        $back_url =
            nwmd_directory_get_app_specialty_url(
                $category_term->slug,
                $current_specialty
            );
    } else {
        $back_url =
            nwmd_directory_get_app_category_url(
                $category_term->slug
            );
    }
}

$state_abbreviation = '';

if ($state_term instanceof WP_Term) {
    $state_abbreviation = sanitize_text_field(
        get_term_meta(
            $state_term->term_id,
            'nwmd_abbreviation',
            true
        )
    );

    if (
        '' === $state_abbreviation &&
        is_array($selected_region)
    ) {
        $state_abbreviation =
            $selected_region['abbreviation'];
    }
}

$ad_context = [
    'state_term_ids' => [],
    'city_term_ids' => [],
    'category_term_ids' => [],
    'specialty_term_ids' => [],
];

if ($category_term instanceof WP_Term) {
    $ad_context['category_term_ids'][] =
        absint($category_term->term_id);
}

if ($specialty_term instanceof WP_Term) {
    $ad_context['specialty_term_ids'][] =
        absint($specialty_term->term_id);
}

if ($state_term instanceof WP_Term) {
    $ad_context['state_term_ids'][] =
        absint($state_term->term_id);
}

if ($city_term instanceof WP_Term) {
    $ad_context['city_term_ids'][] =
        absint($city_term->term_id);
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

<body <?php body_class('nwmd-app-body nwmd-directory-app-body'); ?>>
<?php wp_body_open(); ?>

<main class="nwmd-app-shell" id="primary">
    <section class="nwmd-app-shell__panel">
        <header class="nwmd-app-bar">
            <a
                class="nwmd-app-bar__back"
                href="<?php echo esc_url($back_url); ?>"
            >
                <span aria-hidden="true">←</span>
                <?php
                echo esc_html__(
                    'Back',
                    'local-directory-framework'
                );
                ?>
            </a>

            <a
                class="nwmd-app-bar__brand"
                href="<?php echo esc_url(home_url('/')); ?>"
            >
                <span
                    class="nwmd-app-bar__mark"
                    aria-hidden="true"
                >
                    NW
                </span>

                <span>NW Monthly</span>
            </a>
        </header>

        <?php if (!$category_term instanceof WP_Term) : ?>

            <header class="nwmd-app-heading">
                <p class="nwmd-app-heading__eyebrow">
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
                        'Choose a category',
                        'local-directory-framework'
                    );
                    ?>
                </h1>
            </header>

            <nav class="nwmd-app-button-grid">
                <?php foreach ($categories as $category) : ?>
                    <a
                        class="nwmd-app-button"
                        href="<?php echo esc_url(
                            nwmd_directory_get_app_category_url(
                                $category['slug']
                            )
                        ); ?>"
                    >
                        <strong>
                            <?php echo esc_html($category['name']); ?>
                        </strong>

                        <span aria-hidden="true">→</span>
                    </a>
                <?php endforeach; ?>
            </nav>

        <?php elseif (!$specialty_selection_complete) : ?>

            <header class="nwmd-app-heading">
                <p class="nwmd-app-heading__eyebrow">
                    <?php echo esc_html($category_term->name); ?>
                </p>

                <h1>
                    <?php
                    echo esc_html__(
                        'Choose a service',
                        'local-directory-framework'
                    );
                    ?>
                </h1>

                <p>
                    <?php
                    echo esc_html__(
                        'Choose one service or view every business in this category.',
                        'local-directory-framework'
                    );
                    ?>
                </p>
            </header>

            <nav class="nwmd-app-button-grid">
                <a
                    class="nwmd-app-button"
                    href="<?php echo esc_url(
                        nwmd_directory_get_app_specialty_url(
                            $category_term->slug,
                            'all'
                        )
                    ); ?>"
                >
                    <strong>
                        <?php
                        echo esc_html(
                            sprintf(
                                /* translators: %s: category name. */
                                __('All %s', 'local-directory-framework'),
                                $category_term->name
                            )
                        );
                        ?>
                    </strong>

                    <span aria-hidden="true">→</span>
                </a>

                <?php foreach ($specialties as $specialty) : ?>
                    <a
                        class="nwmd-app-button"
                        href="<?php echo esc_url(
                            nwmd_directory_get_app_specialty_url(
                                $category_term->slug,
                                $specialty['slug']
                            )
                        ); ?>"
                    >
                        <strong>
                            <?php echo esc_html($specialty['name']); ?>
                        </strong>

                        <span aria-hidden="true">→</span>
                    </a>
                <?php endforeach; ?>
            </nav>

        <?php elseif (!$state_term instanceof WP_Term) : ?>

            <header class="nwmd-app-heading">
                <p class="nwmd-app-heading__eyebrow">
                    <?php
                    echo esc_html(
                        $specialty_term instanceof WP_Term
                            ? $specialty_term->name
                            : $category_term->name
                    );
                    ?>
                </p>

                <h1>
                    <?php
                    echo esc_html__(
                        'Choose your state',
                        'local-directory-framework'
                    );
                    ?>
                </h1>

                <p>
                    <?php
                    echo esc_html__(
                        'Choose Washington or Oregon.',
                        'local-directory-framework'
                    );
                    ?>
                </p>
            </header>

            <nav class="nwmd-app-button-grid">
                <?php foreach ($regions as $region) : ?>
                    <a
                        class="nwmd-app-button"
                        href="<?php echo esc_url(
                            nwmd_directory_get_app_state_url(
                                $category_term->slug,
                                $current_specialty,
                                $region['slug']
                            )
                        ); ?>"
                    >
                        <strong>
                            <?php echo esc_html($region['name']); ?>
                        </strong>

                        <span>
                            <?php echo esc_html($region['abbreviation']); ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </nav>

        <?php elseif (!$city_term instanceof WP_Term) : ?>

            <header class="nwmd-app-heading">
                <p class="nwmd-app-heading__eyebrow">
                    <?php
                    echo esc_html(
                        $state_term->name
                        . (
                            '' !== $state_abbreviation
                                ? ' · ' . $state_abbreviation
                                : ''
                        )
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

                <p>
                    <?php
                    echo esc_html__(
                        'Choose one city to see local businesses.',
                        'local-directory-framework'
                    );
                    ?>
                </p>
            </header>

            <div class="nwmd-city-grid">
                <?php foreach ($selected_region['cities'] as $city) : ?>
                    <a
                        class="nwmd-city-button"
                        href="<?php echo esc_url(
                            nwmd_directory_get_app_city_url(
                                $category_term->slug,
                                $current_specialty,
                                $state_term->slug,
                                $city['slug']
                            )
                        ); ?>"
                    >
                        <strong>
                            <?php echo esc_html($city['name']); ?>
                        </strong>

                        <span>
                            <?php echo esc_html($state_abbreviation); ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>

        <?php else : ?>

            <header class="nwmd-app-heading">
                <p class="nwmd-app-heading__eyebrow">
                    <?php
                    echo esc_html(
                        $city_term->name
                        . (
                            '' !== $state_abbreviation
                                ? ', ' . $state_abbreviation
                                : ''
                        )
                    );
                    ?>
                </p>

                <h1>
                    <?php echo esc_html($category_term->name); ?>
                </h1>

                <p>
                    <?php
                    echo esc_html__(
                        'Tap a business for details.',
                        'local-directory-framework'
                    );
                    ?>
                </p>
            </header>

            <?php
            nwmd_directory_render_advertising_inquiry_notice();

            nwmd_directory_render_ad(
                'results_sponsored',
                $ad_context
            );
            ?>

            <?php if (have_posts()) : ?>

                <div class="nwmd-business-list">
                    <?php while (have_posts()) : ?>
                        <?php
                        the_post();

                        $post_id = get_the_ID();

                        $specialties =
                            nwmd_directory_get_business_term_names(
                                $post_id,
                                'nwmd_specialty'
                            );

                        $phone = sanitize_text_field(
                            nwmd_directory_get_business_record_meta(
                                $post_id,
                                'public_phone'
                            )
                        );

                        $phone_href = preg_replace(
                            '/[^0-9+]/',
                            '',
                            $phone
                        );

                        $website = esc_url_raw(
                            nwmd_directory_get_business_record_meta(
                                $post_id,
                                'website_url'
                            )
                        );

                        $excerpt = get_the_excerpt();

                        if ('' === trim($excerpt)) {
                            $excerpt = wp_trim_words(
                                wp_strip_all_tags(
                                    get_the_content()
                                ),
                                20
                            );
                        }
                        ?>

                        <article <?php post_class('nwmd-business-row'); ?>>
                            <div class="nwmd-business-row__content">
                                <?php if (!empty($specialties)) : ?>
                                    <p class="nwmd-business-row__type">
                                        <?php
                                        echo esc_html(
                                            implode(
                                                ' · ',
                                                $specialties
                                            )
                                        );
                                        ?>
                                    </p>
                                <?php endif; ?>

                                <h2>
                                    <a href="<?php echo esc_url(get_permalink()); ?>">
                                        <?php echo esc_html(get_the_title()); ?>
                                    </a>
                                </h2>

                                <p class="nwmd-business-row__location">
                                    <?php
                                    echo esc_html(
                                        $city_term->name
                                        . (
                                            '' !== $state_abbreviation
                                                ? ', ' . $state_abbreviation
                                                : ''
                                        )
                                    );
                                    ?>
                                </p>

                                <?php if ('' !== trim($excerpt)) : ?>
                                    <p class="nwmd-business-row__excerpt">
                                        <?php
                                        echo esc_html(
                                            wp_trim_words(
                                                $excerpt,
                                                22
                                            )
                                        );
                                        ?>
                                    </p>
                                <?php endif; ?>
                            </div>

                            <div class="nwmd-business-row__actions">
                                <?php if ('' !== $phone_href) : ?>
                                    <a
                                        href="<?php echo esc_url(
                                            'tel:' . $phone_href
                                        ); ?>"
                                    >
                                        <?php
                                        echo esc_html__(
                                            'Call',
                                            'local-directory-framework'
                                        );
                                        ?>
                                    </a>
                                <?php endif; ?>

                                <?php if (wp_http_validate_url($website)) : ?>
                                    <a
                                        href="<?php echo esc_url($website); ?>"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        <?php
                                        echo esc_html__(
                                            'Website',
                                            'local-directory-framework'
                                        );
                                        ?>
                                    </a>
                                <?php endif; ?>

                                <a
                                    class="nwmd-business-row__details"
                                    href="<?php echo esc_url(get_permalink()); ?>"
                                >
                                    <?php
                                    echo esc_html__(
                                        'Details',
                                        'local-directory-framework'
                                    );
                                    ?>
                                </a>
                            </div>
                        </article>
                    <?php endwhile; ?>
                </div>

                <div class="nwmd-app-pagination">
                    <?php
                    the_posts_pagination(
                        [
                            'mid_size'  => 1,
                            'prev_text' => esc_html__(
                                'Previous',
                                'local-directory-framework'
                            ),
                            'next_text' => esc_html__(
                                'Next',
                                'local-directory-framework'
                            ),
                            'add_args' => [
                                'filter_category' =>
                                    $category_term->slug,
                                'filter_specialty' =>
                                    $current_specialty,
                                'filter_state' =>
                                    $state_term->slug,
                                'filter_city' =>
                                    $city_term->slug,
                            ],
                        ]
                    );
                    ?>
                </div>

            <?php else : ?>

                <section class="nwmd-app-empty">
                    <h2>
                        <?php
                        echo esc_html__(
                            'No businesses listed yet.',
                            'local-directory-framework'
                        );
                        ?>
                    </h2>

                    <p>
                        <?php
                        echo esc_html__(
                            'A local business can request a new listing.',
                            'local-directory-framework'
                        );
                        ?>
                    </p>

                    <a href="<?php echo esc_url($manage_url); ?>">
                        <?php
                        echo esc_html__(
                            'Add a business',
                            'local-directory-framework'
                        );
                        ?>
                    </a>
                </section>

            <?php endif; ?>

            <?php
            nwmd_directory_render_ad(
                'results_bottom',
                $ad_context
            );
            ?>

        <?php endif; ?>

        <?php nwmd_directory_render_app_footer(); ?>
    </section>
</main>

<?php wp_footer(); ?>
</body>
</html>
