<?php
// includes/membership/class-welcome-pack.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The club's welcome pack, shown to a member on their dashboard.
 *
 * What a club wants here is the practical stuff a new member needs once they
 * have paid: how to get in, where to park, who to ask. It is written once in
 * Club Pages and read on the dashboard.
 *
 * Why it renders plainly. The dashboard is SureCart's page, and this plugin
 * deliberately leaves commerce pages standing alone rather than wrapping them
 * in the club's header, footer and design tokens — see External_Chrome, which
 * excludes them outright and says why. None of the look's CSS is loaded there,
 * so a block dressed in clubhouse classes would arrive unstyled. This markup
 * therefore asks for almost nothing: a rule above it, some spacing, and the
 * page's own typography and colour. It reads as part of the dashboard because
 * it is not fighting it.
 *
 * Pure throughout — the values are handed in, so every rule here is testable
 * without WordPress or SureCart.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Welcome_Pack {

	/** The content address the pack is written to and read from. */
	public const STORE_PAGE = 'global';
	public const SECTION    = 'welcome';

	private static function e( string $v ): string {
		return htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * The pack as markup, or '' when the club has not written one.
	 *
	 * Empty means empty: a club that has left this alone gets nothing at all,
	 * not a heading over a blank space. The heading alone is not enough either —
	 * "Welcome" with no words under it is a worse dashboard than no section.
	 *
	 * @param array{heading:string,body:string,link_label:string,link_href:string} $pack
	 */
	public static function render( array $pack ): string {
		$heading = trim( (string) ( $pack['heading'] ?? '' ) );
		$body    = trim( (string) ( $pack['body'] ?? '' ) );
		if ( '' === $body ) {
			return '';
		}

		$out = '<section class="clubhouse-welcome">';
		if ( '' !== $heading ) {
			$out .= '<h2 class="clubhouse-welcome__h">' . self::e( $heading ) . '</h2>';
		}
		foreach ( self::paragraphs( $body ) as $paragraph ) {
			$out .= '<p class="clubhouse-welcome__p">' . self::e( $paragraph ) . '</p>';
		}
		$out .= self::link( $pack );
		return $out . '</section>';
	}

	/**
	 * A blank line starts a new paragraph and nothing else is interpreted. The
	 * same rule the legal pages use, so an owner who has written one page knows
	 * how the other behaves.
	 *
	 * @return array<int,string>
	 */
	public static function paragraphs( string $body ): array {
		$parts = preg_split( '/\R{2,}/u', trim( $body ) ) ?: array();
		$out   = array();
		foreach ( $parts as $part ) {
			// Single newlines inside a paragraph are wrapping in the textarea, not
			// meaning — collapse them rather than emitting ragged lines.
			$text = trim( (string) preg_replace( '/\s+/u', ' ', $part ) );
			if ( '' !== $text ) {
				$out[] = $text;
			}
		}
		return $out;
	}

	/**
	 * The optional link. Both halves or neither: a label with no address is
	 * dead text, and an address with no label is a link nobody can read.
	 *
	 * @param array<string,mixed> $pack
	 */
	private static function link( array $pack ): string {
		$label = trim( (string) ( $pack['link_label'] ?? '' ) );
		$href  = trim( (string) ( $pack['link_href'] ?? '' ) );
		if ( '' === $label || '' === $href ) {
			return '';
		}
		return '<p class="clubhouse-welcome__p"><a class="clubhouse-welcome__link" href="' . self::e( $href ) . '">'
			. self::e( $label ) . '</a></p>';
	}

	/**
	 * The stylesheet, inlined next to the block.
	 *
	 * Deliberately tiny and deliberately not in the look files: those are loaded
	 * on clubhouse pages, and this markup only ever appears on one that is not.
	 * It sets spacing and a rule, and inherits everything else — font, size and
	 * colour are SureCart's on SureCart's page.
	 */
	public static function register(): void {
		// the_content, not a template hook: the dashboard is SureCart's page and
		// its template is theirs to change. Filtering the content puts the pack
		// under what they render without this plugin knowing how they render it.
		//
		// Priority 20, after the default: SureCart expands the dashboard into the
		// content at 10, so at 10 the pack could land above it.
		add_filter( 'the_content', array( self::class, 'append_to_dashboard' ), 20 );
	}

	/**
	 * Append the pack to the customer dashboard, and to nothing else.
	 *
	 * Four things have to be true, and the cheap checks come first so the vast
	 * majority of requests leave after one comparison.
	 *
	 * @param string $content
	 */
	public static function append_to_dashboard( $content ): string {
		$content = (string) $content;
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$dashboard = Blueworx_Clubhouse_Shop_Pages::page_id( 'dashboard' );
		if ( 0 === $dashboard || get_the_ID() !== $dashboard ) {
			return $content;
		}
		$storage = new Blueworx_Clubhouse_Options_Storage();
		if ( ! ( new Blueworx_Clubhouse_Visibility( $storage ) )->is_section_visible( 'home', self::SECTION ) ) {
			return $content;
		}

		$store = new Blueworx_Clubhouse_Content_Store( $storage );
		$block = self::render(
			array(
				'heading'    => (string) $store->get( self::STORE_PAGE, self::SECTION, 'heading', '' ),
				'body'       => (string) $store->get( self::STORE_PAGE, self::SECTION, 'body', '' ),
				'link_label' => (string) $store->get( self::STORE_PAGE, self::SECTION, 'link_label', '' ),
				'link_href'  => (string) $store->get( self::STORE_PAGE, self::SECTION, 'link_href', '' ),
			)
		);
		if ( '' === $block ) {
			return $content;
		}
		// The stylesheet rides with the block rather than being enqueued: it is a
		// handful of rules on exactly one page, and enqueueing would put it on
		// every dashboard request including the ones with no pack to show.
		return $content . '<style>' . self::css() . '</style>' . $block;
	}

	public static function css(): string {
		return '.clubhouse-welcome{margin:2.5rem 0 0;padding-top:1.75rem;border-top:1px solid currentColor;'
			. 'border-top-color:rgba(128,128,128,.3)}'
			. '.clubhouse-welcome__h{margin:0 0 .75rem;font-size:1.25em}'
			. '.clubhouse-welcome__p{margin:0 0 .75rem;line-height:1.6}'
			. '.clubhouse-welcome__p:last-child{margin-bottom:0}';
	}
}
