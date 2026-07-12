# Collections / CPTs Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Swap the page renderers' hardcoded demo arrays for six WordPress custom post types read through a `Collections` repository, seeded so a fresh install still shows a fully populated site — without changing any `Sections` renderer.

**Architecture:** A `Collections` interface returns canonical per-collection arrays. `Demo_Content` (pure) single-sources the demo data; `Demo_Collections` (pure) serves it to the preview and tests; `WP_Collections` (thin WP) reads seeded CPT posts via a unit-tested `Collection_Mappers`. Projection from canonical → each renderer's exact shape lives in `Page_Renderer` (the composition layer). CPT registration + seeding are thin WP glue verified with the WP-function shim.

**Tech Stack:** PHP 8.2+, WordPress CPT APIs (`register_post_type`, `register_post_meta`, `WP_Query`, `wp_insert_post`, `get_post_meta`), PHPUnit, no new dependencies.

## Global Constraints

- **No new runtime or dev dependency.**
- **PHP 8.2+**; every PHP file starts with `declare(strict_types=1);` and `if ( ! defined( 'ABSPATH' ) ) { exit; }`.
- **Pure core stays WP-free** — no WordPress functions in `includes/render/`, `includes/theme/`, `includes/content/`, `includes/core/`, or the pure `includes/collections/` files (`interface-collections.php`, `class-demo-content.php`, `class-demo-collections.php`, `class-collection-mappers.php`). WordPress calls live only in `class-wp-collections.php`, `class-collection-types.php`, `class-collection-seeder.php`, `includes/frontend/`, and the main plugin file.
- **`Sections` renderers are NOT changed** — this plan adds no `ch-*` markup and edits no file in a way that alters renderer output; only the *data source* changes. Rendered HTML for the demo site stays equivalent.
- **Uniform page-method signature:** every `Page_Renderer` page method takes `( Branding $branding, Visibility $visibility, Collections $collections )`; membership/login accept and ignore `$collections`.
- **Version:** minor bump **0.12.0 → 0.13.0** (header + `BLUEWORX_LABS_CLUBHOUSE_VERSION` + `package.json`), changelog entry.
- **PHPCS clean** (`composer lint`), **all PHPUnit green** (`vendor/bin/phpunit`) at every commit. Tests run WP-free; WP glue is covered via `tests/php/wp-stubs.php`.

## File Structure

- Create `includes/collections/interface-collections.php` — the `Collections` contract.
- Create `includes/collections/class-demo-content.php` — pure single source of demo canonical arrays.
- Create `includes/collections/class-demo-collections.php` — pure `Collections` over `Demo_Content` (preview + tests).
- Create `includes/collections/class-collection-mappers.php` — pure raw-post→canonical mappers (shared by WP impl).
- Create `includes/collections/class-wp-collections.php` — WP `Collections` (WP_Query + meta → mappers).
- Create `includes/collections/class-collection-types.php` — registers the six CPTs + meta.
- Create `includes/collections/class-collection-seeder.php` — seeds each empty collection from `Demo_Content`.
- Create `includes/render/class-fixture-projection.php` — pure fixture canonical→renderer-shape helpers.
- Modify `includes/bootstrap.php` — require the pure collections files + fixture projection.
- Modify `includes/render/class-page-renderer.php` — add `$collections` param; replace inline demo arrays with canonical→shape projection.
- Modify `includes/render/class-page-map.php` — thread `$collections` through `render()`.
- Modify `includes/frontend/class-frontend.php` — build `WP_Collections`, register CPTs on `init`, seed on activation.
- Modify `blueworx-labs-clubhouse.php` — require the collections classes; activation seeds.
- Modify `preview/index.php` — pass `new Demo_Collections()`.
- Modify `tests/php/wp-stubs.php` — add CPT/query/insert stubs.
- Modify `tests/php/bootstrap.php` — require the new WP-glue collection classes for tests.
- Modify `tests/smoke.spec.js` — assert a representative collection item per page.
- Create `tests/php/DemoContentTest.php`, `DemoCollectionsTest.php`, `FixtureProjectionTest.php`, `CollectionMappersTest.php`, `CollectionTypesTest.php`, `CollectionSeederTest.php`.

---

### Task 1: `Collections` interface + `Demo_Content` + `Demo_Collections`

**Files:**
- Create: `includes/collections/interface-collections.php`, `includes/collections/class-demo-content.php`, `includes/collections/class-demo-collections.php`
- Modify: `includes/bootstrap.php`
- Test: `tests/php/DemoContentTest.php`, `tests/php/DemoCollectionsTest.php`

**Interfaces:**
- Produces:
  - `interface Blueworx_Clubhouse_Collections { public function sports():array; public function teams():array; public function fixtures():array; public function events():array; public function sponsors():array; public function people():array; }`
  - `Blueworx_Clubhouse_Demo_Content::{sports,teams,fixtures,events,sponsors,people}(): array` (static, canonical arrays)
  - `Blueworx_Clubhouse_Demo_Collections implements Blueworx_Clubhouse_Collections` (delegates to `Demo_Content`)
- Canonical item shapes (keys the later projections rely on):
  - sport: `{title, label, subtitle, description, stat1_value, stat1_label, stat2_value, stat2_label, image}`
  - team: `{title, sport, description, match_day, league, image}`
  - fixture: `{sport, match_date (YYYY-MM-DD), kickoff_time, venue, home, away, score, outcome ('' | W | L | D), result_summary}`
  - event: `{title, tag, date, detail, cta_label, cta_href, status ('upcoming' | 'past')}`
  - sponsor: `{name, url}`
  - person: `{name, committee_role, directory_role, email}`

- [ ] **Step 1: Write the failing test** (`tests/php/DemoContentTest.php`)

```php
<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class DemoContentTest extends TestCase {

	public function test_sports_have_all_canonical_keys(): void {
		$sports = Blueworx_Clubhouse_Demo_Content::sports();
		$this->assertGreaterThanOrEqual( 6, count( $sports ) );
		foreach ( $sports as $s ) {
			foreach ( array( 'title', 'label', 'subtitle', 'description', 'stat1_value', 'stat1_label', 'stat2_value', 'stat2_label', 'image' ) as $k ) {
				$this->assertArrayHasKey( $k, $s );
			}
		}
		$this->assertSame( 'Rugby', $sports[0]['title'] );
	}

	public function test_fixtures_have_upcoming_and_played(): void {
		$fx = Blueworx_Clubhouse_Demo_Content::fixtures();
		$upcoming = array_filter( $fx, static fn( $f ) => '' === $f['outcome'] );
		$played   = array_filter( $fx, static fn( $f ) => '' !== $f['outcome'] );
		$this->assertGreaterThanOrEqual( 3, count( $upcoming ) );
		$this->assertGreaterThanOrEqual( 3, count( $played ) );
		foreach ( $fx as $f ) {
			$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}$/', $f['match_date'] );
			$this->assertContains( $f['outcome'], array( '', 'W', 'L', 'D' ) );
		}
	}

	public function test_people_have_committee_and_directory_members(): void {
		$people = Blueworx_Clubhouse_Demo_Content::people();
		$committee = array_filter( $people, static fn( $p ) => '' !== $p['committee_role'] );
		$directory = array_filter( $people, static fn( $p ) => '' !== $p['directory_role'] );
		$this->assertGreaterThanOrEqual( 6, count( $committee ) );
		$this->assertGreaterThanOrEqual( 6, count( $directory ) );
	}

	public function test_events_split_upcoming_past(): void {
		$events = Blueworx_Clubhouse_Demo_Content::events();
		$this->assertNotEmpty( array_filter( $events, static fn( $e ) => 'upcoming' === $e['status'] ) );
		$this->assertNotEmpty( array_filter( $events, static fn( $e ) => 'past' === $e['status'] ) );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter DemoContentTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Create the interface**

```php
<?php
// includes/collections/interface-collections.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read model for the six site collections. Each method returns a list of
 * canonical associative arrays (all fields a projection might need). The pure
 * Demo implementation serves the preview and tests; the WP implementation reads
 * seeded custom-post-type posts.
 *
 * @package BlueworxLabsClubhouse
 */
