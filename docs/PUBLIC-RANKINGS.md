# Public Rankings

## Release

Implemented in Local Directory Framework 0.1.15.

## Purpose

Publish reviewed monthly Top 10 ranking snapshots without calculating
rankings on public page requests.

## Public Route

`/top-businesses/`

Visitors choose:

- State
- City
- Category
- Optional specialty

The public query returns no more than 10 entries from the newest published
ranking period.

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
- Public pages read saved ranking positions in ascending order.
- Public pages do not expose evidence summaries or editorial scores.
- Only published Business profiles can appear in public results.
- Archiving a published period preserves its entries and publication date.
- Paid advertising does not alter organic ranking positions.

## Theme Overrides

A theme may override the plugin template by providing:

`public-rankings.php`
