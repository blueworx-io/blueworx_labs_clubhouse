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

/**
 * A club page's words live on the page now — in post meta, behind a post id
 * held in an option — so a preview with no WordPress at all can no longer seed
 * the two states below (see $preview_content). These four functions are the
 * whole of what Page_Content and Club_Pages ask WordPress for, answered from
 * memory for the life of one request.
 *
 * Everything else in the plugin that guards on function_exists( 'get_option' )
 * asks for a value nothing here ever writes, and gets the default it would
 * have taken anyway — so this changes what the preview can seed, not how it
 * renders.
 */
$GLOBALS['blueworx_clubhouse_preview_options'] = array();
$GLOBALS['blueworx_clubhouse_preview_meta']    = array();

// Guarded, because the PHP test suite loads this file to prove the preview
// still boots, and its own WordPress stubs have already defined these.
if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $option, mixed $default = false ): mixed {
		return $GLOBALS['blueworx_clubhouse_preview_options'][ $option ] ?? $default;
	}
}
if ( ! function_exists( 'update_post_meta' ) ) {
	function update_post_meta( int $post_id, string $key, mixed $value ): bool {
		$GLOBALS['blueworx_clubhouse_preview_meta'][ $post_id ][ $key ] = $value;
		return true;
	}
}
if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( int $post_id, string $key = '', bool $single = false ): mixed {
		$value = $GLOBALS['blueworx_clubhouse_preview_meta'][ $post_id ][ $key ] ?? '';
		return $single ? $value : array( $value );
	}
}
if ( ! function_exists( 'metadata_exists' ) ) {
	function metadata_exists( string $type, int $object_id, string $key ): bool {
		return isset( $GLOBALS['blueworx_clubhouse_preview_meta'][ $object_id ][ $key ] );
	}
}

require_once dirname( __DIR__ ) . '/includes/bootstrap.php';

// One synthetic post id per club page, so Page_Content has somewhere to write.
$blueworx_clubhouse_preview_next_id = 1;
foreach ( Blueworx_Clubhouse_Page_Map::pages() as $blueworx_clubhouse_preview_page ) {
	$blueworx_clubhouse_preview_slug = (string) $blueworx_clubhouse_preview_page['slug'];
	$GLOBALS['blueworx_clubhouse_preview_options'][ 'clubhouse_page_id_' . ( '' === $blueworx_clubhouse_preview_slug ? 'home' : $blueworx_clubhouse_preview_slug ) ] = $blueworx_clubhouse_preview_next_id++;
}

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

/** @return array<int,array{name:string,c:string,ink:string,deep:string,wash:string,block:string}> */
function blueworx_clubhouse_preview_palettes( Blueworx_Clubhouse_Base_Look $look ): array {
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
			'name'  => $name,
			'c'     => $d['--color-accent'],
			'ink'   => $d['--color-accent-ink'],
			'deep'  => $d['--color-accent-deep'],
			'wash'  => $d['--color-accent-wash'],
			'block' => $d['--color-accent-block'],
		);
	}
	return $out;
}

