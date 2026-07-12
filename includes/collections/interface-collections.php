<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read model for the six site collections. Each method returns a list of
 * canonical associative arrays (all fields a projection might need). The pure
 * Demo implementation serves the preview and tests; the WP implementation reads
 * seeded custom-post-type posts.
 *
 * @package BlueworxLabsClubhouse
 */
interface Blueworx_Clubhouse_Collections {
	/** @return array<int,array<string,mixed>> */
	public function sports(): array;
	/** @return array<int,array<string,mixed>> */
	public function teams(): array;
	/** @return array<int,array<string,mixed>> */
	public function fixtures(): array;
	/** @return array<int,array<string,mixed>> */
	public function events(): array;
	/** @return array<int,array<string,mixed>> */
	public function sponsors(): array;
	/** @return array<int,array<string,mixed>> */
	public function people(): array;
}