interface Blueworx_Clubhouse_Collections {
	/** @return array<int,array<string,mixed>> */
	public function sports(): array;
	/** @return array<int,array<string,mixed>> */
	public function teams(): array;
	/** @return array<int,array<string,mixed>> */
	public function fixtures(): array;
	/** @return array<int,array<string,mixed>> */
	public function events(): array;
	/** @return array<int,array<string,mixed>> */
	public function sponsors(): array;
	/** @return array<int,array<string,mixed>> */
	public function people(): array;
}
```

- [ ] **Step 4: Create `Demo_Content`** with the reconciled canonical data

The demo data is relocated and reconciled from `includes/render/class-page-renderer.php` (Home sports 177-180, Sports page 571-588, Teams 636-647, Home fixtures/results 218-225, Calendar 754-760, Events 692-708, Sponsors 256, About committee 319-324, Contact directory 499-504). Preserve the copy verbatim. Sports use the Sports-page 6 as the superset (Home shows the first 4); the `label` equals the shared tag/chip (they match in the source); `subtitle` is Home's short line (authored for Hockey/Netball, which never appear on Home). People carry per-context roles because the same person has different committee vs directory roles. Fixtures are one coherent set that projects to both Home and Calendar (this makes the two views consistent — a minor, intended improvement over the previously-divergent hardcoded demo).

```php
<?php
// includes/collections/class-demo-content.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single source of the ClubHouse demo content, as canonical collection arrays.
 * Both the seeder (writes these as CPT posts) and Demo_Collections (serves them
 * directly to the preview/tests) read from here, so demo data lives in one place.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Demo_Content {

	/** @return array<int,array<string,mixed>> */
	public static function sports(): array {
		return array(
			array( 'title' => 'Rugby', 'label' => 'Sat', 'subtitle' => 'Senior · colts · touch',
				'description' => 'Senior, colts and touch rugby, from minis upward.',
				'stat1_value' => '4', 'stat1_label' => 'Teams', 'stat2_value' => '120', 'stat2_label' => 'Players', 'image' => '' ),
			array( 'title' => 'Tennis', 'label' => 'Daily', 'subtitle' => 'Four courts · coaching',
				'description' => 'Four courts with coaching for every age.',
				'stat1_value' => '4', 'stat1_label' => 'Courts', 'stat2_value' => '90', 'stat2_label' => 'Members', 'image' => '' ),
			array( 'title' => 'Cricket', 'label' => 'Summer', 'subtitle' => 'Youth → senior league',
				'description' => 'Youth to senior league cricket on the square.',
				'stat1_value' => '3', 'stat1_label' => 'Teams', 'stat2_value' => '80', 'stat2_label' => 'Players', 'image' => '' ),
			array( 'title' => 'Football', 'label' => 'Sun', 'subtitle' => 'Juniors · ages 5–16',
				'description' => 'Junior football for ages 5 to 16.',
				'stat1_value' => '6', 'stat1_label' => 'Teams', 'stat2_value' => '140', 'stat2_label' => 'Players', 'image' => '' ),
			array( 'title' => 'Hockey', 'label' => 'Sat', 'subtitle' => 'Ladies · mixed',
				'description' => 'Ladies and mixed hockey, league affiliated.',
				'stat1_value' => '3', 'stat1_label' => 'Teams', 'stat2_value' => '60', 'stat2_label' => 'Players', 'image' => '' ),
			array( 'title' => 'Netball', 'label' => 'Wed', 'subtitle' => 'Back-to-netball · squads',
				'description' => 'Back-to-netball through to divisional squads.',
				'stat1_value' => '2', 'stat1_label' => 'Teams', 'stat2_value' => '40', 'stat2_label' => 'Players', 'image' => '' ),
		);
	}

	/** @return array<int,array<string,mixed>> */
	public static function teams(): array {
		return array(
			array( 'title' => '1st XV', 'sport' => 'Rugby', 'description' => 'Saturday league rugby, Division 3 South.', 'match_day' => 'Sat', 'league' => 'Div 3', 'image' => '' ),
			array( 'title' => '1st XI', 'sport' => 'Cricket', 'description' => 'Premier division Saturday cricket.', 'match_day' => 'Sat', 'league' => 'Prem', 'image' => '' ),
			array( 'title' => 'Ladies 1s', 'sport' => 'Hockey', 'description' => 'County league hockey with a strong colts feed.', 'match_day' => 'Sat', 'league' => 'County', 'image' => '' ),
			array( 'title' => 'Netball 2s', 'sport' => 'Netball', 'description' => 'Wednesday-night divisional netball.', 'match_day' => 'Wed', 'league' => 'Div 2', 'image' => '' ),
		);
	}

	/** @return array<int,array<string,mixed>> */
	public static function fixtures(): array {
		return array(
			array( 'sport' => 'Rugby · 1st XV', 'match_date' => '2026-07-12', 'kickoff_time' => '14:00', 'venue' => 'Home', 'home' => 'ClubHouse', 'away' => 'Riverside RFC', 'score' => '', 'outcome' => '', 'result_summary' => '' ),
			array( 'sport' => 'Netball · Div 2', 'match_date' => '2026-07-13', 'kickoff_time' => '11:00', 'venue' => 'Away', 'home' => 'ClubHouse', 'away' => 'Castlebridge', 'score' => '', 'outcome' => '', 'result_summary' => '' ),
			array( 'sport' => 'Hockey · Ladies 1s', 'match_date' => '2026-07-19', 'kickoff_time' => '15:30', 'venue' => 'Home', 'home' => 'ClubHouse', 'away' => 'Elmwood', 'score' => '', 'outcome' => '', 'result_summary' => '' ),
			array( 'sport' => 'Cricket · 1st XI', 'match_date' => '2026-07-05', 'kickoff_time' => '11:00', 'venue' => 'Home', 'home' => 'ClubHouse 1st XI', 'away' => 'Hartfield CC', 'score' => '+34', 'outcome' => 'W', 'result_summary' => 'Won by 34 runs' ),
			array( 'sport' => 'Tennis · Singles', 'match_date' => '2026-07-02', 'kickoff_time' => '18:00', 'venue' => 'Home', 'home' => 'J. Patel', 'away' => 'R. Osei', 'score' => '2–0', 'outcome' => 'W', 'result_summary' => 'Won 2–0' ),
			array( 'sport' => 'Rugby · 2nd XV', 'match_date' => '2026-06-28', 'kickoff_time' => '14:00', 'venue' => 'Away', 'home' => 'ClubHouse 2nd XV', 'away' => 'Dunmore', 'score' => '18–24', 'outcome' => 'L', 'result_summary' => 'Lost 18–24' ),
			array( 'sport' => 'Hockey · Mixed', 'match_date' => '2026-06-21', 'kickoff_time' => '13:00', 'venue' => 'Home', 'home' => 'ClubHouse Mixed', 'away' => 'Elmwood', 'score' => '2–2', 'outcome' => 'D', 'result_summary' => 'Drew 2–2' ),
		);
	}

	/** @return array<int,array<string,mixed>> */
	public static function events(): array {
		return array(
			array( 'title' => 'Club Open Day', 'tag' => 'Open day', 'date' => 'Sat 26 Jul', 'detail' => '10:00–14:00 · Clubhouse & grounds — all welcome.', 'cta_label' => 'Register interest', 'cta_href' => '?page=contact', 'status' => 'upcoming' ),
			array( 'title' => 'Summer Football Camp', 'tag' => 'Junior football', 'date' => '4–8 Aug', 'detail' => 'Ages 5–12 · a week of coaching and games.', 'cta_label' => 'Book a place', 'cta_href' => '?page=contact', 'status' => 'upcoming' ),
			array( 'title' => 'Annual Awards Night', 'tag' => 'Social', 'date' => 'Fri 12 Sep', 'detail' => '19:00 · Clubhouse function room.', 'cta_label' => '', 'cta_href' => '', 'status' => 'upcoming' ),
			array( 'title' => 'Summer BBQ & Family Day', 'tag' => 'Social', 'date' => 'Jun 2026', 'detail' => '', 'cta_label' => '', 'cta_href' => '', 'status' => 'past' ),
			array( 'title' => 'Spring Sevens Rugby Festival', 'tag' => 'Tournament', 'date' => 'May 2026', 'detail' => '', 'cta_label' => '', 'cta_href' => '', 'status' => 'past' ),
			array( 'title' => 'Annual General Meeting', 'tag' => 'Club', 'date' => 'Apr 2026', 'detail' => '', 'cta_label' => '', 'cta_href' => '', 'status' => 'past' ),
			array( 'title' => 'Easter Multi-Sport Camp', 'tag' => 'Junior', 'date' => 'Mar 2026', 'detail' => '', 'cta_label' => '', 'cta_href' => '', 'status' => 'past' ),
		);
	}

	/** @return array<int,array<string,mixed>> */
	public static function sponsors(): array {
		return array(
			array( 'name' => 'Sponsor 01', 'url' => '' ),
			array( 'name' => 'Sponsor 02', 'url' => '' ),
			array( 'name' => 'Sponsor 03', 'url' => '' ),
			array( 'name' => 'Sponsor 04', 'url' => '' ),
			array( 'name' => 'Sponsor 05', 'url' => '' ),
			array( 'name' => 'Sponsor 06', 'url' => '' ),
		);
	}

	/** @return array<int,array<string,mixed>> */
	public static function people(): array {
		return array(
			array( 'name' => 'Priya Nair', 'committee_role' => 'Chair', 'directory_role' => 'Press', 'email' => 'press@clubhouse.example' ),
			array( 'name' => 'Tom Ellison', 'committee_role' => 'Treasurer', 'directory_role' => 'Sponsorship', 'email' => 'sponsors@clubhouse.example' ),
			array( 'name' => 'Grace Okafor', 'committee_role' => 'Secretary', 'directory_role' => 'Venue hire', 'email' => 'hire@clubhouse.example' ),
			array( 'name' => 'Daniel Reed', 'committee_role' => 'Membership', 'directory_role' => 'Membership', 'email' => 'membership@clubhouse.example' ),
			array( 'name' => 'Aisha Khan', 'committee_role' => 'Safeguarding', 'directory_role' => 'Juniors & safeguarding', 'email' => 'safeguarding@clubhouse.example' ),
			array( 'name' => 'Mark Bailey', 'committee_role' => 'Grounds', 'directory_role' => '', 'email' => '' ),
			array( 'name' => 'The club office', 'committee_role' => '', 'directory_role' => 'General', 'email' => 'hello@clubhouse.example' ),
		);
	}
}
```

- [ ] **Step 5: Create `Demo_Collections`**

```php
<?php
// includes/collections/class-demo-collections.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * DB-free Collections backed by Demo_Content. Used by the preview and tests so
 * pages render real-shaped collection data without WordPress.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Demo_Collections implements Blueworx_Clubhouse_Collections {
	public function sports(): array {
		return Blueworx_Clubhouse_Demo_Content::sports();
	}
	public function teams(): array {
		return Blueworx_Clubhouse_Demo_Content::teams();
	}
	public function fixtures(): array {
		return Blueworx_Clubhouse_Demo_Content::fixtures();
	}
	public function events(): array {
		return Blueworx_Clubhouse_Demo_Content::events();
	}
	public function sponsors(): array {
		return Blueworx_Clubhouse_Demo_Content::sponsors();
	}
	public function people(): array {
		return Blueworx_Clubhouse_Demo_Content::people();
	}
}
```

Add to `includes/bootstrap.php` after the render requires:

```php
// Collections (pure)
require_once __DIR__ . '/collections/interface-collections.php';
require_once __DIR__ . '/collections/class-demo-content.php';
require_once __DIR__ . '/collections/class-demo-collections.php';
```

- [ ] **Step 6: Add `DemoCollectionsTest.php`**

```php
<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class DemoCollectionsTest extends TestCase {
	public function test_delegates_to_demo_content(): void {
		$c = new Blueworx_Clubhouse_Demo_Collections();
		$this->assertSame( Blueworx_Clubhouse_Demo_Content::sports(), $c->sports() );
		$this->assertSame( Blueworx_Clubhouse_Demo_Content::people(), $c->people() );
		$this->assertInstanceOf( Blueworx_Clubhouse_Collections::class, $c );
	}
}
```

- [ ] **Step 7: Run tests + lint**

Run: `vendor/bin/phpunit --filter "DemoContentTest|DemoCollectionsTest" && composer lint`
Expected: green; clean.

- [ ] **Step 8: Commit**

```bash
git add includes/collections/interface-collections.php includes/collections/class-demo-content.php includes/collections/class-demo-collections.php includes/bootstrap.php tests/php/DemoContentTest.php tests/php/DemoCollectionsTest.php
git commit -m "feat: add Collections interface + Demo_Content + Demo_Collections"
```

---

### Task 2: Thread `Collections` through the render path; project Sports

**Files:**
- Modify: `includes/render/class-page-map.php`, `includes/render/class-page-renderer.php`, `preview/index.php`, `includes/frontend/class-frontend.php`, `tests/php/bootstrap.php` (none needed — bootstrap already loads collections), existing tests: `tests/php/PageMapTest.php`, `tests/php/PageRendererTest.php`, `tests/php/PreviewRenderTest.php`, `tests/php/FrontendTest.php`

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Collections`, `Blueworx_Clubhouse_Demo_Collections`.
- Produces: page methods `home/about/membership/contact/login/sports/teams/events/calendar( Branding, Visibility, Collections ): string`; `Page_Map::render( string, Branding, Visibility, Collections ): string`.

