/**
 * Helpers for the declarative "raw" transform: turn the payload's `selector`
 * (a CSS selector string, or a { tag, class, attrs } rule) into core's
 * isMatch() predicate and its sanitizing schema.
 */

const SIMPLE_SELECTOR = /^([A-Za-z][A-Za-z0-9-]*)?((?:\.[A-Za-z0-9_-]+)*)$/;

/**
 * Normalize a selector to { tag, classes, attrs } when it is simple enough to
 * describe (tag and classes only, for a string).
 *
 * @param {string|Object} selector Selector string or rule.
 * @return {Object|null} { tag, classes, attrs } or null for a complex selector.
 */
function toRule( selector ) {
	if ( 'string' === typeof selector ) {
		const parts = selector.trim().match( SIMPLE_SELECTOR );
		if ( ! parts || ( ! parts[ 1 ] && ! parts[ 2 ] ) ) {
			return null;
		}

		return {
			tag: parts[ 1 ] ? parts[ 1 ].toLowerCase() : '',
			classes: parts[ 2 ]
				? parts[ 2 ].split( '.' ).filter( Boolean )
				: [],
			attrs: {},
		};
	}

	if ( selector && 'object' === typeof selector ) {
		return {
			tag: selector.tag ? String( selector.tag ).toLowerCase() : '',
			classes: []
				.concat( selector.class || [] )
				.map( String )
				.filter( Boolean ),
			attrs: selector.attrs || {},
		};
	}

	return null;
}

/**
 * Build the isMatch( node ) predicate for core's raw transform.
 *
 * @param {string|Object} selector Selector string or rule.
 * @return {Function|null} The predicate, or null when the selector is unusable.
 */
export function buildRawMatcher( selector ) {
	if ( 'string' === typeof selector && selector.trim() ) {
		return ( node ) => {
			if ( ! node || 1 !== node.nodeType ) {
				return false;
			}
			try {
				return node.matches( selector );
			} catch ( error ) {
				return false;
			}
		};
	}

	const rule = toRule( selector );
	if (
		! rule ||
		( ! rule.tag &&
			! rule.classes.length &&
			! Object.keys( rule.attrs ).length )
	) {
		return null;
	}

	return ( node ) => {
		if ( ! node || 1 !== node.nodeType ) {
			return false;
		}
		if ( rule.tag && node.nodeName.toLowerCase() !== rule.tag ) {
			return false;
		}
		if (
			! rule.classes.every( ( name ) => node.classList.contains( name ) )
		) {
			return false;
		}

		return Object.entries( rule.attrs ).every(
			( [ name, value ] ) => String( value ) === node.getAttribute( name )
		);
	};
}

/**
 * Build the `schema` core needs so pasted markup survives its HTML cleanup
 * (which strips unknown elements, classes and attributes before isMatch()
 * runs). Only available for a tag-based rule; complex CSS selectors get none,
 * so a consumer should pair them with a tag the paste filter keeps. Nested
 * content is limited to phrasing content.
 *
 * @param {string|Object} selector Selector string or rule.
 * @return {Function|null} A schema factory, or null.
 */
export function buildRawSchema( selector ) {
	const rule = toRule( selector );

	if ( ! rule || ! rule.tag ) {
		return null;
	}

	return ( { phrasingContentSchema } = {} ) => ( {
		[ rule.tag ]: {
			attributes: [ 'class', ...Object.keys( rule.attrs ) ],
			classes: rule.classes,
			children: phrasingContentSchema,
		},
	} );
}
