# Admin Phase 4 — Clubhouse Owner Role, Admin Lockdown & Dashboard Takeover — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Register a curated `clubhouse_owner` WordPress role that lands owners on the Setup screen at login, locks the admin menu to an allowlist (hidden *and* capability-denied), groups the six collection CPTs under one "Content" menu, and cleans up predictably (kept on deactivate, removed on uninstall).

**Architecture:** A pure `Owner_Capabilities` map (caps + menu allowlist + admin-cap grants) is the single source of truth; a thin `Owner_Role` glue class registers the role, grants the `manage_clubhouse` cap to administrators, and — gated on the current user actually being an owner — locks the menu and replaces the dashboard body with the reused `Setup_Screen`. The Setup screen's capability switches from `manage_options` to `manage_clubhouse`.

**Tech Stack:** PHP 8.2, WordPress plugin, PHPUnit (WP-free via `function_exists`-guarded stubs in `tests/php/wp-stubs.php`), PHP_CodeSniffer (`composer lint`).

## Global Constraints

- **Requires PHP:** 8.2. Every PHP file starts with `declare(strict_types=1);` and the `if ( ! defined( 'ABSPATH' ) ) { exit; }` guard.
- **Pure vs glue split:** `Owner_Capabilities` is WP-free (core PHP only), unit-tested. Only `Owner_Role`, `Setup_Controller`, `Collection_Types`, the plugin bootstrap, and `uninstall.php` may call WordPress functions.
- **Role name:** `clubhouse_owner`; display "Clubhouse Owner". **Custom capability:** `manage_clubhouse`, granted to `clubhouse_owner` **and** `administrator` on activation.
- **Owner caps granted:** `read`, `manage_clubhouse`, `upload_files`, `list_users`, `moderate_comments`, and the post caps `edit_posts`, `edit_others_posts`, `edit_published_posts`, `publish_posts`, `delete_posts`, `delete_others_posts`, `delete_published_posts`, `read_private_posts`.
- **Owner caps NEVER granted (asserted absent):** `manage_options`, `edit_theme_options`, `switch_themes`, `activate_plugins`, `install_plugins`, `install_themes`, `update_core`, `update_plugins`, `update_themes`, `edit_pages`, `edit_others_pages`, `publish_pages`, `create_users`, `edit_users`, `delete_users`, `promote_users`.
- **Menu allowlist (top-level slugs the owner keeps):** `index.php`, `clubhouse-content`, `upload.php`, `edit.php`, `edit-comments.php`, `users.php`, `profile.php`. Everything else is removed for the role.
- **Role lifecycle:** created/updated on activation, kept on deactivate, removed only on uninstall (`uninstall.php`).
- **Every owner-specific runtime hook is gated on `Owner_Role::is_owner( wp_get_current_user() )`** so nothing leaks into a full admin's experience.
- **Version:** bump `0.18.0 → 0.19.0` (minor) in `blueworx-labs-clubhouse.php` (header + `BLUEWORX_LABS_CLUBHOUSE_VERSION`) and `package.json`, with a matching `CHANGELOG.md` entry — final task.
- **Isolation:** this branch (`admin-phase-4-owner-role`) is executed in a dedicated git worktree at `C:\Users\LukeMcfarland\Documents\GitHub\blueworx_labs_clubhouse-phase4` (a second session works `admin-demo-mode` in the primary copy). Run all commands from the worktree root: tests `./vendor/bin/phpunit`, single test `./vendor/bin/phpunit --filter TestName`, lint `composer lint`.
- **Commit** after each task's tests pass. Base is `main` @ v0.18.0.

---

## File Structure

**New files**
- `includes/admin/class-owner-capabilities.php` — pure caps map + menu allowlist + admin-cap grants (Task 1).
- `includes/admin/class-owner-role.php` — role registration, menu lockdown, dashboard takeover glue (Task 2, extended in 5/6).
- `uninstall.php` — plugin-root uninstall handler calling `Owner_Role::uninstall()` (Task 7).
- `tests/php/OwnerCapabilitiesTest.php`, `tests/php/OwnerRoleTest.php` (Tasks 1/2, extended in 5/6).

**Modified files**
- `includes/bootstrap.php` — require `Owner_Capabilities` (Task 1).
- `blueworx-labs-clubhouse.php` + `tests/php/bootstrap.php` — require `Owner_Role` (Task 2); init + activation + content-menu wiring (Tasks 4/7); version bump (Task 8).
- `includes/admin/class-setup-controller.php` — `CAPABILITY` → `manage_clubhouse`; extract `screen_html()` (Task 3).
- `includes/collections/class-collection-types.php` — `show_in_menu` → the Content parent slug + `register_content_menu()` (Task 4).
- `tests/php/wp-stubs.php` — add role/menu/dashboard stubs (Tasks 2/4/5/6).
- `tests/php/CollectionTypesTest.php`, `tests/php/SetupControllerTest.php` — updated assertions (Tasks 4/3).
- `CHANGELOG.md` (Task 8).

---

## Task 1: `Owner_Capabilities` — pure caps map, menu allowlist, admin-cap grants

**Files:**
- Create: `includes/admin/class-owner-capabilities.php`
- Modify: `includes/bootstrap.php` (require after `class-setup-screen.php`)
- Test: `tests/php/OwnerCapabilitiesTest.php`

