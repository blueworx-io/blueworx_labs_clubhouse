# Membership tiers connected to SureCart — implementation plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let a club owner connect each membership tier to a SureCart price, so the card shows what that price really charges and Join goes to checkout with the tier pre-filled.

**Architecture:** A products adapter is the only thing that knows SureCart exists — everything else sees a plain array with a formatted amount and period. The adapter reaches the renderer through a static seam (`Products_Source`), copying the `Links` pattern rather than threading a new parameter through eleven page methods that the block rebuild is about to delete. Tiers store only a price id; prices are read live and cached.

**Tech Stack:** PHP 8.1+, PHPUnit 10 (`composer test`), PHP_CodeSniffer (`composer lint`), Playwright (`npx playwright test`). SureCart is the integration; no new dependencies.

Spec: `docs/superpowers/specs/2026-08-13-membership-tiers-and-surecart-design.md`.

## Global Constraints

- **No new dependencies.** `approved-deps.json` governs npm and needs prior approval; this plan needs nothing new.
- **Only one class may reference SureCart.** `Blueworx_Clubhouse_SureCart_Products` (Task 6). Everything else — renderer, admin, tests, preview — talks to the `Blueworx_Clubhouse_Products` interface. A `SureCart` symbol anywhere else is a defect.
- **Pure classes stay WordPress-free.** No `esc_*`, `get_option`, `wp_*`, `add_action` outside the WordPress-facing classes (`SureCart_Products`, and the controller/frontend wiring). Escaping happens in the renderer and screen as it does today.
- **No autoloader.** Every new runtime class is explicitly required in `includes/bootstrap.php`, after anything it references at load time.
- **Coding standard:** tabs, `declare(strict_types=1);`, `if ( ! defined( 'ABSPATH' ) ) { exit; }` guard, WordPress spacing inside parentheses, `final class` (except the interface), `@package BlueworxLabsClubhouse` docblock. Run `composer lint` before each commit.
- **Existing club sites must not change.** A tier with no connected price renders exactly as it does today — typed price, contact link. Every task keeps the full suite green.
- **Version bump and changelog** happen once, in Task 7. Minor bump — new capability.
- **Commit after every task**, one plain line. Never skip git hooks.

---

## File Structure

| File | Responsibility |
| --- | --- |
| `docs/integrations/surecart-notes.md` | What SureCart actually does — how to read a price, how to pre-fill a basket. Written from a real install in Task 1; the source Tasks 3 and 6 build against. |
| `includes/membership/interface-products.php` | The two questions the rest of the plugin may ask about prices. |
| `includes/membership/class-demo-products.php` | Fixed prices for tests and the DB-free preview. Runtime class, like `Demo_Collections`. |
| `includes/membership/class-products-source.php` | The seam: holds the environment's products adapter, or none. |
| `includes/membership/class-checkout.php` | Builds the checkout URL for a price. Pure; the base URL is installed by the environment. |
| `includes/membership/class-surecart-products.php` | The only SureCart-aware class: reads prices, formats money, caches, invalidates. |
| `includes/render/class-page-renderer.php` | Modify `membership_tiers()` — connected tiers take their price and link from the adapter. |
| `includes/admin/class-content-catalogue.php` | Modify — the tiers loop gains the product field, its options supplied by the adapter. |
| `includes/admin/class-content-controller.php` | Modify — pass the adapter when rendering and when saving. |
| `includes/frontend/class-frontend.php` | Modify — install the real adapter and the checkout base URL. |
| `preview/index.php` | Modify — install `Demo_Products` so the preview shows connected tiers. |

---

### Task 1: Find out what SureCart actually does

Everything downstream rests on two facts nobody in this repo currently knows: how to read a price's amount and interval, and how to hand SureCart a checkout URL with an item already in the basket. Guessing here produces code that looks right and takes no money.

**This task needs a WordPress site with SureCart installed and at least one product with a recurring price.** The club's demo site is one. If you cannot reach such a site, stop and say so — do not infer the answers from documentation you cannot run, and do not proceed to Task 2 assuming them.

**Files:**
- Create: `docs/integrations/surecart-notes.md`

**Interfaces:**
- Consumes: nothing.
- Produces: the notes file. Tasks 3 and 6 read it. Nothing else in this plan may contradict it.

- [ ] **Step 1: Establish how a price is read in PHP**

On the site with SureCart active, in a context where the plugin is loaded (WP-CLI `wp eval`, or a scratch mu-plugin — not a file left in the repo), determine:

- The class or function that fetches one price by id, and what it returns.
- Where the amount lives on that object, in what units (SureCart stores money in minor units — pence), and where the currency is.
- Where the recurring interval lives — the unit ("month") and the count (1) — and how a one-off price differs from a recurring one.
- The product's name, reached from the price.
- How to list all prices for the picker, and whether archived or draft prices come back.

Record the exact expressions that worked, with their real output.

- [ ] **Step 2: Establish how a checkout URL pre-fills a basket**

On the same site, find the URL form that lands on checkout with a chosen price already in the basket. Test it in a browser and confirm the checkout shows that item with the right price. Record:

- The checkout page's URL, and how SureCart is told which page that is.
- The exact query parameters, spelled exactly, including how quantity is expressed.
- What happens with an unknown or archived price id — does it error, or land on an empty checkout?

- [ ] **Step 3: Write the notes**

Create `docs/integrations/surecart-notes.md` recording, for each of the two questions: the working expression or URL, its real output, the SureCart version tested, and the date. Where something did not work, record that too — a dead end saved is a dead end nobody repeats.

Keep it factual. This file is a record of what was observed, not a design.

- [ ] **Step 4: Commit**

```bash
git add docs/integrations/surecart-notes.md
git commit -m "docs: record how SureCart prices and checkout links actually work"
```

---

### Task 2: The products interface, the demo adapter, and the seam

**Files:**
- Create: `includes/membership/interface-products.php`
- Create: `includes/membership/class-demo-products.php`
- Create: `includes/membership/class-products-source.php`
- Create: `tests/php/ProductsSourceTest.php`
- Modify: `includes/bootstrap.php`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `Blueworx_Clubhouse_Products` — `prices(): array<int,array{id:string,product:string,label:string,amount:string,period:string}>` and `price( string $id ): ?array{id:string,product:string,label:string,amount:string,period:string}`.
  - `Blueworx_Clubhouse_Demo_Products` implementing it, with three fixed prices.
  - `Blueworx_Clubhouse_Products_Source::set( ?Blueworx_Clubhouse_Products )`, `::get(): ?Blueworx_Clubhouse_Products`.

