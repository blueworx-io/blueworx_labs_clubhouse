# Member Dashboard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** The plugin owns the member's account page — our frame, our nav, our
cards — with SureCart's and LatePoint's own output inside each panel.

**Architecture:** A `the_content` filter on the three pages SureCart seeds
(customer dashboard, checkout, order confirmation). On the dashboard it replaces
the content entirely with a shell of our own: a page head, a left nav of views,
and one panel holding whichever plugin owns that view's data. On checkout and
order confirmation it wraps the page's existing content in the same shell
without the nav. Which views exist is a pure, declarative list read by both the
nav and the router, so they cannot disagree. The look is the BlueWorx admin
design system, vendored under `assets/bw/` and already scoped to `.bw-admin` by
its own base stylesheet.

**Tech Stack:** PHP 8.1+, WordPress, PHPUnit 11, Playwright. No new Composer or
npm dependency.

**Spec:** `docs/superpowers/specs/2026-08-18-member-dashboard-design.md`

**Issue:** [#231](../../../../issues/231)

## Global Constraints

- Work on a branch, never `main`. One pull request. Branch name:
  `member-dashboard`.
- Version bumped and `CHANGELOG.md` updated in the same pull request — CI fails
  without both. This is a feature: **0.78.0 → 0.79.0**, in
  `blueworx-labs-clubhouse.php` (header `Version:` and
  `BLUEWORX_LABS_CLUBHOUSE_VERSION`) and in `package.json`.
- No new dependency without prior approval in `approved-deps.json`. Everything
  here is vendored static assets and plain PHP — add nothing.
- Changelog entries are written for a club owner, in their words, saying what
  changed for them. No file names, no class names.
- Every new class file starts with `declare(strict_types=1);` and the
  `if ( ! defined( 'ABSPATH' ) ) { exit; }` guard, matching every other file in
  `includes/`.
- Every new class is registered in `includes/bootstrap.php` in dependency order.
- All output escaped with `htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' )` via the
  class's own `e()` helper, the pattern `Welcome_Pack` and `Sections` use.
- Pure classes must not call WordPress functions directly. Where WordPress is
  needed, install a seam with a `set_*` static, exactly as
  `Blueworx_Clubhouse_Integrations::set_detector()` does. The DB-free preview and
  PHPUnit both run without WordPress loaded.
- `composer lint` (PHPCS) is run **once**, at the very end, and its findings are
  reported to Luke rather than fixed in a loop.
- Run `composer test` (PHPUnit) after every task. Run the browser suite against
  the real WordPress harness (`npm run wp:up`, then
  `PLAYWRIGHT_BASE_URL=http://localhost:8705 npx playwright test`) before opening
  the pull request — `@wordpress` specs do not run against the DB-free preview.
- Icons are inline SVG. The design draws Lucide icons loaded by a JavaScript
  module; shipping that module would be a new dependency and a script tag on a
  commerce page. The same six glyphs are inlined instead.

---

### Task 1: Vendor the BlueWorx admin design system

The design is drawn against a design system that lives in the Claude Design
project, not in this repo. Nothing can be built until its CSS and fonts are
files on disk.

**Files:**
- Create: `assets/bw/bw.css`
- Create: `assets/bw/README.md`
- Create: `assets/bw/fonts/sora-400.woff2`, `sora-600.woff2`, `sora-700.woff2`,
  `inter-400.woff2`, `inter-500.woff2`, `inter-600.woff2`
- Create: `includes/dashboard/class-dashboard-assets.php`
- Modify: `includes/bootstrap.php`
- Test: `tests/php/DashboardAssetsTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `Blueworx_Clubhouse_Dashboard_Assets::handle(): string`,
  `::relative_path(): string`, `::register(): void`, `::enqueue(): void`.

**Source of the assets.** Claude Design project
`0b906da5-c173-4d93-b806-f559a4baf924`, under the directory
`_ds/labs-wordpress-backend-components-75753ef2-9fb7-4c3a-ba0e-a7a0b967565d/`.
Read each file with the `DesignSync` tool, `method: "get_file"`, passing that
`projectId` and the full path.

- [ ] **Step 1: Fetch the stylesheets and concatenate them**

Fetch these files, in **this exact order** — it is the order
`styles.css` imports them, and the order matters because later files override
earlier ones:

```
tokens/fonts.css
tokens/colors.css
tokens/typography.css
tokens/spacing.css
tokens/radius.css
tokens/elevation.css
tokens/motion.css
tokens/semantic.css
components/core/core.css
components/navigation/navigation.css
components/forms/forms.css
components/forms/forms-extra.css
components/layout/layout.css
components/layout/layout-extra.css
components/feedback/feedback.css
components/feedback/feedback-extra.css
components/data/data.css
components/data/data-extra.css
tokens/base.css
```

Concatenate them into a single `assets/bw/bw.css`, in that order, each preceded
by a comment naming its source path. One file rather than nineteen `@import`s
because an imported stylesheet is a serialised round trip — nineteen of them on
a checkout page is a visible delay.

In the `tokens/fonts.css` portion, rewrite every `url("../assets/fonts/X.woff2")`
to `url("fonts/X.woff2")` — the vendored fonts sit beside the stylesheet, not two
directories up.

Do **not** vendor `styles.css` itself (it is only the list of imports),
`_ds_bundle.js`, `lucide-icons.js`, `dashicons.css`, or the Dashicons fonts.

Put this header at the top of the file:

```css
/* BlueWorx Admin Design System — vendored, do not hand-edit.
   Source: Claude Design project 0b906da5-c173-4d93-b806-f559a4baf924,
   _ds/labs-wordpress-backend-components-75753ef2-9fb7-4c3a-ba0e-a7a0b967565d/
   Concatenated in the order styles.css imports, with font urls rewritten to
   ./fonts/. Re-sync by re-fetching, never by editing this file.
   Everything here is scoped to .bw-admin or a .bw-* class, so it cannot reach
   markup that has not opted in. */
```

- [ ] **Step 2: Fetch the fonts**

Fetch the six `.woff2` files from `assets/fonts/` in the same design project and
write them to `assets/bw/fonts/`. They come back base64-encoded — decode before
writing. Do not fetch `dashicons.ttf` or `dashicons.woff2`; nothing here uses
them.

- [ ] **Step 3: Verify the vendored CSS carries what the shell will use**

Run this and confirm every one of these classes is found:

```bash
for c in bw-admin bw-page bw-page__body bw-pagehead bw-pagehead__titles \
         bw-pagehead__h1 bw-pagehead__lede bw-pagehead__actions bw-panels \
         bw-secnav bw-secnav__item bw-card bw-card__head bw-card__title \
         bw-card__body bw-btn bw-btn--primary bw-btn--secondary bw-empty \
         bw-empty__title bw-empty__text bw-empty__actions bw-icon; do
  grep -q "\.$c[^a-z0-9_-]" assets/bw/bw.css || echo "MISSING: $c"
done
```

Expected: no output. If anything is missing, it is defined only in the design
file's own inline styles — write the missing rule into a short
`/* Clubhouse additions */` block at the end of `assets/bw/bw.css`, matching the
surrounding token vocabulary, and say which rules you added in the pull request.

- [ ] **Step 4: Write `assets/bw/README.md`**

```markdown
# BlueWorx Admin Design System (vendored)

`bw.css` and `fonts/` are copied from the Claude Design project
`0b906da5-c173-4d93-b806-f559a4baf924`, directory
`_ds/labs-wordpress-backend-components-75753ef2-9fb7-4c3a-ba0e-a7a0b967565d/`.

Do not hand-edit them. To take an update, re-fetch every file listed in the
header comment of `bw.css`, concatenate in that order, and rewrite the font
urls to `./fonts/`.

This is the member area's look only. The club's public site is styled by
`assets/looks/`, and the two never meet: `bw.css` is enqueued on the member
area, checkout and order confirmation and nowhere else, and every rule in it is
scoped to `.bw-admin` or a `.bw-*` class.
```

- [ ] **Step 5: Write the failing test**

`tests/php/DashboardAssetsTest.php`:

```php
<?php

use PHPUnit\Framework\TestCase;

final class DashboardAssetsTest extends TestCase {

	private function root(): string {
		return dirname( __DIR__, 2 );
	}

	public function test_the_vendored_stylesheet_is_on_disk(): void {
		$this->assertFileExists( $this->root() . '/assets/bw/bw.css' );
	}

	public function test_the_fonts_it_asks_for_are_beside_it(): void {
		// A font url that resolves to nothing is a silent fallback to a system
		// face — the page still renders, so nothing else would ever catch it.
		$css = (string) file_get_contents( $this->root() . '/assets/bw/bw.css' );
		preg_match_all( '/url\(\s*["\']?([^"\')]+)["\']?\s*\)/', $css, $found );
		$this->assertNotSame( array(), $found[1], 'the stylesheet declares no fonts at all' );
		foreach ( $found[1] as $url ) {
			if ( str_starts_with( $url, 'data:' ) ) {
				continue;
			}
			$this->assertFileExists( $this->root() . '/assets/bw/' . $url );
		}
	}

	public function test_nothing_in_it_can_reach_markup_that_has_not_opted_in(): void {
		// The club's own pages must be untouchable from here. Every selector has
		// to be a .bw- class or sit under .bw-admin; a bare element selector
		// would restyle whatever page this is ever loaded on, including
		// SureCart's own controls inside our panels.
		$css = (string) file_get_contents( $this->root() . '/assets/bw/bw.css' );
		$css = (string) preg_replace( '#/\*.*?\*/#s', '', $css );
		$css = (string) preg_replace( '/@(font-face|media|supports|keyframes)[^{]*\{/', '{', $css );

		$offenders = array();
		foreach ( explode( '}', $css ) as $chunk ) {
			$brace = strpos( $chunk, '{' );
			if ( false === $brace ) {
				continue;
			}
			$selectors = substr( $chunk, 0, $brace );
			foreach ( explode( ',', $selectors ) as $selector ) {
				$selector = trim( $selector );
				if ( '' === $selector || str_starts_with( $selector, '@' ) ) {
					continue;
				}
				if ( ':root' === $selector || str_starts_with( $selector, '.bw-' ) ) {
					continue;
				}
				$offenders[] = $selector;
			}
		}
		$this->assertSame( array(), array_unique( $offenders ), 'unscoped selectors in the vendored stylesheet' );
	}

	public function test_the_handle_and_path_agree(): void {
		$this->assertSame( 'blueworx-clubhouse-bw', Blueworx_Clubhouse_Dashboard_Assets::handle() );
		$this->assertSame( 'assets/bw/bw.css', Blueworx_Clubhouse_Dashboard_Assets::relative_path() );
		$this->assertFileExists( $this->root() . '/' . Blueworx_Clubhouse_Dashboard_Assets::relative_path() );
	}
}
```

- [ ] **Step 6: Run the test to verify it fails**

Run: `composer test -- --filter DashboardAssetsTest`
Expected: FAIL — `Class "Blueworx_Clubhouse_Dashboard_Assets" not found`.

If instead it fails on `test_nothing_in_it_can_reach_markup_that_has_not_opted_in`,
the vendored file has a bare element selector. Do not weaken the test: find the
rule, and either drop that portion (if it is a global reset the member area does
not need) or scope it under `.bw-admin`. Record what you changed in
`assets/bw/README.md`.

- [ ] **Step 7: Write the implementation**

`includes/dashboard/class-dashboard-assets.php`:

```php
<?php
// includes/dashboard/class-dashboard-assets.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The member area's stylesheet: the BlueWorx admin design system, vendored.
 *
 * Loaded on the three pages this plugin takes over and nowhere else. The club's
 * public site is styled by assets/looks/ and the two systems never meet — see
 * assets/bw/README.md.
 *
 * Registered rather than enqueued at load: whether a request is one of ours is
 * decided per request by Member_Dashboard, which calls enqueue() once it knows.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Dashboard_Assets {

	private const HANDLE = 'blueworx-clubhouse-bw';
	private const PATH   = 'assets/bw/bw.css';

	public static function handle(): string {
		return self::HANDLE;
	}

	/** Where the stylesheet is, relative to the plugin root. */
	public static function relative_path(): string {
		return self::PATH;
	}

	public static function register(): void {
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}
		add_action( 'wp_enqueue_scripts', array( self::class, 'declare_style' ) );
	}

	/**
	 * Tell WordPress the stylesheet exists. Nothing is put on the page here —
	 * enqueue() does that, and only for a request we are rendering.
	 */
	public static function declare_style(): void {
		if ( ! function_exists( 'wp_register_style' ) || ! defined( 'BLUEWORX_LABS_CLUBHOUSE_URL' ) ) {
			return;
		}
		wp_register_style(
			self::HANDLE,
			BLUEWORX_LABS_CLUBHOUSE_URL . self::PATH,
			array(),
			defined( 'BLUEWORX_LABS_CLUBHOUSE_VERSION' ) ? BLUEWORX_LABS_CLUBHOUSE_VERSION : null
		);
	}

	/** Put it on this page. Safe to call more than once. */
	public static function enqueue(): void {
		if ( function_exists( 'wp_enqueue_style' ) ) {
			wp_enqueue_style( self::HANDLE );
		}
	}
}
```

Check the constant name for the plugin URL before writing this — read
`blueworx-labs-clubhouse.php` and use whatever it actually defines (it defines
`BLUEWORX_LABS_CLUBHOUSE_DIR`; if there is no URL constant, add
`define( 'BLUEWORX_LABS_CLUBHOUSE_URL', plugin_dir_url( __FILE__ ) );` beside it
and use that).

- [ ] **Step 8: Register it in the loader**

In `includes/bootstrap.php`, after the `// Membership` block, add:

