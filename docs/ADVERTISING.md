# Public Advertising

## Release

Direct public advertising was introduced in 0.1.17.

## Purpose

Support simple direct sponsorships without changing editorial ranking positions.

## Admin Workflow

Businesses -> Advertising

Administrators can create and update advertisements with:

- Advertiser name
- Campaign and public headline
- Placement
- Optional Media Library image
- Destination URL
- Optional state, city, category, and specialty targets
- Optional start and end date
- Status

Available placements:

- Sponsored result
- Results bottom banner
- Business profile bottom banner

Available statuses:

- Draft
- Active
- Paused
- Expired
- Archived

## Public Selection Rules

A targeting value of zero means that the campaign applies to every value for
that taxonomy.

For each placement, the public page:

1. Reads active campaigns whose schedule includes the current WordPress time.
2. Removes campaigns that do not match the current public context.
3. Chooses the campaign with the most nonzero targeting fields.
4. Uses the most recently updated campaign when specificity is tied.

Activating a campaign pauses another active campaign with the exact same
placement and exact same targeting values.

## Ranking Results

The final guided ranking results page may show:

- One clearly labeled Sponsored result before the organic Top 10 list
- One clearly labeled Advertisement banner after the organic list

Sponsored content is outside the ordered ranking list. It never receives a
rank number and never changes saved organic rank positions.

## Business Profiles

A published Business profile may show one Advertisement banner after the
profile content.

The campaign must match the Business profile terms unless the campaign uses
zero-value fallback targeting.

## Links and Counters

Paid links use:

`rel="sponsored noopener noreferrer"`

Clicks pass through:

`/sponsored-click/{id}/`

The redirect validates that the campaign is still active and within schedule,
increments the aggregate click counter, and then sends the visitor to the
validated HTTP or HTTPS destination.

Impressions increment when the plugin renders a placement. With a full-page
cache, impression totals may represent origin renders rather than every cached
page view. Detailed event-level analytics are outside version 1 scope.

## Safety Rules

- Administrators need `manage_options`.
- Save and archive actions require WordPress nonces.
- Destination URLs must use HTTP or HTTPS.
- Image IDs must reference Media Library images.
- City and state targets must match.
- End dates must be after start dates.
- Ended active campaigns are marked Expired.
- Archived rows are preserved rather than deleted.
- Advertising never changes editorial ranking records.
