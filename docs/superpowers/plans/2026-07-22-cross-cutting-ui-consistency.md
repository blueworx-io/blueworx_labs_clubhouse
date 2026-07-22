# Cross-cutting UI Consistency Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** One social treatment, one membership-join label, zero dead links, and the missing Sponsors eyebrow — across the whole front end, with a guardrail so none of it silently returns.

**Architecture:** All markup is in `includes/render/class-sections.php`; per-page composition in `includes/render/class-page-renderer.php`; internal links via `Blueworx_Clubhouse_Links::url()`; social URLs via `Blueworx_Clubhouse_Branding`. Changes are localised to those files plus the look stylesheets, a new CTA-label constant, and one new test.

**Tech Stack:** PHP 8.1+, PHPUnit 9, plain CSS with custom properties, WordPress plugin (no build step). Playwright for the existing suites.

## Global Constraints

- **Branch:** work on `cross-cutting-ui-consistency`, already branched from `main` (which includes slices 0). Never commit to `main`.
- **No `href="#"`** may appear in any rendered front-end page after this slice. That is the point of the work and is enforced by the Task 4 guardrail.
- **The membership-join action reads "Join the club"** everywhere, sourced from one constant. Distinct contact intents (Register interest, Ask a question, Book a visit, Enquire about hire, Volunteer) are left exactly as-is — do not touch them.
- **Court Side must not change visually** except where this slice deliberately changes it (letter-circles → pills, sponsors eyebrow/CTA, news cards no longer links). Verify under all three looks.
- **Tier-card behaviour and font pairing are out of scope** (slices 2 and item 3). Do not touch tier-card "Join" labels/destinations.
- **Version:** bump to **0.27.0** in `blueworx-labs-clubhouse.php` (header + constant) and `package.json`, add a `CHANGELOG.md` entry. CI enforces the bump and changelog.
- **Linting:** run `composer lint` once, at the end. Do not loop.
- Escaping: all dynamic output already goes through `self::e()` / `esc_*`. Preserve that — never interpolate an unescaped value into markup.

---

### Task 1: One social treatment — retire the letter-circles

**Files:**
- Modify: `includes/render/class-sections.php` — add `social_links()`, rewrite the social loop in `social()`, `footer()`, `contact_form()`
- Modify: `includes/render/class-page-renderer.php` — footer caller (`:156`), contact caller (`:679`)
- Modify: `assets/looks/court-side.css`, `assets/looks/floodlight.css`, `assets/looks/members-house.css` — remove dead `ch-footer__social` / `ch-contact__social` rules
- Test: `tests/php/SectionsTest.php`

**Interfaces:**
- Produces: `Sections::social_links(array $urls): string` — `$urls` is an ordered map `['Facebook'=>url,'Instagram'=>url,'LinkedIn'=>url]`; renders one `ch-social__link` pill per **non-empty** url, each with its brand icon and `role="listitem"`. Consumed by `social()`, `footer()`, `contact_form()`.

- [ ] **Step 1: Write the failing test**

Add to `tests/php/SectionsTest.php`:

```php
	public function test_social_links_renders_a_pill_per_nonempty_url_only(): void {
		$html = Blueworx_Clubhouse_Sections::social_links( array(
			'Facebook'  => 'https://facebook.com/x',
			'Instagram' => '',
			'LinkedIn'  => 'https://linkedin.com/company/x',
		) );
		$this->assertSame( 2, substr_count( $html, 'ch-social__link' ), 'one pill per non-empty url' );
		$this->assertStringContainsString( 'https://facebook.com/x', $html );
		$this->assertStringContainsString( 'https://linkedin.com/company/x', $html );
		$this->assertStringNotContainsString( 'Instagram', $html, 'empty url renders no pill' );
	}

	public function test_footer_uses_social_pills_not_letter_circles(): void {
		$html = Blueworx_Clubhouse_Sections::footer( $this->footerData() );
		$this->assertStringContainsString( 'ch-social__link', $html );
		$this->assertStringNotContainsString( 'ch-footer__social', $html );
		$this->assertStringNotContainsString( 'href="#"', $html );
	}

	public function test_contact_uses_social_pills_not_letter_circles(): void {
		$html = Blueworx_Clubhouse_Sections::contact_form( $this->contactData() );
		$this->assertStringContainsString( 'ch-social__link', $html );
		$this->assertStringNotContainsString( 'ch-contact__social', $html );
	}
```

