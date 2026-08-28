<?php
// includes/profile/class-profile-user-screen.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The club's custom fields on WordPress's own user profile screens.
 *
 * Everything is editable here, including the fields a member never sees — this
 * IS where a squad number or a DBS date gets set. Required is not enforced:
 * staff routinely change one thing about a member, and blocking that on an
 * unrelated required field would make the screen unusable.
 *
 * Nothing is drawn or saved unless the current user can edit the user in
 * question. WordPress only fires these hooks on a screen it has already gated,
 * but the check is repeated here rather than assumed — the cost is a function
 * call and the failure it prevents is one member reading another's record.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Profile_User_Screen {

	public const NONCE       = 'clubhouse_profile_user';
	public const NONCE_FIELD = '_clubhouse_profile_nonce';

	public static function register(): void {
		add_action( 'show_user_profile', array( __CLASS__, 'render' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'render' ) );
		add_action( 'personal_options_update', array( __CLASS__, 'handle' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'handle' ) );
	}

	/** @param object $user The WP_User whose screen this is. */
	public static function render( object $user ): void {
		$user_id = (int) ( $user->ID ?? 0 );
		if ( $user_id <= 0 || ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}
		$store  = new Blueworx_Clubhouse_Profile_Store( new Blueworx_Clubhouse_Options_Storage() );
		$fields = $store->fields();
		if ( array() === $fields ) {
			return;
		}
		wp_nonce_field( self::NONCE, self::NONCE_FIELD );
		// Built by a pure method; every value inside it is escaped there.
		echo self::table( $fields, $store->answers( $user_id, $fields ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public static function handle( int $user_id ): void {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}
		if ( ! isset( $_POST[ self::NONCE_FIELD ] )
			|| ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified immediately above.
		$post = wp_unslash( $_POST );
		self::save(
			new Blueworx_Clubhouse_Profile_Store( new Blueworx_Clubhouse_Options_Storage() ),
			$user_id,
			is_array( $post ) ? $post : array()
		);
	}

	/**
	 * The club's fields as a standard WordPress settings table.
	 *
	 * @param array<int,array<string,mixed>>         $fields
	 * @param array<string,string|array<int,string>> $answers
	 */
	public static function table( array $fields, array $answers ): string {
		if ( array() === $fields ) {
			return '';
		}
		$out = '<h2>Club details</h2>'
			. '<p>What your club keeps about this member. Set here by club staff; members fill in their own on their Profile page.</p>'
			. '<table class="form-table" role="presentation"><tbody>';

		foreach ( $fields as $field ) {
			$key   = (string) $field['key'];
			$type  = (string) $field['type'];
			$id    = 'clubhouse-profile-' . $key;
			$name  = Blueworx_Clubhouse_Profile_Values::POST_KEY . '[' . $key . ']' . ( 'multiselect' === $type ? '[]' : '' );
			$value = $answers[ $key ] ?? ( 'multiselect' === $type ? array() : '' );

			$out .= '<tr><th><label for="' . esc_attr( $id ) . '">' . esc_html( (string) $field['label'] ) . '</label></th><td>';
			// Always editable: the who setting governs the MEMBER's page, not
			// this one. This screen is where a club field gets its value at all.
			$out .= Blueworx_Clubhouse_Profile_Panel::control( $field, $value, $name, true );

			$notes = array();
			if ( '' !== (string) ( $field['help'] ?? '' ) ) {
				$notes[] = (string) $field['help'];
			}
			if ( 'private' === (string) $field['who'] ) {
				$notes[] = 'The member never sees this.';
			} elseif ( 'club' === (string) $field['who'] ) {
				$notes[] = 'The member can see this but cannot change it.';
			}
			if ( array() !== $notes ) {
				$out .= '<p class="description">' . esc_html( implode( ' ', $notes ) ) . '</p>';
			}
			$out .= '</td></tr>';
		}

		return $out . '</tbody></table>';
	}

	/** @param array<string,mixed> $post */
	public static function save( Blueworx_Clubhouse_Profile_Store $store, int $user_id, array $post ): void {
		if ( $user_id <= 0 ) {
			return;
		}
		$store->save_answers( $user_id, Blueworx_Clubhouse_Profile_Values::from_admin_post( $store->fields(), $post ) );
	}
}
