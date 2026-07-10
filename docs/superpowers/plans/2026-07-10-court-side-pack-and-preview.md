# Court Side Pack & Live Preview Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn the invisible theming framework into a live, engine-driven Court Side page on localhost — the Court Side Base Look pack + skin-agnostic section renderers + a page renderer + a `php -S` preview with a working accent switcher.

**Architecture:** Sections are pure-PHP renderers that return semantic, skin-agnostic HTML (no look-specific classes/colours). A page renderer assembles the active look's `<head>` (Google-CDN fonts, the look stylesheet, and the `:root{…}` variables from `Theme_Css`) around a body of visible sections. The Court Side stylesheet consumes the CSS custom properties, so the same markup re-themes by swapping the accent — exactly the engine path `registry → active look → branding → derive → compose → to_css`. The preview is a thin bootstrap that calls the renderer; WordPress `template_include` will later wrap the *same* renderer. Everything except the CSS asset is pure PHP, unit-tested with no WordPress runtime.

**Tech Stack:** PHP 8.2+ (`declare(strict_types=1)`), PHPUnit 11, WordPress-plugin conventions (`Blueworx_Clubhouse_*`, `class-*.php`/`interface-*.php`, ABSPATH guard, tabs, `( $spaced )` parens). Fonts via Google Fonts CDN. No new runtime dependency.

## Global Constraints

- PHP floor `>=8.2`; every new `.php` starts with `declare(strict_types=1);` then `if ( ! defined( 'ABSPATH' ) ) { exit; }`.
- Class prefix `Blueworx_Clubhouse_`. New dirs: `includes/looks/`, `includes/render/`, plus `assets/looks/` (CSS) and `preview/` (harness). Runtime classes loaded by explicit `require_once` in `includes/bootstrap.php`, in dependency order.
- **Skin-agnostic rule:** section renderers and the page body emit only semantic `ch-*` classes and content — never a colour literal, font name, radius, or look slug. All appearance comes from the look's tokens + stylesheet. (This is what makes A/B re-skins free.)
- Consume the existing engine only through its public API: `Blueworx_Clubhouse_Base_Look`, `Blueworx_Clubhouse_Base_Look_Registry`, `Blueworx_Clubhouse_Branding`, `Blueworx_Clubhouse_Theme_Css::compose()/to_css()`, `Blueworx_Clubhouse_Color_Engine::derive()`, `Blueworx_Clubhouse_Visibility`. No storage access except through `Blueworx_Clubhouse_Storage`.
- Court Side fixed shell tokens (verbatim): `--color-bg:#faf8f3`, `--color-paper:#ffffff`, `--color-ink:#1c1b18`, `--color-ink-soft:#6a675f`, `--color-line:#e9e4d8`, `--radius-xl:32px`, `--radius-lg:24px`, `--radius-md:16px`, `--font-display:'Syne', ui-sans-serif, sans-serif`, `--font-body:'Inter', ui-sans-serif, sans-serif`. (`--color-accent*` come from the engine, never the look.)
- Court Side fonts: Syne (600,700,800) + Inter (400,500,600), `display=swap`.
- Copy/brand: club name defaults to `ClubHouse`. Default accent `#c6f24e`.
- Tests in `tests/php/*Test.php`, extend `TestCase`, no namespace; use `#[DataProvider]` attributes (not `@dataProvider`). Run with `vendor/bin/phpunit`.
- All markup must escape dynamic text with `htmlspecialchars(...)` (the render path owns output escaping, per the spec).

---

### Task 1: Court Side Base Look pack

**Files:**
- Create: `includes/looks/class-court-side.php`
- Modify: `includes/bootstrap.php` (add `// Looks` loader block)
- Test: `tests/php/CourtSideTest.php`

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Base_Look` (interface), `Blueworx_Clubhouse_Base_Look_Registry`, `Blueworx_Clubhouse_Branding`, `Blueworx_Clubhouse_Theme_Css`, `Blueworx_Clubhouse_Fake_Storage`.
- Produces: `final class Blueworx_Clubhouse_Court_Side implements Blueworx_Clubhouse_Base_Look` with `slug()='court-side'`, `name()='Court Side'`, `description()`, `tokens()` (the fixed shell above), `fonts()` (Syne+Inter), `stylesheet()='assets/looks/court-side.css'`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/php/CourtSideTest.php

use PHPUnit\Framework\TestCase;

final class CourtSideTest extends TestCase {

	public function test_identity_and_stylesheet(): void {
		$look = new Blueworx_Clubhouse_Court_Side();
		$this->assertSame( 'court-side', $look->slug() );
		$this->assertSame( 'Court Side', $look->name() );
		$this->assertSame( 'assets/looks/court-side.css', $look->stylesheet() );
	}

	public function test_tokens_carry_the_fixed_shell(): void {
		$t = ( new Blueworx_Clubhouse_Court_Side() )->tokens();
		$this->assertSame( '#faf8f3', $t['--color-bg'] );
		$this->assertSame( '#1c1b18', $t['--color-ink'] );
		$this->assertStringContainsString( 'Syne', $t['--font-display'] );
		$this->assertArrayNotHasKey( '--color-accent', $t ); // accent is engine-derived, never in the look
	}

	public function test_fonts_are_syne_and_inter(): void {
		$families = array_column( ( new Blueworx_Clubhouse_Court_Side() )->fonts(), 'family' );
		$this->assertSame( array( 'Syne', 'Inter' ), $families );
	}

	public function test_registers_and_composes_with_derived_accent(): void {
		$storage  = new Blueworx_Clubhouse_Fake_Storage();
		$registry = new Blueworx_Clubhouse_Base_Look_Registry( $storage );
		$registry->register( new Blueworx_Clubhouse_Court_Side() );
		$branding = new Blueworx_Clubhouse_Branding( $storage );
		$branding->set_accent( '#ff5b23' );

		$vars = Blueworx_Clubhouse_Theme_Css::compose( $registry->active(), $branding );
		$this->assertSame( '#faf8f3', $vars['--color-bg'] );
		$this->assertSame( '#ff5b23', $vars['--color-accent'] );
		$this->assertArrayHasKey( '--color-accent-ink', $vars );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter CourtSideTest`
