# Admin Phase 3 — Collection Editing, Projection Robustness & Header Logo/Nav — Design

**Date:** 2026-07-13
**Status:** Approved (brainstorm), pending implementation plan
**Branch:** `admin-phase-3-cpt-editing` (off `main`)
**Umbrella spec:** `docs/superpowers/specs/2026-07-13-admin-and-clubhouse-owner-experience-design.md` (Phase 3)

## Purpose

Phases 1–2 gave owners a bespoke **config** surface (Base Look, branding, visibility)
via the Clubhouse Setup screen. Phase 3 gives them the **content** surface: the six
collection CPTs become editable on WordPress's own post-edit screens through custom
meta-boxes and admin list columns, so an owner can replace the seeded demo data with
their own. It also closes two deferred `Fixture_Projection` robustness items and wires
the front-end logo rendering + hidden-page nav omission deferred from Phase 2.

The guiding constraints throughout: keep the pure/WP-free core untouched, preserve the
seed→register→read meta-key contract end-to-end, and keep the WordPress front end and
the DB-free preview byte-identical for identical inputs.

## Scope

In scope:

1. **Meta-box editing** for all six collection CPTs, driven by a single pure field
   definition (`Collection_Meta`), with native typed inputs and pure sanitisation.
2. **Admin list columns** for the high-signal fields of each CPT.
3. **`Fixture_Projection` robustness**: multi-year-correct calendar grouping (`Y-m`) and
   a malformed/empty `match_date` guard (no more `new DateTimeImmutable('')` → "now").
4. **Front-end logo rendering + hidden-page nav omission**, threaded into the pure
   `shell_header`/`shell_footer` as resolved data (one coherent task).

Out of scope (later phases / unchanged): the `clubhouse_owner` role + admin lockdown +
Dashboard takeover (Phase 4); font self-hosting (Phase 5); bespoke CRUD forms (rejected —
editing stays native); any third-party plugin integration.

## Key decisions (from the brainstorm)

- **Native typed inputs.** Meta-box fields use browser-native input types where they map
  cleanly (`date`, `time`, `email`, `url`, `<select>`); free text / textarea for the rest.
  No JS validation; all validation/sanitisation is server-side and pure.
- **Media fields store attachment IDs, resolved to URLs in the WP layer.** Chosen over
  storing raw URLs for **stability** (survives domain moves, HTTPS, CDN offload, thumbnail
  regeneration), **security** (an ID sanitises to a single integer via `absint` — a URL is
  attacker-controllable string data in a `src`), and **maintainability** (the logo already
  stores an ID; one convention across the plugin). Resolution lives only in the WP-coupled
  layer, so the pure core is unchanged.
- **Logo in the header renders as image + club-name text.** The `<img>` replaces the "C"
  mark glyph; the club-name text stays beside it; `alt` = club name; empty logo falls back
  to today's glyph.
- **Manual WP smoke owed for Phases 1 & 2 is still outstanding** (confirmed with the user).
  Phase 3 adds its own owed smoke to the same list; none are unit-testable.

## Architecture

Two new pure units and two thin WP-glue units, mirroring the codebase's existing split.

### Pure, WP-free, unit-tested

- **`Collection_Meta`** — the single source of truth for collection fields. Per CPT it
  declares each field's `key`, `label`, input `type`, `<select>` options, and a **pure
  sanitiser**; and a **columns** map (`[key => label]`) for the admin list screens. Used by
  the meta-box glue (render + save), by `WP_Collections` (to know which keys are `media`),
  and asserted directly in tests. Sanitisers use only core PHP (`filter_var`,
  `DateTimeImmutable::createFromFormat`, `in_array`, `trim`/`strip_tags`, `absint`) so the
  unit has no WordPress dependency. It shares the meta-key vocabulary with the seeder and
  mappers — the keys it writes are exactly the keys they read/seed.

- **`Fixture_Projection`** (existing, hardened) — `calendar_months` groups by a `Y-m` key
  with an `F Y` display label; a private `try_date(): ?DateTimeImmutable` strictly parses
  and returns `null` for empty/invalid input; the three views handle `null` deterministically.

### Thin WP-coupled glue (tested via `tests/php/wp-stubs.php` recorders + manual smoke)

