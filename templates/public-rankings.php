<?php

if (!defined('ABSPATH')) {
    exit;
}

$period = nwmd_directory_get_current_published_ranking_period();
$selection = nwmd_directory_get_public_ranking_selection();
$step = nwmd_directory_get_public_ranking_step($selection);

$entries = [];
$step_terms = [];
$all_specialties_available = false;
$ranking_ad_context = nwmd_directory_get_ranking_ad_context(
    $selection
);

if ($period) {
    if ('category' === $step) {
        $step_terms = nwmd_directory_get_public_ranking_step_terms(
            $period->id,
            'nwmd_category',
            $selection
        );
    } elseif ('state' === $step) {
        $step_terms = nwmd_directory_get_public_ranking_step_terms(
            $period->id,
            'nwmd_state',
            $selection
        );
    } elseif ('city' === $step) {
        $step_terms = nwmd_directory_get_public_ranking_step_terms(
            $period->id,
            'nwmd_city',
            $selection
        );
    } elseif ('specialty' === $step) {
        $step_terms = nwmd_directory_get_public_ranking_step_terms(
            $period->id,
            'nwmd_specialty',
            $selection
        );

        $all_specialties_available =
            nwmd_directory_public_ranking_has_all_specialties(
                $period->id,
                $selection
            );
    } elseif ('results' === $step) {
        $entries = nwmd_directory_get_public_ranking_entries(
            $period->id,
            $selection
        );
    }
}

$archive_url = get_post_type_archive_link(
    'nwmd_business'
);

$category_term = $selection['terms']['category'];
$state_term = $selection['terms']['state'];
$city_term = $selection['terms']['city'];
$specialty_term = $selection['terms']['specialty'];

$category_args = [];
$state_args = [];
$city_args = [];

if ($category_term instanceof WP_Term) {
    $category_args['ranking_category'] = $category_term->slug;
    $state_args = $category_args;
    $city_args = $category_args;
}

if ($state_term instanceof WP_Term) {
    $state_args['ranking_state'] = $state_term->slug;
    $city_args = $state_args;
}

if ($city_term instanceof WP_Term) {
    $city_args['ranking_city'] = $city_term->slug;
}

$step_number = [
    'category'  => 1,
    'state'     => 2,
    'city'      => 3,
    'specialty' => 4,
    'results'   => 4,
];

$current_step_number = $step_number[$step] ?? 1;

$back_url = home_url('/');

if ('state' === $step) {
    $back_url =
        nwmd_directory_get_public_rankings_url();
} elseif ('city' === $step) {
    $back_url =
        nwmd_directory_get_public_ranking_navigation_url(
            $category_args
        );
} elseif ('specialty' === $step) {
    $back_url =
        nwmd_directory_get_public_ranking_navigation_url(
            $state_args
        );
} elseif ('results' === $step) {
    $back_url =
        nwmd_directory_get_public_ranking_navigation_url(
            $city_args
        );
}

$back_url =
    nwmd_directory_get_requested_public_return_url(
        $back_url
    );

$progress_steps = [
    'category' => [
        'number' => 1,
        'label'  => __('Category', 'local-directory-framework'),
        'value'  => $category_term instanceof WP_Term
            ? $category_term->name
            : '',
    ],
    'state' => [
        'number' => 2,
        'label'  => __('State', 'local-directory-framework'),
        'value'  => $state_term instanceof WP_Term
            ? $state_term->name
            : '',
    ],
    'city' => [
        'number' => 3,
        'label'  => __('City', 'local-directory-framework'),
        'value'  => $city_term instanceof WP_Term
            ? $city_term->name
            : '',
    ],
    'specialty' => [
        'number' => 4,
        'label'  => __('Specialty', 'local-directory-framework'),
        'value'  => !empty($selection['specialty_all'])
            ? __('All specialties', 'local-directory-framework')
            : (
                $specialty_term instanceof WP_Term
                    ? $specialty_term->name
                    : ''
            ),
    ],
];

$result_labels = [];

