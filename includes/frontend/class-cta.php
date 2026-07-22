<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Canonical CTA label strings, so the same action reads identically everywhere.
 * The UX review found the membership-join action labelled six different ways;
 * one constant is the single source of truth. Distinct actions that merely share
 * a destination (contact enquiries) are intentionally not collapsed here.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Cta {
	/** Membership-join action, used sitewide. */
	public const JOIN = 'Join the club';
}
