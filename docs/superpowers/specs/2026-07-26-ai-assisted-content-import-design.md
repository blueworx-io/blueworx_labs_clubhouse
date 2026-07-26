# AI-Assisted Content Import — Design

**Date:** 2026-07-26
**Status:** Approved (brainstorm)
**Branch:** `ai-assisted-content-import`

## Problem

Populating a new ClubHouse site means typing into ~45 catalogue sections across 9 tabs
plus six collections. The club owner has the facts — history, tiers, fixtures, committee —
but transcribing them into an admin UI is slow and they usually stall part-way.

## Solution

A three-step loop that moves the typing into an AI chat:

1. **Download** — an admin downloads a generated Markdown prompt from ClubHouse.
2. **Chat** — they paste it into any AI chat, which interviews them section by section and
   emits a JSON file.
3. **Upload** — they upload that file back to ClubHouse, review a preview, and apply it.

The prompt is *generated from the plugin's own declarative catalogue*, so it stays correct
as ClubHouse grows — no separately-maintained copy to drift.

## Scope

**In scope**

- All page content in `Content_Catalogue::pages()` — every `fields` and `loop` section
  across the Global, About, Membership, Contact, Login, Sports, Teams, Events and
  Calendar tabs.
- All six collections (`clubhouse_sport`, `clubhouse_team`, `clubhouse_fixture`,
  `clubhouse_event`, `clubhouse_sponsor`, `clubhouse_person`), with the meta defined in
  `Collection_Meta`.
- Images, via URL sideload into the Media Library, with a post-import "still needed" list.

**Out of scope**

- Branding and site setup (club name, accent, Base Look, social URLs, logo, favicon) —
  these stay on the Setup screen.
- Section visibility — stays on the Content screen.
- Blog posts and native WordPress content.
- Export (the reverse direction). Nothing here precludes it later.

## Component design

Pure core, thin WordPress glue — the pattern already used by Setup and Content.

| Class | File | Kind | Responsibility |
|---|---|---|---|
| `Blueworx_Clubhouse_Import_Prompt` | `includes/import/class-import-prompt.php` | pure | Render the downloadable Markdown from the catalogue + collection meta |
| `Blueworx_Clubhouse_Import_Parser` | `includes/import/class-import-parser.php` | pure | Decoded JSON → `Import_Plan`; validate against the catalogue allow-list |
| `Blueworx_Clubhouse_Import_Plan` | `includes/import/class-import-plan.php` | pure DTO | Section writes, collection operations, image fetches, warnings |
| `Blueworx_Clubhouse_Import_Preview` | `includes/import/class-import-preview.php` | pure | Plan → human summary rows for the screen |
| `Blueworx_Clubhouse_Import_Screen` | `includes/import/class-import-screen.php` | pure | Escaped HTML for the Import page (download, upload, preview, apply) |
| `Blueworx_Clubhouse_Import_Applier` | `includes/import/class-import-applier.php` | glue | Execute a plan: `Content_Store` writes, CPT posts, image sideloads |
| `Blueworx_Clubhouse_Import_Controller` | `includes/import/class-import-controller.php` | glue | Menu, capability + nonce gates, upload handling, prompt download, plan hand-off |
| `Blueworx_Clubhouse_Content_Sanitiser` | `includes/content/class-content-sanitiser.php` | pure | Field sanitising by catalogue type — **extracted** from `Content_Controller` |

### Sanitiser extraction

`Content_Controller::sanitise_field()` and `::sanitise_items()` move verbatim to a pure
`Content_Sanitiser`. `Content_Controller` delegates to it; the importer uses the same
class. This is deliberate: an AI-authored file must be treated exactly like form input,
and there must be exactly one place where a catalogue field type decides its own
sanitising. The existing `Content_Controller` tests keep passing unchanged.

Collections reuse `Collection_Meta::sanitise( $type, $key, $raw )` as-is.

## The generated prompt

`Import_Prompt::markdown(): string` composes:

1. **Role and rules.** Interview the club one section at a time in catalogue order. Draft
   copy from what the club describes rather than demanding finished text. Never invent
   facts — prices, dates, names, results must be asked for. Keep to the field's intent
   (an "Eyebrow" is a few words, a "Description" a short paragraph).
