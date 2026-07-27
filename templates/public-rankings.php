<?php

if (!defined('ABSPATH')) {
    exit;
}

get_header();

$period = nwmd_directory_get_current_published_ranking_period();

$selection = nwmd_directory_get_public_ranking_selection();

$entries = [];

$state_terms = [];
$city_terms = [];
$category_terms = [];
$specialty_terms = [];

if ($period) {
    $state_terms = nwmd_directory_get_public_ranking_terms(
        $period->id,
        'nwmd_state'
    );

    $city_terms = nwmd_directory_get_public_ranking_terms(
        $period->id,
        'nwmd_city'
    );

    $category_terms = nwmd_directory_get_public_ranking_terms(
        $period->id,
        'nwmd_category'
    );

    $specialty_terms = nwmd_directory_get_public_ranking_terms(
        $period->id,
        'nwmd_specialty'
    );

    if (!empty($selection['ready'])) {
        $entries = nwmd_directory_get_public_ranking_entries(
            $period->id,
            $selection
        );
    }
}

$archive_url = get_post_type_archive_link(
    'nwmd_business'
);

$result_labels = [];

if (!empty($selection['ready'])) {
    $result_labels[] = $selection['terms']['category']->name;
    $result_labels[] = $selection['terms']['city']->name;
    $result_labels[] = $selection['terms']['state']->name;

    $result_labels[] = (
        $selection['terms']['specialty'] instanceof WP_Term
    )
        ? $selection['terms']['specialty']->name
        : __(
            'All specialties',
            'local-directory-framework'
        );
}
?>

<main
    class="nwmd-directory nwmd-directory--rankings"
    id="primary"
>
    <section class="nwmd-directory__intro">
        <p class="nwmd-directory__eyebrow">
            <?php echo esc_html__('Northwest Monthly Rankings', 'local-directory-framework'); ?>
        </p>

        <h1 class="nwmd-directory__title">
            <?php echo esc_html__('Top Local Businesses', 'local-directory-framework'); ?>
        </h1>

        <p class="nwmd-directory__description">
            <?php
            echo esc_html__(
                'Browse the latest published editorial ranking snapshot by location, category, and specialty.',
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

        <form
            class="nwmd-ranking-filters"
            action="<?php echo esc_url(
                nwmd_directory_get_public_rankings_url()
            ); ?>"
            method="get"
        >
            <div class="nwmd-ranking-filters__fields">
                <label class="nwmd-ranking-filters__field">
                    <span>
                        <?php echo esc_html__('State', 'local-directory-framework'); ?>
                    </span>

                    <select
                        name="ranking_state"
                        required
                    >
                        <option value="">
                            <?php echo esc_html__('Choose a state', 'local-directory-framework'); ?>
                        </option>

                        <?php foreach ($state_terms as $term) : ?>
                            <option
                                value="<?php echo esc_attr($term->slug); ?>"
                                <?php selected(
                                    $selection['slugs']['state'],
                                    $term->slug
                                ); ?>
                            >
                                <?php echo esc_html($term->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="nwmd-ranking-filters__field">
                    <span>
                        <?php echo esc_html__('City', 'local-directory-framework'); ?>
                    </span>

                    <select
                        name="ranking_city"
                        required
                    >
                        <option value="">
                            <?php echo esc_html__('Choose a city', 'local-directory-framework'); ?>
                        </option>

                        <?php foreach ($city_terms as $term) : ?>
                            <option
                                value="<?php echo esc_attr($term->slug); ?>"
                                <?php selected(
                                    $selection['slugs']['city'],
                                    $term->slug
                                ); ?>
                            >
                                <?php echo esc_html($term->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="nwmd-ranking-filters__field">
                    <span>
                        <?php echo esc_html__('Category', 'local-directory-framework'); ?>
                    </span>

                    <select
                        name="ranking_category"
                        required
                    >
                        <option value="">
                            <?php echo esc_html__('Choose a category', 'local-directory-framework'); ?>
                        </option>

                        <?php foreach ($category_terms as $term) : ?>
                            <option
                                value="<?php echo esc_attr($term->slug); ?>"
                                <?php selected(
                                    $selection['slugs']['category'],
                                    $term->slug
                                ); ?>
                            >
                                <?php echo esc_html($term->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="nwmd-ranking-filters__field">
                    <span>
                        <?php echo esc_html__('Specialty', 'local-directory-framework'); ?>
                    </span>

                    <select name="ranking_specialty">
                        <option value="">
                            <?php echo esc_html__('All specialties', 'local-directory-framework'); ?>
                        </option>

                        <?php foreach ($specialty_terms as $term) : ?>
                            <option
                                value="<?php echo esc_attr($term->slug); ?>"
                                <?php selected(
                                    $selection['slugs']['specialty'],
                                    $term->slug
                                ); ?>
                            >
                                <?php echo esc_html($term->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <div class="nwmd-ranking-filters__actions">
                <button
                    class="nwmd-ranking-filters__submit"
                    type="submit"
                >
                    <?php echo esc_html__('View Top 10', 'local-directory-framework'); ?>
                </button>

                <?php if (!empty(array_filter($selection['slugs']))) : ?>
                    <a
                        class="nwmd-ranking-filters__clear"
                        href="<?php echo esc_url(
                            nwmd_directory_get_public_rankings_url()
                        ); ?>"
                    >
                        <?php echo esc_html__('Clear Selection', 'local-directory-framework'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </form>

        <?php if (!empty($selection['invalid'])) : ?>

            <div
                class="nwmd-request-notice nwmd-request-notice--error"
                role="alert"
            >
                <p>
                    <?php
                    echo esc_html__(
                        'Choose a valid state, city, category, and specialty combination.',
                        'local-directory-framework'
                    );
                    ?>
                </p>
            </div>

        <?php elseif (empty($selection['ready'])) : ?>

            <div class="nwmd-directory__empty">
                <h2>
                    <?php echo esc_html__('Choose your ranking area.', 'local-directory-framework'); ?>
                </h2>

                <p>
                    <?php
                    echo esc_html__(
                        'Select a state, city, and category. Specialty is optional.',
                        'local-directory-framework'
                    );
                    ?>
                </p>
            </div>

        <?php elseif (empty($entries)) : ?>

            <div class="nwmd-directory__empty">
                <h2>
                    <?php echo esc_html__('No published rankings matched.', 'local-directory-framework'); ?>
                </h2>

                <p>
                    <?php
                    echo esc_html__(
                        'Try another specialty or choose a different location and category.',
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
                        <?php
                        echo esc_html(
                            implode(
                                ' · ',
                                $result_labels
                            )
                        );
                        ?>
                    </h2>
                </header>

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

                        $permalink = get_permalink(
                            $business->ID
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
                                        __(
                                            'Rank %d',
                                            'local-directory-framework'
                                        ),
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
                                    aria-label="<?php echo esc_attr(
                                        $business->post_title
                                    ); ?>"
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
            </section>

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
</main>

<?php
get_footer();
