# Sports · Teams · Events · Calendar Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the four remaining ClubHouse pages — Sports, Teams, Events, Calendar — as skin-agnostic `ch-*` section renderers, composed page methods honouring `Visibility`, styled by Court Side, and viewable in the localhost preview.

**Architecture:** Extends the established pattern exactly. New static renderers on `Blueworx_Clubhouse_Sections` return semantic HTML with only `ch-*` classes (no colour/font/radius/look literals, all text escaped at the boundary). New `Page_Renderer` methods compose them under per-section `Visibility`, feeding hardcoded ClubHouse demo data (the Collections/CPT plan later swaps the data source behind the unchanged renderers). `assets/looks/court-side.css` grows to style the new hooks, consuming engine custom properties only. `preview/index.php` gains `?page=` routes for the four pages.

**Tech Stack:** PHP 8.3, PHPUnit 11 (no WP runtime — the existing dev-only Composer harness), PHP_CodeSniffer (`composer lint`). Pure server-rendered strings; progressive-enhancement vanilla JS only; no new dependencies.

## Global Constraints

- **Plugin header version → `0.11.0`** (minor bump — new features). Update BOTH the plugin header in `blueworx-labs-clubhouse.php` AND `package.json` (CI checks they match). Copy the value verbatim: `0.11.0`.
- **Changelog updated** alongside the version bump (`CHANGELOG.md`, Keep-a-Changelog format, a `## [0.11.0] - 2026-07-11` section).
- **No new dependency** — nothing added to `composer.json`/`package.json` require blocks.
- **Skin-agnostic renderers:** every renderer returns semantic HTML with only `ch-*` classes. **No hex colours, no inline `style=`, no font/radius/look-slug literals** in renderer output. All interpolated text escaped via `self::e()`.
- **Escaping at the render boundary:** use the existing `private static function e()` and `media()` helpers on `Blueworx_Clubhouse_Sections`; never echo raw input.
- **List semantics:** any grid of repeated peer items exposes `role="list"` on the container and `role="listitem"` on each child (the `assertListSemantics` contract). Navigation (filter bars) uses a `<nav>` landmark, NOT list roles — consistent with how header/footer nav is excluded.
- **Progressive enhancement:** all content renders server-side and is usable with JS disabled. Filter pills are plain links (no JS needed this round — demo data is unfiltered).
- **Demo data, hardcoded** inline in the page methods, ClubHouse brand (adapt any "Marlow Community SC" export copy to ClubHouse).
- **Court Side skin only** this plan. Members' House and Floodlight live on sibling branches; see "Cross-branch follow-up" at the end.

**Branch:** create `sports-teams-events-calendar` off `base-look-theming-design`. Do NOT build on `main`.

**Test command:** `composer test` (PHPUnit). Single test: `vendor/bin/phpunit --filter test_name`. Lint: `composer lint`.

---

## File Structure

- `includes/render/class-sections.php` *(modify)* — add 5 new static renderers: `hero_filter`, `stat_card_grid`, `event_grid`, `event_archive`, `calendar_months`. No new file — these are methods on the existing final class, so `includes/bootstrap.php` is unchanged.
- `includes/render/class-page-renderer.php` *(modify)* — add 4 new page methods: `sports`, `teams`, `events`, `calendar`. Reuse `shell_header`/`shell_footer` and the `band` (ink) renderer for the shared CTA.
- `preview/index.php` *(modify)* — add `case 'sports'|'teams'|'events'|'calendar'` to `blueworx_clubhouse_preview_body()`.
- `assets/looks/court-side.css` *(modify)* — style every new `ch-*` hook, tokens only.
- `tests/php/SectionsTest.php` *(modify)* — one test group per new renderer.
- `tests/php/PageRendererTest.php` *(modify)* — one composition test per new page.
- `tests/php/PreviewRenderTest.php` *(modify)* — routing coverage for the four new pages.
- `tests/php/CourtSideStylesheetTest.php` *(modify)* — assert the new section hooks are styled.
- `blueworx-labs-clubhouse.php`, `package.json`, `CHANGELOG.md` *(modify)* — version bump + changelog (final task).

## Design notes (deliberate deviations from the umbrella spec §3, recorded so the reviewer isn't surprised)

1. **`stat_card_grid` is a NEW renderer, not the existing `card_grid`.** Spec §3 lists Sports/Teams under `card_grid ⟳`, but the shipped `card_grid` signature is fixed (`image/tag/title/subtitle` overlay cards used by Home) and Sports/Teams need `chip + title + description + stat pairs`. Overloading `card_grid` would break its Home callers and tests. A separate `stat_card_grid` is DRY-clean and keeps each renderer single-purpose.
2. **Calendar inlines its fixture/result rows** inside `calendar_months` rather than extracting the standalone `fixture_rows`/`result_rows` the spec names. `activity_tabs` already inlines those shapes; extracting a shared row renderer is a larger refactor best done in the Collections plan when the data model is real. Documented as deferred.
3. **Filter pills are non-functional links this round** (demo data is unfiltered) — matching the spec's "progressive-enhancement, forms presentational" cross-cutting decision. Real filtering is the Collections plan's concern.

---

### Task 1: `hero_filter` renderer

Filter-bar hero for the four collection pages: eyebrow, H1 (lead + accent highlight, same shape as `hero`), lede, and a pill filter bar.

**Files:**
- Modify: `includes/render/class-sections.php` (add method after `hero`, ~line 106)
- Test: `tests/php/SectionsTest.php`

**Interfaces:**
- Produces: `Blueworx_Clubhouse_Sections::hero_filter( array $data ): string` where
  `$data = array{eyebrow:string, title_lead:string, title_highlight:string, lede:string, filter_label:string, filters:array<int,array{label:string, href:string, active:bool}>}`.

- [ ] **Step 1: Write the failing test**

Add to `tests/php/SectionsTest.php` (before the closing brace):

```php
public function test_hero_filter_renders_title_lede_and_filter_pills(): void {
	$html = Blueworx_Clubhouse_Sections::hero_filter( array(
		'eyebrow'         => 'Our sports',
		'title_lead'      => 'Nine sports, ',
		'title_highlight' => 'one club.',
		'lede'            => 'Find your section and get playing.',
		'filter_label'    => 'Filter by sport',
		'filters'         => array(
			array( 'label' => 'All', 'href' => '?page=sports', 'active' => true ),
			array( 'label' => 'Rugby', 'href' => '?page=sports', 'active' => false ),
			array( 'label' => 'Tennis', 'href' => '?page=sports', 'active' => false ),
		),
	) );
	$this->assertStringContainsString( 'class="ch-hero-f"', $html );
	$this->assertStringContainsString( 'class="ch-hero-f__hl"', $html );
	$this->assertStringContainsString( 'Find your section', $html );
	// Filter bar is a nav landmark (not a list), label-driven, active pill flagged.
	$this->assertStringContainsString( '<nav class="ch-filters" aria-label="Filter by sport">', $html );
	$this->assertSame( 3, substr_count( $html, 'class="ch-filter' ) );
	$this->assertSame( 1, substr_count( $html, 'ch-filter--on' ) );
	$this->assertStringContainsString( 'Rugby', $html );
	$this->assertNoHexColour( $html );
	$this->assertStringNotContainsString( 'style=', $html );
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter test_hero_filter_renders_title_lede_and_filter_pills`
Expected: FAIL — `Call to undefined method Blueworx_Clubhouse_Sections::hero_filter()`.

