# Business CSV Import

## Release

- CSV template and complete-file validation: 0.1.12
- Draft creation, index synchronization, source creation, and rollback: 0.1.13

## Purpose

Provide a secure WordPress administrator tool for importing researched
business records from CSV without overwriting existing directory data.

## Admin Location

Businesses → CSV Import

Required capability:

- manage_options

## Admin Workflow

1. Download and complete the official CSV template.
2. Select the CSV in Businesses → CSV Import.
3. Use Validate CSV to check the file without changing records.
4. Use Import CSV to validate again and create the complete batch.
5. Review the new Business drafts before publishing.

## Import Safety

The importer must:

- Create new Business posts as drafts.
- Never publish imported businesses automatically.
- Never overwrite an existing Business.
- Validate the complete CSV before creating any records.
- Reject unknown CSV columns.
- Reject duplicate header names.
- Reject rows with the wrong number of columns.
- Reject duplicate business slugs within the CSV.
- Reject business slugs that already exist in WordPress.
- Reject duplicate non-empty registration numbers.
- Reject duplicate non-empty license numbers.
- Use only existing taxonomy terms.
- Never create taxonomy terms during an import.
- Verify that each city belongs to the selected state.
- Sanitize every imported value.
- Synchronize the business index after metadata and terms are saved.
- Attempt to roll back all records created by a failed import.
- Limit uploads to 2 MB.
- Limit imports to 500 data rows.

## Required Columns

- business_name
- business_slug
- state_slug
- city_slug
- category_slug

## Optional Business Columns

- description
- excerpt
- public_name
- legal_name
- website_url
- public_email
- public_phone
- street_address
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
- specialty_slug

## Optional Research Source Columns

A row may include one initial research source:

- source_type
- source_name
- source_url
- source_identifier
- source_notes
- source_retrieved_at
- source_verified_at
- source_verification_result

When any source column contains data:

- source_type is required.
- source_name is required.
- source_retrieved_at is required.
- source_url or source_identifier is required.
- source_verified_at is required when the result is not pending.

## Allowed License Status Values

- unknown
- active
- inactive
- expired
- suspended
- revoked
- not-required

Default:

- unknown

## Allowed Verification Status Values

- unverified
- pending
- verified
- needs-review

Default:

- unverified

## Allowed Claimed Status Values

- unclaimed
- claimed
- disputed

Default:

- unclaimed

## Allowed Ranking Eligibility Values

- 1
- 0
- yes
- no
- true
- false

Default:

- 0

## Allowed Research Source Types

- official_registration
- professional_license
- public_inspection
- business_website
- business_submission
- editorial_research
- licensed_data_provider

## Allowed Research Verification Results

- pending
- verified
- partial
- mismatch
- unavailable

Default:

- pending

## Formatting Rules

- Slugs must be lowercase WordPress slugs.
- Dates must use YYYY-MM-DD.
- Latitude must be between -90 and 90.
- Longitude must be between -180 and 180.
- URLs must be valid public HTTP or HTTPS URLs.
- Taxonomy values must use existing term slugs.
- Blank optional values are allowed.
- A UTF-8 byte-order mark in the first header is allowed.

## Import Sequence

For every validated row:

1. Create an nwmd_business draft.
2. Assign state, city, category, and optional specialty terms.
3. Save sanitized nwmd_ Business metadata.
4. Save ranking eligibility.
5. Synchronize wp_nwmd_business_index.
6. Save the optional wp_nwmd_business_sources record.

## Failure Behavior

Validation errors:

- Create no records.
- Show line-specific errors in WordPress admin.

Database or WordPress errors during creation:

- Stop immediately.
- Delete source and index rows created by the import.
- Permanently delete Business drafts created by the import.
- Show a safe administrator error message.

## Template

The admin page must provide a downloadable, header-only UTF-8 CSV
template containing all supported columns in the documented order.
