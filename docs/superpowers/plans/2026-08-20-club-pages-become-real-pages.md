# Club Pages Become Real WordPress Pages — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** every club page gets a real WordPress page behind it, serves from that
page, and is still edited only in Club Pages — with the old routes left
answering underneath as a safety net.

**Architecture:** One new class owns the mapping between a club page's slug and
its WordPress post id, and every other task reads it. Pages are created
alongside the existing routes, then serving, admin and links move over one at a
time. Nothing is deleted this session.

**Tech Stack:** PHP 7.4+, WordPress, PHPUnit (`composer test`), Playwright
(`npm test`; WordPress specs need `npm run wp:up` then
`PLAYWRIGHT_BASE_URL=http://127.0.0.1:8705`), PHPCS (`composer lint`).

**Spec:** none. GitHub milestone 1 and issues #235–#241 are the spec; each task
below names its issue and repeats its goal, scope and done-when.

## Global Constraints

- **Editing must feel exactly as it does today.** Owners keep the Club Pages
  screens and nothing else. No block editor, and never ask an owner to fill in a
  WordPress field they have not seen. This is the milestone's binding rule.
- **Nothing is deleted this session.** The rewrite rules, the route resolver and
  the 404 gate all stay and keep working. Issue #243 removes them later, awake.
- **No club is live**, but the demo and test sites are real installs. No step may
  lose a club's stored words or its visibility settings.
- Every PHP file starts `<?php`, then `declare(strict_types=1);`, then the
  `if ( ! defined( 'ABSPATH' ) ) { exit; }` guard. Classes `final`, prefixed
  `Blueworx_Clubhouse_`. WordPress brace and spacing style — `composer lint` is
  a CI gate.
- No new dependencies.
- Copy an owner reads is plain English, sentence case, no jargon, no identifiers.
- **Version:** one bump at the end of the session, not per task. `0.85.1` →
  `0.86.0`, with a changelog entry, in `blueworx-labs-clubhouse.php` (header and
  the version define) and `package.json`.
- The branch has **pre-existing WordPress-suite failures** unrelated to this
  work — `member-dashboard.spec.js:54` and `:64` consistently, others
  intermittently. Do not investigate them. Run targeted specs, not the full
  browser suite.

## The pages this concerns

From `Blueworx_Clubhouse_Page_Map::pages()`: Home (slug `''`), About,
Membership, Contact, Log in, News (internal key `blog`), Sports, Teams, Events,
Calendar, Bookings (`booking`, needs LatePoint), Member area
(`member-dashboard`, private), Privacy, Terms.

---

### Task 1: Find out what a club actually loses today (#235)

A spike. Its output is a written record, not code kept.

**Files:**
- Create: `docs/integrations/wordpress-pages-and-seo-notes.md`

- [ ] **Step 1: Boot the harness and install Yoast**

Run: `npm run wp:up`

Install Yoast SEO into `.wp-test/wp/wp-content/plugins/` and activate it through
wp-admin. Do not add it to the repo or to `approved-deps.json` — it is a probe,
not a dependency.

- [ ] **Step 2: Record what lands in the head, on three kinds of page**

For each of: a club page (`/about/`), a real blog post
(`/clubhouse-post-fixture/`), and the front page — record what is present in
`<head>`: title, meta description, canonical, `og:` tags, `twitter:` tags, and
which plugin emitted them.

- [ ] **Step 3: Record the other three things core gives a page**

- Is the club page in `/wp-sitemap.xml`? Follow the index to the page sitemap.
- Does WordPress's own search (`/?s=<a word only on the About page>`) find it?
- Does the club page have a canonical URL at all?

- [ ] **Step 4: Write it up**

Write `docs/integrations/wordpress-pages-and-seo-notes.md` in the house style of
`docs/integrations/surecart-notes.md`: what was observed, dated, with anything
not verified said plainly. State per feature whether a club page has it today.
Lead with the finding that matters — whether a club running Yoast gets nothing
at all on its own pages.

- [ ] **Step 5: Commit**

```bash
git add docs/integrations/wordpress-pages-and-seo-notes.md
git commit -m "Record what a club page is missing that a real page would have"
```

---

### Task 2: A real page behind every club page (#236)