if ('results' === $step) {
    $result_labels[] = $category_term->name;
    $result_labels[] = $city_term->name;
    $result_labels[] = $state_term->name;
    $result_labels[] = !empty($selection['specialty_all'])
        ? __('All specialties', 'local-directory-framework')
        : $specialty_term->name;
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

<body <?php body_class('nwmd-app-body nwmd-rankings-app-body'); ?>>
<?php wp_body_open(); ?>

<main
    class="nwmd-directory nwmd-directory--rankings"
    id="primary"
>
    <header class="nwmd-rankings-bar">
        <a
            class="nwmd-rankings-bar__brand"
            href="<?php echo esc_url(home_url('/')); ?>"
        >
            <span
                class="nwmd-rankings-bar__mark"
                aria-hidden="true"
            >
                NW
            </span>

            <span>NW Monthly</span>
        </a>
    </header>
    <section class="nwmd-directory__intro">
        <p class="nwmd-directory__eyebrow">
            <?php echo esc_html__('NW Monthly Rankings', 'local-directory-framework'); ?>
        </p>

        <h1 class="nwmd-directory__title">
            <?php echo esc_html__('Find Top Local Businesses', 'local-directory-framework'); ?>
        </h1>

        <p class="nwmd-directory__description">
            <?php
            echo esc_html__(
                'Choose a category, state, city, and specialty to view the latest published Top 10 ranking.',
                'local-directory-framework'
            );
            ?>
        </p>

        <?php if ($period) : ?>
            <p class="nwmd-ranking-period">
                <?php
                echo esc_html(
                    sprintf(
                        /* translators: %s: Ranking month. */
                        __(
                            'Published ranking period: %s',
                            'local-directory-framework'
                        ),
                        $period->period_label
                    )
                );
                ?>
            </p>
        <?php endif; ?>
    </section>

    <?php if (!$period) : ?>

        <div class="nwmd-directory__empty">
            <h2>
                <?php echo esc_html__('Rankings are being prepared.', 'local-directory-framework'); ?>
            </h2>

            <p>
                <?php
                echo esc_html__(
                    'No monthly ranking period is currently published. Please check back soon.',
                    'local-directory-framework'
                );
                ?>
            </p>
        </div>

    <?php else : ?>

        <nav
            class="nwmd-guided-progress"
            aria-label="<?php echo esc_attr__('Ranking search progress', 'local-directory-framework'); ?>"
        >
            <ol>
                <?php foreach ($progress_steps as $progress_key => $progress_step) : ?>
                    <?php
                    $is_complete = '' !== $progress_step['value'];
                    $is_current = $progress_step['number'] === $current_step_number &&
                        'results' !== $step;

                    $classes = [
                        'nwmd-guided-progress__step',
                    ];

                    if ($is_complete) {
                        $classes[] = 'is-complete';
                    }

                    if ($is_current) {
                        $classes[] = 'is-current';
                    }
                    ?>
                    <li
                        class="<?php echo esc_attr(implode(' ', $classes)); ?>"
                        <?php if ($is_current) : ?>
                            aria-current="step"
                        <?php endif; ?>
                    >
                        <span class="nwmd-guided-progress__number">
                            <?php echo esc_html($progress_step['number']); ?>
                        </span>

                        <span class="nwmd-guided-progress__text">
                            <strong>
                                <?php echo esc_html($progress_step['label']); ?>
                            </strong>

                            <?php if ($is_complete) : ?>
                                <small>
                                    <?php echo esc_html($progress_step['value']); ?>
                                </small>
                            <?php endif; ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ol>
        </nav>

        <?php if ('invalid' === $step) : ?>

            <div
                class="nwmd-request-notice nwmd-request-notice--error"
                role="alert"
            >
                <p>
                    <?php
                    echo esc_html__(
                        'That ranking selection is not valid. Start again and choose one of the published options.',
                        'local-directory-framework'
                    );
                    ?>
                </p>
            </div>

            <p class="nwmd-guided-actions">
                <a
                    class="nwmd-guided-actions__primary"
                    href="<?php echo esc_url(
                        nwmd_directory_get_public_rankings_url()
                    ); ?>"
                >
                    <?php echo esc_html__('Start Over', 'local-directory-framework'); ?>
                </a>
            </p>

        <?php elseif ('category' === $step) : ?>

            <section
                class="nwmd-guided-step"
                aria-labelledby="nwmd-guided-step-title"
            >
                <header class="nwmd-guided-step__header">
                    <p class="nwmd-directory__eyebrow">
                        <?php echo esc_html__('Step 1 of 4', 'local-directory-framework'); ?>
                    </p>

                    <h2 id="nwmd-guided-step-title">
                        <?php echo esc_html__('What type of business are you looking for?', 'local-directory-framework'); ?>
                    </h2>

                    <p>
                        <?php echo esc_html__('Choose one business category.', 'local-directory-framework'); ?>
                    </p>
                </header>

                <?php if (empty($step_terms)) : ?>
                    <div class="nwmd-directory__empty">
                        <h3>
                            <?php echo esc_html__('No published categories are available.', 'local-directory-framework'); ?>
                        </h3>
                    </div>
                <?php else : ?>
                    <div class="nwmd-guided-options">
                        <?php foreach ($step_terms as $term) : ?>
                            <a
                                class="nwmd-guided-option"
                                href="<?php echo esc_url(
                                    nwmd_directory_get_public_ranking_navigation_url(
                                        [
                                            'ranking_category' => $term->slug,
                                        ]
                                    )
                                ); ?>"
                            >
                                <strong>
                                    <?php echo esc_html($term->name); ?>
                                </strong>
                                <span>
                                    <?php echo esc_html__('Choose category', 'local-directory-framework'); ?>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

        <?php elseif ('state' === $step) : ?>

            <section
                class="nwmd-guided-step"
                aria-labelledby="nwmd-guided-step-title"
            >
                <header class="nwmd-guided-step__header">
                    <p class="nwmd-directory__eyebrow">
                        <?php echo esc_html__('Step 2 of 4', 'local-directory-framework'); ?>
                    </p>

                    <h2 id="nwmd-guided-step-title">
                        <?php echo esc_html__('Which state should we search?', 'local-directory-framework'); ?>
                    </h2>

                    <p>
                        <?php
                        echo esc_html(
                            sprintf(
                                /* translators: %s: Selected category. */
                                __('Category: %s', 'local-directory-framework'),
                                $category_term->name
                            )
                        );
                        ?>
                    </p>
                </header>

                <?php if (empty($step_terms)) : ?>
                    <div class="nwmd-directory__empty">
                        <h3>
                            <?php echo esc_html__('No published states match this category.', 'local-directory-framework'); ?>
                        </h3>
                    </div>
                <?php else : ?>
                    <div class="nwmd-guided-options">
                        <?php foreach ($step_terms as $term) : ?>
                            <a
                                class="nwmd-guided-option"
                                href="<?php echo esc_url(
                                    nwmd_directory_get_public_ranking_navigation_url(
                                        [
                                            'ranking_category' => $category_term->slug,
                                            'ranking_state'    => $term->slug,
                                        ]
                                    )
                                ); ?>"
                            >
                                <strong>
                                    <?php echo esc_html($term->name); ?>
                                </strong>
                                <span>
                                    <?php echo esc_html__('Choose state', 'local-directory-framework'); ?>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <p class="nwmd-guided-actions">
                    <a
                        class="nwmd-guided-actions__secondary"
                        href="<?php echo esc_url(
                            nwmd_directory_get_public_rankings_url()
                        ); ?>"
                    >
                        <?php echo esc_html__('← Change Category', 'local-directory-framework'); ?>
                    </a>
                </p>
            </section>

        <?php elseif ('city' === $step) : ?>

            <section
                class="nwmd-guided-step"
                aria-labelledby="nwmd-guided-step-title"
            >
                <header class="nwmd-guided-step__header">
                    <p class="nwmd-directory__eyebrow">
                        <?php echo esc_html__('Step 3 of 4', 'local-directory-framework'); ?>
                    </p>

                    <h2 id="nwmd-guided-step-title">
                        <?php echo esc_html__('Which city should we use?', 'local-directory-framework'); ?>
                    </h2>

                    <p>
                        <?php
                        echo esc_html(
                            sprintf(
                                /* translators: 1: Category. 2: State. */
                                __('%1$s in %2$s', 'local-directory-framework'),
                                $category_term->name,
                                $state_term->name
                            )
                        );
                        ?>
                    </p>
                </header>

                <?php if (empty($step_terms)) : ?>
                    <div class="nwmd-directory__empty">
                        <h3>
                            <?php echo esc_html__('No published cities match this selection.', 'local-directory-framework'); ?>
                        </h3>
                    </div>
                <?php else : ?>
                    <div class="nwmd-guided-options">
                        <?php foreach ($step_terms as $term) : ?>
                            <a
                                class="nwmd-guided-option"
                                href="<?php echo esc_url(
                                    nwmd_directory_get_public_ranking_navigation_url(
                                        [
                                            'ranking_category' => $category_term->slug,
                                            'ranking_state'    => $state_term->slug,
                                            'ranking_city'     => $term->slug,
                                        ]
                                    )
                                ); ?>"
                            >
                                <strong>
                                    <?php echo esc_html($term->name); ?>
                                </strong>
                                <span>
                                    <?php echo esc_html__('Choose city', 'local-directory-framework'); ?>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <p class="nwmd-guided-actions">
                    <a
                        class="nwmd-guided-actions__secondary"
                        href="<?php echo esc_url(
                            nwmd_directory_get_public_ranking_navigation_url(
                                $category_args
                            )
                        ); ?>"
                    >
                        <?php echo esc_html__('← Change State', 'local-directory-framework'); ?>
                    </a>
                </p>
            </section>

        <?php elseif ('specialty' === $step) : ?>

            <section
                class="nwmd-guided-step"
                aria-labelledby="nwmd-guided-step-title"
            >
                <header class="nwmd-guided-step__header">
                    <p class="nwmd-directory__eyebrow">
                        <?php echo esc_html__('Step 4 of 4', 'local-directory-framework'); ?>
                    </p>

                    <h2 id="nwmd-guided-step-title">
                        <?php echo esc_html__('Choose a specialty.', 'local-directory-framework'); ?>
                    </h2>

                    <p>
                        <?php
                        echo esc_html(
                            sprintf(
                                /* translators: 1: Category. 2: City. 3: State. */
                                __('%1$s in %2$s, %3$s', 'local-directory-framework'),
                                $category_term->name,
                                $city_term->name,
                                $state_term->name
                            )
                        );
                        ?>
                    </p>
                </header>

                <?php if (!$all_specialties_available && empty($step_terms)) : ?>
                    <div class="nwmd-directory__empty">
                        <h3>
                            <?php echo esc_html__('No published specialties match this selection.', 'local-directory-framework'); ?>
                        </h3>
                    </div>
                <?php else : ?>
                    <div class="nwmd-guided-options">
                        <?php if ($all_specialties_available) : ?>
                            <a
                                class="nwmd-guided-option nwmd-guided-option--featured"
                                href="<?php echo esc_url(
                                    nwmd_directory_get_public_ranking_navigation_url(
                                        array_merge(
                                            $city_args,
                                            [
                                                'ranking_specialty' => 'all',
                                            ]
                                        )
                                    )
                                ); ?>"
                            >
                                <strong>
                                    <?php echo esc_html__('All specialties', 'local-directory-framework'); ?>
                                </strong>
                                <span>
                                    <?php echo esc_html__('View the general category ranking', 'local-directory-framework'); ?>
                                </span>
                            </a>
                        <?php endif; ?>

                        <?php foreach ($step_terms as $term) : ?>
                            <a
                                class="nwmd-guided-option"
                                href="<?php echo esc_url(
                                    nwmd_directory_get_public_ranking_navigation_url(
                                        array_merge(
                                            $city_args,
                                            [
                                                'ranking_specialty' => $term->slug,
                                            ]
                                        )
                                    )
                                ); ?>"
                            >
                                <strong>
                                    <?php echo esc_html($term->name); ?>
                                </strong>
                                <span>
                                    <?php echo esc_html__('View specialty ranking', 'local-directory-framework'); ?>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <p class="nwmd-guided-actions">
                    <a
                        class="nwmd-guided-actions__secondary"
                        href="<?php echo esc_url(
                            nwmd_directory_get_public_ranking_navigation_url(
                                $state_args
                            )
                        ); ?>"
                    >
                        <?php echo esc_html__('← Change City', 'local-directory-framework'); ?>
                    </a>
                </p>
            </section>

        <?php elseif ('results' === $step) : ?>

            <?php if (empty($entries)) : ?>

                <div class="nwmd-directory__empty">
                    <h2>
                        <?php echo esc_html__('No published rankings matched.', 'local-directory-framework'); ?>
                    </h2>

                    <p>
                        <?php
                        echo esc_html__(
                            'Choose another specialty or return to an earlier step.',
                            'local-directory-framework'
                        );
                        ?>
                    </p>
                </div>

            <?php else : ?>

                <section
                    class="nwmd-ranking-results-section"
                    aria-labelledby="nwmd-ranking-results-title"
                >
                    <header class="nwmd-ranking-results-section__header">
                        <p class="nwmd-directory__eyebrow">
                            <?php echo esc_html($period->period_label); ?>
                        </p>

                        <h2
                            class="nwmd-ranking-results-section__title"
                            id="nwmd-ranking-results-title"
                        >
                            <?php echo esc_html(implode(' · ', $result_labels)); ?>
                        </h2>
                    </header>

                    <?php
                    nwmd_directory_render_ad(
                        'results_sponsored',
                        $ranking_ad_context
                    );
                    ?>

                    <ol class="nwmd-ranking-results">
                        <?php foreach ($entries as $entry) : ?>
                            <?php
                            $business = get_post(
                                absint($entry->business_post_id)
                            );

                            if (
                                !($business instanceof WP_Post) ||
                                'publish' !== $business->post_status
                            ) {
                                continue;
                            }

                            $permalink = add_query_arg(
                                [
                                    'return_to' =>
                                        nwmd_directory_get_current_public_url(),
                                ],
                                get_permalink($business->ID)
                            );

                            $excerpt = get_the_excerpt(
                                $business
                            );

                            if ('' === trim($excerpt)) {
                                $excerpt = wp_trim_words(
                                    wp_strip_all_tags(
                                        $business->post_content
                                    ),
                                    28
                                );
                            }
                            ?>

                            <li class="nwmd-ranking-result">
                                <div
                                    class="nwmd-ranking-result__position"
                                    aria-label="<?php echo esc_attr(
                                        sprintf(
                                            /* translators: %d: Ranking position. */
                                            __('Rank %d', 'local-directory-framework'),
                                            absint($entry->rank_position)
                                        )
                                    ); ?>"
                                >
                                    <?php echo esc_html($entry->rank_position); ?>
                                </div>

                                <?php if (has_post_thumbnail($business->ID)) : ?>
                                    <a
                                        class="nwmd-ranking-result__image"
                                        href="<?php echo esc_url($permalink); ?>"
                                        aria-label="<?php echo esc_attr($business->post_title); ?>"
                                    >
                                        <?php
                                        echo wp_kses_post(
                                            get_the_post_thumbnail(
                                                $business->ID,
                                                'medium_large'
                                            )
                                        );
                                        ?>
                                    </a>
                                <?php endif; ?>

                                <div class="nwmd-ranking-result__content">
                                    <h3 class="nwmd-ranking-result__title">
                                        <a href="<?php echo esc_url($permalink); ?>">
                                            <?php echo esc_html($business->post_title); ?>
                                        </a>
                                    </h3>

                                    <?php if ('' !== trim($excerpt)) : ?>
                                        <p class="nwmd-ranking-result__excerpt">
                                            <?php echo esc_html($excerpt); ?>
                                        </p>
                                    <?php endif; ?>

                                    <?php if ('' !== trim($entry->editorial_note)) : ?>
                                        <p class="nwmd-ranking-result__note">
                                            <?php echo esc_html($entry->editorial_note); ?>
                                        </p>
                                    <?php endif; ?>

                                    <a
                                        class="nwmd-business-card__link"
                                        href="<?php echo esc_url($permalink); ?>"
                                    >
                                        <?php echo esc_html__('View Business', 'local-directory-framework'); ?>
                                    </a>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ol>

                    <?php
                    nwmd_directory_render_ad(
                        'results_bottom',
                        $ranking_ad_context
                    );
                    ?>
                </section>

            <?php endif; ?>

            <?php if (empty($entries)) : ?>
                <?php
                nwmd_directory_render_ad(
                    'results_bottom',
                    $ranking_ad_context
                );
                ?>
            <?php endif; ?>

            <p class="nwmd-guided-actions">
                <a
                    class="nwmd-guided-actions__secondary"
                    href="<?php echo esc_url(
                        nwmd_directory_get_public_ranking_navigation_url(
                            $city_args
                        )
                    ); ?>"
                >
                    <?php echo esc_html__('← Change Specialty', 'local-directory-framework'); ?>
                </a>

                <a
                    class="nwmd-guided-actions__secondary"
                    href="<?php echo esc_url(
                        nwmd_directory_get_public_rankings_url()
                    ); ?>"
                >
                    <?php echo esc_html__('Start Over', 'local-directory-framework'); ?>
                </a>
            </p>

        <?php endif; ?>

    <?php endif; ?>

    <?php if (!empty($archive_url)) : ?>
        <p class="nwmd-ranking-back">
            <a
                class="nwmd-business-profile__back"
                href="<?php echo esc_url($archive_url); ?>"
            >
                <?php echo esc_html__('← Browse All Businesses', 'local-directory-framework'); ?>
            </a>
        </p>
    <?php endif; ?>
    <?php nwmd_directory_render_app_footer(); ?>
</main>

<?php wp_footer(); ?>
</body>
</html>
