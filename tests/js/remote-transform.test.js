/**
 * Shortcode / raw transforms backed by a PHP callback: wiring in
 * buildTransforms(), the synchronous pending block, and the REST resolution.
 */

import apiFetch from '@wordpress/api-fetch';
import { dispatch, select } from '@wordpress/data';
import {
	buildTransforms,
	flattenShortcodeAttrs,
} from '../../src/assets/src/editor/transforms';
import {
	PENDING_KEY,
	isPending,
} from '../../src/assets/src/editor/remote-transform/remote';
import {
	buildRawMatcher,
	buildRawSchema,
} from '../../src/assets/src/editor/remote-transform/raw';

jest.mock( '@wordpress/blocks', () => {
	let n = 0;
	return {
		createBlock: ( name, attributes = {}, innerBlocks = [] ) => {
			n += 1;
			return { clientId: 'cid-' + n, name, attributes, innerBlocks };
		},
	};
} );

jest.mock( '@wordpress/data', () => ( {
	dispatch: jest.fn(),
	select: jest.fn(),
} ) );

jest.mock( '@wordpress/api-fetch', () => ( {
	__esModule: true,
	default: jest.fn(),
} ) );

const NS = 'acme-blocks/v1';

const DESCRIPTOR = {
	name: 'acme/form',
	transforms: {
		from: [
			{ type: 'block', blocks: [ 'core/paragraph' ], map: { a: 'content' } },
			{ type: 'shortcode', tag: 'contact', remote: true },
			{ type: 'raw', selector: 'div.legacy', remote: true },
		],
	},
};

/** Let queued promise callbacks run. */
const flush = () => new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

let tree;
let blockEditor;
let notices;

beforeEach( () => {
	apiFetch.mockReset();
	tree = [];
	blockEditor = {
		getBlocks: () => tree,
		updateBlockAttributes: jest.fn(),
		replaceInnerBlocks: jest.fn(),
		replaceBlock: jest.fn(),
	};
	notices = { createErrorNotice: jest.fn() };
	select.mockImplementation( ( store ) =>
		'core/block-editor' === store
			? blockEditor
			: { getCurrentPostId: () => 42 }
	);
	dispatch.mockImplementation( ( store ) =>
		'core/block-editor' === store ? blockEditor : notices
	);
} );

describe( 'buildTransforms with remote entries', () => {
	it( 'builds shortcode and raw transforms next to block transforms, keeping list positions', () => {
		const built = buildTransforms( DESCRIPTOR, { restNamespace: NS } );

		expect( built.from.map( ( t ) => t.type ) ).toEqual( [
			'block',
			'shortcode',
			'raw',
		] );
		expect( built.from[ 1 ].tag ).toBe( 'contact' );
		expect( typeof built.from[ 2 ].isMatch ).toBe( 'function' );
	} );

	it( 'drops remote entries when the payload has no REST namespace', () => {
		const built = buildTransforms( DESCRIPTOR );

		expect( built.from.map( ( t ) => t.type ) ).toEqual( [ 'block' ] );
	} );

	it( 'drops incomplete entries', () => {
		const built = buildTransforms(
			{
				name: 'acme/form',
				transforms: {
					from: [
						{ type: 'shortcode' },
						{ type: 'raw' },
					],
				},
			},
			{ restNamespace: NS }
		);

		expect( built ).toBeUndefined();
	} );
} );

