<?php
// includes/playtomic/class-playtomic-controller.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Clubhouse → Playtomic screen's WordPress entry points. Everything that
 * can be decided without WordPress is a static method taking what it needs,
 * so it is unit-tested; the handle_*() methods only read the request, call
 * one of those, and redirect.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Playtomic_Controller {

	public const PAGE_SLUG  = 'clubhouse-playtomic';
	public const CAPABILITY = 'manage_options';
	public const NONCE      = 'clubhouse_playtomic';
	public const NOTICE_ARG = 'playtomic_notice';

	public const SAVE_ACTION     = 'clubhouse_playtomic_save';
	public const TEST_ACTION     = 'clubhouse_playtomic_test';
	public const DOWNLOAD_ACTION = 'clubhouse_playtomic_download';
	public const CLEAR_ACTION    = 'clubhouse_playtomic_clear';

	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'add_menu' ), 11 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
		add_action( 'admin_post_' . self::SAVE_ACTION, array( self::class, 'handle_save' ) );
		add_action( 'admin_post_' . self::TEST_ACTION, array( self::class, 'handle_test' ) );
		add_action( 'admin_post_' . self::DOWNLOAD_ACTION, array( self::class, 'handle_download' ) );
		add_action( 'admin_post_' . self::CLEAR_ACTION, array( self::class, 'handle_clear' ) );
	}

	public static function add_menu(): void {
		add_submenu_page( Blueworx_Clubhouse_Setup_Editor::PAGE_SLUG, 'Playtomic', 'Playtomic', self::CAPABILITY, self::PAGE_SLUG, array( self::class, 'render_page' ) );
	}

	public static function enqueue( string $hook ): void {
		if ( false === strpos( $hook, self::PAGE_SLUG ) ) {
			return;
		}
		Blueworx_Clubhouse_Admin_Assets::enqueue();
		wp_enqueue_script( 'clubhouse-playtomic-export', BLUEWORX_LABS_CLUBHOUSE_URL . 'assets/js/playtomic-export.js', array(), BLUEWORX_LABS_CLUBHOUSE_VERSION, true );
	}

	/* ---- the pure pieces ------------------------------------------------ */

	/** Key material for the secret: the site's own salts, which are already private per site. */
	public static function key_material(): string {
		$parts = array();
		foreach ( array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY' ) as $name ) {
			$parts[] = defined( $name ) ? (string) constant( $name ) : '';
		}
		return implode( '|', $parts );
	}

	public static function settings(): Blueworx_Clubhouse_Playtomic_Settings {
		return new Blueworx_Clubhouse_Playtomic_Settings( new Blueworx_Clubhouse_Options_Storage(), new Blueworx_Clubhouse_Playtomic_Secret( self::key_material() ) );
	}

	public static function client( Blueworx_Clubhouse_Playtomic_Settings $settings ): Blueworx_Clubhouse_Playtomic_Client {
		return new Blueworx_Clubhouse_Playtomic_Client( new Blueworx_Clubhouse_Playtomic_Wp_Transport(), $settings->client_id(), $settings->secret() );
	}

	/**
	 * The members source and the qualifying rule, from the saved settings.
	 *
	 * The subscription rule fails closed: if it is chosen but the shop is not
	 * running, nobody qualifies. Subscriptions are looked up once, for
	 * everyone, the first time anybody is checked.
	 *
	 * @param (callable():array<int,array<string,mixed>>)|null $source Who the members are; the site's subscribers when omitted.
	 */
	public static function members( ?Blueworx_Clubhouse_Playtomic_Settings $settings = null, ?callable $source = null ): Blueworx_Clubhouse_Playtomic_Members {
		$all    = ( $settings ?? self::settings() )->read();
		$read   = $source ?? static function (): array {
			if ( ! function_exists( 'get_users' ) ) {
				return array();
			}
			return Blueworx_Clubhouse_Playtomic_Members::from_users(
				get_users( array( 'role' => 'subscriber', 'number' => -1, 'orderby' => 'registered', 'order' => 'DESC' ) )
			);
		};
		$people = null;
		$cached = static function () use ( &$people, $read ): array {
			if ( null === $people ) {
				$people = $read();
			}
			return $people;
		};

		$qualifies = null;
		if ( Blueworx_Clubhouse_Playtomic_Settings::WHO_SUBSCRIPTIONS === (string) $all['who'] ) {
			if ( ! Blueworx_Clubhouse_Playtomic_Subscriptions::available() ) {
				$qualifies = static fn( int $user_id ): bool => false;
			} else {
				$price_ids = array_map( 'strval', (array) $all['price_ids'] );
				$set       = null;
				$qualifies = static function ( int $user_id ) use ( &$set, $cached, $price_ids ): bool {
					if ( null === $set ) {
						$ids = array_map( static fn( array $p ): int => (int) ( $p['id'] ?? 0 ), $cached() );
						$set = Blueworx_Clubhouse_Playtomic_Subscriptions::qualifying_user_ids( $ids, $price_ids );
					}
					return in_array( $user_id, $set, true );
				};
			}
		}
		return new Blueworx_Clubhouse_Playtomic_Members( $cached, $qualifies );
	}

	/** True when the saved rule needs the shop and the shop is not running, so nobody is listed. @param array<string,mixed> $settings */
	public static function shop_missing_for_rule( array $settings, bool $shop ): bool {
		return Blueworx_Clubhouse_Playtomic_Settings::WHO_SUBSCRIPTIONS === (string) ( $settings['who'] ?? '' ) && ! $shop;
	}

	/**
	 * The notice to show: the one the last action asked for, or else the
	 * warning that the rule needs a shop that is not running.
	 *
	 * @param array<string,mixed> $settings
	 * @return array{type:string,text:string}|null
	 */
	public static function page_notice( string $code, array $settings, bool $shop ): ?array {
		$notice = self::notice_from( $code );
		if ( null === $notice && self::shop_missing_for_rule( $settings, $shop ) ) {
			$notice = array( 'type' => 'warning', 'text' => 'Your export rule needs the shop, which is not running, so nobody is listed. Switch the rule to everyone or reinstall SureCart.' );
		}
		return $notice;
	}

	/**
	 * The venues that can go in the file: those with a club name, since the
	 * club name is what Playtomic matches the columns on.
	 *
	 * @param array<int,array<string,mixed>> $venues
	 * @return array<int,array<string,mixed>>
	 */
	public static function usable_venues( array $venues ): array {
		return array_values( array_filter( $venues, static fn( array $v ): bool => '' !== trim( (string) ( $v['club'] ?? '' ) ) ) );
	}

	/** @return array{ok:bool,text:string} */
	public static function test_connection( Blueworx_Clubhouse_Playtomic_Client $client, Blueworx_Clubhouse_Playtomic_Settings $settings ): array {
		try {
			$client->forget_token();
			$venues = $client->venues();
		} catch ( Blueworx_Clubhouse_Playtomic_Exception $e ) {
			$settings->set_last_error( $e->getMessage() );
			Blueworx_Clubhouse_Playtomic_Client::log( 'Test connection failed: ' . $e->getMessage() . ( '' !== $e->detail() ? ' | ' . $e->detail() : '' ) );
			return array( 'ok' => false, 'text' => $e->getMessage() );
		}
		$settings->merge_venues( $venues );
		$settings->set_last_error( '' );
		$settings->set_last_test( 'Connected on ' . self::today() . ': ' . ( array() === $venues ? 'no venues named in the token' : implode( ', ', $venues ) ) . '.' );
		$n = count( $venues );
		return array(
			'ok'   => true,
			'text' => 'Connected. Playtomic lists ' . $n . ' venue' . ( 1 === $n ? '' : 's' ) . ( $n > 0 ? ': ' . implode( ', ', $venues ) . '. Give each a name below.' : '.' ),
		);
	}

	/**
	 * The file for the ticked ids, or null when nobody valid was ticked or no
	 * venue has a club name. Venues without one are left out of the file.
	 *
	 * @param array<int,mixed>              $ids
	 * @param array<int,array<string,mixed>> $venues
	 * @return array{filename:string,body:string,ids:array<int,int>}|null
	 */
	public static function download_payload( Blueworx_Clubhouse_Playtomic_Members $members, array $ids, array $venues, string $today, string $from = '', string $to = '' ): ?array {
		$venues = self::usable_venues( $venues );
		if ( array() === $venues ) {
			return null;
		}
		$ids  = array_values( array_filter( array_map( 'intval', array_filter( $ids, 'is_scalar' ) ), static fn( int $id ): bool => $id > 0 ) );
		$rows = array() === $ids ? array() : $members->exportable( $ids, $from, $to );
		if ( array() === $rows ) {
			return null;
		}
		return array(
			'filename' => Blueworx_Clubhouse_Playtomic_Csv::FILENAME_PREFIX . $today . '.csv',
			'body'     => Blueworx_Clubhouse_Playtomic_Csv::build( $rows, $venues, $today ),
			'ids'      => array_map( 'intval', array_column( $rows, 'id' ) ),
		);
	}

	/** @return array{type:string,text:string}|null */
	public static function notice_from( string $code ): ?array {
		return match ( $code ) {
			'saved'       => array( 'type' => 'success', 'text' => 'Settings saved.' ),
			'test-ok'     => array( 'type' => 'success', 'text' => 'Connected. See the venues below.' ),
			'test-failed' => array( 'type' => 'danger', 'text' => 'Could not connect. The reason is under the Secret field.' ),
			'nothing'     => array( 'type' => 'warning', 'text' => 'Tick at least one member with a name and an email address, then download.' ),
			'cleared'     => array( 'type' => 'success', 'text' => 'Export mark cleared. They will be listed under Not yet exported again.' ),
			'no-venues'   => array( 'type' => 'warning', 'text' => 'Give every venue a club name under Settings before downloading.' ),
			default       => null,
		};
	}

	/* ---- WordPress entry points ----------------------------------------- */

	public static function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- filters on a GET screen, read-only.
		$filter = Blueworx_Clubhouse_Playtomic_Members::FILTER_ALL === (string) ( $_GET[ Blueworx_Clubhouse_Playtomic_Screen::FILTER ] ?? '' ) ? 'all' : 'new';
		$from   = self::date_arg( (string) ( $_GET[ Blueworx_Clubhouse_Playtomic_Screen::FROM ] ?? '' ) );
		$to     = self::date_arg( (string) ( $_GET[ Blueworx_Clubhouse_Playtomic_Screen::TO ] ?? '' ) );
		$code   = sanitize_key( (string) ( $_GET[ self::NOTICE_ARG ] ?? '' ) );
		// phpcs:enable

		$settings = self::settings();
		$all      = $settings->read();
		unset( $all['secret'] );
		$all['has_secret'] = $settings->has_secret();

		$members = self::members( $settings )->rows( $filter, $from, $to );
		$clear   = array();
		foreach ( $members as $m ) {
			if ( '' !== (string) $m['exported'] ) {
				$clear[ (int) $m['id'] ] = add_query_arg( array( 'action' => self::CLEAR_ACTION, 'id' => (int) $m['id'], '_wpnonce' => wp_create_nonce( self::NONCE ) ), admin_url( 'admin-post.php' ) );
			}
		}

		$prices = array();
		if ( Blueworx_Clubhouse_SureCart_Products::is_active() ) {
			foreach ( ( new Blueworx_Clubhouse_SureCart_Products() )->prices() as $price ) {
				$prices[] = array( 'id' => (string) $price['id'], 'label' => (string) $price['label'] . ' — ' . (string) $price['amount'] . (string) $price['period'] );
			}
		}

		$model = array(
			'page_url'    => admin_url( 'admin.php?page=' . self::PAGE_SLUG ),
			'action_url'  => admin_url( 'admin-post.php' ),
			'nonce_field' => wp_nonce_field( self::NONCE, '_wpnonce', true, false ),
			'actions'     => array( 'save' => self::SAVE_ACTION, 'test' => self::TEST_ACTION, 'download' => self::DOWNLOAD_ACTION, 'clear' => self::CLEAR_ACTION ),
			'clear_urls'  => $clear,
			'role_tags'   => Blueworx_Clubhouse_Access_Controller::role_chips_for( self::PAGE_SLUG ),
			'notice'      => self::page_notice( $code, $all, Blueworx_Clubhouse_SureCart_Products::is_active() ),
			'settings'    => $all,
			'can_encrypt' => Blueworx_Clubhouse_Playtomic_Secret::can_encrypt(),
			'shop'        => Blueworx_Clubhouse_SureCart_Products::is_active(),
			'prices'      => $prices,
			'filter'      => $filter,
			'from'        => $from,
			'to'          => $to,
			'members'     => $members,
			'today'       => self::today(),
		);
		echo Blueworx_Clubhouse_Playtomic_Screen::render( $model ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped within Playtomic_Screen.
	}

	public static function handle_save(): void {
		self::guard();
		$raw = wp_unslash( $_POST[ Blueworx_Clubhouse_Playtomic_Screen::FIELD ] ?? array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitised by Playtomic_Settings::save().
		self::settings()->save( is_array( $raw ) ? $raw : array() );
		self::back( 'saved' );
	}

	public static function handle_test(): void {
		self::guard();
		$raw = wp_unslash( $_POST[ Blueworx_Clubhouse_Playtomic_Screen::FIELD ] ?? array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitised by Playtomic_Settings::save().
		$settings = self::settings();
		$settings->save( is_array( $raw ) ? $raw : array() );
		$result = self::test_connection( self::client( $settings ), $settings );
		self::back( $result['ok'] ? 'test-ok' : 'test-failed' );
	}

	public static function handle_download(): void {
		self::guard();
		$ids     = (array) wp_unslash( $_POST[ Blueworx_Clubhouse_Playtomic_Screen::IDS ] ?? array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- reduced to positive ints in download_payload().
		$from    = self::date_arg( (string) wp_unslash( $_POST[ Blueworx_Clubhouse_Playtomic_Screen::FROM ] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- date_arg() validates.
		$to      = self::date_arg( (string) wp_unslash( $_POST[ Blueworx_Clubhouse_Playtomic_Screen::TO ] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- date_arg() validates.
		$today   = self::today();
		$settings = self::settings();
		if ( array() === self::usable_venues( $settings->venues() ) ) {
			self::back( 'no-venues' );
		}
		$payload = self::download_payload( self::members( $settings ), $ids, $settings->venues(), $today, $from, $to );
		if ( null === $payload ) {
			self::back( 'nothing' );
		}
		Blueworx_Clubhouse_Playtomic_Members::mark( $payload['ids'], $today );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $payload['filename'] . '"' );
		header( 'Content-Length: ' . strlen( $payload['body'] ) );
		echo $payload['body']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a CSV download, not HTML.
		exit;
	}

	public static function handle_clear(): void {
		self::guard();
		$id = (int) ( $_GET['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked in guard().
		if ( $id > 0 ) {
			Blueworx_Clubhouse_Playtomic_Members::clear( $id );
		}
		self::back( 'cleared', Blueworx_Clubhouse_Playtomic_Members::FILTER_ALL );
	}

	/* ---------------------------------------------------------------- */

	private static function guard(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( 'You do not have permission to do that.', '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::NONCE );
	}

	/** Back to the screen with a notice, keeping the filter. */
	private static function back( string $code, string $filter = '' ): never {
		$args = array( 'page' => self::PAGE_SLUG, self::NOTICE_ARG => $code );
		if ( '' !== $filter ) {
			$args[ Blueworx_Clubhouse_Playtomic_Screen::FILTER ] = $filter;
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	private static function date_arg( string $raw ): string {
		return Blueworx_Clubhouse_Playtomic_Settings::is_date( $raw ) ? $raw : '';
	}

	/** Today in the site's own timezone, so a late-evening export is not dated tomorrow. */
	private static function today(): string {
		return function_exists( 'wp_date' ) ? (string) wp_date( 'Y-m-d' ) : gmdate( 'Y-m-d' );
	}
}
