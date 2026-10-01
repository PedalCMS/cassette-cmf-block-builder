<?php
/**
 * Binding_Source_Registrar test.
 *
 * @package PedalCMS\CassetteCMFBlocks\Tests\Unit
 */

use PedalCMS\CassetteCMFBlocks\Binding\Binding_Source_Registrar;

require_once __DIR__ . '/CassetteCMFBlocks_UnitTestCase.php';

/**
 * Class Test_Binding_Source_Registrar
 */
class Test_Binding_Source_Registrar extends CassetteCMFBlocks_UnitTestCase {

	/**
	 * Unregister the source and the test block type after each test.
	 */
	public function tear_down(): void {
		if ( function_exists( 'unregister_block_bindings_source' ) ) {
			unregister_block_bindings_source( Binding_Source_Registrar::SOURCE_NAME );
		}
		if ( WP_Block_Type_Registry::get_instance()->is_registered( 'acme-test/binding-registrar-block' ) ) {
			unregister_block_type( 'acme-test/binding-registrar-block' );
		}
		parent::tear_down();
	}

	/**
	 * register_source() registers "cassette-cmf/field" with the declared
	 * "uses_context", and a working get_value_callback — verified
	 * functionally (calling the source's own public get_value(), the only
	 * way to reach a WP_Block_Bindings_Source's callback from outside the
	 * class: WP_Block_Bindings_Source::$get_value_callback is private).
	 */
	public function test_registers_a_working_source(): void {
		Binding_Source_Registrar::register_source();

		$source = get_block_bindings_source( Binding_Source_Registrar::SOURCE_NAME );
		$this->assertNotNull( $source );
		$this->assertSame( [ 'postId', 'postType' ], $source->uses_context );

		register_block_type( 'acme-test/binding-registrar-block', [ 'uses_context' => [ 'postId' ] ] );
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'agency_phone', '555-0100' );
		$block = new WP_Block(
			[
				'blockName' => 'acme-test/binding-registrar-block',
				'attrs'     => [],
			],
			[ 'postId' => $post_id ]
		);

		$value = $source->get_value( [ 'field' => 'agency_phone' ], $block, 'content' );

		$this->assertSame( '555-0100', $value );
	}
}