Add two small private data providers, `footerData()` and `contactData()`, returning the array shape those methods expect (mirror the payload the renderer passes at `class-page-renderer.php:144-181` for footer and `:672-683` for contact). For social, use the three-URL map; drop the old `socials` five-name array — the new methods will not read it.

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter 'social_links|letter_circles'`
Expected: FAIL — `social_links` undefined, and footer/contact still emit `ch-footer__social` / `ch-contact__social`.

- [ ] **Step 3: Add `social_links()` and refactor `social()`**

Add the helper (place it beside `social()`):

```php
	/**
	 * The site's one social-link treatment: a branded pill per network the club
	 * actually has a URL for. Used by the social band, the footer, and the contact
	 * panel, so all three stay identical and none can carry a dead link.
	 *
	 * @param array<string,string> $urls network name => URL (empty URL = no pill)
	 */
	public static function social_links( array $urls ): string {
		$icons = array(
			'Facebook'  => self::FACEBOOK_ICON,
			'Instagram' => self::INSTAGRAM_ICON,
			'LinkedIn'  => self::LINKEDIN_ICON,
		);
		$out = '';
		foreach ( $urls as $name => $url ) {
			if ( '' === $url ) {
				continue;
			}
			$icon = $icons[ $name ] ?? '';
			$out .= '<a class="ch-social__link" role="listitem" href="' . self::e( $url ) . '" aria-label="Follow us on ' . self::e( $name ) . '">'
				. '<span class="ch-social__icon" aria-hidden="true">' . $icon . '</span>'
				. '<span class="ch-social__label">' . self::e( $name ) . '</span></a>';
		}
		return $out;
	}
```

Rewrite `social()` (`:735-752`) so its link list comes from the helper:

```php
	public static function social( array $data ): string {
		$out = self::social_links( array(
			'Facebook'  => $data['facebook_url'],
			'Instagram' => $data['instagram_url'],
			'LinkedIn'  => $data['linkedin_url'],
		) );
		return '<section class="ch-social"><div class="ch-wrap ch-social__in">'
			. '<div class="ch-social__text"><h2 class="ch-social__title">' . self::e( $data['heading'] ) . '</h2>'
			. '<p class="ch-social__lede">' . self::e( $data['lede'] ) . '</p></div>'
			. '<div class="ch-social__links" role="list">' . $out . '</div>'
			. '</div></section>';
	}
```

For the default branding (all three URLs set) this is byte-identical output to today.

- [ ] **Step 4: Rewrite the footer and contact social blocks**

In `footer()` (`:471-475`), delete the letter-circle loop. Replace the `<div class="ch-footer__socials">' . $socials . '</div>` (at `:498`) with a list wrapper carrying the pills:

```php
			. '<div class="ch-footer__socials ch-social__links" role="list">' . self::social_links( $data['socials'] ) . '</div></div>'
```

Change the `$data['socials']` shape from the five-name string array to the URL map (see Step 5). Update the `footer()` docblock's `socials` type to `array<string,string>`.

In `contact_form()` (`:623-627`), delete the letter-circle loop. Replace `<div class="ch-contact__connect">' . $socials . '</div>` (`:644`) with:

```php
			. '<div class="ch-contact__connect ch-social__links" role="list">' . self::social_links( $data['info']['socials'] ) . '</div></aside>';
