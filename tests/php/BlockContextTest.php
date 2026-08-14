<?php

use PHPUnit\Framework\TestCase;

final class BlockContextTest extends TestCase {

	private function ctx( string $page = 'home', string $filter = '' ): Blueworx_Clubhouse_Block_Context {
		return new Blueworx_Clubhouse_Block_Context(
			$page,
			new Blueworx_Clubhouse_Branding( new Blueworx_Clubhouse_Fake_Storage() ),
			new Blueworx_Clubhouse_Demo_Collections(),
			'ch-home-hero',
			$filter,
			'https://club.test/logo.png'
		);
	}

	public function test_it_carries_the_page_and_anchor(): void {
		$ctx = $this->ctx();
		$this->assertSame( 'home', $ctx->page );
		$this->assertSame( 'ch-home-hero', $ctx->anchor_id );
	}

	public function test_filter_and_logo_are_carried_through(): void {
		$ctx = $this->ctx( 'sports', 'netball' );
		$this->assertSame( 'netball', $ctx->filter );
		$this->assertSame( 'https://club.test/logo.png', $ctx->logo_url );
	}

	public function test_branding_and_collections_are_the_objects_given(): void {
		$ctx = $this->ctx();
		$this->assertInstanceOf( Blueworx_Clubhouse_Branding::class, $ctx->branding );
		$this->assertNotSame( array(), $ctx->collections->sports() );
	}
}