- [ ] **Step 1: Update the failing tests first** — change every `Page_Renderer::<page>( $branding, $visibility )` and `Page_Map::render( $slug, $branding, $visibility )` call in `tests/php/PageMapTest.php`, `tests/php/PageRendererTest.php`, `tests/php/PreviewRenderTest.php` to pass a third arg `new Blueworx_Clubhouse_Demo_Collections()`. Add a Sports-projection assertion to `PageRendererTest.php`:

```php
	public function test_sports_page_renders_collection_sports(): void {
		$html = Blueworx_Clubhouse_Page_Renderer::sports(
			new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() ),
			new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() ),
			new Blueworx_Clubhouse_Demo_Collections()
		);
		// Sports page shows all six sports with the stat-card chip + description.
		$this->assertStringContainsString( 'Rugby', $html );
		$this->assertStringContainsString( 'Netball', $html );
		$this->assertStringContainsString( 'Senior, colts and touch rugby, from minis upward.', $html );
		$this->assertSame( 6, substr_count( $html, 'ch-scard__title' ) );
	}

	public function test_home_shows_first_four_sports_as_cards(): void {
		$html = Blueworx_Clubhouse_Page_Renderer::home(
			new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() ),
			new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() ),
			new Blueworx_Clubhouse_Demo_Collections()
		);
		$this->assertStringContainsString( 'Senior · colts · touch', $html );  // Home uses the short subtitle
		$this->assertStringNotContainsString( 'Netball', substr( $html, strpos( $html, 'Our sports' ), 600 ) );  // only first 4
	}
```

(`ch-scard__title` is the Sports/Teams stat-card title class — verify the exact class in `class-sections.php::stat_card_grid` before running; if it differs, use the real one.)

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/phpunit --filter "PageRendererTest|PageMapTest|PreviewRenderTest"`
Expected: FAIL — too few arguments to the page methods (signature not yet updated).

- [ ] **Step 3: Thread the param through `Page_Map::render`**

In `includes/render/class-page-map.php`, change `render()` to:

```php
	public static function render(
		string $slug,
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility,
		Blueworx_Clubhouse_Collections $collections
	): string {
		$method = 'home';
		foreach ( self::pages() as $page ) {
			if ( $page['slug'] === $slug ) {
				$method = $page['method'];
				break;
			}
		}
		return call_user_func(
			array( Blueworx_Clubhouse_Page_Renderer::class, $method ),
			$branding,
			$visibility,
			$collections
		);
	}
```

- [ ] **Step 4: Update every page-method signature + the Sports projection**

In `includes/render/class-page-renderer.php`, add `Blueworx_Clubhouse_Collections $collections` as the third parameter to all nine page methods (`home`, `about`, `membership`, `contact`, `login`, `sports`, `teams`, `events`, `calendar`). membership/login keep the param unused.

Replace the Home sports `card_grid` `cards` literal (lines ~176-181) with a projection of the first four collection sports:

```php
			$sports = array_slice( $collections->sports(), 0, 4 );
			$out .= Blueworx_Clubhouse_Sections::card_grid( array(
				'eyebrow'    => 'Our sports',
				'heading'    => 'Pick your game.',
				'link_label' => 'All sections →',
				'link_href'  => '?page=sports',
				'cards'      => array_map(
					static function ( array $s ): array {
						return array(
							'image'     => $s['image'],
							'image_alt' => $s['title'],
							'tag'       => $s['label'],
							'title'     => $s['title'],
							'subtitle'  => $s['subtitle'],
						);
					},
					$sports
				),
			) );
