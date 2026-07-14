# Setup Screen Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild the Clubhouse Setup admin screen as a bespoke, tabbed, look-inheriting UI that live-re-skins to the selected Base Look, and add Favicon + LinkedIn as stored, live-rendered brand inputs.

**Architecture:** Preserve the existing pure/glue split. `Setup_Screen` (pure HTML) is rewritten to the tabbed layout; `Setup_Controller` (WP glue) supplies an enriched model — including each look's composed design tokens and combined `@font-face` CSS — and sanitises the two new fields. `Setup_Screen` embeds the active look's tokens as a scoped custom-property block plus all looks' tokens as JSON; `admin-setup.js` swaps them on look selection. The admin stylesheet is rewritten entirely in `var(--color-*)`/`var(--font-*)`, exactly like a frontend look pack. Favicon emits `<link rel="icon">` in `wp_head`; LinkedIn joins the frontend `social()` block + footer.

**Tech Stack:** PHP 8.1 (strict types), WordPress plugin APIs (`wp.media`, `wp_head`, options), PHPUnit + a `function_exists`-guarded WP shim (`tests/php/wp-stubs.php`), vanilla progressive-enhancement JS, token-based CSS.

## Global Constraints

- **Version bump:** minor **v0.21.0 → v0.22.0** in `blueworx-labs-clubhouse.php` (plugin header **and** `BLUEWORX_LABS_CLUBHOUSE_VERSION` const) **and** `package.json`; matching `CHANGELOG.md` entry. (CI fails without an ascending bump + changelog.)
- **No new npm dependency** — do not touch `approved-deps.json`.
- **Pure/glue split:** `Setup_Screen` makes **no** WordPress calls and no persistence; all WP coupling stays in `Setup_Controller`. `Setup_Progress`, `Setup_Sections`, `Theme_Css`, `Color_Engine`, `Branding` stay pure.
- **Escaping:** every value rendered by `Setup_Screen` passes through its `esc()` (`htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`). Frontend section output uses `Sections::e()`.
- **Skin-agnostic frontend rule:** `assets/looks/*.css` and the section renderers reference the accent only via `var(--color-accent*)`; new SVG icons use `currentColor` only — **no hex, no icon font**.
- **Admin CSS hygiene:** `assets/css/admin-setup.css` references colours only via `var(--color-*)` and fonts only via `var(--font-*)`; it must not contain any of the looks' literal accents (`#c6f24e`, `#7a2f3a`, `#f7a70a`) or font-family names.
- **No-JS fallback:** all tabs/panels render stacked and usable server-side; JS only *enhances*. `wp.media` pickers already require JS (unchanged).
- **Lint:** `composer test` (PHPUnit) and `composer lint` (PHP_CodeSniffer) must both be green. Run the linter once at the end; do not loop.
- **Run tests with:** `composer test` (all) or `vendor/bin/phpunit --filter <TestClass>` (one class).

---

### Task 1: Branding — favicon + linkedin fields

**Files:**
- Modify: `includes/theme/class-branding.php`
- Test: `tests/php/BrandingTest.php`

**Interfaces:**
- Produces: `Branding::get_favicon(): string`, `Branding::set_favicon(string $url_or_id): void`, `Branding::get_linkedin_url(): string`, `Branding::set_linkedin_url(string $url): void`. New defaults: `favicon => ''`, `linkedin => 'https://linkedin.com/company/clubhouse'`.

- [ ] **Step 1: Write the failing tests**

Add to `tests/php/BrandingTest.php`:

```php
public function test_favicon_defaults_empty_and_round_trips(): void {
	$b = new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() );
	$this->assertSame( '', $b->get_favicon() );
	$b->set_favicon( '77' );
	$this->assertSame( '77', $b->get_favicon() );
}

public function test_linkedin_has_demo_default_and_round_trips(): void {
	$b = new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() );
	$this->assertSame( 'https://linkedin.com/company/clubhouse', $b->get_linkedin_url() );
	$b->set_linkedin_url( 'https://linkedin.com/company/riverside' );
	$this->assertSame( 'https://linkedin.com/company/riverside', $b->get_linkedin_url() );
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/phpunit --filter BrandingTest`
Expected: FAIL — `Call to undefined method ...::get_favicon()`.

- [ ] **Step 3: Implement**

In `includes/theme/class-branding.php`, add to the `DEFAULTS` array (after `'instagram' => ...`):

```php
		'linkedin'  => 'https://linkedin.com/company/clubhouse',
		'favicon'   => '',
```

Add these methods after `set_instagram_url()` (before the closing brace):

```php
	public function get_linkedin_url(): string {
		return (string) $this->value( 'linkedin' );
	}

	public function set_linkedin_url( string $url ): void {
		$this->put( 'linkedin', $url );
	}

	public function get_favicon(): string {
		return (string) $this->value( 'favicon' );
	}

	public function set_favicon( string $url_or_id ): void {
		$this->put( 'favicon', $url_or_id );
	}
```

Also update the class docblock line "one accent, club name, logo." → "one accent, club name, logo, favicon, socials."

- [ ] **Step 4: Run the tests to verify they pass**

Run: `vendor/bin/phpunit --filter BrandingTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add includes/theme/class-branding.php tests/php/BrandingTest.php
git commit -m "feat: add favicon + linkedin branding inputs"
```

---

### Task 2: Setup_Progress — regroup into 5 setup sections

**Files:**
- Modify: `includes/admin/class-setup-progress.php`
- Test: `tests/php/SetupProgressTest.php` (rewrite the assertions — the item set + total change)

**Interfaces:**
- Consumes: `Branding` (Task 1 getters), `Color_Engine::accent_is_legible_for`.
- Produces: `Setup_Progress::compute( Branding $branding, Base_Look $active_look, bool $look_chosen ): array{items:array{look:bool,accent:bool,club_name:bool,logo_favicon:bool,social:bool},completed:int,total:int}`. `total` is now **5**.

Grouping rules: `logo_favicon` = logo OR favicon set; `social` = any of Facebook / Instagram / LinkedIn differs from its demo default. Nothing is compulsory (progress is informational only; Save is never gated — see Task 6).

- [ ] **Step 1: Rewrite the failing tests**

Replace the body of `tests/php/SetupProgressTest.php` with:

```php
<?php
// tests/php/SetupProgressTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class SetupProgressTest extends TestCase {

	private function look(): Blueworx_Clubhouse_Base_Look {
		return new Blueworx_Clubhouse_Court_Side();
	}

	public function test_fresh_defaults_count_zero_over_five_groups(): void {
		$branding = new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() );
		$p = Blueworx_Clubhouse_Setup_Progress::compute( $branding, $this->look(), false );

		$this->assertSame( 5, $p['total'] );
		$this->assertSame( 0, $p['completed'] );
		$this->assertSame( array( 'look', 'accent', 'club_name', 'logo_favicon', 'social' ), array_keys( $p['items'] ) );
		foreach ( $p['items'] as $done ) {
			$this->assertFalse( $done );
		}
	}

	public function test_all_groups_complete(): void {
		$branding = new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() );
		$branding->set_accent( '#7a2f3a' );                 // legible on Court Side, != default
		$branding->set_club_name( 'Riverside RFC' );
		$branding->set_logo( '42' );
		$branding->set_facebook_url( 'https://facebook.com/riverside' );

		$p = Blueworx_Clubhouse_Setup_Progress::compute( $branding, $this->look(), true );

		foreach ( $p['items'] as $key => $done ) {
			$this->assertTrue( $done, "expected group {$key} to be complete" );
		}
		$this->assertSame( 5, $p['completed'] );
	}

	public function test_favicon_alone_completes_the_logo_favicon_group(): void {
		$branding = new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() );
		$branding->set_favicon( '99' ); // logo still empty
		$p = Blueworx_Clubhouse_Setup_Progress::compute( $branding, $this->look(), false );
		$this->assertTrue( $p['items']['logo_favicon'] );
	}

	public function test_linkedin_alone_completes_the_social_group(): void {
		$branding = new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() );
		$branding->set_linkedin_url( 'https://linkedin.com/company/riverside' ); // fb/ig still default
		$p = Blueworx_Clubhouse_Setup_Progress::compute( $branding, $this->look(), false );
		$this->assertTrue( $p['items']['social'] );
	}

	public function test_default_socials_do_not_count(): void {
		$branding = new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() );
		$p = Blueworx_Clubhouse_Setup_Progress::compute( $branding, $this->look(), false );
		$this->assertFalse( $p['items']['social'] );
	}

	public function test_illegible_non_default_accent_does_not_count(): void {
		$branding = new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() );
		$branding->set_accent( '#7a7a7a' ); // != default, but illegible-as-ink on Court Side
		$p = Blueworx_Clubhouse_Setup_Progress::compute( $branding, $this->look(), true );
		$this->assertFalse( $p['items']['accent'] );
	}
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/phpunit --filter SetupProgressTest`
Expected: FAIL — `total` is 6, and `logo_favicon`/`social` keys are absent.

