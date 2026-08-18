# Social Feed — Meta Connection (stage two) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A club connects its Facebook Page or Instagram account once, and its recent posts arrive on the Home page daily without anybody touching the site.

**Architecture:** Stage one's `Blueworx_Clubhouse_Feed_Source` seam is unchanged. Behind it: `Meta_Connection` (what was connected, and the token), `Meta_Client` (the Graph API, transport injected), `Meta_Feed_Source` (maps either platform into the normalised post), and `Meta_Connect_Controller` (Connect/Disconnect/Refresh now, and the daily scheduled refresh). `Social_Feed` keeps the caches and gains a connected mode that never fetches during a page view.

**Tech Stack:** PHP 8.1+, WordPress core only (`wp_remote_get`, `wp_schedule_event`, options, transients), PHPUnit, Playwright.

**Spec:** `docs/superpowers/specs/2026-08-18-social-feed-meta-connection-design.md`

## Global Constraints

- No new dependency — nothing added to `approved-deps.json`. HTTP via `wp_remote_get()`.
- No test ever calls Meta. The client's transport is injected; fixtures are recorded once and committed.
- The app secret never appears in this repo. The plugin receives a token; it never exchanges a code.
- A page render never fetches. When a club is connected, reads serve the cache or the last-good posts only.
- Post images are linked, never copied into the media library.
- The four admin-visible facts stay distinct: not connected, failing with history, failing without history, refused.
- Version bumped and `CHANGELOG.md` updated on the PR (minor bump).
- Run `composer lint` once at the end; present findings, do not fix without approval.

## Before starting

1. **The connect endpoint must exist first.** It is a separate deliverable in its own repo (`blueworx_service_meta_connect`) with its own plan: it holds the app id and secret, runs Facebook Login, exchanges the code for a long-lived token, and redirects back to the club site with the token and account details on a signed, single-use, short-lived handoff. Task 5 here consumes it. Do not start Task 5 until its handoff format is fixed and written down.
2. **Record the fixtures** (Task 2, Step 1) against our own Page and Instagram account with the Meta app in development mode. Every mapping decision in Task 3 is made against those recordings, never against guessed field names — the SureCart integration shipped broken for months on guessed shapes.

---

### Task 1: What the club connected

**Files:**
- Create: `includes/social/class-meta-connection.php`
- Modify: `includes/bootstrap.php` (add to the Social block)
- Test: `tests/php/MetaConnectionTest.php`

**Interfaces:**
- Produces: `final class Blueworx_Clubhouse_Meta_Connection` with `__construct( Blueworx_Clubhouse_Storage $storage )`, `is_connected(): bool`, `platform(): string` (`'facebook'`/`'instagram'`/`''`), `account_id(): string`, `account_name(): string`, `token(): string`, `connected_at(): string`, `store( string $platform, string $account_id, string $account_name, string $token ): bool`, `forget(): void`, `mark_refused(): void`, `is_refused(): bool`, `clear_refused(): void`.

- [ ] **Step 1: Write the failing test**

Create `tests/php/MetaConnectionTest.php`:

```php
<?php

use PHPUnit\Framework\TestCase;

final class MetaConnectionTest extends TestCase {

	private function connection(): Blueworx_Clubhouse_Meta_Connection {
		return new Blueworx_Clubhouse_Meta_Connection( new Blueworx_Clubhouse_Fake_Storage() );
	}

	public function test_a_fresh_site_is_not_connected(): void {
		$c = $this->connection();
		$this->assertFalse( $c->is_connected() );
		$this->assertSame( '', $c->token() );
		$this->assertSame( '', $c->platform() );
	}

	public function test_storing_a_connection_makes_it_readable(): void {
		$c = $this->connection();
		$this->assertTrue( $c->store( 'facebook', '1234', 'Marlow RFC', 'tok-abc' ) );
		$this->assertTrue( $c->is_connected() );
		$this->assertSame( 'facebook', $c->platform() );
		$this->assertSame( '1234', $c->account_id() );
		$this->assertSame( 'Marlow RFC', $c->account_name() );
		$this->assertSame( 'tok-abc', $c->token() );
		$this->assertNotSame( '', $c->connected_at() );
	}

	public function test_an_unknown_platform_is_refused_outright(): void {
		// Only the two platforms the feed can draw are storable; anything else
		// would reach the client and be asked of an endpoint that does not exist.
		$c = $this->connection();
		$this->assertFalse( $c->store( 'myspace', '1234', 'Marlow RFC', 'tok-abc' ) );
		$this->assertFalse( $c->is_connected() );
	}

	public function test_a_connection_without_a_token_is_not_a_connection(): void {
		$c = $this->connection();
		$this->assertFalse( $c->store( 'facebook', '1234', 'Marlow RFC', '' ) );
		$this->assertFalse( $c->is_connected() );
	}

	public function test_forgetting_leaves_nothing_behind(): void {
		$c = $this->connection();
		$c->store( 'instagram', '99', 'Marlow RFC', 'tok-abc' );
		$c->forget();
		$this->assertFalse( $c->is_connected() );
		$this->assertSame( '', $c->token() );
		$this->assertSame( '', $c->account_name() );
	}

	public function test_a_refused_connection_is_remembered_until_it_is_reconnected(): void {
		$c = $this->connection();
		$c->store( 'facebook', '1234', 'Marlow RFC', 'tok-abc' );
		$this->assertFalse( $c->is_refused() );

		$c->mark_refused();
		$this->assertTrue( $c->is_refused() );
		// Still "connected": the token is there, it is simply no longer accepted.
		// The admin needs both facts to say "reconnect Marlow RFC" rather than
		// "connect an account".
		$this->assertTrue( $c->is_connected() );

		$c->store( 'facebook', '1234', 'Marlow RFC', 'tok-new' );
		$this->assertFalse( $c->is_refused(), 'reconnecting must clear the refusal' );
	}
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit --filter MetaConnectionTest`
Expected: FAIL — `Class "Blueworx_Clubhouse_Meta_Connection" not found`.

- [ ] **Step 3: Write the connection**

Create `includes/social/class-meta-connection.php`:

