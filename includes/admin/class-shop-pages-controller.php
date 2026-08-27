<?php
// includes/admin/class-shop-pages-controller.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tells an owner which of their shop's pages are missing, and asks SureCart to
 * put them back when they say so.
 *
 * Behind a button rather than automatic: publishing pages on a club's live site
 * is not something a plugin should do on a whim, and the state this catches is
 * rare enough that somebody should be there when it is fixed.
 *
 * The notice runs on every admin screen rather than only the Clubhouse ones,
 * because the owner who needs it has no reason to visit a Clubhouse screen —
 * the symptom they are living with is on the front end.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Shop_Pages_Controller {

	public const CAPABILITY = 'manage_options';
	public const ACTION     = 'clubhouse_shop_pages_repair';
	public const NONCE      = 'clubhouse_shop_pages_repair';

	public static function register(): void {
		add_action( 'admin_notices', array( self::class, 'render_notice' ) );
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handle_repair' ) );
		// The confirmation page is made here rather than only on activation,
		// because the usual order is Clubhouse first and the shop afterwards.
		// admin_init at the default priority: after the shop has loaded, and
		// before the notice above decides whether it has anything to say.
		add_action( 'admin_init', array( Blueworx_Clubhouse_Shop_Pages::class, 'ensure_confirmation' ) );
	}

	/**
	 * What the notice says. Pure.
	 *
	 * One notice for the lot rather than one per page, because the usual cause
	 * is a shop that was never finished, and four warnings about one cause is
	 * four times the noise for no extra information.
	 *
	 * @param array<string,string>                                            $problems From Shop_Pages::problems().
	 * @param array<string,array{label:string,consequence:string}> $pages    From Shop_Pages::pages().
	 * @param bool                                                            $can_seed Whether SureCart can create what is missing.
	 * @return array{lines:array<int,string>,button:string,footnote:string}|null Null when nothing is wrong.
	 */
	public static function message( array $problems, array $pages, bool $can_seed ): ?array {
		if ( array() === $problems ) {
			return null;
		}

		$lines = array();
		foreach ( $problems as $key => $status ) {
			$page  = $pages[ $key ] ?? array( 'label' => $key, 'consequence' => 'it cannot be reached' );
			$state = Blueworx_Clubhouse_Shop_Pages::STATUS_UNPUBLISHED === $status
				? 'is in the trash or unpublished'
				: 'is missing';
			$lines[] = 'Your ' . $page['label'] . ' ' . $state . ', so ' . $page['consequence'] . '.';
		}

		$repairable = Blueworx_Clubhouse_Shop_Pages::repairable( $problems, $pages );
		$button     = ( array() !== $repairable && $can_seed ) || self::has_unpublished( $problems )
			? 'Put the missing pages back'
			: '';

		// Anything the button will not fix is named, so pressing it and finding
		// a warning still there is not a surprise. With no button on offer the
		// same sentence would be talking about a button that is not there.
		$left     = array_diff_key( $problems, $repairable );
		$footnote = '';
		if ( array() !== $left && '' !== $button ) {
			$footnote = 'Open SureCart and finish setting the shop up — the button above cannot create the '
				. implode( ' or the ', array_map( static fn ( string $k ): string => $pages[ $k ]['label'] ?? $k, array_keys( $left ) ) ) . '.';
		} elseif ( '' === $button ) {
			$footnote = 'Open SureCart and finish setting the shop up.';
		}

		return array(
			'lines'    => $lines,
			'button'   => $button,
			'footnote' => $footnote,
		);
	}

	/** @param array<string,string> $problems */
	private static function has_unpublished( array $problems ): bool {
		return in_array( Blueworx_Clubhouse_Shop_Pages::STATUS_UNPUBLISHED, $problems, true );
	}

	/**
	 * The notice markup. Pure, so the escaping is asserted in a test rather
	 * than by eye.
	 *
	 * @param array{lines:array<int,string>,button:string,footnote:string}|null $message From message().
	 */
	public static function notice_html( ?array $message, string $action_url ): string {
		if ( null === $message ) {
			return '';
		}
		$html = '<div class="notice notice-warning"><p><strong>Clubhouse:</strong> your shop is not ready to take payments.</p><ul>';
		foreach ( $message['lines'] as $line ) {
			$html .= '<li>' . esc_html( $line ) . '</li>';
		}
		$html .= '</ul>';
		if ( '' !== $message['button'] ) {
			$html .= '<p><a class="button button-primary" href="' . esc_url( $action_url ) . '">'
				. esc_html( $message['button'] ) . '</a></p>';
		}
		if ( '' !== $message['footnote'] ) {
			$html .= '<p>' . esc_html( $message['footnote'] ) . '</p>';
		}
		return $html . '</div>';
	}

	public static function render_notice(): void {
		if ( ! self::can_manage() ) {
			return;
		}
		$pages    = Blueworx_Clubhouse_Shop_Pages::pages();
		$problems = Blueworx_Clubhouse_Shop_Pages::problems( Blueworx_Clubhouse_Shop_Pages::statuses() );
		if ( array() === $problems ) {
			return;
		}
		echo self::notice_html( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- notice_html escapes every dynamic part.
			self::message( $problems, $pages, Blueworx_Clubhouse_Shop_Pages::can_seed() ),
			self::action_url()
		);
	}

	public static function handle_repair(): void {
		if ( ! self::can_manage() ) {
			return;
		}
		check_admin_referer( self::NONCE );
		Blueworx_Clubhouse_Shop_Pages::repair();
		$back = wp_get_referer();
		wp_safe_redirect( false !== $back ? $back : admin_url() );
		exit;
	}

	private static function action_url(): string {
		return wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION ), self::NONCE );
	}

	private static function can_manage(): bool {
		return function_exists( 'current_user_can' ) && current_user_can( self::CAPABILITY );
	}
}
