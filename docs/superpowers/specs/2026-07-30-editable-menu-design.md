# Editable header menu

**Date:** 2026-07-30
**Status:** Approved design

## Problem

The header navigation is a hardcoded list of nine plugin pages in
`Page_Renderer::shell_header()`. An owner can hide a page (the visibility
toggles) but cannot reorder items, rename them, group them under a parent, or
point an item anywhere other than a plugin page's top.

Separately, every URL field in Club Content (header banner link, Join button,
each page's CTA) is a free-text box whose only assistance is a `<datalist>` of
the nine page URLs. An owner who wants to link a section of a page, or a
particular sport, has to know and type the URL.

## Goal

Let an owner edit the header menu — order, labels, one level of child items —
and point any item, or any link field in Club Content, at a page, a section
anchor, or a filtered collection view, chosen from a searchable list rather
than typed.

Out of scope: the footer link columns (they stay derived from page visibility),
WordPress pages and posts outside this plugin, and drag-and-drop reordering.

## Architecture

Four new units, plus changes to three existing ones.

### `Blueworx_Clubhouse_Link_Catalogue` (new, `includes/content/`)

The single source of linkable targets, read by both the menu editor and every
URL field in Club Content. One method:

```php
public static function targets( Blueworx_Clubhouse_Collections $collections ): array
```

Returns a flat list, each entry `{ target: string, label: string, group: string,
url: string }`, in group order:

| Group      | Source                            | `target`                 | Example label            |
|------------|-----------------------------------|--------------------------|--------------------------|
| Pages      | `Page_Map::available()`           | `page:about`             | `About`                  |
| Sections   | `Content_Catalogue::pages()`      | `anchor:about.history`   | `About → History`        |
| Sports     | `$collections->sports()`          | `filter:sports:netball`  | `Sports → Netball`       |
| Teams      | `$collections->teams()`           | `filter:teams:1st-xv`    | `Teams → 1st XV`         |
| Events     | `$collections->events()` tags     | `filter:events:socials`  | `Events → Socials`       |

Collections have no per-item permalink — they render inside filtered list
pages — so a collection target is a filtered page view, built with
`Links::filtered_url()` and the same `Page_Renderer::slugify()` the filter
pills use. Deriving both from one slugify keeps a picked target and a rendered
pill in agreement.

`Link_Catalogue::resolve( string $target, Collections $c ): string` turns a
target tag into an href, returning `''` when the target no longer exists (page
hidden or integration absent, section removed from the catalogue, sport
deleted). Callers treat `''` as "drop this link".

A fifth target form, `url:https://…`, resolves to itself after
`esc_url_raw()`; it never appears in `targets()` and exists so a stored
free-text URL is representable in the same field as a picked one.

### `Blueworx_Clubhouse_Menu` (new, `includes/content/`)

Owns the stored nav tree. Constructed with the `Storage` seam like
`Visibility` is, reading option key `menu`. Stored shape:

```php
[ [ 'label' => 'About', 'target' => 'page:about', 'children' => [
    [ 'label' => 'Our history', 'target' => 'anchor:about.history' ],
  ] ], … ]
```

Children never have children — `Menu` truncates a third level on save rather
than rejecting it.

`Menu::items( Collections $c, Visibility $v ): array` returns render-ready
`[ { label, href, children: [ { label, href } ] } ]`:

- Each target resolves via `Link_Catalogue::resolve()`; an item whose target
  resolves to `''` is dropped.
- A `page:`/`anchor:` target additionally passes the owner's `is_page_visible()`
  check, so hiding a page still removes it from the nav however the menu is
  ordered — the gate `nav_links()` applies today, unchanged in effect.
- A parent whose own target is gone but that still has surviving children
  renders as a non-link heading rather than disappearing and taking its
  children with it.
- A parent with no surviving children and no href is dropped.

**Default:** when the option is absent or empty, `Menu` returns the current
hardcoded nine items (Home, About, Sports, Teams, Membership, Events, Calendar,
Book a court, Contact) as `page:` targets, flat. There is no migration step and
an install that never opens the editor renders exactly today's nav.

### Section anchors (change to `Sections` + `Page_Renderer`)

Section markup carries no ids today, so `anchor:` targets have nothing to jump
to. Each section renderer gains an `anchor` key in its data array, emitted as
`id="ch-<page>-<section>"` on the section's outer element and omitted when
empty. `Page_Renderer` passes `page` and `section` keys that are the same
strings `Content_Catalogue` uses for its tabs and sections, so the catalogue
cannot offer an anchor the markup does not emit.

### Header rendering (change to `Sections::header()`)

`nav` entries gain an optional `children` array. An item with children renders
as a `ch-nav__item--has-children` wrapper containing the parent link and a
`ch-nav__sub` list. The submenu opens on `:hover` and `:focus-within` — no
JavaScript, and reachable by keyboard because focus moving into the sublist
keeps it open. `aria-haspopup="true"` on the parent link.

In the mobile drawer, children render as an indented sub-list that is always
open; there is no disclosure to operate, so nothing to make accessible.

### `Menu` tab in Club Content (change to `Content_Screen` / `Content_Controller`)

A "Menu" tab, first in the tab row, ahead of Global. It reuses the screen's
existing single-tab save path: its own hidden `clubhouse_content_tab` value and
its own Save button, so saving the menu cannot clobber another tab.

The panel is a list of rows. Each row is a label text input, a target picker,
and four buttons: move up, move down, indent, outdent. Indent is disabled on
the first row and on a row already indented; outdent is disabled on a top-level
row. A final "Add item" row appends a blank item. Reordering posts and
re-renders — no JavaScript, and directly testable in Playwright.

Row order and nesting are carried in the field names (`menu[0][label]`,
`menu[0][children][0][label]`), so the submitted form is the tree.

### Shared link picker (change to `Content_Screen`)

The `url` field type keeps its `<input type="url" list=…>` shape. The shared
`<datalist>` grows from the nine page URLs to the whole `Link_Catalogue`, each
`<option>` carrying the catalogue label. Native search-as-you-type, no new
dependency, and every URL already saved stays valid because the input is still
free text. The menu editor's target picker is a `<select>` over the same
catalogue plus a "Custom URL…" option that reveals a URL input.

## Data flow

```
Content_Catalogue ─┐
Page_Map ──────────┼─→ Link_Catalogue::targets() ─┬─→ Menu tab target picker
Collections ───────┘                              └─→ Club Content URL datalist

Menu (option 'menu') ─→ Menu::items(Collections, Visibility) ─→ Sections::header()
                              │
                              └─ Link_Catalogue::resolve() per target
```

## Error handling

- A target that no longer resolves is dropped from the nav silently — a broken
  link is worse than a missing one, and the Menu tab still shows the stored row
  so the owner can see and fix it. The row is marked "target unavailable".
- A saved menu with every item unresolvable renders an empty `<nav>`; the
  header, logo, Join and Log in are unaffected.
- `url:` targets pass through `esc_url_raw()` on save and the existing
  `esc_url()` scheme guard on render, so `javascript:` cannot reach an href.
- Deleting all rows and saving stores an explicit empty menu, which renders an
  empty nav — it does not fall back to the defaults. The defaults apply only
  when the option was never written.

## Testing

PHPUnit (`tests/php/`):
- `LinkCatalogueTest` — every group present; anchor targets match catalogue
  keys; filter targets slugify the same way the pills do; `resolve()` returns
  `''` for a removed page, section and sport; `url:` passes through.
- `MenuTest` — defaults when unset; explicit empty stays empty; order and
  labels preserved; third level truncated; hidden page dropped; unavailable
  integration dropped; parent with dead target but live children becomes a
  heading; parent with neither is dropped.
- `SectionAnchorTest` — every section the catalogue lists emits its id.
- Extend `PageRendererTest` — nav renders children, and renders the default
  nine when no menu is stored.

Playwright (`tests/`):
- `menu-editor.spec.js` — add an item, rename it, move it up, indent it under
  the row above, save, and confirm the front end nav shows the new order with
  the child nested.
- Extend a nav test to assert the submenu is reachable by keyboard (focus the
  parent link, the sublist becomes visible).

## Versioning

Minor bump — new owner-facing feature. Changelog entry alongside.
