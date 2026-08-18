# Social Feed Section (stage one) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship a Home-page section that shows a club's recent Facebook or Instagram posts, fed for now by post links the club pastes, cached, and hidden until a club opts in.

**Architecture:** A `Blueworx_Clubhouse_Feed_Source` interface with one method is the seam. `Manual_Feed_Source` implements it from the content store (stage one, no network). `Social_Feed` is the cache in front of whichever source is active and the only thing the renderer talks to; it copies the three-store caching of `class-surecart-products.php` (transient, short failure marker, unversioned last-good option). `Sections::social_feed()` draws the cards; `Page_Renderer::home()` gates it on visibility.

**Tech Stack:** PHP 8.1+, WordPress core functions only (no new dependency), PHPUnit for units, Playwright against the DB-free preview.

**Spec:** `docs/superpowers/specs/2026-08-18-social-feed-section-design.md`

## Global Constraints

- No new dependency — nothing added to `approved-deps.json`. WordPress core functions only.
- The section ships hidden, via `Visibility::SECTION_DEFAULTS`.
- One platform at a time — Facebook or Instagram, never both.
- A page render never makes an outbound call: everything goes through the cache.
- Every normalised post has `id`, `image`, `caption`, `date`, `permalink`; a record missing `id` or `permalink` is dropped, never rendered.
- The last-good option is NOT keyed to the plugin version.
- All output escaping happens in `class-sections.php` (the render path owns escaping).
- Version bumped and `CHANGELOG.md` updated on the PR (minor bump — this is a feature).
- Run the linter once at the end (`composer lint`); do not loop lint/fix.

## Decisions taken while planning (deviations from the spec, deliberate)

1. **Section key is `social_feed`, labelled "Social feed".** The spec calls it "Social", but `home.social` already exists (the follow-us band above the footer). Two sections cannot share a key.
2. **`posts(): ?array`, not `posts(): array`.** The three failure states need "the fetch failed" to be distinguishable from "there is nothing", exactly as `SureCart_Products::fetch_prices()` returns `?array`. `null` means failed; `array()` means asked, nothing there.
3. **The source is injected through the constructor, not through a `BLUEWORX_CLUBHOUSE_RUNNING_TESTS`-gated static seam.** The spec asked for the gated seam by analogy with `set_raw_fetcher()`; that gate exists because `SureCart_Products` is static. `Social_Feed` takes its source as a constructor argument, so there is no global to poison on a live request and no gate to need. Tests pass a fake source.
4. **Manual items carry a link and an optional caption.** A pasted link alone renders as a card with no words on it. The caption is part of the normalised post shape already, so offering it costs nothing and is what makes stage one presentable. No image: nothing can produce one without a fetch, so manual cards use the existing empty-media placeholder.

---

### Task 1: The seam and the manual source

**Files:**
- Create: `includes/social/interface-feed-source.php`
- Create: `includes/social/class-manual-feed-source.php`
- Modify: `includes/bootstrap.php` (add a "Social" require block after the Content block)
- Test: `tests/php/ManualFeedSourceTest.php`

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Content_Store::get_items( string $page, string $section ): array`
- Produces: `interface Blueworx_Clubhouse_Feed_Source { public function posts(): ?array; }` and `final class Blueworx_Clubhouse_Manual_Feed_Source implements Blueworx_Clubhouse_Feed_Source` with `__construct( Blueworx_Clubhouse_Content_Store $content )`, `posts(): ?array`, and `public static function normalise( array $item ): ?array`. A normalised post is `array{id:string,image:string,caption:string,date:string,permalink:string}`.

- [ ] **Step 1: Write the failing test**

Create `tests/php/ManualFeedSourceTest.php`:

```php
<?php

use PHPUnit\Framework\TestCase;

final class ManualFeedSourceTest extends TestCase {

	private function store( array $items ): Blueworx_Clubhouse_Content_Store {
		$content = new Blueworx_Clubhouse_Content_Store( new Blueworx_Clubhouse_Fake_Storage() );
		$content->set_items( 'home', 'social_feed', $items );
		return $content;
	}

	public function test_a_pasted_link_becomes_a_normalised_post(): void {
		$source = new Blueworx_Clubhouse_Manual_Feed_Source(
			$this->store( array( array( 'href' => 'https://facebook.com/club/posts/1', 'caption' => 'Saturday win' ) ) )
		);
		$posts = $source->posts();
		$this->assertIsArray( $posts );
		$this->assertCount( 1, $posts );
		$this->assertSame( 'https://facebook.com/club/posts/1', $posts[0]['permalink'] );
		$this->assertSame( 'Saturday win', $posts[0]['caption'] );
		$this->assertSame( '', $posts[0]['image'] );
		$this->assertSame( '', $posts[0]['date'] );
		$this->assertNotSame( '', $posts[0]['id'] );
	}

	public function test_the_same_link_always_gets_the_same_id(): void {
		$a = Blueworx_Clubhouse_Manual_Feed_Source::normalise( array( 'href' => 'https://x.test/p/1' ) );
		$b = Blueworx_Clubhouse_Manual_Feed_Source::normalise( array( 'href' => 'https://x.test/p/1' ) );
		$this->assertSame( $a['id'], $b['id'] );
	}

	public function test_a_row_without_a_usable_link_is_dropped_not_rendered(): void {
		$source = new Blueworx_Clubhouse_Manual_Feed_Source(
			$this->store( array(
				array( 'href' => '', 'caption' => 'no link' ),
				array( 'caption' => 'no href key at all' ),
				array( 'href' => 'javascript:alert(1)', 'caption' => 'not a web address' ),
				array( 'href' => 'https://good.test/p/1', 'caption' => 'kept' ),
			) )
		);
		$posts = $source->posts();
		$this->assertCount( 1, $posts );
		$this->assertSame( 'kept', $posts[0]['caption'] );
	}

