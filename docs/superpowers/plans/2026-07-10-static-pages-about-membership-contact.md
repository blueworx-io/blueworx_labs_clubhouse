# Static Pages — About · Membership · Contact Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the **About**, **Membership**, and **Contact** pages of the ClubHouse plugin as skin-agnostic section renderers under the Court Side look, navigable in the localhost preview — completing Plan 2 of the concrete-sections spec's 3-plan decomposition.

**Architecture:** Adds new section renderers (`benefit_grid`, `people_grid`, `timeline`, `list_split`, `step_grid`, `faq`, `contact_form`) as static methods on `Blueworx_Clubhouse_Sections`, reusing the existing `hero`, `tier_grid`, `image_band`, `band`, `header`, `footer`, and `media()` from Plan 1. `Blueworx_Clubhouse_Page_Renderer` gains `about()`, `membership()`, `contact()` composition methods (plus extracted `shell_header()`/`shell_footer()` helpers to DRY the shared nav/footer across pages), and the preview routes `?page=about|membership|contact`. Interactivity is progressive-enhancement only: the FAQ uses native `<details>`/`<summary>` (works with no JS); forms are presentational.

**Tech Stack:** PHP 8.1+ (`declare(strict_types=1)`), PHPUnit 10 (dev-only, no WordPress runtime), CSS custom properties, native `<details>` disclosure. Preview: `php -S localhost:8124` from the plugin root.

## Global Constraints

- **Skin-agnostic markup:** section methods emit only `ch-*` classes. **No** hex colours, **no** `style=` attributes, no font/radius literals, no look slugs. All interpolated text escaped via `Blueworx_Clubhouse_Sections::e()`.
- **Skin-agnostic test guard:** section tests assert `$this->assertNoHexColour( $html )` (the existing private helper in `SectionsTest`, which strips `&#\d+;` numeric entities before matching `/#[0-9a-fA-F]{3,6}\b/`) **and** `$this->assertStringNotContainsString( 'style=', $html )`. Because `assertNoHexColour` ignores numeric entities, demo/test text may contain straight apostrophes freely.
- **Court Side CSS** (`assets/looks/court-side.css`) consumes `var(--color-*)`/`var(--radius-*)` custom properties only; the accent is always `var(--color-accent*)`; the literal `#c6f24e` must never appear there.
- **Icon-light:** no icon font. Accent-dots for list bullets, "→" text arrows, single-letter social pills.
- **Progressive-enhancement only:** the FAQ works fully with JavaScript disabled (native `<details>`). Forms render styled markup with **no** submission handler (`onsubmit="return false"` on the form is allowed — it is not a `style=` attribute).
- **Demo data = ClubHouse:** all copy hardcoded inline in the page methods (as `home()` does).
- **Image slots degrade to a CSS placeholder** when no URL is supplied — pass `''`.
- **PHP conventions:** `<?php`, `declare(strict_types=1);`, `if ( ! defined( 'ABSPATH' ) ) { exit; }` guard (except tests). Classes `final`, prefixed `Blueworx_Clubhouse_`.
- **Versioning:** bump plugin version (minor → 0.6.0) and update `CHANGELOG.md` in the final task. Version lives in `blueworx-labs-clubhouse.php` line 6 header (`* Version:           x.y.z`) and line 24 constant (`define( 'BLUEWORX_LABS_CLUBHOUSE_VERSION', 'x.y.z' );`). Changelog uses Keep-a-Changelog format (`## [x.y.z] - 2026-07-10`).
- **Run tests with:** `vendor/bin/phpunit` from the plugin root. Baseline entering this plan: 106 tests passing, pristine.

---

## File structure

- **Modify:** `includes/render/class-sections.php` — add `benefit_grid`, `people_grid`, `timeline`, `list_split`, `step_grid`, `faq`, `contact_form`; make `hero()`'s media block conditional.
- **Modify:** `includes/render/class-page-renderer.php` — extract `shell_header()`/`shell_footer()`; add `about()`, `membership()`, `contact()`.
- **Modify:** `assets/looks/court-side.css` — style every new `ch-*` class; widen `.ch-tiers` to auto-fit for the 4-tier Membership grid.
- **Modify:** `preview/index.php` — route `about`/`membership`/`contact`.
- **Modify:** `tests/php/SectionsTest.php` — one test per new renderer + the hero-without-media case.
- **Modify:** `tests/php/PageRendererTest.php` — assert each page composes its sections.
- **Modify:** `blueworx-labs-clubhouse.php` + `CHANGELOG.md` — version bump (final task).

New renderer signatures produced by this plan (all `public static` on `Blueworx_Clubhouse_Sections`, all return `string`):

```
benefit_grid(array{eyebrow:string,heading:string,cards:array<int,array{title:string,description:string}>}): string
people_grid(array{eyebrow:string,heading:string,people:array<int,array{name:string,role:string,email:string}>}): string
timeline(array{eyebrow:string,heading:string,milestones:array<int,array{year:string,title:string,desc:string}>}): string
list_split(array{eyebrow:string,heading:string,included:array<int,string>,not_included:array<int,string>,
           policies:array<int,array{title:string,desc:string}>}): string
step_grid(array{eyebrow:string,heading:string,steps:array<int,array{number:string,title:string,description:string}>}): string
faq(array{eyebrow:string,heading:string,items:array<int,array{question:string,answer:string,open:bool}>}): string
contact_form(array{eyebrow:string,heading:string,name_label:string,email_label:string,enquiry_label:string,
             enquiry_options:array<int,string>,message_label:string,submit_label:string,
             info:array{heading:string,address:array<int,string>,email:string,phone:string,socials:array<int,string>}}): string
```

Page_Renderer additions:

```
private static function shell_header(string $club, string $active): string
private static function shell_footer(string $club): string
public static function about(Blueworx_Clubhouse_Branding $branding, Blueworx_Clubhouse_Visibility $visibility): string
public static function membership(Blueworx_Clubhouse_Branding $branding, Blueworx_Clubhouse_Visibility $visibility): string
public static function contact(Blueworx_Clubhouse_Branding $branding, Blueworx_Clubhouse_Visibility $visibility): string
```

---

### Task 1: Extract shared shell helpers (`shell_header` / `shell_footer`)

Refactor so the standard nav header and 4-column footer are defined once and reused by every page. Pure refactor — no behaviour change.

**Files:**
- Modify: `includes/render/class-page-renderer.php`
- Test: existing `tests/php/PageRendererTest.php` (must stay green; no new test needed)

**Interfaces:**
- Produces: `private static function shell_header(string $club, string $active): string` and `private static function shell_footer(string $club): string`.

- [ ] **Step 1: Add the two private helpers** to `Blueworx_Clubhouse_Page_Renderer` (place them above `home()`). Copy the header/footer argument arrays **currently in `home()`** verbatim, parameterising the header's `active`:

```php
	private static function shell_header( string $club, string $active ): string {
		return Blueworx_Clubhouse_Sections::header( array(
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
			'active'      => $active,
			'login'       => 'Log in',
			'join'        => 'Join the Club',
			'join_href'   => '?page=membership',
		) );
	}

	private static function shell_footer( string $club ): string {
		return Blueworx_Clubhouse_Sections::footer( array(
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
	}
```

- [ ] **Step 2: Rewrite `home()`'s header and footer blocks** to call the helpers. Replace the whole `if ( $visibility->is_section_visible( 'home', 'header' ) ) { $out .= Blueworx_Clubhouse_Sections::header( array( ... ) ); }` block with:

```php
			if ( $visibility->is_section_visible( 'home', 'header' ) ) {
				$out .= self::shell_header( $club, '?page=home' );
			}
```

and replace the `if ( $visibility->is_section_visible( 'home', 'footer' ) ) { $out .= Blueworx_Clubhouse_Sections::footer( array( ... ) ); }` block with:

```php
			if ( $visibility->is_section_visible( 'home', 'footer' ) ) {
				$out .= self::shell_footer( $club );
			}
```

- [ ] **Step 3: Run the suite, verify green** (pure refactor — `test_home_includes_the_shell_sections` still finds `ch-nav`/`ch-footer`):

Run: `vendor/bin/phpunit`
Expected: PASS (106 tests).

- [ ] **Step 4: Commit**

```bash
git add includes/render/class-page-renderer.php
git commit -m "refactor: extract shared shell_header/shell_footer helpers"
```

---

### Task 2: Hero media block becomes conditional

So heading-only heroes (Contact) don't render an empty placeholder box.

**Files:**
- Modify: `includes/render/class-sections.php` (`hero()`)
- Test: `tests/php/SectionsTest.php`

**Interfaces:**
- Consumes/Produces: `hero()` — same signature; the `ch-hero__media` block now renders only when `image`, `image_alt`, or `image_caption` is non-empty.

- [ ] **Step 1: Add a failing test** to `SectionsTest.php` (a heading-only hero omits the media block):

```php
	public function test_hero_without_media_when_no_image_or_caption(): void {
		$html = Blueworx_Clubhouse_Sections::hero( array(
			'eyebrow' => 'Contact', 'title_lead' => 'We will point you to ', 'title_highlight' => 'the right person.',
			'lede' => 'Start here.', 'cta_primary' => 'Email us', 'cta_primary_href' => '#',
			'cta_secondary' => 'Call us', 'cta_secondary_href' => '#',
			'image' => '', 'image_alt' => '', 'image_caption' => '',
		) );
		$this->assertStringContainsString( 'class="ch-hero"', $html );
		$this->assertStringNotContainsString( 'ch-hero__media', $html );
	}
```

- [ ] **Step 2: Run, verify fail**

Run: `vendor/bin/phpunit --filter test_hero_without_media`
Expected: FAIL — `hero()` always renders `ch-hero__media`.

- [ ] **Step 3: Make the media block conditional** in `hero()`. Replace the `$media = ...` line with:

```php
		$has_media = '' !== $data['image'] || '' !== $data['image_alt'] || '' !== $data['image_caption'];
		$media     = $has_media
			? '<div class="ch-hero__media">' . self::media( $data['image'], $data['image_alt'], '' ) . $caption . '</div>'
			: '';
```

(Leave the rest of `hero()` unchanged — `$media` is already concatenated after the `ch-hero__sub` block.)

