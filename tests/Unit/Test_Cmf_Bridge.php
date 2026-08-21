<?php
/**
 * Cmf_Bridge test.
 *
 * @package Pedalcms\CassetteCmfBlocks\Tests\Unit
 */

use Pedalcms\CassetteCmfBlocks\Compat\Cmf_Bridge;

require_once __DIR__ . '/CassetteCmfBlocks_UnitTestCase.php';

/**
 * Class Test_Cmf_Bridge
 */
class Test_Cmf_Bridge extends CassetteCmfBlocks_UnitTestCase {

	/**
	 * sanitize() should delegate to the parent's field type — "email"
	 * sanitizes via WP's sanitize_email(), which trims surrounding whitespace.
	 */
	public function test_sanitize_delegates_to_parent_field_type(): void {
		$sanitized = Cmf_Bridge::sanitize( 'email', [], '  person@example.com  ' );

		$this->assertSame( 'person@example.com', $sanitized );
	}

	/**
	 * validate() should delegate to the parent's field type and report a
	 * required-field error using the parent's own message.
	 */
	public function test_validate_delegates_to_parent_field_type(): void {
		$result = Cmf_Bridge::validate( 'text', [ 'required' => true ], '' );

		$this->assertFalse( $result['valid'] );
		$this->assertNotEmpty( $result['errors'] );
	}

	/**
	 * A valid value should pass validation with no errors.
	 */
	public function test_validate_passes_for_valid_value(): void {
		$result = Cmf_Bridge::validate( 'text', [ 'required' => true ], 'hello' );

		$this->assertTrue( $result['valid'] );
		$this->assertSame( [], $result['errors'] );
	}

	/**
	 * normalize_conditional() should reach the parent's public
	 * get_schema()['conditional'] path and resolve operator aliases (e.g.
	 * "equals" -> "==").
	 */
	public function test_normalize_conditional_resolves_operator_aliases(): void {
		$normalized = Cmf_Bridge::normalize_conditional(
			[
				'field'    => 'is_open',
				'operator' => 'equals',
				'value'    => '1',
			]
		);

		$this->assertSame( 'AND', $normalized['relation'] );
		$this->assertSame( '==', $normalized['rules'][0]['operator'] );
	}

	/**
	 * An empty conditional config should normalize to an empty array.
	 */
	public function test_normalize_conditional_empty_input(): void {
		$this->assertSame( [], Cmf_Bridge::normalize_conditional( [] ) );
	}

	/**
	 * is_condition_met() should evaluate the normalized rule against a
	 * value context.
	 */
	public function test_is_condition_met_evaluates_against_context(): void {
		$conditional = [
			'field'    => 'is_open',
			'operator' => '==',
			'value'    => '1',
		];

		$this->assertTrue( Cmf_Bridge::is_condition_met( $conditional, [ 'is_open' => '1' ] ) );
		$this->assertFalse( Cmf_Bridge::is_condition_met( $conditional, [ 'is_open' => '0' ] ) );
	}

	/**
	 * An empty conditional config should always evaluate as met (visible).
	 */
	public function test_is_condition_met_with_no_conditional_is_always_true(): void {
		$this->assertTrue( Cmf_Bridge::is_condition_met( [], [] ) );
	}

	/**
	 * has_cmf_type()/get_cmf_types() should reflect the parent's live registry.
	 */
	public function test_has_cmf_type_and_get_cmf_types(): void {
		$this->assertTrue( Cmf_Bridge::has_cmf_type( 'text' ) );
		$this->assertFalse( Cmf_Bridge::has_cmf_type( 'not_a_real_type' ) );
		$this->assertArrayHasKey( 'text', Cmf_Bridge::get_cmf_types() );
	}
}
