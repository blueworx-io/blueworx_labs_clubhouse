<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One club, one run: copies a club's words out of the old option-backed
 * content store and onto the real pages (and the flat global option) that
 * now hold them. Deleted in phase 4 once it has done its job — there is no
 * back-compat layer here and no permanent upgrade path.
 *
 * Reads Content_Store directly rather than through it — Content_Store is the
 * last thing that still reads the old option, and it is itself deleted in
 * task 10, so this class has to outlive it. Walks Page_Fields::areas(), not
 * the old catalogue: the new shape is the target, and anything sitting in the
 * old option under an address Page_Fields no longer declares is content
 * nothing would ever have rendered.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Content_Migration {

	/**
	 * Marks that run() has completed at least once — not that it moved
	 * anything, since a club with nothing in the old store yet is still a
	 * completed run. Read by has_run() so the command that drives this can
	 * tell an owner it has already been done.
	 */
	private const DONE_KEY = 'content_migration_done';

	/**
	 * @return array{moved:int,skipped:array<int,string>,pages:array<string,int>}
	 */
	public static function run( Blueworx_Clubhouse_Storage $storage ): array {
		$old        = new Blueworx_Clubhouse_Content_Store( $storage );
		$new        = new Blueworx_Clubhouse_Page_Content( $storage );
		$visibility = new Blueworx_Clubhouse_Visibility( $storage );

		$moved   = 0;
		$skipped = array();
		$pages   = array();

		foreach ( Blueworx_Clubhouse_Page_Fields::areas() as $area => $spec ) {
			$before_area = $moved;
			$has_page    = self::has_page( $area );

			foreach ( $spec['tabs'] as $tab ) {
				foreach ( $tab['panels'] as $panel ) {
					$section = (string) $panel['id'];

					foreach ( $panel['fields'] as $field ) {
						$key = Blueworx_Clubhouse_Page_Fields::field_key( $section, (string) $field['id'] );
						if ( '' === $key ) {
							continue; // The panel's own auto-declared switch — nothing was ever stored under this id.
						}
						self::migrate_field( $old, $new, $area, $section, $key, (string) $field['kind'], $has_page, $moved, $skipped );
					}

					// The Shown switch isn't read from the old content option at
					// all — it lives in Visibility, under the same storage — so it
					// is written unconditionally rather than gated on "was it ever
					// saved". A page with no post behind it still gets the call;
					// Page_Content::set() no-ops safely, and there is nothing to
					// report, because there was never a place to lose this to.
					if ( ! empty( $panel['hideable'] ) ) {
						$new->set( $area, $section, '_shown', $visibility->is_section_visible( $area, $section ) );
						if ( $has_page ) {
							++$moved;
						}
					}
				}
			}

			$pages[ $area ] = $moved - $before_area;
		}

		$storage->set( self::DONE_KEY, true );

		return array(
			'moved'   => $moved,
			'skipped' => $skipped,
			'pages'   => $pages,
		);
	}

	public static function has_run( Blueworx_Clubhouse_Storage $storage ): bool {
		return (bool) $storage->get( self::DONE_KEY, false );
	}

	/**
	 * One field's worth of the move — the one place that decides, per kind,
	 * whether an old value counts as placed. $moved and $skipped are threaded
	 * through by reference so repeaters, media, toggles and plain fields all
	 * share the same bookkeeping.
	 *
	 * @param array<int,string> $skipped
	 */
	private static function migrate_field(
		Blueworx_Clubhouse_Content_Store $old,
		Blueworx_Clubhouse_Page_Content $new,
		string $area,
		string $section,
		string $key,
		string $kind,
		bool $has_page,
		int &$moved,
		array &$skipped
	): void {
		$address = $area . '/' . $section . '/' . $key;

		if ( 'repeater' === $kind ) {
			$items = $old->get_items( $area, $section );
			if ( array() === $items ) {
				return; // Never saved, or saved empty — either way, nothing to place.
			}
			if ( ! $has_page ) {
				$skipped[] = $address;
				return;
			}
			$new->set_items( $area, $section, $items );
			++$moved;
			return;
		}

		// Every other kind reads as a single value, with a null default —
		// null means the field was never saved, and nothing is written for it.
		$value = $old->get( $area, $section, $key, null );
		if ( null === $value ) {
			return;
		}

		if ( ! $has_page ) {
			$skipped[] = $address;
			return;
		}

		if ( 'media' === $kind ) {
			self::migrate_media( $new, $area, $section, $key, $value, $address, $moved, $skipped );
			return;
		}

		if ( 'toggle' === $kind ) {
			$new->set( $area, $section, $key, (bool) $value );
			++$moved;
			return;
		}

		$new->set( $area, $section, $key, $value );
		++$moved;
	}

	/**
	 * A media field's value has held two shapes: an attachment id, and a raw
	 * URL from a demo or a preview. The kind is an integer, so a raw URL would
	 * cast to 0 and the picture would vanish — never written; a numeric value
	 * writes straight through, and anything else is resolved through
	 * attachment_url_to_postid(), a hit written and a miss reported.
	 *
	 * @param array<int,string> $skipped
	 */
	private static function migrate_media(
		Blueworx_Clubhouse_Page_Content $new,
		string $area,
		string $section,
		string $key,
		mixed $value,
		string $address,
		int &$moved,
		array &$skipped
	): void {
		if ( is_numeric( $value ) ) {
			$new->set( $area, $section, $key, (int) $value );
			++$moved;
			return;
		}

		$resolved = function_exists( 'attachment_url_to_postid' ) ? (int) attachment_url_to_postid( (string) $value ) : 0;
		if ( $resolved > 0 ) {
			$new->set( $area, $section, $key, $resolved );
			++$moved;
			return;
		}

		$skipped[] = $address;
	}

	/**
	 * Whether an area has somewhere to write to. The global option always
	 * does; a club page needs the real post its content would be saved on.
	 */
	private static function has_page( string $area ): bool {
		if ( Blueworx_Clubhouse_Page_Content::GLOBAL_AREA === $area ) {
			return true;
		}
		$slug = Blueworx_Clubhouse_Club_Pages::slug_for_page_key( $area );
		return null !== $slug && Blueworx_Clubhouse_Club_Pages::post_id( $slug ) > 0;
	}
}
