# Changelog

All notable changes to this project are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
