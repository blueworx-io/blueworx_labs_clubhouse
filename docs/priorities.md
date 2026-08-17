# What to work on next

The standing priority order for this repo. **The page builder is the only code
left on the list**, plus three things only a person can do.

Written 14 August 2026, rewritten 17 August 2026 against main at v0.71.0. Keep
it current: when an issue closes, strike it here.

---

## 1. The page builder — the whole list

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
