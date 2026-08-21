/**
 * Evaluates the mustache-lite `{{ path | filter1 | filter2:arg }}` tokens
 * used inside a markup node's "class"/"attrs"/"text"/"html" values.
 *
 * The JS half of "one declarative markup tree drives three runtimes" — kept
 * honest against Render\Expression (PHP) by sharing test fixture VALUES
 * (tests/fixtures/markup.json): each language's test suite asserts its own
 * evaluator against the same { expression, scope, expected } cases, so a
 * filter behaving differently between the two would show up as a failure in
 * both, not just drift silently apart. The two evaluators are not expected
 * to produce byte-identical *markup* output (an HTML string and a React
 * element tree are different representations) — only identical expression
 * *values*, which is what the fixture actually tests.
 */

import { slugify } from '../util/slugify';

const TOKEN_PATTERN = /\{\{\s*([\s\S]+?)\s*\}\}/g;

/**
 * Replace every "{{ ... }}" token in a template string with its resolved,
 * stringified value.
 *
 * @param {string} template Template string.
 * @param {Object} scope    Value scope, e.g. { attributes: {...}, item: {...} }.
 * @return {string} The fully-resolved string.
 */
export function interpolate( template, scope ) {
	return String( template ).replace( TOKEN_PATTERN, ( match, expression ) =>
		stringify( evaluate( expression, scope ) )
	);
}

/**
 * Evaluate a single "path | filter1 | filter2:arg" expression (the inside
 * of one "{{ ... }}" token) against a scope, returning the raw (not yet
 * stringified) resulting value.
 *
 * @param {string} expression e.g. "attributes.tone | default:neutral | upper".
 * @param {Object} scope      Value scope.
 * @return {*} The resolved value.
 */
export function evaluate( expression, scope ) {
	const segments = expression
		.split( '|' )
		.map( ( segment ) => segment.trim() );
	const path = segments.shift();
	let value = resolvePath( path, scope );

	segments.forEach( ( filterExpression ) => {
		if ( '' === filterExpression ) {
			return;
		}
		value = applyFilter( filterExpression, value );
	} );

	return value;
}

/**
 * Resolve a dot-notation path (e.g. "attributes.tone") against a scope. A
 * missing path resolves to null rather than throwing.
 *
 * @param {string} path  Dot-notation path.
 * @param {Object} scope Value scope.
 * @return {*} The resolved value, or null.
 */
function resolvePath( path, scope ) {
	return path.split( '.' ).reduce( ( current, segment ) => {
		if (
			current &&
			'object' === typeof current &&
			Object.prototype.hasOwnProperty.call( current, segment )
		) {
			return current[ segment ];
		}
		return null;
	}, scope );
}

/**
 * Apply one filter ("name" or "name:arg") to a value. Unknown filter names
 * pass the value through unchanged.
 *
 * @param {string} filterExpression e.g. "default:neutral" or "upper".
 * @param {*}      value            Value to filter.
 * @return {*} The filtered value.
 */
function applyFilter( filterExpression, value ) {
	const colonIndex = filterExpression.indexOf( ':' );
	const name =
		-1 === colonIndex
			? filterExpression
			: filterExpression.slice( 0, colonIndex );
	const arg =
		-1 === colonIndex ? null : filterExpression.slice( colonIndex + 1 );

	switch ( name.trim() ) {
		case 'default':
			return isEmpty( value ) ? arg : value;
		case 'upper':
			return String( value ).toUpperCase();
		case 'lower':
			return String( value ).toLowerCase();
		case 'slug':
			return slugify( String( value ) );
		case 'esc_url':
			return escUrl( String( value ) );
		case 'date':
			return filterDate( value, arg );
		case 'number':
			return Number( value ).toFixed(
				null !== arg ? parseInt( arg, 10 ) : 0
			);
		case 'join':
			return Array.isArray( value )
				? value.join( null !== arg ? arg : ', ' )
				: value;
		case 'count':
			return Array.isArray( value ) || 'string' === typeof value
				? value.length
				: 0;
		case 'not':
			return truthy( value ) ? '' : '1';
		default:
			return value;
	}
}

/**
 * The "date" filter: format a Unix timestamp (seconds) or a
 * Date.parse()-parseable date string. Only Y/m/d/H/i/s tokens are
 * supported — enough to keep parity with Render\Expression's fixture cases,
 * not a full date()-format implementation.
 *
 * @param {*}       value  Timestamp (seconds) or date string.
 * @param {?string} format Format string; defaults to "Y-m-d".
 * @return {string} The formatted date, or "" if unparseable.
 */
function filterDate( value, format ) {
	const fmt = format || 'Y-m-d';
	const timestamp =
		'number' === typeof value ? value * 1000 : Date.parse( value );

	if ( Number.isNaN( timestamp ) ) {
		return '';
	}

	const date = new Date( timestamp );
	const pad = ( n ) => String( n ).padStart( 2, '0' );
	const tokens = {
		Y: date.getFullYear(),
		m: pad( date.getMonth() + 1 ),
		d: pad( date.getDate() ),
		H: pad( date.getHours() ),
		i: pad( date.getMinutes() ),
		s: pad( date.getSeconds() ),
	};

	return fmt.replace( /Y|m|d|H|i|s/g, ( token ) =>
		undefined !== tokens[ token ] ? String( tokens[ token ] ) : token
	);
}

/**
 * Whether a value counts as "empty" for the "default" filter — null,
 * undefined, empty string, or empty array. Deliberately excludes 0/false.
 *
 * @param {*} value Value to check.
 * @return {boolean} Whether the value counts as empty.
 */
function isEmpty( value ) {
	return (
		null === value ||
		undefined === value ||
		'' === value ||
		( Array.isArray( value ) && 0 === value.length )
	);
}

/**
 * Boolean-ish truthiness for the "not" filter.
 *
 * @param {*} value Value to check.
 * @return {boolean} Whether the value is truthy.
 */
function truthy( value ) {
	if ( Array.isArray( value ) ) {
		return value.length > 0;
	}
	return Boolean( value );
}

/**
 * A minimal ASCII-safe URL guard for the "esc_url" filter: only http(s),
 * root-relative, and fragment URLs pass through. Not a port of WordPress's
 * full esc_url() (protocol whitelist, entity encoding, kses) — good enough
 * for editor-preview use, where the real front-end escaping is
 * Render\Expression's esc_url() call server-side.
 *
 * @param {string} value URL to check.
 * @return {string} The URL unchanged, or "" if it looks unsafe.
 */
function escUrl( value ) {
	if (
		/^https?:\/\//i.test( value ) ||
		value.startsWith( '/' ) ||
		value.startsWith( '#' )
	) {
		return value;
	}
	return '';
}

/**
 * Stringify a resolved value for interpolation into a template. Booleans
 * stringify as "1"/"", matching Render\Expression's PHP (string) cast.
 *
 * @param {*} value Value to stringify.
 * @return {string} The stringified value.
 */
function stringify( value ) {
	if ( Array.isArray( value ) ) {
		return '';
	}
	if ( 'boolean' === typeof value ) {
		return value ? '1' : '';
	}
	if ( null === value || undefined === value ) {
		return '';
	}
	return String( value );
}
