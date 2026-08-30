# Collection record editors — implementation plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** The six collections — Sports, Teams, Fixtures, Events, Sponsors, People — are edited on their own page editor screens instead of a hand-rolled "Details" meta box on WordPress's post screen.

**Architecture:** `Collection_Meta` stays as the single definition of what a collection field is; a new `Collection_Fields` reads it and says the same thing in the library's vocabulary. The screens use the library's own post store, so the library handles the record's title, status, slug, order and featured image — which means the meta keys move from `sport` to `clubhouse_team_sport`. One place knows that convention (`Collection_Meta::meta_key()`), every reader goes through it, and a one-off migration moves the club's existing values across.

**Tech Stack:** PHP 8.3, WordPress, PHPUnit 11, Playwright, no build step. The vendored `Blueworx\PageEditor\v1\…` library.

**Spec:** [docs/superpowers/specs/2026-08-28-page-editor-adoption-design.md](../specs/2026-08-28-page-editor-adoption-design.md) — §4, §7, §9. Phase 5 of six.

**Shorter than the phase 3 and 4 plans on purpose.** Those two established the pattern and the worked examples are in the repo; this records the decisions and the order of work rather than restating library mechanics a third time.

## Global Constraints

- **Baseline:** plugin v0.100.0, `main` at `8c2c2c6`.
- Branch, PR, CI guardrails never bypassed. Minor bump with the changelog in the same PR.
- **Do not edit `blueworx-page-editor/`.** It is hash-compared against the foundation.
- **Sign in as an owner, not an administrator,** in every browser spec that touches what a role can reach.
- **Local green is not green for admin screens.** `npm run wp:up` then `npm run test:wp`. A shop installed locally makes the admin slow enough to fail specs that CI passes — CI has none.

## Decisions taken

| Question | Decision |
| --- | --- |
| Storage | The library's post store, not a read/write pair. Setup supplied its own because its values live in eight different places; a collection's live in post meta on the record, which is exactly what the post store is for. The library then owns the title, status, slug, order, excerpt and featured image — ten settings fields this plugin would otherwise have to persist itself, where a missed one saves nothing and says nothing. |
| The meta keys | They move, because the post store derives them (`<post_type>_<field_id>`) and the library has no per-field override. One place knows it — `Collection_Meta::meta_key()` — and a one-off migration copies every existing value across, the way phase 3 did for page content. |
| `Collection_Meta` | Stays. It is the field definitions and the sanitising, and the screen definitions are written from it. |
| `Collection_Meta_Boxes` | Goes, apart from the admin list columns, which are still WordPress's own list. |
| The Collections menu | Goes. WordPress's own lists do that job, and having both is how an owner ends up on the wrong one. |
| Kick-off time | The library has no `time` control, so it becomes a `text` field with a hint. `Collection_Meta::sanitise()` still enforces `H:i`, so a mistyped time is still refused. |
| A collection's body | Not carried over. `supports` is title and page-attributes only, and nothing renders a collection's `post_content`. |

---

## File Structure

**Created:**
- `includes/collections/class-collection-fields.php` — `Collection_Meta` said in the library's vocabulary. Pure.
- `includes/collections/class-collection-editors.php` — registers the six screens, hides their menu items, points each list's Edit at them, keeps the block editor shut.
- `includes/collections/class-collection-migration.php` — the one-off. Deleted in phase 6.
- `tests/php/CollectionFieldsTest.php`, `tests/php/CollectionEditorsTest.php`, `tests/php/CollectionMigrationTest.php`
- `tests/collection-editor.spec.js` — Playwright, `@wordpress`, signed in as the owner.
- `docs/upgrades/2026-08-30-collections-become-records.md`

**Modified:**
- `includes/collections/class-collection-meta.php` — gains `meta_key()`, the one place the convention lives.
- `includes/collections/class-collection-mappers.php`, `class-wp-collections.php`, `class-collection-seeder.php`, `class-demo-posts.php`, `class-collection-types.php`, `includes/import/class-import-applier.php` — read and write through `meta_key()`.
- `includes/collections/class-collection-meta-boxes.php` — reduced to the list columns.
- `includes/bootstrap.php`, `blueworx-labs-clubhouse.php`, `CHANGELOG.md`, `docs/priorities.md`.

