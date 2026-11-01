<?php
/**
 * ancestor_field control and cross-block `conditions` rules, server side.
 *
 * @package PedalCMS\CassetteCMFBlocks\Tests\Unit
 */

use PedalCMS\CassetteCMFBlocks\CassetteCMFBlocks;
use PedalCMS\CassetteCMFBlocks\Core\Block_Manager;
use PedalCMS\CassetteCMFBlocks\Core\Editor_Payload;
use PedalCMS\CassetteCMFBlocks\Render\Conditional_Evaluator;
use PedalCMS\CassetteCMFBlocks\Schema\Ancestor_Options;
use PedalCMS\CassetteCMFBlocks\Schema\Control_Catalog;

require_once __DIR__ . '/CassetteCMFBlocks_UnitTestCase.php';

/**
 * Class Test_Ancestor_Field
 */
class Test_Ancestor_Field extends CassetteCMFBlocks_UnitTestCase {

	/**
	 * Reset library state.
	 */
	public function set_up(): void {
		parent::set_up();
		Control_Catalog::reset();
		Block_Manager::reset();
	}

	/**
	 * Unregister the test block and reset state.
	 */
	public function tear_down(): void {
		if ( WP_Block_Type_Registry::get_instance()->is_registered( 'acme-test/anc' ) ) {
			unregister_block_type( 'acme-test/anc' );
		}
		Block_Manager::reset();
		Control_Catalog::reset();
		parent::tear_down();
	}

	/**
	 * Defaults are filled in.
	 */
	public function test_options_defaults(): void {
		$this->assertSame(
			[
				'ancestor'        => 'acme/form',
				'blocks'          => [],
				'has_attribute'   => '',
				'name_attribute'  => 'name',
				'label_attribute' => 'label',
				'exclude_self'    => true,
			],
			Ancestor_Options::normalize( [ 'ancestor' => 'acme/form' ], 'ref', 'ancestor_field' )
		);
	}

	/**
	 * Malformed options are rejected.
	 *
	 * @dataProvider bad_options
	 *
	 * @param mixed $options Options.
	 */
	public function test_bad_options_throw( $options ): void {
		$this->expectException( InvalidArgumentException::class );
		Ancestor_Options::normalize( $options, 'ref', 'ancestor_field' );
	}

	/**
	 * Bad option sets.
	 *
	 * @return array<string, array<int, mixed>>
	 */
	public function bad_options(): array {
		return [
			'not an array'      => [ 'acme/form' ],
			'missing ancestor'  => [ [ 'blocks' => [] ] ],
			'bad ancestor'      => [ [ 'ancestor' => 'Form' ] ],
			'bad blocks'        => [
				[
					'ancestor' => 'acme/form',
					'blocks'   => 'x',
				],
			],
			'bad name_attr'     => [
				[
					'ancestor'       => 'acme/form',
					'name_attribute' => '',
				],
			],
			'bad has_attribute' => [
				[
					'ancestor'      => 'acme/form',
					'has_attribute' => [],
				],
			],
		];
	}

	/**
	 * Register a block with an ancestor_field and a conditions control.
	 *
	 * @param array<string, mixed> $ref_field   Overrides for the ancestor_field.
	 * @param array<string, mixed> $rules_field Overrides for the conditions field.
	 */
	private function register( array $ref_field = [], array $rules_field = [] ): void {
		CassetteCMFBlocks::register_from_array(
			[
				'blocks' => [
					[
						'id'     => 'acme-test/anc',
						'fields' => [
							array_merge(
								[
									'name'     => 'source',
									'type'     => 'ancestor_field',
									'ancestor' => 'acme/form',
									'blocks'   => [ 'acme/field' ],
								],
								$ref_field
							),
							array_merge(
								[
									'name' => 'rules',
									'type' => 'conditions',
								],
								$rules_field
							),
						],
						'render' => [ 'callback' => '__return_empty_string' ],
					],
				],
			]
		);
	}

	/**
	 * The attribute is a string, and the value is sanitized as plain text.
	 */
	public function test_schema_is_string_and_sanitized(): void {
		$this->register();

		$definition = CassetteCMFBlocks::get_definition( 'acme-test/anc' );
		$this->assertSame( 'string', $definition->attribute_schema()['source']['type'] );
		$this->assertSame( 'email', $definition->sanitize_attributes( [ 'source' => "  <b>email</b>\n" ] )['source'] );
	}

	/**
	 * The editor payload carries the canonical options on the field.
	 */
	public function test_payload_carries_canonical_options(): void {
		$this->register(
			[],
			[ 'conditions' => [ 'ancestor' => 'acme/form' ] ]
		);

		$tree = Editor_Payload::build( Block_Manager::init() )['blocks']['acme-test/anc']['fields'];

		$this->assertSame( 'acme/form', $tree[0]['config']['ancestor'] );
		$this->assertSame( [ 'acme/field' ], $tree[0]['config']['blocks'] );
		$this->assertTrue( $tree[0]['config']['exclude_self'] );
		$this->assertSame( 'name', $tree[1]['config']['conditions']['name_attribute'] );
	}

	/**
	 * A missing ancestor makes the block fail to compile (registration error, block skipped).
	 */
	public function test_missing_ancestor_is_a_registration_error(): void {
		// The _doing_it_wrong() from Block_Manager::register_blocks() is ignored by the base test case.
		$this->register( [ 'ancestor' => '' ] );

		$this->assertFalse( WP_Block_Type_Registry::get_instance()->is_registered( 'acme-test/anc' ) );
		$this->assertNull( CassetteCMFBlocks::get_definition( 'acme-test/anc' ) );
	}

	/**
	 * Cross-block rules are recognised in both shapes the evaluator accepts.
	 */
	public function test_cross_block_rule_detection(): void {
		$this->assertTrue(
			Conditional_Evaluator::is_cross_block_rule(
				[
					'source'   => 'block',
					'ref'      => 'email',
					'operator' => '==',
				]
			)
		);
		$this->assertFalse(
			Conditional_Evaluator::is_cross_block_rule(
				[
					'field'    => 'tone',
					'operator' => '==',
				]
			)
		);
	}
}
