# What to work on next

The standing priority order for this repo. **The block builder has been
withdrawn and Club Pages is the builder again.** One thing is left, and it is a
person's: the legal wording each club has to write for itself.

Written 14 August 2026, rewritten 17 August 2026 against main at v0.76.0. Keep
it current: when an issue closes, strike it here.

---

## The running order

| # | Do | Why here |
| --- | --- | --- |
| 1 | ~~[#221](../../issues/221) Revert to the old builder~~ | **Done** — v0.76.0. |
| 2 | ~~[#219](../../issues/219) Social feed section, stage one~~ | **Done** — v0.77.0. Posts are pasted in by hand. |
| 3 | ~~Monthly / annual membership prices~~ | **Done** — v0.78.0. |
| 4 | ~~Welcome pack as a banner~~ | **Done** — v0.76.2. |

**What is left:** one thing on this list is a person's. The automatic social feed ([#229](../../issues/229)) is parked until Meta app review and the connect endpoint exist. Each club has to replace the
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
