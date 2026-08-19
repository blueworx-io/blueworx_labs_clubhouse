# Member Area as a Club Page — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Serve the member area from Clubhouse's own page system at `/member-dashboard/`, retire the `the_content` takeover, and hide WordPress's Pages menu.

**Architecture:** One entry in `Page_Map::pages()` gives the member area an address, a rewrite rule, a visibility switch and a place in the link catalogue — the existing machinery does the rest. `Page_Renderer::member_dashboard()` renders it by delegating to a new `Member_Dashboard::screen()`, which is the body of today's `the_content` filter with the WordPress page-ownership plumbing taken out. A `template_redirect` carries SureCart's old dashboard page across and sends signed-out visitors to the club's own login page. The header's account button becomes the way in and the way back.

**Tech Stack:** PHP 8.2+, WordPress plugin, PHPUnit 11, Playwright, PHPCS (`composer lint`).

**Spec:** `docs/superpowers/specs/2026-08-19-member-area-as-a-club-page-design.md`

**Branch:** continue on `member-dashboard`, the branch PR #233 is open on. This change replaces that PR's central mechanism, so folding it in gives one coherent pull request rather than a second one that rewrites the first.

## Global Constraints

- **Delete nothing.** No WordPress page object is deleted, trashed or rewritten. Hiding the Pages menu is the whole of the "do away with pages" change.
- **Checkout and order confirmation are untouched.** `Commerce_Pages` keeps its `the_content` dressing and SureCart's URLs. Do not move, redirect or restyle them.
- **The member area keeps the BlueWorx admin look** — `assets/bw/bw.css`, no club header, no club footer, no look stylesheet. The two design systems never meet.
- **The member area's slug is exactly `member-dashboard`** and its label is exactly `Member area`.
- **No new dependencies.** Nothing is added to `package.json` or `composer.json`.
- **PHP style:** `declare(strict_types=1)`, the `ABSPATH` guard, tabs, and the `Blueworx_Clubhouse_` class prefix, matching every file you touch. `composer lint` is run once at the end of the plan, not per task.
- **WordPress browser tests need credentials.** Run them as `WP_ADMIN_USER=admin WP_ADMIN_PASS=wptest-admin-pw npx playwright test --workers=1`. Without those every `signIn` times out and the run is meaningless.
- **Known-red before this plan starts:** `hidden-page-404.spec.js:40` and `user-guide.spec.js:59` fail on `main` for unrelated reasons. They are not yours to fix; do not report them as regressions.
- **User-facing copy is short and plain** — the changelog entry says what changed for the club, in their words.

---

### Task 1: The member area becomes a Clubhouse page

Adds the map entry, the renderer method, and the visibility switch. At the end of this task `/member-dashboard/` serves the member area — unstyled, because assets come in Task 2.

**Files:**
- Modify: `includes/render/class-page-map.php`
- Modify: `includes/render/class-page-renderer.php`
- Modify: `includes/admin/class-setup-sections.php`
- Modify: `includes/dashboard/class-member-dashboard.php`
- Test: `tests/php/PageMapTest.php`, `tests/php/MemberDashboardTest.php`

**Interfaces:**
- Produces: `Blueworx_Clubhouse_Page_Map::is_private( string $slug ): bool`; `Blueworx_Clubhouse_Page_Renderer::member_dashboard( Branding, Visibility, Collections, string $logo_url = '', ?Content_Store $content = null, string $filter = '' ): string`; `Blueworx_Clubhouse_Member_Dashboard::screen( string $base, string $home ): string`.
- Consumes: `Blueworx_Clubhouse_Dashboard_Shell::page()` and `Blueworx_Clubhouse_Dashboard_Views::*`, both already built and unchanged.

- [ ] **Step 1: Write the failing tests**

Append to `tests/php/PageMapTest.php`, inside the class:

```php
	public function test_the_member_area_is_a_page_this_plugin_serves(): void {
		$entry = null;
		foreach ( Blueworx_Clubhouse_Page_Map::pages() as $page ) {
			if ( 'member-dashboard' === $page['slug'] ) {
				$entry = $page;
			}
		}
		$this->assertNotNull( $entry, 'member-dashboard must be in the page map' );
		$this->assertSame( 'Member area', $entry['label'] );
		$this->assertSame( 'member_dashboard', $entry['method'] );
	}

	/** Members-only pages are kept out of the SEO report; club pages are not. */
	public function test_private_pages_are_marked_as_such(): void {
		$this->assertTrue( Blueworx_Clubhouse_Page_Map::is_private( 'member-dashboard' ) );
		$this->assertFalse( Blueworx_Clubhouse_Page_Map::is_private( 'about' ) );
		$this->assertFalse( Blueworx_Clubhouse_Page_Map::is_private( 'nope' ) );
	}

	/** The one page that renders no club chrome — it is a BlueWorx admin screen. */
	public function test_member_area_renders_its_own_frame_and_no_club_chrome(): void {
		$html = Blueworx_Clubhouse_Page_Map::render(
			'member-dashboard',
			$this->branding(),
			$this->visibility(),
			$this->collections()
		);
		$this->assertStringContainsString( 'bw-admin', $html );
		$this->assertStringContainsString( 'clubhouse-member', $html );
		$this->assertStringNotContainsString( 'ch-nav', $html );
		$this->assertStringNotContainsString( 'ch-footer', $html );
	}
```