```

Replace the Sports page `stat_card_grid` `cards` literal (lines ~570-589) with a projection of all collection sports:

```php
			$out .= Blueworx_Clubhouse_Sections::stat_card_grid( array(
				'eyebrow'    => 'All sections',
				'heading'    => 'Pick your sport.',
				'link_label' => 'Join the club →',
				'link_href'  => '?page=membership',
				'cards'      => array_map(
					static function ( array $s ): array {
						return array(
							'image'       => $s['image'],
							'image_alt'   => $s['title'],
							'chip'        => $s['label'],
							'title'       => $s['title'],
							'description' => $s['description'],
							'stats'       => array(
								array( 'value' => $s['stat1_value'], 'label' => $s['stat1_label'] ),
								array( 'value' => $s['stat2_value'], 'label' => $s['stat2_label'] ),
							),
						);
					},
					$collections->sports()
				),
			) );
```

- [ ] **Step 5: Update callers** — `preview/index.php`: at the `Page_Map::render(...)` call, pass `new Blueworx_Clubhouse_Demo_Collections()` as the fourth argument. `includes/frontend/class-frontend.php`: in `render_body()`, pass a collections instance — for now `new Blueworx_Clubhouse_Demo_Collections()` (Task 6 swaps this to `WP_Collections`); update the `context()`/`render_body()` accordingly so it compiles.

- [ ] **Step 6: Run tests + lint**

Run: `vendor/bin/phpunit && composer lint`
Expected: all green (existing structure assertions still pass because the projected data matches the old literals for the first four / all six sports), clean.

- [ ] **Step 7: Preview parity check**

Run:
```bash
php -S 127.0.0.1:8131 >/tmp/p.log 2>&1 &
sleep 1
for p in home sports; do printf '%s ' "$p"; curl -s "http://127.0.0.1:8131/preview/?page=$p" | grep -c 'Rugby'; done
kill %1
```
Expected: both pages contain "Rugby" (home ≥1, sports ≥1).

- [ ] **Step 8: Commit**

```bash
git add includes/render/class-page-map.php includes/render/class-page-renderer.php preview/index.php includes/frontend/class-frontend.php tests/php/
git commit -m "feat: thread Collections through the render path; project Sports from the repository"
```

---

### Task 3: Project Teams, Events, Sponsors, People

**Files:**
- Modify: `includes/render/class-page-renderer.php`
- Test: `tests/php/PageRendererTest.php`

**Interfaces:**
- Consumes: `Collections::teams()/events()/sponsors()/people()` canonical arrays (Task 1 shapes).

- [ ] **Step 1: Write failing projection assertions** (append to `PageRendererTest.php`)

```php
	private function render( string $page ): string {
		return Blueworx_Clubhouse_Page_Map::render(
			$page,
			new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() ),
			new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() ),
			new Blueworx_Clubhouse_Demo_Collections()
		);
	}

	public function test_teams_projected(): void {
		$html = $this->render( 'teams' );
		$this->assertStringContainsString( '1st XV', $html );
		$this->assertStringContainsString( 'Saturday league rugby, Division 3 South.', $html );
		$this->assertStringContainsString( 'Match day', $html );
	}

	public function test_events_upcoming_and_archive_projected(): void {
		$html = $this->render( 'events' );
		$this->assertStringContainsString( 'Club Open Day', $html );          // upcoming
		$this->assertStringContainsString( 'Register interest', $html );      // upcoming CTA
		$this->assertStringContainsString( 'Summer BBQ &amp; Family Day', $html ); // past (escaped &)
	}

	public function test_sponsors_projected(): void {
		$this->assertStringContainsString( 'Sponsor 01', $this->render( 'home' ) );
	}

	public function test_committee_blanks_email_directory_shows_it(): void {
		$about = $this->render( 'about' );
		$this->assertStringContainsString( 'Priya Nair', $about );
		$this->assertStringNotContainsString( 'press@clubhouse.example', $about ); // committee blanks email
		$contact = $this->render( 'contact' );
		$this->assertStringContainsString( 'membership@clubhouse.example', $contact ); // directory shows email
	}
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/phpunit --filter PageRendererTest`
Expected: FAIL — projections not yet in place (still literal arrays).

- [ ] **Step 3: Replace the Teams `stat_card_grid` literal** (lines ~635-648) with:

```php
			$out .= Blueworx_Clubhouse_Sections::stat_card_grid( array(
				'eyebrow'    => 'Squads',
				'heading'    => 'Find your team.',
				'link_label' => '',
				'link_href'  => '',
				'cards'      => array_map(
					static function ( array $t ): array {
						return array(
							'image'       => $t['image'],
							'image_alt'   => $t['sport'] . ' ' . $t['title'],
							'chip'        => $t['sport'],
							'title'       => $t['title'],
							'description' => $t['description'],
							'stats'       => array(
								array( 'value' => $t['match_day'], 'label' => 'Match day' ),
								array( 'value' => $t['league'], 'label' => 'League' ),
							),
						);
					},
					$collections->teams()
				),
			) );
```

- [ ] **Step 4: Replace the Events `event_grid` + `event_archive` literals** (lines ~691-709) with projections that split by `status`:

```php
			$upcoming = array_values( array_filter( $collections->events(), static fn( $e ) => 'upcoming' === $e['status'] ) );
			$out .= Blueworx_Clubhouse_Sections::event_grid( array(
				'eyebrow' => 'Coming up',
				'heading' => 'Upcoming events',
				'cards'   => array_map(
					static function ( array $e ): array {
						return array(
							'tag'       => $e['tag'],
							'date'      => $e['date'],
							'title'     => $e['title'],
							'detail'    => $e['detail'],
							'cta_label' => $e['cta_label'],
							'cta_href'  => $e['cta_href'],
						);
					},
					$upcoming
				),
			) );
```
and
```php
			$past = array_values( array_filter( $collections->events(), static fn( $e ) => 'past' === $e['status'] ) );
			$out .= Blueworx_Clubhouse_Sections::event_archive( array(
				'heading' => 'Recently at the club',
				'rows'    => array_map(
					static function ( array $e ): array {
						return array( 'date' => $e['date'], 'tag' => $e['tag'], 'title' => $e['title'] );
					},
					$past
				),
			) );
```
Also replace the Home `activity_tabs` `events` sub-array (lines ~227-231) with the upcoming events mapped to `{tag,date,title,detail}` (first 3):
```php
				'events'   => array_map(
					static function ( array $e ): array {
						return array( 'tag' => $e['tag'], 'date' => $e['date'], 'title' => $e['title'], 'detail' => $e['detail'] );
					},
					array_slice( array_values( array_filter( $collections->events(), static fn( $e ) => 'upcoming' === $e['status'] ) ), 0, 3 )
				),
```

- [ ] **Step 5: Replace the Home `sponsors` `names` literal** (line ~256) with:

```php
				'names'   => array_map( static fn( array $s ): string => $s['name'], $collections->sponsors() ),
```

- [ ] **Step 6: Replace the About committee + Contact directory `people_grid` literals** (lines ~318-325 and ~498-505) with projections:

About committee:
```php
				'people'  => array_map(
					static function ( array $p ): array {
						return array( 'name' => $p['name'], 'role' => $p['committee_role'], 'email' => '' );
					},
					array_values( array_filter( $collections->people(), static fn( $p ) => '' !== $p['committee_role'] ) )
				),
```
Contact directory:
```php
				'people'  => array_map(
					static function ( array $p ): array {
						return array( 'name' => $p['name'], 'role' => $p['directory_role'], 'email' => $p['email'] );
					},
					array_values( array_filter( $collections->people(), static fn( $p ) => '' !== $p['directory_role'] ) )
				),
