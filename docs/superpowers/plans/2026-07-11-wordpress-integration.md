# WordPress Integration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the plugin serve its eight-page Court Side site inside WordPress via `template_include`, with proper enqueue, cached `:root` tokens, and rewrite-rule routing — the milestone that produces the first deployment zip.

**Architecture:** A pure `Page_Map` (slug → renderer) is the single dispatch source for both the preview and WordPress. A thin `Frontend` class registers rewrite rules, selects a canvas template that fires `wp_head()`/`wp_footer()`, and enqueues the look stylesheet + fonts + cached `:root` inline style. All HTML stays in the already-tested `Page_Renderer`/`Sections`. WP-coupled code is verified with a dependency-free WP-function shim; pure code with the existing fakes.

**Tech Stack:** PHP 8.2+, WordPress plugin APIs (rewrite rules, `template_include`, `wp_enqueue_*`), PHPUnit (existing dev harness), no new dependencies.

## Global Constraints

- **No new runtime or dev dependency** (composer.json unchanged; `approved-deps.json` is npm-only and untouched).
- **PHP 8.2+**; every PHP file starts with `declare(strict_types=1);` and the `if ( ! defined( 'ABSPATH' ) ) { exit; }` guard.
- **Pure core stays WP-free** — no WordPress functions in `includes/render/`, `includes/theme/`, `includes/content/`, `includes/core/`. WordPress calls live only in `includes/frontend/`, `templates/`, and the main plugin file.
- **Renderers stay skin-agnostic and untouched** — this plan adds no `ch-*` markup and changes no section renderer.
- **Escaping:** all interpolated text already escaped inside `Sections`/`Page_Renderer`; new code adds no unescaped output.
- **Version:** minor bump **0.11.0 → 0.12.0** (plugin header + `BLUEWORX_LABS_CLUBHOUSE_VERSION` + `package.json`), changelog entry alongside.
- **PHPCS clean** (`composer lint`) and **all PHPUnit green** (`vendor/bin/phpunit`) at every commit.

## File Structure

- Create `includes/render/class-page-map.php` — pure slug→renderer dispatch.
- Create `includes/theme/class-theme-cache.php` — Storage-backed `:root` cache.
- Create `includes/frontend/class-frontend.php` — thin WP glue + pure `resolve_slug`/`enqueue_specs` helpers.
- Create `templates/clubhouse.php` — canvas template.
- Create `assets/js/reveal.js` — extracted scroll-reveal script (single source).
- Create `tests/php/wp-stubs.php` — dependency-free WP-function recorder shim.
- Create `tests/php/PageMapTest.php`, `tests/php/ThemeCacheTest.php`, `tests/php/FrontendTest.php`.
- Modify `includes/bootstrap.php` — require the two new pure classes.
- Modify `includes/render/class-page-renderer.php` — `reveal_script()` reads the JS file.
- Modify `preview/index.php` — dispatch through `Page_Map`.
- Modify `blueworx-labs-clubhouse.php` — boot `Frontend`, register activation/deactivation hooks, version bump.
- Modify `tests/php/bootstrap.php` — load `wp-stubs.php`.
- Modify `CHANGELOG.md`.

---

### Task 1: `Page_Map` — pure slug → renderer dispatch

**Files:**
- Create: `includes/render/class-page-map.php`
- Test: `tests/php/PageMapTest.php`
- Modify: `includes/bootstrap.php` (add require after the page renderer require), `preview/index.php`

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Page_Renderer::{home,about,membership,contact,login,sports,teams,events,calendar}( Branding, Visibility ): string`; `Blueworx_Clubhouse_Branding`; `Blueworx_Clubhouse_Visibility`.
- Produces:
  - `Blueworx_Clubhouse_Page_Map::pages(): array<int,array{slug:string,label:string,method:string}>`
  - `Blueworx_Clubhouse_Page_Map::has(string $slug): bool`
  - `Blueworx_Clubhouse_Page_Map::render(string $slug, Branding, Visibility): string`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/php/PageMapTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class PageMapTest extends TestCase {

	private function branding(): Blueworx_Clubhouse_Branding {
		return new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() );
	}
	private function visibility(): Blueworx_Clubhouse_Visibility {
		return new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() );
	}

	public function test_home_slug_is_empty_string_and_first(): void {
		$pages = Blueworx_Clubhouse_Page_Map::pages();
		$this->assertSame( '', $pages[0]['slug'] );
		$this->assertSame( 'home', $pages[0]['method'] );
	}

	public function test_has_known_and_unknown(): void {
		$this->assertTrue( Blueworx_Clubhouse_Page_Map::has( '' ) );
		$this->assertTrue( Blueworx_Clubhouse_Page_Map::has( 'calendar' ) );
		$this->assertFalse( Blueworx_Clubhouse_Page_Map::has( 'nope' ) );
	}

	public function test_all_eight_page_slugs_present(): void {
		$slugs = array_column( Blueworx_Clubhouse_Page_Map::pages(), 'slug' );
		foreach ( array( '', 'about', 'membership', 'contact', 'login', 'sports', 'teams', 'events', 'calendar' ) as $slug ) {
			$this->assertContains( $slug, $slugs );
		}
	}

	public function test_render_dispatches_to_the_right_page(): void {
		// Calendar body carries the calendar-only hook; About carries benefits, not calendar.
		$cal = Blueworx_Clubhouse_Page_Map::render( 'calendar', $this->branding(), $this->visibility() );
		$this->assertStringContainsString( 'ch-cal', $cal );

		$about = Blueworx_Clubhouse_Page_Map::render( 'about', $this->branding(), $this->visibility() );
		$this->assertStringContainsString( 'ch-benefits', $about );
		$this->assertStringNotContainsString( 'ch-cal"', $about );
	}

	public function test_render_home_for_empty_slug(): void {
		$home = Blueworx_Clubhouse_Page_Map::render( '', $this->branding(), $this->visibility() );
		$this->assertStringContainsString( 'ch-cards', $home );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter PageMapTest`
