<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A club page's words, read from the page itself.
 *
 * The page editor library owns the writing: it saves each field as post meta
 * on the club page, keyed <post_type>_<field_id>. This class is the other
 * half — the front end's way in, and the only place in this plugin that
 * mirrors that key. It keeps the four methods Content_Store had, so the
 * renderer's own read helpers change type and nothing else.
 *
 * It casts, because raw meta cannot answer for itself. WordPress stores a
 * boolean false as an empty string, and Page_Renderer::cget() reads an empty
 * string as "never set" and substitutes its own default — so a toggle an
 * owner switched off would come back on, and a media field left blank would
 * come back as its shipped attachment id. That is true today only for toggle
 * and media: cget() itself collapses '' to its default regardless of what
 * this class returns, so a cleared text field springs back to its hardcoded
 * wording either way — cget()'s question to answer, not this class's. Kinds
 * come from Page_Fields, which is also what the library validated the value
 * against on the way in.
 *
 * Global content — header, footer, welcome pack, cookie notice — has no page
 * behind it, so it lives in one option and is reached under the page key
 * 'global'. Same addresses, same methods, different shelf.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Page_Content {

	public const GLOBAL_AREA   = 'global';
	public const GLOBAL_OPTION = 'global_content';

	/**
	 * Every kind this class is deliberately able to answer for: the four
	 * cast() gives special handling (see below), plus the three it is safe to
	 * hand back raw because storage and PHP already agree on their shape — a
	 * string round-trips through post meta and an option exactly as written.
	 * Page_Fields only declares these seven today; the library itself accepts
	 * six more (range, file, record, checkboxes, scrolllist, tokens — see
	 * Store::castByKind() in the vendored library). PageFieldsTest checks
	 * every kind Page_Fields actually declares against this list, so the day
	 * one of those six is declared here, the test fails and says to add a
	 * case to cast() rather than the editor and the front end silently
	 * reading the same stored value differently.
	 */
	public const KNOWN_KINDS = array( 'toggle', 'media', 'number', 'repeater', 'text', 'textarea', 'select' );

	/** The post type club pages are, and so the prefix the library's meta keys carry. */
	private const POST_TYPE = 'page';

	private Blueworx_Clubhouse_Storage $globals;

	/**
	 * Memoised for the life of this instance, keyed by page. Page_Content is
	 * built once per render (the renderer reads a page's every field through
	 * the same instance), so this alone is enough to stop a hundred-odd field
	 * reads each re-resolving the same page's post id through its own option
	 * read — no static cache, and so nothing to forget between tests, since a
	 * fresh instance already starts with an empty one.
	 *
	 * @var array<string,int>
	 */
	private array $post_id_cache = array();

	public function __construct( ?Blueworx_Clubhouse_Storage $globals = null ) {
		$this->globals = $globals ?? new Blueworx_Clubhouse_Options_Storage();
	}

	/** The meta key the library writes a field to. Mirrors PostStore::key(). */
	private function meta_key( string $section, string $field ): string {
		return self::POST_TYPE . '_' . Blueworx_Clubhouse_Page_Fields::field_id( $section, $field );
	}

	/** The post behind a club page, or 0 when there is none yet. */
	private function post_id( string $page ): int {
		if ( ! array_key_exists( $page, $this->post_id_cache ) ) {
			$slug = Blueworx_Clubhouse_Club_Pages::slug_for_page_key( $page );
			$this->post_id_cache[ $page ] = null === $slug ? 0 : Blueworx_Clubhouse_Club_Pages::post_id( $slug );
		}
		return $this->post_id_cache[ $page ];
	}

	/** @return array<string,mixed> */
	private function global_values(): array {
		$saved = $this->globals->get( self::GLOBAL_OPTION, array() );
		return is_array( $saved ) ? $saved : array();
	}

	public function get( string $page, string $section, string $field, mixed $default = null ): mixed {
		$kind = Blueworx_Clubhouse_Page_Fields::kind_of( $page, $section, $field );

		if ( self::GLOBAL_AREA === $page ) {
			$values = $this->global_values();
			$key    = Blueworx_Clubhouse_Page_Fields::field_id( $section, $field );
			return array_key_exists( $key, $values ) ? $this->cast( $kind, $values[ $key ] ) : $default;
		}

		$id = $this->post_id( $page );
		if ( 0 === $id || ! function_exists( 'metadata_exists' ) ) {
			return $default;
		}
		$key = $this->meta_key( $section, $field );
		// metadata_exists() is the only way to tell "never written" from
		// "written as empty" — get_post_meta() answers '' to both. That
		// distinction is load-bearing today for a toggle (this would otherwise
		// cast a merely-unwritten field to false, same as one truly switched
		// off) and for media (would cast to 0, a real attachment id elsewhere).
		// A text field passes straight through either way, since cget()'s own
		// fallback treats '' as unset regardless of what this method returns.
		return metadata_exists( 'post', $id, $key )
			? $this->cast( $kind, get_post_meta( $id, $key, true ) )
			: $default;
	}

	public function set( string $page, string $section, string $field, mixed $value ): void {
		if ( self::GLOBAL_AREA === $page ) {
			$values = $this->global_values();
			$values[ Blueworx_Clubhouse_Page_Fields::field_id( $section, $field ) ] = $value;
			$this->globals->set( self::GLOBAL_OPTION, $values );
			return;
		}
		$id = $this->post_id( $page );
		if ( 0 === $id || ! function_exists( 'update_post_meta' ) ) {
			return;
		}
		update_post_meta( $id, $this->meta_key( $section, $field ), $value );
	}

	/** @return array<int,array<string,mixed>> */
	public function get_items( string $page, string $section ): array {
		$value = $this->get( $page, $section, Blueworx_Clubhouse_Page_Fields::REPEATER_FIELD, array() );
		return is_array( $value ) ? array_values( $value ) : array();
	}

	/** @param array<int,array<string,mixed>> $items */
	public function set_items( string $page, string $section, array $items ): void {
		$this->set( $page, $section, Blueworx_Clubhouse_Page_Fields::REPEATER_FIELD, array_values( $items ) );
	}

	/**
	 * Whether a panel's Shown switch is on. Defaults to on, the way the
	 * library's own auto-declared switch does — a panel nobody has touched has
	 * not been hidden.
	 *
	 * The library names that switch <panel_id>__shown, and this class joins a
	 * section and a field with one underscore, so the field key here is
	 * "_shown" — the second underscore is the join.
	 */
	public function is_section_shown( string $page, string $section ): bool {
		return (bool) $this->get( $page, $section, '_shown', true );
	}

	/** Turn what storage handed back into what the field's kind means. */
	private function cast( string $kind, mixed $value ): mixed {
		switch ( $kind ) {
			case 'toggle':
				return (bool) $value;
			case 'media':
			case 'number':
				return is_numeric( $value ) ? (int) $value : 0;
			case 'repeater':
				return is_array( $value ) ? array_values( $value ) : array();
			default:
				return $value;
		}
	}
}
