# Setup rebuilt on the page editor library — implementation plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** The Clubhouse Setup screen stops being a hand-built form and becomes one settings editor declared to the BlueWorx page editor library, with the six tabs the design asks for — absorbing [#283](../../../../issues/283), [#284](../../../../issues/284) and [#285](../../../../issues/285), and finishing [#282](../../../../issues/282).

**Architecture:** One declarative source (`Setup_Fields`) describes the whole screen in the library's vocabulary. Setup's values do not live in one option — they are spread across the look registry, `Branding`, `Visibility`, `Menu`, `Profile_Store`, `Auth_Settings`, `Mail_Settings` and `Demo_State` — so the screen supplies its own `read`/`write` pair (`Setup_Storage`) instead of a store, and every existing setter stays exactly where it is. Nothing moves in the database, so phase 4 needs **no migration**. The accent legibility rule moves to the screen's `validate` callback. The old controller, screen builder, menu panel and their two assets are deleted in the same release.

**Tech Stack:** PHP 8.3, WordPress, PHPUnit 11, Playwright, no build step. The vendored `Blueworx\PageEditor\v1\…` library, already in this repo since phase 3.

**Spec:** [docs/superpowers/specs/2026-08-28-page-editor-adoption-design.md](../specs/2026-08-28-page-editor-adoption-design.md) — §3, §6, §9. Phase 4 of six.

## Global Constraints

- **Baseline:** plugin v0.99.3, `main` at `64439f1`.
- **Branch and PR.** Always a branch, never `main`. Every change through a pull request. CI guardrails never bypassed.
- **Version and changelog.** Minor bump (new feature) with the changelog updated alongside, in the same PR.
- **No new dependency** without `approved-deps.json` first. This plan adds none.
- **Do not edit `.claude/skills/blueworx-admin-design/` or `blueworx-page-editor/`.** They are hash-compared against the foundation. Everything this phase needs is already in the vendored copy: `Schema::REPEATER_KINDS` already carries `textarea`, `select`, `toggle` and `media`; `Store::for()` already honours a screen's own `read`/`write`; `Validate::run()` already calls a screen's `validate`.
- **The screen owns its storage, so `store` is still declared but never used.** `Schema::validate()` defaults `store` to `post` and would then demand a `post_type`; declare `'store' => 'option'` alongside `read`/`write` so the shape check passes. `Store::for()` sends the screen to its own callbacks regardless.
- **One capability on the screen, per-field capabilities inside it.** The screen's capability is `Owner_Capabilities::CONTENT_CAP`, because a Content Editor must still reach the Menu tab. Every field outside Menu carries `'capability' => Owner_Capabilities::SETUP_CAP`; Demo mode's carries `manage_options`.
- **`Capabilities::reduce()` empties a panel it may not show, it does not remove it.** A tab a user may not touch must therefore be left out of the declaration for that user, not filtered afterwards — `Setup_Fields::tabs()` takes a `can` map and returns only the tabs that survive it.
- **Local green is not green for admin screens.** `@wordpress`-tagged specs are silently skipped by a preview-only run. Run `npm run wp:up` then `npm run test:wp` before claiming a screen works.
- **Sign in as an owner, not an administrator.** An administrator holds every capability WordPress has and so can never notice a role missing one — the bug v0.99.2 fixed. Every browser spec here signs in as the owner in the harness, the way `tests/owner-edits-a-club-page.spec.js` does.

## Decisions taken in this plan

| Question | Decision |
| --- | --- |
| The look picker's live re-skin | Goes. §6 takes the club's look out of wp-admin, so there is nothing left to re-skin. The picker becomes a `radio` of looks with each look's description as its help. |
| The setup progress bar | Goes. The library gives every screen the same header, and a percentage that only this screen has is exactly the bespoke chrome §6 removes. `Setup_Progress` and its option key are deleted. |
| A low-contrast secondary colour | Still saved, still not refused — but the library has no warning channel, only field errors. The warning becomes a permanent line of help under the field. The primary accent is still refused outright, through the screen's `validate`. |
| The owner's dashboard | Today it renders the entire Setup form as a dashboard widget, which a library screen cannot do. It becomes a short welcome panel with three links: Setup, Pages, Members. |
| Removing a custom member field | A repeater row's remove takes the field off the form and **leaves every member's answer alone**. Erasing answers stays a separate, deliberate act with its own confirmation — a row you can drag out by accident must never wipe member data. Task 4 keeps `Profile_Store::forget()` for that. |
| `Setup_Sections` | Stays. Phase 3 was expected to delete it; it now sources the page and section inventory for `Page_Fields` and `Import_Sections`, which is a different job. Only its per-section visibility role is gone, and that went in phase 3. |

---

## File Structure

**Created:**

- `includes/admin/class-setup-fields.php` — the declarative source. Six tabs, their panels and fields, in library vocabulary. Pure: no hooks, no WordPress calls, so a test can hold it against `Schema::validate()`.
- `includes/admin/class-setup-storage.php` — the screen's `read`/`write` pair. Maps every field id onto the store that already owns it, in both directions. Takes a `Storage`, so it is unit-testable WordPress-free.
- `includes/admin/class-setup-editor.php` — registers the screen with the library, owns the Clubhouse top-level menu, and carries the `validate` callback.
- `includes/admin/class-owner-welcome.php` — the replacement dashboard widget.
- `tests/php/SetupFieldsTest.php`, `tests/php/SetupStorageTest.php`, `tests/php/SetupEditorTest.php`, `tests/php/SetupPageControllerTest.php`, `tests/php/OwnerWelcomeTest.php`
- `tests/setup-editor.spec.js` — Playwright, `@wordpress`, signed in as the owner.

**Modified:**

- `includes/bootstrap.php` — load the new classes, drop the deleted ones.
- `includes/admin/class-owner-role.php` — the dashboard widget calls `Owner_Welcome`.
- `includes/admin/class-access-controller.php` — role tags were passed into the old screen model; they attach to the new screen's lede instead.
- `includes/admin/class-admin-assets.php` — stops enqueuing the two Setup assets.
- `includes/content/class-visibility.php` — a page's switch reads the page's real status.
- `tests/menu-editor.spec.js` — rewritten against the new controls.
- `blueworx-labs-clubhouse.php`, `CHANGELOG.md`, `docs/priorities.md`.

**Deleted (task 9):**