- [ ] **Step 3: Write minimal implementation**

Add to `includes/render/class-sections.php` immediately after the `hero()` method:

```php
	/**
	 * @param array{eyebrow:string,title_lead:string,title_highlight:string,lede:string,
	 *   filter_label:string,filters:array<int,array{label:string,href:string,active:bool}>} $data
	 */
	public static function hero_filter( array $data ): string {
		$pills = '';
		foreach ( $data['filters'] as $f ) {
			$on     = ! empty( $f['active'] ) ? ' ch-filter--on' : '';
			$pills .= '<a class="ch-filter' . $on . '" href="' . self::e( $f['href'] ) . '">' . self::e( $f['label'] ) . '</a>';
		}
		return '<section class="ch-hero-f"><div class="ch-wrap">'
			. '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
			. '<h1 class="ch-hero-f__title">' . self::e( $data['title_lead'] )
			. '<span class="ch-hero-f__hl">' . self::e( $data['title_highlight'] ) . '</span></h1>'
			. '<p class="ch-hero-f__lede">' . self::e( $data['lede'] ) . '</p>'
			. '<nav class="ch-filters" aria-label="' . self::e( $data['filter_label'] ) . '">' . $pills . '</nav>'
			. '</div></section>';
	}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter test_hero_filter_renders_title_lede_and_filter_pills`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add includes/render/class-sections.php tests/php/SectionsTest.php
git commit -m "feat: add hero_filter section renderer"
```

---

### Task 2: `stat_card_grid` renderer

Chip + title + description + stat-pairs card grid for Sports and Teams directories.

**Files:**
- Modify: `includes/render/class-sections.php` (add after `card_grid`)
- Test: `tests/php/SectionsTest.php`

**Interfaces:**
- Produces: `Blueworx_Clubhouse_Sections::stat_card_grid( array $data ): string` where
  `$data = array{eyebrow:string, heading:string, link_label:string, link_href:string, cards:array<int,array{image:string, image_alt:string, chip:string, title:string, description:string, stats:array<int,array{value:string, label:string}>}>}`.
  An empty `link_label` omits the head link (About-style optional CTA).

- [ ] **Step 1: Write the failing test**

```php
public function test_stat_card_grid_renders_cards_with_chip_and_stats(): void {
	$html = Blueworx_Clubhouse_Sections::stat_card_grid( array(
		'eyebrow'    => 'All sections',
		'heading'    => 'Pick your sport.',
		'link_label' => 'Join the club →',
		'link_href'  => '?page=membership',
		'cards'      => array(
			array( 'image' => '', 'image_alt' => 'Rugby', 'chip' => 'Sat', 'title' => 'Rugby',
				'description' => 'Senior, colts and touch rugby.',
				'stats' => array( array( 'value' => '4', 'label' => 'Teams' ), array( 'value' => '120', 'label' => 'Players' ) ) ),
			array( 'image' => '', 'image_alt' => 'Tennis', 'chip' => 'Daily', 'title' => 'Tennis',
				'description' => 'Four courts, coaching for all ages.',
				'stats' => array( array( 'value' => '4', 'label' => 'Courts' ) ) ),
		),
	) );
	$this->assertStringContainsString( 'class="ch-scards"', $html );
	$this->assertSame( 2, substr_count( $html, 'ch-scard"' ) );
	$this->assertStringContainsString( 'ch-scard__chip', $html );
	$this->assertStringContainsString( 'Senior, colts and touch rugby.', $html );
	// 3 stat pairs total across the two cards.
	$this->assertSame( 3, substr_count( $html, 'ch-scard__stat"' ) );
	// The card grid is the only list; stat rows are inline metrics, not list items.
	$this->assertListSemantics( $html, 1, 2 );
	$this->assertStringContainsString( 'Join the club', $html );
	$this->assertNoHexColour( $html );
	$this->assertStringNotContainsString( 'style=', $html );
}