Field meanings, fixed here and relied on by every later task: `amount` is display-ready and includes the currency symbol (`"£28"`); `period` is the suffix the tier card already renders beside the price (`"/mo"`, `"/yr"`, or `""` for a one-off); `label` is for the admin picker only (`"Adult membership — £28/mo"`); `product` is the product's name.

- [ ] **Step 1: Write the failing test**

Create `tests/php/ProductsSourceTest.php`:

```php
<?php

use PHPUnit\Framework\TestCase;

final class ProductsSourceTest extends TestCase {

	protected function tearDown(): void {
		// A leaked adapter would silently change what every later test renders.
		Blueworx_Clubhouse_Products_Source::set( null );
	}

	public function test_nothing_is_installed_by_default(): void {
		$this->assertNull( Blueworx_Clubhouse_Products_Source::get() );
	}

	public function test_an_adapter_can_be_installed_and_removed(): void {
		Blueworx_Clubhouse_Products_Source::set( new Blueworx_Clubhouse_Demo_Products() );
		$this->assertInstanceOf( Blueworx_Clubhouse_Products::class, Blueworx_Clubhouse_Products_Source::get() );

		Blueworx_Clubhouse_Products_Source::set( null );
		$this->assertNull( Blueworx_Clubhouse_Products_Source::get() );
	}

	public function test_the_demo_adapter_lists_prices_the_picker_can_show(): void {
		$prices = ( new Blueworx_Clubhouse_Demo_Products() )->prices();
		$this->assertNotSame( array(), $prices );
		foreach ( $prices as $price ) {
			$this->assertNotSame( '', $price['id'] );
			$this->assertNotSame( '', $price['product'] );
			$this->assertNotSame( '', $price['label'] );
			$this->assertStringStartsWith( '£', $price['amount'] );
			$this->assertContains( $price['period'], array( '/mo', '/yr', '' ) );
		}
	}

	public function test_a_known_price_comes_back_whole(): void {
		$demo  = new Blueworx_Clubhouse_Demo_Products();
		$price = $demo->price( 'price_adult_monthly' );
		$this->assertSame( 'price_adult_monthly', $price['id'] );
		$this->assertSame( '£28', $price['amount'] );
		$this->assertSame( '/mo', $price['period'] );
	}

	public function test_an_unknown_price_is_null_not_an_empty_array(): void {
		// Null is the single fallback signal the renderer branches on. An empty
		// array would be truthy-adjacent and invite a wrong check.
		$this->assertNull( ( new Blueworx_Clubhouse_Demo_Products() )->price( 'price_nope' ) );
	}
}
```

- [ ] **Step 2: Run it and watch it fail**

Run: `composer test -- --filter ProductsSourceTest`
Expected: FAIL — `Class "Blueworx_Clubhouse_Products_Source" not found`.

- [ ] **Step 3: Write the interface**

Create `includes/membership/interface-products.php`:

```php
<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The only two questions the plugin asks about what a membership costs.
 *
 * A seam, for the same reason Collections and Links are: the renderer, the
 * admin screen, the unit tests and the DB-free preview all have to work with no
 * shop plugin present. The implementation that knows SureCart exists is the one
 * class allowed to; everything else sees the plain arrays described below.
 *
 * A price array is display-ready:
 *   id      the store's own identifier, stored on the tier
 *   product the product's name, e.g. "Adult membership"
 *   label   for the admin picker, e.g. "Adult membership — £28/mo"
 *   amount  with its currency symbol, e.g. "£28"
 *   period  the suffix beside the price: "/mo", "/yr", or "" for a one-off
 *
 * Formatting lives in the implementation because only it knows the currency and
 * the minor units the store keeps money in.
 *
 * @package BlueworxLabsClubhouse
 */
interface Blueworx_Clubhouse_Products {

	/**
	 * Every price an owner may connect a tier to.
	 *
	 * @return array<int,array{id:string,product:string,label:string,amount:string,period:string}>
	 */
	public function prices(): array;

	/**
	 * One price, or null when it is unknown, archived or the store is gone. Null
	 * is the fallback signal: the card then shows the club's typed price.
	 *
	 * @return array{id:string,product:string,label:string,amount:string,period:string}|null
	 */
	public function price( string $id ): ?array;
}
```

- [ ] **Step 4: Write the demo adapter**

Create `includes/membership/class-demo-products.php`:

```php
<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fixed prices for the DB-free preview and the tests — the Demo_Collections of
 * memberships. Deliberately a runtime class rather than a test fake: the
 * preview is a real caller and must render connected tiers without a shop.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Demo_Products implements Blueworx_Clubhouse_Products {

	/** @return array<int,array{id:string,product:string,label:string,amount:string,period:string}> */
	public function prices(): array {
		return array(
			array( 'id' => 'price_junior_monthly', 'product' => 'Junior membership', 'label' => 'Junior membership — £12/mo', 'amount' => '£12', 'period' => '/mo' ),
			array( 'id' => 'price_adult_monthly', 'product' => 'Adult membership', 'label' => 'Adult membership — £28/mo', 'amount' => '£28', 'period' => '/mo' ),
			array( 'id' => 'price_adult_yearly', 'product' => 'Adult membership', 'label' => 'Adult membership — £300/yr', 'amount' => '£300', 'period' => '/yr' ),
		);
	}

	/** @return array{id:string,product:string,label:string,amount:string,period:string}|null */
	public function price( string $id ): ?array {
		foreach ( $this->prices() as $price ) {
			if ( $price['id'] === $id ) {
				return $price;
			}
		}
		return null;
	}
}
```

- [ ] **Step 5: Write the seam**

Create `includes/membership/class-products-source.php`:

```php
<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Holds the environment's products adapter. WordPress installs the SureCart one,
 * the preview installs the demo one, and tests install whichever they need.
 *
 * A static seam rather than a parameter threaded through every page method, for
 * the same reason Links is one: the renderer is shared by WordPress and the
 * preview, one optional dependency does not justify changing eleven signatures,
 * and those signatures are being replaced wholesale by the block rebuild.
 *
 * Null — nothing installed — is a first-class state: no shop, and every tier
 * falls back to the price its club typed.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Products_Source {

	private static ?Blueworx_Clubhouse_Products $products = null;

	public static function set( ?Blueworx_Clubhouse_Products $products ): void {
		self::$products = $products;
	}

	public static function get(): ?Blueworx_Clubhouse_Products {
		return self::$products;
	}
}
```