**Files:**
- Create: `includes/content/class-club-pages.php`
- Modify: `includes/bootstrap.php` (require it, after `class-visibility.php`)
- Modify: `includes/frontend/class-frontend.php` (call `register()` beside the other registrations)
- Test: `tests/php/ClubPagesTest.php`

**Interfaces — every later task reads these, so the names are fixed:**

```php
Blueworx_Clubhouse_Club_Pages::option_name( string $slug ): string   // pure
Blueworx_Clubhouse_Club_Pages::post_id( string $slug ): int          // 0 when absent
Blueworx_Clubhouse_Club_Pages::slug_for( int $post_id ): string      // '' when not ours
Blueworx_Clubhouse_Club_Pages::is_club_page( int $post_id ): bool
Blueworx_Clubhouse_Club_Pages::ensure(): void                        // create or repair, idempotent
Blueworx_Clubhouse_Club_Pages::desired( string $slug, string $label, bool $private ): array  // pure; the wp_insert_post args
```

`slug_for()` returns the Page_Map slug, so Home comes back as `''`. Because `''`
is also "not ours", callers must use `is_club_page()` for the yes/no question.

- [ ] **Step 1: Write the failing tests**

Create `tests/php/ClubPagesTest.php`:

```php
<?php

use PHPUnit\Framework\TestCase;

/**
 * The WordPress page behind each club page.
 *
 * Club pages have been rewrite-rule routes with nothing in the database behind
 * them. That cost the site everything WordPress gives a real page — the
 * sitemap, canonicals, search, and anything an SEO plugin would do. These
 * assert the mapping only; serving from it is a later task.
 */
final class ClubPagesTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
	}

	public function test_the_option_key_is_stable_and_slug_scoped(): void {
		// Stored per page, so one missing page never hides another.
		$this->assertSame( 'clubhouse_page_id_about', Blueworx_Clubhouse_Club_Pages::option_name( 'about' ) );
	}

	public function test_home_has_a_key_of_its_own_despite_an_empty_slug(): void {
		// Home's slug is '' — the front page. Without this it would collide with
		// every other empty lookup and the front page would point at nothing.
		$this->assertSame( 'clubhouse_page_id_home', Blueworx_Clubhouse_Club_Pages::option_name( '' ) );
	}

	public function test_a_page_that_is_not_ours_maps_to_nothing(): void {
		$this->assertSame( '', Blueworx_Clubhouse_Club_Pages::slug_for( 999 ) );
		$this->assertFalse( Blueworx_Clubhouse_Club_Pages::is_club_page( 999 ) );
		$this->assertFalse( Blueworx_Clubhouse_Club_Pages::is_club_page( 0 ) );
	}

	public function test_a_stored_page_maps_both_ways(): void {
		update_option( Blueworx_Clubhouse_Club_Pages::option_name( 'about' ), 42 );
		$this->assertSame( 42, Blueworx_Clubhouse_Club_Pages::post_id( 'about' ) );
		$this->assertSame( 'about', Blueworx_Clubhouse_Club_Pages::slug_for( 42 ) );
		$this->assertTrue( Blueworx_Clubhouse_Club_Pages::is_club_page( 42 ) );
	}

	public function test_home_maps_back_to_an_empty_slug(): void {
		update_option( Blueworx_Clubhouse_Club_Pages::option_name( '' ), 7 );
		$this->assertSame( '', Blueworx_Clubhouse_Club_Pages::slug_for( 7 ) );
		$this->assertTrue( Blueworx_Clubhouse_Club_Pages::is_club_page( 7 ) );
	}

	public function test_the_page_args_carry_the_right_slug_title_and_status(): void {
		$args = Blueworx_Clubhouse_Club_Pages::desired( 'about', 'About', false );
		$this->assertSame( 'page', $args['post_type'] );
		$this->assertSame( 'about', $args['post_name'] );
		$this->assertSame( 'About', $args['post_title'] );
		$this->assertSame( 'publish', $args['post_status'] );
	}

	public function test_the_member_area_page_is_never_public(): void {
		// Nothing on it is for a signed-out visitor, and a published page would
		// put it in the sitemap and in search results.
		$args = Blueworx_Clubhouse_Club_Pages::desired( 'member-dashboard', 'Member area', true );
		$this->assertSame( 'private', $args['post_status'] );
	}

	public function test_the_body_is_left_empty(): void {
		// The club's words stay in the content store and are still edited in
		// Club Pages. A body here would be a second, contradictory copy.
		$this->assertSame( '', Blueworx_Clubhouse_Club_Pages::desired( 'about', 'About', false )['post_content'] );
	}
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `composer test -- --filter ClubPagesTest`
Expected: FAIL — class not found

- [ ] **Step 3: Write the class**

Create `includes/content/class-club-pages.php`. It must:

- Build option names as `'clubhouse_page_id_' . ( '' === $slug ? 'home' : $slug )`.
- `post_id()` reads the option and casts to int, returning 0 for anything else.
- `slug_for()` walks `Page_Map::pages()` and compares stored ids. `is_club_page()`
  is the same walk returning a boolean, so `''` is never ambiguous.
- `desired()` is pure and returns the `wp_insert_post` args: `post_type` `page`,
  `post_name` the slug (Home uses `home`), `post_title` the label,
  `post_status` `private` when `$private` else `publish`, `post_content` `''`.
- `ensure()` walks `Page_Map::pages()` — the full list, not `available()`, so a
  club that installs LatePoint later already has its Bookings page — and for
  each one: if the stored id names a published or private page of type `page`,
  do nothing; if it names a trashed page, restore it; otherwise insert and store
  the new id. Guard every WordPress call with `function_exists`, the way
  `Shop_Pages` does.
- `ensure()` must be idempotent. Running it twice creates nothing.

Do NOT touch `show_on_front` or `page_on_front` in this task. Home gets a page
with slug `home`; making it the site's front page is Task 3's problem, and doing
it here would change the whole site before anything can serve from it.

- [ ] **Step 4: Run to verify it passes**

Run: `composer test -- --filter ClubPagesTest`
Expected: PASS

- [ ] **Step 5: Load it and run it on activation**

Add the `require_once` to `includes/bootstrap.php` in the Content block, after
`class-visibility.php`. Then call `ensure()` where the plugin already does its
activation and upgrade work — read how `Frontend::maybe_flush_rewrites()` is
wired and follow that pattern, since it solves the same "run once per version"
problem. `ensure()` is cheap and idempotent, so erring toward running it more
often is safe; erring toward never is not.

- [ ] **Step 6: Prove it in a real WordPress**

Add `tests/club-pages.spec.js`:

```js
const { test, expect } = require('@playwright/test');

