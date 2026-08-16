<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reading a block's content, with the same rules Page_Renderer's cget()/citems()
 * have always used: a field the owner has not filled in — unset, or emptied —
 * falls back to the code default, and an empty item list falls back to the
 * default list.
 *
 * That "empty means unset" rule is why clearing a field puts the default back
 * rather than blanking the section, and it has to survive the move to blocks or
 * every club that ever cleared a box would see its page change.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Block_Content {

	/**
	 * A block's own value for a field, or the default when it has none.
	 *
	 * @param array<string,mixed> $content  The block's stored content.
	 * @param array<string,mixed> $defaults The code defaults for this block.
	 */
	public static function field( array $content, array $defaults, string $key, mixed $fallback = '' ): mixed {
		$default = $defaults[ $key ] ?? $fallback;
		$value   = $content[ $key ] ?? null;
		return ( null === $value || '' === $value ) ? $default : $value;
	}

	/** field() cast to string, which is what most of Sections wants. */
	public static function text( array $content, array $defaults, string $key, mixed $fallback = '' ): string {
		return (string) self::field( $content, $defaults, $key, $fallback );
	}

	/**
	 * A block's repeatable items, or the default list when it has stored none.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function items( array $content, array $defaults, string $key = 'items' ): array {
		$stored = $content[ $key ] ?? array();
		$stored = is_array( $stored ) ? array_values( $stored ) : array();
		if ( array() !== $stored ) {
			return $stored;
		}
		$default = $defaults[ $key ] ?? array();
		return is_array( $default ) ? array_values( $default ) : array();
	}

	/**
	 * Resolve a stored image field to a URL. Stored values are attachment IDs;
	 * '' (no override) and any non-digit string (a raw URL, as every render test
	 * passes) come back unchanged. Same rule as Page_Renderer::media_src().
	 */
	public static function media_src( string $val ): string {
		if ( ctype_digit( $val ) && function_exists( 'wp_get_attachment_image_url' ) ) {
			$url = wp_get_attachment_image_url( (int) $val, 'large' );
			return is_string( $url ) ? $url : $val;
		}
		return $val;
	}

	/**
	 * Split a textarea's "one item per line" convention into a trimmed,
	 * non-empty array. An array passes through unchanged.
	 *
	 * @return array<int,string>
	 */
	public static function lines( mixed $val ): array {
		if ( is_array( $val ) ) {
			return $val;
		}
		return array_values(
			array_filter(
				array_map( 'trim', explode( "\n", (string) $val ) ),
				static fn( string $l ): bool => '' !== $l
			)
		);
	}
}