**Interfaces:**
- Produces:
  - `Blueworx_Clubhouse_Owner_Capabilities::ROLE` = `'clubhouse_owner'`, `::DISPLAY` = `'Clubhouse Owner'`, `::SETUP_CAP` = `'manage_clubhouse'`
  - `::capabilities(): array<string,bool>`
  - `::denied(): array<int,string>`
  - `::admin_cap_grants(): array<int,string>`
  - `::menu_allowlist(): array<int,string>`

- [ ] **Step 1: Write the failing test**

Create `tests/php/OwnerCapabilitiesTest.php`:

```php
<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class OwnerCapabilitiesTest extends TestCase {

	public function test_role_and_cap_names(): void {
		$this->assertSame( 'clubhouse_owner', Blueworx_Clubhouse_Owner_Capabilities::ROLE );
		$this->assertSame( 'Clubhouse Owner', Blueworx_Clubhouse_Owner_Capabilities::DISPLAY );
		$this->assertSame( 'manage_clubhouse', Blueworx_Clubhouse_Owner_Capabilities::SETUP_CAP );
	}

	public function test_grants_the_essential_capabilities(): void {
		$caps = Blueworx_Clubhouse_Owner_Capabilities::capabilities();
		foreach ( array( 'read', 'manage_clubhouse', 'upload_files', 'list_users', 'moderate_comments', 'edit_posts', 'edit_others_posts', 'publish_posts', 'delete_posts', 'read_private_posts' ) as $cap ) {
			$this->assertArrayHasKey( $cap, $caps );
			$this->assertTrue( $caps[ $cap ] );
		}
	}

	public function test_never_grants_a_denied_capability(): void {
		$caps = Blueworx_Clubhouse_Owner_Capabilities::capabilities();
		foreach ( Blueworx_Clubhouse_Owner_Capabilities::denied() as $cap ) {
			$this->assertArrayNotHasKey( $cap, $caps, "owner must not be granted {$cap}" );
		}
	}

	public function test_denied_list_covers_the_dangerous_caps(): void {
		$denied = Blueworx_Clubhouse_Owner_Capabilities::denied();
		foreach ( array( 'manage_options', 'activate_plugins', 'edit_theme_options', 'install_plugins', 'update_core', 'edit_pages', 'create_users', 'promote_users', 'delete_users' ) as $cap ) {
			$this->assertContains( $cap, $denied );
		}
	}

	public function test_admin_cap_grants_include_the_setup_cap(): void {
		$this->assertContains( 'manage_clubhouse', Blueworx_Clubhouse_Owner_Capabilities::admin_cap_grants() );
	}

	public function test_menu_allowlist_is_exactly_the_owner_surfaces(): void {
		$this->assertSame(
			array( 'index.php', 'clubhouse-content', 'upload.php', 'edit.php', 'edit-comments.php', 'users.php', 'profile.php' ),
			Blueworx_Clubhouse_Owner_Capabilities::menu_allowlist()
		);
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit --filter OwnerCapabilitiesTest`
Expected: FAIL with "Class 'Blueworx_Clubhouse_Owner_Capabilities' not found".

- [ ] **Step 3: Write minimal implementation**

Create `includes/admin/class-owner-capabilities.php`:

```php
<?php
// includes/admin/class-owner-capabilities.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single source of truth for the clubhouse_owner role: the exact capability map,
 * the capabilities that must never be granted (asserted in tests + used nowhere
 * else), the caps administrators also receive, and the admin-menu allowlist the
 * owner keeps. Pure — no WordPress. Consumed by Owner_Role and asserted directly.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Owner_Capabilities {

	public const ROLE      = 'clubhouse_owner';
	public const DISPLAY   = 'Clubhouse Owner';
	public const SETUP_CAP = 'manage_clubhouse';

	/**
	 * The exact capability map for the role. The post caps cover both the six
	 * collection CPTs (default 'post' capability type) and the native blog.
	 *
	 * @return array<string,bool>
	 */
	public static function capabilities(): array {
		return array(
			'read'                   => true,
			self::SETUP_CAP          => true,
			'upload_files'           => true,
			'list_users'             => true,
			'moderate_comments'      => true,
			'edit_posts'             => true,
			'edit_others_posts'      => true,
			'edit_published_posts'   => true,
			'publish_posts'          => true,
			'delete_posts'           => true,
			'delete_others_posts'    => true,
			'delete_published_posts' => true,
			'read_private_posts'     => true,
		);
	}

	/** Capabilities the owner must never hold. @return array<int,string> */
	public static function denied(): array {
		return array(
			'manage_options', 'edit_theme_options', 'switch_themes', 'activate_plugins',
			'install_plugins', 'install_themes', 'update_core', 'update_plugins', 'update_themes',
			'edit_pages', 'edit_others_pages', 'publish_pages',
			'create_users', 'edit_users', 'delete_users', 'promote_users',
		);
	}

	/** Caps added to the administrator role on activation (removed on uninstall). @return array<int,string> */
	public static function admin_cap_grants(): array {
		return array( self::SETUP_CAP );
	}

	/** Top-level admin-menu slugs the owner keeps; everything else is removed. @return array<int,string> */
	public static function menu_allowlist(): array {
		return array( 'index.php', 'clubhouse-content', 'upload.php', 'edit.php', 'edit-comments.php', 'users.php', 'profile.php' );
	}
}
```