Expected: FAIL — `Error: Class "Blueworx_Clubhouse_Court_Side" not found`.

- [ ] **Step 3: Write the pack**

```php
<?php
// includes/looks/class-court-side.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Court Side — the reference Base Look. Bright, playful-premium: near-white warm
 * canvas, soft warm ink (never pure black), rounded shapes, Syne + Inter. Supplies
 * presentation only; the accent tokens are derived by the engine, not defined here.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Court_Side implements Blueworx_Clubhouse_Base_Look {

	public function slug(): string {
		return 'court-side';
	}

	public function name(): string {
		return 'Court Side';
	}

	public function description(): string {
		return 'Bright, playful-premium — near-white canvas, bold accent blocks, Syne display.';
	}

	/** @return array<string,string> */
	public function tokens(): array {
		return array(
			'--color-bg'       => '#faf8f3',
			'--color-paper'    => '#ffffff',
			'--color-ink'      => '#1c1b18',
			'--color-ink-soft' => '#6a675f',
			'--color-line'     => '#e9e4d8',
			'--radius-xl'      => '32px',
			'--radius-lg'      => '24px',
			'--radius-md'      => '16px',
			'--font-display'   => "'Syne', ui-sans-serif, sans-serif",
			'--font-body'      => "'Inter', ui-sans-serif, sans-serif",
		);
	}

	/** @return array<int,array{family:string,weights:array<int,int>,display:string}> */
	public function fonts(): array {
		return array(
			array( 'family' => 'Syne', 'weights' => array( 600, 700, 800 ), 'display' => 'swap' ),
			array( 'family' => 'Inter', 'weights' => array( 400, 500, 600 ), 'display' => 'swap' ),
		);
	}

	public function stylesheet(): string {
		return 'assets/looks/court-side.css';
	}
}
```

- [ ] **Step 4: Register in the runtime loader**

In `includes/bootstrap.php`, after the `// Theme` block, add:

```php

// Looks
require_once __DIR__ . '/looks/class-court-side.php';
```

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter CourtSideTest`
Expected: PASS (4 tests).

- [ ] **Step 6: Commit**

```bash
git add includes/looks/class-court-side.php includes/bootstrap.php tests/php/CourtSideTest.php
git commit -m "feat: add Court Side base look pack"
```

---

### Task 2: Skin-agnostic section renderers (shell)

**Files:**
- Create: `includes/render/class-sections.php`
- Modify: `includes/bootstrap.php` (add `// Render` loader block)
- Test: `tests/php/SectionsTest.php`

**Interfaces:**
- Consumes: nothing (pure functions over arrays).
- Produces: `final class Blueworx_Clubhouse_Sections` with static methods returning HTML strings:
  - `header( array $data ): string` — `$data` keys: `club_name` (string), `nav` (list<string>), `cta` (string).
  - `hero( array $data ): string` — keys: `eyebrow`, `title_lead` (string, plain), `title_highlight` (string, wrapped in the accent chip), `lede`, `cta_primary`, `cta_secondary`.
  - `stat_strip( array $stats ): string` — `$stats` is `list<array{value:string,label:string}>`.
  - `footer( array $data ): string` — keys: `club_name`, `tagline`.
  - All output only `ch-*` semantic classes; all interpolated text passes through `htmlspecialchars()`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/php/SectionsTest.php

use PHPUnit\Framework\TestCase;

final class SectionsTest extends TestCase {

	public function test_header_renders_brand_nav_and_cta(): void {
		$html = Blueworx_Clubhouse_Sections::header( array(
			'club_name' => 'ClubHouse',
			'nav'       => array( 'Membership', 'Sports' ),
			'cta'       => 'Join the Club',
		) );
		$this->assertStringContainsString( 'class="ch-nav"', $html );
		$this->assertStringContainsString( 'ClubHouse', $html );
		$this->assertStringContainsString( 'Membership', $html );
		$this->assertStringContainsString( 'Join the Club', $html );
	}

	public function test_hero_highlights_the_accent_span(): void {
		$html = Blueworx_Clubhouse_Sections::hero( array(
			'eyebrow'         => 'Est. 1974',
			'title_lead'      => 'Every sport. Every age. ',
			'title_highlight' => 'One community.',
			'lede'            => 'Nine sports, twenty-four teams.',
			'cta_primary'     => 'Explore membership',
			'cta_secondary'   => 'Take a tour',
		) );
		$this->assertStringContainsString( 'class="ch-hero"', $html );
		$this->assertStringContainsString( 'class="ch-hero__hl"', $html );
		$this->assertStringContainsString( 'One community.', $html );
		$this->assertStringContainsString( 'Explore membership', $html );
	}

