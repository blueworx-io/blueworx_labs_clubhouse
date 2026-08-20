# Checkout in Our Own Clothes — Design

**Status:** Approved (design phase)
**Date:** 2026-08-20
**Repo:** `blueworx_labs_clubhouse`
**Design:** Claude Design project `0b906da5-c173-4d93-b806-f559a4baf924`,
screens `Member Checkout.dc.html` and `Member Checkout Mobile.dc.html`
**Vendor source read:** SureCart 4.6.4, downloaded from wordpress.org
**Related spec:** `docs/superpowers/specs/2026-08-19-member-area-as-a-club-page-design.md`

## Summary

Today the checkout is SureCart's page with SureCart's form on it, wrapped in the
member area's bare shell. Nobody has ever configured that form: it is whatever
SureCart's own seeder wrote, and on a site where seeding never ran there is no
form at all.

This gives the checkout our own header, footer and two-column frame, replaces
the seeded form with one we author, and skins SureCart's own fields with the
member area's design tokens. A club owner never opens SureCart to build a
checkout, and a buyer never sees a page that looks like a different product.

Nothing here takes payment, stores a card, or replaces SureCart. Every field is
still a SureCart block, and the money still moves through SureCart and Stripe.

## Goals

- The checkout page and its form build themselves. No club owner opens SureCart
  to make a checkout exist.
- The checkout wears the BlueWorx member-area look — Sora and Inter, the indigo
  brand, the same cards and fields as the dashboard.
- The page has its own header and footer, and a two-column body on a desktop
  that collapses to a single column with a fixed pay bar on a phone.
- An owner who wants to change the form still can. What we seed is a starting
  point, not a lock.
- Nothing a club has already written or configured is overwritten.

## Non-Goals

- **Collecting card details ourselves.** Card entry is Stripe's iframe, placed
  and coloured by us but drawn by them. Decided because the alternative is
  handling raw card data, which changes what the plugin is.
- **Replacing SureCart's checkout API.** We author the form; SureCart runs it.
- **The extras drawn in the mockup** — shirt size, membership start date,
  collect-or-post, Direct Debit, pay on account, express Apple/Google/PayPal.
  Every one is a real SureCart feature, and every one needs the club to
  configure something in SureCart before it does anything. Shipping them by
  default would put the owner back in SureCart, which is the thing this design
  exists to avoid. See "The extras, and why they are not here".
- **Multi-item baskets.** The mockup's basket holds a shirt, a membership and a
  guest fee. Today a Join button sends one price. The summary renders whatever
  is in the basket, so this is a limit of what links in, not of the design.
- Moving the checkout off SureCart's URL. Settled in the member-area spec: every
  SureCart buy-button builds its link from the stored page id.

## Key Decisions

| Decision | Choice | Rationale |
|---|---|---|
| Approach | Author the form, let SureCart seed it | Decided by Luke, from three options. Uses SureCart's own extension points, so it survives their updates. |
| Look | BlueWorx member area, not the club's public look | Decided by Luke. The checkout reads as one product across every club, like the rest of the member area. |
| Frame | Own header and footer, two columns between | Decided by Luke, from the Claude Design screens. |
| Card fields | Stripe's element, skinned | Decided by Luke, having seen that the mockup's own card boxes cannot be built. |
| Scope | Short form; mockup extras deferred | Decided by Luke, on maintenance cost. |
| Existing forms | Never overwritten | A club that has already edited its checkout keeps it. |

## Architecture

Three parts, deliberately separable. Each can be built, tested and reverted
without the other two.

### 1. The theme stylesheet

`assets/bw/surecart.css` — pure CSS, no PHP. It maps the member area's tokens
onto SureCart's:

```css
.bw-admin sc-input, .bw-admin sc-payment { --sc-input-height-medium: var(--bw-control-h); … }
```

SureCart's fields are web components, most of them using shadow DOM, so our
ordinary selectors cannot reach inside them. Custom properties can, and SureCart
exposes a large set — thirty-nine on the text input alone, covering height,
radius, border, focus ring, font, placeholder and disabled colours. That is the
whole styling surface, and it is enough.

This is the cheapest and most durable part. If SureCart renames a token our rule
stops applying and their default shows — ugly, never broken.

Loaded on the checkout page only, beside the existing `bw.css`, by
`Dashboard_Assets`.

### 2. The page frame

`Dashboard_Shell::checkout()` — a third shell beside `page()` and `bare()`.

`bare()` draws a page heading and drops the content in a panel. The approved
design is a different shape: a fixed header carrying the club's crest, name and
the Stripe reassurance; a scrolling form column; a sticky order-summary rail;
and a footer with the club's legal links and registration number. On a phone the
rail becomes a collapsible strip under the header and the pay button becomes a
fixed bar at the bottom.