	public function test_nothing_pasted_is_an_empty_list_never_a_failure(): void {
		$source = new Blueworx_Clubhouse_Manual_Feed_Source( $this->store( array() ) );
		$this->assertSame( array(), $source->posts() );
	}
}
```

If `Blueworx_Clubhouse_Fake_Storage` is not the name used by `tests/php/fakes`, read `tests/php/FakeStorageTest.php` and use whatever fake the existing tests use; do not invent a second fake.

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit --filter ManualFeedSourceTest`
Expected: FAIL — `Class "Blueworx_Clubhouse_Manual_Feed_Source" not found`.

- [ ] **Step 3: Write the interface**

Create `includes/social/interface-feed-source.php`:

```php
<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Where the social feed's posts come from. One method, deliberately: this is
 * the seam the staged plan rests on. Stage one implements it from links a club
 * pastes; stage two implements it from a Meta connection, and nothing above
 * this line changes.
 *
 * @package BlueworxLabsClubhouse
 */
interface Blueworx_Clubhouse_Feed_Source {

	/**
	 * Recent posts, newest first, or null when the fetch itself failed.
	 *
	 * null and array() are different facts and callers depend on the
	 * difference: array() is "asked, there is nothing", null is "could not
	 * ask". Caching the second as the first is what makes an outage look like
	 * a club that never connected — see Social_Feed.
	 *
	 * @return array<int,array{id:string,image:string,caption:string,date:string,permalink:string}>|null
	 */
	public function posts(): ?array;
}
```

- [ ] **Step 4: Write the manual source**

Create `includes/social/class-manual-feed-source.php`:

```php
<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stage one's source: the post links a club has pasted on Club Pages, turned
 * into the same normalised posts the Meta source will later return. No network
 * call, so it can never fail — it returns array() when nothing is pasted, which
 * the cache reads as "not connected yet".
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Manual_Feed_Source implements Blueworx_Clubhouse_Feed_Source {

	private Blueworx_Clubhouse_Content_Store $content;

	public function __construct( Blueworx_Clubhouse_Content_Store $content ) {
		$this->content = $content;
	}

	/** @return array<int,array{id:string,image:string,caption:string,date:string,permalink:string}> */
	public function posts(): ?array {
		$posts = array();
		foreach ( $this->content->get_items( 'home', 'social_feed' ) as $item ) {
			$post = self::normalise( is_array( $item ) ? $item : array() );
			if ( null !== $post ) {
				$posts[] = $post;
			}
		}
		return $posts;
	}

	/**
	 * One pasted row as a normalised post, or null when there is no usable link
	 * — a half-filled row is dropped rather than rendered as a card leading
	 * nowhere.
	 *
	 * The id is derived from the link so the same post keeps the same render
	 * key across saves.
	 *
	 * @param array<string,mixed> $item
	 * @return array{id:string,image:string,caption:string,date:string,permalink:string}|null
	 */
	public static function normalise( array $item ): ?array {
		$permalink = trim( (string) ( $item['href'] ?? '' ) );
		if ( '' === $permalink || 1 !== preg_match( '#^https?://#i', $permalink ) ) {
			return null;
		}
		return array(
			'id'        => 'manual-' . md5( $permalink ),
			'image'     => '',
			'caption'   => trim( (string) ( $item['caption'] ?? '' ) ),
			'date'      => '',
			'permalink' => $permalink,
		);
	}
}
```

- [ ] **Step 5: Load both files at runtime**

In `includes/bootstrap.php`, immediately after the `// Content` require block, add:

```php
// Social feed. Interface before its implementors, as everywhere else here.
require_once __DIR__ . '/social/interface-feed-source.php';
require_once __DIR__ . '/social/class-manual-feed-source.php';
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `vendor/bin/phpunit --filter ManualFeedSourceTest`
Expected: PASS, 4 tests.

- [ ] **Step 7: Commit**

```bash
git add includes/social tests/php/ManualFeedSourceTest.php includes/bootstrap.php
git commit -m "Add the social feed source seam and the pasted-links source"
```

---

### Task 2: The cache and the three failure states

**Files:**
- Create: `includes/social/class-social-feed.php`
- Modify: `includes/bootstrap.php` (one more require, after the manual source)
- Test: `tests/php/SocialFeedTest.php`

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Feed_Source::posts(): ?array` (Task 1).
- Produces: `final class Blueworx_Clubhouse_Social_Feed` with `__construct( Blueworx_Clubhouse_Feed_Source $source )`, `posts(): array`, `status(): string`, and the constants `Social_Feed::OK`, `Social_Feed::NOT_CONNECTED`, `Social_Feed::ERROR` (values `'ok'`, `'not_connected'`, `'error'`).

- [ ] **Step 1: Write the failing test**

Create `tests/php/SocialFeedTest.php`:

