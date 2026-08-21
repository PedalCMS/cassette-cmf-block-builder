/**
 * Evaluates an already-normalized `{ relation, rules[] }` conditional
 * config against a value context — a direct port of the parent library's
 * ten-operator evaluator (Abstract_Field::evaluate_conditional_rule() and
 * friends, src/field/class-abstract-field.php:553-699), and the exact JS
 * counterpart to Render\Conditional_Evaluator (PHP). Both are kept honest
 * against the same tests/fixtures/conditions.json cases
 * (Test_Conditional_Evaluator.php and conditions.test.js) — unlike the
 * markup fixture (an HTML string vs. a React tree, not directly
 * comparable), a conditional's result is a plain boolean in both
 * languages, so this really is byte-for-byte two-language parity, not a
 * structural approximation.
 *
 * Normalisation itself is NOT ported here — the payload ships fields'
 * "conditional" and markup nodes' "when" already normalized
 * (Compat\Cmf_Bridge::normalize_conditional(), still reusing the parent
 * library verbatim), so this file never sees an operator alias like
 * "equals" or "contains", only the canonical "==" already resolved.
 */

/**
 * Evaluate an already-normalized conditional config against a context.
 *
 * @param {Object} normalized { relation: 'AND'|'OR', rules: [ { field, operator, value? } ] }. Empty (no rules) always evaluates true.
 * @param {Object} context    Field-name => value map.
 * @return {boolean} Whether the condition is met.
 */
export function evaluate( normalized, context ) {
	const rules = normalized.rules || [];

	if ( 0 === rules.length ) {
		return true;
	}

	const results = rules.map( ( rule ) => {
		const actualValue = Object.prototype.hasOwnProperty.call(
			context,
			rule.field
		)
			? context[ rule.field ]
			: null;

		return evaluateRule( rule, actualValue );
	} );

	if ( 'OR' === ( normalized.relation || 'AND' ) ) {
		return results.includes( true );
	}

	return ! results.includes( false );
}

/**
 * Evaluate a single normalized rule against a resolved actual value.
 *
 * @param {Object} rule        Normalized rule: { field, operator, value? }.
 * @param {*}      actualValue Resolved value for rule.field.
 * @return {boolean} Whether the rule is satisfied.
 */
function evaluateRule( rule, actualValue ) {
	const operator = rule.operator || '==';
	const expectedValue = undefined !== rule.value ? rule.value : null;

	switch ( operator ) {
		case 'empty':
			return isEmptyValue( actualValue );
		case 'not_empty':
			return ! isEmptyValue( actualValue );
		case 'in':
			return evaluateInclusionRule( actualValue, expectedValue );
		case 'not_in':
			return ! evaluateInclusionRule( actualValue, expectedValue );
		case '>':
		case '>=':
		case '<':
		case '<=':
			return evaluateNumericRule( actualValue, expectedValue, operator );
		case '!=':
			return ! valuesMatch( actualValue, expectedValue );
		case '==':
		default:
			return valuesMatch( actualValue, expectedValue );
	}
}

/**
 * Evaluate inclusion-style ("in"/"not_in") rules.
 *
 * @param {*} actualValue   Resolved actual value.
 * @param {*} expectedValue Expected value or values.
 * @return {boolean} Whether actualValue is included in expectedValue.
 */
function evaluateInclusionRule( actualValue, expectedValue ) {
	const expectedValues = Array.isArray( expectedValue )
		? expectedValue
		: [ expectedValue ];

	if ( Array.isArray( actualValue ) ) {
		return actualValue.some( ( value ) =>
			evaluateInclusionRule( value, expectedValues )
		);
	}

	return expectedValues.some( ( expected ) =>
		valuesMatch( actualValue, expected )
	);
}

/**
 * Evaluate numeric-comparison (">"/">="/"<"/"<=") rules. A non-numeric
 * actual or expected value never satisfies a numeric rule.
 *
 * @param {*}      actualValue   Resolved actual value.
 * @param {*}      expectedValue Expected threshold.
 * @param {string} operator      One of '>', '>=', '<', '<='.
 * @return {boolean} Whether the numeric comparison is satisfied.
 */
function evaluateNumericRule( actualValue, expectedValue, operator ) {
	const resolvedActual = Array.isArray( actualValue )
		? actualValue[ 0 ]
		: actualValue;

	if ( ! isNumeric( resolvedActual ) || ! isNumeric( expectedValue ) ) {
		return false;
	}

	const actual = parseFloat( resolvedActual );
	const expected = parseFloat( expectedValue );

	switch ( operator ) {
		case '>':
			return actual > expected;
		case '>=':
			return actual >= expected;
		case '<':
			return actual < expected;
		case '<=':
			return actual <= expected;
		default:
			return false;
	}
}

/**
 * Whether two values match for "=="/"!=" purposes — string comparison
 * after normalizing booleans to "1"/"0", and true if any element of an
 * array actual value matches (so a multi-select attribute's value can
 * satisfy a single-value rule).
 *
 * @param {*} actualValue   Resolved actual value.
 * @param {*} expectedValue Expected value.
 * @return {boolean} Whether the values match.
 */
function valuesMatch( actualValue, expectedValue ) {
	if ( Array.isArray( actualValue ) ) {
		return actualValue.some( ( value ) =>
			valuesMatch( value, expectedValue )
		);
	}

	return (
		String( normalizeBoolean( actualValue ) ) ===
		String( normalizeBoolean( expectedValue ) )
	);
}

/**
 * Normalize a boolean to "1"/"0" (matching PHP's (string) cast), leaving
 * every other type untouched.
 *
 * @param {*} value Value to normalize.
 * @return {*} The normalized value.
 */
function normalizeBoolean( value ) {
	if ( 'boolean' !== typeof value ) {
		return value;
	}

	return value ? '1' : '0';
}

/**
 * Whether a value counts as empty — null/undefined, '', or an array with
 * no non-empty elements.
 *
 * @param {*} value Value to check.
 * @return {boolean} Whether the value counts as empty.
 */
function isEmptyValue( value ) {
	if ( Array.isArray( value ) ) {
		return value.every( ( item ) => isEmptyValue( item ) );
	}

	return null === value || undefined === value || '' === value;
}

/**
 * PHP's is_numeric() equivalent, close enough for this evaluator's needs:
 * true for a number, or a string that parses fully to a finite number.
 *
 * @param {*} value Value to check.
 * @return {boolean} Whether the value is numeric.
 */
function isNumeric( value ) {
	if ( 'number' === typeof value ) {
		return Number.isFinite( value );
	}

	if ( 'string' === typeof value && '' !== value.trim() ) {
		return Number.isFinite( Number( value ) );
	}

	return false;
}
