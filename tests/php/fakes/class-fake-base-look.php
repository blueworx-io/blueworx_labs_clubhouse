<?php
declare(strict_types=1);

final class Blueworx_Clubhouse_Fake_Look implements Blueworx_Clubhouse_Base_Look {

	private string $slug;
	private string $name;
	private string $description;
	/** @var array<string,string> */
	private array $tokens;

	/** @param array<string,string>|null $tokens */
	public function __construct(
		string $slug = 'fake',
		string $name = 'Fake Look',
		string $description = 'A test look.',
		?array $tokens = null
	) {
		$this->slug        = $slug;
		$this->name        = $name;
		$this->description = $description;
		$this->tokens      = $tokens ?? array(
			'--color-bg'   => '#faf8f3',
			'--color-ink'  => '#1c1b18',
			'--radius-lg'  => '24px',
			'--font-display' => 'Syne, sans-serif',
		);
	}

	public function slug(): string { return $this->slug; }
	public function name(): string { return $this->name; }
	public function description(): string { return $this->description; }

	/** @return array<string,string> */
	public function tokens(): array { return $this->tokens; }

	/** @return array<int,array{family:string,weights:array<int,int>,display:string}> */
	public function fonts(): array {
		return array(
			array( 'family' => 'Syne', 'weights' => array( 600, 700, 800 ), 'display' => 'swap' ),
			array( 'family' => 'Inter', 'weights' => array( 400, 500, 600 ), 'display' => 'swap' ),
		);
	}

	public function stylesheet(): string {
		return 'assets/looks/' . $this->slug . '.css';
	}

	public function accent_bears_text(): bool {
		return true;
	}
}
