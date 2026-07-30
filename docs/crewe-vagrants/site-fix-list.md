# Crewe Vagrants Squash — site fix list

Reviewed 29 July 2026 against `https://wordpress-878924-5561053.cloudwaysapps.com`
with read-only support access, plus the old site at `crewevagrantssquash.co.uk`.

Three kinds of work, in the order they should be done. **Steps 1 and 2 come
first** — until they are done, nobody can see the real site, including you.

---

## 1. Hosting — the public homepage is serving the old, broken site

**What is happening.** A request to `/` returns a Cloudways Varnish cache **HIT
with an `Age` of over 163,000 seconds** — roughly 45 hours. What it serves is the
*previous* Elementor build of the site. That build's stylesheets belonged to the
`uicore-pro` theme, which is no longer the active theme, so every one of those
CSS files now 404s and the cached page renders as unstyled black text on white.

The current ClubHouse homepage is fine underneath — add any cache-busting query
string (`/?x=1`) and it renders correctly. **Every real visitor is seeing the
broken cached copy.**

**Fix.**

1. Cloudways → Application → **Application Settings → Purge Varnish cache**.
2. Confirm with:
   ```
   curl -sS -D - -o /dev/null https://wordpress-878924-5561053.cloudwaysapps.com/ | grep -i -E 'x-cache|age'
   ```
   You want `X-Cache: MISS` on the first call, and a small `Age` after that — not
   a five- or six-digit one.
3. Either shorten the Varnish TTL or exclude `/` while the site is still being
   built, so this cannot recur between now and launch.

## 2. WordPress — pretty permalinks are switched off

Every link on the site is currently of the form
`/?clubhouse_page=membership`, and `/membership/` returns a **404 from Apache**.

ClubHouse registers rewrite rules for `/about/`, `/membership/`, `/teams/` and
the rest, but WordPress only honours rewrite rules when a permalink structure is
set. With the structure set to Plain, none of them can ever match.

**Fix.** Settings → Permalinks → **Post name** → Save. Saving is what flushes the
rules; you do not need to change anything else. Then re-check `/membership/`.

---

## 3. Content — upload the corrected import file

`clubhouse-import.json` in this folder replaces the demo content that is
currently live. Upload it at **Club Content → Import**, and **leave the
"switch off sections this file has no content for" tick box on**.

It was validated against the plugin's own import parser: 0 warnings, every field
address recognised, 16 images assigned.

What it changes:

| Area | From (live now) | To |
|---|---|---|
| Membership prices | Student £228 / Full £324 / Junior £162 | **Full £295/yr or £28/mo**, **Junior £50/yr or £5/mo**, **Family** (per Eric, 21 July) |
| Contact address | "12 Riverside Lane, Marlow, SL7 1AA" | The Vagrants Ground, Newcastle Road, Willaston, Nantwich, Cheshire CW5 7EP |
| Contact email | `hello@clubhouse.example` | `enquiries@crewevagrantssquash.co.uk` |
| Contact phone | "01628 000 000" | *cleared* — see open question below |
| Members | 150 | 120 |
| Member ages | 5–85 | 5–89 |
| Session times | — | all eight weekly sessions, from the old site |
| Bar hours | — | Mon–Fri 18:00–23:00, Sat 12:00–23:00, Sun 11:00–18:00 |
| Empty image slots | 16 placeholders | real club photos, already in your media library |

**Images used** — all already uploaded, no new files needed:

| Slot | Photo |
|---|---|
| Home hero | `Ladies_5.jpeg` |
| Home "Three courts" band | `Members_1-scaled.jpg` |
| Home news ×3 | `handicap.jpg`, `Team.jpg`, `db9418fe-…-1.jpeg` |
| About hero | `Exhibition_Juniors_1-1.jpg` |
| About facilities band | `Court_2-scaled.jpg` |
| Membership hero | `Ladies_2-scaled.jpg` |
| Contact hero | `IMG_0035-2.jpg` |
| Contact map tile | `Court_2-scaled.jpg` |
| Sports: Squash / Squash 57 | `IMG_0035.jpg` / `Junior_1-scaled.jpg` |
| Teams ×4 | `Team.jpg`, `First_team_1-scaled.jpg`, `Members_2-scaled.jpg`, `Junior_coaching_2.jpg` |

## 4. Branding — set the club identity

Site setup → Branding. The header and footer currently show the **"ClubHouse"
wordmark**, not the club.

- **Logo** → the club crest, already in the library as
  `crewe-vagrants-squash-icon` (attachment 1617).
- **Favicon** → the same crest.
- **Club name** → `Crewe Vagrants Squash`. This also drives the browser tab title
  on every page from v0.39.0 onward.
- **Facebook / Instagram / LinkedIn** → the club's real profiles, or clear them.
  The old site listed Twitter and Facebook; the footer currently shows three
  generic icons.

## 5. Delete the demo fixtures

The Calendar page and the home page's activity tabs currently list **Rugby,
Netball, Hockey, Cricket and Tennis** fixtures — "ClubHouse vs Riverside RFC",
"ClubHouse vs Elmwood" and so on. This is a squash club.

These are seeded demo entries in the **Fixtures** collection. The import file
cannot clear them: the importer deliberately ignores an empty list, on the
grounds that an empty list is usually an oversight. So either:

- **Delete the seven demo fixtures** in wp-admin → Fixtures. From v0.39.0 the
  Calendar and activity sections render nothing at all when there are none,
  rather than an empty heading; or
- **Supply real squash fixtures** and I will add them to the import file — the
  demo entries are then removed automatically on upload.

---

## Open questions for the club

1. **Family membership.** Eric asked whether the 50% discount for up to three
   additional adults at the same address is *built into the system* or handled
   with a promotional code. The import file describes the offer and points people
   at the club to arrange it, because that decision has not been made. Once it is,
   the Family tier's wording and its button should be updated to match.
2. **Club phone number.** The demo number has been cleared rather than replaced,
   because the old site does not publish one. If the club has a number for
   enquiries, it goes in Club Content → Contact → Contact form → Club phone.
3. **Code of conduct.** You said to use the existing Dropbox link — please send
   it. Nothing on the site links to it yet, and there is no document page in
   ClubHouse today, so it will most likely become a link in the footer or on the
   Membership page.
4. **Welcome pack.** Eric is holding off updating it until the fob-access and
   court-booking instructions are final, which depends on the LatePoint work.
   Nothing to do here yet.
5. **Twitter/X.** The old site links one; ClubHouse's branding offers Facebook,
   Instagram and LinkedIn. Confirm which profiles the club actually wants shown.

## Not addressed, by request

**LatePoint.** Court booking is still wired to placeholder links. The "Book a
court" buttons currently point at the Calendar page. To be connected later.