Append to `tests/php/MemberDashboardTest.php`, inside the class:

```php
	/** The frame, addressed at a URL of ours, with no WordPress page under it. */
	public function test_screen_renders_the_frame_at_a_given_address(): void {
		$html = Blueworx_Clubhouse_Member_Dashboard::screen( '/member-dashboard/', '/' );
		$this->assertStringContainsString( 'bw-admin', $html );
		$this->assertStringContainsString( '/member-dashboard/', $html );
	}
```

- [ ] **Step 2: Run them to make sure they fail**

Run: `composer test -- --filter 'PageMapTest|MemberDashboardTest'`
Expected: FAIL — `is_private` and `screen` do not exist, and `member-dashboard` is not in the map.

- [ ] **Step 3: Add the map entry and the private helper**

In `includes/render/class-page-map.php`, inside `pages()`, immediately **before** the `privacy` entry:

```php
			// The member's own area. Private: nothing on it is for a signed-out
			// visitor, so it is kept out of the SEO report rather than scored as
			// a page a search engine should be finding.
			array(
				'slug'    => 'member-dashboard',
				'label'   => 'Member area',
				'method'  => 'member_dashboard',
				'private' => true,
			),
```

Update the `@return` docblock on `pages()` and on `available()` to include `private?:bool`.

Add this method to the same class, after `label()`:

```php
	/** True for a members-only page — one no visitor should be sent to and no search engine should be scored on. */
	public static function is_private( string $slug ): bool {
		foreach ( self::pages() as $page ) {
			if ( $page['slug'] === $slug ) {
				return (bool) ( $page['private'] ?? false );
			}
		}
		return false;
	}
```

- [ ] **Step 4: Extract the screen out of the content filter**

In `includes/dashboard/class-member-dashboard.php`, add this public method (put it directly above the existing private `render()`):

```php
	/**
	 * The member area itself: the nav, the panel that was asked for, and the
	 * club's welcome above the overview.
	 *
	 * Takes its two addresses as arguments rather than reaching for them, so
	 * the screen can be rendered from a Clubhouse route, from the preview, and
	 * from a unit test without a WordPress runtime under it.
	 *
	 * @param string $base The member area's own address — every view link is built on it.
	 * @param string $home The club site's front page, for the way back out.
	 * @return string '' when no view can be drawn at all, which callers treat as "render nothing".
	 */
	public static function screen( string $base, string $home ): string {
		$views   = Blueworx_Clubhouse_Dashboard_Views::available(
			Blueworx_Clubhouse_SureCart_Products::is_active(),
			Blueworx_Clubhouse_Integrations::has_latepoint()
		);
		$current = Blueworx_Clubhouse_Dashboard_Views::resolve( self::requested_view(), $views );
		$view    = Blueworx_Clubhouse_Dashboard_Views::find( $current, $views );
		if ( null === $view ) {
			return ''; // Cannot happen — resolve() only returns a key it found.
		}

		$welcome = Blueworx_Clubhouse_Dashboard_Views::DEFAULT_VIEW === $current ? self::welcome_pack() : '';
		$body    = Blueworx_Clubhouse_Dashboard_Views::DEFAULT_VIEW === $current
			? self::overview( $welcome, $views, $home, $base )
			: self::view_body( $view, '', $home );

		// The pack brings its own rules, so they are only worth printing on a
		// view that actually draws one.
		$style = '' !== $welcome
			? '<style>' . Blueworx_Clubhouse_Welcome_Pack::css( ...self::accent() ) . '</style>'
			: '';

		return $style
			. Blueworx_Clubhouse_Dashboard_Shell::page(
				$views,
				$current,
				(string) $view['title'],
				(string) $view['lede'],
				$body,
				$home,
				self::club_name(),
				$base,
				self::logout_url()
			);
	}
```

Then replace the whole body of the existing private `render()` with a delegation, leaving its docblock in place:

```php
	private static function render( string $content ): string {
		Blueworx_Clubhouse_Dashboard_Assets::enqueue();
		self::enqueue_shop_assets();
		$home = function_exists( 'home_url' ) ? (string) home_url( '/' ) : '/';
		$out  = self::screen( self::page_url(), $home );
		return '' !== $out ? $out : $content;
	}
```

- [ ] **Step 5: Add the renderer method**

In `includes/render/class-page-renderer.php`, directly after the `login()` method, add:

```php
	/**
	 * The member's account area.
	 *
	 * The one page this plugin serves with no club header and no club footer.
	 * It is a BlueWorx admin screen: the shell it returns is already a whole
	 * page, so unlike every other method here it wraps nothing around it.
	 *
	 * The trailing arguments are the shared page-method signature Page_Map
	 * dispatches with; this page has no branding-driven chrome and no filter.
	 *
	 * @param string $logo_url Unused — the member area carries no club logo.
	 * @param string $filter   Unused — the member area has no filter pills.
	 */
	public static function member_dashboard(
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility,
		Blueworx_Clubhouse_Collections $collections,
		string $logo_url = '',
		?Blueworx_Clubhouse_Content_Store $content = null,
		string $filter = ''
	): string {
		return Blueworx_Clubhouse_Member_Dashboard::screen(
			Blueworx_Clubhouse_Links::url( 'member-dashboard' ),
			Blueworx_Clubhouse_Links::url( 'home' )
		);
	}
```

