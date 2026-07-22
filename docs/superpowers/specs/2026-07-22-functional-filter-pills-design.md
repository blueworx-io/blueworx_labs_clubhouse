# Functional filter pills (slice 2d)

**Date:** 2026-07-22
**Status:** Approved
**Branch:** off `main`, targets `main`.

## Problem

The Sports / Teams / Events / Calendar pages render a row of filter pills via
`Sections::hero_filter()`, but they're decorative: every pill links to the same
page URL, "All" is hardcoded active, and no JS or query var reads them. The pill
labels are also hardcoded arrays that can drift from the actual content.

## Goal

Make the pills filter, server-side (no JS), and derive them from the data so
they can never drift.

## Mechanism (server-side)

1. **Query param** `clubhouse_filter` (a slug). Read and sanitised by the
   WordPress frontend (`render_body`) and the preview (`preview/index.php`),
   threaded through `Page_Map::render` into a new `$filter` param on the four
   filtered page renderers. The other five page methods ignore it.
2. **Pill hrefs** built by a new `Links::filtered_url($key, $slug)` →
   `<page-url>?clubhouse_filter=<slug>`. "All" links to the bare page URL.
3. **Active + shown items chosen server-side.** The pill whose slug equals the
   current filter is marked active (default "All"); the listed items are filtered
   to that slug. An empty or unknown filter shows everything with "All" active.

## Deriving pills + matching

Pills come from the distinct values present in the data (first-seen order),
always led by "All". Matching is by `slugify(value) === $filter`:

| Page | Value filtered on | Source field |
|------|-------------------|--------------|
| Sports | sport title | `sports()[].title` |
| Teams | team sport | `teams()[].sport` |
| Events | event tag (across upcoming + past) | `events()[].tag` |
| Calendar | sport prefix before "·" | `fixtures()[].sport` (`"Rugby · 1st XV"` → `Rugby`) |

No taxonomy/schema migration — derive-from-data + slug matching against the
existing free-text fields. Events pills derive from all events so they stay
stable regardless of the active filter; the fixtures are filtered before the
month projection.

## Shared helpers (Page_Renderer, private)

- `slugify(string): string` — lowercase, non-alphanumeric → `-`, trimmed.
- `distinct(rows, pick): string[]` — distinct non-empty picked values, first-seen order.
- `filter_rows(rows, current, pick): rows` — keep rows whose picked value slugifies to `current`; empty `current` keeps all.
- `filter_pills(page_key, labels, current): filters[]` — "All" + one pill per label, hrefs + active flag.

## Testing

Per page (PHPUnit, calling the renderer with a `$filter`):
- filtering to a slug narrows the listed items and marks that pill active;
- "All"/empty and an unknown slug show everything with "All" active.
Plus `Links::filtered_url` output, and `Page_Map::render` threading the filter.
A Playwright spec drives the query param end-to-end on the preview.

## Out of scope

- No JS, no client-side filtering.
- No changes to the collection data model or the `hero_filter()` markup.
- Filter labels are not owner-editable (they mirror the data).