2. **Field inventory**, derived from `Content_Catalogue::pages()`: each tab, each section,
   each field with its label, type, and placeholder. Loop sections are described as
   repeatable with their item name ("Tile", "Milestone", "Tier"). `linkout` and `auto`
   sections carry a note explaining that their content comes from a collection.
3. **Collection inventory**, derived from `Collection_Meta`: each type with its title
   field and every meta key, label and type, including `select` option lists.
4. **Image rule.** For every `image`/`media` field, ask for a publicly-reachable image URL
   or accept "skip". Never fabricate a URL.
5. **Checkpoints.** After each tab, offer to continue or to generate the file now.
6. **Output contract.** One JSON code block, saved as `clubhouse-import.json`, in the
   shape below. Only include what was actually discussed.

Because every part is derived, adding a section, field or CPT meta key to ClubHouse
updates the prompt automatically. A lockstep test asserts that every catalogue field key
and every `Collection_Meta` key appears in the rendered prompt.

## File format

```json
{
  "clubhouse_import": 1,
  "generated_for": "0.34.0",
  "content": {
    "home": {
      "hero":  { "eyebrow": "Est. 1974 · Marlow", "title_lead": "Your club, ",
                 "image": { "url": "https://example.org/clubhouse.jpg", "alt": "The pavilion" } },
      "stats": { "items": [ { "value": "450", "label": "Members", "featured": true } ] }
    },
    "global": { "header": { "join": "Join the club", "join_href": "/membership/" } }
  },
  "collections": {
    "clubhouse_sport": [
      { "title": "Tennis", "subtitle": "Six courts, all year",
        "image": { "url": "https://example.org/tennis.jpg" } }
    ]
  }
}
```

- `clubhouse_import` is the format version (integer, currently `1`). A missing or unknown
  value is a hard validation error.
- `generated_for` is informational — the plugin version the prompt was generated from. A
  mismatch never blocks an import; unknown keys surface as warnings instead.
- `content` is keyed by **`store_page`**, not tab slug — matching `Content_Store` exactly.
  The Global tab's Header and Footer therefore appear under `"global"`.
- Loop sections carry `items`, matching `Content_Store::set_items()`.
- Image fields are objects (`{ "url": …, "alt"?: … }`), because a chat cannot know
  attachment IDs. A bare string is accepted and treated as the URL.
- **Absent means untouched.** This is what makes partial files work: a file covering only
  About writes only About and leaves every other page as it was.

## Validation

`Import_Parser::parse( array $decoded ): Import_Plan` is pure and total — it never throws
on bad input, it returns a plan plus warnings.

Hard errors (no plan produced, nothing applied):

- Not an object, or `clubhouse_import` missing/unsupported.
- Neither `content` nor `collections` present.

Warnings (dropped, import proceeds):

- Unknown page, section, field or collection type — not in the catalogue.
- A field supplied for a section that has no such field.
- A scalar where a loop's `items` array was expected, or vice versa.
- A `select` value outside its option list (falls back to the field default).
- An image value that is neither an object with a `url` nor a string.

Every surviving value is then sanitised by its catalogue type before it reaches the plan.
The plan that the preview shows is the plan that gets applied — there is no second parse.

## Collection merge

Chosen behaviour: **replace demo, keep real.** For each collection type present in the
file:

1. Existing posts of that type are classified **demo** or **real**. A post is demo if it
   carries `_clubhouse_demo` meta, or — for installs seeded before that marker existed —
   its title matches a `Demo_Content` title for that type.
2. Demo posts are deleted (`wp_delete_post( $id, true )`).
3. Each item in the file is matched against surviving real posts by title:
   same title → update its meta; no match → create.
4. Real posts not mentioned in the file are left alone.

Types absent from the file are untouched entirely — importing sports never disturbs
fixtures.

`Collection_Seeder` starts stamping `_clubhouse_demo = 1` on every post it creates, so
this stops depending on title-matching for new installs. The title fallback stays for
existing ones.

## Images

Applied through `media_sideload_image( $url, 0, $alt, 'id' )`, which routes through
`download_url()` → `wp_safe_remote_get()`. That is WordPress's vetted fetch path and
already rejects internal and private hosts, so the SSRF surface is WordPress's own rather
than something new. On top of it:

