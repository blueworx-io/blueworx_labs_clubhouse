# Moving a club's content onto the pages

What happens when a club's existing content — saved through the old Content
editor, into one big option — is moved onto the real pages and the flat
global option that the new Club Pages editor reads. Checked with a PHPUnit
round trip and a real run on the local WordPress harness, most recently on
29 August 2026 after a review found two ways content could go missing
silently; both are fixed and checked below.

**The short answer:** every value the migration can place, it places
unchanged, type included. Content behind a plugin that is not installed
(Bookings without LatePoint, sign-in without a shop) is named in the report
rather than silently dropped or silently moved nowhere. Running it twice
writes the same values. The old option is never touched or deleted, so a bad
run can always be re-run from the same source.

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

**A value-level round trip, in PHPUnit — the permanent check.** An earlier
version of this check rendered pages as HTML before and after migrating and
diffed them, on the theory that nothing should change. That theory doesn't
hold: the front end already reads only the new store, so "before" is always
the hardcoded defaults regardless of what the old option holds, and an HTML
diff cannot tell a value placed correctly from one silently dropped, folds
`false`, `''` and `0` together, and says nothing about a field no renderer
prints. It also isn't permanent — it lived in throwaway scripts against a
disposable harness, gone the moment the harness was.

`ContentMigrationTest::test_every_declared_field_round_trips_through_the_migration`
replaces it: every address `Page_Fields` declares — 195 of them, with both
integrations active so Bookings and sign-in are included — is seeded with a
value of its own kind (text, textarea, url, toggle both ways, select, a
numeric image id, two-row repeaters), the migration is run once, and every
one is read back through `Page_Content::get()`/`get_items()` and compared
with `assertSame()` — the exact PHP type, not just an equal-looking value, so
a toggle that came back as a truthy string instead of `true` would fail this
where it would pass a looser check. It also asserts nothing was skipped in
that run. Reproduce it with:

```bash
vendor/bin/phpunit --filter test_every_declared_field_round_trips_through_the_migration
```
```
OK (1 test, 199 assertions)
```

Eight further tests in the same file cover the cases the round trip
deliberately doesn't: a field never saved (nothing written, nothing
reported), a page with no post behind it, an image URL that resolves to a
real attachment, one that doesn't, a media field an owner deliberately
cleared (not a false alarm), a repeater surviving a second run without
doubling, a global panel's Shown switch carrying a club's real Setup choice
across, and content behind an unavailable integration being named rather than
dropped. `vendor/bin/phpunit --filter ContentMigrationTest` runs all 18.

**A real run, on the local WordPress harness, for the shape of the report an
operator actually sees.** With both fixes below deliberately exercised:

```
wp eval-file bin/migrate-club-pages.php
```
```
224 value(s) moved onto the pages.

By page:
  global: 22  home: 45  about: 29  membership: 27  contact: 23  login: 3
  news: 7  sports: 11  teams: 11  events: 12  calendar: 13  privacy: 7
  terms: 7  rules: 7

2 address(es) skipped:
  home/hero/image — not a real attachment
  booking/hero/title_lead — the Bookings plugin is not active — activate it and run this again
```
(This harness carries content seeded across several earlier sessions, so the
224 is specific to it, not a number to expect on a fresh site — the round
trip above is the number to trust for "does every kind move correctly".)

## Two ways content could have gone missing silently — found in review, fixed, checked

**Content behind a plugin that isn't installed.** The migration originally
walked `Page_Fields::areas()`, which drops a whole area (Bookings, Log in)
when its plugin isn't active, and drops individual panels the same way (the
Calendar's booking slot, without LatePoint). Real content sitting under those
old addresses was invisible to both sides of the check that used to exist —
the seed script walked the same filtered list the migration did — so it
could vanish with the report saying nothing was skipped. Fixed by walking
`Page_Fields::all_areas()`, the undropped set, and reporting every address
that still holds real content there. Checked on the harness above
(`booking/hero/title_lead`, reported with the plugin named) and in
`test_content_behind_an_unavailable_area_is_reported_not_dropped` and
`test_content_behind_an_unavailable_panel_is_reported_not_dropped`.

**The sitewide header and footer's Shown switch.** The Global content editor
writes its own panel switches to the flat `global_content` option, but the
migration was writing them there while reading the club's actual choice from
the wrong place — `home.header`/`home.footer` is where Setup really stores
it — and the front end's header/footer gate was reading a third, different
address again. A club that had switched its header or footer off in Setup
would get it back, silently, the moment this ran. All three now agree: Setup
→ read from `home.<section>`, migration → written under `global`, front end
→ read from `global`. Checked on the harness above: switching the footer off
through the same storage Setup uses, then migrating, correctly leaves
`global_content['footer__shown']` false and the footer markup absent from the
rendered page — present before the run, gone after. Also checked in
`test_a_global_panel_switched_off_in_setup_keeps_its_state`.

## What a report from a real run means

- **A number moved.** How many old addresses now have a home on a page or in
  the global option.
- **An integration reason** ("the Bookings plugin is not active — activate it
  and run this again") — the page or section needs a plugin this site doesn't
  have active. Nothing was lost; the old value is still in the old option and
  will move the next time this runs, once the plugin is on.
- **A page reason** ("the "about" page hasn't been created yet") — Setup
  hasn't run yet on this site. Run Setup once, then run this again; nothing
  is lost by waiting.
- **An image reason** ("not a real attachment") — the old value was a URL,
  not a WordPress attachment id, most likely a demo or preview leftover.
  Re-upload the picture by hand in the new Club Pages editor; nothing was
  overwritten.

## If the run goes wrong

The old option is the escape hatch, and it is never touched or deleted by
this migration, so there is always a way back:

- **Wrong values landed on a page.** Re-run it — it is idempotent, so a bad
  first attempt does not need undoing before trying again; every write
  simply overwrites with the same source values.
- **Something needs to come off a page entirely.** Clear that field in the
  new Club Pages editor. The old option still holds the original value if it
  needs re-checking.
- **Everything needs undoing.** Nothing this migration wrote can corrupt the
  old option, so the source of truth is intact; clearing the affected post
  meta (or `global_content`) by hand and re-running reproduces exactly the
  same result. There is no separate "undo" command, because there is nothing
  destructive to undo — only the new pages' content to overwrite again.

## What to check afterwards

Open a handful of pages that had real content and check the words are the
club's own, not the design defaults. Anything named in the report as skipped
is worth a look in the new editor — activate the plugin it names, create the
page in Setup, or re-upload the picture, then run the migration again.
