# What to work on next

The standing priority order for this repo. **Club pages are becoming real
WordPress pages** — that milestone is what is being worked on now, and it is
first below. The block builder stays withdrawn and Club Pages is the builder.
One older thing is still a person's, not code: the legal wording each club has
to write for itself.

Written 14 August 2026, rewritten 17 August 2026 against main at v0.76.0,
updated 21 August 2026 against main at v0.86.0. Keep it current: when an issue
closes, strike it here.

---

## Now: club pages become real WordPress pages

The milestone in progress. Club pages used to be rewrite-rule routes with
nothing in the database behind them, which cost us the SEO we cannot get back
and a large slice of bespoke code. Each one is a real page now.

| # | Do | Why here |
| --- | --- | --- |
| 1 | ~~[#236](../../issues/236) A real page behind every club page~~ | **Done** — v0.86.0. |
| 2 | ~~[#237](../../issues/237) Serve club pages from their page~~ | **Done** — v0.86.0. |
| 3 | ~~[#238](../../issues/238) Block editor off, Edit goes to Club Pages~~ | **Done** — v0.86.0. |
| 4 | ~~[#239](../../issues/239) The Pages menu back, club pages read-only~~ | **Done** — v0.86.0. |
| 5 | ~~[#240](../../issues/240) Switching a page off makes it a draft~~ | **Done** — v0.86.0, Bulk Edit guard included. |
| 6 | ~~[#241](../../issues/241) Nav and internal links from permalinks~~ | **Done** — v0.86.0. |
| 7 | ~~[#244](../../issues/244) Prove an existing site upgrades cleanly~~ | **Done** — v0.86.1. See [the record](upgrades/2026-08-21-club-pages-become-real-pages.md): nothing is lost, and a second upgrade is a no-op. |
| 8 | [#243](../../issues/243) Retire the rewrite rules | Last. Where the maintenance saving lands; until it is done the plugin runs both systems. |

**Parked, not dropped:** [#235](../../issues/235) and
[#242](../../issues/242), the SEO half of this milestone — what a club page
gives Google and what a shared link looks like. Parked 21 August 2026 as not
needed yet, to be picked up when SEO for club sites matters.

**Also open:** [#245](../../issues/245), test failures unrelated to this work
that make a real regression hard to spot.

## The running order

| # | Do | Why here |
| --- | --- | --- |
| 1 | ~~[#221](../../issues/221) Revert to the old builder~~ | **Done** — v0.76.0. |
| 2 | ~~[#219](../../issues/219) Social feed section, stage one~~ | **Done** — v0.77.0. Posts are pasted in; connecting Facebook or Instagram directly is stage two and still open. |
| 3 | ~~[#231](../../issues/231) One member account page~~ | **Done** — v0.79.0. |
| 4 | ~~Give checkout the member area's own look~~ | **Done** — v0.85.0. It now carries the club's name, crest and terms, in the member area's design, with a checkout form already set up. |

**What is left:** one thing on this list is a person's, and stage two of the social feed ([#219](../../issues/219)) needs Meta app review before it can start. Each club has to replace the
example wording on its own privacy and terms pages before the site takes real
sign-ups — see [#210](../../issues/210). Everything else on this list is done.

**The two bugs the smoke test turned up** shipped in v0.75.0 and survive the
revert: ~~[#211](../../issues/211)~~ a hidden page answers 404, and
~~[#212](../../issues/212)~~ a content item missing a key leaves a gap rather
than taking the page down.

~~[#209](../../issues/209) One real purchase on a live shop~~ — closed, not
planned. The link format stays confirmed against SureCart's source; the live
checkout is untested by choice.

## 1. The block builder, and why it is gone

Built over v0.64.0–v0.75.0 and withdrawn in v0.76.0 ([#221](../../issues/221)).
Pages are composed from the club's own content again, edited on Club Pages,
with the Setup Visibility tab back for taking a section off a page.

The shelved work is on the `block-builder` branch, tagged `v0.75.0-blocks` — the
spec (`docs/superpowers/specs/2026-08-13-page-composition-and-block-library-design.md`)
and both plans stay in the repo so the thinking is not lost. No club site was on
it, so the block options are simply dropped on update and nothing a club wrote
was ever at risk.

## 2. Not code, but blocking a real launch

- [#210](../../issues/210) **Write the privacy and terms wording.** Both pages
  now read as finished pages, but wherever only the club can answer they carry
  worked examples opening "Example wording — replace this with your club's own."
  Every one has to be replaced before the site takes real sign-ups. A wrong
  policy is worse than an obviously unfinished one, so this is not optional.

## The rule

Anything not on this list is below everything on it.
