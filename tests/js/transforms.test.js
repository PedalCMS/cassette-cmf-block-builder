/**
 * transforms.js test.
 */

import { buildTransforms } from '../../src/assets/src/editor/transforms';

describe( 'buildTransforms', () => {
	it( 'returns undefined when no "transforms" config is declared', () => {
		expect( buildTransforms( { name: 'acme/callout' } ) ).toBeUndefined();
	} );

	it( 'returns undefined when "from"/"to" are both empty', () => {
		expect(
			buildTransforms( { name: 'acme/callout', transforms: {} } )
		).toBeUndefined();
	} );

	it( 'builds a "from" transform that creates THIS block, mapping the matched block\'s attributes', () => {
		const built = buildTransforms( {
			name: 'acme/callout',
			transforms: {
				from: [
					{
						type: 'block',
						blocks: [ 'core/paragraph' ],
						map: { heading: 'content' },
					},
				],
			},
		} );

		expect( built.from ).toHaveLength( 1 );
		expect( built.from[ 0 ].type ).toBe( 'block' );
		expect( built.from[ 0 ].blocks ).toEqual( [ 'core/paragraph' ] );

		const result = built.from[ 0 ].transform( { content: 'Hi there' } );

		expect( result ).toEqual( {
			name: 'acme/callout',
			attributes: { heading: 'Hi there' },
		} );
	} );

	it( 'builds a "to" transform that creates the TARGET block, mapping this block\'s own attributes', () => {
		const built = buildTransforms( {
			name: 'acme/callout',
			transforms: {
				to: [
					{
						type: 'block',
						blocks: [ 'core/paragraph' ],
						map: { content: 'heading' },
					},
				],
			},
		} );

		expect( built.to ).toHaveLength( 1 );

		const result = built.to[ 0 ].transform( { heading: 'Hi there' } );

		expect( result ).toEqual( {
			name: 'core/paragraph',
			attributes: { content: 'Hi there' },
		} );
	} );

	it( 'drops an entry with an unsupported "type" (e.g. "raw"/"shortcode") rather than guessing', () => {
		const built = buildTransforms( {
			name: 'acme/callout',
			transforms: {
				from: [ { type: 'raw', isMatch: () => true } ],
			},
		} );

		expect( built ).toBeUndefined();
	} );

	it( 'a source attribute missing from the matched block is simply omitted, not set to undefined', () => {
		const built = buildTransforms( {
			name: 'acme/callout',
			transforms: {
				from: [
					{
						type: 'block',
						blocks: [ 'core/paragraph' ],
						map: { heading: 'content' },
					},
				],
			},
		} );

		const result = built.from[ 0 ].transform( {} );

		expect( result.attributes ).toEqual( {} );
	} );
} );