```php
<?php

use PHPUnit\Framework\TestCase;

/** A source whose answer the test decides, including "the fetch failed" (null). */
final class FakeFeedSource implements Blueworx_Clubhouse_Feed_Source {
	/** @var array<int,mixed>|null */
	private $answer;
	public int $calls = 0;
	public function __construct( ?array $answer ) {
		$this->answer = $answer;
	}
	public function posts(): ?array {
		++$this->calls;
		return $this->answer;
	}
}

final class SocialFeedTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
	}

	protected function tearDown(): void {
		wp_stub_reset();
	}

	/** @return array<int,array{id:string,image:string,caption:string,date:string,permalink:string}> */
	private function twoPosts(): array {
		return array(
			array( 'id' => 'a', 'image' => '', 'caption' => 'one', 'date' => '', 'permalink' => 'https://x.test/1' ),
			array( 'id' => 'b', 'image' => '', 'caption' => 'two', 'date' => '', 'permalink' => 'https://x.test/2' ),
		);
	}

	public function test_a_good_fetch_is_served_and_cached(): void {
		$source = new FakeFeedSource( $this->twoPosts() );
		$feed   = new Blueworx_Clubhouse_Social_Feed( $source );
		$this->assertCount( 2, $feed->posts() );
		$this->assertSame( Blueworx_Clubhouse_Social_Feed::OK, $feed->status() );

		// A second instance reads the transient rather than the source.
		$again = new Blueworx_Clubhouse_Social_Feed( $source );
		$this->assertCount( 2, $again->posts() );
		$this->assertSame( 1, $source->calls, 'the cache did not spare the source a second call' );
	}

	public function test_nothing_to_show_reads_as_not_connected(): void {
		$feed = new Blueworx_Clubhouse_Social_Feed( new FakeFeedSource( array() ) );
		$this->assertSame( array(), $feed->posts() );
		$this->assertSame( Blueworx_Clubhouse_Social_Feed::NOT_CONNECTED, $feed->status() );
	}

	public function test_a_failed_fetch_keeps_the_last_good_posts_up(): void {
		( new Blueworx_Clubhouse_Social_Feed( new FakeFeedSource( $this->twoPosts() ) ) )->posts();
		wp_stub_clear_transients();

		$feed = new Blueworx_Clubhouse_Social_Feed( new FakeFeedSource( null ) );
		$this->assertCount( 2, $feed->posts(), 'a blip lost the club its feed' );
		$this->assertSame( Blueworx_Clubhouse_Social_Feed::OK, $feed->status() );
	}

	public function test_a_failed_fetch_with_no_history_is_an_error_not_an_empty_feed(): void {
		$feed = new Blueworx_Clubhouse_Social_Feed( new FakeFeedSource( null ) );
		$this->assertSame( array(), $feed->posts() );
		$this->assertSame( Blueworx_Clubhouse_Social_Feed::ERROR, $feed->status() );
	}

	public function test_a_failure_is_not_retried_on_every_page_load(): void {
		$source = new FakeFeedSource( null );
		( new Blueworx_Clubhouse_Social_Feed( $source ) )->posts();
		( new Blueworx_Clubhouse_Social_Feed( $source ) )->posts();
		$this->assertSame( 1, $source->calls, 'an outage was re-fetched on the next request' );
	}

	public function test_a_record_missing_its_link_never_reaches_the_renderer(): void {
		$feed = new Blueworx_Clubhouse_Social_Feed( new FakeFeedSource( array(
			array( 'id' => 'a', 'image' => '', 'caption' => 'kept', 'date' => '', 'permalink' => 'https://x.test/1' ),
			array( 'id' => '', 'image' => '', 'caption' => 'no id', 'date' => '', 'permalink' => 'https://x.test/2' ),
			array( 'id' => 'c', 'image' => '', 'caption' => 'no link', 'date' => '', 'permalink' => '' ),
			'not even an array',
		) ) );
		$posts = $feed->posts();
		$this->assertCount( 1, $posts );
		$this->assertSame( 'kept', $posts[0]['caption'] );
	}

	public function test_a_corrupted_last_good_option_is_treated_as_never_having_been_there(): void {
		update_option( 'blueworx_clubhouse_social_feed_last_good', 'not an array', false );
		$feed = new Blueworx_Clubhouse_Social_Feed( new FakeFeedSource( null ) );
		$this->assertSame( array(), $feed->posts() );
		$this->assertSame( Blueworx_Clubhouse_Social_Feed::ERROR, $feed->status() );
	}
}
```

