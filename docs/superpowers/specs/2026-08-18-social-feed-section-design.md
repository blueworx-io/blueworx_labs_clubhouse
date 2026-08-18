# Social Feed Section — Design

**Status:** Approved (design phase)
**Date:** 2026-08-18
**Issue:** [#219](../../../../issues/219)
**Repo:** `blueworx_labs_clubhouse`

## Summary

A club can show its latest Facebook **or** Instagram posts on the Home page. The
section is edited under Club Pages like every other section, ships hidden so no
existing club site changes on update, and caches whatever it fetches so a page
load never waits on Meta.

The work is staged. Stage one ships the section, the cache and the failure
handling against a **manual source** — post links the club pastes. Stage two
replaces that source with a **Meta connection** behind the same interface. The
visible feature therefore lands without being blocked on Meta app review, and
the connection swaps in underneath without the section, the editor or the tests
changing shape.

## Goals

- One section, on Home, showing recent posts: image, caption, date, link back.
- One platform at a time — Facebook or Instagram, never both.
- Cached, so a page render never makes an outbound call.
- Three distinct failure states, each handled differently (see below).
- Ships hidden; a club opts in.
- No new dependency.

## Non-Goals

- Both platforms at once.
- Comments, likes, reactions, or any engagement data.
- Posting *to* social from the site.
- A cron-driven refresh. The cache refreshes on read, as the SureCart price
  cache does; a second scheduling style is not worth the surface area.
- Stage two's Meta app review, business verification and privacy paperwork —
  real work, tracked separately, and not code.

## Key Decisions

| Decision | Choice | Rationale |
|---|---|---|
| Connection model | One Meta app we own, clubs click Connect (stage two) | Clubs cannot realistically create their own Meta developer app and re-paste a token every 60 days. The cost lands on us once rather than on every club forever. |
| Staging | Manual source first, Meta source behind the same interface later | App review is slow and outside our control. Nothing else in the feature needs to wait for it. |
| Caching | Transient + short failure marker + last-good option | Already proven in `class-surecart-products.php`. Reusing it beats inventing a second caching style. |
| Refresh | On read, no cron | Matches the existing pattern; no scheduled-event lifecycle to get wrong on activation, deactivation and update. |
| Page | Home | Where a visitor sees that the club is active. News was the alternative and stays open if a club asks. |
| Default visibility | Hidden | An empty feed section on every existing club site would be a regression shipped by update. |
| HTTP | WordPress core functions | No new entry in `approved-deps.json`. |

## Architecture

A new `includes/social/` folder holding four things.

**`interface-feed-source.php`** — one method, `posts(): array`, returning
normalised posts. This is the seam the whole staging plan rests on.

**`class-manual-feed-source.php`** — stage one. Reads the post links a club has
pasted from the content store and turns them into normalised posts. No network
call.

**`class-meta-feed-source.php`** — stage two. Talks to the Graph API using the
stored connection and normalises the response to the same shape.

**`class-social-feed.php`** — the cache in front of whichever source is active,
and the only thing the renderer talks to.

### The normalised post

Every source returns the same shape, so the renderer never learns which platform
it is drawing:

| Field | Notes |
|---|---|
| `id` | Stable per post; used as the render key. |
| `image` | Absolute URL, or empty for a text-only post. |
| `caption` | Plain text, truncated at render, never trusted as HTML. |
| `date` | ISO 8601, formatted at render in the site's timezone. |
| `permalink` | Absolute URL back to the post on the platform. |

A record missing `id` or `permalink` is dropped rather than rendered — the same
defensive stance `looks_like_a_price()` takes, and for the same reason: the
last-good option has no expiry and nothing else validates it once written.

### Caching

Three stores, copied deliberately from the SureCart price cache:

- A **transient** holding the last good fetch, for the normal path.
- A **short failure marker** so a platform outage does not re-hit the API on
  every page load for the whole TTL.
- A **last-good option, no expiry**, read only when a fetch has already failed.
  Not scoped to the plugin version — keying it to the version would empty the
  safety net on every release, which is the exact failure it exists to prevent.

Unlike prices, the feed is identical for every visitor, so there is no
logged-in/logged-out cache context to split on.

## Editing

A new **Social** section on the Home tab of Club Pages:

- Platform — a select, Facebook or Instagram.
- Heading and blurb — as every other section has.
- How many posts to show.
- The connection. Stage one: the post links, pasted. Stage two: a Connect
  button and a "connected as…" line. Section content is untouched by that
  swap.

The section is added to `SECTION_DEFAULTS` in `class-visibility.php`, which
ships it hidden. That mechanism exists today and is currently unused; this is
the first section to use it as intended.

## Failure states

Three states, deliberately not collapsed into one.

**Not connected yet.** The section renders nothing on the front end and Club
Pages says why. A heading over an empty space reads as a broken site, which is
worse than no section.

**Fetch failed, but we have fetched before.** The last good posts stay up, with
nothing said to the visitor. A club should not lose its feed because Meta had a
bad minute.

**Fetch failed and we have never succeeded.** Nothing renders, and the admin
says the connection needs attention. Distinct from the first state because the
club's action is different: connect it, versus fix it.

## Testing

**PHP unit tests** drive the cache and all three failure states through an
injected fetcher, gated on `BLUEWORX_CLUBHOUSE_RUNNING_TESTS` exactly as
`set_raw_fetcher()` is — the seam must be a no-op on every real request.

**A Playwright spec** covers the section rendering against the DB-free preview,
the hidden default, and the not-connected state.

## Open questions

- Which Meta permissions stage two actually needs, and what app review demands
  of us, is unknown until we file. It does not block stage one.
- Facebook Page posts and Instagram Business media come from different Graph
  endpoints with different shapes. Both normalise to the table above, but
  whether they need one source class or two is a stage two decision, made with
  the real responses in front of us rather than guessed now.
