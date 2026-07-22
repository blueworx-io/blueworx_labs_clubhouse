# Cross-cutting UI consistency — design

**Date:** 2026-07-22
**Status:** Approved, ready for planning
**Slice:** 1 of 4 (slice 0 = look CSS parity, shipped in 0.26.5)

## Problem

The UX/UI review of 2026-07-21 found the same actions labelled differently across
pages, two competing social-link treatments, dead `href="#"` links shipping to
users, and one titled section missing the eyebrow every other section has. These
are cross-cutting: each is one small change repeated in a few places, and settling
them first fixes the vocabulary the page-structure and look-character slices build
on.

All front-end markup is in `includes/render/class-sections.php`; per-page
composition is in `includes/render/class-page-renderer.php`; internal links
resolve through `Blueworx_Clubhouse_Links::url($key)`; branding data comes from
`Blueworx_Clubhouse_Branding`.

## Goals

- One social-link treatment across the whole site, driven by real data.
- One label for the membership-join action everywhere.
- No `href="#"` reaches a rendered page.
- The Sponsors section matches every other titled section.
- A guardrail so dead links and the retired treatment cannot silently return.

## Non-goals (deferred, not dropped)

- **Tier-card behaviour** — the "Join" destination cross-talk (home cards →
  membership, membership cards → contact) and making whole cards clickable. The
  review groups these under structure; they are slice 2.
- **Font pairing** — a per-look identity choice, folded into the look-character
  work (item 3).
- **Contact-intent CTAs** — "Register interest", "Ask a question", "Book a visit",
  "Enquire about hire", "Volunteer" are genuinely distinct actions that happen to
  share the contact destination. They are left as-is; the review's CTA complaint
  is specifically the membership-join sprawl.
- Admin-editable legal/social fields. Adding settings is its own task.

## A. Social links — one treatment

Two treatments exist today:

- **Pill buttons** — `Sections::social()` (`class-sections.php:735-752`), rendering
  `ch-social__link` with branded SVG marks and **real URLs** from
  `Branding::get_facebook_url()` / `get_instagram_url()` / `get_linkedin_url()`.
  Called by the home and contact `social` sections.
- **Letter-circles (F/I/L/C/S)** — two independent loops, in `footer()`
  (`class-sections.php:471-475`) and `contact_form()` (`class-sections.php:623-627`),
  each emitting single-initial circles with a hardcoded `href="#"`, fed a hardcoded
  five-name array (`renderer:156`, `renderer:679`) that includes "Community" and
  "Share" — not networks at all.

Planning confirmed **Branding has no data behind the letter-circles** — no email,
phone, or address getter exists, and the five names are literals. The pill
treatment is the only one with real data.

**Change:** retire the letter-circles. Extract the pill link-list into a helper
and use it wherever social links appear.

- New `Sections::social_links(array $urls): string` renders the `ch-social__link`
  pill for **each non-empty URL only** — a club that clears its Instagram URL
  shows no Instagram pill, rather than a dead one. `$urls` is an ordered map of
  `network => url` so icon and label stay paired.
- `social()` uses `social_links()` for its link list (no visual change to the
  home/contact social bands).
- `footer()` and `contact_form()` call `social_links()` in place of their
  letter-circle loops. The `ch-footer__social` and `ch-contact__social` classes
  and their `href="#"` are removed.
- The hardcoded five-name arrays at `renderer:156` and `renderer:679` are removed;
  those callers pass the three real URLs instead (or nothing — the helper handles
  an empty set by rendering no pills).

CSS: `ch-footer__social` / `ch-contact__social` rules become dead and are removed
from the look stylesheets. `ch-social__link` already lives in `base.css` (slice 0),
so the pills are styled under every look in both new locations for free.

## B. CTA labels — one label per action

The membership-join action is reached by six different labels: "Join the Club"
(header, capitalised), "Explore membership", "Choose your tier →", "Join the club"
(tiles/heroes), and bare "Join" on cards. Standardise on **"Join the club"**.

**Single source of truth:** a `Blueworx_Clubhouse_Cta` holder exposing the labels
used in more than one place, so they cannot drift again:

```php
final class Blueworx_Clubhouse_Cta {
    public const JOIN = 'Join the club';   // membership-join action, sitewide
}
```

The renderer references `Blueworx_Clubhouse_Cta::JOIN` at every membership-join
site currently reading "Join the Club" (`renderer:147` header), "Explore
membership" (`renderer:229`), "Choose your tier →" (`renderer:299`), and the
existing "Join the club" occurrences (about hero `renderer:409`, closing bands,
login alt `renderer:731`). The trailing arrow on band CTAs is presentation, added
by the band, not part of the label.

