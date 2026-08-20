# Checkout in Our Own Clothes — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give the checkout page the member area's header, footer and look, and
seed SureCart a checkout form we authored, so no club owner ever opens SureCart
to make a checkout exist.

**Architecture:** Three separable parts. A pure-CSS stylesheet maps the member
area's `--bw-*` tokens onto SureCart's `--sc-*` ones, which is the only way to
reach inside their shadow-DOM fields. A new `Dashboard_Shell::checkout()` draws
the page chrome. A new `Checkout_Form` class answers SureCart's
`surecart/create_forms` filter with our own block markup, so SureCart's own
seeder writes the form.

**Tech Stack:** PHP 7.4+, WordPress, PHPUnit (`composer test`), Playwright
(`npm test`, `npm run test:wp`), PHPCS (`composer lint`).

**Spec:** `docs/superpowers/specs/2026-08-20-checkout-in-our-own-clothes-design.md`

## Global Constraints

- **PHP style.** Every file starts `<?php`, then `declare(strict_types=1);`, then
  the `if ( ! defined( 'ABSPATH' ) ) { exit; }` guard. Classes are `final` and
  prefixed `Blueworx_Clubhouse_`. WordPress brace and spacing style throughout —
  `composer lint` is a CI gate.
- **No new dependencies.** Adding one needs prior approval in `approved-deps.json`.
- **Guard every SureCart-facing call.** A site with no shop must return empty or
  `''`, never fatal. `SureCart_Products::is_active()` is the gate.
- **Never overwrite a club's content.** The filter only supplies content for a
  form SureCart is already about to create.
- **Copy rules.** Plain words, no jargon, no identifiers in anything a club owner
  or buyer reads. Sentence case.
- **Version.** This is a feature: bump `0.84.1` → `0.85.0` in
  `blueworx-labs-clubhouse.php` (both the header and
  `BLUEWORX_LABS_CLUBHOUSE_VERSION`), `package.json`, and `CHANGELOG.md`.
- **SureCart facts are read from source, never guessed.** Version 4.6.4, recorded
  in `docs/integrations/surecart-notes.md`.

---

### Task 1: The SureCart theme stylesheet

Maps the member area's design tokens onto SureCart's, and loads it on the
checkout page only.

**Files:**
- Create: `assets/bw/surecart.css`
- Modify: `includes/dashboard/class-dashboard-assets.php`
- Test: `tests/php/DashboardAssetsTest.php`

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Dashboard_Assets::page_key( int $post_id ): string`,
  which already returns `'checkout'`, `'order-confirmation'` or `''`.
- Produces: `Blueworx_Clubhouse_Dashboard_Assets::SURECART_HANDLE` (string,
  `'blueworx-clubhouse-surecart'`) and
  `Blueworx_Clubhouse_Dashboard_Assets::surecart_relative_path(): string`.

- [ ] **Step 1: Write the failing test**

Append to `tests/php/DashboardAssetsTest.php`:

```php
	public function test_the_surecart_stylesheet_is_queued_on_checkout_only(): void {
		// The token mapping is the only thing that makes SureCart's own fields
		// look like the member area, so it has to be on the page where they
		// render — and on no other, because it would otherwise leak the
		// member-area look onto a club's public shop pages.
		$this->assertTrue(
			Blueworx_Clubhouse_Dashboard_Assets::wants_surecart_style( 'checkout' )
		);
		$this->assertFalse(
			Blueworx_Clubhouse_Dashboard_Assets::wants_surecart_style( 'order-confirmation' )
		);
		$this->assertFalse(
			Blueworx_Clubhouse_Dashboard_Assets::wants_surecart_style( '' )
		);
	}

	public function test_the_surecart_stylesheet_is_a_real_file(): void {
		// A handle pointing at nothing registers happily and 404s in the
		// browser, which looks like SureCart's default rather than a bug.
		$this->assertFileExists(
			dirname( __DIR__, 2 ) . '/' . Blueworx_Clubhouse_Dashboard_Assets::surecart_relative_path()
		);
	}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer test -- --filter DashboardAssetsTest`
Expected: FAIL — `Call to undefined method ...::wants_surecart_style()`

- [ ] **Step 3: Write the stylesheet**

Create `assets/bw/surecart.css`:

```css
/*
 * SureCart's checkout, wearing the member area's clothes.
 *
 * SureCart's fields are Stencil web components and 406 of its 482 components
 * render into shadow DOM, so an ordinary selector cannot reach the input
 * inside <sc-input>. Custom properties can — they inherit through the shadow
 * boundary — and SureCart exposes a generous set of them (thirty-nine on the
 * text input alone). Setting them on an ancestor is the supported way to theme
 * the checkout, and it is the only way that survives their updates.
 *
 * Scoped to .bw-admin, like everything else in this folder, so it cannot reach
 * a club's public shop pages.
 *
 * If SureCart renames a token, the declaration stops applying and their own
 * default shows through. Ugly for a release, never broken.
 *
 * Token names read from SureCart 4.6.4 —
 * dist/components/collection/components/ui/. See
 * docs/integrations/surecart-notes.md.
 */