// @wordpress only: this is about rows in the database, which the DB-free
// preview does not have.

async function signIn(page) {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'wptest-admin-pw');
  await page.click('#wp-submit');
  await expect(page.locator('#wpadminbar')).toBeVisible();
}

test('every club page has a real page behind it @wordpress', async ({ page }) => {
  await signIn(page);
  await page.goto('/wp-admin/edit.php?post_type=page&post_status=all');
  for (const title of ['About', 'Membership', 'Contact', 'News', 'Sports', 'Teams', 'Events', 'Calendar', 'Privacy', 'Terms']) {
    await expect(page.locator('#the-list a.row-title', { hasText: new RegExp(`^${title}$`) })).toHaveCount(1);
  }
});
```

Run: `npm run wp:up` then
`PLAYWRIGHT_BASE_URL=http://127.0.0.1:8705 npx playwright test tests/club-pages.spec.js --workers=1`
Expected: PASS. If the pages have not been created, the activation hook is not
firing on an already-installed harness — deactivate and reactivate the plugin
through wp-admin, and if that is what it took, say so in your report.

- [ ] **Step 7: Full suite and lint**

Run: `composer test` — expect all passing
Run: `composer lint` — report findings, do not fix them

- [ ] **Step 8: Commit**

```bash
git add includes/content/class-club-pages.php includes/bootstrap.php includes/frontend/class-frontend.php tests/php/ClubPagesTest.php tests/club-pages.spec.js
git commit -m "Give every club page a real WordPress page behind it"
```

---

### Task 3: Serve from the page (#237)

The URL a visitor types now resolves to a real page, and WordPress renders it
through our template. The rewrite rules stay and keep answering.

