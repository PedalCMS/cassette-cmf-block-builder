<?php
/**
 * Attribute_Schema_Mapper test.
 *
 * @package PedalCMS\CassetteCMFBlocks\Tests\Unit
 */

use PedalCMS\CassetteCMFBlocks\Core\Field_Collection;
use PedalCMS\CassetteCMFBlocks\Schema\Attribute_Schema_Mapper;
use PedalCMS\CassetteCMFBlocks\Schema\Control_Catalog;

require_once __DIR__ . '/CassetteCMFBlocks_UnitTestCase.php';

/**
 * Class Test_Attribute_Schema_Mapper
 */
class Test_Attribute_Schema_Mapper extends CassetteCMFBlocks_UnitTestCase {

	/**
	 * Reset the catalog before each test.
	 */
	public function set_up(): void {
		parent::set_up();
		Control_Catalog::reset();
	}

	/**
	 * Table-driven: one case per control type family, asserting the inferred
	 * WP attribute "type" and zero-value "default".
	 *
	 * @dataProvider provide_control_type_mappings
	 *
	 * @param string $control_type Control type to test.
	 * @param string $expected_wp_type Expected WP attribute schema "type".
	 * @param mixed  $expected_default Expected default value when none is declared.
	 */
	public function test_control_type_maps_to_expected_wp_type( string $control_type, string $expected_wp_type, $expected_default ): void {
		$collection = new Field_Collection(
			[
				[
					'name' => 'the_field',
					'type' => $control_type,
				],
			]
		);
		$attributes = Attribute_Schema_Mapper::from_fields( $collection );

		$this->assertArrayHasKey( 'the_field', $attributes );
		$this->assertSame( $expected_wp_type, $attributes['the_field']['type'] );
		$this->assertSame( $expected_default, $attributes['the_field']['default'] );
	}

	/**
	 * Data provider for test_control_type_maps_to_expected_wp_type().
	 *
	 * @return array<string, array{0: string, 1: string, 2: mixed}>
	 */
	public function provide_control_type_mappings(): array {
		return [
			'text'     => [ 'text', 'string', '' ],
			'toggle'   => [ 'toggle', 'boolean', false ],
			'number'   => [ 'number', 'number', 0 ],
			'media'    => [ 'media', 'integer', 0 ],
			'repeater' => [ 'repeater', 'array', [] ],
			'link'     => [ 'link', 'object', [] ],
		];
	}

	/**
	 * An explicit "default" on the field config takes precedence over the
	 * type's zero-value default.
	 */
	public function test_explicit_default_is_used(): void {
		$collection = new Field_Collection(
			[
				[
					'name'    => 'count',
					'type'    => 'number',
					'default' => 5,
				],
			]
		);
		$attributes = Attribute_Schema_Mapper::from_fields( $collection );

		$this->assertSame( 5, $attributes['count']['default'] );
	}

	/**
	 * A field with "options" should produce an "enum" from the option keys —
	 * the parent's convention of options as a value => label map.
	 */
	public function test_options_produce_enum(): void {
		$collection = new Field_Collection(
			[
				[
					'name'    => 'tone',
					'type'    => 'select',
					'options' => [
						'neutral' => 'Neutral',
						'warm'    => 'Warm',
					],
				],
			]
		);
		$attributes = Attribute_Schema_Mapper::from_fields( $collection );

		$this->assertSame( [ 'neutral', 'warm' ], $attributes['tone']['enum'] );
	}

	/**
	 * "value_type" on the field config overrides the control's default type
	 * inference.
	 */
	public function test_explicit_value_type_overrides_inference(): void {
		$collection = new Field_Collection(
			[
				[
					'name'       => 'ids',
					'type'       => 'text',
					'value_type' => 'array',
				],
			]
		);
		$attributes = Attribute_Schema_Mapper::from_fields( $collection );

		$this->assertSame( 'array', $attributes['ids']['type'] );
	}

	/**
	 * A field with source="meta" must not become a block attribute — it is
	 * handled by the meta-binding milestone instead.
	 */
	public function test_meta_sourced_field_is_excluded(): void {
		$collection = new Field_Collection(
			[
				[
					'name'   => 'headline',
					'type'   => 'text',
					'source' => 'meta',
				],
			]
		);
		$attributes = Attribute_Schema_Mapper::from_fields( $collection );

		$this->assertArrayNotHasKey( 'headline', $attributes );
	}

	/**
	 * A repeater's own sub-fields must NOT become independent top-level
	 * block attributes — their values live inside one row object within the
	 * repeater's own array attribute. Regression test: an earlier version of
	 * Field_Collection::get_leaves() collected every value-bearing leaf
	 * unconditionally, including ones nested inside a repeater, which would
	 * have mapped "title"/"url" here as if they were the block's own
	 * top-level attributes — duplicating the row data as disconnected
	 * attributes and leaking a row field's name into the block's flat
	 * attribute namespace.
	 */
	public function test_repeater_sub_fields_are_excluded_from_the_attribute_schema(): void {
		$collection = new Field_Collection(
			[
				[
					'name'   => 'items',
					'type'   => 'repeater',
					'fields' => [
						[
							'name' => 'title',
							'type' => 'text',
						],
						[
							'name' => 'url',
							'type' => 'url',
						],
					],
				],
			]
		);
		$attributes = Attribute_Schema_Mapper::from_fields( $collection );

		$this->assertArrayHasKey( 'items', $attributes );
		$this->assertSame( 'array', $attributes['items']['type'] );
		$this->assertArrayNotHasKey( 'title', $attributes );
		$this->assertArrayNotHasKey( 'url', $attributes );
		$this->assertCount( 1, $attributes );
	}

	/**
	 * build() should merge field-derived attributes with the top-level
	 * "attributes" passthrough for pure-data attributes with no control.
	 */
	public function test_build_merges_extra_attributes(): void {
		$collection = new Field_Collection(
			[
				[
					'name' => 'label',
					'type' => 'text',
				],
			]
		);
		$attributes = Attribute_Schema_Mapper::build(
			$collection,
			[
				'internal_id' => [
					'type'    => 'string',
					'default' => '',
				],
			]
		);

		$this->assertArrayHasKey( 'label', $attributes );
		$this->assertArrayHasKey( 'internal_id', $attributes );
	}

	/**
	 * A name collision between a field and the "attributes" passthrough must
	 * be rejected rather than silently letting one clobber the other.
	 */
	public function test_build_rejects_name_collision(): void {
		$this->expectException( InvalidArgumentException::class );

		$collection = new Field_Collection(
			[
				[
					'name' => 'label',
					'type' => 'text',
				],
			]
		);
		Attribute_Schema_Mapper::build(
			$collection,
			[
				'label' => [
					'type'    => 'string',
					'default' => '',
				],
			]
		);
	}
}