- [ ] **Step 3: Implement**

Replace the `compute()` method and constants in `includes/admin/class-setup-progress.php`. Add a `DEMO_LINKEDIN` constant beside the others, and rewrite the class docblock ("five setup-progress booleans … by section") and `compute`:

```php
	// Mirror of Blueworx_Clubhouse_Branding::DEFAULTS (kept explicit for the check).
	private const DEMO_ACCENT    = '#c6f24e';
	private const DEMO_CLUB_NAME = 'ClubHouse';
	private const DEMO_FACEBOOK  = 'https://facebook.com/clubhouse';
	private const DEMO_INSTAGRAM = 'https://instagram.com/clubhouse';
	private const DEMO_LINKEDIN  = 'https://linkedin.com/company/clubhouse';

	/**
	 * @return array{items:array{look:bool,accent:bool,club_name:bool,logo_favicon:bool,social:bool},completed:int,total:int}
	 */
	public static function compute(
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Base_Look $active_look,
		bool $look_chosen
	): array {
		$accent = $branding->get_accent();

		$social = ( '' !== $branding->get_facebook_url()  && self::DEMO_FACEBOOK  !== $branding->get_facebook_url() )
			|| ( '' !== $branding->get_instagram_url() && self::DEMO_INSTAGRAM !== $branding->get_instagram_url() )
			|| ( '' !== $branding->get_linkedin_url()  && self::DEMO_LINKEDIN  !== $branding->get_linkedin_url() );

		$items = array(
			'look'         => $look_chosen,
			'accent'       => self::DEMO_ACCENT !== $accent
				&& Blueworx_Clubhouse_Color_Engine::accent_is_legible_for( $active_look, $accent ),
			'club_name'    => '' !== $branding->get_club_name() && self::DEMO_CLUB_NAME !== $branding->get_club_name(),
			'logo_favicon' => '' !== $branding->get_logo() || '' !== $branding->get_favicon(),
			'social'       => $social,
		);

		return array(
			'items'     => $items,
			'completed' => count( array_filter( $items ) ),
			'total'     => count( $items ),
		);
	}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `vendor/bin/phpunit --filter SetupProgressTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add includes/admin/class-setup-progress.php tests/php/SetupProgressTest.php
git commit -m "feat: recompute setup progress by section (5 groups)"
```

---

### Task 3: LinkedIn on the frontend — social() block + footer

**Files:**
- Modify: `includes/render/class-sections.php` (add `LINKEDIN_ICON`, extend `social()`, add LinkedIn to footer socials via caller)
- Modify: `includes/render/class-page-renderer.php` (thread `linkedin_url` into the two `social()` calls; add `'LinkedIn'` to the two footer `socials` arrays)
- Test: `tests/php/SectionsTest.php`

**Interfaces:**
- Consumes: `Branding::get_linkedin_url()` (Task 1).
- Produces: `Sections::social()` now reads `$data['linkedin_url']` and renders a third link. Data contract for `social()` becomes `array{heading,lede,facebook_url,instagram_url,linkedin_url}`.

- [ ] **Step 1: Write the failing tests**

Update `test_social_renders_links_with_labels_and_list_semantics` in `tests/php/SectionsTest.php` to include LinkedIn, and change the list-item count to 3:

```php
	public function test_social_renders_links_with_labels_and_list_semantics(): void {
		$html = Blueworx_Clubhouse_Sections::social( array(
			'heading'       => 'Follow the club',
			'lede'          => 'Match-day photos, results and behind-the-scenes.',
			'facebook_url'  => 'https://facebook.com/clubhouse',
			'instagram_url' => 'https://instagram.com/clubhouse',
			'linkedin_url'  => 'https://linkedin.com/company/clubhouse',
		) );
		$this->assertStringContainsString( 'class="ch-social"', $html );
		$this->assertStringContainsString( 'href="https://facebook.com/clubhouse"', $html );
		$this->assertStringContainsString( 'href="https://instagram.com/clubhouse"', $html );
		$this->assertStringContainsString( 'href="https://linkedin.com/company/clubhouse"', $html );
		$this->assertStringContainsString( 'aria-label="Follow us on LinkedIn"', $html );
		$this->assertStringContainsString( '>LinkedIn<', $html );
		$this->assertListSemantics( $html, 1, 3 );
		$this->assertNoHexColour( $html );
	}
```

Also update `test_social_escapes_heading_lede_and_urls` to pass a `'linkedin_url'` key (add `'linkedin_url' => 'https://linkedin.com/x?a=b&c="d"',` to that call so the required key is present).

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/phpunit --filter SectionsTest`
Expected: FAIL — LinkedIn href/label absent; list count is 2 not 3.

- [ ] **Step 3: Implement**

In `includes/render/class-sections.php`, add the icon constant beside `INSTAGRAM_ICON`:

```php
	/** Self-hosted brand mark, inherits colour via currentColor — no hex, no icon font. */
	private const LINKEDIN_ICON = '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true">'
		. '<path d="M20.45 20.45h-3.56v-5.57c0-1.33-.02-3.04-1.85-3.04-1.85 0-2.14 1.45-2.14 2.94v5.67H9.35V9h3.42v1.56h.05c.48-.9 1.64-1.85 3.37-1.85 3.6 0 4.27 2.37 4.27 5.46v6.28ZM5.34 7.43a2.07 2.07 0 1 1 0-4.14 2.07 2.07 0 0 1 0 4.14ZM7.12 20.45H3.55V9h3.57v11.45ZM22.22 0H1.77C.79 0 0 .77 0 1.73v20.54C0 23.22.79 24 1.77 24h20.45c.98 0 1.78-.78 1.78-1.73V1.73C24 .77 23.2 0 22.22 0Z"/></svg>';
```

Extend the `social()` `$links` array (add the LinkedIn row) and update its docblock `@param`:

```php
	/** Global "follow us" links — not a live/embedded feed. @param array{heading:string,lede:string,facebook_url:string,instagram_url:string,linkedin_url:string} $data */
	public static function social( array $data ): string {
		$links = array(
			array( 'label' => 'Facebook', 'url' => $data['facebook_url'], 'icon' => self::FACEBOOK_ICON ),
			array( 'label' => 'Instagram', 'url' => $data['instagram_url'], 'icon' => self::INSTAGRAM_ICON ),
			array( 'label' => 'LinkedIn', 'url' => $data['linkedin_url'], 'icon' => self::LINKEDIN_ICON ),
		);
```

(Leave the rest of `social()` unchanged — the `foreach` already renders each link.)

- [ ] **Step 4: Thread `linkedin_url` + footer socials through the page renderer**

In `includes/render/class-page-renderer.php`, both `social()` calls (in `home()` ~line 293 and `contact()` ~line 552) gain a `linkedin_url` key. Home:

```php
				$out .= Blueworx_Clubhouse_Sections::social( array(
					'heading'       => 'Follow the club',
					'lede'          => 'Match-day photos, results and behind-the-scenes — join us on socials.',
					'facebook_url'  => $branding->get_facebook_url(),
					'instagram_url' => $branding->get_instagram_url(),
					'linkedin_url'  => $branding->get_linkedin_url(),
				) );
```

Contact:

```php
				$out .= Blueworx_Clubhouse_Sections::social( array(
					'heading'       => 'Stay connected',
					'lede'          => 'Follow the club for match-day updates, results and event announcements.',
					'facebook_url'  => $branding->get_facebook_url(),
					'instagram_url' => $branding->get_instagram_url(),
					'linkedin_url'  => $branding->get_linkedin_url(),
				) );
```

In `shell_footer()` (~line 100), add `'LinkedIn'` to the decorative socials list:

```php
			'socials'    => array( 'Facebook', 'Instagram', 'LinkedIn', 'Community', 'Share' ),
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `vendor/bin/phpunit --filter SectionsTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add includes/render/class-sections.php includes/render/class-page-renderer.php tests/php/SectionsTest.php
git commit -m "feat: render LinkedIn in the social block and footer"
```

---

### Task 4: Favicon emission in wp_head

**Files:**
- Modify: `includes/frontend/class-frontend.php` (pure `favicon_link_html`, glue `render_favicon`, hook in `register()`)
- Test: `tests/php/FrontendTest.php`

**Interfaces:**
- Consumes: `Branding::get_favicon()` (Task 1), `Frontend::resolve_logo()` (reused to turn an attachment id/URL into a URL — it is media-agnostic).
- Produces: `Frontend::favicon_link_html( string $favicon_url ): string` — returns `<link rel="icon" href="…">` (escaped) or `''` when empty.

- [ ] **Step 1: Write the failing test**

Add to `tests/php/FrontendTest.php`:

```php
public function test_favicon_link_html_emits_link_when_set_and_nothing_when_empty(): void {
	$this->assertSame( '', Blueworx_Clubhouse_Frontend::favicon_link_html( '' ) );
	$html = Blueworx_Clubhouse_Frontend::favicon_link_html( 'https://club.test/favicon.png' );
	$this->assertStringContainsString( '<link rel="icon"', $html );
	$this->assertStringContainsString( 'href="https://club.test/favicon.png"', $html );
}