public function test_stat_card_grid_omits_head_link_when_label_empty(): void {
	$html = Blueworx_Clubhouse_Sections::stat_card_grid( array(
		'eyebrow' => 'x', 'heading' => 'y', 'link_label' => '', 'link_href' => '',
		'cards'   => array( array( 'image' => '', 'image_alt' => '', 'chip' => 'c', 'title' => 't', 'description' => 'd', 'stats' => array() ) ),
	) );
	$this->assertStringNotContainsString( 'ch-sec__head', $html );
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter test_stat_card_grid`
Expected: FAIL — undefined method.

- [ ] **Step 3: Write minimal implementation**

Add to `includes/render/class-sections.php` after `card_grid()`:

```php
	/**
	 * @param array{eyebrow:string,heading:string,link_label:string,link_href:string,
	 *   cards:array<int,array{image:string,image_alt:string,chip:string,title:string,description:string,
	 *   stats:array<int,array{value:string,label:string}>}>} $data
	 */
	public static function stat_card_grid( array $data ): string {
		$cards = '';
		foreach ( $data['cards'] as $c ) {
			$stats = '';
			foreach ( $c['stats'] as $s ) {
				$stats .= '<div class="ch-scard__stat"><b class="ch-scard__stat-v">' . self::e( $s['value'] )
					. '</b><span class="ch-scard__stat-l">' . self::e( $s['label'] ) . '</span></div>';
			}
			$cards .= '<article class="ch-scard" role="listitem">'
				. self::media( $c['image'], $c['image_alt'], 'ch-scard__media' )
				. '<span class="ch-scard__chip">' . self::e( $c['chip'] ) . '</span>'
				. '<div class="ch-scard__body">'
				. '<h3 class="ch-scard__title">' . self::e( $c['title'] ) . '</h3>'
				. '<p class="ch-scard__desc">' . self::e( $c['description'] ) . '</p>'
				. '<div class="ch-scard__stats">' . $stats . '</div></div></article>';
		}
		$head = '';
		if ( '' !== $data['link_label'] ) {
			$head = '<div class="ch-sec__head"><div>'
				. '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
				. '<h2 class="ch-sec__title">' . self::e( $data['heading'] ) . '</h2></div>'
				. '<a class="ch-btn ch-btn--ghost" href="' . self::e( $data['link_href'] ) . '">' . self::e( $data['link_label'] ) . '</a></div>';
		} else {
			$head = '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
				. '<h2 class="ch-sec__title">' . self::e( $data['heading'] ) . '</h2>';
		}
		return '<section class="ch-sec"><div class="ch-wrap">'
			. $head
			. '<div class="ch-scards" role="list">' . $cards . '</div></div></section>';
	}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter test_stat_card_grid`
Expected: PASS (both tests).

- [ ] **Step 5: Commit**

```bash
git add includes/render/class-sections.php tests/php/SectionsTest.php
git commit -m "feat: add stat_card_grid section renderer"
```

---

### Task 3: `event_grid` + `event_archive` renderers

Upcoming-event cards and a past-event archive list for the Events page.

**Files:**
- Modify: `includes/render/class-sections.php`
- Test: `tests/php/SectionsTest.php`

**Interfaces:**
- Produces: `Blueworx_Clubhouse_Sections::event_grid( array $data ): string` where
  `$data = array{eyebrow:string, heading:string, cards:array<int,array{tag:string, date:string, title:string, detail:string, cta_label:string, cta_href:string}>}`. Empty `cta_label` omits the card CTA.
- Produces: `Blueworx_Clubhouse_Sections::event_archive( array $data ): string` where
  `$data = array{heading:string, rows:array<int,array{date:string, tag:string, title:string}>}`.

- [ ] **Step 1: Write the failing test**

```php
public function test_event_grid_renders_upcoming_cards_with_optional_cta(): void {
	$html = Blueworx_Clubhouse_Sections::event_grid( array(
		'eyebrow' => 'Upcoming', 'heading' => 'What is on',
		'cards'   => array(
			array( 'tag' => 'Open day', 'date' => 'Sat 26 Jul', 'title' => 'Club Open Day',
				'detail' => '10:00–14:00 · Clubhouse & grounds', 'cta_label' => 'Book a place', 'cta_href' => '#' ),
			array( 'tag' => 'Social', 'date' => 'Fri 12 Sep', 'title' => 'Annual Awards Night',
				'detail' => '19:00 · Function room', 'cta_label' => '', 'cta_href' => '' ),
		),
	) );
	$this->assertStringContainsString( 'class="ch-events"', $html );
	$this->assertSame( 2, substr_count( $html, 'ch-event"' ) );
	$this->assertListSemantics( $html, 1, 2 );
	$this->assertStringContainsString( 'Club Open Day', $html );
	// Only the first card has a CTA.
	$this->assertSame( 1, substr_count( $html, 'ch-event__cta' ) );
	$this->assertNoHexColour( $html );
	$this->assertStringNotContainsString( 'style=', $html );
}

public function test_event_archive_renders_past_rows(): void {
	$html = Blueworx_Clubhouse_Sections::event_archive( array(
		'heading' => 'Past events',
		'rows'    => array(
			array( 'date' => 'Jun 2026', 'tag' => 'Social', 'title' => 'Summer BBQ' ),
			array( 'date' => 'May 2026', 'tag' => 'Tournament', 'title' => 'Spring Sevens' ),
			array( 'date' => 'Apr 2026', 'tag' => 'Club', 'title' => 'AGM' ),
		),
	) );
	$this->assertStringContainsString( 'class="ch-archive"', $html );
	$this->assertSame( 3, substr_count( $html, 'ch-archive__row' ) );
	$this->assertListSemantics( $html, 1, 3 );
	$this->assertStringContainsString( 'Spring Sevens', $html );
	$this->assertNoHexColour( $html );
	$this->assertStringNotContainsString( 'style=', $html );
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter "test_event_grid_renders_upcoming_cards_with_optional_cta|test_event_archive_renders_past_rows"`
Expected: FAIL — undefined methods.

- [ ] **Step 3: Write minimal implementation**

Add to `includes/render/class-sections.php` (group the two together, e.g. after `news_cards`):

```php
	/**
	 * @param array{eyebrow:string,heading:string,
	 *   cards:array<int,array{tag:string,date:string,title:string,detail:string,cta_label:string,cta_href:string}>} $data
	 */
	public static function event_grid( array $data ): string {
		$cards = '';
		foreach ( $data['cards'] as $c ) {
			$cta = '' !== $c['cta_label']
				? '<a class="ch-btn ch-btn--ghost ch-event__cta" href="' . self::e( $c['cta_href'] ) . '">' . self::e( $c['cta_label'] ) . '</a>'
				: '';
			$cards .= '<article class="ch-event" role="listitem">'
				. '<div class="ch-event__meta"><span class="ch-event__tag">' . self::e( $c['tag'] ) . '</span>'
				. '<span class="ch-event__date">' . self::e( $c['date'] ) . '</span></div>'
				. '<h3 class="ch-event__title">' . self::e( $c['title'] ) . '</h3>'
				. '<p class="ch-event__detail">' . self::e( $c['detail'] ) . '</p>'
				. $cta . '</article>';
		}
		return '<section class="ch-sec"><div class="ch-wrap">'
			. '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
			. '<h2 class="ch-sec__title">' . self::e( $data['heading'] ) . '</h2>'
			. '<div class="ch-events" role="list">' . $cards . '</div></div></section>';
	}

	/**
	 * @param array{heading:string,rows:array<int,array{date:string,tag:string,title:string}>} $data
	 */
	public static function event_archive( array $data ): string {
		$rows = '';
		foreach ( $data['rows'] as $r ) {
			$rows .= '<div class="ch-archive__row" role="listitem">'
				. '<span class="ch-archive__date">' . self::e( $r['date'] ) . '</span>'
				. '<span class="ch-archive__tag">' . self::e( $r['tag'] ) . '</span>'
				. '<span class="ch-archive__title">' . self::e( $r['title'] ) . '</span></div>';
		}
		return '<section class="ch-sec ch-sec--alt"><div class="ch-wrap">'
			. '<h2 class="ch-sec__title ch-sec__title--sm">' . self::e( $data['heading'] ) . '</h2>'
			. '<div class="ch-archive" role="list">' . $rows . '</div></div></section>';
	}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter "test_event_grid_renders_upcoming_cards_with_optional_cta|test_event_archive_renders_past_rows"`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add includes/render/class-sections.php tests/php/SectionsTest.php
git commit -m "feat: add event_grid and event_archive section renderers"
```

---

### Task 4: `calendar_months` renderer

Month-grouped fixtures/results. Each row's `outcome` drives a status badge (reusing the existing `ch-badge--w/l/d` classes from `activity_tabs`); an empty outcome marks an upcoming fixture.

**Files:**
- Modify: `includes/render/class-sections.php`
- Test: `tests/php/SectionsTest.php`

**Interfaces:**
- Produces: `Blueworx_Clubhouse_Sections::calendar_months( array $data ): string` where
  `$data = array{eyebrow:string, heading:string, months:array<int,array{label:string, rows:array<int,array{date:string, competition:string, matchup:string, detail:string, outcome:string}>}>}`.
  `outcome`: `''` = upcoming (renders a "ch-cal__soon" tag); `'W'|'D'|'L'` = result (renders `ch-badge--w|d|l`, unknown non-empty falls back to `d`, matching `activity_tabs`).

- [ ] **Step 1: Write the failing test**

```php
public function test_calendar_months_groups_rows_and_badges_outcomes(): void {
	$html = Blueworx_Clubhouse_Sections::calendar_months( array(
		'eyebrow' => 'Season', 'heading' => 'Fixtures & results',
		'months'  => array(
			array( 'label' => 'July', 'rows' => array(
				array( 'date' => 'Sat 12', 'competition' => 'Rugby · 1st XV', 'matchup' => 'ClubHouse vs Riverside', 'detail' => 'Home · 14:00', 'outcome' => '' ),
				array( 'date' => 'Sat 5', 'competition' => 'Cricket · 1st XI', 'matchup' => 'ClubHouse vs Hartfield', 'detail' => 'Won by 34', 'outcome' => 'W' ),
			) ),
			array( 'label' => 'June', 'rows' => array(
				array( 'date' => 'Sat 28', 'competition' => 'Rugby · 2nd XV', 'matchup' => 'ClubHouse vs Dunmore', 'detail' => 'Lost 18–24', 'outcome' => 'L' ),
			) ),
		),
	) );
	$this->assertStringContainsString( 'class="ch-cal"', $html );
	$this->assertSame( 2, substr_count( $html, 'ch-cal__month' ) );
	$this->assertStringContainsString( '>July<', $html );
	$this->assertStringContainsString( '>June<', $html );
	// Upcoming row → soon tag; result rows → W/L badges.
	$this->assertSame( 1, substr_count( $html, 'ch-cal__soon' ) );
	$this->assertStringContainsString( 'ch-badge--w', $html );
	$this->assertStringContainsString( 'ch-badge--l', $html );
	// One list per month; 3 rows total (2 + 1).
	$this->assertListSemantics( $html, 2, 3 );
	$this->assertNoHexColour( $html );
	$this->assertStringNotContainsString( 'style=', $html );
}

public function test_calendar_unknown_outcome_falls_back_to_draw_badge(): void {
	$html = Blueworx_Clubhouse_Sections::calendar_months( array(
		'eyebrow' => 'x', 'heading' => 'y',
		'months'  => array( array( 'label' => 'Aug', 'rows' => array(
			array( 'date' => 'Sat 1', 'competition' => 'c', 'matchup' => 'm', 'detail' => 'd', 'outcome' => 'X' ),
		) ) ),
	) );
	$this->assertStringContainsString( 'ch-badge--d', $html );
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter test_calendar`
Expected: FAIL — undefined method.

- [ ] **Step 3: Write minimal implementation**

Add to `includes/render/class-sections.php`:

```php
	/**
	 * @param array{eyebrow:string,heading:string,
	 *   months:array<int,array{label:string,rows:array<int,array{date:string,competition:string,
	 *   matchup:string,detail:string,outcome:string}>}>} $data
	 */
	public static function calendar_months( array $data ): string {
		$months = '';
		foreach ( $data['months'] as $m ) {
			$rows = '';
			foreach ( $m['rows'] as $r ) {
				if ( '' === $r['outcome'] ) {
					$status = '<span class="ch-cal__soon">Upcoming</span>';
				} else {
					$o      = strtolower( $r['outcome'] );
					$mod    = in_array( $o, array( 'w', 'l', 'd' ), true ) ? $o : 'd';
					$status = '<span class="ch-badge ch-badge--' . $mod . '">' . self::e( $r['outcome'] ) . '</span>';
				}
				$rows .= '<div class="ch-cal__row" role="listitem">'
					. '<span class="ch-cal__date">' . self::e( $r['date'] ) . '</span>'
					. '<div class="ch-cal__body"><span class="ch-cal__comp">' . self::e( $r['competition'] ) . '</span>'
					. '<span class="ch-cal__match">' . self::e( $r['matchup'] ) . '</span></div>'
					. '<span class="ch-cal__detail">' . self::e( $r['detail'] ) . '</span>'
					. $status . '</div>';
			}
			$months .= '<div class="ch-cal__month"><h3 class="ch-cal__mlabel">' . self::e( $m['label'] ) . '</h3>'
				. '<div class="ch-cal__rows" role="list">' . $rows . '</div></div>';
		}
		return '<section class="ch-sec"><div class="ch-wrap ch-cal">'
			. '<span class="ch-eyebrow">' . self::e( $data['eyebrow'] ) . '</span>'
			. '<h2 class="ch-sec__title">' . self::e( $data['heading'] ) . '</h2>'
			. $months . '</div></section>';
	}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter test_calendar`
Expected: PASS (both tests).

- [ ] **Step 5: Commit**

```bash
git add includes/render/class-sections.php tests/php/SectionsTest.php
git commit -m "feat: add calendar_months section renderer"
```

---

### Task 5: `sports()` + `teams()` page methods

Compose Sports and Teams under `Visibility`, feeding ClubHouse demo data. Both reuse `shell_header`/`shell_footer` and the ink `band` for the shared CTA.

**Files:**
- Modify: `includes/render/class-page-renderer.php` (add after `login()`, before the closing brace)
- Test: `tests/php/PageRendererTest.php`

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Sections::hero_filter`, `::stat_card_grid`, `::band` (Tasks 1, 2, existing).
- Produces: `Blueworx_Clubhouse_Page_Renderer::sports( Branding $branding, Visibility $visibility ): string`, `::teams( Branding $branding, Visibility $visibility ): string`. Section keys — Sports: `hero`, `directory`, `cta`; Teams: `hero`, `directory`, `cta`.

- [ ] **Step 1: Write the failing test**

Add to `tests/php/PageRendererTest.php`:

```php
public function test_sports_composes_filter_hero_and_stat_cards(): void {
	$vis  = new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() );
	$body = Blueworx_Clubhouse_Page_Renderer::sports( $this->branding(), $vis );
	$this->assertStringContainsString( 'class="ch-nav"', $body );
	$this->assertStringContainsString( 'class="ch-hero-f"', $body );
	$this->assertStringContainsString( 'class="ch-scards"', $body );
	$this->assertStringContainsString( 'class="ch-footer"', $body );
	$this->assertStringContainsString( 'ch-band--ink', $body ); // shared CTA band
	// Sports nav item is the active one.
	$this->assertStringContainsString( 'ch-nav__link--active" href="?page=sports"', $body );
}

public function test_teams_composes_filter_hero_and_stat_cards(): void {
	$vis  = new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() );
	$body = Blueworx_Clubhouse_Page_Renderer::teams( $this->branding(), $vis );
	$this->assertStringContainsString( 'class="ch-hero-f"', $body );
	$this->assertStringContainsString( 'class="ch-scards"', $body );
	$this->assertStringContainsString( 'ch-nav__link--active" href="?page=teams"', $body );
}