```

- [ ] **Step 7: Run tests + lint**

Run: `vendor/bin/phpunit && composer lint`
Expected: all green, clean.

- [ ] **Step 8: Commit**

```bash
git add includes/render/class-page-renderer.php tests/php/PageRendererTest.php
git commit -m "feat: project Teams, Events, Sponsors and People from the repository"
```

---

### Task 4: Fixtures projection (Home tabs + Calendar months)

**Files:**
- Create: `includes/render/class-fixture-projection.php`
- Modify: `includes/bootstrap.php` (require it), `includes/render/class-page-renderer.php` (home activity_tabs fixtures/results; calendar months)
- Test: `tests/php/FixtureProjectionTest.php`

**Interfaces:**
- Consumes: fixture canonical arrays (Task 1 shape).
- Produces:
  - `Blueworx_Clubhouse_Fixture_Projection::home_fixtures( array $fixtures, int $limit = 3 ): array` → `[{month,day,competition,time,matchup}]` (upcoming only, date asc)
  - `::home_results( array $fixtures, int $limit = 3 ): array` → `[{date,home,away,score,outcome}]` (played only, date desc)
  - `::calendar_months( array $fixtures ): array` → `[{label,rows:[{date,competition,matchup,detail,outcome}]}]` (grouped by month-year, months in reverse-chron of first row)

- [ ] **Step 1: Write the failing test** (`tests/php/FixtureProjectionTest.php`)

```php
<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class FixtureProjectionTest extends TestCase {

	private function fx(): array {
		return Blueworx_Clubhouse_Demo_Content::fixtures();
	}

	public function test_home_fixtures_are_upcoming_only_with_split_date(): void {
		$rows = Blueworx_Clubhouse_Fixture_Projection::home_fixtures( $this->fx() );
		$this->assertCount( 3, $rows );
		$this->assertSame( array( 'month', 'day', 'competition', 'time', 'matchup' ), array_keys( $rows[0] ) );
		$this->assertSame( 'JUL', $rows[0]['month'] );
		$this->assertSame( '12', $rows[0]['day'] );
		$this->assertSame( 'ClubHouse vs Riverside RFC', $rows[0]['matchup'] );
	}

	public function test_home_results_are_played_only_desc(): void {
		$rows = Blueworx_Clubhouse_Fixture_Projection::home_results( $this->fx() );
		$this->assertCount( 3, $rows );
		$this->assertSame( array( 'date', 'home', 'away', 'score', 'outcome' ), array_keys( $rows[0] ) );
		// Most recent played first: Cricket 2026-07-05.
		$this->assertSame( 'JUL 5', $rows[0]['date'] );
		$this->assertContains( $rows[0]['outcome'], array( 'W', 'L', 'D' ) );
	}

	public function test_calendar_groups_by_month_with_detail(): void {
		$months = Blueworx_Clubhouse_Fixture_Projection::calendar_months( $this->fx() );
		$labels = array_column( $months, 'label' );
		$this->assertSame( array( 'July', 'June' ), $labels );
		$july = $months[0]['rows'];
		// An upcoming row uses "{venue} · {time}" detail and empty outcome.
		$up = array_values( array_filter( $july, static fn( $r ) => '' === $r['outcome'] ) )[0];
		$this->assertStringContainsString( '·', $up['detail'] );
		// A played row uses the result_summary as detail.
		$won = array_values( array_filter( $july, static fn( $r ) => 'W' === $r['outcome'] ) )[0];
		$this->assertSame( 'Won by 34 runs', $won['detail'] );
	}
}
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/phpunit --filter FixtureProjectionTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement the projection** (pure `DateTimeImmutable`)

```php
<?php
// includes/render/class-fixture-projection.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Projects canonical fixtures to the three renderer shapes: Home upcoming
 * (activity_tabs fixtures), Home played (results), and Calendar month groups.
 * Pure — no WordPress, no ambient time; ordering is by the stored match_date.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Fixture_Projection {

	private static function date( string $iso ): DateTimeImmutable {
		return new DateTimeImmutable( $iso );
	}

	/** @param array<int,array<string,mixed>> $fixtures @return array<int,array<string,string>> */
	public static function home_fixtures( array $fixtures, int $limit = 3 ): array {
		$upcoming = array_values( array_filter( $fixtures, static fn( $f ) => '' === $f['outcome'] ) );
		usort( $upcoming, static fn( $a, $b ) => strcmp( $a['match_date'], $b['match_date'] ) );
		$upcoming = array_slice( $upcoming, 0, $limit );
		return array_map(
			static function ( array $f ): array {
				$d = self::date( $f['match_date'] );
				return array(
					'month'       => strtoupper( $d->format( 'M' ) ),
					'day'         => $d->format( 'j' ),
					'competition' => $f['sport'],
					'time'        => $f['kickoff_time'],
					'matchup'     => $f['home'] . ' vs ' . $f['away'],
				);
			},
			$upcoming
		);
	}

	/** @param array<int,array<string,mixed>> $fixtures @return array<int,array<string,string>> */
	public static function home_results( array $fixtures, int $limit = 3 ): array {
		$played = array_values( array_filter( $fixtures, static fn( $f ) => '' !== $f['outcome'] ) );
		usort( $played, static fn( $a, $b ) => strcmp( $b['match_date'], $a['match_date'] ) );
		$played = array_slice( $played, 0, $limit );
		return array_map(
			static function ( array $f ): array {
				$d = self::date( $f['match_date'] );
				return array(
					'date'    => strtoupper( $d->format( 'M' ) ) . ' ' . $d->format( 'j' ),
					'home'    => $f['home'],
					'away'    => $f['away'],
					'score'   => $f['score'],
					'outcome' => $f['outcome'],
				);
			},
			$played
		);
	}

	/** @param array<int,array<string,mixed>> $fixtures @return array<int,array{label:string,rows:array<int,array<string,string>>}> */
	public static function calendar_months( array $fixtures ): array {
		$sorted = $fixtures;
		usort( $sorted, static fn( $a, $b ) => strcmp( $b['match_date'], $a['match_date'] ) );
		$groups = array();
		$order  = array();
		foreach ( $sorted as $f ) {
			$d     = self::date( $f['match_date'] );
			$label = $d->format( 'F' );
			if ( ! isset( $groups[ $label ] ) ) {
				$groups[ $label ] = array();
				$order[]          = $label;
			}
			$detail = '' === $f['outcome']
				? $f['venue'] . ' · ' . $f['kickoff_time']
				: $f['result_summary'];
			$groups[ $label ][] = array(
				'date'        => $d->format( 'D j' ),
				'competition' => $f['sport'],
				'matchup'     => $f['home'] . ' vs ' . $f['away'],
				'detail'      => $detail,
				'outcome'     => $f['outcome'],
			);
		}
		$out = array();
		foreach ( $order as $label ) {
			$out[] = array( 'label' => $label, 'rows' => $groups[ $label ] );
		}
		return $out;
	}
}
```

Add to `includes/bootstrap.php` after `class-page-map.php`:

```php
require_once __DIR__ . '/render/class-fixture-projection.php';
```

- [ ] **Step 4: Wire the projection into `Page_Renderer`** — replace the Home `activity_tabs` `fixtures` and `results` sub-arrays (lines ~217-226) with:

```php
				'fixtures' => Blueworx_Clubhouse_Fixture_Projection::home_fixtures( $collections->fixtures() ),
				'results'  => Blueworx_Clubhouse_Fixture_Projection::home_results( $collections->fixtures() ),
```
and replace the Calendar `calendar_months` `months` literal (lines ~752-762) with:

```php
				'months'  => Blueworx_Clubhouse_Fixture_Projection::calendar_months( $collections->fixtures() ),
```

- [ ] **Step 5: Run tests + lint**

Run: `vendor/bin/phpunit && composer lint`
Expected: all green, clean.

- [ ] **Step 6: Commit**

```bash
git add includes/render/class-fixture-projection.php includes/bootstrap.php includes/render/class-page-renderer.php tests/php/FixtureProjectionTest.php
git commit -m "feat: project fixtures to Home tabs and Calendar months from the repository"
```

---

### Task 5: `Collection_Mappers` + `WP_Collections`

**Files:**
- Create: `includes/collections/class-collection-mappers.php`, `includes/collections/class-wp-collections.php`
- Modify: `tests/php/wp-stubs.php` (add `get_posts`, `get_post_meta`), `tests/php/bootstrap.php` (require the two new WP-glue files)
- Test: `tests/php/CollectionMappersTest.php`

**Interfaces:**
- Produces:
  - `Blueworx_Clubhouse_Collection_Mappers::sport( array $post ): array` and `team/fixture/event/sponsor/person` — each maps a raw `{title, meta:array}` to the canonical shape, filling defaults for missing meta.
  - `Blueworx_Clubhouse_WP_Collections implements Blueworx_Clubhouse_Collections` — reads posts of each type via `get_posts` + `get_post_meta`, maps via `Collection_Mappers`.

- [ ] **Step 1: Write the failing mapper test** (`tests/php/CollectionMappersTest.php`)

