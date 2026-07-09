<?php

use PHPUnit\Framework\TestCase;

final class HarnessTest extends TestCase {
	public function test_harness_runs(): void {
		$this->assertTrue( defined( 'ABSPATH' ) );
	}
}
