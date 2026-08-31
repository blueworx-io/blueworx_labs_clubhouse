<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What an owner sees on the WordPress dashboard.
 *
 * This used to be the whole Setup form, embedded as a dashboard widget. Setup
 * is a page editor library screen now — it mounts itself on its own admin page
 * and cannot be rendered inside somebody else's — so the dashboard points at
 * the three places an owner actually starts from instead.
 *
 * A screen that reads and points, never one that edits: no tabs, no save bar.
 * Carrying both is what the design system's adherence check refuses.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Owner_Welcome {

	private static function esc( string $v ): string {
		return htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' );
	}

	private static function url( string $path ): string {
		return function_exists( 'admin_url' ) ? (string) admin_url( $path ) : $path;
	}

	/**
	 * @return array<int,array{title:string,note:string,url:string}>
	 */
	private static function links(): array {
		return array(
			array(
				'title' => 'Set up your club',
				'note'  => 'How your site looks, which pages it shows, and what it asks your members.',
				'url'   => self::url( 'admin.php?page=' . Blueworx_Clubhouse_Setup_Editor::PAGE_SLUG ),
			),
			array(
				'title' => 'Edit your pages',
				'note'  => 'Every page on your site, edited on the page itself.',
				'url'   => self::url( 'edit.php?post_type=page' ),
			),
			array(
				'title' => 'Your members',
				'note'  => 'Who has joined, and what they told you.',
				'url'   => self::url( 'users.php' ),
			),
		);
	}

	/**
	 * Three rows, each naming a place and saying what is there, with the way in
	 * on the right. Built from the design system's own "a list of named things
	 * you can act on" pattern rather than classes invented here — an invented
	 * class has no styles behind it, which is how this panel came to draw as
	 * bare text (issue #307).
	 *
	 * The link reads "Open" and carries the row's name as its accessible name,
	 * so a screen reader hears "Open your club setup" rather than three links
	 * all called "Open", and the eye is not shown the same words twice.
	 */
	public static function render(): string {
		$body = '<div class="bw-fnstack">';
		foreach ( self::links() as $link ) {
			$body .= '<div class="bw-fncard"><div class="bw-fncard__row"><div>'
				. '<span class="bw-fncard__name">' . self::esc( $link['title'] ) . '</span>'
				. '<p class="bw-fncard__desc">' . self::esc( $link['note'] ) . '</p>'
				. '</div>'
				. '<a class="bw-btn bw-btn--secondary" href="' . self::esc( $link['url'] ) . '"'
				. ' aria-label="' . self::esc( $link['title'] ) . '">Open</a>'
				. '</div></div>';
		}
		$body .= '</div>';

		return '<div class="bw-admin">'
			. Blueworx_Clubhouse_Admin_Shell::card(
				// No eyebrow: the widget WordPress draws around this already
				// carries the word Clubhouse at the top of it.
				'',
				'Welcome back',
				'Everything your club site needs is in these three places.',
				$body
			)
			. '</div>';
	}
}
