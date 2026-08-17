<?php
// tests/php/ClubhouseContextTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class ClubhouseContextTest extends TestCase {

	public function test_holds_named_members(): void {
		$storage    = new Blueworx_Clubhouse_Fake_Storage();
		$registry   = Blueworx_Clubhouse_Frontend::registry( $storage );
		$look       = $registry->active();
		$branding   = new Blueworx_Clubhouse_Branding( $storage );
		$visibility = new Blueworx_Clubhouse_Visibility( $storage );
		$cache      = new Blueworx_Clubhouse_Theme_Cache( $storage );
		$collections = new Blueworx_Clubhouse_Demo_Collections();
		$content     = new Blueworx_Clubhouse_Content_Store( $storage );

		$ctx = new Blueworx_Clubhouse_Clubhouse_Context(
			$look, $branding, $visibility, $cache, $collections, $registry, $content
		);

		$this->assertInstanceOf( Blueworx_Clubhouse_Court_Side::class, $ctx->look );
		$this->assertSame( $branding, $ctx->branding );
		$this->assertSame( $visibility, $ctx->visibility );
		$this->assertSame( $cache, $ctx->cache );
		$this->assertSame( $collections, $ctx->collections );
		$this->assertSame( $registry, $ctx->registry );
		$this->assertSame( $content, $ctx->content );
	}

	public function test_context_dto_carries_content_store(): void {
		$s   = new Blueworx_Clubhouse_Fake_Storage();
		$ctx = new Blueworx_Clubhouse_Clubhouse_Context(
			null,
			new Blueworx_Clubhouse_Branding( $s ),
			new Blueworx_Clubhouse_Visibility( $s ),
			new Blueworx_Clubhouse_Theme_Cache( $s ),
			new Blueworx_Clubhouse_Demo_Collections(),
			Blueworx_Clubhouse_Frontend::registry( $s ),
			new Blueworx_Clubhouse_Content_Store( $s )
		);
		$this->assertInstanceOf( Blueworx_Clubhouse_Content_Store::class, $ctx->content );
	}
}