- **`Collection_Meta_Boxes`** — `add_meta_box` per CPT (fields rendered from
  `Collection_Meta`); `save_post` gated by nonce + `current_user_can`, persisting through
  `Collection_Meta::sanitise()` → `update_post_meta`; `manage_{type}_posts_columns` +
  `manage_{type}_posts_custom_column` for the list columns; enqueues `wp.media` for the two
  `image` fields.

- **`Blueworx_Clubhouse_Media`** — a one-method WP helper: `url(int $id): string` wrapping
  `wp_get_attachment_image_url($id, 'full')`, returning `''` for a missing/deleted/non-image
  attachment. Shared by `WP_Collections` (image fields) and `Frontend` (logo).

- **`WP_Collections`** (existing, extended) — when assembling a raw post record, resolves
  `media`-typed meta (per `Collection_Meta`) from attachment ID → URL via `Media::url()`, so
  the record it hands the **pure mapper** still holds a plain URL-or-empty string.

- **`Frontend`** (existing, extended) — `render_body` resolves the logo attachment ID → URL
  via `Media::url()` and threads the string into the page render; preview passes `''`.

### Front-end threading (logo + nav)

- **`Sections::header`** gains a `logo` slot (URL string). Non-empty →
  `<img class="ch-brand__logo" alt="{club_name}">` replacing the mark glyph, name text kept;
  empty → today's glyph. All output escaped as now.
- **`Page_Renderer::shell_header`/`shell_footer`** gain `$logo_url` + `Visibility`, and
  filter their hardcoded nav / footer link lists by `is_page_visible` (map each `?page=x`
  href → slug → visibility check). Pure — the `Visibility` object is already passed to every
  page method.
- **`Page_Map::render`** and the nine page methods thread a new `string $logo_url` parameter
  (explicit, no default) to `shell_header`. `Frontend::render_body` supplies the resolved
  logo URL; `preview/index.php` supplies `''`.
- **Look CSS**: a new `.ch-brand__logo` hook (max-height constraint sized to the brand mark)
  added to `court-side.css` and mirrored to `members-house.css` + `floodlight.css` for
  re-skin parity.

## Field definitions (`Collection_Meta`)

Input `type` per field; sanitiser noted where non-obvious. Keys are exactly the registered
meta keys (`class-collection-types.php`) and match the seeder writes and mapper reads.

| CPT | Fields (type) |
|---|---|
| `clubhouse_fixture` | sport `text`, match_date `date` (strict `Y-m-d` or `''`), kickoff_time `time` (strict `H:i` or `''`), venue `text`, home_team `text`, away_team `text`, score `text`, outcome `select` `''`/`W`/`D`/`L`, result_summary `text` |
| `clubhouse_person` | committee_role `text`, directory_role `text`, email `email` (`FILTER_VALIDATE_EMAIL` or `''`) |
| `clubhouse_sponsor` | url `url` (`FILTER_VALIDATE_URL` or `''`) |
| `clubhouse_sport` | label `text`, subtitle `text`, description `textarea`, stat1_value `text`, stat1_label `text`, stat2_value `text`, stat2_label `text`, image `media` (`absint`, stored as ID) |
| `clubhouse_team` | sport `text`, description `textarea`, match_day `text`, league `text`, image `media` (`absint`, stored as ID) |
| `clubhouse_event` | tag `text`, date `text` (a display label like "Sat 26 Jul", **not** ISO), detail `textarea`, cta_label `text`, cta_href `url`, status `select` (`upcoming`/`past`, default `upcoming`) |

Text/textarea sanitiser: `trim(strip_tags(...))` (textarea preserves newlines). Output in the
meta-box is `htmlspecialchars(..., ENT_QUOTES)` — same discipline as `Sections::e`.

## Admin list columns (`Collection_Meta::columns`)

