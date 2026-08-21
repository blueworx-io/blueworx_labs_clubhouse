# Upgrading a live site to v0.86.0

What happens when a site built on v0.85.1 — club pages as rewrite-rule routes,
with nothing in the database behind them — is upgraded to v0.86.0, where each
one is a real WordPress page. Run on the local WordPress harness on 21 August
2026, against a site with content, two pages switched off and one section off.

**The short answer:** nothing is lost. Every page keeps its content, its
address and whether it is on or off. A second upgrade changes nothing. Rolling
the plugin back gives the old site again, with three leftovers listed at the
end.

## What was checked

A site was installed on v0.85.1, given content, and set up as an owner would:
Events and News switched off in Setup, and the welcome section switched off on
Home. Every club address was then captured — the home page, all twelve other
pages, a sport page and a team page — along with the status code each answered.
The same capture was taken after upgrading, after upgrading a second time, and
after rolling back.

## The upgrade

- **Every address answers exactly as it did.** The switched-off pages still
  answer 404, the member area still redirects a signed-out visitor, and every
  other page still answers 200.
- **No content changed.** The only differences in the markup are WordPress's
  own markers for a real page: the `<body>` class becomes `page page-id-…`
  instead of `home blog`, and core adds a `shortlink` tag.
- **On or off carried across.** Events and News were created as drafts, which
  is what "off" now means. Everything else was created published.
- **The old settings are still there.** The visibility option is untouched, so
  nothing has to be rebuilt to go back.
- **Fourteen pages were created**, one per club page, each keeping its own
  address.

## Upgrading twice

The second upgrade is a no-op: the same page ids, the same statuses, and a
byte-identical front end. The repair that carries a site across only acts on a
page whose status disagrees with its flag, so there is nothing left to do the
second time.

## Rolling back

Putting v0.85.1 back gives the old front end: identical content and identical
status codes on every address. Three things stay behind, because rolling back
the code does not undo what the upgrade wrote:

1. **The pages remain.** They are drafts or published pages in the database,
   and the old version ignores them — but see the next point.
2. **The home page carries two canonical tags.** The old version emits its own,
   and WordPress core now emits one for the real page behind it. Both name the
   same address, so this is untidy rather than harmful.
3. **The front page setting still points at the Home page.** Harmless to the
   old version, which routes the home page itself, but it changes one `<body>`
   class on the theme's own pages.

To reverse an upgrade completely, the created pages have to be removed as well.
None of the three loses anything.

## One thing to decide

Club pages are real published pages now, so **WordPress's own page list block
includes them** — the list a default theme's navigation uses. On the harness's
theme that means a 404 page offers Home, About, Sports, Teams, Membership,
Contact, Calendar, Privacy, Terms, and also **Log in, Bookings and Member
area**, which are internal. Switched-off pages are correctly absent, being
drafts.

This is a consequence of the pages being real, not a fault in the upgrade, and
it does not touch the club's own front end, which never uses the theme's
navigation. It only shows on pages the theme draws, such as a 404. Whether the
internal three should be kept out of that list is a decision, not a bug.
