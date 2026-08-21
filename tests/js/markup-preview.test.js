/**
 * preview/MarkupPreview.js test.
 */

import MarkupPreview from '../../src/assets/src/editor/preview/MarkupPreview';

describe( 'MarkupPreview', () => {
	it( 'renders the root tag with interpolated class/attrs and its content', () => {
		const element = MarkupPreview( {
			markup: {
				tag: 'div',
				class: 'acme-callout',
				text: '{{ attributes.heading }}',
			},
			attributes: { heading: 'Hi' },
		} );

		expect( element.type ).toBe( 'div' );
		expect( element.props.className ).toBe( 'acme-callout' );
		expect( element.props.children ).toBe( 'Hi' );
	} );

	it( 'a root "when" that fails renders an empty wrapper', () => {
		const element = MarkupPreview( {
			markup: {
				tag: 'div',
				text: 'Hi',
				when: {
					relation: 'AND',
					rules: [ { field: 'is_open', operator: '==', value: true } ],
				},
			},
			attributes: { is_open: false },
		} );

		expect( element.type ).toBe( 'div' );
		expect( element.props.children ).toBeUndefined();
	} );

	it( 'renders a real <InnerBlocks /> for a "slot" node when innerBlocksConfig.enabled', () => {
		const element = MarkupPreview( {
			markup: { tag: 'div', slot: 'inner_blocks' },
			attributes: {},
			innerBlocksConfig: {
				enabled: true,
				allowed: [ 'core/paragraph' ],
			},
		} );

		const slotChild = element.props.children;
		expect( slotChild.type.name ).toBe( 'InnerBlocks' );
		expect( slotChild.props.allowedBlocks ).toEqual( [ 'core/paragraph' ] );
	} );

	it( 'falls back to the default RawHTML placeholder when innerBlocksConfig is disabled/absent', () => {
		const element = MarkupPreview( {
			markup: { tag: 'div', slot: 'inner_blocks' },
			attributes: {},
		} );

		const slotChild = element.props.children;
		expect( slotChild.type.name ).not.toBe( 'InnerBlocks' );
	} );
} );
