# What to work on next

The standing priority order for this repo. **The page builder is done, and so
are the bugs behind it.** One thing is left, and it is a person's: the legal
wording each club has to write for itself.

Written 14 August 2026, rewritten 17 August 2026 against main at v0.75.0. Keep
it current: when an issue closes, strike it here.

---

## The running order

Do these in this order. The only ordering that truly matters is the smoke test
before the screens — the rest is a chain because each step's output is the next
step's input.

| # | Do | Why here |
| --- | --- | --- |
| 1 | ~~Merge PR #203~~ | **Done** — merged. |
| 2 | ~~[#208](../../issues/208) Smoke-test the migration on real WordPress~~ | **Done** — all four checks passed on a real install; raised [#211](../../issues/211) and [#212](../../issues/212) along the way. |
| 3 | ~~[#204](../../issues/204) Content → Pages screen~~ | **Done** — v0.72.0. |
| 4 | ~~[#205](../../issues/205) Content → Blocks screen~~ | **Done** — v0.73.0. |
| 5 | ~~[#206](../../issues/206) Repoint import, guide and link picker~~ | **Done** — v0.74.0, alongside the deletion. |
| 6 | ~~[#207](../../issues/207) Delete the old path~~ | **Done** — v0.74.0. |

**What is left:** one thing, and it is a person's. Each club has to replace the
example wording on its own privacy and terms pages before the site takes real
sign-ups — see [#210](../../issues/210). Everything else on this list is done.

**The two bugs the smoke test turned up** are fixed in v0.75.0:
~~[#211](../../issues/211)~~ a hidden page now answers 404, and
~~[#212](../../issues/212)~~ a content item missing a key now leaves a gap
rather than taking the page down.

~~[#209](../../issues/209) One real purchase on a live shop~~ — closed, not
planned. The link format stays confirmed against SureCart's source; the live
checkout is untested by choice.

## 1. The page builder

Spec: `docs/superpowers/specs/2026-08-13-page-composition-and-block-library-design.md`.

| Order | Work | State |
| --- | --- | --- |
| 1 | Registry and stores | **Done** — v0.64.0 |
| 2 | Render pages from blocks, behind a byte-for-byte parity check | **Done** — v0.71.0 |
| 3 | Seeding and migration, so existing club sites upgrade unchanged | **Done** — v0.71.0 |
| 4 | The two admin screens — [#204](../../issues/204) Pages, [#205](../../issues/205) Blocks | **Done** — v0.72.0 and v0.73.0 |
| 5 | ~~[#206](../../issues/206) repoint import, guide and link picker, then [#207](../../issues/207) delete the old path~~ | **Done** — v0.74.0. |

**What 5 cleared up:** the eleven page methods on `Page_Renderer` and the parity
test that measured them; the Club Pages screen and both halves of the seeder's
projection; `Setup_Sections` and the Setup Visibility tab. `Content_Store` and
`Visibility`'s section reads survive, read once by the migration and by nothing
else. The import and the link picker both address blocks now.

## 2. Not code, but blocking a real launch

- [#210](../../issues/210) **Write the privacy and terms wording.** Both pages
  now read as finished pages, but wherever only the club can answer they carry
  worked examples opening "Example wording — replace this with your club's own."
  Every one has to be replaced before the site takes real sign-ups. A wrong
  policy is worse than an obviously unfinished one, so this is not optional.

## The rule

Anything not on this list is below everything on it.
