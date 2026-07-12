# Collections / CPTs — Design

**Date:** 2026-07-12
**Branch:** `collections-cpts` (off `wp-integration` / PR #7)
**Status:** Approved (design), pre-implementation

## Context

Every page renders from **hardcoded PHP demo arrays** built inline in `Page_Renderer` methods and passed to the skin-agnostic `Sections` renderers. This plan swaps that data source for six WordPress custom post types **without changing any renderer**, and seeds the ClubHouse demo content as real posts so a fresh install still shows a fully populated site. The custom-field **editing UI** (meta boxes) is deliberately out of scope — it belongs to the admin-flow plan (task 4); this plan delivers the data model, a repository, seeding, and the renderer data-source swap.

The design mirrors the existing `Storage` / `Options_Storage` / `Fake_Storage` split that keeps the engine testable without a WordPress runtime: a `Collections` interface with a pure DB-free implementation (preview + tests) and a thin WP implementation (production).

## Goals

- Six CPTs registered: `clubhouse_sport`, `clubhouse_team`, `clubhouse_fixture`, `clubhouse_event`, `clubhouse_sponsor`, `clubhouse_person`.
- A `Collections` repository returns **canonical** per-collection arrays; `Page_Renderer` projects them to the exact shapes the renderers already consume. **No `Sections` renderer changes.**
- On activation, each empty collection is seeded from a single `Demo_Content` source, so the demo site renders with no manual data entry.
- Pure core stays WP-free and unit-tested; WP-coupled code stays thin and is covered by the WP-function shim + pure mappers.
- No new runtime or dev dependency.

## Non-goals (owned by later plans)

- **Custom-field meta boxes / editing UI** — the admin-flow plan. CPTs register with `show_ui => true` so seeded posts are visible/manageable at the post level, but per-field editing comes later.
- **Taxonomies / filtering logic** — the filter pills stay presentational (as decided); no real taxonomy-driven filtering this round.
- **Sponsor logos/links, richer people profiles** — the renderers have no slots for them; stored-but-unused meta is fine, no renderer changes.

## The six collections (CPT + canonical meta)

Canonical arrays carry every field any projection needs; projection to renderer shapes happens in `Page_Renderer`.

### `clubhouse_sport`
- `post_title` = sport name. Meta: `label` (short tag/chip, e.g. "Sat"), `subtitle` (short line), `description` (long), `stat1_value`, `stat1_label`, `stat2_value`, `stat2_label`, `image` (URL). `menu_order` for ordering.
- Projections: **Home `card_grid`** (first 4) → `{image, image_alt:title, tag:label, title, subtitle}`; **Sports `stat_card_grid`** (all) → `{image, image_alt:title, chip:label, title, description, stats:[{value:stat1_value,label:stat1_label},{value:stat2_value,label:stat2_label}]}`.

### `clubhouse_team`
- `post_title` = team name (e.g. "1st XV"). Meta: `sport` (chip, e.g. "Rugby"), `description`, `match_day` (e.g. "Sat"), `league` (e.g. "Div 3"), `image`. `menu_order`.
- Projection: **Teams `stat_card_grid`** → `{image, image_alt:title, chip:sport, title, description, stats:[{value:match_day,label:'Match day'},{value:league,label:'League'}]}`.

### `clubhouse_fixture`
- `post_title` = auto/matchup. Meta: `sport` (competition label, e.g. "Rugby · 1st XV"), `match_date` (ISO `YYYY-MM-DD`), `kickoff_time` (e.g. "14:00"), `venue` ("Home"/"Away"), `home_team`, `away_team`, `score` (e.g. "+34"), `outcome` (`''` upcoming | `W` | `L` | `D`), `result_summary` (e.g. "Won by 34 runs"). `menu_order`.
- Projections (pure `DateTime`, unit-tested):
  - **Home `activity_tabs.fixtures`** (upcoming: `outcome === ''`) → `{month:MON, day:DD, competition:sport, time:kickoff_time, matchup:"home_team vs away_team"}`.
  - **Home `activity_tabs.results`** (played: `outcome !== ''`) → `{date:"MON D", home:home_team, away:away_team, score, outcome}`.
  - **Calendar `calendar_months`** → grouped by month-year into `{label:"Month", rows:[{date:"MON D", competition:sport, matchup, detail, outcome}]}`, where `detail` = `"{venue} · {kickoff_time}"` when upcoming else `result_summary`.

### `clubhouse_event`
- `post_title` = event title. Meta: `tag`, `date` (display string, e.g. "Sat 26 Jul" or "Jun 2026" for past), `detail`, `cta_label`, `cta_href`, `status` ("upcoming"/"past"). `menu_order`.
- Projections: **Home tab / Events `event_grid`** (upcoming) → `{tag, date, title, detail, cta_label, cta_href}` (Home tab omits the CTA keys → `{tag,date,title,detail}`); **Events `event_archive`** (past) → `{date, tag, title}`.

### `clubhouse_sponsor`
- `post_title` = sponsor name. Meta: `url` (stored, **unused** — the `sponsors` renderer takes only name strings). Projection: **Home `sponsors.names`** → `post_title` string.

### `clubhouse_person`
- `post_title` = person name. Meta: `role`, `email`, `show_committee` (bool), `show_directory` (bool). `menu_order`.
- Projections: **About committee** (`show_committee`) → `{name, role, email:''}` (committee blanks emails, per the demo); **Contact directory** (`show_directory`) → `{name, role, email}`.

## Components

- `includes/collections/interface-collections.php` — `Blueworx_Clubhouse_Collections` with `sports()/teams()/fixtures()/events()/sponsors()/people(): array` (canonical arrays).
- `includes/collections/class-demo-content.php` — pure single source of the demo canonical arrays for all six collections (the current inline demo data, relocated).
- `includes/collections/class-demo-collections.php` — pure `Collections` returning `Demo_Content`; used by the preview and tests.
- `includes/collections/class-collection-mappers.php` — pure static per-collection `map( array $post_fields ): array` functions (raw post+meta → canonical); unit-tested; shared by `WP_Collections`.
- `includes/collections/class-wp-collections.php` — WP `Collections`: `WP_Query`/`get_posts` + `get_post_meta` per type → `Collection_Mappers` → canonical. Thin fetch (WP-runtime-only) over the tested mapper.
- `includes/collections/class-collection-types.php` — registers the six post types + meta (`register_post_type`, `register_post_meta`), hooked on `init`.
- `includes/collections/class-collection-seeder.php` — on activation, for each collection with zero posts, insert posts from `Demo_Content` (`wp_insert_post` + meta).
- **Projection in `Page_Renderer`** — each page method gains a `Blueworx_Clubhouse_Collections $collections` param and maps canonical → renderer shape inline. The Fixtures month/day/grouping helpers live as private pure methods on `Page_Renderer` (or a small `Fixture_Projection` helper), unit-tested.

## Data flow / wiring

- `Page_Map::render( $slug, $branding, $visibility, $collections )` — threads the new param; all nine page methods take the uniform signature (membership/login ignore it).
- `Frontend::context()` builds `new WP_Collections()` and passes it into `Page_Map::render` / `render_body`. `Collection_Types::register()` is hooked on `init` alongside the rewrite rules; the activation hook also runs `Collection_Seeder::seed()`.
- `preview/index.php` builds `new Demo_Collections()`.
- CPT registration must run before rewrite flushing on activation so post-type rewrite rules are included.

## Testing strategy (no new deps)

- **Pure, unit-tested with existing harness:** `Demo_Content` (shapes are non-empty and carry every meta key), `Demo_Collections` (returns canonical arrays), `Collection_Mappers` (raw post+meta → canonical, incl. missing-meta defaults), the Fixtures projection helpers (date split, month grouping, upcoming vs result detail, W/D/L), and every `Page_Renderer` page method rendered against `Demo_Collections` (asserts the same `ch-*` structure as today, empty-collection handling, and Home-vs-all counts).
- **WP-function shim (`tests/php/wp-stubs.php`) extended** with recorder/stub versions of `register_post_type`, `register_post_meta`, `get_posts`/`WP_Query` (returning a seeded fixture set), `get_post_meta`, `wp_insert_post`, `get_option`/`update_option` — enough to assert `Collection_Types` registers six types and `Collection_Seeder` inserts when empty / skips when populated.
- **Playwright preview smoke** already covers end-to-end rendering; because the preview now reads through `Demo_Collections`, a green smoke proves the data-source swap renders identically. Extend the smoke to assert a representative collection item on Sports/Teams/Events/Calendar.
- **Manual WP smoke** (runtime-only): activate on a WP install, confirm the six CPTs appear with seeded posts and every page renders real data.

## Git, versioning, deployment

- Branch `collections-cpts` off `wp-integration`. Minor bump **0.12.0 → 0.13.0**; changelog entry. PR → `wp-integration` (extends the stack; merges after #7).
- Deployment zip refreshed at `<plugin-parent-dir>/blueworx-labs-clubhouse.zip` at the end (the new `includes/collections/` files are runtime; build via .NET `ZipArchive` with forward-slash entries).

## Decomposition (for the implementation plan)

1. `Collections` interface + `Demo_Content` (relocate demo data) + `Demo_Collections` (pure) + tests.
2. Thread `Collections` through `Page_Map::render`, all `Page_Renderer` page methods, `preview/index.php`, and existing tests — Sports first as the reference projection; renderers untouched.
3. Remaining projections (Teams, Events, Sponsors, People) in `Page_Renderer`, each tested against `Demo_Collections`.
4. Fixtures projection helpers (date split, month grouping, upcoming/result) + the three fixture shapes; tested.
5. `Collection_Mappers` (raw→canonical) + `WP_Collections` + shim extensions + tests.
6. `Collection_Types` (register six CPTs + meta) + `Collection_Seeder` (seed-if-empty) + shim tests; wire into `Frontend` (`init`) and the activation hook.
7. Extend the Playwright preview smoke; version bump 0.13.0 + changelog; refresh deployment zip.
