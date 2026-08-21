<?php
/**
 * Block_Schema_Validator test.
 *
 * @package Pedalcms\CassetteCmfBlocks\Tests\Unit
 */

use Pedalcms\CassetteCmfBlocks\Json\Block_Schema_Validator;
use Pedalcms\CassetteCmfBlocks\Schema\Control_Catalog;

require_once __DIR__ . '/CassetteCmfBlocks_UnitTestCase.php';

/**
 * Class Test_Block_Schema_Validator
 */
class Test_Block_Schema_Validator extends CassetteCmfBlocks_UnitTestCase {

	/**
	 * The validator under test.
	 *
	 * @var Block_Schema_Validator
	 */
	private Block_Schema_Validator $validator;

	/**
	 * Reset the catalog and build a fresh validator before each test.
	 */
	public function set_up(): void {
		parent::set_up();
		Control_Catalog::reset();
		$this->validator = new Block_Schema_Validator();
	}

	/**
	 * A well-formed config should validate with no errors.
	 */
	public function test_valid_config_passes(): void {
		$valid = $this->validator->validate(
			[
				'blocks' => [
					[
						'id'     => 'acme/callout',
						'args'   => [ 'title' => 'Callout' ],
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

		$this->assertTrue( $valid );
		$this->assertFalse( $this->validator->has_errors() );
	}

	/**
	 * An empty config (no "blocks" key) is valid — nothing to register.
	 */
	public function test_config_without_blocks_key_is_valid(): void {
		$this->assertTrue( $this->validator->validate( [] ) );
	}

	/**
	 * A block missing "id" should produce an error naming its path.
	 */
	public function test_missing_id_produces_error(): void {
		$valid = $this->validator->validate( [ 'blocks' => [ [ 'args' => [] ] ] ] );

		$this->assertFalse( $valid );
		$this->assertStringContainsString( "blocks[0] missing required field 'id'", $this->validator->get_error_message() );
	}

	/**
	 * An id not matching the namespaced-name pattern should produce an error.
	 */
	public function test_malformed_id_produces_error(): void {
		$valid = $this->validator->validate( [ 'blocks' => [ [ 'id' => 'NotNamespaced' ] ] ] );

		$this->assertFalse( $valid );
		$this->assertStringContainsString( 'blocks[0].id', $this->validator->get_error_message() );
	}

	/**
	 * An unregistered control type should be rejected, listing valid types —
	 * the same trick as the parent's Schema_Validator reading from Field_Factory.
	 */
	public function test_unknown_field_type_produces_error(): void {
		$valid = $this->validator->validate(
			[
				'blocks' => [
					[
						'id'     => 'acme/callout',
						'fields' => [
							[
								'name' => 'x',
								'type' => 'not_a_type',
							],
						],
					],
				],
			]
		);

		$this->assertFalse( $valid );
		$this->assertStringContainsString( 'must be one of:', $this->validator->get_error_message() );
	}

	/**
	 * An unknown area should be rejected.
	 */
	public function test_unknown_area_produces_error(): void {
		$valid = $this->validator->validate(
			[
				'blocks' => [
					[
						'id'     => 'acme/callout',
						'fields' => [
							[
								'name' => 'x',
								'type' => 'text',
								'area' => 'nowhere',
							],
						],
					],
				],
			]
		);

		$this->assertFalse( $valid );
		$this->assertStringContainsString( 'blocks[0].fields[0].area', $this->validator->get_error_message() );
	}

	/**
	 * A conditional config without "rules" should be rejected.
	 */
	public function test_conditional_without_rules_produces_error(): void {
		$valid = $this->validator->validate(
			[
				'blocks' => [
					[
						'id'     => 'acme/callout',
						'fields' => [
							[
								'name'        => 'x',
								'type'        => 'text',
								'conditional' => [ 'relation' => 'AND' ],
							],
						],
					],
				],
			]
		);

		$this->assertFalse( $valid );
		$this->assertStringContainsString( 'conditional.rules is required', $this->validator->get_error_message() );
	}

	/**
	 * A conditional rule missing "field" should be rejected.
	 */
	public function test_conditional_rule_without_field_produces_error(): void {
		$valid = $this->validator->validate(
			[
				'blocks' => [
					[
						'id'     => 'acme/callout',
						'fields' => [
							[
								'name'        => 'x',
								'type'        => 'text',
								'conditional' => [
									'rules' => [
										[
											'operator' => '==',
											'value'    => '1',
										],
									],
								],
							],
						],
					],
				],
			]
		);

		$this->assertFalse( $valid );
		$this->assertStringContainsString( "rules[0] missing required field 'field'", $this->validator->get_error_message() );
	}

	/**
	 * Nested container fields should be validated recursively.
	 */
	public function test_nested_field_errors_are_caught(): void {
		$valid = $this->validator->validate(
			[
				'blocks' => [
					[
						'id'     => 'acme/callout',
						'fields' => [
							[
								'name'   => 'settings',
								'type'   => 'panel',
								'fields' => [
									[
										'name' => 'x',
										'type' => 'still_not_a_type',
									],
								],
							],
						],
					],
				],
			]
		);

		$this->assertFalse( $valid );
		$this->assertStringContainsString( 'blocks[0].fields[0].fields[0].type', $this->validator->get_error_message() );
	}

	/**
	 * validate() should reset errors from a previous call.
	 */
	public function test_validate_resets_errors_between_calls(): void {
		$this->validator->validate( [ 'blocks' => [ [ 'args' => [] ] ] ] );
		$this->assertTrue( $this->validator->has_errors() );

		$this->validator->validate( [ 'blocks' => [ [ 'id' => 'acme/callout' ] ] ] );
		$this->assertFalse( $this->validator->has_errors() );
	}
}
