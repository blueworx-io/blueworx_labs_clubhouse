# Member Area Shell — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the member area match the Claude Design project — a full-height sidebar, a top bar beside it, panels that switch without a page reload, and a mobile layout with a bottom tab bar.

**Architecture:** The design's CSS is already vendored in `assets/bw/bw.css` in full; the gap is markup and behaviour, not styling. `Dashboard_Shell` is restructured into a two-column shell (sidebar column + content column whose head is the top bar). Every panel is rendered server-side and all but one carries `hidden`; a small script swaps them on click and keeps the address bar in step. The nav stays real links, so with no JavaScript the page behaves exactly as it does today. Mobile replaces the sidebar with a fixed bottom tab bar.

**Tech Stack:** PHP 8.2+, WordPress plugin, PHPUnit 11, Playwright, PHPCS.

**Design source:** Claude Design project `0b906da5-c173-4d93-b806-f559a4baf924`, files `Member Dashboard.dc.html` and `Member Dashboard Mobile.dc.html`. Copies are on disk at `.design-source/` (scratch, git-ignored — add it to `.gitignore` in Task 1 if it is not already there).

**Spec:** none — this plan is the design plus the decisions recorded below. Where the design and this plan disagree, the design wins for anything visual; the decisions below are where the design shows something Clubhouse has no data for.

## Global Constraints

- **The design's CSS is vendored and must not be re-derived.** Every class the design uses already exists in `assets/bw/bw.css`. Never redefine a `bw-*` rule. New rules go **only** in the "Clubhouse additions" section at the end of that file, under `.clubhouse-member`, using the same token vocabulary (`var(--bw-space-5)`, `var(--bw-border)`, …) and never a raw colour or pixel value where a token exists.
- **The nav is real links.** Every view keeps its own address (`?view=orders`). The script is an enhancement layered on top; with JavaScript off, or before it loads, clicking a nav item must still work by navigating.
- **Third-party panels boot once.** SureCart's panels are web components and LatePoint's is a shortcode. They are rendered at page load, all of them, and only shown or hidden afterwards. Never fetch or inject a panel's markup after load.
- **Escaping:** every interpolated value goes through `Dashboard_Shell::e()`. No exceptions.
- **No new dependencies.** No build step, no framework. The script is plain ES5-compatible JavaScript in `assets/js/`, matching the other files there.
- PHP style: `declare(strict_types=1)`, the `ABSPATH` guard, tabs, the `Blueworx_Clubhouse_` prefix, and comments that explain *why*.
- `composer lint` is run once at the end of the plan; its findings go to the human, not fixed in a task.
- Browser tests need `WP_ADMIN_USER=admin WP_ADMIN_PASS=wptest-admin-pw PLAYWRIGHT_BASE_URL=http://127.0.0.1:8705` and `--workers=1`. **All three** — without `PLAYWRIGHT_BASE_URL` the entire `@wordpress` half of the suite is silently skipped and the run proves nothing.

## Decisions (made in the author's absence — each is reversible)

1. **All panels rendered, one shown.** Not fetched on demand. Third-party web components initialise at load; injecting their markup later is how a panel comes to render as an empty box.
2. **Mobile bottom bar shows at most five views**, in the declared order. Any beyond five are reached from link rows at the top of the Account panel — the pattern the design itself uses for "Plan and renewal".
3. **The sidebar's person block** shows the member's display name and email. The design shows a membership number; Clubhouse has no such field.
4. **No count badges** on nav items. The design shows counts on Bookings/Orders/Invoices; producing them means reading two plugins' records, which the member area's design deliberately does not do.
5. **The design's "Show sample/empty data" toggle is dropped** — it is a prototype control for the design tool.
6. **The brand block's subtitle is "Member area"**; the design's "Squash section" has no equivalent in Clubhouse.

---

### Task 1: The shell becomes a sidebar and a top bar

**Files:**
- Modify: `includes/render/class-dashboard-shell.php`
- Modify: `includes/dashboard/class-member-dashboard.php`
- Modify: `assets/bw/bw.css` (the "Clubhouse additions" section only, at the end)
- Modify: `.gitignore`
- Test: `tests/php/DashboardShellTest.php`, `tests/php/MemberDashboardTest.php`

**Interfaces:**
- Consumes: `Dashboard_Views::available()` entries — `key`, `label`, `title`, `lede`, `icon`.
- Produces: `Blueworx_Clubhouse_Dashboard_Shell::page( array $args ): string` — replaces the old nine-positional-argument signature. Keys: `views`, `current`, `panels` (map of view key → rendered html), `home_url`, `club_name`, `logo_url`, `base`, `logout_url`, `member_name`, `member_email`. Every key is optional except `views`, `current` and `panels`.

- [ ] **Step 1: Write the failing tests**

In `tests/php/DashboardShellTest.php`, replace the existing `page()` tests with ones for the new shape (keep every `card()`, `empty_state()`, `icon()`, `view_url()` and `bare()` test exactly as it is):