- [ ] **Step 6: Give it a visibility switch**

In `includes/admin/class-setup-sections.php`, inside `MAP`, directly after the `booking` entry:

```php
		// No sections of its own — the panels inside it belong to the shop and
		// the booking plugin. The page switch is the point: a club that does not
		// want a member area can take the address off.
		'member-dashboard' => array(),
```

- [ ] **Step 7: Run the tests**

Run: `composer test`
Expected: PASS. Watch in particular for `FrontEndLinkHygieneTest` (it renders every page in the map and forbids `href="#"`), `LinkCatalogueTest` (every available page must be offered as a target with a matching label), `SectionAnchorTest` and `PageRendererTest`. If any of those fail, the cause is in your change, not in them.

- [ ] **Step 8: Commit**

```bash
git add includes/render/class-page-map.php includes/render/class-page-renderer.php includes/admin/class-setup-sections.php includes/dashboard/class-member-dashboard.php tests/php/PageMapTest.php tests/php/MemberDashboardTest.php
git commit -m "Serve the member area at its own club address"
```

---

### Task 2: The member area gets its own stylesheet, early

The BlueWorx design system replaces the club's look on this route, and the shop's own components are queued while WordPress is still collecting for the head rather than at render time.

**Files:**
- Modify: `includes/frontend/class-frontend.php`
- Modify: `includes/dashboard/class-dashboard-assets.php`
- Modify: `includes/dashboard/class-member-dashboard.php`
- Test: `tests/php/FrontendTest.php`, `tests/php/DashboardAssetsTest.php`

**Interfaces:**
- Consumes: `Page_Map` entry from Task 1.
- Produces: `Blueworx_Clubhouse_Frontend::MEMBER_AREA` (const string `'member-dashboard'`); `Blueworx_Clubhouse_Frontend::style_family( ?string $slug, bool $is_article ): string` returning `'member'`, `'look'` or `'none'`; `Blueworx_Clubhouse_Frontend::current_page_slug(): ?string`; `Blueworx_Clubhouse_Member_Dashboard::enqueue_shop_assets(): void` (was private, now public).

- [ ] **Step 1: Write the failing tests**

Append to `tests/php/FrontendTest.php`, inside the class:

```php
	public function test_style_family_picks_the_member_design_for_the_member_area(): void {
		$this->assertSame( 'member', Blueworx_Clubhouse_Frontend::style_family( 'member-dashboard', false ) );
	}

	public function test_style_family_picks_the_club_look_for_club_pages_and_articles(): void {
		$this->assertSame( 'look', Blueworx_Clubhouse_Frontend::style_family( 'about', false ) );
		$this->assertSame( 'look', Blueworx_Clubhouse_Frontend::style_family( '', false ) );
		$this->assertSame( 'look', Blueworx_Clubhouse_Frontend::style_family( null, true ) );
	}

	public function test_style_family_loads_nothing_off_our_pages(): void {
		$this->assertSame( 'none', Blueworx_Clubhouse_Frontend::style_family( null, false ) );
	}
```

Replace the body of `test_each_page_we_take_over_is_named_and_nothing_else_is()` in `tests/php/DashboardAssetsTest.php` so the dashboard page is no longer one of ours — it is a Clubhouse route now, and the SureCart page it used to be on redirects away:

```php
	public function test_only_the_commerce_pages_are_taken_over_by_post_id(): void {
		// The member area moved to a Clubhouse route, so no post id maps to it.
		// Checkout and the thank-you page are still SureCart's own pages.
		$this->assertSame( '', Blueworx_Clubhouse_Dashboard_Assets::page_key( 0 ) );
		$this->assertSame( '', Blueworx_Clubhouse_Dashboard_Assets::page_key( 4242 ) );
	}
```

- [ ] **Step 2: Run them to make sure they fail**

Run: `composer test -- --filter 'FrontendTest|DashboardAssetsTest'`
Expected: FAIL — `style_family` does not exist.

- [ ] **Step 3: Add the slug constant, the accessor and the pure decision**

In `includes/frontend/class-frontend.php`, beside the existing `QUERY_VAR` constant:

```php
	/** The slug the member area is served at. */
	public const MEMBER_AREA = 'member-dashboard';
```

Add, next to `is_clubhouse_page()`:

```php
	/** The clubhouse slug this request resolves to, or null. Public so the member area's routing can ask. */
	public static function current_page_slug(): ?string {
		return self::current_slug();
	}

	/**
	 * Which design system this request gets: the member area's BlueWorx admin
	 * stylesheet, the club's own look, or nothing at all.
	 *
	 * A single answer rather than two independent checks, because the two must
	 * never both be true — the member area's design and the club's look define
	 * the same variables and would fight over the page.
	 *
	 * Pure, so the choice is testable without a WordPress runtime.
	 */
	public static function style_family( ?string $slug, bool $is_article ): string {
		if ( self::MEMBER_AREA === $slug ) {
			return 'member';
		}
		if ( null !== $slug || $is_article ) {
			return 'look';
		}
		return 'none';
	}
```

