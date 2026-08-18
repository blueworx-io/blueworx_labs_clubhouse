# Membership Monthly / Annual Switcher Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A visitor switches the tier grid between monthly and annual prices in place, and each cadence sells the right thing.

**Architecture:** Each tier carries both cadences. `Page_Renderer::membership_tiers()` resolves a price and a CTA per cadence using the existing all-or-nothing SureCart rule, applied twice. `Sections::tier_grid()` renders both into one card and a switcher shows one at a time, client-side, following the existing tab treatment.

**Tech Stack:** PHP 8.1+, WordPress core only, PHPUnit, Playwright.

**Spec:** `docs/superpowers/specs/2026-08-18-membership-monthly-annual-switcher-design.md`

## Global Constraints

- No new dependency; no new JS build step. The switcher script is inline, like `Sections::TAB_SCRIPT`.
- No migration: `price`, `period` and `price_id` keep their meaning and become the monthly cadence.
- The all-or-nothing SureCart rule holds per cadence: a shop price is used only when the price exists AND a checkout URL exists, otherwise the typed price and the club's own CTA.
- A saving is shown only when both amounts are unambiguous, and only when annual is genuinely cheaper.
- New `ch-` classes need a rule in all three looks (`LookCoverageTest`).
- Version bumped, `CHANGELOG.md` updated (minor bump). `composer lint` once at the end; present findings, do not fix without approval.

---

### Task 1: Two prices per tier, and the saving

**Files:**
- Modify: `includes/render/class-page-renderer.php` (`membership_tiers()`)
- Test: `tests/php/MembershipTierPricingTest.php`

**Interfaces:**
- Produces: each tier from `membership_tiers()` gains
  `monthly` and `annual`, each `array{price:string,period:string,cta_label:string,cta_href:string,available:bool}`,
  plus `saving:string` (`''` when none). The existing top-level `price`, `period`,
  `cta_label` and `cta_href` keep their current values — the monthly ones — so
  nothing reading a tier today breaks.
- Produces: `Blueworx_Clubhouse_Page_Renderer::annual_saving( string $monthly, string $annual ): string`.

- [ ] **Step 1: Write the failing test**

Add to `tests/php/MembershipTierPricingTest.php` (read it first — it already builds tiers through `membership_tiers_for_test()` and sets a products source; reuse those helpers):

