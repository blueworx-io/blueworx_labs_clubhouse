<?php
// tests/php/LinksTest.php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class LinksTest extends TestCase {

	protected function tearDown(): void {
		Blueworx_Clubhouse_Links::set_resolver( null );
	}

	public function test_default_resolver_emits_preview_query_links(): void {
		Blueworx_Clubhouse_Links::set_resolver( null );
		$this->assertSame( '?page=home', Blueworx_Clubhouse_Links::url( 'home' ) );
		$this->assertSame( '?page=about', Blueworx_Clubhouse_Links::url( 'about' ) );
		$this->assertSame( '?page=calendar', Blueworx_Clubhouse_Links::url( 'calendar' ) );
	}

	public function test_custom_resolver_is_used(): void {
		Blueworx_Clubhouse_Links::set_resolver(
			static fn( string $key ): string => 'home' === $key ? 'https://x.test/' : 'https://x.test/' . $key . '/'
		);
		$this->assertSame( 'https://x.test/', Blueworx_Clubhouse_Links::url( 'home' ) );
		$this->assertSame( 'https://x.test/about/', Blueworx_Clubhouse_Links::url( 'about' ) );
	}

	public function test_resetting_resolver_restores_default(): void {
		Blueworx_Clubhouse_Links::set_resolver( static fn( string $key ): string => '/x' );
		Blueworx_Clubhouse_Links::set_resolver( null );
		$this->assertSame( '?page=contact', Blueworx_Clubhouse_Links::url( 'contact' ) );
	}
}