- [ ] **Step 4: Use it when enqueuing**

Replace the opening of `enqueue_assets()` in the same file:

```php
	public static function enqueue_assets(): void {
		$family = self::style_family( self::current_slug(), self::is_article() );
		if ( 'none' === $family ) {
			return;
		}
		if ( 'member' === $family ) {
			// A BlueWorx admin screen, so it gets that design system and none of
			// the club's. No scroll reveal either: it ships elements hidden until
			// they scroll into view, which on a page of the shop's own web
			// components would hide a member's orders behind an animation.
			Blueworx_Clubhouse_Dashboard_Assets::enqueue();
			Blueworx_Clubhouse_Member_Dashboard::enqueue_shop_assets();
			return;
		}
		if ( ! self::enqueue_look_styles() ) {
			return;
		}
```

Leave the three `wp_enqueue_script` calls that follow exactly as they are.

- [ ] **Step 5: Make the shop enqueue callable, and stop keying the member area off a post id**

In `includes/dashboard/class-member-dashboard.php`, change `private static function enqueue_shop_assets()` to `public static function enqueue_shop_assets()` and extend its docblock with:

```php
	 * Public and called from the asset pass rather than from the render, so the
	 * shop's stylesheet reaches the head. Called during rendering it arrived in
	 * the footer, and a member watched their account page snap into shape after
	 * it had loaded.
```

In `includes/dashboard/class-dashboard-assets.php`, replace `page_key()` with:

```php
	/**
	 * Which page this plugin dresses a post is — 'checkout' or
	 * 'order-confirmation' — and '' for every other post on the site.
	 *
	 * The member area is not here any more: it is a Clubhouse route with no
	 * WordPress post under it, and its stylesheet is queued by Frontend.
	 */
	public static function page_key( int $post_id ): string {
		return Blueworx_Clubhouse_Commerce_Pages::page_key(
			$post_id,
			Blueworx_Clubhouse_Shop_Pages::page_id( 'checkout' ),
			Blueworx_Clubhouse_Shop_Pages::page_id( 'order-confirmation' )
		);
	}
```

Also update the class docblock's "Loaded on the three pages this plugin takes over" to say the two commerce pages and the member area's own route.

- [ ] **Step 6: Run the tests**

Run: `composer test`
Expected: PASS. `MemberDashboardTest`'s existing `take_over` tests still pass — the filter is still in place until Task 3.

- [ ] **Step 7: Commit**

```bash
git add includes/frontend/class-frontend.php includes/dashboard/class-dashboard-assets.php includes/dashboard/class-member-dashboard.php tests/php/FrontendTest.php tests/php/DashboardAssetsTest.php
git commit -m "Serve the member area's own stylesheet from the club route"
```

---

### Task 3: Retire the content takeover

The filter goes, and with it three pieces of defensive code that existed only because we were rewriting a page we did not own.

**Files:**
- Modify: `includes/dashboard/class-member-dashboard.php`
- Test: `tests/php/MemberDashboardTest.php`

**Interfaces:**
- Consumes: `Member_Dashboard::screen()` from Task 1, `Frontend::enqueue_assets()` from Task 2.
- Produces: `Member_Dashboard::register()` now installs only the plugin slot and the stylesheet declaration.

- [ ] **Step 1: Delete the takeover**

In `includes/dashboard/class-member-dashboard.php`, delete all of these:

- the `PRIORITY` constant
- the `add_filter( 'the_content', ... )` line in `register()`
- the `private static bool $rendering` property and its docblock
- `public static function owns( int $post_id ): bool`
- `public static function take_over( $content ): string`
- `private static function render( string $content ): string`
- `private static function page_url(): string`

`register()` is left as:

```php
	public static function register(): void {
		if ( ! function_exists( 'add_filter' ) ) {
			return;
		}
		Blueworx_Clubhouse_Plugin_Slot::install_wordpress();
		Blueworx_Clubhouse_Dashboard_Assets::register();
	}
```

Replace the paragraph in the class docblock that begins "Taken over by filtering the_content" with:

```php
 * Served as a Clubhouse page at /member-dashboard/, the same way About and
 * Membership are, rather than by rewriting the page the shop seeds. So there is
 * no other plugin's template to be surprised by, no re-entrancy to guard, and
 * no page whose own content has to be judged and thrown away.
```

- [ ] **Step 2: Move the tests onto the screen**

In `tests/php/MemberDashboardTest.php`, every test that calls `take_over()` is testing a path that no longer exists. Rewrite each against `screen( '/member-dashboard/', '/' )`:

- The test asserting a signed-out visitor keeps the page's own content: **delete it.** That behaviour moves to the redirect in Task 4 and is tested there.
- The tests asserting the welcome pack's `<style>` appears only when the pack is on: keep them, calling `screen( '/member-dashboard/', '/' )` and asserting on its return value.
- The re-entrancy test (the one whose inner call re-enters `take_over`): **delete it.** There is no filter to re-enter.
- Any test calling `owns()`: **delete it.**

Keep every test of `view_body()`, `overview()` and `club_name()` exactly as it is.

- [ ] **Step 3: Run the tests**

