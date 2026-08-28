# Adopting the page editor standard — design

**Date:** 2026-08-28
**Baseline:** v0.95.0
**Foundation:** `bluegroup_core_foundation` at v1.9.0 (`18a9db1`, "Give every plugin the same page editor")

## The change

Clubhouse's wp-admin is entirely bespoke. Club Pages is a hand-built tabbed form
over a hand-built catalogue; Setup is another; the six collections are edited
through a "Details" meta box on WordPress's own post screen. None of it uses the
`blueworx-admin-design` system — the `bw-` classes this plugin does carry are
front-end only, on the member area, the checkout and the profile panel.

The foundation now sets one shape for every custom editor screen we build: a
vendored library a plugin declares its screen to, a closed list of controls, one
save model, and the rule that record-like content is a WordPress post type. It
also forbids hand-writing an editor screen — markup carrying both `bw-tabs` and
`bw-savebar` outside the library fails the adherence check.

After this change every Clubhouse admin screen is one of three things, and
nothing else:

- **A record editor** — the library, storing to a post. One per club page, one
  per collection record.
- **A settings editor** — the library, storing to options. Global content, and
  Setup.
- **A plain screen** — the design system, no library. Screens that read and act
  rather than edit a record.

The old system is not kept beside the new one. One club uses this plugin, so
each phase migrates that site once and the release after it deletes what it
replaced.

## Decisions taken

| Question | Decision |
| --- | --- |
| How much of the admin? | All of it. Six phases, but one project — the admin is never half one thing and half the other for longer than a release. |
| Where does a club page's content live? | Post meta on its own WordPress page. Records, with revisions and the library's Publish & settings tab. |
| What replaces the Club Pages screen? | WordPress's own Pages list. Edit on a club page opens that page's editor. The Club Pages menu item goes. |
| Setup → Visibility | Kept, as a page controller: a switch per club page, writing that page's status. Per-*section* on/off moves to each panel's own Shown/Hidden switch. |
| The repeaters | The foundation's repeater is widened. Clubhouse does not work around it. |
| The club's own look in wp-admin | Dropped ([#282](../../../../issues/282)). The Base Look is a front-end thing from here on. |
| Link suggestions | Kept, by adding them to the foundation. |
| Migration and rollback | One-off per phase, for one site, then delete the old code. No back-compat layer. |

## 1. What has to land in the foundation first

Three additions to `bluegroup_core_foundation`, in one release, before Clubhouse
touches anything. Each is a general gap, not a Clubhouse special case.

### 1.1 Repeater rows take more than text and number

`Schema::REPEATER_KINDS` is `['text', 'number']` because the browser draws every
cell as a text box. Clubhouse has 13 repeating sections and 12 of them need
something else — only the Home ticker's messages are text alone:

| Cell kind needed | Sections needing it |
| --- | --- |
| `textarea` | History milestones, Values, Ways to help, Benefits, Steps, FAQ questions, Find-us columns, Tiers (features) |
| `media` | News articles |
| `select` | Quick tiles (icon), Tiers (which price it sells, monthly and annual) |
| `toggle` | Tier points (included / not included), Tiers (most popular) |
| `url` | Quick tiles, Find-us columns, Social feed posts |

The first four need the row renderer to draw a textarea, a media picker, a
select and a toggle in a cell, and `Sanitise` to handle each by its own kind
rather than as text.

`url` needs no new kind — `format => 'url'` already exists on `text` and
`Validate` honours it. What phase 1 must confirm is that `format`, and the
suggestions of §1.3, are honoured on a **repeater cell** and not only on a
top-level field. If they are not, that is part of the same change.

### 1.2 A settings screen can supply its own read and write

`Store::for()` sends an `option` screen to `OptionStore`, which reads and writes
one option and nothing else. There is no read hook, no write hook and no
after-save action.

Setup's page controller cannot work inside that. Its 14 switches are not
settings — each one *is* a WordPress page's status, which is what makes a
switched-off page return a proper "not found" rather than a page with nothing on
it. Stored as an option they would be a second copy of that fact, and publishing
a page from the Pages list would make the two disagree with no way to tell which
was right.

So a screen definition gains an optional `read` and `write` callback pair. When
present the library uses them in place of the store, and everything else — the
schema, the capability filtering, the sanitising, the validation, the save bar
and its states — is unchanged. A screen supplying one must supply both.

### 1.3 Link suggestions on a URL field

