# Hide native WordPress "Pages" (admin UI)

**Date:** 2026-07-22
**Status:** Approved
**Branch:** off `main`, targets `main`.

## Problem

The plugin serves every site page through its own routing (the `clubhouse_page`
query var + rewrite rules + Club Content editor). WordPress's native "Pages" are
therefore unused, but administrators still see the "Pages" admin menu and the
"+ New → Page" admin-bar item — clutter that invites editing content that never
renders.

## Goal

Hide native Pages from the admin UI, for all users. Reversible; nothing deleted.

## Scope (approved)

Hide the admin UI only — do **not** disable the `page` post type. Leaving it
registered keeps WordPress internals working (static front page, privacy-policy
page) and any other plugin that relies on pages.

## Design

New class `Blueworx_Clubhouse_Native_Pages`, one concern, matching the plugin's
per-class admin style. Registered from the plugin boot alongside the other
`::register()` calls.

- **`register()`** hooks:
  - `admin_menu` (priority 999, after the menu is built) → `hide_pages_menu()`.
  - `admin_bar_menu` (priority 999) → `hide_new_page_node( $wp_admin_bar )`.
- **`hide_pages_menu()`** calls `remove_menu_page( self::PAGES_MENU_SLUG )` where
  `PAGES_MENU_SLUG = 'edit.php?post_type=page'`.
- **`hide_new_page_node( $bar )`** takes the admin-bar object (injected, as
  `Demo_Controller::admin_bar_node` does) and calls `$bar->remove_node( 'new-page' )`.

Both run for every admin user. Owners are already menu-locked to their
allowlist, so this is a no-op for them and removes the item for administrators.

## Testing

New `NativePagesTest`, following the `DemoController`/`OwnerRole` stub pattern:

- `register()` hooks both `admin_menu` and `admin_bar_menu`.
- `hide_pages_menu()` records `remove_menu_page( 'edit.php?post_type=page' )`.
- `hide_new_page_node()` calls `remove_node( 'new-page' )` on a fake bar.

## Out of scope

- No change to the `page` post type registration or the front end.
- No bulk trashing/unpublishing of existing pages.
- Blog "Posts" are untouched — only Pages are hidden.