Before running, check `tests/php/wp-stubs.php` for how transients are reset. If there is no `wp_stub_clear_transients()`, add one next to `wp_stub_reset()` that empties only the transient store, and use it — the test needs the transient gone while the option survives, which is exactly what TTL expiry looks like.

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit --filter SocialFeedTest`
Expected: FAIL — `Class "Blueworx_Clubhouse_Social_Feed" not found`.

- [ ] **Step 3: Write the cache**

Create `includes/social/class-social-feed.php`:

```php
<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The cache in front of whichever feed source is active, and the only thing the
 * renderer talks to. Three stores, copied deliberately from the SureCart price
 * cache (class-surecart-products.php):
 *
 *  - a transient holding the last good fetch, for the normal path;
 *  - a short failure marker, so a platform outage does not re-hit the source on
 *    every page load for the whole TTL;
 *  - a last-good option with no expiry, read only once a fetch has failed.
 *
 * Unlike prices, the feed is identical for every visitor, so there is no
 * logged-in/logged-out cache context to split on.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Social_Feed {

	/** Posts to show. */
	public const OK = 'ok';
	/** Nothing has ever been connected — the front end shows nothing at all. */
	public const NOT_CONNECTED = 'not_connected';
	/** A fetch failed and none has ever succeeded — the admin needs to know. */
	public const ERROR = 'error';

	/** Long enough that a page render almost never pays for a fetch; short enough that a new post appears the same day. */
	private const CACHE_TTL = 900;

	/** How long a failed fetch is remembered before the next request may try again. */
	private const FAILURE_TTL = 60;

	/**
	 * Deliberately NOT scoped to the plugin version, unlike cache_key(): this is
	 * the safety net a blip falls back to, and every release changes the
	 * version — keying it there would empty the net on every update, which is
	 * the exact failure it exists to prevent.
	 */
	private const LAST_GOOD_OPTION = 'blueworx_clubhouse_social_feed_last_good';

	private Blueworx_Clubhouse_Feed_Source $source;

	/** @var array<int,array<string,string>>|null Memoised so one render resolves once. */
	private ?array $resolved = null;

	private string $status = self::NOT_CONNECTED;

	public function __construct( Blueworx_Clubhouse_Feed_Source $source ) {
		$this->source = $source;
	}

	/** @return array<int,array{id:string,image:string,caption:string,date:string,permalink:string}> */
	public function posts(): array {
		if ( null !== $this->resolved ) {
			return $this->resolved;
		}

		$cached = get_transient( self::cache_key() );
		if ( is_array( $cached ) ) {
			$this->status   = array() === $cached ? self::NOT_CONNECTED : self::OK;
			$this->resolved = $cached;
			return $this->resolved;
		}

		if ( false !== get_transient( self::failure_key() ) ) {
			// A fetch failed moments ago. Serve whatever last succeeded rather
			// than paying for the failure again on every render of the outage.
			$this->resolved = $this->after_failure();
			return $this->resolved;
		}

		$fetched = $this->source->posts();
		if ( null === $fetched ) {
			set_transient( self::failure_key(), true, self::FAILURE_TTL );
			$this->resolved = $this->after_failure();
			return $this->resolved;
		}

		$posts = self::clean( $fetched );
		set_transient( self::cache_key(), $posts, self::CACHE_TTL );
		if ( array() !== $posts ) {
			// No expiry: this is read only when a later fetch fails, so it has
			// to still be here whenever that happens.
			update_option( self::LAST_GOOD_OPTION, $posts, false );
		}
		$this->status   = array() === $posts ? self::NOT_CONNECTED : self::OK;
		$this->resolved = $posts;
		return $this->resolved;
	}

	/**
	 * Which of the three states this feed is in. Resolves the feed if that has
	 * not happened yet, so callers may ask in either order.
	 */
	public function status(): string {
		$this->posts();
		return $this->status;
	}

	/**
	 * What to serve once a fetch has failed. Posts we have shown before stay
	 * up and the visitor is told nothing; a club should not lose its feed
	 * because Meta had a bad minute. With no history there is nothing to show,
	 * and that is a different fact from never having connected — the club's
	 * next action is to fix the connection, not to make one.
	 *
	 * @return array<int,array<string,string>>
	 */
	private function after_failure(): array {
		$last         = self::last_good();
		$this->status = array() === $last ? self::ERROR : self::OK;
		return $last;
	}

	/**
	 * Drop anything that is not a usable post. A record with no id has no
	 * render key and one with no permalink is a card leading nowhere; both are
	 * better absent than drawn.
	 *
	 * @param array<int,mixed> $rows
	 * @return array<int,array{id:string,image:string,caption:string,date:string,permalink:string}>
	 */
	private static function clean( array $rows ): array {
		$posts = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$id        = trim( (string) ( $row['id'] ?? '' ) );
			$permalink = trim( (string) ( $row['permalink'] ?? '' ) );
			if ( '' === $id || '' === $permalink ) {
				continue;
			}
			$posts[] = array(
				'id'        => $id,
				'image'     => (string) ( $row['image'] ?? '' ),
				'caption'   => (string) ( $row['caption'] ?? '' ),
				'date'      => (string) ( $row['date'] ?? '' ),
				'permalink' => $permalink,
			);
		}
		return $posts;
	}

	/**
	 * The last successfully fetched posts, or array() when none ever were.
	 * Filtered on the way out: the option has no expiry and nothing else
	 * validates it once written, so a value corrupted by a manual edit or a
	 * future format change must read as absent rather than reach the renderer.
	 *
	 * @return array<int,array<string,string>>
	 */
	private static function last_good(): array {
		$stored = get_option( self::LAST_GOOD_OPTION, array() );
		return is_array( $stored ) ? self::clean( $stored ) : array();
	}

	/** Transient key, scoped to the running plugin version like Theme_Cache. */
	private static function cache_key(): string {
		$version = defined( 'BLUEWORX_LABS_CLUBHOUSE_VERSION' ) ? BLUEWORX_LABS_CLUBHOUSE_VERSION : 'dev';
		return 'blueworx_clubhouse_social_feed_' . md5( (string) $version );
	}

	/** Transient key for the short "a fetch just failed" marker — see FAILURE_TTL. */
	private static function failure_key(): string {
		return 'blueworx_clubhouse_social_feed_failed';
	}
}
```

- [ ] **Step 4: Load it at runtime**

In `includes/bootstrap.php`, in the Social block added in Task 1, add after the manual source:

```php
require_once __DIR__ . '/social/class-social-feed.php';
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `vendor/bin/phpunit --filter SocialFeedTest`
Expected: PASS, 7 tests.

- [ ] **Step 6: Run the whole PHP suite**

Run: `vendor/bin/phpunit`
Expected: PASS. Nothing else touches these keys yet, so a failure here means a stub was changed carelessly — fix that, do not adjust the new tests.

- [ ] **Step 7: Commit**

```bash
git add includes/social/class-social-feed.php includes/bootstrap.php tests/php/SocialFeedTest.php tests/php/wp-stubs.php
git commit -m "Cache the social feed and handle its three failure states"
```

---

### Task 3: The section renderer and its styling

**Files:**
- Modify: `includes/render/class-sections.php` (add `social_feed()` and two private helpers, beside `news_cards()`)
- Modify: `assets/looks/court-side.css`, `assets/looks/floodlight.css`, `assets/looks/members-house.css`
- Test: `tests/php/SocialFeedSectionTest.php`

**Interfaces:**
- Consumes: nothing from earlier tasks — it takes a plain array.
- Produces: `Blueworx_Clubhouse_Sections::social_feed( array $data ): string` where `$data` is `array{platform:string,heading:string,lede:string,posts:array<int,array{id:string,image:string,caption:string,date:string,permalink:string}>}`. Returns `''` when there are no posts.

- [ ] **Step 1: Write the failing test**

Create `tests/php/SocialFeedSectionTest.php`:

```php
<?php

use PHPUnit\Framework\TestCase;

final class SocialFeedSectionTest extends TestCase {

	/** @param array<int,array<string,string>> $posts */
	private function html( array $posts, string $platform = 'facebook' ): string {
		return Blueworx_Clubhouse_Sections::social_feed( array(
			'platform' => $platform,
			'heading'  => 'Latest from the club',
			'lede'     => 'What we have been up to.',
			'posts'    => $posts,
		) );
	}

	/** @return array<int,array{id:string,image:string,caption:string,date:string,permalink:string}> */
	private function onePost( array $overrides = array() ): array {
		return array( array_merge( array(
			'id'        => 'a',
			'image'     => 'https://cdn.test/1.jpg',
			'caption'   => 'Saturday win',
			'date'      => '2026-08-15T10:30:00+00:00',
			'permalink' => 'https://facebook.com/club/posts/1',
		), $overrides ) );
	}

	public function test_no_posts_means_no_band_at_all(): void {
		// A heading over an empty space reads as a broken site.
		$this->assertSame( '', $this->html( array() ) );
	}

	public function test_a_post_renders_as_a_card_linking_back_to_the_platform(): void {
		$html = $this->html( $this->onePost() );
		$this->assertStringContainsString( 'ch-feed__card', $html );
		$this->assertStringContainsString( 'href="https://facebook.com/club/posts/1"', $html );
		$this->assertStringContainsString( 'Saturday win', $html );
		$this->assertStringContainsString( 'https://cdn.test/1.jpg', $html );
	}

	public function test_the_platform_is_named_once_in_the_eyebrow(): void {
		$this->assertStringContainsString( 'Facebook', $this->html( $this->onePost() ) );
		$this->assertStringContainsString( 'Instagram', $this->html( $this->onePost(), 'instagram' ) );
	}

	public function test_a_caption_is_text_never_markup(): void {
		$html = $this->html( $this->onePost( array( 'caption' => '<script>alert(1)</script>' ) ) );
		$this->assertStringNotContainsString( '<script>', $html );
	}

	public function test_a_long_caption_is_cut_rather_than_running_down_the_page(): void {
		$html = $this->html( $this->onePost( array( 'caption' => str_repeat( 'word ', 80 ) ) ) );
		$this->assertStringContainsString( "\u{2026}", $html );
	}

	public function test_a_text_only_post_still_renders(): void {
		$html = $this->html( $this->onePost( array( 'image' => '', 'caption' => 'Just words' ) ) );
		$this->assertStringContainsString( 'ch-media--empty', $html );
		$this->assertStringContainsString( 'Just words', $html );
	}

	public function test_an_undated_post_shows_no_date(): void {
		$html = $this->html( $this->onePost( array( 'date' => '' ) ) );
		$this->assertStringNotContainsString( 'ch-feed__date', $html );
	}

	public function test_a_date_is_shown_readably(): void {
		$this->assertStringContainsString( '15 Aug 2026', $this->html( $this->onePost() ) );
	}
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit --filter SocialFeedSectionTest`
Expected: FAIL — `Call to undefined method ...::social_feed()`.

- [ ] **Step 3: Write the renderer**

In `includes/render/class-sections.php`, directly after `news_cards()`, add:

```php
	/** How the platform is named to a visitor. Anything else names no platform at all. */
	private const FEED_PLATFORMS = array(
		'facebook'  => 'Facebook',
		'instagram' => 'Instagram',
	);

	/** Longest caption a card carries before it is cut — enough for a sentence, not a post. */
	private const FEED_CAPTION_MAX = 140;

	/**
	 * The club's recent social posts, drawn as cards that link back to the
	 * platform. Never a heading over an empty space: no posts means no band, in
	 * every one of the three states the feed can be in (see Social_Feed).
	 *
	 * Captions are plain text and are escaped like everything else here; the
	 * platform's own markup is never trusted, and no post is ever embedded.
	 *
	 * @param array{platform:string,heading:string,lede:string,
	 *   posts:array<int,array{id:string,image:string,caption:string,date:string,permalink:string}>} $data
	 */
	public static function social_feed( array $data ): string {
		$posts = $data['posts'] ?? array();
		if ( array() === $posts ) {
			return '';
		}
		$platform = self::FEED_PLATFORMS[ (string) ( $data['platform'] ?? '' ) ] ?? '';

		$cards = '';
		foreach ( $posts as $post ) {
			$caption = self::truncate( (string) ( $post['caption'] ?? '' ), self::FEED_CAPTION_MAX );
			$date    = self::feed_date( (string) ( $post['date'] ?? '' ) );
			$cards  .= '<a class="ch-feed__card" href="' . self::e( (string) $post['permalink'] ) . '">'
				// The image carries the caption as its alt text; a post whose
				// picture is its whole content would otherwise be silent.
				. self::media( (string) ( $post['image'] ?? '' ), $caption, 'ch-feed__media' )
				. ( '' !== $caption ? '<p class="ch-feed__caption">' . self::e( $caption ) . '</p>' : '' )
				. ( '' !== $date ? '<span class="ch-feed__date">' . self::e( $date ) . '</span>' : '' )
				. '</a>';
		}

		$lede = (string) ( $data['lede'] ?? '' );
		return '<section class="ch-sec"><div class="ch-wrap">'
			. '<div class="ch-sec__head"><div>'
			. ( '' !== $platform ? '<span class="ch-eyebrow">On ' . self::e( $platform ) . '</span>' : '' )
			. '<h2 class="ch-sec__title ch-sec__title--sm">' . self::e( (string) ( $data['heading'] ?? '' ) ) . '</h2>'
			. ( '' !== $lede ? '<p class="ch-feed__lede">' . self::e( $lede ) . '</p>' : '' )
			. '</div></div>'
			// Cards are links, so no role="list" — it would override the link
			// role on every anchor, the trap the news cards and hero tiles hit.
			. '<div class="ch-feed">' . $cards . '</div>'
			. '</div></section>';
	}

	/** Cut at a word boundary and mark the cut, or leave short text alone. */
	private static function truncate( string $text, int $max ): string {
		$text = trim( $text );
		if ( mb_strlen( $text ) <= $max ) {
			return $text;
		}
		$cut   = mb_substr( $text, 0, $max );
		$space = mb_strrpos( $cut, ' ' );
		if ( false !== $space && $space > 0 ) {
			$cut = mb_substr( $cut, 0, $space );
		}
		return rtrim( $cut ) . "\u{2026}";
	}

	/**
	 * An ISO 8601 timestamp as a date a reader recognises, or '' when there is
	 * no usable date — an unreadable one is worse than none beside a post.
	 * wp_date() applies the site's timezone where WordPress is present; the
	 * preview has no WordPress, so it falls back to UTC.
	 */
	private static function feed_date( string $iso ): string {
		$iso = trim( $iso );
		if ( '' === $iso ) {
			return '';
		}
		$time = strtotime( $iso );
		if ( false === $time ) {
			return '';
		}
		return function_exists( 'wp_date' ) ? (string) wp_date( 'j M Y', $time ) : gmdate( 'j M Y', $time );
	}
```

