# SureCart — what it actually does

Observed 13 August 2026 against `demo.305media.co.uk`, a live WordPress site with
SureCart active and 13 prices across 10 products. Read-only REST access, so
everything below is either observed from real responses or explicitly flagged as
not verifiable from here.

**Updated 14 August 2026.** Several things below were recorded as unverifiable
because the support window was REST-only. They are now read directly from
SureCart's own source — the plugin is a free download from wordpress.org, which
is a faster and more certain answer than any amount of probing a live site.
Anything sourced that way names the file it came from. The lesson is worth
keeping: **read the vendor's source before designing around a guess.**

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

The adapter does not use SureCart's PHP models at all — it dispatches the same
verified REST route internally through WordPress's own REST server. That still
looks like the right call: the route and its field shapes are observed from a
real shop, while the model classes would be one more vendor symbol to guard.

## Cache invalidation

The adapter hooks three actions so a price change shows without waiting out the
five-minute cache. Two of the three were guesses and **both were wrong**:
`surecart/price/saved` and `surecart/product/saved` appear nowhere in SureCart.
They were replaced with the real ones, `surecart/product/sync/created` and
`surecart/product/sync/updated` (`Sync\PostSyncService`), alongside
`save_post_sc_product`, whose post type is confirmed.

Nothing broke while the guesses were in place — a hook that never fires is
harmless, and the cache expiry was covering for them — but prices were up to
five minutes stale after an edit rather than immediate.

## The checkout page

**SureCart has its own seeder for this page.**
`Activation\ActivationService::activate()` is wired to
`register_activation_hook` and calls `PageSeeder::seed()`, which seeds a
checkout form, a checkout page, a cart post and a shop page. The checkout page:

| Thing | Value |
| --- | --- |
| Slug / title | `checkout` / "Checkout" |
| Content | `<!-- wp:surecart/checkout-form {"id":FORM_ID} -->` |
| Page id stored in | `surecart_checkout_page_id` |
| Form id stored in | `surecart_checkout_sc_form_id` |
| Form post type | `sc_form` |

The option name is built at runtime as `'surecart_' . $option . '_' . $post_type
. '_id'` (`PageService::getOptionName`), so `checkout` + `page` gives the name
above; it also appears literally in the uninstall routine. The plugin's earlier
guess at this name was right.

`PageService::find()` treats `pending`, `trash`, `future` and `auto-draft` as
"no page". Clubhouse is stricter still and accepts only `publish`, because a
draft or private page is a 404 to the logged-out visitor doing the buying.

**But seeding does not always happen.** SureCart 4.6.3 was installed into the
local test harness and activated through wp-admin, and no checkout page, form or
option appeared — the seeder exists and is wired to the activation hook, but on
a fresh install with no store connected it produced nothing. The onboarding
path (`Install\InstallService::createPages`) creates the same pages again, which
suggests seeding really lands when a store is connected rather than at
activation.

So a site can be missing a checkout page for two different reasons — never
seeded, or seeded and later deleted — and the fix is the same either way. That
is what `Blueworx_Clubhouse_Checkout_Page` does: report the state and, on an
owner's say-so, republish or recreate the page using SureCart's own slug, block
and option, so SureCart cannot tell the difference. Verified end to end against
a real SureCart install on 14 August 2026: notice shown, button pressed, page
created, and SureCart's own slide-out cart picked up the new page as its
Checkout destination.

## Detecting that SureCart is here at all

`SURECART_PLUGIN_FILE` (a constant defined at the top of `surecart.php`) and the
**global** `SureCart` class — no namespace. Confirmed by loading the real plugin.

This was previously a `surecart()` function and a `\SureCart\SureCart` class,
neither of which exists anywhere in the plugin, so Clubhouse never detected a
shop on any real site. Everything downstream — tier prices, checkout links, the
missing-page notice — was unreachable in production while every test passed,
because the tests set an override rather than exercising the detection. There is
now an out-of-process test that does exercise it
(`tests/php/fixtures/surecart-detection-check.php`).

## The checkout URL

**Confirmed from source.** `Routing\AdminURLService::checkout()` builds it as
`add_query_arg( [ 'line_items' => [ [ 'price_id' => …, 'quantity' => … ] ] ],
$checkout_page_url )`, which serialises to exactly
`line_items[0][price_id]=…&line_items[0][quantity]=1`. That is what
`Blueworx_Clubhouse_Checkout::PRICE_PARAM` and `QUANTITY_PARAM` already hold, so
those constants are correct as written.

No PHP in SureCart parses `line_items` off the query string, so the prefill is
read client-side by its checkout bundle. That half is still worth a real
end-to-end purchase to confirm — the URL shape is proven, the shop's behaviour on
arrival is not.

`checkout_url()` returning `''` on a site with no reachable checkout page makes
every tier fall back to its typed price and the contact link. Correct behaviour,
not a failure.

## Also worth knowing

- The demo site's products are SureCart's own sample catalogue — "Simple
  Physical Product", "Subscribe & Save Product" — not club memberships. Nothing
  there is connectable to a real tier yet.
- `/wp/v2/users`, `/wp/v2/comments` and the order and customer routes are
  withheld by the support-access plugin. No customer data was read.