A `text` field with `format => 'url'` gains an optional `suggestions` list of
label-and-address pairs, offered as the field's `<datalist>` and nothing more —
the input stays free text, because plenty of links point somewhere the plugin
does not own.

Clubhouse fills it from `Link_Catalogue`, the same source the menu editor
already uses, so a link an owner can pick in the menu is a link they can pick
anywhere. This is what Club Pages does today and it is the only part of that
screen's behaviour worth carrying across.

## 2. Club pages become records

### 2.1 The shape

Fourteen club pages carry editable content: Home, About, Membership, Contact,
Sports, Teams, News, Events, Calendar, Bookings, Log in, Privacy, Terms and Club
rules. (Member area is a club page too but has nothing an owner writes.) Each
becomes one record editor screen:

- **Store** — `post`, on the page's own post type.
- **Tabs** — one per group of sections, or none where a page has three panels or
  fewer. The library appends **Publish & settings** itself.
- **Panels** — one per section, carrying today's section label as the title and
  a one-sentence note. Every panel a club can currently switch off is
  `hideable`.
- **Fields** — today's catalogue fields, mapped by kind.

The `Content_Catalogue` is not ported. It is read once, to write the screen
definitions, and then deleted with the rest of the old system.

### 2.2 Field kinds, mapped

| Today | Becomes | Note |
| --- | --- | --- |
| `text` | `text` | |
| `textarea` | `textarea` | |
| `url` | `text`, `format => 'url'`, with suggestions | §1.3 |
| `image` | `media` | |
| `toggle` | `toggle` | Carries its declared default, as it does now |
| `select` | `select` | |
| `shortcode` | `text` | The field only ever held a shortcode string; rendering it is the front end's job and stays there |
| `loop` | `repeater` | Needs §1.1 |

Three sections are `linkout` or `auto` today — a panel of explanatory text
pointing at a collection, with nothing to edit. Those become a panel with a
`facts` or `copytext` field, which is what the library has for display-only
content.

### 2.3 The way in

Club pages are already real WordPress pages. They appear in the Pages list, and
`Club_Page_Editing` already sends Edit to the right place — it keeps doing
exactly that, pointed at the page's own editor screen instead of a tab on Club
Pages. The block editor stays switched off for them and typing `post.php`
directly still redirects, for the same reason as before: the page body is
deliberately empty and anything typed there would never appear.

What changes is that the Pages list becomes the signposted way in rather than a
back door, so it stops being hidden.

### 2.4 What a record gains

The library's Publish & settings tab arrives with the screen: status,
visibility, date, author, slug with its no-redirect warning, excerpt, revisions,
featured image, parent, template and menu order. Three of those replace
Clubhouse code:

- **Status** replaces the bespoke page on/off, and is what Setup's page
  controller writes.
- **Slug** replaces nothing today — a club cannot currently change a page's
  address at all.
- **Revisions** are new. Every word a club writes becomes recoverable.

## 3. Setup

One settings editor screen, storing to options, with six tabs in this order:

1. **Base Look & Branding**
2. **Visibility** — the page controller (§3.1)
3. **Menu**
4. **Members** — the editor for a club's own custom member fields
5. **Settings** — "After signing in", and Emails
6. **Demo mode**

