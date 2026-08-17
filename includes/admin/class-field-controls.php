<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The individual form controls a content field is edited with: one text box,
 * textarea, image picker, select or switch per field definition.
 *
 * These were the innermost part of the Club Pages screen, and are the one part
 * of it that outlived it — the Blocks screen edits the same field definitions
 * and has to draw them the same way, or a club would meet two different image
 * pickers depending on which screen it opened.
 *
 * Pure: no WordPress, no request data, no persistence. The caller supplies the
 * field definition, the stored value and the input's name.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Field_Controls {

	/** Shared <datalist> every URL field points at. Emitted by the screen. */
	public const LINKS_DATALIST_ID = 'clubhouse-links';

	/** @param array<string,mixed> $field */
	public static function render( array $field, mixed $value, string $name ): string {
		return self::field_row( $field, $name, $value );
	}

	private static function esc( string $v ): string {
		return htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' );
	}

	/** A DOM-safe id/anchor fragment built from arbitrary key parts. */
	private static function slug_id( string ...$parts ): string {
		$joined = implode( '-', $parts );
		return (string) preg_replace( '/[^a-zA-Z0-9_-]+/', '-', $joined );
	}


	/** @param array<string,mixed> $field_def */
	private static function field_row( array $field_def, string $name, mixed $value ): string {
		$id    = self::slug_id( 'field', $name );
		$label = (string) $field_def['label'];
		$type  = (string) $field_def['type'];

		if ( 'toggle' === $type ) {
			// null means the club has never touched this switch, so what it draws
			// is the catalogue's declared default — which is what the page is
			// actually doing. Drawing it off regardless used to misreport a live
			// cookie notice as switched off, and saving the tab then wrote that
			// back and turned it off for real.
			$state   = null === $value ? (bool) ( $field_def['default'] ?? false ) : $value;
			$checked = ( true === $state || '1' === $state || 1 === $state ) ? ' checked' : '';
			return '<label class="clubhouse-field clubhouse-field--toggle">'
				. '<input type="checkbox" id="' . self::esc( $id ) . '" name="' . self::esc( $name ) . '" value="1"' . $checked . '>'
				. '<span class="clubhouse-field__label">' . self::esc( $label ) . '</span></label>';
		}

		$out = '<div class="clubhouse-field"><label class="clubhouse-label" for="' . self::esc( $id ) . '">' . self::esc( $label ) . '</label>';
		switch ( $type ) {
			case 'textarea':
				$rows = isset( $field_def['rows'] ) ? (int) $field_def['rows'] : 3;
				$out .= '<textarea id="' . self::esc( $id ) . '" name="' . self::esc( $name ) . '" rows="' . $rows . '" placeholder="' . self::esc( (string) ( $field_def['placeholder'] ?? '' ) ) . '" class="clubhouse-input">'
					. self::esc( (string) $value ) . '</textarea>';
				break;
			case 'shortcode':
				// Monospace and a fixed placeholder, because what goes in here is code
				// rather than prose. The note names the plugins this exists for, so an
				// owner is not left guessing what a "shortcode" is.
				$out .= '<textarea id="' . self::esc( $id ) . '" name="' . self::esc( $name ) . '" rows="3" class="clubhouse-input clubhouse-input--code" placeholder="[surecart_checkout]" spellcheck="false">'
					. self::esc( (string) $value ) . '</textarea>'
					. '<p class="clubhouse-help">Paste a shortcode from another plugin — SureCart, LatePoint, SureForms or SureDash. '
					. 'Leave it empty and nothing is shown here.</p>';
				break;
			case 'url':
				// list= offers every Clubhouse page as a type-to-search suggestion, so
				// nobody has to know the ?clubhouse_page=… form by heart. It is a
				// suggestion list, not a picker: the field still takes any URL, which
				// external links and other plugins' pages need.
				$out .= '<input type="url" id="' . self::esc( $id ) . '" name="' . self::esc( $name ) . '" value="' . self::esc( (string) $value ) . '" placeholder="' . self::esc( (string) ( $field_def['placeholder'] ?? '' ) ) . '" class="clubhouse-input" list="' . self::LINKS_DATALIST_ID . '">';
				break;
			case 'image':
				$out .= '<div class="clubhouse-media" data-media="' . self::esc( $name ) . '">'
					. '<input type="hidden" id="' . self::esc( $id ) . '" name="' . self::esc( $name ) . '" value="' . self::esc( (string) $value ) . '">'
					. '<span class="clubhouse-media__hint">' . ( '' !== (string) $value && '0' !== (string) $value ? 'Image set (#' . self::esc( (string) $value ) . ')' : 'No image set' ) . '</span>'
					. '<button type="button" class="clubhouse-btn clubhouse-btn--sm" data-media-pick>Choose image</button>'
					. '<button type="button" class="clubhouse-btn-link" data-media-clear>Remove</button>'
					. '</div>';
				break;
			case 'select':
				$options = (array) ( $field_def['options'] ?? array() );
				// A value the shop no longer offers: keep it visible and selected, and
				// say so. Rendering it as "Not connected" would tell the owner their
				// tier was never wired up, when in fact its product has gone.
				if ( '' !== (string) $value && ! array_key_exists( (string) $value, $options ) ) {
					$options[ (string) $value ] = 'No longer available — visitors see your typed price, and this clears when you save';
				}
				$out    .= '<select id="' . self::esc( $id ) . '" name="' . self::esc( $name ) . '" class="clubhouse-input">';
				foreach ( $options as $opt_value => $opt_label ) {
					$selected = ( (string) $value === (string) $opt_value ) ? ' selected' : '';
					$out     .= '<option value="' . self::esc( (string) $opt_value ) . '"' . $selected . '>' . self::esc( (string) $opt_label ) . '</option>';
				}
				$out .= '</select>';
				break;
			default: // text
				$out .= '<input type="text" id="' . self::esc( $id ) . '" name="' . self::esc( $name ) . '" value="' . self::esc( (string) $value ) . '" placeholder="' . self::esc( (string) ( $field_def['placeholder'] ?? '' ) ) . '" class="clubhouse-input">';
		}
		$out .= '</div>';
		return $out;
	}
}