Run: `composer test`
Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add includes/dashboard/class-member-dashboard.php tests/php/MemberDashboardTest.php
git commit -m "Stop rewriting the shop's account page"
```

---

### Task 4: Arriving and leaving

Two journeys meet on one hook: an old bookmark or the shop's own account link is carried across to the new address with its panel intact, and a signed-out visitor is sent to the club's login page instead of an empty frame.

**Files:**
- Modify: `includes/dashboard/class-member-dashboard.php`
- Test: `tests/php/MemberDashboardTest.php`
- Modify: `tests/member-dashboard.spec.js`, `tests/welcome-pack.spec.js`

**Interfaces:**
- Consumes: `Frontend::MEMBER_AREA`, `Frontend::current_page_slug()`, `Frontend::link_url()`, `Dashboard_Shell::view_url()`, `Shop_Pages::page_id()`.
- Produces: `Member_Dashboard::redirect_to( int $queried_id, int $dashboard_id, bool $on_member_area, bool $signed_in, string $view, string $member_url, string $login_url ): string` and `Member_Dashboard::route(): void`.

- [ ] **Step 1: Write the failing tests**

Append to `tests/php/MemberDashboardTest.php`:

```php
	private function redirect( array $over = array() ): string {
		$args = array_merge(
			array(
				'queried'    => 0,
				'dashboard'  => 0,
				'on_member'  => false,
				'signed_in'  => false,
				'view'       => '',
				'member_url' => '/member-dashboard/',
				'login_url'  => '/login/',
			),
			$over
		);
		return Blueworx_Clubhouse_Member_Dashboard::redirect_to(
			$args['queried'],
			$args['dashboard'],
			$args['on_member'],
			$args['signed_in'],
			$args['view'],
			$args['member_url'],
			$args['login_url']
		);
	}

	public function test_the_shops_old_account_page_is_carried_across(): void {
		$this->assertSame( '/member-dashboard/', $this->redirect( array( 'queried' => 42, 'dashboard' => 42 ) ) );
	}

	public function test_the_panel_asked_for_is_carried_across_with_it(): void {
		$this->assertSame(
			'/member-dashboard/?view=orders',
			$this->redirect( array( 'queried' => 42, 'dashboard' => 42, 'view' => 'orders' ) )
		);
	}

	public function test_every_other_page_is_left_alone(): void {
		$this->assertSame( '', $this->redirect( array( 'queried' => 7, 'dashboard' => 42 ) ) );
		$this->assertSame( '', $this->redirect( array( 'queried' => 0, 'dashboard' => 0 ) ) );
		// A shop with no dashboard page recorded must not swallow post id 0.
		$this->assertSame( '', $this->redirect( array( 'queried' => 0, 'dashboard' => 0, 'view' => 'orders' ) ) );
	}

	public function test_a_signed_out_visitor_to_the_member_area_is_sent_to_log_in(): void {
		$this->assertSame( '/login/', $this->redirect( array( 'on_member' => true ) ) );
	}

	public function test_a_signed_in_member_stays_on_the_member_area(): void {
		$this->assertSame( '', $this->redirect( array( 'on_member' => true, 'signed_in' => true ) ) );
	}

	/** Nothing is worth redirecting to an address we could not build. */
	public function test_no_redirect_when_the_target_address_is_unknown(): void {
		$this->assertSame( '', $this->redirect( array( 'queried' => 42, 'dashboard' => 42, 'member_url' => '' ) ) );
		$this->assertSame( '', $this->redirect( array( 'on_member' => true, 'login_url' => '' ) ) );
	}
```

- [ ] **Step 2: Run them to make sure they fail**

Run: `composer test -- --filter MemberDashboardTest`
Expected: FAIL — `redirect_to` does not exist.

- [ ] **Step 3: Write the decision**

Add to `includes/dashboard/class-member-dashboard.php`:

```php
	/**
	 * Where this request should be sent instead, or '' to stay put.
	 *
	 * Two journeys meet here. A member who followed the shop's own account link,
	 * or an old bookmark, lands on the page SureCart seeded and is carried
	 * across to ours with the panel they asked for intact. A signed-out visitor
	 * who reaches the member area is sent to the club's own login page, rather
	 * than a frame with nothing in it and no way to sign in.
	 *
	 * Pure, so both journeys are testable without a WordPress runtime.
	 *
	 * @param int    $queried_id    The post this request resolved to, 0 for none.
	 * @param int    $dashboard_id  The page id the shop recorded, 0 when it has none.
	 * @param bool   $on_member_area Whether this request is our own member-area route.
	 * @param bool   $signed_in     Whether anyone is signed in.
	 * @param string $view          The panel named in the address, '' for the overview.
	 * @param string $member_url    The member area's address, '' when it cannot be built.
	 * @param string $login_url     The club's login page, '' when it cannot be built.
	 */
	public static function redirect_to(
		int $queried_id,
		int $dashboard_id,
		bool $on_member_area,
		bool $signed_in,
		string $view,
		string $member_url,
		string $login_url
	): string {
		if ( $on_member_area ) {
			return $signed_in ? '' : $login_url;
		}
		if ( $dashboard_id <= 0 || $queried_id !== $dashboard_id || '' === $member_url ) {
			return '';
		}
		return '' === $view ? $member_url : Blueworx_Clubhouse_Dashboard_Shell::view_url( $view, $member_url );
	}

	/**
	 * Act on that decision.
	 *
	 * template_redirect, so the answer is settled before WordPress picks a
	 * template and long before anything has been sent to the browser.
	 */
	public static function route(): void {
		if ( ! function_exists( 'wp_safe_redirect' ) || ! function_exists( 'get_queried_object_id' ) ) {
			return;
		}
		$target = self::redirect_to(
			(int) get_queried_object_id(),
			Blueworx_Clubhouse_Shop_Pages::page_id( 'dashboard' ),
			Blueworx_Clubhouse_Frontend::MEMBER_AREA === Blueworx_Clubhouse_Frontend::current_page_slug(),
			function_exists( 'is_user_logged_in' ) && is_user_logged_in(),
			self::requested_view(),
			// link_url() rather than Links::url(): the link resolver is not
			// installed until rendering starts, which is after this runs.
			Blueworx_Clubhouse_Frontend::link_url( Blueworx_Clubhouse_Frontend::MEMBER_AREA ),
			Blueworx_Clubhouse_Frontend::link_url( 'login' )
		);
		if ( '' === $target ) {
			return;
		}
		wp_safe_redirect( $target, 302 );
		exit;
	}
