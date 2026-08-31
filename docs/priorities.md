# What to work on next

The standing priority order for this repo. Read this first; anything not on it
is below everything on it.

Written 14 August 2026, rewritten 17 August 2026 against main at v0.76.0,
23 August 2026 against v0.87.1, 27 August 2026 against v0.91.1,
28 August 2026 against v0.97.0, 30 August 2026 against v0.98.0 and again
against v0.100.0, and 31 August 2026 against v0.101.6. Keep it current — the
surest way is to close the issue as the pull request merges, which is what let
this list drift the last time.

---

## What to do next

| # | Do | Why here |
| --- | --- | --- |
| [#256](../../issues/256) | An honours board page | Club champions, chairpersons, captains, with categories the club sets and two tiers of filters. **Designed and waiting on three answers** — [the spec](superpowers/specs/2026-08-31-honours-board-design.md) has them at the end, and they are two minutes' reading. Nothing should be built until they are settled: two of the three change the markup and the addresses. |
| [#274](../../issues/274) | Put a shop in the CI test harness | Deferred 30 August 2026 — the shop is hand-checked before a release, and that is accepted. Genuinely uncertain anyway: SureCart has to install and authenticate in CI, which nobody has proved yet. |

**Cannot start yet:** [#229](../../issues/229), pulling the social feed
straight from Facebook or Instagram. It needs Meta app review before a line of
it can be written. Posts are pasted in by hand until then, which works.

**Parked, not dropped:** [#242](../../issues/242) — what a club page gives
Google, and what a shared link looks like. Parked 21 August 2026 as not needed
yet. Club pages are real WordPress pages now, so an SEO plugin can reach them
whenever this is picked up; that was the hard part and it is done.

**Moved out of this repo, 27 August 2026.** Owning the shop, collection and
product pages, and the full admin backend redesign, both belong to the
WordPress enhancement plugin rather than to Clubhouse. They are
[blueworx_labs_wordpress#183](https://github.com/blueworx-io/blueworx_labs_wordpress/issues/183)
and [#184](https://github.com/blueworx-io/blueworx_labs_wordpress/issues/184)
now. They were the top two on this list until they moved, so nothing here is
waiting on them.

## One decision waiting: the guardrail that is only warning

CI's `admin_ui_adherence` check reports without failing
([`.github/workflows/ci.yml`](../.github/workflows/ci.yml)). Its note used to
say "take this back off with phase 4". Phase 4 has landed, the whole admin is
on the design system, and it still cannot be taken off — so the reason is
written down here once, rather than chased again.

Run over the whole plugin on 31 August 2026 it raised 23 findings. What is
actually left is three things, and none of them is a screen to rebuild:

- **The club's own front end, judged as an admin screen.** The check decides
  what is an admin file by looking for `class="… wrap …"`, and the front end's
  own `ch-wrap` contains that word. So `Page_Renderer` and `Sections` are read
  as admin screens and told off for their hand-written colours and icons. Any
  pull request touching the front end would fail on rules that do not apply to
  it. Fixing it means tightening the check in `bluegroup_core_foundation`,
  which every project shares — Luke's call, not a Clubhouse one.
- **The WordPress admin menu icon.** WordPress takes that as an inline SVG data
  URI; there is no way to give it the design system's icon component, so the
  finding has no action behind it.
- **The shop-pages warning.** It is a real WordPress admin notice, shown on
  every screen in wp-admin, so it wears WordPress's notice classes on purpose —
  the design system's stylesheet is not loaded outside Clubhouse's own screens,
  and a `bw-notice` there would arrive unstyled. Narrowing where the warning
  appears is a change to what an administrator is told, not a change of clothes.

The findings that were genuinely ours are fixed: the owner's dashboard panel
([#307](../../issues/307), v0.101.3), which had been drawing as bare text.

## Done, and worth knowing

### The admin is on the page editor standard, and that project is finished

All six phases have landed, and there is no half-and-half left. It was the main
line of work from 28 August to 31 August 2026. The design is
[here](superpowers/specs/2026-08-28-page-editor-adoption-design.md); each phase
got its own plan and its own release, and this table is the record.

| Phase | What | State |
| --- | --- | --- |
| 1 | The three foundation additions — a repeater that takes more than text, a screen that owns its own storage, link suggestions | **Done**, in `bluegroup_core_foundation`, and vendored here. Checked 30 August 2026: `Schema::REPEATER_KINDS` carries textarea, select, toggle and media; `Store::for()` honours a screen's own read and write; suggestions are honoured on a repeater cell. The vendored copy is byte-identical to the foundation's, so none of it was a local workaround |
| 2 | Vendor the design system; rebuild the five screens the editor library will not replace | **Done, v0.97.0.** [Plan](superpowers/plans/2026-08-28-design-system-adoption.md) |
| 3 | Club pages become records; the Club Pages screen is deleted | **Done, v0.98.0.** [Plan](superpowers/plans/2026-08-28-club-pages-become-records.md). It did not wait on phase 1, which was already in the foundation by then |
| 4 | Setup rebuilt on the library, absorbing [#283](../../issues/283), [#284](../../issues/284) and [#285](../../issues/285) | **Done, v0.100.0.** [Plan](superpowers/plans/2026-08-30-setup-rebuilt-on-the-library.md). No migration was needed: the screen reads and writes through the stores that already owned every value |
| 5 | Collection record editors replace the Details meta box | **Done, v0.101.0.** [Plan](superpowers/plans/2026-08-30-collection-record-editors.md). The meta keys moved with it — [the record](upgrades/2026-08-30-collections-become-records.md) has what happens on update and on a rollback |
| 6 | The remaining screens | **Done, v0.97.0** — by phase 2, not after it. Import, Search & sharing, User guide, What's new and Access are the five screens the editor library will not replace, and phase 2's plan converted all five (tasks 3 to 7). Checked 31 August 2026: each one opens through `Admin_Shell` and the design system's own components. This row said "next" until then, which was simply wrong |

[#282](../../issues/282) spanned phases 2 to 4 — the club's look left wp-admin
one screen at a time, and the last of it went with Setup, along with
`admin-setup.css`. [#287](../../issues/287) closed with phase 2.

### Everything else

**The import reads the page fields** ([#294](../../issues/294), v0.101.2). It
used to keep a second declaration of every field — `Content_Catalogue`, with
`Content_Sanitiser` beside it — held against `Page_Fields` by a lockstep test.
Both are deleted. `Page_Fields::sections()` is the flat view everything outside
the editors reads: the import's allow-list, the prompt it writes for an AI, the
sections an import switches on and off, and the menu's list of anchors. An
imported value is cleaned by the page editor library's own `Sanitise` — the
same code a save runs through — so a file really is treated as form input now
rather than nearly.

**The six collections are records** (phase 5, v0.101.0). Sports, Teams,
Fixtures, Events, Sponsors and People are edited on their own screens, opened
from their own lists. The "Details" meta box is gone.

**Two menus, not one** ([#311](../../issues/311), v0.101.6). Phase 5 folded the
six lists into the Clubhouse menu and dropped the Collections menu. That buried
Setup: WordPress opens a menu at its first child, the first child became the
Sports list, and the code that hides the fourteen page editors was deleting
Setup's own row along with theirs — so from v0.101.0 to v0.101.5 there was no
way to reach Setup by looking. Clubhouse is Setup, Import, Search & sharing,
User guide and What's new; Collections is the six lists and Global content.

**Their meta keys moved, and that is the thing to remember.** A team's sport
was stored as `sport` and is now `clubhouse_team_sport`, because the page
editor library derives the key from the post type and the field id and has no
per-field override. One place knows it — `Collection_Meta::meta_key()` — and
every read and write goes through there. Reach for that whenever you touch a
collection field, and never write the key by hand.

**Setup is a page editor screen** (phase 4, v0.100.0). Six tabs, one save bar,
and the menu saves with everything else ([#285](../../issues/285)). What a club
asks its members has a tab of its own ([#283](../../issues/283)) and the tabs
are in the order [#284](../../issues/284) asked for. The club's own look is out
of wp-admin altogether ([#282](../../issues/282)).

**Nothing moved in the database to do it.** Setup's values were never one
option — they live in the look registry, Branding, Visibility, Menu,
Profile_Store, Auth_Settings, Mail_Settings and Demo_State — so the screen
supplies its own read and write (`Setup_Storage`) instead of a store, and every
existing setter and side effect stays where it was. That is the pattern to
reach for whenever a screen's values are spread across stores that already work.

**Three things went with the old screen, on purpose.** The live re-skin as you
pick a look (there is no club look in wp-admin to re-skin), the setup progress
bar, and the ten preset colour swatches (the library's colour control is the
browser's own). A low-contrast second colour is still allowed and now says so
in the field's help, because the library has field errors and no warning
channel.

**A club page is edited on the page itself** (phase 3, v0.98.0). The Club Pages
screen is deleted. Three things it quietly owned went with it and needed homes
first: the header menu's save, the reminder about pictures an import could not
fetch, and an owner's route into a page at all.

**That route is the thing to remember.** Deleting Club Pages locked owners and
content editors out of every page (fixed in v0.99.2). The page capabilities had
been stripped from both roles precisely because that screen existed, and
WordPress then refused them the Pages list — which is now the only way into a
page's editor. Nothing caught it because every browser spec signed in as an
administrator, who holds every capability WordPress has and so can never notice
a role missing one. There is an owner in the harness now
([owner-edits-a-club-page.spec.js](../tests/owner-edits-a-club-page.spec.js));
use it whenever a change touches what a role can reach.

**Custom member fields show as columns on the members list**
([#278](../../issues/278), v0.99.0), sortable, with private fields withheld from
anybody who could not already read them.

**Club pages are real WordPress pages.** They used to be rewrite-rule routes
with nothing in the database behind them, which cost the SEO we cannot get back
and carried a large slice of bespoke code. Each one is a real page now, found
the way WordPress finds any page, switched off by becoming a draft, and linked
from its own permalink. The bespoke routing is gone
([#236](../../issues/236)–[#241](../../issues/241), [#243](../../issues/243),
v0.86.0–v0.87.0). An existing site upgrades cleanly and a rollback loses
nothing — [the record](upgrades/2026-08-21-club-pages-become-real-pages.md) has
what happens either way.

**The member area and the checkout are the club's own.** One member account
page, and a checkout that carries the club's name, crest and terms in the
member area's design ([#231](../../issues/231), v0.79.0–v0.85.0).

**The social feed, stage one** ([#219](../../issues/219), v0.77.0). Posts are
pasted in. Stage two is #229 above.

**The privacy and terms wording** ([#210](../../issues/210)) is written. It was
the one thing on this list that was a person's job rather than code.

**The member area does what its buttons say** ([#257](../../issues/257),
[#260](../../issues/260), v0.90.0–v0.91.0). Every write link inside it —
update billing, add a card, payment history, open an order, change or cancel a
plan — led back to the same read-only screen and did nothing, because the block
that reads the address and dispatches was the one this plugin replaced. A
member can also see and change their own name, email and password now, which
the member area never offered at all.

**The shop's pages look after themselves** ([#259](../../issues/259),
[#269](../../issues/269), v0.88.2, v0.91.1). The order confirmation page is
created as soon as Clubhouse and the shop are both installed, whichever order
they arrived in. It used to be reported as missing with no way to fix it, then
briefly needed a button press.

**Email comes from the club** ([#258](../../issues/258), v0.89.0), rather than
from "WordPress" at a wordpress@ address that was identical on every site.

**The test suite is trustworthy again** ([#245](../../issues/245), v0.87.1). A
wp-admin screen is a couple of hundred requests against a server that answers
one at a time, so slow-but-passing specs were being reported as failures. See
[testing.md](testing.md) before reaching for `test.slow()` on a new one.

[#209](../../issues/209), one real purchase on a live shop, was closed as not
planned. The link format stays confirmed against SureCart's source; the live
checkout is untested by choice.

## The block builder, and why it is gone

Built over v0.64.0–v0.75.0 and withdrawn in v0.76.0 ([#221](../../issues/221)).
Pages are composed from the club's own content again, edited on the pages
themselves, with each section switched off on its own panel.

The shelved work is on the `block-builder` branch, tagged `v0.75.0-blocks` — the
spec (`docs/superpowers/specs/2026-08-13-page-composition-and-block-library-design.md`)
and both plans stay in the repo so the thinking is not lost. No club site was on
it, so the block options are simply dropped on update and nothing a club wrote
was ever at risk.