- [ ] **Step 4: Wire into the runtime loader**

In `includes/bootstrap.php`, after `require_once __DIR__ . '/admin/class-setup-screen.php';` add:

```php
require_once __DIR__ . '/admin/class-owner-capabilities.php';
```

- [ ] **Step 5: Run test to verify it passes**

Run: `./vendor/bin/phpunit --filter OwnerCapabilitiesTest`
Expected: PASS (6 tests).

- [ ] **Step 6: Commit**

```bash
git add includes/admin/class-owner-capabilities.php includes/bootstrap.php tests/php/OwnerCapabilitiesTest.php
git commit -m "feat: Owner_Capabilities pure caps map, deny-list and menu allowlist"
```

---

## Task 2: `Owner_Role` — activate / uninstall / is_owner (role registration)

**Files:**
- Create: `includes/admin/class-owner-role.php`
- Modify: `blueworx-labs-clubhouse.php` (require), `tests/php/bootstrap.php` (require), `tests/php/wp-stubs.php` (role stubs)
- Test: `tests/php/OwnerRoleTest.php`

**Interfaces:**
- Consumes: `Owner_Capabilities::ROLE/DISPLAY/capabilities()/admin_cap_grants()` (Task 1).
- Produces:
  - `Blueworx_Clubhouse_Owner_Role::activate(): void`
  - `::uninstall(): void`
  - `::is_owner( $user ): bool`

- [ ] **Step 1: Add role stubs to `wp-stubs.php`**

In `tests/php/wp-stubs.php`, add a stub role registry. First, extend `wp_stub_reset()` — change its body to also seed roles (add this line inside `wp_stub_reset`, after the existing resets):

```php
	$GLOBALS['wp_stub_roles'] = array( 'administrator' => array( 'display' => 'Administrator', 'caps' => array() ) );
```

Also add the same initialiser near the top of the file with the other `$GLOBALS` initialisers:

```php
$GLOBALS['wp_stub_roles'] = array( 'administrator' => array( 'display' => 'Administrator', 'caps' => array() ) );
```

Then add these guarded functions + a stub role class at the end of the file:

```php
if ( ! class_exists( 'Blueworx_Stub_Role' ) ) {
	final class Blueworx_Stub_Role {
		public string $name;
		public function __construct( string $name ) { $this->name = $name; }
		public function add_cap( string $cap, bool $grant = true ): void {
			$GLOBALS['wp_stub_roles'][ $this->name ]['caps'][ $cap ] = $grant;
			wp_stub_record( 'role_add_cap', array( $this->name, $cap ) );
		}
		public function remove_cap( string $cap ): void {
			unset( $GLOBALS['wp_stub_roles'][ $this->name ]['caps'][ $cap ] );
			wp_stub_record( 'role_remove_cap', array( $this->name, $cap ) );
		}
	}
}
if ( ! function_exists( 'add_role' ) ) {
	function add_role( $role, $display, $caps = array() ) {
		$GLOBALS['wp_stub_roles'][ $role ] = array( 'display' => $display, 'caps' => $caps );
		wp_stub_record( 'add_role', array( $role, $display, $caps ) );
		return new Blueworx_Stub_Role( $role );
	}
}
if ( ! function_exists( 'remove_role' ) ) {
	function remove_role( $role ) { unset( $GLOBALS['wp_stub_roles'][ $role ] ); wp_stub_record( 'remove_role', array( $role ) ); }
}
if ( ! function_exists( 'get_role' ) ) {
	function get_role( $role ) { return isset( $GLOBALS['wp_stub_roles'][ $role ] ) ? new Blueworx_Stub_Role( $role ) : null; }
}
```

- [ ] **Step 2: Write the failing test**

Create `tests/php/OwnerRoleTest.php`:

```php
<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class OwnerRoleTest extends TestCase {

	protected function setUp(): void {
		wp_stub_reset();
	}

	public function test_activate_registers_the_role_with_the_capability_map(): void {
		Blueworx_Clubhouse_Owner_Role::activate();
		$this->assertArrayHasKey( 'clubhouse_owner', $GLOBALS['wp_stub_roles'] );
		$caps = $GLOBALS['wp_stub_roles']['clubhouse_owner']['caps'];
		$this->assertTrue( $caps['manage_clubhouse'] );
		$this->assertTrue( $caps['edit_posts'] );
		$this->assertArrayNotHasKey( 'manage_options', $caps );
	}

	public function test_activate_grants_the_setup_cap_to_administrator(): void {
		Blueworx_Clubhouse_Owner_Role::activate();
		$this->assertTrue( $GLOBALS['wp_stub_roles']['administrator']['caps']['manage_clubhouse'] );
	}

	public function test_uninstall_removes_the_role_and_the_admin_grant(): void {
		Blueworx_Clubhouse_Owner_Role::activate();
		Blueworx_Clubhouse_Owner_Role::uninstall();
		$this->assertArrayNotHasKey( 'clubhouse_owner', $GLOBALS['wp_stub_roles'] );
		$this->assertArrayNotHasKey( 'manage_clubhouse', $GLOBALS['wp_stub_roles']['administrator']['caps'] );
	}

	public function test_is_owner_true_only_for_a_user_with_the_role(): void {
		$owner = (object) array( 'roles' => array( 'clubhouse_owner' ) );
		$admin = (object) array( 'roles' => array( 'administrator' ) );
		$this->assertTrue( Blueworx_Clubhouse_Owner_Role::is_owner( $owner ) );
		$this->assertFalse( Blueworx_Clubhouse_Owner_Role::is_owner( $admin ) );
		$this->assertFalse( Blueworx_Clubhouse_Owner_Role::is_owner( null ) );
	}
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `./vendor/bin/phpunit --filter OwnerRoleTest`
Expected: FAIL with "Class 'Blueworx_Clubhouse_Owner_Role' not found".

- [ ] **Step 4: Write minimal implementation**

Create `includes/admin/class-owner-role.php`:

```php
<?php
// includes/admin/class-owner-role.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress glue for the clubhouse_owner role: registers/removes the role and the
 * administrator cap grant on activation/uninstall, and (in later steps) locks the
 * admin menu and takes over the dashboard for owners. All runtime hooks are gated
 * on is_owner() so a full admin's experience is never touched.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Owner_Role {

	public static function activate(): void {
		remove_role( Blueworx_Clubhouse_Owner_Capabilities::ROLE ); // idempotent: re-add with the current caps.
		add_role(
			Blueworx_Clubhouse_Owner_Capabilities::ROLE,
			Blueworx_Clubhouse_Owner_Capabilities::DISPLAY,
			Blueworx_Clubhouse_Owner_Capabilities::capabilities()
		);
		$admin = get_role( 'administrator' );
		if ( null !== $admin ) {
			foreach ( Blueworx_Clubhouse_Owner_Capabilities::admin_cap_grants() as $cap ) {
				$admin->add_cap( $cap );
			}
		}
	}

	public static function uninstall(): void {
		remove_role( Blueworx_Clubhouse_Owner_Capabilities::ROLE );
		$admin = get_role( 'administrator' );
		if ( null !== $admin ) {
			foreach ( Blueworx_Clubhouse_Owner_Capabilities::admin_cap_grants() as $cap ) {
				$admin->remove_cap( $cap );
			}
		}
	}

	/** True iff the given WP_User-like object carries the owner role. */
	public static function is_owner( $user ): bool {
		return is_object( $user ) && isset( $user->roles ) && is_array( $user->roles )
			&& in_array( Blueworx_Clubhouse_Owner_Capabilities::ROLE, $user->roles, true );
	}
}
```

- [ ] **Step 5: Require in both loaders**

In `blueworx-labs-clubhouse.php`, after `require_once ... 'includes/admin/class-setup-controller.php';` add:

```php
require_once BLUEWORX_LABS_CLUBHOUSE_DIR . 'includes/admin/class-owner-role.php';
```

In `tests/php/bootstrap.php`, after `require_once ... '/includes/admin/class-setup-controller.php';` add:

```php
require_once dirname( __DIR__, 2 ) . '/includes/admin/class-owner-role.php';
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `./vendor/bin/phpunit --filter OwnerRoleTest`
Expected: PASS (4 tests). Then the full suite to confirm the new stubs didn't break anything: `./vendor/bin/phpunit`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add includes/admin/class-owner-role.php blueworx-labs-clubhouse.php tests/php/bootstrap.php tests/php/wp-stubs.php tests/php/OwnerRoleTest.php
git commit -m "feat: Owner_Role registers/removes the role and admin cap grant"
```

---

## Task 3: Switch the Setup screen to `manage_clubhouse` + extract `screen_html()`

**Files:**
- Modify: `includes/admin/class-setup-controller.php`
- Test: `tests/php/SetupControllerTest.php` (add one assertion)

**Interfaces:**
- Consumes: `Owner_Capabilities::SETUP_CAP` (Task 1).
- Produces: `Blueworx_Clubhouse_Setup_Controller::screen_html( Blueworx_Clubhouse_Storage $storage, array $notices ): string` — the rendered Setup screen HTML (reused by the dashboard takeover in Task 6).

- [ ] **Step 1: Write the failing test**

In `tests/php/SetupControllerTest.php`, add:

```php
	public function test_capability_is_the_custom_clubhouse_cap(): void {
		$this->assertSame( 'manage_clubhouse', Blueworx_Clubhouse_Setup_Controller::CAPABILITY );
	}

	public function test_screen_html_renders_the_setup_form(): void {
		$html = Blueworx_Clubhouse_Setup_Controller::screen_html( new Blueworx_Clubhouse_Fake_Storage(), array() );
		$this->assertStringContainsString( 'clubhouse-setup', $html );
		$this->assertStringContainsString( '<form', $html );
	}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit --filter SetupControllerTest`
Expected: FAIL — `CAPABILITY` is still `manage_options` and `screen_html` does not exist.

- [ ] **Step 3: Switch the capability and extract `screen_html`**

In `includes/admin/class-setup-controller.php`, change the constant:

```php
	public const CAPABILITY = Blueworx_Clubhouse_Owner_Capabilities::SETUP_CAP; // manage_clubhouse — owner + admin.