**Files:**
- Create: `templates/club-page.php`
- Modify: `includes/frontend/class-frontend.php`
- Test: `tests/php/FrontendTest.php`, `tests/club-pages.spec.js`

**Interfaces:**
- Consumes: `Club_Pages::slug_for()`, `::is_club_page()`, `::post_id()` from Task 2.
- Produces: `Blueworx_Clubhouse_Frontend::template_for_post( int $post_id, string $default, string $ours ): string` — pure.

- [ ] **Step 1: Write the failing test**

Add to `tests/php/FrontendTest.php` (read it first; add, never replace):

```php
	public function test_a_club_page_is_served_from_this_plugins_template(): void {
		update_option( Blueworx_Clubhouse_Club_Pages::option_name( 'about' ), 42 );
		$this->assertSame(
			'/plugin/club-page.php',
			Blueworx_Clubhouse_Frontend::template_for_post( 42, '/theme/page.php', '/plugin/club-page.php' )
		);
	}

	public function test_any_other_page_keeps_the_themes_template(): void {
		// This runs on every front-end request. Anything not ours comes back
		// untouched or the plugin has taken over the whole site.
		$this->assertSame(
			'/theme/page.php',
			Blueworx_Clubhouse_Frontend::template_for_post( 999, '/theme/page.php', '/plugin/club-page.php' )
		);
	}
```

- [ ] **Step 2: Run to verify it fails**

Run: `composer test -- --filter FrontendTest`
Expected: FAIL — undefined method

- [ ] **Step 3: Write the template**

Create `templates/club-page.php`, the same shape as the existing
`templates/clubhouse.php`: doctype, `wp_head()`, body class,
`Blueworx_Clubhouse_Frontend::render_body()`, `wp_footer()`. Copy that file and
change only its comment, which should say why it exists — that the page is now
found by WordPress rather than by a rewrite rule, and the renderer is unchanged.

- [ ] **Step 4: Make the renderer answer for a post as well as a route**

`Frontend::render_body()` calls `current_slug()`, which reads the rewrite query
var. On a real page request that query var is absent, so it would render
nothing. Make `current_slug()` fall back to
`Club_Pages::slug_for( get_queried_object_id() )` when the query var gives
nothing and the queried post is a club page. Keep the existing visibility check
in `resolve_slug()` applying either way — a page that is switched off must still
refuse to render.

Then add `template_for_post()` as a pure function, and hook `template_include`
to it. Priority and shape: follow `Commerce_Pages::serve_template()`, which
solves the identical problem for the checkout page.

- [ ] **Step 5: Make Home the site's front page**

Home's slug is `''`, so its page must be the site's static front page or `/`
will not reach it. In `Club_Pages::ensure()`, after the Home page exists, set
`show_on_front` to `page` and `page_on_front` to its id — but only when
`show_on_front` is still `posts` or `page_on_front` is 0 or names a page that no
longer exists. A club that has deliberately chosen a different front page keeps
it. Add a test for both branches.

- [ ] **Step 6: Run to verify it passes**

Run: `composer test -- --filter FrontendTest`
Expected: PASS

- [ ] **Step 7: Prove it in a browser**

Add to `tests/club-pages.spec.js`:

```js
test('a club page renders from its own page, not the rewrite rule @wordpress', async ({ page }) => {
  await page.goto('/about/');
  await expect(page.locator('.ch-nav')).toHaveCount(1);
  const isPage = await page.evaluate(() => document.body.className.includes('page-id-'));
  expect(isPage).toBe(true);
});

test('the front page is the club home page @wordpress', async ({ page }) => {
  await page.goto('/');
  await expect(page.locator('.ch-nav')).toHaveCount(1);
});
```

Run the WordPress specs for this file plus `tests/smoke.spec.js`, which walks
several club pages end to end. Both must pass.

- [ ] **Step 8: Full suite, targeted browser specs, lint**

Run: `composer test`, then `npm test`, then the WordPress specs for
`tests/club-pages.spec.js`, `tests/smoke.spec.js` and
`tests/hidden-page-404.spec.js`. Do NOT run the whole browser suite.

Run: `composer lint` — report findings, do not fix them

- [ ] **Step 9: Commit**

