<?php

if (!defined('ABSPATH')) {
    exit;
}
$business = nwmd_directory_get_public_business_request_business();

$selected_request_type
    = nwmd_directory_get_public_business_request_type(
        $business instanceof WP_Post
            ? 'update'
            : 'add'
    );

$request_types = nwmd_directory_get_business_request_type_choices();

$archive_url = get_post_type_archive_link(
    'nwmd_business'
);

$back_url = !empty($archive_url)
    ? $archive_url
    : home_url('/');

$back_url =
    nwmd_directory_get_requested_public_return_url(
        $back_url
    );

$business_name = $business instanceof WP_Post
    ? sanitize_text_field(
        get_the_title($business->ID)
    )
    : '';
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

<body <?php body_class('nwmd-app-body nwmd-request-app-body'); ?>>
<?php wp_body_open(); ?>

<div class="nwmd-request-shell">
<header class="nwmd-request-bar">
    <a
        class="nwmd-request-bar__brand"
        href="<?php echo esc_url(home_url('/')); ?>"
    >
        <span
            class="nwmd-request-bar__mark"
            aria-hidden="true"
        >
            NW
        </span>

        <span>NW Monthly</span>
    </a>
</header>

<main
    class="nwmd-directory nwmd-directory--request"
    id="primary"