- [ ] **Step 6: Require the three files and run the test**

In `includes/bootstrap.php`, after the existing `includes/content/` requires:

```php
require_once __DIR__ . '/membership/interface-products.php';
require_once __DIR__ . '/membership/class-demo-products.php';
require_once __DIR__ . '/membership/class-products-source.php';
```

Run: `composer test -- --filter ProductsSourceTest`
Expected: PASS, 5 tests.

- [ ] **Step 7: Lint and commit**

```bash
composer lint
git add includes/membership tests/php/ProductsSourceTest.php includes/bootstrap.php
git commit -m "feat: add the membership products seam"
```

---

### Task 3: The checkout link

**Files:**
- Create: `includes/membership/class-checkout.php`
- Create: `tests/php/CheckoutLinkTest.php`
- Modify: `includes/bootstrap.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `Blueworx_Clubhouse_Checkout::set_base_url( string )`, `::base_url(): string`, `::url( string $price_id ): string` — the checkout URL for a price, or `''` when there is no checkout page or no price id.

Read `docs/integrations/surecart-notes.md` from Task 1 before writing this and use the parameter names it records. The test below asserts the shape recorded there; if Task 1 found different parameter names, use those in both the code and the test, and say so in your report.

`''` is the "cannot link" signal, and the renderer treats it exactly like an unresolvable price — fall back to the contact page. A checkout page that does not exist must never produce a dead link.

- [ ] **Step 1: Write the failing test**

Create `tests/php/CheckoutLinkTest.php`:

```php
<?php

use PHPUnit\Framework\TestCase;

final class CheckoutLinkTest extends TestCase {

	protected function setUp(): void {
		Blueworx_Clubhouse_Checkout::set_base_url( 'https://club.test/checkout/' );
	}

	protected function tearDown(): void {
		Blueworx_Clubhouse_Checkout::set_base_url( '' );
	}

	public function test_a_price_becomes_a_checkout_url_carrying_it(): void {
		$url = Blueworx_Clubhouse_Checkout::url( 'price_adult_monthly' );
		$this->assertStringStartsWith( 'https://club.test/checkout/?', $url );
		$this->assertStringContainsString( 'price_adult_monthly', $url );
	}

	public function test_no_checkout_page_means_no_link_rather_than_a_broken_one(): void {
		Blueworx_Clubhouse_Checkout::set_base_url( '' );
		$this->assertSame( '', Blueworx_Clubhouse_Checkout::url( 'price_adult_monthly' ) );
	}

	public function test_no_price_means_no_link(): void {
		$this->assertSame( '', Blueworx_Clubhouse_Checkout::url( '' ) );
	}

	public function test_a_base_url_that_already_has_a_query_keeps_it(): void {
		Blueworx_Clubhouse_Checkout::set_base_url( 'https://club.test/?page_id=42' );
		$url = Blueworx_Clubhouse_Checkout::url( 'price_adult_monthly' );
		$this->assertStringContainsString( 'page_id=42', $url );
		$this->assertStringContainsString( '&', $url );
	}

	public function test_a_price_id_is_url_encoded(): void {
		// Ids come from a third party. One with a reserved character must not be
		// able to add a parameter of its own.
		$url = Blueworx_Clubhouse_Checkout::url( 'price&admin=1' );
		$this->assertStringNotContainsString( 'price&admin=1', $url );
		$this->assertStringContainsString( 'price%26admin%3D1', $url );
	}
}
```

- [ ] **Step 2: Run it and watch it fail**

Run: `composer test -- --filter CheckoutLinkTest`
Expected: FAIL — `Class "Blueworx_Clubhouse_Checkout" not found`.

- [ ] **Step 3: Write the class**

Create `includes/membership/class-checkout.php`. Replace the two constants' values with what Task 1 recorded if they differ:

```php
<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The link from a membership tier to a checkout with that tier in the basket.
 *
 * Pure: the environment installs the checkout page's URL (WordPress asks the
 * shop where its checkout lives; the preview and tests set their own), and this
 * builds the link. Empty base or empty price means no link at all — a tier then
 * falls back to the contact page rather than offering a dead button.
 *
 * The parameter names below are what the shop was observed to accept — see
 * docs/integrations/surecart-notes.md. They live here as constants because they
 * are the one thing in this plugin that a shop update could change.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Checkout {

	/** The query parameter naming the price to buy. */
	public const PRICE_PARAM = 'line_items[0][price_id]';

	/** The query parameter naming how many of it. */
	public const QUANTITY_PARAM = 'line_items[0][quantity]';

	private static string $base_url = '';

	public static function set_base_url( string $url ): void {
		self::$base_url = $url;
	}

	public static function base_url(): string {
		return self::$base_url;
	}

	/** The checkout URL for a price, or '' when we cannot build one. */
	public static function url( string $price_id ): string {
		if ( '' === self::$base_url || '' === $price_id ) {
			return '';
		}
		$separator = ( false !== strpos( self::$base_url, '?' ) ) ? '&' : '?';

		return self::$base_url . $separator
			. rawurlencode( self::PRICE_PARAM ) . '=' . rawurlencode( $price_id )
			. '&' . rawurlencode( self::QUANTITY_PARAM ) . '=1';
	}
}
```

- [ ] **Step 4: Require it and run the test**

In `includes/bootstrap.php`, after the products-source require:

```php
require_once __DIR__ . '/membership/class-checkout.php';
```

Run: `composer test -- --filter CheckoutLinkTest`
Expected: PASS, 5 tests.

- [ ] **Step 5: Lint and commit**

```bash
composer lint
git add includes/membership/class-checkout.php includes/bootstrap.php tests/php/CheckoutLinkTest.php
git commit -m "feat: build the checkout link for a membership price"
```

---

### Task 4: Connected tiers render their real price

**Files:**
- Modify: `includes/render/class-page-renderer.php` — `membership_tiers()`, currently at lines 252-283
- Create: `tests/php/MembershipTierPricingTest.php`

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Products_Source::get()`, `Blueworx_Clubhouse_Checkout::url()`, and the tier field `price_id`.
- Produces: no new public API. `membership_tiers()` keeps its signature — the adapter arrives through the seam, not a parameter.

