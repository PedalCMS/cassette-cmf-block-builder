<?php
/**
 * Conditional_Evaluator class for Cassette-CMF Blocks
 *
 * Evaluates an already-normalized `{ relation, rules[] }` conditional
 * config (see Compat\Cmf_Bridge::normalize_conditional(), which still
 * verbatim-reuses the parent library for normalisation — operator alias
 * resolution, "value" stripping for empty/not_empty rules — per this
 * library's design principle of reusing the parent's own logic) against a
 * value context.
 *
 * This is a direct, parent-independent port of the ten-operator evaluator
 * in the parent's Abstract_Field (evaluate_conditional_rule(),
 * values_match(), evaluate_inclusion_rule(), evaluate_numeric_rule(),
 * is_empty_value() — src/field/class-abstract-field.php:553-699),
 * deliberately NOT routed through Compat\Cmf_Bridge's
 * make-a-throwaway-field-instance trick the way Cmf_Bridge::is_condition_met()
 * is: that trick exists because normalisation genuinely needs the parent's
 * Field_Factory, but evaluation of an already-normalized shape does not,
 * and a direct port is what makes a PHP<->JS parity fixture
 * (tests/fixtures/conditions.json, mirroring markup.json's pattern)
 * possible in the first place — markup/conditions.js is the JS half of the
 * exact same port, kept honest against this class by the same fixture.
 *
 * @package Pedalcms\CassetteCmfBlocks
 * @since 0.1.0
 */

namespace Pedalcms\CassetteCmfBlocks\Render;

/**
 * Class Conditional_Evaluator
 */
class Conditional_Evaluator {

	/**
	 * Evaluate an already-normalized conditional config against a context.
	 *
	 * @param array<string, mixed> $normalized { relation: 'AND'|'OR', rules: [ { field, operator, value? } ] }. Empty (no rules) always evaluates true.
	 * @param array<string, mixed> $context    Field-name => value map.
	 * @return bool
	 */
	public static function evaluate( array $normalized, array $context ): bool {
		$rules = $normalized['rules'] ?? [];

		if ( empty( $rules ) ) {
			return true;
		}

		$results = [];
		foreach ( $rules as $rule ) {
			$actual_value = array_key_exists( $rule['field'], $context ) ? $context[ $rule['field'] ] : null;
			$results[]    = self::evaluate_rule( $rule, $actual_value );
		}

		if ( 'OR' === ( $normalized['relation'] ?? 'AND' ) ) {
			return in_array( true, $results, true );
		}

		return ! in_array( false, $results, true );
	}

	/**
	 * Evaluate a single normalized rule against a resolved actual value.
	 *
	 * @param array{field: string, operator: string, value?: mixed} $rule         Normalized rule.
	 * @param mixed                                                 $actual_value Resolved value for $rule['field'].
	 * @return bool
	 */
	private static function evaluate_rule( array $rule, $actual_value ): bool {
		$operator       = $rule['operator'] ?? '==';
		$expected_value = $rule['value'] ?? null;

		switch ( $operator ) {
			case 'empty':
				return self::is_empty_value( $actual_value );

			case 'not_empty':
				return ! self::is_empty_value( $actual_value );

			case 'in':
				return self::evaluate_inclusion_rule( $actual_value, $expected_value );

			case 'not_in':
				return ! self::evaluate_inclusion_rule( $actual_value, $expected_value );

			case '>':
			case '>=':
			case '<':
			case '<=':
				return self::evaluate_numeric_rule( $actual_value, $expected_value, $operator );

			case '!=':
				return ! self::values_match( $actual_value, $expected_value );

			case '==':
			default:
				return self::values_match( $actual_value, $expected_value );
		}
	}

	/**
	 * Evaluate inclusion-style ("in"/"not_in") rules.
	 *
	 * @param mixed $actual_value   Resolved actual value.
	 * @param mixed $expected_value Expected value or values.
	 * @return bool
	 */
	private static function evaluate_inclusion_rule( $actual_value, $expected_value ): bool {
		$expected_values = is_array( $expected_value ) ? $expected_value : [ $expected_value ];

		if ( is_array( $actual_value ) ) {
			foreach ( $actual_value as $value ) {
				if ( self::evaluate_inclusion_rule( $value, $expected_values ) ) {
					return true;
				}
			}

			return false;
		}

		foreach ( $expected_values as $expected ) {
			if ( self::values_match( $actual_value, $expected ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Evaluate numeric-comparison ("&gt;"/"&gt;="/"&lt;"/"&lt;=") rules. A
	 * non-numeric actual or expected value never satisfies a numeric rule.
	 *
	 * @param mixed  $actual_value   Resolved actual value.
	 * @param mixed  $expected_value Expected threshold.
	 * @param string $operator       One of '>', '>=', '<', '<='.
	 * @return bool
	 */
	private static function evaluate_numeric_rule( $actual_value, $expected_value, string $operator ): bool {
		if ( is_array( $actual_value ) ) {
			$actual_value = reset( $actual_value );
		}

		if ( ! is_numeric( $actual_value ) || ! is_numeric( $expected_value ) ) {
			return false;
		}

		$actual_value   = (float) $actual_value;
		$expected_value = (float) $expected_value;

		switch ( $operator ) {
			case '>':
				return $actual_value > $expected_value;
			case '>=':
				return $actual_value >= $expected_value;
			case '<':
				return $actual_value < $expected_value;
			case '<=':
				return $actual_value <= $expected_value;
		}

		return false;
	}

	/**
	 * Whether two values match for "=="/"!=" purposes — string comparison
	 * after normalizing booleans to "1"/"0", and true if any element of an
	 * array actual value matches (so a multi-select attribute's value can
	 * satisfy a single-value rule).
	 *
	 * @param mixed $actual_value   Resolved actual value.
	 * @param mixed $expected_value Expected value.
	 * @return bool
	 */
	private static function values_match( $actual_value, $expected_value ): bool {
		if ( is_array( $actual_value ) ) {
			foreach ( $actual_value as $value ) {
				if ( self::values_match( $value, $expected_value ) ) {
					return true;
				}
			}

			return false;
		}

		if ( is_bool( $actual_value ) ) {
			$actual_value = $actual_value ? '1' : '0';
		}

		if ( is_bool( $expected_value ) ) {
			$expected_value = $expected_value ? '1' : '0';
		}

		return (string) $actual_value === (string) $expected_value;
	}

	/**
	 * Whether a value counts as empty — null, '', or an array with no
	 * non-empty elements.
	 *
	 * @param mixed $value Value to check.
	 * @return bool
	 */
	private static function is_empty_value( $value ): bool {
		if ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				if ( ! self::is_empty_value( $item ) ) {
					return false;
				}
			}

			return true;
		}

		return null === $value || '' === $value;
	}
}
