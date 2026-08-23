# What to work on next

The standing priority order for this repo. Read this first; anything not on it
is below everything on it.

Written 14 August 2026, rewritten 17 August 2026 against main at v0.76.0 and
23 August 2026 against v0.87.1. Keep it current — the surest way is to close
the issue as the pull request merges, which is what let this list drift the
last time.

---

## Next

Five issues are open. Two are ready to start, one cannot start yet, and two are
parked on purpose.

| # | Do | Why here |
| --- | --- | --- |
| 1 | [#232](../../issues/232) Own the shop, collection and product pages | The largest thing genuinely open, and the last stretch of a road already half built — the checkout, the order confirmation and the member area carry the club's own look, while the shop pages around them still do not. |
| 2 | [#220](../../issues/220) Full design update for the admin backend | Every screen an owner uses to run their site. Nothing is broken, so it waits behind work that changes what a visitor sees. |

The order of those two is a judgement, not a decree. Swap them if the admin
screens are what a club is actually complaining about.

**Cannot start yet:** [#229](../../issues/229), pulling the social feed
straight from Facebook or Instagram. It needs Meta app review before a line of
it can be written. Posts are pasted in by hand until then, which works.

**Parked, not dropped:** [#235](../../issues/235) and
[#242](../../issues/242) — what a club page gives Google, and what a shared
link looks like. Parked 21 August 2026 as not needed yet. Club pages are real
WordPress pages now, so an SEO plugin can reach them whenever this is picked
up; that was the hard part and it is done.

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

**The test suite is trustworthy again** ([#245](../../issues/245), v0.87.1). A
wp-admin screen is a couple of hundred requests against a server that answers
one at a time, so slow-but-passing specs were being reported as failures. See
[testing.md](testing.md) before reaching for `test.slow()` on a new one.

[#209](../../issues/209), one real purchase on a live shop, was closed as not
planned. The link format stays confirmed against SureCart's source; the live
checkout is untested by choice.

## The block builder, and why it is gone

Built over v0.64.0–v0.75.0 and withdrawn in v0.76.0 ([#221](../../issues/221)).
Pages are composed from the club's own content again, edited on Club Pages,
with the Setup Visibility tab back for taking a section off a page.

The shelved work is on the `block-builder` branch, tagged `v0.75.0-blocks` — the
spec (`docs/superpowers/specs/2026-08-13-page-composition-and-block-library-design.md`)
and both plans stay in the repo so the thinking is not lost. No club site was on
it, so the block options are simply dropped on update and nothing a club wrote
was ever at risk.
