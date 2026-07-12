# Changelog

All notable changes to this project are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.11.0] - 2026-07-11

### Sports, Teams, Events & Calendar pages

The four remaining collection pages, completing the eight-page ClubHouse site under the Court Side look.

#### New

- **Four new pages** — Sports, Teams, Events and Calendar — composed under per-section `Visibility` with hardcoded ClubHouse demo data, routed in the preview via `?page=`.
- **Five new skin-agnostic section renderers** on `Blueworx_Clubhouse_Sections`: `hero_filter` (filter-pill hero), `stat_card_grid` (chip + stats cards for Sports/Teams), `event_grid` + `event_archive` (upcoming cards + past list), and `calendar_months` (month-grouped fixtures/results with W/D/L status badges). All emit only `ch-*` classes, escape interpolated text, and carry list semantics.
- **Court Side styling** for every new hook, consuming engine custom properties only.

#### Notes

- Demo data is hardcoded this round; the later Collections/CPT plan swaps the data source behind the unchanged renderers.
- Filter pills are presentational (unfiltered demo data), consistent with the progressive-enhancement / presentational-forms decisions.
- Members' House and Floodlight will need the same new `ch-*` hooks styled when they rebase onto this branch (re-skin contract).

## [0.10.0] - 2026-07-11

### Floodlight Base Look

A bold, dark, night-match re-skin covering every `ch-*` hook.

#### New

- **Floodlight — third Base Look.** A bold, dark, night-match re-skin (Bricolage Grotesque + Hanken Grotesk, warm-ink canvas, bold-modern 16/11/7 radii, accent spent as glow) covering every `ch-*` hook. Adds `includes/looks/class-floodlight.php` and `assets/looks/floodlight.css`, registers the look in the DB-free preview, and cycles looks via `?look=`. Pure re-skin: no changes to section renderers or the theme engine. On the dark shell all accent text/marks route through the engine's AA-guaranteed `--color-accent-deep`; the engine's `accent-ink`-on-dark limitation is sidestepped by the glow idiom, not triggered.

> Note: `0.9.0` is the Members' House Base Look, delivered on its own sibling branch/PR; Floodlight takes `0.10.0` so the two do not collide when both merge into `base-look-theming-design`.

## [0.9.0] - 2026-07-10

### Members' House — second Base Look

The first re-skin. A refined, editorial Base Look that reuses the engine and every
skin-agnostic section unchanged — swapping the active look changes only the tokens,
fonts, and stylesheet.

- **New Base Look `members-house`** (`Blueworx_Clubhouse_Members_House`): warm parchment
  shell, warm near-black ink, small crisp radii (10/7/4px), Fraunces (display) + Mulish
  (body). Accent stays engine-derived — the look defines no accent tokens.
- **Refined-editorial stylesheet** (`assets/looks/members-house.css`): every `ch-*` hook
  restyled in a restrained idiom — hairline rules, rectangular buttons, an accent
  underline on the hero highlight (no rotated block), quiet accent-wash bands, and fine
  accent marks. Accent is referenced only via `var(--color-accent*)`, so a club still
  re-themes by swapping one colour. All accessibility and motion behaviour (skip link,
  focus indicator, no-JS nav drawer, ticker pause, scroll-reveal, reduced-motion) is
  preserved through the shared section markup.
- **Preview look switch**: `preview/index.php` registers both looks and takes `?look=`
  (default Court Side), with a toggle to flip between them; the accent swatches derive
  from the active look's shell so they stay AA-correct per look.

## [0.8.1] - 2026-07-11

### CI preview wiring

Un-gates the merge train by running Playwright against the plugin's DB-free PHP preview instead of a not-yet-existent staging site.

#### Changed

- **Playwright now boots the preview itself.** `playwright.config.js` gains a `webServer` that starts `php -S` (docroot = plugin root) and points `baseURL` at `preview/`, so CI needs no deployed staging URL. The foundation `preview_url` input in `.github/workflows/ci.yml` is set to the same localhost preview URL.
- **Real smoke test.** The skipped placeholder (`tests/example.spec.js`) is replaced by `tests/smoke.spec.js`, which loads each built page (home, about, membership, contact, login) and asserts it renders (title + `<main>` landmark) and that `?page=` routing resolves to the right page rather than the Home fallback.

## [0.8.0] - 2026-07-10

### Login page, hover motion, and design polish

Second design-review pass on the Court Side site.

#### New

- **Member login page** (`?page=login`). A centred sign-in card — email/password with autocomplete, remember-me, forgot-password link, and a "not a member yet? Join the club" prompt — rendered by a new skin-agnostic `auth()` section. The header's "Log in" (bar and mobile drawer) now routes here instead of `#`.

#### Spacing

- **Fixed inline-title sections butting against their content.** Sections that render the eyebrow + title directly above a grid (benefits, news, directory, timeline, included/excluded, steps, FAQ, contact, activity tabs) had no gap between the heading and the grid below — the title's descenders touched the first row. Added the same 34px gap the header-variant sections already get from `.ch-sec__head`.
- **FAQ now shares the one 1200px content column** with every other section. It was capped at 820px and centred, which read as a misaligned island against the full-width sections above and below; answers keep their own 64ch cap for readability.