```php
	public function test_a_tier_carries_both_cadences(): void {
		$tiers = $this->tiers( array( array(
			'name' => 'Adult', 'price' => '£28', 'period' => '/mo',
			'price_annual' => '£280', 'cta_label' => 'Join',
		) ) );
		$this->assertSame( '£28', $tiers[0]['monthly']['price'] );
		$this->assertSame( '/mo', $tiers[0]['monthly']['period'] );
		$this->assertTrue( $tiers[0]['monthly']['available'] );
		$this->assertSame( '£280', $tiers[0]['annual']['price'] );
		$this->assertSame( '/yr', $tiers[0]['annual']['period'] );
		$this->assertTrue( $tiers[0]['annual']['available'] );
	}

	public function test_a_tier_with_no_annual_price_says_so_rather_than_vanishing(): void {
		$tiers = $this->tiers( array( array( 'name' => 'Junior', 'price' => '£12', 'period' => '/mo' ) ) );
		$this->assertFalse( $tiers[0]['annual']['available'] );
		// It still shows the price it has, so the card does not empty out.
		$this->assertSame( '£12', $tiers[0]['annual']['price'] );
	}

	public function test_the_annual_saving_is_worked_out_not_typed(): void {
		$tiers = $this->tiers( array( array( 'name' => 'Adult', 'price' => '£28', 'price_annual' => '£280' ) ) );
		$this->assertSame( 'Save £56 a year', $tiers[0]['saving'] );
	}

	public function test_no_saving_when_annual_is_not_actually_cheaper(): void {
		$tiers = $this->tiers( array( array( 'name' => 'Adult', 'price' => '£28', 'price_annual' => '£340' ) ) );
		$this->assertSame( '', $tiers[0]['saving'] );
	}

	public function test_no_saving_from_a_price_that_is_not_plainly_a_number(): void {
		// A wrong saving contradicts the prices beside it; a missing one costs nothing.
		$this->assertSame( '', Blueworx_Clubhouse_Page_Renderer::annual_saving( 'from £28', '£280' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Page_Renderer::annual_saving( '£28 per adult', '£280' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Page_Renderer::annual_saving( '£28', '' ) );
		$this->assertSame( '', Blueworx_Clubhouse_Page_Renderer::annual_saving( 'Free', 'Free' ) );
	}

	public function test_a_saving_keeps_the_currency_it_was_given(): void {
		$this->assertSame( 'Save €56 a year', Blueworx_Clubhouse_Page_Renderer::annual_saving( '€28', '€280' ) );
		// Two different currencies cannot be subtracted from each other.
		$this->assertSame( '', Blueworx_Clubhouse_Page_Renderer::annual_saving( '€28', '£280' ) );
	}

	public function test_each_cadence_sells_its_own_price(): void {
		// With a shop and a checkout, the annual CTA must buy the annual price.
		$tiers = $this->tiersWithShop( array( array(
			'name' => 'Adult', 'price' => '£28', 'price_id' => 'price_monthly', 'price_id_annual' => 'price_annual',
		) ) );
		$this->assertStringContainsString( 'price_monthly', $tiers[0]['monthly']['cta_href'] );
		$this->assertStringContainsString( 'price_annual', $tiers[0]['annual']['cta_href'] );
	}

	public function test_an_annual_price_the_shop_does_not_know_falls_back_to_the_typed_one(): void {
		$tiers = $this->tiersWithShop( array( array(
			'name' => 'Adult', 'price' => '£28', 'price_annual' => '£280', 'price_id_annual' => 'price_vanished',
		) ) );
		$this->assertSame( '£280', $tiers[0]['annual']['price'] );
		$this->assertStringNotContainsString( 'price_vanished', $tiers[0]['annual']['cta_href'] );
	}
```

Add `tiers()` and `tiersWithShop()` helpers if the file has no equivalents, modelled on how the existing tests in that file set `Products_Source` and `Checkout::set_base_url()`.

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/phpunit --filter MembershipTierPricingTest`
Expected: FAIL — no `monthly`/`annual` keys.

- [ ] **Step 3: Resolve each cadence**

In `Page_Renderer::membership_tiers()`, extract the existing "connect a tier to a shop price" logic into a private helper used twice:

```php
	/**
	 * One cadence of one tier: what it costs and what its button does.
	 *
	 * All-or-nothing, exactly as the single-price version was: a shop price is
	 * used only when the shop knows it AND there is a checkout to reach, because
	 * showing the shop's price beside a contact link advertises a price the
	 * visitor cannot pay.
	 *
	 * @param array<string,mixed> $tier
	 * @return array{price:string,period:string,cta_label:string,cta_href:string,available:bool}
	 */
	private static function tier_cadence( array $tier, string $price_field, string $price_id_field, string $fallback_period ): array {
		$typed = trim( (string) ( $tier[ $price_field ] ?? '' ) );
		$cta   = array(
			'cta_label' => (string) ( $tier['cta_label'] ?? '' ),
			'cta_href'  => (string) ( $tier['cta_href'] ?? Blueworx_Clubhouse_Links::url( 'contact' ) ),
		);

		$price_id = (string) ( $tier[ $price_id_field ] ?? '' );
		if ( '' !== $price_id ) {
			$price    = Blueworx_Clubhouse_Products_Source::get()?->price( $price_id );
			$checkout = Blueworx_Clubhouse_Checkout::url( $price_id );
			if ( null !== $price && '' !== $checkout ) {
				return array(
					'price'     => $price['amount'],
					'period'    => $price['period'],
					'cta_label' => '' !== $cta['cta_label'] ? $cta['cta_label'] : 'Join',
					'cta_href'  => $checkout,
					'available' => true,
				);
			}
		}

		return array(
			'price'     => $typed,
			'period'    => '' !== $typed ? $fallback_period : '',
			'cta_label' => $cta['cta_label'],
			'cta_href'  => $cta['cta_href'],
			'available' => '' !== $typed,
		);
	}
