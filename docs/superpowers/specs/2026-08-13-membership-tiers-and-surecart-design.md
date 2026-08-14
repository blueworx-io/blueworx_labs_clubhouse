# Membership tiers connected to SureCart

Design, 2026-08-13. Baseline: v0.63.1.

## The change

Today a membership tier is four pieces of text an owner types — name, price,
period, features — and its Join button points at the contact page. SureCart may
be installed with real products, but nothing joins the two, so a club cannot
take a membership payment. That is issue #90: the page promises "join in five
minutes" and delivers a contact form.

After this change an owner connects each tier to a SureCart price. The card then
shows what that price actually charges, and Join goes straight to checkout with
the tier already in the basket.

## Decisions taken

| Question | Decision |
| --- | --- |
| What does SureCart own? | The price and the billing interval. The club still writes the tier's name, pitch and features — SureCart has nowhere good to keep marketing copy. |
| What does a tier connect to? | One specific price ("Adult Membership — £28/month"), not a product. A club wanting monthly and yearly makes two tiers. |
| What does Join do? | Goes to the SureCart checkout page with that price pre-filled. |
| What if the product is unusable? | The card falls back to the typed price and a contact link — exactly today's behaviour. Nothing hides, nothing 404s. |
| How is the price read? | Live from SureCart through an adapter, cached, cache cleared when a product is saved. Not copied onto the tier, which would go stale. |
| When is it built? | Now, on the existing Membership tiers editor. The block-library rebuild moves those same fields into a block later and carries the connection with them. |

## Model

### The tier gains one field

A tier stores a `price_id` and nothing else about SureCart. No amount, no
interval, no product name — copying any of those is what makes a page say £28
after the club has moved to £30.

### The products adapter

A seam, for the same reason `Collections`, `Links` and `Integrations` are: the
renderer, the admin screen, the tests and the DB-free preview must all work
without SureCart present.

```
interface Blueworx_Clubhouse_Products
    prices(): array<int, array{id, label, amount, period, product}>   // for the picker
    price( string $id ): ?array{id, label, amount, period, product}   // for rendering
```

`amount` is a formatted display string ("£28") and `period` the interval in the
form the card already uses ("/mo"), so the renderer's existing tier shape does
not change. Two implementations:

- `Blueworx_Clubhouse_SureCart_Products` — the only class that knows SureCart
  exists. Reads prices and their products, formats the amount from SureCart's
  minor units and currency, and caches.
- `Blueworx_Clubhouse_Fake_Products` — fixed data for tests and the preview.

`price()` returns null for an unknown id, a deleted product, or when SureCart is
absent. Null is the fallback signal; there is no separate "is SureCart here"
branch in the renderer.

### Caching

Prices are cached in a transient — a membership page must not query SureCart per
card. The cache is cleared on SureCart's product and price save hooks, so an
owner changing a price sees it immediately rather than waiting out a timer. The
cache key carries the plugin version, following `Theme_Cache`.

## Rendering

`Page_Renderer::membership_tiers()` gains the adapter as a parameter. Per tier:

- **`price_id` set and `price()` returns a price** — `price` and `period` come
  from SureCart, `cta_href` is the checkout URL for that price, `cta_label` is
  the club's own label.
- **Otherwise** — the typed `price` and `period`, `cta_href` to the contact page.
  Identical to today's output, which is what keeps existing club sites unchanged.

The card's markup does not change, so all three Base Looks carry over untouched.

Home's tier row renders from the same tier data, so connecting a tier once wires
it on both pages.

### The checkout URL

Built from the SureCart checkout page plus the chosen price, pre-filled. **The
exact query format SureCart accepts must be verified against a real install
before it is built** — it is not to be written from memory of their API. The
plan's first task is that verification, and its result is recorded there.

Two dependencies, both already tracked:

- **The checkout page must exist** — issue #150. Without it there is nowhere to
  send a member, so a club gets the fallback until that lands. The URL builder
  therefore treats a missing checkout page the same as a missing price: fall
  back, do not emit a dead link.
- **A member needs somewhere to manage the membership afterwards** — the
  SureCart dashboard, also #150. Out of scope here.

## Admin

The Membership tiers editor gains one field per tier: the product it sells. A
select listing every SureCart price as "Adult Membership — £28/month", with "Not
connected" first and selected by default.

Three states the field must handle honestly:

- **SureCart absent** — the field explains that instead of showing an empty
  dropdown.
- **Stored id no longer resolves** — the field shows that value as "no longer
  available", still selected, and says what visitors are seeing meanwhile and
  that saving will clear it. What must not happen is the field quietly reading
  "Not connected", which would tell an owner their tier was never wired up when
  in fact its product has gone.
- **No prices yet** — SureCart is installed but has no products; say so, and
  where to make one.

The field is sanitised as an opaque id and validated against `prices()` on save.
Persisted through `Content_Store` with the rest of the tier's fields, so it
travels into the block model with them.

## Testing

- Pure unit tests against `Fake_Products`: a connected tier renders SureCart's
  price and a checkout link; an unconnected tier renders the typed price and the
  contact link; a tier whose price has been deleted falls back; the amount and
  period formats match what the card expects; the checkout URL is well-formed
  and escaped.
- The SureCart-facing adapter is tested with the WordPress-stub pattern the
  collections and admin code already use, covering the minor-units and currency
  formatting and the cache invalidation hooks.
- Admin tests for the three field states above.
- Playwright over the DB-free preview, which uses the fake — the membership page
  renders with connected and unconnected tiers side by side.
- **Manual smoke on a real install with SureCart**, which no test double can
  replace: connect a tier, see the price on the page, click Join, arrive at
  checkout with the right item in the basket, change the price in SureCart and
  see the page follow.

## Risks

- **SureCart's API is the unknown.** Both the price read and the checkout URL
  depend on it, and it is not installed in this repo's test environment. The
  adapter confines that risk to one file; the plan front-loads verifying it.
- **A price change is a price change.** Once tiers are live, editing a price in
  SureCart changes what the club's website advertises, immediately. That is the
  intent, and it is worth saying to owners in the editor.
- **The fallback can mask a broken setup** — a club could believe checkout works
  while every visitor gets the contact form. The editor's warnings are what stop
  that being silent.

## Out of scope

Member accounts and membership status, gating content behind a tier, upgrades
or proration between tiers, a monthly/yearly toggle on one card, and creating
the checkout and dashboard pages themselves (issue #150).