If `truncate()` already exists on this class under another name, use the existing one rather than adding a second.

- [ ] **Step 4: Run the test to verify it passes**

Run: `vendor/bin/phpunit --filter SocialFeedSectionTest`
Expected: PASS, 8 tests.

- [ ] **Step 5: Style the new classes in all three looks**

`LookCoverageTest` asserts parity: a class styled in one look must be styled in all three. Add rules for `ch-feed`, `ch-feed__card`, `ch-feed__media`, `ch-feed__caption`, `ch-feed__date` and `ch-feed__lede` to each look's CSS, beside that look's own `.ch-news` rules, using only that look's tokens.

For `assets/looks/court-side.css`, beside its `.ch-news` block:

```css
.ch-feed{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(220px,100%),1fr));gap:24px}
.ch-feed__lede{font-size:15px;color:var(--color-ink-soft);max-width:56ch;margin-top:8px}
.ch-feed__card{display:block;color:inherit;transition:transform .25s ease}
@media(prefers-reduced-motion:no-preference){.ch-feed__card:hover{transform:translateY(-3px)}}
.ch-feed__media{aspect-ratio:1/1;border-radius:var(--radius-md);margin-bottom:12px}
.ch-feed__caption{font-size:15px;line-height:1.45}
.ch-feed__date{display:block;margin-top:6px;font-size:12px;color:var(--color-ink-soft)}
```

Then write the equivalent block in `floodlight.css` and `members-house.css`, matching each file's own conventions — read its `.ch-news` rules first and follow its radii, sizes and colour tokens rather than pasting the above verbatim. Every class above must appear in all three files.

- [ ] **Step 6: Run the look parity test**

Run: `vendor/bin/phpunit --filter LookCoverageTest`
Expected: PASS. If a `ch-feed*` class is reported unstyled, style it in the look that is missing it — do not add it to `KNOWN_UNSTYLED`.

- [ ] **Step 7: Commit**

```bash
git add includes/render/class-sections.php assets/looks tests/php/SocialFeedSectionTest.php
git commit -m "Draw the social feed cards, styled in all three looks"
```

---

### Task 4: Wire the section into Club Pages, visibility and the Home page

**Files:**
- Modify: `includes/content/class-visibility.php` (`SECTION_DEFAULTS`)
- Modify: `includes/admin/class-setup-sections.php` (`MAP['home']`)
- Modify: `includes/admin/class-content-catalogue.php` (Home tab sections)
- Modify: `includes/render/class-page-renderer.php` (Home, after the news block)
- Test: `tests/php/VisibilityTest.php`, `tests/php/SetupSectionsTest.php`, `tests/php/PageRendererTest.php`

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Social_Feed` and `Blueworx_Clubhouse_Manual_Feed_Source` (Tasks 1–2), `Blueworx_Clubhouse_Sections::social_feed()` (Task 3).
- Produces: the section key `home.social_feed`, storing `platform`, `heading`, `lede`, `count` and an `items` loop of `href`/`caption` under store page `home`.

- [ ] **Step 1: Write the failing tests**

Add to `tests/php/VisibilityTest.php`:

```php
	public function test_the_social_feed_ships_hidden(): void {
		$visibility = new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() );
		$this->assertFalse( $visibility->is_section_visible( 'home', 'social_feed' ) );
		// Everything else still ships visible.
		$this->assertTrue( $visibility->is_section_visible( 'home', 'news' ) );
	}

	public function test_a_club_can_opt_the_social_feed_in(): void {
		$visibility = new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() );
		$visibility->set_section_visible( 'home', 'social_feed', true );
		$this->assertTrue( $visibility->is_section_visible( 'home', 'social_feed' ) );
	}
```

Add to `tests/php/PageRendererTest.php` (reuse whatever helper that file already has for building a Home render rather than repeating the five arguments — the calls below show the argument order if there is none):

```php
	public function test_the_social_feed_is_absent_until_a_club_switches_it_on(): void {
		wp_stub_reset();
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$content = new Blueworx_Clubhouse_Content_Store( $storage );
		$content->set_items( 'home', 'social_feed', array( array( 'href' => 'https://facebook.com/club/posts/1', 'caption' => 'Saturday win' ) ) );
		$html = Blueworx_Clubhouse_Page_Renderer::home(
			new Blueworx_Clubhouse_Branding( $storage ),
			new Blueworx_Clubhouse_Visibility( $storage ),
			new Blueworx_Clubhouse_Demo_Collections(),
			'',
			$content
		);
		$this->assertStringNotContainsString( 'ch-feed__card', $html );
	}

	public function test_a_switched_on_social_feed_shows_the_pasted_posts(): void {
		wp_stub_reset();
		$storage    = new Blueworx_Clubhouse_Fake_Storage();
		$visibility = new Blueworx_Clubhouse_Visibility( $storage );
		$visibility->set_section_visible( 'home', 'social_feed', true );
		$content = new Blueworx_Clubhouse_Content_Store( $storage );
		$content->set_items( 'home', 'social_feed', array( array( 'href' => 'https://facebook.com/club/posts/1', 'caption' => 'Saturday win' ) ) );
		$html = Blueworx_Clubhouse_Page_Renderer::home(
			new Blueworx_Clubhouse_Branding( $storage ),
			$visibility,
			new Blueworx_Clubhouse_Demo_Collections(),
			'',
			$content
		);
		$this->assertStringContainsString( 'ch-feed__card', $html );
		$this->assertStringContainsString( 'Saturday win', $html );
	}

	public function test_a_switched_on_but_unconnected_social_feed_renders_nothing(): void {
		wp_stub_reset();
		$storage    = new Blueworx_Clubhouse_Fake_Storage();
		$visibility = new Blueworx_Clubhouse_Visibility( $storage );
		$visibility->set_section_visible( 'home', 'social_feed', true );
		$html = Blueworx_Clubhouse_Page_Renderer::home(
			new Blueworx_Clubhouse_Branding( $storage ),
			$visibility,
			new Blueworx_Clubhouse_Demo_Collections(),
			'',
			new Blueworx_Clubhouse_Content_Store( $storage )
		);
		$this->assertStringNotContainsString( 'ch-feed', $html );
	}
