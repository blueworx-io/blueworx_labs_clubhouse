# ClubHouse — Concrete Sections & Pages (step 4 detail)

**Date:** 2026-07-10
**Status:** Approved (design direction) — feeds `writing-plans`
**Builds on:**
- `2026-07-10-base-look-theming-and-site-design.md` (§5 page inventory, §10 sequencing) — this is the
  detailed design for **step 4, "Concrete sections & pages."**
- Delivered engine: `Registry`, `Storage`/`Options_Storage`, `Content_Store`, `Visibility`
  (`2026-07-09`), Base Look framework + colour engine (`0.3.0`), Court Side pack + `Sections`
  renderers + `Page_Renderer` + preview (`0.4.0`).

**Source brief:** the client's Claude Design export at
`Downloads/Sports club template site/` (8 page `*.dc.html` files + `SiteHeader`/`SiteFooter` +
`_ds/` tokens). Per the client, the export defines **what pages and sections to build and their
content** — it is the *structural brief*, not the skin. The site renders in the shipped **Court
Side** look (Syne + Inter, warm near-white, pill/rounded, single derived accent), unchanged.

---

## 1. Purpose

Turn the current Home *shell* (header · hero · stat strip · footer) into the **full 8-page site** —
Home, About, Sports, Teams, Membership, Events, Calendar, Contact — plus the upgraded shared
header/footer, all as skin-agnostic section renderers styled by Court Side, viewable live in the
localhost preview. Data is hardcoded ClubHouse demo content this round; the later Collections plan
wires WordPress custom post types behind the same renderers.

## 2. Architecture (unchanged principles, extended surface)

- **Skin-agnostic renderers.** Every section is a static method returning semantic HTML with only
  `ch-*` classes — **no colour, font, radius, or look-slug literals**, all interpolated text
  escaped at the render boundary (extends `Blueworx_Clubhouse_Sections`). A re-skin (A/B later)
  must restyle the identical DOM.
- **Court Side styling.** `assets/looks/court-side.css` grows to style every new `ch-*` class,
  consuming engine custom properties (`--color-accent*`, `--color-ink`, `--radius-*`, `--font-*`)
  only. The single derived accent still re-themes the whole site by swapping one colour.
- **Page composition.** `Blueworx_Clubhouse_Page_Renderer` gains one method per page
  (`about()`, `sports()`, `teams()`, `membership()`, `events()`, `calendar()`, `contact()` beside
  `home()`). Each composes sections in order, honouring `Visibility` per section, and feeds
  hardcoded demo data (a `page → sections → demo content` shape mirroring `home()`).
- **Preview routing.** `preview/index.php` gains `?page=<slug>` routing (default `home`) so the nav
  is clickable and every page is viewable live. This same page→renderer map is what the WordPress
  `template_include` wrapper (later plan) will reuse, and it becomes the CI staging URL target that
  currently blocks merges to `main`.

## 3. Section renderer catalogue

Distinct renderers to build (⟳ = reused across ≥2 pages). Each lists its demo-data shape and whether
the data is **singular** (one-off option copy) or a **collection** (repeating list → a future CPT).

**Shell**
- `header` ⟳ *(upgrade)* — promo banner (dismissable, singular), brand mark, 8-item nav w/ active
  state (collection `{key,label,href}`), Login + Join CTAs.
- `footer` ⟳ *(upgrade)* — 4 columns: brand + tagline + socials (collection `{name,svg}`); "Club"
  links; "Get involved" links; newsletter form (presentational); oversized watermark wordmark;
  legal bar.

**Heroes**
- `hero_split` ⟳ — eyebrow + hairline rule, H1 (lead + accent highlight), lede, 0–2 CTAs, side/bleed
  image slot. (Home, About, Sports, Membership, Contact.)
- `hero_filter` ⟳ — eyebrow, H1, lede, filter-pill bar (collection of taxonomy labels). (Teams,
  Events, Calendar.)

**Reused content blocks**
- `stat_strip` ⟳ *(built)* — value/label pairs.
- `quick_tiles` — icon-light quick-access tiles (collection `{label,href}`). (Home.)
- `ticker` — CSS-only news marquee, `prefers-reduced-motion` safe (collection of strings). (Home.)
- `card_grid` ⟳ — image + chip + title + description + stat pairs card grid; drives Home sports
  overview, Sports directory, Teams (different fields, same layout). Collection.
- `image_band` ⟳ — full-bleed image + gradient overlay + heading + CTA. (Home clubhouse, About
  facilities.) Singular.
- `tier_grid` ⟳ — membership/pricing cards (collection `{eyebrow,name,price,period,features[],
  recommended,cta}`, "recommended" highlighted). (Home preview, Membership.)
- `benefit_grid` ⟳ — accent-dot benefit cards (collection `{title,description}`). (About values,
  Membership why-join.)
- `people_grid` ⟳ — avatar/portrait cards (collection `{role,name,email?}`). (About committee,
  Contact directory.)
- `cta_band` ⟳ — dark ink island: heading + lede + one pill CTA. (All 7 content pages.) Singular.

**Page-specific blocks**
- `activity_tabs` — tabbed Fixtures/Results/Events; all panels server-rendered, JS toggles. (Home.)
- `fixture_rows` ⟳ / `result_rows` ⟳ — date + matchup + venue + W/D/L text badge (collection). (Home
  tab, Calendar.)
