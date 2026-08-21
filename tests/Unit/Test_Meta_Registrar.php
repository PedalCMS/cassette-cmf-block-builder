<?php
/**
 * Meta_Registrar test.
 *
 * @package Pedalcms\CassetteCmfBlocks\Tests\Unit
 */

use Pedalcms\CassetteCmfBlocks\Binding\Meta_Registrar;
use Pedalcms\CassetteCmfBlocks\CassetteCmfBlocks;
use Pedalcms\CassetteCmfBlocks\Core\Block_Manager;
use Pedalcms\CassetteCmfBlocks\Schema\Control_Catalog;

require_once __DIR__ . '/CassetteCmfBlocks_UnitTestCase.php';

/**
 * Class Test_Meta_Registrar
 */
class Test_Meta_Registrar extends CassetteCmfBlocks_UnitTestCase {

	/**
	 * Reset library singletons before each test.
	 */
	public function set_up(): void {
		parent::set_up();
		Block_Manager::reset();
		Control_Catalog::reset();
	}

	/**
	 * Unregister test meta keys and reset singletons after each test.
	 */
	public function tear_down(): void {
		unregister_meta_key( 'post', 'acme_test_meta_key' );
		unregister_meta_key( 'post', 'reason' );
		unregister_meta_key( 'term', 'category_color' );
		Block_Manager::reset();
		Control_Catalog::reset();
		parent::tear_down();
	}

	/**
	 * A "meta"-sourced field is registered with a real show_in_rest schema.
	 */
	public function test_registers_a_meta_sourced_field_with_a_rest_schema(): void {
		CassetteCmfBlocks::register_from_array(
			[
				'blocks' => [
					[
						'id'     => 'acme-test/meta-registrar',
						'fields' => [
							[
								'name'   => 'reason',
								'type'   => 'text',
								'source' => 'meta',
							],
						],
					],
				],
			]
		);

		Meta_Registrar::register_meta_fields();

		$this->assertTrue( registered_meta_key_exists( 'post', 'reason' ) );

		$registered = get_registered_meta_keys( 'post' );
		$this->assertSame( 'string', $registered['reason']['type'] );
		$this->assertIsArray( $registered['reason']['show_in_rest'] );
		$this->assertSame( 'string', $registered['reason']['show_in_rest']['schema']['type'] );
	}

	/**
	 * "meta.key" overrides the meta key name used, distinct from the
	 * field's own "name" (the block attribute-namespace identity — meta
	 * fields don't become attributes at all, so there's no collision risk
	 * to worry about there, but the DB key is still worth being able to
	 * name independently, e.g. to match an existing meta key).
	 */
	public function test_meta_key_override(): void {
		CassetteCmfBlocks::register_from_array(
			[
				'blocks' => [
					[
						'id'     => 'acme-test/meta-registrar-key',
						'fields' => [
							[
								'name'   => 'internal_field_name',
								'type'   => 'text',
								'source' => 'meta',
								'meta'   => [ 'key' => 'acme_test_meta_key' ],
							],
						],
					],
				],
			]
		);

		Meta_Registrar::register_meta_fields();

		$this->assertTrue( registered_meta_key_exists( 'post', 'acme_test_meta_key' ) );
		$this->assertFalse( registered_meta_key_exists( 'post', 'internal_field_name' ) );
	}

	/**
	 * An attribute-sourced field (the default) is never registered as meta.
	 */
	public function test_attribute_sourced_fields_are_not_registered(): void {
		CassetteCmfBlocks::register_from_array(
			[
				'blocks' => [
					[
						'id'     => 'acme-test/meta-registrar-attr',
						'fields' => [
							[
								'name' => 'heading',
								'type' => 'text',
							],
						],
					],
				],
			]
		);

		Meta_Registrar::register_meta_fields();

		$this->assertFalse( registered_meta_key_exists( 'post', 'heading' ) );
	}

	/**
	 * "meta.object_type" registers against a different object type (e.g. "term").
	 */
	public function test_registers_against_a_declared_object_type(): void {
		CassetteCmfBlocks::register_from_array(
			[
				'blocks' => [
					[
						'id'     => 'acme-test/meta-registrar-term',
						'fields' => [
							[
								'name'   => 'category_color',
								'type'   => 'color',
								'source' => 'meta',
								'meta'   => [ 'object_type' => 'term' ],
							],
						],
					],
				],
			]
		);

		Meta_Registrar::register_meta_fields();

		$this->assertTrue( registered_meta_key_exists( 'term', 'category_color' ) );
		$this->assertFalse( registered_meta_key_exists( 'post', 'category_color' ) );
	}

	/**
	 * An empty "meta.key" (as opposed to it being unset) falls back to the
	 * field's own "name", the same as leaving "meta.key" out entirely.
	 */
	public function test_empty_meta_key_falls_back_to_the_field_name(): void {
		CassetteCmfBlocks::register_from_array(
			[
				'blocks' => [
					[
						'id'     => 'acme-test/meta-registrar-empty-key',
						'fields' => [
							[
								'name'   => 'reason',
								'type'   => 'text',
								'source' => 'meta',
								'meta'   => [ 'key' => '' ],
							],
						],
					],
				],
			]
		);

		// Should not throw.
		Meta_Registrar::register_meta_fields();

		$this->assertTrue( registered_meta_key_exists( 'post', 'reason' ) );
	}

	/**
	 * A "meta"-sourced field nested inside a repeater must NOT be
	 * registered via register_meta() — its value lives inside one row of
	 * the repeater's own array attribute, not at a fixed post meta key.
	 * Regression test, same bug class as
	 * Test_Attribute_Schema_Mapper::test_repeater_sub_fields_are_excluded_from_the_attribute_schema().
	 */
	public function test_meta_sourced_repeater_sub_fields_are_not_registered(): void {
		CassetteCmfBlocks::register_from_array(
			[
				'blocks' => [
					[
						'id'     => 'acme-test/meta-registrar-repeater',
						'fields' => [
							[
								'name'   => 'items',
								'type'   => 'repeater',
								'fields' => [
									[
										'name'   => 'note',
										'type'   => 'text',
										'source' => 'meta',
									],
								],
							],
						],
					],
				],
			]
		);

		Meta_Registrar::register_meta_fields();

		$this->assertFalse( registered_meta_key_exists( 'post', 'note' ) );
	}
}
