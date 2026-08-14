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
$GLOBALS['wp_stub_logged_in']    = false;
$GLOBALS['wp_stub_is_front_page'] = false;
$GLOBALS['wp_stub_is_admin']      = false;
$GLOBALS['wp_stub_users']         = array();
$GLOBALS['wp_stub_query_vars']    = array();
$GLOBALS['wp_stub_transients']    = array();
$GLOBALS['wp_stub_sideload_next'] = 500;
$GLOBALS['wp_stub_sideload_fail'] = array();
$GLOBALS['wp_stub_insert_fail']   = array();
$GLOBALS['wp_stub_update_fail']   = array();
$GLOBALS['wp_stub_delete_fail']   = array();
$GLOBALS['wp_stub_permalinks']    = array();
$GLOBALS['wp_stub_post_status']   = array();
$GLOBALS['wp_stub_referer']       = false;

function wp_stub_reset(): void {
	$GLOBALS['wp_stub_calls']       = array();
	$GLOBALS['wp_stub_options']     = array();
	$GLOBALS['wp_stub_posts']       = array();
	$GLOBALS['wp_stub_postmeta']    = array();
	$GLOBALS['wp_stub_roles']       = array( 'administrator' => array( 'display' => 'Administrator', 'caps' => array() ) );
	$GLOBALS['wp_stub_current_user'] = (object) array( 'roles' => array() );
	$GLOBALS['wp_stub_logged_in']    = false;
	$GLOBALS['wp_stub_is_front_page'] = false;
	$GLOBALS['wp_stub_is_admin']      = false;
	$GLOBALS['wp_stub_users']         = array();
	$GLOBALS['wp_stub_query_vars']    = array();
	$GLOBALS['wp_stub_transients']    = array();
	$GLOBALS['wp_stub_sideload_next'] = 500;
	$GLOBALS['wp_stub_sideload_fail'] = array();
	$GLOBALS['wp_stub_insert_fail']   = array();
	$GLOBALS['wp_stub_update_fail']   = array();
	$GLOBALS['wp_stub_delete_fail']   = array();
	$GLOBALS['wp_stub_permalinks']    = array();
	$GLOBALS['wp_stub_post_status']   = array();
	$GLOBALS['wp_stub_referer']       = false;
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
	$GLOBALS['wp_stub_is_admin']      = false;
	$GLOBALS['wp_stub_users']         = array();
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
		$type  = $args['post_type'] ?? '';
		$posts = $GLOBALS['wp_stub_posts'][ $type ] ?? array();
		// Real get_posts() returns ints, not post objects, for fields => 'ids'.
		// Callers branch on that, so the stub has to honour it.
		if ( 'ids' === ( $args['fields'] ?? '' ) ) {
			$posts = array_map( static fn ( $post ) => (int) ( $post->ID ?? 0 ), $posts );
		}
		return $posts;
	}
}
if ( ! function_exists( 'get_post_status' ) ) {
	// False for a post that does not exist, a status string otherwise —
	// including 'trash', which is the case Checkout_Page exists to catch.
	function get_post_status( $post = null ) {
		$id = is_object( $post ) ? (int) ( $post->ID ?? 0 ) : (int) $post;
		return $GLOBALS['wp_stub_post_status'][ $id ] ?? false;
	}
}
if ( ! function_exists( 'wp_get_referer' ) ) {
	function wp_get_referer() { return $GLOBALS['wp_stub_referer'] ?? false; }
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
	function wp_insert_post( ...$a ) {
		wp_stub_record( 'wp_insert_post', $a );
		$post  = $a[0] ?? array();
		$title = is_array( $post ) ? (string) ( $post['post_title'] ?? '' ) : '';
		if ( isset( $GLOBALS['wp_stub_insert_fail'][ $title ] ) ) {
			return 0;
		}
		return count( $GLOBALS['wp_stub_calls'] );
	}
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
if ( ! function_exists( 'shortcode_exists' ) ) {
	/**
	 * Registry-backed so a test can declare a shortcode present. Frontend installs
	 * this as the integration detector, and Page_Map::is_available() calls it on
	 * nearly every render, so it has to exist here even when no test cares.
	 */
	function shortcode_exists( $tag ) {
		return isset( $GLOBALS['wp_stub_shortcodes'] ) && in_array( $tag, (array) $GLOBALS['wp_stub_shortcodes'], true );
	}
}
if ( ! function_exists( 'do_shortcode' ) ) {
	function do_shortcode( $content ) { return $content; }
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
if ( ! function_exists( 'is_user_logged_in' ) ) {
	function is_user_logged_in(): bool { return $GLOBALS['wp_stub_logged_in'] ?? false; }
}
if ( ! function_exists( 'remove_menu_page' ) ) {
	function remove_menu_page( $slug ) { wp_stub_record( 'remove_menu_page', array( $slug ) ); return false; }
}
if ( ! function_exists( 'get_userdata' ) ) {
	function get_userdata( $id ) { return $GLOBALS['wp_stub_users'][ (int) $id ] ?? false; }
}
if ( ! function_exists( 'is_admin' ) ) {
	function is_admin() { return (bool) $GLOBALS['wp_stub_is_admin']; }
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
	// Real wp_nonce_url() returns an esc_html'd URL — its '&' separators come back as
	// '&#038;'. The stub must do the same, or a caller that escapes the result a second
	// time looks correct here and ships a double-escaped href.
	function wp_nonce_url( $url, $action = -1, $name = '_wpnonce' ) { return esc_html( (string) $url . ( str_contains( (string) $url, '?' ) ? '&' : '?' ) . $name . '=stubnonce' ); }
}
if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( $action = -1 ) { return 'stubnonce'; }
}
if ( ! function_exists( 'add_query_arg' ) ) {
	// Unlike wp_nonce_url(), the real add_query_arg() does not escape — it returns a raw '&'.
	function add_query_arg( $key, $value, $url ) { return (string) $url . ( str_contains( (string) $url, '?' ) ? '&' : '?' ) . rawurlencode( (string) $key ) . '=' . rawurlencode( (string) $value ); }
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
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $key ) ); }
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
		/** @var array<string,bool> */
		public array $capabilities;
		public function __construct( string $name ) {
			$this->name         = $name;
			$this->capabilities = $GLOBALS['wp_stub_roles'][ $name ]['caps'] ?? array();
		}
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
if ( ! function_exists( 'get_permalink' ) ) {
	// Recorded (unlike get_option()) so tests can assert whether this ran at
	// all — needed to prove Frontend::register() never resolves a checkout
	// URL eagerly, since that would reach this function via
	// SureCart_Products::checkout_url() before $wp_rewrite exists.
	function get_permalink( $post = 0 ) {
		wp_stub_record( 'get_permalink', array( $post ) );
		$id = is_object( $post ) ? (int) ( $post->ID ?? 0 ) : (int) $post;
		return $GLOBALS['wp_stub_permalinks'][ $id ] ?? false;
	}
}

