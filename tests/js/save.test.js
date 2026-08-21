/**
 * save.js test.
 */

import { createSave } from '../../src/assets/src/editor/save';

describe( 'createSave', () => {
	it( 'returns a save() that always returns null when markup is null/undefined', () => {
		const Save = createSave( null );

		expect( Save( { attributes: {} } ) ).toBeNull();
	} );

	it( 'renders the markup tree with interpolated class/attrs on the root', () => {
		const Save = createSave( {
			tag: 'div',
			class: 'acme-callout',
			attrs: { 'data-open': '{{ attributes.is_open }}' },
			text: '{{ attributes.heading }}',
		} );

		const element = Save( { attributes: { is_open: true, heading: 'Hi' } } );

		expect( element.type ).toBe( 'div' );
		expect( element.props.className ).toContain( 'acme-callout' );
		expect( element.props[ 'data-open' ] ).toBe( '1' );
		expect( element.props.children ).toBe( 'Hi' );
	} );

	it( 'renders an "inner_blocks" slot as <InnerBlocks.Content />, not RawHTML', () => {
		const Save = createSave( { tag: 'div', slot: 'inner_blocks' } );

		const element = Save( { attributes: {} } );
		const slotChild = element.props.children;

		// InnerBlocks.Content is a distinct component reference from RawHTML;
		// asserting its displayName/name (rather than importing it directly
		// here, which would just re-assert object identity trivially) proves
		// save() reached for the real WP component, not a leftover RawHTML
		// string-injection path meant for the editor preview.
		expect( slotChild.type.name || slotChild.type.displayName ).toMatch(
			/Content/
		);
	} );

	it( 'a root "when" that fails saves an empty wrapper, not the content', () => {
		const Save = createSave( {
			tag: 'div',
			text: 'Hi',
			when: {
				relation: 'AND',
				rules: [ { field: 'is_open', operator: '==', value: true } ],
			},
		} );

		const element = Save( { attributes: { is_open: false } } );

		expect( element.type ).toBe( 'div' );
		expect( element.props.children ).toBeUndefined();
	} );
} );