```php
<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class CollectionMappersTest extends TestCase {

	public function test_sport_mapper_fills_canonical_shape(): void {
		$raw = array(
			'title' => 'Rugby',
			'meta'  => array(
				'label' => 'Sat', 'subtitle' => 'Senior · colts', 'description' => 'Rugby desc',
				'stat1_value' => '4', 'stat1_label' => 'Teams', 'stat2_value' => '120', 'stat2_label' => 'Players', 'image' => '',
			),
		);
		$c = Blueworx_Clubhouse_Collection_Mappers::sport( $raw );
		$this->assertSame( 'Rugby', $c['title'] );
		$this->assertSame( 'Sat', $c['label'] );
		$this->assertSame( '120', $c['stat2_value'] );
	}

	public function test_person_mapper_defaults_missing_meta_to_empty(): void {
		$c = Blueworx_Clubhouse_Collection_Mappers::person( array( 'title' => 'Priya Nair', 'meta' => array() ) );
		$this->assertSame( 'Priya Nair', $c['name'] );
		$this->assertSame( '', $c['committee_role'] );
		$this->assertSame( '', $c['directory_role'] );
		$this->assertSame( '', $c['email'] );
	}

	public function test_fixture_mapper_maps_outcome_and_dates(): void {
		$c = Blueworx_Clubhouse_Collection_Mappers::fixture( array(
			'title' => 'Rugby vs Riverside',
			'meta'  => array( 'sport' => 'Rugby · 1st XV', 'match_date' => '2026-07-12', 'kickoff_time' => '14:00', 'venue' => 'Home', 'home_team' => 'ClubHouse', 'away_team' => 'Riverside RFC', 'score' => '', 'outcome' => '', 'result_summary' => '' ),
		) );
		$this->assertSame( 'ClubHouse', $c['home'] );
		$this->assertSame( 'Riverside RFC', $c['away'] );
		$this->assertSame( '', $c['outcome'] );
	}
}
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/phpunit --filter CollectionMappersTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement `Collection_Mappers`** (pure; note the fixture meta keys `home_team`/`away_team` map to canonical `home`/`away`)

```php
<?php
// includes/collections/class-collection-mappers.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps a raw post record ({title, meta:array}) to a canonical collection array.
 * Pure — no WordPress. WP_Collections fetches the raw records; this fills the
 * canonical shape and defaults missing meta to empty strings.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Collection_Mappers {

	private static function m( array $post, string $key ): string {
		return isset( $post['meta'][ $key ] ) ? (string) $post['meta'][ $key ] : '';
	}

	public static function sport( array $post ): array {
		return array(
			'title'       => (string) $post['title'],
			'label'       => self::m( $post, 'label' ),
			'subtitle'    => self::m( $post, 'subtitle' ),
			'description' => self::m( $post, 'description' ),
			'stat1_value' => self::m( $post, 'stat1_value' ),
			'stat1_label' => self::m( $post, 'stat1_label' ),
			'stat2_value' => self::m( $post, 'stat2_value' ),
			'stat2_label' => self::m( $post, 'stat2_label' ),
			'image'       => self::m( $post, 'image' ),
		);
	}

	public static function team( array $post ): array {
		return array(
			'title'       => (string) $post['title'],
			'sport'       => self::m( $post, 'sport' ),
			'description' => self::m( $post, 'description' ),
			'match_day'   => self::m( $post, 'match_day' ),
			'league'      => self::m( $post, 'league' ),
			'image'       => self::m( $post, 'image' ),
		);
	}

	public static function fixture( array $post ): array {
		return array(
			'sport'          => self::m( $post, 'sport' ),
			'match_date'     => self::m( $post, 'match_date' ),
			'kickoff_time'   => self::m( $post, 'kickoff_time' ),
			'venue'          => self::m( $post, 'venue' ),
			'home'           => self::m( $post, 'home_team' ),
			'away'           => self::m( $post, 'away_team' ),
			'score'          => self::m( $post, 'score' ),
			'outcome'        => self::m( $post, 'outcome' ),
			'result_summary' => self::m( $post, 'result_summary' ),
		);
	}

	public static function event( array $post ): array {
		return array(
			'title'     => (string) $post['title'],
			'tag'       => self::m( $post, 'tag' ),
			'date'      => self::m( $post, 'date' ),
			'detail'    => self::m( $post, 'detail' ),
			'cta_label' => self::m( $post, 'cta_label' ),
			'cta_href'  => self::m( $post, 'cta_href' ),
			'status'    => '' === self::m( $post, 'status' ) ? 'upcoming' : self::m( $post, 'status' ),
		);
	}

	public static function sponsor( array $post ): array {
		return array( 'name' => (string) $post['title'], 'url' => self::m( $post, 'url' ) );
	}

	public static function person( array $post ): array {
		return array(
			'name'           => (string) $post['title'],
			'committee_role' => self::m( $post, 'committee_role' ),
			'directory_role' => self::m( $post, 'directory_role' ),
			'email'          => self::m( $post, 'email' ),
		);
	}
}
```

- [ ] **Step 4: Extend the WP shim** (`tests/php/wp-stubs.php`) — add, guarded:

```php
if ( ! function_exists( 'get_posts' ) ) {
	function get_posts( array $args = array() ) {
		$type = $args['post_type'] ?? '';
		return $GLOBALS['wp_stub_posts'][ $type ] ?? array();
	}
}
if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( int $id, string $key = '', bool $single = false ) {
		$meta = $GLOBALS['wp_stub_postmeta'][ $id ] ?? array();
		if ( '' === $key ) {
			return $meta;
		}
		return $single ? ( $meta[ $key ] ?? '' ) : array( $meta[ $key ] ?? '' );
	}
}
```
and initialise `$GLOBALS['wp_stub_posts'] = array();` and `$GLOBALS['wp_stub_postmeta'] = array();` in the file header and in `wp_stub_reset()`.

- [ ] **Step 5: Implement `WP_Collections`** (thin fetch over the tested mapper)

```php
<?php
// includes/collections/class-wp-collections.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads the six collections from their custom-post-type posts and maps each to
 * the canonical shape via Collection_Mappers. Thin WordPress glue — the mapping
 * logic is pure and unit-tested.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_WP_Collections implements Blueworx_Clubhouse_Collections {

	/** @param callable(array):array $mapper */
	private function fetch( string $post_type, callable $mapper ): array {
		$posts = get_posts( array(
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'numberposts'    => -1,
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
		) );
		$out = array();
		foreach ( $posts as $post ) {
			$out[] = $mapper( array(
				'title' => get_the_title( $post ),
				'meta'  => get_post_meta( is_object( $post ) ? $post->ID : (int) $post, '', false ) ? self::flatten_meta( is_object( $post ) ? $post->ID : (int) $post ) : array(),
			) );
		}
		return $out;
	}

	private static function flatten_meta( int $id ): array {
		$raw  = get_post_meta( $id );
		$flat = array();
		foreach ( is_array( $raw ) ? $raw : array() as $key => $vals ) {
			$flat[ $key ] = is_array( $vals ) ? (string) ( $vals[0] ?? '' ) : (string) $vals;
		}
		return $flat;
	}

	public function sports(): array {
		return $this->fetch( 'clubhouse_sport', array( Blueworx_Clubhouse_Collection_Mappers::class, 'sport' ) );
	}
	public function teams(): array {
		return $this->fetch( 'clubhouse_team', array( Blueworx_Clubhouse_Collection_Mappers::class, 'team' ) );
	}
	public function fixtures(): array {
		return $this->fetch( 'clubhouse_fixture', array( Blueworx_Clubhouse_Collection_Mappers::class, 'fixture' ) );
	}
	public function events(): array {
		return $this->fetch( 'clubhouse_event', array( Blueworx_Clubhouse_Collection_Mappers::class, 'event' ) );
	}
	public function sponsors(): array {
		return $this->fetch( 'clubhouse_sponsor', array( Blueworx_Clubhouse_Collection_Mappers::class, 'sponsor' ) );
	}
	public function people(): array {
		return $this->fetch( 'clubhouse_person', array( Blueworx_Clubhouse_Collection_Mappers::class, 'person' ) );
	}
}
```

(NOTE for the implementer: `WP_Collections::fetch` is WP-runtime-only and not unit-tested; keep it thin and correct. The `flatten_meta`/`get_the_title` calls assume standard WP; simplify if the shim shape differs — the tested contract is `Collection_Mappers`, which takes `{title, meta:array<string,string>}`. Ensure whatever `fetch` builds matches that contract.)

Require both new files for tests — add to `tests/php/bootstrap.php` after the frontend require:
```php
require_once dirname( __DIR__, 2 ) . '/includes/collections/class-collection-mappers.php';
require_once dirname( __DIR__, 2 ) . '/includes/collections/class-wp-collections.php';
```

- [ ] **Step 6: Run tests + lint**

Run: `vendor/bin/phpunit && composer lint`
Expected: green, clean.

- [ ] **Step 7: Commit**

```bash
git add includes/collections/class-collection-mappers.php includes/collections/class-wp-collections.php tests/php/wp-stubs.php tests/php/bootstrap.php tests/php/CollectionMappersTest.php
git commit -m "feat: add Collection_Mappers (pure) + WP_Collections (thin CPT reader)"
```

---

### Task 6: `Collection_Types` + `Collection_Seeder` + wiring

**Files:**
- Create: `includes/collections/class-collection-types.php`, `includes/collections/class-collection-seeder.php`
- Modify: `blueworx-labs-clubhouse.php` (require the WP collection classes; activation seeds), `includes/frontend/class-frontend.php` (register CPTs on `init`; use `WP_Collections` in `render_body`), `tests/php/wp-stubs.php` (add `register_post_type`, `register_post_meta`, `wp_insert_post`, `add_post_meta`)
- Test: `tests/php/CollectionTypesTest.php`, `tests/php/CollectionSeederTest.php`

**Interfaces:**
- Produces:
  - `Blueworx_Clubhouse_Collection_Types::register(): void` — registers the six CPTs + their meta.
  - `Blueworx_Clubhouse_Collection_Types::POST_TYPES` — `['clubhouse_sport','clubhouse_team','clubhouse_fixture','clubhouse_event','clubhouse_sponsor','clubhouse_person']`.
  - `Blueworx_Clubhouse_Collection_Seeder::seed(): void` — inserts demo posts for each empty type.

- [ ] **Step 1: Write the failing tests**

`tests/php/CollectionTypesTest.php`:
```php
<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class CollectionTypesTest extends TestCase {
	protected function setUp(): void { wp_stub_reset(); }

	public function test_registers_six_post_types(): void {
		Blueworx_Clubhouse_Collection_Types::register();
		$registered = array_map( static fn( $c ) => $c['args'][0], wp_stub_calls( 'register_post_type' ) );
		foreach ( Blueworx_Clubhouse_Collection_Types::POST_TYPES as $type ) {
			$this->assertContains( $type, $registered );
		}
		$this->assertCount( 6, wp_stub_calls( 'register_post_type' ) );
	}
}
```

`tests/php/CollectionSeederTest.php`:
```php
<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class CollectionSeederTest extends TestCase {
	protected function setUp(): void { wp_stub_reset(); }

	public function test_seeds_when_empty(): void {
		Blueworx_Clubhouse_Collection_Seeder::seed();
		$inserts = wp_stub_calls( 'wp_insert_post' );
		// 6 sports + 4 teams + 7 fixtures + 7 events + 6 sponsors + 7 people = 37.
		$this->assertSame( 37, count( $inserts ) );
	}

	public function test_skips_when_already_populated(): void {
		$GLOBALS['wp_stub_posts']['clubhouse_sport'] = array( (object) array( 'ID' => 1 ) );
		Blueworx_Clubhouse_Collection_Seeder::seed();
		$sportInserts = array_filter(
			wp_stub_calls( 'wp_insert_post' ),
			static fn( $c ) => ( $c['args'][0]['post_type'] ?? '' ) === 'clubhouse_sport'
		);
		$this->assertCount( 0, $sportInserts ); // sports skipped because already populated
	}
}
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/phpunit --filter "CollectionTypesTest|CollectionSeederTest"`
Expected: FAIL — classes not found.

- [ ] **Step 3: Extend the shim** (`tests/php/wp-stubs.php`) — add guarded stubs:

```php
if ( ! function_exists( 'register_post_type' ) ) {
	function register_post_type( ...$a ) { wp_stub_record( 'register_post_type', $a ); return (object) array( 'name' => $a[0] ?? '' ); }
}
if ( ! function_exists( 'register_post_meta' ) ) {
	function register_post_meta( ...$a ) { wp_stub_record( 'register_post_meta', $a ); return true; }
}
if ( ! function_exists( 'wp_insert_post' ) ) {
	function wp_insert_post( ...$a ) { wp_stub_record( 'wp_insert_post', $a ); return count( $GLOBALS['wp_stub_calls'] ); }
}
if ( ! function_exists( 'add_post_meta' ) ) {
	function add_post_meta( ...$a ) { wp_stub_record( 'add_post_meta', $a ); return true; }
}
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) { return $text; }
}
if ( ! function_exists( 'get_the_title' ) ) {
	function get_the_title( $post = 0 ) { return is_object( $post ) ? ( $post->post_title ?? '' ) : ''; }
}
```

- [ ] **Step 4: Implement `Collection_Types`**

```php
<?php
// includes/collections/class-collection-types.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the six collection custom post types and their meta keys. Editing UI
 * (custom-field meta boxes) is the admin-flow plan's job; these register with a
 * basic admin UI so seeded posts are visible/manageable.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Collection_Types {

	public const POST_TYPES = array(
		'clubhouse_sport',
		'clubhouse_team',
		'clubhouse_fixture',
		'clubhouse_event',
		'clubhouse_sponsor',
		'clubhouse_person',
	);

	/** @var array<string,array<int,string>> meta keys per type */
	private const META = array(
		'clubhouse_sport'   => array( 'label', 'subtitle', 'description', 'stat1_value', 'stat1_label', 'stat2_value', 'stat2_label', 'image' ),
		'clubhouse_team'    => array( 'sport', 'description', 'match_day', 'league', 'image' ),
		'clubhouse_fixture' => array( 'sport', 'match_date', 'kickoff_time', 'venue', 'home_team', 'away_team', 'score', 'outcome', 'result_summary' ),
		'clubhouse_event'   => array( 'tag', 'date', 'detail', 'cta_label', 'cta_href', 'status' ),
		'clubhouse_sponsor' => array( 'url' ),
		'clubhouse_person'  => array( 'committee_role', 'directory_role', 'email' ),
	);

	private const LABELS = array(
		'clubhouse_sport'   => array( 'Sport', 'Sports' ),
		'clubhouse_team'    => array( 'Team', 'Teams' ),
		'clubhouse_fixture' => array( 'Fixture', 'Fixtures' ),
		'clubhouse_event'   => array( 'Event', 'Events' ),
		'clubhouse_sponsor' => array( 'Sponsor', 'Sponsors' ),
		'clubhouse_person'  => array( 'Person', 'People' ),
	);

	public static function register(): void {
		foreach ( self::POST_TYPES as $type ) {
			list( $singular, $plural ) = self::LABELS[ $type ];
			register_post_type( $type, array(
				'labels'       => array( 'name' => $plural, 'singular_name' => $singular ),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => true,
				'menu_icon'    => 'dashicons-groups',
				'supports'     => array( 'title', 'page-attributes' ),
				'has_archive'  => false,
				'rewrite'      => false,
			) );
			foreach ( self::META[ $type ] as $key ) {
				register_post_meta( $type, $key, array(
					'type'         => 'string',
					'single'       => true,
					'show_in_rest' => false,
					'default'      => '',
				) );
			}
		}
	}
}
```

- [ ] **Step 5: Implement `Collection_Seeder`** (seed each empty type from `Demo_Content`; map canonical keys to meta keys — inverse of the mappers, note `home`/`away` → `home_team`/`away_team`, `name` → title for sponsor/person)

```php
<?php
// includes/collections/class-collection-seeder.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Seeds each empty collection with the ClubHouse demo content on activation, so
 * a fresh install renders a populated site before any data is entered.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Collection_Seeder {

	public static function seed(): void {
		self::seed_type( 'clubhouse_sport', Blueworx_Clubhouse_Demo_Content::sports(), 'title', array( 'label', 'subtitle', 'description', 'stat1_value', 'stat1_label', 'stat2_value', 'stat2_label', 'image' ) );
		self::seed_type( 'clubhouse_team', Blueworx_Clubhouse_Demo_Content::teams(), 'title', array( 'sport', 'description', 'match_day', 'league', 'image' ) );
		self::seed_fixtures();
		self::seed_type( 'clubhouse_event', Blueworx_Clubhouse_Demo_Content::events(), 'title', array( 'tag', 'date', 'detail', 'cta_label', 'cta_href', 'status' ) );
		self::seed_type( 'clubhouse_sponsor', Blueworx_Clubhouse_Demo_Content::sponsors(), 'name', array( 'url' ) );
		self::seed_type( 'clubhouse_person', Blueworx_Clubhouse_Demo_Content::people(), 'name', array( 'committee_role', 'directory_role', 'email' ) );
	}

	/** @param array<int,array<string,mixed>> $items */
	private static function seed_type( string $type, array $items, string $title_key, array $meta_keys ): void {
		if ( self::has_posts( $type ) ) {
			return;
		}
		$order = 0;
		foreach ( $items as $item ) {
			$id = wp_insert_post( array(
				'post_type'   => $type,
				'post_status' => 'publish',
				'post_title'  => (string) $item[ $title_key ],
				'menu_order'  => $order++,
			) );
			foreach ( $meta_keys as $key ) {
				add_post_meta( (int) $id, $key, isset( $item[ $key ] ) ? (string) $item[ $key ] : '' );
			}
		}
	}

	private static function seed_fixtures(): void {
		if ( self::has_posts( 'clubhouse_fixture' ) ) {
			return;
		}
		$order = 0;
		foreach ( Blueworx_Clubhouse_Demo_Content::fixtures() as $f ) {
			$id = wp_insert_post( array(
				'post_type'   => 'clubhouse_fixture',
				'post_status' => 'publish',
				'post_title'  => $f['home'] . ' vs ' . $f['away'],
				'menu_order'  => $order++,
			) );
			$meta = array(
				'sport' => $f['sport'], 'match_date' => $f['match_date'], 'kickoff_time' => $f['kickoff_time'],
				'venue' => $f['venue'], 'home_team' => $f['home'], 'away_team' => $f['away'],
				'score' => $f['score'], 'outcome' => $f['outcome'], 'result_summary' => $f['result_summary'],
			);
			foreach ( $meta as $key => $value ) {
				add_post_meta( (int) $id, $key, (string) $value );
			}
		}
	}

	private static function has_posts( string $type ): bool {
		$existing = get_posts( array( 'post_type' => $type, 'post_status' => 'any', 'numberposts' => 1 ) );
		return ! empty( $existing );
	}
}
```

- [ ] **Step 6: Wire into `Frontend` + main file** — in `includes/frontend/class-frontend.php`: (a) in `register()`, add `add_action( 'init', array( Blueworx_Clubhouse_Collection_Types::class, 'register' ) );` (b) in `render_body()`/`context()`, replace `new Blueworx_Clubhouse_Demo_Collections()` with `new Blueworx_Clubhouse_WP_Collections()`. In `blueworx-labs-clubhouse.php`: require the six collection class files (mappers, wp-collections, types, seeder) after the frontend require; in the activation hook, call `Blueworx_Clubhouse_Collection_Types::register();` then `Blueworx_Clubhouse_Collection_Seeder::seed();` **before** `flush_rewrite_rules();`.

Require the two new WP-glue files for tests — add to `tests/php/bootstrap.php`:
```php
require_once dirname( __DIR__, 2 ) . '/includes/collections/class-collection-types.php';
require_once dirname( __DIR__, 2 ) . '/includes/collections/class-collection-seeder.php';
```

- [ ] **Step 7: Run tests + lint**

Run: `vendor/bin/phpunit && composer lint`
Expected: all green, clean.

- [ ] **Step 8: Commit**

```bash
git add includes/collections/class-collection-types.php includes/collections/class-collection-seeder.php includes/frontend/class-frontend.php blueworx-labs-clubhouse.php tests/php/wp-stubs.php tests/php/bootstrap.php tests/php/CollectionTypesTest.php tests/php/CollectionSeederTest.php
git commit -m "feat: register six collection CPTs + seed demo content on activation; use WP_Collections"
```

---

### Task 7: Preview smoke, version bump, deployment zip

**Files:**
- Modify: `tests/smoke.spec.js`, `blueworx-labs-clubhouse.php`, `package.json`, `CHANGELOG.md`

- [ ] **Step 1: Extend the Playwright smoke** — the preview already routes through `Demo_Collections`, so add a representative collection assertion. Append to `tests/smoke.spec.js`:

```js
test('sports page lists collection sports', async ({ page }) => {
  await page.goto('?page=sports');
  await expect(page.getByText('Rugby').first()).toBeVisible();
  await expect(page.getByText('Netball').first()).toBeVisible();
});

