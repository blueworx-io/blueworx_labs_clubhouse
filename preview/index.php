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

require_once dirname( __DIR__ ) . '/includes/bootstrap.php';

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
	$body      = Blueworx_Clubhouse_Page_Map::render( $slug, $branding, $visibility, new Blueworx_Clubhouse_Demo_Collections() );
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
	$style     = '<style>.ch-switcher{position:fixed;right:16px;bottom:16px;z-index:90;background:#fff;border:1px solid #e9e4d8;border-radius:16px;padding:8px;display:flex;flex-wrap:wrap;max-width:150px}</style>';

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