- [ ] **Step 4: Run the suite, verify green** (the existing `test_hero_highlights_accent_and_renders_media` passes `image_caption` so media still renders; Home's hero sets `image_alt` so its media still renders):

Run: `vendor/bin/phpunit`
Expected: PASS (107 tests).

- [ ] **Step 5: Commit**

```bash
git add includes/render/class-sections.php tests/php/SectionsTest.php
git commit -m "feat: render hero media only when an image or caption is present"
```

---

### Task 3: `benefit_grid` renderer

Accent-dot benefit cards. Reused by About (values) and Membership (why-join).

**Files:** Modify `class-sections.php`, `court-side.css`; Test `SectionsTest.php`.

**Interfaces:** Produces `benefit_grid(array{eyebrow,heading,cards:array<int,array{title,description}>}): string`.

- [ ] **Step 1: Add test**:

```php
	public function test_benefit_grid_renders_each_card(): void {
		$html = Blueworx_Clubhouse_Sections::benefit_grid( array(
			'eyebrow' => 'Why join', 'heading' => 'More than a membership',
			'cards'   => array(
				array( 'title' => 'All training included', 'description' => 'Every session, all season.' ),
				array( 'title' => 'Discounted events', 'description' => 'Members save on tournaments.' ),
			),
		) );
		$this->assertStringContainsString( 'class="ch-benefits"', $html );
		$this->assertSame( 2, substr_count( $html, 'ch-benefit"' ) );
		$this->assertStringContainsString( 'All training included', $html );
		$this->assertNoHexColour( $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}
```

- [ ] **Step 2: Run, verify fail** — `vendor/bin/phpunit --filter test_benefit_grid` → FAIL.

- [ ] **Step 3: Implement** in `class-sections.php`:

```php
	/** @param array{eyebrow:string,heading:string,cards:array<int,array{title:string,description:string}>} $data */
	public static function benefit_grid( array $data ): string {
		$cards = '';
		foreach ( $data['cards'] as $c ) {
			$cards .= '<article class="ch-benefit"><span class="ch-benefit__dot" aria-hidden="true"></span>'
				. '<h3 class="ch-benefit__title">' . self::e( $c['title'] ) . '</h3>'
				. '<p class="ch-benefit__desc">' . self::e( $c['description'] ) . '</p></article>';
		}
		return '<section class="ch-sec"><div class="ch-wrap">'
			. '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
			. '<h2 class="ch-sec__title">' . self::e( $data['heading'] ) . '</h2>'
			. '<div class="ch-benefits">' . $cards . '</div></div></section>';
	}
```

- [ ] **Step 4: Add CSS** to `court-side.css`:

```css
.ch-benefits{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px}
.ch-benefit{background:var(--color-paper);border:1px solid var(--color-line);border-radius:var(--radius-lg);padding:28px}
.ch-benefit__dot{display:block;width:22px;height:22px;border-radius:50%;background:var(--color-accent);margin-bottom:16px}
.ch-benefit__title{font-family:var(--font-display);font-weight:800;font-size:21px;margin-bottom:8px}
.ch-benefit__desc{font-size:15px;color:var(--color-ink-soft)}
```

- [ ] **Step 5: Run suite → PASS, commit**

```bash
git add includes/render/class-sections.php assets/looks/court-side.css tests/php/SectionsTest.php
git commit -m "feat: add benefit_grid section renderer"
```

---

### Task 4: `people_grid` renderer

Avatar/portrait cards with role, name, and an optional email link. Reused by About (committee, no email) and Contact (directory, with email).

**Files:** Modify `class-sections.php`, `court-side.css`; Test `SectionsTest.php`.

**Interfaces:** Produces `people_grid(array{eyebrow,heading,people:array<int,array{name,role,email}>}): string`. The email link renders only when `email` is non-empty.

- [ ] **Step 1: Add test**:

```php
	public function test_people_grid_renders_optional_email(): void {
		$html = Blueworx_Clubhouse_Sections::people_grid( array(
			'eyebrow' => 'Who to contact', 'heading' => 'The directory',
			'people'  => array(
				array( 'name' => 'Priya Nair', 'role' => 'Chair', 'email' => '' ),
				array( 'name' => 'Daniel Reed', 'role' => 'Membership', 'email' => 'membership@clubhouse.example' ),
			),
		) );
		$this->assertStringContainsString( 'class="ch-people"', $html );
		$this->assertSame( 2, substr_count( $html, 'ch-person"' ) );
		$this->assertStringContainsString( 'Priya Nair', $html );
		$this->assertStringContainsString( 'mailto:membership@clubhouse.example', $html );
		$this->assertSame( 1, substr_count( $html, 'ch-person__email' ) ); // only the one with an email
		$this->assertNoHexColour( $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}
```

- [ ] **Step 2: Run, verify fail.**

- [ ] **Step 3: Implement**:

```php
	/** @param array{eyebrow:string,heading:string,people:array<int,array{name:string,role:string,email:string}>} $data */
	public static function people_grid( array $data ): string {
		$people = '';
		foreach ( $data['people'] as $p ) {
			$email = '' !== $p['email']
				? '<a class="ch-person__email" href="mailto:' . self::e( $p['email'] ) . '">' . self::e( $p['email'] ) . '</a>' : '';
			$people .= '<article class="ch-person">'
				. self::media( '', self::e( $p['name'] ), 'ch-person__avatar' )
				. '<span class="ch-person__role">' . self::e( $p['role'] ) . '</span>'
				. '<h3 class="ch-person__name">' . self::e( $p['name'] ) . '</h3>' . $email . '</article>';
		}
		return '<section class="ch-sec"><div class="ch-wrap">'
			. '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
			. '<h2 class="ch-sec__title">' . self::e( $data['heading'] ) . '</h2>'
			. '<div class="ch-people">' . $people . '</div></div></section>';
	}
```

Note: `media('', ...)` passes an already-escaped name as the alt; since `media()` escapes again, pass the RAW name instead — change `self::e( $p['name'] )` inside the `media()` call to `$p['name']` (media() escapes it). Corrected line:

```php
				. self::media( '', $p['name'], 'ch-person__avatar' )
```

- [ ] **Step 4: Add CSS**:

```css
.ch-people{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px}
.ch-person{display:flex;flex-direction:column}
.ch-person__avatar{aspect-ratio:1/1;border-radius:var(--radius-lg);margin-bottom:14px}
.ch-person__role{font-size:12px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--color-accent-deep)}
.ch-person__name{font-family:var(--font-display);font-weight:800;font-size:20px;margin:4px 0}
.ch-person__email{font-size:14px;color:var(--color-ink-soft)}
.ch-person__email:hover{color:var(--color-accent-deep)}
```

- [ ] **Step 5: Run suite → PASS, commit**

```bash
git add includes/render/class-sections.php assets/looks/court-side.css tests/php/SectionsTest.php
git commit -m "feat: add people_grid section renderer"
```

---

### Task 5: `timeline` renderer

Milestone rows (year · title · description). About history.

**Files:** Modify `class-sections.php`, `court-side.css`; Test `SectionsTest.php`.

**Interfaces:** Produces `timeline(array{eyebrow,heading,milestones:array<int,array{year,title,desc}>}): string`.

- [ ] **Step 1: Add test**:

```php
	public function test_timeline_renders_each_milestone(): void {
		$html = Blueworx_Clubhouse_Sections::timeline( array(
			'eyebrow' => 'Our story', 'heading' => 'From one pitch to nine sports',
			'milestones' => array(
				array( 'year' => '1974', 'title' => 'One pitch, one team', 'desc' => 'A handful of players lease a field.' ),
				array( 'year' => '2024', 'title' => 'A modern home', 'desc' => 'A full clubhouse refurbishment.' ),
			),
		) );
		$this->assertStringContainsString( 'class="ch-timeline"', $html );
		$this->assertSame( 2, substr_count( $html, 'ch-milestone"' ) );
		$this->assertStringContainsString( '1974', $html );
		$this->assertNoHexColour( $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}
```

- [ ] **Step 2: Run, verify fail.**

- [ ] **Step 3: Implement**:

```php
	/** @param array{eyebrow:string,heading:string,milestones:array<int,array{year:string,title:string,desc:string}>} $data */
	public static function timeline( array $data ): string {
		$rows = '';
		foreach ( $data['milestones'] as $m ) {
			$rows .= '<div class="ch-milestone"><div class="ch-milestone__year">' . self::e( $m['year'] ) . '</div>'
				. '<div class="ch-milestone__body"><h3 class="ch-milestone__title">' . self::e( $m['title'] ) . '</h3>'
				. '<p class="ch-milestone__desc">' . self::e( $m['desc'] ) . '</p></div></div>';
		}
		return '<section class="ch-sec"><div class="ch-wrap">'
			. '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
			. '<h2 class="ch-sec__title">' . self::e( $data['heading'] ) . '</h2>'
			. '<div class="ch-timeline">' . $rows . '</div></div></section>';
	}
```

- [ ] **Step 4: Add CSS**:

```css
.ch-timeline{display:flex;flex-direction:column;border-top:1px solid var(--color-line)}
.ch-milestone{display:grid;grid-template-columns:120px 1fr;gap:24px;padding:26px 0;border-bottom:1px solid var(--color-line)}
.ch-milestone__year{font-family:var(--font-display);font-weight:800;font-size:28px;color:var(--color-accent-deep)}
.ch-milestone__title{font-family:var(--font-display);font-weight:800;font-size:22px;margin-bottom:6px}
.ch-milestone__desc{font-size:15px;color:var(--color-ink-soft);max-width:60ch}
@media(max-width:600px){.ch-milestone{grid-template-columns:1fr;gap:6px}}
```

- [ ] **Step 5: Run suite → PASS, commit**

```bash
git add includes/render/class-sections.php assets/looks/court-side.css tests/php/SectionsTest.php
git commit -m "feat: add timeline section renderer"
```

---

### Task 6: `list_split` renderer

Three columns: what's included (accent checks), what's not (muted), and policy cards. Membership.

**Files:** Modify `class-sections.php`, `court-side.css`; Test `SectionsTest.php`.

**Interfaces:** Produces `list_split(array{eyebrow,heading,included:array<int,string>,not_included:array<int,string>,policies:array<int,array{title,desc}>}): string`.

- [ ] **Step 1: Add test**:

```php
	public function test_list_split_renders_three_columns(): void {
		$html = Blueworx_Clubhouse_Sections::list_split( array(
			'eyebrow' => 'The detail', 'heading' => 'What is included',
			'included'     => array( 'All training', 'Match fees' ),
			'not_included' => array( 'Individual coaching' ),
			'policies'     => array( array( 'title' => 'Free trial', 'desc' => 'Your first session is on us.' ) ),
		) );
		$this->assertStringContainsString( 'class="ch-splits"', $html );
		$this->assertSame( 2, substr_count( $html, 'ch-split__yes' ) );
		$this->assertSame( 1, substr_count( $html, 'ch-split__no' ) );
		$this->assertStringContainsString( 'Free trial', $html );
		$this->assertNoHexColour( $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}
```

- [ ] **Step 2: Run, verify fail.**

- [ ] **Step 3: Implement**:

```php
	/**
	 * @param array{eyebrow:string,heading:string,included:array<int,string>,not_included:array<int,string>,
	 *   policies:array<int,array{title:string,desc:string}>} $data
	 */
	public static function list_split( array $data ): string {
		$yes = '';
		foreach ( $data['included'] as $item ) {
			$yes .= '<li class="ch-split__yes">' . self::e( $item ) . '</li>';
		}
		$no = '';
		foreach ( $data['not_included'] as $item ) {
			$no .= '<li class="ch-split__no">' . self::e( $item ) . '</li>';
		}
		$pol = '';
		foreach ( $data['policies'] as $p ) {
			$pol .= '<div class="ch-policy"><h4 class="ch-policy__title">' . self::e( $p['title'] ) . '</h4>'
				. '<p class="ch-policy__desc">' . self::e( $p['desc'] ) . '</p></div>';
		}
		return '<section class="ch-sec"><div class="ch-wrap">'
			. '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
			. '<h2 class="ch-sec__title">' . self::e( $data['heading'] ) . '</h2>'
			. '<div class="ch-splits">'
			. '<div class="ch-split"><h3 class="ch-split__h">Included</h3><ul class="ch-split__list">' . $yes . '</ul></div>'
			. '<div class="ch-split"><h3 class="ch-split__h">Not included</h3><ul class="ch-split__list">' . $no . '</ul></div>'
			. '<div class="ch-split"><h3 class="ch-split__h">Good to know</h3><div class="ch-policies">' . $pol . '</div></div>'
			. '</div></div></section>';
	}
```

- [ ] **Step 4: Add CSS**:

```css
.ch-splits{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:28px}
.ch-split__h{font-size:12px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--color-ink-soft);margin-bottom:16px}
.ch-split__list{list-style:none;display:grid;gap:11px}
.ch-split__yes,.ch-split__no{font-size:15px;display:flex;gap:10px;align-items:flex-start}
.ch-split__yes::before{content:"";width:16px;height:16px;border-radius:50%;background:var(--color-accent);flex:none;margin-top:3px}
.ch-split__no{color:var(--color-ink-soft)}
.ch-split__no::before{content:"";width:16px;height:2px;background:var(--color-stone,var(--color-line));flex:none;margin-top:10px}
.ch-policies{display:grid;gap:14px}
.ch-policy__title{font-family:var(--font-display);font-weight:800;font-size:17px;margin-bottom:4px}
.ch-policy__desc{font-size:14px;color:var(--color-ink-soft)}
```

- [ ] **Step 5: Run suite → PASS, commit**

```bash
git add includes/render/class-sections.php assets/looks/court-side.css tests/php/SectionsTest.php
git commit -m "feat: add list_split section renderer (included/excluded/policies)"
```

---

### Task 7: `step_grid` renderer

Numbered "how to join" steps. Membership.

**Files:** Modify `class-sections.php`, `court-side.css`; Test `SectionsTest.php`.

**Interfaces:** Produces `step_grid(array{eyebrow,heading,steps:array<int,array{number,title,description}>}): string`.

- [ ] **Step 1: Add test**:

```php
	public function test_step_grid_renders_numbered_steps(): void {
		$html = Blueworx_Clubhouse_Sections::step_grid( array(
			'eyebrow' => 'How to join', 'heading' => 'Four steps to playing',
			'steps'   => array(
				array( 'number' => '01', 'title' => 'Pick your section', 'description' => 'Find where you fit.' ),
				array( 'number' => '02', 'title' => 'Choose a tier', 'description' => 'Adult, family or junior.' ),
			),
		) );
		$this->assertStringContainsString( 'class="ch-steps"', $html );
		$this->assertSame( 2, substr_count( $html, 'ch-step"' ) );
		$this->assertStringContainsString( 'Pick your section', $html );
		$this->assertNoHexColour( $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}
```

- [ ] **Step 2: Run, verify fail.**

- [ ] **Step 3: Implement**:

```php
	/** @param array{eyebrow:string,heading:string,steps:array<int,array{number:string,title:string,description:string}>} $data */
	public static function step_grid( array $data ): string {
		$steps = '';
		foreach ( $data['steps'] as $s ) {
			$steps .= '<article class="ch-step"><span class="ch-step__num">' . self::e( $s['number'] ) . '</span>'
				. '<h3 class="ch-step__title">' . self::e( $s['title'] ) . '</h3>'
				. '<p class="ch-step__desc">' . self::e( $s['description'] ) . '</p></article>';
		}
		return '<section class="ch-sec"><div class="ch-wrap">'
			. '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
			. '<h2 class="ch-sec__title">' . self::e( $data['heading'] ) . '</h2>'
			. '<div class="ch-steps">' . $steps . '</div></div></section>';
	}
```

- [ ] **Step 4: Add CSS**:

```css
.ch-steps{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;counter-reset:none}
.ch-step{background:var(--color-paper);border:1px solid var(--color-line);border-radius:var(--radius-lg);padding:26px}
.ch-step__num{font-family:var(--font-display);font-weight:800;font-size:34px;color:var(--color-accent-deep);display:block;margin-bottom:10px}
.ch-step__title{font-family:var(--font-display);font-weight:800;font-size:19px;margin-bottom:6px}
.ch-step__desc{font-size:14px;color:var(--color-ink-soft)}
```

- [ ] **Step 5: Run suite → PASS, commit**

```bash
git add includes/render/class-sections.php assets/looks/court-side.css tests/php/SectionsTest.php
git commit -m "feat: add step_grid section renderer"
```

---

### Task 8: `faq` renderer (native `<details>`)

Accordion that works with no JS. Membership.

**Files:** Modify `class-sections.php`, `court-side.css`; Test `SectionsTest.php`.

**Interfaces:** Produces `faq(array{eyebrow,heading,items:array<int,array{question,answer,open}>}): string`. `open:true` sets the native `open` attribute (first item expanded by default).

- [ ] **Step 1: Add test**:

```php
	public function test_faq_renders_details_and_marks_open(): void {
		$html = Blueworx_Clubhouse_Sections::faq( array(
			'eyebrow' => 'Questions', 'heading' => 'Frequently asked',
			'items'   => array(
				array( 'question' => 'Do I have to commit?', 'answer' => 'No, join any time.', 'open' => true ),
				array( 'question' => 'Can I try first?', 'answer' => 'Yes, free trial.', 'open' => false ),
			),
		) );
		$this->assertStringContainsString( 'class="ch-faq"', $html );
		$this->assertSame( 2, substr_count( $html, '<details class="ch-faq__item"' ) );
		$this->assertSame( 1, substr_count( $html, '<details class="ch-faq__item" open>' ) );
		$this->assertStringContainsString( 'Do I have to commit?', $html );
		$this->assertNoHexColour( $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}
```

- [ ] **Step 2: Run, verify fail.**

- [ ] **Step 3: Implement**:

```php
	/** @param array{eyebrow:string,heading:string,items:array<int,array{question:string,answer:string,open:bool}>} $data */
	public static function faq( array $data ): string {
		$items = '';
		foreach ( $data['items'] as $it ) {
			$open   = ! empty( $it['open'] ) ? ' open' : '';
			$items .= '<details class="ch-faq__item"' . $open . '>'
				. '<summary class="ch-faq__q">' . self::e( $it['question'] ) . '<span class="ch-faq__mark" aria-hidden="true"></span></summary>'
				. '<p class="ch-faq__a">' . self::e( $it['answer'] ) . '</p></details>';
		}
		return '<section class="ch-sec"><div class="ch-wrap ch-faq-wrap">'
			. '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
			. '<h2 class="ch-sec__title">' . self::e( $data['heading'] ) . '</h2>'
			. '<div class="ch-faq">' . $items . '</div></div></section>';
	}
```

- [ ] **Step 4: Add CSS** (the `<summary>` marker is a CSS +/− via `::before`; hide the native disclosure triangle):

```css
.ch-faq-wrap{max-width:820px}
.ch-faq{border-top:1px solid var(--color-line)}
.ch-faq__item{border-bottom:1px solid var(--color-line)}
.ch-faq__q{list-style:none;cursor:pointer;display:flex;justify-content:space-between;align-items:center;gap:16px;padding:22px 0;font-family:var(--font-display);font-weight:800;font-size:19px}
.ch-faq__q::-webkit-details-marker{display:none}
.ch-faq__mark{position:relative;width:16px;height:16px;flex:none}
.ch-faq__mark::before,.ch-faq__mark::after{content:"";position:absolute;background:var(--color-accent-deep);inset:7px 0 auto 0;height:2px}
.ch-faq__mark::after{transform:rotate(90deg);transition:transform .2s ease}
.ch-faq__item[open] .ch-faq__mark::after{transform:rotate(0)}
.ch-faq__a{padding:0 0 22px;color:var(--color-ink-soft);max-width:64ch}
```

- [ ] **Step 5: Run suite → PASS, commit**

```bash
git add includes/render/class-sections.php assets/looks/court-side.css tests/php/SectionsTest.php
git commit -m "feat: add faq section renderer (native details, works no-JS)"
```

---

### Task 9: `contact_form` renderer (presentational)

Two-column: a styled form (name, email, enquiry `<select>`, message, submit) + an info card (address, email, phone, social pills). No submission handler.

**Files:** Modify `class-sections.php`, `court-side.css`; Test `SectionsTest.php`.

**Interfaces:** Produces `contact_form(array{eyebrow,heading,name_label,email_label,enquiry_label,enquiry_options,message_label,submit_label,info:{heading,address,email,phone,socials}}): string`.

- [ ] **Step 1: Add test**:

```php
	public function test_contact_form_renders_fields_select_and_info(): void {
		$html = Blueworx_Clubhouse_Sections::contact_form( array(
			'eyebrow' => 'Get in touch', 'heading' => 'Send us a message',
			'name_label' => 'Full name', 'email_label' => 'Email',
			'enquiry_label' => 'Enquiry type', 'enquiry_options' => array( 'General', 'Membership' ),
			'message_label' => 'Message', 'submit_label' => 'Send message',
			'info' => array(
				'heading' => 'Find us', 'address' => array( '12 Riverside Lane', 'Marlow' ),
				'email' => 'hello@clubhouse.example', 'phone' => '01628 000 000',
				'socials' => array( 'Facebook', 'Instagram' ),
			),
		) );
		$this->assertStringContainsString( 'class="ch-contact"', $html );
		$this->assertStringContainsString( 'onsubmit="return false"', $html );
		$this->assertSame( 2, substr_count( $html, '<option' ) );
		$this->assertStringContainsString( 'mailto:hello@clubhouse.example', $html );
		$this->assertSame( 2, substr_count( $html, 'ch-contact__social' ) );
		$this->assertNoHexColour( $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}
```

- [ ] **Step 2: Run, verify fail.**

- [ ] **Step 3: Implement**:

```php
	/**
	 * @param array{eyebrow:string,heading:string,name_label:string,email_label:string,enquiry_label:string,
	 *   enquiry_options:array<int,string>,message_label:string,submit_label:string,
	 *   info:array{heading:string,address:array<int,string>,email:string,phone:string,socials:array<int,string>}} $data
	 */
	public static function contact_form( array $data ): string {
		$opts = '';
		foreach ( $data['enquiry_options'] as $o ) {
			$opts .= '<option>' . self::e( $o ) . '</option>';
		}
		$addr = '';
		foreach ( $data['info']['address'] as $line ) {
			$addr .= '<span class="ch-contact__line">' . self::e( $line ) . '</span>';
		}
		$socials = '';
		foreach ( $data['info']['socials'] as $name ) {
			$socials .= '<a class="ch-contact__social" href="#" aria-label="' . self::e( $name ) . '">'
				. '<span aria-hidden="true">' . self::e( mb_substr( $name, 0, 1 ) ) . '</span></a>';
		}
		$form = '<form class="ch-contact__form" onsubmit="return false">'
			. '<label class="ch-field"><span class="ch-field__label">' . self::e( $data['name_label'] ) . '</span>'
			. '<input class="ch-field__input" type="text" name="name"></label>'
			. '<label class="ch-field"><span class="ch-field__label">' . self::e( $data['email_label'] ) . '</span>'
			. '<input class="ch-field__input" type="email" name="email"></label>'
			. '<label class="ch-field"><span class="ch-field__label">' . self::e( $data['enquiry_label'] ) . '</span>'
			. '<select class="ch-field__input" name="enquiry">' . $opts . '</select></label>'
			. '<label class="ch-field"><span class="ch-field__label">' . self::e( $data['message_label'] ) . '</span>'
			. '<textarea class="ch-field__input" name="message" rows="5"></textarea></label>'
			. '<button class="ch-btn ch-btn--accent" type="submit">' . self::e( $data['submit_label'] ) . '</button></form>';
		$info = '<aside class="ch-contact__info"><h3 class="ch-contact__h">' . self::e( $data['info']['heading'] ) . '</h3>'
			. self::media( '', 'Map of ClubHouse', 'ch-contact__map' )
			. '<div class="ch-contact__lines">' . $addr . '</div>'
			. '<a class="ch-contact__link" href="mailto:' . self::e( $data['info']['email'] ) . '">' . self::e( $data['info']['email'] ) . '</a>'
			. '<a class="ch-contact__link" href="tel:' . self::e( $data['info']['phone'] ) . '">' . self::e( $data['info']['phone'] ) . '</a>'
			. '<div class="ch-contact__socials">' . $socials . '</div></aside>';
		return '<section class="ch-sec"><div class="ch-wrap">'
			. '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
			. '<h2 class="ch-sec__title">' . self::e( $data['heading'] ) . '</h2>'
			. '<div class="ch-contact">' . $form . $info . '</div></div></section>';
	}
```

- [ ] **Step 4: Add CSS**:

```css
.ch-contact{display:grid;grid-template-columns:1.4fr 1fr;gap:32px;align-items:start}
.ch-contact__form{display:grid;gap:16px}
.ch-field{display:grid;gap:6px}
.ch-field__label{font-size:13px;font-weight:600;color:var(--color-ink-soft)}
.ch-field__input{font-family:var(--font-body);font-size:15px;color:var(--color-ink);background:var(--color-paper);border:1px solid var(--color-line);border-radius:var(--radius-md);padding:13px 16px}
.ch-field__input:focus{outline:2px solid var(--color-accent-deep);outline-offset:1px}
.ch-contact__info{background:var(--color-ink);color:var(--color-bg);border-radius:var(--radius-lg);padding:28px}
.ch-contact__h{font-size:12px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--color-accent);margin-bottom:16px}
.ch-contact__map{aspect-ratio:4/3;border-radius:var(--radius-md);margin-bottom:18px}
.ch-contact__lines{display:flex;flex-direction:column;gap:2px;margin-bottom:14px}
.ch-contact__link{display:block;color:var(--color-accent);font-size:15px;margin-bottom:6px}
.ch-contact__link:hover{color:var(--color-bg)}
.ch-contact__socials{display:flex;gap:8px;margin-top:14px}
.ch-contact__social{width:36px;height:36px;border-radius:50%;border:1px solid color-mix(in oklab,var(--color-bg) 30%,transparent);display:grid;place-items:center;font-weight:700}
.ch-contact__social:hover{background:var(--color-accent);color:var(--color-accent-ink);border-color:var(--color-accent)}
@media(max-width:760px){.ch-contact{grid-template-columns:1fr}}
```

- [ ] **Step 5: Run suite → PASS, commit**

```bash
git add includes/render/class-sections.php assets/looks/court-side.css tests/php/SectionsTest.php
git commit -m "feat: add presentational contact_form section renderer"
```

---

### Task 10: About page composition + route

**Files:** Modify `class-page-renderer.php`, `preview/index.php`; Test `tests/php/PageRendererTest.php`.

**Interfaces:** Consumes `shell_header`/`shell_footer` (Task 1), `hero`, `timeline`, `benefit_grid`, `people_grid`, `image_band`, `band`. Produces `about(Branding, Visibility): string`.

- [ ] **Step 1: Add a page test** to `PageRendererTest.php`:

```php
	public function test_about_composes_its_sections(): void {
		$vis  = new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() );
		$body = Blueworx_Clubhouse_Page_Renderer::about( $this->branding(), $vis );
		$this->assertStringContainsString( 'class="ch-nav"', $body );
		$this->assertStringContainsString( 'class="ch-timeline"', $body );
		$this->assertStringContainsString( 'class="ch-benefits"', $body );
		$this->assertStringContainsString( 'class="ch-people"', $body );
		$this->assertStringContainsString( 'class="ch-band-img"', $body );
		$this->assertStringContainsString( 'class="ch-footer"', $body );
		$this->assertStringContainsString( 'ch-nav__link--active', $body );
	}
```

- [ ] **Step 2: Run, verify fail** — `vendor/bin/phpunit --filter test_about_composes` → FAIL (method undefined).

- [ ] **Step 3: Implement `about()`** in `class-page-renderer.php` (add after `home()`):

```php
	public static function about(
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility
	): string {
		$club = $branding->get_club_name();
		$out  = self::shell_header( $club, '?page=about' );

		if ( $visibility->is_section_visible( 'about', 'hero' ) ) {
			$out .= Blueworx_Clubhouse_Sections::hero( array(
				'eyebrow'            => 'About the club',
				'title_lead'         => 'Fifty-two years of ',
				'title_highlight'    => 'community sport.',
				'lede'               => 'From one rugby pitch in 1974 to nine sports and twenty-four teams — ClubHouse has always been about more than the game.',
				'cta_primary'        => 'Join the club',
				'cta_primary_href'   => '?page=membership',
				'cta_secondary'      => 'Meet the committee',
				'cta_secondary_href' => '?page=contact',
				'image'              => '',
				'image_alt'          => 'ClubHouse members on the terrace',
				'image_caption'      => '',
			) );
		}
		if ( $visibility->is_section_visible( 'about', 'history' ) ) {
			$out .= Blueworx_Clubhouse_Sections::timeline( array(
				'eyebrow'    => 'Our story',
				'heading'    => 'From one pitch to nine sports',
				'milestones' => array(
					array( 'year' => '1974', 'title' => 'One pitch, one team', 'desc' => 'A handful of rugby players lease a field by the river.' ),
					array( 'year' => '1982', 'title' => 'Cricket joins', 'desc' => 'Summer cricket takes over the square; the first pavilion goes up.' ),
					array( 'year' => '1991', 'title' => 'Juniors take root', 'desc' => 'Minis and colts sections launch across rugby and cricket.' ),
					array( 'year' => '2003', 'title' => 'Courts & clubhouse', 'desc' => 'Four tennis courts and the current clubhouse open.' ),
					array( 'year' => '2015', 'title' => 'Nine sports', 'desc' => 'Hockey, netball and squash complete the multi-sport club.' ),
					array( 'year' => '2024', 'title' => 'A modern home', 'desc' => 'A full clubhouse refurbishment for the next generation.' ),
				),
			) );
		}
		if ( $visibility->is_section_visible( 'about', 'values' ) ) {
			$out .= Blueworx_Clubhouse_Sections::benefit_grid( array(
				'eyebrow' => 'What we stand for',
				'heading' => 'Our values',
				'cards'   => array(
					array( 'title' => 'Everyone plays', 'description' => 'Beginners and county players train side by side, every age welcome.' ),
					array( 'title' => 'Volunteer-run', 'description' => 'Coaches, committee and bar staff give their time so the club thrives.' ),
					array( 'title' => 'Community first', 'description' => 'The clubhouse is a place to belong, on and off the pitch.' ),
					array( 'title' => 'Play for life', 'description' => 'Pathways from minis to vets — a home for the whole journey.' ),
				),
			) );
		}
		if ( $visibility->is_section_visible( 'about', 'committee' ) ) {
			$out .= Blueworx_Clubhouse_Sections::people_grid( array(
				'eyebrow' => 'Who runs the club',
				'heading' => 'The committee',
				'people'  => array(
					array( 'name' => 'Priya Nair', 'role' => 'Chair', 'email' => '' ),
					array( 'name' => 'Tom Ellison', 'role' => 'Treasurer', 'email' => '' ),
					array( 'name' => 'Grace Okafor', 'role' => 'Secretary', 'email' => '' ),
					array( 'name' => 'Daniel Reed', 'role' => 'Membership', 'email' => '' ),
					array( 'name' => 'Aisha Khan', 'role' => 'Safeguarding', 'email' => '' ),
					array( 'name' => 'Mark Bailey', 'role' => 'Grounds', 'email' => '' ),
				),
			) );
		}
		if ( $visibility->is_section_visible( 'about', 'facilities' ) ) {
			$out .= Blueworx_Clubhouse_Sections::image_band( array(
				'eyebrow'   => 'The facilities',
				'heading'   => 'Five pitches, four courts, one clubhouse',
				'image'     => '', 'image_alt' => 'ClubHouse grounds from the air',
				'cta_label' => 'Book a visit', 'cta_href' => '?page=contact',
			) );
		}
		if ( $visibility->is_section_visible( 'about', 'cta' ) ) {
			$out .= Blueworx_Clubhouse_Sections::band( array(
				'variant'   => 'ink',
				'eyebrow'   => 'Get involved',
				'heading'   => 'Want to be part of it?',
				'lede'      => 'Play, volunteer, or just come for the atmosphere.',
				'cta_label' => 'Join the club →',
				'cta_href'  => '?page=membership',
			) );
		}
		$out .= self::shell_footer( $club );
		return $out;
	}
```

- [ ] **Step 4: Route it** in `preview/index.php` — add a case above `case 'home':` in `blueworx_clubhouse_preview_body()`:

```php
		case 'about':
			return Blueworx_Clubhouse_Page_Renderer::about( $branding, $visibility );
```

- [ ] **Step 5: Run suite → PASS, commit**

```bash
git add includes/render/class-page-renderer.php preview/index.php tests/php/PageRendererTest.php
git commit -m "feat: compose the About page and route it in the preview"
```

---

### Task 11: Membership page composition + route

**Files:** Modify `class-page-renderer.php`, `assets/looks/court-side.css`, `preview/index.php`; Test `PageRendererTest.php`.

**Interfaces:** Consumes `shell_header`/`shell_footer`, `hero`, `benefit_grid`, `tier_grid`, `list_split`, `step_grid`, `faq`, `band`. Produces `membership(Branding, Visibility): string`.

- [ ] **Step 1: Add a page test** to `PageRendererTest.php`:

```php
	public function test_membership_composes_its_sections(): void {
		$vis  = new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() );
		$body = Blueworx_Clubhouse_Page_Renderer::membership( $this->branding(), $vis );
		$this->assertStringContainsString( 'class="ch-benefits"', $body );
		$this->assertStringContainsString( 'class="ch-tiers"', $body );
		$this->assertStringContainsString( 'class="ch-splits"', $body );
		$this->assertStringContainsString( 'class="ch-steps"', $body );
		$this->assertStringContainsString( 'class="ch-faq"', $body );
		$this->assertSame( 4, substr_count( $body, 'ch-tier"' ) + substr_count( $body, 'ch-tier ch-tier--pop"' ) );
	}
```

- [ ] **Step 2: Run, verify fail.**

- [ ] **Step 3: Widen `.ch-tiers`** in `court-side.css` so 4 tiers lay out cleanly (keeps Home's 3-tier row identical). Replace the existing `.ch-tiers{...}` rule:

```css
.ch-tiers{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-top:22px}
```

- [ ] **Step 4: Implement `membership()`** in `class-page-renderer.php` (after `about()`):

```php
	public static function membership(
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility
	): string {
		$club = $branding->get_club_name();
		$out  = self::shell_header( $club, '?page=membership' );

		if ( $visibility->is_section_visible( 'membership', 'hero' ) ) {
			$out .= Blueworx_Clubhouse_Sections::hero( array(
				'eyebrow'            => 'Membership',
				'title_lead'         => 'Join in five minutes. ',
				'title_highlight'    => 'Play for years.',
				'lede'               => 'From first-timers to county players, there is a category for you — every membership includes clubhouse access, discounted events and a free trial.',
				'cta_primary'        => 'Register interest',
				'cta_primary_href'   => '?page=contact',
				'cta_secondary'      => 'Ask a question',
				'cta_secondary_href' => '?page=contact',
				'image'              => '',
				'image_alt'          => 'ClubHouse members warming up',
				'image_caption'      => '',
			) );
		}
		if ( $visibility->is_section_visible( 'membership', 'why' ) ) {
			$out .= Blueworx_Clubhouse_Sections::benefit_grid( array(
				'eyebrow' => 'Why join',
				'heading' => 'More than a membership',
				'cards'   => array(
					array( 'title' => 'All training included', 'description' => 'Access every session for your section, all season.' ),
					array( 'title' => 'Discounted events', 'description' => 'Members save on tournaments, socials and camps.' ),
					array( 'title' => 'Clubhouse & socials', 'description' => 'The bar, the terrace, and a calendar of member events.' ),
					array( 'title' => 'Kit discounts', 'description' => 'Save on team kit at our partner suppliers.' ),
				),
			) );
		}
		if ( $visibility->is_section_visible( 'membership', 'tiers' ) ) {
			$out .= Blueworx_Clubhouse_Sections::tier_grid( array(
				array( 'eyebrow' => 'Under 18', 'name' => 'Junior', 'price' => '£12', 'period' => '/mo',
					'features' => array( 'Any junior section', 'Coaching included', 'Holiday camp discounts' ),
					'recommended' => false, 'cta_label' => 'Join', 'cta_href' => '?page=contact' ),
				array( 'eyebrow' => 'Full playing', 'name' => 'Adult', 'price' => '£28', 'period' => '/mo',
					'features' => array( 'Any section, any level', 'League affiliation', 'Clubhouse & socials' ),
					'recommended' => false, 'cta_label' => 'Join', 'cta_href' => '?page=contact' ),
				array( 'eyebrow' => 'Best value', 'name' => 'Family', 'price' => '£45', 'period' => '/mo',
					'features' => array( 'Up to 5 members', 'Any sections', 'Priority event booking' ),
					'recommended' => true, 'cta_label' => 'Join', 'cta_href' => '?page=contact' ),
				array( 'eyebrow' => 'Off the pitch', 'name' => 'Social', 'price' => '£12', 'period' => '/mo',
					'features' => array( 'Full clubhouse access', 'Member events', 'Support your club' ),
					'recommended' => false, 'cta_label' => 'Join', 'cta_href' => '?page=contact' ),
			) );
		}
		if ( $visibility->is_section_visible( 'membership', 'detail' ) ) {
			$out .= Blueworx_Clubhouse_Sections::list_split( array(
				'eyebrow'      => 'The detail',
				'heading'      => 'What is included',
				'included'     => array( "Access to all your section's training", 'League match fees', 'Clubhouse & bar membership', 'Member events & socials' ),
				'not_included' => array( 'Individual coaching (available separately)', 'Tournament entry fees', 'Club kit (discounted, not free)' ),
				'policies'     => array(
					array( 'title' => 'Free trial', 'desc' => 'Your first session is on us — try before you join.' ),
					array( 'title' => 'Juniors', 'desc' => 'Under-18s pay a reduced rate; safeguarding applies to all youth sections.' ),
					array( 'title' => 'Family cap', 'desc' => 'Family membership covers up to five people at one address.' ),
				),
			) );
		}
		if ( $visibility->is_section_visible( 'membership', 'steps' ) ) {
			$out .= Blueworx_Clubhouse_Sections::step_grid( array(
				'eyebrow' => 'How to join',
				'heading' => 'Four steps to playing',
				'steps'   => array(
					array( 'number' => '01', 'title' => 'Pick your section', 'description' => 'Browse sports and find where you fit.' ),
					array( 'number' => '02', 'title' => 'Choose a tier', 'description' => 'Adult, family, junior or social.' ),
					array( 'number' => '03', 'title' => 'Register interest', 'description' => 'Fill in a short form — no payment yet.' ),
					array( 'number' => '04', 'title' => 'Come and play', 'description' => 'We will match you to a coach and a session.' ),
				),
			) );
		}
		if ( $visibility->is_section_visible( 'membership', 'faq' ) ) {
			$out .= Blueworx_Clubhouse_Sections::faq( array(
				'eyebrow' => 'Questions',
				'heading' => 'Frequently asked',
				'items'   => array(
					array( 'question' => 'Do I have to commit for a season?', 'answer' => 'No — you can join any time and pay monthly.', 'open' => true ),
					array( 'question' => 'Can I try before I join?', 'answer' => 'Yes, your first session is a free trial.', 'open' => false ),
					array( 'question' => 'Do you have junior sections?', 'answer' => 'Every sport runs junior pathways from age 5 upward.', 'open' => false ),
					array( 'question' => 'Is there a family rate?', 'answer' => 'Family membership covers up to five people at one address.', 'open' => false ),
					array( 'question' => 'How do I pay?', 'answer' => 'Payment details are arranged once your interest is confirmed.', 'open' => false ),
				),
			) );
		}
		if ( $visibility->is_section_visible( 'membership', 'cta' ) ) {
			$out .= Blueworx_Clubhouse_Sections::band( array(
				'variant'   => 'ink',
				'eyebrow'   => 'Ready?',
				'heading'   => 'Register your interest',
				'lede'      => 'Tell us a little about you and we will be in touch within a few days.',
				'cta_label' => 'Register interest →',
				'cta_href'  => '?page=contact',
			) );
		}
		$out .= self::shell_footer( $club );
		return $out;
	}
```

- [ ] **Step 5: Route it** in `preview/index.php` — add above `case 'home':`:

```php
		case 'membership':
			return Blueworx_Clubhouse_Page_Renderer::membership( $branding, $visibility );
```

- [ ] **Step 6: Run suite → PASS, commit**

```bash
git add includes/render/class-page-renderer.php assets/looks/court-side.css preview/index.php tests/php/PageRendererTest.php
git commit -m "feat: compose the Membership page and route it in the preview"
```

---

### Task 12: Contact page composition + route

**Files:** Modify `class-page-renderer.php`, `preview/index.php`; Test `PageRendererTest.php`.

**Interfaces:** Consumes `shell_header`/`shell_footer`, `hero` (heading-only), `contact_form`, `people_grid`. Produces `contact(Branding, Visibility): string`. Contact ends with the footer directly — **no CTA band** (matches the export).

- [ ] **Step 1: Add a page test** to `PageRendererTest.php`:

```php
	public function test_contact_composes_form_and_directory_without_cta_band(): void {
		$vis  = new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() );
		$body = Blueworx_Clubhouse_Page_Renderer::contact( $this->branding(), $vis );
		$this->assertStringContainsString( 'class="ch-contact"', $body );
		$this->assertStringContainsString( 'class="ch-people"', $body );
		$this->assertStringContainsString( 'class="ch-footer"', $body );
		$this->assertStringNotContainsString( 'ch-band--ink', $body ); // no CTA band on Contact
	}
```

- [ ] **Step 2: Run, verify fail.**

- [ ] **Step 3: Implement `contact()`** in `class-page-renderer.php` (after `membership()`):

```php
	public static function contact(
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility
	): string {
		$club = $branding->get_club_name();
		$out  = self::shell_header( $club, '?page=contact' );

		if ( $visibility->is_section_visible( 'contact', 'hero' ) ) {
			$out .= Blueworx_Clubhouse_Sections::hero( array(
				'eyebrow'            => 'Contact',
				'title_lead'         => 'We will point you to ',
				'title_highlight'    => 'the right person.',
				'lede'               => 'Questions about joining, playing, or hiring the clubhouse? Start here.',
				'cta_primary'        => 'Email the club',
				'cta_primary_href'   => 'mailto:hello@clubhouse.example',
				'cta_secondary'      => 'Call 01628 000 000',
				'cta_secondary_href' => 'tel:01628000000',
				'image'              => '', 'image_alt' => '', 'image_caption' => '',
			) );
		}
		if ( $visibility->is_section_visible( 'contact', 'form' ) ) {
			$out .= Blueworx_Clubhouse_Sections::contact_form( array(
				'eyebrow'         => 'Get in touch',
				'heading'         => 'Send us a message',
				'name_label'      => 'Full name',
				'email_label'     => 'Email',
				'enquiry_label'   => 'Enquiry type',
				'enquiry_options' => array( 'General enquiry', 'Membership', 'Coaching', 'Venue hire', 'Volunteering', 'Something else' ),
				'message_label'   => 'Message',
				'submit_label'    => 'Send message',
				'info'            => array(
					'heading' => 'Find us',
					'address' => array( '12 Riverside Lane', 'Marlow, SL7 1AA' ),
					'email'   => 'hello@clubhouse.example',
					'phone'   => '01628 000 000',
					'socials' => array( 'Facebook', 'Instagram', 'Community', 'Share' ),
				),
			) );
		}
		if ( $visibility->is_section_visible( 'contact', 'directory' ) ) {
			$out .= Blueworx_Clubhouse_Sections::people_grid( array(
				'eyebrow' => 'Who to contact',
				'heading' => 'The directory',
				'people'  => array(
					array( 'name' => 'Daniel Reed', 'role' => 'Membership', 'email' => 'membership@clubhouse.example' ),
					array( 'name' => 'Aisha Khan', 'role' => 'Juniors & safeguarding', 'email' => 'safeguarding@clubhouse.example' ),
					array( 'name' => 'Grace Okafor', 'role' => 'Venue hire', 'email' => 'hire@clubhouse.example' ),
					array( 'name' => 'Tom Ellison', 'role' => 'Sponsorship', 'email' => 'sponsors@clubhouse.example' ),
					array( 'name' => 'Priya Nair', 'role' => 'Press', 'email' => 'press@clubhouse.example' ),
					array( 'name' => 'The club office', 'role' => 'General', 'email' => 'hello@clubhouse.example' ),
				),
			) );
		}
		$out .= self::shell_footer( $club );
		return $out;
	}
```

- [ ] **Step 4: Route it** in `preview/index.php` — add above `case 'home':`:

```php
		case 'contact':
			return Blueworx_Clubhouse_Page_Renderer::contact( $branding, $visibility );
```

- [ ] **Step 5: Run suite → PASS, commit**

```bash
git add includes/render/class-page-renderer.php preview/index.php tests/php/PageRendererTest.php
git commit -m "feat: compose the Contact page and route it in the preview"
```

---

### Task 13: Version bump, changelog, manual verification

**Files:** Modify `blueworx-labs-clubhouse.php`, `CHANGELOG.md`.

- [ ] **Step 1: Bump version to `0.6.0`** in `blueworx-labs-clubhouse.php`:
  - Line 6 header: `* Version:           0.6.0`
  - Line 24 constant: `define( 'BLUEWORX_LABS_CLUBHOUSE_VERSION', '0.6.0' );`

- [ ] **Step 2: Add a changelog entry** directly above `## [0.5.0] - 2026-07-10` in `CHANGELOG.md`:

```markdown
## [0.6.0] - 2026-07-10

### Added

- **About**, **Membership** and **Contact** pages under the Court Side look: new skin-agnostic
  section renderers — benefit grid, people/committee grid, history timeline, included/excluded
  list split, how-to-join steps, a native-`<details>` FAQ (works with no JS), and a presentational
  contact form with an info card. Shared header/footer extracted into `shell_header`/`shell_footer`
  helpers; hero renders its media block only when an image or caption is present. Preview routes
  `?page=about|membership|contact`.
```

- [ ] **Step 3: Run the full suite**

Run: `vendor/bin/phpunit`
Expected: PASS (all green, pristine — ~117 tests).

- [ ] **Step 4: Manually verify the three pages.** Start the server and open each:

```bash
php -S localhost:8124
```
Open `http://localhost:8124/preview/?page=about`, `?page=membership`, `?page=contact`. Confirm: the active nav item is highlighted on each; About shows the timeline, values, committee avatars, facilities band, and CTA band; Membership shows why-join, four pricing tiers (Family highlighted), the included/excluded/policies split, the four steps, and the FAQ (first item open; clicking a question toggles it **with JS off too**); Contact shows a heading-only hero (no empty media box), the form + dark info card, and the directory (each with a mailto link), ending at the footer with no CTA band. Click an accent swatch and confirm all three pages re-theme with legible ink.

- [ ] **Step 5: Commit**

```bash
git add blueworx-labs-clubhouse.php CHANGELOG.md
git commit -m "chore: bump version to 0.6.0 and update changelog for static pages"
```

---

## Self-review notes (author)

- **Spec coverage:** the design doc §4 compositions for About (hero·timeline·benefit_grid·people_grid·image_band·cta_band), Membership (hero·benefit_grid·tier_grid·list_split·step_grid·faq·cta_band), and Contact (hero·contact_form·people_grid·footer, no CTA band) each map to Tasks 10/11/12; every new renderer they need is built first in Tasks 3–9; the shell reuse is Task 1; the heading-only hero for Contact is Task 2.
- **Reuse:** `hero`, `tier_grid`, `image_band`, `band` (ink variant, first used here), `header`, `footer`, `media()` are all reused from Plan 1 unchanged (except the additive hero-media conditional). `benefit_grid` and `people_grid` are each used on two pages.
- **Skin-agnostic guard:** every new renderer test asserts `assertNoHexColour` + no `style=`. Form inputs, `<select>`/`<option>`, `<details>`/`<summary>`, and the accent-dot/step-number/faq-mark decorations all live in `court-side.css` via `var(--color-*)`/`color-mix` — never inline. `onsubmit="return false"` is the only inline handler and is not a `style=` attribute.
- **Type consistency:** renderer signatures match between the File-structure list, each task's Interfaces block, and the page-composition calls in Tasks 10–12. `people_grid` email is optional (empty → no `ch-person__email`); `faq` `open:true` → native `open` attribute; `band` `variant:'ink'` → `ch-band--ink`.
- **No-JS:** FAQ uses native `<details>` (fully functional without JS); forms are presentational. No new JavaScript is added by this plan.
- **Deferred (unchanged from Plan 1):** `role="list"` a11y sweep across grids (now also applies to benefit/people/step grids — fold into the same later sweep); real form submission; the remaining Sports/Teams/Events/Calendar pages (Plan 3); WP integration + CPTs.
