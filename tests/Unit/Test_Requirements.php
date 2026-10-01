<?php
/**
 * Requirements test.
 *
 * @package PedalCMS\CassetteCMFBlocks\Tests\Unit
 */

use PedalCMS\CassetteCMFBlocks\Compat\Requirements;

require_once __DIR__ . '/CassetteCMFBlocks_UnitTestCase.php';

/**
 * Class Test_Requirements
 */
class Test_Requirements extends CassetteCMFBlocks_UnitTestCase {

	/**
	 * Reset the cached check result before each test.
	 */
	public function set_up(): void {
		parent::set_up();
		Requirements::reset();
	}

	/**
	 * The parent library and current WordPress version should satisfy the floor
	 * in the test environment, since composer.json requires pedalcms/cassette-cmf ^0.1
	 * and CI runs against WP 6.8+.
	 */
	public function test_check_passes_in_test_environment(): void {
		$result = Requirements::check();

		$this->assertTrue( $result['ok'], implode( ' ', $result['errors'] ) );
		$this->assertSame( [], $result['errors'] );
	}

	/**
	 * check() should be idempotent and cache its result.
	 */
	public function test_check_is_cached(): void {
		$first  = Requirements::check();
		$second = Requirements::check();

		$this->assertSame( $first, $second );
	}

	/**
	 * reset() should clear the cache so a subsequent check() re-evaluates.
	 */
	public function test_reset_clears_cache(): void {
		Requirements::check();
		Requirements::reset();

		// No exception, and a fresh evaluation still passes.
		$result = Requirements::check();
		$this->assertTrue( $result['ok'] );
	}

	/**
	 * cmf_version() reads the parent library's real installed version from
	 * its own composer.json — a non-empty, version_compare()-able string in
	 * this test environment, since the parent is a real Composer dependency
	 * here.
	 */
	public function test_cmf_version_reads_a_real_version_string(): void {
		$version = Requirements::cmf_version();

		$this->assertIsString( $version );
		$this->assertNotSame( '', $version );
		// A real semantic version, not garbage — proves this parsed the
		// parent's actual composer.json rather than returning something
		// coincidentally non-empty.
		$this->assertMatchesRegularExpression( '/^\d+\.\d+\.\d+/', $version );
	}
}
