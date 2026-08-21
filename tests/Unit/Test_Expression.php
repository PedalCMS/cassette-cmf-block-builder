<?php
/**
 * Expression test.
 *
 * @package Pedalcms\CassetteCmfBlocks\Tests\Unit
 */

use Pedalcms\CassetteCmfBlocks\Render\Expression;

require_once __DIR__ . '/CassetteCmfBlocks_UnitTestCase.php';

/**
 * Class Test_Expression
 */
class Test_Expression extends CassetteCmfBlocks_UnitTestCase {

	/**
	 * A bare path with no filters resolves to the scope value.
	 */
	public function test_resolves_a_dot_notation_path(): void {
		$value = Expression::evaluate( 'attributes.tone', [ 'attributes' => [ 'tone' => 'warm' ] ] );

		$this->assertSame( 'warm', $value );
	}

	/**
	 * A missing path resolves to null rather than throwing.
	 */
	public function test_missing_path_resolves_to_null(): void {
		$this->assertNull( Expression::evaluate( 'attributes.missing', [ 'attributes' => [] ] ) );
	}

	/**
	 * interpolate() replaces every "{{ ... }}" token in a template, leaving
	 * surrounding literal text untouched.
	 */
	public function test_interpolate_replaces_tokens_in_a_template(): void {
		$result = Expression::interpolate(
			'acme-callout acme-callout--{{ attributes.tone }}',
			[ 'attributes' => [ 'tone' => 'warm' ] ]
		);

		$this->assertSame( 'acme-callout acme-callout--warm', $result );
	}

	/**
	 * interpolate() must handle multiple tokens in one template.
	 */
	public function test_interpolate_handles_multiple_tokens(): void {
		$result = Expression::interpolate(
			'{{ attributes.a }}-{{ attributes.b }}',
			[
				'attributes' => [
					'a' => 'x',
					'b' => 'y',
				],
			]
		);

		$this->assertSame( 'x-y', $result );
	}

	/**
	 * The "default" filter substitutes only for null/''/[] — not for 0 or false.
	 */
	public function test_default_filter(): void {
		$this->assertSame( 'neutral', Expression::evaluate( 'attributes.tone | default:neutral', [ 'attributes' => [] ] ) );
		$this->assertSame( 'warm', Expression::evaluate( 'attributes.tone | default:neutral', [ 'attributes' => [ 'tone' => 'warm' ] ] ) );
		$this->assertSame( 0, Expression::evaluate( 'attributes.n | default:5', [ 'attributes' => [ 'n' => 0 ] ] ) );
		$this->assertFalse( Expression::evaluate( 'attributes.flag | default:5', [ 'attributes' => [ 'flag' => false ] ] ) );
	}

	/**
	 * upper/lower filters.
	 */
	public function test_upper_and_lower_filters(): void {
		$this->assertSame( 'WARM', Expression::evaluate( 'attributes.tone | upper', [ 'attributes' => [ 'tone' => 'Warm' ] ] ) );
		$this->assertSame( 'warm', Expression::evaluate( 'attributes.tone | lower', [ 'attributes' => [ 'tone' => 'Warm' ] ] ) );
	}

	/**
	 * slug filter.
	 */
	public function test_slug_filter(): void {
		$this->assertSame(
			'hello-world',
			Expression::evaluate( 'attributes.title | slug', [ 'attributes' => [ 'title' => 'Hello World!' ] ] )
		);
	}

	/**
	 * join/count filters, for array-valued attributes.
	 */
	public function test_join_and_count_filters(): void {
		$scope = [ 'attributes' => [ 'tags' => [ 'a', 'b', 'c' ] ] ];

		$this->assertSame( 'a, b, c', Expression::evaluate( 'attributes.tags | join', $scope ) );
		$this->assertSame( 'a-b-c', Expression::evaluate( 'attributes.tags | join:-', $scope ) );
		$this->assertSame( 3, Expression::evaluate( 'attributes.tags | count', $scope ) );
	}

	/**
	 * not filter inverts truthiness into "1"/"".
	 */
	public function test_not_filter(): void {
		$this->assertSame( '', Expression::evaluate( 'attributes.flag | not', [ 'attributes' => [ 'flag' => true ] ] ) );
		$this->assertSame( '1', Expression::evaluate( 'attributes.flag | not', [ 'attributes' => [ 'flag' => false ] ] ) );
	}

	/**
	 * number filter.
	 */
	public function test_number_filter(): void {
		$this->assertSame( '42.50', Expression::evaluate( 'attributes.price | number:2', [ 'attributes' => [ 'price' => 42.5 ] ] ) );
		$this->assertSame( '43', Expression::evaluate( 'attributes.price | number', [ 'attributes' => [ 'price' => 42.5 ] ] ) );
	}

	/**
	 * Filters chain left to right.
	 */
	public function test_filters_chain(): void {
		$result = Expression::evaluate(
			'attributes.tone | default:neutral | upper',
			[ 'attributes' => [] ]
		);

		$this->assertSame( 'NEUTRAL', $result );
	}

	/**
	 * An unknown filter name is a silent no-op, not a fatal.
	 */
	public function test_unknown_filter_is_a_noop(): void {
		$this->assertSame( 'warm', Expression::evaluate( 'attributes.tone | nope', [ 'attributes' => [ 'tone' => 'warm' ] ] ) );
	}

	/**
	 * interpolate() stringifies booleans as "1"/"" and arrays as "".
	 */
	public function test_interpolate_stringifies_booleans_and_arrays(): void {
		$this->assertSame( '1', Expression::interpolate( '{{ attributes.flag }}', [ 'attributes' => [ 'flag' => true ] ] ) );
		$this->assertSame( '', Expression::interpolate( '{{ attributes.flag }}', [ 'attributes' => [ 'flag' => false ] ] ) );
		$this->assertSame( '', Expression::interpolate( '{{ attributes.list }}', [ 'attributes' => [ 'list' => [ 'a' ] ] ] ) );
	}
}