Expected: FAIL — `Class "Blueworx_Clubhouse_Page_Map" not found`.

- [ ] **Step 3: Write minimal implementation**

```php
<?php
// includes/render/class-page-map.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single source of truth for which pages the plugin serves and how each renders.
 * Slug '' is the site root (Home). Both the WordPress frontend and the DB-free
 * preview dispatch through here, so they render byte-identical bodies.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Page_Map {

	/** @return array<int,array{slug:string,label:string,method:string}> */
	public static function pages(): array {
		return array(
			array( 'slug' => '',           'label' => 'Home',       'method' => 'home' ),
			array( 'slug' => 'about',      'label' => 'About',      'method' => 'about' ),
			array( 'slug' => 'membership', 'label' => 'Membership', 'method' => 'membership' ),
			array( 'slug' => 'contact',    'label' => 'Contact',    'method' => 'contact' ),
			array( 'slug' => 'login',      'label' => 'Log in',     'method' => 'login' ),
			array( 'slug' => 'sports',     'label' => 'Sports',     'method' => 'sports' ),
			array( 'slug' => 'teams',      'label' => 'Teams',      'method' => 'teams' ),
			array( 'slug' => 'events',     'label' => 'Events',     'method' => 'events' ),
			array( 'slug' => 'calendar',   'label' => 'Calendar',   'method' => 'calendar' ),
		);
	}

	public static function has( string $slug ): bool {
		foreach ( self::pages() as $page ) {
			if ( $page['slug'] === $slug ) {
				return true;
			}
		}
		return false;
	}

	public static function render(
		string $slug,
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility
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
			$visibility
		);
	}
}
```

Add to `includes/bootstrap.php` immediately after the `class-page-renderer.php` require:

```php
require_once __DIR__ . '/render/class-page-map.php';
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter PageMapTest`
Expected: PASS (5 tests).

- [ ] **Step 5: Refactor `preview/index.php` to dispatch through `Page_Map`**

Replace the whole `blueworx_clubhouse_preview_body()` function and its call site. Delete the function; at its former call site (`$body = blueworx_clubhouse_preview_body( (string) $page, ... )`) use:

```php
$slug = 'home' === $page ? '' : (string) $page;
if ( ! Blueworx_Clubhouse_Page_Map::has( $slug ) ) {
	$slug = '';
}
$body = Blueworx_Clubhouse_Page_Map::render( $slug, $branding, $visibility );
```

- [ ] **Step 6: Verify preview parity**

Run:
```bash
php -S 127.0.0.1:8130 >/tmp/p.log 2>&1 &
sleep 1
for p in home about membership contact login sports teams events calendar; do
  printf '%s ' "$p"; curl -s -o /dev/null -w '%{http_code}\n' "http://127.0.0.1:8130/preview/?page=$p";
done
kill %1
```
Expected: every page `200`.

- [ ] **Step 7: Run full PHP suite + lint**

Run: `vendor/bin/phpunit && composer lint`
Expected: all green; PHPCS clean.

- [ ] **Step 8: Commit**

```bash
git add includes/render/class-page-map.php includes/bootstrap.php preview/index.php tests/php/PageMapTest.php
git commit -m "feat: add Page_Map slug->renderer dispatch; route preview through it"
```

---

### Task 2: Extract the scroll-reveal script to a single JS file