public function test_sports_respects_visibility(): void {
	$storage = new Blueworx_Clubhouse_Fake_Storage();
	$vis     = new Blueworx_Clubhouse_Visibility( $storage );
	$vis->set_section_visible( 'sports', 'directory', false );
	$body = Blueworx_Clubhouse_Page_Renderer::sports( $this->branding(), $vis );
	$this->assertStringNotContainsString( 'class="ch-scards"', $body );
	$this->assertStringContainsString( 'class="ch-hero-f"', $body ); // hero still present
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter "test_sports_composes|test_teams_composes|test_sports_respects"`
Expected: FAIL — undefined methods.

- [ ] **Step 3: Write minimal implementation**

Add to `includes/render/class-page-renderer.php` before the final closing `}`:

```php
	public static function sports(
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility
	): string {
		$club = $branding->get_club_name();
		$out  = self::shell_header( $club, '?page=sports' ) . '<main class="ch-main" id="ch-main" tabindex="-1">';

		if ( $visibility->is_section_visible( 'sports', 'hero' ) ) {
			$out .= Blueworx_Clubhouse_Sections::hero_filter( array(
				'eyebrow'         => 'Our sports',
				'title_lead'      => 'Nine sports, ',
				'title_highlight' => 'one club.',
				'lede'            => 'From first session to first team — find your section and get playing.',
				'filter_label'    => 'Filter by sport',
				'filters'         => array(
					array( 'label' => 'All', 'href' => '?page=sports', 'active' => true ),
					array( 'label' => 'Rugby', 'href' => '?page=sports', 'active' => false ),
					array( 'label' => 'Cricket', 'href' => '?page=sports', 'active' => false ),
					array( 'label' => 'Tennis', 'href' => '?page=sports', 'active' => false ),
					array( 'label' => 'Football', 'href' => '?page=sports', 'active' => false ),
					array( 'label' => 'Hockey', 'href' => '?page=sports', 'active' => false ),
					array( 'label' => 'Netball', 'href' => '?page=sports', 'active' => false ),
				),
			) );
		}
		if ( $visibility->is_section_visible( 'sports', 'directory' ) ) {
			$out .= Blueworx_Clubhouse_Sections::stat_card_grid( array(
				'eyebrow'    => 'All sections',
				'heading'    => 'Pick your sport.',
				'link_label' => 'Join the club →',
				'link_href'  => '?page=membership',
				'cards'      => array(
					array( 'image' => '', 'image_alt' => 'Rugby', 'chip' => 'Sat', 'title' => 'Rugby',
						'description' => 'Senior, colts and touch rugby, from minis upward.',
						'stats' => array( array( 'value' => '4', 'label' => 'Teams' ), array( 'value' => '120', 'label' => 'Players' ) ) ),
					array( 'image' => '', 'image_alt' => 'Cricket', 'chip' => 'Summer', 'title' => 'Cricket',
						'description' => 'Youth to senior league cricket on the square.',
						'stats' => array( array( 'value' => '3', 'label' => 'Teams' ), array( 'value' => '80', 'label' => 'Players' ) ) ),
					array( 'image' => '', 'image_alt' => 'Tennis', 'chip' => 'Daily', 'title' => 'Tennis',
						'description' => 'Four courts with coaching for every age.',
						'stats' => array( array( 'value' => '4', 'label' => 'Courts' ), array( 'value' => '90', 'label' => 'Members' ) ) ),
					array( 'image' => '', 'image_alt' => 'Football', 'chip' => 'Sun', 'title' => 'Football',
						'description' => 'Junior football for ages 5 to 16.',
						'stats' => array( array( 'value' => '6', 'label' => 'Teams' ), array( 'value' => '140', 'label' => 'Players' ) ) ),
					array( 'image' => '', 'image_alt' => 'Hockey', 'chip' => 'Sat', 'title' => 'Hockey',
						'description' => 'Ladies and mixed hockey, league affiliated.',
						'stats' => array( array( 'value' => '3', 'label' => 'Teams' ), array( 'value' => '60', 'label' => 'Players' ) ) ),
					array( 'image' => '', 'image_alt' => 'Netball', 'chip' => 'Wed', 'title' => 'Netball',
						'description' => 'Back-to-netball through to divisional squads.',
						'stats' => array( array( 'value' => '2', 'label' => 'Teams' ), array( 'value' => '40', 'label' => 'Players' ) ) ),
				),
			) );
		}
		if ( $visibility->is_section_visible( 'sports', 'cta' ) ) {
			$out .= Blueworx_Clubhouse_Sections::band( array(
				'variant'   => 'ink',
				'eyebrow'   => 'New to the club?',
				'heading'   => 'Try any sport with a free session',
				'lede'      => 'Not sure which section fits? Come down and try before you join.',
				'cta_label' => 'Register interest →',
				'cta_href'  => '?page=contact',
			) );
		}
		$out .= '</main>' . self::shell_footer( $club );
		return $out;
	}

	public static function teams(
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility
	): string {
		$club = $branding->get_club_name();
		$out  = self::shell_header( $club, '?page=teams' ) . '<main class="ch-main" id="ch-main" tabindex="-1">';

		if ( $visibility->is_section_visible( 'teams', 'hero' ) ) {
			$out .= Blueworx_Clubhouse_Sections::hero_filter( array(
				'eyebrow'         => 'Our teams',
				'title_lead'      => 'Twenty-four teams, ',
				'title_highlight' => 'every level.',
				'lede'            => 'League sides, development squads and junior pathways across all nine sports.',
				'filter_label'    => 'Filter teams by sport',
				'filters'         => array(
					array( 'label' => 'All', 'href' => '?page=teams', 'active' => true ),
					array( 'label' => 'Rugby', 'href' => '?page=teams', 'active' => false ),
					array( 'label' => 'Cricket', 'href' => '?page=teams', 'active' => false ),
					array( 'label' => 'Hockey', 'href' => '?page=teams', 'active' => false ),
					array( 'label' => 'Netball', 'href' => '?page=teams', 'active' => false ),
				),
			) );
		}
		if ( $visibility->is_section_visible( 'teams', 'directory' ) ) {
			$out .= Blueworx_Clubhouse_Sections::stat_card_grid( array(
				'eyebrow'    => 'Squads',
				'heading'    => 'Find your team.',
				'link_label' => '',
				'link_href'  => '',
				'cards'      => array(
					array( 'image' => '', 'image_alt' => 'Rugby 1st XV', 'chip' => 'Rugby', 'title' => '1st XV',
						'description' => 'Saturday league rugby, Division 3 South.',
						'stats' => array( array( 'value' => 'Sat', 'label' => 'Match day' ), array( 'value' => 'Div 3', 'label' => 'League' ) ) ),
					array( 'image' => '', 'image_alt' => 'Cricket 1st XI', 'chip' => 'Cricket', 'title' => '1st XI',
						'description' => 'Premier division Saturday cricket.',
						'stats' => array( array( 'value' => 'Sat', 'label' => 'Match day' ), array( 'value' => 'Prem', 'label' => 'League' ) ) ),
					array( 'image' => '', 'image_alt' => 'Ladies hockey 1s', 'chip' => 'Hockey', 'title' => 'Ladies 1s',
						'description' => 'County league hockey with a strong colts feed.',
						'stats' => array( array( 'value' => 'Sat', 'label' => 'Match day' ), array( 'value' => 'County', 'label' => 'League' ) ) ),
					array( 'image' => '', 'image_alt' => 'Netball Div 2', 'chip' => 'Netball', 'title' => 'Netball 2s',
						'description' => 'Wednesday-night divisional netball.',
						'stats' => array( array( 'value' => 'Wed', 'label' => 'Match day' ), array( 'value' => 'Div 2', 'label' => 'League' ) ) ),
				),
			) );
		}
		if ( $visibility->is_section_visible( 'teams', 'cta' ) ) {
			$out .= Blueworx_Clubhouse_Sections::band( array(
				'variant'   => 'ink',
				'eyebrow'   => 'Want to play?',
				'heading'   => 'Trials run all season',
				'lede'      => 'Every squad welcomes new players — get in touch and we will match you to a session.',
				'cta_label' => 'Get in touch →',
				'cta_href'  => '?page=contact',
			) );
		}
		$out .= '</main>' . self::shell_footer( $club );
		return $out;
	}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter "test_sports_composes|test_teams_composes|test_sports_respects"`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add includes/render/class-page-renderer.php tests/php/PageRendererTest.php
git commit -m "feat: add Sports and Teams page composition"
```

