<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Immutable per-request bundle of the engine collaborators the Frontend and
 * (later) the admin screens need. Replaces the old positional array so call
 * sites read by name and a new member can be added without touching every
 * destructuring site.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Clubhouse_Context {

	public function __construct(
		public readonly ?Blueworx_Clubhouse_Base_Look $look,
		public readonly Blueworx_Clubhouse_Branding $branding,
		public readonly Blueworx_Clubhouse_Visibility $visibility,
		public readonly Blueworx_Clubhouse_Theme_Cache $cache,
		public readonly Blueworx_Clubhouse_Collections $collections,
		public readonly Blueworx_Clubhouse_Base_Look_Registry $registry,
		public readonly ?Blueworx_Clubhouse_Content_Store $content = null
	) {}
}
