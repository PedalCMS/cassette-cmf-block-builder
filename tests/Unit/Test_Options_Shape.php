<?php
/**
 * "options" accepts a value => label map or a list of { value, label }
 * objects; both reach the attribute schema (enum), the sanitizer and the
 * editor payload as the canonical map.
 *
 * @package PedalCMS\CassetteCMFBlocks\Tests\Unit
 */

use PedalCMS\CassetteCMFBlocks\CassetteCMFBlocks;
use PedalCMS\CassetteCMFBlocks\Core\Block_Definition;
use PedalCMS\CassetteCMFBlocks\Core\Block_Manager;
use PedalCMS\CassetteCMFBlocks\Core\Editor_Payload;
use PedalCMS\CassetteCMFBlocks\Core\Field_Collection;
use PedalCMS\CassetteCMFBlocks\Render\Attribute_Sanitizer;
use PedalCMS\CassetteCMFBlocks\Schema\Control_Catalog;

require_once __DIR__ . '/CassetteCMFBlocks_UnitTestCase.php';

/**
 * Class Test_Options_Shape
 */
class Test_Options_Shape extends CassetteCMFBlocks_UnitTestCase {

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
		if ( WP_Block_Type_Registry::get_instance()->is_registered( 'acme-test/options-payload' ) ) {
			unregister_block_type( 'acme-test/options-payload' );
		}
		Block_Manager::reset();
		Control_Catalog::reset();
		parent::tear_down();
	}

	/**
	 * The same options, in both accepted shapes.
	 *
	 * @return array<string, array<int, array<int|string, mixed>>>
	 */
	public function shapes_provider(): array {
		return [
			'map'  => [
				[
					'info'    => 'Information',
					'warning' => 'Warning',
				],
			],
			'list' => [
				[
					[
						'value' => 'info',
						'label' => 'Information',
					],
					[
						'value' => 'warning',
						'label' => 'Warning',
					],
				],
			],
		];
	}

	/**
	 * Build a field collection with a select over the given options.
	 *
	 * @param mixed  $options Options in either shape.
	 * @param string $type    Control type.
	 * @return Field_Collection
	 */
	private function collection( $options, string $type = 'select' ): Field_Collection {
		return new Field_Collection(
			[
				[
					'name'    => 'note_type',
					'type'    => $type,
					'options' => $options,
				],
			]
		);
	}

	/**
	 * Both shapes produce the same enum of the real values.
	 *
	 * @dataProvider shapes_provider
	 * @param array<int|string, mixed> $options Options.
	 */
	public function test_enum_holds_the_values_for_both_shapes( array $options ): void {
		$definition = new Block_Definition(
			[
				'id'     => 'acme-test/options-enum',
				'fields' => [
					[
						'name'    => 'note_type',
						'type'    => 'select',
						'options' => $options,
					],
				],
			]
		);

		$this->assertSame( [ 'info', 'warning' ], $definition->get_attributes()['note_type']['enum'] );
	}

	/**
	 * The compiled field config is always the canonical map.
	 *
	 * @dataProvider shapes_provider
	 * @param array<int|string, mixed> $options Options.
	 */
	public function test_both_shapes_normalize_to_the_map( array $options ): void {
		$leaf = $this->collection( $options )->get_leaves()[0];

		$this->assertSame(
			[
				'info'    => 'Information',
				'warning' => 'Warning',
			],
			$leaf['config']['options']
		);
	}

	/**
	 * The parent sanitizer accepts a real value for both shapes and rejects an unknown one.
	 *
	 * @dataProvider shapes_provider
	 * @param array<int|string, mixed> $options Options.
	 */
	public function test_sanitizer_accepts_real_values_for_both_shapes( array $options ): void {
		$fields = $this->collection( $options );

		$this->assertSame( 'info', Attribute_Sanitizer::sanitize_all( [ 'note_type' => 'info' ], $fields )['note_type'] );
		$this->assertSame( 'warning', Attribute_Sanitizer::sanitize_all( [ 'note_type' => 'warning' ], $fields )['note_type'] );
		$this->assertSame( '', Attribute_Sanitizer::sanitize_all( [ 'note_type' => 'nope' ], $fields )['note_type'] );
	}

	/**
	 * The editor payload ships the canonical map for both shapes (what the JS controls read).
	 *
	 * @dataProvider shapes_provider
	 * @param array<int|string, mixed> $options Options.
	 */
	public function test_payload_ships_the_map_for_both_shapes( array $options ): void {
		CassetteCMFBlocks::register_from_array(
			[
				'blocks' => [
					[
						'id'     => 'acme-test/options-payload',
						'fields' => [
							[
								'name'    => 'note_type',
								'type'    => 'select',
								'options' => $options,
							],
						],
					],
				],
			]
		);

		$payload = Editor_Payload::build( Block_Manager::init() )['blocks']['acme-test/options-payload'];

		$this->assertSame(
			[
				'info'    => 'Information',
				'warning' => 'Warning',
			],
			$payload['fields'][0]['config']['options']
		);
		$this->assertSame( [ 'info', 'warning' ], $payload['settings']['attributes']['note_type']['enum'] );
	}

	/**
	 * A list item without a label uses its value as the label.
	 */
	public function test_list_item_label_defaults_to_the_value(): void {
		$leaf = $this->collection(
			[
				[ 'value' => 'a' ],
				[
					'value' => 'b',
					'label' => 'Bee',
				],
			]
		)->get_leaves()[0]; // phpcs:ignore Universal.Arrays.MultipleStatementAlignment

		$this->assertSame(
			[
				'a' => 'a',
				'b' => 'Bee',
			],
			$leaf['config']['options']
		);
	}

	/**
	 * Options nested in containers and repeaters are normalized too.
	 */
	public function test_nested_options_are_normalized(): void {
		$fields = new Field_Collection(
			[
				[
					'name'   => 'panel',
					'type'   => 'panel',
					'fields' => [
						[
							'name'    => 'tone',
							'type'    => 'radio',
							'options' => [
								[
									'value' => 'warm',
									'label' => 'Warm',
								],
							],
						],
					],
				],
			]
		);

		$this->assertSame( [ 'warm' => 'Warm' ], $fields->get_leaves()[0]['config']['options'] );
		$this->assertSame( [ 'warm' => 'Warm' ], $fields->to_editor_tree()[0]['fields'][0]['config']['options'] );
	}

	/**
	 * Malformed options raise _doing_it_wrong() and are dropped.
	 *
	 * @dataProvider invalid_options_provider
	 * @param mixed $options Malformed options.
	 */
	public function test_malformed_options_raise_a_notice_and_are_dropped( $options ): void {
		$this->setExpectedIncorrectUsage( Field_Collection::class );

		$leaf = $this->collection( $options )->get_leaves()[0];

		$this->assertArrayNotHasKey( 'options', $leaf['config'] );
	}

	/**
	 * Malformed options.
	 *
	 * @return array<string, array<int, mixed>>
	 */
	public function invalid_options_provider(): array {
		return [
			'a string'                        => [ 'info,warning' ],
			'a number'                        => [ 5 ],
			'a list item without a value'     => [ [ [ 'label' => 'x' ] ] ],
			'a list item with an array value' => [ [ [ 'value' => [ 'a' ] ] ] ],
			'mixed scalars and objects'       => [
				[
					[ 'value' => 'a' ],
					'b',
				],
			],
		];
	}

	/**
	 * A duplicate value in the list form is reported; the first wins.
	 */
	public function test_duplicate_list_values_are_reported(): void {
		$this->setExpectedIncorrectUsage( Field_Collection::class );

		$leaf = $this->collection(
			[
				[
					'value' => 'a',
					'label' => 'First',
				],
				[
					'value' => 'a',
					'label' => 'Second',
				],
			]
		)->get_leaves()[0];

		$this->assertSame( [ 'a' => 'First' ], $leaf['config']['options'] );
	}

	/**
	 * An empty list is an empty map, not an error.
	 */
	public function test_empty_options_are_accepted(): void {
		$leaf = $this->collection( [] )->get_leaves()[0];

		$this->assertSame( [], $leaf['config']['options'] );
	}

	/**
	 * checkbox_group: the allowed values go on "items", not as a top-level
	 * enum (which WordPress would compare against the whole array and reject
	 * every real selection).
	 *
	 * @dataProvider shapes_provider
	 * @param array<int|string, mixed> $options Options.
	 */
	public function test_checkbox_group_enum_goes_on_items( array $options ): void {
		$schema = ( new Block_Definition(
			[
				'id'     => 'acme-test/options-checkbox',
				'fields' => [
					[
						'name'    => 'note_type',
						'type'    => 'checkbox_group',
						'options' => $options,
					],
				],
			]
		) )->get_attributes()['note_type'];

		$this->assertArrayNotHasKey( 'enum', $schema );
		$this->assertSame(
			[
				'type' => 'string',
				'enum' => [ 'info', 'warning' ],
			],
			$schema['items']
		);

		$valid = rest_validate_value_from_schema( [ 'info', 'warning' ], $schema, 'note_type' );
		$this->assertTrue( $valid );
		$this->assertWPError( rest_validate_value_from_schema( [ 'info', 'other' ], $schema, 'note_type' ) );
	}

	/**
	 * The bundled JSON example with a { value, label } list now sanitizes real values.
	 */
	public function test_bundled_json_example_note_type_works(): void {
		$config = json_decode( (string) file_get_contents( dirname( __DIR__, 2 ) . '/examples/02-simple-json/config.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		$note_type = null;
		foreach ( $config['blocks'] as $block ) {
			$definition = new Block_Definition( $block );
			if ( isset( $definition->get_attributes()['note_type'] ) ) {
				$note_type = $definition;
				break;
			}
		}

		$this->assertNotNull( $note_type );
		$this->assertSame( [ 'info', 'warning' ], $note_type->get_attributes()['note_type']['enum'] );
		$this->assertSame(
			'info',
			Attribute_Sanitizer::sanitize_all( [ 'note_type' => 'info' ], $note_type->get_fields() )['note_type']
		);
	}
}
