# Business Deals

Business Deals are structured records linked to an existing
`nwmd_business` post.

Deals must never be hard-coded into templates.

## Stable identity

Monthly updates identify one Deal using:

- `business_slug`
- `deal_slug`

The Deal slug remains stable when the title, terms, dates, code,
or source URL changes.

## Fields

- Business
- Deal slug
- Title
- Short card text
- Full description and terms
- Promo code
- Official source URL
- Start date
- Expiration date
- Last verified date
- Status
- Featured status

## Visibility rules

A Deal appears publicly only when:

- its status is `active`;
- it is not archived;
- its start date is empty or has arrived;
- its expiration date is empty or has not passed.

Expired Deals remain stored for history but disappear publicly.

## Monthly maintenance

Monthly research should update only the Deal record. It must not
overwrite the connected business record.

The future Deals CSV updater will use `business_slug + deal_slug`
and will support small changes such as:

- revised Deal wording;
- changed promo code;
- changed expiration date;
- changed source URL;
- verification-date updates;
- activation or deactivation.

Official business websites must remain the primary source for
public Deal terms.