```

Add the `screen_html()` method (place it just above `build_model`):

```php
	/** Render the Setup screen HTML for a storage + notices — shared by the page and the owner dashboard. */
	public static function screen_html( Blueworx_Clubhouse_Storage $storage, array $notices ): string {
		$nonce_field = wp_nonce_field( self::NONCE, '_wpnonce', true, false )
			. '<input type="hidden" name="clubhouse_setup_submit" value="1">';
		$action_url  = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
		return Blueworx_Clubhouse_Setup_Screen::render( self::build_model( $storage, $notices, $nonce_field, $action_url ) );
	}
```

Replace the body of `render_page()` so it delegates to `screen_html()`:

```php
	public static function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		$storage = new Blueworx_Clubhouse_Options_Storage();
		$notices = array();
		if ( isset( $_POST['clubhouse_setup_submit'] ) ) {
			check_admin_referer( self::NONCE );
			$notices = self::handle_save( wp_unslash( $_POST ), $storage );
		}
		echo self::screen_html( $storage, $notices ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped within Setup_Screen.
	}
```

(`Owner_Capabilities` is loaded before `Setup_Controller`: `bootstrap.php` requires it, and the plugin/test bootstraps require `class-setup-controller.php` after `bootstrap.php`, so the constant expression resolves.)

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit --filter SetupControllerTest`
Expected: PASS (existing tests + the 2 new — the `current_user_can` stub returns true, so `render_page` is unaffected). Then `./vendor/bin/phpunit`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add includes/admin/class-setup-controller.php tests/php/SetupControllerTest.php
git commit -m "feat: Setup screen uses manage_clubhouse cap; extract screen_html for reuse"
```

---

## Task 4: Group the six CPTs under one "Content" menu

**Files:**
- Modify: `includes/collections/class-collection-types.php`, `blueworx-labs-clubhouse.php` (init hook), `tests/php/wp-stubs.php` (`remove_submenu_page` stub)
- Test: `tests/php/CollectionTypesTest.php`

**Interfaces:**
- Produces: `Blueworx_Clubhouse_Collection_Types::CONTENT_SLUG` = `'clubhouse-content'`; `::register_content_menu(): void`.

- [ ] **Step 1: Add the `remove_submenu_page` stub**

In `tests/php/wp-stubs.php`, add near the other menu stubs:

```php
if ( ! function_exists( 'remove_submenu_page' ) ) {
	function remove_submenu_page( ...$a ) { wp_stub_record( 'remove_submenu_page', $a ); return false; }
}
```

- [ ] **Step 2: Write the failing test**

In `tests/php/CollectionTypesTest.php`, add (and if an existing test asserts `show_in_menu === true`, update it to expect `Blueworx_Clubhouse_Collection_Types::CONTENT_SLUG`):

```php
	public function test_cpts_mount_under_the_content_parent(): void {
		wp_stub_reset();
		Blueworx_Clubhouse_Collection_Types::register();
		$calls = wp_stub_calls( 'register_post_type' );
		$this->assertNotEmpty( $calls );
		foreach ( $calls as $call ) {
			$this->assertSame( 'clubhouse-content', $call['args'][1]['show_in_menu'] );
		}
	}

	public function test_register_content_menu_adds_parent_and_drops_duplicate(): void {
		wp_stub_reset();
		Blueworx_Clubhouse_Collection_Types::register_content_menu();
		$menu = wp_stub_calls( 'add_menu_page' );
		$this->assertNotEmpty( $menu );
		$this->assertSame( 'clubhouse-content', $menu[0]['args'][3] );
		$dropped = wp_stub_calls( 'remove_submenu_page' );
		$this->assertSame( array( 'clubhouse-content', 'clubhouse-content' ), $dropped[0]['args'] );
	}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `./vendor/bin/phpunit --filter CollectionTypesTest`
Expected: FAIL — CPTs still register `show_in_menu => true`; `register_content_menu` / `CONTENT_SLUG` do not exist.

- [ ] **Step 4: Implement the grouping**

In `includes/collections/class-collection-types.php`, add the constant inside the class (near `POST_TYPES`):

```php
	public const CONTENT_SLUG = 'clubhouse-content';
```

In `register()`, change the CPT args line `'show_in_menu' => true,` to:

```php
				'show_in_menu' => self::CONTENT_SLUG,
```

Add the parent-menu registrar method:

```php
	/**
	 * Registers the top-level "Content" menu the six CPTs nest under, and removes
	 * the auto-created duplicate submenu so the parent link opens the first CPT.
	 * Hooked on admin_menu.
	 */
	public static function register_content_menu(): void {
		add_menu_page( 'Content', 'Content', 'edit_posts', self::CONTENT_SLUG, '', 'dashicons-clipboard', 4 );
		remove_submenu_page( self::CONTENT_SLUG, self::CONTENT_SLUG );
	}
```

In `blueworx-labs-clubhouse.php`, inside `blueworx_labs_clubhouse_init()`, add:

```php
	add_action( 'admin_menu', array( Blueworx_Clubhouse_Collection_Types::class, 'register_content_menu' ) );
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `./vendor/bin/phpunit --filter CollectionTypesTest`
Expected: PASS. Then `./vendor/bin/phpunit`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add includes/collections/class-collection-types.php blueworx-labs-clubhouse.php tests/php/wp-stubs.php tests/php/CollectionTypesTest.php
git commit -m "feat: group the six collection CPTs under one Content menu"
```

---

## Task 5: `Owner_Role` menu lockdown

**Files:**
- Modify: `includes/admin/class-owner-role.php`, `tests/php/wp-stubs.php` (`remove_menu_page` + `wp_get_current_user` stubs)
- Test: `tests/php/OwnerRoleTest.php`

**Interfaces:**
- Consumes: `Owner_Capabilities::menu_allowlist()`.
- Produces: `Owner_Role::register(): void`; `::lock_menu(): void`; `::removable_menu_slugs( array $current, array $allowlist ): array` (pure).

- [ ] **Step 1: Add the menu + current-user stubs**

In `tests/php/wp-stubs.php`:

Add a settable current user. Near the top `$GLOBALS` initialisers and inside `wp_stub_reset()` add:

```php
	$GLOBALS['wp_stub_current_user'] = (object) array( 'roles' => array() );
```

(add the same line at the top-of-file initialisers too). Then add the stubs:

```php
if ( ! function_exists( 'wp_get_current_user' ) ) {
	function wp_get_current_user() { return $GLOBALS['wp_stub_current_user']; }
}
if ( ! function_exists( 'remove_menu_page' ) ) {
	function remove_menu_page( $slug ) { wp_stub_record( 'remove_menu_page', array( $slug ) ); return false; }
}
```

- [ ] **Step 2: Write the failing test**

In `tests/php/OwnerRoleTest.php`, add:

```php
	public function test_removable_menu_slugs_is_current_minus_allowlist(): void {
		$this->assertSame(
			array( 'themes.php', 'plugins.php', 'tools.php' ),
			Blueworx_Clubhouse_Owner_Role::removable_menu_slugs(
				array( 'index.php', 'themes.php', 'plugins.php', 'tools.php', 'upload.php' ),
				Blueworx_Clubhouse_Owner_Capabilities::menu_allowlist()
			)
		);
	}

	public function test_lock_menu_removes_disallowed_only_for_owners(): void {
		$GLOBALS['menu'] = array(
			array( '', 'read', 'index.php' ),
			array( '', 'edit_theme_options', 'themes.php' ),
			array( '', 'activate_plugins', 'plugins.php' ),
			array( '', 'upload_files', 'upload.php' ),
		);
		// Not an owner → no removals.
		$GLOBALS['wp_stub_current_user'] = (object) array( 'roles' => array( 'administrator' ) );
		Blueworx_Clubhouse_Owner_Role::lock_menu();
		$this->assertSame( array(), wp_stub_calls( 'remove_menu_page' ) );

		// Owner → themes.php + plugins.php removed, index.php + upload.php kept.
		$GLOBALS['wp_stub_current_user'] = (object) array( 'roles' => array( 'clubhouse_owner' ) );
		Blueworx_Clubhouse_Owner_Role::lock_menu();
		$removed = array_map( static fn( $c ) => $c['args'][0], wp_stub_calls( 'remove_menu_page' ) );
		$this->assertContains( 'themes.php', $removed );
		$this->assertContains( 'plugins.php', $removed );
		$this->assertNotContains( 'index.php', $removed );
		$this->assertNotContains( 'upload.php', $removed );
	}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `./vendor/bin/phpunit --filter OwnerRoleTest`
Expected: FAIL — `removable_menu_slugs`, `lock_menu`, `register` don't exist.

- [ ] **Step 4: Implement lockdown**

In `includes/admin/class-owner-role.php`, add:

```php
	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'lock_menu' ), 999 );
	}

	/** Remove every top-level menu the owner is not allowed. Gated on the owner role. */
	public static function lock_menu(): void {
		if ( ! self::is_owner( wp_get_current_user() ) ) {
			return;
		}
		$menu    = isset( $GLOBALS['menu'] ) && is_array( $GLOBALS['menu'] ) ? $GLOBALS['menu'] : array();
		$current = array();
		foreach ( $menu as $item ) {
			if ( isset( $item[2] ) ) {
				$current[] = (string) $item[2];
			}
		}
		foreach ( self::removable_menu_slugs( $current, Blueworx_Clubhouse_Owner_Capabilities::menu_allowlist() ) as $slug ) {
			remove_menu_page( $slug );
		}
	}

	/**
	 * @param array<int,string> $current
	 * @param array<int,string> $allowlist
	 * @return array<int,string>
	 */
	public static function removable_menu_slugs( array $current, array $allowlist ): array {
		return array_values( array_diff( $current, $allowlist ) );
	}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `./vendor/bin/phpunit --filter OwnerRoleTest`