```php
<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What a club connected, and the token that proves it. The only class that
 * touches the stored token: everything else asks this one.
 *
 * The token is as safe as the club's database, which is where every other
 * WordPress secret lives. It is stored in its own entry rather than alongside
 * the section's content so that nothing which renders, exports or imports club
 * content can carry it out by accident.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Meta_Connection {

	private const KEY = 'social_connection';

	/** The only platforms the feed can draw. Anything else is refused on the way in. */
	private const PLATFORMS = array( 'facebook', 'instagram' );

	private Blueworx_Clubhouse_Storage $storage;

	public function __construct( Blueworx_Clubhouse_Storage $storage ) {
		$this->storage = $storage;
	}

	/** @return array<string,mixed> */
	private function state(): array {
		$state = $this->storage->get( self::KEY, array() );
		return is_array( $state ) ? $state : array();
	}

	private function field( string $key ): string {
		$value = $this->state()[ $key ] ?? '';
		return is_string( $value ) ? $value : '';
	}

	public function is_connected(): bool {
		return '' !== $this->token() && '' !== $this->platform();
	}

	public function platform(): string {
		$platform = $this->field( 'platform' );
		return in_array( $platform, self::PLATFORMS, true ) ? $platform : '';
	}

	public function account_id(): string {
		return $this->field( 'account_id' );
	}

	public function account_name(): string {
		return $this->field( 'account_name' );
	}

	public function token(): string {
		return $this->field( 'token' );
	}

	/** ISO 8601, or '' if never connected. Shown on Club Pages, never to a visitor. */
	public function connected_at(): string {
		return $this->field( 'connected_at' );
	}

	/**
	 * Store a completed connection, or refuse it. False means nothing was
	 * written: a connection with no token, or for a platform this cannot draw,
	 * is not a connection, and storing it would put the section into a state
	 * that looks connected and can never work.
	 *
	 * Reconnecting clears any refusal — that is what reconnecting is for.
	 */
	public function store( string $platform, string $account_id, string $account_name, string $token ): bool {
		if ( ! in_array( $platform, self::PLATFORMS, true ) || '' === trim( $token ) ) {
			return false;
		}
		$this->storage->set( self::KEY, array(
			'platform'     => $platform,
			'account_id'   => trim( $account_id ),
			'account_name' => trim( $account_name ),
			'token'        => trim( $token ),
			'connected_at' => gmdate( 'c' ),
			'refused'      => false,
		) );
		return true;
	}

	public function forget(): void {
		$this->storage->set( self::KEY, array() );
	}

	/**
	 * Meta refused this token — revoked, password changed, permissions removed.
	 * Distinct from a failed fetch: it will not recover on its own, so the daily
	 * refresh stops until somebody reconnects.
	 */
	public function mark_refused(): void {
		$state            = $this->state();
		$state['refused'] = true;
		$this->storage->set( self::KEY, $state );
	}

	public function is_refused(): bool {
		return (bool) ( $this->state()['refused'] ?? false );
	}

	public function clear_refused(): void {
		$state            = $this->state();
		$state['refused'] = false;
		$this->storage->set( self::KEY, $state );
	}
}
```

- [ ] **Step 4: Load it**

In `includes/bootstrap.php`, in the Social block, after `interface-feed-source.php`:

```php
require_once __DIR__ . '/social/class-meta-connection.php';
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `vendor/bin/phpunit --filter MetaConnectionTest`
Expected: PASS, 6 tests.

- [ ] **Step 6: Commit**

```bash
git add includes/social/class-meta-connection.php includes/bootstrap.php tests/php/MetaConnectionTest.php
git commit -m "Remember which social account a club connected"
```

---

### Task 2: Record what Meta actually returns

**Files:**
- Create: `tests/php/fixtures/meta-facebook-posts.json`
- Create: `tests/php/fixtures/meta-instagram-media.json`
- Create: `docs/integrations/meta-graph-notes.md`

This task produces no plugin code. It produces the ground truth the next task is written against, in the style of `docs/integrations/surecart-notes.md`.

- [ ] **Step 1: Fetch real responses**

With the Meta app in development mode and our own Page and Instagram Business account connected, fetch and save both responses verbatim:

```
GET https://graph.facebook.com/v21.0/{page-id}/posts
    ?fields=id,message,created_time,permalink_url,full_picture
    &limit=12&access_token={page-token}

GET https://graph.facebook.com/v21.0/{ig-user-id}/media
    ?fields=id,caption,timestamp,permalink,media_type,media_url,thumbnail_url
    &limit=12&access_token={page-token}
```

Save the raw JSON bodies to the two fixture paths above. Redact the token from anything committed; leave every other field exactly as returned, including nulls and absences.

- [ ] **Step 2: Write down what was observed**

Create `docs/integrations/meta-graph-notes.md` recording, for each platform: the endpoint and version used, which fields were present on every record, which were absent or null on some, what a video post looked like versus a photo versus text-only, and the exact shape of an error response. Note the date and the API version — this file is what a future reader will trust instead of guessing.

- [ ] **Step 3: Note the differences that matter to the mapping**

Explicitly record the answers to these, since Task 3 depends on them:
- Does a Facebook post without a picture omit `full_picture`, or return it empty?
- For an Instagram video, is `media_url` the video file and `thumbnail_url` the still?
- What does a text-only post look like on each platform?
- What is returned when the token is refused, versus when the request merely fails?

- [ ] **Step 4: Commit**

```bash
git add tests/php/fixtures/meta-*.json docs/integrations/meta-graph-notes.md
git commit -m "Record what the Graph API actually returns"
```

---

### Task 3: The Graph client and the feed source

**Files:**
- Create: `includes/social/class-meta-client.php`
- Create: `includes/social/class-meta-feed-source.php`
- Modify: `includes/bootstrap.php`
- Test: `tests/php/MetaFeedSourceTest.php`

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Meta_Connection` (Task 1), the fixtures (Task 2), `Blueworx_Clubhouse_Feed_Source` (stage one).
- Produces:
  - `final class Blueworx_Clubhouse_Meta_Client` with `__construct( ?callable $transport = null )` (the transport takes a URL string and returns `array{code:int,body:string}|null`; null default uses `wp_remote_get()`), and `fetch( string $platform, string $account_id, string $token ): array` returning `array{status:string,records:array<int,mixed>}` where status is `Meta_Client::OK`, `Meta_Client::FAILED` or `Meta_Client::REFUSED`.
  - `final class Blueworx_Clubhouse_Meta_Feed_Source implements Blueworx_Clubhouse_Feed_Source` with `__construct( Blueworx_Clubhouse_Meta_Connection $connection, Blueworx_Clubhouse_Meta_Client $client )`, `posts(): ?array`, and `refused(): bool`.

