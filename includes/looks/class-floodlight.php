<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Floodlight — the third Base Look. Bold and dark: warm-ink near-black canvas,
 * warm bone text, crisp mid radii, a contemporary grotesque display, and an accent
 * spent as glow (outline, deep-text, soft shadow, wash) rather than solid fills.
 * The accent's tokens are engine-derived, not defined here. Supplies presentation
 * only — never adds or reads content.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Floodlight implements Blueworx_Clubhouse_Base_Look {

	public function slug(): string {
		return 'floodlight';
	}

	public function name(): string {
		return 'Floodlight';
	}

	public function description(): string {
		return 'Bold and dark — warm-ink canvas, night-match energy, grotesque display, accent glows.';
	}

	/** @return array<string,string> */
	public function tokens(): array {
		return array(
			'--color-bg'       => '#14110b',
			'--color-paper'    => '#1e1913',
			'--color-ink'      => '#f3ede0',
			'--color-ink-soft' => '#a99f8c',
			'--color-line'     => '#302a20',
			'--radius-xl'      => '16px',
			'--radius-lg'      => '11px',
			'--radius-md'      => '7px',
			'--font-display'   => "'Bricolage Grotesque', ui-sans-serif, system-ui, sans-serif",
			'--font-body'      => "'Hanken Grotesk', ui-sans-serif, system-ui, sans-serif",
		);
	}

	/** @return array<int,array{family:string,weights:array<int,int>,display:string}> */
	public function fonts(): array {
		return array(
			array( 'family' => 'Bricolage Grotesque', 'weights' => array( 500, 600, 700, 800 ), 'display' => 'swap' ),
			array( 'family' => 'Hanken Grotesk', 'weights' => array( 400, 500, 600, 700 ), 'display' => 'swap' ),
		);
	}

	public function stylesheet(): string {
		return 'assets/looks/floodlight.css';
	}
}