```

Register it in `register()`, which becomes:

```php
	public static function register(): void {
		if ( ! function_exists( 'add_filter' ) ) {
			return;
		}
		Blueworx_Clubhouse_Plugin_Slot::install_wordpress();
		Blueworx_Clubhouse_Dashboard_Assets::register();
		// Priority 5: before Frontend's own 404 pass at the default 10, so a
		// signed-out visitor is moved on rather than shown a page they cannot use.
		add_action( 'template_redirect', array( self::class, 'route' ), 5 );
	}
```

- [ ] **Step 4: Run the PHP tests**

Run: `composer test`
Expected: PASS.

- [ ] **Step 5: Point the browser tests at the new address**

In `tests/member-dashboard.spec.js`, change `const DASHBOARD = '/member-area-fixture/';` to `const DASHBOARD = '/member-dashboard/';` and update the comment block above it: the fixture page is no longer where the member area renders — it is the old address that redirects, and there is a separate test for that in Task 7.

In `tests/welcome-pack.spec.js`, change every navigation to the dashboard to `/member-dashboard/`, and replace the test named `a signed-out visitor is left with the shop own content, not the pack` with:

```js
test('a signed-out visitor is sent to the club login page @wordpress', async ({ page }) => {
  await page.context().clearCookies();
  await page.goto('/member-dashboard/');
  await expect(page).toHaveURL(/\/login\/?$/);
});
```

- [ ] **Step 6: Run the browser tests you touched**

Run: `WP_ADMIN_USER=admin WP_ADMIN_PASS=wptest-admin-pw npx playwright test tests/member-dashboard.spec.js tests/welcome-pack.spec.js --workers=1`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add includes/dashboard/class-member-dashboard.php tests/php/MemberDashboardTest.php tests/member-dashboard.spec.js tests/welcome-pack.spec.js
git commit -m "Send old account links and signed-out visitors to the right place"
```

---

### Task 5: The header button becomes the way back

**Files:**
- Modify: `includes/render/class-page-renderer.php`
- Test: `tests/php/PageRendererTest.php`

**Interfaces:**
- Produces: `Blueworx_Clubhouse_Page_Renderer::header_account( bool $signed_in, bool $member_area_on, string $logout_url ): array` returning `array{0:string,1:string}` — label, href.

- [ ] **Step 1: Write the failing tests**

Append to `tests/php/PageRendererTest.php`:

```php
	public function test_the_header_offers_the_way_in_when_nobody_is_signed_in(): void {
		list( $label, $href ) = Blueworx_Clubhouse_Page_Renderer::header_account( false, true, '/out/' );
		$this->assertSame( 'Log in', $label );
		$this->assertStringContainsString( 'login', $href );
	}

	public function test_the_header_offers_the_member_area_to_a_signed_in_member(): void {
		list( $label, $href ) = Blueworx_Clubhouse_Page_Renderer::header_account( true, true, '/out/' );
		$this->assertSame( 'Member area', $label );
		$this->assertStringContainsString( 'member-dashboard', $href );
	}

	/** A club that switched the member area off must not strand a signed-in member. */
	public function test_the_header_keeps_the_way_out_when_there_is_no_member_area(): void {
		list( $label, $href ) = Blueworx_Clubhouse_Page_Renderer::header_account( true, false, '/out/' );
		$this->assertSame( 'Log out', $label );
		$this->assertSame( '/out/', $href );
	}
```

- [ ] **Step 2: Run them to make sure they fail**

Run: `composer test -- --filter PageRendererTest`
Expected: FAIL — `header_account` does not exist.

- [ ] **Step 3: Write it**

Add to `includes/render/class-page-renderer.php`, directly above `shell_header()`:

```php
	/**
	 * The header's account button: label and address.
	 *
	 * One button carries the whole account journey — the way in when nobody is
	 * signed in, and the way back to the member area when somebody is. Signing
	 * out lives inside the member area, which is where a member goes to manage
	 * everything else about their membership.
	 *
	 * A club that has switched the member area off keeps the old way out here,
	 * so a signed-in member is never left without one.
	 *
	 * @return array{0:string,1:string} label, href
	 */
	public static function header_account( bool $signed_in, bool $member_area_on, string $logout_url ): array {
		if ( ! $signed_in ) {
			return array( 'Log in', Blueworx_Clubhouse_Links::url( 'login' ) );
		}
		if ( $member_area_on ) {
			return array( 'Member area', Blueworx_Clubhouse_Links::url( 'member-dashboard' ) );
		}
		return array( 'Log out', $logout_url );
	}
```

In `shell_header()`, replace the two `'login' =>` / `'login_href' =>` array entries. Directly above the `return`, add:

```php
		list( $account_label, $account_href ) = self::header_account(
			$signed_in,
			Blueworx_Clubhouse_Page_Map::is_available( 'member-dashboard' )
				&& $visibility->is_page_visible( 'member-dashboard' ),
			$auth['logout_url']
		);
```

and change the two entries to:

```php
			'login'       => $account_label,
			'login_href'  => $account_href,
```

Update the comment above `$auth` — it currently explains offering the way out where the way in was found. Replace it with:

```php
		// The way in and the way back are the same button: "Log in" to a visitor,
		// "Member area" to a member. Off WordPress the state seam is unset, so the
		// preview keeps showing "Log in".
```

- [ ] **Step 4: Run the tests**

Run: `composer test`
Expected: PASS. `FrontEndLinkHygieneTest` renders every page with the auth seam unset, so every header still says "Log in" and no link is dead.

- [ ] **Step 5: Commit**

```bash
git add includes/render/class-page-renderer.php tests/php/PageRendererTest.php
git commit -m "Point the header button at the member area when signed in"
```

---

### Task 6: Hide WordPress's Pages menu

Removes the menu for everyone, including administrators. Nothing is deleted, and the pages themselves still serve and still answer to a typed URL.

**Files:**
- Create: `includes/admin/class-wordpress-pages.php`
- Modify: `blueworx-labs-clubhouse.php`
- Test: `tests/php/WordpressPagesTest.php`

**Interfaces:**
- Produces: `Blueworx_Clubhouse_Wordpress_Pages::MENU_SLUG` (const `'edit.php?post_type=page'`), `::register()`, `::hide_menu()`.

- [ ] **Step 1: Write the failing test**

Create `tests/php/WordpressPagesTest.php`:

```php
<?php
use PHPUnit\Framework\TestCase;

final class WordpressPagesTest extends TestCase {

	public function test_the_menu_slug_is_wordpress_own_pages_screen(): void {
		$this->assertSame( 'edit.php?post_type=page', Blueworx_Clubhouse_Wordpress_Pages::MENU_SLUG );
	}

	/**
	 * Late, so every plugin has added its menus before ours has the last word —
	 * and so a menu another plugin adds back is still taken off.
	 */
	public function test_it_hides_the_menu_after_every_plugin_has_added_its_own(): void {
		$hooks = Blueworx_Clubhouse_WP_Hooks::recorded();
		$this->assertContains(
			array( 'admin_menu', array( Blueworx_Clubhouse_Wordpress_Pages::class, 'hide_menu' ), 999 ),
			$hooks
		);
	}
}
```

**Before writing this test, read `tests/php/wp-stubs.php` and the hook-wiring test in `tests/php/FrontendTest.php`.** The project already has a shim that records `add_action` calls; use that shim's real API rather than the invented `Blueworx_Clubhouse_WP_Hooks::recorded()` above, and adjust the assertion to match it. If no such recorder exists, keep only the first test and prove the hook in the browser test in Task 7 instead.

- [ ] **Step 2: Run it to make sure it fails**

Run: `composer test -- --filter WordpressPagesTest`
Expected: FAIL — the class does not exist.

- [ ] **Step 3: Write the class**

Create `includes/admin/class-wordpress-pages.php`:

```php
<?php
// includes/admin/class-wordpress-pages.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Takes WordPress's own Pages screen off the menu.
 *
 * A club manages its site in one place — Club Pages for the words, Setup for
 * what is shown — and every page this plugin serves is a route of its own with
 * no WordPress page under it. The Pages screen only ever offered a club a
 * second, contradictory place to look.
 *
 * Nothing is deleted. The pages SureCart and LatePoint rely on are still there,
 * still published and still served; they are simply not on the menu. Anyone who
 * needs one can still type its address. Removing the menu is the reversible half
 * of the change, which is the whole reason it is the half we do.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Wordpress_Pages {

	/** WordPress's own top-level menu for pages. */
	public const MENU_SLUG = 'edit.php?post_type=page';

	public static function register(): void {
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}
		// 999, after every plugin has added its menus, so ours is the last word.
		add_action( 'admin_menu', array( self::class, 'hide_menu' ), 999 );
	}

	public static function hide_menu(): void {
		if ( function_exists( 'remove_menu_page' ) ) {
			remove_menu_page( self::MENU_SLUG );
		}
	}
}
```

- [ ] **Step 4: Boot it**

