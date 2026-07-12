# Shell + Home Page Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn the current Home *shell* (header · hero · stat strip · footer) into the full Court Side **Home page** — upgraded header/footer plus quick-access tiles, news ticker, sports card grid, clubhouse image band, membership preview, tabbed club-activity, news, info strip and sponsors — all skin-agnostic and viewable live in the localhost preview.

**Architecture:** Every section is a static method on `Blueworx_Clubhouse_Sections` returning semantic HTML with only `ch-*` classes (no colour/font/radius/look literals, all interpolated text escaped). `Blueworx_Clubhouse_Page_Renderer::home()` composes them in order, each wrapped in a `Visibility` check and fed hardcoded ClubHouse demo data. `assets/looks/court-side.css` grows to style every new class using engine custom properties only. `preview/index.php` gains `?page=` routing so the site is navigable on localhost.

**Tech Stack:** PHP 8.1+ (`declare(strict_types=1)`), PHPUnit 10 (dev-only, no WordPress runtime), vanilla progressive-enhancement JS, CSS custom properties. Preview served with `php -S localhost:8124` from the plugin root.

## Global Constraints

Copy these into every task's mental checklist — they are implicit requirements everywhere.

- **Skin-agnostic markup:** section methods emit only `ch-*` classes. **No** hex colours, **no** `style=` attributes, no font/radius literals, no look slugs in the HTML. Enforced by test (`assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{3,6}\b/')` + `assertStringNotContainsString('style=')`).
- **Escape all interpolated text** at the render boundary via the existing `Blueworx_Clubhouse_Sections::e()` helper (`htmlspecialchars( $s, ENT_QUOTES, 'UTF-8' )`).
- **Court Side CSS consumes custom properties only:** every colour is `var(--color-*)`; the accent is always `var(--color-accent*)`. The literal `#c6f24e` must never appear in `court-side.css` (enforced by `CourtSideStylesheetTest`).
- **Icon-light:** no Material Symbols / icon font, no second font CDN. Use accent-dots, "→" text arrows, W/D/L text badges, and single-letter social pills.
- **Progressive-enhancement JS only:** tabs render **all** panels server-side and work with JS disabled; a tiny inline vanilla script enhances them. No framework, no hydration.
- **Forms are presentational:** the newsletter input in the footer renders styled markup with no submission handler.
- **Demo data = ClubHouse:** all copy uses "ClubHouse" branding (export's "Marlow Community SC" is adapted), hardcoded inline in `home()`.
- **Image slots degrade to a CSS placeholder** when no URL is supplied — no external image dependencies. Demo data passes `''` for images this round.
- **PHP conventions:** every new/edited PHP file starts with `<?php`, `declare(strict_types=1);`, then the `if ( ! defined( 'ABSPATH' ) ) { exit; }` guard (except test files). Classes are `final`, prefixed `Blueworx_Clubhouse_`.
- **Versioning:** bump the plugin version (minor — new feature) and update the changelog in the final task, before any PR.
- **Run tests with:** `vendor/bin/phpunit` (or `php vendor/phpunit/phpunit/phpunit`) from the plugin root. Runtime class order lives in `includes/bootstrap.php`.

---

## File structure

- **Modify:** `includes/render/class-sections.php` — add every new section renderer + a shared `media()` helper.
- **Modify:** `includes/render/class-page-renderer.php` — grow `home()` to compose all sections with demo data.
- **Modify:** `assets/looks/court-side.css` — style every new `ch-*` class.
- **Modify:** `preview/index.php` — add `?page=` routing.
- **Modify:** `tests/php/SectionsTest.php` — one test per renderer (structure + escape + skin-agnostic guard).
- **Modify:** `tests/php/PageRendererTest.php` — assert new sections appear in `home()` and honour visibility.
- **Modify:** `tests/php/PreviewRenderTest.php` — assert routing.
- **Modify:** `clubhouse.php` (or the main plugin file) + `CHANGELOG.md` — version bump (final task).

Reusable renderer signatures produced by this plan (all `public static` on `Blueworx_Clubhouse_Sections`, all return `string`):

```
header(array{club_name:string, banner:string, banner_href:string,
       nav:array<int,array{label:string,href:string}>, active:string,
       login:string, join:string, join_href:string}): string
hero(array{eyebrow:string, title_lead:string, title_highlight:string, lede:string,
     cta_primary:string, cta_primary_href:string, cta_secondary:string,
     cta_secondary_href:string, image:string, image_alt:string, image_caption:string}): string
quick_tiles(array<int,array{label:string,href:string}>): string
ticker(array<int,string>): string
card_grid(array{eyebrow:string, heading:string, link_label:string, link_href:string,
          cards:array<int,array{image:string,image_alt:string,tag:string,title:string,subtitle:string}>}): string
image_band(array{eyebrow:string, heading:string, image:string, image_alt:string,
           cta_label:string, cta_href:string}): string
band(array{variant:string, eyebrow:string, heading:string, lede:string,
     cta_label:string, cta_href:string}): string           // variant: 'accent' | 'ink'
tier_grid(array<int,array{eyebrow:string,name:string,price:string,period:string,
          features:array<int,string>,recommended:bool,cta_label:string,cta_href:string}>): string
activity_tabs(array{eyebrow:string, heading:string,
          fixtures:array<int,array{month:string,day:string,competition:string,time:string,matchup:string}>,
          results:array<int,array{date:string,home:string,away:string,score:string,outcome:string}>,
          events:array<int,array{tag:string,date:string,title:string,detail:string}>}): string
news_cards(array{eyebrow:string, heading:string,
          cards:array<int,array{image:string,image_alt:string,tag:string,date:string,title:string}>}): string
info_strip(array<int,array{label:string,lines:array<int,string>,link_label:string,link_href:string}>): string
sponsors(array{heading:string, link_label:string, link_href:string, names:array<int,string>}): string
footer(array{club_name:string, tagline:string, socials:array<int,string>,
       columns:array<int,array{title:string,links:array<int,array{label:string,href:string}>}>,
       newsletter:array{heading:string,lede:string,placeholder:string,cta:string},
       legal:array<int,array{label:string,href:string}>}): string
```

---

### Task 1: Shared `media()` helper + upgraded `header`

**Files:**
- Modify: `includes/render/class-sections.php`
- Modify: `includes/render/class-page-renderer.php` (`home()` header call)
- Modify: `assets/looks/court-side.css`
- Test: `tests/php/SectionsTest.php`

**Interfaces:**
- Consumes: existing `Blueworx_Clubhouse_Sections::e()`.
- Produces: `header(array{...})` (signature above) and `private static function media(string $url, string $alt, string $modifier): string`.

- [ ] **Step 1: Replace the header test** in `tests/php/SectionsTest.php` (the old `test_header_renders_brand_nav_and_cta` used a flat string nav — replace it):

```php
	public function test_header_renders_banner_nav_active_and_dual_cta(): void {
		$html = Blueworx_Clubhouse_Sections::header( array(
			'club_name'   => 'ClubHouse',
			'banner'      => 'Summer sign-ups are open →',
			'banner_href' => '?page=membership',
			'nav'         => array(
				array( 'label' => 'Home', 'href' => '?page=home' ),
				array( 'label' => 'Membership', 'href' => '?page=membership' ),
			),
			'active'      => '?page=home',
			'login'       => 'Log in',
			'join'        => 'Join the Club',
			'join_href'   => '?page=membership',
		) );
		$this->assertStringContainsString( 'class="ch-banner"', $html );
		$this->assertStringContainsString( 'Summer sign-ups are open', $html );
		$this->assertStringContainsString( 'class="ch-nav"', $html );
		$this->assertStringContainsString( 'ch-nav__link--active', $html );
		$this->assertStringContainsString( 'Log in', $html );
		$this->assertStringContainsString( 'Join the Club', $html );
		$this->assertDoesNotMatchRegularExpression( '/#[0-9a-fA-F]{3,6}\b/', $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}

	public function test_header_hides_banner_when_empty(): void {
		$html = Blueworx_Clubhouse_Sections::header( array(
			'club_name' => 'ClubHouse', 'banner' => '', 'banner_href' => '',
			'nav' => array(), 'active' => '', 'login' => 'Log in',
			'join' => 'Join', 'join_href' => '#',
		) );
		$this->assertStringNotContainsString( 'class="ch-banner"', $html );
	}
```

- [ ] **Step 2: Run the tests, verify they fail**

Run: `vendor/bin/phpunit --filter test_header`
Expected: FAIL — `header()` still uses the old signature (missing `ch-banner`, `ch-nav__link--active`).

- [ ] **Step 3: Add the `media()` helper and rewrite `header()`** in `includes/render/class-sections.php` (replace the existing `header()` method; add `media()` just below `e()`):

```php
	/** Image slot that degrades to a tonal placeholder when no URL is given. */
	private static function media( string $url, string $alt, string $modifier ): string {
		$cls = 'ch-media' . ( '' !== $modifier ? ' ' . $modifier : '' );
		$img = '' !== $url
			? '<img class="ch-media__img" src="' . self::e( $url ) . '" alt="' . self::e( $alt ) . '">'
			: '';
		return '<div class="' . $cls . '">' . $img . '</div>';
	}

	/**
	 * @param array{club_name:string,banner:string,banner_href:string,
	 *   nav:array<int,array{label:string,href:string}>,active:string,
	 *   login:string,join:string,join_href:string} $data
	 */
	public static function header( array $data ): string {
		$banner = '';
		if ( '' !== $data['banner'] ) {
			$banner = '<div class="ch-banner"><div class="ch-wrap ch-banner__in">'
				. '<a class="ch-banner__link" href="' . self::e( $data['banner_href'] ) . '">'
				. self::e( $data['banner'] ) . '</a></div></div>';
		}
		$links = '';
		foreach ( $data['nav'] as $item ) {
			$active  = $item['href'] === $data['active'] ? ' ch-nav__link--active' : '';
			$links  .= '<a class="ch-nav__link' . $active . '" href="' . self::e( $item['href'] ) . '">'
				. self::e( $item['label'] ) . '</a>';
		}
		return $banner
			. '<header class="ch-nav"><div class="ch-wrap ch-nav__in">'
			. '<a class="ch-brand" href="?page=home"><span class="ch-brand__mark">C</span>' . self::e( $data['club_name'] ) . '</a>'
			. '<nav class="ch-nav__links">' . $links . '</nav>'
			. '<div class="ch-nav__cta">'
			. '<a class="ch-btn ch-btn--ghost" href="#">' . self::e( $data['login'] ) . '</a>'
			. '<a class="ch-btn ch-btn--ink" href="' . self::e( $data['join_href'] ) . '">' . self::e( $data['join'] ) . '</a>'
			. '</div></div></header>';
	}
```

- [ ] **Step 4: Update the `home()` header call** in `includes/render/class-page-renderer.php` (replace the existing `header(...)` argument array inside the `is_section_visible( 'home', 'header' )` block):

```php
			$out .= Blueworx_Clubhouse_Sections::header( array(
				'club_name'   => $club,
				'banner'      => 'Summer sign-ups are open — register your interest for 2026/27 →',
				'banner_href' => '?page=membership',
				'nav'         => array(
					array( 'label' => 'Home', 'href' => '?page=home' ),
					array( 'label' => 'About', 'href' => '?page=about' ),
					array( 'label' => 'Sports', 'href' => '?page=sports' ),
					array( 'label' => 'Teams', 'href' => '?page=teams' ),
					array( 'label' => 'Membership', 'href' => '?page=membership' ),
					array( 'label' => 'Events', 'href' => '?page=events' ),
					array( 'label' => 'Calendar', 'href' => '?page=calendar' ),
					array( 'label' => 'Contact', 'href' => '?page=contact' ),
				),
				'active'      => '?page=home',
				'login'       => 'Log in',
				'join'        => 'Join the Club',
				'join_href'   => '?page=membership',
			) );
```

- [ ] **Step 5: Add CSS** for the banner + upgraded nav to `assets/looks/court-side.css` (append; the existing `.ch-nav`/`.ch-brand` rules stay). Add a `.ch-media` placeholder rule too — it is reused everywhere:

```css
.ch-banner{background:var(--color-ink);color:var(--color-bg)}
.ch-banner__in{display:flex;justify-content:center;height:40px;align-items:center}
.ch-banner__link{font-size:13px;font-weight:600;text-align:center}
.ch-banner__link:hover{color:var(--color-accent)}
.ch-nav__cta{display:flex;gap:10px;align-items:center}
.ch-nav__link--active{color:var(--color-accent-deep);font-weight:600}
@media(max-width:900px){.ch-nav__cta .ch-btn--ghost{display:none}}

.ch-media{position:relative;overflow:hidden;background:var(--color-line);border-radius:var(--radius-lg)}
.ch-media__img{width:100%;height:100%;object-fit:cover;display:block}
```

- [ ] **Step 6: Run the full suite, verify green**

Run: `vendor/bin/phpunit`
Expected: PASS (existing `test_home_includes_the_shell_sections` / `test_home_respects_visibility` still green — `ch-nav` still emitted).

- [ ] **Step 7: Commit**

```bash
git add includes/render/class-sections.php includes/render/class-page-renderer.php assets/looks/court-side.css tests/php/SectionsTest.php
git commit -m "feat: upgrade header (banner, dual CTA, active nav) + media helper"
```

---

### Task 2: Hero media block

**Files:**
- Modify: `includes/render/class-sections.php` (`hero()`)
- Modify: `includes/render/class-page-renderer.php` (`home()` hero call)
- Modify: `assets/looks/court-side.css`
- Test: `tests/php/SectionsTest.php`

**Interfaces:**
- Consumes: `media()` (Task 1).
- Produces: extended `hero(array{...})` with `image`, `image_alt`, `image_caption`, and per-CTA hrefs.

- [ ] **Step 1: Replace the hero test** in `tests/php/SectionsTest.php` (`test_hero_highlights_the_accent_span` — extend it to cover the media block):

```php
	public function test_hero_highlights_accent_and_renders_media(): void {
		$html = Blueworx_Clubhouse_Sections::hero( array(
			'eyebrow'            => 'Est. 1974',
			'title_lead'         => 'Every sport. Every age. ',
			'title_highlight'    => 'One community.',
			'lede'               => 'Nine sports, twenty-four teams.',
			'cta_primary'        => 'Explore membership',
			'cta_primary_href'   => '?page=membership',
			'cta_secondary'      => 'Take a tour',
			'cta_secondary_href' => '#',
			'image'              => '',
			'image_alt'          => '',
			'image_caption'      => 'Saturday, floodlights on',
		) );
		$this->assertStringContainsString( 'class="ch-hero"', $html );
		$this->assertStringContainsString( 'class="ch-hero__hl"', $html );
		$this->assertStringContainsString( 'class="ch-hero__media"', $html );
		$this->assertStringContainsString( 'Saturday, floodlights on', $html );
		$this->assertDoesNotMatchRegularExpression( '/#[0-9a-fA-F]{3,6}\b/', $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}
```

Delete the now-duplicated `test_no_colour_literals_leak_into_markup` only if it references the old hero signature — it calls `hero()` with the old keys, so update its argument array to include `cta_primary_href => '', cta_secondary_href => '', image => '', image_alt => '', image_caption => ''`.

- [ ] **Step 2: Run, verify fail**

Run: `vendor/bin/phpunit --filter test_hero`
Expected: FAIL — no `ch-hero__media` yet.

- [ ] **Step 3: Rewrite `hero()`** in `includes/render/class-sections.php`:

```php
	/**
	 * @param array{eyebrow:string,title_lead:string,title_highlight:string,lede:string,
	 *   cta_primary:string,cta_primary_href:string,cta_secondary:string,
	 *   cta_secondary_href:string,image:string,image_alt:string,image_caption:string} $data
	 */
	public static function hero( array $data ): string {
		$caption = '' !== $data['image_caption']
			? '<div class="ch-hero__pill"><i class="ch-hero__pill-dot"></i>' . self::e( $data['image_caption'] ) . '</div>'
			: '';
		$media = '<div class="ch-hero__media">' . self::media( $data['image'], $data['image_alt'], '' ) . $caption . '</div>';
		return '<section class="ch-hero"><div class="ch-wrap">'
			. '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
			. '<h1 class="ch-hero__title">' . self::e( $data['title_lead'] )
			. '<span class="ch-hero__hl">' . self::e( $data['title_highlight'] ) . '</span></h1>'
			. '<div class="ch-hero__sub">'
			. '<p class="ch-hero__lede">' . self::e( $data['lede'] ) . '</p>'
			. '<div class="ch-hero__cta">'
			. '<a class="ch-btn ch-btn--accent" href="' . self::e( $data['cta_primary_href'] ) . '">' . self::e( $data['cta_primary'] ) . '</a>'
			. '<a class="ch-btn ch-btn--ghost" href="' . self::e( $data['cta_secondary_href'] ) . '">' . self::e( $data['cta_secondary'] ) . '</a>'
			. '</div></div>'
			. $media
			. '</div></section>';
	}
```

- [ ] **Step 4: Update the `home()` hero call** in `class-page-renderer.php`:

```php
			$out .= Blueworx_Clubhouse_Sections::hero( array(
				'eyebrow'            => 'Est. 1974 · Marlow, UK',
				'title_lead'         => 'Every sport. Every age. ',
				'title_highlight'    => 'One community.',
				'lede'               => "Nine sports, twenty-four teams, and a clubhouse that's always open. Come for the game — stay for the people.",
				'cta_primary'        => 'Explore membership',
				'cta_primary_href'   => '?page=membership',
				'cta_secondary'      => 'Take a tour →',
				'cta_secondary_href' => '?page=about',
				'image'              => '',
				'image_alt'          => 'ClubHouse floodlit pitch on a Saturday',
				'image_caption'      => 'Saturday, floodlights on',
			) );
```

- [ ] **Step 5: Add CSS** to `court-side.css` (append; keep existing `.ch-hero*` rules):

```css
.ch-hero__media{margin-top:34px;aspect-ratio:16/7}
.ch-hero__media .ch-media{width:100%;height:100%;border-radius:var(--radius-xl)}
.ch-hero__pill{position:absolute;left:20px;bottom:20px;z-index:2;background:var(--color-paper);border-radius:999px;padding:11px 18px;font-size:13px;font-weight:600;display:flex;align-items:center;gap:9px}
.ch-hero__pill-dot{width:9px;height:9px;border-radius:50%;background:var(--color-accent)}
@media(max-width:640px){.ch-hero__media{aspect-ratio:4/3}}
```

- [ ] **Step 6: Run suite, verify green**

Run: `vendor/bin/phpunit`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add includes/render/class-sections.php includes/render/class-page-renderer.php assets/looks/court-side.css tests/php/SectionsTest.php
git commit -m "feat: add hero media block with status pill"
```

---

### Task 3: Quick-access tiles

**Files:** Modify `class-sections.php`, `class-page-renderer.php`, `court-side.css`; Test `SectionsTest.php`, `PageRendererTest.php`.

**Interfaces:** Produces `quick_tiles(array<int,array{label:string,href:string}>): string`.

- [ ] **Step 1: Add test** to `SectionsTest.php`:

```php
	public function test_quick_tiles_render_each_link(): void {
		$html = Blueworx_Clubhouse_Sections::quick_tiles( array(
			array( 'label' => 'Membership', 'href' => '?page=membership' ),
			array( 'label' => 'Sports', 'href' => '?page=sports' ),
		) );
		$this->assertStringContainsString( 'class="ch-tiles"', $html );
		$this->assertSame( 2, substr_count( $html, 'ch-tiles__tile' ) );
		$this->assertStringContainsString( 'Membership', $html );
		$this->assertDoesNotMatchRegularExpression( '/#[0-9a-fA-F]{3,6}\b/', $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}
```

- [ ] **Step 2: Run, verify fail** — `vendor/bin/phpunit --filter test_quick_tiles` → FAIL (method undefined).

- [ ] **Step 3: Implement** in `class-sections.php`:

```php
	/** @param array<int,array{label:string,href:string}> $tiles */
	public static function quick_tiles( array $tiles ): string {
		$items = '';
		foreach ( $tiles as $t ) {
			$items .= '<a class="ch-tiles__tile" href="' . self::e( $t['href'] ) . '">'
				. '<span class="ch-tiles__label">' . self::e( $t['label'] ) . '</span>'
				. '<span class="ch-tiles__arrow" aria-hidden="true">→</span></a>';
		}
		return '<section class="ch-tiles-sec"><div class="ch-wrap"><div class="ch-tiles">' . $items . '</div></div></section>';
	}
```

- [ ] **Step 4: Wire into `home()`** — add after the `hero` visibility block, before `stats`:

```php
			if ( $visibility->is_section_visible( 'home', 'quick_tiles' ) ) {
				$out .= Blueworx_Clubhouse_Sections::quick_tiles( array(
					array( 'label' => 'Join / Membership', 'href' => '?page=membership' ),
					array( 'label' => 'Sports & Sections', 'href' => '?page=sports' ),
					array( 'label' => 'Fixtures & Results', 'href' => '?page=calendar' ),
					array( 'label' => 'Events', 'href' => '?page=events' ),
					array( 'label' => 'Contact', 'href' => '?page=contact' ),
					array( 'label' => 'Member Login', 'href' => '#' ),
				) );
			}
```

- [ ] **Step 5: Add CSS**:

```css
.ch-tiles-sec{padding:26px 0 8px}
.ch-tiles{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px}
.ch-tiles__tile{display:flex;align-items:center;justify-content:space-between;gap:12px;background:var(--color-paper);border:1px solid var(--color-line);border-radius:var(--radius-md);padding:18px 20px;font-weight:600;font-size:15px;transition:background .18s ease,color .18s ease}
.ch-tiles__tile:hover{background:var(--color-ink);color:var(--color-bg)}
.ch-tiles__arrow{color:var(--color-accent-deep)}
.ch-tiles__tile:hover .ch-tiles__arrow{color:var(--color-accent)}
```

- [ ] **Step 6: Add a `home()` assertion** to `PageRendererTest.php` inside `test_home_includes_the_shell_sections`:

```php
		$this->assertStringContainsString( 'class="ch-tiles"', $body );
```

- [ ] **Step 7: Run suite (`vendor/bin/phpunit`) → PASS, then commit**

```bash
git add includes/render/class-sections.php includes/render/class-page-renderer.php assets/looks/court-side.css tests/php/SectionsTest.php tests/php/PageRendererTest.php
git commit -m "feat: add quick-access tiles to Home"
```

---

### Task 4: News ticker

**Files:** Modify `class-sections.php`, `class-page-renderer.php`, `court-side.css`; Test `SectionsTest.php`.

**Interfaces:** Produces `ticker(array<int,string>): string`.

- [ ] **Step 1: Add test**:

```php
	public function test_ticker_repeats_items_for_marquee(): void {
		$html = Blueworx_Clubhouse_Sections::ticker( array( 'News one', 'News two' ) );
		$this->assertStringContainsString( 'class="ch-ticker"', $html );
		$this->assertStringContainsString( 'News one', $html );
		// The track is duplicated so the CSS marquee loops seamlessly.
		$this->assertSame( 2, substr_count( $html, 'ch-ticker__track' ) );
		$this->assertStringNotContainsString( 'style=', $html );
	}
```

- [ ] **Step 2: Run, verify fail.**

- [ ] **Step 3: Implement** (the track is emitted twice; CSS translates -50% for a seamless loop, `aria-hidden` on the duplicate):

```php
	/** @param array<int,string> $items */
	public static function ticker( array $items ): string {
		$build = static function ( bool $hidden ) use ( $items ): string {
			$out = '<div class="ch-ticker__track"' . ( $hidden ? ' aria-hidden="true"' : '' ) . '>';
			foreach ( $items as $item ) {
				$out .= '<span class="ch-ticker__item"><i class="ch-ticker__dot"></i>' . self::e( $item ) . '</span>';
			}
			return $out . '</div>';
		};
		return '<section class="ch-ticker"><div class="ch-ticker__label">Club news</div>'
			. '<div class="ch-ticker__viewport">' . $build( false ) . $build( true ) . '</div></section>';
	}
```

- [ ] **Step 4: Wire into `home()`** after quick_tiles:

```php
			if ( $visibility->is_section_visible( 'home', 'ticker' ) ) {
				$out .= Blueworx_Clubhouse_Sections::ticker( array(
					'1st XV promoted to Div 3 South',
					'Open Day — Sat 26 Jul, 10:00–14:00',
					'Clubhouse refurbishment complete',
					'Summer Football Camp · 4–8 Aug',
				) );
			}
```

- [ ] **Step 5: Add CSS** (respects reduced-motion):

```css
.ch-ticker{display:flex;align-items:center;gap:0;background:var(--color-ink);color:var(--color-bg);overflow:hidden;margin:8px 0}
.ch-ticker__label{flex:none;background:var(--color-accent);color:var(--color-accent-ink);font-size:12px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;padding:12px 16px}
.ch-ticker__viewport{position:relative;display:flex;overflow:hidden;white-space:nowrap}
.ch-ticker__track{display:inline-flex;align-items:center;gap:34px;padding:0 17px;animation:ch-marquee 28s linear infinite}
.ch-ticker:hover .ch-ticker__track{animation-play-state:paused}
.ch-ticker__item{display:inline-flex;align-items:center;gap:11px;font-size:14px;font-weight:500}
.ch-ticker__dot{width:6px;height:6px;border-radius:50%;background:var(--color-accent)}
@keyframes ch-marquee{from{transform:translateX(0)}to{transform:translateX(-50%)}}
@media(prefers-reduced-motion:reduce){.ch-ticker__track{animation:none}.ch-ticker__viewport{overflow-x:auto}.ch-ticker__track[aria-hidden="true"]{display:none}}
```

- [ ] **Step 6: Run suite → PASS, commit**

```bash
git add includes/render/class-sections.php includes/render/class-page-renderer.php assets/looks/court-side.css tests/php/SectionsTest.php
git commit -m "feat: add CSS news ticker (reduced-motion safe) to Home"
```

---

### Task 5: Sports card grid

**Files:** Modify `class-sections.php`, `class-page-renderer.php`, `court-side.css`; Test `SectionsTest.php`, `PageRendererTest.php`.

**Interfaces:** Produces `card_grid(array{eyebrow,heading,link_label,link_href,cards:array<int,array{image,image_alt,tag,title,subtitle}>}): string`.

- [ ] **Step 1: Add test**:

```php
	public function test_card_grid_renders_head_and_overlay_cards(): void {
		$html = Blueworx_Clubhouse_Sections::card_grid( array(
			'eyebrow'    => 'Our sports',
			'heading'    => 'Pick your game.',
			'link_label' => 'All sections →',
			'link_href'  => '?page=sports',
			'cards'      => array(
				array( 'image' => '', 'image_alt' => '', 'tag' => 'Sat', 'title' => 'Rugby', 'subtitle' => 'Senior · colts · touch' ),
				array( 'image' => '', 'image_alt' => '', 'tag' => 'Daily', 'title' => 'Tennis', 'subtitle' => 'Four courts · coaching' ),
			),
		) );
		$this->assertStringContainsString( 'class="ch-cards"', $html );
		$this->assertSame( 2, substr_count( $html, 'ch-card"' ) );
		$this->assertStringContainsString( 'Pick your game.', $html );
		$this->assertStringContainsString( 'Rugby', $html );
		$this->assertDoesNotMatchRegularExpression( '/#[0-9a-fA-F]{3,6}\b/', $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}
```

- [ ] **Step 2: Run, verify fail.**

- [ ] **Step 3: Implement**:

```php
	/**
	 * @param array{eyebrow:string,heading:string,link_label:string,link_href:string,
	 *   cards:array<int,array{image:string,image_alt:string,tag:string,title:string,subtitle:string}>} $data
	 */
	public static function card_grid( array $data ): string {
		$cards = '';
		foreach ( $data['cards'] as $c ) {
			$cards .= '<article class="ch-card">'
				. self::media( $c['image'], $c['image_alt'], 'ch-card__media' )
				. '<div class="ch-card__scrim"></div>'
				. '<span class="ch-card__tag">' . self::e( $c['tag'] ) . '</span>'
				. '<div class="ch-card__body"><h3 class="ch-card__title">' . self::e( $c['title'] ) . '</h3>'
				. '<p class="ch-card__sub">' . self::e( $c['subtitle'] ) . '</p></div></article>';
		}
		return '<section class="ch-sec"><div class="ch-wrap">'
			. '<div class="ch-sec__head"><div>'
			. '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
			. '<h2 class="ch-sec__title">' . self::e( $data['heading'] ) . '</h2></div>'
			. '<a class="ch-btn ch-btn--ghost" href="' . self::e( $data['link_href'] ) . '">' . self::e( $data['link_label'] ) . '</a></div>'
			. '<div class="ch-cards">' . $cards . '</div></div></section>';
	}
```

- [ ] **Step 4: Wire into `home()`** after `stats`:

```php
			if ( $visibility->is_section_visible( 'home', 'sports' ) ) {
				$out .= Blueworx_Clubhouse_Sections::card_grid( array(
					'eyebrow'    => 'Our sports',
					'heading'    => 'Pick your game.',
					'link_label' => 'All sections →',
					'link_href'  => '?page=sports',
					'cards'      => array(
						array( 'image' => '', 'image_alt' => 'Rugby', 'tag' => 'Sat', 'title' => 'Rugby', 'subtitle' => 'Senior · colts · touch' ),
						array( 'image' => '', 'image_alt' => 'Tennis', 'tag' => 'Daily', 'title' => 'Tennis', 'subtitle' => 'Four courts · coaching' ),
						array( 'image' => '', 'image_alt' => 'Cricket', 'tag' => 'Summer', 'title' => 'Cricket', 'subtitle' => 'Youth → senior league' ),
						array( 'image' => '', 'image_alt' => 'Football', 'tag' => 'Sun', 'title' => 'Football', 'subtitle' => 'Juniors · ages 5–16' ),
					),
				) );
			}
```

- [ ] **Step 5: Add CSS** (overlay cards + shared section-head styles reused by later tasks):

```css
.ch-sec{padding:80px 0}
.ch-sec__head{display:flex;justify-content:space-between;align-items:end;gap:24px;margin-bottom:34px;flex-wrap:wrap}
.ch-sec__title{font-family:var(--font-display);font-weight:800;font-size:clamp(36px,5.5vw,64px);letter-spacing:-.03em;max-width:16ch;margin-top:14px;line-height:.98}
.ch-cards{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}
.ch-card{position:relative;border-radius:var(--radius-lg);overflow:hidden;aspect-ratio:3/4;background:var(--color-line);color:var(--color-bg);display:flex;flex-direction:column;justify-content:end;transition:transform .2s ease}
.ch-card:hover{transform:translateY(-5px)}
.ch-card__media{position:absolute;inset:0;border-radius:0}
.ch-card__scrim{position:absolute;inset:0;background:linear-gradient(180deg,transparent 30%,color-mix(in oklab,var(--color-ink) 82%,transparent))}
.ch-card__tag{position:absolute;top:14px;left:14px;z-index:2;background:var(--color-accent);color:var(--color-accent-ink);font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;padding:6px 11px;border-radius:999px}
.ch-card__body{position:relative;z-index:2;padding:20px}
.ch-card__title{font-family:var(--font-display);font-weight:800;font-size:24px}
.ch-card__sub{font-size:14px;color:color-mix(in oklab,var(--color-bg) 88%,transparent);margin-top:4px}
@media(max-width:900px){.ch-cards{grid-template-columns:1fr 1fr}}
```

- [ ] **Step 6: Add `home()` assertion** to `PageRendererTest.php`:

```php
		$this->assertStringContainsString( 'class="ch-cards"', $body );
```

- [ ] **Step 7: Run suite → PASS, commit**

```bash
git add includes/render/class-sections.php includes/render/class-page-renderer.php assets/looks/court-side.css tests/php/SectionsTest.php tests/php/PageRendererTest.php
git commit -m "feat: add sports card grid to Home"
```

---

### Task 6: Clubhouse image band

**Files:** Modify `class-sections.php`, `class-page-renderer.php`, `court-side.css`; Test `SectionsTest.php`.

**Interfaces:** Produces `image_band(array{eyebrow,heading,image,image_alt,cta_label,cta_href}): string`.

- [ ] **Step 1: Add test**:

```php
	public function test_image_band_renders_overlay_heading_and_cta(): void {
		$html = Blueworx_Clubhouse_Sections::image_band( array(
			'eyebrow'   => 'The clubhouse',
			'heading'   => 'A home ground for every team',
			'image'     => '', 'image_alt' => '',
			'cta_label' => 'Visit us', 'cta_href' => '?page=contact',
		) );
		$this->assertStringContainsString( 'class="ch-band-img"', $html );
		$this->assertStringContainsString( 'A home ground for every team', $html );
		$this->assertStringContainsString( 'Visit us', $html );
		$this->assertDoesNotMatchRegularExpression( '/#[0-9a-fA-F]{3,6}\b/', $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}
```

- [ ] **Step 2: Run, verify fail.**

- [ ] **Step 3: Implement**:

```php
	/**
	 * @param array{eyebrow:string,heading:string,image:string,image_alt:string,
	 *   cta_label:string,cta_href:string} $data
	 */
	public static function image_band( array $data ): string {
		return '<section class="ch-band-img">'
			. self::media( $data['image'], $data['image_alt'], 'ch-band-img__media' )
			. '<div class="ch-band-img__scrim"></div>'
			. '<div class="ch-wrap ch-band-img__in"><div>'
			. '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
			. '<h2 class="ch-band-img__title">' . self::e( $data['heading'] ) . '</h2></div>'
			. '<a class="ch-btn ch-btn--accent" href="' . self::e( $data['cta_href'] ) . '">' . self::e( $data['cta_label'] ) . '</a>'
			. '</div></section>';
	}
```

- [ ] **Step 4: Wire into `home()`** after `sports`:

```php
			if ( $visibility->is_section_visible( 'home', 'clubhouse' ) ) {
				$out .= Blueworx_Clubhouse_Sections::image_band( array(
					'eyebrow'   => 'The clubhouse',
					'heading'   => 'A home ground for every team, and everyone who follows them',
					'image'     => '', 'image_alt' => 'ClubHouse pavilion at dusk',
					'cta_label' => 'Visit us', 'cta_href' => '?page=contact',
				) );
			}
```

- [ ] **Step 5: Add CSS**:

```css
.ch-band-img{position:relative;overflow:hidden;min-height:420px;display:flex;align-items:end;margin:40px 0}
.ch-band-img__media{position:absolute;inset:0;border-radius:0}
.ch-band-img__scrim{position:absolute;inset:0;background:linear-gradient(to top,color-mix(in oklab,var(--color-ink) 80%,transparent),transparent 65%)}
.ch-band-img__in{position:relative;z-index:2;display:flex;justify-content:space-between;align-items:end;gap:24px;flex-wrap:wrap;padding-bottom:48px;width:100%;color:var(--color-bg)}
.ch-band-img__title{font-family:var(--font-display);font-weight:800;font-size:clamp(30px,4.6vw,54px);letter-spacing:-.03em;max-width:18ch;margin-top:14px;line-height:1.03}
```

- [ ] **Step 6: Run suite → PASS, commit**

```bash
git add includes/render/class-sections.php includes/render/class-page-renderer.php assets/looks/court-side.css tests/php/SectionsTest.php
git commit -m "feat: add clubhouse image band to Home"
```

---

### Task 7: Membership band + tier grid

**Files:** Modify `class-sections.php`, `class-page-renderer.php`, `court-side.css`; Test `SectionsTest.php`, `PageRendererTest.php`.

**Interfaces:** Produces `band(array{variant,eyebrow,heading,lede,cta_label,cta_href}): string` and `tier_grid(array<int,array{eyebrow,name,price,period,features,recommended,cta_label,cta_href}>): string`.

- [ ] **Step 1: Add tests**:

```php
	public function test_band_accent_variant_renders_modifier(): void {
		$html = Blueworx_Clubhouse_Sections::band( array(
			'variant' => 'accent', 'eyebrow' => 'Membership',
			'heading' => 'Open to everyone, from £28/month.',
			'lede' => 'Every tier includes clubhouse access.',
			'cta_label' => 'Choose your tier', 'cta_href' => '?page=membership',
		) );
		$this->assertStringContainsString( 'ch-band--accent', $html );
		$this->assertStringContainsString( 'Open to everyone', $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}

	public function test_tier_grid_marks_recommended_and_lists_features(): void {
		$html = Blueworx_Clubhouse_Sections::tier_grid( array(
			array( 'eyebrow' => 'Full playing', 'name' => 'Adult', 'price' => '£28', 'period' => '/mo',
				'features' => array( 'Any section', 'League affiliation' ), 'recommended' => false,
				'cta_label' => 'Join', 'cta_href' => '?page=membership' ),
			array( 'eyebrow' => 'Best value', 'name' => 'Family', 'price' => '£45', 'period' => '/mo',
				'features' => array( 'Up to 5 members' ), 'recommended' => true,
				'cta_label' => 'Join', 'cta_href' => '?page=membership' ),
		) );
		$this->assertSame( 2, substr_count( $html, 'ch-tier"' ) + substr_count( $html, 'ch-tier ch-tier--pop"' ) );
		$this->assertStringContainsString( 'ch-tier--pop', $html );
		$this->assertStringContainsString( 'Any section', $html );
		$this->assertDoesNotMatchRegularExpression( '/#[0-9a-fA-F]{3,6}\b/', $html );
	}
```

- [ ] **Step 2: Run, verify fail.**

- [ ] **Step 3: Implement both** in `class-sections.php`:

```php
	/**
	 * @param array{variant:string,eyebrow:string,heading:string,lede:string,
	 *   cta_label:string,cta_href:string} $data variant: 'accent' | 'ink'
	 */
	public static function band( array $data ): string {
		$mod     = 'ink' === $data['variant'] ? 'ch-band--ink' : 'ch-band--accent';
		$btn     = 'ink' === $data['variant'] ? 'ch-btn--accent' : 'ch-btn--ink';
		$eyebrow = '' !== $data['eyebrow']
			? '<span class="ch-eyebrow ch-eyebrow--band">' . self::e( $data['eyebrow'] ) . '</span>' : '';
		$lede    = '' !== $data['lede'] ? '<p class="ch-band__lede">' . self::e( $data['lede'] ) . '</p>' : '';
		return '<section class="ch-wrap ch-band-wrap"><div class="ch-band ' . $mod . '">'
			. $eyebrow
			. '<h2 class="ch-band__title">' . self::e( $data['heading'] ) . '</h2>'
			. $lede
			. '<a class="ch-btn ' . $btn . '" href="' . self::e( $data['cta_href'] ) . '">' . self::e( $data['cta_label'] ) . '</a>'
			. '</div></section>';
	}

	/**
	 * @param array<int,array{eyebrow:string,name:string,price:string,period:string,
	 *   features:array<int,string>,recommended:bool,cta_label:string,cta_href:string}> $tiers
	 */
	public static function tier_grid( array $tiers ): string {
		$cards = '';
		foreach ( $tiers as $t ) {
			$cls   = $t['recommended'] ? 'ch-tier ch-tier--pop' : 'ch-tier';
			$btn   = $t['recommended'] ? 'ch-btn--accent' : 'ch-btn--ghost';
			$feats = '';
			foreach ( $t['features'] as $f ) {
				$feats .= '<li class="ch-tier__feat">' . self::e( $f ) . '</li>';
			}
			$cards .= '<div class="' . $cls . '">'
				. '<span class="ch-tier__k">' . self::e( $t['eyebrow'] ) . '</span>'
				. '<h3 class="ch-tier__name">' . self::e( $t['name'] ) . '</h3>'
				. '<div class="ch-tier__amt">' . self::e( $t['price'] ) . '<small>' . self::e( $t['period'] ) . '</small></div>'
				. '<ul class="ch-tier__feats">' . $feats . '</ul>'
				. '<a class="ch-btn ' . $btn . ' ch-tier__cta" href="' . self::e( $t['cta_href'] ) . '">' . self::e( $t['cta_label'] ) . '</a>'
				. '</div>';
		}
		return '<section class="ch-wrap"><div class="ch-tiers">' . $cards . '</div></section>';
	}
```

- [ ] **Step 4: Wire into `home()`** after `clubhouse`:

```php
			if ( $visibility->is_section_visible( 'home', 'membership' ) ) {
				$out .= Blueworx_Clubhouse_Sections::band( array(
					'variant'   => 'accent',
					'eyebrow'   => 'Membership',
					'heading'   => 'Open to everyone, from £28/month.',
					'lede'      => 'From first-timers to county players — every tier includes clubhouse access, discounted events and a free trial session.',
					'cta_label' => 'Choose your tier →',
					'cta_href'  => '?page=membership',
				) );
				$out .= Blueworx_Clubhouse_Sections::tier_grid( array(
					array( 'eyebrow' => 'Full playing', 'name' => 'Adult', 'price' => '£28', 'period' => '/mo',
						'features' => array( 'Any section, any level', 'League affiliation', 'Clubhouse & socials' ),
						'recommended' => false, 'cta_label' => 'Join', 'cta_href' => '?page=membership' ),
					array( 'eyebrow' => 'Best value', 'name' => 'Family', 'price' => '£45', 'period' => '/mo',
						'features' => array( 'Up to 5 members', 'Any sections', 'Priority event booking' ),
						'recommended' => true, 'cta_label' => 'Join', 'cta_href' => '?page=membership' ),
					array( 'eyebrow' => 'Off the pitch', 'name' => 'Social', 'price' => '£12', 'period' => '/mo',
						'features' => array( 'Full clubhouse access', 'Member events', 'Support your club' ),
						'recommended' => false, 'cta_label' => 'Join', 'cta_href' => '?page=membership' ),
				) );
			}
```

- [ ] **Step 5: Add CSS**:

```css
.ch-band-wrap{padding-top:40px}
.ch-band{border-radius:var(--radius-xl);padding:64px 52px;text-align:center}
.ch-band--accent{background:var(--color-accent);color:var(--color-accent-ink)}
.ch-band--ink{background:var(--color-ink);color:var(--color-bg)}
.ch-eyebrow--band{background:var(--color-ink);color:var(--color-accent)}
.ch-band--ink .ch-eyebrow--band{background:var(--color-accent);color:var(--color-accent-ink)}
.ch-band__title{font-family:var(--font-display);font-weight:800;font-size:clamp(34px,6vw,80px);letter-spacing:-.03em;margin-top:16px;line-height:.98}
.ch-band__lede{max-width:46ch;margin:18px auto 0;opacity:.85;font-size:19px}
.ch-band .ch-btn{margin-top:28px}
.ch-tiers{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-top:22px}
.ch-tier{background:var(--color-paper);border:1px solid var(--color-line);border-radius:var(--radius-lg);padding:28px;display:flex;flex-direction:column}
.ch-tier--pop{border:2px solid var(--color-ink)}
.ch-tier__k{font-size:12px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--color-ink-soft)}
.ch-tier__name{font-family:var(--font-display);font-weight:800;font-size:30px;margin:6px 0 12px}
.ch-tier__amt{font-family:var(--font-display);font-weight:800;font-size:40px}
.ch-tier__amt small{font-family:var(--font-body);font-weight:500;font-size:15px;color:var(--color-ink-soft)}
.ch-tier__feats{list-style:none;margin:18px 0 22px;display:grid;gap:9px}
.ch-tier__feat{font-size:15px;color:var(--color-ink-soft);display:flex;gap:9px;align-items:center}
.ch-tier__feat::before{content:"";width:16px;height:16px;border-radius:50%;background:var(--color-accent);flex:none}
.ch-tier__cta{width:100%;justify-content:center;margin-top:auto}
@media(max-width:820px){.ch-tiers{grid-template-columns:1fr}}
```

- [ ] **Step 6: Add `home()` assertion** to `PageRendererTest.php`:

```php
		$this->assertStringContainsString( 'class="ch-tiers"', $body );
```

- [ ] **Step 7: Run suite → PASS, commit**

```bash
git add includes/render/class-sections.php includes/render/class-page-renderer.php assets/looks/court-side.css tests/php/SectionsTest.php tests/php/PageRendererTest.php
git commit -m "feat: add membership band + tier grid to Home"
```

---

### Task 8: Club-activity tabs (fixtures / results / events)

**Files:** Modify `class-sections.php`, `class-page-renderer.php`, `court-side.css`; Test `SectionsTest.php`.

**Interfaces:** Produces `activity_tabs(array{eyebrow,heading,fixtures[],results[],events[]}): string`. All three panels render server-side; a tiny inline script toggles them (works with JS off — all panels visible / stacked).

- [ ] **Step 1: Add test**:

```php
	public function test_activity_tabs_render_all_three_panels(): void {
		$html = Blueworx_Clubhouse_Sections::activity_tabs( array(
			'eyebrow'  => 'Club activity',
			'heading'  => "What's happening",
			'fixtures' => array( array( 'month' => 'JUL', 'day' => '12', 'competition' => 'Rugby · 1st XV', 'time' => '14:00', 'matchup' => 'ClubHouse vs Riverside' ) ),
			'results'  => array( array( 'date' => 'JUL 5', 'home' => 'ClubHouse 1st XI', 'away' => 'Hartfield', 'score' => '+34', 'outcome' => 'W' ) ),
			'events'   => array( array( 'tag' => 'Open day', 'date' => 'Sat 26 Jul', 'title' => 'Club Open Day', 'detail' => '10:00–14:00' ) ),
		) );
		$this->assertStringContainsString( 'class="ch-tabs"', $html );
		$this->assertSame( 3, substr_count( $html, 'ch-tabs__panel' ) );
		$this->assertStringContainsString( 'data-ch-tab="fixtures"', $html );
		$this->assertStringContainsString( 'ClubHouse vs Riverside', $html );
		$this->assertStringContainsString( 'ch-badge--w', $html );
		$this->assertDoesNotMatchRegularExpression( '/#[0-9a-fA-F]{3,6}\b/', $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}
```

- [ ] **Step 2: Run, verify fail.**

- [ ] **Step 3: Implement** (note the outcome maps to a `ch-badge--w|l|d` modifier — text badge, no colour literal in markup):

```php
	/**
	 * @param array{eyebrow:string,heading:string,
	 *   fixtures:array<int,array{month:string,day:string,competition:string,time:string,matchup:string}>,
	 *   results:array<int,array{date:string,home:string,away:string,score:string,outcome:string}>,
	 *   events:array<int,array{tag:string,date:string,title:string,detail:string}>} $data
	 */
	public static function activity_tabs( array $data ): string {
		$fx = '';
		foreach ( $data['fixtures'] as $f ) {
			$fx .= '<div class="ch-fx"><div class="ch-fx__date"><b>' . self::e( $f['day'] ) . '</b><span>' . self::e( $f['month'] ) . '</span></div>'
				. '<div class="ch-fx__body"><span class="ch-fx__comp">' . self::e( $f['competition'] ) . '</span>'
				. '<span class="ch-fx__match">' . self::e( $f['matchup'] ) . '</span></div>'
				. '<span class="ch-fx__time">' . self::e( $f['time'] ) . '</span></div>';
		}
		$rs = '';
		foreach ( $data['results'] as $r ) {
			$o    = strtolower( $r['outcome'] );
			$mod  = in_array( $o, array( 'w', 'l', 'd' ), true ) ? $o : 'd';
			$rs  .= '<div class="ch-res"><span class="ch-res__date">' . self::e( $r['date'] ) . '</span>'
				. '<span class="ch-res__teams">' . self::e( $r['home'] ) . ' v ' . self::e( $r['away'] ) . '</span>'
				. '<span class="ch-res__score">' . self::e( $r['score'] ) . '</span>'
				. '<span class="ch-badge ch-badge--' . $mod . '">' . self::e( $r['outcome'] ) . '</span></div>';
		}
		$ev = '';
		foreach ( $data['events'] as $e ) {
			$ev .= '<div class="ch-evt"><div class="ch-evt__meta"><span class="ch-evt__tag">' . self::e( $e['tag'] ) . '</span>'
				. '<span class="ch-evt__date">' . self::e( $e['date'] ) . '</span></div>'
				. '<h3 class="ch-evt__title">' . self::e( $e['title'] ) . '</h3>'
				. '<p class="ch-evt__detail">' . self::e( $e['detail'] ) . '</p></div>';
		}
		$tabs = '';
		foreach ( array( 'fixtures' => 'Fixtures', 'results' => 'Results', 'events' => 'Events' ) as $key => $label ) {
			$on    = 'fixtures' === $key ? ' ch-tabs__btn--on' : '';
			$tabs .= '<button type="button" class="ch-tabs__btn' . $on . '" data-ch-tabbtn="' . $key . '">' . self::e( $label ) . '</button>';
		}
		return '<section class="ch-sec ch-sec--alt"><div class="ch-wrap">'
			. '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
			. '<h2 class="ch-sec__title">' . self::e( $data['heading'] ) . '</h2>'
			. '<div class="ch-tabs" data-ch-tabs>'
			. '<div class="ch-tabs__bar">' . $tabs . '</div>'
			. '<div class="ch-tabs__panel" data-ch-tab="fixtures"><div class="ch-fx-list">' . $fx . '</div></div>'
			. '<div class="ch-tabs__panel ch-tabs__panel--off" data-ch-tab="results"><div class="ch-res-list">' . $rs . '</div></div>'
			. '<div class="ch-tabs__panel ch-tabs__panel--off" data-ch-tab="events"><div class="ch-evt-grid">' . $ev . '</div></div>'
			. '</div></div>'
			. '<script>(function(){var r=document.querySelector("[data-ch-tabs]");if(!r)return;'
			. 'r.querySelectorAll("[data-ch-tabbtn]").forEach(function(b){b.addEventListener("click",function(){'
			. 'var k=b.getAttribute("data-ch-tabbtn");'
			. 'r.querySelectorAll("[data-ch-tabbtn]").forEach(function(x){x.classList.toggle("ch-tabs__btn--on",x===b)});'
			. 'r.querySelectorAll("[data-ch-tab]").forEach(function(p){p.classList.toggle("ch-tabs__panel--off",p.getAttribute("data-ch-tab")!==k)});'
			. '})})})();</script></section>';
	}
```

*Skin-agnostic note:* the inline `<script>` contains no colours/styles — it toggles classes only, so the `style=`/hex guards still pass (they scan for `style=` attributes and hex literals, neither of which appear).

- [ ] **Step 4: Wire into `home()`** after `membership`:

```php
			if ( $visibility->is_section_visible( 'home', 'activity' ) ) {
				$out .= Blueworx_Clubhouse_Sections::activity_tabs( array(
					'eyebrow'  => 'Club activity',
					'heading'  => "What's happening",
					'fixtures' => array(
						array( 'month' => 'JUL', 'day' => '12', 'competition' => 'Rugby · 1st XV', 'time' => '14:00', 'matchup' => 'ClubHouse vs Riverside RFC' ),
						array( 'month' => 'JUL', 'day' => '13', 'competition' => 'Netball · Div 2', 'time' => '11:00', 'matchup' => 'ClubHouse vs Castlebridge' ),
						array( 'month' => 'JUL', 'day' => '19', 'competition' => 'Hockey · Ladies 1s', 'time' => '15:30', 'matchup' => 'ClubHouse vs Elmwood' ),
					),
					'results'  => array(
						array( 'date' => 'JUL 5', 'home' => 'ClubHouse 1st XI', 'away' => 'Hartfield CC', 'score' => '+34', 'outcome' => 'W' ),
						array( 'date' => 'JUN 28', 'home' => 'ClubHouse 2nd XV', 'away' => 'Dunmore', 'score' => '18–24', 'outcome' => 'L' ),
						array( 'date' => 'JUL 2', 'home' => 'J. Patel', 'away' => 'R. Osei', 'score' => '2–0', 'outcome' => 'W' ),
					),
					'events'   => array(
						array( 'tag' => 'Open day', 'date' => 'Sat 26 Jul', 'title' => 'Club Open Day', 'detail' => '10:00–14:00 · Clubhouse & grounds' ),
						array( 'tag' => 'Junior football', 'date' => '4–8 Aug', 'title' => 'Summer Football Camp', 'detail' => 'Ages 5–12 · book via Events' ),
						array( 'tag' => 'Social', 'date' => 'Fri 12 Sep', 'title' => 'Annual Awards Night', 'detail' => '19:00 · Clubhouse function room' ),
					),
				) );
			}
```

- [ ] **Step 5: Add CSS**:

```css
.ch-sec--alt{background:var(--color-paper)}
.ch-tabs__bar{display:flex;gap:8px;flex-wrap:wrap;margin:26px 0 22px}
.ch-tabs__btn{font-size:13px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;padding:10px 20px;border-radius:999px;border:1px solid var(--color-line);background:transparent;color:var(--color-ink-soft);cursor:pointer}
.ch-tabs__btn--on{background:var(--color-ink);color:var(--color-bg);border-color:var(--color-ink)}
.ch-tabs__panel--off{display:none}
.ch-fx-list,.ch-res-list{background:var(--color-ink);border-radius:var(--radius-lg);padding:14px;display:flex;flex-direction:column;gap:6px}
.ch-fx,.ch-res{display:flex;align-items:center;gap:16px;padding:14px 16px;border-radius:var(--radius-md);color:var(--color-bg)}
.ch-fx:hover,.ch-res:hover{background:color-mix(in oklab,var(--color-bg) 8%,transparent)}
.ch-fx__date{text-align:center;line-height:1;min-width:44px}
.ch-fx__date b{font-family:var(--font-display);font-size:22px;color:var(--color-accent)}
.ch-fx__date span{font-size:11px;letter-spacing:.1em}
.ch-fx__body{display:flex;flex-direction:column;flex:1}
.ch-fx__comp{font-size:12px;color:color-mix(in oklab,var(--color-bg) 70%,transparent)}
.ch-fx__match{font-weight:600}
.ch-fx__time{font-family:var(--font-display);font-weight:700}
.ch-res{gap:14px}
.ch-res__date{min-width:64px;font-size:13px;color:color-mix(in oklab,var(--color-bg) 70%,transparent)}
.ch-res__teams{flex:1;font-weight:500}
.ch-res__score{font-family:var(--font-display);font-weight:700}
.ch-badge{min-width:26px;text-align:center;font-size:12px;font-weight:700;padding:4px 8px;border-radius:999px}
.ch-badge--w{background:var(--color-accent);color:var(--color-accent-ink)}
.ch-badge--l{background:color-mix(in oklab,var(--color-bg) 18%,transparent);color:var(--color-bg)}
.ch-badge--d{background:transparent;border:1px solid color-mix(in oklab,var(--color-bg) 40%,transparent);color:var(--color-bg)}
.ch-evt-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px}
.ch-evt{background:var(--color-bg);border:1px solid var(--color-line);border-radius:var(--radius-lg);padding:22px}
.ch-evt__meta{display:flex;justify-content:space-between;margin-bottom:12px;font-size:12px}
.ch-evt__tag{color:var(--color-accent-deep);font-weight:700;text-transform:uppercase;letter-spacing:.08em}
.ch-evt__date{color:var(--color-ink-soft)}
.ch-evt__title{font-family:var(--font-display);font-weight:800;font-size:22px;margin-bottom:8px}
.ch-evt__detail{font-size:15px;color:var(--color-ink-soft)}
```

- [ ] **Step 6: Run suite → PASS, commit**

```bash
git add includes/render/class-sections.php includes/render/class-page-renderer.php assets/looks/court-side.css tests/php/SectionsTest.php
git commit -m "feat: add tabbed club-activity (fixtures/results/events) to Home"
```

---

### Task 9: News cards

**Files:** Modify `class-sections.php`, `class-page-renderer.php`, `court-side.css`; Test `SectionsTest.php`.

**Interfaces:** Produces `news_cards(array{eyebrow,heading,cards:array<int,array{image,image_alt,tag,date,title}>}): string`.

- [ ] **Step 1: Add test**:

```php
	public function test_news_cards_render_each_post(): void {
		$html = Blueworx_Clubhouse_Sections::news_cards( array(
			'eyebrow' => 'Latest news', 'heading' => 'From the clubhouse',
			'cards'   => array(
				array( 'image' => '', 'image_alt' => '', 'tag' => 'Club news', 'date' => '2 Jul', 'title' => 'Refurbishment complete' ),
				array( 'image' => '', 'image_alt' => '', 'tag' => 'Sections', 'date' => '28 Jun', 'title' => '40 new players' ),
			),
		) );
		$this->assertStringContainsString( 'class="ch-news"', $html );
		$this->assertSame( 2, substr_count( $html, 'ch-news__card' ) );
		$this->assertStringContainsString( 'From the clubhouse', $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}
```

- [ ] **Step 2: Run, verify fail.**

- [ ] **Step 3: Implement**:

```php
	/**
	 * @param array{eyebrow:string,heading:string,
	 *   cards:array<int,array{image:string,image_alt:string,tag:string,date:string,title:string}>} $data
	 */
	public static function news_cards( array $data ): string {
		$cards = '';
		foreach ( $data['cards'] as $c ) {
			$cards .= '<a class="ch-news__card" href="#">'
				. self::media( $c['image'], $c['image_alt'], 'ch-news__media' )
				. '<div class="ch-news__meta"><span class="ch-news__tag">' . self::e( $c['tag'] ) . '</span>'
				. '<span class="ch-news__date">' . self::e( $c['date'] ) . '</span></div>'
				. '<h3 class="ch-news__title">' . self::e( $c['title'] ) . '</h3></a>';
		}
		return '<section class="ch-sec"><div class="ch-wrap">'
			. '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
			. '<h2 class="ch-sec__title">' . self::e( $data['heading'] ) . '</h2>'
			. '<div class="ch-news">' . $cards . '</div></div></section>';
	}
```

- [ ] **Step 4: Wire into `home()`** after `activity`:

```php
			if ( $visibility->is_section_visible( 'home', 'news' ) ) {
				$out .= Blueworx_Clubhouse_Sections::news_cards( array(
					'eyebrow' => 'Latest news',
					'heading' => 'From the clubhouse',
					'cards'   => array(
						array( 'image' => '', 'image_alt' => 'Clubhouse interior', 'tag' => 'Club news', 'date' => '2 Jul', 'title' => 'Clubhouse refurbishment complete' ),
						array( 'image' => '', 'image_alt' => 'Junior footballers', 'tag' => 'Sections', 'date' => '28 Jun', 'title' => 'Junior Football signs 40 new players' ),
						array( 'image' => '', 'image_alt' => 'Volunteers', 'tag' => 'Volunteering', 'date' => '24 Jun', 'title' => 'Volunteers needed for the Open Day' ),
					),
				) );
			}
```

- [ ] **Step 5: Add CSS**:

```css
.ch-news{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:28px}
.ch-news__card{display:block;transition:transform .25s ease}
.ch-news__card:hover{transform:translateY(-4px)}
.ch-news__media{height:200px;border-radius:var(--radius-md);margin-bottom:16px}
.ch-news__meta{display:flex;align-items:center;gap:10px;margin-bottom:8px;font-size:12px}
.ch-news__tag{color:var(--color-accent-deep);font-weight:700;text-transform:uppercase;letter-spacing:.08em}
.ch-news__date{color:var(--color-ink-soft)}
.ch-news__title{font-family:var(--font-display);font-weight:800;font-size:22px;line-height:1.05}
```

- [ ] **Step 6: Run suite → PASS, commit**

```bash
git add includes/render/class-sections.php includes/render/class-page-renderer.php assets/looks/court-side.css tests/php/SectionsTest.php
git commit -m "feat: add news cards to Home"
```

---

### Task 10: Info strip

**Files:** Modify `class-sections.php`, `class-page-renderer.php`, `court-side.css`; Test `SectionsTest.php`.

**Interfaces:** Produces `info_strip(array<int,array{label:string,lines:array<int,string>,link_label:string,link_href:string}>): string`.

- [ ] **Step 1: Add test**:

```php
	public function test_info_strip_renders_columns_and_optional_link(): void {
		$html = Blueworx_Clubhouse_Sections::info_strip( array(
			array( 'label' => 'Location', 'lines' => array( '12 Riverside Lane', 'Marlow' ), 'link_label' => '', 'link_href' => '' ),
			array( 'label' => 'Find us', 'lines' => array(), 'link_label' => 'Open in Maps', 'link_href' => '#' ),
		) );
		$this->assertStringContainsString( 'class="ch-info"', $html );
		$this->assertSame( 2, substr_count( $html, 'ch-info__col' ) );
		$this->assertStringContainsString( '12 Riverside Lane', $html );
		$this->assertStringContainsString( 'Open in Maps', $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}
```

- [ ] **Step 2: Run, verify fail.**

- [ ] **Step 3: Implement**:

```php
	/** @param array<int,array{label:string,lines:array<int,string>,link_label:string,link_href:string}> $cols */
	public static function info_strip( array $cols ): string {
		$out = '';
		foreach ( $cols as $c ) {
			$lines = '';
			foreach ( $c['lines'] as $line ) {
				$lines .= '<span class="ch-info__line">' . self::e( $line ) . '</span>';
			}
			$link = '' !== $c['link_label']
				? '<a class="ch-info__link" href="' . self::e( $c['link_href'] ) . '">' . self::e( $c['link_label'] ) . ' →</a>' : '';
			$out .= '<div class="ch-info__col"><div class="ch-info__label">' . self::e( $c['label'] ) . '</div>'
				. '<div class="ch-info__body">' . $lines . $link . '</div></div>';
		}
		return '<section class="ch-info"><div class="ch-wrap ch-info__in">' . $out . '</div></section>';
	}
```

- [ ] **Step 4: Wire into `home()`** after `news`:

```php
			if ( $visibility->is_section_visible( 'home', 'info' ) ) {
				$out .= Blueworx_Clubhouse_Sections::info_strip( array(
					array( 'label' => 'Location', 'lines' => array( '12 Riverside Lane', 'Marlow, SL7 1AA' ), 'link_label' => '', 'link_href' => '' ),
					array( 'label' => 'Opening hours', 'lines' => array( 'Mon–Sun', '7:00am – 10:00pm' ), 'link_label' => '', 'link_href' => '' ),
					array( 'label' => 'Contact', 'lines' => array( 'hello@clubhouse.example', '01628 000 000' ), 'link_label' => '', 'link_href' => '' ),
					array( 'label' => 'Find us', 'lines' => array(), 'link_label' => 'Open in Maps', 'link_href' => '#' ),
				) );
			}
```

- [ ] **Step 5: Add CSS**:

```css
.ch-info{background:var(--color-ink);color:var(--color-bg);padding:48px 0;margin-top:40px}
.ch-info__in{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:32px}
.ch-info__label{font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.1em;color:var(--color-accent);margin-bottom:12px}
.ch-info__body{display:flex;flex-direction:column;gap:2px;font-size:16px;line-height:1.5}
.ch-info__link{color:var(--color-accent);font-weight:600;font-size:13px;text-transform:uppercase;letter-spacing:.08em;margin-top:6px}
.ch-info__link:hover{color:var(--color-bg)}
```

- [ ] **Step 6: Run suite → PASS, commit**

```bash
git add includes/render/class-sections.php includes/render/class-page-renderer.php assets/looks/court-side.css tests/php/SectionsTest.php
git commit -m "feat: add dark info strip to Home"
```

---

### Task 11: Sponsors grid

**Files:** Modify `class-sections.php`, `class-page-renderer.php`, `court-side.css`; Test `SectionsTest.php`.

**Interfaces:** Produces `sponsors(array{heading,link_label,link_href,names:array<int,string>}): string`.

- [ ] **Step 1: Add test**:

```php
	public function test_sponsors_render_each_tile(): void {
		$html = Blueworx_Clubhouse_Sections::sponsors( array(
			'heading' => 'Our sponsors & partners', 'link_label' => 'Become a sponsor', 'link_href' => '#',
			'names'   => array( 'Sponsor 01', 'Sponsor 02', 'Sponsor 03' ),
		) );
		$this->assertStringContainsString( 'class="ch-sponsors"', $html );
		$this->assertSame( 3, substr_count( $html, 'ch-sponsors__tile' ) );
		$this->assertStringContainsString( 'Become a sponsor', $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}
```

- [ ] **Step 2: Run, verify fail.**

- [ ] **Step 3: Implement**:

```php
	/** @param array{heading:string,link_label:string,link_href:string,names:array<int,string>} $data */
	public static function sponsors( array $data ): string {
		$tiles = '';
		foreach ( $data['names'] as $name ) {
			$tiles .= '<div class="ch-sponsors__tile">' . self::e( $name ) . '</div>';
		}
		return '<section class="ch-sec"><div class="ch-wrap">'
			. '<div class="ch-sec__head"><h2 class="ch-sec__title ch-sec__title--sm">' . self::e( $data['heading'] ) . '</h2>'
			. '<a class="ch-link" href="' . self::e( $data['link_href'] ) . '">' . self::e( $data['link_label'] ) . ' →</a></div>'
			. '<div class="ch-sponsors">' . $tiles . '</div></div></section>';
	}
```

- [ ] **Step 4: Wire into `home()`** after `info`:

```php
			if ( $visibility->is_section_visible( 'home', 'sponsors' ) ) {
				$out .= Blueworx_Clubhouse_Sections::sponsors( array(
					'heading' => 'Our sponsors & partners', 'link_label' => 'Become a sponsor', 'link_href' => '#',
					'names'   => array( 'Sponsor 01', 'Sponsor 02', 'Sponsor 03', 'Sponsor 04', 'Sponsor 05', 'Sponsor 06' ),
				) );
			}
```

- [ ] **Step 5: Add CSS**:

```css
.ch-sec__title--sm{font-size:clamp(28px,3.8vw,38px)}
.ch-link{font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:.08em;color:var(--color-ink);border-bottom:2px solid var(--color-accent);padding-bottom:4px}
.ch-link:hover{color:var(--color-accent-deep)}
.ch-sponsors{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px}
.ch-sponsors__tile{height:88px;background:var(--color-paper);border:1px solid var(--color-line);border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:600;color:var(--color-ink-soft);text-transform:uppercase;letter-spacing:.08em;text-align:center;padding:8px;transition:background .25s ease,color .25s ease}
.ch-sponsors__tile:hover{background:var(--color-ink);color:var(--color-bg)}
```

- [ ] **Step 6: Run suite → PASS, commit**

```bash
git add includes/render/class-sections.php includes/render/class-page-renderer.php assets/looks/court-side.css tests/php/SectionsTest.php
git commit -m "feat: add sponsors grid to Home"
```

---

### Task 12: Upgraded footer (4-column)

**Files:** Modify `class-sections.php` (`footer()`), `class-page-renderer.php`, `court-side.css`; Test `SectionsTest.php`.

**Interfaces:** Produces the extended `footer(array{...})` signature above. The `test_output_is_escaped` test currently calls `footer()` with the old `{club_name,tagline}` shape — update it in Step 1.

- [ ] **Step 1: Update the escape test + add a footer structure test** in `SectionsTest.php`. Replace `test_output_is_escaped` body's `footer()` call and add the new test:

```php
	public function test_output_is_escaped(): void {
		$html = Blueworx_Clubhouse_Sections::footer( array(
			'club_name'  => 'A & B <script>',
			'tagline'    => 'x',
			'socials'    => array(),
			'columns'    => array(),
			'newsletter' => array( 'heading' => 'h', 'lede' => 'l', 'placeholder' => 'p', 'cta' => 'Subscribe' ),
			'legal'      => array(),
		) );
		$this->assertStringContainsString( 'A &amp; B &lt;script&gt;', $html );
		$this->assertStringNotContainsString( '<script>A', $html );
	}

	public function test_footer_renders_columns_socials_and_newsletter(): void {
		$html = Blueworx_Clubhouse_Sections::footer( array(
			'club_name'  => 'ClubHouse', 'tagline' => 'A home ground for every team.',
			'socials'    => array( 'Facebook', 'Instagram' ),
			'columns'    => array(
				array( 'title' => 'Club', 'links' => array( array( 'label' => 'About', 'href' => '?page=about' ) ) ),
			),
			'newsletter' => array( 'heading' => 'Stay in the loop', 'lede' => 'Club news, monthly.', 'placeholder' => 'Your email', 'cta' => 'Subscribe' ),
			'legal'      => array( array( 'label' => 'Privacy', 'href' => '#' ) ),
		) );
		$this->assertStringContainsString( 'class="ch-footer"', $html );
		$this->assertStringContainsString( 'ch-footer__social', $html );
		$this->assertStringContainsString( 'Stay in the loop', $html );
		$this->assertStringContainsString( 'Privacy', $html );
		$this->assertDoesNotMatchRegularExpression( '/#[0-9a-fA-F]{3,6}\b/', $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}
```

- [ ] **Step 2: Run, verify fail** — `vendor/bin/phpunit --filter footer` → FAIL (old `footer()` signature has no socials/columns).

- [ ] **Step 3: Rewrite `footer()`** in `class-sections.php` (social pills use the first letter as an icon-light glyph with an accessible label):

```php
	/**
	 * @param array{club_name:string,tagline:string,socials:array<int,string>,
	 *   columns:array<int,array{title:string,links:array<int,array{label:string,href:string}>}>,
	 *   newsletter:array{heading:string,lede:string,placeholder:string,cta:string},
	 *   legal:array<int,array{label:string,href:string}>} $data
	 */
	public static function footer( array $data ): string {
		$socials = '';
		foreach ( $data['socials'] as $name ) {
			$glyph    = self::e( mb_substr( $name, 0, 1 ) );
			$socials .= '<a class="ch-footer__social" href="#" aria-label="' . self::e( $name ) . '"><span aria-hidden="true">' . $glyph . '</span></a>';
		}
		$cols = '';
		foreach ( $data['columns'] as $col ) {
			$links = '';
			foreach ( $col['links'] as $l ) {
				$links .= '<a class="ch-footer__link" href="' . self::e( $l['href'] ) . '">' . self::e( $l['label'] ) . '</a>';
			}
			$cols .= '<div class="ch-footer__col"><h4 class="ch-footer__h">' . self::e( $col['title'] ) . '</h4>' . $links . '</div>';
		}
		$nl = '<div class="ch-footer__col ch-footer__nl"><h4 class="ch-footer__h">' . self::e( $data['newsletter']['heading'] ) . '</h4>'
			. '<p class="ch-footer__lede">' . self::e( $data['newsletter']['lede'] ) . '</p>'
			. '<form class="ch-footer__form" onsubmit="return false">'
			. '<input class="ch-footer__input" type="email" placeholder="' . self::e( $data['newsletter']['placeholder'] ) . '" aria-label="Email address">'
			. '<button class="ch-btn ch-btn--accent" type="submit">' . self::e( $data['newsletter']['cta'] ) . '</button></form></div>';
		$legal = '';
		foreach ( $data['legal'] as $l ) {
			$legal .= '<a class="ch-footer__legal-link" href="' . self::e( $l['href'] ) . '">' . self::e( $l['label'] ) . '</a>';
		}
		return '<footer class="ch-footer"><div class="ch-wrap">'
			. '<div class="ch-footer__grid">'
			. '<div class="ch-footer__brand-col">'
			. '<a class="ch-brand" href="?page=home"><span class="ch-brand__mark">C</span>' . self::e( $data['club_name'] ) . '</a>'
			. '<p class="ch-footer__tagline">' . self::e( $data['tagline'] ) . '</p>'
			. '<div class="ch-footer__socials">' . $socials . '</div></div>'
			. $cols . $nl . '</div>'
			. '<div class="ch-footer__legal">' . $legal . '</div>'
			. '</div></footer>';
	}
```

- [ ] **Step 4: Update the `home()` footer call** in `class-page-renderer.php`:

```php
			$out .= Blueworx_Clubhouse_Sections::footer( array(
				'club_name'  => $club,
				'tagline'    => 'Nine sports, one club. A home ground for every team, and everyone who follows them.',
				'socials'    => array( 'Facebook', 'Instagram', 'Community', 'Share' ),
				'columns'    => array(
					array( 'title' => 'Club', 'links' => array(
						array( 'label' => 'About', 'href' => '?page=about' ),
						array( 'label' => 'Sports', 'href' => '?page=sports' ),
						array( 'label' => 'Teams', 'href' => '?page=teams' ),
						array( 'label' => 'Events', 'href' => '?page=events' ),
					) ),
					array( 'title' => 'Get involved', 'links' => array(
						array( 'label' => 'Membership', 'href' => '?page=membership' ),
						array( 'label' => 'Calendar', 'href' => '?page=calendar' ),
						array( 'label' => 'Volunteer', 'href' => '?page=contact' ),
						array( 'label' => 'Contact', 'href' => '?page=contact' ),
					) ),
				),
				'newsletter' => array(
					'heading'     => 'Stay in the loop',
					'lede'        => 'Fixtures, results and club news — one email a month.',
					'placeholder' => 'Your email',
					'cta'         => 'Subscribe',
				),
				'legal'      => array(
					array( 'label' => 'Privacy Policy', 'href' => '#' ),
					array( 'label' => 'Terms', 'href' => '#' ),
					array( 'label' => 'Club Rules', 'href' => '#' ),
					array( 'label' => 'Safeguarding', 'href' => '#' ),
				),
			) );
```

- [ ] **Step 5: Replace the footer CSS** in `court-side.css` (the old `.ch-footer`/`.ch-footer__tagline` rules — extend them):

```css
.ch-footer{margin-top:40px;padding:66px 0 40px;border-top:1px solid var(--color-line)}
.ch-footer__grid{display:grid;grid-template-columns:1.4fr 1fr 1fr 1.4fr;gap:40px}
.ch-footer__tagline{color:var(--color-ink-soft);max-width:30ch;margin-top:12px;font-size:15px}
.ch-footer__socials{display:flex;gap:8px;margin-top:16px}
.ch-footer__social{width:36px;height:36px;border-radius:50%;border:1px solid var(--color-line);display:grid;place-items:center;font-weight:700}
.ch-footer__social:hover{background:var(--color-accent);color:var(--color-accent-ink);border-color:var(--color-accent)}
.ch-footer__h{font-size:12px;letter-spacing:.12em;text-transform:uppercase;color:var(--color-ink-soft);margin-bottom:14px;font-weight:600}
.ch-footer__link{display:block;font-size:15px;color:var(--color-ink-soft);margin-bottom:9px}
.ch-footer__link:hover{color:var(--color-ink)}
.ch-footer__lede{font-size:14px;color:var(--color-ink-soft);margin-bottom:14px;max-width:32ch}
.ch-footer__form{display:flex;gap:8px;flex-wrap:wrap}
.ch-footer__input{flex:1 1 160px;background:var(--color-paper);border:1px solid var(--color-line);border-radius:999px;padding:13px 18px;font-family:var(--font-body);font-size:15px;color:var(--color-ink)}
.ch-footer__legal{display:flex;gap:20px;flex-wrap:wrap;margin-top:46px;font-size:13px}
.ch-footer__legal-link{color:var(--color-ink-soft)}
.ch-footer__legal-link:hover{color:var(--color-ink)}
@media(max-width:820px){.ch-footer__grid{grid-template-columns:1fr 1fr}}
@media(max-width:520px){.ch-footer__grid{grid-template-columns:1fr}}
```

- [ ] **Step 6: Run suite → PASS** (existing `test_home_includes_the_shell_sections` still finds `ch-footer`). Commit:

```bash
git add includes/render/class-sections.php includes/render/class-page-renderer.php assets/looks/court-side.css tests/php/SectionsTest.php
git commit -m "feat: upgrade footer to 4-column with socials + newsletter"
```

---

### Task 13: Preview page routing

**Files:** Modify `preview/index.php`; Test `PreviewRenderTest.php`.

**Interfaces:** Adds `blueworx_clubhouse_preview_body(string $page, Blueworx_Clubhouse_Branding $branding, Blueworx_Clubhouse_Visibility $visibility): string` — maps a page slug to a renderer. Only `home` renders a full page this plan; unknown/other slugs fall back to `home` with a notice banner so nav links resolve without error. Plans 2–3 extend the switch.

- [ ] **Step 1: Add routing test** to `PreviewRenderTest.php`:

```php
	public function test_preview_defaults_to_home_and_routes_by_page_param(): void {
		require_once dirname( __DIR__, 2 ) . '/preview/index.php';
		$b   = new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() );
		$vis = new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() );

		$home = blueworx_clubhouse_preview_body( 'home', $b, $vis );
		$this->assertStringContainsString( 'class="ch-hero"', $home );

		// Unknown page falls back to Home rather than erroring.
		$other = blueworx_clubhouse_preview_body( 'about', $b, $vis );
		$this->assertStringContainsString( 'class="ch-nav"', $other );
	}
```

- [ ] **Step 2: Run, verify fail** — `vendor/bin/phpunit --filter test_preview_defaults` → FAIL (function undefined).

- [ ] **Step 3: Add the routing function and use `$_GET['page']`** in `preview/index.php`. Add this function above `blueworx_clubhouse_preview_document()`:

```php
/** Route a page slug to its renderer. Only Home is built this plan; others fall back. */
function blueworx_clubhouse_preview_body(
	string $page,
	Blueworx_Clubhouse_Branding $branding,
	Blueworx_Clubhouse_Visibility $visibility
): string {
	switch ( $page ) {
		case 'home':
		default:
			return Blueworx_Clubhouse_Page_Renderer::home( $branding, $visibility );
	}
}
```

Then, inside `blueworx_clubhouse_preview_document()`, replace the line
`$body = Blueworx_Clubhouse_Page_Renderer::home( $branding, $visibility );` with:

```php
	$page = isset( $_GET['page'] ) && is_string( $_GET['page'] ) ? preg_replace( '/[^a-z]/', '', $_GET['page'] ) : 'home';
	$body = blueworx_clubhouse_preview_body( (string) $page, $branding, $visibility );
```

- [ ] **Step 4: Run suite → PASS.** (Existing `test_preview_builds_a_full_court_side_home_document` still green — default is Home.)

- [ ] **Step 5: Commit**

```bash
git add preview/index.php tests/php/PreviewRenderTest.php
git commit -m "feat: add ?page= routing to the localhost preview"
```

---

### Task 14: Version bump, changelog, manual preview verification

**Files:** Modify the main plugin file (version header + any version constant), `CHANGELOG.md`.

- [ ] **Step 1: Find the current version.** Run:

```bash
grep -RniE "version" clubhouse.php blueworx-labs-clubhouse.php 2>/dev/null | head; grep -RniE "0\.4\.0" . --include=*.php --include=*.md -l | grep -v vendor
```

Expected: locates the plugin header `Version:` line and any `CH_CLUBHOUSE_VERSION`-style constant currently at `0.4.0`.

- [ ] **Step 2: Bump to `0.5.0`** (minor — new feature) in the plugin header and the version constant. Example edit (adjust to the real file/line found in Step 1):

```
 * Version: 0.5.0
```
and
```php
define( 'BLUEWORX_CLUBHOUSE_VERSION', '0.5.0' );
```

- [ ] **Step 3: Update `CHANGELOG.md`** — add a new top entry:

```markdown
## 0.5.0 — 2026-07-10

### Added
- Full Court Side **Home page**: upgraded header (promo banner, dual CTA, active nav)
  and 4-column footer (socials + newsletter), plus quick-access tiles, a reduced-motion-safe
  news ticker, sports card grid, clubhouse image band, membership band + tier grid, tabbed
  club-activity (fixtures/results/events), news cards, dark info strip, and sponsors grid —
  all skin-agnostic `ch-*` renderers styled by the Court Side pack.
- `?page=` routing in the localhost preview so the site is navigable.
```

- [ ] **Step 4: Run the full suite one final time**

Run: `vendor/bin/phpunit`
Expected: PASS, 0 deprecations, 0 warnings (config runs with `failOnWarning`/`failOnRisky`).

- [ ] **Step 5: Manually verify the preview.** Run the server and open Home:

```bash
php -S localhost:8124
```
Open `http://localhost:8124/preview/`. Confirm visually: banner, nav with active "Home", hero + media placeholder + status pill, quick tiles, ticker scrolling (and paused on hover), sports cards, clubhouse band, membership band + 3 tiers (Family highlighted), activity tabs switching Fixtures/Results/Events on click, news cards, dark info strip, sponsors, 4-column footer. Click an accent swatch and confirm the whole page re-themes (accent blocks, badges, dots) with legible `accent-ink`. Click a nav item (e.g. Sports) and confirm it falls back to Home without error.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "chore: bump version to 0.5.0 and update changelog for Home page"
```

---

## Self-review notes (author)

- **Spec coverage:** every Home section in the design doc §4 Home composition maps to a task —
  header (T1), hero+media (T2), quick_tiles (T3), ticker (T4), card_grid/sports (T5), image_band
  (T6), band+tier_grid (T7), activity_tabs (T8), news_cards (T9), info_strip (T10), sponsors (T11),
  footer (T12), preview routing (T13), version/changelog/verify (T14). `stat_strip` already exists.
  The Home CTA band is intentionally omitted (export ends sponsors → footer; `band` is still built
  for the membership moment and reused by later plans).
- **Skin-agnostic guard:** every renderer test asserts no `#hex` and no `style=`. Scrims/gradients,
  marquee animation, tab colours, and badge colours all live in `court-side.css` via `var(--color-*)`
  / `color-mix` — never inline. The tab toggle `<script>` sets classes only.
- **Type consistency:** `media()`, `band` variants (`accent`/`ink`), and every renderer signature
  match between the File-structure interface list, each task's Interfaces block, and the `home()`
  wiring. `tier_grid` `recommended` → `ch-tier--pop`; result `outcome` → `ch-badge--w|l|d`.
- **Deferred (not this plan):** mobile nav drawer (Court Side hides nav under 900px like the
  reference mockup — a hamburger drawer is a later polish); real image assets (slots degrade to a
  tonal placeholder); real form submission; the other 7 pages (Plans 2–3).
```