.bw-admin {
  /* Type. SureCart sets its own stack on every control. */
  --sc-font-sans: var(--bw-font-body);
  --sc-input-font-family: var(--bw-font-body);

  /* Fields, matching .bw-input. */
  --sc-input-height-small: var(--bw-control-h-sm);
  --sc-input-height-medium: var(--bw-control-h);
  --sc-input-height-large: var(--bw-control-h);
  --sc-input-border-radius-small: var(--bw-control-radius);
  --sc-input-border-radius-medium: var(--bw-control-radius);
  --sc-input-border-radius-large: var(--bw-control-radius);
  --sc-input-border-color: var(--bw-border-field);
  --sc-input-border-color-hover: var(--bw-border-strong);
  --sc-input-border-color-focus: var(--bw-brand);
  --sc-input-background-color: var(--bw-control-bg);
  --sc-input-background-color-hover: var(--bw-control-bg-hover);
  --sc-input-color: var(--bw-text-body);
  --sc-input-placeholder-color: var(--bw-text-faint);

  /* Labels, help and errors. */
  --sc-input-label-color: var(--bw-text-heading);
  --sc-input-help-text-color: var(--bw-text-muted);
  --sc-input-error-text-color: var(--bw-danger);

  /* The focus ring is the one thing a keyboard user depends on. */
  --sc-focus-ring-color-primary: var(--bw-focus-color);
  --sc-focus-ring-width: var(--bw-focus-width);

  /* The pay button. */
  --sc-color-primary-500: var(--bw-brand);
}
```

- [ ] **Step 4: Add the handle and the decision to Dashboard_Assets**

In `includes/dashboard/class-dashboard-assets.php`, beside the existing
`HANDLE` and `PATH` constants:

```php
	private const SURECART_HANDLE = 'blueworx-clubhouse-surecart';
	private const SURECART_PATH   = 'assets/bw/surecart.css';

	public static function surecart_handle(): string {
		return self::SURECART_HANDLE;
	}

	/** Where the SureCart token mapping is, relative to the plugin root. */
	public static function surecart_relative_path(): string {
		return self::SURECART_PATH;
	}

	/**
	 * Whether this page needs the SureCart token mapping. Pure.
	 *
	 * Checkout alone. The order confirmation page renders SureCart's
	 * confirmation blocks, which are read-only text rather than fields, and
	 * loading a field theme there would be dead weight on the one page a buyer
	 * lands on straight after paying.
	 */
	public static function wants_surecart_style( string $page_key ): bool {
		return 'checkout' === $page_key;
	}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `composer test -- --filter DashboardAssetsTest`
Expected: PASS

- [ ] **Step 6: Register and enqueue it beside the existing stylesheet**

In `declare_style()`, after the existing `wp_register_style()` and the
`get_queried_object_id()` guard, register the second sheet and queue it only
when `wants_surecart_style()` says so. It depends on the member-area sheet so
it always loads after it, and therefore wins:

```php
		wp_register_style(
			self::SURECART_HANDLE,
			BLUEWORX_LABS_CLUBHOUSE_URL . self::SURECART_PATH,
			array( self::HANDLE ),
			defined( 'BLUEWORX_LABS_CLUBHOUSE_VERSION' ) ? BLUEWORX_LABS_CLUBHOUSE_VERSION : null
		);
```

and beside the existing `wp_enqueue_style( self::HANDLE )` call:

```php
		if ( self::wants_surecart_style( $key ) ) {
			wp_enqueue_style( self::SURECART_HANDLE );
		}
```

Read the surrounding method first — `$key` is whatever the existing code named
the result of `page_key()`. Match it rather than renaming.

- [ ] **Step 7: Run the full PHP suite and the linter**

Run: `composer test`
Expected: PASS, no regressions

Run: `composer lint`
Expected: no errors in the files touched

- [ ] **Step 8: Commit**

```bash
git add assets/bw/surecart.css includes/dashboard/class-dashboard-assets.php tests/php/DashboardAssetsTest.php
git commit -m "Dress SureCart's own checkout fields in the member area's look"
```

---

### Task 2: The checkout page frame

A third shell beside `page()` and `bare()`: header, content, footer. Pure — it
takes everything it draws as arguments, so it can be tested directly.

**Files:**
- Modify: `includes/render/class-dashboard-shell.php`
- Test: `tests/php/DashboardShellTest.php` (create if absent)

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Dashboard_Shell::e( string ): string`,
  `::icon( string ): string`, `::initials( string ): string` — all already public.
- Produces:

```php
/**
 * @param array{club_name?:string, logo_url?:string, home_url?:string,
 *              home_label?:string, body?:string, footnote?:string,
 *              links?:array<int,array{label:string,href:string}>} $args
 */
public static function checkout( array $args ): string
```

- [ ] **Step 1: Write the failing test**

Create `tests/php/DashboardShellTest.php`:

```php
<?php

use PHPUnit\Framework\TestCase;

/**
 * The checkout page's frame. Pure, so it is asserted directly rather than
 * through a rendered page.
 */
final class DashboardShellTest extends TestCase {

	/** @return array<string,mixed> */
	private function args(): array {
		return array(
			'club_name'  => 'Crewe Vagrants',
			'logo_url'   => '',
			'home_url'   => 'https://club.test/',
			'home_label' => 'Back to Crewe Vagrants',
			'body'       => '<p id="form">FORM</p>',
			'footnote'   => 'Crewe Vagrants Sports Club, registered in England 04128877',
			'links'      => array(
				array( 'label' => 'Terms', 'href' => 'https://club.test/terms/' ),
				array( 'label' => 'Privacy', 'href' => 'https://club.test/privacy/' ),
			),
		);
	}

	public function test_the_shop_content_is_passed_through_untouched(): void {
		// The shop renders the shop. The frame must never rewrite what is
		// inside it, or a SureCart update silently breaks the form.
		$this->assertStringContainsString(
			'<p id="form">FORM</p>',
			Blueworx_Clubhouse_Dashboard_Shell::checkout( $this->args() )
		);
	}

	public function test_the_header_carries_the_club_and_the_footer_the_legals(): void {
		$out = Blueworx_Clubhouse_Dashboard_Shell::checkout( $this->args() );
		$this->assertStringContainsString( 'Crewe Vagrants', $out );
		$this->assertStringContainsString( 'https://club.test/terms/', $out );
		$this->assertStringContainsString( 'registered in England 04128877', $out );
	}

	public function test_there_is_exactly_one_h1(): void {
		// The page heading is the checkout itself. A second one would leave a
		// screen reader with two competing titles on a payment page.
		$this->assertSame(
			1,
			substr_count( Blueworx_Clubhouse_Dashboard_Shell::checkout( $this->args() ), '<h1' )
		);
	}

	public function test_no_nav_is_offered(): void {
		// Someone mid-purchase should not be handed six places to wander off
		// to — the same reasoning as bare().
		$out = Blueworx_Clubhouse_Dashboard_Shell::checkout( $this->args() );
		$this->assertStringNotContainsString( 'bw-secnav', $out );
		$this->assertStringNotContainsString( 'clubhouse-member__tabbar', $out );
	}

	public function test_a_club_with_no_legal_pages_gets_no_empty_nav(): void {
		// A dead link is worse than no link, and an empty <nav> is worse than
		// no nav — it announces a navigation landmark holding nothing.
		$args          = $this->args();
		$args['links'] = array();
		$this->assertStringNotContainsString(
			'clubhouse-checkout__links',
			Blueworx_Clubhouse_Dashboard_Shell::checkout( $args )
		);
	}