```bash
git add templates/club-page.php includes/frontend/class-frontend.php includes/content/class-club-pages.php tests/php/FrontendTest.php tests/php/ClubPagesTest.php tests/club-pages.spec.js
git commit -m "Serve club pages from their own page rather than a rewrite rule"
```

---

### Task 4: No block editor, and Edit goes to Club Pages (#238)

**Files:**
- Create: `includes/admin/class-club-page-editing.php`
- Modify: `includes/bootstrap.php`, `includes/frontend/class-frontend.php` (registration)
- Test: `tests/php/ClubPageEditingTest.php`

**Interfaces:**
- Consumes: `Club_Pages::slug_for()`, `::is_club_page()`.
- Produces: `Blueworx_Clubhouse_Club_Page_Editing::editor_url( string $slug ): string`,
  `::register(): void`.

The Club Pages screen lives at `?page=clubhouse-site-content` and selects a page
with `&tab=<slug>`. Home's tab is its Page_Map slug, `''` — check what the
screen actually emits for Home before assuming, by reading
`Blueworx_Clubhouse_Content_Screen`'s tab hrefs, and match it exactly.

- [ ] **Step 1: Write the failing tests**

```php
	public function test_the_editor_url_points_at_the_right_page_in_club_pages(): void {
		$this->assertStringContainsString( 'page=clubhouse-site-content', Blueworx_Clubhouse_Club_Page_Editing::editor_url( 'about' ) );
		$this->assertStringContainsString( 'tab=about', Blueworx_Clubhouse_Club_Page_Editing::editor_url( 'about' ) );
	}

	public function test_a_club_page_never_uses_the_block_editor(): void {
		update_option( Blueworx_Clubhouse_Club_Pages::option_name( 'about' ), 42 );
		$this->assertFalse( Blueworx_Clubhouse_Club_Page_Editing::wants_block_editor( true, 42 ) );
	}

	public function test_every_other_page_keeps_whatever_editor_it_had(): void {
		// This filter runs for every post in wp-admin. A club page is the only
		// thing we have an opinion about.
		$this->assertTrue( Blueworx_Clubhouse_Club_Page_Editing::wants_block_editor( true, 999 ) );
		$this->assertFalse( Blueworx_Clubhouse_Club_Page_Editing::wants_block_editor( false, 999 ) );
	}
```

- [ ] **Step 2: Run to verify it fails**

Run: `composer test -- --filter ClubPageEditingTest`
Expected: FAIL

- [ ] **Step 3: Write the class**

It must:

- Filter `use_block_editor_for_post` through `wants_block_editor( bool $default, int $post_id ): bool` — pure, returns false for a club page and `$default` for anything else.
- Filter `get_edit_post_link` so a club page's edit link is `editor_url()`.
- On `load-post.php`, redirect to `editor_url()` when the post being opened is a club page, so typing the address does not get round it. Use `wp_safe_redirect` and `exit`.
- `editor_url()` builds `admin_url( 'admin.php?page=clubhouse-site-content&tab=<slug>' )`, url-encoding the slug.

- [ ] **Step 4: Run to verify it passes**

Run: `composer test -- --filter ClubPageEditingTest`
Expected: PASS

- [ ] **Step 5: Prove there is no way in**

Add to `tests/club-pages.spec.js` a signed-in test that opens
`/wp-admin/post.php?post=<id>&action=edit` for the About page and asserts it
lands on `page=clubhouse-site-content`. Get the id by finding the About row in
the Pages list first, the way the earlier test does.

- [ ] **Step 6: Full suite, targeted specs, lint. Then commit**

```bash
git commit -m "Send Edit on a club page to Club Pages, and turn the block editor off"
```

---

### Task 5: The Pages menu comes back (#239)

**Files:**
- Modify: `includes/admin/class-wordpress-pages.php`
- Test: `tests/php/WordPressPagesTest.php` (check whether it exists first)

Constraint pinned to this issue: the Pages list is somewhere to **see** club
pages, not to edit them. No quick edit, no inline title editing, no bulk action
that could change one. Every route out of the screen for a club page goes to its
Club Pages editor — Task 4 already did the edit links.

- [ ] **Step 1: Write the failing tests**