- `event_grid` + `event_archive` — upcoming cards + past list rows (collection `{tag,date,title,
  detail}`). (Events.)
- `calendar_months` — month-grouped fixtures/results (collection grouped by month; outcome drives
  accent/status text). (Calendar.)
- `timeline` — milestone rows (collection `{year,title,desc}`). (About.)
- `list_split` — included / not-included / policies columns (mostly singular lists). (Membership.)
- `step_grid` — numbered "how to join" steps (collection `{number,title,description}`). (Membership.)
- `faq` — accordion (collection `{question,answer,open?}`); details/summary, works with no JS.
  (Membership.)
- `sponsors` — logo/name tile grid (collection of strings). (Home.)
- `contact_form` — name/email/enquiry-type(select)/message/submit + info card + map slot.
  Presentational (no submission wiring). (Contact.)
- `info_strip` — location / hours / contact / find-us on a dark island (singular). (Home.)

## 4. Per-page composition (section order)

- **Home** — header · hero_split(+image) · quick_tiles · ticker · card_grid(sports) · image_band ·
  tier_grid(preview) · activity_tabs · card_grid(news/blog) · info_strip · sponsors · cta_band · footer.
- **About** — hero_split · timeline · benefit_grid(values) · people_grid(committee) · image_band
  (facilities) · cta_band.
- **Sports** — hero_filter · card_grid(sports, chip+stats) · cta_band.
- **Teams** — hero_filter · card_grid(teams, chip+stats) · cta_band.
- **Membership** — hero_split · benefit_grid(why-join) · tier_grid(pricing) · list_split
  (incl/excl/policies) · step_grid · faq · cta_band.
- **Events** — hero_filter · event_grid(upcoming) · event_archive(past) · cta_band.
- **Calendar** — hero_filter · calendar_months · cta_band.
- **Contact** — hero_split · contact_form(+info+map) · people_grid(directory) · footer.

## 5. Cross-cutting decisions

- **Icon-light.** No Material Symbols / icon font (Court Side is deliberately calm and
  self-contained; no second font CDN). Accent-dots for list bullets, "→" text arrows on CTAs, W/D/L
  text badges for results, minimal inline SVG for footer socials only.
- **Progressive-enhancement JS only.** Tabs, taxonomy filters, FAQ accordion, and mobile nav render
  **all** their content server-side and are fully usable with JavaScript disabled; small vanilla
  toggles enhance them (no framework, no hydration). FAQ uses native `<details>`.
- **Forms are presentational.** Contact and newsletter forms render styled markup with no
  submission handling — real handling is the integration/admin plan's concern (SureForms seam).
- **Demo data, hardcoded.** Page methods carry ClubHouse demo content inline (as `home()` does).
  The Collections plan later swaps the data source behind the unchanged renderers. Collections
  implied (for that plan, not built here): Sports/Sections, Teams, Fixtures-Results, Events,
  Committee/Contacts, Membership Tiers, Milestones, FAQ.
- **Brand = ClubHouse.** Export copy ("Marlow Community SC") is adapted to ClubHouse demo content.
- **Visibility everywhere.** Every section is wrapped in a `Visibility` check keyed `page/section`,
  so any block can be hidden per club.

## 6. Testing strategy

- **PHPUnit (existing harness, no WP runtime):** each renderer tested for (a) expected `ch-*`
  structure, (b) text escaping of interpolated content, (c) **no colour/font/radius/look literal**
  in output (the skin-agnostic guard), (d) collection renderers handle empty and N-item inputs.
  Page methods tested for section order and visibility honouring.
- **Playwright (CI guardrail, once staging URL wired):** render each page under Court Side; assert
  structure and that swapping accent re-themes without layout change; the A/B re-skin test (same DOM
  under two packs) remains the later packs' responsibility.

## 7. Build decomposition — three sequential plans

Each plan ends with the affected pages viewable in the localhost preview.

1. **Shell + Home (flagship).** Upgrade `header`/`footer`; add `?page=` preview routing; build all
   Home sections. Establishes the majority of reusable renderers (hero_split, quick_tiles, ticker,
   card_grid, image_band, tier_grid, activity_tabs, fixture/result rows, event card, info_strip,
   sponsors, cta_band).
2. **Static pages — About · Membership · Contact.** New: timeline, benefit_grid, people_grid,
   list_split, step_grid, faq, contact_form. Reuses hero_split, tier_grid, image_band, cta_band.
3. **Collection-ish pages — Sports · Teams · Events · Calendar.** New: hero_filter + filter bar,
   card_grid stat-card variant, event_grid/event_archive, calendar_months. Natural lead-in to the
   later Collections (CPT) plan.

Then downstream (unchanged from the parent spec): Collections/CPTs → admin setup flow → WP
render/enqueue + caching + `template_include` → font self-hosting; then A & B packs as re-skins.

## 8. Out of scope / deferred

- Real form submission, admin UI, CPT registration, WordPress `template_include` wrapper, caching,
  font self-hosting, A/B Base Look packs — all downstream plans.
- Second brand accent, multisite, page-builder editing — out of v1 (parent spec §2 non-goals).
- The `spl_autoload_register` decision (carried item) — manual `require_once` stays readable at the
  current file count; revisit if the section files make it unwieldy.
- **CI staging-URL blocker** stays open until the WP preview/render plan; the `?page=` preview
  routing here is the intended basis for resolving it.
