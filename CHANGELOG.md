# Changelog

All notable changes to this project are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 0.24.2

- **Fix (Court Side):** the small label above the home hero headline ("Est. 1974 · Marlow, UK") was invisible — its text was the same colour as the pill behind it. It now reads correctly, and the label's text automatically switches between dark and light so it stays readable whatever colour your club picks.

- **Change:** the top banner and news ticker now carry your club's colour instead of near-black, and the home hero does too whenever no hero photo is set. Each is filled with the Base Look's own ink pulled 30% toward your accent, so the site reads as tinted with your brand while keeping the same weight and readability. Every club colour is handled automatically — no setting to configure, and contrast is guaranteed by the colour engine. Applies to Court Side and Members' House; Floodlight is unchanged, as its blocks were never near-black. Hero photographs are unaffected: the scrim over them stays neutral so your images keep their true colours.

## 0.24.0

- **Site Content editor** — a new "Site Content" screen under Clubhouse in the admin menu lets you edit the page copy that used to be hardcoded: heroes, the news ticker, stats, bands, news, info strips, FAQ, steps, tiers, values, the facilities image band and the call-to-action bands. Anything you haven't edited keeps rendering exactly as it does today. Sports, teams, events, sponsors, committee and directory content still live on their own dedicated screens, which the new screen links out to. It picks up your chosen Base Look automatically, and each section's Shown/Hidden switch is the same one used on the Setup screen.
- **Fix:** every field in the Site Content catalogue now genuinely changes the rendered site. Previously ~26 fields across About (history body/image, the Facilities loop), Contact (form intro/submissions email/success message), Log in (support email) and the Sports/Teams/Events/Calendar hero (CTAs, image) were editable but silently ignored by the renderer. Those sections have been reshaped to match what actually renders, or wired up where the renderer already supported the field — an owner's edit now always shows up on the site.

## 0.23.0

