# Look CSS parity — design

**Date:** 2026-07-21
**Status:** Approved, ready for planning
**Slice:** 0 of 4 (see "Where this sits" below)

## Problem

Six front-end components are styled by `court-side.css` and by neither of the
other two looks. Under Floodlight and Members House, the **sports, teams, events
and calendar** pages therefore render structurally correct but essentially
unstyled — raw filter links, unstyled cards and rows — and the "Follow the club"
group on home and contact degrades to bare anchors.

This was not in the UX review, because the review was carried out on Court Side.
It was found by comparing the classes `Sections` emits against the selectors each
look defines.

Measured on `main` at 0.26.4:

| | classes |
|---|---|
| Emitted by `class-sections.php` | 259 |
| Unstyled under `court-side` | 15 |
| Unstyled under `floodlight` | 62 |
| Unstyled under `members-house` | 62 |

The Floodlight and Members House gaps are **identical**, and 15 of the 62 are the
same entries Court Side "lacks" — these are not real gaps (see Exemptions). So the
genuine shortfall is **47 classes, forming six whole components**:

```
ch-hero-f + ch-filter (6)   ch-scard (9)    ch-cal (11)
ch-event (7)                ch-archive (5)  ch-social (9)
```

### Root cause

Each look is an independently authored, self-contained stylesheet. There is no
shared structural layer, and nothing ever checked that a look covered what the
renderer emits. A component added to `Sections` is styled in whichever look the
author had open. Writing the missing CSS without addressing this only resets the
clock.

## Goals

- All three looks render every page completely.
- The three looks are built from the same building blocks, so switching skins is
  seamless.
- A component added in future cannot silently miss a look.
- Court Side, which has been reviewed and signed off, does not change visually.

## Non-goals

- The findings in the UX review itself. Those are slices 1–3.
- Redesigning any look's visual character.
- Rewriting the three existing stylesheets in this slice. That is the incremental
  migration described below, done behind the guardrail.

## Approach

Considered three options:

- **A. Full extraction** — one tokenized `base.css` plus three thin skins. Best
  end state; rewrites all 1,358 existing lines including the reviewed Court Side
  rules, so highest risk of unintended visual change to the look that ships.
- **B. Additive base** — `base.css` carrying only the six missing components.
  Near-zero risk, but leaves the duplication and does not deliver shared building
  blocks.
- **C. B now, migrating to A incrementally behind a guardrail.** *(Chosen.)*

C reaches A's end state without a single risky rewrite, and the guardrail is what
actually prevents recurrence — the reason this drifted is that nothing checked.
It also matches how the repo already works: the zip has an allowlist verifier, CI
has a zero-tests check.

## Architecture

### `assets/looks/base.css`

Structural rules written **only** against the shared token vocabulary — the ten
tokens every look defines (`--color-bg`, `--color-paper`, `--color-ink`,
`--color-ink-soft`, `--color-line`, `--radius-xl/lg/md`, `--font-display`,
`--font-body`) plus the engine-derived accent tokens. No literal colours or font
families.

### Load order

`base.css` loads **before** the active look's stylesheet, so look rules continue
to win. Two emission paths, both must be wired:

| Path | Location | Used by |
|---|---|---|
| `Page_Renderer::document()` | `includes/render/class-page-renderer.php:49-55` | the PHP preview, and the WP template |
| `Frontend::enqueue_specs()` | `includes/frontend/class-frontend.php:41-52` | the WordPress front end |

`enqueue_specs()` gains a `base_stylesheet_url` key alongside `stylesheet_url`.
Both functions are already unit-tested, so those tests are updated rather than
added.

`base.css` is look-independent — it is a constant path, not a `Base_Look` method.
Adding it to the `Base_Look` interface would imply a look could substitute its
own base, which is the opposite of the intent.

### Specificity constraint

Court Side is expected to be unchanged because its rules load later and win. That
guarantee **only holds if `base.css` does not out-specify them**. Therefore:

> Every selector in `base.css` stays at single-class specificity. No compound
> selectors, no `!important`, no ID selectors.

This is a correctness constraint, not a style preference, and the Court Side
visual pass before merge exists to verify it held.

