<?php
// includes/dashboard/class-dashboard-actions.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The member area's write journeys — updating billing details, adding a card,
 * opening an order, cancelling a plan.
 *
 * SureCart composes its own dashboard from one wrapper block that reads
 * `model` and `action` off the address and hands the request to a controller,
 * plus the leaf blocks that draw each read-only panel. The member area replaces
 * that wrapper with its own frame and renders only the leaves, so every link
 * SureCart draws — Update, Add, Payment History, Cancel — came back to the same
 * read-only panel and did nothing at all.
 *
 * This is the part that was missing: which addresses mean an action, and which
 * of our panels that action belongs under. The rendering is still SureCart's —
 * done by rendering its own wrapper block — so its controllers, its permission
 * checks and its markup are the ones that run.
 *
 * The map is SureCart's, read from its DashboardPage block. A model it does not
 * know, or an action its controller has no method for, is not an action at all:
 * the member area draws its normal panels, which is what an old bookmark or a
 * hand-typed address should do.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Dashboard_Actions {

	/** SureCart's own wrapper block: the piece that does the routing. */
	public const BLOCK = 'surecart/dashboard-page';

	/** The query args SureCart's links carry. */
	public const MODEL_ARG  = 'model';
	public const ACTION_ARG = 'action';

	/**
	 * SureCart's model-to-controller map, from its DashboardPage block.
	 *
	 * @var array<string,string>
	 */
	private const CONTROLLERS = array(
		'subscription'   => '\SureCartBlocks\Controllers\SubscriptionController',
		'payment_method' => '\SureCartBlocks\Controllers\PaymentMethodController',
		'charge'         => '\SureCartBlocks\Controllers\ChargeController',
		'order'          => '\SureCartBlocks\Controllers\OrderController',
		'user'           => '\SureCartBlocks\Controllers\UserController',
		'customer'       => '\SureCartBlocks\Controllers\CustomerController',
		'download'       => '\SureCartBlocks\Controllers\DownloadController',
		'invoice'        => '\SureCartBlocks\Controllers\InvoiceController',
		'license'        => '\SureCartBlocks\Controllers\LicenseController',
	);

	/**
	 * Which of our panels a model's action belongs under, so the nav goes on
	 * highlighting the thing the member is actually looking at.
	 *
	 * SureCart's own links drop every query arg but its own, so the view named
	 * in the address does not survive the click. This puts it back.
	 *
	 * @var array<string,string>
	 */
	private const VIEWS = array(
		'customer'       => 'account',
		'user'           => 'account',
		'payment_method' => 'account',
		'subscription'   => 'plans',
		'order'          => 'orders',
		'charge'         => 'orders',
		'invoice'        => 'invoices',
	);

	/**
	 * Whether this address asks for something to be done rather than read.
	 *
	 * Pure: the caller says which controllers and methods the shop on this site
	 * actually has, so the rule is testable without SureCart present.
	 *
	 * @param callable(string,string):bool $exists Does this controller have this method?
	 */
	public static function is_action( string $model, string $action, callable $exists ): bool {
		$model  = trim( $model );
		$action = trim( $action );
		if ( '' === $model || '' === $action || ! isset( self::CONTROLLERS[ $model ] ) ) {
			return false;
		}
		return (bool) $exists( self::CONTROLLERS[ $model ], $action );
	}

	/**
	 * The panel an action belongs under, or '' when it belongs under none —
	 * downloads and licences, which the member area offers no panel for and
	 * which no club sells.
	 */
	public static function view_for( string $model ): string {
		return self::VIEWS[ trim( $model ) ] ?? '';
	}

	/** @var (callable(string,string):bool)|null */
	private static $check = null;

	/**
	 * Swap the "does this controller have this method" test.
	 *
	 * A seam, like Plugin_Slot's: SureCart's classes are not loaded in a unit
	 * test, so without one every action address would answer "not an action"
	 * and the routing could only be exercised on a live site.
	 *
	 * @param (callable(string,string):bool)|null $check Null restores the real one.
	 */
	public static function set_check( ?callable $check ): void {
		self::$check = $check;
	}

	/**
	 * The real "does this controller have this method" test, for the site.
	 *
	 * Exactly the check SureCart's own wrapper makes before dispatching, so an
	 * address this says yes to is one SureCart will act on.
	 *
	 * @return callable(string,string):bool
	 */
	public static function site_check(): callable {
		if ( null !== self::$check ) {
			return self::$check;
		}
		return static function ( string $controller, string $action ): bool {
			return class_exists( $controller ) && method_exists( $controller, $action );
		};
	}

	/**
	 * What the address is asking for.
	 *
	 * @return array{model:string,action:string}
	 */
	public static function requested(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- reading which screen to draw; SureCart's own controllers check permissions before acting on anything.
		$model  = $_GET[ self::MODEL_ARG ] ?? '';
		$action = $_GET[ self::ACTION_ARG ] ?? '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		return array(
			'model'  => is_string( $model ) ? $model : '',
			'action' => is_string( $action ) ? $action : '',
		);
	}
}