	public function test_stat_strip_renders_each_stat(): void {
		$html = Blueworx_Clubhouse_Sections::stat_strip( array(
			array( 'value' => '900+', 'label' => 'Members' ),
			array( 'value' => '9', 'label' => 'Sports' ),
		) );
		$this->assertStringContainsString( 'class="ch-stats"', $html );
		$this->assertSame( 2, substr_count( $html, 'ch-stats__item' ) );
		$this->assertStringContainsString( '900+', $html );
	}

	public function test_output_is_escaped(): void {
		$html = Blueworx_Clubhouse_Sections::footer( array(
			'club_name' => 'A & B <script>',
			'tagline'   => 'x',
		) );
		$this->assertStringContainsString( 'A &amp; B &lt;script&gt;', $html );
		$this->assertStringNotContainsString( '<script>', $html );
	}

	public function test_no_colour_literals_leak_into_markup(): void {
		$html = Blueworx_Clubhouse_Sections::hero( array(
			'eyebrow' => 'e', 'title_lead' => 't ', 'title_highlight' => 'h',
			'lede' => 'l', 'cta_primary' => 'a', 'cta_secondary' => 'b',
		) );
		// Skin-agnostic: sections must not hard-code colours or styles.
		$this->assertStringNotContainsString( '#', $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter SectionsTest`
Expected: FAIL — `Error: Class "Blueworx_Clubhouse_Sections" not found`.

- [ ] **Step 3: Write the section renderers**

```php
<?php
// includes/render/class-sections.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Skin-agnostic section renderers. Each returns semantic HTML using only ch-*
 * classes — no colours, fonts, radii or look slugs — so any Base Look styles the
 * same markup. All interpolated text is escaped here (the render path owns output
 * escaping). WordPress and the preview both render these same strings.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Sections {

	private static function e( string $s ): string {
		return htmlspecialchars( $s, ENT_QUOTES, 'UTF-8' );
	}

	/** @param array{club_name:string,nav:array<int,string>,cta:string} $data */
	public static function header( array $data ): string {
		$links = '';
		foreach ( $data['nav'] as $label ) {
			$links .= '<a class="ch-nav__link" href="#">' . self::e( $label ) . '</a>';
		}
		return '<header class="ch-nav"><div class="ch-wrap ch-nav__in">'
			. '<a class="ch-brand" href="#"><span class="ch-brand__mark">C</span>' . self::e( $data['club_name'] ) . '</a>'
			. '<nav class="ch-nav__links">' . $links . '</nav>'
			. '<a class="ch-btn ch-btn--ink" href="#">' . self::e( $data['cta'] ) . '</a>'
			. '</div></header>';
	}

	/** @param array{eyebrow:string,title_lead:string,title_highlight:string,lede:string,cta_primary:string,cta_secondary:string} $data */
	public static function hero( array $data ): string {
		return '<section class="ch-hero"><div class="ch-wrap">'
			. '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
			. '<h1 class="ch-hero__title">' . self::e( $data['title_lead'] )
			. '<span class="ch-hero__hl">' . self::e( $data['title_highlight'] ) . '</span></h1>'
			. '<div class="ch-hero__sub">'
			. '<p class="ch-hero__lede">' . self::e( $data['lede'] ) . '</p>'
			. '<div class="ch-hero__cta">'
			. '<a class="ch-btn ch-btn--accent" href="#">' . self::e( $data['cta_primary'] ) . '</a>'
			. '<a class="ch-btn ch-btn--ghost" href="#">' . self::e( $data['cta_secondary'] ) . '</a>'
			. '</div></div></div></section>';
	}

	/** @param array<int,array{value:string,label:string}> $stats */
	public static function stat_strip( array $stats ): string {
		$items = '';
		foreach ( $stats as $stat ) {
			$items .= '<div class="ch-stats__item"><b class="ch-stats__value">' . self::e( $stat['value'] )
				. '</b><span class="ch-stats__label">' . self::e( $stat['label'] ) . '</span></div>';
		}
		return '<section class="ch-stats"><div class="ch-wrap ch-stats__in">' . $items . '</div></section>';
	}

	/** @param array{club_name:string,tagline:string} $data */
	public static function footer( array $data ): string {
		return '<footer class="ch-footer"><div class="ch-wrap">'
			. '<a class="ch-brand" href="#"><span class="ch-brand__mark">C</span>' . self::e( $data['club_name'] ) . '</a>'
			. '<p class="ch-footer__tagline">' . self::e( $data['tagline'] ) . '</p>'
			. '</div></footer>';
	}
}
```

- [ ] **Step 4: Register in the runtime loader**

In `includes/bootstrap.php`, after the `// Looks` block, add:

```php

// Render
require_once __DIR__ . '/render/class-sections.php';
```

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter SectionsTest`
Expected: PASS (5 tests).

- [ ] **Step 6: Commit**

```bash
git add includes/render/class-sections.php includes/bootstrap.php tests/php/SectionsTest.php
git commit -m "feat: add skin-agnostic shell section renderers"
```

---

### Task 3: Page renderer (head assembly + Home body)

**Files:**
- Create: `includes/render/class-page-renderer.php`
- Modify: `includes/bootstrap.php` (add loader line under `// Render`)
- Test: `tests/php/PageRendererTest.php`

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Base_Look` (`tokens()/fonts()/stylesheet()`), `Blueworx_Clubhouse_Branding`, `Blueworx_Clubhouse_Theme_Css`, `Blueworx_Clubhouse_Visibility`, `Blueworx_Clubhouse_Sections`, `Blueworx_Clubhouse_Fake_Storage`, `Blueworx_Clubhouse_Court_Side`.
- Produces: `final class Blueworx_Clubhouse_Page_Renderer`:
  - `public static function google_fonts_url( Blueworx_Clubhouse_Base_Look $look ): string` — builds the CDN `family=` URL from `fonts()`.
  - `public static function document( Blueworx_Clubhouse_Base_Look $look, Blueworx_Clubhouse_Branding $branding, string $body, string $plugin_url = '' ): string` — full `<!doctype html>` doc: `<head>` with the font `<link>`, the look stylesheet `<link>` (`$plugin_url . $look->stylesheet()`), and `<style>` = `Theme_Css::to_css( Theme_Css::compose( $look, $branding ) )`; `<body>` = `$body`.
  - `public static function home( Blueworx_Clubhouse_Branding $branding, Blueworx_Clubhouse_Visibility $visibility ): string` — assembles header + hero + stat_strip + footer (default demo content), omitting any section hidden for page `home` in `$visibility`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/php/PageRendererTest.php

use PHPUnit\Framework\TestCase;

final class PageRendererTest extends TestCase {

	private function branding(): Blueworx_Clubhouse_Branding {
		return new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() );
	}

	public function test_google_fonts_url_lists_both_families(): void {
		$url = Blueworx_Clubhouse_Page_Renderer::google_fonts_url( new Blueworx_Clubhouse_Court_Side() );
		$this->assertStringStartsWith( 'https://fonts.googleapis.com/css2?', $url );
		$this->assertStringContainsString( 'family=Syne:wght@600;700;800', $url );
		$this->assertStringContainsString( 'family=Inter:wght@400;500;600', $url );
		$this->assertStringContainsString( 'display=swap', $url );
	}

	public function test_document_head_carries_tokens_fonts_and_stylesheet(): void {
		$b = $this->branding();
		$b->set_accent( '#3b5bdb' );
		$doc = Blueworx_Clubhouse_Page_Renderer::document(
			new Blueworx_Clubhouse_Court_Side(), $b, '<main>hi</main>', '/wp-content/plugins/clubhouse/'
		);
		$this->assertStringContainsString( '<!doctype html>', $doc );
		$this->assertStringContainsString( ':root{', $doc );
		$this->assertStringContainsString( '--color-bg:#faf8f3;', $doc );
		$this->assertStringContainsString( '--color-accent:#3b5bdb;', $doc );
		$this->assertStringContainsString( 'fonts.googleapis.com', $doc );
		$this->assertStringContainsString( '/wp-content/plugins/clubhouse/assets/looks/court-side.css', $doc );
		$this->assertStringContainsString( '<main>hi</main>', $doc );
	}

	public function test_home_includes_the_shell_sections(): void {
		$vis  = new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() );
		$body = Blueworx_Clubhouse_Page_Renderer::home( $this->branding(), $vis );
		$this->assertStringContainsString( 'class="ch-nav"', $body );
		$this->assertStringContainsString( 'class="ch-hero"', $body );
		$this->assertStringContainsString( 'class="ch-stats"', $body );
		$this->assertStringContainsString( 'class="ch-footer"', $body );
	}

	public function test_home_respects_visibility(): void {
		$storage = new Blueworx_Clubhouse_Fake_Storage();
		$vis     = new Blueworx_Clubhouse_Visibility( $storage );
		$vis->set_section_visible( 'home', 'stats', false );
		$body = Blueworx_Clubhouse_Page_Renderer::home( $this->branding(), $vis );
		$this->assertStringNotContainsString( 'class="ch-stats"', $body );
		$this->assertStringContainsString( 'class="ch-hero"', $body ); // others still present
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter PageRendererTest`
Expected: FAIL — `Error: Class "Blueworx_Clubhouse_Page_Renderer" not found`.

- [ ] **Step 3: Write the page renderer**

```php
<?php
// includes/render/class-page-renderer.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Assembles a full HTML document for a Base Look + branding: <head> carries the
 * Google-fonts link, the look stylesheet, and the derived :root variables; <body>
 * is a string of rendered sections. home() composes the demo Home shell, honouring
 * per-section visibility. The same output is what WordPress template_include will
 * later echo — the preview is just an earlier caller.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Page_Renderer {

	public static function google_fonts_url( Blueworx_Clubhouse_Base_Look $look ): string {
		$families = array();
		foreach ( $look->fonts() as $font ) {
			$families[] = 'family=' . rawurlencode( $font['family'] )
				. ':wght@' . implode( ';', $font['weights'] );
		}
		return 'https://fonts.googleapis.com/css2?' . implode( '&', $families ) . '&display=swap';
	}

	public static function document(
		Blueworx_Clubhouse_Base_Look $look,
		Blueworx_Clubhouse_Branding $branding,
		string $body,
		string $plugin_url = ''
	): string {
		$vars = Blueworx_Clubhouse_Theme_Css::compose( $look, $branding );
		$css  = Blueworx_Clubhouse_Theme_Css::to_css( $vars );
		$font = htmlspecialchars( self::google_fonts_url( $look ), ENT_QUOTES, 'UTF-8' );
		$sheet = htmlspecialchars( $plugin_url . $look->stylesheet(), ENT_QUOTES, 'UTF-8' );

		return '<!doctype html><html lang="en"><head>'
			. '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
			. '<title>' . htmlspecialchars( $branding->get_club_name(), ENT_QUOTES, 'UTF-8' ) . '</title>'
			. '<link rel="preconnect" href="https://fonts.googleapis.com">'
			. '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
			. '<link rel="stylesheet" href="' . $font . '">'
			. '<link rel="stylesheet" href="' . $sheet . '">'
			. '<style>' . $css . '</style>'
			. '</head><body>' . $body . '</body></html>';
	}

	public static function home(
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility
	): string {
		$club = $branding->get_club_name();
		$out  = '';

		if ( $visibility->is_section_visible( 'home', 'header' ) ) {
			$out .= Blueworx_Clubhouse_Sections::header( array(
				'club_name' => $club,
				'nav'       => array( 'Membership', 'Sports', 'Teams', 'Events', 'About' ),
				'cta'       => 'Join the Club',
			) );
		}
		if ( $visibility->is_section_visible( 'home', 'hero' ) ) {
			$out .= Blueworx_Clubhouse_Sections::hero( array(
				'eyebrow'         => 'Est. 1974 · Marlow, UK',
				'title_lead'      => 'Every sport. Every age. ',
				'title_highlight' => 'One community.',
				'lede'            => "Nine sports, twenty-four teams, and a clubhouse that's always open.",
				'cta_primary'     => 'Explore membership',
				'cta_secondary'   => 'Take a tour',
			) );
		}
		if ( $visibility->is_section_visible( 'home', 'stats' ) ) {
			$out .= Blueworx_Clubhouse_Sections::stat_strip( array(
				array( 'value' => '900+', 'label' => 'Members' ),
				array( 'value' => '9', 'label' => 'Sports' ),
				array( 'value' => '24', 'label' => 'Teams' ),
				array( 'value' => '1974', 'label' => 'Founded' ),
			) );
		}
		if ( $visibility->is_section_visible( 'home', 'footer' ) ) {
			$out .= Blueworx_Clubhouse_Sections::footer( array(
				'club_name' => $club,
				'tagline'   => 'A home ground for every team, and everyone who follows them.',
			) );
		}
		return $out;
	}
}
```

- [ ] **Step 4: Register in the runtime loader**

In `includes/bootstrap.php`, under `// Render`, after the sections line, add:

```php
require_once __DIR__ . '/render/class-page-renderer.php';
```

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter PageRendererTest`
Expected: PASS (4 tests).

- [ ] **Step 6: Commit**

```bash
git add includes/render/class-page-renderer.php includes/bootstrap.php tests/php/PageRendererTest.php
git commit -m "feat: add page renderer for head assembly and Home shell body"
```

---

### Task 4: Court Side stylesheet asset

**Files:**
- Create: `assets/looks/court-side.css`
- Test: `tests/php/CourtSideStylesheetTest.php`

**Interfaces:**
- Consumes: the semantic `ch-*` classes emitted by `Blueworx_Clubhouse_Sections` and the CSS custom properties emitted by `Theme_Css` (`--color-*`, `--font-*`, `--radius-*`).
- Produces: a static CSS file. It must reference the accent via `var(--color-accent*)` (never a literal), so re-theming works.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/php/CourtSideStylesheetTest.php

use PHPUnit\Framework\TestCase;

final class CourtSideStylesheetTest extends TestCase {

	private function css(): string {
		$path = dirname( __DIR__, 2 ) . '/assets/looks/court-side.css';
		$this->assertFileExists( $path );
		return (string) file_get_contents( $path );
	}

	public function test_styles_the_shell_sections(): void {
		$css = $this->css();
		foreach ( array( '.ch-nav', '.ch-hero', '.ch-stats', '.ch-footer', '.ch-btn' ) as $sel ) {
			$this->assertStringContainsString( $sel, $css );
		}
	}

	public function test_accent_is_referenced_via_custom_property_not_literals(): void {
		$css = $this->css();
		$this->assertStringContainsString( 'var(--color-accent)', $css );
		// The accent must not be baked in as a hex — that would break re-theming.
		$this->assertStringNotContainsString( '#c6f24e', $css );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter CourtSideStylesheetTest`
Expected: FAIL — `Failed asserting that file "…/assets/looks/court-side.css" exists`.

- [ ] **Step 3: Write the stylesheet**

Create `assets/looks/court-side.css` with exactly this content:

```css
/* Court Side — look stylesheet. Consumes engine custom properties; the accent is
   always var(--color-accent*) so any club re-themes by swapping one colour. */
*{box-sizing:border-box;margin:0;padding:0}
body{background:var(--color-bg);color:var(--color-ink);font-family:var(--font-body);font-size:17px;line-height:1.6;-webkit-font-smoothing:antialiased}
a{color:inherit;text-decoration:none}
.ch-wrap{max-width:1200px;margin-inline:auto;padding-inline:28px}

.ch-eyebrow{display:inline-flex;align-items:center;gap:8px;font-size:12px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--color-ink);background:var(--color-accent);padding:7px 14px;border-radius:999px}

.ch-btn{display:inline-flex;align-items:center;gap:.5em;font-family:var(--font-body);font-weight:600;font-size:15px;padding:15px 28px;border-radius:999px;transition:transform .14s ease}
.ch-btn:active{transform:scale(.96)}
.ch-btn--accent{background:var(--color-accent);color:var(--color-accent-ink)}
.ch-btn--ink{background:var(--color-ink);color:var(--color-bg)}
.ch-btn--ghost{border:1.5px solid var(--color-ink);color:var(--color-ink)}
.ch-btn--ghost:hover{background:var(--color-ink);color:var(--color-bg)}

.ch-nav{position:sticky;top:0;z-index:40;background:color-mix(in oklab,var(--color-bg) 85%,transparent);backdrop-filter:blur(10px)}
.ch-nav__in{display:flex;align-items:center;justify-content:space-between;height:80px}
.ch-brand{font-family:var(--font-display);font-weight:800;font-size:25px;letter-spacing:-.03em;display:flex;align-items:center;gap:10px}
.ch-brand__mark{width:36px;height:36px;border-radius:12px;background:var(--color-accent);color:var(--color-accent-ink);display:grid;place-items:center;font-size:18px;font-weight:800}
.ch-nav__links{display:flex;gap:28px;font-size:15px;font-weight:500;color:var(--color-ink-soft)}
.ch-nav__link:hover{color:var(--color-ink)}
@media(max-width:900px){.ch-nav__links{display:none}}

.ch-hero{padding:64px 0 32px}
.ch-hero__title{font-family:var(--font-display);font-weight:800;font-size:clamp(52px,10vw,132px);letter-spacing:-.03em;line-height:.95}
.ch-hero__hl{background:var(--color-accent);color:var(--color-accent-ink);padding:0 .12em;border-radius:14px;display:inline-block;transform:rotate(-1.5deg)}
.ch-hero__sub{display:flex;justify-content:space-between;align-items:end;gap:30px;margin-top:26px;flex-wrap:wrap}
.ch-hero__lede{max-width:40ch;font-size:19px;color:var(--color-ink-soft)}
.ch-hero__cta{display:flex;gap:12px;flex-wrap:wrap}

.ch-stats{padding:24px 0}
.ch-stats__in{display:flex;gap:14px;flex-wrap:wrap}
.ch-stats__item{flex:1 1 200px;background:var(--color-paper);border:1px solid var(--color-line);border-radius:var(--radius-lg);padding:26px 28px}
.ch-stats__item:nth-child(2){background:var(--color-ink);color:var(--color-bg)}
.ch-stats__value{font-family:var(--font-display);font-weight:800;font-size:44px;display:block;letter-spacing:-.03em}
.ch-stats__label{font-size:13px;font-weight:600;color:var(--color-ink-soft)}

.ch-footer{margin-top:40px;padding:64px 0 48px;border-top:1px solid var(--color-line)}
.ch-footer__tagline{color:var(--color-ink-soft);max-width:30ch;margin-top:12px;font-size:15px}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter CourtSideStylesheetTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add assets/looks/court-side.css tests/php/CourtSideStylesheetTest.php
git commit -m "feat: add Court Side look stylesheet"
```

---

### Task 5: Localhost preview harness

**Files:**
- Create: `preview/index.php`
- Create: `preview/README.md`
- Test: `tests/php/PreviewRenderTest.php`

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Base_Look_Registry`, `Blueworx_Clubhouse_Court_Side`, `Blueworx_Clubhouse_Branding`, `Blueworx_Clubhouse_Visibility`, `Blueworx_Clubhouse_Page_Renderer`, `Blueworx_Clubhouse_Color_Engine`, `Blueworx_Clubhouse_Options_Storage` is NOT used (no WP) — the preview uses an in-memory storage. Because `Blueworx_Clubhouse_Fake_Storage` lives under `tests/php/fakes/`, the preview defines its own tiny array storage implementing `Blueworx_Clubhouse_Storage`.
- Produces: `preview/index.php` — defines `ABSPATH`, requires the plugin bootstrap, wires Court Side + branding + visibility, renders the Home document via `Page_Renderer`, and injects a client-side accent switcher whose swatch set is **pre-derived server-side** through `Color_Engine::derive()` (so `-ink/-deep/-wash` update too, showcasing the real engine). Served with `php -S`. The document-building logic is extracted into a function `blueworx_clubhouse_preview_document(): string` so it is unit-testable without a web server.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/php/PreviewRenderTest.php

use PHPUnit\Framework\TestCase;

final class PreviewRenderTest extends TestCase {

	public function test_preview_builds_a_full_court_side_home_document(): void {
		require_once dirname( __DIR__, 2 ) . '/preview/index.php';
		$html = blueworx_clubhouse_preview_document();

		$this->assertStringContainsString( '<!doctype html>', $html );
		$this->assertStringContainsString( 'court-side.css', $html );
		$this->assertStringContainsString( 'class="ch-hero"', $html );
		$this->assertStringContainsString( ':root{', $html );
		// The accent switcher embeds pre-derived palettes (real engine output).
		$this->assertStringContainsString( 'data-ch-palettes', $html );
		$this->assertStringContainsString( '--color-accent-ink', $html );
	}
}
```

Note: `preview/index.php` must guard its "serve" behaviour so that `require`-ing it in the test does not emit output. Structure it as: define `ABSPATH` if needed, `require_once` bootstrap, define the functions, then `if ( PHP_SAPI !== 'cli' ) { echo blueworx_clubhouse_preview_document(); }` at the very end (PHPUnit runs under the `cli` SAPI, so the echo is skipped; `php -S` runs under a server SAPI, so it renders).

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter PreviewRenderTest`
Expected: FAIL — `preview/index.php` does not exist / function undefined.

- [ ] **Step 3: Write the preview harness**

Create `preview/index.php`:

```php
<?php
/**
 * Court Side live preview. Boots the plugin engine WITHOUT WordPress and renders
 * the Home shell so progress is viewable on localhost:
 *
 *   php -S localhost:8124            (from the plugin root; docroot = plugin root)
 *   open http://localhost:8124/preview/
 *
 * The accent switcher's swatches are derived server-side through the real colour
 * engine, so every token (-ink/-deep/-wash) updates on swap. WordPress will later
 * render the same Page_Renderer output; this harness is just an earlier caller.
 */
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

require_once dirname( __DIR__ ) . '/includes/bootstrap.php';

/** Minimal in-memory storage so the preview needs no WordPress/DB. */
final class Blueworx_Clubhouse_Preview_Storage implements Blueworx_Clubhouse_Storage {
	/** @var array<string,mixed> */
	private array $data = array();
	public function get( string $key, mixed $default = null ): mixed {
		return array_key_exists( $key, $this->data ) ? $this->data[ $key ] : $default;
	}
	public function set( string $key, mixed $value ): void {
		$this->data[ $key ] = $value;
	}
	public function delete( string $key ): void {
		unset( $this->data[ $key ] );
	}
}

/** @return array<int,array{name:string,c:string,ink:string,deep:string,wash:string}> */
function blueworx_clubhouse_preview_palettes(): array {
	$look    = new Blueworx_Clubhouse_Court_Side();
	$tokens  = $look->tokens();
	$accents = array(
		'Volt Lime'     => '#c6f24e',
		'Signal Orange' => '#ff5b23',
		'Court Teal'    => '#12c3b0',
		'Cobalt'        => '#3b5bdb',
		'Berry'         => '#c2337a',
	);
	$out = array();
	foreach ( $accents as $name => $hex ) {
		$d     = Blueworx_Clubhouse_Color_Engine::derive( $hex, $tokens['--color-bg'], $tokens['--color-ink'] );
		$out[] = array(
			'name' => $name,
			'c'    => $d['--color-accent'],
			'ink'  => $d['--color-accent-ink'],
			'deep' => $d['--color-accent-deep'],
			'wash' => $d['--color-accent-wash'],
		);
	}
	return $out;
}

function blueworx_clubhouse_preview_document(): string {
	$storage  = new Blueworx_Clubhouse_Preview_Storage();
	$registry = new Blueworx_Clubhouse_Base_Look_Registry( $storage );
	$registry->register( new Blueworx_Clubhouse_Court_Side() );
	$branding   = new Blueworx_Clubhouse_Branding( $storage );
	$visibility = new Blueworx_Clubhouse_Visibility( $storage );

	$body      = Blueworx_Clubhouse_Page_Renderer::home( $branding, $visibility );
	$palettes  = blueworx_clubhouse_preview_palettes();
	$switcher   = '<div class="ch-switcher" data-ch-palettes=\''
		. htmlspecialchars( json_encode( $palettes ), ENT_QUOTES, 'UTF-8' ) . '\'></div>'
		. '<script>(function(){'
		. 'var box=document.querySelector(".ch-switcher");'
		. 'var ps=JSON.parse(box.getAttribute("data-ch-palettes"));'
		. 'ps.forEach(function(p){var s=document.createElement("button");s.type="button";'
		. 's.style.cssText="width:30px;height:30px;border-radius:50%;border:2px solid #fff;box-shadow:0 0 0 1px #ddd;cursor:pointer;margin:4px";'
		. 's.style.background=p.c;s.title=p.name;'
		. 's.onclick=function(){var r=document.documentElement.style;'
		. 'r.setProperty("--color-accent",p.c);r.setProperty("--color-accent-ink",p.ink);'
		. 'r.setProperty("--color-accent-deep",p.deep);r.setProperty("--color-accent-wash",p.wash);};'
		. 'box.appendChild(s);});'
		. '})();</script>';
	$style     = '<style>.ch-switcher{position:fixed;right:16px;bottom:16px;z-index:90;background:#fff;border:1px solid #e9e4d8;border-radius:16px;padding:8px;display:flex;flex-wrap:wrap;max-width:150px}</style>';

	// Served with docroot = plugin root, so the look stylesheet resolves from '/'.
	return Blueworx_Clubhouse_Page_Renderer::document(
		$registry->active(),
		$branding,
		$body . $switcher . $style,
		'/'
	);
}

if ( PHP_SAPI !== 'cli' ) {
	echo blueworx_clubhouse_preview_document();
}
```

Note on the stylesheet path: serve with docroot = the plugin root (`php -S localhost:8124` from the plugin root, no `-t`), so `document()` is passed `'/'` as the plugin URL → the stylesheet href is `/assets/looks/court-side.css`, which the built-in server serves from the plugin root. The preview page itself is at `http://localhost:8124/preview/` (the built-in server runs `preview/index.php` for that directory request).

Create `preview/README.md`:

```markdown
# Court Side live preview

Boots the ClubHouse engine without WordPress and renders the Home shell.

```bash
# from the plugin root (docroot = plugin root)
php -S localhost:8124
# open http://localhost:8124/preview/
```

The bottom-right swatches re-theme the page via the real colour engine (every
derived token updates, not just the base accent). This same `Page_Renderer`
output is what WordPress `template_include` will echo later; this harness is the
early, DB-free way to watch progress and the basis for the CI preview URL.
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter PreviewRenderTest`
Expected: PASS (1 test).

- [ ] **Step 5: Smoke-test the server manually**

Run (from the plugin root): `php -S localhost:8124` then in another shell `curl -s http://localhost:8124/preview/ | head -c 200` and `curl -s -o /dev/null -w "%{http_code}" http://localhost:8124/assets/looks/court-side.css` (expect `200`).
Expected: HTML beginning `<!doctype html>`, and the stylesheet returns 200. Stop the server afterward.

- [ ] **Step 6: Commit**

```bash
git add preview/index.php preview/README.md tests/php/PreviewRenderTest.php
git commit -m "feat: add DB-free Court Side localhost preview with live accent switcher"
```

---

### Task 6: Version bump, changelog, full suite

**Files:**
- Modify: `blueworx-labs-clubhouse.php`
- Modify: `CHANGELOG.md`

- [ ] **Step 1: Run the full suite (no regressions)**

Run: `vendor/bin/phpunit`
Expected: PASS — phase-1 + framework + all new Court Side/render/preview tests green, output pristine (0 deprecations).

- [ ] **Step 2: Bump the version (minor — new feature)**

In `blueworx-labs-clubhouse.php`: set the `Version:` header and `BLUEWORX_LABS_CLUBHOUSE_VERSION` to `0.4.0`.

- [ ] **Step 3: Update the changelog**

In `CHANGELOG.md`, add a `0.4.0` entry matching the file's existing format: "Court Side base look pack (tokens, Syne+Inter, stylesheet); skin-agnostic section renderers (header/hero/stats/footer); page renderer (head + `:root` + Home body honouring visibility); DB-free localhost preview with live, engine-derived accent switcher."

- [ ] **Step 4: Commit**

```bash
git add blueworx-labs-clubhouse.php CHANGELOG.md
git commit -m "chore: bump version to 0.4.0 and update changelog"
```

---

## Self-Review

**1. Spec coverage (against `2026-07-10-base-look-theming-and-site-design.md`):**
- §3 Court Side pack (tokens/fonts/stylesheet) → Task 1 + Task 4. ✅
- §2 skin-agnostic rendering (re-skin safety) → Task 2 sections emit only `ch-*` classes; a test asserts no colour literals/`style=` leak. ✅
- §7 inline `:root`, fast, cache-friendly → Task 3 `document()` inlines `to_css()`. (Caching remains the later WP-wrapper plan; pure here.) ✅
- §8 watch progress on localhost → Task 5 preview; also the seed of the CI preview URL. ✅
- §5 Home section inventory → this plan does the shell subset (header/hero/stats/footer); the remaining Home sections (sports grid, membership, events, blog, sponsors) are the next plan. Explicitly scoped. ✅
- §4 colour model → reused via `Theme_Css`/`Color_Engine`; the preview switcher proves per-client derivation end-to-end. ✅
- **Out of this plan (later):** remaining Home sections + other pages; the six CPTs; admin setup flow (incl. accent-contrast validation); WordPress `template_include`/enqueue + caching; font self-hosting; A & B packs.

**2. Placeholder scan:** No TBD/TODO/"handle edge cases"/"similar to". Every code step is complete; the CSS asset is given in full; tests carry real assertions. ✅

**3. Type consistency:** `Court_Side::tokens()` returns the keys `document()`/`derive()` consume (`--color-bg`, `--color-ink`). `Page_Renderer::home(Branding, Visibility)` matches its call in the preview. `Page_Renderer::document(look, branding, body, plugin_url)` argument order is identical in Task 3 and Task 5. `fonts()` shape `{family, weights, display}` from Task 1 is what `google_fonts_url()` iterates. `Blueworx_Clubhouse_Storage` (get/set/delete) is implemented identically by `Preview_Storage`. ✅

**4. Loader ordering:** `// Theme` (interface, registry, color-engine, branding, theme-css) → `// Looks` (court-side, needs the interface) → `// Render` (sections need nothing engine-y; page-renderer needs look/branding/theme-css/visibility/sections — all loaded earlier). ✅

## Downstream plans (not in this plan)

- **Remaining Home sections & other pages** — sports grid, membership, events, blog, sponsors; then About/Sports/Teams/Membership/Events/Calendar/Contact.
- **Collections** — the six CPTs + admin.
- **Admin setup flow** — Base Look picker → accent/branding (with accent-contrast validation) → content, bespoke UI.
- **WordPress render/enqueue + caching** — `template_include` echoing `Page_Renderer`, `wp_head` inline (cached) `:root`, per-look font + stylesheet enqueue, `to_css()` value escaping.
- **Font self-hosting**; then **A (Members' House)** and **B (Floodlight)** packs as re-skins.