function blueworx_clubhouse_preview_document(): string {
	$storage  = new Blueworx_Clubhouse_Preview_Storage();
	$registry = new Blueworx_Clubhouse_Base_Look_Registry( $storage );
	$registry->register( new Blueworx_Clubhouse_Court_Side() );
	$registry->register( new Blueworx_Clubhouse_Members_House() );
	$registry->register( new Blueworx_Clubhouse_Floodlight() );
	$look_order = array( 'court-side', 'members-house', 'floodlight' );
	$look_slug  = isset( $_GET['look'] ) && is_string( $_GET['look'] ) ? preg_replace( '/[^a-z-]/', '', $_GET['look'] ) : 'court-side';
	if ( ! $registry->has( (string) $look_slug ) ) {
		$look_slug = 'court-side';
	}
	$registry->set_active( (string) $look_slug );
	$branding   = new Blueworx_Clubhouse_Branding( $storage );
	$visibility = new Blueworx_Clubhouse_Visibility( $storage );

	// The preview is a design tool, so integration-backed pages have to be
	// designable here even though there is no WordPress to detect a plugin with.
	// Everything is reported present; the shortcodes themselves still have no
	// expander, so each slot shows its shortcode as text rather than pretending
	// to render a booking calendar the preview could not produce.
	Blueworx_Clubhouse_Integrations::set_detector( static fn( string $tag ): bool => true );

	// Accepts WordPress's real query var (`clubhouse_page`, see Frontend::QUERY_VAR)
	// as well as the preview's own `?page=`. The specs navigate with the former so a
	// single URL form works against both this harness and a real WordPress install;
	// `?page=` stays supported because the on-page nav emits it via Links::url().
	$raw  = $_GET['clubhouse_page'] ?? $_GET['page'] ?? 'home';
	$page = is_string( $raw ) ? (string) preg_replace( '/[^a-z]/', '', $raw ) : 'home';
	$slug = 'home' === $page ? '' : (string) $page;
	if ( ! Blueworx_Clubhouse_Page_Map::has( $slug ) ) {
		$slug = '';
	}
	// The filter pill slug ([a-z0-9-]); matched against the page's derived pills,
	// an unknown value falls back to "All". Mirrors Frontend::sanitize_filter.
	$raw_filter = $_GET[ Blueworx_Clubhouse_Links::FILTER_PARAM ] ?? '';
	$filter     = is_string( $raw_filter ) ? trim( (string) preg_replace( '/[^a-z0-9]+/', '-', strtolower( $raw_filter ) ), '-' ) : '';
	// Club news, without a database behind it. 'post' is not a page-map slug —
	// an article lives at a WordPress permalink — so the preview routes to the
	// article renderer directly, which is what the front end does too.
	Blueworx_Clubhouse_News::set_source( new Blueworx_Clubhouse_Demo_Posts() );
	Blueworx_Clubhouse_News::set_page( $_GET[ Blueworx_Clubhouse_News::PAGE_PARAM ] ?? 1 );
	// Membership tiers, without a shop behind them. The preview has no stored
	// content, so every tier renders unconnected — the correct default — but the
	// seam is installed so the preview exercises the same code path as the live site.
	Blueworx_Clubhouse_Products_Source::set( new Blueworx_Clubhouse_Demo_Products() );
	Blueworx_Clubhouse_Checkout::set_base_url( '?page=checkout-demo' );
	// Two seeded states the preview can be asked for, both off by default. Each
	// makes something visible that a fresh site has no content for: the social
	// feed ships hidden and shows only pasted posts, and the Monthly / Annual
	// switch only appears when a tier is priced both ways. They share one
	// content store so both can be asked for at once.
	$preview_content = null;
	$preview_store   = static function () use ( $storage, &$preview_content ): Blueworx_Clubhouse_Page_Content {
		if ( null === $preview_content ) {
			$preview_content = new Blueworx_Clubhouse_Page_Content( $storage );
		}
		return $preview_content;
	};

	// 'demo' seeds three posts; 'empty' switches the section on with nothing
	// pasted, which is what a club sees between opting in and connecting.
	$raw_social = $_GET['clubhouse_social'] ?? '';
	$social     = is_string( $raw_social ) ? (string) preg_replace( '/[^a-z]/', '', $raw_social ) : '';
	if ( 'demo' === $social || 'empty' === $social ) {
		$content_store = $preview_store();
		$content_store->set( 'home', 'social_feed', '_shown', true );
		$content_store->set( 'home', 'social_feed', 'platform', 'instagram' );
		$content_store->set( 'home', 'social_feed', 'heading', 'Latest from the club' );
		$content_store->set( 'home', 'social_feed', 'lede', 'Match-day photos and the week as it happened.' );
		if ( 'demo' === $social ) {
			$content_store->set_items( 'home', 'social_feed', array(
				array( 'href' => 'https://www.instagram.com/p/clubhouse-1/', 'caption' => 'Saturday’s win, in one photograph.' ),
				array( 'href' => 'https://www.instagram.com/p/clubhouse-2/', 'caption' => 'Juniors back on the pitch after the break.' ),
				array( 'href' => 'https://www.instagram.com/p/clubhouse-3/', 'caption' => 'The clubhouse bar is open again from Friday.' ),
			) );
		}
	}

	// One tier is left deliberately monthly-only, because how that card behaves
	// mid-switch is the part worth looking at.
	if ( 'cadence' === (string) preg_replace( '/[^a-z]/', '', (string) ( $_GET['clubhouse_tiers'] ?? '' ) ) ) {
		$preview_store()->set_items( 'membership', 'tiers', array(
			array( 'eyebrow' => 'Under 18', 'name' => 'Junior', 'price' => '£12', 'period' => '/mo',
				'features' => "Any junior section\nCoaching included", 'cta_label' => 'Join' ),
			array( 'eyebrow' => 'Full playing', 'name' => 'Adult', 'price' => '£28', 'period' => '/mo',
				'price_annual' => '£280', 'features' => "Any section, any level\nClubhouse & socials", 'cta_label' => 'Join' ),
			array( 'eyebrow' => 'Best value', 'name' => 'Family', 'price' => '£45', 'period' => '/mo',
				'price_annual' => '£450', 'features' => "Up to 5 members\nAny sections", 'featured' => true, 'cta_label' => 'Join' ),
		) );
	}
	// The login page's form is the shop's now (issue #261), and the shop is not
	// here — so the preview draws the club's card around an element that stays
	// inert, which is the honest picture of what this plugin contributes to that
	// page. Nothing to publish: state() carries only the session.
	// One sport's or one team's own page, same param the front end reads.
	$raw_item = $_GET[ Blueworx_Clubhouse_Links::ITEM_PARAM ] ?? '';
	$item     = is_string( $raw_item ) ? trim( (string) preg_replace( '/[^a-z0-9]+/', '-', strtolower( $raw_item ) ), '-' ) : '';
	$collections = new Blueworx_Clubhouse_Demo_Collections();
	$body        = 'post' === $page
		? Blueworx_Clubhouse_Page_Renderer::post( $branding, $visibility, $collections, '', $preview_content, $filter )
		: Blueworx_Clubhouse_Page_Map::render( $slug, $branding, $visibility, $collections, '', $preview_content, $filter, $item );
	$palettes  = blueworx_clubhouse_preview_palettes( $registry->active() );
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
		. 'r.setProperty("--color-accent-deep",p.deep);r.setProperty("--color-accent-wash",p.wash);'
		. 'r.setProperty("--color-accent-block",p.block);};'
		. 'box.appendChild(s);});'
		. '})();</script>';
	// The second rule is the same clearance demo.css gives the demo bar: this
	// harness's own look picker sits in the corner the cookie notice's dismiss
	// button occupies, and preview tooling must never be the reason a real
	// control cannot be clicked. The notice moves up rather than the tooling
	// standing down — the notice is on screen for every first page load, so
	// hiding the picker for it would mean hiding it almost always.
	$style     = '<style>.ch-switcher{position:fixed;right:16px;bottom:16px;z-index:90;background:#fff;border:1px solid #e9e4d8;border-radius:16px;padding:8px;display:flex;flex-wrap:wrap;max-width:150px}'
		. 'body:has(.ch-switcher) .ch-cookie{bottom:120px}</style>';

	$idx        = array_search( (string) $look_slug, $look_order, true );
	$next       = $look_order[ ( (int) $idx + 1 ) % count( $look_order ) ];
	$next_look  = $registry->get( $next );
	$next_name  = $next_look instanceof Blueworx_Clubhouse_Base_Look ? $next_look->name() : ucwords( str_replace( '-', ' ', $next ) );
	$look_toggle = '<a class="ch-look-toggle" href="?look=' . rawurlencode( $next )
		. '&page=' . rawurlencode( (string) $page ) . '">Look: '
		. htmlspecialchars( $next_name, ENT_QUOTES, 'UTF-8' ) . ' &rarr;</a>';
	$style      .= '<style>.ch-look-toggle{position:fixed;left:16px;bottom:16px;z-index:90;background:#1e1913;color:#f3ede0;font:600 13px/1 system-ui,sans-serif;padding:12px 16px;border-radius:8px;text-decoration:none;border:1px solid #302a20}</style>';

	// Preview-only: on a non-default look, carry the active look through the on-page
	// ?page= links (nav, footer, CTAs) so clicking around stays in the selected look.
	// This lives entirely in the preview harness — the sections stay skin-agnostic and
	// emit bare ?page= hrefs; the real WordPress site has no ?look= param (the look is a
	// persisted setting), so no link rewriting is needed there. Court Side is the default,
	// so its links are left bare.
	$look_persist = '';
	if ( 'court-side' !== $look_slug ) {
		$look_persist = '<script>(function(){var look=' . json_encode( (string) $look_slug )
			. ';document.querySelectorAll(\'a[href^="?page="]\').forEach(function(a){'
			. 'a.setAttribute("href",a.getAttribute("href")+"&look="+encodeURIComponent(look));});'
			. '})();</script>';
	}

	// Preview-only: mount the REAL Demo mode bar (Demo_Mode is WP-free, demo.js is
	// plain JS) so its picker can be driven in a browser. Demo_Controller itself is
	// WordPress-coupled and cannot run here. Additive and opt-in — the preview's own
	// .ch-switcher is unaffected.
	$demo = '';
	if ( isset( $_GET['demo'] ) && '1' === $_GET['demo'] ) {
		$demo_looks = array();
		foreach ( $registry->all() as $demo_look ) {
			$demo_looks[] = array( 'slug' => $demo_look->slug(), 'name' => $demo_look->name() );
		}
		$demo = '<link rel="stylesheet" href="/assets/css/demo.css">'
			. '<script>' . Blueworx_Clubhouse_Demo_Mode::head_script(
				Blueworx_Clubhouse_Demo_Mode::palettes( $registry->active() )
			) . '</script>'
			. Blueworx_Clubhouse_Demo_Mode::switcher_html( $demo_looks, (string) $look_slug, null )
			. '<script src="/assets/js/demo.js"></script>';
	}

	// Served with docroot = plugin root, so the look stylesheet resolves from '/'.
	return Blueworx_Clubhouse_Page_Renderer::document(
		$registry->active(),
		$branding,
		$body . $switcher . $look_toggle . $look_persist . $style . $demo,
		'/'
	);
}

if ( PHP_SAPI !== 'cli' ) {
	echo blueworx_clubhouse_preview_document();
}
