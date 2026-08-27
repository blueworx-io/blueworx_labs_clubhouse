# What to work on next

The standing priority order for this repo. Read this first; anything not on it
is below everything on it.

Written 14 August 2026, rewritten 17 August 2026 against main at v0.76.0,
23 August 2026 against v0.87.1, and 27 August 2026 against v0.91.1. Keep it
current — the surest way is to close the issue as the pull request merges,
which is what let this list drift the last time.

---

## Next

Four issues are open here. One is ready and waiting on a session somebody is
watching, two are ordinary work, one cannot start yet, and one is parked.

| # | Do | Why here |
| --- | --- | --- |
| 1 | [#261](../../issues/261) Use SureCart's sign-in instead of our own login page | The decision is made and the scoping is done. It is first because it deletes a whole parallel auth stack — sign-in, forgot password, reset, and the reset email — that we otherwise keep maintaining beside SureCart's. |
| 2 | [#268](../../issues/268) A What's New screen in plain English | Small, and the only way a club owner ever finds out what a release changed. The changelog it reads is already written for them. |
| 3 | [#256](../../issues/256) An honours board page | Club champions, chairpersons, captains, with categories the club sets and two tiers of filters. The largest of the three, and the only one that adds a page. |

**#261 needs somebody watching it.** SureCart is not in the local WordPress
test harness, so the sign-in journey cannot be exercised before merge — and it
gates every member's access on every site. Two findings from scoping it are on
the issue: the Setup "after signing in" setting survives the change, and making
the login page disappear cleanly on a shop-less site is the wide part, not the
form swap.

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
Pages are composed from the club's own content again, edited on Club Pages,
with the Setup Visibility tab back for taking a section off a page.

The shelved work is on the `block-builder` branch, tagged `v0.75.0-blocks` — the
spec (`docs/superpowers/specs/2026-08-13-page-composition-and-block-library-design.md`)
and both plans stay in the repo so the thinking is not lost. No club site was on
it, so the block options are simply dropped on update and nothing a club wrote
was ever at risk.