**Deleted:** the meta box itself, `assets/js/admin-collections.js`, and the tests that name them.

---

### Task 1: One place knows the meta key

`Collection_Meta::meta_key( string $type, string $key ): string` returning `$type . '_' . $key`, mirroring `PostStore::key()`. Nothing uses it yet.

- [ ] Test: `meta_key( 'clubhouse_team', 'sport' )` is `clubhouse_team_sport`.
- [ ] Test: it matches what the library's post store would derive, asserted against a registered screen rather than a repeated literal.
- [ ] Implement, run, commit.

### Task 2: The screen definitions

`Collection_Fields::screen( string $type )` — one tab, one "Details" panel, one field per `Collection_Meta::fields()`, plus a `title` field for the record's own name.

Kind mapping: `text`→`text`, `textarea`→`textarea`, `date`→`date`, `time`→`text` with a hint, `email`→`text` + `format => 'email'`, `url`/`href`→`text` + `format => 'url'`, `select`→`select` (blank option dropped — the library's select carries its own), `media`→`media`.

- [ ] Test: every one of the six passes `Schema::validate()`.
- [ ] Test: every field in `Collection_Meta::fields()` appears on its screen.
- [ ] Test: a fixture's outcome offers W, D and L and no blank.
- [ ] Implement, run, commit.

### Task 3: Register the screens, and route the lists

`Collection_Editors::register()` — declare on init 20, hide the six menu items on admin_head, point `get_edit_post_link` at the screen, switch the block editor off for these types, redirect a typed `post.php`. The same shape as `Club_Page_Editing`, which is the worked example in this repo.

- [ ] Test: each screen's slug, capability and post type.
- [ ] Test: an edit link for a collection post goes to its screen; any other post's is untouched.
- [ ] Implement, run, look at one in the harness, commit.

### Task 4: The migration

For every post of the six types, copy each field's value from the bare key to `meta_key()`, leaving the old value in place — it costs nothing and it is the only copy of the previous state.

- [ ] Test: every field arrives at its new address with its value intact.
- [ ] Test: a field never saved does not arrive as an empty string.
- [ ] Test: running it twice changes nothing the second time.
- [ ] Implement, run, commit.

### Task 5: Repoint every reader and writer

`Collection_Mappers`, `WP_Collections`, `Collection_Seeder`, `Demo_Posts`, `Collection_Types::register_post_meta()`, `Import_Applier`, and the list columns.

- [ ] The existing suite is the test: a missed reader breaks a rendered page and a spec.
- [ ] Run the whole PHP suite and the browser suite, commit.

### Task 6: Delete the meta box, and the Collections menu

- [ ] Delete `class-collection-meta-boxes.php`'s meta box and save, keeping the columns; delete `assets/js/admin-collections.js`.
- [ ] Drop `register_content_menu()` and its call.
- [ ] Run everything, commit.

### Task 7: Browser coverage, version, changelog, docs

- [ ] `tests/collection-editor.spec.js`, signed in as the owner: a list opens a record in its own editor; a change wakes the save bar; saving shows on the front end.
- [ ] The upgrade record, the version bump, the changelog, the priority list.
- [ ] Full suite, PR.

## Self-review

**Spec coverage.** §4's record editors — tasks 2 and 3. §4's "`Collection_Meta` stays, `Collection_Meta_Boxes` goes apart from the columns" — task 6. §4's "the Collections menu item goes" — task 6. §7's migrate-then-delete — tasks 4 and 5, with the written record in task 7. §9's testing — tasks 4 and 7.

**The risk worth naming.** Task 5 is a wide, mechanical change, and a reader missed there is a collection field that silently reads empty on the front end. The mitigation is that it fails loudly: every collection is rendered by a spec, and the PHP suite covers the mappers directly.