```php
	public function test_a_club_page_offers_no_row_action_that_could_change_it(): void {
		// The plugin depends on these pages existing at these slugs. Trash,
		// quick edit and rename are all ways to break the site from a screen
		// that looks harmless.
		$actions = Blueworx_Clubhouse_Wordpress_Pages::row_actions(
			array( 'edit' => 'E', 'inline hide-if-no-js' => 'Q', 'trash' => 'T', 'view' => 'V' ),
			true
		);
		$this->assertArrayNotHasKey( 'inline hide-if-no-js', $actions );
		$this->assertArrayNotHasKey( 'trash', $actions );
		$this->assertArrayHasKey( 'edit', $actions );
		$this->assertArrayHasKey( 'view', $actions );
	}

	public function test_any_other_page_keeps_all_of_its_actions(): void {
		$given   = array( 'edit' => 'E', 'inline hide-if-no-js' => 'Q', 'trash' => 'T' );
		$this->assertSame( $given, Blueworx_Clubhouse_Wordpress_Pages::row_actions( $given, false ) );
	}
```

- [ ] **Step 2: Run to verify it fails, then implement**

- Stop removing the menu: delete the `remove_menu_page` call and its hook, and
  rewrite the class docblock — it currently explains why the screen was hidden,
  and that reason is gone. Say instead that these are real pages now, edited in
  Club Pages, and that the list is read-only for them.
- Add `row_actions( array $actions, bool $is_club_page ): array` — pure, strips
  quick edit and trash for a club page.
- Hook `page_row_actions` to it, asking `Club_Pages::is_club_page()`.
- Add a "Club page" column, or mark the title, so an owner can tell ours from
  their own. Keep it plain: the word "Club page", nothing clever.
- Block the deletion for real, not only in the UI: on `wp_trash_post` and
  `before_delete_post`, refuse when the post is a club page. A row action hidden
  from the list is not a guarantee.

- [ ] **Step 3: Prove it**

Add a browser test that the Pages menu is present again, that the About row
shows no Quick Edit and no Trash, and that a non-club page still has both. Seed
an ordinary page in `tests/global-setup.js` if there is not already one to
compare against — read that file first and add to the existing heredoc.

- [ ] **Step 4: Full suite, targeted specs, lint. Then commit**

```bash
git commit -m "Bring the Pages menu back, with club pages read-only in the list"
```

---

### Task 6: Switching a page off makes it a draft (#240)

**Files:**
- Modify: `includes/content/class-visibility.php`, `includes/content/class-club-pages.php`
- Test: `tests/php/VisibilityTest.php` (check whether it exists first)

**Interfaces:**
- Produces: `Blueworx_Clubhouse_Club_Pages::status_for( bool $visible, bool $private ): string` — pure.

- [ ] **Step 1: Write the failing tests**

```php
	public function test_a_page_that_is_on_is_published(): void {
		$this->assertSame( 'publish', Blueworx_Clubhouse_Club_Pages::status_for( true, false ) );
	}

	public function test_a_page_that_is_off_is_a_draft(): void {
		// A draft is a 404 to a visitor and is out of the sitemap and search,
		// which is exactly what the visibility flag was for.
		$this->assertSame( 'draft', Blueworx_Clubhouse_Club_Pages::status_for( false, false ) );
	}

	public function test_a_private_page_that_is_on_stays_private(): void {
		// The member area is never public even when it is switched on.
		$this->assertSame( 'private', Blueworx_Clubhouse_Club_Pages::status_for( true, true ) );
	}

	public function test_a_private_page_that_is_off_is_still_a_draft(): void {
		$this->assertSame( 'draft', Blueworx_Clubhouse_Club_Pages::status_for( false, true ) );
	}
```

- [ ] **Step 2: Implement**

- Add `status_for()` to `Club_Pages`, and use it in `desired()` so creation and
  updates agree on one rule.
- When `Visibility::set_page_visible()` is called, update the page's status to
  match. Keep the stored flag as well — it is what the Setup screen reads and
  what `resolve_slug()` checks, and this session deletes nothing.
- Add a reconcile step to `ensure()` so a page whose status has drifted from its
  flag is put back. That is what carries existing sites across without a
  separate migration.

- [ ] **Step 3: Prove it**

