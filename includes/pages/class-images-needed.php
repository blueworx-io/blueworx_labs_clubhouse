<?php
// includes/pages/class-images-needed.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The pictures an AI import named but could not fetch, and the reminder an
 * owner gets until they have added them.
 *
 * This was a notice on the Club Pages screen, which built it as it rendered
 * and pruned it as it saved. That screen is gone, so it is a wp-admin notice
 * on the page editors now — and because the editors save through the library's
 * own REST route rather than through anything here, an entry is pruned when
 * the list is read rather than when a picture is saved. Same result, one pass:
 * whatever is in place by the time somebody looks is no longer outstanding.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Images_Needed {

	public static function register(): void {
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}
		add_action( 'admin_notices', array( self::class, 'render' ) );
	}

	/**
	 * Every picture still missing, named and linked to the editor that can add
	 * it. Prunes the ones since filled — in storage as well as in the answer,
	 * so a club that has finished stops paying for the check.
	 *
	 * @return array<int,array{label:string,url:string}>
	 */
	public static function outstanding( Blueworx_Clubhouse_Storage $storage ): array {
		$needed = $storage->get( Blueworx_Clubhouse_Import_Controller::IMAGES_NEEDED_KEY, array() );
		if ( ! is_array( $needed ) || array() === $needed ) {
			return array();
		}

		$content = new Blueworx_Clubhouse_Page_Content( $storage );
		$left    = array();
		$out     = array();

		foreach ( $needed as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$area    = (string) ( $entry['page'] ?? '' );
			$section = (string) ( $entry['section'] ?? '' );
			$field   = (string) ( $entry['field'] ?? '' );
			// An entry stored before 'index' existed (or a hand-edited option)
			// has no such key; default it to -1 so it keeps behaving as a
			// section-level entry rather than being lost.
			$index = isset( $entry['index'] ) ? (int) $entry['index'] : -1;

			if ( self::is_filled( $content, $area, $section, $field, $index ) ) {
				continue; // Added since the import — forgotten, here and in storage.
			}

			// Still outstanding as far as the club's content goes, so it stays
			// in storage even when this plugin can no longer place it: an area
			// whose integration is absent today may be back tomorrow, and
			// dropping the entry would lose the reminder for good.
			$left[] = $entry;

			$address = $area . '/' . $section;
			$label   = Blueworx_Clubhouse_Page_Fields::address_label( $address );
			if ( $label === $address ) {
				continue; // Nothing declares this section, so there is no editor to send anybody to.
			}
			$out[] = array(
				'label' => '' !== (string) ( $entry['label'] ?? '' ) ? (string) $entry['label'] : $label,
				'url'   => Blueworx_Clubhouse_Page_Editors::editor_url( $area ),
			);
		}

		if ( count( $left ) !== count( $needed ) ) {
			$storage->set( Blueworx_Clubhouse_Import_Controller::IMAGES_NEEDED_KEY, array_values( $left ) );
		}
		return $out;
	}

	/**
	 * Has this slot been filled? Branches on $index exactly as
	 * Import_Applier::place_image() does: a section-level image (index < 0)
	 * lives at a plain content field, while a loop-item image (index >= 0)
	 * lives at items[index][field] — reading the section field for a loop-item
	 * entry would always see '' and the entry would never clear.
	 */
	private static function is_filled( Blueworx_Clubhouse_Page_Content $content, string $area, string $section, string $field, int $index ): bool {
		if ( $index < 0 ) {
			$value = $content->get( $area, $section, $field, '' );
		} else {
			$items = $content->get_items( $area, $section );
			$value = array_key_exists( $index, $items ) ? ( $items[ $index ][ $field ] ?? '' ) : '';
		}
		return '' !== $value && 0 !== $value;
	}

	/** The sentence above the list, counted the way an owner would say it. */
	public static function text( int $count ): string {
		return sprintf(
			'%d %s from your import could not be fetched. Add %s whenever you have the files:',
			$count,
			1 === $count ? 'picture' : 'pictures',
			1 === $count ? 'it' : 'them'
		);
	}

	/**
	 * @param array<int,array{label:string,url:string}> $outstanding
	 */
	public static function html( array $outstanding ): string {
		if ( array() === $outstanding ) {
			return '';
		}
		$out = '<div class="notice notice-warning"><p>' . esc_html( self::text( count( $outstanding ) ) ) . '</p><ul>';
		foreach ( $outstanding as $item ) {
			$out .= '<li><a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['label'] ) . '</a></li>';
		}
		return $out . '</ul></div>';
	}

	/**
	 * Printed on the Clubhouse screens only. Everywhere else in wp-admin this
	 * is somebody else's screen, and a notice about club pictures on it is
	 * noise — the same reason the old one only ever showed on Club Pages.
	 */
	public static function render(): void {
		if ( ! function_exists( 'get_current_screen' ) || ! current_user_can( Blueworx_Clubhouse_Owner_Capabilities::CONTENT_CAP ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( null === $screen || ! str_contains( (string) $screen->id, 'clubhouse' ) ) {
			return;
		}
		echo self::html( self::outstanding( new Blueworx_Clubhouse_Options_Storage() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in html().
	}
}
