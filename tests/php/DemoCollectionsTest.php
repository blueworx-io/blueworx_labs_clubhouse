<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class DemoCollectionsTest extends TestCase {
	public function test_delegates_to_demo_content(): void {
		$c = new Blueworx_Clubhouse_Demo_Collections();
		$this->assertSame( Blueworx_Clubhouse_Demo_Content::sports(), $c->sports() );
		$this->assertSame( Blueworx_Clubhouse_Demo_Content::people(), $c->people() );
		$this->assertInstanceOf( Blueworx_Clubhouse_Collections::class, $c );
	}
}