**Files:**
- Create: `assets/js/reveal.js`
- Modify: `includes/render/class-page-renderer.php` (`reveal_script()` only)
- Test: `tests/php/PageRendererTest.php` (add one assertion)

**Interfaces:**
- Produces: `assets/js/reveal.js` (the single source of the reveal IIFE); `reveal_script()` unchanged in signature, now returns the file's contents wrapped in `<script>`.

- [ ] **Step 1: Write the failing test** (append to `tests/php/PageRendererTest.php`)

```php
	public function test_document_inlines_reveal_script_from_file(): void {
		$look     = new Blueworx_Clubhouse_Court_Side();
		$branding = new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() );
		$html     = Blueworx_Clubhouse_Page_Renderer::document( $look, $branding, '<main></main>', '/' );
		$this->assertStringContainsString( 'IntersectionObserver', $html );
		$this->assertStringContainsString( "querySelectorAll('.ch-main > *:not(.ch-hero)')", $html );
	}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter test_document_inlines_reveal_script_from_file`
Expected: FAIL — the current inline JS uses double-quoted `querySelectorAll(".ch-main...")`, so the single-quoted assertion does not match.

- [ ] **Step 3: Create `assets/js/reveal.js`**

```js
(function () {
  if (!('IntersectionObserver' in window) || matchMedia('(prefers-reduced-motion:reduce)').matches) {
    return;
  }
  var els = document.querySelectorAll('.ch-main > *:not(.ch-hero)');
  if (!els.length) {
    return;
  }
  els.forEach(function (el) {
    el.classList.add('ch-reveal');
  });
  var io = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (entry.isIntersecting) {
        entry.target.classList.add('is-in');
        io.unobserve(entry.target);
      }
    });
  }, { rootMargin: '0px 0px -10% 0px', threshold: 0.08 });
  els.forEach(function (el) {
    io.observe(el);
  });
})();
```

- [ ] **Step 4: Rewrite `reveal_script()` to read the file**

Replace the body of `private static function reveal_script(): string` with:

```php
	private static function reveal_script(): string {
		$js = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/js/reveal.js' );
		return '<script>' . $js . '</script>';
	}
```

(`dirname( __DIR__, 2 )` from `includes/render/` is the plugin root — works in both the preview and WordPress with no constants required.)

- [ ] **Step 5: Run tests + lint**

Run: `vendor/bin/phpunit && composer lint`
Expected: all green (the new assertion passes; existing reveal assertions still match `IntersectionObserver`).

- [ ] **Step 6: Commit**

```bash
git add assets/js/reveal.js includes/render/class-page-renderer.php tests/php/PageRendererTest.php
git commit -m "refactor: extract scroll-reveal script to assets/js/reveal.js (single source)"
```

---

### Task 3: `:root` token cache (Storage-backed)

**Files:**
- Create: `includes/theme/class-theme-cache.php`
- Modify: `includes/bootstrap.php` (require after `class-theme-css.php`)
- Test: `tests/php/ThemeCacheTest.php`

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Storage`; `Blueworx_Clubhouse_Base_Look::slug()`; `Blueworx_Clubhouse_Branding::get_accent()`; `Blueworx_Clubhouse_Theme_Css::compose()`/`to_css()`.
- Produces:
  - `new Blueworx_Clubhouse_Theme_Cache( Storage )`
  - `root_css( Base_Look, Branding ): string`
  - `invalidate(): void`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/php/ThemeCacheTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class ThemeCacheTest extends TestCase {

	private function look(): Blueworx_Clubhouse_Base_Look {
		return new Blueworx_Clubhouse_Court_Side();
	}

	public function test_root_css_matches_pure_compose(): void {
		$storage  = new Blueworx_Clubhouse_Fake_Storage();
		$branding = new Blueworx_Clubhouse_Branding( $storage );
		$cache    = new Blueworx_Clubhouse_Theme_Cache( $storage );

		$expected = Blueworx_Clubhouse_Theme_Css::to_css(
			Blueworx_Clubhouse_Theme_Css::compose( $this->look(), $branding )
		);
		$this->assertSame( $expected, $cache->root_css( $this->look(), $branding ) );
	}

	public function test_second_read_uses_cached_string(): void {
		$storage  = new Blueworx_Clubhouse_Fake_Storage();
		$branding = new Blueworx_Clubhouse_Branding( $storage );
		$cache    = new Blueworx_Clubhouse_Theme_Cache( $storage );

		$cache->root_css( $this->look(), $branding );
		// Corrupt the stored CSS; a cache hit must return the corrupted value (proves no recompute).
		$storage->set( 'root_css', ':root{--sentinel:1}' );
		$this->assertSame( ':root{--sentinel:1}', $cache->root_css( $this->look(), $branding ) );
	}

	public function test_accent_change_recomputes(): void {
		$storage  = new Blueworx_Clubhouse_Fake_Storage();
		$branding = new Blueworx_Clubhouse_Branding( $storage );
		$cache    = new Blueworx_Clubhouse_Theme_Cache( $storage );

		$first = $cache->root_css( $this->look(), $branding );
		$branding->set_accent( '#ff5b23' );
		$second = $cache->root_css( $this->look(), $branding );
		$this->assertNotSame( $first, $second );
	}

	public function test_invalidate_clears_cache(): void {
		$storage  = new Blueworx_Clubhouse_Fake_Storage();
		$branding = new Blueworx_Clubhouse_Branding( $storage );
		$cache    = new Blueworx_Clubhouse_Theme_Cache( $storage );

		$cache->root_css( $this->look(), $branding );
		$cache->invalidate();
		$this->assertSame( '', $storage->get( 'root_css', '' ) );
		$this->assertSame( '', $storage->get( 'root_css_sig', '' ) );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter ThemeCacheTest`
