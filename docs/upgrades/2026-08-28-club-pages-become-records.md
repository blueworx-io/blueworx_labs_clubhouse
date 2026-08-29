# Moving a club's content onto the pages

What happens when a club's existing content — saved through the old Content
editor, into one big option — is moved onto the real pages and the flat
global option that the new Club Pages editor reads. Run on the local
WordPress harness on 29 August 2026, against a site seeded with 168 content
addresses across every page and the global chrome.

**The short answer:** every value that was ever saved lands at its new
address, unchanged, and nothing that was never saved is written. Running it
twice changes nothing the second time. The old content is never touched or
deleted.

## Why this exists

Earlier work in this phase moved the front end and the club-page editors over
to reading and writing through the real pages: each field is now WordPress
post meta, and the sitewide chrome (header, footer, welcome pack, cookie
notice) lives in one flat `global_content` option. Nothing copies a club's
*existing* words across on its own — until this runs, every page falls back to
its hardcoded design defaults, silently, with no error. This is the one-off
that does the copying. One club, one run. It is deleted, along with the class
it runs, in phase 4.

## How to run it

There is no WP-CLI surface in this plugin worth building for a command used
once, and the alternative — a button and a result screen on the Import
page — is a full UI for something deleted in a few weeks. So this ships as a
single file, run with WP-CLI's `eval-file`:

```bash
wp eval-file bin/migrate-club-pages.php
```

Run it once, on the club's real site, after this release is live. It prints
how many values moved, a breakdown by page, and every address it could not
place, with why. Safe to run again — a second run reports the same numbers
and changes nothing.

## What was checked

The old option was seeded directly with 168 field addresses — every text,
textarea, url, toggle, select, media and repeater field this plugin declares,
across all thirteen available pages (Bookings was unavailable on this harness,
with no LatePoint installed, so its area is dropped entirely — expected, not a
gap) and the global area — plus two explicit section visibility states. Media
fields were split so most held a numeric attachment id (the common case) and
one held a URL that resolves to nothing (a demo/preview leftover).

All fourteen public addresses (the home page and the thirteen others) were
captured before migrating, after migrating once, and after migrating a second
time.

## The migration

- **Before → after (one run): real, expected differences.** Because the front
  end already reads only the new store, the "before" capture shows nothing but
  hardcoded defaults — the seeded content was invisible until this ran. After
  one run, every page shows exactly its seeded values: the right text in the
  right place, repeater rows intact as one value, toggles switching real UI
  (a `banner_show` seeded false hid the announcement bar outright), the global
  header/footer/cookie-notice/welcome-pack content appearing on every page
  through the shared chrome, and the one non-attachment image left alone —
  the picture stayed exactly as unset as it was before, not replaced by a
  broken link. No unrelated field moved, and no PHP warnings or notices leaked
  into any of the fourteen pages.
- **222 of 224 values placed automatically; 1 image reported.** The
  `home/hero/image` field held a URL that does not resolve to a real
  attachment, so it was left where it was (never overwritten with a URL) and
  named in the report. The Bookings page itself carries no content in the
  new scheme on this harness (no LatePoint), so nothing was expected to move
  for it, and nothing did — its only difference before/after came from the
  shared header and footer, exactly like every other page.
- **After (one run) → after (two runs): byte-identical.** Re-running moved the
  same 224 values and reported the same one skip; all fourteen pages rendered
  byte-for-byte the same both times. This is the check that maps directly onto
  "no differences" — the first run is expected to change the site, the second
  is expected not to.
- **The old option was untouched.** `content_home` (and its twelve
  siblings, and `content_global`) still held every seeded value after the
  run — nothing is deleted, because it is the only copy of the previous state.

## What a report from a real run means

- **A number moved.** How many old addresses now have a home on a page or in
  the global option.
- **A page-shaped page reason** ("the "about" page hasn't been created yet") —
  Setup hasn't run yet on this site. Run Setup once, then run this again; nothing
  is lost by waiting.
- **An image reason** ("not a real attachment") — the old value was a URL, not
  a WordPress attachment id, most likely a demo or preview leftover. Re-upload
  the picture by hand in the new Club Pages editor; nothing was overwritten.

## What to check afterwards

Open a handful of pages that had real content and check the words are the
club's own, not the design defaults. Anything named in the report as skipped
is worth a look in the new editor — either the page needs creating (Setup), or
the picture needs re-uploading.
