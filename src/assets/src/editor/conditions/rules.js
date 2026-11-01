import { isCrossBlockRule } from './evaluate';

/**
 * Helpers for the rule list the `conditions` control edits. A rule is either
 * an own-attribute rule `{ field, operator, value? }` or a cross-block rule
 * `{ source: 'block', ref, operator, value? }` (see docs/control-catalog.md).
 */

export { isCrossBlockRule };

/**
 * A new own-attribute rule.
 *
 * @param {string} field Attribute name.
 * @return {Object} The rule.
 */
export function newOwnRule( field = '' ) {
	return { field, operator: '==', value: '' };
}

/**
 * A new cross-block rule.
 *
 * @param {string} ref Referenced block's name.
 * @return {Object} The rule.
 */
export function newBlockRule( ref = '' ) {
	return { source: 'block', ref, operator: '==', value: '' };
}

/**
 * Convert a rule between the two kinds, keeping its operator and value.
 *
 * @param {Object} rule  The rule.
 * @param {string} kind  'block' or 'own'.
 * @param {string} [own] Attribute to point an own rule at when converting to 'own'.
 * @return {Object} The converted rule (the input when it already has that kind).
 */
export function convertRule( rule, kind, own = '' ) {
	const cross = isCrossBlockRule( rule );
	const { operator = '==', value } = rule;
	const rest = undefined === value ? {} : { value };

	if ( 'block' === kind ) {
		return cross ? rule : { source: 'block', ref: '', operator, ...rest };
	}

	return cross ? { field: own, operator, ...rest } : rule;
}
