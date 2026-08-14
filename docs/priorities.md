# What to work on next

The standing priority order for this repo. Two things are being pushed right now
— **making SureCart actually work**, and **the page builder**. Everything else
waits behind them unless it is dangerous.

Written 14 August 2026, against main at v0.65.0. Keep it current: when an issue
closes, strike it here; when priorities change, change them here rather than in
someone's head.

---

## 1. Make SureCart work

A club cannot take a single payment today. The tiers can sell — that shipped in
v0.65.0 — but there is nowhere to send a buyer, so every tier quietly falls back
to the contact page. This is the shortest path from "demo" to "a club could use
this".

| Order | Issue | Why it is here |
| --- | --- | --- |
| 1 | [#169](../../issues/169) Create the checkout page | The blocker. Nothing else in this section matters until a Join button has a real destination. |
| 2 | [#91](../../issues/91) Hidden basket with a dead Checkout button on every page | Broken furniture shipped site-wide. Likely closes itself once the checkout page exists — check it does. |
| 3 | [#132](../../issues/132) Add to Cart spins forever, never reports an error | The only buy path on the site today, failing silently and retrying several times a second. Re-test with the support window closed; the silent retry is a defect regardless. |
| 4 | [#170](../../issues/170) Create the customer dashboard page | Where a member manages a membership after paying. Needed for a real membership, not for the first sale. |
| 5 | [#90](../../issues/90) Membership has no way to join or pay | The umbrella. Verify and close once 1 and 4 are done and the page's copy matches what actually happens. |
| 6 | [#130](../../issues/130) Every product image is broken | Shop presentation. Real, but nobody reaches the shop yet. |
| 7 | [#131](../../issues/131) Products indexable but unreachable | Partly stale — a Shop page now exists. Re-check before working it. |

**The honest gap.** The SureCart adapter shipped with four guesses that could not
be verified without a live shop: how prices are reached from PHP, what SureCart
calls its checkout page, which hooks fire when a price changes, and whether its
price route answers a logged-out visitor. Each fails safe — a wrong guess means
tiers fall back, not that anything breaks. `docs/integrations/surecart-notes.md`
records what was observed and what was not. **Item 1 is also the first chance to
close those**, so test it end to end, including once in a private window.

## 2. The page builder

Pages are currently fixed recipes in code; an owner can only switch a section
off. The goal is a library of blocks edited once and reused, and a page built by
choosing which blocks it shows.

Spec: `docs/superpowers/specs/2026-08-13-page-composition-and-block-library-design.md`.
Five plans; the first shipped in v0.64.0 and nothing renders from it yet.

| Order | Work | State |
| --- | --- | --- |
| 1 | Registry and stores | **Done** — v0.64.0, `docs/superpowers/plans/2026-08-13-blocks-1-registry-and-stores.md` |
| 2 | Render pages from blocks, behind a byte-for-byte parity check; delete the eleven page methods | Next. The biggest and riskiest step — it rewrites how every page is assembled. |
| 3 | Seeding and migration, so existing club sites upgrade unchanged | After 2 |
| 4 | The two admin screens — Content → Pages and Content → Blocks | **This is the part that looks like a page builder.** After 3 |
| 5 | Repoint import, guide and link picker; delete the old path | Last |

Plans 2–5 have no issues of their own — they are tracked by this file and the
spec. Raise issues for them if that suits how you want to work.

## 3. Everything else, in the order it should be dealt with

**Legal first.** [#121](../../issues/121) — no privacy policy, terms or cookie
notice, while forms collect names, emails and phone numbers. This is the one
item in this section that should jump the queue if the site goes anywhere near
real visitors.

**Then the booking system**, which is a whole subsystem still showing the
vendor's sample data: [#92](../../issues/92) sample data, [#93](../../issues/93)
courts in the wrong cities, [#96](../../issues/96) everything costs £0.00,
[#94](../../issues/94) and [#95](../../issues/95) US phone defaults,
[#97](../../issues/97) US date formats, [#98](../../issues/98) hours that
contradict the footer, [#106](../../issues/106) an old add-on copy still loading
on every page. Then how it behaves: [#101](../../issues/101) no field labels,
[#102](../../issues/102) run-on unannounced errors, [#103](../../issues/103)
"Any coach" silently picking one, [#133](../../issues/133) a modal that cannot be
closed with a keyboard, [#104](../../issues/104) it looks like a different
website, [#105](../../issues/105) it calls coaches agents,
[#136](../../issues/136) booking offered twice two different ways.

**Then content that is visibly wrong:** [#99](../../issues/99) the calendar shows
coaching sessions instead of fixtures, [#100](../../issues/100) the month name
overflows the grid, [#108](../../issues/108) missing hero images,
[#111](../../issues/111) misaligned coach cards, [#163](../../issues/163)
sponsors named "Sponsor 01", [#164](../../issues/164) two About buttons that both
go to the contact page, [#165](../../issues/165) an event card missing its
button.

**Then admin polish:** [#147](../../issues/147) sport filters below what they
filter, [#148](../../issues/148) Save button placement,
[#144](../../issues/144) move the menu builder onto the Clubhouse screen,
[#145](../../issues/145) move Import and the guide there too.

**Then** [#166](../../issues/166) site search, which is a feature rather than a
fix.

## The rule

Anything not on this list is below everything on it. If something new arrives
that genuinely outranks the SureCart work, it goes at the top of section 1 and
this file gets edited to say so.