The rule, per tier: when `price_id` is set, an adapter is installed, `price()` returns a price, **and** the checkout URL is non-empty, take `price`, `period` and `cta_href` from the shop. Otherwise change nothing. All four conditions — a half-connected tier that shows a shop price but links to the contact page would misprice the club's own page.

- [ ] **Step 1: Write the failing test**

Create `tests/php/MembershipTierPricingTest.php`:

```php
<?php

use PHPUnit\Framework\TestCase;

final class MembershipTierPricingTest extends TestCase {

	protected function tearDown(): void {
		Blueworx_Clubhouse_Products_Source::set( null );
		Blueworx_Clubhouse_Checkout::set_base_url( '' );
	}

	/** A store with a checkout, and one tier connected to the adult monthly price. */
	private function connected(): Blueworx_Clubhouse_Content_Store {
		Blueworx_Clubhouse_Products_Source::set( new Blueworx_Clubhouse_Demo_Products() );
		Blueworx_Clubhouse_Checkout::set_base_url( 'https://club.test/checkout/' );

		$content = new Blueworx_Clubhouse_Content_Store( new Blueworx_Clubhouse_Fake_Storage() );
		$content->set_items(
			'membership',
			'tiers',
			array(
				array( 'name' => 'Adult', 'price' => '£99', 'period' => '/yr', 'features' => "One\nTwo", 'cta_label' => 'Join', 'price_id' => 'price_adult_monthly' ),
				array( 'name' => 'Social', 'price' => '£12', 'period' => '/mo', 'features' => 'One', 'cta_label' => 'Join', 'price_id' => '' ),
			)
		);
		return $content;
	}

	private function tiers( Blueworx_Clubhouse_Content_Store $content ): string {
		return Blueworx_Clubhouse_Sections::tier_grid(
			Blueworx_Clubhouse_Page_Renderer::membership_tiers_for_test( $content ),
			2
		);
	}

	public function test_a_connected_tier_shows_the_shops_price_not_the_typed_one(): void {
		$html = $this->tiers( $this->connected() );
		$this->assertStringContainsString( '£28', $html );
		$this->assertStringNotContainsString( '£99', $html );
	}

	public function test_a_connected_tier_links_to_checkout_carrying_its_price(): void {
		$html = $this->tiers( $this->connected() );
		$this->assertStringContainsString( 'https://club.test/checkout/', $html );
		$this->assertStringContainsString( 'price_adult_monthly', $html );
	}

	public function test_an_unconnected_tier_is_untouched(): void {
		$html = $this->tiers( $this->connected() );
		// The second tier keeps its typed price and its contact link.
		$this->assertStringContainsString( '£12', $html );
		$this->assertStringContainsString( '?page=contact', $html );
	}

	public function test_a_deleted_price_falls_back_to_the_typed_price(): void {
		Blueworx_Clubhouse_Products_Source::set( new Blueworx_Clubhouse_Demo_Products() );
		Blueworx_Clubhouse_Checkout::set_base_url( 'https://club.test/checkout/' );

		$content = new Blueworx_Clubhouse_Content_Store( new Blueworx_Clubhouse_Fake_Storage() );
		$content->set_items( 'membership', 'tiers', array(
			array( 'name' => 'Adult', 'price' => '£99', 'period' => '/yr', 'features' => 'One', 'cta_label' => 'Join', 'price_id' => 'price_gone' ),
		) );

		$html = $this->tiers( $content );
		$this->assertStringContainsString( '£99', $html );
		$this->assertStringContainsString( '?page=contact', $html );
	}

	public function test_no_checkout_page_means_no_checkout_link_even_when_connected(): void {
		Blueworx_Clubhouse_Products_Source::set( new Blueworx_Clubhouse_Demo_Products() );
		Blueworx_Clubhouse_Checkout::set_base_url( '' );

		$content = new Blueworx_Clubhouse_Content_Store( new Blueworx_Clubhouse_Fake_Storage() );
		$content->set_items( 'membership', 'tiers', array(
			array( 'name' => 'Adult', 'price' => '£99', 'period' => '/yr', 'features' => 'One', 'cta_label' => 'Join', 'price_id' => 'price_adult_monthly' ),
		) );

		// Half-connected is worse than not connected: the club's own page would
		// advertise the shop's price and send the visitor to a contact form.
		$html = $this->tiers( $content );
		$this->assertStringContainsString( '£99', $html );
		$this->assertStringNotContainsString( '£28', $html );
	}

	public function test_with_no_shop_installed_nothing_changes(): void {
		Blueworx_Clubhouse_Products_Source::set( null );
		Blueworx_Clubhouse_Checkout::set_base_url( '' );

		$content = new Blueworx_Clubhouse_Content_Store( new Blueworx_Clubhouse_Fake_Storage() );
		$content->set_items( 'membership', 'tiers', array(
			array( 'name' => 'Adult', 'price' => '£99', 'period' => '/yr', 'features' => 'One', 'cta_label' => 'Join', 'price_id' => 'price_adult_monthly' ),
		) );

		$html = $this->tiers( $content );
		$this->assertStringContainsString( '£99', $html );
		$this->assertStringContainsString( '?page=contact', $html );
	}
}
```

- [ ] **Step 2: Run it and watch it fail**

Run: `composer test -- --filter MembershipTierPricingTest`
Expected: FAIL — `membership_tiers_for_test` does not exist.

- [ ] **Step 3: Expose the tier builder, then connect it**

`membership_tiers()` is private. Add a thin public wrapper beside it so the test can reach it without making the whole renderer public:

```php
	/**
	 * membership_tiers() for tests. The tier list is the one piece of this
	 * renderer with logic worth testing on its own — everything else is markup
	 * assembly covered by the page tests.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function membership_tiers_for_test( ?Blueworx_Clubhouse_Content_Store $content ): array {
		return self::membership_tiers( $content );
	}
```

Then, inside `membership_tiers()`, replace the `return array_map( … )` at the end with a map that applies the connection. The existing per-field mapping is unchanged; the new block is the tail:

```php
		return array_map(
			static function ( array $t ): array {
				$tier = array(
					'eyebrow'     => (string) ( $t['eyebrow'] ?? '' ),
					'name'        => (string) ( $t['name'] ?? '' ),
					'price'       => (string) ( $t['price'] ?? '' ),
					'period'      => (string) ( $t['period'] ?? '' ),
					'features'    => self::lines( $t['features'] ?? array() ),
					'recommended' => (bool) ( $t['featured'] ?? ( $t['recommended'] ?? false ) ),
					'cta_label'   => (string) ( $t['cta_label'] ?? '' ),
					'cta_href'    => (string) ( $t['cta_href'] ?? '' ),
				);

				// A tier connected to a real price shows what that price charges and
				// sells it. Anything missing — no shop, no such price, no checkout
				// page — leaves the tier exactly as the club typed it, which is how
				// every site behaved before this existed.
				$price_id = (string) ( $t['price_id'] ?? '' );
				if ( '' === $price_id ) {
					return $tier;
				}
				$price = Blueworx_Clubhouse_Products_Source::get()?->price( $price_id );
				if ( null === $price ) {
					return $tier;
				}
				$checkout = Blueworx_Clubhouse_Checkout::url( $price_id );
				if ( '' === $checkout ) {
					// Deliberately all-or-nothing: showing the shop's price beside a
					// contact link would advertise a price the visitor cannot pay.
					return $tier;
				}

				$tier['price']    = $price['amount'];
				$tier['period']   = $price['period'];
				$tier['cta_href'] = $checkout;
				return $tier;
			},
			$items
		);
```

- [ ] **Step 4: Run the new tests, then the whole suite**

Run: `composer test -- --filter MembershipTierPricingTest`
Expected: PASS, 6 tests.

Run: `composer test`
Expected: PASS, whole suite. Existing membership and home page tests must be untouched — they have no `price_id`, so they take the first early return.

- [ ] **Step 5: Lint and commit**

```bash
composer lint
git add includes/render/class-page-renderer.php tests/php/MembershipTierPricingTest.php
git commit -m "feat: connected membership tiers show the shop's price and sell it"
```

---

### Task 5: The owner picks a product

**Files:**
- Modify: `includes/admin/class-content-catalogue.php` — `pages()` and the membership `tiers` section
- Modify: `includes/admin/class-content-controller.php` — every `Content_Catalogue::pages()` call site
- Create: `tests/php/ContentCatalogueProductsTest.php`

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Products_Source::get()`.
- Produces: `Blueworx_Clubhouse_Content_Catalogue::pages( ?Blueworx_Clubhouse_Products $products = null )` — unchanged output except the membership tiers loop, which gains a `price_id` select whose options come from `$products`.

Why a `select` and not a new field type: the screen already renders selects and `Content_Sanitiser` already validates a select's value against its own options, rejecting anything not offered. Supplying the options from the adapter gets validation for free. **The controller must pass the same adapter when saving as when rendering** — sanitising against an empty option list would wipe every connection on the first Save.

Three states the field handles through its options alone, so no new UI plumbing is needed:

- Nothing installed, or no prices — the only option is "Not connected", and the section's note says why.
- A stored id that no longer resolves — the select renders an extra option for that value, selected, labelled so the owner sees what happened rather than finding the field innocently reading "Not connected". The rule, stated plainly in that label: the connection is cleared the next time this page is saved, and meanwhile visitors see the typed price. That is honest and needs no new storage; silently resetting is what must not happen.

- [ ] **Step 1: Write the failing test**

Create `tests/php/ContentCatalogueProductsTest.php`:

```php
<?php

use PHPUnit\Framework\TestCase;

final class ContentCatalogueProductsTest extends TestCase {

	/** @return array<string,mixed> The membership tiers section. */
	private function tiers_section( ?Blueworx_Clubhouse_Products $products ): array {
		foreach ( Blueworx_Clubhouse_Content_Catalogue::pages( $products ) as $page ) {
			if ( 'membership' !== $page['tab'] ) {
				continue;
			}
			foreach ( $page['sections'] as $section ) {
				if ( 'tiers' === $section['key'] ) {
					return $section;
				}
			}
		}
		$this->fail( 'the membership tiers section has gone' );
	}

	/** @return array<string,mixed> The price_id field of that section's loop. */
	private function price_field( ?Blueworx_Clubhouse_Products $products ): array {
		foreach ( $this->tiers_section( $products )['loop']['fields'] as $field ) {
			if ( 'price_id' === $field['key'] ) {
				return $field;
			}
		}
		$this->fail( 'the tier has no product field' );
	}

	public function test_with_a_shop_every_price_is_offered(): void {
		$options = $this->price_field( new Blueworx_Clubhouse_Demo_Products() )['options'];
		$this->assertSame( '', array_key_first( $options ) );
		$this->assertArrayHasKey( 'price_adult_monthly', $options );
		$this->assertSame( 'Adult membership — £28/mo', $options['price_adult_monthly'] );
	}

	public function test_with_no_shop_only_not_connected_is_offered(): void {
		$options = $this->price_field( null )['options'];
		$this->assertSame( array( '' ), array_keys( $options ) );
	}

	public function test_the_section_says_why_there_is_nothing_to_connect_to(): void {
		$note = (string) $this->tiers_section( null )['note'];
		$this->assertNotSame( '', $note );
	}

	public function test_the_field_is_a_select_so_it_validates_against_its_options(): void {
		$this->assertSame( 'select', $this->price_field( new Blueworx_Clubhouse_Demo_Products() )['type'] );
	}

	/** A value the shop no longer offers must be rejected, not stored. */
	public function test_sanitising_rejects_a_price_that_is_not_offered(): void {
		$field = $this->price_field( new Blueworx_Clubhouse_Demo_Products() );
		$this->assertSame( '', Blueworx_Clubhouse_Content_Sanitiser::field( $field, 'price_made_up', true ) );
		$this->assertSame( 'price_adult_monthly', Blueworx_Clubhouse_Content_Sanitiser::field( $field, 'price_adult_monthly', true ) );
	}

