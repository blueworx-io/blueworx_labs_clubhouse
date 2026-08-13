# Page composition and block library

Design, 2026-08-13. Baseline: v0.63.1.

## The change

Today a page is a fixed recipe baked into code. `Page_Renderer` has eleven page
methods (~1,200 of its 1,765 lines); each is a run of `if ( visible ) { … }`
lumps that read content from a fixed `page/section` address, shape any
collection data, and call a renderer on `Sections`. An owner can only switch a
section off. Content saved at `home/hero` has nothing to do with content saved
at `about/hero`, and no section can appear on a page its method does not
mention.

After this change:

- Every block type exists once, in a registry, and can go on any page.
- Owners keep a **library of blocks** — named instances of those types, each
  holding its own content. Edit a block once; every page using it changes.
- Each page stores **which blocks it shows** and whether the page is live.
- The front end is assembled by one loop, not eleven hand-written methods.

`Sections` — the skin-agnostic markup — does not change, so all three Base Looks
and their stylesheets carry over untouched.

## Decisions taken

| Question | Decision |
| --- | --- |
| What do pages share? | Named instances in a library. Same block on two pages = genuinely shared content. Different words = two blocks. |
| Can owners reorder? | No. Each block carries a position, seeded from where it sits today; its type supplies the default position for blocks created fresh. |
| Can owners create pages? | No. The eleven pages stay fixed. |
| Which blocks can go where? | Any block on any page, including collection-driven ones. |
| Setup → Visibility tab | Removed. Page on/off moves into the page editor. |
| Admin shape | Two screens: Content → Pages, Content → Blocks. |
| Existing installs | Migrated silently; front end identical after upgrade. |

## Model

### Block type — code, pure, declarative

One registry entry per renderer on `Sections`, roughly 25 of them. Each entry
declares:

- `key` — stable identifier, e.g. `hero`, `cta`, `faq`, `sponsors`.
- `label` — owner-facing name, e.g. "Hero".
- `fields` — the editable field list, in the shape `Content_Catalogue` already
  uses (`text`, `textarea`, `url`, `image`, `toggle`, `select`, `shortcode`) plus
  an optional repeatable-item definition.
- `rank` — the default position for a block of this type created fresh. Header
  is first, footer last, hero near the top, call-to-action near the bottom. A
  block may carry its own position instead; see below.
- `source` — `content` (owner writes it), `collection` (drawn from a CPT) or
  `mixed` (a heading the owner writes over a collection-driven list).
- `settings` — optional per-block configuration for collection-driven types that
  need to know which slice to show. Added only where the existing renderer
  already takes such an input; not invented speculatively.
- `render` — given the block's content, branding, collections and the link
  resolver, returns the section's HTML by calling `Sections`.
- `singleton` — true for header and footer only.
- `requires` — an integration key, for types that cannot render without a
  third-party plugin (the LatePoint booking slots). Absent integration removes
  the type from the picker, exactly as `Integrations::section_available` does now.

This registry is the single source of truth. The page editor, the library, the
migration, the import subsystem and the front end all read it, so they cannot
disagree about what a block is.

### Block — stored owner data

A named instance of a type:

- `id` — slug, unique, generated from the name (`home-hero`).
- `type` — a registry key.
- `name` — owner-facing, e.g. "Home hero".
- `content` — field values plus repeatable items, same shape `Content_Store`
  holds today.
- `settings` — values for the type's declared settings, if any.
- `position` — where it sits on a page. Seeded and migrated blocks carry the
  position they hold today; a block created fresh takes its type's `rank`.
  One rank per type alone cannot reproduce today's pages — About runs values,
  facilities, committee, get involved, where the first and last are the same
  type either side of two others — so the position belongs to the block.

### Page — stored owner data

The eleven pages stay fixed: `home`, `about`, `membership`, `contact`, `login`,
`news`, `sports`, `teams`, `events`, `calendar`, and `booking` (which only
exists when LatePoint is installed). Header and footer are not pages — they are
singleton blocks shown on every one. Each page stores:

- `enabled` — whether the page is on the site.
- `blocks` — the ids of the blocks it shows, in the order they were added.

Render order is each block's `position`, with the stored list order breaking
ties. The page does not store an order of its own — moving a block is not
something the editor offers.

### Storage

Two new stores over the existing `Storage` interface, following `Content_Store`:

- `Block_Library` — the blocks, one autoloaded option.
- `Page_Composition` — per-page `enabled` flag and block-id list, one autoloaded
  option.

`Content_Store` is superseded for page content once migration has run; it stays
readable so nothing else breaks mid-build, and is removed in the final step.
`Visibility` keeps `is_page_visible` / `set_page_visible` (used by
`Frontend::resolve_slug` to 404 a hidden page); its section-level methods and
`Setup_Sections` go.

## Render path

`Page_Renderer` gains one page method:

1. Read the page's block ids.
2. Resolve each to a block and its type; drop ids with no block, and types whose
   integration is absent.
3. Sort by each block's `position`, ties broken by its place in the stored list.
4. Render each, wrapped in its anchor.
5. Header and footer are pinned outside the loop — header first, then `<main>`,
   then footer.

The eleven page methods are deleted. `Page_Map` keeps its slug→page dispatch but
every page resolves through the same method.

**Anchors.** `Link_Catalogue::anchor_id( page, section )` keeps its current
addresses so existing in-page links and the owner's link picker keep working.
The anchor is derived from the page slug plus the block's **type key**, not its
name or id. Two blocks of one type on one page: the first keeps the plain
anchor, later ones get a numbered suffix.

