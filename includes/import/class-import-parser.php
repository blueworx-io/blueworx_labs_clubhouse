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
 * The Content_Catalogue and Collection_Meta are the allow-list. Nothing reaches
 * the plan that they do not declare, and every value is sanitised by the same
 * code the admin editor uses — an AI-authored file is treated exactly like
 * form input.
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
	 * `null`, an absent key, or anything else non-scalar is off. Unlike
	 * Content_Sanitiser::field()'s form-post semantics (where merely being
	 * present means checked), a JSON file's toggle key can carry `false`
	 * explicitly and must be read as such.
	 */
	private static function toggle_value( mixed $raw ): bool {
		return true === $raw || 1 === $raw || '1' === $raw || 'true' === $raw;
	}

	/**
	 * Catalogue sections keyed by their stored address, carrying the definition
	 * and the labels needed to name an image slot for a human.
	 *
	 * @return array<string,array{def:array<string,mixed>,tab_label:string,section_label:string}>
	 */
	private static function sections(): array {
		$labels = Blueworx_Clubhouse_Content_Catalogue::index();
		$out    = array();
		// Without the products adapter, the tier's price_id select has only its
		// empty option, and Content_Sanitiser::field() then sanitises every
		// imported price_id to '' — silently clearing every tier's connection.
		// The block field shapes pass this same source
		// for exactly this reason; the importer must match it.
		foreach ( Blueworx_Clubhouse_Content_Catalogue::pages( Blueworx_Clubhouse_Products_Source::get() ) as $page ) {
			foreach ( $page['sections'] as $section ) {
				$address         = (string) $section['store_page'] . '/' . (string) $section['key'];
				$out[ $address ] = array(
					'def'           => $section,
					'tab_label'     => $labels[ $address ]['tab_label'] ?? '',
					'section_label' => $labels[ $address ]['section_label'] ?? '',
				);
			}
		}
		return $out;
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
	 * @param array{def:array<string,mixed>,tab_label:string,section_label:string} $entry
	 * @param array<string,mixed>                                                  $supplied
	 */
	private static function parse_section( string $page, string $section_key, array $entry, array $supplied, Blueworx_Clubhouse_Import_Plan $plan ): void {
		$def         = $entry['def'];
		$field_defs  = is_array( $def['fields'] ?? null ) ? $def['fields'] : array();
		$loop_fields = is_array( $def['loop']['fields'] ?? null ) ? $def['loop']['fields'] : array();
		$by_key      = array();
		foreach ( $field_defs as $field_def ) {
			$by_key[ (string) $field_def['key'] ] = $field_def;
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
			if ( 'image' === $field_def['type'] ) {
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
			if ( 'toggle' === $field_def['type'] ) {
				// Content_Sanitiser::field()'s presence-means-true rule is correct
				// for a form POST (an unticked checkbox never posts) and wrong here,
				// where the key is present and can carry `false` — a JSON file must
				// be read for what it says, not just whether the key showed up.
				$plan->add_field( $page, $section_key, $field_key, self::toggle_value( $raw ) );
				continue;
			}
			$plan->add_field(
				$page,
				$section_key,
				$field_key,
				Blueworx_Clubhouse_Content_Sanitiser::field( $field_def, $raw, true )
			);
		}

		if ( ! array_key_exists( self::ITEMS_KEY, $supplied ) ) {
			return;
		}
		if ( array() === $loop_fields ) {
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

		// Sanitise first, then force every image-typed field back to the ''
		// sentinel: Content_Sanitiser::field() runs absint() on any scalar for an
		// 'image' field, which is correct for a real form post (the value is
		// genuinely an attachment ID) but not here, where the raw value is a URL
		// or reference object. A loop item's image is never legitimately
		// non-empty at parse time — the applier fills in the attachment ID after
		// sideloading — so the field is always cleared, whether or not its URL
		// was valid, and the image is queued separately below.
		$items = Blueworx_Clubhouse_Content_Sanitiser::items( $loop_fields, $raw_items );
		foreach ( $loop_fields as $field_def ) {
			if ( 'image' !== $field_def['type'] ) {
				continue;
			}
			$field_key = (string) $field_def['key'];
			foreach ( array_keys( $items ) as $index ) {
				$items[ $index ][ $field_key ] = '';
			}
		}
		// Same presence-vs-value correction as the section-level toggle above,
		// applied per item: Content_Sanitiser::items() has already set every
		// toggle to "was the key present", which is wrong for a JSON file. $items
		// and $raw_items share the same 0-based order (both came from the same
		// list), so the raw value for item N is read back by the same index.
		foreach ( $loop_fields as $field_def ) {
			if ( 'toggle' !== $field_def['type'] ) {
				continue;
			}
			$field_key = (string) $field_def['key'];
			foreach ( array_keys( $items ) as $index ) {
				$raw_item  = $raw_items[ $index ] ?? null;
				$raw_value = ( is_array( $raw_item ) && array_key_exists( $field_key, $raw_item ) ) ? $raw_item[ $field_key ] : null;
				$items[ $index ][ $field_key ] = self::toggle_value( $raw_value );
			}
		}
		$plan->add_items( $page, $section_key, $items );

		foreach ( $loop_fields as $field_def ) {
			if ( 'image' !== $field_def['type'] ) {
				continue;
			}
			$field_key = (string) $field_def['key'];
			foreach ( $raw_items as $index => $raw_item ) {
				if ( ! is_array( $raw_item ) || ! array_key_exists( $field_key, $raw_item ) ) {
					continue;
				}
				$ref = self::image_ref( $raw_item[ $field_key ] );
				if ( null === $ref ) {
					$plan->warn( sprintf( 'Ignored "%s/%s/items[%d]/%s": expected an image URL.', $page, $section_key, (int) $index, $field_key ) );
					continue;
				}
				$plan->add_image(
					$page,
					$section_key,
					$field_key,
					$ref['url'],
					$ref['alt'],
					self::image_label( $entry, (string) $field_def['label'] ),
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

	/** @param array{def:array<string,mixed>,tab_label:string,section_label:string} $entry */
	private static function image_label( array $entry, string $field_label ): string {
		return $entry['tab_label'] . ' · ' . $entry['section_label'] . ' — ' . $field_label;
	}
}