```

Update the corresponding docblock/`info.socials` type.

- [ ] **Step 5: Feed the callers real URLs**

In `class-page-renderer.php`, the footer payload (`:156`) currently passes
`'socials' => array( 'Facebook', 'Instagram', 'LinkedIn', 'Community', 'Share' )`.
Replace with the branding URL map:

```php
			'socials' => array(
				'Facebook'  => $branding->get_facebook_url(),
				'Instagram' => $branding->get_instagram_url(),
				'LinkedIn'  => $branding->get_linkedin_url(),
			),
```

Do the same for the contact `info.socials` payload (`:679`). Confirm `$branding` is in scope in both methods (it is — both already read branding). "Community" and "Share" are gone.

- [ ] **Step 6: Remove the dead CSS**

In each of the three look stylesheets, delete the rules whose selectors are `.ch-footer__social` and `.ch-contact__social` (the single-circle styles) — grep each file for those exact tokens. Leave the container rules (`.ch-footer__socials`, `.ch-contact__connect`) — they now lay out the pills; adjust their `gap`/`flex` only if the visual check in Step 8 shows the pills need it. Do not touch `.ch-social__link` (it lives in `base.css`).

- [ ] **Step 7: Run the tests**

Run: `vendor/bin/phpunit`
Expected: PASS (existing + 3 new). If a test that built the old five-name `socials` array now fails, update its fixture to the URL map — that is the intended shape change, not a regression to work around.

- [ ] **Step 8: Visual check under all three looks**

Run: `npm run wp:up` (harness on port 8705), then load `/` and `/contact/` and switch look via demo mode (or append the demo cookie). Confirm: footer and contact show the branded pills, no letter-circles, layout intact under Court Side, Floodlight and Members House. Report exactly what you saw.

- [ ] **Step 9: Commit**

```bash
git add includes/render/class-sections.php includes/render/class-page-renderer.php assets/looks/*.css tests/php/SectionsTest.php
git commit -m "feat: one social treatment sitewide — retire the FILCS letter-circles

The footer and contact panel rendered five hardcoded href=# letter-circles
(two not even networks), while the real branded pills lived only in the
social band. Branding has no data behind the circles. Consolidated on the
pill treatment, driven by the real social URLs; a network with no URL set
now shows no pill rather than a dead one."
```

---

### Task 2: One membership-join label

**Files:**
- Create: `includes/frontend/class-cta.php` (or nearest existing constants location — check `includes/` structure and follow the autoload pattern)
- Modify: `includes/render/class-page-renderer.php` — every membership-join label
- Modify: `blueworx-labs-clubhouse.php` or the bootstrap require list if the new class needs registering (check how classes are loaded)
- Test: `tests/php/` — a new small `CtaLabelsTest.php`

**Interfaces:**
- Produces: `Blueworx_Clubhouse_Cta::JOIN` — string constant `'Join the club'`.

- [ ] **Step 1: Confirm the class-loading pattern**

Read `includes/bootstrap.php` (or wherever classes are required) and one existing small class (e.g. `includes/frontend/class-links.php`) to see the naming, `declare(strict_types=1)`, `ABSPATH` guard, and how the file gets required. The new class must load the same way.

- [ ] **Step 2: Write the failing test**

Create `tests/php/CtaLabelsTest.php`:

```php
<?php
use PHPUnit\Framework\TestCase;

final class CtaLabelsTest extends TestCase {
	public function test_join_label_is_the_single_canonical_string(): void {
		$this->assertSame( 'Join the club', Blueworx_Clubhouse_Cta::JOIN );
	}

	/**
	 * The membership-join sprawl the UX review flagged must be gone from every
	 * rendered page. These strings were the variants; their absence proves the
	 * canonicalisation held. (The positive check — that JOIN still appears — is in
	 * the Task 4 link-hygiene guardrail, which renders every page.)
	 */
	public function test_retired_join_variants_do_not_appear_in_home_or_membership(): void {
		$branding   = new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() );
		$visibility = new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() );
		$collections = new Blueworx_Clubhouse_Demo_Collections();
		foreach ( array( '', 'membership' ) as $slug ) {
			$html = Blueworx_Clubhouse_Page_Map::render( $slug, $branding, $visibility, $collections );
			$this->assertStringNotContainsString( 'Explore membership', $html, "slug '$slug'" );
			$this->assertStringNotContainsString( 'Choose your tier', $html, "slug '$slug'" );
			$this->assertStringNotContainsString( 'Join the Club', $html, "capitalised variant, slug '$slug'" );
		}
	}
}
```

(Confirm the exact constructor signatures for `Branding`/`Visibility`/`Demo_Collections`/`Page_Map::render` from a neighbouring render test such as `PreviewRenderTest.php` and match them.)

- [ ] **Step 3: Run to verify it fails**

Run: `vendor/bin/phpunit --filter CtaLabelsTest`
Expected: FAIL — `Blueworx_Clubhouse_Cta` undefined.

- [ ] **Step 4: Create the constant class**

`includes/frontend/class-cta.php`:

```php
<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Canonical CTA label strings, so the same action reads identically everywhere.
 * The UX review found the membership-join action labelled six different ways;
 * one constant is the single source of truth. Distinct actions that merely share
 * a destination (contact enquiries) are intentionally not collapsed here.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Cta {
	/** Membership-join action, used sitewide. */
	public const JOIN = 'Join the club';
}
```

Require it wherever the sibling classes are required (match Step 1).

- [ ] **Step 5: Canonicalise the labels**

In `class-page-renderer.php`, set every membership-join CTA to `Blueworx_Clubhouse_Cta::JOIN`:
- Header CTA button `:147` — was `'Join the Club'`
- Home hero primary `:229` — was `'Explore membership'`
- Home membership band `:299` — was `'Choose your tier →'` (drop the literal arrow; band CTAs add their own presentation arrow — confirm by reading `band()`; if the arrow is part of the passed label, keep the label arrow-free and let the band add it, or if the band does not add one, this CTA keeps parity with the other band CTAs which read e.g. `'Register interest →'` — match whatever the sibling band CTAs do so the arrow treatment is uniform)
- About hero primary `:409`, about closing band `:470`, login alt `:731`, sports directory `:771` — already `'Join the club'`; repoint to the constant so they cannot drift.

Do **not** change the tier-card "Join" labels (`:304-310`, `:518-527`) — slice 2.

- [ ] **Step 6: Run the tests**

Run: `vendor/bin/phpunit`
Expected: PASS. `CtaLabelsTest` green, nothing else broken.

- [ ] **Step 7: Commit**

```bash
git add includes/frontend/class-cta.php includes/**/*.php tests/php/CtaLabelsTest.php
git commit -m "feat: one label for the membership-join action

