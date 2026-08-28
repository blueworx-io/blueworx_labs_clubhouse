<?php
// includes/profile/class-profile-form.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The member's Profile card: drawing it, and saving what they typed.
 *
 * All or nothing on save. A submission that fails its required check writes
 * nothing at all, so a member who has to come back finds the page as they left
 * it rather than half-written.
 *
 * The outcome of a save travels back in a query argument rather than a session,
 * because the member area is a plain server-rendered page and a redirect after
 * post is the only thing that stops a refresh saving twice.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Profile_Form {

	/** What the last save had to say, for the member to read. */
	public const RESULT_ARG = 'clubhouse_profile_result';

	/** The name of the panel this class draws, as Dashboard_Views declares it. */
	public const PANEL = 'profile';

	public static function register(): void {
		add_action( 'admin_post_' . Blueworx_Clubhouse_Profile_Panel::ACTION, array( __CLASS__, 'handle' ) );
	}

	/**
	 * The card, for Member_Dashboard's panel renderer.
	 *
	 * Empty for a club that has defined no fields, which leaves the Profile page
	 * as the shop's name-and-password block alone rather than a blank card.
	 */
	public static function panel( string $which ): string {
		if ( self::PANEL !== $which ) {
			return '';
		}
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return '';
		}
		$store  = new Blueworx_Clubhouse_Profile_Store( new Blueworx_Clubhouse_Options_Storage() );
		$fields = $store->fields();
		if ( array() === $fields ) {
			return '';
		}

		return Blueworx_Clubhouse_Profile_Panel::render(
			$fields,
			$store->answers( $user_id, $fields ),
			admin_url( 'admin-post.php' ),
			wp_nonce_field( Blueworx_Clubhouse_Profile_Panel::NONCE, '_wpnonce', true, false ),
			self::notices()
		);
	}

	/**
	 * What the last save had to say, read off the address.
	 *
	 * @return array<int,array{type:string,text:string}>
	 */
	public static function notices(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading the outcome of our own redirect, not acting on it.
		$raw = $_GET[ self::RESULT_ARG ] ?? '';
		if ( ! is_string( $raw ) || '' === $raw ) {
			return array();
		}
		if ( 'saved' === $raw ) {
			return array( array( 'type' => 'success', 'text' => 'Saved. Thank you.' ) );
		}

		$missing = array_values( array_filter( array_map( 'sanitize_text_field', explode( '|', $raw ) ) ) );
		if ( array() === $missing ) {
			return array();
		}
		return array(
			array(
				'type' => 'error',
				'text' => 'Nothing was saved — your club still needs ' . implode( ', ', $missing ) . '.',
			),
		);
	}

	/** The admin-post handler. Verifies, applies, redirects back. */
	public static function handle(): void {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}
		check_admin_referer( Blueworx_Clubhouse_Profile_Panel::NONCE );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified immediately above.
		$post   = wp_unslash( $_POST );
		$store  = new Blueworx_Clubhouse_Profile_Store( new Blueworx_Clubhouse_Options_Storage() );
		$result = self::apply( $store, $user_id, is_array( $post ) ? $post : array() );

		$back = wp_get_referer();
		if ( ! is_string( $back ) || '' === $back ) {
			$back = home_url( '/' );
		}
		// Cleared first: a member who saves twice must not accumulate the outcome
		// of both attempts in one address.
		$back = remove_query_arg( self::RESULT_ARG, $back );
		$back = add_query_arg(
			self::RESULT_ARG,
			$result['saved'] ? 'saved' : implode( '|', $result['missing'] ),
			$back
		);
		wp_safe_redirect( $back );
		exit;
	}

	/**
	 * Apply one member's submission. WordPress-free, so the rules are testable.
	 *
	 * @param array<string,mixed> $post
	 * @return array{saved:bool,missing:array<int,string>}
	 */
	public static function apply( Blueworx_Clubhouse_Profile_Store $store, int $user_id, array $post ): array {
		if ( $user_id <= 0 ) {
			return array( 'saved' => false, 'missing' => array() );
		}
		$result = Blueworx_Clubhouse_Profile_Values::from_member_post( $store->fields(), $post );
		if ( array() !== $result['missing'] ) {
			return array( 'saved' => false, 'missing' => $result['missing'] );
		}
		$store->save_answers( $user_id, $result['values'] );
		return array( 'saved' => true, 'missing' => array() );
	}
}