Expected: FAIL — `Class "Blueworx_Clubhouse_Theme_Cache" not found`.

- [ ] **Step 3: Write minimal implementation**

```php
<?php
// includes/theme/class-theme-cache.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Caches the composed :root token string in storage (an autoloaded option in
 * production) so the colour math runs only when the look or accent changes.
 * The admin flow calls invalidate() on save.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Theme_Cache {

	private const CSS_KEY = 'root_css';
	private const SIG_KEY = 'root_css_sig';

	private Blueworx_Clubhouse_Storage $storage;

	public function __construct( Blueworx_Clubhouse_Storage $storage ) {
		$this->storage = $storage;
	}

	public function root_css(
		Blueworx_Clubhouse_Base_Look $look,
		Blueworx_Clubhouse_Branding $branding
	): string {
		$signature  = self::signature( $look, $branding );
		$cached_css = $this->storage->get( self::CSS_KEY, '' );
		$cached_sig = $this->storage->get( self::SIG_KEY, '' );

		if ( is_string( $cached_css ) && '' !== $cached_css && $cached_sig === $signature ) {
			return $cached_css;
		}

		$css = Blueworx_Clubhouse_Theme_Css::to_css(
			Blueworx_Clubhouse_Theme_Css::compose( $look, $branding )
		);
		$this->storage->set( self::CSS_KEY, $css );
		$this->storage->set( self::SIG_KEY, $signature );
		return $css;
	}

	public function invalidate(): void {
		$this->storage->delete( self::CSS_KEY );
		$this->storage->delete( self::SIG_KEY );
	}

	private static function signature(
		Blueworx_Clubhouse_Base_Look $look,
		Blueworx_Clubhouse_Branding $branding
	): string {
		// Tokens depend only on the look's shell tokens and the derived accent.
		return md5( $look->slug() . '|' . $branding->get_accent() );
	}
}
```

Add to `includes/bootstrap.php` immediately after the `class-theme-css.php` require:

```php
require_once __DIR__ . '/theme/class-theme-cache.php';
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter ThemeCacheTest`
Expected: PASS (4 tests).

- [ ] **Step 5: Run full suite + lint**

Run: `vendor/bin/phpunit && composer lint`
Expected: all green.

- [ ] **Step 6: Commit**

```bash
git add includes/theme/class-theme-cache.php includes/bootstrap.php tests/php/ThemeCacheTest.php
git commit -m "feat: cache composed :root tokens keyed by look+accent signature"
```

---

### Task 4: WP-function shim + `Frontend` pure helpers

**Files:**
- Create: `tests/php/wp-stubs.php`
- Modify: `tests/php/bootstrap.php` (load the shim)
- Create: `includes/frontend/class-frontend.php` (pure helpers only in this task)
- Test: `tests/php/FrontendTest.php`

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Page_Map::has()`; `Blueworx_Clubhouse_Base_Look`; `Blueworx_Clubhouse_Page_Renderer::google_fonts_url()`.
- Produces:
  - `Blueworx_Clubhouse_Frontend::resolve_slug( bool $is_front_page, mixed $query_var ): ?string`
  - `Blueworx_Clubhouse_Frontend::enqueue_specs( Base_Look $look, string $root_css, string $plugin_url ): array{fonts_url:string,stylesheet_url:string,inline_css:string,reveal_url:string}`

- [ ] **Step 1: Create the WP-function shim**

```php
<?php
// tests/php/wp-stubs.php
// Dependency-free recorder stubs for the handful of WordPress functions the
// Frontend glue calls. Each records into $GLOBALS['wp_stub_calls'] so tests can
// assert what was registered/enqueued. Guarded so a real WP runtime is never
// shadowed. Reset with wp_stub_reset() in setUp().
declare(strict_types=1);

