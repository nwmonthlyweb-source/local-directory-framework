<?php

if (!defined('ABSPATH')) {
    exit;
}

while (have_posts()) :
    the_post();

    $post_id = get_the_ID();

    $legal_name = sanitize_text_field(
        nwmd_directory_get_business_record_meta(
            $post_id,
            'legal_name'
        )
    );

    $website_url = esc_url_raw(
        nwmd_directory_get_business_record_meta(
            $post_id,
            'website_url'
        )
    );

    $public_email = sanitize_email(
        nwmd_directory_get_business_record_meta(
            $post_id,
            'public_email'
        )
    );

    $public_phone = sanitize_text_field(
        nwmd_directory_get_business_record_meta(
            $post_id,
            'public_phone'
        )
    );

    $street_address = sanitize_text_field(
        nwmd_directory_get_business_record_meta(
            $post_id,
            'street_address'
        )
    );

    $postal_code = sanitize_text_field(
        nwmd_directory_get_business_record_meta(
            $post_id,
            'postal_code'
        )
    );

    $phone_href = preg_replace(
        '/[^0-9+]/',
        '',
        $public_phone
    );

    $category_terms = get_the_terms(
        $post_id,
        'nwmd_category'
    );

    $specialty_terms = get_the_terms(
        $post_id,
        'nwmd_specialty'
    );

    $city_terms = get_the_terms(
        $post_id,
        'nwmd_city'
    );

    $state_terms = get_the_terms(
        $post_id,
        'nwmd_state'
    );

    $category_terms = is_wp_error($category_terms)
        ? []
        : (array) $category_terms;

    $specialty_terms = is_wp_error($specialty_terms)
        ? []
        : (array) $specialty_terms;

    $city_terms = is_wp_error($city_terms)
        ? []
        : (array) $city_terms;

    $state_terms = is_wp_error($state_terms)
        ? []
        : (array) $state_terms;

    $category_names = wp_list_pluck(
        $category_terms,
        'name'
    );

    $specialty_names = wp_list_pluck(
        $specialty_terms,
        'name'
    );

    $city_names = wp_list_pluck(
        $city_terms,
        'name'
    );

    $state_names = wp_list_pluck(
        $state_terms,
        'name'
    );

    $category_term = $category_terms[0] ?? null;
    $city_term = $city_terms[0] ?? null;

    $state_abbreviation = '';

    if ($city_term instanceof WP_Term) {
        $state_abbreviation = sanitize_text_field(
            get_term_meta(
                $city_term->term_id,
                'nwmd_state_abbreviation',
                true
            )
        );
    }

    $location_parts = [];

    if ($city_term instanceof WP_Term) {
        $location_parts[] = $city_term->name;
    }

    if ('' !== $state_abbreviation) {
        $location_parts[] = $state_abbreviation;
    } elseif (!empty($state_names)) {
        $location_parts[] = $state_names[0];
    }

    $location_label = implode(
        ', ',
        array_filter($location_parts)
    );

    $back_url = home_url('/');

    if (
        $category_term instanceof WP_Term &&
        $city_term instanceof WP_Term
    ) {
        $back_url = nwmd_directory_get_app_city_url(
            $category_term->slug,
            $city_term->slug
        );
    } elseif ($category_term instanceof WP_Term) {
        $back_url = nwmd_directory_get_app_category_url(
            $category_term->slug
        );
    }

    $manage_business_url =
        nwmd_directory_get_business_request_url(
            [
                'request_type' => 'update',
                'business_id'  => $post_id,
            ]
        );

    $address_parts = array_filter(
        [
            $street_address,
            $location_label,
            $postal_code,
        ]
    );

    $address_label = implode(
        ', ',
        $address_parts
    );

    $business_ad_context =
        nwmd_directory_get_business_ad_context(
            $post_id
        );

    $content = apply_filters(
        'the_content',
        get_the_content()
    );
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

<body <?php body_class('nwmd-app-body nwmd-profile-app-body'); ?>>
<?php wp_body_open(); ?>

