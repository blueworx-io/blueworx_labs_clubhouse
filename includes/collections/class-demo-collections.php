<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * DB-free Collections backed by Demo_Content. Used by the preview and tests so
 * pages render real-shaped collection data without WordPress.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Demo_Collections implements Blueworx_Clubhouse_Collections {
	public function sports(): array {
		return Blueworx_Clubhouse_Demo_Content::sports();
	}
	public function teams(): array {
		return Blueworx_Clubhouse_Demo_Content::teams();
	}
	public function fixtures(): array {
		return Blueworx_Clubhouse_Demo_Content::fixtures();
	}
	public function events(): array {
		return Blueworx_Clubhouse_Demo_Content::events();
	}
	public function sponsors(): array {
		return Blueworx_Clubhouse_Demo_Content::sponsors();
	}
	public function people(): array {
		return Blueworx_Clubhouse_Demo_Content::people();
	}
}