---

### Task 6: `events()` + `calendar()` page methods

**Files:**
- Modify: `includes/render/class-page-renderer.php`
- Test: `tests/php/PageRendererTest.php`

**Interfaces:**
- Consumes: `Blueworx_Clubhouse_Sections::hero_filter`, `::event_grid`, `::event_archive`, `::calendar_months`, `::band`.
- Produces: `Blueworx_Clubhouse_Page_Renderer::events(...)`, `::calendar(...)`. Section keys — Events: `hero`, `upcoming`, `past`, `cta`; Calendar: `hero`, `schedule`, `cta`.

- [ ] **Step 1: Write the failing test**

```php
public function test_events_composes_upcoming_and_archive(): void {
	$vis  = new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() );
	$body = Blueworx_Clubhouse_Page_Renderer::events( $this->branding(), $vis );
	$this->assertStringContainsString( 'class="ch-hero-f"', $body );
	$this->assertStringContainsString( 'class="ch-events"', $body );
	$this->assertStringContainsString( 'class="ch-archive"', $body );
	$this->assertStringContainsString( 'ch-nav__link--active" href="?page=events"', $body );
	$this->assertStringContainsString( 'ch-band--ink', $body );
}

public function test_calendar_composes_month_schedule(): void {
	$vis  = new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() );
	$body = Blueworx_Clubhouse_Page_Renderer::calendar( $this->branding(), $vis );
	$this->assertStringContainsString( 'class="ch-hero-f"', $body );
	$this->assertStringContainsString( 'class="ch-cal"', $body );
	$this->assertStringContainsString( 'ch-cal__month', $body );
	$this->assertStringContainsString( 'ch-nav__link--active" href="?page=calendar"', $body );
}

public function test_calendar_respects_visibility(): void {
	$storage = new Blueworx_Clubhouse_Fake_Storage();
	$vis     = new Blueworx_Clubhouse_Visibility( $storage );
	$vis->set_section_visible( 'calendar', 'schedule', false );
	$body = Blueworx_Clubhouse_Page_Renderer::calendar( $this->branding(), $vis );
	$this->assertStringNotContainsString( 'ch-cal__month', $body );
	$this->assertStringContainsString( 'class="ch-hero-f"', $body );
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter "test_events_composes|test_calendar_composes|test_calendar_respects"`
Expected: FAIL — undefined methods.

