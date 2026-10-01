<?php
/**
 * Deprecation_Builder test.
 *
 * @package PedalCMS\CassetteCMFBlocks\Tests\Unit
 */

use PedalCMS\CassetteCMFBlocks\Render\Deprecation_Builder;

require_once __DIR__ . '/CassetteCMFBlocks_UnitTestCase.php';

/**
 * Class Test_Deprecation_Builder
 */
class Test_Deprecation_Builder extends CassetteCMFBlocks_UnitTestCase {

	/**
	 * The same (markup, attributes) pair must always hash the same.
	 */
	public function test_hash_is_stable_for_identical_input(): void {
		$markup     = [
			'tag'  => 'div',
			'text' => '{{ attributes.heading }}',
		];
		$attributes = [ 'heading' => [ 'type' => 'string' ] ];

		$this->assertSame(
			Deprecation_Builder::hash( $markup, $attributes ),
			Deprecation_Builder::hash( $markup, $attributes )
		);
	}

	/**
	 * A different markup tree must produce a different hash.
	 */
	public function test_hash_differs_for_different_markup(): void {
		$attributes = [ 'heading' => [ 'type' => 'string' ] ];

		$hash_a = Deprecation_Builder::hash( [ 'tag' => 'div' ], $attributes );
		$hash_b = Deprecation_Builder::hash( [ 'tag' => 'span' ], $attributes );

		$this->assertNotSame( $hash_a, $hash_b );
	}

	/**
	 * A different attribute schema must produce a different hash — a
	 * markup-identical block with a changed attribute schema is still a
	 * breaking change deprecations need to know about.
	 */
	public function test_hash_differs_for_different_attributes(): void {
		$markup = [ 'tag' => 'div' ];

		$hash_a = Deprecation_Builder::hash( $markup, [ 'heading' => [ 'type' => 'string' ] ] );
		$hash_b = Deprecation_Builder::hash( $markup, [ 'heading' => [ 'type' => 'number' ] ] );

		$this->assertNotSame( $hash_a, $hash_b );
	}

	/**
	 * Key order in either array must not affect the hash — wp_json_encode()
	 * doesn't sort keys, but PHP array key order is otherwise incidental to
	 * a config's meaning, so a hash sensitive to it would be spuriously
	 * "different" for two config builds that only differ by iteration order.
	 */
	public function test_hash_is_stable_regardless_of_array_key_order(): void {
		$attributes_a = [
			'heading' => [ 'type' => 'string' ],
			'tone'    => [ 'type' => 'string' ],
		];
		$attributes_b = [
			'tone'    => [ 'type' => 'string' ],
			'heading' => [ 'type' => 'string' ],
		];

		$this->assertSame(
			Deprecation_Builder::hash( [ 'tag' => 'div' ], $attributes_a ),
			Deprecation_Builder::hash( [ 'tag' => 'div' ], $attributes_b )
		);
	}
}
