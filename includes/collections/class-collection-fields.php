<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The six collections, said in the page editor library's vocabulary.
 *
 * Written from Collection_Meta rather than beside it: that class stays the one
 * definition of what a collection field is and how it is cleaned, and this one
 * only translates. A field added there appears on its screen with no change
 * here at all.
 *
 * Pure — no hooks, no WordPress, no storage — so CollectionFieldsTest can hold
 * all six screens against Schema::validate() and a mistake is a red test
 * rather than an owner opening a screen that says it is not ready.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Collection_Fields {

	/** Every collection editor's slug is this plus its post type. */
	public const SLUG_PREFIX = 'clubhouse-edit-';

	public static function slug_for( string $type ): string {
		return self::SLUG_PREFIX . $type;
	}

	/**
	 * A collection's editor screen, ready for Editor::register().
	 *
	 * The record's own name is a `title` field at the top rather than a line in
	 * the settings tab — it is the first thing anybody types, and the library
	 * stores it as the post title.
	 *
	 * @return array<string,mixed>
	 */
	public static function screen( string $type ): array {
		$singular = self::singular( $type );

		return array(
			'slug'       => self::slug_for( $type ),
			'title'      => $singular,
			'menu_title' => $singular,
			'eyebrow'    => Blueworx_Clubhouse_Collection_Meta::label( $type ),
			'lede'       => sprintf( 'The details of one %s. Nothing changes on the site until you save.', strtolower( $singular ) ),
			'capability' => Blueworx_Clubhouse_Owner_Capabilities::CONTENT_CAP,
			'store'      => 'post',
			'post_type'  => $type,
			// Registered under the Clubhouse menu so that menu stays
			// highlighted while a record is open. The item itself is hidden
			// straight afterwards — a collection is reached from its own list,
			// the way a club page is reached from the Pages list.
			'parent'     => Blueworx_Clubhouse_Setup_Editor::PAGE_SLUG,
			'tabs'       => array(
				array(
					'id'     => 'details',
					'label'  => 'Details',
					'panels' => array(
						array(
							'id'     => 'details',
							'title'  => $singular,
							'note'   => 'What the site shows about this one.',
							'fields' => self::fields( $type ),
						),
					),
				),
			),
		);
	}

	/** @return array<int,array<string,mixed>> */
	public static function fields( string $type ): array {
		$fields = array(
			array(
				'id'       => 'post_title',
				'kind'     => 'title',
				'label'    => 'Name',
				'required' => true,
			),
		);
		foreach ( Blueworx_Clubhouse_Collection_Meta::fields( $type ) as $field ) {
			$fields[] = self::field( $field );
		}
		return $fields;
	}

	/**
	 * One Collection_Meta field as a library control.
	 *
	 * @param array{key:string,label:string,type:string,options?:array<int,string>,default?:string} $field
	 * @return array<string,mixed>
	 */
	private static function field( array $field ): array {
		$out = array(
			'id'    => $field['key'],
			'kind'  => 'text',
			'label' => $field['label'],
		);

		switch ( $field['type'] ) {
			case 'textarea':
				$out['kind'] = 'textarea';
				break;
			case 'date':
				$out['kind'] = 'date';
				break;
			case 'media':
				$out['kind'] = 'media';
				break;
			case 'email':
				$out['format'] = 'email';
				break;
			case 'url':
			case 'href':
				// href is the permissive one — it keeps a site-relative link,
				// which is why it is not given the url format. Collection_Meta
				// still refuses a script scheme on the way in.
				if ( 'url' === $field['type'] ) {
					$out['format'] = 'url';
				}
				break;
			case 'time':
				// The design system has no time control. Kept as text with the
				// shape spelled out; Collection_Meta::sanitise() still refuses
				// anything that is not H:i, so a mistyped time is dropped
				// rather than stored.
				$out['help'] = 'As 24-hour time, like 14:30.';
				break;
			case 'select':
				$out['kind']    = 'select';
				$out['options'] = self::options( $field );
				break;
		}

		return $out;
	}

	/**
	 * A select's choices. A blank option is dropped: the library's select
	 * draws its own leading blank, and offering two would let an owner pick
	 * the wrong kind of nothing.
	 *
	 * @param array{options?:array<int,string>,default?:string} $field
	 * @return array<int,array{value:string,label:string}>
	 */
	private static function options( array $field ): array {
		$out = array();
		foreach ( $field['options'] ?? array() as $option ) {
			if ( '' === $option ) {
				continue;
			}
			$out[] = array( 'value' => $option, 'label' => self::option_label( $option ) );
		}
		return $out;
	}

	/** W, D and L mean nothing on their own; upcoming and past already read as words. */
	private static function option_label( string $option ): string {
		$known = array(
			'W' => 'Won',
			'D' => 'Drew',
			'L' => 'Lost',
		);
		return $known[ $option ] ?? ucfirst( $option );
	}

	/** The singular name of a collection — "Team" for Teams. */
	private static function singular( string $type ): string {
		$plural = Blueworx_Clubhouse_Collection_Meta::label( $type );
		$known  = array(
			'People'  => 'Person',
			'Sports'  => 'Sport',
			'Teams'   => 'Team',
			'Events'  => 'Event',
			'Fixtures' => 'Fixture',
			'Sponsors' => 'Sponsor',
		);
		return $known[ $plural ] ?? $plural;
	}
}
