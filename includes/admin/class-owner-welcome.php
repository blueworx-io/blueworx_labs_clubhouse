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

	public static function render(): string {
		$body = '<div class="bw-stack">';
		foreach ( self::links() as $link ) {
			$body .= '<div class="bw-row">'
				. '<p class="bw-row__title">' . self::esc( $link['title'] ) . '</p>'
				. '<p class="bw-fieldnote">' . self::esc( $link['note'] ) . '</p>'
				. '<p><a class="bw-btn bw-btn--secondary" href="' . self::esc( $link['url'] ) . '">'
				. self::esc( $link['title'] ) . '</a></p>'
				. '</div>';
		}
		$body .= '</div>';

		return '<div class="bw-admin">'
			. Blueworx_Clubhouse_Admin_Shell::card(
				'Clubhouse',
				'Welcome back',
				'Everything your club site needs is in these three places.',
				$body
			)
			. '</div>';
	}
}