test('calendar shows month-grouped fixtures from the collection', async ({ page }) => {
  await page.goto('?page=calendar');
  await expect(page.getByText('July').first()).toBeVisible();
  await expect(page.getByText('Won by 34 runs').first()).toBeVisible();
});
```

- [ ] **Step 2: Run the Playwright smoke**

Run: `npx playwright test`
Expected: all pass (the two new tests + existing five).

- [ ] **Step 3: Bump to 0.13.0** — `blueworx-labs-clubhouse.php` header `* Version:           0.13.0` and `define( 'BLUEWORX_LABS_CLUBHOUSE_VERSION', '0.13.0' );`; `package.json` `"version": "0.13.0",`.

- [ ] **Step 4: Add the changelog entry** (above `## [0.12.0]`)

```markdown
## [0.13.0] - 2026-07-12

### Collections / custom post types

Real data behind the unchanged renderers.

#### New

- **Six custom post types** — Sports, Teams, Fixtures, Events, Sponsors, People — registered with their meta fields. Fixtures is a single type carrying an outcome (empty = upcoming, `W`/`L`/`D` = result), feeding both the Home activity tabs and the Calendar.
- **`Collections` repository** — a pure `Demo_Collections` (preview + tests) and a thin `WP_Collections` (reads the CPT posts) behind one interface; `Page_Renderer` projects the canonical data to each renderer's exact shape, so no renderer changed.
- **Demo content is seeded** on activation from a single `Demo_Content` source, so a fresh install still shows a fully populated site. Per-field editing UI arrives with the admin flow.

#### Notes

- Home and Calendar fixtures are now derived from one consistent set (the previously-hardcoded demo diverged between the two views).
- The `sponsors` renderer takes only names, so a sponsor's URL is stored but unused for now; committee entries render without email (matching the demo), the directory shows emails.
```