- Only `http` and `https` URLs are attempted; anything else is a warning.
- Each fetch failure is collected as a warning and the field is left unset — never fatal,
  never partial-writes a broken attachment ID.
- The resulting attachment ID is what gets stored, matching the existing convention
  (attachment IDs everywhere, URLs resolved only in the WP layer).

Fields whose image was skipped or failed are recorded in an `import_images_needed`
option: a list of `{ page, section, field, label }`. The Content screen shows an
**"Images still needed"** notice listing them, each linking to its section. Setting an
image on that section clears its entry.

## Screen and flow

A submenu page **Club Content → Import**, capability
`Owner_Capabilities::SETUP_CAP` (`manage_clubhouse` — club owner and full administrator).
The club owner is the person who does this, so gating it to `manage_options` would defeat
the feature.

Three states on one page:

1. **Start** — an explanation, a "Download the prompt" button, and a file upload field.
2. **Preview** — after upload: summary rows (`Home · Hero — 5 fields`,
   `Sports — 4 items, replacing 5 demo`, `12 images to fetch`), a warnings list, and
   **Apply** / **Cancel**.
3. **Result** — after apply: what was written, what failed, and the images-still-needed
   list.

The parsed plan is held between preview and apply in a user-scoped transient
(`clubhouse_import_plan_{user_id}`, 1 hour). The uploaded file itself is not retained.
Apply re-checks the capability and a fresh nonce.

Prompt download is a nonce- and capability-gated `admin_post` action that streams the
generated Markdown as `clubhouse-import-prompt.md`.

## Security

- Both the upload and the apply step are capability-gated and nonce-checked; the download
  action has no `nopriv` handler.
- Uploaded files are read and JSON-decoded in place — never moved into the uploads
  directory, never `include`d. A size cap (1 MB) and a decode-depth limit apply.
- Content values pass through `Content_Sanitiser`, collection values through
  `Collection_Meta::sanitise` — identical to the editor's own paths, so no new XSS or
  stored-markup surface is opened.
- Image fetches use `wp_safe_remote_get` via `media_sideload_image`.
- The transient is keyed per user, so one admin's pending plan is not applicable by
  another.

## Testing

WP-free PHPUnit, per the project's existing style:

- `Import_Prompt` — renders every tab, section, field and CPT key; the **lockstep test**
  asserting catalogue ↔ prompt coverage in both directions.
- `Import_Parser` — hard errors, each warning class, partial files, `store_page` keying,
  loop `items`, image object vs bare string, select fallback, sanitising applied.
- `Import_Plan` / `Import_Preview` — summary rows and counts, including the
  "replacing N demo" phrasing.
- `Import_Screen` — escaping, all three states.
- `Content_Sanitiser` — the extracted tests, plus proof `Content_Controller` still behaves
  identically.
- Glue (`Import_Applier`, `Import_Controller`, seeder marker) via the existing
  `tests/php/wp-stubs.php` shim, extended with `media_sideload_image`, `wp_delete_post`,
  `set_transient`/`get_transient`/`delete_transient`.

The DB-free preview server cannot run admin screens, so Playwright covers none of this.

### Manual WP smoke owed (runtime-only)

1. Download the prompt as an owner; it opens as Markdown and names every current section.
2. Paste it into a chat, answer a few sections, upload the resulting file — preview lists
   exactly what was supplied, nothing else.
3. Apply; the front end shows the imported copy on the right pages.
4. A second, partial file (About only) leaves Home untouched.
5. A file with 3 sports on a freshly-seeded install: demo sports gone, 3 real ones
   present, teams and fixtures untouched.
6. An image URL that 404s: warning shown, import still applies, field appears in
   "Images still needed".
7. A subscriber cannot reach the Import page or the download action.

## Decisions taken during the brainstorm

- Page content **and** collections in scope; branding and visibility explicitly out.
- Images by URL sideload, with a to-do list for the rest.
- Partial files that merge — the owner can import a tab at a time.
- JSON, not Markdown, for the uploaded file: it must be validated, and a chat emits JSON
  reliably.
- Preview before apply, no undo snapshot in v1.
- `manage_clubhouse`, not `manage_options`.

## Deferred

- Export (site → file), which would make the format round-trip and enable
  club-to-club templating.
- Undo / rollback of an applied import.
- Branding and visibility in the import file.
- Bundling images in a zip alongside the JSON.
