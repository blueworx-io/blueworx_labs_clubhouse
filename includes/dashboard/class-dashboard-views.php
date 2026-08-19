<?php
// includes/dashboard/class-dashboard-views.php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What the member area is made of: the views, in order, and what each needs.
 *
 * One declarative list because three things have to agree — the left nav, the
 * router that decides which view an address means, and the panel that renders
 * it. Two lists is how a nav item comes to point at a view that does not exist.
 *
 * Pure. Which plugins a site has is answered by Integrations and handed in.
 *
 * The block names are SureCart's own, read from its source: its customer
 * dashboard is composed of these separate blocks rather than one, which is what
 * makes one block per panel possible. customer-downloads and customer-licenses
 * are deliberately absent — no club sells either, and an empty panel on every
 * account page is a cost with no reader.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Dashboard_Views {

	/** Where a member lands with no view named, and the fallback for anything unrecognised. */
	public const DEFAULT_VIEW = 'dashboard';

	public const NEEDS_SURECART  = 'surecart';
	public const NEEDS_LATEPOINT = 'latepoint';

	/**
	 * Every view this plugin knows how to draw, in the order the nav shows them.
	 *
	 * 'icon' names the glyph Dashboard_Shell draws. 'blocks' are rendered in
	 * order, each in its own card. 'shortcode' takes the whole view — LatePoint
	 * brings its own tabs and does not belong boxed inside a card.
	 *
	 * @return array<int,array{key:string,label:string,title:string,lede:string,icon:string,requires:string,blocks:array<int,string>,shortcode:string}>
	 */
	public static function all(): array {
		return array(
			array(
				'key'       => 'dashboard',
				'label'     => 'Dashboard',
				'title'     => 'Your account',
				'lede'      => 'Everything the club keeps for you, in one place.',
				'icon'      => 'layout-dashboard',
				'requires'  => '',
				'blocks'    => array(),
				'shortcode' => '',
			),
			array(
				'key'       => 'bookings',
				'label'     => 'Bookings',
				'title'     => 'Bookings',
				'lede'      => 'What you have booked, and anything coming up.',
				'icon'      => 'calendar',
				'requires'  => self::NEEDS_LATEPOINT,
				'blocks'    => array(),
				'shortcode' => 'latepoint_customer_dashboard',
			),
			array(
				'key'       => 'orders',
				'label'     => 'Orders',
				'title'     => 'Orders',
				'lede'      => 'Everything you have bought from the club.',
				'icon'      => 'shopping-cart',
				'requires'  => self::NEEDS_SURECART,
				'blocks'    => array( 'surecart/customer-orders' ),
				'shortcode' => '',
			),
			array(
				'key'       => 'invoices',
				'label'     => 'Invoices',
				'title'     => 'Invoices',
				'lede'      => 'Your receipts, and anything still to pay.',
				'icon'      => 'file-spreadsheet',
				'requires'  => self::NEEDS_SURECART,
				'blocks'    => array( 'surecart/customer-invoices' ),
				'shortcode' => '',
			),
			array(
				'key'       => 'plans',
				'label'     => 'Plans',
				'title'     => 'Your membership',
				'lede'      => 'What you pay, how often, and when it renews.',
				'icon'      => 'refresh-cw',
				'requires'  => self::NEEDS_SURECART,
				'blocks'    => array( 'surecart/customer-subscriptions' ),
				'shortcode' => '',
			),
			array(
				'key'       => 'account',
				'label'     => 'Account',
				'title'     => 'Account details',
				'lede'      => 'Your details and how you pay.',
				'icon'      => 'users',
				'requires'  => self::NEEDS_SURECART,
				'blocks'    => array( 'surecart/customer-billing-details', 'surecart/customer-payment-methods' ),
				'shortcode' => '',
			),
		);
	}

	/**
	 * The views this club can actually offer.
	 *
	 * A view whose plugin is absent is not offered at all — see the spec. It is
	 * not hidden behind a message, because there is nothing a member could do
	 * about it and nothing the club needs to be told twice.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function available( bool $has_surecart, bool $has_latepoint ): array {
		$out = array();
		foreach ( self::all() as $view ) {
			$needs = (string) $view['requires'];
			if ( self::NEEDS_SURECART === $needs && ! $has_surecart ) {
				continue;
			}
			if ( self::NEEDS_LATEPOINT === $needs && ! $has_latepoint ) {
				continue;
			}
			$out[] = $view;
		}
		return $out;
	}

	/**
	 * Which view an address means. Anything not on the list — junk, a typo, a
	 * bookmark from before a plugin was removed — lands on the dashboard, which
	 * always exists.
	 *
	 * @param array<int,array<string,mixed>> $available From available().
	 */
	public static function resolve( string $requested, array $available ): string {
		$requested = trim( $requested );
		foreach ( $available as $view ) {
			if ( $requested === (string) $view['key'] ) {
				return $requested;
			}
		}
		return self::DEFAULT_VIEW;
	}

	/**
	 * One view by key, or null.
	 *
	 * @param array<int,array<string,mixed>> $views
	 * @return array<string,mixed>|null
	 */
	public static function find( string $key, array $views ): ?array {
		foreach ( $views as $view ) {
			if ( $key === (string) $view['key'] ) {
				return $view;
			}
		}
		return null;
	}
}