```php
// Member area. Pure first, then the page that uses them.
require_once __DIR__ . '/dashboard/class-dashboard-assets.php';
```

- [ ] **Step 9: Run the tests**

Run: `composer test -- --filter DashboardAssetsTest`
Expected: PASS, 4 tests.

Then run the whole suite: `composer test`
Expected: PASS — nothing else has changed yet.

- [ ] **Step 10: Commit**

```bash
git checkout -b member-dashboard
git add assets/bw includes/dashboard/class-dashboard-assets.php includes/bootstrap.php tests/php/DashboardAssetsTest.php
git commit -m "Vendor the BlueWorx admin design system for the member area"
```

---

### Task 2: The list of views

The single declarative source the nav and the router both read. Pure — no
WordPress, no plugin detection, just the rules.

**Files:**
- Create: `includes/dashboard/class-dashboard-views.php`
- Modify: `includes/bootstrap.php`
- Test: `tests/php/DashboardViewsTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `Blueworx_Clubhouse_Dashboard_Views::all(): array<int,array{key:string,label:string,title:string,lede:string,icon:string,requires:string,blocks:array<int,string>,shortcode:string}>`
  - `::available( bool $has_surecart, bool $has_latepoint ): array<int,array<string,mixed>>`
  - `::resolve( string $requested, array $available ): string`
  - `::find( string $key, array $views ): ?array<string,mixed>`
  - Constants `Blueworx_Clubhouse_Dashboard_Views::DEFAULT_VIEW = 'dashboard'`,
    `::NEEDS_SURECART = 'surecart'`, `::NEEDS_LATEPOINT = 'latepoint'`.

- [ ] **Step 1: Write the failing test**

`tests/php/DashboardViewsTest.php`:

```php
<?php

use PHPUnit\Framework\TestCase;

final class DashboardViewsTest extends TestCase {

	/** @return array<int,string> */
	private function keys( array $views ): array {
		return array_column( $views, 'key' );
	}

	public function test_the_views_are_in_the_order_the_design_draws_them(): void {
		$this->assertSame(
			array( 'dashboard', 'bookings', 'orders', 'invoices', 'plans', 'account' ),
			$this->keys( Blueworx_Clubhouse_Dashboard_Views::all() )
		);
	}

	public function test_every_view_is_fully_described(): void {
		foreach ( Blueworx_Clubhouse_Dashboard_Views::all() as $view ) {
			$this->assertNotSame( '', $view['label'], 'a nav item with no label cannot be clicked' );
			$this->assertNotSame( '', $view['title'] );
			$this->assertNotSame( '', $view['lede'] );
			$this->assertNotSame( '', $view['icon'] );
			$this->assertIsArray( $view['blocks'] );
			$this->assertIsString( $view['shortcode'] );
		}
	}

	public function test_a_club_with_both_plugins_gets_every_view(): void {
		$views = Blueworx_Clubhouse_Dashboard_Views::available( true, true );
		$this->assertSame(
			array( 'dashboard', 'bookings', 'orders', 'invoices', 'plans', 'account' ),
			$this->keys( $views )
		);
	}

	public function test_a_club_with_no_shop_is_not_offered_shop_views(): void {
		// A nav item that cannot render is worse than an absent one.
		$views = Blueworx_Clubhouse_Dashboard_Views::available( false, true );
		$this->assertSame( array( 'dashboard', 'bookings' ), $this->keys( $views ) );
	}

	public function test_a_club_with_no_bookings_is_not_offered_bookings(): void {
		$views = Blueworx_Clubhouse_Dashboard_Views::available( true, false );
		$this->assertNotContains( 'bookings', $this->keys( $views ) );
	}

	public function test_a_club_with_neither_still_has_somewhere_to_land(): void {
		// The welcome pack lives here, and a club that has not set up a shop
		// should still have a member area that greets a member.
		$views = Blueworx_Clubhouse_Dashboard_Views::available( false, false );
		$this->assertSame( array( 'dashboard' ), $this->keys( $views ) );
	}

	public function test_an_address_naming_a_real_view_lands_on_it(): void {
		$views = Blueworx_Clubhouse_Dashboard_Views::available( true, true );
		$this->assertSame( 'invoices', Blueworx_Clubhouse_Dashboard_Views::resolve( 'invoices', $views ) );
	}

	public function test_an_empty_address_lands_on_the_dashboard(): void {
		$views = Blueworx_Clubhouse_Dashboard_Views::available( true, true );
		$this->assertSame( 'dashboard', Blueworx_Clubhouse_Dashboard_Views::resolve( '', $views ) );
	}

	public function test_a_made_up_address_lands_on_the_dashboard(): void {
		// Rather than an empty frame, or a fatal from a missing key.
		$views = Blueworx_Clubhouse_Dashboard_Views::available( true, true );
		$this->assertSame( 'dashboard', Blueworx_Clubhouse_Dashboard_Views::resolve( 'nonsense', $views ) );
		$this->assertSame( 'dashboard', Blueworx_Clubhouse_Dashboard_Views::resolve( '../../etc/passwd', $views ) );
	}

	public function test_an_address_for_a_view_this_club_does_not_have_lands_on_the_dashboard(): void {
		// A bookmark kept from before the club removed a plugin.
		$views = Blueworx_Clubhouse_Dashboard_Views::available( false, false );
		$this->assertSame( 'dashboard', Blueworx_Clubhouse_Dashboard_Views::resolve( 'orders', $views ) );
	}

	public function test_find_returns_the_named_view_and_null_for_anything_else(): void {
		$views = Blueworx_Clubhouse_Dashboard_Views::available( true, true );
		$found = Blueworx_Clubhouse_Dashboard_Views::find( 'plans', $views );
		$this->assertIsArray( $found );
		$this->assertSame( 'Plans', $found['label'] );
		$this->assertNull( Blueworx_Clubhouse_Dashboard_Views::find( 'nope', $views ) );
	}

	public function test_bookings_is_the_one_view_a_shortcode_fills(): void {
		$views = Blueworx_Clubhouse_Dashboard_Views::available( true, true );
		$bookings = Blueworx_Clubhouse_Dashboard_Views::find( 'bookings', $views );
		$this->assertSame( 'latepoint_customer_dashboard', $bookings['shortcode'] );
		$this->assertSame( array(), $bookings['blocks'] );
	}

