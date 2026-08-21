<?php
/**
 * Control_Catalog test.
 *
 * @package Pedalcms\CassetteCmfBlocks\Tests\Unit
 */

use Pedalcms\CassetteCmfBlocks\Schema\Control_Catalog;

require_once __DIR__ . '/CassetteCmfBlocks_UnitTestCase.php';

/**
 * Class Test_Control_Catalog
 */
class Test_Control_Catalog extends CassetteCmfBlocks_UnitTestCase {

	/**
	 * Reset the catalog before each test.
	 */
	public function set_up(): void {
		parent::set_up();
		Control_Catalog::reset();
	}

	/**
	 * Reset the catalog after each test so later test files don't inherit filters.
	 */
	public function tear_down(): void {
		Control_Catalog::reset();
		remove_all_filters( 'cassette_cmf_blocks_control_catalog' );
		parent::tear_down();
	}

	/**
	 * Every built-in control type should be registered with a complete spec.
	 */
	public function test_defaults_are_registered(): void {
		$types = Control_Catalog::get_registered_types();

		$this->assertArrayHasKey( 'text', $types );
		$this->assertArrayHasKey( 'panel', $types );
		$this->assertArrayHasKey( 'repeater', $types );

		foreach ( $types as $type => $spec ) {
			foreach ( [ 'value_type', 'cmf_type', 'is_container', 'holds_value' ] as $key ) {
				$this->assertArrayHasKey( $key, $spec, "Type \"{$type}\" is missing \"{$key}\"." );
			}
		}
	}

	/**
	 * "password" must never appear in the catalog — it is rejected by
	 * Control_Mapper, not merely absent by oversight.
	 */
	public function test_password_is_not_registered(): void {
		$this->assertFalse( Control_Catalog::has_type( 'password' ) );
	}

	/**
	 * Containers must be flagged correctly; repeater is the one container
	 * that also holds its own value.
	 */
	public function test_container_flags(): void {
		$panel = Control_Catalog::get_type( 'panel' );
		$this->assertTrue( $panel['is_container'] );
		$this->assertFalse( $panel['holds_value'] );

		$repeater = Control_Catalog::get_type( 'repeater' );
		$this->assertTrue( $repeater['is_container'] );
		$this->assertTrue( $repeater['holds_value'] );

		$text = Control_Catalog::get_type( 'text' );
		$this->assertFalse( $text['is_container'] );
		$this->assertTrue( $text['holds_value'] );

		// "tab" is a container in its own right (holds a tab's fields), not
		// a value control, and not the same registration as "tab_panel".
		$tab = Control_Catalog::get_type( 'tab' );
		$this->assertTrue( $tab['is_container'] );
		$this->assertFalse( $tab['holds_value'] );
	}

	/**
	 * register_type() should reject a spec missing a required key.
	 */
	public function test_register_type_requires_all_keys(): void {
		$this->expectException( InvalidArgumentException::class );

		Control_Catalog::register_type( 'incomplete', [ 'value_type' => 'string' ] );
	}

	/**
	 * A custom type registered before defaults are lazily loaded must survive
	 * register_defaults() being called later — mirrors the parent
	 * Field_Factory::register_defaults() merge-back behaviour.
	 */
	public function test_custom_type_registered_before_defaults_survives(): void {
		Control_Catalog::register_type(
			'slider',
			[
				'value_type'   => 'number',
				'cmf_type'     => 'number',
				'is_container' => false,
				'holds_value'  => true,
			]
		);

		// Force defaults to register now.
		Control_Catalog::get_registered_types();

		$this->assertTrue( Control_Catalog::has_type( 'slider' ) );
	}

	/**
	 * The cassette_cmf_blocks_control_catalog filter should be able to
	 * mutate the whole catalog — the extensibility hook the parent's
	 * Field_Factory lacks.
	 */
	public function test_control_catalog_filter_can_add_a_type(): void {
		add_filter(
			'cassette_cmf_blocks_control_catalog',
			function ( $types ) {
				$types['custom_widget'] = [
					'value_type'   => 'object',
					'cmf_type'     => null,
					'is_container' => false,
					'holds_value'  => true,
				];
				return $types;
			}
		);

		$this->assertTrue( Control_Catalog::has_type( 'custom_widget' ) );
	}

	/**
	 * unregister_type() should remove a type.
	 */
	public function test_unregister_type_removes_it(): void {
		Control_Catalog::get_registered_types();
		$this->assertTrue( Control_Catalog::has_type( 'text' ) );

		Control_Catalog::unregister_type( 'text' );

		$this->assertFalse( Control_Catalog::has_type( 'text' ) );
	}
}