The repo already has `tests/hidden-page-404.spec.js`. Extend it: after switching
a page off, its page is not published; after switching it on, it is. Read the
file first and follow its existing helpers.

- [ ] **Step 4: Full suite, targeted specs, lint. Then commit**

```bash
git commit -m "Make switching a page off unpublish its page"
```

---

### Task 7: Links come from permalinks (#241)

**Files:**
- Modify: `includes/frontend/class-frontend.php`
- Test: `tests/php/FrontendTest.php`

`Frontend::link_url( string $key ): string` currently builds club page URLs by
hand — `home_url( '/' . $slug . '/' )`, or a query-var form on plain permalinks.
Now that every club page has a post, ask WordPress instead.

- [ ] **Step 1: Write the failing test**

```php
	public function test_a_club_page_link_is_its_permalink(): void {
		// Built by hand, these drift the moment a permalink structure changes.
		update_option( Blueworx_Clubhouse_Club_Pages::option_name( 'about' ), 42 );
		$GLOBALS['wp_stub_permalinks'][42] = 'https://club.test/about/';
		$this->assertSame( 'https://club.test/about/', Blueworx_Clubhouse_Frontend::link_url( 'about' ) );
	}

	public function test_a_page_with_no_post_still_gets_a_usable_link(): void {
		// Until every site has run the upgrade, a missing post must fall back to
		// the address the rewrite rule still answers rather than to nothing.
		$this->assertNotSame( '', Blueworx_Clubhouse_Frontend::link_url( 'about' ) );
	}
```

- [ ] **Step 2: Implement**

`link_url()` asks `Club_Pages::post_id()` and returns `get_permalink()` when
there is one. When there is not, it keeps exactly the behaviour it has today —
that fallback is what makes this safe to ship with the routes still in place.
Home returns `home_url( '/' )` as it does now.

- [ ] **Step 3: Prove it**

`tests/smoke.spec.js` already walks the nav across several pages. Run it. Then
add one test to `tests/club-pages.spec.js` asserting the header's About link
href matches the About page's permalink.

- [ ] **Step 4: Full suite, targeted specs, lint. Then commit**

```bash
git commit -m "Build club page links from their permalinks"
```

---

### Task 8: Release

**Files:**
- Modify: `blueworx-labs-clubhouse.php`, `package.json`, `CHANGELOG.md`, `docs/priorities.md`

- [ ] **Step 1: Bump 0.85.1 → 0.86.0** in all three places — a feature.

- [ ] **Step 2: Changelog**, in the club owner's words. Something like: their
pages now appear under Pages in WordPress, editing is unchanged and still in
Club Pages, and search engines can find the site properly. No identifiers.

- [ ] **Step 3: Note it in `docs/priorities.md`** in that file's existing voice,
including what is deliberately left — issues #242, #243 and #244.

- [ ] **Step 4: Verify**: `composer test`, `npm test`, the WordPress specs for
`tests/club-pages.spec.js`, `tests/smoke.spec.js` and
`tests/hidden-page-404.spec.js`. Then `composer lint`, reporting findings only.

- [ ] **Step 5: Commit** `git commit -m "Release 0.86.0"`

---

## Self-Review

**Issue coverage.** #235 is Task 1. #236 is Task 2. #237 is Task 3. #238 is
Task 4. #239 is Task 5. #240 is Task 6. #241 is Task 7. #242, #243 and #244 have
no task, correctly — they are out of this session's scope by decision.

**Placeholders.** None. Three steps say to read a file before writing (the Club
Pages tab href in Task 4, `global-setup.js` in Task 5, `hidden-page-404.spec.js`
in Task 6) because the exact shape cannot be quoted without seeing it; each says
what to do once seen.

**Type consistency.** `option_name`, `post_id`, `slug_for`, `is_club_page`,
`ensure`, `desired` and `status_for` are defined in Tasks 2 and 6 and used with
those exact names and signatures in Tasks 3–7. `template_for_post` matches
`Commerce_Pages::template_for`'s established shape. `wants_block_editor` matches
what `use_block_editor_for_post` passes.

**The risk this plan carries.** Task 3 changes how every page on the site is
found, and Task 3 Step 5 changes the site's front page. Both are reversible
while the rewrite rules remain, which is why nothing is deleted this session.