- [ ] **Step 3: Write minimal implementation**

Add to `includes/render/class-page-renderer.php` before the final closing `}`:

```php
	public static function events(
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility
	): string {
		$club = $branding->get_club_name();
		$out  = self::shell_header( $club, '?page=events' ) . '<main class="ch-main" id="ch-main" tabindex="-1">';

		if ( $visibility->is_section_visible( 'events', 'hero' ) ) {
			$out .= Blueworx_Clubhouse_Sections::hero_filter( array(
				'eyebrow'         => "What's on",
				'title_lead'      => 'Socials, camps and ',
				'title_highlight' => 'open days.',
				'lede'            => "There's always something happening at the club — on the pitch and off it.",
				'filter_label'    => 'Filter events by type',
				'filters'         => array(
					array( 'label' => 'All', 'href' => '?page=events', 'active' => true ),
					array( 'label' => 'Social', 'href' => '?page=events', 'active' => false ),
					array( 'label' => 'Junior', 'href' => '?page=events', 'active' => false ),
					array( 'label' => 'Tournament', 'href' => '?page=events', 'active' => false ),
				),
			) );
		}
		if ( $visibility->is_section_visible( 'events', 'upcoming' ) ) {
			$out .= Blueworx_Clubhouse_Sections::event_grid( array(
				'eyebrow' => 'Coming up',
				'heading' => 'Upcoming events',
				'cards'   => array(
					array( 'tag' => 'Open day', 'date' => 'Sat 26 Jul', 'title' => 'Club Open Day',
						'detail' => '10:00–14:00 · Clubhouse & grounds — all welcome.', 'cta_label' => 'Register interest', 'cta_href' => '?page=contact' ),
					array( 'tag' => 'Junior football', 'date' => '4–8 Aug', 'title' => 'Summer Football Camp',
						'detail' => 'Ages 5–12 · a week of coaching and games.', 'cta_label' => 'Book a place', 'cta_href' => '?page=contact' ),
					array( 'tag' => 'Social', 'date' => 'Fri 12 Sep', 'title' => 'Annual Awards Night',
						'detail' => '19:00 · Clubhouse function room.', 'cta_label' => '', 'cta_href' => '' ),
				),
			) );
		}
		if ( $visibility->is_section_visible( 'events', 'past' ) ) {
			$out .= Blueworx_Clubhouse_Sections::event_archive( array(
				'heading' => 'Recently at the club',
				'rows'    => array(
					array( 'date' => 'Jun 2026', 'tag' => 'Social', 'title' => 'Summer BBQ & Family Day' ),
					array( 'date' => 'May 2026', 'tag' => 'Tournament', 'title' => 'Spring Sevens Rugby Festival' ),
					array( 'date' => 'Apr 2026', 'tag' => 'Club', 'title' => 'Annual General Meeting' ),
					array( 'date' => 'Mar 2026', 'tag' => 'Junior', 'title' => 'Easter Multi-Sport Camp' ),
				),
			) );
		}
		if ( $visibility->is_section_visible( 'events', 'cta' ) ) {
			$out .= Blueworx_Clubhouse_Sections::band( array(
				'variant'   => 'ink',
				'eyebrow'   => 'Hosting something?',
				'heading'   => 'Hire the clubhouse',
				'lede'      => 'Function room and bar available for members and the community.',
				'cta_label' => 'Enquire about hire →',
				'cta_href'  => '?page=contact',
			) );
		}
		$out .= '</main>' . self::shell_footer( $club );
		return $out;
	}

	public static function calendar(
		Blueworx_Clubhouse_Branding $branding,
		Blueworx_Clubhouse_Visibility $visibility
	): string {
		$club = $branding->get_club_name();
		$out  = self::shell_header( $club, '?page=calendar' ) . '<main class="ch-main" id="ch-main" tabindex="-1">';

		if ( $visibility->is_section_visible( 'calendar', 'hero' ) ) {
			$out .= Blueworx_Clubhouse_Sections::hero_filter( array(
				'eyebrow'         => 'Fixtures & results',
				'title_lead'      => 'Every game, ',
				'title_highlight' => 'all season.',
				'lede'            => 'Match days across all nine sports, with results as they come in.',
				'filter_label'    => 'Filter fixtures by sport',
				'filters'         => array(
					array( 'label' => 'All', 'href' => '?page=calendar', 'active' => true ),
					array( 'label' => 'Rugby', 'href' => '?page=calendar', 'active' => false ),
					array( 'label' => 'Cricket', 'href' => '?page=calendar', 'active' => false ),
					array( 'label' => 'Hockey', 'href' => '?page=calendar', 'active' => false ),
				),
			) );
		}
		if ( $visibility->is_section_visible( 'calendar', 'schedule' ) ) {
			$out .= Blueworx_Clubhouse_Sections::calendar_months( array(
				'eyebrow' => 'The schedule',
				'heading' => 'Fixtures & results',
				'months'  => array(
					array( 'label' => 'July', 'rows' => array(
						array( 'date' => 'Sat 12', 'competition' => 'Rugby · 1st XV', 'matchup' => 'ClubHouse vs Riverside RFC', 'detail' => 'Home · 14:00', 'outcome' => '' ),
						array( 'date' => 'Sun 13', 'competition' => 'Netball · Div 2', 'matchup' => 'ClubHouse vs Castlebridge', 'detail' => 'Away · 11:00', 'outcome' => '' ),
						array( 'date' => 'Sat 5', 'competition' => 'Cricket · 1st XI', 'matchup' => 'ClubHouse vs Hartfield CC', 'detail' => 'Won by 34 runs', 'outcome' => 'W' ),
					) ),
					array( 'label' => 'June', 'rows' => array(
						array( 'date' => 'Sat 28', 'competition' => 'Rugby · 2nd XV', 'matchup' => 'ClubHouse vs Dunmore', 'detail' => 'Lost 18–24', 'outcome' => 'L' ),
						array( 'date' => 'Sat 21', 'competition' => 'Hockey · Ladies 1s', 'matchup' => 'ClubHouse vs Elmwood', 'detail' => 'Drew 2–2', 'outcome' => 'D' ),
					) ),
				),
			) );
		}
		if ( $visibility->is_section_visible( 'calendar', 'cta' ) ) {
			$out .= Blueworx_Clubhouse_Sections::band( array(
				'variant'   => 'ink',
				'eyebrow'   => 'Follow the club',
				'heading'   => 'Never miss a result',
				'lede'      => 'Fixtures, results and club news — one email a month.',
				'cta_label' => 'Join the mailing list →',
				'cta_href'  => '?page=contact',
			) );
		}
		$out .= '</main>' . self::shell_footer( $club );
		return $out;
	}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter "test_events_composes|test_calendar_composes|test_calendar_respects"`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add includes/render/class-page-renderer.php tests/php/PageRendererTest.php