#### Layout

- **Directory (contact page) caps at three across** instead of packing six narrow columns, so names and emails keep room to breathe; steps to two, then one, as the viewport tightens.

#### Motion

- **Hover animations across the previously-inert surfaces.** Primary CTAs (accent/ink buttons) now lift and deepen on hover — before, only the ghost outline responded; the paper cards (benefits, tiers, steps) lift with an accent edge; directory people and their avatars, and FAQ questions, respond too. All hover transforms respect `prefers-reduced-motion`.
- **Entrance motion** on scroll — the hero rises in on load and each section reveals as it enters the viewport, via CSS plus a tiny IntersectionObserver (no runtime dependency; per the foundation guidance, GSAP is reserved for genuinely complex animation). Content stays fully visible without JS and when reduced motion is preferred.

#### Tooling

- **PHP linting is now real.** Added PHP_CodeSniffer with a curated, tab-aware ruleset (`phpcs.xml.dist`) tuned to the project's WordPress-flavoured style; run locally with `composer lint` and enforced by the shared CI PHPCS step. The previous `npm run lint` was a placeholder that always passed. Synced `package.json` to the plugin version so the CI version-sync check passes.

## [0.7.0] - 2026-07-10

### Design review fixes (responsiveness, navigation, spacing, accessibility)

Actioned a principal-designer UX/accessibility review of the Court Side site.

#### Navigation

- **Mobile & tablet navigation restored.** Below 900px the primary nav was previously hidden with no replacement, leaving those users unable to reach any page. Added a no-JS `<details>` disclosure (hamburger → drawer) carrying every nav link plus Log in; the persistent Join CTA stays in the bar on tablet and moves into the drawer on phones so the header always fits.

#### Responsiveness

- **Eliminated horizontal scroll on mobile** across all pages: hero and section/band titles now wrap long words (`overflow-wrap`), the hero highlight is width-capped, the accent band pads down on small screens, and every auto-fit grid uses `minmax(min(…,100%),1fr)` so a track can never force overflow. Verified clean from 320px to desktop.
- Hero type scaled down slightly so the primary CTA isn't pushed off-screen; buttons no longer wrap mid-label.

#### Spacing

- Introduced a shared spacing scale (`--space-*`) and a **flow-based rhythm**: one consistent gap between every top-level block (`--flow-lg`, 88px desktop / 52px mobile) and one tight gap inside the hero utility cluster and between a band and its cards (`--flow-sm`, 24px / 20px). Spacing now lives in a single margin rule instead of ad-hoc per-section paddings, so adjacent paddings can never double up and every section gap is identical. Background-bearing sections keep their own internal padding. Fixed the brand/nav gap.

#### Placeholder images & gradients

- **Every empty image slot now renders an intentional gradient placeholder** with a centred photo icon (hero media, sports cards, the clubhouse image band, news thumbnails and the contact map); people/committee avatars use a gradient behind their initials. Image bands get a dark gradient placeholder so their white heading stays legible. Placeholders are engine-derived, so they re-theme with the club accent, with subtle per-position variation so a grid doesn't read as identical.
- **Wider gradient usage** for depth: the membership accent band (radial highlight), the featured stat card, the dark info strip and ink CTA band, and a soft ambient wash behind the hero — all derived from the accent tokens.

#### Hierarchy & clarity

- The emphasised stat is now chosen by data (a `featured` flag) instead of DOM position.
- Home quick tiles reframed as task-oriented shortcuts (Join the club / Take a tour / See fixtures / Get in touch) rather than a second, mismatched copy of the nav.
- Photo-less people avatars render initials, and empty image slots show a subtle patterned placeholder instead of a flat-grey block; committee/directory names reserve two lines so a wrapped name doesn't break row alignment.

#### Accessibility

- Added a skip link and a `<main id="ch-main">` landmark to every page.
- One branded, guaranteed-contrast `:focus-visible` indicator for all interactive elements (previously only form inputs had a focus style).
- The news ticker gained an accessible, no-JS pause control (WCAG 2.2.2).
- Active nav link now signals the current page with full-contrast ink text and an accent underline (colour alone previously measured ~4.3:1).
- Footer links, legal links and social icons raised to ≥44px touch targets.

## [0.6.1] - 2026-07-10

### Accessibility & fixes

- **List semantics across all grids.** Every grid of repeated items — quick tiles, stats, sports cards, membership tiers (and their feature lists), fixtures/results/events, news, info columns, sponsors, benefits, committee/directory people, the history timeline, the included/excluded/policies split and the how-to-join steps — now carries `role="list"` / `role="listitem"`. This restores list semantics for screen readers, including on WebKit where `list-style:none` silently strips the implicit roles from `<ul>` grids.
- `list_split` column headers (previously the hard-coded English "Included / Not included / Good to know") are now passed in as data, so a non-English club can relabel them.
- The Contact info card's `tel:` link now strips whitespace from the number so it dials correctly (`tel:01628000000`); the visible number keeps its spacing.

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
