<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create or update Local Directory Framework tables.
 */
function nwmd_directory_install_schema() {

    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset_collate = $wpdb->get_charset_collate();

    $business_index_table = $wpdb->prefix . 'nwmd_business_index';
    $sources_table        = $wpdb->prefix . 'nwmd_business_sources';
    $periods_table        = $wpdb->prefix . 'nwmd_ranking_periods';
    $rankings_table       = $wpdb->prefix . 'nwmd_ranking_entries';
    $requests_table       = $wpdb->prefix . 'nwmd_business_requests';
    $ads_table            = $wpdb->prefix . 'nwmd_ads';

    $queries = [];

    $queries[] = "CREATE TABLE {$business_index_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        business_post_id bigint(20) unsigned NOT NULL,
        legal_name varchar(255) NOT NULL DEFAULT '',
        public_name varchar(255) NOT NULL DEFAULT '',
        website_url varchar(2048) NOT NULL DEFAULT '',
        public_email varchar(320) NOT NULL DEFAULT '',
        public_phone varchar(100) NOT NULL DEFAULT '',
        street_address varchar(255) NOT NULL DEFAULT '',
        city_term_id bigint(20) unsigned NOT NULL DEFAULT 0,
        state_term_id bigint(20) unsigned NOT NULL DEFAULT 0,
        postal_code varchar(20) NOT NULL DEFAULT '',
        latitude decimal(10,7) DEFAULT NULL,
        longitude decimal(10,7) DEFAULT NULL,
        registration_number varchar(100) NOT NULL DEFAULT '',
        license_number varchar(100) NOT NULL DEFAULT '',
        license_status varchar(50) NOT NULL DEFAULT '',
        verification_status varchar(50) NOT NULL DEFAULT 'unverified',
        claimed_status varchar(50) NOT NULL DEFAULT 'unclaimed',
        ranking_eligible tinyint(1) unsigned NOT NULL DEFAULT 0,
        last_verified_at datetime DEFAULT NULL,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        archived_at datetime DEFAULT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY business_post_id (business_post_id),
        KEY city_term_id (city_term_id),
        KEY state_term_id (state_term_id),
        KEY verification_status (verification_status),
        KEY ranking_eligible (ranking_eligible),
        KEY last_verified_at (last_verified_at),
        KEY archived_at (archived_at)
    ) {$charset_collate};";

    $queries[] = "CREATE TABLE {$sources_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        business_post_id bigint(20) unsigned NOT NULL,
        source_type varchar(100) NOT NULL DEFAULT '',
        source_name varchar(255) NOT NULL DEFAULT '',
        source_url varchar(2048) NOT NULL DEFAULT '',
        source_identifier varchar(255) NOT NULL DEFAULT '',
        source_notes longtext NOT NULL,
        retrieved_at datetime DEFAULT NULL,
        verified_at datetime DEFAULT NULL,
        verification_result varchar(100) NOT NULL DEFAULT '',
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY business_post_id (business_post_id),
        KEY source_type (source_type),
        KEY source_identifier (source_identifier),
        KEY retrieved_at (retrieved_at),
        KEY verified_at (verified_at)
    ) {$charset_collate};";

    $queries[] = "CREATE TABLE {$periods_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        period_key varchar(20) NOT NULL,
        period_label varchar(100) NOT NULL DEFAULT '',
        status varchar(40) NOT NULL DEFAULT 'draft',
        published_at datetime DEFAULT NULL,
        created_by bigint(20) unsigned NOT NULL DEFAULT 0,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY period_key (period_key),
        KEY status (status),
        KEY published_at (published_at)
    ) {$charset_collate};";

    $queries[] = "CREATE TABLE {$rankings_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        ranking_period_id bigint(20) unsigned NOT NULL,
        state_term_id bigint(20) unsigned NOT NULL DEFAULT 0,
        city_term_id bigint(20) unsigned NOT NULL DEFAULT 0,
        category_term_id bigint(20) unsigned NOT NULL DEFAULT 0,
        specialty_term_id bigint(20) unsigned NOT NULL DEFAULT 0,
        business_post_id bigint(20) unsigned NOT NULL,
        rank_position smallint(5) unsigned NOT NULL,
        editorial_score decimal(8,2) NOT NULL DEFAULT 0.00,
        editorial_note longtext NOT NULL,
        evidence_summary longtext NOT NULL,
        published_at datetime DEFAULT NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY ranking_position (
            ranking_period_id,
            city_term_id,
            category_term_id,
            specialty_term_id,
            rank_position
        ),
        KEY ranking_period_id (ranking_period_id),
        KEY state_term_id (state_term_id),
        KEY city_term_id (city_term_id),
        KEY category_term_id (category_term_id),
        KEY specialty_term_id (specialty_term_id),
        KEY business_post_id (business_post_id),
        KEY published_at (published_at)
    ) {$charset_collate};";

    $queries[] = "CREATE TABLE {$requests_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        request_type varchar(40) NOT NULL,
        business_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
        requester_name varchar(255) NOT NULL DEFAULT '',
        requester_email varchar(320) NOT NULL DEFAULT '',
        requester_phone varchar(100) NOT NULL DEFAULT '',
        business_name varchar(255) NOT NULL DEFAULT '',
        submitted_data longtext NOT NULL,
        verification_token_hash char(64) NOT NULL DEFAULT '',
        email_verified_at datetime DEFAULT NULL,
        status varchar(40) NOT NULL DEFAULT 'pending_email',
        admin_notes longtext NOT NULL,
        reviewed_by bigint(20) unsigned NOT NULL DEFAULT 0,
        reviewed_at datetime DEFAULT NULL,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY request_type (request_type),
        KEY business_post_id (business_post_id),
        KEY requester_email (requester_email),
        KEY status (status),
        KEY created_at (created_at)
    ) {$charset_collate};";

    $queries[] = "CREATE TABLE {$ads_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        advertiser_name varchar(255) NOT NULL DEFAULT '',
        campaign_name varchar(255) NOT NULL DEFAULT '',
        placement varchar(80) NOT NULL DEFAULT '',
        image_attachment_id bigint(20) unsigned NOT NULL DEFAULT 0,
        destination_url varchar(2048) NOT NULL DEFAULT '',
        state_term_id bigint(20) unsigned NOT NULL DEFAULT 0,
        city_term_id bigint(20) unsigned NOT NULL DEFAULT 0,
        category_term_id bigint(20) unsigned NOT NULL DEFAULT 0,
        specialty_term_id bigint(20) unsigned NOT NULL DEFAULT 0,
        starts_at datetime DEFAULT NULL,
        ends_at datetime DEFAULT NULL,
        status varchar(40) NOT NULL DEFAULT 'draft',
        impression_count bigint(20) unsigned NOT NULL DEFAULT 0,
        click_count bigint(20) unsigned NOT NULL DEFAULT 0,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY placement (placement),
        KEY status (status),
        KEY starts_at (starts_at),
        KEY ends_at (ends_at),
        KEY state_term_id (state_term_id),
        KEY city_term_id (city_term_id),
        KEY category_term_id (category_term_id),
        KEY specialty_term_id (specialty_term_id)
    ) {$charset_collate};";

    foreach ($queries as $query) {
        dbDelta($query);
    }
}