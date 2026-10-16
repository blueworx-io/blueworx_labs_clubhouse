<?php
// includes/playtomic/class-playtomic-secret.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps the Playtomic secret encrypted at rest.
 *
 * WordPress has no secret store, so the key is derived from material the
 * site already keeps private (its salts — see Playtomic_Controller). Sodium
 * when PHP has it, OpenSSL otherwise, and a plain prefix as the last resort
 * so a site with neither still works and the screen can say so.
 *
 * Pure: no WordPress at all.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Playtomic_Secret {

	private const SODIUM  = 's1:';
	private const OPENSSL = 'o1:';
	private const PLAIN   = 'p1:';

	private string $key;

	public function __construct( string $key_material ) {
		$this->key = hash( 'sha256', 'clubhouse-playtomic|' . $key_material, true );
	}

	/** True when the secret will actually be encrypted on this server. */
	public static function can_encrypt(): bool {
		return function_exists( 'sodium_crypto_secretbox' ) || function_exists( 'openssl_encrypt' );
	}

	public function encrypt( string $plain ): string {
		if ( '' === $plain ) {
			return '';
		}
		if ( function_exists( 'sodium_crypto_secretbox' ) ) {
			$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			return self::SODIUM . base64_encode( $nonce . sodium_crypto_secretbox( $plain, $nonce, $this->key ) );
		}
		if ( function_exists( 'openssl_encrypt' ) ) {
			$iv   = random_bytes( 16 );
			$body = openssl_encrypt( $plain, 'aes-256-cbc', $this->key, OPENSSL_RAW_DATA, $iv );
			return false === $body ? '' : self::OPENSSL . base64_encode( $iv . $body );
		}
		return self::PLAIN . base64_encode( $plain );
	}

	public function decrypt( string $stored ): string {
		if ( strlen( $stored ) < 4 ) {
			return '';
		}
		$prefix = substr( $stored, 0, 3 );
		$raw    = base64_decode( substr( $stored, 3 ), true );
		if ( false === $raw ) {
			return '';
		}
		try {
			if ( self::SODIUM === $prefix && function_exists( 'sodium_crypto_secretbox_open' ) ) {
				$n   = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
				$out = strlen( $raw ) > $n ? sodium_crypto_secretbox_open( substr( $raw, $n ), substr( $raw, 0, $n ), $this->key ) : false;
				return false === $out ? '' : $out;
			}
			if ( self::OPENSSL === $prefix && function_exists( 'openssl_decrypt' ) ) {
				$out = strlen( $raw ) > 16 ? openssl_decrypt( substr( $raw, 16 ), 'aes-256-cbc', $this->key, OPENSSL_RAW_DATA, substr( $raw, 0, 16 ) ) : false;
				return false === $out ? '' : $out;
			}
			if ( self::PLAIN === $prefix ) {
				return $raw;
			}
		} catch ( \Throwable $e ) {
			return '';
		}
		return '';
	}
}