Expected: PASS. Then `./vendor/bin/phpunit`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add includes/admin/class-owner-role.php tests/php/wp-stubs.php tests/php/OwnerRoleTest.php
git commit -m "feat: Owner_Role locks the admin menu to the allowlist for owners"
```

---

## Task 6: `Owner_Role` dashboard takeover

**Files:**
- Modify: `includes/admin/class-owner-role.php`, `tests/php/wp-stubs.php` (`wp_add_dashboard_widget` stub)
- Test: `tests/php/OwnerRoleTest.php`

**Interfaces:**
- Consumes: `Setup_Controller::screen_html()` (Task 3).
- Produces: `Owner_Role::takeover_dashboard(): void`; `::render_dashboard(): void`.

- [ ] **Step 1: Add the dashboard-widget stub**

In `tests/php/wp-stubs.php`, add:

```php
if ( ! function_exists( 'wp_add_dashboard_widget' ) ) {
	function wp_add_dashboard_widget( ...$a ) { wp_stub_record( 'wp_add_dashboard_widget', $a ); }
}
```

- [ ] **Step 2: Write the failing test**

In `tests/php/OwnerRoleTest.php`, add:

```php
	public function test_takeover_dashboard_replaces_widgets_only_for_owners(): void {
		$GLOBALS['wp_meta_boxes'] = array( 'dashboard' => array( 'normal' => array( 'core' => array( 'dashboard_activity' => array() ) ) ) );

		// Admin → untouched.
		$GLOBALS['wp_stub_current_user'] = (object) array( 'roles' => array( 'administrator' ) );
		Blueworx_Clubhouse_Owner_Role::takeover_dashboard();
		$this->assertSame( array(), wp_stub_calls( 'wp_add_dashboard_widget' ) );
		$this->assertNotSame( array(), $GLOBALS['wp_meta_boxes']['dashboard'] );

		// Owner → default widgets cleared + our Setup widget added.
		$GLOBALS['wp_stub_current_user'] = (object) array( 'roles' => array( 'clubhouse_owner' ) );
		Blueworx_Clubhouse_Owner_Role::takeover_dashboard();
		$this->assertSame( array(), $GLOBALS['wp_meta_boxes']['dashboard'] );
		$added = wp_stub_calls( 'wp_add_dashboard_widget' );
		$this->assertSame( 'clubhouse_setup_dashboard', $added[0]['args'][0] );
	}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `./vendor/bin/phpunit --filter OwnerRoleTest`
Expected: FAIL — `takeover_dashboard` doesn't exist.

- [ ] **Step 4: Implement the takeover**

In `includes/admin/class-owner-role.php`, extend `register()` to add the dashboard hook (change the method to):

```php
	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'lock_menu' ), 999 );
		add_action( 'wp_dashboard_setup', array( self::class, 'takeover_dashboard' ), 999 );
	}
```

Add the two methods:

```php
	/** For owners only: clear the default dashboard widgets and mount the Setup screen. */
	public static function takeover_dashboard(): void {
		if ( ! self::is_owner( wp_get_current_user() ) ) {
			return;
		}
		$GLOBALS['wp_meta_boxes']['dashboard'] = array();
		wp_add_dashboard_widget( 'clubhouse_setup_dashboard', 'Clubhouse Setup', array( self::class, 'render_dashboard' ) );
	}

	/** Dashboard widget body: the reused Setup screen (its form posts to the Setup page). */
	public static function render_dashboard(): void {
		echo Blueworx_Clubhouse_Setup_Controller::screen_html( new Blueworx_Clubhouse_Options_Storage(), array() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped within Setup_Screen.
	}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `./vendor/bin/phpunit --filter OwnerRoleTest`
Expected: PASS. Then `./vendor/bin/phpunit`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add includes/admin/class-owner-role.php tests/php/wp-stubs.php tests/php/OwnerRoleTest.php
git commit -m "feat: Owner_Role takes over the dashboard with the Setup screen for owners"
```

---

## Task 7: Activation, uninstall & init wiring

**Files:**
- Modify: `blueworx-labs-clubhouse.php` (activation hook + init register)
- Create: `uninstall.php`

**Interfaces:**
- Consumes: `Owner_Role::activate()/uninstall()/register()`.

- [ ] **Step 1: Wire activation + init**

In `blueworx-labs-clubhouse.php`, add `Owner_Role::register()` inside `blueworx_labs_clubhouse_init()` (after the existing `register()` calls):

```php
	Blueworx_Clubhouse_Owner_Role::register();
```

And add `Owner_Role::activate()` inside the `register_activation_hook` closure (after `Blueworx_Clubhouse_Collection_Seeder::seed();`):

```php
			Blueworx_Clubhouse_Owner_Role::activate();
```

- [ ] **Step 2: Create `uninstall.php`**

Create `uninstall.php` at the plugin root:

```php
<?php
/**
 * Uninstall handler — removes the clubhouse_owner role and the administrator cap
 * grant. Runs only on plugin delete (kept on deactivate, per the design).
 *
 * @package BlueworxLabsClubhouse
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/admin/class-owner-role.php';

Blueworx_Clubhouse_Owner_Role::uninstall();
```

- [ ] **Step 3: Verify wiring and suite**

Run: `grep -n "Owner_Role::register\|Owner_Role::activate" blueworx-labs-clubhouse.php`
Expected: both present.

Run: `./vendor/bin/phpunit`
Expected: PASS (whole suite — this is plugin-file + uninstall wiring; the `activate()`/`uninstall()`/`register()` behaviours are already unit-covered by `OwnerRoleTest`).