- [ ] **Step 1: Write the failing test**

Create `tests/php/MetaFeedSourceTest.php`. Correct the fixture-derived assertions to whatever Task 2 actually recorded before running:

```php
<?php

use PHPUnit\Framework\TestCase;

final class MetaFeedSourceTest extends TestCase {

	private function fixture( string $name ): string {
		return (string) file_get_contents( __DIR__ . '/fixtures/' . $name );
	}

	/** A transport that answers with whatever the test decides, and counts calls. */
	private function transport( int $code, string $body, ?array &$urls = null ): callable {
		return static function ( string $url ) use ( $code, $body, &$urls ): array {
			if ( null !== $urls ) {
				$urls[] = $url;
			}
			return array( 'code' => $code, 'body' => $body );
		};
	}

	private function connection( string $platform ): Blueworx_Clubhouse_Meta_Connection {
		$c = new Blueworx_Clubhouse_Meta_Connection( new Blueworx_Clubhouse_Fake_Storage() );
		$c->store( $platform, '1234', 'Marlow RFC', 'tok-abc' );
		return $c;
	}

	private function source( string $platform, callable $transport ): Blueworx_Clubhouse_Meta_Feed_Source {
		return new Blueworx_Clubhouse_Meta_Feed_Source(
			$this->connection( $platform ),
			new Blueworx_Clubhouse_Meta_Client( $transport )
		);
	}

	public function test_facebook_posts_become_normalised_posts(): void {
		$source = $this->source( 'facebook', $this->transport( 200, $this->fixture( 'meta-facebook-posts.json' ) ) );
		$posts  = $source->posts();
		$this->assertIsArray( $posts );
		$this->assertNotSame( array(), $posts );
		foreach ( $posts as $post ) {
			$this->assertNotSame( '', $post['id'] );
			$this->assertStringStartsWith( 'http', $post['permalink'] );
			$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}/', $post['date'] );
		}
	}

	public function test_instagram_media_become_the_same_shape(): void {
		$source = $this->source( 'instagram', $this->transport( 200, $this->fixture( 'meta-instagram-media.json' ) ) );
		$posts  = $source->posts();
		$this->assertIsArray( $posts );
		$this->assertNotSame( array(), $posts );
		$this->assertSame(
			array( 'id', 'image', 'caption', 'date', 'permalink' ),
			array_keys( $posts[0] ),
			'both platforms must normalise to one shape or the renderer learns which is which'
		);
	}

	public function test_a_record_with_no_link_is_dropped(): void {
		$body   = json_encode( array( 'data' => array(
			array( 'id' => '1', 'message' => 'kept', 'created_time' => '2026-08-15T10:30:00+0000', 'permalink_url' => 'https://facebook.com/1' ),
			array( 'id' => '2', 'message' => 'no link' ),
		) ) );
		$source = $this->source( 'facebook', $this->transport( 200, (string) $body ) );
		$posts  = $source->posts();
		$this->assertCount( 1, $posts );
		$this->assertSame( 'kept', $posts[0]['caption'] );
	}

	public function test_an_outage_is_a_failure_not_an_empty_feed(): void {
		$source = $this->source( 'facebook', $this->transport( 500, 'Server Error' ) );
		$this->assertNull( $source->posts(), 'a failed fetch must be null so the cache serves the last good posts' );
		$this->assertFalse( $source->refused() );
	}

	public function test_a_revoked_token_is_a_refusal_not_an_outage(): void {
		$body   = json_encode( array( 'error' => array( 'code' => 190, 'message' => 'Error validating access token' ) ) );
		$source = $this->source( 'facebook', $this->transport( 400, (string) $body ) );
		$this->assertNull( $source->posts() );
		$this->assertTrue( $source->refused(), 'a revoked token will never recover on its own' );
	}

	public function test_nothing_is_asked_of_meta_without_a_connection(): void {
		$urls   = array();
		$source = new Blueworx_Clubhouse_Meta_Feed_Source(
			new Blueworx_Clubhouse_Meta_Connection( new Blueworx_Clubhouse_Fake_Storage() ),
			new Blueworx_Clubhouse_Meta_Client( $this->transport( 200, '{}', $urls ) )
		);
		$this->assertSame( array(), $source->posts() );
		$this->assertSame( array(), $urls );
	}

	public function test_the_token_never_appears_in_a_normalised_post(): void {
		$source = $this->source( 'facebook', $this->transport( 200, $this->fixture( 'meta-facebook-posts.json' ) ) );
		$this->assertStringNotContainsString( 'tok-abc', json_encode( $source->posts() ) );
	}
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit --filter MetaFeedSourceTest`
Expected: FAIL — `Class "Blueworx_Clubhouse_Meta_Client" not found`.

- [ ] **Step 3: Write the client**

Create `includes/social/class-meta-client.php`:

```php
<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The only class in the plugin that knows the Graph API exists.
 *
 * Written against real responses recorded in docs/integrations/meta-graph-notes.md,
 * not against guessed field names — see that file before changing any of the
 * field lists below.
 *
 * The transport is injected so every test exercises the real mapping and error
 * handling without a network. Null (the default) uses wp_remote_get().
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Meta_Client {

	public const OK      = 'ok';
	public const FAILED  = 'failed';
	public const REFUSED = 'refused';

	private const API = 'https://graph.facebook.com/v21.0/';

	/** How many posts are asked for. The section shows at most nine. */
	private const LIMIT = 12;

	/** Seconds. A background job may wait; it must not wait forever. */
	private const TIMEOUT = 15;

	/** @var callable(string):?array{code:int,body:string} */
	private $transport;

	/** @param (callable(string):?array{code:int,body:string})|null $transport */
	public function __construct( ?callable $transport = null ) {
		$this->transport = $transport ?? array( self::class, 'wp_transport' );
	}

	/**
	 * Ask one platform for one account's recent posts.
	 *
	 * @return array{status:string,records:array<int,mixed>}
	 */
	public function fetch( string $platform, string $account_id, string $token ): array {
		$url = self::url( $platform, $account_id, $token );
		if ( '' === $url ) {
			return array( 'status' => self::FAILED, 'records' => array() );
		}

		$response = ( $this->transport )( $url );
		if ( ! is_array( $response ) || ! isset( $response['code'], $response['body'] ) ) {
			return array( 'status' => self::FAILED, 'records' => array() );
		}

		$decoded = json_decode( (string) $response['body'], true );
		if ( ! is_array( $decoded ) ) {
			return array( 'status' => self::FAILED, 'records' => array() );
		}

		if ( isset( $decoded['error'] ) ) {
			return array( 'status' => self::is_refusal( $decoded['error'] ) ? self::REFUSED : self::FAILED, 'records' => array() );
		}
		if ( 200 !== (int) $response['code'] ) {
			return array( 'status' => self::FAILED, 'records' => array() );
		}

		$data = $decoded['data'] ?? null;
		return array( 'status' => self::OK, 'records' => is_array( $data ) ? $data : array() );
	}

	/**
	 * An error that says the token itself is no good, rather than that the
	 * request went wrong. These never recover on their own, so the caller stops
	 * the daily refresh and asks the club to reconnect.
	 *
	 * 190 is an invalid or expired token; the 200-series subcodes are a
	 * permission the club has withdrawn. Confirm both against the error shapes
	 * recorded in docs/integrations/meta-graph-notes.md.
	 *
	 * @param mixed $error
	 */
	private static function is_refusal( $error ): bool {
		if ( ! is_array( $error ) ) {
			return false;
		}
		$code = (int) ( $error['code'] ?? 0 );
		return 190 === $code || ( $code >= 200 && $code <= 299 );
	}

	private static function url( string $platform, string $account_id, string $token ): string {
		if ( '' === trim( $account_id ) || '' === trim( $token ) ) {
			return '';
		}
		if ( 'facebook' === $platform ) {
			$path   = $account_id . '/posts';
			$fields = 'id,message,created_time,permalink_url,full_picture';
		} elseif ( 'instagram' === $platform ) {
			$path   = $account_id . '/media';
			$fields = 'id,caption,timestamp,permalink,media_type,media_url,thumbnail_url';
		} else {
			return '';
		}
		return self::API . $path . '?' . http_build_query( array(
			'fields'       => $fields,
			'limit'        => self::LIMIT,
			'access_token' => $token,
		) );
	}

	/**
	 * The real transport. Returns null on anything WordPress calls an error, so
	 * fetch() reports a failure rather than mistaking it for an empty account.
	 *
	 * @return array{code:int,body:string}|null
	 */
	private static function wp_transport( string $url ): ?array {
		if ( ! function_exists( 'wp_remote_get' ) ) {
			return null;
		}
		$response = wp_remote_get( $url, array( 'timeout' => self::TIMEOUT ) );
		if ( function_exists( 'is_wp_error' ) && is_wp_error( $response ) ) {
			return null;
		}
		return array(
			'code' => (int) wp_remote_retrieve_response_code( $response ),
			'body' => (string) wp_remote_retrieve_body( $response ),
		);
	}
}
```

- [ ] **Step 4: Write the feed source**

Create `includes/social/class-meta-feed-source.php`:

```php
<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stage two's source: the club's connected account, mapped into the same
 * normalised posts the pasted-links source returns. The renderer never learns
 * which platform, or which source, it is drawing.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Meta_Feed_Source implements Blueworx_Clubhouse_Feed_Source {

	private Blueworx_Clubhouse_Meta_Connection $connection;
	private Blueworx_Clubhouse_Meta_Client $client;
	private bool $refused = false;

	public function __construct( Blueworx_Clubhouse_Meta_Connection $connection, Blueworx_Clubhouse_Meta_Client $client ) {
		$this->connection = $connection;
		$this->client     = $client;
	}

	/** @return array<int,array{id:string,image:string,caption:string,date:string,permalink:string}>|null */
	public function posts(): ?array {
		if ( ! $this->connection->is_connected() ) {
			// Nothing connected is not a failure: it is a club that has not
			// finished setting the section up, and the cache must read it as
			// "not connected" rather than as an outage.
			return array();
		}

		$result = $this->client->fetch(
			$this->connection->platform(),
			$this->connection->account_id(),
			$this->connection->token()
		);

		if ( Blueworx_Clubhouse_Meta_Client::REFUSED === $result['status'] ) {
			$this->refused = true;
			return null;
		}
		if ( Blueworx_Clubhouse_Meta_Client::OK !== $result['status'] ) {
			return null;
		}

		$mapper = 'instagram' === $this->connection->platform()
			? array( self::class, 'map_instagram' )
			: array( self::class, 'map_facebook' );

		$posts = array();
		foreach ( $result['records'] as $record ) {
			$post = is_array( $record ) ? $mapper( $record ) : null;
			if ( null !== $post ) {
				$posts[] = $post;
			}
		}
		return $posts;
	}

	/** Whether the last fetch was refused rather than merely failing — see Meta_Client. */
	public function refused(): bool {
		return $this->refused;
	}

	/**
	 * One Facebook Page post. Null when there is no id or no link back: a card
	 * that leads nowhere is worse than one fewer card.
	 *
	 * @param array<string,mixed> $record
	 * @return array{id:string,image:string,caption:string,date:string,permalink:string}|null
	 */
	public static function map_facebook( array $record ): ?array {
		return self::post(
			(string) ( $record['id'] ?? '' ),
			(string) ( $record['full_picture'] ?? '' ),
			(string) ( $record['message'] ?? '' ),
			(string) ( $record['created_time'] ?? '' ),
			(string) ( $record['permalink_url'] ?? '' )
		);
	}

	/**
	 * One Instagram media item. A video's own URL is the video file, so the
	 * thumbnail is what a card should show — see the media_type notes in
	 * docs/integrations/meta-graph-notes.md.
	 *
	 * @param array<string,mixed> $record
	 * @return array{id:string,image:string,caption:string,date:string,permalink:string}|null
	 */
	public static function map_instagram( array $record ): ?array {
		$type  = strtoupper( (string) ( $record['media_type'] ?? '' ) );
		$image = 'VIDEO' === $type
			? (string) ( $record['thumbnail_url'] ?? '' )
			: (string) ( $record['media_url'] ?? '' );
		return self::post(
			(string) ( $record['id'] ?? '' ),
			$image,
			(string) ( $record['caption'] ?? '' ),
			(string) ( $record['timestamp'] ?? '' ),
			(string) ( $record['permalink'] ?? '' )
		);
	}

	/**
	 * The shared shape. Dates are normalised to ISO 8601 here so the renderer
	 * has one format to read whatever the platform sent.
	 *
	 * @return array{id:string,image:string,caption:string,date:string,permalink:string}|null
	 */
	private static function post( string $id, string $image, string $caption, string $date, string $permalink ): ?array {
		$id        = trim( $id );
		$permalink = trim( $permalink );
		if ( '' === $id || 1 !== preg_match( '#^https?://#i', $permalink ) ) {
			return null;
		}
		$time = '' !== trim( $date ) ? strtotime( $date ) : false;
		return array(
			'id'        => $id,
			'image'     => 1 === preg_match( '#^https?://#i', trim( $image ) ) ? trim( $image ) : '',
			'caption'   => trim( $caption ),
			'date'      => false !== $time ? gmdate( 'c', $time ) : '',
			'permalink' => $permalink,
		);
	}
}
```