The join action was reached by six labels (Join the Club, Explore membership,
Choose your tier, Join the club, ...). Standardised on 'Join the club' from a
single Cta::JOIN constant so it cannot drift again. Distinct contact intents are
left alone; tier-card labels are slice 2."
```

---

### Task 3: Dead-link dispositions

**Files:**
- Modify: `includes/render/class-sections.php` — `sponsors()`, `news_cards()`, `footer()` legal guard, `contact_form()`/home info map link, add `maps_url()`
- Modify: `includes/render/class-page-renderer.php` — sponsors caller (`:371-374`), Open-in-Maps href (`:355`), legal array (`:178-181`), forgot href (`:727`)
- Modify: look stylesheets only if the news-card element change needs it
- Test: `tests/php/SectionsTest.php`

**Interfaces:**
- Produces: `Sections::maps_url(array $lines): string` — pure; returns a Google Maps search URL for the address lines, or `''` if all lines are empty.

- [ ] **Step 1: Write the failing tests**

Add to `tests/php/SectionsTest.php`:

```php
	public function test_maps_url_encodes_the_address(): void {
		$url = Blueworx_Clubhouse_Sections::maps_url( array( '12 Riverside Lane', 'Marlow, SL7 1AA' ) );
		$this->assertStringStartsWith( 'https://www.google.com/maps/search/?api=1&query=', $url );
		$this->assertStringContainsString( '12%20Riverside%20Lane', $url );
		$this->assertStringContainsString( 'Marlow', $url );
	}

	public function test_maps_url_is_empty_for_a_blank_address(): void {
		$this->assertSame( '', Blueworx_Clubhouse_Sections::maps_url( array( '', '' ) ) );
	}

	public function test_sponsors_has_an_eyebrow_and_a_real_cta(): void {
		$html = Blueworx_Clubhouse_Sections::sponsors( array(
			'eyebrow'    => 'Our partners',
			'heading'    => 'Our sponsors & partners',
			'link_label' => 'Become a sponsor',
			'link_href'  => 'https://example.test/contact/',
			'names'      => array( 'Acme' ),
		) );
		$this->assertStringContainsString( 'ch-eyebrow', $html );
		$this->assertStringContainsString( 'ch-btn', $html, 'sponsor CTA is a pill, not a plain link' );
		$this->assertStringNotContainsString( 'href="#"', $html );
	}

	public function test_news_cards_are_not_links(): void {
		$html = Blueworx_Clubhouse_Sections::news_cards( $this->newsData() );
		$this->assertStringNotContainsString( 'href="#"', $html );
		$this->assertStringNotContainsString( '<a class="ch-news__card"', $html );
	}
