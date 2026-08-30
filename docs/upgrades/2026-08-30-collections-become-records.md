# Collections become records — what happens on update

**Version:** v0.101.0 · **Phase 5 of six** of [the page editor adoption](../superpowers/specs/2026-08-28-page-editor-adoption-design.md).

## What changed

The six collections — Sports, Teams, Fixtures, Events, Sponsors, People — are
edited on their own page editor screens instead of a hand-rolled "Details" meta
box on WordPress's post screen. Each is opened from its own list, exactly as
before.

## What moves in the database

Every collection field moves from a bare meta key to the one the page editor
library reads:

| Before | After |
| --- | --- |
| `sport` on a team | `clubhouse_team_sport` |
| `venue` on a fixture | `clubhouse_fixture_venue` |
| `email` on a person | `clubhouse_person_email` |

The library derives that key from the post type and the field id and offers no
way to override it per field, so the values move rather than the convention
bending. One place in this plugin knows it — `Collection_Meta::meta_key()` —
and everything that reads or writes a collection field goes through there.

## When it runs

`Collection_Editors::maybe_migrate()`, on the first admin request after the
update. It is guarded by an option, so it runs once; an in-place plugin update
never fires the activation hook, which is why it is not there.

**The old values are left exactly where they are.** Nothing reads them, they
cost nothing, and they are the only copy of the previous state. They are
deleted in phase 6, not before.

## What a club sees

Nothing, on the front end. The rendered pages are identical: the reader that
feeds them (`WP_Collections`) asks for the new address and falls back to the
old one, so a site is never blank in the window between the files updating and
the migration running.

## If it has to be rolled back

Reinstall the previous version. The old meta is untouched and still complete,
so the "Details" meta box reads exactly what it read before. Anything edited on
the new screens between the update and the rollback was written to the new keys
only, and would be lost — that is the one thing worth knowing before rolling
back rather than forward.

## How it was checked

- `CollectionMigrationTest` — a value arrives intact, a field never answered is
  not invented, an empty answer a club actually gave is carried across, a
  second run moves nothing, and a value already edited on the new screen is
  never overwritten.
- `tests/collection-editor.spec.js` — signed in as an owner, in a real
  WordPress: each list opens its record in its own editor, all six mount, and a
  change to a team's league saves and appears on the site.
