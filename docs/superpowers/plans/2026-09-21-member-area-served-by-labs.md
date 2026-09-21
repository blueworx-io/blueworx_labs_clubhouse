# Member Area Served By Labs — Implementation Plan (ClubHouse side)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Delete ClubHouse's own checkout, thank-you, member-dashboard and shop-pages code and have it hook the BlueWorx Labs plugin's store pages instead, so a member of Crewe Vagrants sees exactly what they see today.

**Architecture:** One new class, `Blueworx_Clubhouse_Labs_Store`, is the only place ClubHouse touches Labs: it hooks Labs' five `blueworx_store_*` filters (Bookings view, welcome pack, profile card, club branding and links, the `/member-dashboard/` address) and wraps the three Labs functions the rest of ClubHouse calls, answering safe defaults when Labs is absent. Everything ported to Labs is deleted here. The `/member-dashboard/` route stays and renders Labs' screen.

**Tech Stack:** PHP 8.2 classes (this plugin's style), PHPUnit 11 (`composer test`), Playwright (`npm run wp:up`, `npm run wp:shop`, `npm test`), WordPress filters.

**Spec:** `c:\Users\LukeMcfarland\Documents\GitHub\blueworx_labs_wordpress\docs\superpowers\specs\2026-09-21-store-pages-design.md` (sections 3, 5, 6, 8) and the Labs API doc `docs/store-pages-api.md` in that repo. Labs 1.87.0 must be built first (plan `docs/superpowers/plans/2026-09-21-store-pages.md` in the Labs repo).

## Global Constraints

- Version becomes **0.105.0** (header and `BLUEWORX_LABS_CLUBHOUSE_VERSION` in `blueworx-labs-clubhouse.php`, `package.json`); CHANGELOG.md gets a `## 0.105.0` entry.
- Labs minimum version: **1.87.0**. Constant `Blueworx_Clubhouse_Labs_Store::MIN_LABS_VERSION = '1.87.0'`.
- Nothing about addresses changes: `/member-dashboard/`, `?view=`, the redirect from SureCart's dashboard page with `view`/`model`/`action`/`id` kept, the checkout and thank-you pages.
- No stand-down guard between the two plugins; the live switch is by hand (spec §8).
- Every Labs function is called through `Blueworx_Clubhouse_Labs_Store` and nowhere else, so "Labs missing" is one code path.
- Class names in markup change from `clubhouse-member__*` / `clubhouse-checkout__*` to `blueworx-store__*` / `blueworx-checkout__*` (Labs' names); root `clubhouse-member` → `blueworx-store`, `clubhouse-checkout` → `blueworx-checkout`, `data-clubhouse-member` → `data-blueworx-store`. ClubHouse's own profile card keeps `clubhouse-profile__*`.
- Commit after every task, trailer `Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>`. Lint once at the end.
- Work on a branch `member-area-served-by-labs` off `main`.

## The seam (used by every task)

`includes/store/class-labs-store.php` — `final class Blueworx_Clubhouse_Labs_Store`:

```php
public const MIN_LABS_VERSION = '1.87.0';
public static function register(): void;                       // hooks the filters + the notice
public static function available(): bool;                      // Labs loaded and new enough
public static function page_id( string $key ): int;            // blueworx_store_page_id() or 0
public static function page_url( string $key ): string;        // blueworx_store_page_url() or ''
public static function page_key( int $post_id ): string;       // blueworx_store_page_key() or ''
public static function screen( string $base, string $home ): string; // blueworx_store_dashboard_screen() or the unavailable card
public static function enqueue_dashboard(): void;              // blueworx_store_enqueue_dashboard() + profile.css
public static function notice_html( bool $installed, string $version ): string; // pure
// filter callbacks:
public static function views( array $views ): array;
public static function panel( string $html, string $key, array $context ): string;
public static function context( array $context ): array;
public static function checkout_links( array $links, array $context ): array;
public static function dashboard_url( string $url ): string;
```

---

### Task 0: Capture the before-switch markup

Done on `main` **before** any code changes, with SureCart in the harness. This is the seamlessness proof; nothing else in the plan makes sense without it.

**Files:**
- Create: `tests/helpers/store-markup.js`, `tests/store-markup-snapshot.spec.js`, `tests/snapshots/*.html` (generated)

- [ ] **Step 1: Write the normaliser**

`tests/helpers/store-markup.js`:

```js
// Turns the member area's markup into something that can be compared across
// the ClubHouse → Labs switch: Labs renamed the CSS classes, and nonces,
// post ids and the plugin's asset version differ per site. What is left is
// the structure and the words, which is what a member actually sees.
const RENAMES = [
  [/data-clubhouse-member/g, 'data-blueworx-store'],
  [/clubhouse-member-navtab-/g, 'blueworx-store-navtab-'],
  [/clubhouse-member-tab-/g, 'blueworx-store-tab-'],
  [/clubhouse-member-view/g, 'blueworx-store-view'],
  [/clubhouse-member__/g, 'blueworx-store__'],
  [/clubhouse-checkout__/g, 'blueworx-checkout__'],
  [/\bclubhouse-member\b/g, 'blueworx-store'],
  [/\bclubhouse-checkout\b/g, 'blueworx-checkout'],
];

function normalise(html) {
  let out = html;
  for (const [from, to] of RENAMES) out = out.replace(from, to);
  return out
    // Labs draws icons as the design system's <i data-lucide> element where
    // ClubHouse inlined the SVG; both render the same glyph, so icons are
    // reduced to a marker on both sides.
    .replace(/<svg[sS]*?</svg>/g, '<ICON>')
    .replace(/<i class="bw-icon" data-lucide="[^"]*" aria-hidden="true"></i>/g, '<ICON>')
    .replace(/_wpnonce=[a-f0-9]+/g, '_wpnonce=NONCE')
    .replace(/nonce=[a-f0-9]+/g, 'nonce=NONCE')
    .replace(/\?ver=[^"&]+/g, '?ver=VER')
    .replace(/page_id=\d+/g, 'page_id=ID')
    .replace(/\s+/g, ' ')
    .replace(/> </g, '><')
    .trim();
}

module.exports = { normalise };
```

- [ ] **Step 2: Write the spec**

`tests/store-markup-snapshot.spec.js` (CommonJS, like the other specs here):

```js
const { test, expect } = require('@playwright/test');
const { hasShop } = require('./helpers/shop');
const { normalise } = require('./helpers/store-markup');
const { ADMIN_PASS } = require('./helpers/credentials');

// Needs a shop: the member area is only served beside one.
test.beforeEach(async ({ page }) => {
  test.skip(!(await hasShop(page)), 'no shop installed — run npm run wp:shop');
});

async function signInAsMember(page) {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', 'member');
  await page.fill('#user_pass', 'wptest-member-pw');
  await page.click('#wp-submit');
  await expect(page.locator('#wpadminbar')).toBeVisible();
}

const PAGES = [
  ['member-overview', '/member-dashboard/', true],
  ['member-orders', '/member-dashboard/?view=orders', true],
  ['member-profile', '/member-dashboard/?view=profile', true],
  ['checkout', '/checkout-fixture/', false],
];

for (const [name, path, member] of PAGES) {
  test(`${name} markup is unchanged by the switch to Labs`, async ({ page }) => {
    if (member) await signInAsMember(page);
    await page.goto(path);
    const html = await page.locator('.bw-admin').first().evaluate((el) => el.outerHTML);
    expect(normalise(html)).toMatchSnapshot(`${name}.html`);
  });
}
```

- [ ] **Step 3: Capture the baseline**

```bash
npm run wp:up && npm run wp:shop
npx playwright test tests/store-markup-snapshot.spec.js --update-snapshots
git add tests/helpers/store-markup.js tests/store-markup-snapshot.spec.js tests/store-markup-snapshot.spec.js-snapshots
git commit -m "Snapshot the member area and checkout markup before they move to Labs"
```

Playwright writes snapshots next to the spec in `tests/store-markup-snapshot.spec.js-snapshots/`. Commit them: they are the "before" the rest of this plan is measured against. Open each once and confirm it is a whole frame (the sidebar, the nav, the panel), not an error page.

---

### Task 1: Put Labs in the harness

**Files:**
- Create: `bin/wp-labs.mjs`
- Modify: `package.json` (`"wp:labs": "node bin/wp-labs.mjs"`), `tests/global-setup.js`, `bin/README.md`

- [ ] **Step 1: Write the installer**

`bin/wp-labs.mjs`, modelled on `bin/wp-shop.mjs`: puts BlueWorx Labs into `.wp-test/wp/wp-content/plugins/blueworx-labs-wordpress` and activates it.

- Source, in order of preference: `BLUEWORX_LABS_DIR` env var (a local checkout — copy `blueworx-labs-wordpress.php`, `uninstall.php`, `readme.txt`, `includes/`, `assets/`, `plugin-update-checker/` with `robocopy` on Windows / `cp -R` elsewhere; never symlink — see the memory note on duplicate plugin symlinks); else the GitHub release asset `https://github.com/blueworx-io/blueworx_labs_wordpress/releases/download/v<VERSION>/blueworx-labs-wordpress-<VERSION>.zip` with `VERSION = '1.87.0'` pinned at the top of the file, unpacked with the same `unzip` → System32 `tar.exe` → `bsdtar` fallback `wp-shop.mjs` uses. `--force` re-copies.
- Activation through a PHP bootstrap like `wp-shop.mjs`'s: `activate_plugin( 'blueworx-labs-wordpress/blueworx-labs-wordpress.php', '', false, false )`, then switch off every Labs feature except `store_pages`, so Labs' login relocation, site protection and admin re-skin cannot interfere with ClubHouse's own specs:

```php
foreach ( array_keys( blueworx_get_feature_definitions() ) as $key ) {
	update_option( 'blueworx_feature_' . $key, 'store_pages' === $key ? '1' : '0' );
}
```

- [ ] **Step 2: Have global setup do it in CI**

In `tests/global-setup.js`, after the existing seeding, when `.wp-test/wp` exists and `blueworx-labs-wordpress/blueworx-labs-wordpress.php` is not under its plugins folder, run `node bin/wp-labs.mjs` with `spawnSync` and fail if it exits non-zero. CI provisions a fresh WordPress per shard, so this is what gives CI Labs. Also seed a `thanks-fixture` page and point `surecart_order_confirmation_page_id` at it (same shape as the existing checkout fixture).

- [ ] **Step 3: Prove it**

```bash
npm run wp:up && npm run wp:labs
```

Expected: the script prints that Labs is active. Then `curl -s http://127.0.0.1:<port>/checkout-fixture/ | grep -c blueworx-checkout` prints a number ≥ 1 **and** `grep -c clubhouse-checkout` also ≥ 1 — two frames, because ClubHouse's own code is still here. That is expected until Task 3 and is exactly why the live switch is done by hand.

- [ ] **Step 4: Commit**

```bash
git add bin/wp-labs.mjs package.json tests/global-setup.js bin/README.md
git commit -m "Harness: install BlueWorx Labs beside the plugin"
```

---

### Task 2: The seam class, with its unit tests

**Files:**
- Create: `includes/store/class-labs-store.php`, `assets/css/member-profile.css`
- Test: `tests/php/LabsStoreTest.php`
- Modify: `tests/php/bootstrap.php` (add the require), `blueworx-labs-clubhouse.php` (require + `register()`)

**Interfaces:**
- Produces: the class in "The seam" above.

- [ ] **Step 1: Write the failing tests**

`tests/php/LabsStoreTest.php`:

```php
<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class LabsStoreTest extends TestCase {

	public function test_bookings_view_is_added_only_when_latepoint_is_present(): void {
		Blueworx_Clubhouse_Integrations::set_detector( static fn ( string $tag ): bool => 'latepoint_customer_dashboard' === $tag );
		$views = Blueworx_Clubhouse_Labs_Store::views( array( array( 'key' => 'dashboard' ) ) );
		$this->assertSame( array( 'dashboard', 'bookings' ), array_column( $views, 'key' ) );
		$this->assertSame( 'latepoint_customer_dashboard', $views[1]['shortcode'] );
		$this->assertSame( 'both', $views[1]['where'] );

		Blueworx_Clubhouse_Integrations::set_detector( static fn ( string $tag ): bool => false );
		$this->assertSame( array( 'dashboard' ), array_column( Blueworx_Clubhouse_Labs_Store::views( array( array( 'key' => 'dashboard' ) ) ), 'key' ) );
	}

	public function test_bookings_sits_after_dashboard_and_before_the_shop_views(): void {
		Blueworx_Clubhouse_Integrations::set_detector( static fn ( string $tag ): bool => true );
		$views = Blueworx_Clubhouse_Labs_Store::views( array( array( 'key' => 'dashboard' ), array( 'key' => 'orders' ) ) );
		$this->assertSame( array( 'dashboard', 'bookings', 'orders' ), array_column( $views, 'key' ) );
	}

	public function test_the_notice_names_what_is_missing(): void {
		$this->assertStringContainsString( 'BlueWorx Labs', Blueworx_Clubhouse_Labs_Store::notice_html( false, '' ) );
		$this->assertStringContainsString( 'is not active', Blueworx_Clubhouse_Labs_Store::notice_html( false, '' ) );
		$this->assertStringContainsString( '1.86.0', Blueworx_Clubhouse_Labs_Store::notice_html( true, '1.86.0' ) );
		$this->assertStringContainsString( '1.87.0', Blueworx_Clubhouse_Labs_Store::notice_html( true, '1.86.0' ) );
	}

	public function test_dashboard_url_claim_is_the_member_area_when_it_is_served(): void {
		// Pure half: the claim is the address handed in when the member area is
		// served, and whatever Labs said otherwise.
		$this->assertSame( 'http://club.test/member-dashboard/', Blueworx_Clubhouse_Labs_Store::claim( true, 'http://club.test/member-dashboard/', '' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Labs_Store::claim( false, 'http://club.test/member-dashboard/', '' ) );
		$this->assertSame( 'http://other/', Blueworx_Clubhouse_Labs_Store::claim( false, 'http://club.test/member-dashboard/', 'http://other/' ) );
	}

	public function test_context_prefers_the_club_favicon_then_logo(): void {
		$this->assertSame( 'http://x/fav.png', Blueworx_Clubhouse_Labs_Store::crest( 'http://x/fav.png', 'http://x/logo.png' ) );
		$this->assertSame( 'http://x/logo.png', Blueworx_Clubhouse_Labs_Store::crest( '', 'http://x/logo.png' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Labs_Store::crest( '', '' ) );
	}

	public function test_without_labs_the_seam_answers_nothing(): void {
		// The test bootstrap never loads Labs, so this IS the "Labs missing" site.
		$this->assertFalse( Blueworx_Clubhouse_Labs_Store::available() );
		$this->assertSame( 0, Blueworx_Clubhouse_Labs_Store::page_id( 'dashboard' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Labs_Store::page_url( 'checkout' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Labs_Store::page_key( 5 ) );
		$this->assertStringContainsString( 'not available', Blueworx_Clubhouse_Labs_Store::screen( 'http://x/', 'http://x/' ) );
	}
}
```

Add `require_once __DIR__ . '/../../includes/store/class-labs-store.php';` to `tests/php/bootstrap.php`'s require list (near the dashboard requires; they are removed in Task 3).

- [ ] **Step 2: Run to see them fail** — `composer test -- --filter LabsStoreTest` → class not found.

- [ ] **Step 3: Write the class**

`includes/store/class-labs-store.php`. Key bodies:

```php
public static function available(): bool {
	if ( ! function_exists( 'blueworx_store_dashboard_screen' ) || ! defined( 'BLUEWORX_LABS_VERSION' ) ) {
		return false;
	}
	return version_compare( (string) BLUEWORX_LABS_VERSION, self::MIN_LABS_VERSION, '>=' );
}

public static function register(): void {
	if ( ! function_exists( 'add_filter' ) ) {
		return;
	}
	add_filter( 'blueworx_store_views', array( self::class, 'views' ) );
	add_filter( 'blueworx_store_panel', array( self::class, 'panel' ), 10, 3 );
	add_filter( 'blueworx_store_context', array( self::class, 'context' ) );
	add_filter( 'blueworx_store_checkout_links', array( self::class, 'checkout_links' ), 10, 2 );
	add_filter( 'blueworx_store_dashboard_url', array( self::class, 'dashboard_url' ) );
	add_action( 'admin_notices', array( self::class, 'render_notice' ) );
}
```

- `views()`: when `Blueworx_Clubhouse_Integrations::has_latepoint()`, insert the `bookings` entry (copied from the old `Dashboard_Views::all()`: key `bookings`, label `Bookings`, title `Bookings`, lede `What you have booked, and anything coming up.`, icon `calendar`, where `both`, shortcode `latepoint_customer_dashboard`) immediately after the entry whose key is `dashboard` (append if none).
- `panel( $html, $key, $context )`: `dashboard` → `self::welcome_pack() . $html` (port `Member_Dashboard::welcome_pack()` and `accent()`; the `<style>` with `Welcome_Pack::css()` is prepended when the pack is non-empty, as `screen()` did); `profile` → `$html . card( Profile_Form::panel( 'profile' ) )` when that is non-empty, where `card()` is `'<div class="bw-card"><div class="bw-card__body">' . $inner . '</div></div>'` (the same markup Labs' `blueworx_store_shell_card( '', … )` emits — copy it, do not call Labs, so the panel filter stays pure).
- `context( $context )`: `site_name` = `get_bloginfo( 'name' )` (unchanged, but set explicitly), `logo_url` = `self::crest( favicon, logo )` using `Branding` + `Frontend::resolve_logo()` as the old `logo_url()` did, `home_url` = `Frontend::link_url( 'home' )`, `home_label` = `'' !== $club ? 'Back to ' . $club : 'Back to the club site'`, `login_url` = `Frontend::link_url( 'login' )`, `logout_url` = `Auth::logout_url()`. `crest( string $favicon, string $logo ): string` is the pure preference.
- `checkout_links( $links, $context )`: port `Commerce_Pages::footer_links()` with the visibility and `link_url` callables resolved once (same closure pattern as the old `dress()`), appended to `$links`.
- `dashboard_url( $url )`: `self::claim( $serving, Frontend::link_url( MEMBER_AREA ), $url )` where `$serving` is the same `Page_Map::is_available( MEMBER_AREA ) && visibility->is_page_visible( MEMBER_AREA )` test `Member_Dashboard::route()` used. `claim( bool $serving, string $member_url, string $otherwise ): string` returns `$member_url` when serving and non-empty, else `$otherwise`.
- `page_id()`, `page_url()`, `page_key()`: `available() ? blueworx_store_…() : default`.
- `screen()`: `available() ? blueworx_store_dashboard_screen( $base, $home ) : self::unavailable( $home )`, where `unavailable()` is a `bw-admin bw-page` wrapper with an h1 "Member area" and the sentence "The member area is not available right now. Please try again later." plus a link back to `$home`.
- `enqueue_dashboard()`: `blueworx_store_enqueue_dashboard()` when available, then `wp_enqueue_style( 'clubhouse-member-profile', BLUEWORX_LABS_CLUBHOUSE_URL . 'assets/css/member-profile.css', array( 'blueworx-store' ), BLUEWORX_LABS_CLUBHOUSE_VERSION )`.
- `notice_html( bool $installed, string $version ): string` → a `notice notice-error` saying "Clubhouse needs the BlueWorx Labs plugin (version 1.87.0 or newer) to serve the member area, checkout and thank-you pages. It is not active." or "… You have 1.86.0." `render_notice()` prints it for `manage_options` users when `! available()`.

`assets/css/member-profile.css`: the `.clubhouse-profile*` rules from `assets/bw/bw.css` lines 670–690, with the `.clubhouse-member` ancestor selector changed to `.blueworx-store`.

In `blueworx-labs-clubhouse.php`: add the `require_once` beside the other includes and `Blueworx_Clubhouse_Labs_Store::register();` in `blueworx_labs_clubhouse_init()`. Add the header line `Requires Plugins: blueworx-labs-wordpress` — note WordPress then refuses to deactivate Labs while ClubHouse is active, so the cutover order (ClubHouse off first) matters.

- [ ] **Step 4: Run the tests** — `composer test -- --filter LabsStoreTest` → green.

- [ ] **Step 5: Commit**

```bash
git add includes/store/class-labs-store.php assets/css/member-profile.css tests/php/LabsStoreTest.php tests/php/bootstrap.php blueworx-labs-clubhouse.php
git commit -m "Hook the BlueWorx Labs store pages: bookings, welcome pack, profile card, club branding"
```

---

### Task 3: Delete the ported code and repoint every caller

**Files:**
- Delete: `includes/dashboard/class-commerce-pages.php`, `class-member-dashboard.php`, `class-dashboard-views.php`, `class-dashboard-assets.php`, `class-dashboard-actions.php`, `class-plugin-slot.php`, `includes/render/class-dashboard-shell.php`, `includes/membership/class-shop-pages.php`, `includes/admin/class-shop-pages-controller.php`, `templates/commerce.php`, `assets/bw/` (whole folder), `assets/js/member-area.js`
- Delete tests: `tests/php/CommercePagesTest.php`, `DashboardActionsTest.php`, `DashboardAssetsTest.php`, `DashboardShellTest.php`, `DashboardViewsTest.php`, `MemberDashboardActionsTest.php`, `MemberDashboardTest.php`, `PluginSlotTest.php`, `ShopPagesAutoCreateTest.php`, `ShopPagesNoticeTest.php`, `ShopPagesTest.php`
- Modify: `blueworx-labs-clubhouse.php`, `tests/php/bootstrap.php`, `includes/content/class-link-catalogue.php:72`, `includes/frontend/class-auth.php:103`, `includes/frontend/class-external-chrome.php:191`, `includes/frontend/class-frontend.php:455–495`, `includes/membership/class-surecart-products.php:180`, `includes/membership/class-welcome-pack.php:157`, `includes/render/class-page-renderer.php:1623`, `includes/frontend/class-legacy-urls.php:57` (comment only)

- [ ] **Step 1: Delete the files and their requires/registrations**

Remove the twelve source files and eleven test files. In `blueworx-labs-clubhouse.php` remove the `require_once` lines for each, `Member_Dashboard::register()`, `Commerce_Pages::register()`, `Shop_Pages_Controller::register()`, and the `Shop_Pages::ensure_confirmation()` call in the activation hook (Labs makes the page now, on `admin_init`). In `tests/php/bootstrap.php` remove their requires. Check `FontAssetsTest.php`, `NoShopNoSignInTest.php`, `ShopLinksTest.php`, `MembersHouseTest.php` and `MembersHouseStylesheetTest.php` for references to the deleted classes or to `assets/bw/` — `grep -n "Dashboard_\|Shop_Pages\|Commerce_Pages\|Plugin_Slot\|Member_Dashboard\|assets/bw" tests/php/*.php` — and rewrite each such assertion against the seam or delete it if it only tested the moved code.

- [ ] **Step 2: Repoint the callers**

- `class-link-catalogue.php:72`: `Blueworx_Clubhouse_Labs_Store::page_url( $key )`.
- `class-auth.php:103`: `Blueworx_Clubhouse_Labs_Store::page_url( 'dashboard' )`.
- `class-external-chrome.php:191`: `'' !== Blueworx_Clubhouse_Labs_Store::page_key( (int) get_queried_object_id() )`.
- `class-frontend.php` `enqueue_assets()`: the `'member'` branch becomes `Blueworx_Clubhouse_Labs_Store::enqueue_dashboard(); return;` (Labs enqueues its stylesheet, the panel script and SureCart's assets). The `'login'` branch's `Member_Dashboard::enqueue_shop_assets()` becomes a local private method `enqueue_shop_assets()` on `Frontend` with the same body (SureCart's components script and default theme, guarded) — it is the login page's, not the store's.
- `class-surecart-products.php:180`: `Blueworx_Clubhouse_Labs_Store::page_url( 'checkout' )`.
- `class-welcome-pack.php:157`: `Blueworx_Clubhouse_Labs_Store::page_id( 'dashboard' )`.
- `class-page-renderer.php:1623`: `Blueworx_Clubhouse_Labs_Store::screen( … )` with the same two arguments.
- `class-legacy-urls.php:57`: the comment now reads "ahead of Labs' store route at 5".

- [ ] **Step 3: PHP suite and a grep**

```bash
composer test
grep -rn "Blueworx_Clubhouse_Dashboard_\|Blueworx_Clubhouse_Shop_Pages\|Blueworx_Clubhouse_Commerce_Pages\|Blueworx_Clubhouse_Plugin_Slot\|Blueworx_Clubhouse_Member_Dashboard\|assets/bw" includes tests bin blueworx-labs-clubhouse.php
```

Expected: tests green; the grep prints nothing.

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "The member area, checkout and thank-you page are now served by BlueWorx Labs"
```

---

### Task 4: Update the browser specs and prove nothing changed

**Files:**
- Modify: `tests/checkout-frame.spec.js`, `tests/member-area-page.spec.js`, `tests/member-area-tabs.spec.js`, `tests/member-dashboard.spec.js`, `tests/member-sign-in.spec.js`, `tests/welcome-pack.spec.js`, `tests/shop-pages-notice.spec.js`
- Create: `tests/labs-missing.spec.js`

- [ ] **Step 1: Rename selectors**

In the six specs, apply the rename table (a search-and-replace of `clubhouse-member__` → `blueworx-store__`, `clubhouse-checkout__` → `blueworx-checkout__`, `.clubhouse-member` → `.blueworx-store`, `.clubhouse-checkout` → `.blueworx-checkout`, `data-clubhouse-member` → `data-blueworx-store`). `checkout-frame.spec.js`'s stylesheet assertion changes from `surecart.css` to `store-surecart.css`. `shop-pages-notice.spec.js`: the notice is Labs' now and prefixed "BlueWorx:"; the no-shop case still asserts no notice — change the text it looks for.

- [ ] **Step 2: The Labs-missing spec**

`tests/labs-missing.spec.js`: signs in as admin, deactivates Labs via `/wp-admin/plugins.php` (the row's Deactivate link for `blueworx-labs-wordpress`), then: `/wp-admin/` shows a notice containing "BlueWorx Labs"; `/member-dashboard/` answers 200 with the text "not available" and no PHP error; `/checkout-fixture/` shows `#shop-content` and no `.blueworx-checkout`. `finally`: reactivate Labs and re-run `node bin/wp-labs.mjs` is not needed — activation alone restores it. Skip the spec when `hasShop()` is false (the member area is not served without a shop).

- [ ] **Step 3: Run the whole suite, then the snapshot spec last**

```bash
npm run wp:up && npm run wp:labs && npm run wp:shop
npm test
npx playwright test tests/store-markup-snapshot.spec.js
```

Expected: everything green. The snapshot spec compares today's markup against Task 0's capture, class names normalised. If a snapshot differs, read the diff: a wording or structure change is a bug in the port (fix it in Labs, not by updating the snapshot); only a difference the normaliser should have hidden (a new volatile attribute) justifies extending `normalise()`.

- [ ] **Step 4: Commit**

```bash
git add tests
git commit -m "Specs follow the member area to Labs, and prove its markup is unchanged"
```

---

### Task 5: Version, changelog, docs, lint, zip

**Files:**
- Modify: `blueworx-labs-clubhouse.php`, `package.json`, `CHANGELOG.md`, `CLAUDE.md` (the plugin's own notes, if they mention the member area's files), `bin/build-zip.sh` (if it lists `assets/bw` or `templates`)

- [ ] **Step 1: Bump to 0.105.0 everywhere the version lives; changelog**

```markdown
## 0.105.0

- The member area, checkout and thank-you page are now served by the BlueWorx Labs plugin, which every BlueWorx site runs. Nothing changes for members: same addresses, same screens. Clubhouse now needs BlueWorx Labs 1.87.0 or newer, and says so in the admin if it is missing.
```

- [ ] **Step 2: Lint once, build, verify**

`composer lint` and `npm run lint` once; present findings. Build the zip per `bin/build-zip.sh`, list it with `tar -tf`, confirm no `assets/bw/` and no `templates/commerce.php` inside, forward slashes throughout, one `blueworx-labs-clubhouse-0.105.0.zip` in the parent folder.

- [ ] **Step 3: Commit, push, PR**

PR title: "Member area, checkout and thank-you page served by BlueWorx Labs". Body: what it does, that it needs Labs 1.87.0, and the cutover steps from spec §8 for crewevagrantssquash.co.uk. Attribution line `🤖 Generated with [Claude Code](https://claude.com/claude-code)`.

---

## Cutover on crewevagrantssquash.co.uk (from spec §8, restated here so it travels with the plan)

1. Merge and tag both: Labs `v1.87.0`, ClubHouse `v0.105.0`, close together.
2. On the site: deactivate ClubHouse **first** (WordPress will not let Labs be deactivated while ClubHouse requires it), then deactivate Labs. Update both. Activate Labs, then ClubHouse. On BlueWorx → Enhancements confirm Store pages is on.
3. Check, in a private window then signed in as a member: `/member-dashboard/` signed out → club login; signed in → same views as before; `?view=orders` → Orders; a Join button → the framed checkout; an "edit card" link → SureCart's form under Account; the thank-you page.
4. Rollback: reinstall ClubHouse 0.104.0 (its release zip) and switch off Store pages on BlueWorx → Enhancements. No data changes either way.

## Self-review

- Spec §3: all five filters hooked in Task 2. §5: deletions and the one new file in Tasks 2–3; `Requires Plugins` + notice in Task 2; the route renders Labs' screen (Task 3, page-renderer); the profile CSS kept locally (Task 2). §6 ClubHouse tests: selectors (Task 4), snapshot proof (Tasks 0 and 4), Labs-missing spec (Task 4). §7 version/changelog: Task 5. §8: restated above.
- Names: `Blueworx_Clubhouse_Labs_Store::page_url/page_id/page_key/screen/enqueue_dashboard` are used identically in Tasks 2 and 3; `claim()` and `crest()` are the pure halves tested in Task 2 and called from `dashboard_url()`/`context()`.
