# Membership tier consolidation (slice 2c)

**Date:** 2026-07-22
**Status:** Approved

## Problem

Two places render membership tiers, and they diverge:

- **Membership page** (`Page_Renderer::membership()`): owner-editable via
  `citems('membership','tiers', $default)` — 4 tiers (Junior/Adult/Family/
  Social), each CTA → the contact page ("register interest").
- **Home page** (`Page_Renderer::home()`): a **hardcoded** `tier_grid()` of 3
  tiers (no Junior), each CTA → the membership page. Not editable, not sourced
  from the membership tiers.

The Content catalogue tells owners "Tiers are managed in one place — the
Membership page," but Home doesn't read that source, so the claim is false and
an owner's tier edits never reach the Home teaser.

## Goal

Make Home's tier grid mirror the single Membership tiers source, so editing the
Membership tiers updates both pages. Home stays a teaser that funnels visitors
to the Membership page.

## Design

Extract the Membership tiers (default set + field mapping) into one private
helper, used by both call sites:

```php
/** The membership tiers — the single source both Membership and the Home
 *  teaser render. Owner edits live under Content → Membership → Tiers. */
private static function membership_tiers( ?Content_Store $content ): array
```

It returns `tier_grid()`-shaped rows from
`citems('membership','tiers', $default)` (the existing 4-tier default and the
existing `featured`→`recommended` mapping).

- **Membership page** calls `tier_grid( self::membership_tiers( $content ) )`
  unchanged in behaviour — CTAs stay → contact from the source data.
- **Home page** calls the same helper, then overrides each row's CTA to funnel
  to the Membership page:
  `cta_label => 'Join'`, `cta_href => Links::url('membership')`. The hardcoded
  3-tier block is deleted.

### CTA rationale

Home tiers are a preview: their job is to drive to the fuller Membership page
(detail, FAQ, how-to-join), which is where conversion happens (→ contact).
Mirroring content while overriding only the CTA destination preserves today's
Home behaviour and keeps a single source for the tier data itself.

## Testing

Extend `PageRendererContentOverrideTest`:

- Editing `membership`/`tiers` surfaces on Home (single source).
- Home shows the full default set (includes `Junior`, which the old subset
  omitted).
- Home tier CTAs point to the Membership page, not contact.
- Membership tier CTAs still point to contact (unchanged).

## Out of scope

- The Home `membership` band's own heading/lede/CTA (already editable) — untouched.
- No change to the tier data model, the catalogue, or the tier_grid markup.
