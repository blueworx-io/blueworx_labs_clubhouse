<?php
// tests/php/wp-stubs.php
// Dependency-free recorder stubs for the handful of WordPress functions the
// Frontend glue calls. Each records into $GLOBALS['wp_stub_calls'] so tests can
// assert what was registered/enqueued. Guarded so a real WP runtime is never
// shadowed. Reset with wp_stub_reset() in setUp().
declare(strict_types=1);

$GLOBALS['wp_stub_calls']       = array();
$GLOBALS['wp_stub_options']     = array();
$GLOBALS['wp_stub_posts']       = array();
$GLOBALS['wp_stub_postmeta']    = array();
$GLOBALS['wp_stub_roles']       = array( 'administrator' => array( 'display' => 'Administrator', 'caps' => array() ) );
$GLOBALS['wp_stub_current_user'] = (object) array( 'roles' => array() );
$GLOBALS['wp_stub_is_front_page'] = false;
$GLOBALS['wp_stub_query_vars']    = array();

function wp_stub_reset(): void {
	$GLOBALS['wp_stub_calls']       = array();
	$GLOBALS['wp_stub_options']     = array();
	$GLOBALS['wp_stub_posts']       = array();
	$GLOBALS['wp_stub_postmeta']    = array();
	$GLOBALS['wp_stub_roles']       = array( 'administrator' => array( 'display' => 'Administrator', 'caps' => array() ) );
	$GLOBALS['wp_stub_current_user'] = (object) array( 'roles' => array() );
	$GLOBALS['wp_stub_is_front_page'] = false;
	$GLOBALS['wp_stub_query_vars']    = array();
	unset( $GLOBALS['menu'], $GLOBALS['wp_meta_boxes'] );
}

/** Put the request on a clubhouse page: the front page, or a mapped page slug. */
function wp_stub_on_clubhouse_page( string $slug = '' ): void {
	$GLOBALS['wp_stub_is_front_page'] = '' === $slug;
	$GLOBALS['wp_stub_query_vars']    = '' === $slug
		? array()
		: array( Blueworx_Clubhouse_Frontend::QUERY_VAR => $slug );
}

