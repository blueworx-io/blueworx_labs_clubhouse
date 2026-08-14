<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Everything a block needs to render itself, and nothing else. Handed to a
 * block type's renderer so the renderer never reaches for global state — the
 * same reason Clubhouse_Context exists on the frontend side.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Block_Context {

	public function __construct(
		public readonly string $page,
		public readonly Blueworx_Clubhouse_Branding $branding,
		public readonly Blueworx_Clubhouse_Collections $collections,
		public readonly string $anchor_id = '',
		public readonly string $filter = '',
		public readonly string $logo_url = ''
	) {}
}
