# Sports Club Template Engine — Design

**Status:** Approved (design phase)
**Date:** 2026-07-09
**Repo:** `blueworx_labs_clubhouse` (WordPress plugin on `bluegroup_core_foundation` guardrails)

## Summary

`blueworx_labs_clubhouse` is a reusable **Sports Club Template Site** delivered as a
single WordPress plugin. It is installed on a **separate WordPress site per club**
(agency model, not multisite). Each club applies its own branding (logo, colors) and
picks one of five font "style" presets. Site owners fill in pre-configured sections
through a bespoke admin UI; that content populates a set of prebuilt, consistently
laid-out frontend pages. Owners can show/hide whole pages and individual sections,
and toggle features (Blog, Social Feeds).

This spec covers the **engine** only. The concrete pages, sections, and collections
are defined in a **second spec** once the full site mockup is provided; they plug
into the engine's registries with no re-architecting.

## Goals

- One reusable, self-contained plugin that renders a consistent club website.
- Per-site branding and a 5-way font style preset, applied as data (no recompile).
- Owner-editable section content with strict, layout-safe show/hide control.
- An extensible content model so mockup-defined pages/sections/collections drop in as modules.
- A **fast** frontend and a **graceful integration seam** for third-party plugins.

## Non-Goals (v1)

- The concrete content inventory (specific pages/sections/collections) — deferred to the mockup-driven spec.
- Concrete third-party integration adapters — only the seam/interface + graceful detection ship in v1.
- Multisite-specific features (each club is its own install).
- Free-form page building / block editing (consistency is enforced by fixed layouts).

## Key Decisions

| Decision | Choice | Rationale |
|---|---|---|
| Distribution | Separate WP install per club | Agency model; fully isolated per club; matches single-plugin deployment tooling. |
| Frontend ownership | Plugin owns page templates via `template_include` | Honors "build as a plugin, in code"; consistent layout across clubs; single deployable artifact. |
| Authoring | Bespoke admin UI, **no ACF** | Self-contained; no per-site ACF Pro licensing or version coupling. |
| Content model | Approach A — declarative module registries | Mirrors house `Feature_Registry` pattern; extensible for mockup content. |
| Singular storage | Autoloaded WP options keyed by slug | Fast, near-zero extra queries for one-of-a-kind pages. |
| Collections | Native custom post types | Familiar WP list UI; right tool for repeating content. |
| Visibility | Single `clubhouse_visibility` option | Same toggle pattern as feature registry. |
| Branding | CSS custom properties (design tokens) | What `theme.json` would emit, kept in the plugin; rebrand is data-only. |
| Style presets | Hardcoded map of 5 font pairings | Styles change only fonts, per requirement. |

## Architecture

All code lives in the plugin, class-based, following the existing `includes/` house style
(`final class`, typed PHP 8, `if (!defined('ABSPATH')) exit;`).

```
blueworx-labs-clubhouse.php        Bootstrap: constants, autoload, init
includes/
  core/
    class-registry.php             Base registry: register / get / ordered iteration
    class-plugin.php               Wires modules on plugins_loaded
  pages/
    class-page-registry.php        All template pages
    abstract-page-module.php       slug, title, template, ordered section list
  sections/
    class-section-registry.php     All section types
    abstract-section-module.php    Field schema + render()
  collections/
    class-collection-registry.php  CPT modules (sponsors, team, fixtures — TBD)
    abstract-collection.php        CPT args, admin columns, query helpers
  content/
    class-content-store.php        Read/write singular content (autoloaded options)
    class-visibility.php           Show/hide state for pages + sections
  admin/
    class-admin-ui.php             Bespoke tabbed settings screens
    class-field-renderer.php       Field schema -> edit form
  frontend/
    class-router.php               template_include -> plugin templates
    class-assets.php               Per-section conditional enqueue
  branding/
    class-branding.php             Logo, colors, favicon settings
    class-style-presets.php        The 5 font sets
    class-token-engine.php         Emit CSS custom properties in wp_head
  features/
    class-feature-registry.php     Toggleable features (Blog, Social Feeds)
    class-social-feeds.php
    class-blog.php
  integrations/
    class-integration-registry.php Adapter registry
    interface-integration.php      detect() / render() / is_available()
templates/                          PHP view files per page + section
tests/                              Playwright specs (+ PHP unit tests for pure logic)
```

### The three registries (Approach A)

- **Page Registry** — each page is a module declaring `slug`, `title`, an ordered list of
  section slugs, and its template. Mockup pages become new module classes.