```

Then in the mapper, after building `$tier`:

```php
				$monthly = self::tier_cadence( $t, 'price', 'price_id', (string) ( $t['period'] ?? '/mo' ) );
				$annual  = self::tier_cadence( $t, 'price_annual', 'price_id_annual', '/yr' );

				// A tier that exists in only one cadence keeps showing the price
				// it has on the other view, labelled — a card that empties out as
				// somebody toggles reads as a broken page.
				if ( ! $annual['available'] ) {
					$annual = array_merge( $monthly, array( 'available' => false ) );
				}
				if ( ! $monthly['available'] ) {
					$monthly = array_merge( $annual, array( 'available' => false ) );
				}

				$tier['monthly'] = $monthly;
				$tier['annual']  = $annual;
				$tier['saving']  = ( $monthly['available'] && $annual['available'] )
					? self::annual_saving( $monthly['price'], $annual['price'] )
					: '';
				// The top-level price/period/CTA stay the monthly ones, so
				// anything reading a tier the old way is unchanged.
				$tier['price']     = $monthly['price'];
				$tier['period']    = $monthly['period'];
				$tier['cta_label'] = $monthly['cta_label'];
				$tier['cta_href']  = $monthly['cta_href'];
```

Delete the old single-price block this replaces, and keep its comments on `tier_cadence()` — the reasoning is still the reasoning.

- [ ] **Step 4: Work out the saving**

Add, public so the tests can drive it directly:

```php
	/**
	 * "Save £56 a year", or '' when it cannot be said safely.
	 *
	 * Deliberately narrow: both prices must be an optional currency symbol and a
	 * number, nothing else, in the same currency. "from £28" and "£28 per adult"
	 * produce no badge, because a saving that contradicts the price printed
	 * beside it is worse than no saving at all.
	 */
	public static function annual_saving( string $monthly, string $annual ): string {
		$parse = static function ( string $raw ): ?array {
			$raw = trim( $raw );
			if ( 1 !== preg_match( '/^([^\d\s]{0,3})\s*(\d+(?:\.\d{1,2})?)$/u', $raw, $m ) ) {
				return null;
			}
			return array( 'symbol' => $m[1], 'amount' => (float) $m[2] );
		};

		$m = $parse( $monthly );
		$a = $parse( $annual );
		if ( null === $m || null === $a || $m['symbol'] !== $a['symbol'] ) {
			return '';
		}

		$saving = ( $m['amount'] * 12 ) - $a['amount'];
		if ( $saving <= 0 ) {
			return '';
		}
		$number = ( 0.0 === fmod( $saving, 1.0 ) ) ? (string) (int) $saving : number_format( $saving, 2 );
		return 'Save ' . $m['symbol'] . $number . ' a year';
	}
```

- [ ] **Step 5: Run the tests**

Run: `vendor/bin/phpunit --filter MembershipTierPricingTest`
Expected: PASS. Then `vendor/bin/phpunit` — the tier shape changed, and `tiers_sell()` and the page tests read it.

- [ ] **Step 6: Commit**

```bash
git add includes/render/class-page-renderer.php tests/php/MembershipTierPricingTest.php
git commit -m "Give every membership tier a monthly and an annual price"
```

---

### Task 2: The switcher, and the card that follows it

**Files:**
- Modify: `includes/render/class-sections.php` (`tier_grid()`, plus a `CADENCE_SCRIPT` constant)
- Modify: `assets/looks/court-side.css`, `assets/looks/floodlight.css`, `assets/looks/members-house.css`
- Test: `tests/php/TierGridCadenceTest.php`

**Interfaces:**
- Consumes: tiers from Task 1.
- Produces: `Sections::tier_grid( array $tiers, int $level = 3, bool $switcher = true ): string` — the third argument is new and defaults to on.

- [ ] **Step 1: Write the failing test**

Create `tests/php/TierGridCadenceTest.php`:

```php
<?php

use PHPUnit\Framework\TestCase;

final class TierGridCadenceTest extends TestCase {