- [ ] **Step 5: Load both**

In `includes/bootstrap.php`, in the Social block, after the connection:

```php
require_once __DIR__ . '/social/class-meta-client.php';
require_once __DIR__ . '/social/class-meta-feed-source.php';
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `vendor/bin/phpunit --filter MetaFeedSourceTest`
Expected: PASS, 7 tests. Where a fixture-derived assertion fails, correct the MAPPING against what Task 2 recorded — never loosen the assertion to match a guess.

- [ ] **Step 7: Commit**

```bash
git add includes/social/class-meta-client.php includes/social/class-meta-feed-source.php includes/bootstrap.php tests/php/MetaFeedSourceTest.php
git commit -m "Fetch a club's posts from the Graph API"
```

---

### Task 4: The daily refresh

**Files:**
- Create: `includes/social/class-social-feed-refresh.php`
- Modify: `includes/social/class-social-feed.php` (a connected read never fetches; a write path for the scheduled run)
- Modify: `blueworx-labs-clubhouse.php` (register the event; clear it on deactivation)
- Test: `tests/php/SocialFeedRefreshTest.php`

**Interfaces:**
- Consumes: `Meta_Connection`, `Meta_Feed_Source` (Tasks 1 and 3), `Social_Feed` (stage one).
- Produces:
  - `final class Blueworx_Clubhouse_Social_Feed_Refresh` with `const HOOK = 'blueworx_clubhouse_social_feed_refresh'`, `static register(): void`, `static schedule(): void`, `static unschedule(): void`, `static run( Blueworx_Clubhouse_Meta_Connection $connection, Blueworx_Clubhouse_Feed_Source $source ): string` (returns `'ok'`, `'skipped'`, `'failed'` or `'refused'`).
  - On `Social_Feed`: `store( array $posts ): void` and `fail(): void`, used only by the scheduled run, plus `__construct( Blueworx_Clubhouse_Feed_Source $source, bool $fetch_on_read = true )`.

- [ ] **Step 1: Write the failing test**

Create `tests/php/SocialFeedRefreshTest.php`:

```php
<?php

use PHPUnit\Framework\TestCase;

final class SocialFeedRefreshTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
	}

	protected function tearDown(): void {
		wp_stub_reset();
	}

	private function connected(): Blueworx_Clubhouse_Meta_Connection {
		$c = new Blueworx_Clubhouse_Meta_Connection( new Blueworx_Clubhouse_Fake_Storage() );
		$c->store( 'facebook', '1234', 'Marlow RFC', 'tok-abc' );
		return $c;
	}

	/** @return array<int,array{id:string,image:string,caption:string,date:string,permalink:string}> */
	private function onePost(): array {
		return array( array( 'id' => 'a', 'image' => '', 'caption' => 'one', 'date' => '', 'permalink' => 'https://x.test/1' ) );
	}

	public function test_a_connected_read_never_fetches(): void {
		// The whole point of the daily job: no visitor ever waits on Meta.
		$source = new FakeFeedSource( $this->onePost() );
		$feed   = new Blueworx_Clubhouse_Social_Feed( $source, false );
		$this->assertSame( array(), $feed->posts() );
		$this->assertSame( 0, $source->calls );
	}

	public function test_the_scheduled_run_fills_the_cache_a_read_then_serves(): void {
		$source = new FakeFeedSource( $this->onePost() );
		$this->assertSame( 'ok', Blueworx_Clubhouse_Social_Feed_Refresh::run( $this->connected(), $source ) );

		$feed = new Blueworx_Clubhouse_Social_Feed( new FakeFeedSource( null ), false );
		$this->assertCount( 1, $feed->posts() );
		$this->assertSame( Blueworx_Clubhouse_Social_Feed::OK, $feed->status() );
	}

	public function test_a_refused_connection_is_marked_and_stops_the_job(): void {
		$connection = $this->connected();
		$source     = new RefusingFeedSource();
		$this->assertSame( 'refused', Blueworx_Clubhouse_Social_Feed_Refresh::run( $connection, $source ) );
		$this->assertTrue( $connection->is_refused() );

		// A refused connection is not asked again until somebody reconnects.
		$this->assertSame( 'skipped', Blueworx_Clubhouse_Social_Feed_Refresh::run( $connection, $source ) );
		$this->assertSame( 1, $source->calls );
	}

	public function test_nothing_connected_means_nothing_is_asked(): void {
		$connection = new Blueworx_Clubhouse_Meta_Connection( new Blueworx_Clubhouse_Fake_Storage() );
		$source     = new FakeFeedSource( $this->onePost() );
		$this->assertSame( 'skipped', Blueworx_Clubhouse_Social_Feed_Refresh::run( $connection, $source ) );
		$this->assertSame( 0, $source->calls );
	}

	public function test_a_failed_run_leaves_the_last_good_posts_in_place(): void {
		Blueworx_Clubhouse_Social_Feed_Refresh::run( $this->connected(), new FakeFeedSource( $this->onePost() ) );
		wp_stub_clear_transients();

		$this->assertSame( 'failed', Blueworx_Clubhouse_Social_Feed_Refresh::run( $this->connected(), new FakeFeedSource( null ) ) );
		$feed = new Blueworx_Clubhouse_Social_Feed( new FakeFeedSource( null ), false );
		$this->assertCount( 1, $feed->posts(), 'a bad night lost the club its feed' );
	}

	public function test_scheduling_is_idempotent_and_reversible(): void {
		Blueworx_Clubhouse_Social_Feed_Refresh::schedule();
		Blueworx_Clubhouse_Social_Feed_Refresh::schedule();
		$this->assertSame( 1, wp_stub_scheduled_count( Blueworx_Clubhouse_Social_Feed_Refresh::HOOK ) );

		Blueworx_Clubhouse_Social_Feed_Refresh::unschedule();
		$this->assertSame( 0, wp_stub_scheduled_count( Blueworx_Clubhouse_Social_Feed_Refresh::HOOK ) );
	}
}

