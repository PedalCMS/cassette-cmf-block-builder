<?php
/**
 * Meta_Schema_Mapper test.
 *
 * @package PedalCMS\CassetteCMFBlocks\Tests\Unit
 */

use PedalCMS\CassetteCMFBlocks\Schema\Meta_Schema_Mapper;

require_once __DIR__ . '/CassetteCMFBlocks_UnitTestCase.php';

/**
 * Class Test_Meta_Schema_Mapper
 */
class Test_Meta_Schema_Mapper extends CassetteCMFBlocks_UnitTestCase {

	/**
	 * A scalar field maps to a plain JSON Schema type/default.
	 */
	public function test_maps_type_and_default(): void {
		$leaf = [
			'config'       => [
				'name'    => 'heading',
				'default' => 'Hi',
			],
			'control_type' => 'text',
			'cmf_type'     => 'text',
		];

		$schema = Meta_Schema_Mapper::to_json_schema( $leaf );

		$this->assertSame( 'string', $schema['type'] );
		$this->assertSame( 'Hi', $schema['default'] );
	}

	/**
	 * "role" (a WP block-attribute concept) is never present — it has no
	 * meta-schema equivalent.
	 */
	public function test_never_includes_role(): void {
		$leaf = [
			'config'       => [
				'name' => 'heading',
				'role' => 'content',
			],
			'control_type' => 'text',
			'cmf_type'     => 'text',
		];

		$this->assertArrayNotHasKey( 'role', Meta_Schema_Mapper::to_json_schema( $leaf ) );
	}

	/**
	 * "options" maps to "enum", the same as Attribute_Schema_Mapper.
	 */
	public function test_maps_options_to_enum(): void {
		$leaf = [
			'config'       => [
				'name'    => 'tone',
				'options' => [
					'warm' => 'Warm',
					'cool' => 'Cool',
				],
			],
			'control_type' => 'select',
			'cmf_type'     => 'select',
		];

		$this->assertSame( [ 'warm', 'cool' ], Meta_Schema_Mapper::to_json_schema( $leaf )['enum'] );
	}

	/**
	 * An "array"-typed field gets a default "items": { type: string }
	 * schema — required by register_meta()'s own validation for
	 * REST-visible array meta, unlike a WP block attribute schema.
	 */
	public function test_array_type_gets_a_default_items_schema(): void {
		$leaf = [
			'config'       => [
				'name'    => 'tags',
				'options' => [
					'a' => 'A',
					'b' => 'B',
				],
			],
			'control_type' => 'checkbox_group',
			'cmf_type'     => 'checkbox',
		];

		$schema = Meta_Schema_Mapper::to_json_schema( $leaf );

		$this->assertSame( 'array', $schema['type'] );
		$this->assertSame( [ 'type' => 'string' ], $schema['items'] );
	}

	/**
	 * An explicit "items" override in the field config is honored instead
	 * of the default.
	 */
	public function test_array_type_honors_an_explicit_items_override(): void {
		$leaf = [
			'config'       => [
				'name'       => 'scores',
				'value_type' => 'array',
				'items'      => [ 'type' => 'number' ],
			],
			'control_type' => 'checkbox_group',
			'cmf_type'     => 'checkbox',
		];

		$this->assertSame( [ 'type' => 'number' ], Meta_Schema_Mapper::to_json_schema( $leaf )['items'] );
	}
}