- **Section Registry** — each section declares a **field schema** (e.g.
  `heading:text`, `body:richtext`, `image:media`, `cta:link`) plus a `render()`. The admin
  builds the edit form from the schema; the frontend renders from stored content. A section
  type is reusable across pages.
- **Collection Registry** — each collection is a CPT module. Sections that display a
  collection query it via the collection's helpers.

### Storage model

- **Singular content** → one **autoloaded WP option per page**, keyed by section + field.
- **Collections** → native CPT posts.
- **Visibility** → a single `clubhouse_visibility` option:
  `{ pages: { slug: bool }, sections: { "page.section": bool } }`.

### Frontend rendering

- On activation, the plugin ensures its pages exist (lightweight WP pages mapped to slugs).
- A **Router** hooks `template_include`: for a plugin-owned page it loads the plugin template
  (header → visible sections in order → footer), still calling `wp_head()` / `wp_footer()`.
  A hidden page returns a standard 404; hidden sections are skipped at render.
- The active theme stays neutral; the plugin owns the shell and branding.

## Branding & Style Presets

- **Branding admin screen**: logo (+ optional inverse logo), favicon, and a color palette by
  **role** — `primary`, `secondary`, `accent`, `ink` (text), `surface` (background). Hover/
  neutral shades are derived, so owners set ~5 colors.
- **Style presets**: a hardcoded map of **5 named font sets** (heading + body pairing). Owner
  picks one; presets change only fonts.
- **Token engine**: emits all branding as **CSS custom properties** in a single small
  `<style>` block in `wp_head` (`--club-color-primary`, `--club-font-heading`, …). All plugin
  CSS references these variables; rebranding is data-only.
- Fonts are **self-hosted** (bundled), not loaded from a third-party CDN — faster, privacy-
  friendly, and compatible with a future SureCookie integration.

## Features & Toggles

- A **Feature Registry** (same pattern as the house `Feature_Registry`) with **Blog** and
  **Social Feeds** as the first toggleable features.
  - **Blog** → native WP posts. On/off controls availability of the Blog page/section.
  - **Social Feeds** → config screen for platform handles/URLs (Facebook, Instagram, X,
    YouTube — adjustable). Rendered as a lazy-loaded, embed-based section so it never blocks
    first paint.

## Integration Seam (architected, not scoped)

- An **Integration Registry**; each third-party plugin gets a thin **adapter** implementing a
  common interface: `detect()` (via `class_exists` / `function_exists` / `is_plugin_active`),
  a safe render helper, and graceful no-op when absent — same philosophy as the ACF-reader
  fallback.
- Sections may declare "hosts integration X" (e.g. SureCart store, SureForms form, LatePoint
  booking, SureDonation form). SureRank (SEO) and SureCookie (consent) are site-wide adapters
  rather than section-hosted.
- Target integrations to design for: **SureCart, SureDash, SureForms, SureRank, SureDonation,
  SureCookie, LatePoint Bookings**.
- **v1 ships the registry + adapter interface + graceful detection only.** Concrete adapters
  land when each integration is scoped.

## Performance Strategy (first-class)

- Server-rendered PHP; **no front-end framework / hydration**.
- **Autoloaded options** for singular content → page render adds near-zero queries;
  collections use bounded, cached `WP_Query`.
- **Per-section conditional asset loading** — a section's CSS/JS enqueues only where that
  section is visible; nothing global-by-default.
- **Inline critical tokens** in `wp_head`; defer/async non-critical JS; lazy-load images and
  social embeds.
- **Full-page-cache friendly** — output depends only on stored content + options (no per-
  request personalization), so it works with server/CDN caching.
- Small CSS footprint and minimal JS treated as a guardrail.

## Testing

- **Playwright** (against the staging/preview URL, per the CI workflow): page show/hide,
  section show/hide, branding tokens applied (computed CSS variables), style-preset switch
  changes fonts, feature toggles, and graceful behavior when an integration plugin is absent.
- **PHP unit tests** for the pure logic: registries, content store, visibility resolution
  (mirrors the foundation's `checks.mjs` unit-test approach).

## Delivery Phases

1. **Engine (this spec).** Core, registries, content store, visibility, branding + token
   engine + style presets, feature registry with Blog + Social Feeds, admin UI framework,
   frontend router, integration seam (interface + detection), performance baseline, tests.
2. **Content modules (next spec, mockup-driven).** Concrete pages, sections, and collections
   declared against the engine registries.
3. **Integration adapters (later, per integration).** Concrete Sure*/LatePoint adapters.

## Open Questions (to confirm during the mockup-driven spec)

- Exact color-role set (is a 5-role palette enough, or do some clubs need more?).
- Final social platform list.
- The concrete page/section/collection inventory (from the mockup).
