<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register the Business Details meta box.
 */
function nwmd_directory_register_business_details_box() {

    add_meta_box(
        'nwmd_business_details',
        'Business Details',
        'nwmd_directory_render_business_details_box',
        'nwmd_business',
        'normal',
        'high'
    );
}

add_action(
    'add_meta_boxes',
    'nwmd_directory_register_business_details_box'
);

/**
 * Render the Business Details meta box.
 *
 * @param WP_Post $post Current business post.
 */
function nwmd_directory_render_business_details_box($post) {

    $legal_name = get_post_meta(
        $post->ID,
        'nwmd_legal_name',
        true
    );

    wp_nonce_field(
        'nwmd_save_business_details',
        'nwmd_business_details_nonce'
    );

    ?>
    <p>
        <label for="nwmd_legal_name">
            <strong>Legal Business Name</strong>
        </label>
    </p>

    <p>
        <input
            type="text"
            id="nwmd_legal_name"
            name="nwmd_legal_name"
            value="<?php echo esc_attr($legal_name); ?>"
            class="widefat"
        >
    </p>
    <?php
}

/**
 * Save the Business Details fields.
 *
 * @param int $post_id Business post ID.
 */
function nwmd_directory_save_business_details($post_id) {

    if (
        wp_is_post_revision($post_id) ||
        wp_is_post_autosave($post_id)
    ) {
        return;
    }

    if (
        !isset($_POST['nwmd_business_details_nonce']) ||
        !wp_verify_nonce(
            sanitize_text_field(
                wp_unslash(
                    $_POST['nwmd_business_details_nonce']
                )
            ),
            'nwmd_save_business_details'
        )
    ) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $legal_name = isset($_POST['nwmd_legal_name'])
        ? sanitize_text_field(
            wp_unslash($_POST['nwmd_legal_name'])
        )
        : '';

    if ('' === $legal_name) {
        delete_post_meta(
            $post_id,
            'nwmd_legal_name'
        );

        return;
    }

    update_post_meta(
        $post_id,
        'nwmd_legal_name',
        $legal_name
    );
}

add_action(
    'save_post_nwmd_business',
    'nwmd_directory_save_business_details'
);