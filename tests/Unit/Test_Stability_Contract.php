<?php
/**
 * The frozen contract in docs/STABILITY.md, as executable cases: the
 * attribute-schema mapping and the sanitizer output per control type, for the
 * render pipeline's sanitize step. A change that breaks one of these needs a
 * major version and a migration note.
 *
 * @package PedalCMS\CassetteCMFBlocks\Tests\Unit
 */

use PedalCMS\CassetteCMFBlocks\CassetteCMFBlocks;
use PedalCMS\CassetteCMFBlocks\Core\Block_Manager;
use PedalCMS\CassetteCMFBlocks\Schema\Control_Catalog;

require_once __DIR__ . '/CassetteCMFBlocks_UnitTestCase.php';

/**
 * Class Test_Stability_Contract
 */
class Test_Stability_Contract extends CassetteCMFBlocks_UnitTestCase {

	/**
	 * Reset library state.
	 */
	public function set_up(): void {
		parent::set_up();
		Control_Catalog::reset();
		Block_Manager::reset();
	}

	/**
	 * Reset library state.
	 */
	public function tear_down(): void {
		Block_Manager::reset();
		Control_Catalog::reset();
		parent::tear_down();
	}

	/**
	 * Register a one-field block and return its definition.
	 *
	 * @param array<string, mixed> $field Field config (name is "f").
	 * @return \PedalCMS\CassetteCMFBlocks\Core\Block_Definition
	 */
	private function definition_for( array $field ) {
		$id = 'acme-stability/' . md5( (string) wp_json_encode( $field ) );

		CassetteCMFBlocks::register_from_array(
			[
				'blocks' => [
					[
						'id'     => $id,
						'args'   => [ 'title' => 'Stability' ],
						'fields' => [ array_merge( [ 'name' => 'f' ], $field ) ],
					],
				],
			]
		);

		$definition = CassetteCMFBlocks::get_definition( $id );
		$this->assertNotNull( $definition );

		return $definition;
	}

	/**
	 * The attribute schema each control type produces.
	 *
	 * @dataProvider provide_schemas
	 *
	 * @param array<string, mixed> $field    Field config.
	 * @param array<string, mixed> $expected Expected attribute schema.
	 */
	public function test_attribute_schema_mapping( array $field, array $expected ): void {
		$this->assertSame( $expected, $this->definition_for( $field )->attribute_schema()['f'] );
	}

	/**
	 * Schema cases.
	 *
	 * @return array<string, array{0: array<string, mixed>, 1: array<string, mixed>}>
	 */
	public function provide_schemas(): array {
		$options = [
			'a' => 'A',
			'b' => 'B',
		];

		return [
			'string default'         => [
				[ 'type' => 'text' ],
				[
					'type'    => 'string',
					'default' => '',
				],
			],
			'explicit default'       => [
				[
					'type'    => 'text',
					'default' => 'x',
				],
				[
					'type'    => 'string',
					'default' => 'x',
				],
			],
			'number'                 => [
				[ 'type' => 'number' ],
				[
					'type'    => 'number',
					'default' => 0,
				],
			],
			'integer (media)'        => [
				[ 'type' => 'media' ],
				[
					'type'    => 'integer',
					'default' => 0,
				],
			],
			'boolean'                => [
				[ 'type' => 'toggle' ],
				[
					'type'    => 'boolean',
					'default' => false,
				],
			],
			'array'                  => [
				[ 'type' => 'media_gallery' ],
				[
					'type'    => 'array',
					'default' => [],
				],
			],
			'object'                 => [
				[ 'type' => 'link' ],
				[
					'type'    => 'object',
					'default' => [],
				],
			],
			'uuid'                   => [
				[ 'type' => 'uuid' ],
				[
					'type'    => 'string',
					'default' => '',
				],
			],
			'enum from options'      => [
				[
					'type'    => 'select',
					'options' => $options,
				],
				[
					'type'    => 'string',
					'default' => '',
					'enum'    => [ 'a', 'b' ],
				],
			],
			'enum from list options' => [
				[
					'type'    => 'select',
					'options' => [
						[
							'value' => 'a',
							'label' => 'A',
						],
						[
							'value' => 'b',
							'label' => 'B',
						],
					],
				],
				[
					'type'    => 'string',
					'default' => '',
					'enum'    => [ 'a', 'b' ],
				],
			],
			'items.enum for arrays'  => [
				[
					'type'    => 'checkbox_group',
					'options' => $options,
				],
				[
					'type'    => 'array',
					'default' => [],
					'items'   => [
						'type' => 'string',
						'enum' => [ 'a', 'b' ],
					],
				],
			],
			'explicit enum'          => [
				[
					'type' => 'text',
					'enum' => [ 'x', 'y' ],
				],
				[
					'type'    => 'string',
					'default' => '',
					'enum'    => [ 'x', 'y' ],
				],
			],
			'value_type override'    => [
				[
					'type'       => 'text',
					'value_type' => 'number',
				],
				[
					'type'    => 'number',
					'default' => 0,
				],
			],
		];
	}