```php
	/** @return array<string,mixed> */
	private function args( array $over = array() ): array {
		return array_merge(
			array(
				'views'   => array(
					array( 'key' => 'dashboard', 'label' => 'Dashboard', 'title' => 'Your account', 'lede' => 'All of it.', 'icon' => 'layout-dashboard' ),
					array( 'key' => 'orders', 'label' => 'Orders', 'title' => 'Orders', 'lede' => 'What you bought.', 'icon' => 'shopping-cart' ),
				),
				'current' => 'dashboard',
				'panels'  => array( 'dashboard' => '<p>overview</p>', 'orders' => '<p>orders</p>' ),
			),
			$over
		);
	}

	public function test_the_sidebar_carries_the_club_and_the_member(): void {
		$html = Blueworx_Clubhouse_Dashboard_Shell::page( $this->args( array(
			'club_name'     => 'Crewe Vagrants',
			'member_name'   => 'Luke McFarland',
			'member_email'  => 'luke@example.com',
		) ) );
		$this->assertStringContainsString( 'clubhouse-member__side', $html );
		$this->assertStringContainsString( 'Crewe Vagrants', $html );
		$this->assertStringContainsString( 'bw-person__name', $html );
		$this->assertStringContainsString( 'Luke McFarland', $html );
		$this->assertStringContainsString( 'luke@example.com', $html );
		// Initials, drawn where the design puts an avatar.
		$this->assertStringContainsString( '>LM<', $html );
	}

	public function test_the_top_bar_shows_the_current_view_title(): void {
		$html = Blueworx_Clubhouse_Dashboard_Shell::page( $this->args( array( 'current' => 'orders' ) ) );
		$this->assertStringContainsString( '<h1 class="bw-pagehead__h1">Orders</h1>', $html );
		$this->assertStringContainsString( 'What you bought.', $html );
	}

	public function test_the_top_bar_sits_inside_the_content_column_beside_the_sidebar(): void {
		$html = Blueworx_Clubhouse_Dashboard_Shell::page( $this->args() );
		// The design puts the sidebar full height on the left, with the page head
		// to its right — so the head must open AFTER the sidebar has closed.
		$this->assertLessThan(
			strpos( $html, 'bw-pagehead' ),
			strpos( $html, 'clubhouse-member__side' ),
			'the sidebar must come before the top bar in source order'
		);
	}

	public function test_the_nav_marks_the_view_being_read(): void {
		$html = Blueworx_Clubhouse_Dashboard_Shell::page( $this->args( array( 'current' => 'orders' ) ) );
		$this->assertStringContainsString( 'aria-current="page"', $html );
		$this->assertSame( 1, substr_count( $html, 'is-active' ), 'exactly one nav item is active' );
	}

	public function test_the_way_back_and_the_way_out_are_both_offered(): void {
		$html = Blueworx_Clubhouse_Dashboard_Shell::page( $this->args( array(
			'home_url'   => '/',
			'logout_url' => '/out/',
		) ) );
		$this->assertStringContainsString( 'Back to the club site', $html );
		$this->assertStringContainsString( '/out/', $html );
	}

	public function test_no_sign_out_link_is_drawn_without_an_address_for_it(): void {
		$html = Blueworx_Clubhouse_Dashboard_Shell::page( $this->args() );
		$this->assertStringNotContainsString( 'Sign out', $html );
	}

	public function test_initials_cope_with_one_name_and_with_none(): void {
		$this->assertSame( 'L', Blueworx_Clubhouse_Dashboard_Shell::initials( 'Luke' ) );
		$this->assertSame( 'LM', Blueworx_Clubhouse_Dashboard_Shell::initials( '  luke   mcfarland ' ) );
		$this->assertSame( 'LB', Blueworx_Clubhouse_Dashboard_Shell::initials( 'Luke James Bell' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Dashboard_Shell::initials( '   ' ) );
	}
```

- [ ] **Step 2: Run them to see them fail**

Run: `composer test -- --filter DashboardShellTest`
Expected: FAIL — `page()` does not take an array and `initials()` does not exist.

- [ ] **Step 3: Restructure the shell**

In `includes/render/class-dashboard-shell.php`, replace `page()`, `head()` and `nav()` with the following. Leave `e()`, `view_url()`, `bare()`, `card()`, `empty_state()` and `icon()` alone except where noted in Step 4.