> **Manual-smoke note:** the role/menu/dashboard behaviour is only observable at runtime — the Phase 4 manual WP smoke (owner logs in → Setup dashboard, locked menu, disallowed URLs 403, admin unaffected) is the regression check for this wiring.

- [ ] **Step 4: Commit**

```bash
git add blueworx-labs-clubhouse.php uninstall.php
git commit -m "feat: wire Owner_Role activation, init hooks and uninstall handler"
```

---

## Task 8: Version bump to 0.19.0, changelog, final verification

**Files:**
- Modify: `blueworx-labs-clubhouse.php` (header `Version:` + `BLUEWORX_LABS_CLUBHOUSE_VERSION`), `package.json` (`version`), `CHANGELOG.md`

- [ ] **Step 1: Bump the version**

In `blueworx-labs-clubhouse.php`, change ` * Version:           0.18.0` → ` * Version:           0.19.0` and `define( 'BLUEWORX_LABS_CLUBHOUSE_VERSION', '0.18.0' );` → `'0.19.0'`.
In `package.json`, change `"version": "0.18.0"` → `"version": "0.19.0"`.

- [ ] **Step 2: Add the changelog entry**

In `CHANGELOG.md`, add immediately below the top heading block, above the most recent entry:

```markdown
## [0.19.0] — Admin Phase 4: Clubhouse Owner role, admin lockdown & Dashboard takeover

### Added
- A new **Clubhouse Owner** role: a curated back-end for non-technical club owners. Login lands directly on the Setup screen (the dashboard is replaced with it), and the admin menu is limited to Setup, Content, Media, Posts, Comments, Users, and Profile — everything else (Appearance, Plugins, Tools, Settings, Pages) is hidden and capability-denied.
- The six collection post types are now grouped under a single **Content** menu.
- Owners can view the Users list but cannot create, edit, or delete users; they can edit the collections and the blog, upload media, and moderate comments.

### Changed
- The Clubhouse Setup screen is now gated by a dedicated `manage_clubhouse` capability (granted to owners and administrators) instead of `manage_options`.

### Notes
- The role is created on activation and kept on deactivate; it is removed only when the plugin is uninstalled.
```

- [ ] **Step 3: Final verification**

Run: `./vendor/bin/phpunit`
Expected: PASS (whole suite).

Run: `composer lint`
Expected: no errors. (Report any findings to the user at session end — do not auto-fix.)

- [ ] **Step 4: Commit**

```bash
git add blueworx-labs-clubhouse.php package.json CHANGELOG.md
git commit -m "chore: bump to 0.19.0 (Admin Phase 4 — Clubhouse Owner role)"
```

---

## Post-plan: PR, CI, merge, deploy (after all tasks pass)

1. Push `admin-phase-4-owner-role`; open a PR **to `main`**.
2. Wait for the required **`guardrails / guardrails`** check to go GREEN, then merge.
3. Refresh the deployment zip at `..\blueworx-labs-clubhouse.zip` — **runtime files only** (`blueworx-labs-clubhouse.php` + `uninstall.php` + `includes/` + `assets/` + `templates/`), excluding `tests/`, `docs/`, `preview/`, `vendor/`, `.superpowers/`. Build via .NET `ZipArchive`, forward-slash entries under a top-level `blueworx-labs-clubhouse/` folder; delete the old zip in a separate call first, then build, then `unzip -l` to verify. **Note: `uninstall.php` is a new runtime file to include.**

## Owed manual WordPress smoke (runtime-only)

Carried on the owed list (Phases 1–3 smokes still outstanding). Phase 4 adds: create a `clubhouse_owner` user → log in → lands on the **Setup dashboard**; the menu shows only Setup/Content/Media/Posts/Comments/Users/Profile; `/wp-admin/plugins.php` and `/wp-admin/themes.php` **403**; the six CPTs appear under **Content**; editing a fixture and a blog post both work; a full **administrator's** dashboard and menu are unchanged.

## Self-Review

- **Spec coverage:** role registration + caps (Tasks 1, 2) ✓; `manage_clubhouse` gate (Tasks 1, 3) ✓; menu lockdown allowlist, hidden + cap-denied (Tasks 1, 5) ✓; dashboard takeover reusing Setup_Screen (Tasks 3, 6) ✓; Content menu grouping (Task 4) ✓; view-only Users (Task 1 caps — `list_users` present, `create/edit/delete/promote_users` in deny-list) ✓; role kept-on-deactivate / removed-on-uninstall (Tasks 2, 7 — `uninstall.php`, no deactivation hook touches the role) ✓; version + changelog (Task 8) ✓; manual smoke carried ✓.
- **Placeholder scan:** none — every step has concrete code + exact commands.
- **Type consistency:** `Owner_Capabilities::ROLE/DISPLAY/SETUP_CAP/capabilities()/denied()/admin_cap_grants()/menu_allowlist()` (Task 1) are consumed with the same names in Tasks 2/3/5; `Owner_Role::activate/uninstall/is_owner/register/lock_menu/removable_menu_slugs/takeover_dashboard/render_dashboard` are consistent across Tasks 2/5/6/7; `Setup_Controller::screen_html(Storage,array):string` (Task 3) is consumed in Task 6; `Collection_Types::CONTENT_SLUG` (Task 4) matches the `menu_allowlist()` entry `clubhouse-content` (Task 1).
