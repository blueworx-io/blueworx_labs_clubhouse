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
