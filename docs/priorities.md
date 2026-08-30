# What to work on next

The standing priority order for this repo. Read this first; anything not on it
is below everything on it.

Written 14 August 2026, rewritten 17 August 2026 against main at v0.76.0,
23 August 2026 against v0.87.1, 27 August 2026 against v0.91.1,
28 August 2026 against v0.97.0, and 30 August 2026 against v0.98.0. Keep it
current — the surest way is to close the issue as the pull request merges,
which is what let this list drift the last time.

---

## The admin moves onto the page editor standard

This is the main line of work now, and it is one project in six phases. The
design is [here](superpowers/specs/2026-08-28-page-editor-adoption-design.md);
each phase gets its own plan and its own release.

| Phase | What | State |
| --- | --- | --- |
| 1 | The three foundation additions — a repeater that takes more than text, a screen that owns its own storage, link suggestions | Not started. `bluegroup_core_foundation`. No longer blocking: phase 3 worked around all three locally |
| 2 | Vendor the design system; rebuild the five screens the editor library will not replace | **Done, v0.97.0.** [Plan](superpowers/plans/2026-08-28-design-system-adoption.md) |
| 3 | Club pages become records; the Club Pages screen is deleted | **Done, v0.98.0.** [Plan](superpowers/plans/2026-08-28-club-pages-become-records.md). It did not wait on phase 1 in the end — the three foundation additions were worked around locally |
| 4 | Setup rebuilt on the library, absorbing [#283](../../issues/283), [#284](../../issues/284) and [#285](../../issues/285) | Next. Phase 3 has landed, so nothing is holding it |
| 5 | Collection record editors replace the Details meta box | Waits on phase 4 |
| 6 | The remaining screens | Waits on phase 5 |

[#282](../../issues/282) spans phases 2 to 4 — the club's look leaves wp-admin
one screen at a time, and the last of it goes with Setup. [#287](../../issues/287)
closed with phase 2.

## Also open

| # | Do | Why here |
| --- | --- | --- |
| [#256](../../issues/256) | An honours board page | Club champions, chairpersons, captains, with categories the club sets and two tiers of filters. Phase 3 has landed, so it can be built the new way now — once, rather than the old way twice. |
| [#278](../../issues/278) | Custom member fields as columns on the members list | Ordinary work, independent of the phases above. |
| [#274](../../issues/274) | Put a shop in the CI test harness | Genuinely uncertain: SureCart has to install and authenticate in CI, which nobody has proved yet. |
| [#290](../../issues/290) | A demo page throws "wp is not defined" on real WordPress | One test fails against real WordPress. Found while fixing #288. |
| [#291](../../issues/291) | "Search & sharing" prints its ampersand as an entity | One label, stored pre-escaped and escaped again. |

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

## Done, and worth knowing

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