git commit -m "feat: add Events and Calendar page composition"
```

---

### Task 7: Preview routing for the four pages

**Files:**
- Modify: `preview/index.php` (the `switch` in `blueworx_clubhouse_preview_body`, ~lines 66–78)
- Test: `tests/php/PreviewRenderTest.php`

**Interfaces:**
- Consumes: the four new `Page_Renderer` methods (Tasks 5, 6).

- [ ] **Step 1: Write the failing test**

Add to `tests/php/PreviewRenderTest.php`:

```php
public function test_preview_routes_the_four_collection_pages(): void {
	require_once dirname( __DIR__, 2 ) . '/preview/index.php';
	$b   = new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() );
	$vis = new Blueworx_Clubhouse_Visibility( new Blueworx_Clubhouse_Fake_Storage() );

	$this->assertStringContainsString( 'ch-scards', blueworx_clubhouse_preview_body( 'sports', $b, $vis ) );
	$this->assertStringContainsString( 'ch-scards', blueworx_clubhouse_preview_body( 'teams', $b, $vis ) );
	$this->assertStringContainsString( 'ch-events', blueworx_clubhouse_preview_body( 'events', $b, $vis ) );
	$this->assertStringContainsString( 'ch-cal__month', blueworx_clubhouse_preview_body( 'calendar', $b, $vis ) );
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter test_preview_routes_the_four_collection_pages`
Expected: FAIL — `sports`/`teams`/`events`/`calendar` fall through to Home (no `ch-scards`/`ch-events`/`ch-cal__month`).

- [ ] **Step 3: Write minimal implementation**

In `preview/index.php`, extend the `switch` in `blueworx_clubhouse_preview_body()` — add these cases before the `case 'home': default:` arm:

```php
		case 'sports':
			return Blueworx_Clubhouse_Page_Renderer::sports( $branding, $visibility );
		case 'teams':
			return Blueworx_Clubhouse_Page_Renderer::teams( $branding, $visibility );
		case 'events':
			return Blueworx_Clubhouse_Page_Renderer::events( $branding, $visibility );
		case 'calendar':
			return Blueworx_Clubhouse_Page_Renderer::calendar( $branding, $visibility );
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter test_preview_routes_the_four_collection_pages`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add preview/index.php tests/php/PreviewRenderTest.php
git commit -m "feat: route sports, teams, events and calendar in the preview"
```

---

### Task 8: Court Side styling + stylesheet test + version bump

Style every new `ch-*` hook in Court Side, consuming engine custom properties only (no hex literals of the accent, no font/radius literals). Then bump the version and update the changelog.

**Files:**
- Modify: `assets/looks/court-side.css`
- Test: `tests/php/CourtSideStylesheetTest.php`
- Modify: `blueworx-labs-clubhouse.php`, `package.json`, `CHANGELOG.md`

- [ ] **Step 1: Write the failing stylesheet test**

Add to `tests/php/CourtSideStylesheetTest.php`:

```php
public function test_styles_the_new_collection_sections(): void {
	$css = $this->css();
	foreach ( array( '.ch-hero-f', '.ch-filter', '.ch-scard', '.ch-events', '.ch-event', '.ch-archive', '.ch-cal__month' ) as $sel ) {
		$this->assertStringContainsString( $sel, $css );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter test_styles_the_new_collection_sections`
Expected: FAIL — the new selectors are not yet in `court-side.css`.

- [ ] **Step 3: Add the Court Side CSS**

