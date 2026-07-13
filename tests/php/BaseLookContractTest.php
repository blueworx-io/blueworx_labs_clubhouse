<?php

use PHPUnit\Framework\TestCase;

final class BaseLookContractTest extends TestCase {
	public function test_fake_look_satisfies_contract(): void {
		$look = new Blueworx_Clubhouse_Fake_Look();
		$this->assertInstanceOf( Blueworx_Clubhouse_Base_Look::class, $look );
	}

	public function test_reports_identity(): void {
		$look = new Blueworx_Clubhouse_Fake_Look( 'court-side', 'Court Side', 'Bright & playful.' );
		$this->assertSame( 'court-side', $look->slug() );
		$this->assertSame( 'Court Side', $look->name() );
		$this->assertSame( 'Bright & playful.', $look->description() );
	}

	public function test_tokens_include_shell_bg_and_ink(): void {
		$tokens = ( new Blueworx_Clubhouse_Fake_Look() )->tokens();
		$this->assertArrayHasKey( '--color-bg', $tokens );
		$this->assertArrayHasKey( '--color-ink', $tokens );
	}

	public function test_fonts_and_stylesheet(): void {
		$look = new Blueworx_Clubhouse_Fake_Look();
		$this->assertNotEmpty( $look->fonts() );
		$this->assertArrayHasKey( 'family', $look->fonts()[0] );
		$this->assertArrayHasKey( 'stem', $look->fonts()[0] );
		$this->assertStringEndsWith( '.css', $look->stylesheet() );
	}
}
