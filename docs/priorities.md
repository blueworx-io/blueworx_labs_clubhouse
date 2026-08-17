# What to work on next

The standing priority order for this repo. **The page builder is the only code
left on the list**, plus three things only a person can do.

Written 14 August 2026, rewritten 17 August 2026 against main at v0.71.0. Keep
it current: when an issue closes, strike it here.

---

## The running order

Do these in this order. The only ordering that truly matters is the smoke test
before the screens — the rest is a chain because each step's output is the next
step's input.

| # | Do | Why here |
| --- | --- | --- |
| 1 | Merge PR #203 | Everything else sits on top of it. |
| 2 | [#208](../../issues/208) Smoke-test the migration on real WordPress | The only unproven part of what is already built. If it mangles a club's saved content, both screens would be built on a broken foundation. An hour with a test install. |
| 3 | [#204](../../issues/204) Content → Pages screen | The bigger screen, and the first thing that lets an owner do something they cannot do today. Its Edit links can land on the old editor until step 4. |
| 4 | [#205](../../issues/205) Content → Blocks screen | Gives those Edit links a home, and stops Club Pages being the only way to change a block's words. |
| 5 | [#206](../../issues/206) Repoint import, guide and link picker | Only worth doing once both screens exist, so guide links point at their final home and the import preview can name the pages a shared block affects. |
| 6 | [#207](../../issues/207) Delete the old path | Last, and only when nothing needs it. Budget the time for rewriting the page tests that call the old methods — that is the bulk of it, not the deletion. |

**Alongside, whenever it suits:** [#209](../../issues/209) the real purchase, as
soon as there is a live shop to try it on, and [#210](../../issues/210) the
privacy and terms wording, which is the club's to write. Neither blocks the
builder; both block a real launch, so do not let them drift to the end.

## 1. The page builder

Spec: `docs/superpowers/specs/2026-08-13-page-composition-and-block-library-design.md`.

| Order | Work | State |
| --- | --- | --- |
| 1 | Registry and stores | **Done** — v0.64.0 |
| 2 | Render pages from blocks, behind a byte-for-byte parity check | **Done** — v0.71.0 |
| 3 | Seeding and migration, so existing club sites upgrade unchanged | **Done** — v0.71.0 |
| 4 | The two admin screens — [#204](../../issues/204) Pages, [#205](../../issues/205) Blocks | **Next.** This is the part that looks like a page builder. |
| 5 | [#206](../../issues/206) repoint import, guide and link picker, then [#207](../../issues/207) delete the old path | After 4 |

**What 4 has to do.** Content → Pages: a page's on/off switch, its blocks in
render order, remove, and a picker that adds an existing block or makes a new
one. Content → Blocks: the library grouped by type, each block saying which
pages use it, with new, duplicate, rename and delete — and a warning naming the
pages before deleting one in use.

**What 5 has to clear up**, all still standing after 4:

- The eleven page methods on `Page_Renderer` are still there. They are what the
  parity test measures the new render against, so they can only go once the
  parity test is retired — which is step 5's job, not step 4's.
- The old Club Pages screen still edits the site, and every save is projected
  onto the blocks (`Block_Seeder::sync`). That projection is interim scaffolding
  and comes out when the new screens take over.
- `Setup_Sections` and the Setup Visibility tab, `Content_Store`'s page content,
  and `Visibility`'s section methods all go at the same time.

## 2. Not code, but blocking a real launch

- [#208](../../issues/208) **Smoke-test the block migration on real WordPress.**
  It cannot run in the DB-free preview, so nothing automated has ever exercised
  it against a club's saved options. Do this one first — it gates the release.
- [#209](../../issues/209) **One real purchase on a live SureCart shop.** The
  link format is confirmed against SureCart's source; only an actual checkout
  proves the basket prefills.
- [#210](../../issues/210) **Write the privacy and terms wording.** Both pages
  carry `ADD:` placeholders wherever only the club can answer, and they have to
  go before the site takes real sign-ups.

## The rule

Anything not on this list is below everything on it.