```

Add a `newsData()` provider mirroring the renderer's news payload.

- [ ] **Step 2: Run to verify they fail**

Run: `vendor/bin/phpunit --filter 'maps_url|sponsors_has|news_cards_are_not'`
Expected: FAIL — `maps_url` undefined; sponsors has no eyebrow/pill; news cards are still `<a href="#">`.

- [ ] **Step 3: Add `maps_url()`**

```php
	/**
	 * Google Maps search URL for a club address, built from the address lines we
	 * already render. Empty when there is no address, so the caller omits the link
	 * rather than emitting a dead one.
	 *
	 * @param array<int,string> $lines address lines
	 */
	public static function maps_url( array $lines ): string {
		$query = trim( implode( ', ', array_filter( array_map( 'trim', $lines ) ) ) );
		if ( '' === $query ) {
			return '';
		}
		return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $query );
	}
```

- [ ] **Step 4: Sponsors — eyebrow + pill + contact**

Rewrite `sponsors()` (`:453-461`) to render an eyebrow above the heading (match the sibling pattern, e.g. `news_cards()` `:392`) and render the CTA as a pill only when `link_href` is non-empty:

```php
	public static function sponsors( array $data ): string {
		$tiles = '';
		foreach ( $data['names'] as $name ) {
			$tiles .= '<div class="ch-sponsors__tile" role="listitem">' . self::e( $name ) . '</div>';
		}
		$cta = '' !== $data['link_href']
			? '<a class="ch-btn ch-btn--ghost" href="' . self::e( $data['link_href'] ) . '">' . self::e( $data['link_label'] ) . '</a>'
			: '';
		return '<section class="ch-sec"><div class="ch-wrap">'
			. '<div class="ch-sec__head">'
			. '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
			. '<h2 class="ch-sec__title ch-sec__title--sm">' . self::e( $data['heading'] ) . '</h2>'
			. $cta . '</div>'
			. '<div class="ch-sponsors" role="list">' . $tiles . '</div></div></section>';
	}