$GLOBALS['wp_stub_calls']   = array();
$GLOBALS['wp_stub_options'] = array();

function wp_stub_reset(): void {
	$GLOBALS['wp_stub_calls']   = array();
	$GLOBALS['wp_stub_options'] = array();
}
function wp_stub_calls( string $fn ): array {
	return array_values( array_filter(
		$GLOBALS['wp_stub_calls'],
		static fn( $c ) => $c['fn'] === $fn
	) );
}
function wp_stub_record( string $fn, array $args ): void {
	$GLOBALS['wp_stub_calls'][] = array( 'fn' => $fn, 'args' => $args );
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( ...$a ) { wp_stub_record( 'add_action', $a ); return true; }
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( ...$a ) { wp_stub_record( 'add_filter', $a ); return true; }
}
if ( ! function_exists( 'add_rewrite_rule' ) ) {
	function add_rewrite_rule( ...$a ) { wp_stub_record( 'add_rewrite_rule', $a ); }
}
if ( ! function_exists( 'add_rewrite_tag' ) ) {
	function add_rewrite_tag( ...$a ) { wp_stub_record( 'add_rewrite_tag', $a ); }
}
if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( ...$a ) { wp_stub_record( 'wp_enqueue_style', $a ); }
}
if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( ...$a ) { wp_stub_record( 'wp_enqueue_script', $a ); }
}
if ( ! function_exists( 'wp_add_inline_style' ) ) {
	function wp_add_inline_style( ...$a ) { wp_stub_record( 'wp_add_inline_style', $a ); }
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $key, $default = false ) {
		return $GLOBALS['wp_stub_options'][ $key ] ?? $default;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( string $key, $value, $autoload = null ): bool {
		$GLOBALS['wp_stub_options'][ $key ] = $value;
		return true;
	}
}
if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( string $key ): bool {
		unset( $GLOBALS['wp_stub_options'][ $key ] );
		return true;
	}
}
```

Add to `tests/php/bootstrap.php` (after `ABSPATH` is defined, before the plugin `bootstrap.php` require):

```php
require_once __DIR__ . '/wp-stubs.php';
```

- [ ] **Step 2: Write the failing test**

```php
<?php
// tests/php/FrontendTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class FrontendTest extends TestCase {

	public function test_resolve_slug_front_page_is_home(): void {
		$this->assertSame( '', Blueworx_Clubhouse_Frontend::resolve_slug( true, null ) );
	}

	public function test_resolve_slug_known_query_var(): void {
		$this->assertSame( 'about', Blueworx_Clubhouse_Frontend::resolve_slug( false, 'about' ) );
	}

	public function test_resolve_slug_unknown_is_null(): void {
		$this->assertNull( Blueworx_Clubhouse_Frontend::resolve_slug( false, 'nope' ) );
		$this->assertNull( Blueworx_Clubhouse_Frontend::resolve_slug( false, '' ) );
	}

	public function test_enqueue_specs_shape(): void {
		$look  = new Blueworx_Clubhouse_Court_Side();
		$specs = Blueworx_Clubhouse_Frontend::enqueue_specs( $look, ':root{--x:1}', 'https://club.test/wp-content/plugins/clubhouse/' );

		$this->assertStringContainsString( 'fonts.googleapis.com', $specs['fonts_url'] );
		$this->assertSame( 'https://club.test/wp-content/plugins/clubhouse/assets/looks/court-side.css', $specs['stylesheet_url'] );
		$this->assertSame( ':root{--x:1}', $specs['inline_css'] );
		$this->assertSame( 'https://club.test/wp-content/plugins/clubhouse/assets/js/reveal.js', $specs['reveal_url'] );
	}
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter FrontendTest`
Expected: FAIL — `Class "Blueworx_Clubhouse_Frontend" not found`.

- [ ] **Step 4: Write the pure helpers** (glue methods come in Task 5)

```php
<?php
// includes/frontend/class-frontend.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The plugin's only WordPress-coupled class: rewrite routing, template
 * selection, and asset enqueue. All HTML is delegated to Page_Map / Page_Renderer.
 * Pure decision helpers (resolve_slug, enqueue_specs) are unit-tested without a
 * WP runtime; the hook wiring is verified with the WP-function shim.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Frontend {

	public const QUERY_VAR = 'clubhouse_page';

	public static function resolve_slug( bool $is_front_page, mixed $query_var ): ?string {
		if ( $is_front_page ) {
			return '';
		}
		if ( is_string( $query_var ) && '' !== $query_var && Blueworx_Clubhouse_Page_Map::has( $query_var ) ) {
			return $query_var;
		}
		return null;
	}

	/**
	 * @return array{fonts_url:string,stylesheet_url:string,inline_css:string,reveal_url:string}
	 */
	public static function enqueue_specs(
		Blueworx_Clubhouse_Base_Look $look,
		string $root_css,
		string $plugin_url
	): array {
		return array(
			'fonts_url'      => Blueworx_Clubhouse_Page_Renderer::google_fonts_url( $look ),
			'stylesheet_url' => $plugin_url . $look->stylesheet(),
			'inline_css'     => $root_css,
			'reveal_url'     => $plugin_url . 'assets/js/reveal.js',
		);
	}
}
```

(`Frontend` is required from the main plugin file in Task 5, not `bootstrap.php`, because it is WP glue — keeping `bootstrap.php` loadable by the WP-free PHPUnit harness. For this task the test loads the class directly; add `require_once __DIR__ . '/../../includes/frontend/class-frontend.php';` to `tests/php/bootstrap.php` after the plugin bootstrap require.)

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter FrontendTest`
Expected: PASS (4 tests).