```php
	/**
	 * The whole member area: the club and the member down the left, the view
	 * being read to the right of them.
	 *
	 * Takes one array rather than a row of positional arguments — the design
	 * needs the club's logo, the member's name and every panel's markup, and
	 * eleven positional strings is a signature nobody can call correctly.
	 *
	 * Every panel is rendered, and every one but the current carries `hidden`.
	 * The panels are other plugins' web components and shortcodes: they come
	 * alive when the page loads, so a panel fetched later would render as an
	 * empty box. Showing and hiding what is already there costs one attribute.
	 *
	 * @param array{
	 *   views:array<int,array<string,mixed>>,
	 *   current:string,
	 *   panels:array<string,string>,
	 *   home_url?:string, club_name?:string, logo_url?:string, base?:string,
	 *   logout_url?:string, member_name?:string, member_email?:string
	 * } $args
	 */
	public static function page( array $args ): string {
		$views   = isset( $args['views'] ) && is_array( $args['views'] ) ? $args['views'] : array();
		$current = (string) ( $args['current'] ?? '' );
		$panels  = isset( $args['panels'] ) && is_array( $args['panels'] ) ? $args['panels'] : array();
		$base    = (string) ( $args['base'] ?? '' );

		return '<div class="bw-admin bw-page clubhouse-member" data-clubhouse-member>'
			. '<div class="clubhouse-member__shell">'
			. self::sidebar( $views, $current, $base, $args )
			. '<div class="clubhouse-member__main">'
			. self::head( $views, $current, $args )
			. '<main class="bw-panels" id="clubhouse-member-view">'
			. self::panels( $views, $current, $panels )
			. '</main>'
			. '</div>'
			. self::tabbar( $views, $current, $base )
			. '</div></div>';
	}

	/**
	 * Every panel, with all but one hidden. See page() for why they are all
	 * drawn. A view with nothing rendered for it is skipped rather than drawn
	 * empty.
	 *
	 * @param array<int,array<string,mixed>> $views
	 * @param array<string,string>           $panels
	 */
	private static function panels( array $views, string $current, array $panels ): string {
		$out = '';
		foreach ( $views as $view ) {
			$key  = (string) $view['key'];
			$body = (string) ( $panels[ $key ] ?? '' );
			if ( '' === $body ) {
				continue;
			}
			$out .= '<div class="clubhouse-member__panel" data-view="' . self::e( $key ) . '"'
				. ' role="tabpanel" aria-labelledby="clubhouse-member-tab-' . self::e( $key ) . '"'
				. ( $key === $current ? '' : ' hidden' ) . '>'
				. $body . '</div>';
		}
		return $out;
	}

	/**
	 * The left column: who the club is, where a member can go, and who they are
	 * signed in as. Full height, as the design draws it.
	 *
	 * @param array<int,array<string,mixed>> $views
	 * @param array<string,mixed>            $args
	 */
	private static function sidebar( array $views, string $current, string $base, array $args ): string {
		$club  = trim( (string) ( $args['club_name'] ?? '' ) );
		$logo  = trim( (string) ( $args['logo_url'] ?? '' ) );
		$home  = trim( (string) ( $args['home_url'] ?? '' ) );
		$name  = trim( (string) ( $args['member_name'] ?? '' ) );
		$email = trim( (string) ( $args['member_email'] ?? '' ) );

		$out = '<aside class="clubhouse-member__side">';

		// The brand block. A club with no logo set gets its initials in the same
		// box, so the corner is never empty.
		$out .= '<div class="clubhouse-member__brand">';
		if ( '' !== $logo ) {
			$out .= '<span class="clubhouse-member__brandmark"><img src="' . self::e( $logo ) . '" alt=""></span>';
		} elseif ( '' !== $club ) {
			$out .= '<span class="clubhouse-member__brandmark">' . self::e( self::initials( $club ) ) . '</span>';
		}
		if ( '' !== $club ) {
			$out .= '<span class="clubhouse-member__brandtext">'
				. '<span class="clubhouse-member__brandname">' . self::e( $club ) . '</span>'
				. '<span class="clubhouse-member__brandsub">Member area</span>'
				. '</span>';
		}
		$out .= '</div>';

		$out .= self::nav( $views, $current, $base );

		if ( '' !== $home ) {
			$out .= '<a class="clubhouse-member__back" href="' . self::e( $home ) . '">'
				. self::icon( 'arrow-left' ) . 'Back to the club site</a>';
		}

		// Who is signed in. The design shows a membership number here; nothing in
		// Clubhouse holds one, so the address they signed in with does the job of
		// telling a member which account they are looking at.
		if ( '' !== $name ) {
			$out .= '<div class="clubhouse-member__person"><div class="bw-person">'
				. '<span class="bw-avatar clubhouse-member__avatar">' . self::e( self::initials( $name ) ) . '</span>'
				. '<span class="clubhouse-member__persontext">'
				. '<span class="bw-person__name">' . self::e( $name ) . '</span>';
			if ( '' !== $email ) {
				$out .= '<span class="bw-person__sub">' . self::e( $email ) . '</span>';
			}
			$out .= '</span></div></div>';
		}

		return $out . '</aside>';
	}

	/**
	 * Up to two letters for an avatar: the first letter of the first word and of
	 * the last. Pure, and safe on a single word, on extra whitespace, and on
	 * nothing at all.
	 */
	public static function initials( string $name ): string {
		$words = preg_split( '/\s+/', trim( $name ), -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $words ) || array() === $words ) {
			return '';
		}
		$first = mb_substr( (string) $words[0], 0, 1 );
		$last  = count( $words ) > 1 ? mb_substr( (string) $words[ count( $words ) - 1 ], 0, 1 ) : '';
		return mb_strtoupper( $first . $last );
	}

	/**
	 * The top bar: what this view is, and the two things a member does from
	 * anywhere — leave, or sign out.
	 *
	 * @param array<int,array<string,mixed>> $views
	 * @param array<string,mixed>            $args
	 */
	private static function head( array $views, string $current, array $args ): string {
		$view   = self::view( $views, $current );
		$title  = (string) ( $view['title'] ?? '' );
		$lede   = (string) ( $view['lede'] ?? '' );
		$logout = trim( (string) ( $args['logout_url'] ?? '' ) );

		$out = '<header class="bw-pagehead clubhouse-member__head"><div class="bw-pagehead__titles">'
			. '<h1 class="bw-pagehead__h1" data-member-title>' . self::e( $title ) . '</h1>';
		if ( '' !== trim( $lede ) ) {
			$out .= '<p class="bw-pagehead__lede" data-member-lede>' . self::e( $lede ) . '</p>';
		} else {
			$out .= '<p class="bw-pagehead__lede" data-member-lede hidden></p>';
		}
		$out .= '</div><div class="bw-pagehead__actions">';
		// Nothing is drawn when there is no address to sign out to — a dead link
		// is worse than no link.
		if ( '' !== $logout ) {
			$out .= '<a class="bw-btn bw-btn--secondary bw-btn--sm" href="' . self::e( $logout ) . '">Sign out</a>';
		}
		return $out . '</div></header>';
	}

	/**
	 * One view's entry, or an empty array.
	 *
	 * @param array<int,array<string,mixed>> $views
	 * @return array<string,mixed>
	 */
	private static function view( array $views, string $key ): array {
		foreach ( $views as $view ) {
			if ( (string) $view['key'] === $key ) {
				return $view;
			}
		}
		return array();
	}

	/**
	 * The side nav. Links, not buttons: each view is its own address, openable
	 * in a new tab and working with no JavaScript at all. The script upgrades
	 * these in place — see assets/js/member-area.js.
	 *
	 * @param array<int,array<string,mixed>> $views
	 */
	private static function nav( array $views, string $current, string $base = '' ): string {
		$out = '<nav class="bw-secnav clubhouse-member__nav" aria-label="Your account">';
		foreach ( $views as $view ) {
			$key    = (string) $view['key'];
			$active = $key === $current;
			$out   .= '<a class="bw-secnav__item' . ( $active ? ' is-active' : '' ) . '"'
				. ' id="clubhouse-member-tab-' . self::e( $key ) . '"'
				. ' data-view-link="' . self::e( $key ) . '"'
				. ' href="' . self::e( self::view_url( $key, $base ) ) . '"'
				. ( $active ? ' aria-current="page"' : '' ) . '>'
				. '<span class="clubhouse-member__navlabel">'
				. self::icon( (string) $view['icon'] )
				. self::e( (string) $view['label'] )
				. '</span></a>';
		}
		return $out . '</nav>';
	}

	/**
	 * The bottom tab bar, which is what the sidebar becomes on a phone.
	 *
	 * Five at most, which is what the design draws and as many as fits a phone.
	 * Anything past the fifth is reached from the last panel — see
	 * Member_Dashboard::overflow_links().
	 *
	 * @param array<int,array<string,mixed>> $views
	 */
	private static function tabbar( array $views, string $current, string $base = '' ): string {
		$shown = array_slice( $views, 0, self::TABBAR_MAX );
		if ( count( $shown ) < 2 ) {
			return '';
		}
		$out = '<nav class="clubhouse-member__tabbar" aria-label="Your account">';
		foreach ( $shown as $view ) {
			$key    = (string) $view['key'];
			$active = $key === $current;
			$out   .= '<a class="clubhouse-member__tab' . ( $active ? ' is-active' : '' ) . '"'
				. ' data-view-link="' . self::e( $key ) . '"'
				. ' href="' . self::e( self::view_url( $key, $base ) ) . '"'
				. ( $active ? ' aria-current="page"' : '' ) . '>'
				. self::icon( (string) $view['icon'] )
				. '<span class="clubhouse-member__tablabel">' . self::e( (string) $view['label'] ) . '</span>'
				. '</a>';
		}
		return $out . '</nav>';
	}

	/** How many views the phone's bottom bar can carry. The design draws five. */
	public const TABBAR_MAX = 5;
```