/** Put the request somewhere the plugin does not render: a blog post, WooCommerce, etc. */
function wp_stub_off_clubhouse_page(): void {
	$GLOBALS['wp_stub_is_front_page'] = false;
	$GLOBALS['wp_stub_query_vars']    = array();
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
if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $str ) {
		if ( ! is_string( $str ) ) {
			return '';
		}
		$str   = preg_replace( '/<[^>]*>/', '', $str );
		$str   = str_replace( array( "\r\n", "\r" ), "\n", (string) $str );
		$lines = array_map(
			static fn( $line ) => trim( preg_replace( '/[\t ]+/', ' ', $line ) ),
			explode( "\n", $str )
		);
		return trim( implode( "\n", $lines ) );
	}
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $maybeint ) { return abs( (int) $maybeint ); }
}
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) { $u = trim( (string) $url ); return preg_match( '#^https?://#i', $u ) ? $u : ''; }
}
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) { $u = trim( (string) $url ); return preg_match( '#^https?://#i', $u ) ? htmlspecialchars( $u, ENT_QUOTES, 'UTF-8' ) : ''; }
}
if ( ! function_exists( 'sanitize_hex_color' ) ) {
	function sanitize_hex_color( $color ) { $c = trim( (string) $color ); return preg_match( '/^#[0-9a-fA-F]{6}$/', $c ) ? strtolower( $c ) : ''; }
}
if ( ! function_exists( 'add_menu_page' ) ) {
	function add_menu_page( ...$a ) { wp_stub_record( 'add_menu_page', $a ); return 'toplevel_page_' . ( $a[3] ?? '' ); }
}
if ( ! function_exists( 'remove_submenu_page' ) ) {
	function remove_submenu_page( ...$a ) { wp_stub_record( 'remove_submenu_page', $a ); return false; }
}
if ( ! function_exists( 'wp_get_current_user' ) ) {
	function wp_get_current_user() { return $GLOBALS['wp_stub_current_user']; }
}
if ( ! function_exists( 'remove_menu_page' ) ) {
	function remove_menu_page( $slug ) { wp_stub_record( 'remove_menu_page', array( $slug ) ); return false; }
}
if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( ...$a ) { wp_stub_record( 'current_user_can', $a ); return true; }
}
if ( ! function_exists( 'wp_enqueue_media' ) ) {
	function wp_enqueue_media( ...$a ) { wp_stub_record( 'wp_enqueue_media', $a ); }
}
if ( ! function_exists( 'nocache_headers' ) ) {
	function nocache_headers( ...$a ) { wp_stub_record( 'nocache_headers', $a ); }
}
if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) { return 'https://club.test/wp-admin/' . ltrim( (string) $path, '/' ); }
}
if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) { return 'https://club.test' . ( '' === (string) $path ? '/' : (string) $path ); }
}
if ( ! function_exists( 'wp_get_attachment_image_url' ) ) {
	function wp_get_attachment_image_url( $id, $size = 'thumbnail' ) { return $id ? 'https://club.test/wp-content/uploads/att-' . (int) $id . '.png' : false; }
}
if ( ! function_exists( 'wp_nonce_url' ) ) {
	function wp_nonce_url( $url, $action = -1, $name = '_wpnonce' ) { return (string) $url . ( str_contains( (string) $url, '?' ) ? '&' : '?' ) . $name . '=stubnonce'; }
}
if ( ! function_exists( 'wp_nonce_field' ) ) {
	function wp_nonce_field( ...$a ) { wp_stub_record( 'wp_nonce_field', $a ); $name = $a[1] ?? '_wpnonce'; return '<input type="hidden" name="' . $name . '" value="stub-nonce">'; }
}
if ( ! function_exists( 'check_admin_referer' ) ) {
	function check_admin_referer( ...$a ) { wp_stub_record( 'check_admin_referer', $a ); return true; }
}
if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $v ) { return $v; }
}
if ( ! function_exists( 'update_post_meta' ) ) {
	function update_post_meta( int $id, string $key, $value ) {
		$GLOBALS['wp_stub_postmeta'][ $id ][ $key ] = $value;
		wp_stub_record( 'update_post_meta', array( $id, $key, $value ) );
		return true;
	}
}
if ( ! function_exists( 'add_meta_box' ) ) {
	function add_meta_box( ...$a ) { wp_stub_record( 'add_meta_box', $a ); }
}
if ( ! function_exists( 'wp_verify_nonce' ) ) {
	function wp_verify_nonce( $nonce, $action = -1 ) { wp_stub_record( 'wp_verify_nonce', array( $nonce, $action ) ); return 1; }
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
}
if ( ! function_exists( 'esc_textarea' ) ) {
	function esc_textarea( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
}
if ( ! function_exists( 'selected' ) ) {
	function selected( $a, $b = true, $echo = true ) {
		$r = ( (string) $a === (string) $b ) ? ' selected="selected"' : '';
		if ( $echo ) { echo $r; }
		return $r;
	}
}

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
if ( ! function_exists( 'wp_add_dashboard_widget' ) ) {
	function wp_add_dashboard_widget( ...$a ) { wp_stub_record( 'wp_add_dashboard_widget', $a ); }
}
// Routing shims for Frontend::current_slug(). The defaults (not the front page, no
// query var) resolve to "not a clubhouse page" — identical to the function_exists()
// fallback these replace, so tests that never touch them behave as before.
if ( ! function_exists( 'is_front_page' ) ) {
	function is_front_page(): bool { return (bool) ( $GLOBALS['wp_stub_is_front_page'] ?? false ); }
}
if ( ! function_exists( 'get_query_var' ) ) {
	function get_query_var( string $var, $default = '' ) { return $GLOBALS['wp_stub_query_vars'][ $var ] ?? $default; }
}