- [ ] **Step 6: Run full suite + lint**

Run: `vendor/bin/phpunit && composer lint`
Expected: all green.

- [ ] **Step 7: Commit**

```bash
git add tests/php/wp-stubs.php tests/php/bootstrap.php includes/frontend/class-frontend.php tests/php/FrontendTest.php
git commit -m "feat: add Frontend pure helpers + WP-function test shim"
```

---

### Task 5: `Frontend` WP wiring, canvas template, activation

**Files:**
- Modify: `includes/frontend/class-frontend.php` (add `register()`, hook callbacks)
- Create: `templates/clubhouse.php`
- Modify: `blueworx-labs-clubhouse.php` (require Frontend, boot it, activation/deactivation hooks)
- Test: `tests/php/FrontendTest.php` (add wiring assertions)

**Interfaces:**
- Consumes: the shim from Task 4; `Blueworx_Clubhouse_Page_Map::pages()`; `Blueworx_Clubhouse_Options_Storage`; `Blueworx_Clubhouse_Base_Look_Registry`; `Blueworx_Clubhouse_Court_Side`.
- Produces:
  - `Blueworx_Clubhouse_Frontend::register(): void` — registers all hooks.
  - `Blueworx_Clubhouse_Frontend::register_rewrites(): void` — rewrite rules + query var (also used on activation).
  - `Blueworx_Clubhouse_Frontend::filter_template( string $template ): string` — `template_include` callback.
  - `Blueworx_Clubhouse_Frontend::enqueue_assets(): void` — `wp_enqueue_scripts` callback.

- [ ] **Step 1: Write the failing wiring test** (append to `tests/php/FrontendTest.php`)

```php
	protected function setUp(): void {
		wp_stub_reset();
	}

	public function test_register_registers_expected_hooks(): void {
		Blueworx_Clubhouse_Frontend::register();

		$actions = array_map( static fn( $c ) => $c['args'][0], wp_stub_calls( 'add_action' ) );
		$filters = array_map( static fn( $c ) => $c['args'][0], wp_stub_calls( 'add_filter' ) );

		$this->assertContains( 'init', $actions );
		$this->assertContains( 'wp_enqueue_scripts', $actions );
		$this->assertContains( 'template_include', $filters );
	}

	public function test_register_rewrites_adds_one_rule_per_non_home_page(): void {
		Blueworx_Clubhouse_Frontend::register_rewrites();

		$rules      = wp_stub_calls( 'add_rewrite_rule' );
		$non_home   = array_filter( Blueworx_Clubhouse_Page_Map::pages(), static fn( $p ) => '' !== $p['slug'] );
		$this->assertCount( count( $non_home ), $rules );
		// Each rule maps its slug to the clubhouse_page query var.
		$this->assertStringContainsString( 'clubhouse_page=about', $rules[0]['args'][1] . $rules[1]['args'][1] . $rules[2]['args'][1] );
	}
```

