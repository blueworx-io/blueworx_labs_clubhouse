# The honours board — design

**Date:** 2026-08-31
**Baseline:** v0.101.4
**Issue:** [#256](../../../../issues/256)

## Read this first

Three decisions in here are worth a minute of Luke's time before anybody
builds. They are marked **Decide** and gathered at the end. Everything else
follows the patterns this plugin already has, and needs no discussion.

## The change

A club wants to record who held what, and when: club champions, chairpersons,
captains, player of the year, life members. There is nowhere to put any of it
today.

This adds another club page — the honours board — and a seventh collection
behind it. The club decides what the categories are, by naming them; the page
lists every entry, filtered by category and then by year.

## The shape

### An honour is a record, not a row on a page

The obvious alternative is a repeating list on the page's own editor, the way
membership tiers and the legal pages work. That is wrong here for one reason:
size. A club with twenty years of five categories has a hundred entries, and a
repeater of a hundred rows is a screen nobody can find anything in. Every other
list of that size in this plugin — fixtures especially — is a collection, with
its own admin list that sorts, searches and paginates.

So: `clubhouse_honour`, the seventh post type, alongside Sports, Teams,
Fixtures, Events, Sponsors and People. It inherits everything phase 5 built —
its own editor screen, its own list under Clubhouse, its meta keys through
`Collection_Meta::meta_key()` — for the cost of declaring it.

| Field | Kind | What it is |
| --- | --- | --- |
| `post_title` | title | The person's name |
| `category` | text | What they won or held — "Club champion", "Chairperson" |
| `year` | text | The year, as the club writes it. Text, not a number: a club with a season writes "2024/25" |
| `detail` | text | One line under the name, optional — "Ladies singles", "Founding member" |

**Decide (1).** `category` as free text means a typo makes a phantom category.
The alternative is a select whose options come from a list the club keeps
somewhere — which is a second thing to maintain, and a coupling between a
collection and a page's content that nothing else here has. Free text is
recommended: it is what Events already does with its `tag`, the same filter
pills are built from it, and a club fixes a typo by fixing the entry.

### The page

`Page_Map` gains `honours`, label "Honours", method `honours`. Nothing else
about it is special: it is a real WordPress page like every other club page,
created by `Club_Pages::ensure()` on the first request after the update, off
until the club turns it on.

Its editor is an area in `Page_Fields` with two panels:

- **Hero** — the four `hero_filter` fields, as Sports, Teams, Events and
  Calendar have. That renderer takes no buttons or photograph, and a page whose
  job is a list does not want them.
- **Board** — a heading and an intro, and the panel that carries the
  `collection` marker naming `clubhouse_honour`, so the import knows the page's
  content comes from the collection rather than from the file.

Both panels appear in `Setup_Sections::inventory()` under a new `honours` page,
which is what gives them their Shown switches and keeps the lockstep test happy.

### Two tiers of filtering

Today a filtered page carries one value: `?clubhouse_filter=hockey`, read by
`Links::FILTER_PARAM`, turned into pills by `Sections::hero_filter()`. The
honours board needs two.

- **Tier one, category.** Pills, exactly as Events does with its tags — derived
  from the distinct categories across every entry, so they cannot go stale, and
  narrowed by `?clubhouse_filter=`.
- **Tier two, year.** A dropdown, not pills, and it narrows within the chosen
  category. Twenty years is twenty pills, which wraps to three lines on a phone
  and is unusable; a dropdown is one control at any length.

**Decide (2).** Pills then dropdown is the recommendation. Pills for both reads
better with three years and worse with twenty, and a club that has kept records
has twenty.

The second value needs a parameter of its own — `clubhouse_year` — which is one
more thing than the site has today. Three places have to learn it: `Links` (so a
link can carry it), `Seo_Head` (so a filtered address does not compete with the
page's own for the same title), and the renderer.

**Decide (3).** The alternative is to fold both into one parameter
(`?clubhouse_filter=club-champion:2024`), which adds no new parameter but makes
the value something to parse rather than something to read. A second parameter
is recommended: it is plainer in the address bar, and each tier stays
independently linkable.

### What a visitor sees

One list, grouped by year within the chosen category, newest year first. Each
row is a year, a name, and the optional line under it. With no category chosen
the list shows every category, each under its own heading.

An empty board — a club that has turned the page on and added nothing — says so
in the section's own empty state, the way the events grid already does. It never
shows a heading over nothing.

## What an existing site does on update

Nothing to migrate. The page is created by `Club_Pages::ensure()`, which runs on
the first request after the version changes, and arrives switched off. The
collection is empty until the club adds to it. A rollback loses the entries a
club typed in the meantime, and nothing else — they are ordinary posts, so they
survive in the database even then, invisible until the plugin is put back.

The demo content gains a handful of honours, so the page has something to show
on a demo site and the browser tests have something to filter.

## Testing

- **PHP.** The collection declares its fields and they round-trip; the page is
  in the map, the inventory and the editor; the renderer groups by year and
  narrows by both filters; an empty board says so.
- **Playwright, against real WordPress.** An owner turns the page on in Setup,
  adds an entry through the collection's own editor, and sees it on the page.
  Both filters work by click and by keyboard. Signed in as the owner, not an
  administrator — the rule this repo learned the hard way.
- **The lockstep tests already in place** cover the rest: a page in the map with
  no editor area, or an editor panel with no visibility switch, fails on its own.

## Out of scope

Photographs, biographies and a page per person. Anything derived from fixtures
or results. Both are named as out of scope on the issue and stay there.

## Decisions to confirm

1. **Category is free text on the entry**, and the pills are built from the
   distinct values — as Events already does with its tag. The alternative is a
   list the club maintains separately.
2. **Category as pills, year as a dropdown.** The alternative is pills for both.
3. **A second query parameter, `clubhouse_year`.** The alternative is one
   parameter carrying both values.

## Order of work

One release, four tasks, each leaving the plugin coherent:

1. The collection: type, meta, editor screen, demo rows.
2. The page: map, visibility inventory, editor area, the real page.
3. The renderer: the board section, grouping, and the two filters.
4. The browser test, and the page turned on in the demo content.
