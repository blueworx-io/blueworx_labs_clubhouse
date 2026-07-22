# Configurable announcement bar (slice 2b)

**Date:** 2026-07-22
**Status:** Approved

## Problem

The site-wide announcement bar (`ch-banner`) is hardcoded in
`Blueworx_Clubhouse_Page_Renderer::shell_header()` (message + link). An owner
cannot edit it or turn it off from the admin, unlike the hero CTAs (editable via
`cget`) or the ticker (toggleable via visibility).

## Goal

Make the announcement bar owner-configurable — editable text + link, and a
dedicated show/hide toggle — reusing the existing content-catalogue pattern.
No visual change until an owner edits it (today's copy stays as the default).

## Design

The bar is a shell-header element rendered on every page, so it belongs in the
existing `global`/`header` content section rather than a per-page visibility
scope. Three new fields are added there:

| Field | Type | Default |
|-------|------|---------|
| `banner_show` | toggle | on |
| `banner` | text | `Summer sign-ups are open — register your interest for 2026/27 →` |
| `banner_href` | url | membership page URL |

### Changes

1. **Catalogue** — `class-content-catalogue.php`, `global`/`header` section:
   add `f_toggle('banner_show', 'Show announcement bar')`,
   `f_text('banner', 'Announcement text')`,
   `f_url('banner_href', 'Announcement link')` to the existing `fields` array.

2. **Renderer** — `class-page-renderer.php::shell_header()`: replace the two
   hardcoded strings with `self::cget()` reads of `banner`/`banner_href`. Pass
   an empty `banner` string when the `banner_show` toggle is off, so the
   existing empty-string guard in `Sections::header()` suppresses the markup.
   The default text/href match today's hardcoded values.

3. **Rendering** — no change to `Sections::header()`; it already renders the bar
   only when `banner !== ''`. The toggle collapses to that same guard.

### Toggle semantics

- `banner_show` on + non-empty text → bar renders.
- `banner_show` off → bar hidden, drafted text preserved in storage.
- `banner_show` on + empty text → bar hidden (empty-string guard).

## Testing

Extend existing PHPUnit suites:

- **Catalogue** — `global`/`header` exposes `banner_show`, `banner`,
  `banner_href` with the right types.
- **Renderer / header** — default content renders the bar with default copy;
  custom `banner`/`banner_href` render through; `banner_show` off hides the bar
  even with non-empty text.

## Out of scope

- No new visibility scope or Setup-screen section (the bar lives in Content →
  Global → Header, toggled by its own field).
- No restyle of the bar (2a already handled banner/ticker styling).