public function test_favicon_link_html_escapes_the_url(): void {
	$html = Blueworx_Clubhouse_Frontend::favicon_link_html( 'https://club.test/f.png?a=b&c="x"' );
	$this->assertStringContainsString( '&amp;', $html );
	$this->assertStringNotContainsString( '="x"', $html );
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit --filter FrontendTest`
Expected: FAIL — `Call to undefined method ...::favicon_link_html()`.

- [ ] **Step 3: Implement**

In `includes/frontend/class-frontend.php`, add the pure method (place it just after `resolve_logo()`):

```php
	/** Build the favicon <link> for a resolved favicon URL; empty string when none. */
	public static function favicon_link_html( string $favicon_url ): string {
		if ( '' === $favicon_url ) {
			return '';
		}
		return '<link rel="icon" href="' . htmlspecialchars( $favicon_url, ENT_QUOTES, 'UTF-8' ) . '">';
	}

	/** Echo the favicon <link> on clubhouse pages only (wp_head). */
	public static function render_favicon(): void {
		if ( null === self::current_slug() ) {
			return;
		}
		$favicon = self::resolve_logo( self::context()->branding->get_favicon() );
		echo self::favicon_link_html( $favicon ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in favicon_link_html.
	}
```

Register the hook in `register()` (after the `wp_enqueue_scripts` line):

```php
		add_action( 'wp_head', array( self::class, 'render_favicon' ) );
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `vendor/bin/phpunit --filter FrontendTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add includes/frontend/class-frontend.php tests/php/FrontendTest.php
git commit -m "feat: emit favicon link in wp_head on clubhouse pages"
```

---

### Task 5: Setup_Controller — sanitise new fields + per-look token model

**Files:**
- Modify: `includes/admin/class-setup-controller.php`
- Test: `tests/php/SetupControllerTest.php`

**Interfaces:**
- Consumes: `Branding` setters (Task 1), `Theme_Css::compose`, `Page_Renderer::font_face_css`, `Frontend::registry`, `Frontend::resolve_logo`.
- Produces (model additions consumed by Task 6):
  - `branding.favicon`, `branding.favicon_preview`, `branding.linkedin` (strings)
  - `active_slug` (string) — the active look's slug
  - `look_tokens` (`array<slug, array<token-name, value>>`) — each look's composed `:root` map at the current accent
  - `font_face_css` (string) — concatenated `@font-face` for every look

- [ ] **Step 1: Write the failing tests**

Add to `tests/php/SetupControllerTest.php`:

```php
public function test_handle_save_persists_favicon_and_linkedin(): void {
	$storage = new Blueworx_Clubhouse_Fake_Storage();
	Blueworx_Clubhouse_Setup_Controller::handle_save( array(
		'clubhouse_favicon'  => '88',
		'clubhouse_linkedin' => 'https://linkedin.com/company/riverside',
	), $storage );
	$b = new Blueworx_Clubhouse_Branding( $storage );
	$this->assertSame( '88', $b->get_favicon() );
	$this->assertSame( 'https://linkedin.com/company/riverside', $b->get_linkedin_url() );
}

public function test_build_model_exposes_favicon_linkedin_and_per_look_tokens(): void {
	$storage = new Blueworx_Clubhouse_Fake_Storage();
	$model   = Blueworx_Clubhouse_Setup_Controller::build_model( $storage, array(), '<nonce>', 'https://x/y' );

	$this->assertArrayHasKey( 'favicon', $model['branding'] );
	$this->assertArrayHasKey( 'linkedin', $model['branding'] );
	$this->assertArrayHasKey( 'look_tokens', $model );
	$this->assertArrayHasKey( 'court-side', $model['look_tokens'] );
	$this->assertArrayHasKey( '--color-bg', $model['look_tokens']['court-side'] );
	$this->assertArrayHasKey( '--color-accent-deep', $model['look_tokens']['court-side'] );
	$this->assertIsString( $model['font_face_css'] );
	$this->assertStringContainsString( '@font-face', $model['font_face_css'] );
}
```

> Note: `handle_save` and `build_model` take a `Storage` and are already WP-shim-safe (`sanitize_text_field`/`esc_url_raw`/`sanitize_hex_color` are stubbed in `tests/php/wp-stubs.php`; `wp_get_attachment_image_url` is stubbed to return `''`). If `esc_url_raw` is not yet in the shim, add a `function_exists`-guarded stub returning its argument.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/phpunit --filter SetupControllerTest`
Expected: FAIL — favicon/linkedin not persisted; `look_tokens`/`font_face_css` keys missing.

- [ ] **Step 3: Implement the save handling**

In `handle_save()` (`includes/admin/class-setup-controller.php`), after the instagram block (~line 68), add:

```php
		if ( isset( $post['clubhouse_linkedin'] ) ) {
			$branding->set_linkedin_url( esc_url_raw( (string) $post['clubhouse_linkedin'] ) );
		}
		if ( isset( $post['clubhouse_favicon'] ) ) {
			$branding->set_favicon( sanitize_text_field( (string) $post['clubhouse_favicon'] ) );
		}
```

- [ ] **Step 4: Implement the model additions**

Add a private helper to build the per-look token map + combined faces:

```php
	/**
	 * Compose each registered look's :root token map (at the current accent) plus
	 * the combined @font-face CSS for all looks — powers the live re-skin preview.
	 *
	 * @return array{tokens:array<string,array<string,string>>,faces:string}
	 */
	private static function look_theming( Blueworx_Clubhouse_Base_Look_Registry $registry, Blueworx_Clubhouse_Branding $branding, string $plugin_url ): array {
		$tokens = array();
		$faces  = '';
		foreach ( $registry->all() as $look ) {
			$tokens[ $look->slug() ] = Blueworx_Clubhouse_Theme_Css::compose( $look, $branding );
			$faces                  .= Blueworx_Clubhouse_Page_Renderer::font_face_css( $look, $plugin_url );
		}
		return array( 'tokens' => $tokens, 'faces' => $faces );
	}
```

In `build_model()`, add the favicon preview beside the logo preview:

```php
		$favicon         = $branding->get_favicon();
		$favicon_preview = '';
		if ( '' !== $favicon ) {
			$favicon_preview = ctype_digit( $favicon ) ? (string) wp_get_attachment_image_url( (int) $favicon, 'medium' ) : $favicon;
		}
		$plugin_url = defined( 'BLUEWORX_LABS_CLUBHOUSE_URL' ) ? BLUEWORX_LABS_CLUBHOUSE_URL : '';
		$theming    = self::look_theming( $registry, $branding, $plugin_url );
```

Extend the `branding` sub-array in the returned model:

```php
			'branding'    => array(
				'accent'          => $branding->get_accent(),
				'club_name'       => $branding->get_club_name(),
				'logo'            => $logo,
				'logo_preview'    => $logo_preview,
				'favicon'         => $favicon,
				'favicon_preview' => $favicon_preview,
				'facebook'        => $branding->get_facebook_url(),
				'instagram'       => $branding->get_instagram_url(),
				'linkedin'        => $branding->get_linkedin_url(),
			),
```

And add the three new top-level model keys (anywhere in the returned array):

```php
			'active_slug'   => null !== $active_look ? $active_look->slug() : '',
			'look_tokens'   => $theming['tokens'],
			'font_face_css' => $theming['faces'],
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `vendor/bin/phpunit --filter SetupControllerTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add includes/admin/class-setup-controller.php tests/php/SetupControllerTest.php
git commit -m "feat: setup controller sanitises favicon+linkedin and exposes per-look tokens"
```

---

### Task 6: Setup_Screen — bespoke tabbed layout

**Files:**
- Rewrite: `includes/admin/class-setup-screen.php`
- Test: `tests/php/SetupScreenTest.php` (rewrite — structure changed)

**Interfaces:**
- Consumes the Task-5 model: `progress` (5-group), `looks`, `active_slug`, `look_tokens`, `font_face_css`, `branding` (+favicon/linkedin), `inventory`, `visibility`, `demo_active`, `notices`, `nonce_field`, `action_url`.
- Produces the HTML the admin CSS/JS (Task 7) hook: container `.clubhouse-setup`, tab buttons `.clubhouse-tab[data-tab]`, panels `.clubhouse-panel[data-panel]`, look cards `.clubhouse-look-card` with `.clubhouse-look-card__preview` (inline look tokens), visibility sub-tabs `.clubhouse-vistab[data-vistab]` + sub-panels, toggle switches `.clubhouse-toggle`, JSON island `#clubhouse-look-tokens`, sticky bar `.clubhouse-bar` with an always-enabled submit.

Design notes for the implementer:
- **No-JS fallback:** render every panel and sub-panel in normal flow. The container starts without a `--js` class; `admin-setup.js` adds `.clubhouse-setup--js`, and the CSS hides non-active panels **only** under that class. So JS-off shows everything stacked.
- **Preview thumbnails:** each look card's `__preview` element carries `style="` + that look's tokens as inline custom properties (from `look_tokens[slug]`) so the mini preview paints in the look's own colours/fonts. Build the inline style by joining `name:value;` pairs (escape values with `esc()`).
- **Scoped skin:** emit `<style>` with `font_face_css`, then `.clubhouse-setup{...active look tokens...}` so the page paints in the saved look before JS runs.
- **Save is never disabled** — the submit button has no `disabled` attribute; the bar shows a progress nudge instead.

- [ ] **Step 1: Rewrite the tests**

Replace `tests/php/SetupScreenTest.php` with:

```php
<?php
// tests/php/SetupScreenTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class SetupScreenTest extends TestCase {

	private function model(): array {
		$look = new Blueworx_Clubhouse_Court_Side();
		$tokens = array(
			'court-side'    => $look->tokens() + array( '--color-accent-deep' => '#3a6a00' ),
			'members-house' => ( new Blueworx_Clubhouse_Members_House() )->tokens() + array( '--color-accent-deep' => '#3a6a00' ),
			'floodlight'    => ( new Blueworx_Clubhouse_Floodlight() )->tokens() + array( '--color-accent-deep' => '#cfe86a' ),
		);
		return array(
			'nonce_field'   => '<input type="hidden" name="_wpnonce" value="NONCE123">',
			'action_url'    => 'https://club.test/wp-admin/admin.php?page=clubhouse-setup',
			'notices'       => array( array( 'type' => 'error', 'text' => 'That accent is too low-contrast.' ) ),
			'progress'      => array(
				'items'     => array( 'look' => true, 'accent' => false, 'club_name' => true, 'logo_favicon' => false, 'social' => false ),
				'completed' => 2,
				'total'     => 5,
			),
			'looks'         => array(
				array( 'slug' => 'court-side', 'name' => 'Court Side', 'description' => 'Bright & playful.', 'active' => true ),
				array( 'slug' => 'members-house', 'name' => "Members' House", 'description' => 'Editorial.', 'active' => false ),
				array( 'slug' => 'floodlight', 'name' => 'Floodlight', 'description' => 'Dark night-match.', 'active' => false ),
			),
			'active_slug'   => 'court-side',
			'look_tokens'   => $tokens,
			'font_face_css' => "@font-face{font-family:'Syne';src:url(x)}",
			'branding'      => array(
				'accent' => '#c6f24e', 'club_name' => 'Riverside & Sons', 'logo' => '42',
				'logo_preview' => 'https://club.test/logo.png',
				'favicon' => '', 'favicon_preview' => '',
				'facebook' => 'https://facebook.com/riverside', 'instagram' => '',
				'linkedin' => 'https://linkedin.com/company/riverside',
			),
			'inventory'     => Blueworx_Clubhouse_Setup_Sections::inventory(),
			'visibility'    => array( 'pages' => array( 'events' => false ), 'sections' => array( 'home.ticker' => false ) ),
			'demo_active'   => false,
		);
	}

	public function test_renders_nonce_action_and_progress_out_of_five(): void {
		$html = Blueworx_Clubhouse_Setup_Screen::render( $this->model() );
		$this->assertStringContainsString( 'name="_wpnonce" value="NONCE123"', $html );
		$this->assertStringContainsString( 'action="https://club.test/wp-admin/admin.php?page=clubhouse-setup"', $html );
		$this->assertStringContainsString( '2 of 5', $html );
	}

	public function test_renders_three_tabs(): void {
		$html = Blueworx_Clubhouse_Setup_Screen::render( $this->model() );
		$this->assertStringContainsString( 'data-tab="look"', $html );
		$this->assertStringContainsString( 'data-tab="visibility"', $html );
		$this->assertStringContainsString( 'data-tab="demo"', $html );
	}

	public function test_renders_look_cards_with_active_marked_and_token_preview(): void {
		$html = Blueworx_Clubhouse_Setup_Screen::render( $this->model() );
		$this->assertSame( 3, substr_count( $html, 'name="clubhouse_look"' ) );
		$this->assertStringContainsString( 'value="court-side" checked', $html );
		$this->assertStringContainsString( 'clubhouse-look-card__preview', $html );
		$this->assertStringContainsString( '--color-bg:', $html ); // preview carries look tokens inline
	}

	public function test_embeds_look_tokens_json_and_font_faces(): void {
		$html = Blueworx_Clubhouse_Setup_Screen::render( $this->model() );
		$this->assertStringContainsString( 'id="clubhouse-look-tokens"', $html );
		$this->assertStringContainsString( '@font-face', $html );
		$this->assertStringContainsString( 'members-house', $html );
	}

	public function test_renders_branding_incl_favicon_and_linkedin_escaped(): void {
		$html = Blueworx_Clubhouse_Setup_Screen::render( $this->model() );
		$this->assertStringContainsString( 'name="clubhouse_accent"', $html );
		$this->assertStringContainsString( 'value="Riverside &amp; Sons"', $html );
		$this->assertStringContainsString( 'name="clubhouse_favicon"', $html );
		$this->assertStringContainsString( 'name="clubhouse_linkedin"', $html );
		$this->assertStringContainsString( 'value="https://linkedin.com/company/riverside"', $html );
	}

	public function test_renders_a_toggle_per_section_plus_per_page(): void {
		$html = Blueworx_Clubhouse_Setup_Screen::render( $this->model() );
		$this->assertSame( 45, substr_count( $html, 'name="clubhouse_section[' ) );
		$this->assertSame( 9, substr_count( $html, 'name="clubhouse_page[' ) );
		$this->assertStringContainsString( 'name="clubhouse_section[home.hero]" value="1" checked', $html );
		$this->assertStringContainsString( 'name="clubhouse_section[home.ticker]" value="1">', $html );
	}

	public function test_save_button_is_never_disabled(): void {
		$html = Blueworx_Clubhouse_Setup_Screen::render( $this->model() );
		$this->assertStringContainsString( 'clubhouse_setup_submit', $html );
		$this->assertDoesNotMatchRegularExpression( '/<button[^>]*type="submit"[^>]*disabled/', $html );
	}

	public function test_renders_error_notice_and_demo_toggle(): void {
		$html = Blueworx_Clubhouse_Setup_Screen::render( $this->model() );
		$this->assertStringContainsString( 'notice notice-error', $html );
		$this->assertStringContainsString( 'That accent is too low-contrast.', $html );
		$this->assertStringContainsString( 'name="clubhouse_demo_active"', $html );
	}

	public function test_checks_demo_toggle_when_active(): void {
		$model = $this->model();
		$model['demo_active'] = true;
		$html = Blueworx_Clubhouse_Setup_Screen::render( $model );
		$this->assertMatchesRegularExpression( '/name="clubhouse_demo_active"[^>]*checked/', $html );
	}
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/phpunit --filter SetupScreenTest`
Expected: FAIL — no tabs, no token preview, favicon/linkedin fields absent.

- [ ] **Step 3: Rewrite `Setup_Screen`**

Replace `includes/admin/class-setup-screen.php` with:

```php
<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pure HTML builder for the Clubhouse Setup admin page: a bespoke, tabbed,
 * look-inheriting form. The controller supplies the model (incl. each look's
 * composed design tokens and combined @font-face CSS) and the WP nonce/action;
 * this class makes no WordPress calls and no persistence.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Setup_Screen {

	private static function esc( string $v ): string {
		return htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' );
	}

	/** Join a token map into an inline custom-property string: "--k:v;--k2:v2;". */
	private static function inline_tokens( array $tokens ): string {
		$out = '';
		foreach ( $tokens as $name => $value ) {
			$out .= self::esc( (string) $name ) . ':' . self::esc( (string) $value ) . ';';
		}
		return $out;
	}

	/** @param array<string,mixed> $model */
	public static function render( array $model ): string {
		$active_tokens = $model['look_tokens'][ $model['active_slug'] ] ?? array();

		$out  = '<div class="wrap">';
		$out .= '<style>' . $model['font_face_css']
			. '.clubhouse-setup{' . self::inline_tokens( $active_tokens ) . '}</style>';
		$out .= '<div class="clubhouse-setup">';
		$out .= self::header( $model['progress'] );
		$out .= self::notices( $model['notices'] );
		$out .= '<form method="post" action="' . self::esc( (string) $model['action_url'] ) . '" class="clubhouse-form">';
		$out .= $model['nonce_field'];

		// Tab nav.
		$out .= '<div class="clubhouse-tabs" role="tablist">';
		$out .= '<button type="button" class="clubhouse-tab is-active" data-tab="look">Base Look &amp; Branding</button>';
		$out .= '<button type="button" class="clubhouse-tab" data-tab="visibility">Visibility</button>';
		$out .= '<button type="button" class="clubhouse-tab" data-tab="demo">Demo Mode</button>';
		$out .= '</div>';

		$out .= '<section class="clubhouse-panel is-active" data-panel="look">'
			. self::look_area( $model['looks'], $model['look_tokens'] )
			. self::branding_area( $model['branding'] ) . '</section>';
		$out .= '<section class="clubhouse-panel" data-panel="visibility">'
			. self::visibility_area( $model['inventory'], $model['visibility'] ) . '</section>';
		$out .= '<section class="clubhouse-panel" data-panel="demo">'
			. self::demo_area( (bool) ( $model['demo_active'] ?? false ) ) . '</section>';

		$out .= self::save_bar( $model['progress'] );
		$out .= '</form>';

		// JSON island for the live re-skin.
		$json = json_encode( $model['look_tokens'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
		$out .= '<script type="application/json" id="clubhouse-look-tokens">' . $json . '</script>';

		$out .= '</div></div>';
		return $out;
	}

	/** @param array{completed:int,total:int} $p */
	private static function header( array $p ): string {
		$pct = 0 === $p['total'] ? 0 : (int) round( 100 * $p['completed'] / $p['total'] );
		return '<header class="clubhouse-head">'
			. '<div class="clubhouse-head__titles"><p class="clubhouse-eyebrow">Clubhouse · Site setup</p>'
			. '<h1 class="clubhouse-head__h1">Clubhouse Setup</h1></div>'
			. '<div class="clubhouse-head__progress"><p class="clubhouse-pct">' . $pct . '%</p>'
			. '<p class="clubhouse-progress__label">' . (int) $p['completed'] . ' of ' . (int) $p['total'] . ' complete</p>'
			. '<div class="clubhouse-progress__track"><div class="clubhouse-progress__bar" style="width:' . $pct . '%"></div></div>'
			. '</div></header>';
	}

	/** @param array<int,array{type:string,text:string}> $notices */
	private static function notices( array $notices ): string {
		$out = '';
		foreach ( $notices as $n ) {
			$type = in_array( $n['type'], array( 'error', 'warning', 'success' ), true ) ? $n['type'] : 'info';
			$out .= '<div class="notice notice-' . self::esc( $type ) . '"><p>' . self::esc( $n['text'] ) . '</p></div>';
		}
		return $out;
	}

	/**
	 * @param array<int,array{slug:string,name:string,description:string,active:bool}> $looks
	 * @param array<string,array<string,string>> $look_tokens
	 */
	private static function look_area( array $looks, array $look_tokens ): string {
		$out  = '<div class="clubhouse-step"><p class="clubhouse-step__k">Step 1 · Foundation</p><h2 class="clubhouse-step__h">Base Look</h2>';
		$out .= '<p class="clubhouse-step__lede">Pick the visual foundation for your club site. Everything else adapts to it.</p>';
		$out .= '<div class="clubhouse-looks" role="radiogroup" aria-label="Base Look">';
		foreach ( $looks as $look ) {
			$checked = $look['active'] ? ' checked' : '';
			$style   = self::inline_tokens( $look_tokens[ $look['slug'] ] ?? array() );
			$out .= '<label class="clubhouse-look-card">';
			$out .= '<span class="clubhouse-look-card__preview" style="' . $style . '">'
				. '<span class="clubhouse-look-card__bar"></span><span class="clubhouse-look-card__accent"></span>'
				. '<span class="clubhouse-look-card__line"></span><span class="clubhouse-look-card__line"></span></span>';
			$out .= '<input type="radio" name="clubhouse_look" value="' . self::esc( $look['slug'] ) . '"' . $checked . '>';
			$out .= '<span class="clubhouse-look-card__name">' . self::esc( $look['name'] ) . '</span>';
			$out .= '<span class="clubhouse-look-card__desc">' . self::esc( $look['description'] ) . '</span>';
			$out .= '</label>';
		}
		$out .= '</div></div>';
		return $out;
	}

	/** @param array<string,string> $b */
	private static function branding_area( array $b ): string {
		$out  = '<div class="clubhouse-step"><p class="clubhouse-step__k">Step 2 · Branding</p><h2 class="clubhouse-step__h">Make it yours</h2>';
		$out .= '<div class="clubhouse-fields">';
		$out .= '<div class="clubhouse-field"><label class="clubhouse-label" for="clubhouse_accent">Accent colour</label>'
			. '<div class="clubhouse-accent"><span class="clubhouse-accent__swatch" id="clubhouse-accent-swatch" style="background:' . self::esc( (string) $b['accent'] ) . '"></span>'
			. '<input type="text" id="clubhouse_accent" name="clubhouse_accent" value="' . self::esc( (string) $b['accent'] ) . '" class="clubhouse-input"></div>'
			. '<p class="clubhouse-help">Used for buttons, links and highlights. Must be legible on the chosen look.</p></div>';
		$out .= self::text_field( 'clubhouse_club_name', 'Club name', (string) $b['club_name'] );
		$out .= self::media_field( 'clubhouse_logo', 'Logo', (string) $b['logo'], (string) $b['logo_preview'], 'No logo set — SVG or PNG, up to 2 MB' );
		$out .= self::media_field( 'clubhouse_favicon', 'Favicon', (string) $b['favicon'], (string) $b['favicon_preview'], 'No favicon set — square PNG, ICO or SVG' );
		$out .= self::text_field( 'clubhouse_facebook', 'Facebook URL', (string) $b['facebook'], 'url' );
		$out .= self::text_field( 'clubhouse_instagram', 'Instagram URL', (string) $b['instagram'], 'url' );
		$out .= self::text_field( 'clubhouse_linkedin', 'LinkedIn URL', (string) $b['linkedin'], 'url' );
		$out .= '</div></div>';
		return $out;
	}

	private static function text_field( string $name, string $label, string $value, string $type = 'text' ): string {
		return '<div class="clubhouse-field"><label class="clubhouse-label" for="' . self::esc( $name ) . '">' . self::esc( $label ) . '</label>'
			. '<input type="' . self::esc( $type ) . '" id="' . self::esc( $name ) . '" name="' . self::esc( $name ) . '" value="' . self::esc( $value ) . '" class="clubhouse-input"></div>';
	}

	private static function media_field( string $name, string $label, string $value, string $preview, string $empty ): string {
		$prev = '' !== $preview
			? '<img class="clubhouse-media__img" src="' . self::esc( $preview ) . '" alt="Current ' . self::esc( strtolower( $label ) ) . '">'
			: '<span class="clubhouse-media__empty" aria-hidden="true"></span>';
		return '<div class="clubhouse-field"><span class="clubhouse-label">' . self::esc( $label ) . '</span>'
			. '<div class="clubhouse-media" data-media="' . self::esc( $name ) . '">'
			. '<input type="hidden" id="' . self::esc( $name ) . '" name="' . self::esc( $name ) . '" value="' . self::esc( $value ) . '">'
			. '<span class="clubhouse-media__preview">' . $prev . '</span>'
			. '<span class="clubhouse-media__meta"><span class="clubhouse-media__hint">' . self::esc( $empty ) . '</span>'
			. '<span class="clubhouse-media__actions"><button type="button" class="clubhouse-btn clubhouse-btn--sm" data-media-pick>Choose ' . self::esc( strtolower( $label ) ) . '</button>'
			. '<button type="button" class="clubhouse-btn-link" data-media-clear>Remove</button></span></span>'
			. '</div></div>';
	}

	/**
	 * @param array<int,array{page:string,label:string,sections:array<int,array{key:string,label:string}>}> $inventory
	 * @param array{pages:array<string,bool>,sections:array<string,bool>} $visibility
	 */
	private static function visibility_area( array $inventory, array $visibility ): string {
		$out  = '<div class="clubhouse-step"><p class="clubhouse-step__k">Step 3 · Visibility</p><h2 class="clubhouse-step__h">What visitors see</h2>';
		$out .= '<p class="clubhouse-step__lede">Everything is shown by default. Switch off any page or the sections within it.</p>';

		// Sub-tab nav — one per page, counts from live state.
		$out .= '<div class="clubhouse-vistabs" role="tablist">';
		$first = true;
		foreach ( $inventory as $page ) {
			$shown = 0;
			foreach ( $page['sections'] as $section ) {
				if ( $visibility['sections'][ $page['page'] . '.' . $section['key'] ] ?? true ) {
					$shown++;
				}
			}
			$total = count( $page['sections'] );
			$cls   = $first ? ' is-active' : '';
			$out  .= '<button type="button" class="clubhouse-vistab' . $cls . '" data-vistab="' . self::esc( $page['page'] ) . '">'
				. self::esc( $page['label'] ) . ' <span class="clubhouse-vistab__count">' . $shown . '/' . $total . '</span></button>';
			$first = false;
		}
		$out .= '</div>';

		// Sub-panels.
		$first = true;
		foreach ( $inventory as $page ) {
			$page_on = ( $visibility['pages'][ $page['page'] ] ?? true );
			$cls     = $first ? ' is-active' : '';
			$out .= '<div class="clubhouse-vispanel' . $cls . '" data-vispanel="' . self::esc( $page['page'] ) . '">';
			$out .= '<div class="clubhouse-vispanel__head"><span class="clubhouse-vispanel__title">' . self::esc( $page['label'] ) . ' sections</span>';
			$out .= self::toggle( 'clubhouse_page[' . $page['page'] . ']', 'Page shown', $page_on ) . '</div>';
			$out .= '<div class="clubhouse-toggle-grid">';
			foreach ( $page['sections'] as $section ) {
				$skey = $page['page'] . '.' . $section['key'];
				$on   = ( $visibility['sections'][ $skey ] ?? true );
				$out .= self::toggle( 'clubhouse_section[' . $skey . ']', $section['label'], $on );
			}
			$out .= '</div></div>';
			$first = false;
		}
		$out .= '</div>';
		return $out;
	}

	private static function toggle( string $name, string $label, bool $on ): string {
		$checked = $on ? ' checked' : '';
		return '<label class="clubhouse-toggle"><input type="checkbox" name="' . self::esc( $name ) . '" value="1"' . $checked . '>'
			. '<span class="clubhouse-toggle__track"><span class="clubhouse-toggle__thumb"></span></span>'
			. '<span class="clubhouse-toggle__label">' . self::esc( $label ) . '</span></label>';
	}

	private static function demo_area( bool $active ): string {
		$checked = $active ? ' checked' : '';
		$out  = '<div class="clubhouse-step"><p class="clubhouse-step__k">Step 4 · Demo mode</p><h2 class="clubhouse-step__h">Preview for everyone</h2>';
		$out .= '<p class="clubhouse-step__lede">When on, every visitor sees a floating switcher to preview the base looks, and the site renders in a demo look. Your saved look isn\'t changed — only administrators can turn this on or off.</p>';
		$out .= '<div class="clubhouse-demo-card">'
			. self::toggle( 'clubhouse_demo_active', 'Enable demo mode for all visitors', $active )
			. '</div></div>';
		return $out;
	}

	/** @param array{completed:int,total:int} $p */
	private static function save_bar( array $p ): string {
		$done = $p['completed'] >= $p['total'];
		$hint = $done
			? 'Everything set — save your changes.'
			: (int) $p['completed'] . ' of ' . (int) $p['total'] . ' sections done — save now and finish later.';
		return '<div class="clubhouse-bar"><p class="clubhouse-bar__hint">' . self::esc( $hint ) . '</p>'
			. '<button type="submit" name="clubhouse_setup_submit" value="1" class="clubhouse-btn clubhouse-btn--primary">Save changes</button></div>';
	}
}
```

> The controller's `screen_html()` currently appends `clubhouse_setup_submit` as a hidden input inside `nonce_field`. The submit button now carries `name="clubhouse_setup_submit"`, so **remove** the hidden `clubhouse_setup_submit` input from `Setup_Controller::screen_html()` to avoid a duplicate (keep the `wp_nonce_field` call). Verify `render_page()` still gates on `isset( $_POST['clubhouse_setup_submit'] )` — it does.

- [ ] **Step 4: Trim the duplicate submit input in the controller**

In `includes/admin/class-setup-controller.php` `screen_html()`, change:

```php
		$nonce_field = wp_nonce_field( self::NONCE, '_wpnonce', true, false )
			. '<input type="hidden" name="clubhouse_setup_submit" value="1">';
```

to:

```php
		$nonce_field = wp_nonce_field( self::NONCE, '_wpnonce', true, false );
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `vendor/bin/phpunit --filter SetupScreenTest`
Expected: PASS.

- [ ] **Step 6: Run the full suite (catches model-shape regressions in controller tests)**

Run: `composer test`
Expected: PASS (all classes).

- [ ] **Step 7: Commit**

```bash
git add includes/admin/class-setup-screen.php includes/admin/class-setup-controller.php tests/php/SetupScreenTest.php
git commit -m "feat: bespoke tabbed setup screen with live-reskin preview"
```

---

### Task 7: Admin stylesheet (token-based) + enhancement JS

**Files:**
- Rewrite: `assets/css/admin-setup.css`
- Rewrite: `assets/js/admin-setup.js`
- Test: `tests/php/AdminSetupStylesheetTest.php` (new — hygiene, mirrors `CourtSideStylesheetTest`)

**Interfaces:**
- Consumes the DOM hooks Task 6 emits (`.clubhouse-setup`, `.clubhouse-tab[data-tab]`, `.clubhouse-panel[data-panel]`, `.clubhouse-vistab`/`.clubhouse-vispanel`, `.clubhouse-toggle`, `.clubhouse-media[data-media]`, `#clubhouse-look-tokens`, `#clubhouse_accent`/`#clubhouse-accent-swatch`).
- Produces: no PHP interface; verified by the hygiene test + manual smoke.

- [ ] **Step 1: Write the failing hygiene test**

Create `tests/php/AdminSetupStylesheetTest.php`:

```php
<?php
// tests/php/AdminSetupStylesheetTest.php
use PHPUnit\Framework\TestCase;

final class AdminSetupStylesheetTest extends TestCase {

	private function css(): string {
		$path = dirname( __DIR__, 2 ) . '/assets/css/admin-setup.css';
		$this->assertFileExists( $path );
		return (string) file_get_contents( $path );
	}

	public function test_styles_the_core_setup_hooks(): void {
		$css = $this->css();
		foreach ( array( '.clubhouse-setup', '.clubhouse-tab', '.clubhouse-panel', '.clubhouse-look-card', '.clubhouse-toggle', '.clubhouse-bar' ) as $sel ) {
			$this->assertStringContainsString( $sel, $css );
		}
	}

	public function test_uses_look_tokens_not_literal_colours_or_fonts(): void {
		$css = $this->css();
		$this->assertStringContainsString( 'var(--color-', $css );
		$this->assertStringContainsString( 'var(--font-', $css );
		// No look's literal accent may be baked in — that would break re-skinning.
		$this->assertStringNotContainsString( '#c6f24e', $css );
		$this->assertStringNotContainsString( '#7a2f3a', $css );
		$this->assertStringNotContainsString( '#f7a70a', $css );
		// No font-family names — fonts arrive only via the tokens.
		foreach ( array( 'Syne', 'Fraunces', 'Bricolage', 'Hanken', 'Mulish' ) as $family ) {
			$this->assertStringNotContainsString( $family, $css );
		}
	}

	public function test_panels_only_hide_under_the_js_class(): void {
		$css = $this->css();
		// JS-off must show everything: hiding is gated on the --js container class.
		$this->assertStringContainsString( '.clubhouse-setup--js', $css );
	}
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit --filter AdminSetupStylesheetTest`
Expected: FAIL — current `admin-setup.css` uses literal `#2271b1` and none of the new hooks.

- [ ] **Step 3: Rewrite `assets/css/admin-setup.css`**

Replace the file with a token-based stylesheet. It scopes everything under `.clubhouse-setup`, consumes the injected look tokens, and hides inactive panels only under `--js`:

```css
/* Clubhouse Setup — bespoke admin skin. All colour/type via injected look tokens
   (var(--color-*) / var(--font-*) / var(--radius-*)); no literals, no font names. */

.clubhouse-setup {
	--pad: 24px;
	max-width: 1120px;
	margin: 8px auto 0;
	padding: 0 16px 120px;
	background: var(--color-bg);
	color: var(--color-ink);
	font-family: var(--font-body);
	border-radius: var(--radius-lg);
}
.clubhouse-setup * { box-sizing: border-box; }

/* Header + progress */
.clubhouse-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 24px; padding: 32px var(--pad) 20px; border-bottom: 1px solid var(--color-line); }
.clubhouse-eyebrow { font-size: 11px; letter-spacing: .14em; text-transform: uppercase; color: var(--color-accent-deep); margin: 0 0 6px; }
.clubhouse-head__h1 { font-family: var(--font-display); font-size: 30px; line-height: 1.1; margin: 0; color: var(--color-ink); text-transform: uppercase; }
.clubhouse-head__progress { text-align: right; min-width: 200px; }
.clubhouse-pct { font-family: var(--font-display); font-size: 26px; margin: 0; color: var(--color-accent-deep); }
.clubhouse-progress__label { font-size: 12px; color: var(--color-ink-soft); margin: 2px 0 8px; }
.clubhouse-progress__track { height: 8px; border-radius: 999px; background: var(--color-line); overflow: hidden; }
.clubhouse-progress__bar { height: 100%; background: var(--color-accent-deep); transition: width .3s ease; }

/* Tabs */
.clubhouse-tabs { display: flex; gap: 6px; padding: 14px var(--pad) 0; border-bottom: 1px solid var(--color-line); flex-wrap: wrap; }
.clubhouse-tab { appearance: none; border: 0; background: transparent; font-family: var(--font-body); font-size: 12px; letter-spacing: .1em; text-transform: uppercase; color: var(--color-ink-soft); padding: 10px 12px; cursor: pointer; border-bottom: 2px solid transparent; }
.clubhouse-tab.is-active { color: var(--color-ink); border-bottom-color: var(--color-accent-deep); }

/* Panels — visible by default (no-JS); JS hides the inactive ones. */
.clubhouse-panel { padding: 24px var(--pad); }
.clubhouse-setup--js .clubhouse-panel { display: none; }
.clubhouse-setup--js .clubhouse-panel.is-active { display: block; }

/* Steps */
.clubhouse-step { background: var(--color-paper); border: 1px solid var(--color-line); border-radius: var(--radius-lg); padding: 24px; margin: 0 0 20px; }
.clubhouse-step__k { font-size: 11px; letter-spacing: .12em; text-transform: uppercase; color: var(--color-accent-deep); margin: 0 0 4px; }
.clubhouse-step__h { font-family: var(--font-display); font-size: 20px; text-transform: uppercase; margin: 0 0 8px; color: var(--color-ink); }
.clubhouse-step__lede { color: var(--color-ink-soft); font-size: 14px; margin: 0 0 18px; max-width: 60ch; }

/* Look cards */
.clubhouse-looks { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 14px; }
.clubhouse-look-card { display: block; border: 2px solid var(--color-line); border-radius: var(--radius-md); padding: 12px; cursor: pointer; background: var(--color-bg); position: relative; }
.clubhouse-look-card:has(input:checked) { border-color: var(--color-accent-deep); }
.clubhouse-look-card input { position: absolute; top: 12px; right: 12px; }
.clubhouse-look-card__preview { display: block; height: 84px; border-radius: var(--radius-md); padding: 12px; margin-bottom: 12px; background: var(--color-bg); border: 1px solid var(--color-line); font-family: var(--font-display); }
.clubhouse-look-card__bar { display: block; height: 8px; width: 40%; border-radius: 999px; background: var(--color-ink); margin-bottom: 8px; }
.clubhouse-look-card__accent { display: block; height: 22px; border-radius: 6px; background: var(--color-accent); margin-bottom: 8px; }
.clubhouse-look-card__line { display: inline-block; height: 6px; width: 28%; border-radius: 999px; background: var(--color-line); margin-right: 6px; }
.clubhouse-look-card__name { display: block; font-family: var(--font-display); font-weight: 600; margin: 4px 0; color: var(--color-ink); }
.clubhouse-look-card__desc { display: block; font-size: 13px; color: var(--color-ink-soft); }

/* Fields */
.clubhouse-fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
.clubhouse-field { display: flex; flex-direction: column; gap: 6px; }
.clubhouse-label { font-size: 11px; letter-spacing: .08em; text-transform: uppercase; color: var(--color-ink-soft); }
.clubhouse-input { font-family: var(--font-body); font-size: 14px; color: var(--color-ink); background: var(--color-bg); border: 1px solid var(--color-line); border-radius: var(--radius-md); padding: 9px 12px; }
.clubhouse-input:focus-visible { outline: 2px solid var(--color-accent-deep); outline-offset: 1px; }
.clubhouse-help { font-size: 12px; color: var(--color-ink-soft); margin: 0; }
.clubhouse-accent { display: flex; align-items: center; gap: 10px; }
.clubhouse-accent__swatch { width: 34px; height: 34px; border-radius: var(--radius-md); border: 1px solid var(--color-line); flex: none; }

/* Media (logo + favicon) */
.clubhouse-media { display: flex; align-items: center; gap: 14px; }
.clubhouse-media__preview { flex: none; }
.clubhouse-media__img { max-height: 56px; max-width: 96px; border-radius: var(--radius-md); }
.clubhouse-media__empty { display: block; width: 48px; height: 48px; border-radius: var(--radius-md); border: 1px dashed var(--color-line); }
.clubhouse-media__hint { display: block; font-size: 12px; color: var(--color-ink-soft); margin-bottom: 8px; }
.clubhouse-media__actions { display: flex; gap: 10px; align-items: center; }

/* Buttons */
.clubhouse-btn { font-family: var(--font-body); font-size: 13px; border-radius: 999px; padding: 8px 16px; border: 1px solid var(--color-ink); background: transparent; color: var(--color-ink); cursor: pointer; }
.clubhouse-btn--sm { padding: 6px 12px; font-size: 12px; }
.clubhouse-btn--primary { background: var(--color-ink); color: var(--color-bg); border-color: var(--color-ink); }
.clubhouse-btn-link { background: transparent; border: 0; color: var(--color-ink-soft); text-decoration: underline; cursor: pointer; font-size: 12px; }

/* Visibility sub-tabs + toggles */
.clubhouse-vistabs { display: flex; gap: 8px; overflow-x: auto; padding-bottom: 6px; margin-bottom: 16px; }
.clubhouse-vistab { flex: none; appearance: none; border: 1px solid var(--color-line); background: var(--color-bg); color: var(--color-ink-soft); border-radius: 999px; padding: 6px 14px; font-size: 12px; cursor: pointer; }
.clubhouse-vistab.is-active { background: var(--color-ink); color: var(--color-bg); border-color: var(--color-ink); }
.clubhouse-vistab__count { opacity: .7; font-size: 11px; }
.clubhouse-vispanel { display: block; }
.clubhouse-setup--js .clubhouse-vispanel { display: none; }
.clubhouse-setup--js .clubhouse-vispanel.is-active { display: block; }
.clubhouse-vispanel__head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; }
.clubhouse-vispanel__title { font-size: 12px; letter-spacing: .08em; text-transform: uppercase; color: var(--color-ink-soft); }
.clubhouse-toggle-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; }

.clubhouse-toggle { display: inline-flex; align-items: center; gap: 10px; cursor: pointer; padding: 8px 10px; border: 1px solid var(--color-line); border-radius: var(--radius-md); background: var(--color-bg); }
.clubhouse-toggle input { position: absolute; opacity: 0; width: 0; height: 0; }
.clubhouse-toggle__track { position: relative; width: 40px; height: 22px; border-radius: 999px; background: var(--color-line); flex: none; transition: background .2s ease; }
.clubhouse-toggle__thumb { position: absolute; top: 3px; left: 3px; width: 16px; height: 16px; border-radius: 50%; background: var(--color-bg); transition: transform .2s ease; }
.clubhouse-toggle input:checked + .clubhouse-toggle__track { background: var(--color-ink); }
.clubhouse-toggle input:checked + .clubhouse-toggle__track .clubhouse-toggle__thumb { transform: translateX(18px); }
.clubhouse-toggle input:focus-visible + .clubhouse-toggle__track { outline: 2px solid var(--color-accent-deep); outline-offset: 2px; }
.clubhouse-toggle__label { font-size: 13px; color: var(--color-ink); }

/* Demo card */
.clubhouse-demo-card { border: 1px solid var(--color-line); border-radius: var(--radius-md); padding: 18px; background: var(--color-bg); }

/* Sticky save bar */
.clubhouse-bar { position: sticky; bottom: 0; display: flex; justify-content: space-between; align-items: center; gap: 16px; padding: 16px var(--pad); margin-top: 8px; background: var(--color-paper); border-top: 1px solid var(--color-line); }
.clubhouse-bar__hint { margin: 0; font-size: 13px; color: var(--color-ink-soft); }

@media (max-width: 782px) {
	.clubhouse-head { flex-direction: column; align-items: flex-start; }
	.clubhouse-head__progress { text-align: left; }
	.clubhouse-fields { grid-template-columns: 1fr; }
}
```

- [ ] **Step 4: Run the hygiene test to verify it passes**

Run: `vendor/bin/phpunit --filter AdminSetupStylesheetTest`
Expected: PASS.

- [ ] **Step 5: Rewrite `assets/js/admin-setup.js`**

Replace the file with the enhancement script (tabs, sub-tabs, live re-skin, dual media pickers, accent swatch, live progress):

```js
/* Clubhouse Setup — progressive enhancement: tabs, live re-skin, media pickers. */
( function () {
	'use strict';
	var root = document.querySelector( '.clubhouse-setup' );
	if ( ! root ) { return; }
	root.classList.add( 'clubhouse-setup--js' );

	function activate( buttons, panels, key, attr ) {
		buttons.forEach( function ( b ) { b.classList.toggle( 'is-active', b.getAttribute( attr ) === key ); } );
		panels.forEach( function ( p ) { p.classList.toggle( 'is-active', p.getAttribute( 'data-' + attr.replace( 'data-', '' ).replace( 'tab', 'panel' ) ) === key ); } );
	}

	// Top tabs.
	var tabs = [].slice.call( root.querySelectorAll( '.clubhouse-tab' ) );
	var panels = [].slice.call( root.querySelectorAll( '.clubhouse-panel' ) );
	tabs.forEach( function ( tab ) {
		tab.addEventListener( 'click', function () {
			var key = tab.getAttribute( 'data-tab' );
			tabs.forEach( function ( t ) { t.classList.toggle( 'is-active', t === tab ); } );
			panels.forEach( function ( p ) { p.classList.toggle( 'is-active', p.getAttribute( 'data-panel' ) === key ); } );
		} );
	} );

	// Visibility sub-tabs.
	var vtabs = [].slice.call( root.querySelectorAll( '.clubhouse-vistab' ) );
	var vpanels = [].slice.call( root.querySelectorAll( '.clubhouse-vispanel' ) );
	vtabs.forEach( function ( tab ) {
		tab.addEventListener( 'click', function () {
			var key = tab.getAttribute( 'data-vistab' );
			vtabs.forEach( function ( t ) { t.classList.toggle( 'is-active', t === tab ); } );
			vpanels.forEach( function ( p ) { p.classList.toggle( 'is-active', p.getAttribute( 'data-vispanel' ) === key ); } );
		} );
	} );

	// Live re-skin on look selection.
	var tokenEl = document.getElementById( 'clubhouse-look-tokens' );
	var tokens = {};
	if ( tokenEl ) { try { tokens = JSON.parse( tokenEl.textContent || '{}' ); } catch ( e ) { tokens = {}; } }
	function applyLook( slug ) {
		var map = tokens[ slug ];
		if ( ! map ) { return; }
		Object.keys( map ).forEach( function ( name ) { root.style.setProperty( name, map[ name ] ); } );
	}
	root.querySelectorAll( 'input[name="clubhouse_look"]' ).forEach( function ( radio ) {
		radio.addEventListener( 'change', function () { if ( radio.checked ) { applyLook( radio.value ); } } );
	} );

	// Accent swatch mirrors the hex field.
	var accent = document.getElementById( 'clubhouse_accent' );
	var swatch = document.getElementById( 'clubhouse-accent-swatch' );
	if ( accent && swatch ) {
		accent.addEventListener( 'input', function () { swatch.style.background = accent.value; } );
	}

	// Media pickers (logo + favicon) via wp.media.
	root.querySelectorAll( '.clubhouse-media' ).forEach( function ( box ) {
		var field = box.querySelector( 'input[type="hidden"]' );
		var pick = box.querySelector( '[data-media-pick]' );
		var clear = box.querySelector( '[data-media-clear]' );
		var preview = box.querySelector( '.clubhouse-media__preview' );
		if ( ! field || ! pick || ! window.wp || ! window.wp.media ) { return; }
		var frame;
		pick.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			if ( ! frame ) {
				frame = window.wp.media( { title: 'Select an image', button: { text: 'Use this image' }, multiple: false } );
				frame.on( 'select', function () {
					var att = frame.state().get( 'selection' ).first().toJSON();
					field.value = String( att.id );
					if ( preview ) { preview.innerHTML = '<img class="clubhouse-media__img" src="' + att.url + '" alt="">'; }
				} );
			}
			frame.open();
		} );
		if ( clear ) {
			clear.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				field.value = '';
				if ( preview ) { preview.innerHTML = '<span class="clubhouse-media__empty" aria-hidden="true"></span>'; }
			} );
		}
	} );
}() );
```

- [ ] **Step 6: Commit**

```bash
git add assets/css/admin-setup.css assets/js/admin-setup.js tests/php/AdminSetupStylesheetTest.php
git commit -m "feat: token-based admin skin + tabs/live-reskin/dual-picker JS"
```

---

### Task 8: Register the stylesheet test, version bump, changelog, verify

**Files:**
- Modify: `tests/php/bootstrap.php` (only if it enumerates test-covered classes — usually not needed; PHPUnit auto-discovers `tests/php/*Test.php`. Skip if discovery is automatic.)
- Modify: `blueworx-labs-clubhouse.php` (header version + `BLUEWORX_LABS_CLUBHOUSE_VERSION`)
- Modify: `package.json` (version)
- Modify: `CHANGELOG.md`

- [ ] **Step 1: Confirm test discovery**

Run: `composer test`
Expected: PASS, and the output lists `AdminSetupStylesheetTest`. If it is not picked up, check `phpunit.xml.dist` `<testsuite>` `<directory>` includes `tests/php` (it does by convention) — no code change needed.

- [ ] **Step 2: Bump the version**

In `blueworx-labs-clubhouse.php`, change the header `Version:` and the `BLUEWORX_LABS_CLUBHOUSE_VERSION` const from `0.21.0` to `0.22.0`. In `package.json`, change `"version": "0.21.0"` to `"0.22.0"`.

- [ ] **Step 3: Add the changelog entry**

At the top of `CHANGELOG.md`, add:

```markdown
## 0.22.0

- Redesigned the Clubhouse Setup screen: a bespoke, tabbed layout (Base Look & Branding · Visibility · Demo Mode) that inherits the selected Base Look's fonts, colours, radii and accent, and re-skins live as you pick a look.
- Added Favicon and LinkedIn as brand inputs. The favicon renders in the browser tab; LinkedIn joins Facebook and Instagram in the site's social block and footer.
- Setup progress now counts the main setup sections (base look, accent, club name, logo & favicon, social) — nothing is compulsory and Save is always available so you can return and finish later.
```

- [ ] **Step 4: Run the full suite + lint**

Run: `composer test && composer lint`
Expected: PASS / no lint errors. (If lint flags anything, present findings to the user per house rules — do not auto-fix in a loop.)

- [ ] **Step 5: Preview smoke (frontend LinkedIn)**

Run: `php -S localhost:8124` from the plugin root; open `http://localhost:8124/preview/?page=home` and confirm the "Follow us" block shows Facebook, Instagram **and** LinkedIn. (The admin page cannot run in preview — its re-skin/tabs/pickers are covered by the owed manual WP smoke.)

- [ ] **Step 6: Commit**

```bash
git add blueworx-labs-clubhouse.php package.json CHANGELOG.md
git commit -m "chore: bump to 0.22.0 (setup screen redesign) + changelog"
```

---

## Self-Review

**Spec coverage:**
- Look inheritance / live re-skin → Task 5 (per-look tokens + faces) + Task 6 (scoped `:root`, JSON island, previews) + Task 7 (token CSS + swap JS). ✓
- Favicon option → Task 1 (field), Task 5 (sanitise + preview), Task 6 (picker), Task 4 (frontend `<link>`). ✓
- LinkedIn social → Task 1 (field), Task 5 (sanitise), Task 6 (field), Task 3 (frontend social + footer). ✓
- Progress by section, nothing compulsory, resumable → Task 2 (5 groups) + Task 6 (Save never disabled, nudge copy). ✓
- Tabbed layout / no-JS fallback → Task 6 (server-rendered panels) + Task 7 (`--js`-gated hiding). ✓
- Version + changelog + delivery → Task 8. ✓

**Placeholder scan:** No "TBD"/"handle edge cases"/"similar to Task N" — all code shown inline. ✓

**Type consistency:** Progress shape `{items:{look,accent,club_name,logo_favicon,social},completed,total}` used identically in Tasks 2 and 6. `social()` data contract `{…,linkedin_url}` used in Tasks 3 and (via Page_Renderer) 3. Model keys `look_tokens`/`font_face_css`/`active_slug`/`branding.favicon`/`branding.linkedin` produced in Task 5, consumed in Task 6. `favicon_link_html` defined + used in Task 4. ✓

**Cross-cutting note for the executor:** Tasks 5 and 6 both touch `Setup_Controller`; do them in order. After Task 6, run `composer test` (not just the filtered class) because the controller model change can ripple into `SetupControllerTest`/`SetupScreenTest`.
