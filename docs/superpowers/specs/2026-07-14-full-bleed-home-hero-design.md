# Full-bleed Home hero — design spec

**Date:** 2026-07-14
**Branch:** `full-bleed-home-hero`
**Status:** Approved for planning

## Goal

Reshape the **Home** page hero to match the supplied guide: a full-bleed
background image with the hero content overlaid, the quick-link row tucked into
the base of the hero as icon cards, and the news ticker sitting directly beneath.
All three Base Looks (Court Side, Floodlight, Members' House) adopt the new
layout, each in its own visual character.

Colour tokens, fonts, and each look's identity are preserved — this is a layout
and composition change, not a restyle. Existing hero copy and CTA labels are kept
verbatim.

## Scope

**In scope**

- A new **Home-only** hero renderer (`home_hero`) rendering the full-bleed
  composition.
- Quick-links move *into* the hero as an integrated foot row with icons; Home
  stops emitting a separate `quick_tiles` section.
- New `.ch-home-hero` layout CSS in all three look stylesheets.
- Graceful no-image fallback (toned accent panel) so the DB-free preview and any
  club without a hero photo still render correctly.
- Version bump + changelog + a Playwright test for the new Home hero.

**Out of scope (explicitly excluded)**

- Any change to the shared `hero()` renderer or the other pages that use it
  (About, Membership, Sports, Events).
- Copy changes: hero eyebrow/title/lede/CTA labels and the four quick-link labels
  stay exactly as they are today.
- Expanding the quick-links from four to the six shown in the guide (that is a
  copy change; the guide's six labels differ from the current four).
- Changes to `quick_tiles()` itself — it remains available for other uses.

## Current state (baseline)

- `Blueworx_Clubhouse_Page_Renderer::home()` composes:
  `hero → quick_tiles (4 text tiles) → ticker → stat_strip → …`.
- `Blueworx_Clubhouse_Sections::hero()` renders eyebrow, an `h1` title with a
  highlighted span, a sub-row (lede + two CTAs), then a rounded media box *below*
  the text. `hero()` is shared by five pages.
- `Blueworx_Clubhouse_Sections::quick_tiles()` renders text label + arrow tiles,
  no icons.
- Sections are **skin-agnostic**: markup carries no colours/fonts/radii/look
  slugs. Each Base Look styles the same `ch-*` classes in its own CSS file
  (`assets/looks/court-side.css`, `floodlight.css`, `members-house.css`). All
  three currently give the hero the same structure, differing only in the title
  highlight treatment and the background wash.
- On Home the hero `image` is currently `''`, so the hero renders the patterned
  placeholder, not a photo. The new layout must look right with no image.

## Design

### 1. New `home_hero()` section renderer

Add a dedicated renderer in `class-sections.php`; leave `hero()` untouched.

Signature (data-driven, same escaping discipline as the rest of the file):

```
home_hero( array $data ): string
```

`$data` keys:

- `eyebrow`, `title_lead`, `title_highlight`, `lede` — as `hero()` today.
- `cta_primary`, `cta_primary_href`, `cta_secondary`, `cta_secondary_href` — as
  `hero()` today.
- `image`, `image_alt` — background photo; empty string → fallback panel.
- `tiles` — ordered array of `{ label, href, icon }`, where `icon` is a semantic
  key (e.g. `join`, `tour`, `fixtures`, `contact`) mapped to an inline SVG.

Markup shape (semantic, skin-agnostic classes only):

```html
<section class="ch-home-hero">
  <div class="ch-home-hero__bg">        <!-- background image or fallback -->
    <img class="ch-home-hero__img" ...>  <!-- only when image != '' -->
  </div>
  <div class="ch-home-hero__scrim" aria-hidden="true"></div>
  <div class="ch-wrap ch-home-hero__in">
    <span class="ch-eyebrow">…</span>
    <h1 class="ch-home-hero__title">lead<span class="ch-home-hero__hl">hl</span></h1>
    <p class="ch-home-hero__lede">…</p>
    <div class="ch-home-hero__cta">
      <a class="ch-btn ch-btn--accent" …>primary</a>
      <a class="ch-btn ch-btn--ghost" …>secondary</a>
    </div>
    <div class="ch-home-hero__foot" role="list">     <!-- integrated quick-links -->
      <a class="ch-home-hero__tile" role="listitem" href="…">
        <span class="ch-home-hero__tile-ico" aria-hidden="true"><svg…></span>
        <span class="ch-home-hero__tile-label">…</span>
        <span class="ch-home-hero__tile-arrow" aria-hidden="true">→</span>
      </a>
      …
    </div>
  </div>
</section>
```

- The background `<img>` is rendered only when a URL is present; when absent the
  `__bg` element is a plain toned panel (styled per look) so text stays legible.
  The `img` carries the `image_alt`; when there is no image the section conveys
  meaning through its text content, so no `img`/`role="img"` is emitted.
- The scrim is a decorative overlay (`aria-hidden`) that guarantees text contrast
  over any photo.
- Tile icons are inline SVGs defined as class constants in `class-sections.php`,
  following the existing `FACEBOOK_ICON` / `INSTAGRAM_ICON` pattern
  (`fill="currentColor"`/`stroke="currentColor"`, no icon font, no external
  request). An unknown icon key renders no glyph (label + arrow still shown).

### 2. Home page renderer wiring

In `Page_Renderer::home()`:

- Replace the `hero()` call with `home_hero()`, passing the **same** eyebrow,
  title, lede and CTA values that are used today (no copy change). The former
  hero `image_caption` ("Saturday, floodlights on") has no place in the new
  composition and is dropped.
- Remove the separate `quick_tiles()` block from Home; move those four tiles
  (same labels, same hrefs) into the `home_hero()` `tiles` argument, each with an
  icon key:
  - Join the club → `join`
  - Take a tour → `tour`
  - See fixtures → `fixtures`
  - Get in touch → `contact`
- The `ticker` block is unchanged and now follows the hero directly.
- Visibility gating: the existing `is_section_visible('home','hero')` continues to
  gate the hero. The `quick_tiles` visibility key is no longer emitted on Home;
  its tiles live inside the hero and share the hero's visibility. (No migration
  needed — visibility keys are checked, not required.)

### 3. Look CSS (three files)

Each look adds a `.ch-home-hero` block. Shared behaviour, per-look character:

- **Frame:** full-bleed section; `__bg`/`__img` cover the frame
  (`object-fit: cover`); a minimum height that scales with viewport; content in
  `.ch-wrap` overlaid above the scrim.
- **Scrim + text colour:** a gradient scrim from existing tokens giving legible
  overlaid text over a photo *and* over the fallback panel. Court Side punchy,
  Floodlight glow, Members' House refined — matching each look's existing hero
  highlight character.
- **Fallback panel:** when there is no photo, `__bg` is a toned accent/ink panel
  (existing tokens) so the composition still reads.
- **Foot / tiles:** the icon-card row tucked against the hero base, wrapping
  responsively; hover states consistent with each look's current `.ch-tiles__tile`
  treatment.
- **Motion:** reuse the existing `ch-rise` entrance animation on eyebrow / title /
  lede / foot; continue to disable under `prefers-reduced-motion`.
- **Responsive:** single-column stack with reduced min-height and full-width tiles
  on narrow viewports (mirroring the existing `max-width:640px` hero handling).

### 4. Accessibility

- `h1` remains the page's main heading; heading order unchanged.
- Skip link and landmark structure unchanged.
- Scrim ensures WCAG-adequate contrast for overlaid text in every look, photo or
  fallback.
- Icons are decorative (`aria-hidden`); each tile's accessible name is its text
  label.
- Keyboard: tiles are ordinary links in DOM order, fully focusable; visible focus
  styles preserved.

## Testing

- Add a Playwright assertion (extending `tests/smoke.spec.js` or a new
  `tests/home-hero.spec.js`) that on Home:
  - `.ch-home-hero` is present and `.ch-hero` (the shared hero) is **not**.
  - The hero contains the four `.ch-home-hero__tile` links with their expected
    labels/hrefs.
  - The `.ch-ticker` section immediately follows the hero.
  - The page still returns 200 with the correct title and `#ch-main`.
- Confirm the other pages that use `hero()` are unchanged (existing smoke
  assertions for about/membership/etc. still pass).
- Manual check across all three looks in the DB-free preview (no image →
  fallback panel) via the look swatches.

## Guardrails / deployment

- Minor version bump (new layout feature) in `blueworx-labs-clubhouse.php` and
  `package.json`, kept in sync.
- Changelog entry describing the Home full-bleed hero.
- PHPCS clean (run once as a final check; findings presented, not auto-looped).
- No new dependencies.

## Risks & mitigations

- **Contrast over arbitrary photos:** mitigated by a per-look scrim tuned for
  worst-case bright images; verified in review.
- **Empty-image today:** the fallback panel is the *default* path right now, so it
  gets first-class treatment and is what the preview/tests exercise.
- **Divergence from shared `hero()`:** accepted — `home_hero()` is intentionally a
  separate renderer so the other four pages are untouched.
