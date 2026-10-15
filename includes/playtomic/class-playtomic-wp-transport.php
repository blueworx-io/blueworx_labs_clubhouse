<?php
// includes/playtomic/class-playtomic-wp-transport.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * wp_remote_request, shaped for the Playtomic client. The only place the
 * plugin makes an HTTP call to Playtomic.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Playtomic_Wp_Transport implements Blueworx_Clubhouse_Playtomic_Transport {

	private const TIMEOUT = 15;

	public function request( string $method, string $url, array $headers, ?string $body ): array {
		$args = array( 'method' => $method, 'headers' => $headers, 'timeout' => self::TIMEOUT );
		if ( null !== $body ) {
			$args['body'] = $body;
		}
		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			throw new Blueworx_Clubhouse_Playtomic_Exception( 'Could not reach Playtomic: ' . $response->get_error_message() . '.' );
		}
		$out = array();
		$raw = wp_remote_retrieve_headers( $response );
		if ( is_object( $raw ) && method_exists( $raw, 'getAll' ) ) {
			$raw = $raw->getAll();
		} elseif ( $raw instanceof Traversable ) {
			$raw = iterator_to_array( $raw );
		} elseif ( ! is_array( $raw ) ) {
			$raw = array();
		}
		foreach ( $raw as $name => $value ) {
			$out[ strtolower( (string) $name ) ] = is_array( $value ) ? implode( ', ', $value ) : (string) $value;
		}
		return array(
			'status'  => (int) wp_remote_retrieve_response_code( $response ),
			'body'    => (string) wp_remote_retrieve_body( $response ),
			'headers' => $out,
		);
	}
}