/** A source that always answers "Meta refused this token". */
final class RefusingFeedSource implements Blueworx_Clubhouse_Feed_Source {
	public int $calls = 0;
	public function posts(): ?array {
		++$this->calls;
		return null;
	}
	public function refused(): bool {
		return true;
	}
}
```

`FakeFeedSource` already exists in `tests/php/SocialFeedTest.php`. Move it to `tests/php/fakes/class-fake-feed-source.php`, require it from the test bootstrap alongside the other fakes, and delete the inline copy — two tests now need it.

`wp_stub_scheduled_count()`, `wp_schedule_event()`, `wp_next_scheduled()` and `wp_clear_scheduled_hook()` do not exist in `tests/php/wp-stubs.php` yet. Add them next to the transient stubs, backed by a `$GLOBALS['wp_stub_scheduled']` array that `wp_stub_reset()` empties.

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit --filter SocialFeedRefreshTest`
Expected: FAIL — `Class "Blueworx_Clubhouse_Social_Feed_Refresh" not found`.

- [ ] **Step 3: Let Social_Feed be filled from outside**

In `includes/social/class-social-feed.php`, add the constructor flag and the two write methods:

```php
	private bool $fetch_on_read;

	public function __construct( Blueworx_Clubhouse_Feed_Source $source, bool $fetch_on_read = true ) {
		$this->source        = $source;
		$this->fetch_on_read = $fetch_on_read;
	}
```

In `posts()`, immediately before `$fetched = $this->source->posts();`:

```php
		if ( ! $this->fetch_on_read ) {
			// A connected club's posts are fetched by the daily job, never by a
			// visitor. With no cache and no history there is simply nothing to
			// show yet — which is what the first minutes after connecting look
			// like, and it renders as nothing rather than as a wait.
			$this->resolved = $this->after_failure();
			return $this->resolved;
		}
```

And add, below `status()`:

```php
	/**
	 * Fill the caches from outside a page render — the daily refresh, and the
	 * Refresh now button. The only writers other than a read-through fetch.
	 *
	 * @param array<int,mixed> $posts
	 */
	public function store( array $posts ): void {
		$clean = self::clean( $posts );
		if ( function_exists( 'set_transient' ) ) {
			set_transient( self::cache_key(), $clean, self::CACHE_TTL );
			delete_transient( self::failure_key() );
		}
		if ( array() !== $clean && function_exists( 'update_option' ) ) {
			update_option( self::LAST_GOOD_OPTION, $clean, false );
		}
		$this->resolved = null;
	}

	/** Record that a scheduled fetch failed, without disturbing the last good posts. */
	public function fail(): void {
		if ( function_exists( 'set_transient' ) ) {
			set_transient( self::failure_key(), true, self::FAILURE_TTL );
		}
		$this->resolved = null;
	}
```

`CACHE_TTL` must now outlive the gap between daily runs, or the cache expires every afternoon and a connected site falls back to its last-good option until the next night's job. Raise it to `172800` (two days) and update its comment to say why: it is a safety net behind a daily job, not the refresh mechanism.

- [ ] **Step 4: Write the scheduled run**

Create `includes/social/class-social-feed-refresh.php`:

```php
<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The daily fetch. Runs in the background so no page render ever waits on Meta,
 * and writes into the same caches Social_Feed already reads.
 *
 * Scheduling, unscheduling and deactivation are all handled here — the three
 * points a scheduled event usually gets wrong, kept in one file so they cannot
 * drift apart.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Social_Feed_Refresh {

	public const HOOK = 'blueworx_clubhouse_social_feed_refresh';

	public static function register(): void {
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}
		add_action( self::HOOK, array( self::class, 'run_scheduled' ) );
	}

	/** Once a day, starting an hour from now so activating does not fetch mid-request. */
	public static function schedule(): void {
		if ( ! function_exists( 'wp_next_scheduled' ) || false !== wp_next_scheduled( self::HOOK ) ) {
			return;
		}
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK );
	}

	public static function unschedule(): void {
		if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
			wp_clear_scheduled_hook( self::HOOK );
		}
	}

	/** What WordPress calls. Builds the real collaborators and defers to run(). */
	public static function run_scheduled(): void {
		$storage    = new Blueworx_Clubhouse_Options_Storage();
		$connection = new Blueworx_Clubhouse_Meta_Connection( $storage );
		$source     = new Blueworx_Clubhouse_Meta_Feed_Source( $connection, new Blueworx_Clubhouse_Meta_Client() );
		self::run( $connection, $source );
	}

	/**
	 * One refresh. Returns what happened, so the Refresh now button can say so
	 * and the tests can assert it.
	 *
	 * A refused connection is never asked again: it cannot recover without
	 * somebody reconnecting, and asking daily for a year would be traffic that
	 * helps nobody.
	 *
	 * @return string 'ok'|'skipped'|'failed'|'refused'
	 */
	public static function run( Blueworx_Clubhouse_Meta_Connection $connection, Blueworx_Clubhouse_Feed_Source $source ): string {
		if ( ! $connection->is_connected() || $connection->is_refused() ) {
			return 'skipped';
		}

		$feed  = new Blueworx_Clubhouse_Social_Feed( $source, false );
		$posts = $source->posts();

		if ( null === $posts ) {
			$feed->fail();
			if ( method_exists( $source, 'refused' ) && $source->refused() ) {
				$connection->mark_refused();
				return 'refused';
			}
			return 'failed';
		}

		$feed->store( $posts );
		return 'ok';
	}
}
```

