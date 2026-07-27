<?php

if (!defined('ABSPATH')) {
    exit;
}

get_header();

while (have_posts()) :
    the_post();

    $post_id = get_the_ID();

    $legal_name = sanitize_text_field(
        get_post_meta(
            $post_id,
            'nwmd_legal_name',
            true
        )
    );

    $website_url = esc_url_raw(
        get_post_meta(
            $post_id,
            'nwmd_website_url',
            true
        )
    );

    $public_email = sanitize_email(
        get_post_meta(
            $post_id,
            'nwmd_public_email',
            true
        )
    );

    $public_phone = sanitize_text_field(
        get_post_meta(
            $post_id,
            'nwmd_public_phone',
            true
        )
    );

    $street_address = sanitize_text_field(
        get_post_meta(
            $post_id,
            'nwmd_street_address',
            true
        )
    );

    $postal_code = sanitize_text_field(
        get_post_meta(
            $post_id,
            'nwmd_postal_code',
            true
        )
    );

    $phone_href = preg_replace(
        '/[^0-9+]/',
        '',
        $public_phone
    );

    $categories = nwmd_directory_get_business_term_names(
        $post_id,
        'nwmd_category'
    );

    $specialties = nwmd_directory_get_business_term_names(
        $post_id,
        'nwmd_specialty'
    );

    $cities = nwmd_directory_get_business_term_names(
        $post_id,
        'nwmd_city'
    );

    $states = nwmd_directory_get_business_term_names(
        $post_id,
        'nwmd_state'
    );

    $archive_url = get_post_type_archive_link(
        'nwmd_business'
    );

    $manage_business_url = nwmd_directory_get_business_request_url(
        [
            'request_type' => 'update',
            'business_id'  => $post_id,
        ]
    );

    $business_ad_context = nwmd_directory_get_business_ad_context(
        $post_id
    );
    ?>

    <main class="nwmd-directory nwmd-directory--single" id="primary">
        <article <?php post_class('nwmd-business-profile'); ?>>

            <?php if (!empty($archive_url)) : ?>
                <a
                    class="nwmd-business-profile__back"
                    href="<?php echo esc_url($archive_url); ?>"
                >
                    <?php echo esc_html__('← All Businesses', 'local-directory-framework'); ?>
                </a>
            <?php endif; ?>

            <header class="nwmd-business-profile__header">
                <?php if (!empty($categories)) : ?>
                    <p class="nwmd-directory__eyebrow">
                        <?php echo esc_html(implode(' · ', $categories)); ?>
                    </p>
                <?php endif; ?>

                <h1 class="nwmd-directory__title">
                    <?php echo esc_html(get_the_title()); ?>
                </h1>

                <?php if (!empty($legal_name)) : ?>
                    <p class="nwmd-business-profile__legal-name">
                        <?php echo esc_html($legal_name); ?>
                    </p>
                <?php endif; ?>
            </header>

            <?php if (has_post_thumbnail()) : ?>
                <div class="nwmd-business-profile__image">
                    <?php
                    echo wp_kses_post(
                        get_the_post_thumbnail(
                            $post_id,
                            'large'
                        )
                    );
                    ?>
                </div>
            <?php endif; ?>

            <div class="nwmd-business-profile__layout">
                <div class="nwmd-business-profile__content">
                    <?php the_content(); ?>
                </div>

                <aside class="nwmd-business-profile__details">
                    <h2>
                        <?php echo esc_html__('Business Details', 'local-directory-framework'); ?>
                    </h2>

                    <?php if (!empty($website_url)) : ?>
                        <div class="nwmd-business-profile__detail">
                            <h3>
                                <?php echo esc_html__('Website', 'local-directory-framework'); ?>
                            </h3>

                            <p>
                                <a
                                    href="<?php echo esc_url($website_url); ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <?php echo esc_html__('Visit Website', 'local-directory-framework'); ?>
                                </a>
                            </p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($public_phone)) : ?>
                        <div class="nwmd-business-profile__detail">
                            <h3>
                                <?php echo esc_html__('Phone', 'local-directory-framework'); ?>
                            </h3>

                            <p>
                                <?php if (!empty($phone_href)) : ?>
                                    <a href="<?php echo esc_url('tel:' . $phone_href); ?>">
                                        <?php echo esc_html($public_phone); ?>
                                    </a>
                                <?php else : ?>
                                    <?php echo esc_html($public_phone); ?>
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($public_email)) : ?>
                        <div class="nwmd-business-profile__detail">
                            <h3>
                                <?php echo esc_html__('Email', 'local-directory-framework'); ?>
                            </h3>

                            <p>
                                <a href="<?php echo esc_url('mailto:' . $public_email); ?>">
                                    <?php echo esc_html($public_email); ?>
                                </a>
                            </p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($street_address) || !empty($postal_code)) : ?>
                        <div class="nwmd-business-profile__detail">
                            <h3>
                                <?php echo esc_html__('Address', 'local-directory-framework'); ?>
                            </h3>

                            <p>
                                <?php
                                echo esc_html(
                                    implode(
                                        ', ',
                                        array_filter(
                                            [
                                                $street_address,
                                                $postal_code,
                                            ]
                                        )
                                    )
                                );
                                ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($specialties)) : ?>
                        <div class="nwmd-business-profile__detail">
                            <h3>
                                <?php echo esc_html__('Specialties', 'local-directory-framework'); ?>
                            </h3>

                            <p>
                                <?php echo esc_html(implode(', ', $specialties)); ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($cities) || !empty($states)) : ?>
                        <div class="nwmd-business-profile__detail">
                            <h3>
                                <?php echo esc_html__('Service Area', 'local-directory-framework'); ?>
                            </h3>

                            <p>
                                <?php
                                echo esc_html(
                                    implode(
                                        ', ',
                                        array_merge(
                                            $cities,
                                            $states
                                        )
                                    )
                                );
                                ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <div class="nwmd-business-profile__detail">
                        <h3>
                            <?php echo esc_html__('Manage This Business', 'local-directory-framework'); ?>
                        </h3>

                        <p>
                            <?php
                            echo esc_html__(
                                'Claim, update, correct, or request removal of this profile.',
                                'local-directory-framework'
                            );
                            ?>
                        </p>

                        <p>
                            <a
                                class="nwmd-business-profile__manage-link"
                                href="<?php echo esc_url($manage_business_url); ?>"
                            >
                                <?php echo esc_html__('Manage a Business', 'local-directory-framework'); ?>
                            </a>
                        </p>
                    </div>
                </aside>
            </div>
        </article>

        <?php
        nwmd_directory_render_ad(
            'business_profile_bottom',
            $business_ad_context
        );
        ?>
    </main>

<?php
endwhile;

get_footer();
