import { slugify } from './slugify';

/**
 * Apply a text field's "format" to a value: "slug" (reuses slugify()),
 * "upper", or "lower". Any other value (including unset) passes the value
 * through unchanged.
 *
 * @param {string} value  Value to format.
 * @param {string} format One of "slug"/"upper"/"lower", or unset.
 * @return {string} The formatted value.
 */
export function applyFormat( value, format ) {
	if ( 'slug' === format ) {
		return slugify( value );
	}
	if ( 'upper' === format ) {
		return value.toUpperCase();
	}
	if ( 'lower' === format ) {
		return value.toLowerCase();
	}
	return value;
}