```

Update the two existing expectations in `tests/php/SetupSectionsTest.php`:

```php
		$this->assertSame(
			array( 'cookies', 'header', 'hero', 'quick_tiles', 'ticker', 'sports', 'clubhouse', 'membership', 'activity', 'news', 'social_feed', 'info', 'sponsors', 'social', 'footer', 'welcome' ),
			$keys
		);
```

and the count test, which becomes 55 without LatePoint and 60 with it — rename the method to `test_total_section_count_is_55_without_latepoint_and_60_with_it` and update its docblock to say the social feed added one.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/phpunit --filter "VisibilityTest|SetupSectionsTest|PageRendererTest"`
Expected: FAIL on the new social-feed assertions.

- [ ] **Step 3: Ship it hidden**

In `includes/content/class-visibility.php`, replace the empty `SECTION_DEFAULTS` body with:

```php
	private const SECTION_DEFAULTS = array(
		// The social feed shows nothing until a club pastes its posts in, so an
		// empty band would appear on every existing club site the moment this
		// shipped. It is opted into, on Setup → Visibility.
		'home.social_feed' => false,
	);
```

- [ ] **Step 4: Offer it on the Visibility screen**

In `includes/admin/class-setup-sections.php`, in `MAP['home']`, add after `'news' => 'News',`:

```php
			'social_feed' => 'Social feed',
```

- [ ] **Step 5: Add the section to Club Pages**

In `includes/admin/class-content-catalogue.php`, on the `home` tab, after the `news` section entry, add:

```php
				array( 'key' => 'social_feed', 'label' => 'Social feed', 'type' => 'loop', 'store_page' => 'home',
					'note' => 'Switched off until you turn it on under Setup → Visibility. Paste the link to each post you want shown — the section stays off the page until at least one is pasted, because a heading over an empty space reads as a broken site. Connecting Facebook or Instagram directly, so posts arrive on their own, comes later.',
					'fields' => array(
						self::f_select( 'platform', 'Platform', array( 'facebook' => 'Facebook', 'instagram' => 'Instagram' ) ),
						self::f_text( 'heading', 'Heading', 'e.g. Latest from the club' ),
						self::f_area( 'lede', 'Blurb', 2 ),
						self::f_select( 'count', 'How many posts to show', array( '3' => '3', '6' => '6', '9' => '9' ) ),
					),
					'loop' => array( 'name' => 'Post', 'plural' => 'Posts', 'fields' => array(
						self::f_url( 'href', 'Post link' ),
						self::f_text( 'caption', 'Caption' ),
					) ) ),
```

- [ ] **Step 6: Render it on Home**

In `includes/render/class-page-renderer.php`, immediately after the `home.news` block closes and before the `home.sponsors` block, add:

```php
		if ( $visibility->is_section_visible( 'home', 'social_feed' ) ) {
			// Stage one's source is the links the club pasted; stage two swaps a
			// Meta connection in here and nothing below this line changes. With no
			// content store there is nothing pasted, so there is nothing to show.
			$posts = null !== $content
				? ( new Blueworx_Clubhouse_Social_Feed( new Blueworx_Clubhouse_Manual_Feed_Source( $content ) ) )->posts()
				: array();
			$count = (int) self::cget( $content, 'home', 'social_feed', 'count', '3' );
			$out  .= self::anchored( 'home', 'social_feed', Blueworx_Clubhouse_Sections::social_feed( array(
				'platform' => self::cget( $content, 'home', 'social_feed', 'platform', 'facebook' ),
				'heading'  => self::cget( $content, 'home', 'social_feed', 'heading', 'Latest from the club' ),
				'lede'     => self::cget( $content, 'home', 'social_feed', 'lede', '' ),
				'posts'    => array_slice( $posts, 0, $count > 0 ? $count : 3 ),
			) ) );
		}
```

Read `self::anchored()` before running: if it wraps an empty section string in an anchor element, guard the call so a section with nothing to show emits nothing at all — the third `PageRendererTest` assertion pins that.

- [ ] **Step 7: Run the full PHP suite**

Run: `vendor/bin/phpunit`
Expected: PASS. `ContentCatalogueTest`'s lockstep and index tests derive their expectations, so they pass once catalogue and inventory agree; if one still fails, the two lists disagree — fix the list, not the test.

- [ ] **Step 8: Commit**

```bash
git add includes tests/php
git commit -m "Put the social feed on Home, edited on Club Pages and shipped hidden"
```

---

### Task 5: See it in the preview, and cover it in the browser

**Files:**
- Modify: `preview/index.php`
- Create: `tests/social-feed.spec.js`

**Interfaces:**
- Consumes: everything from Tasks 1–4.
- Produces: the preview query parameter `?clubhouse_social=demo` (three pasted posts) and `?clubhouse_social=empty` (switched on, nothing pasted).

- [ ] **Step 1: Write the failing spec**

Create `tests/social-feed.spec.js`, following the conventions of an existing portable spec such as `tests/news.spec.js` (read it first for how this repo navigates and tags specs):