- Full-bleed Home hero: the home page hero is now a full-bleed background image (with a graceful toned fallback panel when no photo is set) with the heading, intro and calls-to-action overlaid, the quick-links folded in as an icon-card row, and the news ticker directly beneath. Applied across all three Base Looks (Court Side, Floodlight, Members' House), each in its own style. Existing copy is unchanged, and the shared hero used by the other pages is untouched.

## 0.22.1

- Setup screen full-bleed CSS tidy: the container now uses `height: 100vh` with `max-width: 100%` and `border-radius: 0`, the `.wrap.clubhouse-wrap` padding is dropped, and panels lose their bottom padding — all still scoped to the Setup page body class so no other admin screen is affected.

## 0.22.0

- Redesigned the Clubhouse Setup screen: a bespoke, tabbed layout (Base Look & Branding · Visibility) that inherits the selected Base Look's fonts, colours, radii and accent, and re-skins live as you pick a look. The screen fills the admin content area edge-to-edge with a full-width Save footer.
- Added Favicon and LinkedIn as brand inputs. The favicon renders in the browser tab; LinkedIn joins Facebook and Instagram in the site's social block and footer.
- Setup progress counts the main setup sections (base look, accent, club name, logo & favicon, social, visibility) — nothing is compulsory and Save is always available so you can return and finish later. Visibility counts as done once you save (keeping the defaults is a valid choice).
- Saving now shows a confirmation notice.
- Demo mode is an admin-only control: its tab shows only to administrators and is never counted in the setup progress.

## [0.21.0] - 2026-07-13

### Phase 5 — Font self-hosting

#### Changed

- **Fonts are now self-hosted.** Every Base Look's typefaces (Syne, Inter, Fraunces, Mulish, Bricolage Grotesque, Hanken Grotesk) are served from woff2 files bundled in the plugin instead of the Google Fonts CDN. No visible change — same families, weights, and `font-display: swap` — but the front end now makes **zero third-party font requests** (no `fonts.googleapis.com`/`fonts.gstatic.com`), which is faster and more private. Each family's SIL OFL 1.1 licence is bundled under `assets/fonts/licenses/`.

## [0.20.1] - 2026-07-13

### Docs

- Added a **manual WP smoke-test checklist** (`docs/manual-smoke-test.md`) covering the live-install-only behaviours across Admin Phases 1–4 and the site-wide Demo mode runtime — the surface that cannot be reached by unit tests (real WordPress, multiple browsers, capability/nonce probing).

## [0.20.0] - 2026-07-13

### Changed

- **Demo mode is now site-wide.** Instead of a private per-admin preview, an administrator turns Demo mode on or off for the whole site — from the ⚡ admin-bar toggle (which now works in the front end *and* in wp-admin) or from a new control on **Clubhouse → Setup**. While it is on, every visitor sees the floating look switcher and can click through the Base Looks themselves (their own choice, held in their browser); the club's saved look is never changed. Only administrators can turn it on or off.

## [0.19.0] — Admin Phase 4: Clubhouse Owner role, admin lockdown & Dashboard takeover

### Added
- A new **Clubhouse Owner** role: a curated back-end for non-technical club owners. Login lands directly on the Setup screen (the dashboard is replaced with it), and the admin menu is limited to Setup, Content, Media, Posts, Comments, Users, and Profile — everything else (Appearance, Plugins, Tools, Settings, Pages) is hidden and capability-denied.
- The six collection post types are now grouped under a single **Content** menu.
- Owners can view the Users list but cannot create, edit, or delete users; they can edit the collections and the blog, upload media, and moderate comments.

### Changed
- The Clubhouse Setup screen is now gated by a dedicated `manage_clubhouse` capability (granted to owners and administrators) instead of `manage_options`.

### Notes
- The role is created on activation and kept on deactivate; it is removed only when the plugin is uninstalled.

## [0.18.0] — Admin Phase 3: collection editing, projection robustness, header logo/nav

### Added
- Native custom meta-boxes for all six collection CPTs (fixtures, teams, people, sponsors, sports, events) with typed inputs (date/time/select/email/url) and a `wp.media` image picker, driven by a single pure `Collection_Meta` field definition; values sanitised server-side and escaped on output.
- Admin list columns for the high-signal fields of each collection (e.g. a fixture's date, teams, and result).
- Front-end logo rendering in the site header (attachment resolved to a URL in the WordPress layer; club-name text kept beside it) and omission of hidden pages from the header nav and footer link lists.

### Fixed
- `Fixture_Projection` now groups the calendar by year-and-month (`January 2026`) so fixtures in different years no longer merge, and guards empty/malformed match dates (which previously resolved to "now") — undated fixtures show as "Date TBC" on the calendar.
- The Clubhouse Setup admin menu is now registered on init (it was defined in v0.16.0 but never wired, so it never appeared on a real install).

## [0.17.0] - 2026-07-13

### Demo mode

An admin-only way to demo the Base Looks live on the real site, so a prospective club owner can pick one. (Superseded by the site-wide model in 0.19.0.)

#### Added

- **Demo mode toggle.** A **⚡ Demo mode** button in the front-end WordPress admin bar (for users who can manage the site). Turning it on reveals a floating switcher listing every installed Base Look; click a look and the whole live site re-skins to it on the spot.
- **Ephemeral and private.** Switching looks in Demo mode is per-admin and temporary (held in a browser cookie) — it never changes the club's saved look, accent, or content, and public visitors always see the saved look. "Exit demo" (or toggling it off) returns to the saved look.

#### Fixed

- **Front-end navigation now uses real permalinks.** Internal links were emitting the preview server's `?page=<slug>` form, which WordPress does not route — so every nav click landed on Home. Links now resolve to proper permalinks (e.g. `/about/`), falling back to a query-var URL when permalinks are set to Plain.

## [0.16.1] - 2026-07-13

### Changed

- **Plugin title** on the WordPress Plugins page is now **Blueworx Labs | Clubhouse** (was "Blueworx Labs Clubhouse").

## [0.16.0] - 2026-07-13

### Clubhouse Setup screen

The first owner-facing admin surface — a standard WordPress admin page under a new **Clubhouse** menu.

#### Added

- **Base Look picker.** Choose Court Side, Members' House, or Floodlight; the choice becomes the active look.
- **Branding controls.** Accent colour (rejected on save if it is too low-contrast for the chosen look — the check is look-aware: text-bearing looks need higher contrast than the glow-only dark look), club name, logo (via the media library), and Facebook / Instagram URLs.
- **Visibility controls.** Show or hide any page and any of its sections. A hidden sub-page now returns a 404 on the front end (a hidden home page falls back to WordPress's front-page setting).
- **Setup progress bar.** Tracks the six branding/look configuration items (page content is not counted).
- **Look-aware accent legibility.** Base Looks now declare whether they paint text on the accent fill, so accent acceptance matches how each look actually uses the colour.

#### Notes

- The screen is a standard admin page for now (capability `manage_options`); the locked-down Clubhouse Owner role and Dashboard takeover arrive in a later phase.
- The logo is stored here; rendering it in the site header (and omitting hidden pages from the nav) lands in the next phase.

## [0.15.0] - 2026-07-13

### Admin foundation & engine hardening

Groundwork for the admin experience — no user-facing UI yet.

#### Changed

- **All three Base Looks are now registered at runtime.** `Frontend` previously registered only Court Side, so the stored active look could never resolve to Members' House or Floodlight on a live site. A shared `Frontend::registry()` now registers all three (Court Side first as the fallback).
- **Theme-cache signature includes look tokens + plugin version.** The composed `:root` cache was keyed only on look slug + accent, so an upgrade that changed a look's shell tokens served stale CSS. The signature now also hashes the look's token contents and the plugin version.
- **`Frontend::context()` returns a named `Clubhouse_Context` DTO** instead of a positional array, and now carries the Base Look registry for the upcoming setup screen.

#### Added

- **`Color_Engine::accent_is_legible()`** — validates that a club accent clears WCAG AA both as ink on the accent fill and as accent-text on the shell. The admin setup screen will use it to reject low-contrast accents.

## [0.14.0] - 2026-07-12

### Social block

A global, skin-agnostic "Follow us" section linking out to the club's Facebook and Instagram — not a live/embedded feed.

#### New

- **`Branding` social URLs** — `facebook`/`instagram` fields alongside accent, club name and logo, so the club's social links are a single global source (admin editing arrives with the admin flow).
- **`Sections::social()`** — a `ch-social` renderer with self-hosted inline brand SVGs for Facebook and Instagram (`fill`/`stroke="currentColor"`, no icon font, no hex), each link carrying a descriptive `aria-label` and list semantics.
- **Placed on Home and Contact** — after Sponsors on Home, after the Directory on Contact, both behind their own `social` visibility toggle.
- **Court Side styling** — a heading + lede band with pill-shaped accent-wash links, hover lift respecting `prefers-reduced-motion`, and a responsive stack on narrow screens.

## [0.13.0] - 2026-07-12

### Collections / custom post types

Real data behind the unchanged renderers.

#### New

- **Six custom post types** — Sports, Teams, Fixtures, Events, Sponsors, People — registered with their meta fields. Fixtures is a single type carrying an outcome (empty = upcoming, `W`/`L`/`D` = result), feeding both the Home activity tabs and the Calendar.
- **`Collections` repository** — a pure `Demo_Collections` (preview + tests) and a thin `WP_Collections` (reads the CPT posts) behind one interface; `Page_Renderer` projects the canonical data to each renderer's exact shape, so no renderer changed.
- **Demo content is seeded** on activation from a single `Demo_Content` source, so a fresh install still shows a fully populated site. Per-field editing UI arrives with the admin flow.

#### Notes

- Home and Calendar fixtures are now derived from one consistent set (the previously-hardcoded demo diverged between the two views).
- The `sponsors` renderer takes only names, so a sponsor's URL is stored but unused for now; committee entries render without email (matching the demo), the directory shows emails.

## [0.12.0] - 2026-07-11

### WordPress integration

The plugin now serves its eight-page Court Side site inside WordPress — the first WordPress-runnable release.

#### New

- **Rewrite-rule routing.** The plugin owns the frontend: Home renders at the site root and each other page at `/{slug}` via rewrite rules, with the active theme left neutral. Flushed on activation/deactivation.
- **Canvas template + proper enqueue.** A `template_include` canvas template fires `wp_head()`/`wp_footer()`; the look stylesheet and fonts are enqueued and the derived `:root` design tokens are injected inline via `wp_add_inline_style`.
- **Cached `:root` tokens.** The composed token string is cached in an autoloaded option keyed by look + accent, so the colour math runs only when they change (`invalidate()` is exposed for the admin flow).
- **`Page_Map`** — a single slug→renderer dispatch used by both WordPress and the DB-free preview, so they render identical bodies. The scroll-reveal script is extracted to `assets/js/reveal.js` (enqueued in WP, inlined in the preview).

#### Notes

- Renderers still use hardcoded demo data; the Collections/CPT plan swaps the data source behind them.
- This branch also carries the CI-preview wiring (from PR #6) so its guardrails check passes ahead of that PR propagating through the stack.

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
