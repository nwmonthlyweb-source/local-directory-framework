# Public Rankings

## Release

Published ranking snapshots were introduced in 0.1.15.
Guided directory navigation was added in 0.1.16.

## Purpose

Publish reviewed monthly Top 10 ranking snapshots without calculating
rankings on public page requests.

## Public Route

`/top-businesses/`

Visitors move through one guided step at a time:

1. Category
2. State
3. City
4. Specialty or All specialties
5. Top 10 results

Each step displays only choices that exist in the newest published ranking
snapshot. The flow is server-rendered, works without JavaScript, and preserves
selections in the URL.

## Publication Workflow

Businesses → Monthly Rankings

1. Create a draft ranking period.
2. Add ranking entries.
3. Send the period to Review.
4. Confirm that every ranked business is published.
5. Publish the period.
6. The previously published period is archived automatically.
7. Published and archived periods remain read-only.

A period must contain at least one ranking entry before it can enter review or
be published.

## Snapshot Rules

- Only the newest period with `published` status is public.
- Publishing timestamps the period and all entries in that period.
- Guided choices come only from that published snapshot.
- Public pages read saved ranking positions in ascending order.
- Public pages do not expose evidence summaries or editorial scores.
- Only published Business profiles can appear in public results.
- `All specialties` reads the saved ranking group whose specialty ID is zero.
- Archiving a published period preserves its entries and publication date.
- Paid advertising does not alter organic ranking positions.

## Theme Overrides

A theme may override the plugin template by providing:

`public-rankings.php`