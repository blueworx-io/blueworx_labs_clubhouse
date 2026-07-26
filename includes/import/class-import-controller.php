<?php
// includes/import/class-import-controller.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress surface for the AI content import: the Club Content → Import
 * submenu, the prompt download, and the upload → preview → apply flow.
 *
 * handle_request() takes the request arrays and a Storage rather than reading
 * superglobals, so the whole flow is unit-testable without WordPress, mirroring
 * Setup_Controller and Content_Controller. The capability and nonce checks live
 * in the thin WP entry points either side of it.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Import_Controller {

	public const CAPABILITY        = Blueworx_Clubhouse_Owner_Capabilities::SETUP_CAP;
	public const PAGE_SLUG         = 'clubhouse-import';
	public const NONCE             = 'clubhouse_import';
	public const DOWNLOAD_ACTION   = 'clubhouse_import_prompt';
	public const IMAGES_NEEDED_KEY = 'import_images_needed';

	/** Import files are text; a megabyte is a very large club's worth of copy. */
	public const MAX_BYTES = 1048576;

	/** How long an approved-but-unapplied plan survives. */
	private const PLAN_TTL = 3600;

	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'add_menu' ) );
		add_action( 'admin_post_' . self::DOWNLOAD_ACTION, array( self::class, 'download_prompt' ) );
	}

	public static function add_menu(): void {
		add_submenu_page(
			Blueworx_Clubhouse_Content_Controller::PAGE_SLUG,
			'Import',
			'Import',
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( self::class, 'render_page' )
		);
	}

	/** The transient holding this user's approved plan. Per-user so one admin cannot apply another's. */
	private static function plan_key(): string {
		return 'clubhouse_import_plan_' . get_current_user_id();
	}

	public static function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		$posted = array();
		if ( isset( $_POST['clubhouse_import_upload'] ) || isset( $_POST['clubhouse_import_apply'] ) || isset( $_POST['clubhouse_import_cancel'] ) ) {
			check_admin_referer( self::NONCE );
			$posted = wp_unslash( $_POST );
		}
		$file = isset( $_FILES['clubhouse_import_file'] ) ? $_FILES['clubhouse_import_file'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- read via handle_request, which validates before use.

		$storage = new Blueworx_Clubhouse_Options_Storage();
		$model   = self::handle_request( $posted, is_array( $file ) ? $file : array(), $storage );

		$model['download_url'] = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::DOWNLOAD_ACTION ), self::NONCE );
		$model['action_url']   = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
		$model['nonce_field']  = wp_nonce_field( self::NONCE, '_wpnonce', true, false );
		$model['max_upload']   = size_format( self::MAX_BYTES );

		echo Blueworx_Clubhouse_Import_Screen::render( $model ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Import_Screen.
	}

	public static function download_prompt(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( 'You do not have permission to do that.', '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::NONCE );

		$version = defined( 'BLUEWORX_LABS_CLUBHOUSE_VERSION' ) ? BLUEWORX_LABS_CLUBHOUSE_VERSION : 'dev';
		$body    = Blueworx_Clubhouse_Import_Prompt::markdown( (string) $version );

		header( 'Content-Type: text/markdown; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . Blueworx_Clubhouse_Import_Prompt::FILENAME . '"' );
		header( 'Content-Length: ' . strlen( $body ) );
		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain-text download, not HTML.
		exit;
	}

	/**
	 * The whole flow, minus WordPress. Returns the Import_Screen model without
	 * the four presentation keys (download_url, action_url, nonce_field,
	 * max_upload) that only the WP entry point can supply.
	 *
	 * @param array<string,mixed> $post
	 * @param array<string,mixed> $file $_FILES entry for the upload field
	 * @return array<string,mixed>
	 */
	public static function handle_request( array $post, array $file, Blueworx_Clubhouse_Storage $storage ): array {
		if ( isset( $post['clubhouse_import_cancel'] ) ) {
			delete_transient( self::plan_key() );
			return self::model( 'start' );
		}
		if ( isset( $post['clubhouse_import_apply'] ) ) {
			return self::apply( $storage );
		}
		if ( isset( $post['clubhouse_import_upload'] ) ) {
			return self::preview( $file );
		}
		return self::model( 'start' );
	}

	/** @param array<string,mixed> $file */
	private static function preview( array $file ): array {
		$error = self::upload_error( $file );
		if ( '' !== $error ) {
			return self::model( 'start', array( 'error' => $error ) );
		}

		$raw = file_get_contents( (string) $file['tmp_name'] );
		if ( ! is_string( $raw ) ) {
			return self::model( 'start', array( 'error' => 'That file could not be read.' ) );
		}

		// Depth 32 is far beyond anything this format needs; it caps a
		// pathological nesting attack before the parser ever sees the data.
		$decoded = json_decode( $raw, true, 32 );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return self::model( 'start', array( 'error' => 'That file could not be read as JSON. Ask the chat to produce the file again.' ) );
		}

		$parsed = Blueworx_Clubhouse_Import_Parser::parse( $decoded );
		if ( null === $parsed['plan'] ) {
			return self::model( 'start', array( 'error' => $parsed['error'] ) );
		}

		$plan    = $parsed['plan'];
		$summary = Blueworx_Clubhouse_Import_Preview::summary(
			$plan,
			Blueworx_Clubhouse_Import_Applier::demo_counts( array_keys( $plan->collections() ) )
		);

		set_transient( self::plan_key(), $plan->to_array(), self::PLAN_TTL );

		return self::model( 'preview', array(
			'rows'     => $summary['rows'],
			'warnings' => $summary['warnings'],
		) );
	}

	private static function apply( Blueworx_Clubhouse_Storage $storage ): array {
		$stored = get_transient( self::plan_key() );
		if ( ! is_array( $stored ) ) {
			return self::model( 'start', array( 'error' => 'That import has expired. Upload the file again.' ) );
		}
		delete_transient( self::plan_key() );

		$result = Blueworx_Clubhouse_Import_Applier::apply(
			Blueworx_Clubhouse_Import_Plan::from_array( $stored ),
			$storage
		);

		$storage->set( self::IMAGES_NEEDED_KEY, $result['images_needed'] );

		return self::model( 'result', array(
			'rows'          => $result['rows'],
			'warnings'      => $result['warnings'],
			'images_needed' => $result['images_needed'],
		) );
	}

	/**
	 * Refuse a file before reading a byte of it. The size check uses the
	 * reported size deliberately — a file bigger than the cap is rejected
	 * rather than loaded into memory to be measured.
	 *
	 * @param array<string,mixed> $file
	 */
	private static function upload_error( array $file ): string {
		$err = (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE );
		if ( UPLOAD_ERR_NO_FILE === $err || '' === (string) ( $file['tmp_name'] ?? '' ) ) {
			return 'Choose a file to upload first.';
		}
		if ( UPLOAD_ERR_OK !== $err ) {
			return 'That file did not upload correctly. Try again.';
		}
		if ( (int) ( $file['size'] ?? 0 ) > self::MAX_BYTES ) {
			return 'That file is too large to be a ClubHouse import.';
		}
		return '';
	}

	/**
	 * @param array<string,mixed> $overrides
	 * @return array<string,mixed>
	 */
	private static function model( string $state, array $overrides = array() ): array {
		return array_merge( array(
			'state'         => $state,
			'error'         => '',
			'rows'          => array(),
			'warnings'      => array(),
			'images_needed' => array(),
		), $overrides );
	}
}
