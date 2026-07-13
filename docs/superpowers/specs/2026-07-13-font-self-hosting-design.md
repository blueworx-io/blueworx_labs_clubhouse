# Font Self-Hosting — Design (Phase 5)

**Date:** 2026-07-13
**Status:** Approved for planning
**Target version:** 0.21.0 (minor — new feature)

## Goal

Realise the commitment made in the original engine design
([2026-07-09](2026-07-09-sports-club-template-engine-design.md)): serve every Base
Look's fonts from files bundled inside the plugin instead of the Google Fonts CDN.
The base-look theming framework plan explicitly deferred "§6 font self-hosting" to
this later phase; this design realises it.

**Outcome:** zero third-party font requests (no `fonts.googleapis.com`,
no `fonts.gstatic.com`), faster and more private, with **no visible change** — same
families, same weights, same `font-display: swap`.

### Scope

Like-for-like swap only. The active look's fonts are self-hosted; families, weights,
and rendering are unchanged. Demo-mode client-side look switching keeps its current
behaviour (a non-active look shows system fallback until the page is reloaded on that
look — exactly as it does today with the CDN). Broadening font loading for the demo
switcher is explicitly **out of scope** (YAGNI).

## Current state

- Each Base Look declares its fonts as metadata via `fonts()`, returning
  `array<int,array{family:string,weights:array<int,int>,display:string}>`.
- [`Page_Renderer::google_fonts_url()`](../../../includes/render/class-page-renderer.php)
  walks that metadata and builds a `https://fonts.googleapis.com/css2?…` URL.
- [`Page_Renderer::document()`](../../../includes/render/class-page-renderer.php)
  emits two `preconnect` links and a stylesheet `<link>` to that URL.
- [`Frontend`](../../../includes/frontend/class-frontend.php) enqueues the Google URL
  as the `clubhouse-fonts` style and adds `googleapis`/`gstatic` preconnect resource
  hints.
- The DB-free [`preview/index.php`](../../../preview/index.php) renders via the same
  `Page_Renderer`, so it also pulls fonts from Google today.

Six families across three looks, all licensed **SIL OFL 1.1**:

| Look | Display font | Body font |
|------|--------------|-----------|
| Court Side | Syne 600/700/800 | Inter 400/500/600 |
| Members' House | Fraunces 400/500/600/700 | Mulish 400/500/600/700 |
| Floodlight | Bricolage Grotesque 500/600/700/800 | Hanken Grotesk 400/500/600/700 |

## Design

### 1. Data model — extend `fonts()`

Keep `fonts()` as the single source of truth; add a filename **stem** so bundled file
paths derive deterministically. Family/weights/display are unchanged:

```php
array( 'family' => 'Syne', 'stem' => 'syne', 'weights' => array( 600, 700, 800 ), 'display' => 'swap' )
```

File path convention: `assets/fonts/{stem}-{weight}.woff2` (e.g.
`assets/fonts/syne-700.woff2`). The interface docblock in
[`interface-base-look.php`](../../../includes/theme/interface-base-look.php) is updated
to document the new shape (`stem` added to the `@return` annotation).

### 2. CSS generation — replace `google_fonts_url()`

`Page_Renderer::google_fonts_url()` is replaced by a pure method:

```php
Page_Renderer::font_face_css( Base_Look $look, string $base_url ): string
```

It walks `fonts()` and emits one `@font-face` rule per weight:

```css
@font-face{font-family:'Syne';font-style:normal;font-weight:700;font-display:swap;
  src:url(<base_url>/assets/fonts/syne-700.woff2) format('woff2')}
```

Pure, deterministic, and unit-testable — the same role `google_fonts_url()` played.
`$base_url` is the caller's plugin/preview root, with no trailing slash assumptions
baked in (normalise inside the method).

### 3. Wiring & removals

- **`Page_Renderer::document()`** — remove both Google `preconnect` links and the
  `googleapis` stylesheet `<link>`. Inject `font_face_css()` output in a `<style>`
  block alongside the existing `:root` block, using the passed `$plugin_url` as base.
- **`Frontend`** — remove the `clubhouse-fonts` Google enqueue and the
  `fonts.googleapis.com` / `fonts.gstatic.com` preconnect resource hints. Attach the
  generated `@font-face` CSS via `wp_add_inline_style` on the look style handle, base =
  `BLUEWORX_LABS_CLUBHOUSE_URL`.
- **`preview/index.php`** — use the same path, base pointing at the repo's
  `assets/fonts`, so the DB-free preview matches WordPress exactly.

**Acceptance:** a grep for `googleapis` / `gstatic` across the codebase returns
nothing after this phase.

### 4. Font assets & licensing

Bundle **22 woff2 files** (latin subset, static cuts) under `assets/fonts/`, named by
the stem convention:

- Court Side — `syne-600/700/800`, `inter-400/500/600`
- Members' House — `fraunces-400/500/600/700`, `mulish-400/500/600/700`
- Floodlight — `bricolage-grotesque-500/600/700/800`, `hanken-grotesk-400/500/600/700`

Sourced from the upstream **SIL OFL 1.1** releases (via google-webfonts-helper /
Fontsource), latin subset only, static instances at exactly the declared weights.
Each family's `OFL.txt` is bundled under `assets/fonts/licenses/` — required for
redistribution and good hygiene for the deployable zip.

### 5. Testing

- **Unit (PHPUnit):** `font_face_css()` emits one rule per declared weight with the
  correct `font-family`, `font-weight`, and `font-display: swap`; `src` resolves to
  `{base}/assets/fonts/{stem}-{weight}.woff2` with `format('woff2')`; output contains
  **no** `googleapis`. Update the existing look/renderer tests that assert
  `google_fonts_url`.
- **Playwright (guardrail requires a test for new functionality):** load a rendered
  page and assert (a) **no** network request to `fonts.googleapis.com` or
  `fonts.gstatic.com`, and (b) a representative `assets/fonts/*.woff2` returns HTTP 200.

### 6. Version & changelog

Minor bump **0.20.0 → 0.21.0** with a new changelog heading for the phase.

## Non-goals

- Preloading font files (`<link rel="preload">`) — not added; `font-display: swap`
  with self-hosted files is sufficient, and preload can be revisited if LCP data ever
  warrants it.
- Variable fonts, additional formats (woff/ttf), or non-latin subsets.
- Broadening demo-mode font loading to all looks at once.