High-signal, per CPT (title stays WordPress's native first column):

- `clubhouse_fixture` — **Date** (`match_date`) · **Home v Away** · **Result** (`score`/`outcome`)
- `clubhouse_team` — Sport · League · Match day
- `clubhouse_person` — Committee role · Directory role · Email
- `clubhouse_event` — Date · Tag · Status
- `clubhouse_sport` — Subtitle · Stat 1
- `clubhouse_sponsor` — URL

## `Fixture_Projection` robustness (detail)

- **`calendar_months`**: group key becomes `$d->format('Y-m')` (multi-year correct); the
  emitted group `label` becomes `$d->format('F Y')` ("January 2026" — a deliberate, correct
  visible change from the year-less "January"); groups ordered by the `Y-m` key descending,
  consistent with the existing newest-first sort.
- **Malformed/empty date guard**: `try_date(string): ?DateTimeImmutable` strictly parses
  (`createFromFormat('Y-m-d', ...)` with a round-trip check) and returns `null` on empty or
  invalid input — removing the `new DateTimeImmutable('')` → "now" bug. Behaviour on `null`:
  - **`home_fixtures` / `home_results`**: undated fixtures are **omitted** — these tabs are
    inherently date-ranked ("next/last 3"), so an undated fixture has no position.
  - **`calendar_months`**: undated fixtures are grouped under a **"Date TBC"** bucket, ordered
    last, with their row `date` rendered as `TBC` — surfaced, never silently dropped, never fatal.

## Data & storage

No new option schema. Collection meta uses the already-registered keys. Image meta now holds
an **attachment ID** (previously the demo seeded `''`); the demo content and preview continue
to use URL-or-empty strings, and `WP_Collections` resolves live IDs to URLs, so the pure
mapper/seeder/projection contract is byte-for-byte unchanged. No persisted state is added for
the logo/nav work — the logo ID already lives on `Branding`; visibility already drives page
gating.

## Error handling & security

- **Meta-box saves**: nonce (`wp_verify_nonce`) + `current_user_can` before any write;
  autosave/revision guards; every field sanitised through `Collection_Meta::sanitise()` before
  `update_post_meta`; every value escaped on output in the box.
- **Media IDs**: `absint` on save; `Media::url()` returns `''` for missing/deleted/non-image
  attachments, so a broken reference degrades to the renderer's existing empty-media placeholder
  — never a broken `src`, never a fatal.
- **Malformed dates**: guarded in the projection (see above) — "Date TBC", never "now", never fatal.
- **Missing/deleted collection content**: renderers already handle empty sets; unchanged.

## Testing strategy

- **Pure units** (WP-free): `Collection_Meta` sanitisers per field type (valid, invalid, empty,
  boundary — e.g. bad date/time/email/url, out-of-set select) and `columns()` maps;
  `Fixture_Projection` `Y-m` grouping across a **two-year** fixture set + the malformed/empty-date
  guard across all three views; `Sections::header` logo slot (present/absent) and nav/footer
  visibility filtering.
- **WP glue** via `tests/php/wp-stubs.php` recorders (extend with `update_post_meta`,
  `wp_get_attachment_image_url`, `add_meta_box`, `wp_verify_nonce`, column hooks as needed):
  `Collection_Meta_Boxes` save round-trip and column registration; `Media::url()` fallback;
  `WP_Collections` image resolution.
- **Playwright preview** stays green (logo passes `''` in preview, so preview output is unchanged
  except the projection's `F Y` calendar label).
- **Manual WordPress smoke owed** (runtime-only): edit a fixture → `/calendar` reflects it and
  groups by month-and-year; set a logo → it appears in the header with the name beside it; hide a
  page → it drops from the nav and footer. This smoke, plus the still-outstanding Phase 1 (routing/
  `is_front_page`) and Phase 2 (accent rejection / hidden-page 404) smokes, are carried as owed.

## Versioning & deployment

Minor bump **0.16.1 → 0.17.0** with the changelog updated alongside, as the final task. Branch
`admin-phase-3-cpt-editing` off `main`; PR to `main`; wait for the `guardrails / guardrails` check
to go GREEN before merging (auto-merge disabled). After merge, refresh the deployment zip at
`..\blueworx-labs-clubhouse.zip` per the house rule (runtime files only; forward-slash entries via
.NET `ZipArchive`).

## Risks & open items

- **Nine page-method signatures** gain `$logo_url` — mechanical but broad; the plan sequences the
  threading as one task so no page renders with a stale signature mid-build.
- **`F Y` calendar label** is a visible front-end change; intended and correct for multi-year data.
- **Interface/contract drift**: `Collection_Meta` must stay the *only* place field keys/types are
  declared for editing — the seeder and mappers keep their own key lists, so a review checkpoint
  confirms all three agree (the key-mismatch class of bug from the collections build).
