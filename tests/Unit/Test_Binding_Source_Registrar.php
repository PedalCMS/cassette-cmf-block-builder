<?php
/**
 * Binding_Source_Registrar test.
 *
 * @package PedalCMS\CassetteCMFBlocks\Tests\Unit
 */

use PedalCMS\CassetteCMFBlocks\Binding\Binding_Source_Registrar;
use PedalCMS\CassetteCMFBlocks\CassetteCMFBlocks;
use PedalCMS\CassetteCMFBlocks\Core\Block_Manager;
use PedalCMS\CassetteCMFBlocks\Core\Identity;

require_once __DIR__ . '/CassetteCMFBlocks_UnitTestCase.php';

/**
 * Class Test_Binding_Source_Registrar
 */
class Test_Binding_Source_Registrar extends CassetteCMFBlocks_UnitTestCase {

	/**
	 * Start every test unbooted with no source registered.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->unregister_source();
		Block_Manager::reset();
	}

	/**
	 * Unregister the source, the test block type and the boot state after each test.
	 */
	public function tear_down(): void {
		$this->unregister_source();
		if ( WP_Block_Type_Registry::get_instance()->is_registered( 'acme-test/binding-registrar-block' ) ) {
			unregister_block_type( 'acme-test/binding-registrar-block' );
		}
		Block_Manager::reset();
		parent::tear_down();
	}

	/**
	 * Remove the source if registered.
	 */
	private function unregister_source(): void {
		if ( null !== get_block_bindings_source( Identity::binding_source_name() ) ) {
			unregister_block_bindings_source( Identity::binding_source_name() );
		}
	}

	/**
	 * By default (no boot options, or an implicit boot) nothing is registered.
	 */
	public function test_not_registered_by_default(): void {
		Binding_Source_Registrar::register_source();
		$this->assertNull( get_block_bindings_source( Identity::binding_source_name() ) );

		CassetteCMFBlocks::boot( [ 'prefix' => 'test' ] );
		Binding_Source_Registrar::register_source();
		$this->assertNull( get_block_bindings_source( Identity::binding_source_name() ) );

		Block_Manager::reset();
		CassetteCMFBlocks::boot(
			[
				'prefix'          => 'test',
				'bindings_source' => false,
			]
		);
		Binding_Source_Registrar::register_source();
		$this->assertNull( get_block_bindings_source( Identity::binding_source_name() ) );

		Block_Manager::reset();
		CassetteCMFBlocks::register_from_array( [ 'blocks' => [] ] );
		Binding_Source_Registrar::register_source();
		$this->assertNull( get_block_bindings_source( Identity::binding_source_name() ) );
	}

	/**
	 * With bindings_source => true the source is registered with the declared
	 * "uses_context" and a callback that is Settings_Binding_Source::get_value()
	 * (so it refuses an unmarked field here).
	 */
	public function test_registers_the_source_when_enabled(): void {
		CassetteCMFBlocks::boot(
			[
				'prefix'          => 'test',
				'bindings_source' => true,
			]
		);
		Binding_Source_Registrar::register_source();

		$source = get_block_bindings_source( Identity::binding_source_name() );
		$this->assertNotNull( $source );
		$this->assertSame( [ 'postId', 'postType' ], $source->uses_context );

		register_block_type( 'acme-test/binding-registrar-block', [ 'uses_context' => [ 'postId' ] ] );
		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		update_post_meta( $post_id, 'agency_phone', '555-0100' );
		$block = new WP_Block(
			[
				'blockName' => 'acme-test/binding-registrar-block',
				'attrs'     => [],
			],
			[ 'postId' => $post_id ]
		);

		// The field is not registered with cassette-cmf as `bindings: true`.
		$this->assertNull( $source->get_value( [ 'field' => 'agency_phone' ], $block, 'content' ) );
	}

	/**
	 * The source name is "{prefix}/field", derived in one place.
	 */
	public function test_source_name(): void {
		CassetteCMFBlocks::boot( [ 'prefix' => 'acme' ] );
		$this->assertSame( 'acme/field', Identity::binding_source_name() );
	}
}