	/** @return array<int,array<string,mixed>> */
	private function tiers(): array {
		return array(
			array(
				'name' => 'Adult', 'eyebrow' => '', 'features' => array(), 'recommended' => false,
				'price' => '£28', 'period' => '/mo', 'cta_label' => 'Join', 'cta_href' => '/join-monthly',
				'monthly' => array( 'price' => '£28', 'period' => '/mo', 'cta_label' => 'Join', 'cta_href' => '/join-monthly', 'available' => true ),
				'annual'  => array( 'price' => '£280', 'period' => '/yr', 'cta_label' => 'Join', 'cta_href' => '/join-annual', 'available' => true ),
				'saving'  => 'Save £56 a year',
			),
			array(
				'name' => 'Junior', 'eyebrow' => '', 'features' => array(), 'recommended' => false,
				'price' => '£12', 'period' => '/mo', 'cta_label' => 'Join', 'cta_href' => '/join-junior',
				'monthly' => array( 'price' => '£12', 'period' => '/mo', 'cta_label' => 'Join', 'cta_href' => '/join-junior', 'available' => true ),
				'annual'  => array( 'price' => '£12', 'period' => '/mo', 'cta_label' => 'Join', 'cta_href' => '/join-junior', 'available' => false ),
				'saving'  => '',
			),
		);
	}

	public function test_both_cadences_are_in_the_markup(): void {
		$html = Blueworx_Clubhouse_Sections::tier_grid( $this->tiers() );
		$this->assertStringContainsString( '£28', $html );
		$this->assertStringContainsString( '£280', $html );
		$this->assertStringContainsString( '/join-annual', $html );
	}

	public function test_monthly_is_what_a_visitor_sees_first(): void {
		$html = Blueworx_Clubhouse_Sections::tier_grid( $this->tiers() );
		$this->assertMatchesRegularExpression( '/ch-cadence__btn[^>]*aria-pressed="true"[^>]*>\s*Monthly/', $html );
	}

	public function test_a_tier_without_an_annual_price_is_labelled_not_hidden(): void {
		$html = Blueworx_Clubhouse_Sections::tier_grid( $this->tiers() );
		$this->assertStringContainsString( 'Monthly only', $html );
		$this->assertSame( 2, substr_count( $html, 'ch-tier__name' ), 'no card may disappear' );
	}

	public function test_the_saving_shows_on_the_annual_side_only(): void {
		$html   = Blueworx_Clubhouse_Sections::tier_grid( $this->tiers() );
		$annual = (string) strstr( $html, 'ch-tier__price--annual' );
		$this->assertStringContainsString( 'Save £56 a year', $annual );
		$this->assertStringNotContainsString( 'Save £56 a year', str_replace( $annual, '', $html ) );
	}

	public function test_the_switcher_can_be_left_off(): void {
		$html = Blueworx_Clubhouse_Sections::tier_grid( $this->tiers(), 3, false );
		$this->assertStringNotContainsString( 'ch-cadence', $html );
		$this->assertStringContainsString( '£28', $html );
	}

