<?php
/**
 * Sample Test
 *
 * A simple test to verify the WordPress test environment is working.
 *
 * @package Pedalcms\CassetteCmfBlocks\Tests\Unit
 */

/**
 * Class Test_Sample
 */
class Test_Sample extends WP_UnitTestCase {

	/**
	 * Test WordPress is loaded.
	 */
	public function test_wordpress_is_loaded(): void {
		$this->assertTrue( function_exists( 'add_action' ) );
		$this->assertTrue( function_exists( 'add_filter' ) );
		$this->assertTrue( function_exists( 'register_block_type' ) );
	}

	/**
	 * Test WordPress version meets this library's floor.
	 */
	public function test_wordpress_version_meets_floor(): void {
		global $wp_version;

		$this->assertTrue(
			version_compare( $wp_version, \Pedalcms\CassetteCmfBlocks\Compat\Requirements::MIN_WP_VERSION, '>=' ),
			"Running WP $wp_version does not meet the library's floor."
		);
	}

	/**
	 * Test the parent library autoloads alongside this one.
	 */
	public function test_parent_library_is_loaded(): void {
		$this->assertTrue( class_exists( \Pedalcms\CassetteCmf\Field\Field_Factory::class ) );
	}
}