<main class="nwmd-profile-app" id="primary">
    <article class="nwmd-profile-app__panel">
        <header class="nwmd-profile-bar">
            <a
                class="nwmd-profile-bar__back"
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
                class="nwmd-profile-bar__brand"
                href="<?php echo esc_url(home_url('/')); ?>"
            >
                <span
                    class="nwmd-profile-bar__mark"
                    aria-hidden="true"
                >
                    NW
                </span>

                <span>NW Monthly</span>
            </a>
        </header>

        <header class="nwmd-profile-heading">
            <?php if (!empty($category_names)) : ?>
                <p class="nwmd-profile-heading__eyebrow">
                    <?php
                    echo esc_html(
                        implode(' · ', $category_names)
                    );
                    ?>
                </p>
            <?php endif; ?>

            <h1>
                <?php echo esc_html(get_the_title()); ?>
            </h1>

            <?php if ('' !== $location_label) : ?>
                <p class="nwmd-profile-heading__location">
                    <?php echo esc_html($location_label); ?>
                </p>
            <?php endif; ?>

            <?php
            if (
                '' !== $legal_name &&
                $legal_name !== get_the_title()
            ) :
                ?>
                <p class="nwmd-profile-heading__legal">
                    <?php echo esc_html($legal_name); ?>
                </p>
            <?php endif; ?>
        </header>

        <?php if (has_post_thumbnail()) : ?>
            <figure class="nwmd-profile-image">
                <?php
                echo wp_kses_post(
                    get_the_post_thumbnail(
                        $post_id,
                        'large',
                        [
                            'loading' => 'eager',
                        ]
                    )
                );
                ?>
            </figure>
        <?php endif; ?>

        <?php
        if (
            '' !== $phone_href ||
            wp_http_validate_url($website_url) ||
            '' !== $public_email
        ) :
            ?>
            <nav
                class="nwmd-profile-actions"
                aria-label="<?php
                    echo esc_attr__(
                        'Business contact actions',
                        'local-directory-framework'
                    );
                ?>"
            >
                <?php if ('' !== $phone_href) : ?>
                    <a href="<?php echo esc_url('tel:' . $phone_href); ?>">
                        <?php
                        echo esc_html__(
                            'Call',
                            'local-directory-framework'
                        );
                        ?>
                    </a>
                <?php endif; ?>

                <?php if (wp_http_validate_url($website_url)) : ?>
                    <a
                        href="<?php echo esc_url($website_url); ?>"
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

                <?php if ('' !== $public_email) : ?>
                    <a href="<?php echo esc_url('mailto:' . $public_email); ?>">
                        <?php
                        echo esc_html__(
                            'Email',
                            'local-directory-framework'
                        );
                        ?>
                    </a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>

        <?php if ('' !== trim(wp_strip_all_tags($content))) : ?>
            <section class="nwmd-profile-description">
                <?php echo wp_kses_post($content); ?>
            </section>
        <?php endif; ?>

        <section class="nwmd-profile-details">
            <h2>
                <?php
                echo esc_html__(
                    'Business Details',
                    'local-directory-framework'
                );
                ?>
            </h2>

            <dl>
                <?php if (!empty($specialty_names)) : ?>
                    <div>
                        <dt>
                            <?php
                            echo esc_html__(
                                'Specialties',
                                'local-directory-framework'
                            );
                            ?>
                        </dt>

                        <dd>
                            <?php
                            echo esc_html(
                                implode(', ', $specialty_names)
                            );
                            ?>
                        </dd>
                    </div>
                <?php endif; ?>

                <?php if ('' !== $public_phone) : ?>
                    <div>
                        <dt>
                            <?php
                            echo esc_html__(
                                'Phone',
                                'local-directory-framework'
                            );
                            ?>
                        </dt>

                        <dd>
                            <a href="<?php echo esc_url('tel:' . $phone_href); ?>">
                                <?php echo esc_html($public_phone); ?>
                            </a>
                        </dd>
                    </div>
                <?php endif; ?>

                <?php if ('' !== $public_email) : ?>
                    <div>
                        <dt>
                            <?php
                            echo esc_html__(
                                'Email',
                                'local-directory-framework'
                            );
                            ?>
                        </dt>

                        <dd>
                            <a href="<?php echo esc_url('mailto:' . $public_email); ?>">
                                <?php echo esc_html($public_email); ?>
                            </a>
                        </dd>
                    </div>
                <?php endif; ?>

                <?php if (wp_http_validate_url($website_url)) : ?>
                    <div>
                        <dt>
                            <?php
                            echo esc_html__(
                                'Website',
                                'local-directory-framework'
                            );
                            ?>
                        </dt>

                        <dd>
                            <a
                                href="<?php echo esc_url($website_url); ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <?php
                                echo esc_html__(
                                    'Visit Website',
                                    'local-directory-framework'
                                );
                                ?>
                            </a>
                        </dd>
                    </div>
                <?php endif; ?>

                <?php if ('' !== $address_label) : ?>
                    <div>
                        <dt>
                            <?php
                            echo esc_html__(
                                'Address',
                                'local-directory-framework'
                            );
                            ?>
                        </dt>

                        <dd>
                            <?php echo esc_html($address_label); ?>
                        </dd>
                    </div>
                <?php elseif ('' !== $location_label) : ?>
                    <div>
                        <dt>
                            <?php
                            echo esc_html__(
                                'Service Area',
                                'local-directory-framework'
                            );
                            ?>
                        </dt>

                        <dd>
                            <?php echo esc_html($location_label); ?>
                        </dd>
                    </div>
                <?php endif; ?>
            </dl>
        </section>

        <section class="nwmd-profile-manage">
            <div>
                <h2>
                    <?php
                    echo esc_html__(
                        'Manage This Business',
                        'local-directory-framework'
                    );
                    ?>
                </h2>

                <p>
                    <?php
                    echo esc_html__(
                        'Add, claim, update, correct, or request removal of this listing.',
                        'local-directory-framework'
                    );
                    ?>
                </p>
            </div>

            <a href="<?php echo esc_url($manage_business_url); ?>">
                <?php
                echo esc_html__(
                    'Manage Listing',
                    'local-directory-framework'
                );
                ?>
            </a>
        </section>

        <?php
        nwmd_directory_render_ad(
            'business_profile_bottom',
            $business_ad_context
        );
        ?>    </article>

    <?php nwmd_directory_render_app_footer(false); ?>
</main>

<?php wp_footer(); ?>
</body>
</html>
    <?php
endwhile;