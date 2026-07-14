# Content editor — design

**Date:** 2026-07-14
**Branch:** `worktree-content-editor` (off `main`)
**Status:** approved design, pre-plan
**Mockup:** `Downloads/Sleek onboarding flow/Clubhouse Content.dc.html` (Claude Design export — carries the full data model in its `<script>`)

## Goal

Give a Clubhouse owner a single admin screen where they can edit the **singular page copy** that is currently hardcoded inside `Page_Renderer` (hero headlines, ledes, CTAs, ticker, stats, bands, news, info strips, FAQ, steps, tiers, values, etc.). Collections (sports/teams/events/sponsors/committee/fixtures) stay in their existing native CPT screens; branding stays in Clubhouse Setup. This closes the last gap in the owner experience: today an owner can pick a look, set branding, toggle visibility, and edit collections — but cannot change a single word of the page copy.

The `Blueworx_Clubhouse_Content_Store` class (page → section → field storage, one autoloaded option per page) was architected on day one for exactly this and has never been wired to anything. This feature wires it in as an **override layer** over the hardcoded defaults.

## Non-goals / out of scope

- **No bespoke CRUD over the six CPTs.** Sports, teams, events, sponsors, people (committee + directory) and fixtures remain edited via WordPress's native list/edit screens. The Content editor *links out* to them. (Honours the Phase-3 "native CPT editing" decision and keeps one source of truth.)
- **No new derivation engine.** Sections that are genuinely derived today (activity tabs, past events, calendar schedule) stay derived and are shown read-only with an explanatory note. We do **not** build blog-feed ingestion for News or a "Club details" store for the info strip (see Divergences).
- **No live front-end preview** inside the editor. Like Setup, the screen inherits the active Base Look for its own chrome, but does not render the site.
- **No drag-reorder of loop items** in v1. Items render in stored order; add appends, remove deletes. (Reorder is a clean follow-up.)

## Placement & chrome

- **New top-level admin menu: "Clubhouse Content"** (`dashicons-edit`), sibling to the existing "Clubhouse" (Setup) menu. Capability `manage_clubhouse` (owner + admin), reusing `Owner_Capabilities::SETUP_CAP`.
- Page slug `clubhouse-content`.
- **Full-bleed / full-height layout reused from Setup**, scoped to `body.toplevel_page_clubhouse-content` (mirrors the `body.toplevel_page_clubhouse-setup` scoping already in `admin-setup.css`). New stylesheet `assets/css/admin-content.css`; new script `assets/js/admin-content.js`.
- **Inherits the active Base Look live** — same token approach as the Setup redesign: the controller composes each look's `:root` tokens + `@font-face` and the pure screen emits the active look's tokens scoped to `.clubhouse-content`, so the editor chrome uses the club's fonts/shell/accent. (The mockup's JS `themes` map — court/members/floodlight — is the reference for which surfaces map to which token.)
- Cross-link button "Site setup →" jumping to `admin.php?page=clubhouse-setup`, matching the mockup.

## Screen structure (faithful to the mockup)

```
┌ Clubhouse · Site content ─────────────────────── [ Site setup → ] ┐
│ CLUBHOUSE CONTENT                                                  │
│ ── page tabs (draggable/scroll): Global About Membership … ────── │
├──────────────┬───────────────────────────────────────────────────┤
│ section nav  │  PANEL for selected section                        │
│  · Header    │   ┌ Global page · HERO      [ Shown ⬤ ]          │  │
│  · Hero  ●   │   │ (hidden-notice | auto-note | info-note)        │  │
│  · Quick..12 │   │ fields grid  /  link-out card  /  loop list    │  │
│  · Ticker    │   └────────────────────────────────────────────────┘ │
│  · Stats auto│                                                    │
├──────────────┴───────────────────────────────────────────────────┤
│ You have unsaved changes.                        [ Save changes ] │  (sticky footer)
└───────────────────────────────────────────────────────────────────┘
```