## Scope of `base.css` in this slice

The six components only, ported from `court-side.css` with literals replaced by
tokens:

`ch-hero-f`/`ch-filter` · `ch-scard` · `ch-event` · `ch-archive` · `ch-cal` ·
`ch-social`

Where a value is genuinely Court Side *character* rather than structure, it stays
in `court-side.css` as an override. When in doubt, the value goes in the look, not
the base — a base rule that shouldn't be shared is harder to detect than a look
rule that could have been.

## Guardrail

Two checks, deliberately covering different failure modes.

### `LookCoverageTest` (PHPUnit)

Fails when any class the renderer emits is styled by neither `base.css` nor the
look under test.

- Extracts emitted classes from `class-sections.php` and `class-page-renderer.php`.
- Extracts defined selectors from `base.css` and each look stylesheet.
- The selector extractor understands **descendant and child combinators**, so a
  wrapper styled via `.ch-split > *` counts as covered. This keeps the exemption
  list small and honest rather than papering over real gaps.

**Exemptions.** A single `EXEMPT` constant, one commented entry each. It exists
because a handful of extracted strings are not selectors at all — chiefly
`ch-badge--`, an artifact of `class="ch-badge--<?php echo $variant; ?>"`. The
list is written from the known 15 and each entry must state why. It is not to be
extended to make a failing check pass; a new entry means either a real gap or an
extractor bug.

### `look-parity.spec.js` (Playwright)

Catches what the static check cannot: a rule that exists but never matches the
emitted markup.

For each of the three looks, loads the four previously-broken pages and asserts a
real computed style on a marker element — e.g. `.ch-filters` computes to
`display: flex`, `.ch-cal__month` has non-zero padding. Assertions are on computed
values, not screenshots, so there are no baselines to churn.

Look switching goes through demo mode's cookie, already exercised by the existing
demo specs. Runs under the `wordpress` Playwright project (see `docs/testing.md`).

## Migration path

The guardrail lands in this slice and stays. Shared rules then move out of the
three look stylesheets into `base.css` **one component at a time**, each step
verified green. That is how C converges on A without a risky rewrite.

Migration is explicitly *not* part of this slice's completion criteria.

## Testing and verification

- `LookCoverageTest` passes for all three looks.
- `look-parity.spec.js` passes for all three looks.
- Existing suites stay green: 480 PHPUnit tests, 27 Playwright (preview and
  WordPress projects), PHPCS clean.
- Manual look-switch pass over 9 pages × 3 looks via demo mode.
- Court Side visual comparison before/after, to confirm the specificity
  constraint held.
- `npm run build:zip` verification clean.

## Risks

| Risk | Mitigation |
|---|---|
| A `base.css` rule out-specifies Court Side and changes it | Single-class specificity constraint; explicit Court Side visual pass before merge |
| Tokenizing a Court Side literal loses intended character in that look | Court Side keeps its own rules — base is additive here, so its rendering is driven by its own stylesheet either way |
| The exemption list becomes a dumping ground | Every entry carries a reason; adding one is a review point, not a routine fix |
| Floodlight / Members House inherit Court Side's *proportions* and look derivative | Accepted for this slice — a complete page in the wrong proportions beats an unstyled one. Refining per-look character is follow-on work, and the base makes it an override rather than a rewrite |

## Where this sits

Sequenced from the UX/UI review of 2026-07-21:

0. **Look CSS parity** — this document.
1. **Cross-cutting fixes** — footer social consolidation (the FILCS row is five
   hardcoded `href="#"` letter-circles including two non-networks, while real
   branding URLs exist), one CTA label per destination, consistent pill treatment,
   eyebrow-pill on every section, dead links, body/display font pairing.
2. **Page structure and flow** — home section order, announcement/ticker merge,
   About timeline and Facilities placement, Membership pricing above the fold,
   the no-op filter pills on teams/sports/events/calendar.
3. **Style unification** — the three hero components (`home_hero`, `hero`,
   `hero_filter`) and the empty `.ch-media--empty` block that `hero()` emits when
   `image_alt` is set without an image, which is why Membership reads as out of
   place.
