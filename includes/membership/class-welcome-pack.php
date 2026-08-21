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

		$out = '<section class="clubhouse-welcome clubhouse-welcome--banner" aria-label="Welcome">';
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
		// A button rather than an underlined line of text: this is the one thing
		// the banner asks a new member to do, and it was reading as a footnote.
		return '<p class="clubhouse-welcome__actions"><a class="clubhouse-welcome__link" href="' . self::e( $href ) . '">'
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
		// its template is theirs to change. Filtering the content places the pack
		// without this plugin knowing how they render anything.
		//
		// Priority 20, after the default: SureCart expands the dashboard into the
		// content at 10, so filtering at 10 could see an empty string and put the
		// banner nowhere. Running after them and prepending is what puts it above
		// their markup reliably.
		add_filter( 'the_content', array( self::class, 'add_to_dashboard' ), 20 );
	}

	/**
	 * The finished page: the banner above the dashboard, never inside it.
	 *
	 * It greets a member, so it goes where a greeting goes. Appended, it sat
	 * under the tabs and an empty appointments list — the last thing a new member
	 * would see, if they scrolled at all.
	 *
	 * The stylesheet rides with the block rather than being enqueued: it is a
	 * handful of rules on exactly one page, and enqueueing would put it on every
	 * dashboard request including the ones with no pack to show.
	 */
	public static function compose( string $content, string $block, string $accent = '', string $accent_ink = '' ): string {
		if ( '' === $block ) {
			return $content;
		}
		return '<style>' . self::css( $accent, $accent_ink ) . '</style>' . $block . $content;
	}

	/**
	 * Append the pack to the customer dashboard, and to nothing else.
	 *
	 * Three things have to be true, and the cheap checks come first so the vast
	 * majority of requests leave after one comparison.
	 *
	 * @param string $content
	 */
	public static function add_to_dashboard( $content ): string {
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
		// The club's own accent, so the banner reads as the club's even on a page
		// wearing none of its design. Derived through the same colour engine the
		// site uses, against a white ground and near-black text — the dashboard's
		// own colours are SureCart's and cannot be read from here.
		$branding = new Blueworx_Clubhouse_Branding( $storage );
		$derived  = Blueworx_Clubhouse_Color_Engine::derive( $branding->get_accent(), '#ffffff', '#111111' );

		return self::compose(
			$content,
			$block,
			(string) ( $derived['--color-accent'] ?? '' ),
			(string) ( $derived['--color-accent-ink'] ?? '' )
		);
	}

	/**
	 * A hex colour, or '' for anything that is not one.
	 *
	 * These values are written straight into a <style> block, so a stored value
	 * that is not a colour must never reach it — nothing else stands between the
	 * branding option and the page.
	 */
	private static function hex( string $value, string $fallback ): string {
		$value = trim( $value );
		return 1 === preg_match( '/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i', $value ) ? $value : $fallback;
	}

	/**
	 * The banner's rules, with the club's accent written in literally.
	 *
	 * No var() and no font-family: the dashboard carries none of the club's
	 * design tokens, so a token here would resolve to nothing, and the type
	 * should stay the shop page's own. Everything else is spacing, a tinted
	 * ground and a button.
	 */
	public static function css( string $accent = '', string $accent_ink = '' ): string {
		$accent = self::hex( $accent, '#2b2b2b' );
		$ink    = self::hex( $accent_ink, '#ffffff' );

		return '.clubhouse-welcome{margin:0 0 1.75rem;padding:1.5rem 1.75rem;border-radius:12px;'
			. 'border:1px solid rgba(128,128,128,.25);border-left:6px solid ' . $accent . ';'
			. 'background:rgba(128,128,128,.06)}'
			. '.clubhouse-welcome__h{margin:0 0 .5rem;font-size:1.25em;line-height:1.25}'
			. '.clubhouse-welcome__p{margin:0 0 .6rem;line-height:1.6}'
			. '.clubhouse-welcome__p:last-child{margin-bottom:0}'
			. '.clubhouse-welcome__actions{margin:1rem 0 0}'
			. '.clubhouse-welcome__link{display:inline-block;padding:.6rem 1.15rem;border-radius:999px;'
			. 'background:' . $accent . ';color:' . $ink . ';text-decoration:none;font-weight:600}'
			. '.clubhouse-welcome__link:hover{opacity:.9;color:' . $ink . '}'
			. '@media(max-width:600px){.clubhouse-welcome{padding:1.25rem}'
			. '.clubhouse-welcome__link{display:block;text-align:center}}';
	}
}