Distinct contact intents keep their own labels (see non-goals). Tier-card "Join"
labels are slice 2.

## C. Dead links

Every `href="#"` from the inventory, with its disposition:

| Link | Current | Disposition |
|---|---|---|
| "Become a sponsor" (home sponsors) | `#` plain text link | → `Links::url('contact')`, promoted to a pill (§D) |
| "Open in Maps" (home Find us) | `#` | → a maps URL built from the address string |
| Privacy / Terms / Club Rules / Safeguarding (footer) | `#` ×4 | **Hidden** — no destinations exist |
| Latest News cards ×3 | `<a href="#">` | Rendered as a non-link `<article>` — content stays, dead affordance goes |
| "Forgot password?" (login) | `#` | **Hidden** — no reset flow is wired |
| Footer / contact letter-circles | `#` | Removed with §A |

**Open in Maps:** the address is a hardcoded two-line string
(`renderer:352`, and `renderer:676` for contact). Build
`https://www.google.com/maps/search/?api=1&query=<urlencoded address>` from it via
a small pure helper, `Sections::maps_url(array $addressLines): string`, so it is
unit-testable and the encoding is done once. If the address is empty the link is
omitted, not rendered dead.

**Legal links:** `footer()` renders the legal row from a `legal` array
(`renderer:178-181`). Pass an empty array; `footer()` omits the row entirely when
empty (guard added). The four labels are not lost data — they were always dead —
so hiding is correct until real pages or an admin field exist.

**News cards:** `news_cards()` wraps each card in `<a class="ch-news__card"
href="#">` (`class-sections.php:385`). Change to `<article class="ch-news__card">`.
The look CSS targets the class, not the element, so styling is unaffected; only
the broken click affordance is removed. Hover-lift that implies clickability is
also dropped for these cards.

## D. Sponsors eyebrow and CTA

`sponsors()` (`class-sections.php:453-462`) is the only titled home section with no
`ch-eyebrow`. Every sibling — `card_grid`, `image_band`, `band`, `activity_tabs`,
`news_cards`, `social` — renders one.

- `sponsors()` gains an `ch-eyebrow` span above its heading, matching the sibling
  pattern.
- Its caller (`renderer:371-374`) passes `eyebrow => 'Our partners'` (or similar)
  alongside the existing heading.
- "Become a sponsor" changes from a plain `ch-link` to a pill (`ch-btn`
  treatment), consistent with every other section CTA, and points at Contact (§C).

## Guardrail

A PHPUnit test, `FrontEndLinkHygieneTest`, renders every front-end page body
(`Page_Map::render()` for each slug, as the existing render tests do) and asserts:

1. **No `href="#"`** appears in any rendered page. This is the durable enforcement
   of "no dead links ship" — the same shape as the look-parity guardrail.
2. **The retired classes are gone** — no `ch-footer__social` or
   `ch-contact__social` in any output.
3. **The membership-join label is consistent** — the strings "Explore membership"
   and "Choose your tier" do not appear in any rendered page (they were the sprawl;
   their absence proves the canonicalisation held), and `Cta::JOIN` does.

Renders are cheap and DB-free, so this covers all pages in milliseconds. A
Playwright assertion is not needed — `href="#"` is a static-output property.

## Testing and verification

- `FrontEndLinkHygieneTest` passes.
- Existing suites stay green: PHPUnit, `npm test` (27), `npm run test:wp` (43),
  PHPCS.
- Manual pass over home and contact under all three looks via demo mode: social
  pills present in footer and contact panel, no letter-circles, sponsors eyebrow
  present, "Open in Maps" resolves.
- `npm run build:zip` clean.
- Version bump to 0.27.0 (a user-facing feature-level change across the site) with
  a club-owner-voiced changelog entry.

## Risks

| Risk | Mitigation |
|---|---|
| Removing letter-circle CSS changes footer layout under a look | The pills replace them in the same slot; visual check under all three looks before merge |
| A club with only some social URLs set gets an uneven pill row | `social_links()` renders only non-empty URLs — intended; an empty set renders nothing |
| Hiding legal links leaves a visibly empty footer region | `footer()` omits the row when the legal array is empty, so there is no empty container |
| "Open in Maps" address encoding is wrong | `maps_url()` is a pure helper with unit tests covering multi-line and empty addresses |

## Where this sits

Slice 1 of the 2026-07-21 UX review. Slice 2 is page structure and flow
(home section order, tier-card behaviour, About timeline, Membership above-the-fold,
the no-op filter pills). Slice 3 is style unification (the three hero components,
the empty `.ch-media--empty` block). Item 3 (look character) folds in the font
pairing.
