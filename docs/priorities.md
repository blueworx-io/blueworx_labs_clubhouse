# What to work on next

The standing priority order for this repo. The SureCart work and the legal pages
are done; **the page builder is now the thing being pushed**, and the booking
system is the biggest pile of real bugs behind it.

Written 14 August 2026, updated the same day against main at v0.67.0. Keep it
current: when an issue closes, strike it here; when priorities change, change
them here rather than in someone's head.

---

## 1. Make SureCart work — **done**

Shipped in v0.66.0 and v0.67.0. The whole section is closed, and the reason none
of it worked was not on this list at all: **the plugin never recognised SureCart**.
It looked for two symbols the plugin does not have, so every real site concluded
there was no shop, which silently switched off tier prices, checkout links and
everything else to do with selling. One line, and it is why membership tiers
never sold on a live site despite working in testing.

| Issue | Outcome |
| --- | --- |
| [#169](../../issues/169) Create the checkout page | Done, but not as written. SureCart seeds its own pages, so Clubhouse detects a missing or trashed one and offers to repair it by calling SureCart's own seeder. |
| [#91](../../issues/91) Dead Checkout button in the basket | Closed by the above — the button was empty only because there was no checkout page. |
| [#132](../../issues/132) Add to Cart spins forever | Not ours. The retry loop is SureCart's own JavaScript; this plugin ships no front-end script that makes requests. Re-check once the site is live with a connected shop. |
| [#170](../../issues/170) Customer dashboard page | Page is created and members land on it after signing in. **Still open** for one thing: a member who navigates away has no link back — the header has a single slot, currently "Log out". |
| [#90](../../issues/90) Membership has no way to join or pay | Done. The page now reads its promise off the tier buttons a visitor can see, so it never promises "join in five minutes" above "register your interest". |
| [#130](../../issues/130) Every product image is broken | Not ours. SureCart serves product images from its own CDN and there is no setting to change that; the 404s mean those products have no image in the SureCart account. |
| [#131](../../issues/131) Products indexable but unreachable | Done. The shop's pages are link targets and the default nav carries a Shop item on sites that have one. |

**The honest gap is closed.** All four unverified guesses were settled by reading
SureCart's own source rather than probing a live site — two were right, two were
wrong. `docs/integrations/surecart-notes.md` records which, and the lesson:
download the vendor's plugin before designing around a guess.

**Still to prove:** one real purchase on a live shop. The link format is
confirmed against SureCart's source, but only an actual checkout proves the
basket prefills.

## 2. The page builder — now the top of the list

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

**Legal — done.** [#121](../../issues/121) shipped in v0.67.0: privacy and terms
pages linked from every footer, and a cookie notice that says what the site
actually uses cookies for without pretending to block anything. Both pages carry
starter wording marked `ADD:` wherever only the club can answer — **those lines
still need writing before the site takes real sign-ups.**

**So the booking system is next**, which is a whole subsystem still showing the
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