	public function test_account_holds_both_of_the_shops_account_panels(): void {
		$views   = Blueworx_Clubhouse_Dashboard_Views::available( true, true );
		$account = Blueworx_Clubhouse_Dashboard_Views::find( 'account', $views );
		$this->assertSame(
			array( 'surecart/customer-billing-details', 'surecart/customer-payment-methods' ),
			$account['blocks']
		);
	}
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `composer test -- --filter DashboardViewsTest`
Expected: FAIL — `Class "Blueworx_Clubhouse_Dashboard_Views" not found`.

- [ ] **Step 3: Write the implementation**

`includes/dashboard/class-dashboard-views.php`:

```php
<?php
// includes/dashboard/class-dashboard-views.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What the member area is made of: the views, in order, and what each needs.
 *
 * One declarative list because three things have to agree — the left nav, the
 * router that decides which view an address means, and the panel that renders
 * it. Two lists is how a nav item comes to point at a view that does not exist.
 *
 * Pure. Which plugins a site has is answered by Integrations and handed in.
 *
 * The block names are SureCart's own, read from its source: its customer
 * dashboard is composed of these separate blocks rather than one, which is what
 * makes one block per panel possible. customer-downloads and customer-licenses
 * are deliberately absent — no club sells either, and an empty panel on every
 * account page is a cost with no reader.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Dashboard_Views {

	/** Where a member lands with no view named, and the fallback for anything unrecognised. */
	public const DEFAULT_VIEW = 'dashboard';

	public const NEEDS_SURECART  = 'surecart';
	public const NEEDS_LATEPOINT = 'latepoint';

	/**
	 * Every view this plugin knows how to draw, in the order the nav shows them.
	 *
	 * 'icon' names the glyph Dashboard_Shell draws. 'blocks' are rendered in
	 * order, each in its own card. 'shortcode' takes the whole view — LatePoint
	 * brings its own tabs and does not belong boxed inside a card.
	 *
	 * @return array<int,array{key:string,label:string,title:string,lede:string,icon:string,requires:string,blocks:array<int,string>,shortcode:string}>
	 */
	public static function all(): array {
		return array(
			array(
				'key'       => 'dashboard',
				'label'     => 'Dashboard',
				'title'     => 'Your account',
				'lede'      => 'Everything the club keeps for you, in one place.',
				'icon'      => 'layout-dashboard',
				'requires'  => '',
				'blocks'    => array(),
				'shortcode' => '',
			),
			array(
				'key'       => 'bookings',
				'label'     => 'Bookings',
				'title'     => 'Bookings',
				'lede'      => 'What you have booked, and anything coming up.',
				'icon'      => 'calendar',
				'requires'  => self::NEEDS_LATEPOINT,
				'blocks'    => array(),
				'shortcode' => 'latepoint_customer_dashboard',
			),
			array(
				'key'       => 'orders',
				'label'     => 'Orders',
				'title'     => 'Orders',
				'lede'      => 'Everything you have bought from the club.',
				'icon'      => 'shopping-cart',
				'requires'  => self::NEEDS_SURECART,
				'blocks'    => array( 'surecart/customer-orders' ),
				'shortcode' => '',
			),
			array(
				'key'       => 'invoices',
				'label'     => 'Invoices',
				'title'     => 'Invoices',
				'lede'      => 'Your receipts, and anything still to pay.',
				'icon'      => 'file-spreadsheet',
				'requires'  => self::NEEDS_SURECART,
				'blocks'    => array( 'surecart/customer-invoices' ),
				'shortcode' => '',
			),
			array(
				'key'       => 'plans',
				'label'     => 'Plans',
				'title'     => 'Your membership',
				'lede'      => 'What you pay, how often, and when it renews.',
				'icon'      => 'refresh-cw',
				'requires'  => self::NEEDS_SURECART,
				'blocks'    => array( 'surecart/customer-subscriptions' ),
				'shortcode' => '',
			),
			array(
				'key'       => 'account',
				'label'     => 'Account',
				'title'     => 'Account details',
				'lede'      => 'Your details and how you pay.',
				'icon'      => 'users',
				'requires'  => self::NEEDS_SURECART,
				'blocks'    => array( 'surecart/customer-billing-details', 'surecart/customer-payment-methods' ),
				'shortcode' => '',
			),
		);
	}

	/**
	 * The views this club can actually offer.
	 *
	 * A view whose plugin is absent is not offered at all — see the spec. It is
	 * not hidden behind a message, because there is nothing a member could do
	 * about it and nothing the club needs to be told twice.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function available( bool $has_surecart, bool $has_latepoint ): array {
		$out = array();
		foreach ( self::all() as $view ) {
			$needs = (string) $view['requires'];
			if ( self::NEEDS_SURECART === $needs && ! $has_surecart ) {
				continue;
			}
			if ( self::NEEDS_LATEPOINT === $needs && ! $has_latepoint ) {
				continue;
			}
			$out[] = $view;
		}
		return $out;
	}

	/**
	 * Which view an address means. Anything not on the list — junk, a typo, a
	 * bookmark from before a plugin was removed — lands on the dashboard, which
	 * always exists.
	 *
	 * @param array<int,array<string,mixed>> $available From available().
	 */
	public static function resolve( string $requested, array $available ): string {
		$requested = trim( $requested );
		foreach ( $available as $view ) {
			if ( $requested === (string) $view['key'] ) {
				return $requested;
			}
		}
		return self::DEFAULT_VIEW;
	}

	/**
	 * One view by key, or null.
	 *
	 * @param array<int,array<string,mixed>> $views
	 * @return array<string,mixed>|null
	 */
	public static function find( string $key, array $views ): ?array {
		foreach ( $views as $view ) {
			if ( $key === (string) $view['key'] ) {
				return $view;
			}
		}
		return null;
	}
}
```

- [ ] **Step 4: Register it in the loader**

In `includes/bootstrap.php`, in the member area block added in Task 1, above
`class-dashboard-assets.php`:

```php
require_once __DIR__ . '/dashboard/class-dashboard-views.php';
```

- [ ] **Step 5: Run the tests**

Run: `composer test -- --filter DashboardViewsTest`
Expected: PASS, 13 tests.

- [ ] **Step 6: Commit**

```bash
git add includes/dashboard/class-dashboard-views.php includes/bootstrap.php tests/php/DashboardViewsTest.php
git commit -m "Describe the member area's views in one place"
```

---

### Task 3: The plugin slot

One place that knows how to render another plugin's block or shortcode, and
returns nothing rather than something broken when the plugin is not there.

**Files:**
- Create: `includes/dashboard/class-plugin-slot.php`
- Modify: `includes/bootstrap.php`
- Test: `tests/php/PluginSlotTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `Blueworx_Clubhouse_Plugin_Slot::set_sources( ?callable $blocks, ?callable $shortcodes ): void` — each callable takes a string name and returns `?string`: the rendered markup, or `null` when that plugin is not providing it.
  - `::block( string $name ): string`
  - `::shortcode( string $tag ): string`
  - `::install_wordpress(): void`

- [ ] **Step 1: Write the failing test**

`tests/php/PluginSlotTest.php`:

```php
<?php

use PHPUnit\Framework\TestCase;

final class PluginSlotTest extends TestCase {

	protected function tearDown(): void {
		Blueworx_Clubhouse_Plugin_Slot::set_sources( null, null );
	}