```

(If `ch-sec__head` expects the eyebrow as a sibling of the title in a specific order, match how `image_band`/`band` place it. Confirm the eyebrow renders visually above the title, not inline.)

In the caller (`class-page-renderer.php:371-374`), add `'eyebrow' => 'Our partners'` and set `'link_href' => Blueworx_Clubhouse_Links::url( 'contact' )` (was `'#'`). Keep `'link_label' => 'Become a sponsor'`.

- [ ] **Step 5: Open in Maps**

In the home info-strip payload (`class-page-renderer.php:355`), replace `'link_href' => '#'` with `Blueworx_Clubhouse_Sections::maps_url( <the address lines used at :352> )`. Read `:352` to get the exact address-lines expression and pass the same lines. If `info_strip()` renders the link unconditionally, guard it: when `link_href` is empty, omit the `<a>` (read `info_strip()` and add the guard, mirroring the sponsors CTA guard). Confirm no `href="#"` remains from this row.

- [ ] **Step 6: Legal links — hide when empty**

In `footer()` (`:489-492`), the legal loop already produces an empty string if the array is empty. Guard the wrapper so an empty legal set renders no container: change `:500` to emit `<div class="ch-footer__legal">...</div>` only when `$legal !== ''`. In the caller (`:178-181`), pass `'legal' => array()`.

- [ ] **Step 7: News cards — not links**

In `news_cards()` (`:385-389`), change the wrapping `<a class="ch-news__card" role="listitem" href="#">...</a>` to `<article class="ch-news__card" role="listitem">...</article>`. If the look CSS gives `.ch-news__card` a hover-lift implying clickability, drop that hover rule for the news card in each look stylesheet (grep `ch-news__card`), since it is no longer interactive.

- [ ] **Step 8: Forgot password — hide**

In `auth()` (read around `:669`), render the forgot-password link only when `forgot_href` is non-empty. In the caller (`:727`), pass `'forgot_href' => ''`. Confirm no `href="#"` remains on the login page.

- [ ] **Step 9: Run the tests + visual check**

Run: `vendor/bin/phpunit`
Expected: PASS.

Then with the harness up, load `/`, `/contact/`, `/login/` and confirm: sponsors has an eyebrow and a pill "Become a sponsor" going to contact; "Open in Maps" opens a real maps URL; no legal row; news cards render but are not clickable; no "Forgot password?" link. Report what you saw.

- [ ] **Step 10: Commit**

```bash
git add includes/render/*.php assets/looks/*.css tests/php/SectionsTest.php
git commit -m "fix: wire or hide every dead href=# on the front end

Become a sponsor -> Contact (now a pill); Open in Maps -> a real maps URL
built from the address; legal links and Forgot password hidden (no
destinations exist); Latest News cards render as static articles, not dead
links. Sponsors gains the eyebrow every other titled section has."
```

---

### Task 4: Link-hygiene guardrail, version, verify

**Files:**
- Create: `tests/php/FrontEndLinkHygieneTest.php`
- Modify: `blueworx-labs-clubhouse.php`, `package.json`, `CHANGELOG.md`
- Modify: `docs/testing.md` (correct the now-fixed harness "rough edge" note — see Step 5)

- [ ] **Step 1: Write the guardrail**

Create `tests/php/FrontEndLinkHygieneTest.php`, rendering every page slug and asserting the invariants. Confirm the exact slug list from `Blueworx_Clubhouse_Page_Map::pages()` and the `render()` signature from a neighbouring test.

```php
<?php
use PHPUnit\Framework\TestCase;

/**
 * Enforces the outcomes of the cross-cutting consistency slice on every rendered
 * page, so none can silently return: no dead links, no retired social treatment,
 * and the membership-join label kept canonical.
 */
final class FrontEndLinkHygieneTest extends TestCase {

	/** @return array<int,string> every front-end slug ('' = home) */
	private function slugs(): array {
		return array_map(
			static fn( $p ) => $p['slug'],
			Blueworx_Clubhouse_Page_Map::pages()
		);
	}