The rail and the pay bar are positions in the frame, not content. What goes in
them is SureCart's — the summary blocks and the submit block — so the frame does
not need to know what is being sold.

`Commerce_Pages::dress()` calls `checkout()` for the checkout page and keeps
`bare()` for order confirmation.

### 3. The seeded form

`includes/membership/class-checkout-form.php` — the only new class, and the only
one that knows SureCart's block names.

It filters `surecart/create_forms`, which SureCart applies inside its own
`PageSeeder::createCheckoutForm()`. We hand back our block markup and SureCart
writes the `sc_form` post, so the form comes into being through SureCart's own
machinery rather than ours.

The form, in order:

| Region | Blocks |
|---|---|
| Errors | `surecart/checkout-errors` |
| Contact | `surecart/email`, `surecart/name`, `surecart/phone` |
| Address | `surecart/address`, inside `surecart/conditional-form` so it appears only when something ships |
| Payment | `surecart/payment` |
| Submit | `surecart/submit` |
| Summary | `surecart/totals` wrapping line items, coupon, subtotal, tax, trial line and total |

Every one is a real block in SureCart 4.6.4, confirmed against
`packages/blocks/Blocks`. Each carries our `bw-*` classes, so the parts SureCart
renders in light DOM inherit the member area's look directly and only the
shadow-DOM parts rely on the token mapping.

### Where it hooks in

`Shop_Pages` already reports which shop pages are missing and repairs them on an
owner's say-so, by calling SureCart's seeder. That is unchanged, and it is what
makes the form build itself: the seeder runs, our filter answers, and the club
gets a checkout in our clothes without anyone opening SureCart.

## The extras, and why they are not here

The mockup draws more than this form has. Each is possible, and each is a
separate decision:

| Drawn | SureCart's answer | Why deferred |
|---|---|---|
| Apple Pay, Google Pay, PayPal | `surecart/express-payment` | Needs each wallet enabled in the club's Stripe account. |
| Collect or post | `surecart/shipping-choices` | Needs shipping zones and rates configured. |
| Direct Debit | Appears inside `surecart/payment` | Needs Bacs enabled in Stripe. |
| Pay on account | A manual payment method | Needs the club to define one, and a credit policy. |
| Shirt size, membership start | `surecart/input`, `surecart/radio-group` | Product-specific, so it belongs to the product rather than the form. |
| Quantity steppers | `surecart/line-items` with `editable` | Only meaningful once a basket can hold more than one line. |

Adding any of them later is a change to one template string.

## Data Flow

Nothing new flows. A Join button carries a price id into SureCart's checkout URL
exactly as it does today; SureCart puts it in the basket; our form renders it;
SureCart and Stripe take the money; SureCart redirects to its order confirmation
page, which the existing `bare()` shell already dresses.

## Error Handling

- **No SureCart.** `SureCart_Products::is_active()` is false, no filter fires,
  no checkout page exists, and Join buttons already fall back to the contact
  page. Unchanged.
- **A form already exists.** SureCart's `createPosts()` finds it and does not
  write a second one, so a club that has edited its checkout keeps it. Our
  filter is only consulted when a form is being created.
- **A block SureCart no longer has.** An unknown block renders as nothing rather
  than an error, so the worst case is a missing field on a form the owner can
  still edit. Playwright covers the fields being present, so this shows up as a
  failing test rather than a silent gap.
- **A token SureCart renames.** The rule stops applying and their default shows.

## Testing

- **PHP.** The form template contains each expected block; the filter returns
  our content and leaves SureCart's other keys untouched; `Commerce_Pages`
  picks `checkout()` for checkout and `bare()` for confirmation;
  `Dashboard_Assets` queues the SureCart stylesheet on the checkout page and
  nowhere else. `Dashboard_Shell::checkout()` is pure and tested directly.
- **Playwright.** Against the DB-free preview: the checkout frame renders with
  its header, rail and footer; the rail collapses and the pay bar appears at
  phone width; the page has one `h1` and every field has a real label.
- **Not tested.** A live purchase. Settled in [#209](../../../../issues/209) —
  the link format is confirmed against SureCart's source and the live checkout
  is untested by choice.

## Accessibility

Every field is a SureCart block that renders its own label, so labelling is
theirs and already correct. Ours to get right: one `h1` in the header, the
summary rail as an `aside` with an accessible name, the collapsible summary as a
real button with `aria-expanded`, the pay bar reachable in tab order before the
footer, and the fixed bar not covering the last field on a phone.

## Open Questions

None. The card-field limit and the deferred extras are decided, not open.
