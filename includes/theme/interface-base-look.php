<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A Base Look pack: supplies presentation only (shell tokens, fonts, stylesheet).
 * It never adds/removes sections or reads content. Swapping the active look
 * changes only which tokens/fonts/stylesheet are emitted, so re-skinning a live
 * site is a setting change with zero content re-entry.
 *
 * @package BlueworxLabsClubhouse
 */
interface Blueworx_Clubhouse_Base_Look {

	public function slug(): string;

	public function name(): string;

	public function description(): string;

	/**
	 * Fixed neutral-shell CSS custom properties (no accent tokens).
	 * MUST include '--color-bg' and '--color-ink'.
	 *
	 * @return array<string, string>
	 */
	public function tokens(): array;

	/**
	 * Loadable font assets for this look.
	 *
	 * @return array<int, array{family:string, weights:array<int,int>, display:string}>
	 */
	public function fonts(): array;

	/** Plugin-root-relative path to the look's stylesheet. */
	public function stylesheet(): string;

	/**
	 * Does this look paint text ON the accent fill (buttons, hero highlight,
	 * ticker label)? If true, an accent must clear AA as ink-on-fill to be
	 * acceptable; glow-only looks (accent spent as ambient light) return false.
	 */
	public function accent_bears_text(): bool;
}