/** Make the next sideload of this URL fail, as a dead link would. */
function wp_stub_fail_sideload( string $url ): void {
	$GLOBALS['wp_stub_sideload_fail'][ $url ] = true;
}

/** Make the next wp_insert_post() for this post title fail, as a DB error would. */
function wp_stub_fail_insert( string $title ): void {
	$GLOBALS['wp_stub_insert_fail'][ $title ] = true;
}

/** Make wp_update_post() for this post ID fail, as a DB error would. */
function wp_stub_fail_update( int $id ): void {
	$GLOBALS['wp_stub_update_fail'][ $id ] = true;
}

/** Make wp_delete_post() for this post ID fail, as a DB error would. */
function wp_stub_fail_delete( int $id ): void {
	$GLOBALS['wp_stub_delete_fail'][ $id ] = true;
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public function __construct( public string $code = '', public string $message = '' ) {}
	}
}
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ): bool { return $thing instanceof WP_Error; }
}
if ( ! function_exists( 'media_sideload_image' ) ) {
	function media_sideload_image( string $url, int $post_id = 0, ?string $desc = null, string $return = 'html' ) {
		wp_stub_record( 'media_sideload_image', array( $url, $post_id, $desc, $return ) );
		if ( isset( $GLOBALS['wp_stub_sideload_fail'][ $url ] ) ) {
			return new WP_Error( 'http_404', 'Not found' );
		}
		return $GLOBALS['wp_stub_sideload_next']++;
	}
}
if ( ! function_exists( 'wp_update_post' ) ) {
	function wp_update_post( array $post = array() ) {
		wp_stub_record( 'wp_update_post', array( $post ) );
		$id = (int) ( $post['ID'] ?? 0 );
		if ( isset( $GLOBALS['wp_stub_update_fail'][ $id ] ) ) {
			return 0;
		}
		return $id;
	}
}
if ( ! function_exists( 'wp_delete_post' ) ) {
	function wp_delete_post( int $id, bool $force = false ) {
		wp_stub_record( 'wp_delete_post', array( $id, $force ) );
		if ( isset( $GLOBALS['wp_stub_delete_fail'][ $id ] ) ) {
			return false;
		}
		return true;
	}
}
if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( string $key, $value, int $ttl = 0 ): bool {
		$GLOBALS['wp_stub_transients'][ $key ] = $value;
		wp_stub_record( 'set_transient', array( $key, $value, $ttl ) );
		return true;
	}
}
if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( string $key ) {
		return $GLOBALS['wp_stub_transients'][ $key ] ?? false;
	}
}
if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( string $key ): bool {
		unset( $GLOBALS['wp_stub_transients'][ $key ] );
		wp_stub_record( 'delete_transient', array( $key ) );
		return true;
	}
}
if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id(): int { return 7; }
}
if ( ! function_exists( 'add_submenu_page' ) ) {
	function add_submenu_page( ...$a ) { wp_stub_record( 'add_submenu_page', $a ); return 'clubhouse_page_stub'; }
}
if ( ! function_exists( 'wp_safe_redirect' ) ) {
	function wp_safe_redirect( string $url, int $status = 302 ): bool {
		wp_stub_record( 'wp_safe_redirect', array( $url, $status ) );
		return true;
	}
}
if ( ! function_exists( 'size_format' ) ) {
	function size_format( $bytes, int $decimals = 0 ) { return (string) $bytes . ' bytes'; }
}

/** Register a fake existing post of a type, with optional meta. */
function wp_stub_add_post( string $type, int $id, string $title, array $meta = array() ): void {
	$GLOBALS['wp_stub_posts'][ $type ][] = (object) array( 'ID' => $id, 'post_title' => $title );
	foreach ( $meta as $key => $value ) {
		$GLOBALS['wp_stub_postmeta'][ $id ][ $key ] = $value;
	}
}
