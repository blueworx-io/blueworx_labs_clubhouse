# Social Feed — Meta Connection (stage two) — Design

**Status:** Approved (design phase). Build deferred — planned now, built later.
**Date:** 2026-08-18
**Issue:** [#219](../../../../issues/219) (stage two)
**Repo:** `blueworx_labs_clubhouse`
**Follows:** `2026-08-18-social-feed-section-design.md` (stage one, shipped v0.77.0)

## Summary

A club clicks **Connect**, picks its Facebook Page or Instagram account, and its
recent posts appear on the Home page and keep appearing — fetched once a day in
the background, never during a page view. The section, the editor and the cache
are already built; this replaces what feeds them.

Stage one's pasted links stay as an alternative a club can choose, so a club
that will not connect an account still has a feed, and a club whose connection
breaks has somewhere to fall back to.

## Goals

- A club connects once and posts keep arriving with nobody touching the site.
- Facebook Page posts or Instagram Business media — one platform at a time.
- Fetched daily in the background. No page render ever waits on Meta.
- A broken connection loses the club nothing on the front end and is said
  plainly on the Clubhouse screens.
- No new dependency in the plugin.

## Non-Goals

- Comments, likes or any engagement data.
- Posting to social from the site.
- Storing post images on the club's site (see Images).
- Personal Instagram accounts. Meta closed the no-review route in 2024; this is
  the Business/Graph path for both platforms, and there is no way around it.
- Meta app review, business verification and the privacy paperwork. Real work,
  outside this repo, and tracked separately.

## The constraint everything else follows from

Meta's OAuth handshake ends in an exchange that needs the **app secret**. This
plugin is installed on club servers, so it can never hold that secret. Meta also
only redirects back to URLs registered in the app, and every club is a different
domain.

So one small thing of ours has to exist on the internet: a **connect endpoint**
on a Blueworx domain that performs the handshake and hands the resulting token
to the club's site. It stores nothing, and it is not in the path of any page
view — while it is down, existing feeds carry on and only new connections wait.

This is not the relay service considered and rejected: the token lives on the
club's site, the fetching happens on the club's site, and no post data ever
reaches us.

## Key Decisions

| Decision | Choice | Rationale |
|---|---|---|
| Meta app | One app Blueworx owns | A club committee cannot realistically register a developer app and re-paste a token. The cost lands on us once rather than on every club forever. |
| Handshake | A connect endpoint on a Blueworx domain | The app secret cannot ship inside a plugin, and per-club redirect URLs cannot be registered by hand. Smallest thing that makes one shared app possible. |
| Token custody | Stored on the club's own site | Nothing of the club's is held by us, which keeps the app-review story simple and means a Blueworx outage cannot take a club's feed down. |
| Refresh | Once a day, in the background | No visitor ever waits on Meta, not even the unlucky first one after a cache expiry. A club feed is not news; daily is fresh enough. |
| Images | Linked from Meta, never stored | Decided by Luke: no copies in the media library. See Images for what this costs and how it degrades. |
| Breakage | Said on the Clubhouse screens only | No email to a stale club admin address, no site-wide banner to learn to dismiss. The front end keeps its last posts either way. |
| Pasted links | Kept, as a choice a club makes | A club that will not connect still gets a feed, and a broken connection has somewhere to fall back to. |
| Source shape | One `Meta_Feed_Source` with two mappers | Facebook and Instagram differ only in field names once normalised; two classes would duplicate the token handling and the error handling that actually matter. |

## Architecture

Stage one's seam does its job: `Social_Feed` and `Sections::social_feed()` are
untouched. Four new things sit behind the interface.

**`includes/social/class-meta-connection.php`** — what the club connected: the
platform, the account id, the display name, and the token. Reads and writes one
option that is not autoloaded, and answers `is_connected()` and `token()`. The
only class that touches the stored token.

**`includes/social/class-meta-client.php`** — the Graph API. One method per
platform, each returning raw records or null, plus the distinction between "no
posts" and "this connection is refused". Nothing above it knows a URL.

**`includes/social/class-meta-feed-source.php`** — implements
`Blueworx_Clubhouse_Feed_Source`. Asks the client, maps whichever platform's
records into the normalised post shape, and returns null when the fetch failed
so the existing cache does the right thing.

**`includes/social/class-meta-connect-controller.php`** — the admin side.
Renders Connect and Disconnect on Club Pages, receives the token handed back by
the connect endpoint, and schedules and clears the daily refresh.

**The connect endpoint** is not in this repo. One serverless function on a
Blueworx domain (`blueworx_service_meta_connect`, its own repo, its own plan):
it holds the app id and secret, runs Facebook Login, exchanges the code for a
long-lived token, fetches the Page or Instagram account the club chose, and
redirects back to the club's site with the token and account details on a
one-time, signed, short-lived handoff. It writes nothing down.

### The daily refresh

A WordPress scheduled event, `blueworx_clubhouse_social_feed_refresh`, runs once
a day, fetches, and writes the result into the caches `Social_Feed` already
reads. It is scheduled when a club connects, cleared when it disconnects, and
cleared on plugin deactivation — the three lifecycle points a scheduled event
usually gets wrong.

Reads never fetch when a club is connected: `Social_Feed` serves the cache, or
the last good posts, and if nothing has ever succeeded the section renders
nothing. That is exactly the behaviour stage one already has and tests.

A **Refresh now** button sits beside Connect for a club that has just posted
something and does not want to wait — the same fetch, run on demand.

### Images

Post images are linked straight from Meta. Those URLs are signed and expire,
Instagram's within days, so the daily refresh is what keeps them alive: in
normal running they are replaced long before they go stale.

When fetching fails for several days in a row, the stored URLs expire and the
pictures stop loading. A card whose image fails falls back to the plain tile the
section already draws for a text-only post, so a stale feed loses its pictures
quietly rather than showing broken-image icons. The text, the date and the link
out keep working throughout.

This is the accepted cost of not storing copies.

## Failure states

Stage one's three states stay exactly as they are. The connection adds a fourth
fact — not a fourth state — which the admin needs and the visitor does not.

**Not connected.** As now: nothing renders, and Club Pages says why.

**Fetch failed, posts we have shown before.** As now: the last good posts stay
up and the visitor is told nothing.

**Fetch failed, never succeeded.** As now: nothing renders, and the admin is
told the connection needs attention.

**The connection is refused rather than merely failing** — the token was
revoked, the password changed, the Page permissions were removed. Meta answers
this differently from an outage, and it must be treated differently: it will
never recover on its own. The front end still shows the last good posts, and
Club Pages says the account needs reconnecting, with the Connect button back.
Retrying a refused token every day for a year would be pointless traffic, so the
daily refresh stops until somebody reconnects.

## Editing

The Social feed section on Club Pages gains a source choice: **Connected
account** or **Pasted links**. Choosing Connected shows either the Connect
button, or "Connected as *Marlow RFC* on Facebook" with Disconnect and Refresh
now beside it, and the date of the last successful fetch. Choosing Pasted links
shows the existing list, untouched.

Everything else on the section — platform, heading, blurb, how many posts —
stays exactly as it is.

## Privacy and what we hold

The club's site holds the token and the posts. The connect endpoint holds
nothing beyond the seconds a handshake takes. Blueworx never stores a club's
posts, tokens or audience data, which is what the app review submission will
say, and it has the merit of being true.

The app submission needs a privacy policy URL and a data-deletion URL on a
Blueworx domain. Both are copy, not code, and belong with the review paperwork.

## Testing

**PHP unit tests** cover the mapping of real recorded Facebook and Instagram
responses into normalised posts, the refused-versus-failed distinction, the
connection's storage and clearing, and the scheduling and unscheduling of the
daily event. The Graph client is injected, exactly as the feed source is today —
no test ever calls Meta.

**Fixtures come from a real fetch**, recorded once against our own club Page and
Instagram account with the app in development mode, and committed. Guessing
Meta's field shapes is how the SureCart integration shipped broken for months.

**A Playwright spec** covers the editor: the source choice, the not-connected
state, the connected state, and the reconnect notice.

## What is deferred until the app exists

Everything here can be built and tested against our own accounts with the Meta
app in development mode. App review and business verification gate other clubs
switching it on, not the work.

## Open questions

- Whether Meta's device-login flow could replace the connect endpoint entirely
  is unverified. If it grants the Page permissions we need, it would remove the
  one hosted piece from this design. Worth a half-day spike before building, not
  worth guessing now.
- Which permissions app review actually grants, and what it demands of us, is
  unknown until we file.
