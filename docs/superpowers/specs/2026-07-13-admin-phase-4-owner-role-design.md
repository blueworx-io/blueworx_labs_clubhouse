# Admin Phase 4 — Clubhouse Owner Role, Admin Lockdown & Dashboard Takeover — Design

**Date:** 2026-07-13
**Status:** Approved (brainstorm), pending implementation plan
**Branch:** `admin-phase-4-owner-role` (off `main` @ v0.18.0, built in an isolated worktree)
**Umbrella spec:** `docs/superpowers/specs/2026-07-13-admin-and-clubhouse-owner-experience-design.md` (Phase 4)

## Purpose

Phases 2 and 3 gave owners two surfaces — a bespoke **Setup** screen (config) and
**native CPT editing** (content). Phase 4 wraps them in a curated **`clubhouse_owner`
role** so a non-technical club owner logs into a safe, focused WordPress back-end:
login lands straight on the Setup screen, the menu shows only what they need, and the
parts of WordPress that could break the site are hidden *and* capability-denied.

## Scope

In scope:
1. A new **`clubhouse_owner` role** with a curated capability set, created on activation.
2. A custom **`manage_clubhouse`** capability gating the Setup screen (granted to owner + administrator).
3. **Admin menu lockdown** to an allowlist for the role (hidden *and* cap-denied).
4. **Dashboard takeover** for owners: the Setup screen renders as the dashboard body.
5. **Grouping the six collection CPTs** under a single "Content" parent menu (for all users).
6. Role **lifecycle**: kept on deactivate, removed on uninstall.

Out of scope (later / rejected): a first-run wizard (rejected — login goes straight to
`index.php`); user *management* for owners (view-only only — see decisions); font
self-hosting (Phase 5); multi-accent / third-party plugin integrations.

## Decisions (from the brainstorm)

- **Users = view only.** `clubhouse_owner` gets `list_users` (so the owner can see the
  member/committee directory) but **no** create/edit/delete/promote — the smallest attack
  surface; an owner can never mint an account or escalate a role.
- **Collections grouped under one "Content" menu** for everyone (admins included), so the
  owner's menu reads cleanly: Setup · Content · Media · Posts · Comments · Users · Profile.
- **Role kept on deactivate, removed on uninstall** — owner assignments survive an
  update/reactivate cycle; the role is cleaned up only on full delete.
- **Custom `manage_clubhouse` capability** gates the Setup screen, granted to
  `clubhouse_owner` **and** `administrator` on activation; `Setup_Controller::CAPABILITY`
  switches `manage_options → manage_clubhouse` (owners reach Setup; admins keep it).
- **Owner content-editing covers collections + the native blog** via the standard `post`
  capabilities. The six CPTs register with the default `post` capability type, so one grant
  of the post caps covers both the collections and the WordPress blog — fewer moving parts,
  and matches the umbrella's intent that owners manage the blog too.
- **Dashboard takeover replaces the dashboard body**, it does not redirect: for owners only,
  the default widgets are removed and `Setup_Screen` is rendered in their place.

## Architecture

Pure core + thin WP glue, consistent with the codebase.

### Pure, WP-free, unit-tested

- **`Owner_Capabilities`** — the single source of truth, as pure data:
  - `capabilities(): array<string,bool>` — the exact cap map for `clubhouse_owner`.
    - **Granted:** `read`; `manage_clubhouse`; the standard post caps
      (`edit_posts`, `edit_others_posts`, `edit_published_posts`, `publish_posts`,
      `delete_posts`, `delete_others_posts`, `delete_published_posts`, `read_private_posts`)
      — covering the six CPTs *and* the native blog; `upload_files` (Media);
      `moderate_comments` + `edit_comment` (Comments, tied to the blog); `list_users` (view Users).
    - **Never granted (asserted absent):** `manage_options`, `edit_theme_options`,
      `switch_themes`, `activate_plugins`, `install_plugins`, `install_themes`,
      `update_core`, `update_plugins`, `update_themes`, `edit_pages`, `edit_others_pages`,
      `publish_pages`, `create_users`, `edit_users`, `delete_users`, `promote_users`.
  - `admin_cap_grants(): array<int,string>` — caps to add to `administrator` on activation
    (`manage_clubhouse`), and remove on uninstall.
  - `menu_allowlist(): array<int,string>` — the admin-menu top-level slugs the owner keeps:
    `index.php` (Dashboard = Setup), the Content parent slug, `upload.php`, `edit.php`
    (Posts), `edit-comments.php`, `users.php`, `profile.php`. Everything else is removed.