- [ ] **Step 5: Wire it into the plugin's lifecycle**

In `blueworx-labs-clubhouse.php`, inside the init function beside the other `register()` calls:

```php
	Blueworx_Clubhouse_Social_Feed_Refresh::register();
```

and in the deactivation hook, before the rewrite flush:

```php
	Blueworx_Clubhouse_Social_Feed_Refresh::unschedule();
```

Read the existing `register_deactivation_hook( __FILE__, 'flush_rewrite_rules' )` line first: it passes a bare function name, so it needs replacing with a small named function that unschedules and then flushes. Add `require_once` for the new class in `includes/bootstrap.php` after the feed source.

- [ ] **Step 6: Run the tests to verify they pass**

Run: `vendor/bin/phpunit --filter "SocialFeedRefreshTest|SocialFeedTest"`
Expected: PASS. Then run the whole suite — the `Social_Feed` constructor changed, so anything constructing it is worth seeing green: `vendor/bin/phpunit`.

- [ ] **Step 7: Commit**

```bash
git add includes tests/php blueworx-labs-clubhouse.php
git commit -m "Fetch the feed once a day in the background"
```

---

### Task 5: Connect, disconnect, and what the club sees

**Files:**
- Create: `includes/social/class-meta-connect-controller.php`
- Modify: `includes/admin/class-content-catalogue.php` (the source choice, and the connection note)
- Modify: `includes/render/class-page-renderer.php` (pick the source from the club's choice)
- Modify: `includes/bootstrap.php`, `blueworx-labs-clubhouse.php`
- Test: `tests/php/MetaConnectControllerTest.php`, `tests/social-feed-connect.spec.js`

**Interfaces:**
- Consumes: everything from Tasks 1, 3 and 4, and the connect endpoint's handoff format (see "Before starting").
- Produces: `final class Blueworx_Clubhouse_Meta_Connect_Controller` with `const CAPABILITY = 'manage_options'`, `const ACTION_RETURN`, `const ACTION_DISCONNECT`, `const ACTION_REFRESH`, `static register(): void`, `static connect_url( string $return_url ): string`, `static status_line( Blueworx_Clubhouse_Meta_Connection $connection, string $last_fetch ): array{state:string,message:string,button:string}`.

- [ ] **Step 1: Write the failing test**

Create `tests/php/MetaConnectControllerTest.php`. Pure functions only — the handoff verification and the message text; the WordPress plumbing is covered by the Playwright spec:

```php
<?php

use PHPUnit\Framework\TestCase;

final class MetaConnectControllerTest extends TestCase {

	private function connection( bool $connected, bool $refused = false ): Blueworx_Clubhouse_Meta_Connection {
		$c = new Blueworx_Clubhouse_Meta_Connection( new Blueworx_Clubhouse_Fake_Storage() );
		if ( $connected ) {
			$c->store( 'facebook', '1234', 'Marlow RFC', 'tok-abc' );
		}
		if ( $refused ) {
			$c->mark_refused();
		}
		return $c;
	}

	public function test_an_unconnected_club_is_asked_to_connect(): void {
		$status = Blueworx_Clubhouse_Meta_Connect_Controller::status_line( $this->connection( false ), '' );
		$this->assertSame( 'not_connected', $status['state'] );
		$this->assertStringContainsString( 'Connect', $status['button'] );
	}

	public function test_a_connected_club_is_told_which_account_and_when(): void {
		$status = Blueworx_Clubhouse_Meta_Connect_Controller::status_line( $this->connection( true ), '2026-08-18T04:00:00+00:00' );
		$this->assertSame( 'connected', $status['state'] );
		$this->assertStringContainsString( 'Marlow RFC', $status['message'] );
		$this->assertStringContainsString( 'Facebook', $status['message'] );
		$this->assertStringContainsString( '18 Aug 2026', $status['message'] );
	}

	public function test_a_connected_club_that_has_never_fetched_says_so_plainly(): void {
		$status = Blueworx_Clubhouse_Meta_Connect_Controller::status_line( $this->connection( true ), '' );
		$this->assertSame( 'connected', $status['state'] );
		$this->assertStringContainsString( 'not fetched yet', $status['message'] );
	}

	public function test_a_refused_connection_asks_for_a_reconnection_not_a_connection(): void {
		// The club's action is different: fix this one, not make a new one.
		$status = Blueworx_Clubhouse_Meta_Connect_Controller::status_line( $this->connection( true, true ), '2026-08-01T04:00:00+00:00' );
		$this->assertSame( 'refused', $status['state'] );
		$this->assertStringContainsString( 'Marlow RFC', $status['message'] );
		$this->assertStringContainsString( 'Reconnect', $status['button'] );
	}

	public function test_the_connect_url_carries_the_club_site_back(): void {
		$url = Blueworx_Clubhouse_Meta_Connect_Controller::connect_url( 'https://club.test/wp-admin/admin.php?page=clubhouse-content' );
		$this->assertStringStartsWith( 'https://', $url );
		$this->assertStringContainsString( rawurlencode( 'https://club.test/wp-admin/admin.php?page=clubhouse-content' ), $url );
	}
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit --filter MetaConnectControllerTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Write the controller**

Create `includes/social/class-meta-connect-controller.php`. It must:

- Register three `admin_post_` handlers: the return from the connect endpoint, disconnect, and refresh now. Each checks `current_user_can( self::CAPABILITY )` and `check_admin_referer()` — except the return handler, which cannot carry a WordPress nonce it never issued and instead verifies the handoff's signature and expiry against the shared secret, then redirects to Club Pages with a result in the query string.
- On a successful return: `store()` the connection, `schedule()` the refresh, and run one immediately so the club sees posts straight away rather than tomorrow.
- On disconnect: `forget()`, `unschedule()`, and clear the cached posts and the last-good option — a club that disconnects should not still see its posts.
- On refresh now: run `Social_Feed_Refresh::run()` and report what happened.
- `status_line()` is pure and returns the state, the sentence and the button label. Dates format with `wp_date( 'j M Y' )` where available, as `Sections::feed_date()` does.

Write the actual handoff verification against the format fixed in "Before starting" — a signature over the payload and an expiry, rejecting anything older than a few minutes and anything whose signature does not match. Do not accept a token from an unsigned redirect: that would let any page hand a club's site a token of someone else's choosing.

- [ ] **Step 4: Offer the choice on Club Pages**

In `includes/admin/class-content-catalogue.php`, on the `social_feed` section, add ahead of the platform field:

```php
						self::f_select( 'source', 'Where posts come from', array( 'connected' => 'Connected account', 'manual' => 'Pasted links' ) ),
```

and rewrite the section's `note` so it explains both: connected accounts fetch once a day on their own; pasted links are for a club that would rather not connect one. The Connect button, the "connected as" line and Refresh now are rendered by the controller into the section's note area — check how `Content_Screen` renders `note` and extend it with a `panel` hook rather than smuggling HTML through a text field.

- [ ] **Step 5: Pick the source in the renderer**

In `includes/render/class-page-renderer.php`, replace the hard-wired manual source in the `home.social_feed` block:

```php
			$source_choice = self::cget( $content, 'home', 'social_feed', 'source', 'manual' );
			if ( 'connected' === $source_choice ) {
				// Connected clubs are served entirely from the caches the daily
				// job fills: false here is what keeps Meta out of a page render.
				$connection = new Blueworx_Clubhouse_Meta_Connection( new Blueworx_Clubhouse_Options_Storage() );
				$feed       = new Blueworx_Clubhouse_Social_Feed(
					new Blueworx_Clubhouse_Meta_Feed_Source( $connection, new Blueworx_Clubhouse_Meta_Client() ),
					false
				);
			} else {
				$feed = new Blueworx_Clubhouse_Social_Feed( new Blueworx_Clubhouse_Manual_Feed_Source( $content ) );
			}
			$posts = null !== $content ? $feed->posts() : array();
```

Check how the renderer gets storage elsewhere before constructing `Options_Storage` directly here — if there is an existing accessor, use it. Add a `PageRendererTest` case pinning that a connected club's render performs no fetch: pass a source that would fail the test if called.

- [ ] **Step 6: Cover the editor in the browser**

Create `tests/social-feed-connect.spec.js` covering the not-connected state, the connected state, and the reconnect notice on the Club Pages screen. These are WordPress-only specs (the admin does not exist in the preview) — tag them the way `tests/menu-editor.spec.js` tags its admin specs, and seed the connection state the way that spec seeds its own.

- [ ] **Step 7: Run everything**

```bash
vendor/bin/phpunit
npm run test:wp
```

Expected: PASS. Remember the WordPress harness boot race — run `npm run wp:up` first if the browser run cannot connect.

- [ ] **Step 8: Commit**

```bash
git add includes tests
git commit -m "Let a club connect its account, and choose where posts come from"
```

---

### Task 6: A stale picture must not look broken

**Files:**
- Modify: `includes/render/class-sections.php` (`social_feed()`)
- Test: `tests/php/SocialFeedSectionTest.php`

Linked images expire when a feed has not refreshed for days. A card whose picture fails must fall back to the plain tile the section already draws for a text-only post, rather than showing a broken-image icon.

- [ ] **Step 1: Write the failing test**

Add to `tests/php/SocialFeedSectionTest.php`:

```php
	public function test_a_picture_that_fails_to_load_falls_back_to_the_plain_tile(): void {
		// Meta's image URLs are signed and expire; a feed that has not refreshed
		// for days must lose its pictures quietly, not show broken-image icons.
		$html = $this->html( $this->onePost() );
		$this->assertStringContainsString( 'onerror', $html );
		$this->assertStringContainsString( 'ch-media--empty', $html );
	}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/phpunit --filter test_a_picture_that_fails`
Expected: FAIL — no `onerror` in the markup.

- [ ] **Step 3: Make the image degrade**

In `Sections::social_feed()`, stop using the shared `media()` helper for feed cards and emit the image with a fallback: the wrapper carries `ch-media ch-feed__media`, and the `<img>` carries an `onerror` that removes itself and adds `ch-media--empty` to its parent. Keep every attribute escaped. Write it as a small private helper beside `social_feed()`, commented with why it exists, so the shared `media()` used by every other section stays untouched.

- [ ] **Step 4: Run the section tests**

Run: `vendor/bin/phpunit --filter SocialFeedSectionTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add includes/render/class-sections.php tests/php/SocialFeedSectionTest.php
git commit -m "Lose an expired picture quietly rather than showing it broken"
```

---

### Task 7: Version, changelog, priorities and the lint pass

**Files:**
- Modify: `blueworx-labs-clubhouse.php`, `package.json`, `CHANGELOG.md`, `docs/priorities.md`

- [ ] **Step 1: Bump to 0.78.0**

A feature, so a minor bump. Update the plugin header `Version:`, `BLUEWORX_LABS_CLUBHOUSE_VERSION` and `package.json`. Check none is missed with a grep for the old number.

- [ ] **Step 2: Write the changelog entry**

At the top of `CHANGELOG.md`:

```markdown
## 0.78.0

- **Your social feed now updates itself.** Connect your Facebook Page or Instagram account under Club Pages → Home → Social feed and your latest posts appear on the Home page, refreshed once a day without anyone touching the site. Pasting links in by hand still works if you would rather not connect an account. If the connection ever stops working, your existing posts stay on the site and Club Pages tells you to reconnect.
```

- [ ] **Step 3: Update the priorities doc**

Strike stage two through in `docs/priorities.md`, note the version, and remove the "needs Meta app review before it can start" line — by this point it has.

- [ ] **Step 4: Run everything once**

```bash
vendor/bin/phpunit
npm run test:wp
composer lint
```

Present lint findings to the user; do not fix them without approval.

- [ ] **Step 5: Commit and open the pull request**

```bash
git add -A
git commit -m "Release 0.78.0 — the social feed connects to Facebook and Instagram"
git push -u origin <branch>
gh pr create --title "Pull the social feed from Facebook and Instagram" --body "$(cat <<'BODY'
A club connects its Facebook Page or Instagram account and its posts arrive on the Home page, refreshed daily in the background. Pasted links stay as a choice.

Needs the connect endpoint deployed, and the Meta app through review, before any club other than ours can switch it on.

Closes #219.

🤖 Generated with [Claude Code](https://claude.com/claude-code)
BODY
)"
```