	private function render( string $slug ): string {
		return Blueworx_Clubhouse_Page_Map::render(
			$slug,
			new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() ),
			new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() ),
			new Blueworx_Clubhouse_Demo_Collections()
		);
	}

	public function test_no_dead_links_on_any_page(): void {
		foreach ( $this->slugs() as $slug ) {
			$this->assertStringNotContainsString( 'href="#"', $this->render( $slug ), "dead link on '$slug'" );
		}
	}

	public function test_no_retired_social_treatment_on_any_page(): void {
		foreach ( $this->slugs() as $slug ) {
			$html = $this->render( $slug );
			$this->assertStringNotContainsString( 'ch-footer__social', $html, "letter-circle on '$slug'" );
			$this->assertStringNotContainsString( 'ch-contact__social', $html, "letter-circle on '$slug'" );
		}
	}

	public function test_membership_join_label_is_present_and_canonical(): void {
		// Positive: the canonical label survives somewhere (home has it).
		$this->assertStringContainsString( Blueworx_Clubhouse_Cta::JOIN, $this->render( '' ) );
		// Negative: no retired variant anywhere.
		foreach ( $this->slugs() as $slug ) {
			$html = $this->render( $slug );
			$this->assertStringNotContainsString( 'Explore membership', $html, "sprawl on '$slug'" );
			$this->assertStringNotContainsString( 'Choose your tier', $html, "sprawl on '$slug'" );
		}
	}
}
```

- [ ] **Step 2: Run the whole suite**

Run: `vendor/bin/phpunit`
Expected: PASS. If the hygiene test finds a dead link Tasks 1-3 missed, that is the guardrail doing its job — fix the source, do not weaken the test.

- [ ] **Step 3: Run the browser suites**

Run: `npm test` → `27 passed`. Run: `npm run test:wp` → `43 passed`. (No new browser specs; these confirm nothing regressed.)

- [ ] **Step 4: Version + changelog**

Bump to 0.27.0:

```bash
sed -i "s/ \* Version:           0.26.5/ * Version:           0.27.0/" blueworx-labs-clubhouse.php
sed -i "s/define( 'BLUEWORX_LABS_CLUBHOUSE_VERSION', '0.26.5' );/define( 'BLUEWORX_LABS_CLUBHOUSE_VERSION', '0.27.0' );/" blueworx-labs-clubhouse.php
sed -i "s/\"version\": \"0.26.5\",/\"version\": \"0.27.0\",/" package.json
```

Add above `## 0.26.5` in `CHANGELOG.md`, in the club-owner voice of the neighbouring entries:

```markdown
## 0.27.0

- **Cleaner, more consistent calls to action across the site.** Social links now use one style everywhere — the branded Facebook/Instagram/LinkedIn buttons — instead of a second row of plain letter icons that went nowhere. The "join" button now reads the same wherever it appears. "Become a sponsor" and "Open in Maps" now go to the right places, and links that had no destination yet (the footer legal links, the news cards) no longer look clickable when they aren't. The sponsors section now has the same small label above its heading as every other section.
```

- [ ] **Step 5: Correct the harness note in docs/testing.md**

The "Known rough edge" section about a stale `php -S` holding the port was fixed upstream (the harness now reconciles teardown against the port). Replace that section with a one-line note that `up`/`down` clean up any orphaned server on the port automatically, or remove it. Do not leave the obsolete debugging instruction.

- [ ] **Step 6: Full verification + commit**

```bash
vendor/bin/phpunit
npm test
npm run test:wp
composer lint
rm -f ../blueworx-labs-clubhouse.zip && npm run build:zip
```

Expected: PHPUnit PASS; `27 passed`; `43 passed`; PHPCS `0 errors`; zip verification all `ok:` at 0.27.0. Run `composer lint` once; present findings, do not auto-fix in a loop.

```bash
git add -A
git commit -m "test: link-hygiene guardrail; bump to 0.27.0

Renders every page and asserts no href=#, no retired letter-circle classes,
and the canonical join label. Documents the slice and corrects the now-fixed
harness note in docs/testing.md."
```

Do not push or open the PR — the controller does that after the whole-branch review.

---

## What this slice does not do

Slice 2 (page structure): home section order, the announcement/ticker merge, tier-card destination and clickability, About timeline and Facilities placement, Membership pricing above the fold, the no-op filter pills. Slice 3 (style unification): the three hero components and the empty `.ch-media--empty` block. Item 3 (look character): per-look typography including Court Side's body/display pairing.
