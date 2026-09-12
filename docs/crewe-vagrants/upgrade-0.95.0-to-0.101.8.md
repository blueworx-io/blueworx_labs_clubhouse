# Crewe Vagrants — upgrading the live site from 0.95.0 to 0.101.8

Rehearsed on 12 September 2026 (and again on v0.101.9, below) on the local WordPress harness: a fresh site on
0.95.0, the club's own import file loaded, Setup filled in, images placed, then
the plugin files swapped for 0.101.8 exactly as an in-place update does it.

**The short answer:** nothing is lost, but the club's words disappear from
the site the moment the new files land and only come back when a one-off
migration is run. So the update and the migration have to happen together.

## What the rehearsal showed

- Every page — home, about, sports, teams, membership, contact, news,
  calendar, privacy, terms, rules, login, the switched-off events and
  booking pages — answered with the same status code before and after.
- After the migration, every page's HTML was **byte-identical** to the
  0.95.0 capture. The one exception was a deliberately fake image address,
  which the migration reported and left empty rather than guessing.
- Setup (club name, colours, look, social links, sender address, menu, member
  questions, which pages and sections are on or off) carried across with no
  work: its storage did not change between the two versions.
- Sports, teams, fixtures, events, sponsors and people carried across on the
  first wp-admin visit. Old values stay in place as a spare copy.
- Every admin screen opened with no PHP errors, and the team editor showed the
  club's own values.
- Running the migration a second time changed nothing.

**Between the files updating and the migration running, the site shows the
design's demo words** ("One club, every sport", "Aisha Khan", "£28/month")
instead of the club's. That window is the whole risk. It is not data loss —
the old content is untouched — but visitors would see it.

## How the window was closed

From v0.101.9 the plugin runs that move itself, on the first request after
the files update, and never again. So the update is one zip upload; nobody
needs SSH, and the club's words are on the site from the first page load.
The WP-CLI script stays for a re-run, and the report of the automatic run is
kept in the option `clubhouse_content_migration_report` for support to read.

## Runbook

1. Take a Cloudways backup of the application. This is the undo.
2. Tell the club: a short blip, and that anything they edit that day should
   wait until it is done.
3. Build the zip from `main` (`npm run build:zip`) and confirm its version is
   0.101.9 or later.
4. Plugins → Add New → Upload → choose the zip → "Replace current with
   uploaded". Do not deactivate and reactivate: reactivating puts demo
   entries back into any collection the club has emptied.
5. Open wp-admin once (the sports, teams and the rest move on that visit).
6. Cloudways → Application settings → Purge Varnish cache.
7. Check, signed out: home, about, membership, sports, teams, contact,
   calendar read as the club's own words; membership shows the annual price
   first (new in 0.96.0). Signed in: Clubhouse → Setup opens and shows the
   club's name and colours.

## If it goes wrong

- Words wrong or missing on a page: copy `bin/migrate-club-pages.php` to the
  server and run `wp eval-file` on it. It rewrites the same values from the
  untouched old copy and prints why anything was skipped.
- Anything worse: restore the Cloudways backup, or reinstall the 0.95.0 zip
  (build it from commit `f3ba56c`). The old version ignores everything the
  new one wrote. Only edits made after the update would be lost.

## Known, not caused by the update

- In 0.101.8 picking "Collections" from the Ctrl+K command search on the new
  screens opens "Cannot load clubhouse-content". The sidebar menu is fine. Worth
  fixing before the club notices, but it is there on a fresh install too.
- Pictures the July import could not fetch are still outstanding on the live
  site (the reminder moves to the page editors and points at the right page).