Note the `is-active` count test: the tab bar also marks the current view, so `substr_count` would find two. Make the test count `data-view-link` occurrences of the active pattern instead — **write the test as given in Step 1 and, when it fails on the count, change the assertion to `2` with a comment saying why (the side nav and the phone's tab bar both mark it).** Do not remove the assertion.

- [ ] **Step 4: Teach `bare()` the new structure**

`bare()` still takes its five positional arguments and must keep working — it is checkout and order confirmation, which are not part of this redesign. Give it its own head rather than calling the new one:

```php
	public static function bare( string $title, string $lede, string $body, string $home_url, string $club_name ): string {
		$head = '<header class="bw-pagehead"><div class="bw-pagehead__titles">';
		if ( '' !== trim( $club_name ) ) {
			$head .= '<p class="bw-pagehead__eyebrow">' . self::e( $club_name ) . '</p>';
		}
		$head .= '<h1 class="bw-pagehead__h1">' . self::e( $title ) . '</h1>';
		if ( '' !== trim( $lede ) ) {
			$head .= '<p class="bw-pagehead__lede">' . self::e( $lede ) . '</p>';
		}
		$head .= '</div><div class="bw-pagehead__actions">'
			. '<a class="bw-btn bw-btn--secondary" href="' . self::e( $home_url ) . '">'
			. self::icon( 'arrow-left' ) . 'Back to the club site</a>'
			. '</div></header>';
		return '<div class="bw-admin bw-page clubhouse-member">' . $head
			. '<div class="bw-page__body"><main class="bw-panels">' . $body . '</main></div></div>';
	}
```

- [ ] **Step 5: Call it from the member area**

In `includes/dashboard/class-member-dashboard.php`, `screen()` currently renders one view. Change it to render every available view into a map and hand the shell one array. Replace the body of `screen()` with:

```php
	public static function screen( string $base, string $home ): string {
		$views   = Blueworx_Clubhouse_Dashboard_Views::available(
			Blueworx_Clubhouse_SureCart_Products::is_active(),
			Blueworx_Clubhouse_Integrations::has_latepoint()
		);
		$current = Blueworx_Clubhouse_Dashboard_Views::resolve( self::requested_view(), $views );
		if ( array() === $views ) {
			return '';
		}

		// Every panel is drawn, not just the one being read — see
		// Dashboard_Shell::page() for why. The welcome pack belongs to the
		// overview only.
		$welcome = self::welcome_pack();
		$panels  = array();
		foreach ( $views as $view ) {
			$key            = (string) $view['key'];
			$panels[ $key ] = Blueworx_Clubhouse_Dashboard_Views::DEFAULT_VIEW === $key
				? self::overview( $welcome, $views, $home, $base )
				: self::view_body( $view, '', $home );
		}

		$style = '' !== $welcome
			? '<style>' . Blueworx_Clubhouse_Welcome_Pack::css( ...self::accent() ) . '</style>'
			: '';

		return $style . Blueworx_Clubhouse_Dashboard_Shell::page( array(
			'views'        => $views,
			'current'      => $current,
			'panels'       => $panels,
			'home_url'     => $home,
			'club_name'    => self::club_name(),
			'logo_url'     => self::logo_url(),
			'base'         => $base,
			'logout_url'   => self::logout_url(),
			'member_name'  => self::member_name(),
			'member_email' => self::member_email(),
		) );
	}

	/** The club's logo for the sidebar's brand block, or '' when none is set. */
	private static function logo_url(): string {
		if ( ! class_exists( 'Blueworx_Clubhouse_Frontend' ) || ! class_exists( 'Blueworx_Clubhouse_Options_Storage' ) ) {
			return '';
		}
		$branding = new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Options_Storage() );
		return Blueworx_Clubhouse_Frontend::resolve_logo( $branding->get_logo() );
	}

	/** The signed-in member's name, or '' off WordPress. */
	private static function member_name(): string {
		if ( ! function_exists( 'wp_get_current_user' ) ) {
			return '';
		}
		$user = wp_get_current_user();
		if ( ! is_object( $user ) ) {
			return '';
		}
		$name = trim( (string) ( $user->display_name ?? '' ) );
		return '' !== $name ? $name : trim( (string) ( $user->user_login ?? '' ) );
	}

	/** The address they signed in with, which tells them which account this is. */
	private static function member_email(): string {
		if ( ! function_exists( 'wp_get_current_user' ) ) {
			return '';
		}
		$user = wp_get_current_user();
		return is_object( $user ) ? trim( (string) ( $user->user_email ?? '' ) ) : '';
	}
```

Keep `overview()`, `view_body()`, `welcome_pack()`, `accent()`, `club_name()`, `logout_url()` and `requested_view()` exactly as they are.

- [ ] **Step 6: Add the layout CSS**

Append to the "Clubhouse additions" section at the **end** of `assets/bw/bw.css` (do not touch anything above it). Keep the existing rules there; add these after them:

```css
/* The two-column shell the design draws: the club and the member down the
   left, full height, with the page head to their right. */
.clubhouse-member .clubhouse-member__shell{display:flex;align-items:stretch;min-height:100vh}
.clubhouse-member .clubhouse-member__side{flex:0 0 268px;display:flex;flex-direction:column;gap:var(--bw-space-5);padding:var(--bw-space-7) var(--bw-space-6);border-right:1px solid var(--bw-border);background:var(--bw-surface-card)}
.clubhouse-member .clubhouse-member__main{flex:1 1 auto;min-width:0;display:flex;flex-direction:column}
.clubhouse-member .clubhouse-member__head{padding:var(--bw-space-7) var(--bw-space-8)}
.clubhouse-member .bw-panels{padding:var(--bw-space-8);flex:1 1 auto}
.clubhouse-member .clubhouse-member__brand{display:flex;align-items:center;gap:var(--bw-space-5);min-width:0;padding-bottom:var(--bw-space-5)}
.clubhouse-member .clubhouse-member__brandmark{display:inline-flex;align-items:center;justify-content:center;width:44px;height:44px;flex:none;border-radius:var(--bw-radius-sm);background:var(--bw-brand-wash);color:var(--bw-text-accent);font-family:var(--bw-font-display);font-weight:var(--bw-weight-semibold);overflow:hidden}
.clubhouse-member .clubhouse-member__brandmark img{width:100%;height:100%;object-fit:contain}
.clubhouse-member .clubhouse-member__brandtext{display:flex;flex-direction:column;min-width:0}
.clubhouse-member .clubhouse-member__brandname{font-family:var(--bw-font-display);font-weight:var(--bw-weight-semibold);color:var(--bw-text-heading);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.clubhouse-member .clubhouse-member__brandsub{font-size:var(--bw-size-sm);color:var(--bw-text-muted)}
.clubhouse-member .clubhouse-member__nav{flex:0 0 auto}
/* The spacer the design uses to push the way out to the foot of the column. */
.clubhouse-member .clubhouse-member__back{margin-top:auto;display:flex;align-items:center;gap:var(--bw-space-3);font-size:var(--bw-size-sm);color:var(--bw-text-muted);text-decoration:none}
.clubhouse-member .clubhouse-member__back:hover{color:var(--bw-text-accent)}
.clubhouse-member .clubhouse-member__person{padding-top:var(--bw-space-5);border-top:1px solid var(--bw-border)}
.clubhouse-member .clubhouse-member__avatar{width:36px;height:36px;font-size:var(--bw-size-sm)}
.clubhouse-member .clubhouse-member__persontext{min-width:0;overflow:hidden}
.clubhouse-member .bw-person__name,.clubhouse-member .bw-person__sub{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
/* The phone's bottom bar is drawn only on a phone — see the media query in
   Task 4. Hidden here so a desktop never sees two navs. */
.clubhouse-member .clubhouse-member__tabbar{display:none}
```

- [ ] **Step 7: Ignore the design scratch directory**

Add `.design-source/` to `.gitignore` if it is not already listed. It is fetched design source, not part of the plugin.

- [ ] **Step 8: Run the tests**

Run: `composer test`
Expected: PASS. `MemberDashboardTest`'s `screen()` tests will need their assertions updated where they assumed one panel — update them to the new markup rather than deleting them, and say in your report which you changed and why.

- [ ] **Step 9: Commit**

```bash
git add includes/render/class-dashboard-shell.php includes/dashboard/class-member-dashboard.php assets/bw/bw.css .gitignore tests/php/DashboardShellTest.php tests/php/MemberDashboardTest.php
git commit -m "Draw the member area as the design draws it"
```

---

### Task 2: Switching views without reloading the page

**Files:**
- Create: `assets/js/member-area.js`
- Modify: `includes/frontend/class-frontend.php`
- Test: `tests/member-area-tabs.spec.js`

**Interfaces:**
- Consumes: the markup Task 1 produces — `[data-clubhouse-member]`, `[data-view-link="KEY"]`, `.clubhouse-member__panel[data-view="KEY"]`, `[data-member-title]`, `[data-member-lede]`.
- Produces: `assets/js/member-area.js`, enqueued by `Frontend::enqueue_assets()` on the member area only.

- [ ] **Step 1: Write the script**

Create `assets/js/member-area.js`:

```js
/**
 * Switching panels in the member area without reloading the page.
 *
 * Every panel is already on the page — the shop's and the booking plugin's
 * own components, which come alive when the page loads. So this shows and
 * hides what is there rather than fetching anything.
 *
 * An enhancement, not the mechanism: every nav item is a real link to a real
 * address. With this script absent, blocked or still loading, clicking one
 * navigates and the server draws the same view. Nothing here is required for
 * the page to work.
 */
(function () {
	'use strict';

	var root = document.querySelector('[data-clubhouse-member]');
	if (!root || !window.history || !window.history.pushState) {
		return;
	}

	var TITLES = {};
	var panels = root.querySelectorAll('.clubhouse-member__panel');
	var i;

	// The title and lede for each view, read off the links so the server stays
	// the single source of what a view is called.
	var links = root.querySelectorAll('[data-view-link]');
	for (i = 0; i < links.length; i++) {
		TITLES[links[i].getAttribute('data-view-link')] = {
			title: links[i].getAttribute('data-view-title') || '',
			lede: links[i].getAttribute('data-view-lede') || ''
		};
	}

	function show(key, push, href) {
		var found = false;
		var j;
		for (j = 0; j < panels.length; j++) {
			var mine = panels[j].getAttribute('data-view') === key;
			panels[j].hidden = !mine;
			if (mine) {
				found = true;
			}
		}
		if (!found) {
			return false;
		}

		for (j = 0; j < links.length; j++) {
			var active = links[j].getAttribute('data-view-link') === key;
			// classList.toggle's second argument is unsupported in older
			// browsers; add and remove are not.
			if (active) {
				links[j].classList.add('is-active');
				links[j].setAttribute('aria-current', 'page');
			} else {
				links[j].classList.remove('is-active');
				links[j].removeAttribute('aria-current');
			}
		}

		var head = TITLES[key] || { title: '', lede: '' };
		var h1 = root.querySelector('[data-member-title]');
		var lede = root.querySelector('[data-member-lede]');
		if (h1 && head.title) {
			h1.textContent = head.title;
			document.title = head.title;
		}
		if (lede) {
			lede.textContent = head.lede;
			lede.hidden = !head.lede;
		}

		if (push && href) {
			window.history.pushState({ clubhouseView: key }, '', href);
		}
		// The panel is what changed, so that is what a screen reader should be
		// taken to — the same place a page load would have left them.
		var view = document.getElementById('clubhouse-member-view');
		if (view) {
			view.setAttribute('tabindex', '-1');
			view.focus();
		}
		return true;
	}

	root.addEventListener('click', function (event) {
		// Let a middle-click, a modified click or a right-click do what the
		// browser would: these are real links and open in a new tab.
		if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
			return;
		}
		var link = event.target.closest ? event.target.closest('[data-view-link]') : null;
		if (!link) {
			return;
		}
		var key = link.getAttribute('data-view-link');
		if (show(key, true, link.getAttribute('href'))) {
			event.preventDefault();
		}
	});

	window.addEventListener('popstate', function (event) {
		var key = event.state && event.state.clubhouseView;
		if (!key) {
			// Arrived back at the address the page was loaded on.
			key = root.getAttribute('data-view-initial') || '';
		}
		if (key) {
			show(key, false, '');
		}
	});
})();
```

- [ ] **Step 2: Give the script what it reads**

In `includes/render/class-dashboard-shell.php`, the script reads each view's title and lede off its nav link, and the initial view off the root. Add to `page()`'s opening div:

```php
		return '<div class="bw-admin bw-page clubhouse-member" data-clubhouse-member data-view-initial="' . self::e( $current ) . '">'
```

and in **both** `nav()` and `tabbar()`, add these two attributes to each link, immediately after `data-view-link`:

```php
				. ' data-view-title="' . self::e( (string) ( $view['title'] ?? '' ) ) . '"'
				. ' data-view-lede="' . self::e( (string) ( $view['lede'] ?? '' ) ) . '"'
```

- [ ] **Step 3: Enqueue it**

In `includes/frontend/class-frontend.php`, in `enqueue_assets()`'s `'member' === $family` branch, after the two existing calls and before the `return`:

```php
			// Switching panels without a reload. Deferred and enhancement-only:
			// the nav is real links, so the page works while this is still on
			// its way and if it never arrives at all.
			wp_enqueue_script(
				'clubhouse-member-area',
				BLUEWORX_LABS_CLUBHOUSE_URL . 'assets/js/member-area.js',
				array(),
				BLUEWORX_LABS_CLUBHOUSE_VERSION,
				true
			);
```

- [ ] **Step 4: Write the browser test**

Create `tests/member-area-tabs.spec.js`:

```js
const { test, expect } = require('@playwright/test');

// @wordpress only: the member area is a real route, and what is being proved
// here is that a click does NOT reload the page — which needs a real browser.

async function signIn(page) {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'wptest-admin-pw');
  await page.click('#wp-submit');
  await expect(page.locator('#wpadminbar')).toBeVisible();
}

test.beforeEach(async ({ page }) => {
  await signIn(page);
});

test('the member area draws the sidebar and the top bar @wordpress', async ({ page }) => {
  await page.goto('/member-dashboard/');
  await expect(page.locator('.clubhouse-member__side')).toHaveCount(1);
  await expect(page.locator('.clubhouse-member__side .bw-secnav')).toHaveCount(1);
  await expect(page.locator('.clubhouse-member__main .bw-pagehead')).toHaveCount(1);
});

test('every panel is on the page, with one shown @wordpress', async ({ page }) => {
  await page.goto('/member-dashboard/');
  const panels = page.locator('.clubhouse-member__panel');
  // The harness has neither SureCart nor LatePoint, so there is one view.
  await expect(panels).not.toHaveCount(0);
  await expect(page.locator('.clubhouse-member__panel:not([hidden])')).toHaveCount(1);
});

test('a nav item is a real link to a real address @wordpress', async ({ page }) => {
  await page.goto('/member-dashboard/');
  const href = await page.locator('[data-view-link]').first().getAttribute('href');
  expect(href).toBeTruthy();
  expect(href).not.toBe('#');
});
```

**If the harness has only one view** (it has neither SureCart nor LatePoint installed, so `available()` returns just the dashboard), a click-to-switch test cannot be written against it honestly. In that case:
- keep the three tests above, and
- prove the switching itself in a **unit-style** browser test: navigate to `/member-dashboard/`, use `page.evaluate` to inject a second panel and a second `[data-view-link]` into the live DOM, click it, and assert the first panel became hidden and the second visible and that `location.search` changed **without** a navigation (assert by setting `window.__stayed = true` before the click and reading it after).

Say in your report which route you took and why.

- [ ] **Step 5: Run the tests**

```
composer test
WP_ADMIN_USER=admin WP_ADMIN_PASS=wptest-admin-pw PLAYWRIGHT_BASE_URL=http://127.0.0.1:8705 npx playwright test tests/member-area-tabs.spec.js tests/member-area-page.spec.js --workers=1
```
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add assets/js/member-area.js includes/frontend/class-frontend.php includes/render/class-dashboard-shell.php tests/member-area-tabs.spec.js
git commit -m "Switch member area panels without reloading"
```

---

### Task 3: The phone layout

**Files:**
- Modify: `assets/bw/bw.css` (Clubhouse additions section only)
- Modify: `includes/dashboard/class-member-dashboard.php`
- Test: `tests/php/MemberDashboardTest.php`, `tests/member-area-tabs.spec.js`

**Interfaces:**
- Consumes: `Dashboard_Shell::TABBAR_MAX` and the `.clubhouse-member__tabbar` markup from Task 1.
- Produces: `Blueworx_Clubhouse_Member_Dashboard::overflow_links( array $views, string $base ): string` — link rows for views the phone's bottom bar cannot carry, '' when there are none.

- [ ] **Step 1: Write the failing test**

Append to `tests/php/MemberDashboardTest.php`:

```php
	/** @return array<int,array<string,mixed>> */
	private function sixViews(): array {
		$out = array();
		foreach ( array( 'dashboard', 'bookings', 'orders', 'invoices', 'plans', 'account' ) as $key ) {
			$out[] = array( 'key' => $key, 'label' => ucfirst( $key ), 'title' => ucfirst( $key ), 'lede' => '', 'icon' => 'users' );
		}
		return $out;
	}

	public function test_views_the_phone_bar_cannot_carry_are_linked_from_the_last_panel(): void {
		$html = Blueworx_Clubhouse_Member_Dashboard::overflow_links( $this->sixViews(), '/member-dashboard/' );
		// Five fit; the sixth does not, so it is offered here instead.
		$this->assertStringContainsString( 'view=account', $html );
		$this->assertStringNotContainsString( 'view=orders', $html );
	}

	public function test_nothing_is_offered_when_every_view_fits(): void {
		$this->assertSame( '', Blueworx_Clubhouse_Member_Dashboard::overflow_links( array_slice( $this->sixViews(), 0, 3 ), '/x/' ) );
	}
```

- [ ] **Step 2: Run it to see it fail**

Run: `composer test -- --filter MemberDashboardTest`
Expected: FAIL — `overflow_links` does not exist.

- [ ] **Step 3: Write it**

Add to `includes/dashboard/class-member-dashboard.php`:

```php
	/**
	 * The views the phone's bottom bar has no room for, offered as link rows.
	 *
	 * The bar holds five, which is what the design draws and as many as fits a
	 * phone. The design solves the same problem the same way — its phone layout
	 * reaches "Plan and renewal" from the account panel rather than the bar.
	 * Drawn on every panel and hidden above the phone breakpoint, where the
	 * sidebar carries every view already.
	 *
	 * @param array<int,array<string,mixed>> $views
	 */
	public static function overflow_links( array $views, string $base ): string {
		$extra = array_slice( $views, Blueworx_Clubhouse_Dashboard_Shell::TABBAR_MAX );
		if ( array() === $extra ) {
			return '';
		}
		$out = '<nav class="clubhouse-member__more" aria-label="More of your account">';
		foreach ( $extra as $view ) {
			$out .= '<a class="clubhouse-member__morelink" data-view-link="' . htmlspecialchars( (string) $view['key'], ENT_QUOTES, 'UTF-8' ) . '"'
				. ' data-view-title="' . htmlspecialchars( (string) ( $view['title'] ?? '' ), ENT_QUOTES, 'UTF-8' ) . '"'
				. ' data-view-lede="' . htmlspecialchars( (string) ( $view['lede'] ?? '' ), ENT_QUOTES, 'UTF-8' ) . '"'
				. ' href="' . htmlspecialchars( Blueworx_Clubhouse_Dashboard_Shell::view_url( (string) $view['key'], $base ), ENT_QUOTES, 'UTF-8' ) . '">'
				. Blueworx_Clubhouse_Dashboard_Shell::icon( (string) $view['icon'] )
				. '<span>' . htmlspecialchars( (string) $view['label'], ENT_QUOTES, 'UTF-8' ) . '</span>'
				. '</a>';
		}
		return $out . '</nav>';
	}
```

Then, in `screen()`, append it to every panel — it belongs to the phone layout, which is why it is on each one rather than only the last:

```php
		$more   = self::overflow_links( $views, $base );
		$panels = array();
		foreach ( $views as $view ) {
			$key            = (string) $view['key'];
			$panels[ $key ] = ( Blueworx_Clubhouse_Dashboard_Views::DEFAULT_VIEW === $key
				? self::overview( $welcome, $views, $home, $base )
				: self::view_body( $view, '', $home ) ) . $more;
		}
```

- [ ] **Step 4: Write the phone CSS**

Append to the Clubhouse additions section of `assets/bw/bw.css`:

```css
/* The phone. The sidebar becomes a fixed bar along the bottom, as the design
   draws it: five destinations, icon over label, and the page head shrinks to
   the view's name. 782px is WordPress's own phone breakpoint, which the rest
   of this stylesheet already uses. */
@media (max-width:782px){
	.clubhouse-member .clubhouse-member__shell{flex-direction:column;min-height:auto}
	.clubhouse-member .clubhouse-member__side{flex:none;width:100%;border-right:0;border-bottom:1px solid var(--bw-border);padding:var(--bw-space-5) var(--bw-space-6);gap:var(--bw-space-4)}
	/* The sidebar keeps the club and the member on a phone; its nav and its way
	   out are the bottom bar's job, so they are not drawn twice. */
	.clubhouse-member .clubhouse-member__side .clubhouse-member__nav,
	.clubhouse-member .clubhouse-member__side .clubhouse-member__back{display:none}
	.clubhouse-member .clubhouse-member__brand{padding-bottom:0}
	.clubhouse-member .clubhouse-member__person{padding-top:0;border-top:0;margin-left:auto}
	.clubhouse-member .clubhouse-member__person .bw-person__sub{display:none}
	.clubhouse-member .clubhouse-member__side{flex-direction:row;align-items:center}
	.clubhouse-member .clubhouse-member__head{padding:var(--bw-space-6)}
	.clubhouse-member .bw-panels{padding:var(--bw-space-6)}
	/* Clear of the fixed bar, so the last card is never trapped behind it. */
	.clubhouse-member .clubhouse-member__main{padding-bottom:76px}
	.clubhouse-member .clubhouse-member__tabbar{display:grid;grid-auto-flow:column;grid-auto-columns:1fr;position:fixed;left:0;right:0;bottom:0;z-index:10;background:var(--bw-surface-card);border-top:1px solid var(--bw-border);padding-bottom:env(safe-area-inset-bottom)}
	.clubhouse-member .clubhouse-member__tab{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;padding:var(--bw-space-4) 2px;color:var(--bw-text-muted);text-decoration:none;font-size:11px;text-align:center}
	.clubhouse-member .clubhouse-member__tab.is-active{color:var(--bw-text-accent)}
	.clubhouse-member .clubhouse-member__tablabel{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:100%}
	.clubhouse-member .clubhouse-member__more{display:flex;flex-direction:column;border:1px solid var(--bw-border);border-radius:var(--bw-radius-sm);background:var(--bw-surface-card);overflow:hidden}
	.clubhouse-member .clubhouse-member__morelink{display:flex;align-items:center;gap:var(--bw-space-4);padding:var(--bw-space-5) var(--bw-space-6);color:var(--bw-text-body);text-decoration:none}
	.clubhouse-member .clubhouse-member__morelink+.clubhouse-member__morelink{border-top:1px solid var(--bw-border)}
}
/* Above the phone breakpoint the sidebar carries every view, so the overflow
   rows are noise. */
.clubhouse-member .clubhouse-member__more{display:none}
```

- [ ] **Step 5: Add the phone browser test**

Append to `tests/member-area-tabs.spec.js`:

```js
test('a phone gets the bottom bar and not the sidebar nav @wordpress', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/member-dashboard/');
  await expect(page.locator('.clubhouse-member__side .bw-secnav')).toBeHidden();
  // The bar is drawn only when there is more than one place to go, and the
  // harness has neither SureCart nor LatePoint — so assert on the rule, not on
  // the element: the sidebar's nav must not be what a phone navigates with.
  await expect(page.locator('.clubhouse-member__brand')).toBeVisible();
});
```

If the harness turns out to have more than one view available, strengthen that test to assert `.clubhouse-member__tabbar` is visible and the sidebar nav is not. Say which in your report.

- [ ] **Step 6: Run the tests**

```
composer test
WP_ADMIN_USER=admin WP_ADMIN_PASS=wptest-admin-pw PLAYWRIGHT_BASE_URL=http://127.0.0.1:8705 npx playwright test tests/member-area-tabs.spec.js --workers=1
```

- [ ] **Step 7: Commit**

```bash
git add assets/bw/bw.css includes/dashboard/class-member-dashboard.php tests/php/MemberDashboardTest.php tests/member-area-tabs.spec.js
git commit -m "Give the member area a phone layout"
```

---

### Task 4: Ship it

**Files:**
- Modify: `blueworx-labs-clubhouse.php`, `package.json`, `CHANGELOG.md`

- [ ] **Step 1: Run everything**

```
composer test
WP_ADMIN_USER=admin WP_ADMIN_PASS=wptest-admin-pw PLAYWRIGHT_BASE_URL=http://127.0.0.1:8705 npx playwright test --workers=1
```
Expected: all green. Any failure is real — fix it if it is in this plan's work, report it and stop if it is not.

- [ ] **Step 2: Bump the version**

Bump `0.80.0` to `0.81.0` everywhere: `grep -rn "0\.80\.0" --include='*.php' --include='*.json' . | grep -v node_modules`. Keep the plugin header, the version constant and `package.json` in step.

- [ ] **Step 3: Write the changelog**

Add above the `## 0.80.0` heading in `CHANGELOG.md`:

```markdown
## 0.81.0

- **The member area now looks the way it was designed, and moves like an app.** Your club's name and badge sit at the top of a proper side menu, with the member's own name at the foot of it, and the page title tells them where they are. Moving between bookings, orders, invoices and the rest no longer reloads the page — it switches instantly, and the back button still works. On a phone the side menu becomes a row of tabs along the bottom, thumb height, so a member can get around one-handed.
```

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "Bump to 0.81.0"
```

---

## Self-Review Notes

**What this plan does not do.** The design shows things Clubhouse has no data for: counts beside each nav item, a "next up" booking card, stat tiles, a renewal notice, and a cancel-confirmation sheet. Every one of those means reading SureCart's or LatePoint's records and re-rendering them, which the member area's original design ruled out and this plan does not revisit. What is implemented is the shell — the sidebar, the top bar, the switching and the phone layout — with each plugin's own panel inside it, exactly as before.

**The riskiest part** is Task 1's change to `Dashboard_Shell::page()`, which every member-area test touches. It is first for that reason: everything after it builds on the new markup.
