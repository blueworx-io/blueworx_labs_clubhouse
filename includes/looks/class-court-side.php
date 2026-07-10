<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Court Side — the reference Base Look. Bright, playful-premium: near-white warm
 * canvas, soft warm ink (never pure black), rounded shapes, Syne + Inter. Supplies
 * presentation only; the accent tokens are derived by the engine, not defined here.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Court_Side implements Blueworx_Clubhouse_Base_Look {

	public function slug(): string {
		return 'court-side';
	}

	public function name(): string {
		return 'Court Side';
	}

	public function description(): string {
		return 'Bright, playful-premium — near-white canvas, bold accent blocks, Syne display.';
	}

	/** @return array<string,string> */
	public function tokens(): array {
		return array(
			'--color-bg'       => '#faf8f3',
			'--color-paper'    => '#ffffff',
			'--color-ink'      => '#1c1b18',
			'--color-ink-soft' => '#6a675f',
			'--color-line'     => '#e9e4d8',
			'--radius-xl'      => '32px',
			'--radius-lg'      => '24px',
			'--radius-md'      => '16px',
			'--font-display'   => "'Syne', ui-sans-serif, sans-serif",
			'--font-body'      => "'Inter', ui-sans-serif, sans-serif",
		);
	}

	/** @return array<int,array{family:string,weights:array<int,int>,display:string}> */
	public function fonts(): array {
		return array(
			array( 'family' => 'Syne', 'weights' => array( 600, 700, 800 ), 'display' => 'swap' ),
			array( 'family' => 'Inter', 'weights' => array( 400, 500, 600 ), 'display' => 'swap' ),
		);
	}

	public function stylesheet(): string {
		return 'assets/looks/court-side.css';
	}
}
