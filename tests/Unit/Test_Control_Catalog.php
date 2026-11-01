<?php
/**
 * Control_Catalog test.
 *
 * @package PedalCMS\CassetteCMFBlocks\Tests\Unit
 */

use PedalCMS\CassetteCMFBlocks\Schema\Control_Catalog;

require_once __DIR__ . '/CassetteCMFBlocks_UnitTestCase.php';

/**
 * Class Test_Control_Catalog
 */
class Test_Control_Catalog extends CassetteCMFBlocks_UnitTestCase {

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
	 * register_type() requires "value_type" and nothing else.
	 */
	public function test_register_type_requires_value_type(): void {
		$this->expectException( InvalidArgumentException::class );

		Control_Catalog::register_type( 'incomplete', [ 'cmf_type' => 'text' ] );
	}

	/**
	 * A minimal spec is completed with the documented defaults.
	 */
	public function test_register_type_completes_a_minimal_spec(): void {
		Control_Catalog::register_type( 'stars', [ 'value_type' => 'integer' ] );
		Control_Catalog::register_type( 'divider_line', [ 'value_type' => null ] );

		$this->assertSame(
			[
				'value_type'   => 'integer',
				'cmf_type'     => null,
				'is_container' => false,
				'holds_value'  => true,
			],
			Control_Catalog::get_type( 'stars' )
		);
		$this->assertFalse( Control_Catalog::get_type( 'divider_line' )['holds_value'] );
	}

	/**
	 * Malformed ids and value types are rejected.
	 *
	 * @dataProvider provide_invalid_registrations
	 *
	 * @param string               $type Type id.
	 * @param array<string, mixed> $spec Spec.
	 */
	public function test_register_type_rejects_malformed_input( string $type, array $spec ): void {
		$this->expectException( InvalidArgumentException::class );

		Control_Catalog::register_type( $type, $spec );
	}

	/**
	 * Invalid registrations.
	 *
	 * @return array<string, array{0: string, 1: array<string, mixed>}>
	 */
	public function provide_invalid_registrations(): array {
		return [
			'uppercase id'      => [ 'Stars', [ 'value_type' => 'string' ] ],
			'prototype key'     => [ '__proto__', [ 'value_type' => 'string' ] ],
			'dash in id'        => [ 'star-rating', [ 'value_type' => 'string' ] ],
			'empty id'          => [ '', [ 'value_type' => 'string' ] ],
			'unknown json type' => [ 'stars', [ 'value_type' => 'float' ] ],
			'bad cmf_type'      => [
				'stars',
				[
					'value_type' => 'string',
					'cmf_type'   => [ 'text' ],
				],
			],
		];
	}

	/**
	 * A catalog filter that returns a malformed entry loses that entry only.
	 */
	public function test_catalog_filter_drops_malformed_entries(): void {
		$this->setExpectedIncorrectUsage( 'cassette_cmf_blocks_control_catalog' );

		add_filter(
			'cassette_cmf_blocks_control_catalog',
			static function ( $types ) {
				$types['Bad Type']  = [ 'value_type' => 'string' ];
				$types['good_type'] = [ 'value_type' => 'string' ];
				return $types;
			}
		);

		$this->assertTrue( Control_Catalog::has_type( 'good_type' ) );
		$this->assertFalse( Control_Catalog::has_type( 'Bad Type' ) );
		$this->assertTrue( Control_Catalog::has_type( 'text' ) );
	}

	/**
	 * The stable extension surface is tagged @api.
	 */
	public function test_register_type_is_tagged_api(): void {
		foreach ( [ 'register_type', 'get_type', 'has_type', 'get_registered_types' ] as $method ) {
			$doc = ( new \ReflectionMethod( Control_Catalog::class, $method ) )->getDocComment();
			$this->assertStringContainsString( '@api', (string) $doc, $method );
		}
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