- `includes/admin/class-setup-controller.php`, `class-setup-screen.php`, `class-setup-progress.php`, `class-menu-controller.php`, `class-menu-panel.php`
- `assets/css/admin-setup.css`, `assets/js/admin-setup.js`
- `tests/php/SetupScreenTest.php`, `SetupControllerTest.php`, `SetupProfileFieldsTest.php`, `MenuTabLocationTest.php`, `AdminSetupStylesheetTest.php`, and any other test naming a deleted class.

**Kept, unchanged:** `Branding`, `Visibility`, `Menu`, `Profile_Store`, `Auth_Settings`, `Mail_Settings`, `Demo_State`, `Color_Engine`, `Base_Look_Registry`, `Link_Catalogue`, `Setup_Sections`. Phase 4 changes the screen, not the stores.

---

### Task 1: The declarative source — tabs, panels and fields

The whole screen as data, in the order [#284](../../../../issues/284) asks for, with the Members/Settings split [#283](../../../../issues/283) asks for. Nothing renders yet.

**Files:**
- Create: `includes/admin/class-setup-fields.php`
- Test: `tests/php/SetupFieldsTest.php`

**Interfaces:**
- Produces: `Blueworx_Clubhouse_Setup_Fields::tabs( array $can, array $looks, array $pages ): array` — the library's `tabs` list. `$can` is `['setup' => bool, 'menu' => bool, 'demo' => bool]`; `$looks` is `[slug => ['name' => string, 'description' => string]]`; `$pages` is `[['page' => string, 'label' => string], …]`.
- Produces: `Blueworx_Clubhouse_Setup_Fields::PAGE_FIELD_PREFIX = 'page_visible_'`.

- [ ] **Step 1: Write the failing test**

```php
// tests/php/SetupFieldsTest.php
final class SetupFieldsTest extends TestCase {

	private function can(): array {
		return array( 'setup' => true, 'menu' => true, 'demo' => true );
	}

	private function looks(): array {
		return array( 'court-side' => array( 'name' => 'Court Side', 'description' => 'Crisp and sporty.' ) );
	}

	private function pages(): array {
		return array(
			array( 'page' => 'home',  'label' => 'Home' ),
			array( 'page' => 'about', 'label' => 'About' ),
		);
	}

	public function test_the_six_tabs_are_in_the_order_issue_284_asks_for(): void {
		$ids = array_column( Blueworx_Clubhouse_Setup_Fields::tabs( $this->can(), $this->looks(), $this->pages() ), 'id' );
		$this->assertSame(
			array( 'look', 'visibility', 'menu', 'members', 'settings', 'demo' ),
			$ids
		);
	}

	public function test_members_and_settings_are_separate_tabs(): void {
		$tabs     = array_column( Blueworx_Clubhouse_Setup_Fields::tabs( $this->can(), $this->looks(), $this->pages() ), null, 'id' );
		$members  = array_column( $tabs['members']['panels'], 'id' );
		$settings = array_column( $tabs['settings']['panels'], 'id' );
		$this->assertSame( array( 'profile_fields' ), $members );
		$this->assertSame( array( 'after_sign_in', 'emails' ), $settings );
	}

	public function test_visibility_has_one_switch_per_available_page(): void {
		$tabs = array_column( Blueworx_Clubhouse_Setup_Fields::tabs( $this->can(), $this->looks(), $this->pages() ), null, 'id' );
		$ids  = array_column( $tabs['visibility']['panels'][0]['fields'], 'id' );
		$this->assertSame( array( 'page_visible_home', 'page_visible_about' ), $ids );
	}

	public function test_a_content_editor_gets_the_menu_tab_and_nothing_else(): void {
		$ids = array_column(
			Blueworx_Clubhouse_Setup_Fields::tabs(
				array( 'setup' => false, 'menu' => true, 'demo' => false ),
				$this->looks(),
				$this->pages()
			),
			'id'
		);
		$this->assertSame( array( 'menu' ), $ids );
	}

	public function test_demo_is_absent_for_a_non_administrator(): void {
		$ids = array_column(
			Blueworx_Clubhouse_Setup_Fields::tabs(
				array( 'setup' => true, 'menu' => true, 'demo' => false ),
				$this->looks(),
				$this->pages()
			),
			'id'
		);
		$this->assertNotContains( 'demo', $ids );
	}
}
```

- [ ] **Step 2: Run it and watch it fail**

Run: `vendor/bin/phpunit --filter SetupFieldsTest`
Expected: FAIL, "Class Blueworx_Clubhouse_Setup_Fields not found".

- [ ] **Step 3: Write `Setup_Fields`**

One method per tab, `tabs()` assembling them behind the `can` map. Every field outside Menu carries `SETUP_CAP`; Demo's carries `manage_options`.

```php
<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Setup screen as data, in the page editor library's vocabulary.
 *
 * Pure — no hooks, no WordPress, no storage — so SetupFieldsTest can hold the
 * whole screen against Schema::validate() and a mistake is a red test rather
 * than a live screen saying it is not ready.
 *
 * Tabs a user may not touch are left out here rather than filtered afterwards:
 * Capabilities::reduce() empties a panel it may not show but keeps the panel,
 * so a Content Editor filtered that way would see four empty tabs.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Setup_Fields {

	public const PAGE_FIELD_PREFIX = 'page_visible_';

	private static function cap(): string {
		return Blueworx_Clubhouse_Owner_Capabilities::SETUP_CAP;
	}

	/**
	 * @param array{setup:bool,menu:bool,demo:bool} $can
	 * @param array<string,array{name:string,description:string}> $looks
	 * @param array<int,array{page:string,label:string}> $pages
	 * @return array<int,array<string,mixed>>
	 */
	public static function tabs( array $can, array $looks, array $pages ): array {
		$tabs = array();
		if ( $can['setup'] ) {
			$tabs[] = self::look_tab( $looks );
			$tabs[] = self::visibility_tab( $pages );
		}
		if ( $can['menu'] ) {
			$tabs[] = self::menu_tab();
		}
		if ( $can['setup'] ) {
			$tabs[] = self::members_tab();
			$tabs[] = self::settings_tab();
		}
		if ( $can['setup'] && $can['demo'] ) {
			$tabs[] = self::demo_tab();
		}
		return $tabs;
	}

	/** @param array<string,array{name:string,description:string}> $looks */
	private static function look_tab( array $looks ): array {
		$options = array();
		$help    = array();
		foreach ( $looks as $slug => $look ) {
			$options[] = array( 'value' => $slug, 'label' => $look['name'] );
			$help[]    = $look['name'] . ' — ' . $look['description'];
		}
		return array(
			'id'     => 'look',
			'label'  => 'Base Look & Branding',
			'panels' => array(
				array(
					'id'     => 'base_look',
					'title'  => 'Base Look',
					'note'   => 'The visual foundation for your site. Everything else adapts to it.',
					'fields' => array(
						array(
							'id'         => 'look',
							'kind'       => 'radio',
							'label'      => 'Base Look',
							'options'    => $options,
							'help'       => implode( ' · ', $help ),
							'capability' => self::cap(),
						),
					),
				),
				array(
					'id'     => 'branding',
					'title'  => 'Branding',
					'note'   => 'Your club name, colours, logo and social links.',
					'fields' => array(
						array( 'id' => 'club_name', 'kind' => 'text', 'label' => 'Club name', 'capability' => self::cap() ),
						array( 'id' => 'accent', 'kind' => 'colour', 'label' => 'Main colour', 'help' => 'Used for buttons and links. It has to stay readable on your chosen look, so a very pale or very grey colour is refused.', 'capability' => self::cap() ),
						array( 'id' => 'secondary', 'kind' => 'colour', 'label' => 'Second colour', 'help' => 'Optional. Leave it empty to have one worked out from your main colour. A low-contrast colour is allowed here, but text on it may be hard to read.', 'capability' => self::cap() ),
						array( 'id' => 'logo', 'kind' => 'media', 'label' => 'Logo', 'capability' => self::cap() ),
						array( 'id' => 'favicon', 'kind' => 'media', 'label' => 'Browser tab icon', 'capability' => self::cap() ),
						array( 'id' => 'facebook', 'kind' => 'text', 'format' => 'url', 'label' => 'Facebook', 'capability' => self::cap() ),
						array( 'id' => 'instagram', 'kind' => 'text', 'format' => 'url', 'label' => 'Instagram', 'capability' => self::cap() ),
						array( 'id' => 'linkedin', 'kind' => 'text', 'format' => 'url', 'label' => 'LinkedIn', 'capability' => self::cap() ),
						array( 'id' => 'x', 'kind' => 'text', 'format' => 'url', 'label' => 'X', 'capability' => self::cap() ),
					),
				),
			),
		);
	}

	/** @param array<int,array{page:string,label:string}> $pages */
	private static function visibility_tab( array $pages ): array {
		$fields = array();
		foreach ( $pages as $page ) {
			$fields[] = array(
				'id'         => self::PAGE_FIELD_PREFIX . $page['page'],
				'kind'       => 'toggle',
				'label'      => $page['label'],
				'default'    => true,
				'capability' => self::cap(),
			);
		}
		return array(
			'id'     => 'visibility',
			'label'  => 'Visibility',
			'panels' => array(
				array(
					'id'     => 'pages',
					'title'  => 'Pages',
					'note'   => 'Switch a page off and it stops being published — visitors and search engines both get a proper "not found". Sections are switched off on the page itself.',
					'fields' => $fields,
				),
			),
		);
	}

	private static function menu_tab(): array {
		return array(
			'id'     => 'menu',
			'label'  => 'Menu',
			'panels' => array(
				array(
					'id'     => 'menu',
					'title'  => 'Menu',
					'note'   => 'The navigation across the top of your site. Drag to reorder.',
					'fields' => array(
						array(
							'id'     => 'menu',
							'kind'   => 'repeater',
							'label'  => 'Menu items',
							'fields' => array(
								array( 'id' => 'label', 'kind' => 'text', 'label' => 'Label', 'required' => true ),
								array( 'id' => 'target', 'kind' => 'text', 'format' => 'url', 'label' => 'Links to' ),
								array( 'id' => 'nested', 'kind' => 'toggle', 'label' => 'Show under the item above' ),
							),
						),
					),
				),
			),
		);
	}

	private static function members_tab(): array {
		return array(
			'id'     => 'members',
			'label'  => 'Members',
			'panels' => array(
				array(
					'id'     => 'profile_fields',
					'title'  => 'What you ask your members',
					'note'   => 'Your club\'s own questions, on top of name and email. Removing one takes it off the form and leaves existing answers alone.',
					'fields' => array(
						array(
							'id'         => 'profile_fields',
							'kind'       => 'repeater',
							'label'      => 'Member fields',
							'capability' => self::cap(),
							'fields'     => array(
								array( 'id' => 'label', 'kind' => 'text', 'label' => 'Question', 'required' => true ),
								array(
									'id'      => 'type',
									'kind'    => 'select',
									'label'   => 'Answer',
									'options' => array(
										array( 'value' => 'text', 'label' => 'Short text' ),
										array( 'value' => 'textarea', 'label' => 'Long text' ),
										array( 'value' => 'select', 'label' => 'Choice' ),
										array( 'value' => 'toggle', 'label' => 'Yes or no' ),
									),
								),
								array( 'id' => 'options', 'kind' => 'textarea', 'label' => 'Choices, one per line' ),
								array( 'id' => 'required', 'kind' => 'toggle', 'label' => 'Must be answered' ),
								array( 'id' => 'private', 'kind' => 'toggle', 'label' => 'Only staff can see it' ),
							),
						),
					),
				),
			),
		);
	}

	private static function settings_tab(): array {
		return array(
			'id'     => 'settings',
			'label'  => 'Settings',
			'panels' => array(
				array(
					'id'     => 'after_sign_in',
					'title'  => 'After signing in',
					'note'   => 'Where a member lands when they sign in, and when they sign out.',
					'fields' => array(
						array( 'id' => 'post_login', 'kind' => 'text', 'label' => 'After signing in', 'capability' => self::cap() ),
						array( 'id' => 'post_logout', 'kind' => 'text', 'label' => 'After signing out', 'capability' => self::cap() ),
					),
				),
				array(
					'id'     => 'emails',
					'title'  => 'Emails',
					'note'   => 'Who your site\'s email comes from. Leave both empty and it comes from your club\'s name at your own domain.',
					'fields' => array(
						array( 'id' => 'mail_from_name', 'kind' => 'text', 'label' => 'From name', 'capability' => self::cap() ),
						array( 'id' => 'mail_from_address', 'kind' => 'text', 'format' => 'email', 'label' => 'Reply-to address', 'capability' => self::cap() ),
					),
				),
			),
		);
	}

	private static function demo_tab(): array {
		return array(
			'id'     => 'demo',
			'label'  => 'Demo mode',
			'panels' => array(
				array(
					'id'     => 'demo',
					'title'  => 'Demo mode',
					'note'   => 'Fills the site with example content so it can be shown before a club has written anything.',
					'fields' => array(
						array( 'id' => 'demo_active', 'kind' => 'toggle', 'label' => 'Demo mode on', 'capability' => 'manage_options' ),
					),
				),
			),
		);
	}
}
```

- [ ] **Step 4: Run the tests**

Run: `vendor/bin/phpunit --filter SetupFieldsTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add includes/admin/class-setup-fields.php tests/php/SetupFieldsTest.php
git commit -m "Declare the Setup screen as data"
```

---

### Task 2: The screen passes the library's own shape check

A screen the library refuses shows an owner "this screen is not ready" and nothing else. Hold the definition against `Schema::validate()` so that can only ever be a red test.

**Files:**
- Modify: `tests/php/SetupFieldsTest.php`

- [ ] **Step 1: Write the failing test**

```php
	public function test_the_screen_passes_the_librarys_own_shape_check(): void {
		$screen = array(
			'slug'       => 'clubhouse-setup',
			'title'      => 'Clubhouse Setup',
			'store'      => 'option',
			'read'       => static fn( int $id ): array => array(),
			'write'      => static fn( array $v, int $id ): bool => true,
			'capability' => Blueworx_Clubhouse_Owner_Capabilities::CONTENT_CAP,
			'tabs'       => Blueworx_Clubhouse_Setup_Fields::tabs( $this->can(), $this->looks(), $this->pages() ),
		);
		$this->assertIsArray( \Blueworx\PageEditor\v1\Schema::validate( $screen ) );
	}
```

- [ ] **Step 2: Run it**

Run: `vendor/bin/phpunit --filter test_the_screen_passes_the_librarys_own_shape_check`
Expected: FAIL first time on whichever rule the declaration breaks — a `select` with no options, a repeater cell of a kind a row may not hold, a duplicate id.

- [ ] **Step 3: Fix the declaration until it passes**

No new code — the fix is always in `Setup_Fields`, never in the library. Two rules bite in practice: a repeater row may only hold `text`, `number`, `textarea`, `select`, `toggle` or `media`, and every id must be unique across the whole screen (the `menu` panel and the `menu` repeater are a panel id and a field id, which are separate namespaces — `profile_fields` likewise).

- [ ] **Step 4: Run the whole file**

Run: `vendor/bin/phpunit --filter SetupFieldsTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add tests/php/SetupFieldsTest.php includes/admin/class-setup-fields.php
git commit -m "Hold the Setup screen against the library's shape check"
```

---

### Task 3: `Setup_Storage` — read

Every field id's current value, gathered from the store that already owns it. Read first, on its own: a screen that reads correctly and cannot yet write is a screen you can look at.

**Files:**
- Create: `includes/admin/class-setup-storage.php`
- Test: `tests/php/SetupStorageTest.php`

**Interfaces:**
- Produces: `Blueworx_Clubhouse_Setup_Storage::__construct( Blueworx_Clubhouse_Storage $storage )`
- Produces: `read(): array<string,mixed>` — keyed by field id.
- Consumes: `Setup_Fields::PAGE_FIELD_PREFIX`, `Setup_Editor::pages()` (task 5; until then a private copy of `Setup_Controller::visibility_pages()`).

- [ ] **Step 1: Write the failing test**

```php
// tests/php/SetupStorageTest.php
final class SetupStorageTest extends TestCase {

	public function test_it_reads_branding_from_the_branding_store(): void {
		$storage = new Blueworx_Clubhouse_Array_Storage();
		( new Blueworx_Clubhouse_Branding( $storage ) )->set_club_name( 'Ashwood RFC' );

		$values = ( new Blueworx_Clubhouse_Setup_Storage( $storage ) )->read();

		$this->assertSame( 'Ashwood RFC', $values['club_name'] );
	}

	public function test_a_never_saved_page_switch_reads_as_on(): void {
		$values = ( new Blueworx_Clubhouse_Setup_Storage( new Blueworx_Clubhouse_Array_Storage() ) )->read();
		$this->assertTrue( $values['page_visible_home'] );
	}

	public function test_the_menu_reads_as_flat_rows_with_a_nested_flag(): void {
		$storage = new Blueworx_Clubhouse_Array_Storage();
		( new Blueworx_Clubhouse_Menu( $storage ) )->save( array(
			array( 'label' => 'About', 'target' => 'page:about', 'children' => array(
				array( 'label' => 'History', 'target' => 'page:about#history' ),
			) ),
		) );

		$rows = ( new Blueworx_Clubhouse_Setup_Storage( $storage ) )->read()['menu'];

		$this->assertSame(
			array(
				array( 'label' => 'About',   'target' => 'page:about',         'nested' => false ),
				array( 'label' => 'History', 'target' => 'page:about#history', 'nested' => true ),
			),
			$rows
		);
	}
}
```

- [ ] **Step 2: Run it and watch it fail**

Run: `vendor/bin/phpunit --filter SetupStorageTest`
Expected: FAIL, "Class Blueworx_Clubhouse_Setup_Storage not found".

- [ ] **Step 3: Write `read()`**

```php
	/** @return array<string,mixed> */
	public function read(): array {
		$branding = new Blueworx_Clubhouse_Branding( $this->storage );
		$auth     = new Blueworx_Clubhouse_Auth_Settings( $this->storage );
		$mail     = new Blueworx_Clubhouse_Mail_Settings( $this->storage );

		$values = array(
			'look'              => (string) $this->storage->get( 'active_base_look', '' ),
			'club_name'         => $branding->get_club_name(),
			'accent'            => $branding->get_accent(),
			'secondary'         => $branding->get_secondary(),
			'logo'              => $branding->get_logo(),
			'favicon'           => $branding->get_favicon(),
			'facebook'          => $branding->get_facebook_url(),
			'instagram'         => $branding->get_instagram_url(),
			'linkedin'          => $branding->get_linkedin_url(),
			'x'                 => $branding->get_x_url(),
			'post_login'        => $auth->get_post_login(),
			'post_logout'       => $auth->get_post_logout(),
			'mail_from_name'    => $mail->get_from_name(),
			'mail_from_address' => $mail->get_from_address(),
			'menu'              => self::menu_rows( new Blueworx_Clubhouse_Menu( $this->storage ) ),
			'profile_fields'    => ( new Blueworx_Clubhouse_Profile_Store( $this->storage ) )->fields(),
			'demo_active'       => ( new Blueworx_Clubhouse_Demo_State( $this->storage ) )->is_on(),
		);

		$vis = new Blueworx_Clubhouse_Visibility( $this->storage );
		foreach ( Blueworx_Clubhouse_Setup_Editor::pages() as $page ) {
			$values[ Blueworx_Clubhouse_Setup_Fields::PAGE_FIELD_PREFIX . $page['page'] ]
				= $vis->is_page_visible( $page['page'] );
		}

		return $values;
	}

	/**
	 * The two-level menu tree, flattened to rows the library's repeater can
	 * hold. Indent is a flag on the row — a child is the row after its parent
	 * with `nested` on — which is the same thing the old drag handle meant.
	 *
	 * @return array<int,array{label:string,target:string,nested:bool}>
	 */
	private static function menu_rows( Blueworx_Clubhouse_Menu $menu ): array {
		$rows = array();
		foreach ( $menu->tree() as $item ) {
			$rows[] = array( 'label' => $item['label'], 'target' => $item['target'], 'nested' => false );
			foreach ( $item['children'] as $child ) {
				$rows[] = array( 'label' => $child['label'], 'target' => $child['target'], 'nested' => true );
			}
		}
		return $rows;
	}
```

- [ ] **Step 4: Run the tests**

Run: `vendor/bin/phpunit --filter SetupStorageTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add includes/admin/class-setup-storage.php tests/php/SetupStorageTest.php
git commit -m "Read every Setup value from the store that owns it"
```

---

### Task 4: `Setup_Storage` — write

The other half. Each value goes back through the setter it already had, so every sanitiser and side effect this plugin relies on — the page status sync, the theme cache bust — keeps running.

**Files:**
- Modify: `includes/admin/class-setup-storage.php`, `tests/php/SetupStorageTest.php`

**Interfaces:**
- Produces: `write( array $values ): bool`

- [ ] **Step 1: Write the failing tests**

```php
	public function test_writing_a_look_and_an_accent_lands_in_branding(): void {
		$storage = new Blueworx_Clubhouse_Array_Storage();
		( new Blueworx_Clubhouse_Setup_Storage( $storage ) )->write( array(
			'look'   => 'court-side',
			'accent' => '#c6f24e',
		) );

		$this->assertSame( 'court-side', $storage->get( 'active_base_look', '' ) );
		$this->assertSame( '#c6f24e', ( new Blueworx_Clubhouse_Branding( $storage ) )->get_accent() );
	}

	public function test_a_nested_row_becomes_a_child_of_the_row_above_it(): void {
		$storage = new Blueworx_Clubhouse_Array_Storage();
		( new Blueworx_Clubhouse_Setup_Storage( $storage ) )->write( array(
			'menu' => array(
				array( 'label' => 'About',   'target' => 'page:about',         'nested' => false ),
				array( 'label' => 'History', 'target' => 'page:about#history', 'nested' => true ),
			),
		) );

		$tree = ( new Blueworx_Clubhouse_Menu( $storage ) )->tree();
		$this->assertCount( 1, $tree );
		$this->assertSame( 'History', $tree[0]['children'][0]['label'] );
	}

	public function test_a_leading_nested_row_becomes_a_top_level_item(): void {
		$storage = new Blueworx_Clubhouse_Array_Storage();
		( new Blueworx_Clubhouse_Setup_Storage( $storage ) )->write( array(
			'menu' => array( array( 'label' => 'Orphan', 'target' => 'page:home', 'nested' => true ) ),
		) );

		$tree = ( new Blueworx_Clubhouse_Menu( $storage ) )->tree();
		$this->assertSame( 'Orphan', $tree[0]['label'] );
		$this->assertSame( array(), $tree[0]['children'] );
	}

	public function test_removing_a_profile_field_row_leaves_member_answers_alone(): void {
		$storage = new Blueworx_Clubhouse_Array_Storage();
		$profile = new Blueworx_Clubhouse_Profile_Store( $storage );
		$profile->save_fields( array( array( 'key' => 'shirt', 'label' => 'Shirt size', 'type' => 'text' ) ) );
		$profile->save_answers( 7, array( 'shirt' => 'Large' ) );

		( new Blueworx_Clubhouse_Setup_Storage( $storage ) )->write( array( 'profile_fields' => array() ) );

		$this->assertSame( array(), $profile->fields() );
		$this->assertSame( 'Large', $storage->get( 'user_7_' . $profile->meta_key( 'shirt' ), 'Large' ) );
	}

	public function test_a_value_the_screen_did_not_send_is_left_alone(): void {
		$storage = new Blueworx_Clubhouse_Array_Storage();
		( new Blueworx_Clubhouse_Branding( $storage ) )->set_club_name( 'Ashwood RFC' );

		( new Blueworx_Clubhouse_Setup_Storage( $storage ) )->write( array( 'accent' => '#c6f24e' ) );

		$this->assertSame( 'Ashwood RFC', ( new Blueworx_Clubhouse_Branding( $storage ) )->get_club_name() );
	}
```

- [ ] **Step 2: Run and watch them fail**

Run: `vendor/bin/phpunit --filter SetupStorageTest`
Expected: FAIL, "Call to undefined method … ::write()".

- [ ] **Step 3: Write `write()`**

Every branch guarded by `array_key_exists`, so a Content Editor's save — which carries only `menu` — never blanks a field they were not shown. Demo mode is written only when `demo_active` is present, which the library's capability filter already guarantees.

```php
	/** @param array<string,mixed> $values */
	public function write( array $values ): bool {
		$branding = new Blueworx_Clubhouse_Branding( $this->storage );

		if ( array_key_exists( 'look', $values ) ) {
			$registry = Blueworx_Clubhouse_Frontend::registry( $this->storage );
			if ( $registry->has( (string) $values['look'] ) ) {
				$registry->set_active( (string) $values['look'] );
			}
		}
		foreach ( array(
			'accent'    => 'set_accent',
			'secondary' => 'set_secondary',
			'club_name' => 'set_club_name',
			'logo'      => 'set_logo',
			'favicon'   => 'set_favicon',
			'facebook'  => 'set_facebook_url',
			'instagram' => 'set_instagram_url',
			'linkedin'  => 'set_linkedin_url',
			'x'         => 'set_x_url',
		) as $id => $setter ) {
			if ( array_key_exists( $id, $values ) ) {
				$branding->{$setter}( (string) $values[ $id ] );
			}
		}

		$auth = new Blueworx_Clubhouse_Auth_Settings( $this->storage );
		if ( array_key_exists( 'post_login', $values ) ) {
			$auth->set_post_login( (string) $values['post_login'] );
		}
		if ( array_key_exists( 'post_logout', $values ) ) {
			$auth->set_post_logout( (string) $values['post_logout'] );
		}

		$mail = new Blueworx_Clubhouse_Mail_Settings( $this->storage );
		if ( array_key_exists( 'mail_from_name', $values ) ) {
			$mail->set_from_name( (string) $values['mail_from_name'] );
		}
		if ( array_key_exists( 'mail_from_address', $values ) ) {
			$mail->set_from_address( (string) $values['mail_from_address'] );
		}

		$vis = new Blueworx_Clubhouse_Visibility( $this->storage );
		foreach ( Blueworx_Clubhouse_Setup_Editor::pages() as $page ) {
			$id = Blueworx_Clubhouse_Setup_Fields::PAGE_FIELD_PREFIX . $page['page'];
			if ( array_key_exists( $id, $values ) ) {
				$vis->set_page_visible( $page['page'], (bool) $values[ $id ] );
			}
		}

		if ( array_key_exists( 'menu', $values ) && is_array( $values['menu'] ) ) {
			( new Blueworx_Clubhouse_Menu( $this->storage ) )->save( self::menu_tree( $values['menu'] ) );
		}

		// A removed row takes the question off the form. It never erases what
		// members already answered — a row you can drag out by accident must
		// not be able to wipe member data. Profile_Store::forget() still does
		// that, deliberately, from the members screen.
		if ( array_key_exists( 'profile_fields', $values ) && is_array( $values['profile_fields'] ) ) {
			( new Blueworx_Clubhouse_Profile_Store( $this->storage ) )->save_fields( $values['profile_fields'] );
		}

		if ( array_key_exists( 'demo_active', $values ) ) {
			( new Blueworx_Clubhouse_Demo_State( $this->storage ) )->set( (bool) $values['demo_active'] );
		}

		( new Blueworx_Clubhouse_Theme_Cache( $this->storage ) )->invalidate();
		return true;
	}

	/**
	 * Rows back to a two-level tree. A nested row with nothing above it is
	 * promoted rather than dropped: an owner who over-indented should lose the
	 * nesting, not the item — the same rule Menu::sanitise() already applies to
	 * a third level.
	 *
	 * @param array<int,array<string,mixed>> $rows
	 */
	private static function menu_tree( array $rows ): array {
		$tree = array();
		foreach ( $rows as $row ) {
			$item = array( 'label' => (string) ( $row['label'] ?? '' ), 'target' => (string) ( $row['target'] ?? '' ) );
			if ( ! empty( $row['nested'] ) && array() !== $tree ) {
				$tree[ array_key_last( $tree ) ]['children'][] = $item;
				continue;
			}
			$item['children'] = array();
			$tree[]           = $item;
		}
		return $tree;
	}
```

- [ ] **Step 4: Run the tests**

Run: `vendor/bin/phpunit --filter SetupStorageTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add includes/admin/class-setup-storage.php tests/php/SetupStorageTest.php
git commit -m "Write every Setup value back through the setter that owns it"
```

---

### Task 5: Register the screen

The screen goes live: the Clubhouse menu item opens the library's editor instead of the hand-built form. The old controller still exists at this point and is deleted in task 9 — nothing points at it after this task.

**Files:**
- Create: `includes/admin/class-setup-editor.php`
- Modify: `includes/bootstrap.php`
- Test: `tests/php/SetupEditorTest.php`

**Interfaces:**
- Produces: `Blueworx_Clubhouse_Setup_Editor::PAGE_SLUG = 'clubhouse-setup'` — the same slug the old controller used, so every existing link, submenu parent and redirect still lands.
- Produces: `register(): void`, `screen(): array`, `pages(): array`, `validate( array $values ): array`.
- Consumes: `Setup_Fields::tabs()`, `Setup_Storage::read()/write()`.

- [ ] **Step 1: Write the failing test**

```php
// tests/php/SetupEditorTest.php
final class SetupEditorTest extends TestCase {

	public function test_the_slug_is_the_one_every_link_already_uses(): void {
		$this->assertSame( 'clubhouse-setup', Blueworx_Clubhouse_Setup_Editor::PAGE_SLUG );
	}

	public function test_the_screen_is_reachable_by_a_content_editor(): void {
		$this->assertSame(
			Blueworx_Clubhouse_Owner_Capabilities::CONTENT_CAP,
			Blueworx_Clubhouse_Setup_Editor::screen()['capability']
		);
	}

	public function test_an_illegible_accent_is_refused_with_a_message_on_the_field(): void {
		$errors = Blueworx_Clubhouse_Setup_Editor::validate( array( 'look' => 'court-side', 'accent' => '#f7f7f7' ) );
		$this->assertArrayHasKey( 'accent', $errors );
	}

	public function test_a_legible_accent_saves(): void {
		$this->assertSame(
			array(),
			Blueworx_Clubhouse_Setup_Editor::validate( array( 'look' => 'court-side', 'accent' => '#c6f24e' ) )
		);
	}

	public function test_a_low_contrast_secondary_is_not_refused(): void {
		$errors = Blueworx_Clubhouse_Setup_Editor::validate(
			array( 'look' => 'court-side', 'accent' => '#c6f24e', 'secondary' => '#8a8a8a' )
		);
		$this->assertArrayNotHasKey( 'secondary', $errors );
	}
}
```

- [ ] **Step 2: Run it and watch it fail**

Run: `vendor/bin/phpunit --filter SetupEditorTest`
Expected: FAIL, "Class Blueworx_Clubhouse_Setup_Editor not found".

- [ ] **Step 3: Write `Setup_Editor`**

`register()` adds the top-level menu at priority 1 — ahead of the library's own `admin_menu` hook, for the same reason the old controller did: the library's submenus (Global content, and the fourteen hidden record editors) name this slug as their parent, and WordPress only resolves a submenu's hook name once its parent exists. Registered any later, Global content opens to "Sorry, you are not allowed to access this page" even for an owner who holds the capability.

Link suggestions are stamped onto the Menu repeater's `target` cell here, not in `Setup_Fields` — `Link_Catalogue::targets()` issues real queries, so it belongs at the one place that runs at a known time, exactly as `Page_Editors::with_suggestions()` does.

```php
	public static function screen(): array {
		$storage = new Blueworx_Clubhouse_Options_Storage();
		$bridge  = new Blueworx_Clubhouse_Setup_Storage( $storage );
		$can     = array(
			'setup' => current_user_can( Blueworx_Clubhouse_Owner_Capabilities::SETUP_CAP ),
			'menu'  => current_user_can( Blueworx_Clubhouse_Owner_Capabilities::CONTENT_CAP ),
			'demo'  => current_user_can( 'manage_options' ),
		);
		return array(
			'slug'       => self::PAGE_SLUG,
			'title'      => 'Clubhouse Setup',
			'menu_title' => 'Clubhouse',
			'eyebrow'    => 'Clubhouse',
			'lede'       => 'How your site looks, which pages it shows, and what it asks your members.',
			'capability' => Blueworx_Clubhouse_Owner_Capabilities::CONTENT_CAP,
			'store'      => 'option',
			'read'       => static fn( int $id ): array => $bridge->read(),
			'write'      => static fn( array $values, int $id ): bool => $bridge->write( $values ),
			'validate'   => array( self::class, 'validate' ),
			'tabs'       => self::with_suggestions(
				Blueworx_Clubhouse_Setup_Fields::tabs( $can, self::looks( $storage ), self::pages() )
			),
		);
	}

	/**
	 * The one rule the library cannot know: an accent has to stay legible on
	 * the look it sits on. Refused for the primary, which carries every call
	 * to action; the secondary is left to the club, with a line of help under
	 * the field rather than a refusal — a club that insists on its real brand
	 * colour there should be told, not overruled, and every derived token is
	 * legibility-clamped anyway.
	 *
	 * @param array<string,mixed> $values
	 * @return array<string,string>
	 */
	public static function validate( array $values ): array {
		if ( ! array_key_exists( 'accent', $values ) ) {
			return array();
		}
		$registry = Blueworx_Clubhouse_Frontend::registry( new Blueworx_Clubhouse_Options_Storage() );
		$look     = $registry->get( (string) ( $values['look'] ?? '' ) ) ?? new Blueworx_Clubhouse_Court_Side();
		$accent   = sanitize_hex_color( (string) $values['accent'] );
		if ( '' === (string) $accent ) {
			return array( 'accent' => 'The accent colour must be a 6-digit hex value like #c6f24e.' );
		}
		if ( ! Blueworx_Clubhouse_Color_Engine::accent_is_legible_for( $look, (string) $accent ) ) {
			return array( 'accent' => 'That accent is too low in contrast for the chosen look. Pick a stronger colour.' );
		}
		return array();
	}
```

`pages()` is the old `Setup_Controller::visibility_pages()`, moved here verbatim, including the Home `''` → `'home'` remap that everything storing visibility depends on.

- [ ] **Step 4: Run the tests**

Run: `vendor/bin/phpunit --filter SetupEditorTest`
Expected: PASS.

- [ ] **Step 5: Boot it**

Add the three new classes to `includes/bootstrap.php` and call `Setup_Editor::register()` where `Setup_Controller::register()` was called. Leave the old class file in place for now — task 9 deletes it.

- [ ] **Step 6: Look at it**

Run: `npm run wp:up`, sign in as the owner, open Clubhouse.
Expected: six tabs, one save bar, every field carrying its current value.

- [ ] **Step 7: Commit**

```bash
git add includes/admin/class-setup-editor.php includes/bootstrap.php tests/php/SetupEditorTest.php
git commit -m "Open Clubhouse Setup on the page editor library"
```

---

### Task 6: The page controller, and one fact about a page

Visibility is not a setting — a switch **is** its page's status. Prove there is only one fact: publishing a page from WordPress's own Pages list makes the switch read as on, without anything else being saved.

**Files:**
- Modify: `includes/admin/class-setup-storage.php`, `includes/content/class-visibility.php`
- Test: `tests/php/SetupPageControllerTest.php`, `tests/setup-editor.spec.js`

**Interfaces:**
- Produces: `Blueworx_Clubhouse_Visibility::page_status_is_visible( string $page ): ?bool` — `null` when there is no page behind the key, otherwise whether that page is published.

- [ ] **Step 1: Write the failing test**

```php
// tests/php/SetupPageControllerTest.php
	public function test_the_switch_reads_the_pages_real_status(): void {
		$storage = new Blueworx_Clubhouse_Array_Storage();
		$this->given_a_club_page( 'about', 'draft' );

		$values = ( new Blueworx_Clubhouse_Setup_Storage( $storage ) )->read();

		$this->assertFalse( $values['page_visible_about'] );
	}

	public function test_publishing_a_page_elsewhere_turns_the_switch_on(): void {
		$storage = new Blueworx_Clubhouse_Array_Storage();
		$this->given_a_club_page( 'about', 'draft' );
		$this->given_a_club_page( 'about', 'publish' );

		$this->assertTrue( ( new Blueworx_Clubhouse_Setup_Storage( $storage ) )->read()['page_visible_about'] );
	}

	public function test_a_site_with_no_pages_yet_falls_back_to_the_stored_flag(): void {
		$storage = new Blueworx_Clubhouse_Array_Storage();
		( new Blueworx_Clubhouse_Visibility( $storage ) )->set_page_visible( 'about', false );

		$this->assertFalse( ( new Blueworx_Clubhouse_Setup_Storage( $storage ) )->read()['page_visible_about'] );
	}
```

`given_a_club_page()` seeds the page through the same stub the phase 3 tests use (`tests/php/wp-stubs.php`) — a post id for the slug and a status for that id.

- [ ] **Step 2: Run and watch it fail**

Run: `vendor/bin/phpunit --filter SetupPageControllerTest`
Expected: FAIL — `read()` currently asks the stored option, which still says the page is visible.

- [ ] **Step 3: Read the page, not the copy**

Add `page_status_is_visible()` to `Visibility`. `Setup_Storage::read()` prefers it and falls back to `is_page_visible()` when it is `null`. The option keeps being written on save, because `set_page_visible()` writes both and the front end still reads it — this task changes which of the two the *switch* believes, and nothing else.

- [ ] **Step 4: Run the tests**

Run: `vendor/bin/phpunit --filter SetupPageControllerTest`
Expected: PASS.

- [ ] **Step 5: Prove it in a browser**

Add to `tests/setup-editor.spec.js` (`@wordpress`, signed in as the owner): switch About off in Setup, save, then check the Pages list shows About as a draft and the page's own address returns a 404.

Run: `npm run test:wp -- setup-editor.spec.js`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add includes/admin/class-setup-storage.php includes/content/class-visibility.php tests/php/SetupPageControllerTest.php tests/setup-editor.spec.js
git commit -m "A visibility switch reads the page's real status"
```

---

### Task 7: One save bar, and the menu with it

[#285](../../../../issues/285) closes here: the Menu tab had its own "Save menu" button only because it was a second form inside somebody else's screen. Under the library there is one save bar per screen, whatever tab is showing.

**Files:**
- Modify: `tests/menu-editor.spec.js`

- [ ] **Step 1: Rewrite the existing menu spec against the new screen**

`tests/menu-editor.spec.js` drives the old panel — its selectors and its second save button are both gone. Rewrite it against the library's controls: add a row, type a label, pick a target from the suggestions, tick "show under the item above", save once, reload, and assert the site's nav shows the new child.

- [ ] **Step 2: Run it and watch it fail**

Run: `npm run test:wp -- menu-editor.spec.js`
Expected: FAIL on the old selectors before the rewrite.

- [ ] **Step 3: Assert there is exactly one save bar**

```js
await expect(page.locator('.bw-savebar')).toHaveCount(1);
```

- [ ] **Step 4: Run the browser suite**

Run: `npm run test:wp`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add tests/menu-editor.spec.js
git commit -m "One save bar on Setup, menu included (#285)"
```

---

### Task 8: The owner's dashboard

The dashboard widget renders the whole Setup form today, which a library screen cannot do. It becomes a short welcome panel.

**Files:**
- Create: `includes/admin/class-owner-welcome.php`
- Modify: `includes/admin/class-owner-role.php`
- Test: `tests/php/OwnerWelcomeTest.php`

- [ ] **Step 1: Write the failing test**

```php
	public function test_the_widget_links_to_setup_pages_and_members(): void {
		$html = Blueworx_Clubhouse_Owner_Welcome::render();
		$this->assertStringContainsString( 'page=clubhouse-setup', $html );
		$this->assertStringContainsString( 'edit.php?post_type=page', $html );
		$this->assertStringContainsString( 'users.php', $html );
	}

	public function test_it_is_not_an_editor_screen(): void {
		$html = Blueworx_Clubhouse_Owner_Welcome::render();
		$this->assertStringNotContainsString( 'bw-savebar', $html );
	}
```

- [ ] **Step 2: Run it**

Run: `vendor/bin/phpunit --filter OwnerWelcomeTest`
Expected: FAIL, class not found.

- [ ] **Step 3: Write the panel**

Three links with a line each, in the design system's classes, no form and no save bar — §5's rule: a screen that reads and points must not carry `bw-tabs` and `bw-savebar` together, or the adherence check refuses it.

- [ ] **Step 4: Point the dashboard at it**

`Owner_Role::render_dashboard()` calls `Owner_Welcome::render()`.

- [ ] **Step 5: Run the tests, and look at the dashboard as the owner**

Run: `vendor/bin/phpunit` then `npm run test:wp -- setup-editor.spec.js`
Expected: PASS, and an owner signing in lands on a dashboard with three working links.

- [ ] **Step 6: Commit**

```bash
git add includes/admin/class-owner-welcome.php includes/admin/class-owner-role.php tests/php/OwnerWelcomeTest.php
git commit -m "The owner's dashboard points at Setup instead of embedding it"
```

---

### Task 9: Delete the old Setup

The release that replaces a thing deletes it. Nothing is kept beside the new screen.

**Files:**
- Delete: `includes/admin/class-setup-controller.php`, `class-setup-screen.php`, `class-setup-progress.php`, `class-menu-controller.php`, `class-menu-panel.php`, `assets/css/admin-setup.css`, `assets/js/admin-setup.js`, and the tests that name them.
- Modify: `includes/bootstrap.php`, `includes/admin/class-admin-assets.php`, `includes/admin/class-access-controller.php`, and every file naming `Setup_Controller::PAGE_SLUG`.

- [ ] **Step 1: Find every reference**

Run: `grep -rn "Setup_Controller\|Setup_Screen\|Setup_Progress\|Menu_Controller\|Menu_Panel\|admin-setup" includes tests bin templates`
Expected: a list. `Setup_Controller::PAGE_SLUG` is the common one — the fourteen record editors name it as their parent. Each becomes `Setup_Editor::PAGE_SLUG`, which is the same string, so nothing about the address changes.

- [ ] **Step 2: Delete, and repoint**

- [ ] **Step 3: Run everything**

Run: `vendor/bin/phpunit` then `npm run wp:up && npm run test:wp`
Expected: PASS. A club page editor must still open from the Pages list — that is the phase 3 regression this touches, so `owner-edits-a-club-page.spec.js` passing is the check that matters most here.

- [ ] **Step 4: The design-system check**

Run: the sync/adherence step CI runs.
Expected: clean. `admin-setup.css` was the last hand-written Setup markup, which is what has kept that check on warn since phase 2. Flip it from warn to blocking in this PR, which is what phase 2 deferred to here.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "Delete the hand-built Setup screen (#282)"
```

---

### Task 10: Browser coverage, version, changelog, docs

**Files:**
- Modify: `tests/setup-editor.spec.js`, `blueworx-labs-clubhouse.php`, `CHANGELOG.md`, `docs/priorities.md`

- [ ] **Step 1: Finish the browser spec**

Signed in as the owner, in one file: the six tabs are there in order; changing a field wakes the save bar; switching tab keeps the dirty state; saving an illegible accent shows the error on the field and writes nothing; saving a legible one leaves the screen clean. Then, signed in as a Content Editor: the Menu tab and nothing else.

- [ ] **Step 2: Run the whole suite**

Run: `vendor/bin/phpunit && npm run wp:up && npm run test:wp`
Expected: PASS. Paste the counts into the PR — a claim of green without them is not evidence.

- [ ] **Step 3: Bump and write the changelog**

Minor bump, with the changelog written in the club's words: Setup looks and behaves like the rest of Clubhouse, the menu saves with everything else, and the member settings are split into Members and Settings.

- [ ] **Step 4: Update the priority list**

Phase 4 done; phase 5 next and unblocked. Close #282, #283, #284 and #285 as the PR merges, not afterwards — that is what let the list drift last time.

- [ ] **Step 5: Commit and open the PR**

```bash
git add -A
git commit -m "Setup rebuilt on the page editor library"
gh pr create
```

---

## Self-review

**Spec coverage.** §3's six tabs, their order and the Members/Settings split — tasks 1 and 2. §3.1 the page controller — task 6. §3.2 the menu repeater and the second save bar — tasks 1, 4 and 7. §6 the club's look leaving wp-admin, and both assets — task 9. §9's testing — tasks 6, 7 and 10. §7's migration — **not applicable, deliberately**: nothing moves in the database, because the screen reads and writes through the stores that already own every value. That is the one place this plan departs from the spec's per-phase pattern, and it removes work rather than adding it.

**What this plan does not do.** It leaves `Setup_Sections` in place (it now serves `Page_Fields` and `Import_Sections`), and it leaves the front end untouched.
