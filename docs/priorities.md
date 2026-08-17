# What to work on next

The standing priority order for this repo. **The page builder is the only code
left on the list**, plus three things only a person can do.

Written 14 August 2026, rewritten 17 August 2026 against main at v0.73.1. Keep
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
| 5 | [#206](../../issues/206) Repoint import, guide and link picker | **Part done** — guide repointed and per-block sanitising in v0.73.1. The import and the link picker are left, and are cleanest done with step 6 rather than before it: while the content store is still there, the import would have to be written twice. |
| 6 | [#207](../../issues/207) Delete the old path | **Next, and the big one.** Budget the time for rewriting the page tests that call the old methods — that is the bulk of it, not the deletion. |

**One thing to know before step 6.** Club Pages and the Blocks screen both write
the same site from different ends, and each is now written back to the other so
neither undoes the other (v0.73.1). Both halves — `Block_Seeder::sync` and
`Block_Seeder::project` — come out together in step 6. Taking one without the
other loses a club's words.

**Alongside, whenever it suits:** [#209](../../issues/209) the real purchase, as
soon as there is a live shop to try it on, and [#210](../../issues/210) the
privacy and terms wording, which is the club's to write. Neither blocks the
builder; both block a real launch, so do not let them drift to the end.

**Two bugs the smoke test turned up**, neither caused by blocks and neither
blocking the builder: [#211](../../issues/211), a hidden page answers 200 with
the WordPress theme instead of 404, and [#212](../../issues/212), a content item
missing a key takes the whole page down rather than leaving a gap.

## 1. The page builder

Spec: `docs/superpowers/specs/2026-08-13-page-composition-and-block-library-design.md`.

| Order | Work | State |
| --- | --- | --- |
| 1 | Registry and stores | **Done** — v0.64.0 |
| 2 | Render pages from blocks, behind a byte-for-byte parity check | **Done** — v0.71.0 |
| 3 | Seeding and migration, so existing club sites upgrade unchanged | **Done** — v0.71.0 |
| 4 | The two admin screens — [#204](../../issues/204) Pages, [#205](../../issues/205) Blocks | **Done** — v0.72.0 and v0.73.0 |
| 5 | [#206](../../issues/206) repoint import, guide and link picker, then [#207](../../issues/207) delete the old path | **Next.** The guide is done; the import and link picker go with the deletion. |

**What 5 has to clear up**, all still standing:

- The eleven page methods on `Page_Renderer`. They are what the parity test
  measures the new render against, so they can only go once the parity test is
  retired.
- The old Club Pages screen still edits the site, and every save is projected
  onto the blocks (`Block_Seeder::sync`) — with `Block_Seeder::project` writing
  the other way so the Blocks screen's edits survive it. Both are interim, and
  both come out together.
- `Setup_Sections` and the Setup Visibility tab, `Content_Store`'s page content,
  and `Visibility`'s section methods all go at the same time.
- The import still writes through `Content_Store`, and the link picker still
  addresses page-and-section. Both move onto blocks here rather than earlier —
  see [#206](../../issues/206).

## 2. Not code, but blocking a real launch

- [#209](../../issues/209) **One real purchase on a live SureCart shop.** The
  link format is confirmed against SureCart's source; only an actual checkout
  proves the basket prefills.
- [#210](../../issues/210) **Write the privacy and terms wording.** Both pages
  carry `ADD:` placeholders wherever only the club can answer, and they have to
  go before the site takes real sign-ups.

## The rule

Anything not on this list is below everything on it.
