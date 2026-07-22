# About & Membership structure (slice 2e)

**Date:** 2026-07-22
**Status:** Approved
**Branch:** stacked on `tier-consolidation` (2c), targets `main`.

## Goal

Restructure the About and Membership pages for a clearer flow, and make the
About timeline editable — reusing existing components (no new CSS).

## Membership

Move **tiers above the fold**: reorder sections from
`hero → why → tiers → detail → steps → faq → cta` to
`hero → tiers → why → detail → steps → faq → cta`. Pure render-order change in
`Page_Renderer::membership()`; visibility keys unchanged. "What's included" is
the existing `detail` split — it stays, now supporting the tiers below them.

## About

New section order:
`hero → history → values → facilities → committee → get_involved → cta`.

1. **Editable timeline** — the 6 history milestones are hardcoded in the
   renderer. Make them an editable loop:
   - Catalogue `about`/`history`: keep `fields => [heading]`, add
     `loop => { name: Milestone, fields: [year, title, desc] }`.
   - Renderer reads `citems('about','history', $default_milestones)`; the
     hardcoded set becomes the default.
   - The `timeline()` component already accepts `milestones` — no markup change.

2. **"Get involved" section** — today it's only the closing CTA's eyebrow. Add a
   real, editable section rendered with `benefit_grid` (heading + eyebrow +
   cards), distinct from the Join CTA:
   - Setup-sections `about` map: add `get_involved => 'Get involved'`.
   - Catalogue `about`: add `get_involved` loop section (cards: title,
     description). Default cards: Volunteer, Coach & officiate, Sponsor & partner.
   - Renderer: `benefit_grid` after committee, before the CTA.
   - **Deviation from the approved sketch:** cards do not carry per-card contact
     links — no existing headed card component supports them. Differentiation is
     by a headed section + placement, with the closing CTA re-scoped to
     membership. Per-card links remain a possible follow-up.

3. **Reposition facilities** — move the facilities image band up from
   second-to-last to directly after values (before committee).

4. **Closing CTA** — change its eyebrow from "Get involved" to "Membership" so it
   no longer collides with the new section; it stays a Join → membership band.

## Lockstep

Catalogue section keys must equal the visibility inventory per page
(`ContentCatalogueTest::test_section_keys_match_visibility_inventory_exactly`).
Adding `get_involved` to both keeps them in step. `about`/`history` gains a loop
but keeps its `heading` field, so the narrowed-sections test's `['heading']`
assertion still holds (its comment is updated).

## Testing

Extend `PageRendererContentOverrideTest`:

- Membership: tiers render before the "Why join" benefits (order assertion).
- About: editing a `history` milestone surfaces on the page (editable timeline).
- About: `get_involved` section renders its default cards, and a `citems`
  override reaches output.
- About: the new section order (facilities before committee; get_involved
  before cta).

## Out of scope

- No new CSS or Sections components.
- Membership `detail`/`steps`/`faq` content unchanged.
