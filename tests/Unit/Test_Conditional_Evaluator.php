<?php
/**
 * Conditional_Evaluator <-> conditions.js parity fixture test, plus direct
 * unit coverage.
 *
 * @package PedalCMS\CassetteCMFBlocks\Tests\Unit
 */

use PedalCMS\CassetteCMFBlocks\Render\Conditional_Evaluator;

require_once __DIR__ . '/CassetteCMFBlocks_UnitTestCase.php';

/**
 * Class Test_Conditional_Evaluator
 */
class Test_Conditional_Evaluator extends CassetteCMFBlocks_UnitTestCase {

	/**
	 * Every case in tests/fixtures/conditions.json — the same file
	 * conditions.test.js reads — must evaluate to its expected boolean.
	 * Unlike markup.json (expression *strings* vs. a React tree, not
	 * directly comparable), a conditional's result is a plain boolean in
	 * both languages, so this is a genuine byte-for-byte (bit-for-bit)
	 * two-language parity test, not a structural approximation.
	 */
	public function test_condition_fixtures(): void {
		$path = dirname( __DIR__ ) . '/fixtures/conditions.json';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$fixtures = json_decode( (string) file_get_contents( $path ), true );

		$this->assertIsArray( $fixtures, 'conditions.json must decode to an array.' );
		$this->assertNotEmpty( $fixtures['cases'], 'conditions.json must contain at least one case.' );

		foreach ( $fixtures['cases'] as $case ) {
			$result = Conditional_Evaluator::evaluate( $case['normalized'], $case['context'] );

			$this->assertSame( $case['expected'], $result, sprintf( 'Fixture case "%s"', $case['description'] ) );
		}
	}

	/**
	 * An array actual value only needs one element to satisfy "==" — the
	 * same "any element matches" behaviour "in" has, ported from the
	 * parent's values_match() recursing into array actual values.
	 */
	public function test_equals_matches_any_element_of_an_array_actual_value(): void {
		$normalized = [
			'relation' => 'AND',
			'rules'    => [
				[
					'field'    => 'tags',
					'operator' => '==',
					'value'    => 'b',
				],
			],
		];

		$this->assertTrue( Conditional_Evaluator::evaluate( $normalized, [ 'tags' => [ 'a', 'b' ] ] ) );
		$this->assertFalse( Conditional_Evaluator::evaluate( $normalized, [ 'tags' => [ 'a', 'c' ] ] ) );
	}

	/**
	 * empty treats an array with only empty-ish elements as empty.
	 */
	public function test_empty_treats_array_of_empties_as_empty(): void {
		$normalized = [
			'relation' => 'AND',
			'rules'    => [
				[
					'field'    => 'tags',
					'operator' => 'empty',
				],
			],
		];

		$this->assertTrue( Conditional_Evaluator::evaluate( $normalized, [ 'tags' => [ '', null ] ] ) );
		$this->assertFalse( Conditional_Evaluator::evaluate( $normalized, [ 'tags' => [ '', 'x' ] ] ) );
	}
}
