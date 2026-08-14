<?php
// includes/admin/class-checkout-page-controller.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tells an owner their shop has no checkout page, and repairs it when they ask.
 *
 * The repair is behind a button rather than automatic. Creating a published
 * page on a club's live site is not something a plugin should do on a whim, and
 * the state this catches is rare — SureCart seeds the page itself, so a missing
 * one means somebody deleted it, and somebody should therefore be the one to
 * put it back.
 *
 * The notice runs on every admin screen rather than only the Clubhouse ones,
 * because the club owner who needs to see it has no reason to visit a Clubhouse
 * screen: the symptom they are living with — Join buttons that go to the
 * contact page — shows on the front end.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Checkout_Page_Controller {

	public const CAPABILITY = 'manage_options';
	public const ACTION     = 'clubhouse_checkout_page_repair';
	public const NONCE      = 'clubhouse_checkout_page_repair';

	public static function register(): void {
		add_action( 'admin_notices', array( self::class, 'render_notice' ) );
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handle_repair' ) );
	}

	/**
	 * What the notice says for each unhealthy state, and whether it can offer a
	 * button. Pure.
	 *
	 * No-form is the one state with no button: the shop has no checkout form to
	 * put on a page, so creating one would publish a checkout that renders
	 * nothing. Better to say so than to hand over a broken page and call it
	 * fixed.
	 *
	 * @return array{text:string,button:string}|null Null when nothing is wrong.
	 */
	public static function message( string $status ): ?array {
		switch ( $status ) {
			case Blueworx_Clubhouse_Checkout_Page::STATUS_MISSING:
				return array(
					'text'   => 'Your shop has no checkout page, so membership Join buttons send people to your contact page instead of taking payment.',
					'button' => 'Create the checkout page',
				);
			case Blueworx_Clubhouse_Checkout_Page::STATUS_UNPUBLISHED:
				return array(
					'text'   => 'Your checkout page is in the trash or unpublished, so anyone clicking a Join button reaches a "page not found".',
					'button' => 'Publish the checkout page',
				);
			case Blueworx_Clubhouse_Checkout_Page::STATUS_NO_FORM:
				return array(
					'text'   => 'Your shop has no checkout page and no checkout form to put on one. Open SureCart and finish setting the shop up, then this notice will offer to create the page.',
					'button' => '',
				);
			default:
				return null;
		}
	}

	/**
	 * The notice markup. Pure, so the escaping is asserted in a unit test
	 * rather than by eye.
	 *
	 * @param array{text:string,button:string}|null $message From message().
	 */
	public static function notice_html( ?array $message, string $action_url ): string {
		if ( null === $message ) {
			return '';
		}
		$html = '<div class="notice notice-warning"><p><strong>Clubhouse:</strong> '
			. esc_html( $message['text'] ) . '</p>';
		if ( '' !== $message['button'] ) {
			$html .= '<p><a class="button button-primary" href="' . esc_url( $action_url ) . '">'
				. esc_html( $message['button'] ) . '</a></p>';
		}
		return $html . '</div>';
	}

	public static function render_notice(): void {
		if ( ! self::can_manage() ) {
			return;
		}
		$status = Blueworx_Clubhouse_Checkout_Page::status();
		if ( ! Blueworx_Clubhouse_Checkout_Page::needs_attention( $status ) ) {
			return;
		}
		echo self::notice_html( self::message( $status ), self::action_url() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- notice_html escapes every dynamic part.
	}

	public static function handle_repair(): void {
		if ( ! self::can_manage() ) {
			return;
		}
		check_admin_referer( self::NONCE );
		Blueworx_Clubhouse_Checkout_Page::repair();
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