	public function test_everything_drawn_is_escaped(): void {
		$args              = $this->args();
		$args['club_name'] = '<script>x</script>';
		$this->assertStringNotContainsString(
			'<script>x</script>',
			Blueworx_Clubhouse_Dashboard_Shell::checkout( $args )
		);
	}

	public function test_the_crest_falls_back_to_initials(): void {
		// Most clubs never upload a square logo. The corner box has to hold
		// something either way.
		$this->assertStringContainsString(
			'CV',
			Blueworx_Clubhouse_Dashboard_Shell::checkout( $this->args() )
		);
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer test -- --filter DashboardShellTest`
Expected: FAIL — `Call to undefined method ...::checkout()`

- [ ] **Step 3: Write the implementation**

Add to `includes/render/class-dashboard-shell.php`, after `bare()`:

```php
	/**
	 * The checkout page's frame: a header, the shop's own form, and a footer.
	 *
	 * Chrome only. The two columns a buyer sees are not drawn here — they are
	 * SureCart's own column blocks, inside the form. The alternative would be
	 * cutting the rendered content in two, and the_content hands it over as a
	 * single string with no seam to cut on. See the design doc.
	 *
	 * No nav, for the same reason bare() has none: someone mid-purchase should
	 * not be offered six places to wander off to. The footer's links are the
	 * exception, because a buyer is entitled to read the terms before paying.
	 *
	 * Pure: everything drawn arrives in $args.
	 *
	 * @param array{club_name?:string, logo_url?:string, home_url?:string,
	 *              home_label?:string, body?:string, footnote?:string,
	 *              links?:array<int,array{label:string,href:string}>} $args
	 */
	public static function checkout( array $args ): string {
		$club     = trim( (string) ( $args['club_name'] ?? '' ) );
		$logo     = trim( (string) ( $args['logo_url'] ?? '' ) );
		$home     = trim( (string) ( $args['home_url'] ?? '' ) );
		$label    = trim( (string) ( $args['home_label'] ?? '' ) );
		$body     = (string) ( $args['body'] ?? '' );
		$footnote = trim( (string) ( $args['footnote'] ?? '' ) );
		$links    = is_array( $args['links'] ?? null ) ? $args['links'] : array();

		$out = '<div class="bw-admin clubhouse-checkout">'
			. self::checkout_head( $club, $logo )
			. '<main class="clubhouse-checkout__body">' . $body . '</main>'
			. self::checkout_foot( $home, $label, $links, $footnote )
			. '</div>';
		return $out;
	}

	/**
	 * The checkout header: the club's crest and name, and the one reassurance
	 * that matters on a payment page — that the club never sees the card.
	 */
	private static function checkout_head( string $club, string $logo ): string {
		$crest = '' !== $logo
			? '<img class="clubhouse-checkout__crest" src="' . self::e( $logo ) . '" alt="" width="34" height="34">'
			: '<span class="clubhouse-checkout__crest" aria-hidden="true">' . self::e( self::initials( $club ) ) . '</span>';

		$out = '<header class="clubhouse-checkout__head">'
			. '<div class="clubhouse-checkout__brand">' . $crest
			. '<span class="clubhouse-checkout__titles">';
		if ( '' !== $club ) {
			$out .= '<span class="clubhouse-checkout__club">' . self::e( $club ) . '</span>';
		}
		$out .= '<h1 class="clubhouse-checkout__h1">Checkout</h1>'
			. '</span></div>'
			. '<p class="clubhouse-checkout__secure">' . self::icon( 'lock' )
			. 'Your card is handled by Stripe. The club never sees it.</p>'
			. '</header>';
		return $out;
	}

	/**
	 * The checkout footer: the way back, the club's legal pages, and whatever
	 * the club has to say about itself in law.
	 *
	 * Every part is drawn only when there is something to draw. An empty nav
	 * announces a navigation landmark holding nothing, which is worse for a
	 * screen reader than no nav at all.
	 *
	 * @param array<int,array{label:string,href:string}> $links
	 */
	private static function checkout_foot( string $home, string $label, array $links, string $footnote ): string {
		$out = '<footer class="clubhouse-checkout__foot">';
		if ( '' !== $home ) {
			$out .= '<a class="clubhouse-checkout__back" href="' . self::e( $home ) . '">'
				. self::icon( 'arrow-left' )
				. self::e( '' !== $label ? $label : 'Back to the club site' )
				. '</a>';
		}
		if ( array() !== $links ) {
			$out .= '<nav class="clubhouse-checkout__links" aria-label="Terms and policies">';
			foreach ( $links as $link ) {
				$href = trim( (string) ( $link['href'] ?? '' ) );
				$text = trim( (string) ( $link['label'] ?? '' ) );
				if ( '' === $href || '' === $text ) {
					continue;
				}
				$out .= '<a href="' . self::e( $href ) . '">' . self::e( $text ) . '</a>';
			}
			$out .= '</nav>';
		}
		if ( '' !== $footnote ) {
			$out .= '<p class="clubhouse-checkout__footnote">' . self::e( $footnote ) . '</p>';
		}
		return $out . '</footer>';
	}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `composer test -- --filter DashboardShellTest`
Expected: PASS, all seven tests

- [ ] **Step 5: Style the frame**

Append to `assets/bw/surecart.css` — the frame's own classes belong beside the
token mapping, because both exist only on this page:

```css
/* The frame itself. Chrome only — the columns inside are SureCart's. */
.clubhouse-checkout { display: flex; flex-direction: column; min-height: 100dvh; background: var(--bw-surface-page); }
.clubhouse-checkout__head { display: flex; align-items: center; gap: var(--bw-space-6, 24px); padding: 0 var(--bw-space-8, 32px); min-height: 64px; background: var(--bw-surface-card); border-bottom: 1px solid var(--bw-border); }
.clubhouse-checkout__brand { display: flex; align-items: center; gap: 12px; flex: 1 1 auto; min-width: 0; }
.clubhouse-checkout__crest { display: flex; align-items: center; justify-content: center; width: 34px; height: 34px; flex: none; border-radius: 8px; object-fit: cover; background: var(--bw-brand-wash); color: var(--bw-text-accent); font-family: var(--bw-font-display); font-size: 13px; font-weight: 700; }
.clubhouse-checkout__titles { display: flex; flex-direction: column; min-width: 0; }
.clubhouse-checkout__club { font-size: 12px; color: var(--bw-text-muted); }
.clubhouse-checkout__h1 { margin: 0; font-family: var(--bw-font-display); font-size: 15px; font-weight: 600; color: var(--bw-text-heading); }
.clubhouse-checkout__secure { display: inline-flex; align-items: center; gap: 6px; margin: 0; font-size: 12px; color: var(--bw-text-muted); }
.clubhouse-checkout__body { flex: 1 1 auto; }
.clubhouse-checkout__foot { display: flex; align-items: center; gap: 20px; flex-wrap: wrap; padding: 14px var(--bw-space-8, 32px); background: var(--bw-surface-card); border-top: 1px solid var(--bw-border); }
.clubhouse-checkout__back { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; }
.clubhouse-checkout__links { display: flex; align-items: center; gap: 18px; flex-wrap: wrap; margin-left: auto; }
.clubhouse-checkout__links a { font-size: 12px; color: var(--bw-text-muted); text-decoration: none; }
.clubhouse-checkout__links a:hover { color: var(--bw-text-link); }
.clubhouse-checkout__footnote { margin: 0; font-size: 12px; color: var(--bw-text-faint); }

/* On a phone the header stacks and the footer becomes a list, so every link
   is a full-width target rather than three words wedged against a neighbour. */
@media (max-width: 640px) {
  .clubhouse-checkout__head { flex-wrap: wrap; gap: 8px; padding: 12px 16px; }
  .clubhouse-checkout__secure { width: 100%; }
  .clubhouse-checkout__foot { flex-direction: column; align-items: stretch; padding: 16px; }
  .clubhouse-checkout__links { flex-direction: column; align-items: stretch; gap: 0; margin-left: 0; }
  .clubhouse-checkout__links a { display: flex; align-items: center; min-height: 44px; }
  .clubhouse-checkout__back { min-height: 44px; }
}
```

- [ ] **Step 6: Run the suite and the linter**

Run: `composer test`
Expected: PASS

Run: `composer lint`
Expected: no errors in the files touched

- [ ] **Step 7: Commit**

```bash
git add includes/render/class-dashboard-shell.php assets/bw/surecart.css tests/php/DashboardShellTest.php
git commit -m "Give the checkout its own header and footer"
```

---

### Task 3: Put the frame on the page

`Commerce_Pages` currently dresses both commerce pages with `bare()`. Checkout
moves to `checkout()`; order confirmation keeps `bare()`.

**Files:**
- Modify: `includes/dashboard/class-commerce-pages.php`
- Test: `tests/php/CommercePagesTest.php` (create if absent — check first,
  `page_key()` may already be covered elsewhere; if so, extend that file)

**Interfaces:**
- Consumes: `Dashboard_Shell::checkout( array $args ): string` from Task 2;
  `Dashboard_Shell::bare()`; `Member_Dashboard::club_name(): string`;
  `Frontend::link_url( string $key ): string`.
- Produces: `Blueworx_Clubhouse_Commerce_Pages::footer_links( callable $visible, callable $url ): array`
  — pure, returns `array<int,array{label:string,href:string}>`.

- [ ] **Step 1: Write the failing test**

```php
	public function test_the_footer_offers_only_pages_the_club_has_switched_on(): void {
		// A dead link on a payment page is the worst place for one. Terms and
		// privacy are switchable like every other club page, so the footer has
		// to ask rather than assume.
		$visible = static fn ( string $slug ): bool => 'privacy' === $slug;
		$url     = static fn ( string $slug ): string => 'https://club.test/' . $slug . '/';

		$links = Blueworx_Clubhouse_Commerce_Pages::footer_links( $visible, $url );

		$this->assertSame(
			array( array( 'label' => 'Privacy', 'href' => 'https://club.test/privacy/' ) ),
			$links
		);
	}

	public function test_a_club_with_nothing_switched_on_gets_no_links(): void {
		$this->assertSame(
			array(),
			Blueworx_Clubhouse_Commerce_Pages::footer_links(
				static fn ( string $slug ): bool => false,
				static fn ( string $slug ): string => 'https://club.test/' . $slug . '/'
			)
		);
	}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer test -- --filter CommercePagesTest`
Expected: FAIL — `Call to undefined method ...::footer_links()`

- [ ] **Step 3: Write the implementation**

Add to `includes/dashboard/class-commerce-pages.php`:

```php
	/**
	 * The club pages a buyer is entitled to read before paying, and their
	 * addresses. Pure — the callers hand in the two questions this cannot
	 * answer itself.
	 *
	 * Contact is here as well as the two legal pages: someone who has hit a
	 * problem halfway through paying needs a way to ask about it, and the
	 * header offers none.
	 *
	 * @param callable(string):bool   $visible
	 * @param callable(string):string $url
	 * @return array<int,array{label:string,href:string}>
	 */
	public static function footer_links( callable $visible, callable $url ): array {
		$out = array();
		foreach ( array(
			'terms'   => 'Terms and conditions',
			'privacy' => 'Privacy notice',
			'contact' => 'Contact the club',
		) as $slug => $label ) {
			if ( ! $visible( $slug ) ) {
				continue;
			}
			$href = trim( $url( $slug ) );
			if ( '' === $href ) {
				continue;
			}
			$out[] = array(
				'label' => $label,
				'href'  => $href,
			);
		}
		return $out;
	}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `composer test -- --filter CommercePagesTest`
Expected: PASS

- [ ] **Step 5: Route checkout through the new frame**

In `dress()`, replace the single `Dashboard_Shell::bare(...)` return with a
branch. Order confirmation is untouched — the same call it makes today:

```php
			if ( 'checkout' === $key ) {
				return Blueworx_Clubhouse_Dashboard_Shell::checkout(
					array(
						'club_name'  => Blueworx_Clubhouse_Member_Dashboard::club_name(),
						'logo_url'   => '',
						'home_url'   => function_exists( 'home_url' ) ? (string) home_url( '/' ) : '/',
						'home_label' => self::back_label( Blueworx_Clubhouse_Member_Dashboard::club_name() ),
						'body'       => $content,
						'footnote'   => '',
						'links'      => self::footer_links(
							static function ( string $slug ): bool {
								$visibility = Blueworx_Clubhouse_Frontend::context()->visibility ?? null;
								return null === $visibility || $visibility->is_page_visible( $slug );
							},
							static fn ( string $slug ): string => Blueworx_Clubhouse_Frontend::link_url( $slug )
						),
					)
				);
			}
```

Read `Frontend::context()` before writing this — if it exposes visibility under
a different name, match what is there rather than inventing an accessor. If it
is not reachable statically, add a small public helper on `Frontend` beside
`link_url()` rather than reaching into internals from here.

And the label helper, beside `footer_links()`:

```php
	/**
	 * "Back to Crewe Vagrants", or the generic wording when a club has not
	 * named itself yet. Pure.
	 */
	public static function back_label( string $club_name ): string {
		$club = trim( $club_name );
		return '' !== $club ? 'Back to ' . $club : 'Back to the club site';
	}
```

- [ ] **Step 6: Test the routing**

```php
	public function test_checkout_gets_the_checkout_frame_and_confirmation_keeps_the_bare_one(): void {
		// Two different pages with two different jobs. The confirmation page is
		// a receipt, so it keeps the heading-and-panel shell it has always had.
		$this->assertSame( 'checkout', Blueworx_Clubhouse_Commerce_Pages::page_key( 7, 7, 9 ) );
		$this->assertSame( 'order-confirmation', Blueworx_Clubhouse_Commerce_Pages::page_key( 9, 7, 9 ) );
	}

	public function test_the_way_back_names_the_club_when_it_has_a_name(): void {
		$this->assertSame( 'Back to Crewe Vagrants', Blueworx_Clubhouse_Commerce_Pages::back_label( 'Crewe Vagrants' ) );
		$this->assertSame( 'Back to the club site', Blueworx_Clubhouse_Commerce_Pages::back_label( '  ' ) );
	}
```

Run: `composer test -- --filter CommercePagesTest`
Expected: PASS

- [ ] **Step 7: Run the suite and the linter**

Run: `composer test`
Expected: PASS

Run: `composer lint`
Expected: no errors in the files touched

- [ ] **Step 8: Commit**

```bash
git add includes/dashboard/class-commerce-pages.php tests/php/CommercePagesTest.php
git commit -m "Put the new frame on the checkout page"
```

---

### Task 4: The checkout form we author

The only class that knows SureCart's block names. It answers
`surecart/create_forms`, so SureCart's own seeder writes our form.

**Files:**
- Create: `includes/membership/class-checkout-form.php`
- Modify: `includes/bootstrap.php` (require it, after `class-shop-pages.php`)
- Modify: `includes/frontend/class-frontend.php` (call `register()` where the
  other SureCart-facing classes are registered — read how
  `SureCart_Products::register()` is wired and follow it)
- Test: `tests/php/CheckoutFormTest.php`

**Interfaces:**
- Consumes: nothing from earlier tasks.
- Produces:
  - `Blueworx_Clubhouse_Checkout_Form::register(): void`
  - `Blueworx_Clubhouse_Checkout_Form::content(): string` — the block markup
  - `Blueworx_Clubhouse_Checkout_Form::filter_forms( mixed $forms ): mixed`

- [ ] **Step 1: Write the failing test**

Create `tests/php/CheckoutFormTest.php`:

```php
<?php

use PHPUnit\Framework\TestCase;

/**
 * The checkout form this plugin hands SureCart to seed.
 *
 * Every block name asserted here is read from SureCart 4.6.4's own
 * packages/blocks/Blocks — see docs/integrations/surecart-notes.md. A block
 * SureCart does not have renders as nothing, so these assertions are the only
 * thing standing between a rename and a silently missing field.
 */
final class CheckoutFormTest extends TestCase {

	public function test_the_form_carries_every_field_a_purchase_needs(): void {
		$content = Blueworx_Clubhouse_Checkout_Form::content();
		foreach ( array(
			'wp:surecart/checkout-errors',
			'wp:surecart/email',
			'wp:surecart/name',
			'wp:surecart/payment',
			'wp:surecart/submit',
			'wp:surecart/totals',
			'wp:surecart/line-items',
			'wp:surecart/coupon',
			'wp:surecart/subtotal',
			'wp:surecart/total',
		) as $block ) {
			$this->assertStringContainsString( $block, $content, $block . ' is missing from the form' );
		}
	}

	public function test_the_form_is_two_columns(): void {
		// The approved design is fields on the left, the order summary on the
		// right. Those are SureCart's own column blocks rather than anything
		// the page frame draws — the frame gets the content as one string.
		$content = Blueworx_Clubhouse_Checkout_Form::content();
		$this->assertStringContainsString( 'wp:surecart/columns', $content );
		$this->assertSame( 2, substr_count( $content, '<!-- wp:surecart/column ' ) );
	}

	public function test_the_address_only_appears_when_something_ships(): void {
		// A membership is not posted anywhere. Asking a member for their
		// address to buy one is a question with no purpose.
		$content = Blueworx_Clubhouse_Checkout_Form::content();
		$this->assertStringContainsString( 'wp:surecart/conditional-form', $content );
		$this->assertStringContainsString( 'wp:surecart/address', $content );
	}

	public function test_the_form_wears_the_member_areas_classes(): void {
		$this->assertStringContainsString( 'bw-card', Blueworx_Clubhouse_Checkout_Form::content() );
	}

	public function test_the_filter_supplies_our_content_for_the_checkout_form(): void {
		$out = Blueworx_Clubhouse_Checkout_Form::filter_forms(
			array(
				'checkout' => array(
					'name'      => 'checkout',
					'title'     => 'Checkout',
					'content'   => 'SURECART DEFAULT',
					'post_type' => 'sc_form',
				),
			)
		);
		$this->assertIsArray( $out );
		$this->assertStringContainsString( 'wp:surecart/email', (string) $out['checkout']['content'] );
		$this->assertStringNotContainsString( 'SURECART DEFAULT', (string) $out['checkout']['content'] );
	}

	public function test_the_filter_leaves_surecarts_other_keys_alone(): void {
		// SureCart wraps our content in its own form block and keys the post
		// off name, title and post_type. Rewriting any of them would produce a
		// form SureCart cannot find again.
		$out = Blueworx_Clubhouse_Checkout_Form::filter_forms(
			array(
				'checkout' => array(
					'name'      => 'checkout',
					'title'     => 'Checkout',
					'content'   => 'SURECART DEFAULT',
					'post_type' => 'sc_form',
				),
				'other'    => array( 'name' => 'other', 'content' => 'LEAVE ME' ),
			)
		);
		$this->assertSame( 'checkout', $out['checkout']['name'] );
		$this->assertSame( 'Checkout', $out['checkout']['title'] );
		$this->assertSame( 'sc_form', $out['checkout']['post_type'] );
		$this->assertSame( 'LEAVE ME', $out['other']['content'] );
	}

	public function test_an_unrecognised_shape_is_handed_straight_back(): void {
		// SureCart applies this filter inside its own seeder. If a future
		// version passes something else, returning it untouched leaves the
		// club with SureCart's default form rather than no checkout at all.
		$this->assertSame( 'not an array', Blueworx_Clubhouse_Checkout_Form::filter_forms( 'not an array' ) );
		$this->assertSame( array(), Blueworx_Clubhouse_Checkout_Form::filter_forms( array() ) );
		$this->assertSame(
			array( 'checkout' => 'not an array either' ),
			Blueworx_Clubhouse_Checkout_Form::filter_forms( array( 'checkout' => 'not an array either' ) )
		);
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer test -- --filter CheckoutFormTest`
Expected: FAIL — `Class "Blueworx_Clubhouse_Checkout_Form" not found`

- [ ] **Step 3: Write the class**

Create `includes/membership/class-checkout-form.php`:

```php
<?php
// includes/membership/class-checkout-form.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The checkout form a club gets without ever opening SureCart.
 *
 * SureCart seeds a checkout form on activation, and its PageSeeder runs
 * everything it is about to write through the surecart/create_forms filter
 * first. This answers that filter with a form of our own, so the club's
 * checkout comes into being through SureCart's own machinery — the post type,
 * the wrapping form block, the option that records the id — while looking like
 * the rest of the member area.
 *
 * Two things this deliberately does not do:
 *
 * It never writes a post. SureCart's createPosts() skips anything that already
 * exists, so a club that has edited its own checkout keeps it, and nothing here
 * can destroy a club's work.
 *
 * It never collects a card. surecart/payment mounts Stripe's own element, which
 * draws the card fields inside an iframe. Placing and colouring it is ours;
 * drawing it is not, and that is the line that keeps this a plugin rather than
 * a payment processor.
 *
 * Every block name below exists in SureCart 4.6.4 (packages/blocks/Blocks) —
 * see docs/integrations/surecart-notes.md. A block SureCart no longer has
 * renders as nothing, so the tests assert each one by name.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Checkout_Form {

	/** The key SureCart's seeder uses for the checkout form. */
	private const KEY = 'checkout';

	public static function register(): void {
		if ( ! function_exists( 'add_filter' ) ) {
			return;
		}
		add_filter( 'surecart/create_forms', array( self::class, 'filter_forms' ) );
	}

	/**
	 * Swap our content in for SureCart's default, and change nothing else.
	 *
	 * Anything unexpected is handed straight back. This filter fires inside
	 * SureCart's seeder at the moment a club's checkout is being created, and
	 * the worst outcome here is not an ugly form — it is no form at all.
	 *
	 * @param mixed $forms
	 * @return mixed
	 */
	public static function filter_forms( $forms ) {
		if ( ! is_array( $forms ) || ! isset( $forms[ self::KEY ] ) || ! is_array( $forms[ self::KEY ] ) ) {
			return $forms;
		}
		$forms[ self::KEY ]['content'] = self::content();
		return $forms;
	}

	/**
	 * The form, as block markup.
	 *
	 * Both the block comment and the rendered element are written out, the way
	 * SureCart's own template does it, so the form renders correctly before
	 * anyone opens it in the editor.
	 *
	 * The order is the approved design: what went wrong, who you are, where it
	 * goes, how you are paying, and the button — with the summary beside it on
	 * a desktop and stacked underneath on a phone.
	 */
	public static function content(): string {
		return '<!-- wp:surecart/columns {"isStackedOnMobile":true,"isReversedOnMobile":true} -->'
			. '<sc-columns is-stacked-on-mobile="1" is-reversed-on-mobile="1" class="wp-block-surecart-columns clubhouse-checkout__cols">'

			// The fields.
			. '<!-- wp:surecart/column {"layout":{"type":"constrained","contentSize":"640px"}} -->'
			. '<sc-column class="wp-block-surecart-column clubhouse-checkout__main">'

			. '<!-- wp:surecart/checkout-errors --><sc-checkout-form-errors></sc-checkout-form-errors><!-- /wp:surecart/checkout-errors -->'

			. '<!-- wp:surecart/email {"label":"Email","placeholder":"you@example.com"} /-->'

			. '<!-- wp:surecart/name {"required":true,"label":"Name","placeholder":"Your full name"} -->'
			. '<sc-customer-name label="Name" placeholder="Your full name" required class="wp-block-surecart-name"></sc-customer-name>'
			. '<!-- /wp:surecart/name -->'

			. '<!-- wp:surecart/phone {"label":"Mobile","required":false} /-->'

			// Only when there is something to post. A membership is not.
			. '<!-- wp:surecart/conditional-form {"conditions":[{"comparison":"contains","condition":"shipping_enabled"}]} -->'
			. '<sc-conditional-form class="wp-block-surecart-conditional-form">'
			. '<!-- wp:surecart/address {"label":"Where should we send it?","shipping":true} /-->'
			. '<!-- wp:surecart/shipping-choices /-->'
			. '</sc-conditional-form>'
			. '<!-- /wp:surecart/conditional-form -->'

			. '<!-- wp:surecart/payment {"secure_notice":"Your card is handled by Stripe. The club never sees it."} -->'
			. '<sc-payment label="Payment" secure-notice="Your card is handled by Stripe. The club never sees it." class="wp-block-surecart-payment"></sc-payment>'
			. '<!-- /wp:surecart/payment -->'

			. '<!-- wp:surecart/submit {"show_total":true,"full":true} -->'
			. '<sc-order-submit type="primary" full="true" size="large" show-total="true" class="wp-block-surecart-submit bw-btn bw-btn--primary bw-btn--lg">Pay now</sc-order-submit>'
			. '<!-- /wp:surecart/submit -->'

			. '</sc-column><!-- /wp:surecart/column -->'

			// The summary.
			. '<!-- wp:surecart/column {"sticky":true,"layout":{"type":"constrained","contentSize":"400px"}} -->'
			. '<sc-column class="wp-block-surecart-column is-sticky clubhouse-checkout__rail bw-card">'

			. '<!-- wp:surecart/totals {"collapsible":true,"collapsedOnMobile":true,"closed_text":"Show order summary","open_text":"Hide order summary"} -->'
			. '<sc-order-summary collapsible="1" collapsed-on-mobile="1" closed-text="Show order summary" open-text="Hide order summary" class="wp-block-surecart-totals">'
			. '<!-- wp:surecart/line-items --><sc-line-items editable="1" class="wp-block-surecart-line-items"></sc-line-items><!-- /wp:surecart/line-items -->'
			. '<!-- wp:surecart/divider --><sc-divider></sc-divider><!-- /wp:surecart/divider -->'
			. '<!-- wp:surecart/subtotal --><sc-line-item-total total="subtotal" class="wp-block-surecart-subtotal"><span slot="description">Subtotal</span></sc-line-item-total><!-- /wp:surecart/subtotal -->'
			. '<!-- wp:surecart/coupon {"text":"Got a code?","button_text":"Apply"} --><sc-order-coupon-form></sc-order-coupon-form><!-- /wp:surecart/coupon -->'
			. '<!-- wp:surecart/tax-line-item --><sc-line-item-tax class="wp-block-surecart-tax-line-item"></sc-line-item-tax><!-- /wp:surecart/tax-line-item -->'
			. '<!-- wp:surecart/trial-line-item /-->'
			. '<!-- wp:surecart/divider --><sc-divider></sc-divider><!-- /wp:surecart/divider -->'
			. '<!-- wp:surecart/total --><sc-line-item-total total="total" size="large" show-currency="1" class="wp-block-surecart-total"><span slot="title">Due today</span><span slot="subscription-title">Due today</span></sc-line-item-total><!-- /wp:surecart/total -->'
			. '</sc-order-summary>'
			. '<!-- /wp:surecart/totals -->'

			. '</sc-column><!-- /wp:surecart/column -->'

			. '</sc-columns>'
			. '<!-- /wp:surecart/columns -->';
	}
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `composer test -- --filter CheckoutFormTest`
Expected: PASS, all seven tests

- [ ] **Step 5: Load and register the class**

In `includes/bootstrap.php`, in the Membership block, after
`require_once __DIR__ . '/membership/class-shop-pages.php';`:

```php
require_once __DIR__ . '/membership/class-checkout-form.php';
```

Then register it where the other SureCart-facing classes register. Read
`includes/frontend/class-frontend.php` around the existing
`Blueworx_Clubhouse_SureCart_Products` wiring and add
`Blueworx_Clubhouse_Checkout_Form::register();` alongside it, inside the same
guard. It must run on `plugins_loaded` or earlier — SureCart's activation seeder
fires after plugins load, and a filter added later never sees it.

- [ ] **Step 6: Verify the filter is actually attached**

Add to `tests/php/CheckoutFormTest.php`:

```php
	public function test_register_attaches_the_filter(): void {
		// A filter that is never added is the exact failure mode that made the
		// whole SureCart integration unreachable once before — see the note on
		// SureCart_Products::is_active(). Assert the wiring, not just the value.
		wp_stub_reset();
		Blueworx_Clubhouse_Checkout_Form::register();
		$this->assertTrue( has_filter( 'surecart/create_forms' ) );
	}
```

If `has_filter()` is not in `tests/php/wp-stubs.php`, add it there beside the
existing `add_filter()` stub, recording what was registered.

Run: `composer test -- --filter CheckoutFormTest`
Expected: PASS

- [ ] **Step 7: Style the two columns**

Append to `assets/bw/surecart.css`:

```css
/* The two columns are SureCart's own blocks; these give them the member
   area's rhythm and keep the summary in view while the form is scrolled. */
.clubhouse-checkout__cols { display: flex; align-items: flex-start; gap: 32px; max-width: 1160px; margin: 0 auto; padding: 32px; }
.clubhouse-checkout__main { flex: 1 1 auto; min-width: 0; }
.clubhouse-checkout__rail { flex: 0 0 400px; position: sticky; top: 32px; }

@media (max-width: 900px) {
  .clubhouse-checkout__cols { flex-direction: column; gap: 20px; padding: 16px; }
  .clubhouse-checkout__rail { flex: 1 1 auto; width: 100%; position: static; }
}
```

- [ ] **Step 8: Run the suite and the linter**

Run: `composer test`
Expected: PASS

Run: `composer lint`
Expected: no errors in the files touched

- [ ] **Step 9: Commit**

```bash
git add includes/membership/class-checkout-form.php includes/bootstrap.php includes/frontend/class-frontend.php assets/bw/surecart.css tests/php/CheckoutFormTest.php tests/php/wp-stubs.php
git commit -m "Hand SureCart a checkout form of our own to seed"
```

---

### Task 5: Prove it in a browser

The WordPress harness has no SureCart, so the fixture is a page carrying the
checkout page id — the same trick `member-area-fixture` already uses for the
customer dashboard. What is asserted is our frame, never SureCart's fields.

**Files:**
- Modify: `tests/global-setup.js`
- Create: `tests/checkout-frame.spec.js`

**Interfaces:**
- Consumes: the `clubhouse-checkout` classes from Task 2.
- Produces: a `checkout-fixture` page, with `surecart_checkout_page_id` pointed
  at it.

- [ ] **Step 1: Seed the fixture**

In `tests/global-setup.js`, inside the PHP heredoc after the `member-area-fixture`
block, add:

```php
// A page standing in for SureCart's checkout. CI has no SureCart, and
// installing it to assert our own frame would be testing SureCart. The stored
// page id IS the contract — Commerce_Pages dresses whichever post it names.
$checkout_existing = get_page_by_path( 'checkout-fixture' );
$checkout_id       = $checkout_existing instanceof WP_Post ? $checkout_existing->ID : wp_insert_post( array(
	'post_type'    => 'page',
	'post_status'  => 'publish',
	'post_name'    => 'checkout-fixture',
	'post_title'   => 'Checkout fixture',
	'post_content' => '<p id="shop-content">SHOP CONTENT</p>',
) );
if ( is_int( $checkout_id ) && $checkout_id > 0 ) {
	update_option( 'surecart_checkout_page_id', $checkout_id );
}
```

- [ ] **Step 2: Write the failing spec**

Create `tests/checkout-frame.spec.js`:

```js
const { test, expect } = require('@playwright/test');

// @wordpress only: the frame goes on whichever post SureCart records as its
// checkout, which the DB-free preview has none of.
//
// The harness has no SureCart, so checkout-fixture (seeded by
// tests/global-setup.js, with the checkout page option pointed at it) stands in
// for it. What these assert is our own frame and the shop's content surviving
// it — never SureCart's fields, which would be testing SureCart.
const CHECKOUT = '/checkout-fixture/';

test('the checkout wears its own frame @wordpress', async ({ page }) => {
  await page.goto(CHECKOUT);
  await expect(page.locator('.bw-admin.clubhouse-checkout')).toHaveCount(1);
  await expect(page.locator('.clubhouse-checkout__head')).toBeVisible();
  await expect(page.locator('.clubhouse-checkout__foot')).toBeVisible();
});

test("the shop's own content is passed through untouched @wordpress", async ({ page }) => {
  // The frame is chrome. The moment it starts rewriting what SureCart rendered,
  // a SureCart update breaks the form silently.
  await page.goto(CHECKOUT);
  await expect(page.locator('#shop-content')).toHaveText('SHOP CONTENT');
});

test('a buyer is offered no nav to wander off into @wordpress', async ({ page }) => {
  await page.goto(CHECKOUT);
  await expect(page.locator('.bw-secnav')).toHaveCount(0);
  await expect(page.locator('.clubhouse-member__tabbar')).toHaveCount(0);
});

test('the page has exactly one heading at the top level @wordpress', async ({ page }) => {
  await page.goto(CHECKOUT);
  await expect(page.locator('h1')).toHaveCount(1);
});

test('the field theme is asked for in the head, not the footer @wordpress', async ({ page }) => {
  // Queued any later and the buyer watches a payment form render bare and then
  // snap into shape — the worst page on the site for that.
  await page.goto(CHECKOUT);
  await expect(
    page.locator('head link[rel="stylesheet"][href*="surecart.css"]')
  ).toHaveCount(1);
});

test('the footer stacks into full-width targets on a phone @wordpress', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto(CHECKOUT);
  const back = page.locator('.clubhouse-checkout__back');
  await expect(back).toBeVisible();
  const box = await back.boundingBox();
  expect(box.height).toBeGreaterThanOrEqual(44);
});
```

- [ ] **Step 3: Run the spec to verify it fails**

Run: `npm run wp:up` then
`PLAYWRIGHT_BASE_URL=http://127.0.0.1:8705 npx playwright test tests/checkout-frame.spec.js`

Expected: FAIL — no `.clubhouse-checkout` element

(If the harness answers connection-refused, it is still booting. `npm run wp:up`
first and point Playwright at 8705 rather than using `npm run test:wp`.)

- [ ] **Step 4: Make it pass**

Nothing new to write — Tasks 1 to 4 supply it. If a spec fails, the failure is
real: fix the implementation, not the assertion.

Run the same command.
Expected: PASS, all six

- [ ] **Step 5: Run the whole suite both ways**

Run: `npm test`
Expected: PASS (the `@wordpress` specs skip)

Run: `npm run test:wp`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add tests/global-setup.js tests/checkout-frame.spec.js
git commit -m "Cover the checkout frame in a browser"
```

---

### Task 6: Version, changelog and notes

**Files:**
- Modify: `blueworx-labs-clubhouse.php` (two places), `package.json`,
  `CHANGELOG.md`, `docs/priorities.md`

- [ ] **Step 1: Bump the version**

`blueworx-labs-clubhouse.php` line 6 header and the
`BLUEWORX_LABS_CLUBHOUSE_VERSION` define, and `package.json` — all three from
`0.84.1` to `0.85.0`. A feature, so a minor bump.

- [ ] **Step 2: Write the changelog entry**

At the top of `CHANGELOG.md`, above `## 0.84.1`. What changed for the person
using it, in their words — no block names, no class names:

```markdown
## 0.85.0

- **Your checkout is now your club's own.** It carries your club's name and crest, your terms and privacy links, and the same look as the rest of the member area, instead of a page that looked like a different product.
- **You never have to build a checkout form.** One is set up for you, ready to take payments, and you can still change it if you want to.
- Card details are still handled entirely by Stripe. The club never sees them.
```

- [ ] **Step 3: Strike it from the priorities list**

`docs/priorities.md` records what to work on next. Add the checkout to the
running order as done, in the same voice as the entries around it.

- [ ] **Step 4: Verify everything one last time**

Run: `composer test`
Expected: PASS

Run: `npm run test:wp`
Expected: PASS

Run: `composer lint`
Expected: report the findings, do not fix them — lint findings go to Luke to
decide on, never auto-fixed in a loop.

- [ ] **Step 5: Commit**

```bash
git add blueworx-labs-clubhouse.php package.json CHANGELOG.md docs/priorities.md
git commit -m "Release 0.85.0"
```

---

## Self-Review

**Spec coverage.** Part 1 (theme stylesheet) is Task 1. Part 2 (page frame) is
Tasks 2 and 3. Part 3 (seeded form) is Task 4. The testing section is spread
across every task, with the Playwright half in Task 5. Accessibility — one `h1`,
the footer nav named and drawn only when populated, 44px targets on a phone — is
asserted in Tasks 2 and 5. The deferred extras have no task, correctly: the spec
lists them as non-goals.

**Placeholders.** None. Two steps say to read a file before writing
(`Frontend::context()` in Task 3, the registration site in Task 4) because the
exact accessor cannot be quoted without seeing it — each says what to do in both
cases rather than leaving it open.

**Type consistency.** `checkout( array $args )` in Task 2 is called with exactly
the keys it documents in Task 3. `footer_links( callable, callable )` returns
`array{label,href}`, which is the shape `checkout()`'s `links` expects.
`wants_surecart_style( string )` is used only where Task 1 defines it.
`filter_forms( mixed ): mixed` matches how `add_filter` calls it.