>
    <section class="nwmd-directory__intro">
        <p class="nwmd-directory__eyebrow">
            <?php echo esc_html__('NW Monthly', 'local-directory-framework'); ?>
        </p>

        <h1 class="nwmd-directory__title">
            <?php echo esc_html__('Manage a Business', 'local-directory-framework'); ?>
        </h1>

        <p class="nwmd-directory__description">
            <?php
            echo esc_html__(
                'Submit an add, claim or update, correction, or removal request. Every request requires email verification and administrator review.',
                'local-directory-framework'
            );
            ?>
        </p>
    </section>

    <?php nwmd_directory_render_business_request_notice(); ?>

    <div class="nwmd-request-layout">
        <form
            class="nwmd-request-form"
            action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
            method="post"
        >
            <input
                type="hidden"
                name="action"
                value="nwmd_submit_business_request"
            >
            <input
                type="hidden"
                name="business_post_id"
                value="<?php echo esc_attr(
                    $business instanceof WP_Post
                        ? $business->ID
                        : 0
                ); ?>"
            >

            <input
                type="hidden"
                name="return_to"
                value="<?php echo esc_url($back_url); ?>"
            >

            <?php
            wp_nonce_field(
                'nwmd_submit_business_request',
                'nwmd_business_request_nonce'
            );
            ?>

            <div class="nwmd-request-form__field">
                <label for="nwmd_request_type">
                    <?php echo esc_html__('Request Type', 'local-directory-framework'); ?>
                    <span aria-hidden="true">*</span>
                </label>

                <select
                    id="nwmd_request_type"
                    name="request_type"
                    required
                >
                    <?php foreach ($request_types as $value => $label) : ?>
                        <option
                            value="<?php echo esc_attr($value); ?>"
                            <?php selected($selected_request_type, $value); ?>
                        >
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="nwmd-request-form__grid">
                <div class="nwmd-request-form__field">
                    <label for="nwmd_requester_name">
                        <?php echo esc_html__('Your Name', 'local-directory-framework'); ?>
                        <span aria-hidden="true">*</span>
                    </label>

                    <input
                        type="text"
                        id="nwmd_requester_name"
                        name="requester_name"
                        maxlength="255"
                        autocomplete="name"
                        required
                    >
                </div>

                <div class="nwmd-request-form__field">
                    <label for="nwmd_requester_email">
                        <?php echo esc_html__('Your Email', 'local-directory-framework'); ?>
                        <span aria-hidden="true">*</span>
                    </label>

                    <input
                        type="email"
                        id="nwmd_requester_email"
                        name="requester_email"
                        maxlength="320"
                        autocomplete="email"
                        required
                    >
                </div>
            </div>

            <div class="nwmd-request-form__field">
                <label for="nwmd_requester_phone">
                    <?php echo esc_html__('Your Phone', 'local-directory-framework'); ?>
                </label>

                <input
                    type="tel"
                    id="nwmd_requester_phone"
                    name="requester_phone"
                    maxlength="100"
                    autocomplete="tel"
                >
            </div>

            <div class="nwmd-request-form__field">
                <label for="nwmd_business_name">
                    <?php echo esc_html__('Business Name', 'local-directory-framework'); ?>
                    <span aria-hidden="true">*</span>
                </label>

                <input
                    type="text"
                    id="nwmd_business_name"
                    name="business_name"
                    value="<?php echo esc_attr($business_name); ?>"
                    maxlength="255"
                    <?php echo $business instanceof WP_Post ? 'readonly' : ''; ?>
                    required
                >
            </div>

            <div class="nwmd-request-form__grid">
                <div class="nwmd-request-form__field">
                    <label for="nwmd_business_website">
                        <?php echo esc_html__('Business Website', 'local-directory-framework'); ?>
                    </label>

                    <input
                        type="url"
                        id="nwmd_business_website"
                        name="business_website"
                        placeholder="https://example.com"
                    >
                </div>

                <div class="nwmd-request-form__field">
                    <label for="nwmd_business_address">
                        <?php echo esc_html__('Business Address', 'local-directory-framework'); ?>
                    </label>

                    <input
                        type="text"
                        id="nwmd_business_address"
                        name="business_address"
                        maxlength="255"
                        autocomplete="street-address"
                    >
                </div>
            </div>

            <div class="nwmd-request-form__field">
                <label for="nwmd_request_details">
                    <?php echo esc_html__('Request Details', 'local-directory-framework'); ?>
                    <span aria-hidden="true">*</span>
                </label>

                <textarea
                    id="nwmd_request_details"
                    name="request_details"
                    rows="8"
                    maxlength="5000"
                    required
                ></textarea>

                <p class="nwmd-request-form__help">
                    <?php
                    echo esc_html__(
                        'Explain what should be added, claimed, updated, corrected, or removed. Include enough information for an administrator to verify the request.',
                        'local-directory-framework'
                    );
                    ?>
                </p>
            </div>

            <div
                class="nwmd-request-form__honeypot"
                aria-hidden="true"
            >
                <label for="nwmd_company_fax">
                    <?php echo esc_html__('Company Fax', 'local-directory-framework'); ?>
                </label>

                <input
                    type="text"
                    id="nwmd_company_fax"
                    name="company_fax"
                    tabindex="-1"
                    autocomplete="off"
                >
            </div>

            <label class="nwmd-request-form__confirmation">
                <input
                    type="checkbox"
                    name="authorization_confirmed"
                    value="1"
                    required
                >

                <span>
                    <?php
                    echo esc_html__(
                        'I confirm that the information in this request is accurate and that I am authorized to submit it.',
                        'local-directory-framework'
                    );
                    ?>
                </span>
            </label>

            <button
                class="nwmd-request-form__submit"
                type="submit"
            >
                <?php echo esc_html__('Send Verification Email', 'local-directory-framework'); ?>
            </button>

            <p class="nwmd-request-form__privacy">
                <?php
                echo esc_html__(
                    'Your email is used to verify this request and communicate about administrator review. Submitting a request does not automatically change or publish a business profile.',
                    'local-directory-framework'
                );
                ?>
            </p>
        </form>

        <aside class="nwmd-request-sidebar">
            <h2>
                <?php echo esc_html__('What happens next?', 'local-directory-framework'); ?>
            </h2>

            <ol>
                <li>
                    <?php echo esc_html__('We email you a verification link.', 'local-directory-framework'); ?>
                </li>
                <li>
                    <?php echo esc_html__('You verify your email within 7 days.', 'local-directory-framework'); ?>
                </li>
                <li>
                    <?php echo esc_html__('An administrator reviews the request and supporting details.', 'local-directory-framework'); ?>
                </li>
                <li>
                    <?php echo esc_html__('Approved changes are applied manually after verification.', 'local-directory-framework'); ?>
                </li>
            </ol>

            <?php if (!empty($archive_url)) : ?>
                <a
                    class="nwmd-business-profile__back"
                    href="<?php echo esc_url($archive_url); ?>"
                >
                    <?php echo esc_html__('Back to Businesses', 'local-directory-framework'); ?>
                </a>
            <?php endif; ?>
        </aside>
    </div>
</main>

<div class="nwmd-request-footer">
    <?php nwmd_directory_render_app_footer(false); ?>
</div>
</div>

<?php wp_footer(); ?>
</body>
</html>