- **Page tabs (top):** one per site page — `Global`, `About`, `Membership`, `Contact`, `Log in`, `Sports`, `Teams`, `Events`, `Calendar`. "Global" = the Home page plus the shared shell (header/footer). Draggable horizontal scroll (progressive-enhancement; plain clicks work JS-off).
- **Section nav (left):** the selected page's sections as a pill list, each with a **meta badge**: `off` when hidden, a **count** for loop sections, `auto` for derived sections, blank otherwise.
- **Panel (right):** for the selected section — a header (page-name eyebrow + section title) with a **Shown/Hidden toggle**, then whichever body applies (see Section types).
- **Sticky footer:** unsaved-changes hint + Save button.

### JS-off behaviour (progressive enhancement, house rule)

The screen must be fully usable with JavaScript disabled. Server-side, the controller reads the selected page/section from query args (`?page=clubhouse-content&tab=about&sec=history`); tabs and section-nav items are `<a>` links carrying those args; the sticky footer Save is a real form submit. JS upgrades tab/section switching to no-reload and adds the drag-scroll. All loop "Add"/"Remove" that need JS degrade to: the form still submits every rendered field, and add/remove are also available as submit buttons (`name="clubhouse_content_add"` / `_remove`) handled server-side. (Matching how the Setup screen's visibility form works JS-off.)

## Section types

Every section in the catalogue is exactly one of four types:

1. **Fields** — a grid of typed inputs editing singular copy. Field types: `text`, `textarea`, `image` (wp.media picker, stores attachment ID like the logo), `toggle` (boolean), `url`. Stored in `Content_Store` under `page.section.field`.
2. **Loop** — a repeatable list ("Add {name}") for lists that are **not** CPTs. Each item is a set of the same field types. Stored as `Content_Store` `page.section` → `{ items: [ {field:value, …}, … ] }`. Add appends an empty item; remove deletes by index.
3. **Link-out** — for CPT-backed sections and cross-references. A card with explanatory text + a button. Two flavours:
   - **`cpt`** → links to a native CPT admin screen (e.g. `edit.php?post_type=clubhouse_team`).
   - **`section`** → internal jump to another editor tab/section (e.g. Home "Membership tiers" band → Membership → Tiers loop).
   A link-out section may *also* carry a small `fields` block (e.g. Home "Sports grid" has an editable Heading/Intro **and** a "Manage sports →" button).
4. **Auto** — read-only. An "Auto" badge + explanatory note + optional link to the source CPT. No editable content (the data is derived at render time).

A section may also carry a **note** (neutral info line, e.g. "Logo and club name come from Site setup → Branding").

## The content catalogue

Declarative, pure, single source of truth: `Blueworx_Clubhouse_Content_Catalogue::pages()`. Section **keys and page slugs match the visibility inventory exactly** (`Setup_Sections::MAP`) so the two screens stay in lockstep. `type` is one of `fields | loop | linkout | auto`. `store` is the `Content_Store` page it reads/writes (shell items live under `global`; Home-body items under `home`).

### Global  (tab `global`)
| Section (key) | Type | Store | Fields / target |
|---|---|---|---|
| Header (`header`) | fields + note | `global.header` | Menu CTA label, Menu CTA link. Note: logo/name from Setup. |
| Hero (`hero`) | fields | `home.hero` | Eyebrow, Heading, Highlighted phrase, Lede, Primary CTA label, Primary CTA link, Secondary CTA label, Secondary CTA link, Background image |
| Quick tiles (`quick_tiles`) | loop | `home.quick_tiles` | item: Label, Link |
| Ticker (`ticker`) | loop **‡** | `home.ticker` | item: Message |
| Stats (`stats`) | loop **‡** | `home.stats` | item: Value, Label, Featured(toggle) |
| Sports grid (`sports`) | fields + linkout(cpt) | `home.sports` | fields: Heading, Intro. → Manage sports (`clubhouse_sport`) |
| Clubhouse band (`clubhouse`) | fields | `home.clubhouse` | Eyebrow, Heading, Image, CTA label, CTA link |
| Membership tiers (`membership`) | fields + linkout(section) | `home.membership` | fields: Eyebrow, Heading, Lede, CTA label, CTA link. → Edit tiers (Membership→Tiers) |
| Activity tabs (`activity`) | auto | — | derived from fixtures/events. → Manage events |
| News (`news`) | fields + loop **‡** | `home.news` | fields: Eyebrow, Heading. loop Article: Tag, Date, Title, Image |
| Info strip (`info`) | loop **‡** | `home.info` | item: Label, Lines(textarea), Link label, Link href |
| Sponsors (`sponsors`) | linkout(cpt) | — | → Manage sponsors (`clubhouse_sponsor`) |
| Social (`social`) | fields + note | `home.social` | fields: Heading, Lede. Note: profile links from Setup. |
| Footer (`footer`) | fields + note | `global.footer` | fields: About blurb. Note: contact/socials from Setup. |

### About (`about`)
| Hero (`hero`) | fields | `about.hero` | hero fields (as above) |
| History (`history`) | fields | `about.history` | Heading, Body, Image |
| Values (`values`) | loop | `about.values` | item: Title, Description |
| Committee (`committee`) | linkout(cpt) | — | → Manage people (`clubhouse_person`) |
| Facilities (`facilities`) | loop | `about.facilities` | item: Name, Description, Image |
| Call to action (`cta`) | fields | `about.cta` | Heading, Body, Button label, Button link |

### Membership (`membership`)
| Hero (`hero`) | fields | `membership.hero` | hero fields |
| Why join (`why`) | fields + loop | `membership.why` | fields: Heading, Intro. loop Benefit: Title, Description |
| Tiers (`tiers`) | loop | `membership.tiers` | item: Name, Price, Period, Features(textarea, one/line), Most popular(toggle), CTA label |
| Included / excluded (`detail`) | loop | `membership.detail` | item: Text, Included(toggle) |
| How to join (`steps`) | loop | `membership.steps` | item: Title, Description |
| FAQ (`faq`) | loop | `membership.faq` | item: Question, Answer |
| Call to action (`cta`) | fields | `membership.cta` | cta fields |

### Contact (`contact`)
| Hero (`hero`) | fields | `contact.hero` | hero fields |
| Contact form (`form`) | fields | `contact.form` | Intro, Submissions email, Success message |
| Directory (`directory`) | linkout(cpt) | — | → Manage people (`clubhouse_person`) |
| Social (`social`) | fields + note | `contact.social` | fields: Heading. Note: profile links from Setup. |

### Log in (`login`)
| Login form (`form`) | fields | `login.form` | Heading, Helper text, Support email |

### Sports (`sports`)
| Hero (`hero`) | fields | `sports.hero` | hero fields |
| Sports directory (`directory`) | linkout(cpt) | — | → Manage sports (`clubhouse_sport`) |
| Call to action (`cta`) | fields | `sports.cta` | cta fields |

### Teams (`teams`)
| Hero (`hero`) | fields | `teams.hero` | hero fields |
| Teams directory (`directory`) | linkout(cpt) | — | → Manage teams (`clubhouse_team`) |
| Call to action (`cta`) | fields | `teams.cta` | cta fields |

### Events (`events`)
| Hero (`hero`) | fields | `events.hero` | hero fields |
| Upcoming events (`upcoming`) | linkout(cpt) | — | → Manage events (`clubhouse_event`) |
| Past events (`past`) | auto | — | derived. → Manage events |
| Call to action (`cta`) | fields | `events.cta` | cta fields |

### Calendar (`calendar`)
| Hero (`hero`) | fields | `calendar.hero` | hero fields |
| Schedule (`schedule`) | fields + auto | `calendar.schedule` | fields: Heading, Intro. auto note: built from events/fixtures |
| Call to action (`cta`) | fields | `calendar.cta` | cta fields |

**‡ Divergences from the mockup** (approved): the mockup marks Ticker, Stats, News and Info strip as "auto" (pulled from records / a blog / a Club-details store). None of that derivation exists, and an owner clearly wants to edit these words, so they are **editable loops** here. Genuinely-derived sections (Activity tabs, Past events, Calendar schedule) stay `auto`.

## Wiring content into the front end (the override layer)

Each `Page_Renderer` page method gains a **nullable** `?Blueworx_Clubhouse_Content_Store $content = null` parameter. A small pure private helper resolves each value:

```php
// null store OR missing key → the hardcoded default; keeps preview + existing render byte-identical.
private static function cget( ?Content_Store $c, string $page, string $sec, string $field, mixed $default ): mixed
private static function citems( ?Content_Store $c, string $page, string $sec, array $default_items ): array
```

Every hardcoded literal in a renderer becomes `self::cget($content, 'home', 'hero', 'eyebrow', 'Est. 1974 · Marlow, UK')`; every hardcoded array (tiers, faq, news cards, …) becomes `self::citems(...)` with today's array as the default. **Defaults are exactly today's copy**, so:

- With no saved content (fresh install, or `$content === null`), output is byte-for-byte identical to current — every existing render test stays green without modification, and the DB-free preview keeps rendering the demo copy.
- Once an owner saves, their values override field-by-field; unset fields still fall back to the default (partial edits are safe).

**Threading:** the store is passed exactly like `Collections` was in the Collections/CPT plan — `Page_Map::render()` gains the store and passes it to the page method; `Frontend::context()` (the `Clubhouse_Context` DTO) gains a `content` slot built over `Options_Storage`; the preview passes an `Options_Storage`-free store (or `null`). No section renderer (`Sections::*`) changes — they still receive plain arrays; only `Page_Renderer` (which composes those arrays) reads the store.

CPT-backed sections (sponsors, committee, directory, sports/teams/events directories, activity, past, calendar schedule) are **not** in the override layer — their data still comes from `Collections`/projections. The editor only *links* to them.

## Visibility integration

The panel's Shown/Hidden toggle reads and writes the **same `Blueworx_Clubhouse_Visibility` store** the Setup screen uses (`page`+`page.section` keys). No new visibility concept. Editing content and toggling a section now live together, and the Setup → Visibility tab keeps working — both are views over one store. The section-nav `off` badge reflects `Visibility::is_section_visible`.

## Architecture (pure / glue split, mirroring Setup)

- `Blueworx_Clubhouse_Content_Catalogue` (`includes/admin/`) — **pure**. `pages()` returns the declarative catalogue above (page → sections → type + field defs + link/auto metadata). Field defs reuse a tiny shape like the mockup's helpers (`text/textarea/image/toggle/url`). Pure, WP-free, fully unit-tested.
- `Blueworx_Clubhouse_Content_Screen` (`includes/admin/`) — **pure**. `render(array $model): string` emits the full escaped HTML (tabs, nav, panel, footer, scoped look tokens + `@font-face`). All output escaped; stylesheet + emitted tokens hygiene-tested (no literal accents/font-names — same guard as Setup). Depends only on the model array.
- `Blueworx_Clubhouse_Content_Controller` (`includes/admin/`) — **glue** (WP-coupled). Menu registration, `wp_enqueue_media` + asset enqueue (gated to the content page hook, like Setup), building the model from stores, and `handle_save(array $post, Storage): array` (nonce + cap gated, sanitise every field via the catalogue's declared type, persist through `Content_Store` + `Visibility`, then `Theme_Cache` is untouched — content doesn't affect `:root`). `handle_save` takes a `Storage` so it is unit-testable WP-free (same pattern as `Setup_Controller::handle_save`).
- `Blueworx_Clubhouse_Content_Store` — **reused as-is** for reads/writes; loop items stored under the `items` key of a section.

Wiring: `Content_Controller::register()` is called once from `blueworx_labs_clubhouse_init()` alongside `Setup_Controller::register()` / `Collection_Meta_Boxes::register()` / `Owner_Role::register()` — **not** from a bare `is_admin()` block (the memory records that pattern being silently dropped in a merge). The owner menu allow-list (`Owner_Capabilities::menu_allowlist()`) gains `clubhouse-content`.

## Sanitisation

Per declared field type, in `Content_Controller::handle_save`, driven by the catalogue (never trust the POST shape):
- `text` → `sanitize_text_field`
- `textarea` → `sanitize_textarea_field` (preserves newlines for "one per line" features)
- `url` → `esc_url_raw`
- `image` → `absint` (attachment ID; empty string clears)
- `toggle` → cast presence to bool
- Loop items: iterate the catalogue's field defs for that section, sanitise each; ignore any POST keys not in the catalogue. Add/remove operate on the sanitised array.

Rendered values are escaped again at output in `Page_Renderer`/`Sections` (already the case). Storage stays raw-sanitised text; escaping is a render concern.

## Testing

TDD, per the house flow. Pure-first so most of it needs no WP runtime:
- **`Content_Catalogue`** — every page/section key matches `Setup_Sections::MAP` exactly (a lockstep test); every section has a valid `type`; link-out targets resolve to real CPT slugs / real sections; no duplicate keys.
- **`Content_Store` override precedence** — set a field → renderer emits it; unset → default; partial section edit keeps other defaults; loop empty → default array, non-empty → stored items.
- **`Page_Renderer` fallback** — `$content === null` (and empty store) ⇒ output identical to the pre-change golden for each page (guards byte-identical preview/back-compat).
- **`Content_Screen` render** — structure (tabs count, nav per page, panel per type), escaping, skin-agnostic (no literal hex/font-name in the emitted CSS), JS-off links present, Save form present.
- **`Content_Controller::handle_save`** — over `Fake_Storage`: each type sanitised; add/remove loop item; unknown POST keys ignored; nonce/cap paths (cap check unit-covered where possible; nonce is a WP concern smoke-checked).
- **Playwright** — the admin screen needs a DB, so it can't run in the DB-free preview; covered by the **manual WP smoke** below (as with every admin surface). No new Playwright preview test is required, but existing preview specs must stay green (proves the override layer left the front end byte-identical).

## Manual WP smoke owed (runtime-only)

1. Activate → **Clubhouse Content** menu appears (owner + admin); regression-guards the init wiring.
2. Screen is skinned to the saved Base Look incl. fonts on first paint; switching look in Setup re-skins it.
3. Edit Global → Hero heading, Save → front-end Home hero shows the new heading; unedited fields unchanged.
4. Add two FAQ items on Membership → they render on `/membership/`; remove one → gone.
5. Toggle a section Hidden here → it disappears from the front end **and** shows unticked in Setup → Visibility (shared store).
6. A link-out (e.g. Teams directory) opens the native `clubhouse_team` list screen.
7. Image field opens `wp.media`, stores the attachment; the image renders on the front end.
8. Everything reachable/submittable **JS-off** (tabs via links, Save via submit, add/remove via submit).
9. Content editor styles do not bleed into the rest of wp-admin (scoped to the page body class).

## Deferred / follow-ups

- Drag-reorder of loop items.
- Optional per-tile icons for Quick tiles / Benefits (the renderer has no icon slot today; adding one is a Sections change, out of scope here).
- A real "Club details" store (address/hours/phone) + blog-feed ingestion for News — if ever built, Info strip and News would flip from editable loops back to `auto`.
- Owner-dashboard mount: like Setup's `screen_html()`, a future step could surface the content editor on the owner Dashboard; not in v1.
