/**
 * register-block.js test — specifically the save() selection logic, since
 * this is where a real bug lived: a dynamic block with "inner_blocks"
 * enabled was unconditionally given "save: () => null", which discards
 * every child block a user adds the moment the post is saved (WordPress
 * derives a block's own serialized inner content from what save() actually
 * emits, not from the editor's live innerBlocks state) — not merely hidden
 * from the front end, genuinely never persisted to post_content. See
 * docs/block-config-reference.md's "inner_blocks" section.
 */

import * as wpBlocks from '@wordpress/blocks';
import { registerBlocks } from '../../src/assets/src/editor/register-block';

function descriptorFor( overrides = {} ) {
	return {
		name: 'acme/test-block',
		settings: { title: 'Test block' },
		render: { mode: 'dynamic' },
		...overrides,
	};
}

function savedSettingsFor( descriptor ) {
	const spy = jest.spyOn( wpBlocks, 'registerBlockType' );
	registerBlocks( { blocks: { [ descriptor.name ]: descriptor } } );
	const settings = spy.mock.calls[ 0 ][ 1 ];
	spy.mockRestore();
	return settings;
}

describe( 'registerBlocks — save() selection', () => {
	it( 'a dynamic block with no inner_blocks saves null', () => {
		const settings = savedSettingsFor( descriptorFor() );

		expect( settings.save( { attributes: {} } ) ).toBeNull();
	} );

	it( 'a dynamic block WITH inner_blocks enabled saves <InnerBlocks.Content />, not null', () => {
		const settings = savedSettingsFor(
			descriptorFor( { innerBlocks: { enabled: true } } )
		);

		const element = settings.save( { attributes: {} } );

		expect( element ).not.toBeNull();
		expect( element.type.name || element.type.displayName ).toMatch(
			/Content/
		);
	} );

	it( 'a static block still uses the real markup-serializing save(), even with inner_blocks enabled', () => {
		const settings = savedSettingsFor(
			descriptorFor( {
				render: {
					mode: 'static',
					markup: { tag: 'div', slot: 'inner_blocks' },
				},
				innerBlocks: { enabled: true },
			} )
		);

		const element = settings.save( { attributes: {} } );

		expect( element.type ).toBe( 'div' );
		const slotChild = element.props.children;
		expect( slotChild.type.name || slotChild.type.displayName ).toMatch(
			/Content/
		);
	} );

	it( 'a dynamic block with inner_blocks explicitly disabled still saves null', () => {
		const settings = savedSettingsFor(
			descriptorFor( { innerBlocks: { enabled: false } } )
		);

		expect( settings.save( { attributes: {} } ) ).toBeNull();
	} );
} );