Append to `assets/looks/court-side.css` (tokens only — reuse the existing `--color-*`, `--radius-*`, `--space-*`, `--font-*` custom properties the sheet already consumes; match the existing file's spacing/rhythm conventions). This is a complete, working baseline — refine spacing to match the shipped look, but do not introduce hex colours or the accent literal:

```css
/* ---- Filter hero (Sports/Teams/Events/Calendar) ---- */
.ch-hero-f { padding: var(--space-9) 0 var(--space-6); }
.ch-hero-f__title { font-family: var(--font-display); font-size: clamp(2rem, 5vw, 3.25rem); line-height: 1.04; letter-spacing: -0.02em; margin: var(--space-3) 0 var(--space-4); }
.ch-hero-f__hl { color: var(--color-accent-deep); }
.ch-hero-f__lede { max-width: 60ch; color: var(--color-ink-soft); font-size: 1.125rem; margin-bottom: var(--space-5); }
.ch-filters { display: flex; flex-wrap: wrap; gap: var(--space-2); }
.ch-filter { display: inline-flex; align-items: center; min-height: 44px; padding: 0 var(--space-4); border: 1px solid var(--color-line); border-radius: var(--radius-pill); color: var(--color-ink); text-decoration: none; font-weight: 600; font-size: 0.9375rem; }
.ch-filter:hover { border-color: var(--color-accent); }
.ch-filter--on { background: var(--color-accent); color: var(--color-accent-ink); border-color: var(--color-accent); }

/* ---- Stat card grid (Sports/Teams) ---- */
.ch-scards { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: var(--space-4); }
.ch-scard { display: flex; flex-direction: column; border: 1px solid var(--color-line); border-radius: var(--radius-lg); overflow: hidden; background: var(--color-surface); }
.ch-scard__media { aspect-ratio: 16 / 10; }
.ch-scard__chip { align-self: flex-start; margin: var(--space-3) var(--space-3) 0; padding: 2px var(--space-2); border-radius: var(--radius-pill); background: var(--color-accent-wash); color: var(--color-accent-deep); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; }
.ch-scard__body { display: flex; flex-direction: column; gap: var(--space-2); padding: var(--space-3); }
.ch-scard__title { font-family: var(--font-display); font-size: 1.375rem; }
.ch-scard__desc { color: var(--color-ink-soft); font-size: 0.9375rem; }
.ch-scard__stats { display: flex; gap: var(--space-4); margin-top: auto; padding-top: var(--space-3); border-top: 1px solid var(--color-line); }
.ch-scard__stat-v { display: block; font-family: var(--font-display); font-size: 1.25rem; }
.ch-scard__stat-l { font-size: 0.75rem; color: var(--color-ink-soft); text-transform: uppercase; letter-spacing: 0.04em; }

/* ---- Event grid + archive (Events) ---- */
.ch-events { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: var(--space-4); }
.ch-event { display: flex; flex-direction: column; gap: var(--space-2); padding: var(--space-4); border: 1px solid var(--color-line); border-radius: var(--radius-lg); }
.ch-event__meta { display: flex; justify-content: space-between; align-items: center; }
.ch-event__tag { padding: 2px var(--space-2); border-radius: var(--radius-pill); background: var(--color-accent-wash); color: var(--color-accent-deep); font-size: 0.75rem; font-weight: 700; }
.ch-event__date { color: var(--color-ink-soft); font-size: 0.875rem; font-weight: 600; }
.ch-event__title { font-family: var(--font-display); font-size: 1.25rem; }
.ch-event__detail { color: var(--color-ink-soft); font-size: 0.9375rem; }
.ch-event__cta { align-self: flex-start; margin-top: var(--space-2); }
.ch-archive { display: flex; flex-direction: column; }
.ch-archive__row { display: grid; grid-template-columns: 120px 120px 1fr; gap: var(--space-3); align-items: center; padding: var(--space-3) 0; border-top: 1px solid var(--color-line); }
.ch-archive__date { color: var(--color-ink-soft); font-size: 0.875rem; }
.ch-archive__tag { color: var(--color-accent-deep); font-weight: 700; font-size: 0.8125rem; text-transform: uppercase; }
.ch-archive__title { font-weight: 600; }

/* ---- Calendar months ---- */
.ch-cal__month + .ch-cal__month { margin-top: var(--space-6); }
.ch-cal__mlabel { font-family: var(--font-display); font-size: 1.5rem; margin-bottom: var(--space-3); padding-bottom: var(--space-2); border-bottom: 2px solid var(--color-line); }
.ch-cal__row { display: grid; grid-template-columns: 80px 1fr auto auto; gap: var(--space-3); align-items: center; padding: var(--space-3) 0; border-top: 1px solid var(--color-line); }
.ch-cal__date { font-weight: 700; }
.ch-cal__body { display: flex; flex-direction: column; }
.ch-cal__comp { font-size: 0.8125rem; color: var(--color-ink-soft); text-transform: uppercase; letter-spacing: 0.03em; }
.ch-cal__match { font-weight: 600; }
.ch-cal__detail { color: var(--color-ink-soft); font-size: 0.875rem; }
.ch-cal__soon { padding: 2px var(--space-2); border-radius: var(--radius-pill); border: 1px solid var(--color-line); color: var(--color-ink-soft); font-size: 0.75rem; font-weight: 600; }

@media (max-width: 640px) {
	.ch-archive__row { grid-template-columns: 1fr auto; }
	.ch-archive__title { grid-column: 1 / -1; }
	.ch-cal__row { grid-template-columns: 64px 1fr auto; }
	.ch-cal__detail { grid-column: 2 / -1; }
}
```

> **Note:** the token names above (`--color-surface`, `--color-ink-soft`, `--radius-lg`, `--radius-pill`, `--space-*`, `--font-display`) must match those actually emitted/consumed by the shipped Court Side look. Before writing, grep `assets/looks/court-side.css` for the exact custom-property names already in use and use those verbatim — do not invent new token names. If a needed token (e.g. a surface colour) is not already defined, reuse the closest existing one rather than adding a literal.

- [ ] **Step 4: Run the stylesheet test + full suite + lint**

Run: `vendor/bin/phpunit --filter test_styles_the_new_collection_sections` → PASS.
Run: `composer test` → all tests PASS (should now be ~145 tests).
Run: `composer lint` → clean.

- [ ] **Step 5: Bump version + changelog**

In `blueworx-labs-clubhouse.php`, change the header `Version: 0.10.0`-line to `Version: 0.11.0` (the current base-look header is `0.8.0`; this branch jumps to `0.11.0` — above the sibling look branches' `0.9.0`/`0.10.0` — so it merges into `base-look-theming-design` cleanly after them; see Cross-branch follow-up). Also update the `BLUEWORX_CLUBHOUSE_VERSION` constant if present.

In `package.json`, set `"version": "0.11.0"`.

In `CHANGELOG.md`, add at the top (below the intro, above `## [0.10.0]`):

```markdown
## [0.11.0] - 2026-07-11

### Sports, Teams, Events & Calendar pages

The four remaining collection pages, completing the eight-page ClubHouse site under the Court Side look.

#### New

- **Four new pages** — Sports, Teams, Events and Calendar — composed under per-section `Visibility` with hardcoded ClubHouse demo data, routed in the preview via `?page=`.
- **Five new skin-agnostic section renderers** on `Blueworx_Clubhouse_Sections`: `hero_filter` (filter-pill hero), `stat_card_grid` (chip + stats cards for Sports/Teams), `event_grid` + `event_archive` (upcoming cards + past list), and `calendar_months` (month-grouped fixtures/results with W/D/L status badges). All emit only `ch-*` classes, escape interpolated text, and carry list semantics.
- **Court Side styling** for every new hook, consuming engine custom properties only.

#### Notes

- Demo data is hardcoded this round; the later Collections/CPT plan swaps the data source behind the unchanged renderers.
- Filter pills are presentational (unfiltered demo data), consistent with the progressive-enhancement / presentational-forms decisions.
- Members' House and Floodlight will need the same new `ch-*` hooks styled when they rebase onto this branch (re-skin contract).
```

- [ ] **Step 6: Verify version sync + commit**

Run: `composer test && composer lint` → both green.
Confirm the plugin header and `package.json` both read `0.11.0`.

```bash
git add blueworx-labs-clubhouse.php package.json CHANGELOG.md assets/looks/court-side.css tests/php/CourtSideStylesheetTest.php
git commit -m "feat: style Sports/Teams/Events/Calendar in Court Side and bump to 0.11.0"
```

---

## Self-Review (completed by plan author)

**1. Spec coverage** (umbrella §3–§7, Plan 3 = "Collection-ish pages"):
- Sports, Teams, Events, Calendar pages → Tasks 5, 6, 7. ✓
- `hero_filter` + filter bar → Task 1. ✓
- card_grid stat-card variant → Task 2 (`stat_card_grid`, deviation documented). ✓
- `event_grid`/`event_archive` → Task 3. ✓
- `calendar_months` → Task 4 (inlines fixture/result rows, deviation documented). ✓
- Court Side styling of new hooks → Task 8. ✓
- Preview `?page=` routing → Task 7. ✓
- Visibility per section → tested in Tasks 5, 6. ✓
- Escaping + no colour/style literals → asserted in every renderer test. ✓
- cta_band (all pages) → reuses existing `band` ink variant. ✓

**2. Placeholder scan:** no TBD/TODO; every code step carries complete code. The one soft spot — exact Court Side token names in Task 8 — is explicitly gated by a "grep the real token names first" instruction, not left as a guess.

**3. Type consistency:** renderer signatures in the Interfaces blocks match their implementations and their page-method call sites (`hero_filter`, `stat_card_grid`, `event_grid`, `event_archive`, `calendar_months`); section-visibility keys are consistent between each page method and its test.

## Cross-branch follow-up (record in the PR description and project memory)

- This branch bumps to **`0.11.0`** and merges into `base-look-theming-design` **after** the sibling look PRs (#3 Members' House `0.9.0`, #4 Floodlight `0.10.0`) so every version-bump check stays green against the ascending base.
- **Re-skin contract:** Members' House (`assets/looks/members-house.css`) and Floodlight (`assets/looks/floodlight.css`) will each need the new `ch-hero-f`, `ch-filter(s)`, `ch-scard*`, `ch-events`/`ch-event*`, `ch-archive*`, `ch-cal*` hooks styled when they rebase onto the updated base-look. The renderers stay skin-agnostic, so this is CSS-only per look — no renderer/engine changes.
- **Deferred (unchanged):** standalone `fixture_rows`/`result_rows` extraction → Collections plan; real filtering → Collections plan; WordPress `template_include` + the CI staging URL → the WP-render plan.