The Members/Settings split is [#283](../../../../issues/283) and the order is
[#284](../../../../issues/284); both are absorbed here rather than done twice.

### 3.1 Visibility, as a page controller

A switch per club page, and nothing else. On means published, off means draft,
and the switch reads the page's real status rather than a copy of it — which is
what §1.2 is for.

Per-section visibility leaves this screen. Each section's on/off is its own
panel's Shown/Hidden switch, on the page that section belongs to, which is where
somebody looking for it would look. `Setup_Sections::inventory()` and its
lockstep test with the catalogue both go.

### 3.2 Menu

The menu editor becomes a repeater: label, target, address, with the library's
own reordering. Indent-to-nest is kept as a field on the row.

This removes the second save bar ([#285](../../../../issues/285)) for free — the
Menu panel has its own "Save menu" button today because it is a separate form
inside the Setup screen, and under the library there is one save bar per screen
whatever tab is showing.

## 4. Collections

The six collections — Sports, Teams, Fixtures, Events, Sponsors, People — are
already registered post types, so they already satisfy the post type rule. What
they lack is the editor: their fields sit in a hand-rolled "Details" meta box on
WordPress's own post screen.

Each becomes a record editor screen, reached from its own list with a row
action, exactly as the foundation's worked example describes. `Collection_Meta`
stays — it is the field definitions and the sanitising, and the screen
definitions are written from it. `Collection_Meta_Boxes` goes, apart from the
admin list columns, which are still WordPress's own list.

The Collections menu item goes with it. WordPress's lists do that job, and
having both is how an owner ends up on the wrong one.

## 5. The remaining screens

Import, Search & sharing, User guide, What's new and Access are not editors.
They read, they explain, and some of them act — none of them edits a record with
a save bar. They move to the design system's components without the library, and
they must not combine `bw-tabs` with `bw-savebar` or the adherence check will
refuse them.

## 6. The club's look leaves wp-admin

Today Club Pages and Setup render in the club's own Base Look — its fonts, its
colours, its accent — so an owner edits roughly in the skin they are editing.
That goes ([#282](../../../../issues/282)). The admin takes its look from the
design system alone, identical on every site, and the Base Look does its job on
the front end only.

`admin-content.css` and `admin-setup.css` are deleted. The design system is
vendored and hash-checked the way the foundation requires:
`.claude/skills/blueworx-admin-design/`, `assets/blueworx-admin-design.css`,
`assets/blueworx-admin-icons.js`, `blueworx-page-editor/` and
`assets/blueworx-page-editor.js`. `assets/bw/` — the older, hand-fetched copy
this plugin uses on the front end — is a separate thing and is not touched here.

## 7. Migration, and deleting the old system

One club uses this plugin. So each phase is a one-off:

1. A migration routine moves that phase's content into its new shape.
2. It runs once on the club's site, and the result is checked against the live
   pages before anything else happens.
3. The next release deletes the code the phase replaced, and the migration
   routine with it.

No back-compat layer, no permanent upgrade path, no rollback flag kept alive.
The old option is left in the database rather than deleted, because it costs
nothing and it is the only copy of the previous state, but nothing reads it.

For phase 3 that migration is: for each of the 14 pages, read its section values
out of `Content_Store` and write them as post meta on that page, keyed by field
id. Global content — header, footer, cookie notice, welcome pack — moves to its
own option, unchanged in shape, because it has no page behind it. The front end
changes where it reads from and not what it renders, so the site looks identical
the moment the phase lands. That is the check.

This is the same approach as [club pages becoming real
pages](../../upgrades/2026-08-21-club-pages-become-real-pages.md), and it gets
the same written record.

## 8. Order of work

Six phases. Each is its own spec, its own plan and its own release, and each
deletes what it replaced once the club's site is across.

| # | Phase | Repo |
| --- | --- | --- |
| 1 | The three foundation additions (§1) | `bluegroup_core_foundation` |
| 2 | Vendor the design system; the Base Look leaves wp-admin (§6) | Clubhouse |
| 3 | Club pages become records (§2); Club Pages screen deleted | Clubhouse |
| 4 | Setup rebuilt (§3), absorbing #283, #284 and #285 | Clubhouse |
| 5 | Collection record editors (§4) | Clubhouse |
| 6 | The remaining screens (§5) | Clubhouse |

Phase 2 is deliberately its own release: it is the one that makes every screen
change appearance at once, and it should not arrive tangled with a change to
what the screens do. Phases 3 to 6 each leave the admin coherent — a screen is
either moved or not, never half.

## 9. Testing

- **Playwright, in the local WordPress harness, per phase.** Each editor screen:
  change a field and the save bar wakes; switch tab and the dirty state
  survives; save invalid and see the field error and no write; save valid and
  see the screen go clean. See [testing.md](../../testing.md) before reaching
  for `test.slow()`.
- **The migration, in PHPUnit.** Every catalogue field arrives at its new
  address with its value and its type intact; a field never saved reads back as
  its declared default and not as an empty string.
- **The page controller.** Switching a page off makes its address return a
  proper "not found"; publishing that page from the Pages list makes the switch
  read as on, because there is only one fact.
- **Front-end parity, phase 3.** The rendered HTML of all 14 pages before and
  after the migration, compared.

## 10. Out of scope

- The front end. Nothing here changes what a visitor sees, except a page's
  status doing what it always did.
- The member area, checkout and profile panel, which use `assets/bw/` on the
  front end and are not admin screens.
- [#261](../../../../issues/261), SureCart sign-in. It deletes the Log in page,
  which removes one of the 14 record editors — whichever lands second is
  smaller for it, and neither blocks the other.
- The block builder. It stays withdrawn, and nothing here revives it: pages are
  still a fixed set of sections a club fills in.
