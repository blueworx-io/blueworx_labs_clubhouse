# Hero unification — targeted reconciliation (slice 3)

**Date:** 2026-07-22
**Status:** Approved (targeted scope)
**Branch:** off `main`, targets `main`.

## Problem

Three hero renderers exist as one family but read as three unrelated blocks:

- `Sections::hero()` — standard in-flow hero (`.ch-hero`), used by About /
  Membership / Contact.
- `Sections::home_hero()` — full-bleed home hero with tiles (`.ch-home-hero`).
- `Sections::hero_filter()` — filtered hero with pills (`.ch-hero-f`).

Two inconsistencies: the filtered variant uses the cryptic abbreviation
`ch-hero-f`, and each method re-emits the same eyebrow + highlighted-title head
independently.

## Scope (targeted, low-risk)

The user chose targeted reconciliation over a full BEM rename: keep the three
variant block names (`ch-hero`, `ch-home-hero`), fix only the odd one out, and
unify the shared head — no wide CSS rewrite, no risk to the look-parity
guardrails.

### 1. Rename `ch-hero-f` → `ch-hero-filter`

Cleanly contained: `.ch-hero-f*` is styled only in `base.css` (4 selectors), and
used in `Sections::hero_filter()` plus three test files. Rename the block and its
`__title` / `__hl` / `__lede` elements throughout.

### 2. Shared hero head

All three variants already share `ch-eyebrow` and emit an identical
`{prefix}__title` with a `{prefix}__hl` highlight span. Extract:

```php
private static function hero_head( string $prefix, array $data ): string
```

returning `eyebrow + <h1 {prefix}__title> lead <span {prefix}__hl> highlight`.
Each variant calls it with its own block prefix (so per-look styling is
untouched) and then adds its distinct body (hero: lede+CTA+media; home: lede+CTA
+tiles; filter: lede+pills). The lede stays per-variant because `hero()` nests it
in a `__sub` row with the CTA, unlike the other two.

### 3. Document the family

A doc comment above the three methods describing them as one hero family with
three variants and the shared head/eyebrow contract.

## Testing

Refactor + rename — covered by existing `SectionsTest` / `PageRendererTest` /
`BaseStylesheetTest`, whose `ch-hero-f` assertions move to `ch-hero-filter`. Add
one test asserting all three variants share the `ch-eyebrow` + `__title` head, to
lock the unified system.

## Out of scope

- No rename of `ch-hero` / `ch-home-hero`.
- No sharing/merging of the per-look hero CSS.
- No structural change to any hero's body.
