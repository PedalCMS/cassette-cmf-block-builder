/**
 * controls/rich-text.js test — the "canvas"-only RichText vs. everywhere-
 * else textarea degradation, see that file's docblock.
 */

import RichTextControl from '../../src/assets/src/editor/controls/rich-text';

describe( 'RichTextControl', () => {
	it( 'renders a real RichText when declared in the "canvas" area', () => {
		const element = RichTextControl( {
			field: { name: 'body', tag: 'div' },
			value: '<p>Hi</p>',
			onChange: () => {},
			area: 'canvas',
		} );

		// The mocked @wordpress/block-editor doesn't export RichText, so an
		// unmocked import resolves to undefined — element.type being a real
		// function (not undefined, and not TextareaControl's own stub
		// identity) confirms this branch reaches for RichText specifically,
		// not the textarea fallback.
		expect( element.props.tagName ).toBe( 'div' );
		expect( element.props.value ).toBe( '<p>Hi</p>' );
	} );

	it( 'degrades to a plain textarea outside the "canvas" area', () => {
		const element = RichTextControl( {
			field: { name: 'body', label: 'Body' },
			value: '<p>Hi</p>',
			onChange: () => {},
			area: 'inspector',
		} );

		expect( element.props.value ).toBe( '<p>Hi</p>' );
		expect( element.props.label ).toBe( 'Body' );
		// TextareaControl's own props shape (label/value/onChange), distinct
		// from RichText's (tagName/value/onChange/placeholder) — no "tagName"
		// prop is the simplest structural signal this is the textarea branch.
		expect( element.props.tagName ).toBeUndefined();
		// The degrade console.warn() is expected behaviour under a
		// non-production build — see rich-text.js's docblock — not a test
		// failure to suppress.
		expect( console ).toHaveWarned();
	} );

	it( 'degrades to a plain textarea when no area is declared at all', () => {
		const element = RichTextControl( {
			field: { name: 'body' },
			value: '',
			onChange: () => {},
		} );

		expect( element.props.tagName ).toBeUndefined();
		expect( console ).toHaveWarned();
	} );
} );