```js
const { test, expect } = require('@playwright/test');

test.describe('social feed', () => {
  test('is absent from Home until a club switches it on', async ({ page }) => {
    await page.goto('?clubhouse_page=home');
    await expect(page.locator('.ch-feed')).toHaveCount(0);
  });

  test('shows the pasted posts once it is on', async ({ page }) => {
    await page.goto('?clubhouse_page=home&clubhouse_social=demo');
    const cards = page.locator('.ch-feed__card');
    await expect(cards).toHaveCount(3);
    await expect(cards.first()).toHaveAttribute('href', /^https?:\/\//);
  });

  test('renders nothing at all when it is on but nothing is pasted', async ({ page }) => {
    await page.goto('?clubhouse_page=home&clubhouse_social=empty');
    await expect(page.locator('.ch-feed')).toHaveCount(0);
    await expect(page.getByText('Latest from the club')).toHaveCount(0);
  });
});
```

- [ ] **Step 2: Run the spec to verify it fails**

Run: `npx playwright test tests/social-feed.spec.js`
Expected: FAIL on the second test — the preview knows nothing about `clubhouse_social` yet.

- [ ] **Step 3: Teach the preview to show it**

In `preview/index.php`, inside `blueworx_clubhouse_preview_document()`, after the `Products_Source::set(...)` block, add:

```php
	// The social feed ships hidden and shows only what a club has pasted, so the
	// preview — a design tool with no database — has to be told to switch it on.
	// 'demo' seeds three posts; 'empty' switches the section on with nothing
	// pasted, which is what a club sees between opting in and connecting.
	$preview_content = null;
	$raw_social      = $_GET['clubhouse_social'] ?? '';
	$social          = is_string( $raw_social ) ? (string) preg_replace( '/[^a-z]/', '', $raw_social ) : '';
	if ( 'demo' === $social || 'empty' === $social ) {
		$visibility->set_section_visible( 'home', 'social_feed', true );
		$preview_content = new Blueworx_Clubhouse_Content_Store( $storage );
		$preview_content->set( 'home', 'social_feed', 'platform', 'instagram' );
		$preview_content->set( 'home', 'social_feed', 'heading', 'Latest from the club' );
		$preview_content->set( 'home', 'social_feed', 'lede', 'Match-day photos and the week as it happened.' );
		if ( 'demo' === $social ) {
			$preview_content->set_items( 'home', 'social_feed', array(
				array( 'href' => 'https://www.instagram.com/p/clubhouse-1/', 'caption' => 'Saturday’s win, in one photograph.' ),
				array( 'href' => 'https://www.instagram.com/p/clubhouse-2/', 'caption' => 'Juniors back on the pitch after the break.' ),
				array( 'href' => 'https://www.instagram.com/p/clubhouse-3/', 'caption' => 'The clubhouse bar is open again from Friday.' ),
			) );
		}
	}
```

Then pass `$preview_content` where the render calls currently pass `null` for the content store — read both call sites (`Page_Map::render(...)` and `Page_Renderer::post(...)`) and put it in the right argument position rather than guessing.

- [ ] **Step 4: Run the spec to verify it passes**

Run: `npx playwright test tests/social-feed.spec.js`
Expected: PASS, 3 tests.

- [ ] **Step 5: Run the whole browser suite**

Run: `npx playwright test`
Expected: PASS. The preview change touches every page's render call, so a failure elsewhere means the content argument landed in the wrong position.

- [ ] **Step 6: Commit**

```bash
git add preview/index.php tests/social-feed.spec.js
git commit -m "Preview and browser-test the social feed"
```

---

### Task 6: Version, changelog, priorities and the lint pass

**Files:**
- Modify: `blueworx-labs-clubhouse.php` (header `Version:` and the version constant)
- Modify: `package.json` (`version`)
- Modify: `CHANGELOG.md`
- Modify: `docs/priorities.md`

- [ ] **Step 1: Bump the version to 0.77.0**

A new feature, so a minor bump. Update the plugin header `Version:` line, `BLUEWORX_LABS_CLUBHOUSE_VERSION`, and `package.json`'s `version`. Run `grep -rn "0\.76\.1" --include="*.php" --include="*.json" . | grep -v node_modules | grep -v vendor` to be sure none is missed.

- [ ] **Step 2: Write the changelog entry**

At the top of `CHANGELOG.md`, above the `## 0.76.1` heading:

```markdown
## 0.77.0

- **Your Facebook or Instagram posts can now sit on your Home page.** Paste the link to each post you want shown under Club Pages → Home → Social feed, then switch the section on under Setup → Visibility. It arrives switched off, so nothing on your site changes until you turn it on, and it stays off the page until you have pasted at least one post. Connecting your account directly, so posts arrive on their own, comes later.
```

- [ ] **Step 3: Update the priorities doc**

`docs/priorities.md` says to keep it current. Add the social feed to the running order as done in 0.77.0, in the same struck-through style the closed items use, referencing issue #219, and note that stage two (the Meta connection) is still open.

- [ ] **Step 4: Run everything once**

```bash
vendor/bin/phpunit
npx playwright test
composer lint
```

Expected: both test suites PASS. Run `composer lint` ONCE — collect what it reports and present it to the user at the end. Do not fix lint findings without approval.

- [ ] **Step 5: Commit**

```bash
git add blueworx-labs-clubhouse.php package.json CHANGELOG.md docs/priorities.md
git commit -m "Release 0.77.0 — the social feed section"
```

- [ ] **Step 6: Open the pull request**

```bash
git push -u origin add-social-feed-section
gh pr create --title "Add the social feed section" --body "$(cat <<'BODY'
Shows a club's recent Facebook or Instagram posts on Home. Posts are pasted in for now; connecting the account directly comes later, behind the same interface.

Ships switched off, so no existing club site changes on update. Closes #219.

🤖 Generated with [Claude Code](https://claude.com/claude-code)
BODY
)"
```
