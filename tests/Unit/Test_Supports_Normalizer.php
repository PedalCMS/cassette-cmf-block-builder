<?php
/**
 * Supports_Normalizer test.
 *
 * @package Pedalcms\CassetteCmfBlocks\Tests\Unit
 */

use Pedalcms\CassetteCmfBlocks\Schema\Supports_Normalizer;

require_once __DIR__ . '/CassetteCmfBlocks_UnitTestCase.php';

/**
 * Class Test_Supports_Normalizer
 */
class Test_Supports_Normalizer extends CassetteCmfBlocks_UnitTestCase {

	/**
	 * apiVersion should default to 3 — WP_Block_Type::$api_version defaults
	 * to 1 when left unset, which silently loses useBlockProps() semantics.
	 */
	public function test_api_version_defaults_to_3(): void {
		$args = Supports_Normalizer::normalize( [] );

		$this->assertSame( 3, $args['apiVersion'] );
	}

	/**
	 * An explicit apiVersion is never overridden.
	 */
	public function test_explicit_api_version_is_preserved(): void {
		$args = Supports_Normalizer::normalize( [ 'apiVersion' => 2 ] );

		$this->assertSame( 2, $args['apiVersion'] );
	}

	/**
	 * A dynamic block should default supports.html to false — its saved
	 * markup is never rendered to the visitor.
	 */
	public function test_dynamic_block_defaults_html_support_to_false(): void {
		$args = Supports_Normalizer::normalize( [], true );

		$this->assertFalse( $args['supports']['html'] );
	}

	/**
	 * A static block should not have supports.html forced.
	 */
	public function test_static_block_does_not_force_html_support(): void {
		$args = Supports_Normalizer::normalize( [], false );

		$this->assertArrayNotHasKey( 'html', $args['supports'] ?? [] );
	}

	/**
	 * An explicit supports.html is never overridden, even for a dynamic block.
	 */
	public function test_explicit_html_support_is_preserved(): void {
		$args = Supports_Normalizer::normalize( [ 'supports' => [ 'html' => true ] ], true );

		$this->assertTrue( $args['supports']['html'] );
	}

	/**
	 * Other supports keys are preserved alongside the html default.
	 */
	public function test_other_supports_keys_are_preserved(): void {
		$args = Supports_Normalizer::normalize( [ 'supports' => [ 'anchor' => true ] ], true );

		$this->assertTrue( $args['supports']['anchor'] );
		$this->assertFalse( $args['supports']['html'] );
	}
}
