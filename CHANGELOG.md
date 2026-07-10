# Changelog

All notable changes to this project are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.6.0] - 2026-07-10

### Added

- **About**, **Membership** and **Contact** pages under the Court Side look: new skin-agnostic section renderers — benefit grid, people/committee grid, history timeline, included/excluded list split, how-to-join steps, a native-`<details>` FAQ (works with no JS), and a presentational contact form with an info card. Shared header/footer extracted into `shell_header`/`shell_footer` helpers; the hero renders its media block only when an image or caption is present. Preview routes `?page=about|membership|contact`.

## [0.5.0] - 2026-07-10

### Added

- Full Court Side **Home page**: upgraded header (promo banner, Login + Join CTAs, active nav) and 4-column footer (social pills + newsletter form + legal bar), plus quick-access tiles, a reduced-motion-safe news ticker, sports card grid, clubhouse image band, membership band + tier grid, tabbed club-activity (fixtures/results/events), news cards, dark info strip, and sponsors grid — all skin-agnostic `ch-*` section renderers styled by the Court Side pack.
- `?page=` routing in the DB-free localhost preview so the site is navigable.

## [0.4.0] - 2026-07-10

### Added

- Court Side base look pack (tokens, Syne+Inter, stylesheet); skin-agnostic section renderers (header/hero/stats/footer); page renderer (head + `:root` + Home body honouring visibility); DB-free localhost preview with live, engine-derived accent switcher.

## [0.3.0] - 2026-07-10

### Added

- Base Look theming framework: pluggable look registry, single-accent colour engine
  with derived legible tokens, branding store, :root CSS composition.

## [0.2.0] - 2026-07-09

### Added

- Engine core & content foundation: PHP unit test harness (PHPUnit, dev-only),
  runtime class loader, base `Registry`, `Storage` interface with an autoloaded
  WordPress options adapter, `Content_Store` for singular section content, and
  page/section `Visibility` — all dependency-injected and unit-tested.

## [0.1.1] - 2026-07-09

### Added

- Design spec for the Sports Club Template **engine**
  (`docs/superpowers/specs/2026-07-09-sports-club-template-engine-design.md`):
  declarative page/section/collection registries, options + CPT storage,
  page/section show/hide, branding token engine with 5 font style presets,
  Blog + Social Feeds feature toggles, a graceful third-party integration seam
  (SureCart, SureDash, SureForms, SureRank, SureDonation, SureCookie, LatePoint),
  and a performance-first frontend.

## [0.1.0] - 2026-07-09

### Added

- Initial project scaffold: main plugin file with WordPress header, shared CI
  guardrail caller workflow (`ci-wordpress.yml`), PR and issue templates, Claude
  Code settings, `CLAUDE.md`, `approved-deps.json`, and a basic Playwright config
  pointed at a placeholder staging/preview URL.
