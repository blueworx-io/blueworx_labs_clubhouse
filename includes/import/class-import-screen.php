<?php
// includes/import/class-import-screen.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pure HTML for the Clubhouse → Import screen, in three states: offer the
 * prompt and an upload; review a parsed plan; report what an apply did. Makes
 * no WordPress calls and reads no request data — the controller hands it a
 * finished model. Every value is escaped here; the only raw markup emitted is
 * the controller's own wp_nonce_field() output.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Import_Screen {

	private static function esc( string $v ): string {
		return htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' );
	}

	/** Escape a URL for an href, refusing any scheme that is not http(s). */
	private static function esc_url( string $v ): string {
		// Collapse whitespace/control chars so an obfuscated scheme (e.g. "java\tscript:") can't slip past the check.
		$probe = (string) preg_replace( '/[\s\x00-\x1F]+/', '', $v );
		if ( preg_match( '/^([a-zA-Z][a-zA-Z0-9+.\-]*):/', $probe, $m ) ) {
			if ( ! in_array( strtolower( $m[1] ), array( 'http', 'https' ), true ) ) {
				return '';
			}
		}
		return self::esc( $v );
	}

	/** @param array<string,mixed> $model */
	public static function render( array $model ): string {
		$state = (string) ( $model['state'] ?? 'start' );
		$error = (string) ( $model['error'] ?? '' );

		$out  = '<div class="wrap clubhouse-wrap">';
		$out .= '<div class="clubhouse-import">';
		// Prebuilt tag markup from Access_Screen, empty for anyone but an
		// administrator — the controller decides that, so this class stays WP-free.
		$out .= '<div class="clubhouse-head"><div class="clubhouse-head__titles">'
			. '<p class="clubhouse-eyebrow">Clubhouse · Import</p>'
			. '<h1 class="clubhouse-head__h1">Import your content</h1>'
			. (string) ( $model['role_tags'] ?? '' ) . '</div></div>';

		if ( '' !== $error ) {
			$out .= '<div class="notice notice-error"><p>' . self::esc( $error ) . '</p></div>';
		}

		switch ( $state ) {
			case 'preview':
				$out .= self::preview_panel( $model );
				break;
			case 'result':
				$out .= self::result_panel( $model );
				break;
			default:
				$out .= self::start_panel( $model );
		}

		$out .= '</div></div>';
		return $out;
	}

	/** @param array<string,mixed> $model */
	private static function start_panel( array $model ): string {
		$out  = '<div class="clubhouse-import__step">';
		$out .= '<h2>1. Download the prompt</h2>';
		$out .= '<p>It describes every part of your site. Download it, then paste it into an '
			. 'AI chat — it will interview you and write your content for you.</p>';
		$out .= '<p><a class="button button-primary" href="' . self::esc_url( (string) $model['download_url'] ) . '">'
			. 'Download the prompt</a></p>';
		$out .= '</div>';

		$out .= '<div class="clubhouse-import__step">';
		$out .= '<h2>2. Upload the file it gives you</h2>';
		$out .= '<p>The chat will produce a file called <code>clubhouse-import.json</code>. '
			. 'You will see exactly what it changes before anything is saved. '
			. 'You can upload as many times as you like — each upload only changes what that file contains.</p>';
		$out .= '<form method="post" enctype="multipart/form-data" action="' . self::esc_url( (string) $model['action_url'] ) . '">';
		$out .= (string) $model['nonce_field'];
		$out .= '<p><input type="file" name="clubhouse_import_file" accept=".json,application/json"></p>';
		$out .= '<p class="description">Maximum file size: ' . self::esc( (string) $model['max_upload'] ) . '.</p>';
		$out .= '<p><button type="submit" class="button button-primary" name="clubhouse_import_upload" value="1">Review this file</button></p>';
		$out .= '</form></div>';
		return $out;
	}

	/** @param array<string,mixed> $model */
	private static function preview_panel( array $model ): string {
		$rows = is_array( $model['rows'] ?? null ) ? $model['rows'] : array();

		$out  = '<div class="clubhouse-import__step">';
		$out .= '<h2>Review this import</h2>';

		if ( array() === $rows ) {
			$out .= '<p>There is nothing to import in that file.</p>';
			$out .= '<p><a class="button" href="' . self::esc_url( (string) $model['action_url'] ) . '">Start again</a></p>';
			$out .= '</div>';
			$out .= self::warnings( $model );
			return $out;
		}

		$out .= '<p>Nothing has been saved yet. This is what applying the file would change:</p>';
		$out .= self::rows_table( $rows );
		$out .= '<form method="post" action="' . self::esc_url( (string) $model['action_url'] ) . '">';
		$out .= (string) $model['nonce_field'];
		$out .= self::sections_choice( $model );
		$out .= '<p><button type="submit" class="button button-primary" name="clubhouse_import_apply" value="1">Apply this import</button> ';
		$out .= '<button type="submit" class="button" name="clubhouse_import_cancel" value="1">Cancel</button></p>';
		$out .= '</form></div>';
		$out .= self::warnings( $model );
		return $out;
	}

	/**
	 * The "tidy up the sections" choice, ticked by default: an owner importing
	 * their own content almost never wants the demo sections the file says
	 * nothing about left showing underneath it. Naming the sections that would
	 * go is the whole point — the content rows above already show what is being
	 * filled in, but nothing else on this screen shows what is being taken away.
	 *
	 * The field name is the plain-HTML twin of Import_Controller::SECTIONS_FIELD,
	 * hardcoded here the same way the apply and cancel buttons are.
	 *
	 * @param array<string,mixed> $model
	 */
	private static function sections_choice( array $model ): string {
		$off = is_array( $model['sections_off'] ?? null ) ? $model['sections_off'] : array();

		$out = '<p><label><input type="checkbox" name="clubhouse_import_sections" value="1" checked> '
			. 'Switch off the sections this file has no content for</label></p>';

		if ( array() === $off ) {
			$out .= '<p class="description">Nothing would be switched off — this file covers every section '
				. 'showing on the pages it touches.</p>';
			return $out;
		}

		$out .= '<p class="description">These sections are showing demo content and would be switched off. '
			. 'You can put any of them back on the page later under Content → Pages.</p><ul class="clubhouse-import__off">';
		foreach ( $off as $label ) {
			$out .= '<li>' . self::esc( (string) $label ) . '</li>';
		}
		return $out . '</ul>';
	}

	/** @param array<string,mixed> $model */
	private static function result_panel( array $model ): string {
		$rows   = is_array( $model['rows'] ?? null ) ? $model['rows'] : array();
		$needed = is_array( $model['images_needed'] ?? null ) ? $model['images_needed'] : array();

		$out  = '<div class="clubhouse-import__step">';
		$out .= '<h2>Import complete</h2>';
		$out .= array() === $rows ? '<p>Nothing was changed.</p>' : self::rows_table( $rows );
		$out .= '<p><a class="button button-primary" href="' . self::esc_url( (string) $model['action_url'] ) . '">Import another file</a></p>';
		$out .= '</div>';

		if ( array() !== $needed ) {
			$out .= '<div class="clubhouse-import__step">';
			$out .= '<h2>Images still needed</h2>';
			$out .= '<p>These picture slots are still empty. Add them under Content → Blocks whenever you have the images.</p><ul>';
			foreach ( $needed as $item ) {
				$out .= '<li>' . self::esc( (string) ( $item['label'] ?? '' ) ) . '</li>';
			}
			$out .= '</ul></div>';
		}

		$out .= self::warnings( $model );
		return $out;
	}

	/** @param array<int,array{label:string,detail:string}> $rows */
	private static function rows_table( array $rows ): string {
		$out = '<table class="widefat striped clubhouse-import__rows"><tbody>';
		foreach ( $rows as $row ) {
			$out .= '<tr><th scope="row">' . self::esc( (string) ( $row['label'] ?? '' ) ) . '</th>'
				. '<td>' . self::esc( (string) ( $row['detail'] ?? '' ) ) . '</td></tr>';
		}
		return $out . '</tbody></table>';
	}

	/** @param array<string,mixed> $model */
	private static function warnings( array $model ): string {
		$warnings = is_array( $model['warnings'] ?? null ) ? $model['warnings'] : array();
		if ( array() === $warnings ) {
			return '';
		}
		$out = '<div class="clubhouse-import__step"><h2>Ignored</h2>'
			. '<p>These parts of the file did not match anything in your site, so they were skipped.</p><ul>';
		foreach ( $warnings as $warning ) {
			$out .= '<li>' . self::esc( (string) $warning ) . '</li>';
		}
		return $out . '</ul></div>';
	}
}