(The last assertion concatenates the first few rule targets so it does not depend on rule order; `about` is among the non-home slugs.)

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter FrontendTest`
Expected: FAIL — `Call to undefined method ...::register()`.

- [ ] **Step 3: Add the wiring methods to `Blueworx_Clubhouse_Frontend`**

```php
	public static function register(): void {
		add_action( 'init', array( self::class, 'register_rewrites' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
		add_filter( 'template_include', array( self::class, 'filter_template' ) );
	}

	public static function register_rewrites(): void {
		add_rewrite_tag( '%' . self::QUERY_VAR . '%', '([^&]+)' );
		foreach ( Blueworx_Clubhouse_Page_Map::pages() as $page ) {
			if ( '' === $page['slug'] ) {
				continue;
			}
			add_rewrite_rule(
				'^' . $page['slug'] . '/?$',
				'index.php?' . self::QUERY_VAR . '=' . $page['slug'],
				'top'
			);
		}
	}

	private static function current_slug(): ?string {
		$is_front = function_exists( 'is_front_page' ) ? is_front_page() : false;
		$qv       = function_exists( 'get_query_var' ) ? get_query_var( self::QUERY_VAR ) : '';
		return self::resolve_slug( (bool) $is_front, $qv );
	}

	public static function filter_template( string $template ): string {
		$slug = self::current_slug();
		if ( null === $slug ) {
			return $template;
		}
		return dirname( __DIR__, 2 ) . '/templates/clubhouse.php';
	}

	private static function context(): array {
		$storage    = new Blueworx_Clubhouse_Options_Storage();
		$registry   = new Blueworx_Clubhouse_Base_Look_Registry( $storage );
		$registry->register( new Blueworx_Clubhouse_Court_Side() );
		$look       = $registry->active();
		$branding   = new Blueworx_Clubhouse_Branding( $storage );
		$visibility = new Blueworx_Clubhouse_Visibility( $storage );
		$cache      = new Blueworx_Clubhouse_Theme_Cache( $storage );
		return array( $look, $branding, $visibility, $cache );
	}

	public static function enqueue_assets(): void {
		if ( null === self::current_slug() ) {
			return;
		}
		list( $look, $branding, , $cache ) = self::context();
		if ( null === $look ) {
			return;
		}
		$specs = self::enqueue_specs(
			$look,
			$cache->root_css( $look, $branding ),
			BLUEWORX_LABS_CLUBHOUSE_URL
		);
		wp_enqueue_style( 'clubhouse-fonts', $specs['fonts_url'], array(), null );
		wp_enqueue_style( 'clubhouse-look', $specs['stylesheet_url'], array(), BLUEWORX_LABS_CLUBHOUSE_VERSION );
		wp_add_inline_style( 'clubhouse-look', $specs['inline_css'] );
		wp_enqueue_script( 'clubhouse-reveal', $specs['reveal_url'], array(), BLUEWORX_LABS_CLUBHOUSE_VERSION, true );
	}

	/** Render the current page body (used by the canvas template). */
	public static function render_body(): string {
		$slug = self::current_slug();
		if ( null === $slug ) {
			return '';
		}
		list( , $branding, $visibility, ) = self::context();
		return Blueworx_Clubhouse_Page_Map::render( $slug, $branding, $visibility );
	}

	public static function club_name(): string {
		list( , $branding, , ) = self::context();
		return $branding->get_club_name();
	}
```

- [ ] **Step 4: Create the canvas template**

```php
<?php
// templates/clubhouse.php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html( Blueworx_Clubhouse_Frontend::club_name() ); ?></title>
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
	<?php echo Blueworx_Clubhouse_Frontend::render_body(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Page_Renderer escapes all interpolated text. ?>
	<?php wp_footer(); ?>
</body>
</html>
```

- [ ] **Step 5: Boot `Frontend` from the main plugin file**

In `blueworx-labs-clubhouse.php`, add the require after the `bootstrap.php` require:

```php
require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/frontend/class-frontend.php';
```

Replace the empty `blueworx_labs_clubhouse_init()` body with:

```php
function blueworx_labs_clubhouse_init() {
	Blueworx_Clubhouse_Frontend::register();
}
add_action( 'plugins_loaded', 'blueworx_labs_clubhouse_init' );

register_activation_hook( __FILE__, static function () {
	Blueworx_Clubhouse_Frontend::register_rewrites();
	flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
```

(Remove the `require_once __DIR__ . '/../../includes/frontend/class-frontend.php';` line added to `tests/php/bootstrap.php` in Task 4 only if the plugin bootstrap now loads it — but the PHPUnit harness loads `includes/bootstrap.php`, not the main plugin file, so **keep** the test-bootstrap require so `Frontend` is available in tests.)

- [ ] **Step 6: Run test to verify wiring passes**

Run: `vendor/bin/phpunit --filter FrontendTest`
Expected: PASS (6 tests).

- [ ] **Step 7: PHP lint the template + classes**

Run: `composer lint`
Expected: clean. (If PHPCS flags the template's echo, the inline `phpcs:ignore` with justification covers it.)

- [ ] **Step 8: Run full suite**

Run: `vendor/bin/phpunit`
Expected: all green.

- [ ] **Step 9: Commit**

```bash
git add includes/frontend/class-frontend.php templates/clubhouse.php blueworx-labs-clubhouse.php tests/php/FrontendTest.php
git commit -m "feat: wire Frontend into WP (rewrite routing, canvas template, enqueue, activation)"
```

---

### Task 6: Version bump, changelog, deployment zip

**Files:**
- Modify: `blueworx-labs-clubhouse.php` (header `Version:` + `BLUEWORX_LABS_CLUBHOUSE_VERSION`), `package.json`, `CHANGELOG.md`

- [ ] **Step 1: Bump the version to 0.12.0**

In `blueworx-labs-clubhouse.php`: header `* Version:           0.12.0` and `define( 'BLUEWORX_LABS_CLUBHOUSE_VERSION', '0.12.0' );`.
In `package.json`: `"version": "0.12.0",`.

- [ ] **Step 2: Add the changelog entry** (above `## [0.11.0]`)

```markdown
## [0.12.0] - 2026-07-11

### WordPress integration

The plugin now serves its eight-page Court Side site inside WordPress — the first WordPress-runnable release.

#### New

- **Rewrite-rule routing.** The plugin owns the frontend: Home renders at the site root and each other page at `/{slug}` via rewrite rules, with the active theme left neutral. Flushed on activation/deactivation.
- **Canvas template + proper enqueue.** A `template_include` canvas template fires `wp_head()`/`wp_footer()`; the look stylesheet and fonts are enqueued and the derived `:root` design tokens are injected inline via `wp_add_inline_style`.
- **Cached `:root` tokens.** The composed token string is cached in an autoloaded option keyed by look + accent, so the colour math runs only when they change (`invalidate()` is exposed for the admin flow).
- **`Page_Map`** — a single slug→renderer dispatch used by both WordPress and the DB-free preview, so they render identical bodies. The scroll-reveal script is extracted to `assets/js/reveal.js` (enqueued in WP, inlined in the preview).

#### Notes

- Renderers still use hardcoded demo data; the Collections/CPT plan swaps the data source behind them.
- This branch also carries the CI-preview wiring (from PR #6) so its guardrails check passes ahead of that PR propagating through the stack.
```

- [ ] **Step 3: Run the full suite + lint one last time**

Run: `vendor/bin/phpunit && composer lint`
Expected: all green, PHPCS clean.

- [ ] **Step 4: Commit the bump**

```bash
git add blueworx-labs-clubhouse.php package.json CHANGELOG.md
git commit -m "chore: bump to 0.12.0 for WordPress integration"
```

- [ ] **Step 5: Produce the deployment zip**

The plugin is now WordPress-runnable, ending the "no zip yet" exception. From the plugin parent directory, remove any older `blueworx-labs-clubhouse*.zip`, then zip the plugin folder excluding dev-only paths (`node_modules`, `vendor`, `tests`, `docs`, `.git`, `.github`, `preview`, dotfiles, `composer.*`, `package*.json`, `phpunit*`, `phpcs*`, `playwright*`). Place the zip at `<plugin-parent-dir>/blueworx-labs-clubhouse.zip`. (Exact command decided at execution time to match the environment; the zip is the deployment artifact — never copy individual files.)

- [ ] **Step 6: Open the PR**

```bash
git push -u origin wp-integration
gh pr create --base sports-teams-events-calendar --head wp-integration --title "feat: WordPress integration (v0.12.0)" --body "<summary + merge choreography>"
```

## Self-Review

**Spec coverage:**
- Rewrite-rule routing → Task 5 (`register_rewrites`, `filter_template`). ✓
- Canvas template + `wp_head`/`wp_footer` → Task 5. ✓
- Enqueue fonts/stylesheet + inline cached `:root` → Task 5 (`enqueue_assets`) + Task 3 (cache). ✓
- Theme untouched (no `get_header`/`get_footer`) → Task 5 template. ✓
- Fresh activation renders demo (defaults + first-look fallback) → Task 5 activation, no seeding. ✓
- `Page_Map` single dispatch + preview refactor → Task 1. ✓
- `reveal.js` single source → Task 2. ✓
- No new deps; pure core WP-free; thin glue → Tasks 1–5 (glue only in `includes/frontend/`). ✓
- Testing via fakes + WP shim; Playwright preview unchanged → Tasks 1,3,4,5. ✓
- Version 0.12.0 + changelog + zip → Task 6. ✓
- Manual WP smoke — called out as environment-dependent in the spec; executed if a local WP is available, otherwise reported as not run.

**Placeholder scan:** none — every step carries concrete code/commands.

**Type consistency:** `resolve_slug(bool,mixed):?string`, `enqueue_specs(Base_Look,string,string):array`, `render(string,Branding,Visibility):string`, `root_css(Base_Look,Branding):string`, `QUERY_VAR='clubhouse_page'` — used consistently across Tasks 1–6.
