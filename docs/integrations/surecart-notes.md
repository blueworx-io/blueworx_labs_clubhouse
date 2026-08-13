# SureCart — what it actually does

Observed 13 August 2026 against `demo.305media.co.uk`, a live WordPress site with
SureCart active and 13 prices across 10 products. Read-only REST access, so
everything below is either observed from real responses or explicitly flagged as
not verifiable from here.

This is a record of what was seen, not a design.

## What a price looks like

`GET /wp-json/surecart/v1/prices?per_page=50&expand[]=product` — 200, 13 prices.
A real record, trimmed to the fields that matter:

```json
{
  "id": "d31a3fac-1b95-4c45-965b-f55fecc34a58",
  "object": "price",
  "name": "Subscribe Monthly & Save",
  "amount": 2900,
  "currency": "gbp",
  "archived": false,
  "current_version": true,
  "recurring_interval": "month",
  "recurring_interval_count": 1,
  "product": { "name": "Subscribe & Save Product", "…": "…" }
}
```

Confirmed across all 13 prices on that site:

| Field | What it holds |
| --- | --- |
| `amount` | Integer, **minor units**. 2900 is £29.00. |
| `currency` | **Lowercase** ISO code — `"gbp"` on every price there. Uppercase before matching a symbol. |
| `recurring_interval` | `"month"` (5 prices), `"year"` (1), or **`null`** for a one-off (7). |
| `recurring_interval_count` | `1` on every recurring price there, `null` on every one-off. Both must be checked — count alone does not say whether it recurs. |
| `archived` | Boolean. All false on that site, so the archived case was **not** observed live. |
| `current_version` | Boolean. All true there. A price superseded by a new version is presumably false; **not observed**. |
| `name` | The price's own name, and it is frequently **`null`** — 3 of the first 6 had none. A label built from the price name alone would read as blank. Use the product's name, with the price name only as extra. |
| `product` | An id string by default; a full object when `expand[]=product` is passed. Its `name` is the product's name. |

So a picker label should come from `product.name`, and the amount and interval
from `amount`/`currency`/`recurring_interval`/`recurring_interval_count`.

## Reading prices from PHP

**Not verified.** The support window is REST-only, so SureCart's PHP classes
could not be exercised, and its plugin source is not readable over HTTP.

What this means for the adapter: treat the PHP entry point as unproven, guard it
with `class_exists()`, and fall back to "no products" rather than fatalling. The
**field names above are the verified part** — SureCart's models expose the same
names as its API, so the mapping is the safe half and the entry point is the
half that needs the manual smoke test.

## The checkout URL

**Not verifiable on this site, for a real reason: it has no checkout page.**

`GET /wp-json/wp/v2/pages` returns exactly one published page — "Shop"
(`/shop/`). No checkout page, no customer dashboard. That is issue #150, and it
is why every buy path on that site currently goes through SureCart's own
JavaScript slide-out cart rather than a URL.

`GET /wp-json/surecart/v1/settings` carries API and display settings only — no
page ids. The front-end bundles
(`packages/blocks-next/build/scripts/checkout/index.js` and friends) contain no
query-string parsing, so the prefill is handled server-side on the checkout page
and cannot be observed without one.

Consequences, all of them already how the plugin behaves:

- `Blueworx_Clubhouse_Checkout` keeps the pre-fill parameters in two constants
  (`PRICE_PARAM`, `QUANTITY_PARAM`) so correcting them is a one-line change.
- `checkout_url()` returning `''` — which is what a site with no checkout page
  must return — makes every tier fall back to its typed price and the contact
  link. Correct behaviour, not a failure.
- The manual smoke test in the plan is what proves the URL, once a checkout page
  exists. It cannot be closed before then.

## Also worth knowing

- The demo site's products are SureCart's own sample catalogue — "Simple
  Physical Product", "Subscribe & Save Product" — not club memberships. Nothing
  there is connectable to a real tier yet.
- `/wp/v2/users`, `/wp/v2/comments` and the order and customer routes are
  withheld by the support-access plugin. No customer data was read.
