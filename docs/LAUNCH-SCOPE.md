# Version 1 Launch Scope

## Product Goal

Create a focused local directory that helps visitors find reviewed businesses without requiring visitor accounts or a complicated membership platform.

## Public Experience

1. Branded intro screen
2. Main business category
3. State
4. City
5. Category-specific specialty
6. Published Top 10 results
7. Individual business profile

The public rankings page reads only a reviewed monthly snapshot. Publishing a
new period archives the previously published period, and published snapshots
remain read-only.

## Business Management

The public header uses the label:

Manage a Business

Available requests:

- Add a Business
- Claim or Update a Business
- Request a Correction
- Request Removal

All requests require email verification and administrator review.

The request workflow must never change or publish a Business profile
automatically. Approved changes are applied by an administrator.

## Advertising

Version 1 supports:

- One clearly labeled sponsored result placement
- One bottom banner on result pages
- One bottom banner on business profiles
- No popup advertising
- No permanent floating advertisement

Advertising must never silently control editorial rankings.

## Accounts

Version 1 does not include business-owner accounts or a frontend dashboard.

Owner accounts may be added later when request volume or paid subscription features justify the added complexity.

## Data Principles

- WordPress database is the source of truth.
- Public pages never query Google Sheets.
- Every researched field records its source and verification date.
- Third-party directory content is not copied without permission.
- Rankings are reviewed before publication.
- Deleted listings are archived rather than permanently destroyed.