- [ ] **Step 5: Full suite + lint**

Run: `vendor/bin/phpunit && composer lint`
Expected: all green, clean.

- [ ] **Step 6: Commit**

```bash
git add tests/smoke.spec.js blueworx-labs-clubhouse.php package.json CHANGELOG.md
git commit -m "chore: bump to 0.13.0; extend preview smoke for collections"
```

- [ ] **Step 7: Refresh the deployment zip** — the new `includes/collections/` files are runtime. Rebuild `<plugin-parent-dir>/blueworx-labs-clubhouse.zip` (top-level `blueworx-labs-clubhouse/`, forward-slash entries via .NET `ZipArchive`, dev-only paths excluded) — controller step at execution time.

## Self-Review

**Spec coverage:**
- Six CPTs registered + meta → Task 6 (`Collection_Types`). ✓
- `Collections` interface + `Demo_Content` + `Demo_Collections` (pure) → Task 1. ✓
- `WP_Collections` + pure `Collection_Mappers` → Task 5. ✓
- Projection in `Page_Renderer`, renderers untouched → Tasks 2–4. ✓
- Fixtures one entity, three shapes → Task 4. ✓
- Seed-if-empty from single `Demo_Content` → Task 6 (`Collection_Seeder`). ✓
- Signature threading (Page_Map, preview, Frontend, tests) → Task 2. ✓
- Sponsors name-only + stored url; committee blanks email → Tasks 3 (projection) + 1 (data). ✓
- WP shim extensions + shim-based tests → Tasks 5, 6. ✓
- Playwright smoke extension → Task 7. ✓
- Version 0.13.0 + changelog + zip → Task 7. ✓

**Placeholder scan:** none — every step carries concrete code/commands. (Task 5's `WP_Collections::fetch` is WP-runtime-only and flagged as such; its tested contract is `Collection_Mappers`.)

**Type consistency:** canonical shapes (Task 1) are consumed identically by projections (2–4), mappers (5), and seeder (6): sport `{title,label,subtitle,description,stat1_value,stat1_label,stat2_value,stat2_label,image}`, fixture `{sport,match_date,kickoff_time,venue,home,away,score,outcome,result_summary}` (meta keys `home_team`/`away_team` ↔ canonical `home`/`away`, handled in both mapper and seeder), event `{title,tag,date,detail,cta_label,cta_href,status}`, person `{name,committee_role,directory_role,email}`, sponsor `{name,url}`. `Collections` method names uniform across interface/Demo/WP.