describe( 'shortcode transform resolution', () => {
	it( 'returns a pending block at once, then applies the REST result', async () => {
		apiFetch.mockResolvedValue( {
			attributes: { title: 'Hello' },
			innerBlocks: [
				{ name: 'acme/field', attributes: { label: 'A' } },
			],
		} );
		const built = buildTransforms( DESCRIPTOR, { restNamespace: NS } );

		const block = built.from[ 1 ].transform(
			{ named: { id: '7' }, numeric: [ 'x' ] },
			{ content: '[contact id="7" x]' }
		);

		expect( block.name ).toBe( 'acme/form' );
		expect( isPending( block.attributes ) ).toBe( true );
		tree = [ block ];

		await flush();

		expect( apiFetch ).toHaveBeenCalledWith( {
			path: '/acme-blocks/v1/transform',
			method: 'POST',
			data: {
				block: 'acme/form',
				index: 1,
				input: { 0: 'x', id: '7' },
				post_id: 42,
			},
		} );
		expect( blockEditor.updateBlockAttributes ).toHaveBeenCalledWith(
			block.clientId,
			{ title: 'Hello', metadata: undefined }
		);
		const inner = blockEditor.replaceInnerBlocks.mock.calls[ 0 ];
		expect( inner[ 0 ] ).toBe( block.clientId );
		expect( inner[ 1 ][ 0 ] ).toMatchObject( {
			name: 'acme/field',
			attributes: { label: 'A' },
		} );
		expect( isPending( block.attributes ) ).toBe( false );
	} );

	it( 'keeps other metadata and still finds a block whose clientId changed', async () => {
		apiFetch.mockResolvedValue( { attributes: { title: 'T' } } );
		const built = buildTransforms( DESCRIPTOR, { restNamespace: NS } );
		const block = built.from[ 1 ].transform( {}, {} );
		const marker = block.attributes.metadata[ PENDING_KEY ];
		tree = [
			{
				clientId: 'other',
				name: 'acme/form',
				attributes: { metadata: { name: 'My form', [ PENDING_KEY ]: marker } },
				innerBlocks: [],
			},
		];

		await flush();

		expect( blockEditor.updateBlockAttributes ).toHaveBeenCalledWith(
			'other',
			{ title: 'T', metadata: { name: 'My form' } }
		);
	} );

	it( 'does nothing when the block was removed meanwhile', async () => {
		apiFetch.mockResolvedValue( { attributes: { title: 'T' } } );
		const built = buildTransforms( DESCRIPTOR, { restNamespace: NS } );
		built.from[ 1 ].transform( {}, {} );
		tree = [];

		await flush();

		expect( blockEditor.updateBlockAttributes ).not.toHaveBeenCalled();
	} );

	it( 'shows a notice and keeps the original shortcode when the request fails', async () => {
		apiFetch.mockRejectedValue( { message: 'No match', code: 'x' } );
		const built = buildTransforms( DESCRIPTOR, { restNamespace: NS } );
		const block = built.from[ 1 ].transform(
			{ named: {}, numeric: [] },
			{ content: '[contact id="9"]' }
		);
		tree = [ block ];

		await flush();

		expect( notices.createErrorNotice ).toHaveBeenCalledWith(
			'No match',
			expect.any( Object )
		);
		const [ target, fallback ] = blockEditor.replaceBlock.mock.calls[ 0 ];
		expect( target ).toBe( block.clientId );
		expect( fallback ).toMatchObject( {
			name: 'core/shortcode',
			attributes: { text: '[contact id="9"]' },
		} );
		expect( blockEditor.updateBlockAttributes ).not.toHaveBeenCalled();
		expect( isPending( block.attributes ) ).toBe( false );
	} );
} );

describe( 'raw transform resolution', () => {
	it( 'sends the node HTML and falls back to core/html on failure', async () => {
		apiFetch.mockRejectedValue( new Error( 'boom' ) );
		const built = buildTransforms( DESCRIPTOR, { restNamespace: NS } );
		const node = document.createElement( 'div' );
		node.className = 'legacy';
		node.innerHTML = '<b>hi</b>';

		expect( built.from[ 2 ].isMatch( node ) ).toBe( true );

		const block = built.from[ 2 ].transform( node );
		tree = [ block ];
		await flush();

		expect( apiFetch.mock.calls[ 0 ][ 0 ].data ).toMatchObject( {
			index: 2,
			input: '<div class="legacy"><b>hi</b></div>',
		} );
		expect( blockEditor.replaceBlock.mock.calls[ 0 ][ 1 ] ).toMatchObject( {
			name: 'core/html',
			attributes: { content: '<div class="legacy"><b>hi</b></div>' },
		} );
	} );
} );

describe( 'helpers', () => {
	it( 'flattens wp.shortcode attrs', () => {
		expect(
			flattenShortcodeAttrs( { named: { id: '1' }, numeric: [ 'a', 'b' ] } )
		).toEqual( { 0: 'a', 1: 'b', id: '1' } );
		expect( flattenShortcodeAttrs( { id: '1' } ) ).toEqual( { id: '1' } );
		expect( flattenShortcodeAttrs( null ) ).toEqual( {} );
	} );

	it( 'matches raw nodes by selector string and by rule', () => {
		const node = document.createElement( 'div' );
		node.className = 'a b';
		node.setAttribute( 'data-x', '1' );
		const span = document.createElement( 'span' );

		expect( buildRawMatcher( 'div.a' )( node ) ).toBe( true );
		expect( buildRawMatcher( 'div.a' )( span ) ).toBe( false );
		expect( buildRawMatcher( '[' )( node ) ).toBe( false );
		expect(
			buildRawMatcher( { tag: 'div', class: [ 'a', 'b' ], attrs: { 'data-x': 1 } } )( node )
		).toBe( true );
		expect( buildRawMatcher( { tag: 'div', class: 'c' } )( node ) ).toBe( false );
		expect( buildRawMatcher( {} ) ).toBeNull();
		expect( buildRawMatcher( 'div.a' )( document.createTextNode( 'x' ) ) ).toBe( false );
	} );

	it( 'derives a paste schema for tag-based selectors only', () => {
		const schema = buildRawSchema( 'div.legacy' )( { phrasingContentSchema: { b: {} } } );

		expect( schema ).toEqual( {
			div: { attributes: [ 'class' ], classes: [ 'legacy' ], children: { b: {} } },
		} );
		expect( buildRawSchema( '[data-x]' ) ).toBeNull();
	} );
} );
