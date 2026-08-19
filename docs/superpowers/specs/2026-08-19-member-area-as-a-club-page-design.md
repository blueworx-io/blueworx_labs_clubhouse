# The Member Area as a Club Page — Design

**Status:** Approved (design phase)
**Date:** 2026-08-19
**Repo:** `blueworx_labs_clubhouse`
**Builds on:** [#231](../../../../issues/231) / PR #233, which took over SureCart's
customer dashboard by filtering `the_content`. This design moves that work onto
Clubhouse's own page system.
**Related spec:** `docs/superpowers/specs/2026-08-18-member-dashboard-design.md`

## Summary

A club owner should never open WordPress's Pages screen. Clubhouse serves every
page it owns from its own rewrite rules — About, Membership, News and the rest
are not WordPress pages and never have been. The member area is the exception:
it currently lives on the page SureCart seeds, reached through a `the_content`
filter.

This makes the member area a Clubhouse page like any other, at `/member-dashboard/`,
and hides the Pages menu. Checkout and the thank-you page keep SureCart's own
URLs, because SureCart builds every buy-button and cart link from its stored
page id and a broken one costs a club a sale.

## Goals

- The Pages menu is gone from wp-admin. A club manages its site in one place.
- The member area is listed in Club Pages and switchable under Setup, exactly
  like About or Membership.
- The member area has its own address, `/member-dashboard/`, on the club's own
  domain and permalink structure.
- Anyone who bookmarked SureCart's dashboard page still arrives somewhere right.
- Nothing a club has written is deleted.

## Non-Goals

- **Deleting any WordPress page.** Decided by Luke: hide, never delete. A club
  may have written something onto a page years ago, and no plugin update should
  be able to destroy it. Hiding is reversible; deleting is not.
- **Moving checkout or order confirmation.** They keep SureCart's pages and
  SureCart's URLs. Decided against because SureCart builds its links from its
  own stored page ids in several places, and each one we failed to intercept
  would put a buyer on a 404 mid-purchase.
- Restyling the member area. It keeps the BlueWorx admin look — decided by Luke.
- The shop, collection and product pages — [#232](../../../../issues/232).

## Key Decisions

| Decision | Choice | Rationale |
|---|---|---|
| WordPress pages | Hidden, never deleted | Decided by Luke. A club's own writing is not ours to destroy, and hiding is reversible. |
| Checkout and confirmation | Stay on SureCart's pages | The money path is the one place a missed link costs real money. |
| Member area address | `/member-dashboard/`, a Clubhouse route | Decided by Luke. Same rewrite system as every other club page, so it inherits visibility, links and the nav for free. |
| Look | BlueWorx admin, full width, no club header or footer | Decided by Luke. The member area reads as one BlueWorx product across every club rather than as part of each club's site. |
| SureCart's dashboard page | Kept, hidden, redirects to `/member-dashboard/` | SureCart's own account links and any member's bookmark still land in the right place. |
| Signed-out visitors | Sent to Clubhouse's own `/login/` | Today they get SureCart's login form, which looks nothing like the club. Clubhouse already owns a login page; use it. |
| The header button | "Log in" signed out, "Member area" signed in | Decided by Luke. The way in and the way back are the same button, so a member never hunts for their account. Signing out lives inside the member area. |

## Architecture

**`Page_Map`** gains one entry: slug `member-dashboard`, label "Member area",
method `member_dashboard`. That single line is what puts the page into the
rewrite rules, Club Pages, the visibility toggles and the link catalogue —
the existing machinery does the rest.

The page is registered unconditionally, like every other. It does not declare a
`requires`: the member area stands up with neither SureCart nor LatePoint
installed, because it still carries the club's welcome pack and the way back to
the site.

**`Page_Renderer::member_dashboard()`** renders it, taking the same arguments as
every other page method. Unlike them it does not call `shell_header()` or
`shell_footer()` — it returns the BlueWorx frame as a complete screen. The view
routing, the panels and the empty states are already built: it delegates to
`Member_Dashboard` and `Dashboard_Shell` exactly as the `the_content` filter
does today.

**Assets.** Clubhouse pages are served `base.css` plus the club's look
stylesheet. This page is served `assets/bw/bw.css` instead. `Frontend`'s
enqueue decision learns one rule: for this slug, the member area's stylesheet
replaces the look's. The two design systems still never meet.

**`Member_Dashboard`** loses its `the_content` filter. Its view routing, panel
rendering and empty states stay exactly as they are and are called by the page
method instead. Deleting the filter also deletes the re-entrancy guard, the
`owns()` page-id comparison and the welcome pack stand-down — all three exist
only because we were filtering someone else's page.

**A redirect** sends SureCart's dashboard page to `/member-dashboard/`, carrying
any `?view=` through, so a bookmark and SureCart's own account links both land
right. It runs on `template_redirect` and only for that exact page id.

**The Pages menu** is removed with `remove_menu_page( 'edit.php?post_type=page' )`.
The pages themselves are untouched: still in the database, still served, still
reachable by direct URL for anyone who types one. Removing the menu is what Luke
asked for and is the reversible half of the change.

**The header button** already switches on login state — it reads "Log in" signed
out and "Log out" signed in. Signed in it now reads **Member area** and points at
`/member-dashboard/`; signed out it is unchanged. Signing out moves inside the
member area, which already carries a nonced sign-out link, so the action is not
lost, only moved to where a member goes to manage everything else about their
membership. The main nav gains nothing: the member area is a members-only screen
and does not belong in a list every visitor sees.

**`Commerce_Pages` is unchanged.** Checkout and order confirmation keep their
`the_content` dressing and their SureCart URLs.

## What a club sees

Club Pages lists Member area beside About, Membership and the rest. Setup →
Visibility can switch it off, which takes the address to a proper 404 like any
other switched-off page. The Pages menu is gone.

## What a member sees

Signed out, the header offers **Log in**. Signed in, that same button reads
**Member area** and opens `/member-dashboard/` — the same screen as today, at a
club address instead of SureCart's. Signing out is inside that screen. A
signed-out visitor who types the address is sent to the club's own login page
rather than SureCart's form. Old bookmarks redirect.

## Testing

**PHP unit tests** cover the new `Page_Map` entry appearing in the page list and
the rewrite registration; the renderer returning the member area's frame without
the club's header or footer; the enqueue decision choosing `bw.css` over the
look stylesheet for this slug and only this slug; and the redirect deciding
correctly for the dashboard page id, for a `0` id, and for any other page; and
the header button reading "Log in" to `/login/` signed out and "Member area" to
`/member-dashboard/` signed in.

**A Playwright spec** against the real WordPress harness covers the address
serving the member area, a switched-off page answering 404, a signed-out visitor
reaching the login page, the redirect from the old dashboard page carrying
`?view=` through, the header button taking a signed-in member to the member area
and a signed-out visitor to the login page, and the Pages menu being absent from
wp-admin.

The existing member-area tests move with the code; the ones asserting the
`the_content` takeover go, because that path goes.

## Risks

- **`Page_Map` is held in lockstep by tests** with the content catalogue, the
  setup sections and the link catalogue. Adding a page trips those until each is
  updated. That is the machinery working, not a problem, but it is the bulk of
  the work.
- **The member area is the first Clubhouse page that renders no club chrome.**
  Anything assuming every clubhouse page has a header — the SEO head, the nav
  highlighting, the skip link — needs checking rather than assuming.
- **Hiding the Pages menu hides it from everyone**, including anyone who needs to
  reach a page WordPress itself relies on. Direct URLs still work, and nothing is
  deleted, so the escape hatch exists for whoever knows to use it.

## Open questions

None. The nav question is settled above: the member area is reached from the
header button, not from the main nav.
