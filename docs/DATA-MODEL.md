# Local Directory Framework Data Model

## Design Goals

- Keep version 1 simple.
- Support high traffic through indexed queries and cacheable pages.
- Keep public business profiles editable through WordPress.
- Preserve research sources, verification history, and monthly rankings.
- Separate editorial rankings from paid advertising.
- Avoid public requests to Google Sheets or third-party directory APIs.

## WordPress Business Profiles

Businesses use a custom post type:

`nwmd_business`

Each business receives a permanent public profile URL.

Example:

`/business/example-company/`

WordPress stores:

- Business name
- Public description
- Logo and gallery
- Publishing status
- Slug
- Revision history
- Author and editorial timestamps

## Directory Taxonomies

### Business Category

Taxonomy:

`nwmd_category`

Examples:

- Contractors
- Realtors
- Restaurants

### Specialty

Taxonomy:

`nwmd_specialty`

Examples:

- Kitchen Remodeling
- Residential Realtor
- Italian Restaurant

### State

Taxonomy:

`nwmd_state`

Initial value:

- Oregon

Future value:

- Washington

### City

Taxonomy:

`nwmd_city`

Initial values:

- Portland
- Beaverton
- Hillsboro
- Lake Oswego
- Gresham

Each city must belong to one state.

## Custom Database Tables

WordPress table prefixes are represented below as `wp_`.

### wp_nwmd_business_index

Purpose:

Fast directory filtering without expensive post-meta queries.

Columns:

- id
- business_post_id
- legal_name
- public_name
- website_url
- public_email
- public_phone
- street_address
- city_term_id
- state_term_id
- postal_code
- latitude
- longitude
- registration_number
- license_number
- license_status
- verification_status
- claimed_status
- ranking_eligible
- last_verified_at
- created_at
- updated_at
- archived_at

Required indexes:

- UNIQUE business_post_id
- city_term_id
- state_term_id
- verification_status
- ranking_eligible
- last_verified_at
- archived_at

### wp_nwmd_business_sources

Purpose:

Record where every researched business record came from.

Columns:

- id
- business_post_id
- source_type
- source_name
- source_url
- source_identifier
- source_notes
- retrieved_at
- verified_at
- verification_result
- created_at
- updated_at

Source types may include:

- official_registration
- professional_license
- public_inspection
- business_website
- business_submission
- editorial_research
- licensed_data_provider

Required indexes:

- business_post_id
- source_type
- source_identifier
- retrieved_at
- verified_at

### wp_nwmd_ranking_periods

Purpose:

Represent one monthly editorial ranking publication.

Columns:

- id
- period_key
- period_label
- status
- published_at
- created_by
- created_at
- updated_at

Example period key:

`2026-07`

Allowed statuses:

- draft
- review
- published
- archived

Required indexes:

- UNIQUE period_key
- status
- published_at

### wp_nwmd_ranking_entries

Purpose:

Store immutable monthly ranking snapshots.

Columns:

- id
- ranking_period_id
- state_term_id
- city_term_id
- category_term_id
- specialty_term_id
- business_post_id
- rank_position
- editorial_score
- editorial_note
- evidence_summary
- published_at
- created_at

Required indexes:

- ranking_period_id
- state_term_id
- city_term_id
- category_term_id
- specialty_term_id
- business_post_id
- rank_position

Required unique key:

- ranking_period_id
- city_term_id
- category_term_id
- specialty_term_id
- rank_position

### wp_nwmd_business_requests

Purpose:

Receive public requests without requiring business-owner accounts.

Request types:

- add
- update (claim or update)
- correction
- removal

Columns:

- id
- request_type
- business_post_id
- requester_name
- requester_email
- requester_phone
- business_name
- submitted_data
- verification_token_hash
- email_verified_at
- status
- admin_notes
- reviewed_by
- reviewed_at
- created_at
- updated_at

Allowed statuses:

- pending_email
- pending_review
- approved
- rejected
- completed
- archived

Required indexes:

- request_type
- business_post_id
- requester_email
- status
- created_at

Request workflow rules:

- Store only a SHA-256 verification-token hash.
- Verification links expire 7 days after `created_at`.
- Successful verification clears the stored token hash.
- Verified requests move from `pending_email` to `pending_review`.
- Public requests never change Business profiles automatically.
- Administrator review records `reviewed_by` and `reviewed_at`.

### wp_nwmd_ads

Purpose:

Manage simple direct advertising without affecting editorial rankings.

Columns:

- id
- advertiser_name
- campaign_name
- placement
- image_attachment_id
- destination_url
- state_term_id
- city_term_id
- category_term_id
- specialty_term_id
- starts_at
- ends_at
- status
- impression_count
- click_count
- created_at
- updated_at

Initial placements:

- results_sponsored
- results_bottom
- business_profile_bottom

Allowed statuses:

- draft
- active
- paused
- expired
- archived

Required indexes:

- placement
- status
- starts_at
- ends_at
- state_term_id
- city_term_id
- category_term_id
- specialty_term_id

## Public Query Strategy

Public result pages must not calculate rankings in real time.

A result page reads the published monthly snapshot:

1. Find the current published ranking period.
2. Match state, city, category, and specialty.
3. Read no more than 10 ranking entries.
4. Load the related business profiles.
5. Cache the complete rendered page.

## Advertising Rules

- Sponsored placements must display `Sponsored` or `Advertisement`.
- Paid placement must not change organic rank positions.
- Paid outbound links use `rel="sponsored"`.
- Version 1 uses aggregate impression and click counters.
- Detailed event-level analytics can be added later.

## Removal Rules

Business records are archived rather than permanently destroyed.

Archiving must preserve:

- Ranking history
- Research sources
- Request history
- Administrative notes
- Previous URLs where needed

## Version 1 Exclusions

Version 1 does not include:

- Business-owner accounts
- User dashboards
- Paid subscriptions
- Real-time third-party review imports
- Radius search
- External search engines
- Google Sheets as a live database