**Defaults stay in code, and stay live.** Today's demo copy is hardcoded in the
page methods and surfaces only where nothing is saved. It cannot simply be
frozen into seeded data, because some of it is computed at render time — the
Home lede counts the club's sports and teams, and default button links resolve
through the link resolver. Freezing those would leave a club reading "nine
sports" with six.

So each block type keeps a defaults function in code, and a block stores only
the owner's overrides — exactly the `cget` behaviour that exists now. Because
Home's hero copy differs from About's, a block also carries a `defaults_key`
naming which default set it draws on: the migrated and seeded blocks carry
their original `page/section` address, and a block an owner creates fresh uses
its type's generic set. Extracting those ~55 default sets out of `Page_Renderer`
is the largest single slice of this work, and the parity check below is what
proves it exact.

A `Block_Seeder` therefore creates named blocks with no content and the default
page compositions on activation, when the library is empty — mirroring
`Collection_Seeder`.

## Admin

### Content → Pages

Pages listed down the side. The selected page shows:

- A switch for the whole page (this is where the removed Visibility tab lands).
- Its blocks in render order. Each row: name, type, an Edit link through to the
  library, and Remove — which takes the block off this page without deleting it.
- Add a block: a picker grouped by type, listing every existing block of that
  type to reuse, plus "New hero" to create one and drop it in.

Header and footer appear pinned at top and bottom, not removable.

### Content → Blocks

The library, grouped by block type. Each row shows the block's name and where it
is used — "Home, About" — so a shared block is obvious before it is opened.

The edit form is the field UI that exists today in `Content_Screen`; field
types and repeatable-item handling carry over. A block used on more than one
page says so at the top of its form.

Actions: new, duplicate (the escape hatch when a shared block must differ on one
page), rename, delete. Deleting a block that is in use asks first and names the
pages.

Both screens keep the existing pure/glue split: pure screen classes emit escaped
HTML from a model; a controller handles menu mounting, nonce and capability
checks, sanitising and persistence. Capability `manage_clubhouse`, as now.

## Downstream work

| Area | Change |
| --- | --- |
| `Content_Catalogue` | Becomes the block-type registry. Its per-page grouping is replaced by per-type definitions; field helpers survive. |
| `Setup_Sections`, Setup Visibility tab | Removed. |
| `Setup_Progress` | Its `visibility` group is dropped or repointed; progress no longer counts a screen that has gone. |
| Import (`Import_Parser`, `_Preview`, `_Applier`, `_Prompt`, `_Sections`) | Addressed at blocks rather than page/section slots. Importing into a shared block updates every page using it — stated in the preview. |
| `Link_Catalogue` | Anchor derivation from page + type key; link picker lists blocks per page. |
| Guide | Content links repointed at the two new screens. |
| `Content_Sanitiser` | Sanitises per block against its type's field list; the logic carries over. |

## Testing

- **Parity check, the safety net.** Before the page methods are deleted, capture
  each of the eleven pages' HTML from the current renderer. The new
  data-driven render, against the seeded library and default compositions, must
  match. A difference stops the build. This is what makes a change of this size
  safe to make in one pass.
- Pure unit tests: registry completeness (every `Sections` renderer reachable as
  a type; every type's fields match what its render reads), rank ordering and
  tie-breaking, migration mapping, library add/duplicate/rename/delete rules
  including the in-use guard, anchor derivation and its numbered-suffix case,
  sanitising per field type.
- Playwright over the DB-free preview, as today: every page renders, no
  horizontal overflow, all three looks.
- Lint clean.

## Migration

Runs once on upgrade, guarded by a version stamp in the same way
`Owner_Role::maybe_upgrade` re-syncs capabilities on a plugin update.

For each of the ~55 page-and-section addresses in today's catalogue:

1. Create a block of the matching type, named "Home · Hero", "About · Hero",
   carrying that address as its `defaults_key` and its position on the page as
   it renders today.
2. Its content is the club's saved content for that address, or nothing — in
   which case it falls back to the same code defaults it uses today.
3. Add it to that page's block list if the section is currently visible; leave
   it in the library, off the page, if it is hidden.

Page `enabled` flags carry over from `Visibility`'s page state unchanged. The
front end must be identical before and after. Old `content_*` and `visibility`
options are left in place rather than deleted, so a bad migration can be
diagnosed; they are not read once migration has run.

## Risks and what we accept

- **Size.** This is the largest single change the plugin has had. It lands as
  several sequential plans — registry and render path, admin screens, import and
  guide repointing, migration — not one. The parity check runs from the first
  plan onward.
- **Runtime-only verification.** The admin screens and the migration cannot run
  in the DB-free preview. A manual WordPress smoke test on a live install is
  required before release: upgrade an install with saved content and confirm the
  front end is unchanged; compose a page; share a block across two pages and
  confirm both change; delete a block in use and confirm the warning.
- **Order is not draggable.** Rank is a house decision, not an owner one. If a
  club wants a section higher than its rank allows, the answer is to change the
  rank in code for every club, or to revisit this decision later.
- **Anchor stability across renames.** Anchors follow the type, not the block
  name, so renaming a block never breaks a link. Two blocks of one type on one
  page produce a suffixed anchor, which is stable as long as their relative
  order does not change.

## Out of scope

Owner-created pages, drag-to-reorder, per-page overrides of a shared block
(duplicate instead), block-level style options, and any change to `Sections`
markup or the Base Look stylesheets.

The per-sport and per-team detail pages, the single-post view and the filtered
list views are generated from a collection item, not composed from a block list.
They keep their current dedicated renderers and are not offered in the page
editor.
