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

/** @return array<int,array{name:string,c:string,ink:string,deep:string,wash:string}> */
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
			'name' => $name,
			'c'    => $d['--color-accent'],
			'ink'  => $d['--color-accent-ink'],
			'deep' => $d['--color-accent-deep'],
			'wash' => $d['--color-accent-wash'],
		);
	}
	return $out;
}

/** Route a page slug to its renderer. Unrouted slugs fall back to Home. */
function blueworx_clubhouse_preview_body(
	string $page,
	Blueworx_Clubhouse_Branding $branding,
	Blueworx_Clubhouse_Visibility $visibility
): string {
	switch ( $page ) {
		case 'about':
			return Blueworx_Clubhouse_Page_Renderer::about( $branding, $visibility );
		case 'membership':
			return Blueworx_Clubhouse_Page_Renderer::membership( $branding, $visibility );
		case 'contact':
			return Blueworx_Clubhouse_Page_Renderer::contact( $branding, $visibility );
		case 'login':
			return Blueworx_Clubhouse_Page_Renderer::login( $branding, $visibility );
		case 'home':
		default:
			return Blueworx_Clubhouse_Page_Renderer::home( $branding, $visibility );
	}
}

function blueworx_clubhouse_preview_document(): string {
	$storage  = new Blueworx_Clubhouse_Preview_Storage();
	$registry = new Blueworx_Clubhouse_Base_Look_Registry( $storage );
	$registry->register( new Blueworx_Clubhouse_Court_Side() );
	$registry->register( new Blueworx_Clubhouse_Members_House() );
	$look_slug = isset( $_GET['look'] ) && is_string( $_GET['look'] ) ? preg_replace( '/[^a-z-]/', '', $_GET['look'] ) : 'court-side';
	if ( ! $registry->has( (string) $look_slug ) ) {
		$look_slug = 'court-side';
	}
	$registry->set_active( (string) $look_slug );
	$branding   = new Blueworx_Clubhouse_Branding( $storage );
	$visibility = new Blueworx_Clubhouse_Visibility( $storage );

	$page      = isset( $_GET['page'] ) && is_string( $_GET['page'] ) ? preg_replace( '/[^a-z]/', '', $_GET['page'] ) : 'home';
	$body      = blueworx_clubhouse_preview_body( (string) $page, $branding, $visibility );
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
		. 'r.setProperty("--color-accent-deep",p.deep);r.setProperty("--color-accent-wash",p.wash);};'
		. 'box.appendChild(s);});'
		. '})();</script>';
	$style     = '<style>.ch-switcher{position:fixed;right:16px;bottom:16px;z-index:90;background:#fff;border:1px solid #e9e4d8;border-radius:16px;padding:8px;display:flex;flex-wrap:wrap;max-width:150px}</style>';

	$other      = 'court-side' === $look_slug ? 'members-house' : 'court-side';
	$other_name = 'court-side' === $look_slug ? "Members' House" : 'Court Side';
	$look_toggle = '<a class="ch-look-toggle" href="?look=' . rawurlencode( $other ) . '&page=' . rawurlencode( (string) $page ) . '">Look: ' . htmlspecialchars( $other_name, ENT_QUOTES, 'UTF-8' ) . ' &rarr;</a>';
	$style      .= '<style>.ch-look-toggle{position:fixed;left:16px;bottom:16px;z-index:90;background:#201c15;color:#fff;font:600 13px/1 system-ui,sans-serif;padding:12px 16px;border-radius:8px;text-decoration:none}</style>';

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

	// Served with docroot = plugin root, so the look stylesheet resolves from '/'.
	return Blueworx_Clubhouse_Page_Renderer::document(
		$registry->active(),
		$branding,
		$body . $switcher . $look_toggle . $look_persist . $style,
		'/'
	);
}

if ( PHP_SAPI !== 'cli' ) {
	echo blueworx_clubhouse_preview_document();
}