	public function test_no_other_section_gained_a_product_field(): void {
		$found = 0;
		foreach ( Blueworx_Clubhouse_Content_Catalogue::pages( new Blueworx_Clubhouse_Demo_Products() ) as $page ) {
			foreach ( $page['sections'] as $section ) {
				foreach ( $section['loop']['fields'] ?? array() as $field ) {
					if ( 'price_id' === $field['key'] ) {
						++$found;
					}
				}
			}
		}
		$this->assertSame( 1, $found );
	}
}
```

- [ ] **Step 2: Run it and watch it fail**

Run: `composer test -- --filter ContentCatalogueProductsTest`
Expected: FAIL — `pages()` takes no argument, and there is no `price_id` field.

- [ ] **Step 3: Give the catalogue the adapter**

In `includes/admin/class-content-catalogue.php`, change the signature and thread it to the tiers section:

```php
	/**
	 * @param Blueworx_Clubhouse_Products|null $products The shop, when there is one.
	 *                                                   Its prices become the tier
	 *                                                   product picker's options.
	 * @return array<int,array<string,mixed>>
	 */
	public static function pages( ?Blueworx_Clubhouse_Products $products = null ): array {
```

Add the two helpers below to the class:

```php
	/**
	 * The options for a tier's product picker: "Not connected" first, then every
	 * price the shop offers. With no shop there is only the first, and the
	 * section's note explains why — an empty dropdown explains nothing.
	 *
	 * @return array<string,string>
	 */
	private static function price_options( ?Blueworx_Clubhouse_Products $products ): array {
		$options = array( '' => 'Not connected — use the price typed above' );
		if ( null === $products ) {
			return $options;
		}
		foreach ( $products->prices() as $price ) {
			$options[ (string) $price['id'] ] = (string) $price['label'];
		}
		return $options;
	}

	/** The note under the tiers section, which depends on whether there is a shop to connect to. */
	private static function tiers_note( ?Blueworx_Clubhouse_Products $products ): string {
		if ( null === $products ) {
			return 'Connect a tier to a product to take payment for it. No shop is installed yet, so tiers show the price you type here and their button goes to the contact page.';
		}
		if ( array() === $products->prices() ) {
			return 'Connect a tier to a product to take payment for it. Your shop has no products yet — add one, and it will appear here.';
		}
		return 'Connect a tier to a product and the card shows what that product charges, with its button going straight to checkout. Change the price in the shop and this page follows. A tier left unconnected shows the price you type here.';
	}
```

Replace the membership `tiers` section entry with one carrying the note and the new field:

```php
					array( 'key' => 'tiers', 'label' => 'Tiers', 'type' => 'loop', 'store_page' => 'membership',
						'note' => self::tiers_note( $products ),
						'loop' => array( 'name' => 'Tier', 'plural' => 'Tiers', 'fields' => array(
							self::f_text( 'name', 'Name' ),
							self::f_text( 'price', 'Price' ),
							self::f_text( 'period', 'Period' ),
							self::f_area( 'features', 'Features (one per line)', 4 ),
							self::f_toggle( 'featured', 'Most popular' ),
							self::f_text( 'cta_label', 'CTA label' ),
							self::f_select( 'price_id', 'Sells', self::price_options( $products ) ),
						) ) ),
```

- [ ] **Step 4: Pass the adapter from the controller**

In `includes/admin/class-content-controller.php`, find every `Blueworx_Clubhouse_Content_Catalogue::pages()` call and pass the installed adapter:

```php
Blueworx_Clubhouse_Content_Catalogue::pages( Blueworx_Clubhouse_Products_Source::get() )
```

Both the render path and the save path, without exception. A save that sanitises against an empty option list wipes every connection on the site — this is the one line in this plan that can destroy an owner's data.

Leave `Content_Catalogue::index()` calling `pages()` with no argument: it exists to name sections for humans, and the picker's options do not affect a label.

- [ ] **Step 5: Tell the owner when a connected product has vanished**

A stored id that is no longer offered would otherwise render as "Not connected", which is a lie — the tier is connected, to something that has gone. Add to `tests/php/ContentCatalogueProductsTest.php`:

```php
	public function test_a_vanished_product_is_shown_as_such_rather_than_as_not_connected(): void {
		$field = $this->price_field( new Blueworx_Clubhouse_Demo_Products() );
		$html  = Blueworx_Clubhouse_Content_Screen::field_html( $field, 'price_vanished', 'tier-0-price_id' );

		// The stale value is still the selected one...
		$this->assertStringContainsString( 'value="price_vanished"', $html );
		$this->assertMatchesRegularExpression( '/value="price_vanished"[^>]*selected/', $html );
		// ...and it says what happened, rather than reading as "Not connected".
		$this->assertStringContainsString( 'no longer', $html );
	}

	public function test_a_normal_value_gains_no_extra_option(): void {
		$field = $this->price_field( new Blueworx_Clubhouse_Demo_Products() );
		$html  = Blueworx_Clubhouse_Content_Screen::field_html( $field, 'price_adult_monthly', 'tier-0-price_id' );
		$this->assertSame( 4, substr_count( $html, '<option ' ) );
	}
```

In `includes/admin/class-content-screen.php`, in the `case 'select':` block at line 381, before the options loop, append the stale value as its own option when it is set but not offered:

```php
			case 'select':
				$options = $field['options'] ?? array();
				// A value the shop no longer offers: keep it visible and selected, and
				// say so. Rendering it as "Not connected" would tell the owner their
				// tier was never wired up, when in fact its product has gone.
				if ( '' !== (string) $value && ! array_key_exists( (string) $value, $options ) ) {
					$options[ (string) $value ] = 'No longer available — visitors see your typed price, and this clears when you save';
				}
```

then use `$options` in the loop below in place of the previous expression.

If `Content_Screen` has no method matching `field_html( array $field, mixed $value, string $id ): string`, find the private method that renders one field and add a thin public wrapper for it, the way Task 4 added `membership_tiers_for_test()` — and use the real signature in the tests above.

Run: `composer test -- --filter ContentCatalogueProductsTest`
Expected: PASS, 8 tests.

- [ ] **Step 6: Run the tests**

Run: `composer test -- --filter ContentCatalogueProductsTest`
Expected: PASS, 8 tests.

Run: `composer test`
Expected: PASS, whole suite. `ContentCatalogueTest`, `ContentControllerTest` and `ContentScreenTest` all call `pages()` with no argument and must still pass unchanged.

- [ ] **Step 7: Lint and commit**

```bash
composer lint
git add includes/admin/class-content-catalogue.php includes/admin/class-content-controller.php includes/admin/class-content-screen.php tests/php/ContentCatalogueProductsTest.php
git commit -m "feat: let an owner connect a membership tier to a product"
```

---

### Task 6: The SureCart adapter

The only class in the plugin that knows SureCart exists. **Build it from `docs/integrations/surecart-notes.md`, written in Task 1 — not from memory or documentation.** Where the notes and your expectation disagree, the notes win; they were observed on a real install.

**Files:**
- Create: `includes/membership/class-surecart-products.php`
- Create: `tests/php/SureCartProductsTest.php`
- Modify: `includes/bootstrap.php`
- Modify: `includes/frontend/class-frontend.php`
- Modify: `tests/php/wp-stubs.php` — add any WordPress functions this needs that are not already stubbed

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Products`, and SureCart's own API per the notes.
- Produces: `Blueworx_Clubhouse_SureCart_Products implements Blueworx_Clubhouse_Products`, plus `::register(): void` which hooks cache invalidation.

Requirements:

- `prices()` returns every price an owner may sell, excluding archived and draft ones — an owner must not be able to connect a tier to something a visitor cannot buy.
- `amount` is formatted from SureCart's minor units and currency: 2800 pence becomes `£28`. Whole amounts show no decimals; non-whole amounts show two (`£28.50`).
- `period` is `/mo` for a monthly recurring price, `/yr` for a yearly one, and `''` for a one-off. An interval SureCart reports that is neither monthly nor yearly returns `''` rather than inventing a suffix.
- `price()` returns null for unknown, archived, or when SureCart is not active.
- Results are cached in a transient keyed with the plugin version, following `Theme_Cache`.
- `register()` clears that cache on SureCart's product and price save hooks, so a price change shows immediately. The notes from Task 1 name those hooks; if SureCart fires none, cache with a short expiry instead and say so in your report — do not leave a cache nothing can clear.
- Frontend installs the adapter and the checkout base URL, both only when SureCart is active.

- [ ] **Step 1: Write the failing test**

Create `tests/php/SureCartProductsTest.php`. The money and interval formatting is the part worth testing without SureCart present, so expose it as pure static methods and test those directly:

```php
<?php

use PHPUnit\Framework\TestCase;

final class SureCartProductsTest extends TestCase {

	public function test_whole_amounts_lose_their_decimals(): void {
		$this->assertSame( '£28', Blueworx_Clubhouse_SureCart_Products::format_amount( 2800, 'GBP' ) );
		$this->assertSame( '£300', Blueworx_Clubhouse_SureCart_Products::format_amount( 30000, 'GBP' ) );
	}

	public function test_part_amounts_keep_both_decimals(): void {
		$this->assertSame( '£28.50', Blueworx_Clubhouse_SureCart_Products::format_amount( 2850, 'GBP' ) );
		$this->assertSame( '£0.99', Blueworx_Clubhouse_SureCart_Products::format_amount( 99, 'GBP' ) );
	}

	public function test_other_currencies_get_their_own_symbol(): void {
		$this->assertSame( '€28', Blueworx_Clubhouse_SureCart_Products::format_amount( 2800, 'EUR' ) );
		$this->assertSame( '$28', Blueworx_Clubhouse_SureCart_Products::format_amount( 2800, 'USD' ) );
	}

	public function test_an_unknown_currency_falls_back_to_its_code(): void {
		// Better "28 NOK" than a wrong symbol on a club's own price.
		$this->assertSame( '28 NOK', Blueworx_Clubhouse_SureCart_Products::format_amount( 2800, 'NOK' ) );
	}

	public function test_recurring_intervals_become_the_suffix_the_card_shows(): void {
		$this->assertSame( '/mo', Blueworx_Clubhouse_SureCart_Products::format_period( 'month', 1 ) );
		$this->assertSame( '/yr', Blueworx_Clubhouse_SureCart_Products::format_period( 'year', 1 ) );
	}

	public function test_anything_else_has_no_suffix(): void {
		// A one-off, or an interval the card has no words for: better silent than
		// wrong beside a price.
		$this->assertSame( '', Blueworx_Clubhouse_SureCart_Products::format_period( '', 0 ) );
		$this->assertSame( '', Blueworx_Clubhouse_SureCart_Products::format_period( 'week', 1 ) );
		$this->assertSame( '', Blueworx_Clubhouse_SureCart_Products::format_period( 'month', 3 ) );
	}

	public function test_the_picker_label_names_the_product_and_the_price(): void {
		$this->assertSame(
			'Adult membership — £28/mo',
			Blueworx_Clubhouse_SureCart_Products::format_label( 'Adult membership', '£28', '/mo' )
		);
	}

	public function test_a_one_off_label_has_no_dangling_slash(): void {
		$this->assertSame(
			'Life membership — £500',
			Blueworx_Clubhouse_SureCart_Products::format_label( 'Life membership', '£500', '' )
		);
	}

	public function test_it_satisfies_the_products_interface(): void {
		$this->assertTrue(
			in_array( Blueworx_Clubhouse_Products::class, class_implements( Blueworx_Clubhouse_SureCart_Products::class ), true )
		);
	}
}
```

- [ ] **Step 2: Run it and watch it fail**

Run: `composer test -- --filter SureCartProductsTest`
Expected: FAIL — `Class "Blueworx_Clubhouse_SureCart_Products" not found`.

- [ ] **Step 3: Write the adapter**

Create `includes/membership/class-surecart-products.php`.

The formatting is knowable without SureCart, so it is given in full here. Write these exactly:

```php
	/** GBP 2800 becomes "£28"; 2850 becomes "£28.50". */
	public static function format_amount( int $minor_units, string $currency ): string {
		$symbols = array( 'GBP' => '£', 'EUR' => '€', 'USD' => '$' );
		$major   = $minor_units / 100;
		// Whole amounts read as prices; part amounts need both decimals or £28.5
		// looks like a typo on a club's own page.
		$number  = ( 0 === $minor_units % 100 ) ? (string) (int) $major : number_format( $major, 2 );
		$symbol  = $symbols[ strtoupper( $currency ) ] ?? '';

		// An unknown currency gets its code rather than a guessed symbol: "28 NOK"
		// is merely plain, the wrong symbol is wrong.
		return '' === $symbol ? $number . ' ' . strtoupper( $currency ) : $symbol . $number;
	}

	/**
	 * The suffix the tier card shows beside the price. Anything that is not a
	 * plain monthly or yearly subscription gets none — the card has no words for
	 * "every 3 months", and silence beside a price beats a wrong claim about it.
	 */
	public static function format_period( string $interval, int $count ): string {
		if ( 1 !== $count ) {
			return '';
		}
		if ( 'month' === $interval ) {
			return '/mo';
		}
		if ( 'year' === $interval ) {
			return '/yr';
		}
		return '';
	}

	/** The picker's label: "Adult membership — £28/mo", or no suffix for a one-off. */
	public static function format_label( string $product, string $amount, string $period ): string {
		return $product . ' — ' . $amount . $period;
	}
```

Then, from the Task 1 notes, add the SureCart-facing parts — this is the one place in the plan whose exact code cannot be written in advance, because it depends on what that task observed:

- `is_active(): bool` — whether SureCart is live on this site.
- `checkout_url(): string` — SureCart's checkout page URL, `''` when it has none.
- A private method fetching SureCart's sellable prices and mapping each into this plugin's price array via the three `format_*` methods above.
- `prices()` and `price( string $id )` on top of a transient cache, keyed with the plugin version.
- `register(): void` hooking cache invalidation to SureCart's product and price save hooks.

Every SureCart call must be guarded so a site without it returns an empty list or null rather than fatalling — the class is loaded on every request, including sites that have never installed a shop.

- [ ] **Step 4: Install it from Frontend**

In `includes/frontend/class-frontend.php`, where the other seams are installed, add — guarded so nothing happens on a site without SureCart:

```php
		// The shop, when there is one. Both are installed together: a products
		// adapter with no checkout page can only produce half-connected tiers,
		// which the renderer deliberately refuses to show.
		if ( Blueworx_Clubhouse_SureCart_Products::is_active() ) {
			Blueworx_Clubhouse_Products_Source::set( new Blueworx_Clubhouse_SureCart_Products() );
			Blueworx_Clubhouse_Checkout::set_base_url( Blueworx_Clubhouse_SureCart_Products::checkout_url() );
		}
```

Add `is_active(): bool` and `checkout_url(): string` to the adapter, per the notes. `checkout_url()` returns `''` when SureCart has no checkout page set — which is issue #150, and the fallback is correct until it is fixed.

Wire `Blueworx_Clubhouse_SureCart_Products::register()` in `blueworx_labs_clubhouse_init()` alongside the other controllers. Do not wire it from `Frontend::register()` — that file no longer wires controllers.

- [ ] **Step 5: Run the tests**

Run: `composer test -- --filter SureCartProductsTest`
Expected: PASS, 9 tests.

Run: `composer test`
Expected: PASS, whole suite.

- [ ] **Step 6: Lint and commit**

```bash
composer lint
git add includes/membership/class-surecart-products.php includes/bootstrap.php includes/frontend/class-frontend.php blueworx-labs-clubhouse.php tests/php/SureCartProductsTest.php tests/php/wp-stubs.php
git commit -m "feat: read membership prices from SureCart"
```

---

### Task 7: Preview, release, and the manual smoke

**Files:**
- Modify: `preview/index.php` — install `Demo_Products` and a checkout base URL
- Create: `tests/membership-tiers.spec.js`
- Modify: `blueworx-labs-clubhouse.php`, `package.json`, `CHANGELOG.md`

**Interfaces:**
- Consumes: everything above.
- Produces: nothing.

- [ ] **Step 1: Show connected tiers in the preview**

In `preview/index.php`, beside the other seam installation, add:

```php
Blueworx_Clubhouse_Products_Source::set( new Blueworx_Clubhouse_Demo_Products() );
Blueworx_Clubhouse_Checkout::set_base_url( '?page=checkout-demo' );
```

The preview has no stored content, so every tier renders unconnected — which is the correct default. The seam is installed so the preview exercises the same code path the live site does.

- [ ] **Step 2: Write the failing browser test**

Create `tests/membership-tiers.spec.js`:

```js
const { test, expect } = require('@playwright/test');

// The membership page with no tier connected — the state every club starts in,
// and the one that must never change.
test('unconnected tiers keep their typed price and a working button @preview', async ({ page }) => {
  await page.goto('?page=membership');

  const tiers = page.locator('.ch-tier');
  await expect(tiers.first()).toBeVisible();

  // Every tier's button goes somewhere: no dead buttons on a page whose whole
  // job is to sign someone up.
  const links = page.locator('.ch-tier a[href]');
  const count = await links.count();
  expect(count).toBeGreaterThan(0);
  for (let i = 0; i < count; i++) {
    const href = await links.nth(i).getAttribute('href');
    expect(href).not.toBe('');
    expect(href).not.toBe('#');
  }
});
```

- [ ] **Step 3: Run it**

Run: `npx playwright test tests/membership-tiers.spec.js`
Expected: PASS. If `.ch-tier` is not the tier card's class, read `Sections::tier_grid()` and use the real one — correct the test, not the markup.

- [ ] **Step 4: Bump the version and write the changelog**

In `blueworx-labs-clubhouse.php`, set the `Version:` header and the version constant below it to **0.65.0**; set `package.json` to match. All three must agree.

At the top of `CHANGELOG.md`:

```markdown
## 0.65.0

- **Membership tiers can now sell.** Connect a tier to a product in your shop and the card shows what that product charges, with its button going straight to checkout with the right membership in the basket. Change a price in the shop and the page follows. Tiers you don't connect are unchanged — they show the price you type and point at the contact page.
```

Version note: 0.64.0 is claimed by the block-library branch and 0.63.2/0.63.3 by the two open fixes, so this takes 0.65.0 and can merge after any of them.

- [ ] **Step 5: Verify everything**

Run: `composer test` — whole suite green.
Run: `composer lint` — no errors.
Run: `npx playwright test` — all green.

- [ ] **Step 6: Commit**

```bash
git add preview/index.php tests/membership-tiers.spec.js blueworx-labs-clubhouse.php package.json CHANGELOG.md
git commit -m "chore: release 0.65.0"
```

- [ ] **Step 7: Write the manual smoke script**

No test double can prove a real payment page. In your final report, write the steps for the human to run on a site with SureCart:

1. Add a recurring product in SureCart, then connect a tier to it in Club Pages → Membership → Tiers.
2. The membership page shows the shop's price and period, not the typed one.
3. Join lands on checkout with that membership in the basket at the right price.
4. The Home page's tier row shows the same connected price.
5. Change the price in SureCart; both pages follow without re-saving anything.
6. Archive or delete the product; both pages fall back to the typed price and the contact link, with no error.
7. Deactivate SureCart; the membership page renders exactly as it did before any of this.

---

## What this plan deliberately leaves undone

- **Creating the checkout and dashboard pages** — issue #150. Until that lands, `checkout_url()` returns `''` and every tier falls back. That is by design, not a gap.
- **Member accounts, membership status, gating content, upgrades and proration** — out of scope per the spec.
- **The monthly/yearly toggle on one card** — turned down during design; two tiers do the job.
- **Moving these fields into the new block model** — the block rebuild's plans carry the tier fields across, `price_id` among them, with no special handling needed.
