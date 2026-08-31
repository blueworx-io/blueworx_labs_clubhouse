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

		// The role tags are prebuilt markup from Access_Screen, empty for anyone
		// but an administrator — the controller decides that, so this class
		// stays WP-free.
		$out = Blueworx_Clubhouse_Admin_Shell::open(
			'Clubhouse · Import',
			'Import your content',
			'Bring a club\'s existing content in, under Clubhouse.',
			(string) ( $model['role_tags'] ?? '' )
		);

		if ( '' !== $error ) {
			$out .= '<div class="bw-notice bw-notice--danger">'
				. '<i class="bw-icon bw-notice__icon" data-lucide="triangle-alert"></i>'
				. '<div class="bw-notice__body"><p class="bw-notice__text">' . self::esc( $error ) . '</p></div></div>';
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

		return $out . Blueworx_Clubhouse_Admin_Shell::close();
	}

	/** @param array<string,mixed> $model */
	private static function start_panel( array $model ): string {
		$one = '<p>It describes every part of your site. Download it, then paste it into an '
			. 'AI chat — it will interview you and write your content for you.</p>'
			. '<p><a class="bw-btn bw-btn--primary" href="' . self::esc_url( (string) $model['download_url'] ) . '">'
			. 'Download the prompt</a></p>';

		$two  = '<p>The chat will produce a file called <code>clubhouse-import.json</code>. '
			. 'You will see exactly what it changes before anything is saved. '
			. 'You can upload as many times as you like — each upload only changes what that file contains.</p>';
		$two .= '<form method="post" enctype="multipart/form-data" action="' . self::esc_url( (string) $model['action_url'] ) . '">';
		$two .= (string) $model['nonce_field'];
		$two .= '<p><input class="bw-input" type="file" name="clubhouse_import_file" accept=".json,application/json"></p>';
		$two .= '<p class="bw-fieldnote">Maximum file size: ' . self::esc( (string) $model['max_upload'] ) . '.</p>';
		$two .= '<p><button type="submit" class="bw-btn bw-btn--primary" name="clubhouse_import_upload" value="1">Review this file</button></p>';
		$two .= '</form>';

		return Blueworx_Clubhouse_Admin_Shell::card( 'Step 1', 'Download the prompt', '', $one )
			. Blueworx_Clubhouse_Admin_Shell::card( 'Step 2', 'Upload the file it gives you', '', $two );
	}

	/** @param array<string,mixed> $model */
	private static function preview_panel( array $model ): string {
		$rows = is_array( $model['rows'] ?? null ) ? $model['rows'] : array();

		if ( array() === $rows ) {
			$body = '<p>There is nothing to import in that file.</p>'
				. '<p><a class="bw-btn bw-btn--secondary" href="' . self::esc_url( (string) $model['action_url'] ) . '">Start again</a></p>';
			return Blueworx_Clubhouse_Admin_Shell::card( 'Review', 'Review this import', '', $body )
				. self::warnings( $model );
		}

		$body  = self::rows_table( $rows );
		$body .= '<form method="post" action="' . self::esc_url( (string) $model['action_url'] ) . '">';
		$body .= (string) $model['nonce_field'];
		$body .= self::sections_choice( $model );
		$body .= '<p><button type="submit" class="bw-btn bw-btn--primary" name="clubhouse_import_apply" value="1">Apply this import</button> ';
		$body .= '<button type="submit" class="bw-btn bw-btn--secondary" name="clubhouse_import_cancel" value="1">Cancel</button></p>';
		$body .= '</form>';

		return Blueworx_Clubhouse_Admin_Shell::card(
			'Review',
			'Review this import',
			'Nothing has been saved yet. This is what applying the file would change.',
			$body
		) . self::warnings( $model );
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

		$out = '<label class="bw-check"><input type="checkbox" name="clubhouse_import_sections" value="1" checked>'
			. '<span class="bw-check__text">Switch off the sections this file has no content for</span></label>';

		if ( array() === $off ) {
			$out .= '<p class="bw-fieldnote">Nothing would be switched off — this file covers every section '
				. 'showing on the pages it touches.</p>';
			return $out;
		}

		$out .= '<p class="bw-fieldnote">These sections are showing demo content and would be switched off. '
			. 'You can switch any of them back on later under Clubhouse Setup.</p><ul>';
		foreach ( $off as $label ) {
			$out .= '<li>' . self::esc( (string) $label ) . '</li>';
		}
		return $out . '</ul>';
	}

	/** @param array<string,mixed> $model */
	private static function result_panel( array $model ): string {
		$rows   = is_array( $model['rows'] ?? null ) ? $model['rows'] : array();
		$needed = is_array( $model['images_needed'] ?? null ) ? $model['images_needed'] : array();

		$body  = array() === $rows ? '<p>Nothing was changed.</p>' : self::rows_table( $rows );
		$body .= '<p><a class="bw-btn bw-btn--primary" href="' . self::esc_url( (string) $model['action_url'] ) . '">Import another file</a></p>';
		$out   = Blueworx_Clubhouse_Admin_Shell::card( 'Done', 'Import complete', '', $body );

		if ( array() !== $needed ) {
			$list = '<ul>';
			foreach ( $needed as $item ) {
				$list .= '<li>' . self::esc( (string) ( $item['label'] ?? '' ) ) . '</li>';
			}
			$list .= '</ul>';
			$out  .= Blueworx_Clubhouse_Admin_Shell::card(
				'Still to do',
				'Images still needed',
				'These picture slots are still empty. Open the page each one is on and add it whenever you have the picture.',
				$list
			);
		}

		return $out . self::warnings( $model );
	}

	/** @param array<int,array{label:string,detail:string}> $rows */
	private static function rows_table( array $rows ): string {
		$out = '<table class="bw-table"><tbody>';
		foreach ( $rows as $row ) {
			$out .= '<tr><th scope="row"><span class="bw-table__primary">' . self::esc( (string) ( $row['label'] ?? '' ) ) . '</span></th>'
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
		$list = '<ul>';
		foreach ( $warnings as $warning ) {
			$list .= '<li>' . self::esc( (string) $warning ) . '</li>';
		}
		$list .= '</ul>';

		return Blueworx_Clubhouse_Admin_Shell::card(
			'Skipped',
			'Ignored',
			'These parts of the file did not match anything in your site, so they were skipped.',
			$list
		);
	}
}
