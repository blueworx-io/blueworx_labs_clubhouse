<?php
// includes/import/class-import-parser.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns a decoded import file into an Import_Plan. Pure and total: it never
 * throws and never partially writes — a malformed value becomes a warning and
 * is dropped, so an owner always gets a reviewable plan plus an honest list of
 * what was ignored.
 *
 * Page_Fields and Collection_Meta are the allow-list — the same two
 * declarations the editing screens themselves are built from, so a file can
 * only ever write to somewhere an owner can see and change. Values are cleaned
 * by the page editor library's own Sanitise, which is literally the code a
 * save runs through: an AI-authored file is treated exactly like form input.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Import_Parser {

	/** The `clubhouse_import` value this parser understands. */
	public const FORMAT_VERSION = 1;

	/** Loop sections carry their repeated rows under this reserved key. */
	private const ITEMS_KEY = 'items';

	/**
	 * @return array{plan:?Blueworx_Clubhouse_Import_Plan,error:string}
	 */
	public static function parse( mixed $decoded ): array {
		if ( ! is_array( $decoded ) ) {
			return self::fail( 'This file is not a ClubHouse import file.' );
		}
		if ( ! array_key_exists( 'clubhouse_import', $decoded ) ) {
			return self::fail( 'This file is missing its "clubhouse_import" format marker.' );
		}
		$version = $decoded['clubhouse_import'];
		if ( ! is_int( $version ) || self::FORMAT_VERSION !== $version ) {
			$shown = is_scalar( $version ) ? (string) $version : 'unknown';
			return self::fail( sprintf(
				'This file uses import format version %s, which this version of ClubHouse cannot read.',
				$shown
			) );
		}

		$content     = is_array( $decoded['content'] ?? null ) ? $decoded['content'] : null;
		$collections = is_array( $decoded['collections'] ?? null ) ? $decoded['collections'] : null;
		if ( null === $content && null === $collections ) {
			return self::fail( 'This file contains no content or collections to import.' );
		}

		$plan = new Blueworx_Clubhouse_Import_Plan();
		if ( null !== $content ) {
			self::parse_content( $content, $plan );
		}
		if ( null !== $collections ) {
			self::parse_collections( $collections, $plan );
		}

		return array( 'plan' => $plan, 'error' => '' );
	}

	/** @return array{plan:null,error:string} */
	private static function fail( string $message ): array {
		return array( 'plan' => null, 'error' => $message );
	}

	/**
	 * Read an image reference. The chat cannot know attachment IDs, so an image
	 * arrives as a URL — either a bare string or an object with an optional alt.
	 * Only http(s) is accepted; anything else (a data: payload, a local path, a
	 * script scheme) is refused here rather than at fetch time.
	 *
	 * @return array{url:string,alt:string}|null
	 */
	public static function image_ref( mixed $raw ): ?array {
		$url = '';
		$alt = '';
		if ( is_string( $raw ) ) {
			$url = trim( $raw );
		} elseif ( is_array( $raw ) ) {
			$url = is_string( $raw['url'] ?? null ) ? trim( $raw['url'] ) : '';
			$alt = is_string( $raw['alt'] ?? null ) ? trim( $raw['alt'] ) : '';
		}
		if ( '' === $url || 1 !== preg_match( '#^https?://#i', $url ) ) {
			return null;
		}
		return array( 'url' => $url, 'alt' => $alt );
	}

	/**
	 * Read a toggle value on its own terms rather than by presence:
	 * `true`/`"true"`/`1`/`"1"` is on; `false`, `"false"`, `0`, `"0"`, `""`,
	 * `null`, an absent key, or anything else non-scalar is off. A form post
	 * means a switch by merely being there — an unticked checkbox never posts —
	 * but a JSON file's toggle key is always there and can carry `false`
	 * explicitly, so it must be read as such.
	 */
	private static function toggle_value( mixed $raw ): bool {
		return true === $raw || 1 === $raw || '1' === $raw || 'true' === $raw;
	}

	/**
	 * Every section a file may write to, keyed by its stored address.
	 *
	 * Without the products adapter the tier's price_id select has only its
	 * empty option, and the library's Sanitise then reduces every imported
	 * price_id to '' — silently clearing every tier's connection. The page
	 * editors are declared with this same source for exactly that reason; the
	 * importer must match them.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private static function sections(): array {
		return Blueworx_Clubhouse_Page_Fields::sections( Blueworx_Clubhouse_Products_Source::get() );
	}

	/**
	 * A link that is not one. The editor refuses a bad address with a field
	 * error rather than quietly storing it, so the importer says the same thing
	 * in its own voice — a warning, and the value dropped.
	 *
	 * @param array<string,mixed> $field
	 */
	private static function is_bad_link( array $field, mixed $value ): bool {
		if ( 'url' !== ( $field['format'] ?? '' ) ) {
			return false;
		}
		$value = (string) $value;
		return '' !== trim( $value ) && '' === esc_url_raw( $value );
	}

	/** @param array<string,mixed> $content */
	private static function parse_content( array $content, Blueworx_Clubhouse_Import_Plan $plan ): void {
		$sections = self::sections();

		foreach ( $content as $page => $page_sections ) {
			$page = (string) $page;
			if ( ! is_array( $page_sections ) ) {
				$plan->warn( sprintf( 'Ignored "%s": expected a group of sections.', $page ) );
				continue;
			}
			foreach ( $page_sections as $section_key => $supplied ) {
				$section_key = (string) $section_key;
				$address     = $page . '/' . $section_key;
				if ( ! isset( $sections[ $address ] ) ) {
					$plan->warn( sprintf( 'Ignored unknown section "%s".', $address ) );
					continue;
				}
				if ( ! is_array( $supplied ) ) {
					$plan->warn( sprintf( 'Ignored "%s": expected a group of fields.', $address ) );
					continue;
				}
				self::parse_section( $page, $section_key, $sections[ $address ], $supplied, $plan );
			}
		}
	}

	/**
	 * @param array<string,mixed> $entry    the section, exactly as Page_Fields declares it.
	 * @param array<string,mixed> $supplied what the file said about that section.
	 */
	private static function parse_section( string $page, string $section_key, array $entry, array $supplied, Blueworx_Clubhouse_Import_Plan $plan ): void {
		$by_key   = $entry['fields'];
		$repeater = $entry['items'];
		$cells    = array();
		foreach ( is_array( $repeater ) ? $repeater['fields'] : array() as $cell ) {
			$cells[ (string) $cell['id'] ] = $cell;
		}

		foreach ( $supplied as $field_key => $raw ) {
			$field_key = (string) $field_key;
			if ( self::ITEMS_KEY === $field_key ) {
				continue; // handled below.
			}
			if ( ! isset( $by_key[ $field_key ] ) ) {
				$plan->warn( sprintf( 'Ignored unknown field "%s/%s/%s".', $page, $section_key, $field_key ) );
				continue;
			}
			$field_def = $by_key[ $field_key ];
			$kind      = (string) ( $field_def['kind'] ?? 'text' );

			if ( 'media' === $kind ) {
				$ref = self::image_ref( $raw );
				if ( null === $ref ) {
					$plan->warn( sprintf( 'Ignored "%s/%s/%s": expected an image URL.', $page, $section_key, $field_key ) );
					continue;
				}
				$plan->add_image(
					$page,
					$section_key,
					$field_key,
					$ref['url'],
					$ref['alt'],
					self::image_label( $entry, (string) $field_def['label'] )
				);
				continue;
			}
			if ( 'toggle' === $kind ) {
				// A form says a switch is on by posting it at all, which is right
				// for a checkbox that never posts when unticked and wrong here: a
				// JSON key is always present and can carry `false`, so a file is
				// read for what it says rather than for what it mentions.
				$plan->add_field( $page, $section_key, $field_key, self::toggle_value( $raw ) );
				continue;
			}
			if ( ! is_scalar( $raw ) ) {
				// The library casts a value to a string, so a list or an object
				// under a text field would be stored as the literal "Array".
				$plan->warn( sprintf( 'Ignored "%s/%s/%s": expected a single value.', $page, $section_key, $field_key ) );
				continue;
			}
			if ( self::is_bad_link( $field_def, $raw ) ) {
				$plan->warn( sprintf( 'Ignored "%s/%s/%s": expected a web address, like https://example.org.', $page, $section_key, $field_key ) );
				continue;
			}
			$plan->add_field(
				$page,
				$section_key,
				$field_key,
				\Blueworx\PageEditor\v1\Sanitise::field( $field_def, $raw )
			);
		}

		if ( ! array_key_exists( self::ITEMS_KEY, $supplied ) ) {
			return;
		}
		if ( ! is_array( $repeater ) ) {
			$plan->warn( sprintf( 'Ignored "%s/%s/items": this section is not a repeatable list.', $page, $section_key ) );
			return;
		}
		$raw_items = $supplied[ self::ITEMS_KEY ];
		if ( ! is_array( $raw_items ) || ! array_is_list( $raw_items ) ) {
			$plan->warn( sprintf( 'Ignored "%s/%s/items": expected a list of items.', $page, $section_key ) );
			return;
		}
		if ( array() === $raw_items ) {
			// An empty list is almost certainly an oversight, and planning it would
			// clear the section invisibly: the preview skips zero-count sections, so
			// no row would show the deletion an apply would actually perform.
			$plan->warn( sprintf( 'Ignored "%s/%s/items": the list is empty.', $page, $section_key ) );
			return;
		}

		// Drop anything in the list that is not an entry before cleaning, so the
		// cleaned rows and the raw ones stay index for index — the passes below
		// read each row's raw value back by its position.
		$rows = array();
		foreach ( $raw_items as $index => $raw_item ) {
			if ( ! is_array( $raw_item ) ) {
				$plan->warn( sprintf( 'Ignored "%s/%s/items[%d]": expected a group of fields.', $page, $section_key, (int) $index ) );
				continue;
			}
			$rows[] = $raw_item;
		}
		if ( array() === $rows ) {
			return;
		}

		// Anything that is not a single value comes out first: the library casts
		// a cell to a string, so a list or an object under a text cell would be
		// stored as the literal "Array". A picture is the one cell whose value
		// is legitimately an object — it is cleared below whatever it holds, and
		// queued from the raw rows rather than from these.
		$clean = array();
		foreach ( $rows as $index => $row ) {
			$clean[ $index ] = array();
			foreach ( $cells as $cell_key => $cell ) {
				if ( ! array_key_exists( $cell_key, $row ) ) {
					continue;
				}
				if ( is_scalar( $row[ $cell_key ] ) ) {
					$clean[ $index ][ $cell_key ] = $row[ $cell_key ];
					continue;
				}
				if ( 'media' !== (string) ( $cell['kind'] ?? 'text' ) ) {
					$plan->warn( sprintf( 'Ignored "%s/%s/items[%d]/%s": expected a single value.', $page, $section_key, $index, $cell_key ) );
				}
			}
		}

		// The library cleans a repeater exactly as it would on a save, so every
		// row comes back with every declared cell and nothing else. Three things
		// then need a second look, because a file is not a form.
		$items = \Blueworx\PageEditor\v1\Sanitise::field( $repeater, $clean );

		foreach ( $cells as $cell_key => $cell ) {
			$kind = (string) ( $cell['kind'] ?? 'text' );
			foreach ( array_keys( $items ) as $index ) {
				$has_value = array_key_exists( $cell_key, $rows[ $index ] );
				$raw_value = $has_value ? $rows[ $index ][ $cell_key ] : null;

				if ( 'media' === $kind ) {
					// A picture arrives as a URL, never as the attachment ID the
					// library casts it to. The applier fills that in once it has
					// sideloaded the file, so the cell is always cleared here —
					// valid URL or not — and the picture queued separately below.
					$items[ $index ][ $cell_key ] = '';
					continue;
				}
				if ( 'toggle' === $kind ) {
					$items[ $index ][ $cell_key ] = self::toggle_value( $raw_value );
					continue;
				}
				if ( $has_value && is_scalar( $raw_value ) && self::is_bad_link( $cell, $raw_value ) ) {
					$plan->warn( sprintf( 'Ignored "%s/%s/items[%d]/%s": expected a web address, like https://example.org.', $page, $section_key, $index, $cell_key ) );
					$items[ $index ][ $cell_key ] = '';
				}
			}
		}
		$plan->add_items( $page, $section_key, $items );

		foreach ( $cells as $cell_key => $cell ) {
			if ( 'media' !== (string) ( $cell['kind'] ?? 'text' ) ) {
				continue;
			}
			foreach ( $rows as $index => $row ) {
				if ( ! array_key_exists( $cell_key, $row ) ) {
					continue;
				}
				$ref = self::image_ref( $row[ $cell_key ] );
				if ( null === $ref ) {
					$plan->warn( sprintf( 'Ignored "%s/%s/items[%d]/%s": expected an image URL.', $page, $section_key, (int) $index, $cell_key ) );
					continue;
				}
				$plan->add_image(
					$page,
					$section_key,
					$cell_key,
					$ref['url'],
					$ref['alt'],
					self::image_label( $entry, (string) $cell['label'] ),
					(int) $index
				);
			}
		}
	}

	/**
	 * Read the file's `collections` object. Collection_Meta is the allow-list:
	 * a type it does not declare, or a meta key that type does not declare, is
	 * dropped with a warning. Media-typed meta is kept as an image reference for
	 * the applier to sideload, never as a raw value — an attachment ID is the
	 * only thing that may reach post meta.
	 *
	 * @param array<string,mixed> $collections
	 */
	private static function parse_collections( array $collections, Blueworx_Clubhouse_Import_Plan $plan ): void {
		$known = Blueworx_Clubhouse_Collection_Meta::types();

		foreach ( $collections as $type => $raw_items ) {
			$type = (string) $type;
			if ( ! in_array( $type, $known, true ) ) {
				$plan->warn( sprintf( 'Ignored unknown collection "%s".', $type ) );
				continue;
			}
			if ( ! is_array( $raw_items ) || ! array_is_list( $raw_items ) ) {
				$plan->warn( sprintf( 'Ignored "%s": expected a list of items.', $type ) );
				continue;
			}
			if ( array() === $raw_items ) {
				// An empty list is almost certainly an oversight, and applying it
				// would delete this type's demo posts and leave the section blank.
				$plan->warn( sprintf( 'Ignored "%s": the list is empty.', $type ) );
				continue;
			}

			$field_defs = array();
			foreach ( Blueworx_Clubhouse_Collection_Meta::fields( $type ) as $field_def ) {
				$field_defs[ (string) $field_def['key'] ] = $field_def;
			}

			$items = array();
			foreach ( $raw_items as $index => $raw_item ) {
				$index = (int) $index;
				if ( ! is_array( $raw_item ) ) {
					$plan->warn( sprintf( 'Ignored %s item %d: expected a group of fields.', $type, $index ) );
					continue;
				}
				$title = is_scalar( $raw_item['title'] ?? null ) ? trim( (string) $raw_item['title'] ) : '';
				if ( '' === $title ) {
					$plan->warn( sprintf( 'Ignored %s item %d: every item needs a title.', $type, $index ) );
					continue;
				}

				$meta   = array();
				$images = array();
				foreach ( $raw_item as $key => $raw_value ) {
					$key = (string) $key;
					if ( 'title' === $key ) {
						continue;
					}
					if ( ! isset( $field_defs[ $key ] ) ) {
						$plan->warn( sprintf( 'Ignored unknown field "%s/%s".', $type, $key ) );
						continue;
					}
					if ( 'media' === $field_defs[ $key ]['type'] ) {
						$ref = self::image_ref( $raw_value );
						if ( null === $ref ) {
							$plan->warn( sprintf( 'Ignored the image for %s item %d: expected an image URL.', $type, $index ) );
							continue;
						}
						$images[ $key ] = $ref;
						continue;
					}
					$value        = is_scalar( $raw_value ) ? (string) $raw_value : '';
					$sanitised    = Blueworx_Clubhouse_Collection_Meta::sanitise( $type, $key, $value );
					$meta[ $key ] = $sanitised;
					// Collection_Meta::sanitise() is the single source of truth for
					// what a select field accepts; rather than re-implementing the
					// option check here, a select value is known to have been
					// out-of-range precisely when sanitising it changed it.
					if ( 'select' === $field_defs[ $key ]['type'] && $sanitised !== $value ) {
						$plan->warn( sprintf( 'Ignored "%s/%s": "%s" is not a valid option; using the default.', $type, $key, $value ) );
					}
				}

				$items[] = array( 'title' => $title, 'meta' => $meta, 'images' => $images );
			}

			if ( array() !== $items ) {
				$plan->add_collection( $type, $items );
			}
		}
	}

	/** @param array<string,mixed> $entry */
	private static function image_label( array $entry, string $field_label ): string {
		return $entry['area_label'] . ' · ' . $entry['section_label'] . ' — ' . $field_label;
	}
}
