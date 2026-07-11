# WordPress Integration — Design

**Date:** 2026-07-11
**Branch:** `wp-integration` (off `sports-teams-events-calendar` / PR #5, with the CI-preview wiring cherry-picked in so the branch's guardrails check is green)
**Status:** Approved (design), pre-implementation

## Context

The Clubhouse engine, theme framework, Court Side look, and the full eight-page site (home, about, membership, contact, login, sports, teams, events, calendar) are all built and render **DB-free** through `preview/index.php`. Nothing is wired into WordPress: `blueworx_labs_clubhouse_init()` is an empty stub. This plan makes the plugin actually runnable inside WordPress — the milestone that produces the first deployment zip.

`Page_Renderer::document()` produces a *complete standalone* HTML document with an inline `<style>` and **no** `wp_head()`/`wp_footer()`. That is correct for the preview but not for a well-behaved WP plugin. Per the project architecture (plugin owns the frontend via `template_include` while still calling `wp_head()`/`wp_footer()`; theme stays neutral) and the third-party integration seam (SureCart/SureDash/etc. need those hooks), WP rendering uses a proper enqueue path and a canvas template rather than echoing `document()`.

## Goals

- The active plugin serves the eight-page Court Side site inside WordPress, at production URLs.
- `wp_head()`/`wp_footer()` fire (admin bar, other plugins, meta all work).
- Look stylesheet + fonts are enqueued; the derived `:root` design tokens are injected inline and **cached** so they aren't recomposed every request.
- The active theme is untouched — no `get_header()`/`get_footer()`, no theme markup.
- A fresh activation renders the demo site immediately (safe defaults, no seeding required).
- No new runtime or dev dependency. Pure core stays unit-tested; WP glue stays thin.

## Non-goals (owned by later plans)

- **Real collection data / CPTs** — renderers keep their hardcoded demo data this round (next plan).
- **Admin setup flow** — no settings UI yet; `:root` cache exposes an `invalidate()` the admin plan will call on save.
- **Font self-hosting** — fonts stay on the Google CDN (a later plan).
- **Native blog feature toggle** — out of scope here; rewrite rules are scoped to the known page slugs so WP's own routing (posts, archives, admin) is untouched.

## Architecture

Two new classes keep the project's established **pure-core / thin-WP-glue** split, plus one template file and one JS asset.

### 1. `Blueworx_Clubhouse_Page_Map` — pure, WP-free (unit-tested)

Single source of truth for *which pages exist and how each renders*. An ordered map of page descriptors:

- `slug` — `''` for home (site root), else the URL segment (`about`, `membership`, `contact`, `login`, `sports`, `teams`, `events`, `calendar`).
- `label` — human label (for future menu use).
- `visibility_key` — the page key passed to `Visibility` / used by the renderer.
- render dispatch — maps the slug to the matching `Page_Renderer::<method>()`.

API:

- `pages(): array` — ordered descriptors.
- `has(string $slug): bool` — is this a Clubhouse page? (Gates routing; unknown slugs are left to WordPress.)
- `render(string $slug, Branding, Visibility): string` — returns the page **body** string (header + `<main>…</main>` + footer). Only called for known slugs.

`preview/index.php` is refactored to dispatch through `Page_Map::render()` instead of its own `switch`, so the preview and WordPress render byte-identical bodies from one code path.

### 2. `Blueworx_Clubhouse_Frontend` — thin WP glue (only WP-coupled class)

Registered from `blueworx_labs_clubhouse_init()` on `plugins_loaded`. Hooks:

- **`init`** — register the active Base Look(s) into a `Base_Look_Registry` backed by `Options_Storage`; register one rewrite rule per non-home `Page_Map` slug (`^{slug}/?$` → `index.php?clubhouse_page={slug}`), register the `clubhouse_page` query var, and mark the front page as the home slug.
- **`template_include`** (filter) — resolve the current request to a slug (front page → `''`; else the `clubhouse_page` query var). If `Page_Map::has()` (front page counts), return `templates/clubhouse.php`; otherwise return the incoming template unchanged.
- **`wp_enqueue_scripts`** — enqueue the fonts stylesheet and the look stylesheet (from `BLUEWORX_LABS_CLUBHOUSE_URL . $look->stylesheet()`, versioned by the plugin constant); `wp_add_inline_style()` the **cached** `:root` CSS onto the look handle; enqueue `assets/js/reveal.js` in the footer. Only enqueues on Clubhouse pages.
- Adds `fonts.googleapis.com` / `fonts.gstatic.com` preconnect via the `wp_resource_hints` filter.

The class resolves look/branding/visibility from storage and delegates **all** HTML to `Page_Renderer`/`Sections`/`Page_Map`. It contains no markup of its own.

Pure, testable decision helpers are extracted so the glue itself is trivial:

- `resolve_slug(array $query_vars, bool $is_front): ?string` — request → slug (or null).
- `enqueue_specs(Base_Look, string $root_css): array` — the handles/URLs/inline payload to enqueue (asserted in tests without a WP runtime).

### 3. `templates/clubhouse.php` — canvas template

```
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>…club name…</title>
  <?php wp_head(); // enqueued fonts, look stylesheet, inline :root ?>
</head>
<body <?php body_class(); ?>>
  <?php echo $clubhouse_page_body; // Page_Map::render(...) ?>
  <?php wp_footer(); ?>
</body>
</html>
```

The `<title>` is emitted from branding (title-tag support is deliberately **not** enabled, to keep the club name authoritative). `body_class()` is included for theme/plugin compatibility.

### 4. `:root` token caching

The composed `:root` CSS (`Theme_Css::compose()` → `to_css()`) is deterministic in (active look id, branding). A thin cache (method on `Frontend` or a small `Theme_Cache` helper) stores it in an **autoloaded option** together with a signature (hash of look id + branding values):

- Read: if the stored signature matches the current one, use the cached string; else recompose, store, and use.
- `invalidate()` deletes the option (the admin plan calls this on save).

`Theme_Css` stays pure; the cache is a Storage-backed wrapper, unit-tested with `Fake_Storage`.

### 5. `assets/js/reveal.js`

The progressive-enhancement scroll-reveal script (currently a PHP string in `Page_Renderer::reveal_script()`) is extracted to a single JS file. WordPress enqueues it in the footer; `document()` (preview) inlines the file's contents so there is one source of truth. Reduced-motion / no-JS behaviour is unchanged (nothing is ever hidden without JS).

### Activation / deactivation

- `register_activation_hook` → run the rewrite-rule registration, then `flush_rewrite_rules()`.
- `register_deactivation_hook` → `flush_rewrite_rules()` (rules disappear once the plugin's `init` stops running).
- No content seeding: renderers carry demo data; `Branding`/`Visibility` have safe defaults; `Base_Look_Registry::active()` falls back to the first-registered look (Court Side). A fresh activation therefore renders the demo site.

## Testing strategy (no WP runtime, no new deps)

- **`Page_Map`** — unit tests: page list/order, `has()` for known/unknown slugs, `render()` dispatches to the right method and returns a body containing that page's unique marker.
- **`:root` cache** — unit tests with `Fake_Storage`: compose→store, signature hit reuses, signature change recomposes, `invalidate()` clears.
- **`Frontend` glue** — a dependency-free **WP-function shim** (recorder stubs for `add_action`/`add_filter`/`add_rewrite_rule`/`add_rewrite_tag`/`wp_enqueue_style`/`wp_add_inline_style`/`wp_enqueue_script`/`get_option`/etc., defined in the PHPUnit bootstrap guarded by `function_exists`) lets tests assert: the right hooks are registered; a simulated `/about` request selects the canvas template and enqueues the expected handles; a non-Clubhouse request passes the template through untouched.
- **Playwright** — the existing preview smoke already exercises end-to-end rendering (same body path via `Page_Map`). No change needed for the render guarantee; the WP glue is covered by the shimmed integration tests above.
- **Manual WP check** — if a local WordPress is available, activate the plugin and visit `/` and `/about` to confirm real-runtime rendering + `wp_head()`/`wp_footer()`. If not available in this environment, this is called out explicitly rather than claimed.

## Git, versioning, deployment

- Branch `wp-integration` off `sports-teams-events-calendar` (#5) — the only branch carrying all eight pages. The CI-preview wiring (PR #6) is cherry-picked in so this branch's guardrails check passes before #6 propagates through the stack.
- Minor bump **0.11.0 → 0.12.0** (new feature); changelog entry folds in a note that this branch also carries the CI-preview wiring.
- Extends the merge train: merges after #5 reaches `base-look-theming-design`.
- **Deployment:** this makes the plugin WordPress-runnable, ending the standing "no zip yet" exception. Produce the deployment zip at `<plugin-parent-dir>/blueworx-labs-clubhouse.zip` (removing any older versioned zip first) at the end of implementation, per the house WordPress-plugin rule.

## Decomposition (for the implementation plan)

1. `Page_Map` (pure) + refactor `preview/index.php` to use it.
2. `assets/js/reveal.js` extraction + `document()`/preview inline from file.
3. `:root` cache helper (Storage-backed) + `invalidate()`.
4. `Frontend` glue + pure `resolve_slug`/`enqueue_specs` helpers + `templates/clubhouse.php`; wire `blueworx_labs_clubhouse_init()` and activation/deactivation hooks.
5. WP-function shim + integration tests; unit tests for 1 & 3.
6. Version bump, changelog, deployment zip.
