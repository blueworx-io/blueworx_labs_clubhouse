<?php
// includes/content/class-content-sanitiser.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pure sanitising for Content_Catalogue field values, by field type. Extracted
 * from Content_Controller so that the admin editor and the AI import path
 * share one implementation — an imported file must be treated exactly like
 * form input, and a field type must decide its own sanitising in exactly one
 * place.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Content_Sanitiser {

	/**
	 * Sanitise a single field's value by its catalogue type.
	 *
	 * @param array<string,mixed> $field_def
	 */
	public static function field( array $field_def, mixed $raw, bool $present ): mixed {
		// A value that isn't scalar (e.g. field[key][]=x submitted as an array, or
		// a nested array under an image/select field) must never reach string
		// coercion below — PHP would emit "Array to string conversion" and store
		// the literal "Array". Treat it as though the field were absent.
		if ( $present && ! is_scalar( $raw ) ) {
			$present = false;
		}
		switch ( $field_def['type'] ) {
			case 'text':
				return $present ? sanitize_text_field( (string) $raw ) : '';
			case 'textarea':
				return $present ? sanitize_textarea_field( (string) $raw ) : '';
			case 'shortcode':
				// Same sanitising as a textarea — tags stripped, newlines kept. The
				// brackets, attributes and quotes a shortcode needs all survive that,
				// and stripping tags means the field cannot be used to smuggle raw
				// HTML past the escaping that every other field still gets.
				return $present ? sanitize_textarea_field( (string) $raw ) : '';
			case 'url':
				return $present ? esc_url_raw( (string) $raw ) : '';
			case 'image':
				// '' — not 0 — is the "unset" sentinel every other type uses, and the
				// one Page_Renderer::cget() falls back on. An image field's hidden
				// input always posts, so absint('') === 0 would otherwise land on every
				// untouched image on the first Save and read back as a real override
				// (rendering src="0" and dropping the empty-state fallback).
				// Attachment IDs start at 1, so nothing legitimate is lost.
				$id = $present ? absint( $raw ) : 0;
				return $id > 0 ? $id : '';
			case 'toggle':
				return $present;
			case 'select':
				$value   = $present ? (string) $raw : '';
				$options = $field_def['options'] ?? array();
				return array_key_exists( $value, $options ) ? $value : '';
			default:
				return '';
		}
	}

	/**
	 * Sanitise every posted item of a loop section by its field definitions.
	 *
	 * @param array<int,array<string,mixed>> $loop_fields
	 * @param array<int,mixed>               $raw_items
	 * @return array<int,array<string,mixed>>
	 */
	public static function items( array $loop_fields, array $raw_items ): array {
		$items = array();
		foreach ( $raw_items as $raw_item ) {
			$raw_item = is_array( $raw_item ) ? $raw_item : array();
			$item     = array();
			foreach ( $loop_fields as $field_def ) {
				$fkey          = (string) $field_def['key'];
				$present       = array_key_exists( $fkey, $raw_item );
				$item[ $fkey ] = self::field( $field_def, $present ? $raw_item[ $fkey ] : null, $present );
			}
			$items[] = $item;
		}
		return $items;
	}
}