	public function test_with_nothing_installed_a_slot_renders_nothing(): void {
		// The default, and the safe way round: an environment that has not opted
		// in shows no third-party panels at all rather than broken ones.
		$this->assertSame( '', Blueworx_Clubhouse_Plugin_Slot::block( 'surecart/customer-orders' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Plugin_Slot::shortcode( 'latepoint_customer_dashboard' ) );
	}

	public function test_a_block_the_shop_provides_is_rendered(): void {
		Blueworx_Clubhouse_Plugin_Slot::set_sources(
			static fn ( string $name ): ?string => 'surecart/customer-orders' === $name ? '<div>orders</div>' : null,
			null
		);
		$this->assertSame( '<div>orders</div>', Blueworx_Clubhouse_Plugin_Slot::block( 'surecart/customer-orders' ) );
	}

	public function test_a_block_the_shop_does_not_provide_renders_nothing(): void {
		Blueworx_Clubhouse_Plugin_Slot::set_sources(
			static fn ( string $name ): ?string => 'surecart/customer-orders' === $name ? '<div>orders</div>' : null,
			null
		);
		$this->assertSame( '', Blueworx_Clubhouse_Plugin_Slot::block( 'surecart/customer-licenses' ) );
	}

	public function test_a_shortcode_that_is_registered_is_rendered(): void {
		Blueworx_Clubhouse_Plugin_Slot::set_sources(
			null,
			static fn ( string $tag ): ?string => 'latepoint_customer_dashboard' === $tag ? '<div>bookings</div>' : null
		);
		$this->assertSame( '<div>bookings</div>', Blueworx_Clubhouse_Plugin_Slot::shortcode( 'latepoint_customer_dashboard' ) );
	}

	public function test_a_plugin_that_renders_only_whitespace_counts_as_nothing(): void {
		// A registered block that returns an empty string is a plugin with
		// nothing to show. The caller must be able to tell that from real
		// output, or it draws an empty card.
		Blueworx_Clubhouse_Plugin_Slot::set_sources( static fn ( string $n ): ?string => "  \n ", null );
		$this->assertSame( '', Blueworx_Clubhouse_Plugin_Slot::block( 'surecart/customer-orders' ) );
	}

	public function test_a_plugin_that_throws_does_not_take_the_page_down(): void {
		// Another plugin's panel failing must cost that panel, not the member's
		// whole account page.
		Blueworx_Clubhouse_Plugin_Slot::set_sources(
			static function ( string $n ): ?string {
				throw new RuntimeException( 'boom' );
			},
			null
		);
		$this->assertSame( '', Blueworx_Clubhouse_Plugin_Slot::block( 'surecart/customer-orders' ) );
	}
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `composer test -- --filter PluginSlotTest`
Expected: FAIL — `Class "Blueworx_Clubhouse_Plugin_Slot" not found`.

- [ ] **Step 3: Write the implementation**

`includes/dashboard/class-plugin-slot.php`:

```php
<?php
// includes/dashboard/class-plugin-slot.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders another plugin's output — a block or a shortcode — or nothing at all.
 *
 * The one place that knows do_blocks() and do_shortcode() exist, so no view has
 * to think about whether SureCart or LatePoint is installed. A slot with nobody
 * to fill it answers '', and the caller draws its honest empty state instead.
 *
 * A seam, like Integrations: the rules are pure and unit-tested, and WordPress
 * installs the real renderers at boot. The default is "nothing is installed",
 * which is the safe way round — a missing panel is obvious and recoverable, a
 * broken one is neither.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Plugin_Slot {

	/** @var (callable(string):?string)|null */
	private static $blocks = null;

	/** @var (callable(string):?string)|null */
	private static $shortcodes = null;

	/**
	 * @param (callable(string):?string)|null $blocks     Returns markup, or null when unregistered.
	 * @param (callable(string):?string)|null $shortcodes Same, for shortcode tags.
	 */
	public static function set_sources( ?callable $blocks, ?callable $shortcodes ): void {
		self::$blocks     = $blocks;
		self::$shortcodes = $shortcodes;
	}

	/** One block's rendered output, or '' when the plugin providing it is absent. */
	public static function block( string $name ): string {
		return self::render( self::$blocks, $name );
	}

	/** One shortcode's rendered output, or '' when it is not registered. */
	public static function shortcode( string $tag ): string {
		return self::render( self::$shortcodes, $tag );
	}

	/**
	 * @param (callable(string):?string)|null $source
	 */
	private static function render( ?callable $source, string $name ): string {
		if ( null === $source ) {
			return '';
		}
		try {
			$out = $source( $name );
		} catch ( \Throwable $e ) {
			// One panel failing must not take the member's account page with it.
			return '';
		}
		if ( ! is_string( $out ) || '' === trim( $out ) ) {
			return '';
		}
		return $out;
	}

	/**
	 * Wire the real WordPress renderers.
	 *
	 * A block is asked for by name and rendered from a block comment, which is
	 * how WordPress renders a dynamic block outside the editor. It is only asked
	 * for when the registry says it exists, so an uninstalled plugin leaves the
	 * comment unrendered rather than printing it.
	 *
	 * A shortcode is checked the same way Integrations checks one — by tag,
	 * because the question is "will this render?", not "is a directory there?".
	 */
	public static function install_wordpress(): void {
		self::set_sources(
			static function ( string $name ): ?string {
				if ( ! class_exists( 'WP_Block_Type_Registry' ) || ! function_exists( 'do_blocks' ) ) {
					return null;
				}
				if ( ! WP_Block_Type_Registry::get_instance()->is_registered( $name ) ) {
					return null;
				}
				return do_blocks( '<!-- wp:' . $name . ' /-->' );
			},
			static function ( string $tag ): ?string {
				if ( ! function_exists( 'shortcode_exists' ) || ! function_exists( 'do_shortcode' ) ) {
					return null;
				}
				if ( ! shortcode_exists( $tag ) ) {
					return null;
				}
				return do_shortcode( '[' . $tag . ']' );
			}
		);
	}
}
```

- [ ] **Step 4: Register it in the loader**

In `includes/bootstrap.php`, in the member area block, after
`class-dashboard-views.php`:

```php
require_once __DIR__ . '/dashboard/class-plugin-slot.php';
```

- [ ] **Step 5: Run the tests**

Run: `composer test -- --filter PluginSlotTest`
Expected: PASS, 6 tests.

- [ ] **Step 6: Commit**

```bash
git add includes/dashboard/class-plugin-slot.php includes/bootstrap.php tests/php/PluginSlotTest.php
git commit -m "Render another plugin's panel, or nothing at all"
```

---

### Task 4: The shell

The markup: page head, left nav, cards. Pure and escaped, in the same way
`Sections` is, so every rule about what the page looks like is testable without
WordPress.

**Files:**
- Create: `includes/render/class-dashboard-shell.php`
- Modify: `includes/bootstrap.php`
- Test: `tests/php/DashboardShellTest.php`

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Dashboard_Views` (for the view arrays passed in).
- Produces:
  - `Blueworx_Clubhouse_Dashboard_Shell::page( array $views, string $current, string $title, string $lede, string $body, string $home_url, string $club_name ): string`
  - `::bare( string $title, string $lede, string $body, string $home_url, string $club_name ): string`
  - `::card( string $title, string $body ): string`
  - `::empty_state( string $title, string $text, string $href, string $label ): string`
  - `::icon( string $name ): string`
  - `::view_url( string $key ): string`

- [ ] **Step 1: Write the failing test**

`tests/php/DashboardShellTest.php`:

```php
<?php

use PHPUnit\Framework\TestCase;

final class DashboardShellTest extends TestCase {

	/** @return array<int,array<string,mixed>> */
	private function views(): array {
		return Blueworx_Clubhouse_Dashboard_Views::available( true, true );
	}

	private function page( string $current = 'dashboard', string $body = '<p>hello</p>' ): string {
		return Blueworx_Clubhouse_Dashboard_Shell::page(
			$this->views(),
			$current,
			'Your account',
			'Everything the club keeps for you.',
			$body,
			'https://club.test/',
			'Crewe Vagrants'
		);
	}

	public function test_the_page_opts_in_to_the_member_area_look(): void {
		// Every rule in the vendored stylesheet is scoped to .bw-admin; without
		// this class the page renders as bare theme output.
		$this->assertStringContainsString( 'bw-admin', $this->page() );
	}

	public function test_every_available_view_is_a_link_in_the_nav(): void {
		$html = $this->page();
		foreach ( $this->views() as $view ) {
			$this->assertStringContainsString( '?view=' . $view['key'], $html, $view['key'] . ' is not reachable' );
			$this->assertStringContainsString( '>' . $view['label'] . '<', $html );
		}
	}

	public function test_the_nav_is_links_not_buttons(): void {
		// Every view is its own address, so each has to be linkable, openable in
		// a new tab and reachable without JavaScript.
		$this->assertMatchesRegularExpression( '/<a[^>]*class="bw-secnav__item[^"]*"[^>]*href="\?view=orders"/', $this->page() );
	}

	public function test_the_view_being_read_is_the_one_marked_current(): void {
		$html = $this->page( 'invoices' );
		$this->assertMatchesRegularExpression( '/href="\?view=invoices"[^>]*aria-current="page"/', $html );
		$this->assertSame( 1, substr_count( $html, 'aria-current="page"' ), 'exactly one nav item is current' );
		$this->assertSame( 1, substr_count( $html, 'is-active' ) );
	}

	public function test_a_club_without_a_shop_has_no_dead_nav_items(): void {
		$html = Blueworx_Clubhouse_Dashboard_Shell::page(
			Blueworx_Clubhouse_Dashboard_Views::available( false, false ),
			'dashboard',
			'Your account',
			'',
			'<p>hello</p>',
			'https://club.test/',
			'Crewe Vagrants'
		);
		$this->assertStringNotContainsString( '?view=orders', $html );
		$this->assertStringNotContainsString( '?view=bookings', $html );
		$this->assertStringContainsString( '?view=dashboard', $html );
	}

	public function test_the_body_is_placed_as_given(): void {
		$this->assertStringContainsString( '<p>hello</p>', $this->page() );
	}

	public function test_there_is_a_way_back_to_the_club(): void {
		// A member area with no exit is a trap; the theme around this page has
		// no header of its own.
		$this->assertStringContainsString( 'href="https://club.test/"', $this->page() );
	}

	public function test_the_club_name_is_shown_and_escaped(): void {
		$html = Blueworx_Clubhouse_Dashboard_Shell::page(
			$this->views(),
			'dashboard',
			'T',
			'L',
			'B',
			'https://club.test/',
			'Bill & Ben\'s <script>'
		);
		$this->assertStringContainsString( 'Bill &amp; Ben&#039;s &lt;script&gt;', $html );
		$this->assertStringNotContainsString( '<script>', $html );
	}

	public function test_the_title_and_lede_are_escaped(): void {
		$html = Blueworx_Clubhouse_Dashboard_Shell::page(
			$this->views(),
			'dashboard',
			'<b>T</b>',
			'<i>L</i>',
			'B',
			'https://club.test/',
			'Club'
		);
		$this->assertStringContainsString( '&lt;b&gt;T&lt;/b&gt;', $html );
		$this->assertStringContainsString( '&lt;i&gt;L&lt;/i&gt;', $html );
	}

	public function test_the_way_home_is_escaped(): void {
		$html = Blueworx_Clubhouse_Dashboard_Shell::page(
			$this->views(),
			'dashboard',
			'T',
			'L',
			'B',
			'" onmouseover="alert(1)',
			'Club'
		);
		$this->assertStringNotContainsString( 'onmouseover=', $html );
	}

	public function test_the_lede_is_left_out_when_there_is_none(): void {
		$html = Blueworx_Clubhouse_Dashboard_Shell::page( $this->views(), 'dashboard', 'T', '', 'B', '/', 'Club' );
		$this->assertStringNotContainsString( 'bw-pagehead__lede', $html );
	}

	public function test_the_bare_shell_has_the_look_but_no_nav(): void {
		// Checkout: a member mid-purchase should not be offered six places to
		// wander off to.
		$html = Blueworx_Clubhouse_Dashboard_Shell::bare( 'Checkout', 'Nearly there.', '<form></form>', 'https://club.test/', 'Club' );
		$this->assertStringContainsString( 'bw-admin', $html );
		$this->assertStringContainsString( '<form></form>', $html );
		$this->assertStringNotContainsString( 'bw-secnav', $html );
		$this->assertStringNotContainsString( '?view=', $html );
	}

	public function test_a_card_carries_its_title_and_its_body(): void {
		$html = Blueworx_Clubhouse_Dashboard_Shell::card( 'Orders', '<table></table>' );
		$this->assertStringContainsString( 'bw-card', $html );
		$this->assertStringContainsString( 'Orders', $html );
		$this->assertStringContainsString( '<table></table>', $html );
	}

	public function test_a_card_with_no_title_has_no_head(): void {
		$html = Blueworx_Clubhouse_Dashboard_Shell::card( '', '<table></table>' );
		$this->assertStringNotContainsString( 'bw-card__head', $html );
		$this->assertStringContainsString( '<table></table>', $html );
	}

	public function test_an_empty_state_says_what_is_missing_and_offers_the_way_out(): void {
		// Never a blank frame: a member who reaches a view the club has not set
		// up is told plainly, and given the way back.
		$html = Blueworx_Clubhouse_Dashboard_Shell::empty_state(
			'Nothing here yet',
			'The club has not set this part up.',
			'https://club.test/',
			'Back to the club'
		);
		$this->assertStringContainsString( 'Nothing here yet', $html );
		$this->assertStringContainsString( 'The club has not set this part up.', $html );
		$this->assertStringContainsString( 'href="https://club.test/"', $html );
		$this->assertStringContainsString( 'Back to the club', $html );
	}

	public function test_every_view_has_a_glyph_and_an_unknown_name_draws_none(): void {
		foreach ( Blueworx_Clubhouse_Dashboard_Views::all() as $view ) {
			$this->assertStringContainsString(
				'<svg',
				Blueworx_Clubhouse_Dashboard_Shell::icon( (string) $view['icon'] ),
				$view['icon'] . ' has no glyph'
			);
		}
		$this->assertSame( '', Blueworx_Clubhouse_Dashboard_Shell::icon( 'no-such-icon' ) );
	}

	public function test_the_shell_emits_no_club_look_classes(): void {
		// The two design systems never meet. A ch-* class here would arrive
		// unstyled, because none of assets/looks/ is loaded on this page.
		$this->assertDoesNotMatchRegularExpression( '/class="[^"]*\bch-/', $this->page() );
	}
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `composer test -- --filter DashboardShellTest`
Expected: FAIL — `Class "Blueworx_Clubhouse_Dashboard_Shell" not found`.

- [ ] **Step 3: Write the implementation**

`includes/render/class-dashboard-shell.php`:

```php
<?php
// includes/render/class-dashboard-shell.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The member area's markup: page head, left nav, cards.
 *
 * Pure and escaped, in the same way Sections is — every rule about what this
 * page looks like is decided here and testable without WordPress or a shop.
 *
 * The classes are the BlueWorx admin design system's, not the club look's. The
 * two never meet: assets/looks/ is not loaded on this page, and every rule in
 * assets/bw/bw.css is scoped to .bw-admin, which only this markup carries.
 *
 * The nav is links rather than buttons because each view is its own address —
 * openable in a new tab, bookmarkable, and working with no JavaScript at all.
 *
 * Icons are inline SVG rather than an icon font or a script. The design draws
 * Lucide glyphs loaded by a JavaScript module; six paths inlined here cost
 * nothing and put no script on a page where a member is paying.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Dashboard_Shell {

	/** The query argument each view is addressed by. */
	public const VIEW_ARG = 'view';

	private static function e( string $v ): string {
		return htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' );
	}

	/** The address of one view, relative to the page the member area is on. */
	public static function view_url( string $key ): string {
		return '?' . self::VIEW_ARG . '=' . rawurlencode( $key );
	}

	/**
	 * The whole member area: nav down the side, one view in the middle.
	 *
	 * @param array<int,array<string,mixed>> $views   From Dashboard_Views::available().
	 * @param string                         $current The key of the view being read.
	 * @param string                         $body    Already-rendered markup for that view.
	 */
	public static function page( array $views, string $current, string $title, string $lede, string $body, string $home_url, string $club_name ): string {
		return '<div class="bw-admin bw-page clubhouse-member">'
			. self::head( $title, $lede, $home_url, $club_name )
			. '<div class="bw-page__body">'
			. self::nav( $views, $current )
			. '<main class="bw-panels" id="clubhouse-member-view">' . $body . '</main>'
			. '</div></div>';
	}

	/**
	 * The same look with no nav — checkout and order confirmation.
	 *
	 * A member on the checkout page is mid-purchase and should not be offered
	 * six places to wander off to.
	 */
	public static function bare( string $title, string $lede, string $body, string $home_url, string $club_name ): string {
		return '<div class="bw-admin bw-page clubhouse-member">'
			. self::head( $title, $lede, $home_url, $club_name )
			. '<div class="bw-page__body">'
			. '<main class="bw-panels">' . $body . '</main>'
			. '</div></div>';
	}

	private static function head( string $title, string $lede, string $home_url, string $club_name ): string {
		$out = '<header class="bw-pagehead"><div class="bw-pagehead__titles">';
		if ( '' !== trim( $club_name ) ) {
			$out .= '<p class="bw-pagehead__eyebrow">' . self::e( $club_name ) . '</p>';
		}
		$out .= '<h1 class="bw-pagehead__h1">' . self::e( $title ) . '</h1>';
		if ( '' !== trim( $lede ) ) {
			$out .= '<p class="bw-pagehead__lede">' . self::e( $lede ) . '</p>';
		}
		$out .= '</div><div class="bw-pagehead__actions">'
			. '<a class="bw-btn bw-btn--secondary" href="' . self::e( $home_url ) . '">'
			. self::icon( 'arrow-left' ) . 'Back to the club site</a>'
			. '</div></header>';
		return $out;
	}

	/**
	 * @param array<int,array<string,mixed>> $views
	 */
	private static function nav( array $views, string $current ): string {
		$out = '<nav class="bw-secnav" aria-label="Your account">';
		foreach ( $views as $view ) {
			$key    = (string) $view['key'];
			$active = $key === $current;
			$out   .= '<a class="bw-secnav__item' . ( $active ? ' is-active' : '' ) . '"'
				. ' href="' . self::e( self::view_url( $key ) ) . '"'
				. ( $active ? ' aria-current="page"' : '' ) . '>'
				. '<span class="clubhouse-member__navlabel">'
				. self::icon( (string) $view['icon'] )
				. self::e( (string) $view['label'] )
				. '</span></a>';
		}
		return $out . '</nav>';
	}

	/** One panel. A card with no title is a card with no head, not an empty one. */
	public static function card( string $title, string $body ): string {
		$out = '<section class="bw-card">';
		if ( '' !== trim( $title ) ) {
			$out .= '<div class="bw-card__head"><div class="bw-card__titles">'
				. '<h2 class="bw-card__title">' . self::e( $title ) . '</h2>'
				. '</div></div>';
		}
		return $out . '<div class="bw-card__body">' . $body . '</div></section>';
	}

	/**
	 * What a member sees where a panel would be if the club has not set that
	 * part up. Never a blank frame: it says so plainly and offers the way back.
	 */
	public static function empty_state( string $title, string $text, string $href, string $label ): string {
		$out = '<div class="bw-empty">'
			. '<p class="bw-empty__title">' . self::e( $title ) . '</p>'
			. '<p class="bw-empty__text">' . self::e( $text ) . '</p>';
		if ( '' !== trim( $href ) && '' !== trim( $label ) ) {
			$out .= '<div class="bw-empty__actions">'
				. '<a class="bw-btn bw-btn--secondary" href="' . self::e( $href ) . '">' . self::e( $label ) . '</a>'
				. '</div>';
		}
		return $out . '</div>';
	}

	/**
	 * One glyph, or '' for a name nothing draws — a missing icon must never be
	 * a fatal or a broken image.
	 *
	 * The paths are Lucide's, the set the design is drawn with.
	 */
	public static function icon( string $name ): string {
		$paths = array(
			'layout-dashboard' => '<rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/>',
			'calendar'         => '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/>',
			'shopping-cart'    => '<circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/>',
			'file-spreadsheet' => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M8 13h2"/><path d="M14 13h2"/><path d="M8 17h2"/><path d="M14 17h2"/>',
			'refresh-cw'       => '<path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M3 21v-5h5"/>',
			'users'            => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
			'arrow-left'       => '<path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>',
		);
		if ( ! isset( $paths[ $name ] ) ) {
			return '';
		}
		return '<svg class="bw-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
			. ' stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
			. $paths[ $name ] . '</svg>';
	}
}
```

- [ ] **Step 4: Add the few rules the design draws inline**

The design system styles every component used here, but the design draws the nav
label's icon-and-text row with an inline style. Append this to the end of
`assets/bw/bw.css`, under a `/* Clubhouse additions */` heading (create the
heading if Task 1 Step 3 did not):

```css
/* Clubhouse additions — layout the design draws inline, not part of the
   vendored system. Same token vocabulary, same scoping rules. */
.clubhouse-member .clubhouse-member__navlabel{display:flex;align-items:center;gap:10px}
.clubhouse-member .bw-secnav__item .bw-icon{flex:none}
.clubhouse-member .bw-pagehead__actions .bw-icon{margin-right:6px}
.clubhouse-member .bw-panels>*+*{margin-top:var(--bw-stack-gap)}
```

Note the `.clubhouse-member` prefix keeps `DashboardAssetsTest`'s scoping check
honest — add `clubhouse-member` to the allowed selector prefixes in that test
(`str_starts_with( $selector, '.bw-' ) || str_starts_with( $selector, '.clubhouse-member' )`).

- [ ] **Step 5: Register it in the loader**

In `includes/bootstrap.php`, in the `// Render` block, after
`class-sections.php`:

```php
require_once __DIR__ . '/render/class-dashboard-shell.php';
```

- [ ] **Step 6: Run the tests**

Run: `composer test -- --filter 'DashboardShellTest|DashboardAssetsTest'`
Expected: PASS.

Then the whole suite: `composer test`
Expected: PASS. Pay attention to `LookCoverageTest` — it scrapes
`includes/render/*.php` for `ch-*` classes. This file emits none, so it must
still pass. If it fails, you have used a `ch-` class by mistake.

- [ ] **Step 7: Commit**

```bash
git add includes/render/class-dashboard-shell.php includes/bootstrap.php assets/bw/bw.css tests/php/DashboardShellTest.php tests/php/DashboardAssetsTest.php
git commit -m "Draw the member area's frame"
```

---

### Task 5: Take over the customer dashboard

The page itself: decide the view, render it, and put the welcome pack where a
greeting belongs.

**Files:**
- Create: `includes/dashboard/class-member-dashboard.php`
- Modify: `includes/bootstrap.php`
- Modify: `includes/membership/class-welcome-pack.php`
- Modify: `blueworx-labs-clubhouse.php`
- Test: `tests/php/MemberDashboardTest.php`

**Interfaces:**
- Consumes: `Dashboard_Views::available()`, `::resolve()`, `::find()`;
  `Plugin_Slot::block()`, `::shortcode()`; `Dashboard_Shell::page()`, `::card()`,
  `::empty_state()`, `::view_url()`; `Welcome_Pack::render()`, `::css()`;
  `Shop_Pages::page_id()`; `Integrations::has_latepoint()`;
  `SureCart_Products::is_active()`.
- Produces:
  - `Blueworx_Clubhouse_Member_Dashboard::register(): void`
  - `::owns( int $post_id ): bool`
  - `::view_body( array $view, string $welcome, string $home_url ): string`
  - `::overview( string $welcome, array $views, string $home_url ): string`
  - `::take_over( $content ): string`

- [ ] **Step 1: Write the failing test**

`tests/php/MemberDashboardTest.php`:

```php
<?php

use PHPUnit\Framework\TestCase;

final class MemberDashboardTest extends TestCase {

	protected function tearDown(): void {
		Blueworx_Clubhouse_Plugin_Slot::set_sources( null, null );
	}

	/** Both plugins present and answering. */
	private function everything_installed(): void {
		Blueworx_Clubhouse_Plugin_Slot::set_sources(
			static fn ( string $n ): ?string => '<div data-block="' . $n . '">panel</div>',
			static fn ( string $t ): ?string => '<div data-shortcode="' . $t . '">bookings</div>'
		);
	}

	/** @return array<string,mixed> */
	private function view( string $key ): array {
		$found = Blueworx_Clubhouse_Dashboard_Views::find( $key, Blueworx_Clubhouse_Dashboard_Views::all() );
		$this->assertIsArray( $found );
		return $found;
	}

	public function test_a_block_view_renders_the_shops_own_panel(): void {
		$this->everything_installed();
		$html = Blueworx_Clubhouse_Member_Dashboard::view_body( $this->view( 'orders' ), '', 'https://club.test/' );
		$this->assertStringContainsString( 'data-block="surecart/customer-orders"', $html );
	}

	public function test_a_view_with_two_panels_renders_both_in_order(): void {
		$this->everything_installed();
		$html = Blueworx_Clubhouse_Member_Dashboard::view_body( $this->view( 'account' ), '', 'https://club.test/' );
		$billing = strpos( $html, 'surecart/customer-billing-details' );
		$cards   = strpos( $html, 'surecart/customer-payment-methods' );
		$this->assertIsInt( $billing );
		$this->assertIsInt( $cards );
		$this->assertLessThan( $cards, $billing );
	}

	public function test_the_bookings_view_hands_the_whole_panel_to_the_booking_plugin(): void {
		// LatePoint brings its own tabs; boxing them inside a card of ours
		// would be two sets of tabs on one screen.
		$this->everything_installed();
		$html = Blueworx_Clubhouse_Member_Dashboard::view_body( $this->view( 'bookings' ), '', 'https://club.test/' );
		$this->assertStringContainsString( 'data-shortcode="latepoint_customer_dashboard"', $html );
		$this->assertStringNotContainsString( 'bw-card__head', $html );
	}

	public function test_a_panel_whose_plugin_says_nothing_shows_the_honest_empty_state(): void {
		// Never a blank frame.
		$html = Blueworx_Clubhouse_Member_Dashboard::view_body( $this->view( 'orders' ), '', 'https://club.test/' );
		$this->assertStringContainsString( 'bw-empty', $html );
		$this->assertStringContainsString( 'href="https://club.test/"', $html );
	}

	public function test_the_overview_greets_a_member_with_the_clubs_welcome_pack(): void {
		$views = Blueworx_Clubhouse_Dashboard_Views::available( true, true );
		$html  = Blueworx_Clubhouse_Member_Dashboard::overview( '<section class="clubhouse-welcome">hi</section>', $views, 'https://club.test/' );
		$this->assertStringContainsString( 'clubhouse-welcome', $html );
	}

	public function test_the_welcome_pack_comes_before_anything_else_on_the_overview(): void {
		// It greets a member, so it goes where a greeting goes.
		$views = Blueworx_Clubhouse_Dashboard_Views::available( true, true );
		$html  = Blueworx_Clubhouse_Member_Dashboard::overview( '<section class="clubhouse-welcome">hi</section>', $views, 'https://club.test/' );
		$this->assertLessThan( strpos( $html, '?view=orders' ), strpos( $html, 'clubhouse-welcome' ) );
	}

	public function test_the_overview_links_into_every_other_view(): void {
		$views = Blueworx_Clubhouse_Dashboard_Views::available( true, true );
		$html  = Blueworx_Clubhouse_Member_Dashboard::overview( '', $views, 'https://club.test/' );
		foreach ( array( 'bookings', 'orders', 'invoices', 'plans', 'account' ) as $key ) {
			$this->assertStringContainsString( '?view=' . $key, $html );
		}
		// Not to itself: a link to the page you are on is a dead control.
		$this->assertStringNotContainsString( '?view=dashboard', $html );
	}

	public function test_an_overview_with_nothing_to_link_to_still_says_something(): void {
		// A club with neither plugin: the pack, and no empty grid of links.
		$views = Blueworx_Clubhouse_Dashboard_Views::available( false, false );
		$html  = Blueworx_Clubhouse_Member_Dashboard::overview( '<section class="clubhouse-welcome">hi</section>', $views, 'https://club.test/' );
		$this->assertStringContainsString( 'clubhouse-welcome', $html );
		$this->assertStringNotContainsString( '?view=', $html );
	}

	public function test_an_overview_with_no_pack_written_is_not_an_empty_page(): void {
		$views = Blueworx_Clubhouse_Dashboard_Views::available( true, true );
		$html  = Blueworx_Clubhouse_Member_Dashboard::overview( '', $views, 'https://club.test/' );
		$this->assertStringContainsString( '?view=orders', $html );
		$this->assertNotSame( '', trim( $html ) );
	}
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `composer test -- --filter MemberDashboardTest`
Expected: FAIL — `Class "Blueworx_Clubhouse_Member_Dashboard" not found`.

- [ ] **Step 3: Write the implementation**

`includes/dashboard/class-member-dashboard.php`:

```php
<?php
// includes/dashboard/class-member-dashboard.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The member's account page: this plugin's frame, the other plugins' data.
 *
 * SureCart seeds a customer dashboard page and LatePoint has a shortcode, and
 * a club that installs both ends up with a page that reads as two plugins in a
 * stack. This takes the page over: one design, one nav, and each panel filled
 * by whichever plugin owns that data. We do not re-render their records — see
 * the spec's non-goals.
 *
 * Taken over by filtering the_content rather than by a template, for the same
 * reason the welcome pack does it: the page is SureCart's and its template is
 * theirs to change. Priority 30, after SureCart expands its own dashboard at
 * 10 and after the welcome pack's old filter at 20, so whatever was there is
 * replaced rather than raced.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Member_Dashboard {

	/** After SureCart (10) and after the welcome pack's own filter (20). */
	private const PRIORITY = 30;

	public static function register(): void {
		if ( ! function_exists( 'add_filter' ) ) {
			return;
		}
		Blueworx_Clubhouse_Plugin_Slot::install_wordpress();
		Blueworx_Clubhouse_Dashboard_Assets::register();
		add_filter( 'the_content', array( self::class, 'take_over' ), self::PRIORITY );
	}

	/** Whether this post is the page the member area is on. */
	public static function owns( int $post_id ): bool {
		$dashboard = Blueworx_Clubhouse_Shop_Pages::page_id( 'dashboard' );
		return $post_id > 0 && $post_id === $dashboard;
	}

	/**
	 * Replace the customer dashboard with ours, and leave every other page
	 * alone.
	 *
	 * The cheap checks come first so the vast majority of requests leave after
	 * one comparison.
	 *
	 * @param string $content
	 */
	public static function take_over( $content ): string {
		$content = (string) $content;
		if ( ! function_exists( 'is_singular' ) || ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		if ( ! self::owns( (int) get_the_ID() ) ) {
			return $content;
		}

		Blueworx_Clubhouse_Dashboard_Assets::enqueue();

		$views   = Blueworx_Clubhouse_Dashboard_Views::available(
			Blueworx_Clubhouse_SureCart_Products::is_active(),
			Blueworx_Clubhouse_Integrations::has_latepoint()
		);
		$current = Blueworx_Clubhouse_Dashboard_Views::resolve( self::requested_view(), $views );
		$view    = Blueworx_Clubhouse_Dashboard_Views::find( $current, $views );
		if ( null === $view ) {
			return $content; // Cannot happen — resolve() only returns a key it found.
		}

		$home = function_exists( 'home_url' ) ? (string) home_url( '/' ) : '/';
		$body = self::view_body( $view, self::welcome_pack(), $home );
		if ( Blueworx_Clubhouse_Dashboard_Views::DEFAULT_VIEW === $current ) {
			$body = self::overview( self::welcome_pack(), $views, $home );
		}

		return '<style>' . Blueworx_Clubhouse_Welcome_Pack::css( ...self::accent() ) . '</style>'
			. Blueworx_Clubhouse_Dashboard_Shell::page(
				$views,
				$current,
				(string) $view['title'],
				(string) $view['lede'],
				$body,
				$home,
				self::club_name()
			);
	}

	/** The view named in the address, unfiltered — resolve() decides what it means. */
	private static function requested_view(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading which panel to show, not acting.
		$raw = $_GET[ Blueworx_Clubhouse_Dashboard_Shell::VIEW_ARG ] ?? '';
		return is_string( $raw ) ? $raw : '';
	}

	/**
	 * One view's contents.
	 *
	 * A shortcode view is handed the whole panel — LatePoint brings its own
	 * tabs and does not belong inside a card of ours. Blocks each get a card.
	 * A panel whose plugin says nothing shows the honest empty state rather
	 * than an empty card.
	 *
	 * @param array<string,mixed> $view
	 */
	public static function view_body( array $view, string $welcome, string $home_url ): string {
		$shortcode = (string) $view['shortcode'];
		if ( '' !== $shortcode ) {
			$out = Blueworx_Clubhouse_Plugin_Slot::shortcode( $shortcode );
			return '' !== $out ? $out : self::not_set_up( $home_url );
		}

		$out = '';
		foreach ( (array) $view['blocks'] as $block ) {
			$panel = Blueworx_Clubhouse_Plugin_Slot::block( (string) $block );
			if ( '' !== $panel ) {
				$out .= Blueworx_Clubhouse_Dashboard_Shell::card( '', $panel );
			}
		}
		if ( '' === $out ) {
			return self::not_set_up( $home_url );
		}
		return ( '' !== $welcome ? $welcome : '' ) . $out;
	}

	/**
	 * The overview: the club's welcome, then the way into everything else.
	 *
	 * The design draws next sessions, recent orders and an outstanding-invoice
	 * notice here. Composing those means reading two plugins' records and
	 * re-rendering them, which the spec rules out — so this is the pack plus
	 * links, and the records stay where the plugins draw them.
	 *
	 * @param array<int,array<string,mixed>> $views
	 */
	public static function overview( string $welcome, array $views, string $home_url ): string {
		$links = '';
		foreach ( $views as $view ) {
			$key = (string) $view['key'];
			if ( Blueworx_Clubhouse_Dashboard_Views::DEFAULT_VIEW === $key ) {
				continue; // A link to the page you are on is a dead control.
			}
			$links .= '<a class="bw-card clubhouse-member__quick" href="'
				. htmlspecialchars( Blueworx_Clubhouse_Dashboard_Shell::view_url( $key ), ENT_QUOTES, 'UTF-8' ) . '">'
				. '<span class="clubhouse-member__quick-icon">' . Blueworx_Clubhouse_Dashboard_Shell::icon( (string) $view['icon'] ) . '</span>'
				. '<span class="clubhouse-member__quick-title">' . htmlspecialchars( (string) $view['label'], ENT_QUOTES, 'UTF-8' ) . '</span>'
				. '<span class="clubhouse-member__quick-lede">' . htmlspecialchars( (string) $view['lede'], ENT_QUOTES, 'UTF-8' ) . '</span>'
				. '</a>';
		}
		if ( '' !== $links ) {
			$links = '<div class="clubhouse-member__quicks">' . $links . '</div>';
		}
		return $welcome . $links;
	}

	/** What a member sees where a panel would be if the club has not set that part up. */
	private static function not_set_up( string $home_url ): string {
		return Blueworx_Clubhouse_Dashboard_Shell::card(
			'',
			Blueworx_Clubhouse_Dashboard_Shell::empty_state(
				'Nothing here yet',
				'The club has not set this part up. Nothing is missing from your membership.',
				$home_url,
				'Back to the club site'
			)
		);
	}

	/** The club's welcome pack, or '' when nobody has written one. */
	private static function welcome_pack(): string {
		$storage = new Blueworx_Clubhouse_Options_Storage();
		if ( ! ( new Blueworx_Clubhouse_Visibility( $storage ) )->is_section_visible( 'home', Blueworx_Clubhouse_Welcome_Pack::SECTION ) ) {
			return '';
		}
		$store = new Blueworx_Clubhouse_Content_Store( $storage );
		return Blueworx_Clubhouse_Welcome_Pack::render(
			array(
				'heading'    => (string) $store->get( Blueworx_Clubhouse_Welcome_Pack::STORE_PAGE, Blueworx_Clubhouse_Welcome_Pack::SECTION, 'heading', '' ),
				'body'       => (string) $store->get( Blueworx_Clubhouse_Welcome_Pack::STORE_PAGE, Blueworx_Clubhouse_Welcome_Pack::SECTION, 'body', '' ),
				'link_label' => (string) $store->get( Blueworx_Clubhouse_Welcome_Pack::STORE_PAGE, Blueworx_Clubhouse_Welcome_Pack::SECTION, 'link_label', '' ),
				'link_href'  => (string) $store->get( Blueworx_Clubhouse_Welcome_Pack::STORE_PAGE, Blueworx_Clubhouse_Welcome_Pack::SECTION, 'link_href', '' ),
			)
		);
	}

	/**
	 * The club's accent, for the welcome pack's own rules. Derived the same way
	 * the pack derives it — against a white ground and near-black text.
	 *
	 * @return array{0:string,1:string}
	 */
	private static function accent(): array {
		$branding = new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Options_Storage() );
		$derived  = Blueworx_Clubhouse_Color_Engine::derive( $branding->get_accent(), '#ffffff', '#111111' );
		return array( (string) ( $derived['--color-accent'] ?? '' ), (string) ( $derived['--color-accent-ink'] ?? '' ) );
	}

	/** The club's name for the page head, or '' when nothing is set. */
	public static function club_name(): string {
		if ( ! function_exists( 'get_bloginfo' ) ) {
			return '';
		}
		return (string) get_bloginfo( 'name' );
	}
}
```

- [ ] **Step 4: Stop the welcome pack rendering twice**

The pack now renders inside the overview. Its own filter must stand down on the
page we have taken over, or a member sees it twice. In
`includes/membership/class-welcome-pack.php`, in `add_to_dashboard()`, directly
after the `$dashboard`/`get_the_ID()` check, add:

```php
		// The member area draws the pack itself, at the top of its overview.
		// Without this the pack would render twice on the same page.
		if ( class_exists( 'Blueworx_Clubhouse_Member_Dashboard' )
			&& Blueworx_Clubhouse_Member_Dashboard::owns( (int) get_the_ID() ) ) {
			return $content;
		}
```

- [ ] **Step 5: Add the overview's quick-link rules**

Append to the `/* Clubhouse additions */` block in `assets/bw/bw.css`:

```css
.clubhouse-member .clubhouse-member__quicks{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:var(--bw-stack-gap)}
.clubhouse-member .clubhouse-member__quick{display:flex;flex-direction:column;gap:var(--bw-space-3);padding:var(--bw-panel-pad);text-decoration:none;color:var(--bw-text-body);transition:var(--bw-transition-control)}
.clubhouse-member .clubhouse-member__quick:hover{border-color:var(--bw-brand);color:var(--bw-text-accent)}
.clubhouse-member .clubhouse-member__quick-icon{color:var(--bw-text-accent)}
.clubhouse-member .clubhouse-member__quick-title{font-family:var(--bw-font-display);font-size:var(--bw-size-h3);font-weight:var(--bw-weight-semibold);color:var(--bw-text-heading)}
.clubhouse-member .clubhouse-member__quick-lede{font-size:var(--bw-size-sm);color:var(--bw-text-muted)}
```

- [ ] **Step 6: Register it in the loader and boot it**

In `includes/bootstrap.php`, in the member area block, last of the four:

```php
require_once __DIR__ . '/dashboard/class-member-dashboard.php';
```

In `blueworx-labs-clubhouse.php`, inside `blueworx_labs_clubhouse_init()`,
directly **after** `Blueworx_Clubhouse_Welcome_Pack::register();`:

```php
	Blueworx_Clubhouse_Member_Dashboard::register();
```

- [ ] **Step 7: Run the tests**

Run: `composer test -- --filter MemberDashboardTest`
Expected: PASS, 9 tests.

Then the whole suite: `composer test`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add includes/dashboard/class-member-dashboard.php includes/bootstrap.php includes/membership/class-welcome-pack.php blueworx-labs-clubhouse.php assets/bw/bw.css tests/php/MemberDashboardTest.php
git commit -m "Take over the member's account page"
```

---

### Task 6: Checkout and order confirmation

The same look, no nav, and the page's own content left exactly as SureCart
renders it.

**Files:**
- Create: `includes/dashboard/class-commerce-pages.php`
- Modify: `includes/bootstrap.php`
- Modify: `blueworx-labs-clubhouse.php`
- Test: `tests/php/CommercePagesTest.php`

**Interfaces:**
- Consumes: `Dashboard_Shell::bare()`, `Shop_Pages::page_id()`,
  `Dashboard_Assets::enqueue()`.
- Produces:
  - `Blueworx_Clubhouse_Commerce_Pages::register(): void`
  - `::PAGES` — `array<string,array{title:string,lede:string}>` keyed by the
    `Shop_Pages` key
  - `::page_key( int $post_id, int $checkout_id, int $confirmation_id ): string`
  - `::dress( $content ): string`

- [ ] **Step 1: Write the failing test**

`tests/php/CommercePagesTest.php`:

```php
<?php

use PHPUnit\Framework\TestCase;

final class CommercePagesTest extends TestCase {

	public function test_the_checkout_page_is_recognised(): void {
		$this->assertSame( 'checkout', Blueworx_Clubhouse_Commerce_Pages::page_key( 12, 12, 34 ) );
	}

	public function test_the_confirmation_page_is_recognised(): void {
		$this->assertSame( 'order-confirmation', Blueworx_Clubhouse_Commerce_Pages::page_key( 34, 12, 34 ) );
	}

	public function test_any_other_page_is_left_alone(): void {
		$this->assertSame( '', Blueworx_Clubhouse_Commerce_Pages::page_key( 99, 12, 34 ) );
	}

	public function test_a_shop_with_no_pages_set_up_dresses_nothing(): void {
		// 0 means "no page recorded". Without this, every page on the site whose
		// id happened to be 0 — and any post on a broken query — would be dressed.
		$this->assertSame( '', Blueworx_Clubhouse_Commerce_Pages::page_key( 0, 0, 0 ) );
	}

	public function test_both_pages_are_described(): void {
		foreach ( Blueworx_Clubhouse_Commerce_Pages::PAGES as $key => $page ) {
			$this->assertNotSame( '', $page['title'], $key . ' has no title' );
			$this->assertNotSame( '', $page['lede'], $key . ' has no lede' );
		}
		$this->assertSame( array( 'checkout', 'order-confirmation' ), array_keys( Blueworx_Clubhouse_Commerce_Pages::PAGES ) );
	}

	public function test_the_page_keeps_its_own_content_inside_our_frame(): void {
		// We do not build checkouts. The shop's form is rendered by the shop and
		// passed through untouched.
		$html = Blueworx_Clubhouse_Dashboard_Shell::bare(
			Blueworx_Clubhouse_Commerce_Pages::PAGES['checkout']['title'],
			Blueworx_Clubhouse_Commerce_Pages::PAGES['checkout']['lede'],
			'<form id="sc-checkout"></form>',
			'https://club.test/',
			'Club'
		);
		$this->assertStringContainsString( '<form id="sc-checkout"></form>', $html );
		$this->assertStringNotContainsString( 'bw-secnav', $html );
	}
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `composer test -- --filter CommercePagesTest`
Expected: FAIL — `Class "Blueworx_Clubhouse_Commerce_Pages" not found`.

- [ ] **Step 3: Write the implementation**

`includes/dashboard/class-commerce-pages.php`:

```php
<?php
// includes/dashboard/class-commerce-pages.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checkout and order confirmation, in the member area's look.
 *
 * The same frame as the member dashboard, minus the nav: someone mid-purchase
 * should not be offered six places to wander off to. The page's own content is
 * passed through untouched — the shop renders the shop, exactly as everywhere
 * else in this plugin.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Commerce_Pages {

	/** After SureCart has expanded its own blocks into the content. */
	private const PRIORITY = 30;

	/**
	 * The pages taken over, keyed the way Shop_Pages keys them.
	 *
	 * @var array<string,array{title:string,lede:string}>
	 */
	public const PAGES = array(
		'checkout'           => array(
			'title' => 'Checkout',
			'lede'  => 'A few details and you are done.',
		),
		'order-confirmation' => array(
			'title' => 'Thank you',
			'lede'  => 'Your order is confirmed. A receipt is on its way by email.',
		),
	);

	public static function register(): void {
		if ( ! function_exists( 'add_filter' ) ) {
			return;
		}
		add_filter( 'the_content', array( self::class, 'dress' ), self::PRIORITY );
	}

	/**
	 * Which of these pages a post is, or '' for anything else. Pure.
	 *
	 * An id of 0 means the shop has not recorded that page, and must never
	 * match — 0 would otherwise dress whatever a broken query returned.
	 */
	public static function page_key( int $post_id, int $checkout_id, int $confirmation_id ): string {
		if ( $post_id <= 0 ) {
			return '';
		}
		if ( $post_id === $checkout_id ) {
			return 'checkout';
		}
		if ( $post_id === $confirmation_id ) {
			return 'order-confirmation';
		}
		return '';
	}

	/**
	 * @param string $content
	 */
	public static function dress( $content ): string {
		$content = (string) $content;
		if ( ! function_exists( 'is_singular' ) || ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$key = self::page_key(
			(int) get_the_ID(),
			Blueworx_Clubhouse_Shop_Pages::page_id( 'checkout' ),
			Blueworx_Clubhouse_Shop_Pages::page_id( 'order-confirmation' )
		);
		if ( '' === $key ) {
			return $content;
		}

		Blueworx_Clubhouse_Dashboard_Assets::enqueue();

		return Blueworx_Clubhouse_Dashboard_Shell::bare(
			self::PAGES[ $key ]['title'],
			self::PAGES[ $key ]['lede'],
			Blueworx_Clubhouse_Dashboard_Shell::card( '', $content ),
			function_exists( 'home_url' ) ? (string) home_url( '/' ) : '/',
			Blueworx_Clubhouse_Member_Dashboard::club_name()
		);
	}
}
```

- [ ] **Step 4: Register it in the loader and boot it**

In `includes/bootstrap.php`, in the member area block, after
`class-member-dashboard.php`:

```php
require_once __DIR__ . '/dashboard/class-commerce-pages.php';
```

In `blueworx-labs-clubhouse.php`, after `Blueworx_Clubhouse_Member_Dashboard::register();`:

```php
	Blueworx_Clubhouse_Commerce_Pages::register();
```

- [ ] **Step 5: Run the tests**

Run: `composer test -- --filter CommercePagesTest`
Expected: PASS, 6 tests.

Then the whole suite: `composer test`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add includes/dashboard/class-commerce-pages.php includes/bootstrap.php blueworx-labs-clubhouse.php tests/php/CommercePagesTest.php
git commit -m "Dress checkout and order confirmation in the member area's look"
```

---

### Task 7: Prove it on real WordPress, then ship

The unit tests cover every rule. What they cannot cover is that the filter fires
on a real page, the stylesheet reaches the browser, and nothing fatals. The
harness has neither SureCart nor LatePoint, so this proves the honest empty path
end to end — which is exactly the path a club sees before it sets a shop up.

**Files:**
- Create: `tests/member-dashboard.spec.js`
- Modify: `tests/welcome-pack.spec.js`
- Modify: `blueworx-labs-clubhouse.php`
- Modify: `package.json`
- Modify: `CHANGELOG.md`
- Modify: `docs/priorities.md`

**Interfaces:**
- Consumes: everything above.
- Produces: nothing further.

- [ ] **Step 1: Write the browser test**

`tests/member-dashboard.spec.js`:

```javascript
const { test, expect } = require('@playwright/test');

// @wordpress only: the member area renders on the customer dashboard, which is
// a real WordPress page the DB-free preview does not have.
//
// The fixture is the one welcome-pack.spec.js and external-chrome.spec.js use —
// an ordinary page carrying SureCart's dashboard template slug, with the
// dashboard page option pointed at it by tests/global-setup.js. That option is
// what the code keys off and what SureCart itself writes.
//
// The harness has neither SureCart nor LatePoint installed, so what these
// assertions cover is the empty path: the frame renders, no dead nav items are
// offered, and nothing fatals. Asserting SureCart's own panels would be testing
// SureCart, the same reasoning external-chrome.spec.js records.
const DASHBOARD = '/external-chrome-fixture/';

test('a member gets the club frame around their account page @wordpress', async ({ page }) => {
  await page.goto(DASHBOARD);
  await expect(page.locator('.bw-admin.clubhouse-member')).toHaveCount(1);
  await expect(page.locator('.bw-pagehead__h1')).toContainText('Your account');
});

test('the member area stylesheet actually loads @wordpress', async ({ page }) => {
  // A 404 on the stylesheet leaves a page that renders but looks like nothing.
  const responses = [];
  page.on('response', (r) => responses.push(r));
  await page.goto(DASHBOARD);

  const sheet = responses.find((r) => r.url().includes('/assets/bw/bw.css'));
  expect(sheet, 'the vendored stylesheet was never requested').toBeTruthy();
  expect(sheet.status()).toBe(200);
});

test('a club with no shop and no bookings is offered no dead nav items @wordpress', async ({ page }) => {
  await page.goto(DASHBOARD);
  await expect(page.locator('.bw-secnav__item')).toHaveCount(1);
  await expect(page.locator('.bw-secnav__item')).toHaveAttribute('href', '?view=dashboard');
});

test('an address for a view this club does not have lands on the dashboard @wordpress', async ({ page }) => {
  // A bookmark kept from before a plugin was removed, or a typed address.
  await page.goto(`${DASHBOARD}?view=orders`);
  await expect(page.locator('.bw-pagehead__h1')).toContainText('Your account');
  await expect(page.locator('.bw-empty')).toHaveCount(0);
});

test('junk in the address does not break the page @wordpress', async ({ page }) => {
  const response = await page.goto(`${DASHBOARD}?view=%3Cscript%3Ealert(1)%3C/script%3E`);
  expect(response.status()).toBe(200);
  await expect(page.locator('.bw-admin.clubhouse-member')).toHaveCount(1);
  await expect(page.locator('.bw-pagehead__h1')).toContainText('Your account');
});

test('the welcome pack greets a member at the top of the overview @wordpress', async ({ page }) => {
  await page.goto(DASHBOARD);
  const pack = page.locator('.clubhouse-welcome');
  await expect(pack).toHaveCount(1);
  await expect(pack.getByRole('heading', { name: 'Welcome to the club' })).toBeVisible();
});

test('there is a way back to the club site @wordpress', async ({ page }) => {
  await expect(page.getByRole('link', { name: 'Back to the club site' }).first()).toBeVisible({
    timeout: 0,
  }).catch(async () => {
    await page.goto(DASHBOARD);
    await expect(page.getByRole('link', { name: 'Back to the club site' }).first()).toBeVisible();
  });
});
```

Simplify that last test to the obvious form once you have the page open — it is
written defensively above only because the preceding test navigates. Prefer:

```javascript
test('there is a way back to the club site @wordpress', async ({ page }) => {
  await page.goto(DASHBOARD);
  await expect(page.getByRole('link', { name: 'Back to the club site' }).first()).toBeVisible();
});
```

- [ ] **Step 2: Update the welcome pack spec**

`tests/welcome-pack.spec.js` currently asserts the pack sits above
`#foreign-content` — the fixture's own content. That content is no longer on the
page at all: the member area replaces it. Replace the second test
(`the pack greets a member above what the shop renders`) with:

```javascript
test('the pack greets a member above everything else on the page @wordpress', async ({ page }) => {
  // It used to sit below all of it, which on a real club's dashboard meant
  // under the tabs and an empty appointments list — the last thing a new
  // member would see, if they scrolled at all. The member area now draws the
  // pack itself, first thing in the overview.
  await page.goto(DASHBOARD);

  const order = await page.evaluate(() => {
    const pack = document.querySelector('.clubhouse-welcome');
    const rest = document.querySelector('.clubhouse-member__quicks, .bw-card');
    if (!pack) return null;
    if (!rest) return 'before'; // Nothing else on the page to come after.
    return pack.compareDocumentPosition(rest) & 4 ? 'before' : 'after';
  });

  expect(order).toBe('before');
});
```

Leave the first test in that file unchanged — the pack's own markup and its two
paragraphs are still exactly as they were.

- [ ] **Step 3: Run the browser suite against real WordPress**

```bash
npm run wp:up
PLAYWRIGHT_BASE_URL=http://localhost:8705 npx playwright test
```

Expected: every spec passes. If the run fails with connection-refused, the
harness lost its boot race — bring it up again and re-run against 8705 rather
than using `npm run test:wp`.

Then bring it down: `npm run wp:down`

- [ ] **Step 4: Bump the version**

In `blueworx-labs-clubhouse.php`, both places:

```php
 * Version:           0.79.0
```

```php
define( 'BLUEWORX_LABS_CLUBHOUSE_VERSION', '0.79.0' );
```

In `package.json`:

```json
  "version": "0.79.0",
```

- [ ] **Step 5: Write the changelog entry**

At the top of `CHANGELOG.md`, directly under the intro paragraph and above
`## 0.78.0`:

```markdown
## 0.79.0

- **Your members now have one account page instead of three plugins in a stack.** Signing in takes a member to a proper member area: a menu down the side for their bookings, orders, invoices, membership and account details, with each one still run by the plugin that owns it. Your welcome pack greets them at the top. If you have no shop, or no bookings, those menu items simply are not there — nothing to set up and nothing to switch on. Your checkout and thank-you pages now match it, and every address on your site stays exactly as it was.
```

- [ ] **Step 6: Strike it off the priorities list**

In `docs/priorities.md`, add a row to the running order table recording that
issue #231 is done in v0.79.0, in the same struck-through style as the rows
above it.

- [ ] **Step 7: Run the linter once**

Run: `composer lint`

Do **not** fix anything it reports. Collect the findings, and report them to
Luke with the pull request so he can decide whether to action them.

- [ ] **Step 8: Commit, push, and open the pull request**

```bash
git add tests/member-dashboard.spec.js tests/welcome-pack.spec.js blueworx-labs-clubhouse.php package.json CHANGELOG.md docs/priorities.md
git commit -m "Prove the member area on real WordPress, and ship 0.79.0"
git push -u origin member-dashboard
gh pr create --title "Take over the member's account page" --body "$(cat <<'EOF'
Closes #231.

The member area is now one page of ours: a nav down the side for bookings,
orders, invoices, plans and account, each panel filled by SureCart's or
LatePoint's own output rather than re-rendered by us. Checkout and order
confirmation get the same look without the nav. Every URL is unchanged.

Two things to know:

- The dashboard view is the welcome pack plus links into the other views. The
  design draws next sessions and recent orders there; composing those means
  re-rendering two plugins' records, which the spec ruled out.
- The panels themselves are only proven against a WordPress install with
  neither plugin, which is the empty path. Worth a look on a real site with
  SureCart before this goes to a club.

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

- [ ] **Step 9: Verify on a real shop before handing over**

The browser suite proves the frame and the empty path. It cannot prove that
SureCart's blocks render standalone outside its own dashboard block, because the
harness has no SureCart. On a site that does have it, open each of
`?view=orders`, `?view=invoices`, `?view=plans` and `?view=account` signed in as
a member and confirm each panel draws. If a block turns out to need attributes
or an ancestor block, the fix is in `Dashboard_Views::all()` — that is the whole
reason the block names live in one declarative list.

Report the result rather than assuming it.

---

## Self-Review

**Spec coverage.** Summary → Tasks 4–6. Goals: one designed member area → Task
4; plugins keep owning their data → Task 3; no dead nav items → Task 2; URLs
unchanged (all three pages adopted via `the_content`, no rewrites, no new pages)
→ Tasks 5–6; nothing a club entered is lost (welcome pack content untouched,
only where it renders changes) → Task 5 Step 4. Non-goals: nothing here renders
a booking, order, invoice or plan itself. The views table → `Dashboard_Views`.
Architecture's four classes → Tasks 2–5, plus `Dashboard_Assets` (Task 1) and
`Commerce_Pages` (Task 6), which the spec describes but did not name. Checkout
and confirmation → Task 6. Missing-plugin behaviour → Tasks 2, 4, 5. Styling
notes (scoped, no `assets/looks/`) → Task 1's scoping test and Task 4's
`test_the_shell_emits_no_club_look_classes`. Testing → all seven tasks. The open
question is resolved as the spec's own fallback (pack plus quick links) and
flagged in the pull request body.

**Placeholders.** None: every step carries the code, the command, or the exact
text to write.

**Type consistency.** `Dashboard_Views` view arrays carry
`key/label/title/lede/icon/requires/blocks/shortcode` and are consumed with
those names in `Dashboard_Shell::page()`, `Member_Dashboard::view_body()` and
`::overview()`. `Plugin_Slot::block()`/`::shortcode()` return `string` and are
compared against `''` at every call site. `Dashboard_Shell::view_url()` produces
`?view=<key>`, which is what both the nav and the tests assert.
`Member_Dashboard::club_name()` is public because `Commerce_Pages` calls it.
`Welcome_Pack::css()` is already public and takes `( $accent, $accent_ink )`,
which is what `Member_Dashboard::accent()` spreads into it.
