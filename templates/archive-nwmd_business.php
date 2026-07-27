<?php

if (!defined('ABSPATH')) {
    exit;
}

get_header();

$archive_title = post_type_archive_title(
    '',
    false
);

$archive_url = get_post_type_archive_link(
    'nwmd_business'
);

$current_category = nwmd_directory_get_archive_filter_value(
    'filter_category'
);

$current_specialty = nwmd_directory_get_archive_filter_value(
    'filter_specialty'
);

$current_city = nwmd_directory_get_archive_filter_value(
    'filter_city'
);

$category_terms = nwmd_directory_get_archive_filter_terms(
    'nwmd_category'
);

$specialty_terms = nwmd_directory_get_archive_filter_terms(
    'nwmd_specialty'
);

$city_terms = nwmd_directory_get_archive_filter_terms(
    'nwmd_city'
);

$active_filters = array_filter(
    [
        'filter_category'  => $current_category,
        'filter_specialty' => $current_specialty,
        'filter_city'      => $current_city,
    ]
);
?>

<main class="nwmd-directory" id="primary">
    <section class="nwmd-directory__intro">
        <p class="nwmd-directory__eyebrow">
            <?php echo esc_html__('Northwest Monthly Directory', 'local-directory-framework'); ?>
        </p>

        <h1 class="nwmd-directory__title">
            <?php echo esc_html($archive_title); ?>
        </h1>

        <p class="nwmd-directory__description">
            <?php echo esc_html__('Discover local businesses serving Portland and nearby Oregon communities.', 'local-directory-framework'); ?>
        </p>

        <div class="nwmd-directory__actions">
            <a
                class="nwmd-directory__rankings-link"
                href="<?php echo esc_url(
                    nwmd_directory_get_public_rankings_url()
                ); ?>"
            >
                <?php echo esc_html__('View Top 10 Rankings', 'local-directory-framework'); ?>
            </a>

            <a
                class="nwmd-directory__manage-link"
                href="<?php echo esc_url(
                    nwmd_directory_get_business_request_url()
                ); ?>"
            >
                <?php echo esc_html__('Manage a Business', 'local-directory-framework'); ?>
            </a>
        </div>
    </section>

    <?php if (!empty($archive_url)) : ?>
        <form
            class="nwmd-directory-filters"
            action="<?php echo esc_url($archive_url); ?>"
            method="get"
        >
            <div class="nwmd-directory-filters__fields">
                <label class="nwmd-directory-filters__field">
                    <span>
                        <?php echo esc_html__('Category', 'local-directory-framework'); ?>
                    </span>

                    <select name="filter_category">
                        <option value="">
                            <?php echo esc_html__('All Categories', 'local-directory-framework'); ?>
                        </option>

                        <?php foreach ($category_terms as $term) : ?>
                            <option
                                value="<?php echo esc_attr($term->slug); ?>"
                                <?php selected($current_category, $term->slug); ?>
                            >
                                <?php echo esc_html($term->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="nwmd-directory-filters__field">
                    <span>
                        <?php echo esc_html__('Specialty', 'local-directory-framework'); ?>
                    </span>

                    <select name="filter_specialty">
                        <option value="">
                            <?php echo esc_html__('All Specialties', 'local-directory-framework'); ?>
                        </option>

                        <?php foreach ($specialty_terms as $term) : ?>
                            <option
                                value="<?php echo esc_attr($term->slug); ?>"
                                <?php selected($current_specialty, $term->slug); ?>
                            >
                                <?php echo esc_html($term->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="nwmd-directory-filters__field">
                    <span>
                        <?php echo esc_html__('City', 'local-directory-framework'); ?>
                    </span>

                    <select name="filter_city">
                        <option value="">
                            <?php echo esc_html__('All Cities', 'local-directory-framework'); ?>
                        </option>

                        <?php foreach ($city_terms as $term) : ?>
                            <option
                                value="<?php echo esc_attr($term->slug); ?>"
                                <?php selected($current_city, $term->slug); ?>
                            >
                                <?php echo esc_html($term->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <div class="nwmd-directory-filters__actions">
                <button
                    class="nwmd-directory-filters__submit"
                    type="submit"
                >
                    <?php echo esc_html__('Apply Filters', 'local-directory-framework'); ?>
                </button>

                <?php if (!empty($active_filters)) : ?>
                    <a
                        class="nwmd-directory-filters__clear"
                        href="<?php echo esc_url($archive_url); ?>"
                    >
                        <?php echo esc_html__('Clear Filters', 'local-directory-framework'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    <?php endif; ?>

    <?php if (have_posts()) : ?>

        <div class="nwmd-directory__grid">
            <?php
            while (have_posts()) :
                the_post();

                $post_id = get_the_ID();

                $categories = nwmd_directory_get_business_term_names(
                    $post_id,
                    'nwmd_category'
                );

                $cities = nwmd_directory_get_business_term_names(
                    $post_id,
                    'nwmd_city'
                );

                $states = nwmd_directory_get_business_term_names(
                    $post_id,
                    'nwmd_state'
                );

                $locations = array_merge(
                    $cities,
                    $states
                );
                ?>

                <article <?php post_class('nwmd-business-card'); ?>>
                    <?php if (has_post_thumbnail()) : ?>
                        <a
                            class="nwmd-business-card__image"
                            href="<?php echo esc_url(get_permalink()); ?>"
                            aria-label="<?php echo esc_attr(get_the_title()); ?>"
                        >
                            <?php
                            echo wp_kses_post(
                                get_the_post_thumbnail(
                                    $post_id,
                                    'medium_large'
                                )
                            );
                            ?>
                        </a>
                    <?php endif; ?>

                    <div class="nwmd-business-card__content">
                        <?php if (!empty($categories)) : ?>
                            <p class="nwmd-business-card__category">
                                <?php echo esc_html(implode(' · ', $categories)); ?>
                            </p>
                        <?php endif; ?>

                        <h2 class="nwmd-business-card__title">
                            <a href="<?php echo esc_url(get_permalink()); ?>">
                                <?php echo esc_html(get_the_title()); ?>
                            </a>
                        </h2>

                        <?php if (!empty($locations)) : ?>
                            <p class="nwmd-business-card__location">
                                <?php echo esc_html(implode(', ', $locations)); ?>
                            </p>
                        <?php endif; ?>

                        <p class="nwmd-business-card__excerpt">
                            <?php
                            echo esc_html(
                                wp_trim_words(
                                    get_the_excerpt(),
                                    28
                                )
                            );
                            ?>
                        </p>

                        <a
                            class="nwmd-business-card__link"
                            href="<?php echo esc_url(get_permalink()); ?>"
                        >
                            <?php echo esc_html__('View Business', 'local-directory-framework'); ?>
                        </a>
                    </div>
                </article>

            <?php endwhile; ?>
        </div>

        <div class="nwmd-directory__pagination">
            <?php
            the_posts_pagination(
                [
                    'mid_size'  => 1,
                    'prev_text' => esc_html__('Previous', 'local-directory-framework'),
                    'next_text' => esc_html__('Next', 'local-directory-framework'),
                    'add_args'  => $active_filters,
                ]
            );
            ?>
        </div>

    <?php else : ?>

        <div class="nwmd-directory__empty">
            <h2>
                <?php
                echo esc_html(
                    !empty($active_filters)
                        ? __('No businesses matched these filters.', 'local-directory-framework')
                        : __('No published businesses yet.', 'local-directory-framework')
                );
                ?>
            </h2>

            <p>
                <?php
                echo esc_html(
                    !empty($active_filters)
                        ? __('Try changing or clearing the selected filters.', 'local-directory-framework')
                        : __('Published business profiles will appear here.', 'local-directory-framework')
                );
                ?>
            </p>
        </div>

    <?php endif; ?>
</main>

<?php
get_footer();