### Thin WP-coupled glue (tested via `tests/php/wp-stubs.php` recorders + manual smoke)

- **`Owner_Role`** — the only new glue class:
  - `activate()` — `add_role('clubhouse_owner', 'Clubhouse Owner', Owner_Capabilities::capabilities())`
    and grant each `admin_cap_grants()` cap to the `administrator` role.
  - `uninstall()` — `remove_role('clubhouse_owner')` and strip the admin cap grants.
  - `register()` — runtime hooks, **every one gated on the current user actually being a
    `clubhouse_owner`** so nothing leaks into the admin experience:
    - `admin_menu` (late priority) → remove every top-level menu **not** in `menu_allowlist()`.
    - `wp_dashboard_setup` → remove the default dashboard widgets.
    - render `Setup_Screen` as the dashboard body (via a full-width dashboard output), with
      the form action pointed at the existing Setup page so POST handling stays in
      `Setup_Controller` (no duplicated form processing).
  - A pure helper `is_owner(WP_User): bool` (role membership check) is unit-testable; the
    hook wiring uses `wp_get_current_user()`.

- **Content menu grouping** — register one top-level **Content** menu
  (`add_menu_page('Content', …, 'edit_posts', 'clubhouse-content', …)`), set each CPT's
  `show_in_menu => 'clubhouse-content'` in `Collection_Types`, and remove the auto-created
  duplicate submenu. Applies to all users.

- **`Setup_Controller`** — `CAPABILITY` constant → `'manage_clubhouse'`. Unchanged otherwise;
  the dashboard takeover reuses `Setup_Screen::render()` and the page's POST handler.

- **Activation / uninstall wiring** — the plugin activation hook calls `Owner_Role::activate()`
  (alongside the existing rewrite/CPT/seed calls); a new **`uninstall.php`** at the plugin
  root calls `Owner_Role::uninstall()`.

## Error handling & security

- **Defense in depth:** disallowed screens are hidden from the menu **and** the caps are
  absent, so a direct URL (`/wp-admin/themes.php`, `/wp-admin/plugins.php`) is denied by
  WordPress's own capability check, not merely unlinked.
- **No leakage:** every owner-specific hook checks the current user's role first; a stray
  owner cap never alters a full admin's dashboard or menu.
- **User safety:** `list_users` only — no create/edit/delete/promote — so an owner cannot
  create an account or escalate any role (including their own).
- **Capability/nonce failures:** standard `wp_die`/cap checks; owners can never reach a
  screen their caps don't allow.

## Data & storage

No new option schema. The role and its caps live in WordPress's roles store (written on
activation). No persisted flag beyond the role itself.

## Testing strategy

- **Pure `Owner_Capabilities`:** the granted map contains every essential cap and the
  deny-list assertions confirm each forbidden cap is **absent**; `menu_allowlist()` is exactly
  the intended slugs; `admin_cap_grants()` contains `manage_clubhouse`.
- **Glue via recorders** (extend `wp-stubs.php` with `add_role`, `remove_role`,
  `remove_menu_page`, `remove_submenu_page`, `remove_meta_box`, `get_role`, and a
  `wp_get_current_user`/role stub): `activate()` adds the role with the right caps and grants
  the admin cap; `uninstall()` removes both; the menu lockdown calls `remove_menu_page` for
  disallowed slugs and **not** for allowed ones; dashboard widgets are removed for an owner
  and **not** for an admin; `is_owner()` true/false.
- **Manual WP smoke** (owed, runtime-only): create a `clubhouse_owner` user → log in → lands
  on the Setup dashboard; the menu shows only the allowlist; `/wp-admin/plugins.php` and
  `/wp-admin/themes.php` 403; editing a fixture and a blog post both work; a full administrator's
  dashboard and menu are unchanged. This smoke also finally discharges the Phase 1–3 owed smokes.

## Versioning & deployment

Minor bump **0.18.0 → 0.19.0** with the changelog updated alongside, as the final task. PR to
`main`; wait for `guardrails / guardrails` GREEN; merge. Refresh the deployment zip
(runtime files only, forward-slash entries) after merge — this phase now bundles `uninstall.php`.

## Risks & open items

- **Dashboard takeover** is the least-conventional piece; it must not touch the full-admin
  dashboard — every hook is gated on the owner role. Verified in the manual smoke.
- **Menu grouping** changes `show_in_menu` for all users; confirm admins still reach all six
  CPTs under the new Content parent (the auto-duplicate submenu is removed cleanly).
- **Capability sharing** (post caps span collections + blog) is deliberate per the decision;
  if isolation is ever required, a custom capability type for the CPTs is the future path.
