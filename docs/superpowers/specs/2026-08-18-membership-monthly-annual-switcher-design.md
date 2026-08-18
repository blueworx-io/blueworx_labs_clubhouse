# Membership Tiers — Monthly / Annual Switcher — Design

**Status:** Approved (design phase)
**Date:** 2026-08-18
**Repo:** `blueworx_labs_clubhouse`

## Summary

A visitor switches the tier grid between monthly and annual prices, in place,
without the page reloading. Each tier can carry both cadences — a typed price
for each, and a SureCart price for each to sell. The switcher appears above the
tier grid on both Home and Membership.

## Goals

- One switcher, two cadences, no page reload.
- Each tier sells the right thing in each cadence: the monthly price buys the
  monthly subscription, the annual one buys the annual subscription.
- A tier that exists in only one cadence stays on the page and says so.
- The annual view says what a member saves, worked out rather than typed.
- Nothing an existing club has entered changes meaning or needs migrating.

## Non-Goals

- Any cadence other than monthly and annual. SureCart can express quarterly;
  the card has no words for it and the switcher has no room for it.
- Remembering a visitor's choice between visits.
- Proration, upgrades, or switching an existing member's cadence — that is the
  shop's job, on the shop's screens.

## Key Decisions

| Decision | Choice | Rationale |
|---|---|---|
| Existing fields | `price`, `period` and `price_id` become the monthly ones | No migration, no re-entry: every club's current tiers already are their monthly prices. |
| New fields | `price_annual`, `price_id_annual` | Two more fields on a loop that already has seven. Anything cleverer means rewriting stored content. |
| Placement | Above the grid on Home and Membership | Chosen by Luke. The Home grid keeps sending its CTAs to the Membership page; only the displayed price changes. |
| Missing cadence | The tier stays, showing the price it has, labelled | Cards vanishing and reappearing as someone toggles reads as a broken page. |
| Saving | Calculated, shown only when both prices are unambiguous | A typed badge contradicts the prices beside it eventually. A calculated one cannot. |
| Switching | Client-side, no reload, same pattern as the existing tabs | The site already has one in-page switching treatment (`Sections::tab_group()`); a second style would be a second thing to maintain and to style in three looks. |
| Default | Monthly | It is the smaller number and the current behaviour. |

## How a price is decided

Per tier, per cadence, in order:

1. The SureCart price for that cadence, if the tier names one, the shop knows
   it, and there is a reachable checkout — then the card shows the shop's amount
   and its CTA buys that price. This is exactly the existing all-or-nothing rule
   in `Page_Renderer::membership_tiers()`, applied twice.
2. Otherwise the typed price for that cadence, with the CTA the club set.
3. Otherwise the tier has no price in that cadence — see below.

## A tier with only one cadence

The card stays exactly where it is. On the view it has no price for, it shows
the price it does have, with a quiet note — "Monthly only" or "Annual only" —
and its CTA is the one that cadence sells. Nothing moves, nothing disappears,
and nobody is offered a purchase that does not exist.

## The saving

On the annual view, a tier shows "Save £56 a year" when — and only when — both
amounts are known unambiguously:

- Both come from SureCart, where amounts are real numbers; or
- Both typed prices are a plain currency amount and nothing else: an optional
  symbol, digits, an optional decimal part. `£28` and `28.50` qualify. `£28 per
  adult` and `from £28` do not.

Anything else shows no saving. The badge appears only when twelve monthly
payments genuinely cost more than one annual one, and it never appears on the
monthly view.

## Markup and behaviour

`Sections::tier_grid()` renders both cadences into each card and marks which is
which; the switcher shows one and hides the other. No second grid, so the cards
cannot fall out of step, and the layout does not jump as someone toggles.

The switcher is two buttons in a group, the active one carrying `aria-pressed`,
labelled "Monthly" and "Annual". Hidden prices are hidden by class, not by the
`hidden` attribute, so a stylesheet-less page shows both rather than neither —
the rule the tab treatment already follows. Without JavaScript the page shows
monthly prices, which is what it shows today.

New classes — `ch-cadence`, `ch-cadence__btn`, `ch-tier__save` and the
cadence-scoped price wrappers — need a rule in all three looks; `LookCoverageTest`
enforces that.

## Editing

The Membership tiers loop gains two fields beside the existing ones: **Annual
price** and **Sells (annual)**. The second is a SureCart price select built the
same way as the existing one, which matters in two places that already know
about `price_id`: the AI import parser and its prompt both special-case that
select, and both need to know about its twin or an import will silently clear a
tier's annual connection.

## Testing

**PHP unit tests** cover the saving calculation (both sources, and every shape
that must produce no badge), the one-cadence-only tiers, the per-cadence price
and CTA resolution, and that a tier connected to a real annual price sells that
price rather than the monthly one.

**A Playwright spec** covers the switcher on both pages against the DB-free
preview: monthly by default, annual prices after a click, no navigation, and the
"Monthly only" note where a tier lacks an annual price.

## Open questions

None. The one judgement call — what counts as an unambiguous typed price — is
settled above, deliberately narrowly: a missing badge costs nothing, a wrong one
costs trust.