	public function test_a_grid_of_old_shape_tiers_still_renders(): void {
		// Defensive: a caller that has not been updated must not fatal.
		$html = Blueworx_Clubhouse_Sections::tier_grid( array( array(
			'name' => 'Adult', 'price' => '£28', 'period' => '/mo', 'features' => array(),
			'cta_label' => 'Join', 'cta_href' => '/join',
		) ) );
		$this->assertStringContainsString( '£28', $html );
	}
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/phpunit --filter TierGridCadenceTest`
Expected: FAIL — no cadence markup.

- [ ] **Step 3: Render both cadences and the switcher**

Rewrite `Sections::tier_grid()` so each card carries both price blocks and both CTAs, one of each hidden by class:

- Each tier falls back to its old flat fields when `monthly`/`annual` are absent, so an un-updated caller still renders (pinned by the last test).
- The price block: `<div class="ch-tier__price ch-tier__price--monthly">` and `--annual`, the annual one carrying `ch-tier__save` when there is a saving and `ch-tier__note` reading "Monthly only" when `annual['available']` is false (and the mirror case for annual-only).
- The CTA: two anchors, same treatment, one hidden.
- The switcher: `<div class="ch-cadence" role="group" aria-label="Payment frequency">` with two `<button type="button" class="ch-cadence__btn" data-cadence="monthly|annual" aria-pressed="…">`.
- Hide with a class (`ch-is-off`), never the `hidden` attribute — a stylesheet-less page then shows both rather than neither, the rule `tab_group()` already follows.
- Add `CADENCE_SCRIPT` beside `TAB_SCRIPT`: on click, set `aria-pressed` on the two buttons and toggle `ch-is-off` on every `--monthly`/`--annual` element inside that section. Scope it to the section, since Home and Membership can both carry one. Emit it once per grid, exactly as `TAB_SCRIPT` is emitted.

- [ ] **Step 4: Style it in all three looks**

Add rules for `ch-cadence`, `ch-cadence__btn`, `ch-cadence__btn--on`, `ch-is-off` (`display:none`), `ch-tier__save` and `ch-tier__note` to each look's CSS, beside its `.ch-tier` rules and using that look's own tokens. `ch-is-off` must be defined in all three or a look will show both prices at once.

- [ ] **Step 5: Run the tests**

Run: `vendor/bin/phpunit --filter "TierGridCadenceTest|LookCoverageTest"`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add includes/render/class-sections.php assets/looks tests/php/TierGridCadenceTest.php
git commit -m "Switch the tier grid between monthly and annual prices"
```

---

### Task 3: Both pages, and the editor

**Files:**
- Modify: `includes/render/class-page-renderer.php` (Home and Membership grid calls)
- Modify: `includes/admin/class-content-catalogue.php` (two new tier fields)
- Modify: `includes/import/class-import-parser.php`, `includes/import/class-import-prompt.php`
- Test: `tests/php/PageRendererTest.php`, `tests/php/ContentCatalogueProductsTest.php`, `tests/php/ImportParserContentTest.php`

- [ ] **Step 1: Write the failing tests**

Add to `tests/php/PageRendererTest.php`:

```php
	public function test_both_pages_offer_the_cadence_switcher(): void {
		$this->assertStringContainsString( 'ch-cadence', $this->render( '' ) );
		$this->assertStringContainsString( 'ch-cadence', $this->render( 'membership' ) );
	}

	public function test_the_home_grid_still_sends_people_to_membership_in_both_cadences(): void {
		// Home shows prices; the Membership page is where somebody chooses.
		$html = $this->render( '' );
		$this->assertStringNotContainsString( 'line_items', $html );
	}
```

Add to `tests/php/ContentCatalogueProductsTest.php` a case asserting the tiers loop offers `price_id_annual` as a select built from the same price options as `price_id`, and to `tests/php/ImportParserContentTest.php` a case asserting an imported `price_id_annual` survives when a shop is installed — mirroring the existing `price_id` tests in both files exactly.

- [ ] **Step 2: Run them to verify they fail**

Run: `vendor/bin/phpunit --filter "PageRendererTest|ContentCatalogueProductsTest|ImportParserContentTest"`

- [ ] **Step 3: Add the fields**

In `class-content-catalogue.php`, in the membership tiers loop, after `price`:

```php
						self::f_text( 'price_annual', 'Annual price' ),
```

and after the existing `price_id` select:

```php
						self::f_select( 'price_id_annual', 'Sells (annual)', self::price_options( $products ) ),
```

Update the section's `tiers_note()` wording to say that a tier with both prices gets a monthly/annual switcher on the page, and one with a single price simply shows that one.

- [ ] **Step 4: Teach the import path about the twin**

`class-import-parser.php` and `class-import-prompt.php` both special-case `price_id` so an import without the products adapter cannot silently clear a tier's connection. Find those two places and make them treat `price_id_annual` identically — a list of the two keys rather than one hard-coded key, so a third never gets missed.

- [ ] **Step 5: Home keeps its funnel**

In the Home tiers block, the CTAs are already rewritten to point at the Membership page. Extend that rewrite to both cadences so the annual CTA does not slip a checkout link onto Home:

```php
			$home_tiers = array_map(
				static function ( array $t ): array {
					$to_membership = static function ( array $cadence ): array {
						$cadence['cta_label'] = 'Join';
						$cadence['cta_href']  = Blueworx_Clubhouse_Links::url( 'membership' );
						return $cadence;
					};
					$t['cta_label'] = 'Join';
					$t['cta_href']  = Blueworx_Clubhouse_Links::url( 'membership' );
					$t['monthly']   = $to_membership( $t['monthly'] ?? array() );
					$t['annual']    = $to_membership( $t['annual'] ?? array() );
					return $t;
				},
				self::membership_tiers( $content )
			);
```

- [ ] **Step 6: Run the full suite**

Run: `vendor/bin/phpunit`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add includes tests/php
git commit -m "Offer the annual price on both pages and in the editor"
```

---

### Task 4: Prove it switches in a browser

**Files:**
- Create: `tests/membership-cadence.spec.js`
- Modify: `preview/index.php` (seed a second tier price so the preview has something to switch to)

- [ ] **Step 1: Write the failing spec**

Create `tests/membership-cadence.spec.js`:

```js
const { test, expect } = require('@playwright/test');

test('@preview the membership grid starts on monthly and switches to annual', async ({ page }) => {
  await page.goto('?clubhouse_page=membership');

  const monthly = page.locator('.ch-tier__price--monthly').first();
  const annual = page.locator('.ch-tier__price--annual').first();
  await expect(monthly).toBeVisible();
  await expect(annual).toBeHidden();

  await page.getByRole('button', { name: 'Annual' }).click();
  await expect(annual).toBeVisible();
  await expect(monthly).toBeHidden();
});

test('@preview switching does not reload the page', async ({ page }) => {
  await page.goto('?clubhouse_page=membership');
  await page.evaluate(() => { window.__stayed = true; });
  await page.getByRole('button', { name: 'Annual' }).click();
  await expect(page.locator('.ch-tier__price--annual').first()).toBeVisible();
  expect(await page.evaluate(() => window.__stayed)).toBe(true);
});

test('@preview a tier with no annual price says so instead of disappearing', async ({ page }) => {
  await page.goto('?clubhouse_page=membership');
  const cards = await page.locator('.ch-tier').count();
  await page.getByRole('button', { name: 'Annual' }).click();
  await expect(page.locator('.ch-tier')).toHaveCount(cards);
  await expect(page.getByText('Monthly only').first()).toBeVisible();
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `npx playwright test tests/membership-cadence.spec.js`

- [ ] **Step 3: Give the preview something to switch**

The preview's tiers come from the renderer's defaults, which have no annual prices. Seed a content store in `preview/index.php` — one already exists for the social feed (`$preview_content`) — with membership tier items carrying `price_annual` on some tiers and not on others, so all three specs have their cases. Keep one tier deliberately monthly-only.

- [ ] **Step 4: Run the spec, then the whole browser suite**

```bash
npx playwright test tests/membership-cadence.spec.js
npx playwright test
```

- [ ] **Step 5: Commit**

```bash
git add preview/index.php tests/membership-cadence.spec.js
git commit -m "Browser-test the monthly and annual switcher"
```

---

### Task 5: Version, changelog and the lint pass

- [ ] **Step 1: Bump the version**

Minor bump (0.78.0, or the next minor if the social feed connection landed first). Plugin header, `BLUEWORX_LABS_CLUBHOUSE_VERSION`, `package.json`.

- [ ] **Step 2: Changelog**

```markdown
- **Members can now be shown monthly or annual prices.** Give a tier an annual price as well as a monthly one and your Membership and Home pages get a Monthly / Annual switch above the tiers. If annual works out cheaper, the card says how much a member saves. A tier with only one price simply shows that one and says so.
```

- [ ] **Step 3: Run everything once**

```bash
vendor/bin/phpunit
npx playwright test
composer lint
```

Present lint findings; do not fix without approval.

- [ ] **Step 4: Commit and open the pull request**

```bash
git add -A
git commit -m "Release <version> — monthly and annual membership prices"
git push -u origin <branch>
gh pr create --title "Switch membership tiers between monthly and annual" --body "$(cat <<'BODY'
Tiers can carry an annual price as well as a monthly one, and the Home and Membership grids get a switch between them. Where both prices are known and annual is cheaper, the card says what a member saves.

Nothing existing changes: a club's current prices are its monthly ones.

🤖 Generated with [Claude Code](https://claude.com/claude-code)
BODY
)"
```
