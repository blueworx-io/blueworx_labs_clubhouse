# Member Dashboard — Design

**Status:** Approved (design phase)
**Date:** 2026-08-18
**Issue:** [#231](../../../../issues/231)
**Repo:** `blueworx_labs_clubhouse`
**Source design:** Claude Design — *BlueWorx | Project | ClubHouse Dashboard*, `Member Dashboard.dc.html`
**Design system:** *BlueWorx Admin Design System* (`_ds/labs-wordpress-backend-components-…`)

## Summary

The plugin takes over the member's account page. We own the frame — the page
header, the left nav, the cards — and each panel holds the output of whichever
plugin owns that data: SureCart's customer blocks for orders, invoices and
plans; LatePoint's shortcode for bookings. The club's welcome pack sits at the
top of the overview.

Checkout and order confirmation are taken over in the same pass, in the same
shell without the member nav.

## Goals

- One member area that looks designed, not like three plugins in a stack.
- The plugin owns the page; the plugins own their data, and keep owning it.
- A club without LatePoint, or without SureCart, sees no dead nav items.
- The pages keep their existing URLs, so links and bookmarks still work.
- Nothing a club has entered is lost.

## Non-Goals

- Rendering bookings, orders, invoices or plans ourselves. Decided against: the
  design draws them natively, but that means reading two plugins' data and
  re-rendering it, and re-doing it whenever either changes. The frame is ours;
  the records stay theirs.
- The shop, collection and product pages — [#232](../../../../issues/232).
- Anything on the club's public site changing.

## Key Decisions

| Decision | Choice | Rationale |
|---|---|---|
| Composition | Our frame, plugin output inside each panel | Decided by Luke. A fraction of the work of native panels, and a SureCart update fixes itself rather than breaking us. |
| Look | The BlueWorx admin design system, as drawn | Decided by Luke. The member area reads as one BlueWorx product across every club, rather than as the club's site. |
| Pages | Adopt SureCart's existing pages | Same URLs, so a bookmarked dashboard or a checkout link in an email still works. |
| Tabs | Separate addresses (`?view=orders`), server-rendered | Each view renders only the plugin output it needs — no page loading four plugins' blocks to show one. Works without JavaScript, and every view is linkable. |
| Missing plugin | The nav item is not offered at all | The rule `Integrations::section_available()` already applies to sections. A tab that cannot render is worse than an absent one. |
| SureCart | Its blocks, rendered server-side | SureCart's dashboard is composed of separate blocks (`customer-orders`, `customer-invoices`, `customer-subscriptions`, `customer-billing-details`, `customer-payment-methods`), not one monolith — verified in its own source. That is what makes one block per panel possible. |
| LatePoint | `[latepoint_customer_dashboard]` | The one entry point it offers. It brings its own tabs; it gets the whole Bookings view to itself rather than being boxed inside a card. |

## The views

Left nav, in this order. Each is an address; each renders one thing.

| View | Holds | Present when |
|---|---|---|
| Dashboard | Welcome pack banner, then a short overview: the club's own quick links | Always |
| Bookings | `[latepoint_customer_dashboard]` | LatePoint installed |
| Orders | `surecart/customer-orders` | SureCart installed |
| Invoices | `surecart/customer-invoices` | SureCart installed |
| Plans | `surecart/customer-subscriptions` | SureCart installed |
| Account | `surecart/customer-billing-details` + `surecart/customer-payment-methods` | SureCart installed |

A member with neither plugin still gets the Dashboard view and the welcome
pack — which is exactly what a club that has not set up a shop should see.

`surecart/customer-downloads` and `customer-licenses` are deliberately left out:
no club sells downloads or licences today, and an empty panel on every account
page is a cost with no reader.

## Architecture

**`includes/dashboard/class-member-dashboard.php`** — the page. Decides the
current view from the address, renders the shell and asks one panel source for
the body. Takes over `the_content` on the adopted page, exactly as the welcome
pack does today.

**`includes/dashboard/class-dashboard-views.php`** — the declarative list above:
key, label, Dashicon, what renders it, and what it needs installed. Pure, and
the single source the nav and the router both read, so they cannot disagree.

**`includes/dashboard/class-plugin-slot.php`** — renders another plugin's
output, given a block name or a shortcode. One place that knows `do_blocks()`
and `do_shortcode()` exist, one place that returns '' when the plugin is absent,
so no view has to think about it.

**`includes/render/class-dashboard-shell.php`** — the markup: page header, left
nav, cards. Pure, escaped, and skin-agnostic in the same way `Sections` is.

**`assets/bw/`** — the design system's CSS and fonts, vendored: the tokens, the
component CSS actually used (core, layout, navigation, data, feedback, forms),
Sora, and Dashicons. Loaded on these pages only.

## Checkout and order confirmation

The same shell, minus the member nav: page header, one card, the plugin's own
block inside (`surecart/checkout-form` and SureCart's order confirmation
block). A member on the checkout page is mid-purchase and should not be offered
six places to wander off to.

## What a club sees when a plugin is missing

Never a blank frame. The nav item is absent, and if a member reaches the
address anyway, the view says plainly that the club has not set that part up
yet, and offers the way back to the club site. The plugin detection is the one
already in `Integrations`.

## Styling notes

The design system is for wp-admin, and this is a front-end page, so two things
have to be deliberate: the CSS is scoped under one root class so it cannot leak
into the club's own look, and it does not assume WordPress admin chrome around
it. Nothing from `assets/looks/` loads here — the two systems never meet.

## Testing

**PHP unit tests** cover the view list (labels, order, what each needs), the
router (a bad or absent `view` falls back to Dashboard; a view whose plugin is
missing is not offered), the shell's markup and escaping, and the plugin slot
returning '' rather than a broken panel when a plugin is absent.

**A Playwright spec** covers the page against the real WordPress harness, which
has neither SureCart nor LatePoint installed — so it proves the honest empty
path end to end: the frame renders, the welcome pack shows, no dead nav items,
and no fatal.

The panels' own contents are SureCart's and LatePoint's to test.

## Open questions

- The design's Dashboard view shows next sessions, recent orders and an
  outstanding-invoice notice — all native panels. With plugin output in the
  panels we cannot compose those cheaply, so the overview ships as the welcome
  pack plus quick links into the other views. Whether that is enough, or the
  overview should just redirect to Bookings, is worth a look once it is real.