	/**
	 * The sanitizer output per control type, for good and bad input.
	 *
	 * @dataProvider provide_sanitizer_outputs
	 *
	 * @param array<string, mixed> $field    Field config.
	 * @param mixed                $input    Raw attribute value.
	 * @param mixed                $expected Expected value after sanitize_attributes().
	 */
	public function test_sanitizer_output( array $field, $input, $expected ): void {
		$result = $this->definition_for( $field )->sanitize_attributes( [ 'f' => $input ] );

		$this->assertSame( $expected, $result['f'] );
	}

	/**
	 * Sanitizer cases (type, input, output).
	 *
	 * @return array<string, array{0: array<string, mixed>, 1: mixed, 2: mixed}>
	 */
	public function provide_sanitizer_outputs(): array {
		$options = [
			'a' => 'A',
			'b' => 'B',
		];

		return [
			'text strips tags'        => [ [ 'type' => 'text' ], " <b>x</b> y\n", 'x y' ],
			'text wrong type'         => [ [ 'type' => 'text' ], [ 1 ], '' ],
			'textarea keeps newline'  => [ [ 'type' => 'textarea' ], "<b>x</b>\n y", "x\n y" ],
			'select in options'       => [
				[
					'type'    => 'select',
					'options' => $options,
				],
				'a',
				'a',
			],
			'select not in options'   => [
				[
					'type'    => 'select',
					'options' => $options,
				],
				'zz',
				'',
			],
			'radio not in options'    => [
				[
					'type'    => 'radio',
					'options' => $options,
				],
				'zz',
				'',
			],
			'toggle_group in options' => [
				[
					'type'    => 'toggle_group',
					'options' => $options,
				],
				'b',
				'b',
			],
			'toggle true'             => [ [ 'type' => 'toggle' ], true, true ],
			'toggle false'            => [ [ 'type' => 'toggle' ], false, false ],
			'toggle junk'             => [ [ 'type' => 'toggle' ], 'yes', false ],
			'toolbar_toggle true'     => [ [ 'type' => 'toolbar_toggle' ], true, true ],
			'checkbox_group valid'    => [
				[
					'type'    => 'checkbox_group',
					'options' => $options,
				],
				[ 'a', 'b' ],
				[ 'a', 'b' ],
			],
			'checkbox_group invalid'  => [
				[
					'type'    => 'checkbox_group',
					'options' => $options,
				],
				[ 'a', 'zz' ],
				[],
			],
			'number int'              => [ [ 'type' => 'number' ], 5, 5 ],
			'number float string'     => [ [ 'type' => 'number' ], '5.5', 5.5 ],
			'number junk'             => [ [ 'type' => 'number' ], 'abc', 0 ],
			'email trims'             => [ [ 'type' => 'email' ], ' A@B.com ', 'A@B.com' ],
			'email invalid'           => [ [ 'type' => 'email' ], 'nope', '' ],
			'url encodes'             => [ [ 'type' => 'url' ], 'http://x.com/a b', 'http://x.com/a%20b' ],
			'url bad protocol'        => [ [ 'type' => 'url' ], 'javascript:alert(1)', '' ],
			'date text field'         => [ [ 'type' => 'date' ], '<i>2026-01-02</i>', '2026-01-02' ],
			'color short'             => [ [ 'type' => 'color' ], '#abc', '#abc' ],
			'color adds hash'         => [ [ 'type' => 'color' ], 'ABCDEF', '#ABCDEF' ],
			'color invalid'           => [ [ 'type' => 'color' ], 'red', '#000000' ],
			'media int'               => [ [ 'type' => 'media' ], 7, 7 ],
			'media numeric string'    => [ [ 'type' => 'media' ], '7', 7 ],
			'media junk'              => [ [ 'type' => 'media' ], 'junk', 0 ],
			'media junk with default' => [
				[
					'type'    => 'media',
					'default' => 3,
				],
				'junk',
				3,
			],
			'uuid lowercases'         => [ [ 'type' => 'uuid' ], 'ABCDEF01-2345-4678-9ABC-DEF012345678', 'abcdef01-2345-4678-9abc-def012345678' ],
			'uuid invalid'            => [ [ 'type' => 'uuid' ], 'not-a-uuid', '' ],
			'ancestor_field as text'  => [
				[
					'type'     => 'ancestor_field',
					'ancestor' => 'acme/parent',
				],
				' <b>n</b> ',
				'n',
			],
			'no cmf type: kept as is' => [ [ 'type' => 'code' ], '<b>x</b>', '<b>x</b>' ],
		];
	}

	/**
	 * A repeater's rows are sanitized with the sub-field sanitizers.
	 */
	public function test_repeater_rows_use_sub_field_sanitizers(): void {
		$definition = $this->definition_for(
			[
				'type'   => 'repeater',
				'fields' => [
					[
						'name' => 'label',
						'type' => 'text',
					],
				],
			]
		);

		$result = $definition->sanitize_attributes( [ 'f' => [ [ 'label' => '<b>Hi</b>' ], [ 'label' => 'x' ] ] ] );

		$this->assertSame( [ [ 'label' => 'Hi' ], [ 'label' => 'x' ] ], $result['f'] );
	}
}
