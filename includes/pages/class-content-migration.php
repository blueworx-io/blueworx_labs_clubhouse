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
 * Reads the old option directly against Storage — never through
 * Content_Store, which is itself deleted in task 10, so this class has to
 * outlive it — with three small helpers below (old_section(), old_get(),
 * old_items()) that mirror its logic exactly.
 *
 * Walks Page_Fields::all_areas(), the undropped set, not areas(): a club can
 * have real content sitting under a booking or login address with no
 * LatePoint or shop installed, and that content must be named in the report
 * rather than silently never mentioned because areas() alone would never
 * speak of it. Anything all_areas() itself does not declare is content
 * nothing would ever have rendered, in the old scheme or the new one, and is
 * left alone.
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
		// One instance for the whole run — it memoises each page's post id
		// for its own lifetime, and re-resolving that for every field read
		// through Options_Storage/get_option() is exactly the cost its own
		// docblock says a fresh instance per field would pay.
		$new        = new Blueworx_Clubhouse_Page_Content( $storage );
		$visibility = new Blueworx_Clubhouse_Visibility( $storage );

		$moved   = 0;
		$skipped = array();
		$pages   = array();

		$declared  = Blueworx_Clubhouse_Page_Fields::all_areas();
		$available = Blueworx_Clubhouse_Page_Fields::areas();

		foreach ( $declared as $area => $spec ) {
			$before_area = $moved;

			if ( ! isset( $available[ $area ] ) ) {
				// LatePoint or the shop is not installed here, so this whole area
				// has nowhere to be placed today — but real content may still sit
				// under its old addresses, and going quiet about it is exactly the
				// silent loss this class exists to prevent.
				self::report_unavailable( $storage, $area, $spec['tabs'], $skipped );
				$pages[ $area ] = $moved - $before_area; // always 0 — nothing here could be placed.
				continue;
			}

			$has_page      = self::has_page( $area );
			$available_ids = self::panel_ids( $available[ $area ] );

			foreach ( $spec['tabs'] as $tab ) {
				foreach ( $tab['panels'] as $panel ) {
					$section = (string) $panel['id'];

					if ( ! in_array( $section, $available_ids, true ) ) {
						// The area is available but this one panel's own integration
						// is not — e.g. the Calendar's booking slot with no LatePoint.
						self::report_unavailable_panel( $storage, $area, $panel, $skipped );
						continue;
					}

					foreach ( $panel['fields'] as $field ) {
						$key = Blueworx_Clubhouse_Page_Fields::field_key( $section, (string) $field['id'] );
						if ( '' === $key ) {
							continue; // The panel's own auto-declared switch — nothing was ever stored under this id.
						}
						self::migrate_field( $storage, $new, $area, $section, $key, (string) $field['kind'], $has_page, $moved, $skipped );
					}

					// The Shown switch isn't read from the old content option at
					// all — it lives in Visibility, under the same storage — so it
					// is written unconditionally rather than gated on "was it ever
					// saved". A page with no post behind it still gets the call;
					// Page_Content::set() no-ops safely, and there is nothing to
					// report, because there was never a place to lose this to.
					if ( ! empty( $panel['hideable'] ) ) {
						// The Global content editor's panels (header, footer, welcome
						// pack, cookie notice) write their Shown switch under the
						// 'global' area, matching where this writes it — but Setup
						// stored the club's actual choice under 'home', the same
						// 'global' → 'home' remap Page_Fields::is_hideable() makes
						// when it decides a global panel is hideable at all. Reading
						// straight from 'global' here would only ever find the
						// library's own unset default, never what an owner switched.
						$visibility_page = Blueworx_Clubhouse_Page_Content::GLOBAL_AREA === $area ? 'home' : $area;
						$new->set( $area, $section, '_shown', $visibility->is_section_visible( $visibility_page, $section ) );
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

	// -------------------------------------------------------------------
	// The old store, read directly. Content_Store's own logic, inlined —
	// Content_Store is deleted in task 10 and this class has to outlive it.
	// -------------------------------------------------------------------

	/** @return array<string,mixed> */
	private static function old_section( Blueworx_Clubhouse_Storage $storage, string $area, string $section ): array {
		$all = $storage->get( 'content_' . $area, array() );
		if ( is_array( $all ) && isset( $all[ $section ] ) && is_array( $all[ $section ] ) ) {
			return $all[ $section ];
		}
		return array();
	}

	private static function old_get( Blueworx_Clubhouse_Storage $storage, string $area, string $section, string $field, mixed $default = null ): mixed {
		$fields = self::old_section( $storage, $area, $section );
		return array_key_exists( $field, $fields ) ? $fields[ $field ] : $default;
	}

	/** @return array<int,array<string,mixed>> */
	private static function old_items( Blueworx_Clubhouse_Storage $storage, string $area, string $section ): array {
		$val = self::old_get( $storage, $area, $section, Blueworx_Clubhouse_Page_Fields::REPEATER_FIELD, array() );
		return is_array( $val ) ? array_values( $val ) : array();
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
		Blueworx_Clubhouse_Storage $storage,
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
			$items = self::old_items( $storage, $area, $section );
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
		$value = self::old_get( $storage, $area, $section, $key, null );
		if ( null === $value ) {
			return;
		}

		if ( 'media' === $kind && '' === $value ) {
			// A media field an owner deliberately cleared reads back as '', not
			// null — Content_Store cannot tell "cleared" from "never saved" any
			// other way. Either way there is no attachment to place, and
			// reporting "not a real attachment" would send an operator chasing a
			// picture that was never there.
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
	 * Every panel id an (already availability-filtered) area declares, flat —
	 * so the field loop can tell "this panel is available" from "this whole
	 * area is, but this one panel's own integration is not" with a single
	 * lookup.
	 *
	 * @param array{tabs:array<int,array{panels:array<int,array<string,mixed>>}>} $area_spec
	 * @return array<int,string>
	 */
	private static function panel_ids( array $area_spec ): array {
		$ids = array();
		foreach ( $area_spec['tabs'] as $tab ) {
			foreach ( $tab['panels'] as $panel ) {
				$ids[] = (string) $panel['id'];
			}
		}
		return $ids;
	}

	/**
	 * Every field with real old content, across a whole dropped area's tabs —
	 * used when an area's own integration (LatePoint, the shop) is absent
	 * entirely, so none of its panels are available to check individually.
	 *
	 * @param array<int,array{panels:array<int,array<string,mixed>>}> $tabs
	 * @param array<int,string>                                       $skipped
	 */
	private static function report_unavailable( Blueworx_Clubhouse_Storage $storage, string $area, array $tabs, array &$skipped ): void {
		foreach ( $tabs as $tab ) {
			foreach ( $tab['panels'] as $panel ) {
				self::report_unavailable_panel( $storage, $area, $panel, $skipped );
			}
		}
	}

	/**
	 * Every field with real old content in one panel whose own integration is
	 * absent — reported, never placed, because there is nowhere available to
	 * put it today. A field never saved in the old store has nothing to lose,
	 * so it is not reported, the same rule every other kind follows.
	 *
	 * The panel's own Shown switch is not reported here: it lives in
	 * Visibility, not Content_Store, so an unavailable panel loses nothing by
	 * this class's own definition of loss.
	 *
	 * @param array<string,mixed> $panel
	 * @param array<int,string>   $skipped
	 */
	private static function report_unavailable_panel( Blueworx_Clubhouse_Storage $storage, string $area, array $panel, array &$skipped ): void {
		$section = (string) $panel['id'];
		foreach ( $panel['fields'] as $field ) {
			$key = Blueworx_Clubhouse_Page_Fields::field_key( $section, (string) $field['id'] );
			if ( '' === $key ) {
				continue;
			}
			$kind = (string) $field['kind'];
			if ( 'repeater' === $kind ) {
				$has_content = array() !== self::old_items( $storage, $area, $section );
			} else {
				$value = self::old_get( $storage, $area, $section, $key, null );
				// Same rule migrate_field() applies: a media field read back as ''
				// was deliberately cleared, not left with something to lose.
				$has_content = null !== $value && ! ( 'media' === $kind && '' === $value );
			}
			if ( $has_content ) {
				$skipped[] = $area . '/' . $section . '/' . $key;
			}
		}
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
