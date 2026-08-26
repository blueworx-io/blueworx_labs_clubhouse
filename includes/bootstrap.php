<?php
/**
 * Runtime class loader. Requires engine classes in dependency order.
 * No hooks or instantiation here — loading only, so it is safe to include
 * from both the plugin runtime and the PHPUnit bootstrap.
 *
 * @package BlueworxLabsClubhouse
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Core primitives. Interface must load before its implementor.
require_once __DIR__ . '/core/interface-storage.php';
require_once __DIR__ . '/core/class-options-storage.php';
require_once __DIR__ . '/core/class-null-storage.php';
require_once __DIR__ . '/core/class-registry.php';

// Content
require_once __DIR__ . '/content/class-content-store.php';
require_once __DIR__ . '/content/class-content-sanitiser.php';
require_once __DIR__ . '/content/class-visibility.php';
require_once __DIR__ . '/content/class-club-pages.php';
require_once __DIR__ . '/content/class-link-catalogue.php';
require_once __DIR__ . '/content/class-menu.php';
require_once __DIR__ . '/content/class-shortcodes.php';
require_once __DIR__ . '/content/class-integrations.php';

// Social feed. Interface before its implementors, as everywhere else here.
require_once __DIR__ . '/social/interface-feed-source.php';
require_once __DIR__ . '/social/class-manual-feed-source.php';
require_once __DIR__ . '/social/class-social-feed.php';

// Membership
require_once __DIR__ . '/membership/interface-products.php';
require_once __DIR__ . '/membership/class-demo-products.php';
require_once __DIR__ . '/membership/class-products-source.php';
require_once __DIR__ . '/membership/class-checkout.php';
require_once __DIR__ . '/membership/class-surecart-products.php';
require_once __DIR__ . '/membership/class-shop-pages.php';
require_once __DIR__ . '/membership/class-checkout-form.php';
require_once __DIR__ . '/membership/class-welcome-pack.php';

// Member area. Pure first, then the page that uses them.
require_once __DIR__ . '/dashboard/class-dashboard-views.php';
require_once __DIR__ . '/dashboard/class-plugin-slot.php';
require_once __DIR__ . '/dashboard/class-dashboard-assets.php';
require_once __DIR__ . '/dashboard/class-member-dashboard.php';
require_once __DIR__ . '/dashboard/class-commerce-pages.php';

// Theme
require_once __DIR__ . '/theme/interface-base-look.php';
require_once __DIR__ . '/theme/class-base-look-registry.php';
require_once __DIR__ . '/theme/class-color-engine.php';
require_once __DIR__ . '/theme/class-branding.php';
require_once __DIR__ . '/theme/class-theme-css.php';
require_once __DIR__ . '/theme/class-theme-cache.php';
require_once __DIR__ . '/theme/class-demo-state.php';

// Looks
require_once __DIR__ . '/looks/class-court-side.php';
require_once __DIR__ . '/looks/class-members-house.php';
require_once __DIR__ . '/looks/class-floodlight.php';

// Render
require_once __DIR__ . '/render/class-sections.php';
require_once __DIR__ . '/render/class-dashboard-shell.php';
require_once __DIR__ . '/render/class-page-renderer.php';
require_once __DIR__ . '/render/class-page-map.php';
require_once __DIR__ . '/render/class-fixture-projection.php';

// Admin (pure)
require_once __DIR__ . '/admin/class-setup-progress.php';
require_once __DIR__ . '/admin/class-setup-sections.php';
require_once __DIR__ . '/admin/class-setup-screen.php';
require_once __DIR__ . '/admin/class-owner-capabilities.php';
require_once __DIR__ . '/admin/class-content-catalogue.php';
require_once __DIR__ . '/admin/class-content-screen.php';
require_once __DIR__ . '/admin/class-menu-panel.php';
require_once __DIR__ . '/admin/class-admin-pages.php';
require_once __DIR__ . '/admin/class-access-screen.php';
require_once __DIR__ . '/admin/class-seo-screen.php';
require_once __DIR__ . '/admin/class-guide.php';
require_once __DIR__ . '/admin/class-guide-screen.php';
require_once __DIR__ . '/admin/class-club-page-editing.php';

// Collections (pure)
require_once __DIR__ . '/collections/interface-collections.php';
require_once __DIR__ . '/collections/interface-post-source.php';
require_once __DIR__ . '/collections/class-demo-content.php';
require_once __DIR__ . '/collections/class-demo-collections.php';
require_once __DIR__ . '/collections/class-demo-posts.php';
require_once __DIR__ . '/collections/class-collection-meta.php';

// Frontend (pure)
require_once __DIR__ . '/frontend/class-auth-view.php';
require_once __DIR__ . '/frontend/class-auth-settings.php';
require_once __DIR__ . '/frontend/class-mail-settings.php';
require_once __DIR__ . '/frontend/class-mail.php';
require_once __DIR__ . '/frontend/class-seo.php';
require_once __DIR__ . '/frontend/class-links.php';
require_once __DIR__ . '/frontend/class-legacy-urls.php';
require_once __DIR__ . '/frontend/class-news.php';
require_once __DIR__ . '/frontend/class-cta.php';
require_once __DIR__ . '/frontend/class-demo-mode.php';

// Import (pure)
require_once __DIR__ . '/import/class-import-plan.php';
require_once __DIR__ . '/import/class-import-parser.php';
require_once __DIR__ . '/import/class-import-prompt.php';
require_once __DIR__ . '/import/class-import-preview.php';
require_once __DIR__ . '/import/class-import-sections.php';
require_once __DIR__ . '/import/class-import-screen.php';