In `blueworx-labs-clubhouse.php`, find where the admin controllers are required and registered (grep for `Blueworx_Clubhouse_Setup_Controller::register`). Add the `require_once` for the new file beside the other `includes/admin/` requires, and `Blueworx_Clubhouse_Wordpress_Pages::register();` beside the other admin `::register()` calls, following whatever `is_admin()` gate the neighbours use.

- [ ] **Step 5: Run the tests**

Run: `composer test`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add includes/admin/class-wordpress-pages.php blueworx-labs-clubhouse.php tests/php/WordpressPagesTest.php
git commit -m "Take WordPress's Pages screen off the menu"
```

---

### Task 7: Prove it in a browser, then ship it

**Files:**
- Create: `tests/member-area-page.spec.js`
- Modify: `blueworx-labs-clubhouse.php` (version header), `CHANGELOG.md`, and any other file carrying the version string

**Interfaces:**
- Consumes: everything above.

- [ ] **Step 1: Write the browser spec**

Create `tests/member-area-page.spec.js`:

```js
const { test, expect } = require('@playwright/test');

// @wordpress only: these prove the routing, which the DB-free preview has none of.
//
// The harness has neither SureCart nor LatePoint installed, so what is covered
// here is the frame and the journeys around it — not the shop's own panels,
// which would be testing SureCart.

async function signIn(page) {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'wptest-admin-pw');
  await page.click('#wp-submit');
  await expect(page.locator('#wpadminbar')).toBeVisible();
}

test('the member area serves at its own club address @wordpress', async ({ page }) => {
  await signIn(page);
  await page.goto('/member-dashboard/');
  await expect(page.locator('.bw-admin.clubhouse-member')).toHaveCount(1);
});

test('the old account page carries a member across, panel and all @wordpress', async ({ page }) => {
  await signIn(page);
  await page.goto('/member-area-fixture/?view=orders');
  await expect(page).toHaveURL(/\/member-dashboard\/\?view=orders/);
});

test('the header offers the member area to a signed-in member @wordpress', async ({ page }) => {
  await signIn(page);
  await page.goto('/');
  await expect(page.locator('.ch-header').getByRole('link', { name: 'Member area' })).toBeVisible();
});

test('the header offers log in to everyone else @wordpress', async ({ page }) => {
  await page.goto('/');
  await expect(page.locator('.ch-header').getByRole('link', { name: 'Log in' })).toBeVisible();
});

test('wp-admin no longer offers the Pages screen @wordpress', async ({ page }) => {
  await signIn(page);
  await page.goto('/wp-admin/');
  await expect(page.locator('#adminmenu a[href="edit.php?post_type=page"]')).toHaveCount(0);
});
```

If the header link selector does not match the markup, read `Blueworx_Clubhouse_Sections::header()` in `includes/render/class-sections.php` and use the class it actually emits. Do not weaken the assertion to make it pass.

- [ ] **Step 2: Run it**

Run: `WP_ADMIN_USER=admin WP_ADMIN_PASS=wptest-admin-pw npx playwright test tests/member-area-page.spec.js --workers=1`
Expected: PASS. If `/member-dashboard/` 404s, the rewrite cache is stale — bumping the version in the next step is what flushes it, so do that first and re-run.

- [ ] **Step 3: Bump the version and write the changelog**

Bump `0.79.0` to `0.80.0` everywhere it appears (`grep -rn "0\.79\.0" --include='*.php' --include='*.json' .`), keeping the plugin header, any version constant and `package.json` in step.

Add at the top of `CHANGELOG.md`, above the `## 0.79.0` heading:

```markdown
## 0.80.0

- **Your members get their own page on your site, and you get one place to manage it.** The member area now lives at /member-dashboard/ on your own address, listed with your other pages and switchable off under Setup like any of them. Signed in, the Log in button in your header becomes Member area, so a member is always one click from their bookings, orders and membership. Anyone with the old link is taken to the new page automatically. WordPress's own Pages screen is gone from your menu — everything you edit lives under Club Pages and Setup now. Nothing has been deleted: the pages your shop and booking plugin rely on are all still there, doing their job quietly.
```

- [ ] **Step 4: Run everything**

```bash
composer test
WP_ADMIN_USER=admin WP_ADMIN_PASS=wptest-admin-pw npx playwright test --workers=1
composer lint
```
Expected: PHP green. Browser green except the two known-red specs named in Global Constraints. Lint findings are **reported, not fixed** — collect them for the human partner and stop there.

- [ ] **Step 5: Commit and push**

```bash
git add -A
git commit -m "Bump to 0.80.0"
git push
```

---

## Self-Review Notes

Two places where this plan knowingly departs from the spec, both recorded here rather than left to be discovered:

**Club Pages.** The spec says the member area is "listed in Club Pages". It is not, and should not be: Club Pages is the content editor, and the member area has no copy of its own to edit — its panels are the shop's and the booking plugin's, and its welcome comes from the pack that is already edited under Home. Adding a tab there would open an empty screen. It is listed under Setup → Visibility instead, which is where the switch the spec asks for actually lives.

**The main nav.** Task 5 puts the member area on the header's account button, per the decision recorded in the spec. It is deliberately not added to `Menu::DEFAULTS` — a members-only screen does not belong in a list every visitor sees. An owner who wants it there can add it, because `Link_Catalogue` offers every available page as a menu target automatically.
