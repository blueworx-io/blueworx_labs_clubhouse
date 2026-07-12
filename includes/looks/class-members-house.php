<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Members' House — the second Base Look. Refined and editorial: warm parchment
 * canvas, warm near-black ink, small crisp radii, hairline rules, Fraunces + Mulish.
 * The accent is spent sparingly by the stylesheet; its tokens are engine-derived,
 * not defined here. Supplies presentation only — never adds or reads content.
 *
 * @package BlueworxLabsClubhouse
 */
final class Blueworx_Clubhouse_Members_House implements Blueworx_Clubhouse_Base_Look {

	public function slug(): string {
		return 'members-house';
	}

	public function name(): string {
		return "Members' House";
	}

	public function description(): string {
		return 'Refined, editorial — warm parchment, hairline rules, Fraunces display, restrained accent.';
	}

	/** @return array<string,string> */
	public function tokens(): array {
		return array(
			'--color-bg'       => '#f2ece0',
			'--color-paper'    => '#fbf7ef',
			'--color-ink'      => '#201c15',
			'--color-ink-soft' => '#6b6154',
			'--color-line'     => '#e0d8c7',
			'--radius-xl'      => '10px',
			'--radius-lg'      => '7px',
			'--radius-md'      => '4px',
			'--font-display'   => "'Fraunces', ui-serif, Georgia, serif",
			'--font-body'      => "'Mulish', ui-sans-serif, sans-serif",
		);
	}

	/** @return array<int,array{family:string,weights:array<int,int>,display:string}> */
	public function fonts(): array {
		return array(
			array( 'family' => 'Fraunces', 'weights' => array( 400, 500, 600, 700 ), 'display' => 'swap' ),
			array( 'family' => 'Mulish', 'weights' => array( 400, 500, 600, 700 ), 'display' => 'swap' ),
		);
	}

	public function stylesheet(): string {
		return 'assets/looks/members-house.css';
	}
}
