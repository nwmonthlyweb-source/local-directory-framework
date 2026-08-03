# Business Deals CSV Import

The Deals CSV importer creates and updates structured Business Deal
records without changing the connected Business post.

## Stable update identity

Every Deal is identified by:

- `business_slug`
- `deal_slug`

Keep both values stable during monthly updates.

## Exact columns

business_slug,deal_slug,title,card_text,description,promo_code,source_url,starts_at,expires_at,verified_at,status,is_featured

## Date format

Use `YYYY-MM-DD`.

Leave a date blank when the official source does not publish it.

## Active Deal requirements

An active Deal requires:

- a valid official `source_url`;
- a valid `verified_at` date;
- an expiration date that has not passed, when one is supplied.

## Featured Deals

Use `1` for the Deal displayed on the Business card.

Use `0` for other Deals.

Only one Deal per Business may be featured in one CSV.

## Monthly workflow

1. Research the official Business website.
2. Update only the matching Deal row.
3. Keep `business_slug` and `deal_slug` unchanged.
4. Validate the CSV.
5. Import and update the Deals.
6. Review the public Business card and profile.

The importer validates every row before making changes and attempts
to restore the prior Deal records if an import fails.
