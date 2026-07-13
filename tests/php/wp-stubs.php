<?php
// tests/php/wp-stubs.php
// Dependency-free recorder stubs for the handful of WordPress functions the
// Frontend glue calls. Each records into $GLOBALS['wp_stub_calls'] so tests can
// assert what was registered/enqueued. Guarded so a real WP runtime is never
// shadowed. Reset with wp_stub_reset() in setUp().
declare(strict_types=1);

$GLOBALS['wp_stub_calls']    = array();
$GLOBALS['wp_stub_options']  = array();
$GLOBALS['wp_stub_posts']    = array();
$GLOBALS['wp_stub_postmeta'] = array();

function wp_stub_reset(): void {
	$GLOBALS['wp_stub_calls']    = array();
	$GLOBALS['wp_stub_options']  = array();
	$GLOBALS['wp_stub_posts']    = array();
	$GLOBALS['wp_stub_postmeta'] = array();
}
function wp_stub_calls( string $fn ): array {
	return array_values( array_filter(
		$GLOBALS['wp_stub_calls'],
		static fn( $c ) => $c['fn'] === $fn
	) );
}
function wp_stub_record( string $fn, array $args ): void {
	$GLOBALS['wp_stub_calls'][] = array( 'fn' => $fn, 'args' => $args );
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( ...$a ) { wp_stub_record( 'add_action', $a ); return true; }
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( ...$a ) { wp_stub_record( 'add_filter', $a ); return true; }
}
if ( ! function_exists( 'add_rewrite_rule' ) ) {
	function add_rewrite_rule( ...$a ) { wp_stub_record( 'add_rewrite_rule', $a ); }
}
if ( ! function_exists( 'add_rewrite_tag' ) ) {
	function add_rewrite_tag( ...$a ) { wp_stub_record( 'add_rewrite_tag', $a ); }
}
if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( ...$a ) { wp_stub_record( 'wp_enqueue_style', $a ); }
}
if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( ...$a ) { wp_stub_record( 'wp_enqueue_script', $a ); }
}
if ( ! function_exists( 'wp_add_inline_style' ) ) {
	function wp_add_inline_style( ...$a ) { wp_stub_record( 'wp_add_inline_style', $a ); }
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $key, $default = false ) {
		return $GLOBALS['wp_stub_options'][ $key ] ?? $default;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( string $key, $value, $autoload = null ): bool {
		$GLOBALS['wp_stub_options'][ $key ] = $value;
		return true;
	}
}
if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( string $key ): bool {
		unset( $GLOBALS['wp_stub_options'][ $key ] );
		return true;
	}
}
if ( ! function_exists( 'get_posts' ) ) {
	function get_posts( array $args = array() ) {
		$type = $args['post_type'] ?? '';
		return $GLOBALS['wp_stub_posts'][ $type ] ?? array();
	}
}
if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( int $id, string $key = '', bool $single = false ) {
		$meta = $GLOBALS['wp_stub_postmeta'][ $id ] ?? array();
		if ( '' === $key ) {
			return $meta;
		}
		return $single ? ( $meta[ $key ] ?? '' ) : array( $meta[ $key ] ?? '' );
	}
}
if ( ! function_exists( 'register_post_type' ) ) {
	function register_post_type( ...$a ) { wp_stub_record( 'register_post_type', $a ); return (object) array( 'name' => $a[0] ?? '' ); }
}
if ( ! function_exists( 'register_post_meta' ) ) {
	function register_post_meta( ...$a ) { wp_stub_record( 'register_post_meta', $a ); return true; }
}
if ( ! function_exists( 'wp_insert_post' ) ) {
	function wp_insert_post( ...$a ) { wp_stub_record( 'wp_insert_post', $a ); return count( $GLOBALS['wp_stub_calls'] ); }
}
if ( ! function_exists( 'add_post_meta' ) ) {
	function add_post_meta( ...$a ) { wp_stub_record( 'add_post_meta', $a ); return true; }
}
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) { return $text; }
}
if ( ! function_exists( 'get_the_title' ) ) {
	function get_the_title( $post = 0 ) { return is_object( $post ) ? ( $post->post_title ?? '' ) : ''; }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) { return is_string( $str ) ? trim( preg_replace( '/[\r\n\t ]+/', ' ', preg_replace( '/<[^>]*>/', '', $str ) ) ) : ''; }
}
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) { $u = trim( (string) $url ); return preg_match( '#^https?://#i', $u ) ? $u : ''; }
}
if ( ! function_exists( 'sanitize_hex_color' ) ) {
	function sanitize_hex_color( $color ) { $c = trim( (string) $color ); return preg_match( '/^#[0-9a-fA-F]{6}$/', $c ) ? strtolower( $c ) : ''; }
}
