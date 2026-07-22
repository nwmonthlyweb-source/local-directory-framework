<?php

if (!defined('ABSPATH')) {
    exit;
}

get_header();

$archive_title = post_type_archive_title(
    '',
    false
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
    </section>

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
                ]
            );
            ?>
        </div>

    <?php else : ?>

        <div class="nwmd-directory__empty">
            <h2>
                <?php echo esc_html__('No published businesses yet.', 'local-directory-framework'); ?>
            </h2>

            <p>
                <?php echo esc_html__('Published business profiles will appear here.', 'local-directory-framework'); ?>
            </p>
        </div>

    <?php endif; ?>
</main>

<?php
get_footer();