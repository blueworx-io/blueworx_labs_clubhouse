<?php
// includes/profile/class-profile-panel.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The member's own view of what the club keeps about them.
 *
 * Pure: it is handed the fields, the answers and a nonce field, and returns
 * HTML. Nothing here decides who may see what — Profile_Values does that, and
 * this asks it rather than re-deriving it, so there is one place to be wrong.
 *
 * A private field never enters this HTML: not as a hidden input, not as
 * read-only text. Hiding one with CSS would put it in the page source, which is
 * not hiding it from anybody.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Profile_Panel {

	public const ACTION = 'clubhouse_profile_save';
	public const NONCE  = 'clubhouse_profile_save';

	/**
	 * @param array<int,array<string,mixed>>            $fields
	 * @param array<string,string|array<int,string>>    $answers
	 * @param array<int,array{type:string,text:string}> $notices
	 */
	public static function render( array $fields, array $answers, string $action_url, string $nonce_field, array $notices = array() ): string {
		$visible = Blueworx_Clubhouse_Profile_Values::visible_to_member( $fields );
		if ( array() === $visible ) {
			return '';
		}
		$editable_count = count( Blueworx_Clubhouse_Profile_Values::writable_by_member( $visible ) );

		$out  = '<form class="clubhouse-profile" method="post" action="' . self::e( $action_url ) . '">';
		$out .= $nonce_field;
		$out .= '<input type="hidden" name="action" value="' . self::e( self::ACTION ) . '">';

		foreach ( $notices as $notice ) {
			$type = (string) ( $notice['type'] ?? 'success' );
			$out .= '<p class="clubhouse-profile__notice clubhouse-profile__notice--' . self::e( $type ) . '" role="status">'
				. self::e( (string) ( $notice['text'] ?? '' ) ) . '</p>';
		}

		$out .= '<div class="clubhouse-profile__fields">';
		foreach ( $visible as $field ) {
			$key      = (string) $field['key'];
			$editable = 'member' === (string) $field['who'];
			$fallback = 'multiselect' === (string) $field['type'] ? array() : '';
			$out     .= self::row( $field, $answers[ $key ] ?? $fallback, $editable );
		}
		$out .= '</div>';

		// Nothing to save is not a reason to draw a button that saves nothing.
		if ( $editable_count > 0 ) {
			$out .= '<button type="submit" class="clubhouse-profile__save">Save my details</button>';
		}
		return $out . '</form>';
	}

	/**
	 * @param array<string,mixed>      $field
	 * @param string|array<int,string> $value
	 */
	private static function row( array $field, string|array $value, bool $editable ): string {
		$key  = (string) $field['key'];
		$id   = 'clubhouse-profile-' . $key;
		$help = (string) ( $field['help'] ?? '' );

		$out  = '<div class="clubhouse-profile__row">';
		$out .= $editable
			? '<label class="clubhouse-profile__label" for="' . self::e( $id ) . '">' . self::e( (string) $field['label'] ) . '</label>'
			: '<p class="clubhouse-profile__label">' . self::e( (string) $field['label'] ) . '</p>';
		$out .= self::control( $field, $value, self::name( $key, (string) $field['type'] ), $editable );
		if ( '' !== $help ) {
			$out .= '<p class="clubhouse-profile__help">' . self::e( $help ) . '</p>';
		}
		return $out . '</div>';
	}

	private static function name( string $key, string $type ): string {
		return Blueworx_Clubhouse_Profile_Values::POST_KEY . '[' . $key . ']' . ( 'multiselect' === $type ? '[]' : '' );
	}

	/**
	 * One field's control, or the plain text a club field shows.
	 *
	 * Reused by the WordPress user screen, where every field is editable — which
	 * is why "editable" is a parameter rather than read off the field's own who
	 * setting. That setting governs the MEMBER's page, not wp-admin.
	 *
	 * @param array<string,mixed>      $field
	 * @param string|array<int,string> $value
	 */
	public static function control( array $field, string|array $value, string $name, bool $editable ): string {
		$type    = (string) $field['type'];
		$key     = (string) $field['key'];
		$id      = 'clubhouse-profile-' . $key;
		$choices = array_map( 'strval', (array) ( $field['choices'] ?? array() ) );
		$req     = ! empty( $field['required'] ) ? ' required' : '';
		$scalar  = is_array( $value ) ? '' : $value;

		if ( ! $editable ) {
			return '<p class="clubhouse-profile__value">' . self::e( self::readable( $field, $value ) ) . '</p>';
		}

		switch ( $type ) {
			case 'textarea':
				return '<textarea id="' . self::e( $id ) . '" name="' . self::e( $name ) . '" rows="4" class="clubhouse-profile__input"' . $req . '>'
					. self::e( $scalar ) . '</textarea>';
			case 'checkbox':
				$checked = '1' === $scalar ? ' checked' : '';
				return '<input type="checkbox" id="' . self::e( $id ) . '" name="' . self::e( $name ) . '" value="1" class="clubhouse-profile__tick"' . $checked . '>';
			case 'select':
				// An empty first option is the only way to answer "none of these"
				// on a field the club did not make required.
				$out = '<select id="' . self::e( $id ) . '" name="' . self::e( $name ) . '" class="clubhouse-profile__input"' . $req . '>'
					. '<option value="">Please choose</option>';
				foreach ( $choices as $choice ) {
					$sel  = $choice === $scalar ? ' selected' : '';
					$out .= '<option value="' . self::e( $choice ) . '"' . $sel . '>' . self::e( $choice ) . '</option>';
				}
				return $out . '</select>';
			case 'multiselect':
				$chosen = is_array( $value ) ? $value : array();
				$size   = min( 6, max( 2, count( $choices ) ) );
				$out    = '<select id="' . self::e( $id ) . '" name="' . self::e( $name ) . '" class="clubhouse-profile__input" multiple size="' . $size . '">';
				foreach ( $choices as $choice ) {
					$sel  = in_array( $choice, $chosen, true ) ? ' selected' : '';
					$out .= '<option value="' . self::e( $choice ) . '"' . $sel . '>' . self::e( $choice ) . '</option>';
				}
				return $out . '</select>';
			case 'number':
			case 'date':
				return '<input type="' . self::e( $type ) . '" id="' . self::e( $id ) . '" name="' . self::e( $name ) . '" value="'
					. self::e( $scalar ) . '" class="clubhouse-profile__input"' . $req . '>';
			default:
				return '<input type="text" id="' . self::e( $id ) . '" name="' . self::e( $name ) . '" value="'
					. self::e( $scalar ) . '" class="clubhouse-profile__input"' . $req . '>';
		}
	}

	/**
	 * What a club field's stored value reads as.
	 *
	 * "Not set" rather than a blank line: an empty row under a label reads as a
	 * broken page, where a member who sees "Not set" knows to ask the club.
	 *
	 * @param array<string,mixed>      $field
	 * @param string|array<int,string> $value
	 */
	private static function readable( array $field, string|array $value ): string {
		if ( is_array( $value ) ) {
			return array() === $value ? 'Not set' : implode( ', ', $value );
		}
		if ( 'checkbox' === (string) $field['type'] ) {
			return '1' === $value ? 'Yes' : 'No';
		}
		return '' === trim( $value ) ? 'Not set' : $value;
	}

	private static function e( string $value ): string {
		return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
	}
}
