<?php
/**
 * "unique" on text-like fields: config validation and what the editor receives.
 *
 * @package PedalCMS\CassetteCMFBlocks\Tests\Unit
 */

use PedalCMS\CassetteCMFBlocks\CassetteCMFBlocks;
use PedalCMS\CassetteCMFBlocks\Core\Block_Manager;
use PedalCMS\CassetteCMFBlocks\Core\Editor_Payload;
use PedalCMS\CassetteCMFBlocks\Core\Field_Collection;
use PedalCMS\CassetteCMFBlocks\Json\Block_Schema_Validator;
use PedalCMS\CassetteCMFBlocks\Schema\Control_Catalog;

require_once __DIR__ . '/CassetteCMFBlocks_UnitTestCase.php';

/**
 * Class Test_Unique_Field
 */
class Test_Unique_Field extends CassetteCMFBlocks_UnitTestCase {

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
		if ( WP_Block_Type_Registry::get_instance()->is_registered( 'acme-test/unique-payload' ) ) {
			unregister_block_type( 'acme-test/unique-payload' );
		}
		Block_Manager::reset();
		Control_Catalog::reset();
		parent::tear_down();
	}

	/**
	 * The settings are normalized into the canonical rule on the compiled field.
	 */
	public function test_rule_is_normalized_on_the_compiled_field(): void {
		$fields = new Field_Collection(
			[
				[
					'name'   => 'a',
					'type'   => 'text',
					'unique' => true,
				],
				[
					'name'   => 'b',
					'type'   => 'text',
					'unique' => [
						'scope' => 'ancestor',
						'block' => 'vendor/form',
						'key'   => 'fieldName',
					],
				],
				[
					'name'   => 'c',
					'type'   => 'email',
					'unique' => false,
				],
				[
					'name'   => 'd',
					'type'   => 'url',
					'unique' => [ 'block' => 'vendor/form' ],
				],
			]
		);

		$leaves = $fields->get_leaves();

		$this->assertSame(
			[
				'scope' => 'post',
				'key'   => 'a',
			],
			$leaves[0]['config']['unique']
		);
		$this->assertSame(
			[
				'scope' => 'ancestor',
				'block' => 'vendor/form',
				'key'   => 'fieldName',
			],
			$leaves[1]['config']['unique']
		);
		$this->assertArrayNotHasKey( 'unique', $leaves[2]['config'] );
		$this->assertSame( 'ancestor', $leaves[3]['config']['unique']['scope'] );
		$this->assertSame( 'd', $leaves[3]['config']['unique']['key'] );
	}

	/**
	 * Malformed settings throw (the manager turns that into _doing_it_wrong and skips the block).
	 */
	public function test_invalid_settings_throw(): void {
		$this->expectException( InvalidArgumentException::class );

		new Field_Collection(
			[
				[
					'name'   => 'a',
					'type'   => 'text',
					'unique' => [ 'scope' => 'ancestor' ],
				],
			]
		);
	}

	/**
	 * Controls other than text/email/url are rejected.
	 */
	public function test_unsupported_control_types_are_rejected(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'only supported on' );

		new Field_Collection(
			[
				[
					'name'   => 'n',
					'type'   => 'number',
					'unique' => true,
				],
			]
		);
	}

	/**
	 * The editor payload carries the canonical rule next to the field's format.
	 */
	public function test_payload_ships_the_canonical_rule(): void {
		CassetteCMFBlocks::register_from_array(
			[
				'blocks' => [
					[
						'id'     => 'acme-test/unique-payload',
						'fields' => [
							[
								'name'   => 'slug',
								'type'   => 'text',
								'format' => 'slug',
								'unique' => [
									'scope' => 'ancestor',
									'block' => 'vendor/form',
								],
							],
						],
					],
				],
			]
		);

		$config = Editor_Payload::build( Block_Manager::init() )['blocks']['acme-test/unique-payload']['fields'][0]['config'];

		$this->assertSame(
			[
				'scope' => 'ancestor',
				'block' => 'vendor/form',
				'key'   => 'slug',
			],
			$config['unique']
		);
		$this->assertSame( 'slug', $config['format'] );
	}

	/**
	 * The JSON validator reports a bad "unique".
	 */
	public function test_schema_validator_flags_a_bad_unique(): void {
		$validator = new Block_Schema_Validator();

		$ok = $validator->validate(
			[
				'blocks' => [
					[
						'id'     => 'acme/x',
						'fields' => [
							[
								'name'   => 'a',
								'type'   => 'text',
								'unique' => [ 'scope' => 'nowhere' ],
							],
						],
					],
				],
			]
		);

		$this->assertFalse( $ok );
		$this->assertStringContainsString( 'unique is invalid', $validator->get_error_message() );
	}
}
