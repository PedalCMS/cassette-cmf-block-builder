/**
 * instances/reducer.js — the pure rules behind "uuid" and "unique".
 */

import {
	createInitialState,
	reduceInstances,
} from '../../src/assets/src/editor/instances/reducer';
import { collectInstances } from '../../src/assets/src/editor/instances/collect';
import {
	buildRegistry,
	collectTrackedFields,
} from '../../src/assets/src/editor/instances/registry';

const registry = {
	'acme/item': { uuid: [ 'instanceId' ], unique: [] },
	'acme/field': {
		uuid: [ 'fieldId' ],
		unique: [
			{
				name: 'name',
				rule: { scope: 'ancestor', block: 'acme/form', key: 'name' },
				format: 'slug',
			},
		],
	},
	'acme/plain': {
		uuid: [],
		unique: [
			{
				name: 'handle',
				rule: { scope: 'post', key: 'handle' },
				format: undefined,
			},
		],
	},
};

let counter = 0;
const generateId = () => `gen-${ ++counter }`;

beforeEach( () => {
	counter = 0;
} );

const e = ( clientId, blockName, attributes = {}, ancestors = [] ) => ( {
	clientId,
	blockName,
	attributes,
	ancestors,
} );
const item = ( clientId, instanceId, ancestors = [] ) =>
	e( clientId, 'acme/item', { instanceId }, ancestors );
const form = ( clientId ) => ( { clientId, blockName: 'acme/form' } );

/**
 * Run ticks, applying each tick's updates to the next tick's entries the way
 * the editor would.
 *
 * @param {Object} state   State.
 * @param {Array}  entries Entries.
 * @param {Object} options Reducer options.
 * @return {Object} The reducer result plus the entries with updates applied.
 */
function tick( state, entries, options = {} ) {
	const result = reduceInstances( state, entries, {
		registry,
		generateId,
		...options,
	} );
	const applied = entries.map( ( entry ) => {
		const update = result.updates.find(
			( u ) => u.clientId === entry.clientId
		);
		return update
			? {
					...entry,
					attributes: { ...entry.attributes, ...update.attributes },
			  }
			: entry;
	} );
	return { ...result, applied };
}

describe( 'uuid: generate on insert, never for loaded content', () => {
	it( 'does nothing while the editor is not ready, and remembers nothing', () => {
		const state = createInitialState();
		const result = reduceInstances( state, [ item( 'a', '' ) ], {
			registry,
			generateId,
			ready: false,
		} );

		expect( result.updates ).toEqual( [] );
		expect( result.state ).toBe( state );
		expect( result.state.initialized ).toBe( false );
	} );

	it( 'leaves loaded content alone, even blocks with empty ids', () => {
		const { updates, idChecks } = tick( createInitialState(), [
			item( 'a', '' ),
			item( 'b', 'id-b' ),
		] );

		expect( updates ).toEqual( [] );
		expect( idChecks ).toEqual( [] );
	} );

	it( 'generates an id for a block inserted after load', () => {
		const first = tick( createInitialState(), [ item( 'a', 'id-a' ) ] );
		const second = tick( first.state, [
			item( 'a', 'id-a' ),
			item( 'b', '' ),
		] );

		expect( second.updates ).toEqual( [
			{ clientId: 'b', attributes: { instanceId: 'gen-1' } },
		] );
	} );

	it( 'does not generate for an old block whose id is empty', () => {
		const first = tick( createInitialState(), [ item( 'a', '' ) ] );
		const second = tick( first.state, [ item( 'a', '' ) ] );

		expect( second.updates ).toEqual( [] );
	} );

	it( 'is a no-op on an unchanged tree (no updates, no checks)', () => {
		const entries = [ item( 'a', 'id-a' ), item( 'b', 'id-b' ) ];
		const first = tick( createInitialState(), entries );
		const second = tick( first.state, entries );
		const third = tick( second.state, entries );

		expect( second.updates ).toEqual( [] );
		expect( third.updates ).toEqual( [] );
		expect( third.idChecks ).toEqual( [] );
	} );
} );

describe( 'uuid: regenerate on duplicate value', () => {
	const loaded = () =>
		tick( createInitialState(), [ item( 'a', 'id-a' ), item( 'b', 'id-b' ) ] );

	it( 'a duplicate gets a new id; the original keeps its own', () => {
		const next = tick( loaded().state, [
			item( 'a', 'id-a' ),
			item( 'a2', 'id-a' ),
			item( 'b', 'id-b' ),
		] );

		expect( next.updates ).toEqual( [
			{ clientId: 'a2', attributes: { instanceId: 'gen-1' } },
		] );
	} );

	it( 'the owner keeps the value even when the copy comes first in the document', () => {
		const next = tick( loaded().state, [
			item( 'a2', 'id-a' ),
			item( 'a', 'id-a' ),
			item( 'b', 'id-b' ),
		] );

		expect( next.updates ).toEqual( [
			{ clientId: 'a2', attributes: { instanceId: 'gen-1' } },
		] );
	} );

	it( 'a nested duplicate (inside a copied container) is fixed at any depth', () => {
		const nested = tick( createInitialState(), [
			item( 'a', 'id-a', [ form( 'f' ) ] ),
		] );
		const next = tick( nested.state, [
			item( 'a', 'id-a', [ form( 'f' ) ] ),
			item( 'a2', 'id-a', [ form( 'f2' ) ] ),
		] );

		expect( next.updates ).toEqual( [
			{ clientId: 'a2', attributes: { instanceId: 'gen-1' } },
		] );
	} );

	it( 'two copies pasted at once with an owner: both get new ids', () => {
		const next = tick( loaded().state, [
			item( 'a', 'id-a' ),
			item( 'p1', 'id-a' ),
			item( 'p2', 'id-a' ),
			item( 'b', 'id-b' ),
		] );

		expect( next.updates.map( ( u ) => u.clientId ) ).toEqual( [
			'p1',
			'p2',
		] );
		expect( new Set( next.updates.map( ( u ) => u.attributes.instanceId ) ).size ).toBe( 2 );
	} );

	it( 'two copies pasted at once and nobody owned the value: the first in document order keeps it', () => {
		const next = tick( loaded().state, [
			item( 'a', 'id-a' ),
			item( 'p1', 'id-x' ),
			item( 'p2', 'id-x' ),
		] );

		expect( next.updates ).toEqual( [
			{ clientId: 'p2', attributes: { instanceId: 'gen-1' } },
		] );
	} );

	it( 'a regenerated id never collides with an existing one', () => {
		const next = tick( loaded().state, [
			item( 'a', 'id-a' ),
			item( 'a2', 'id-a' ),
			item( 'b', 'gen-1' ),
		] );

		expect( next.updates[ 0 ].attributes.instanceId ).toBe( 'gen-2' );
	} );

	it( 'is stable: after the fix is applied the next tick does nothing', () => {
		const dup = tick( loaded().state, [
			item( 'a', 'id-a' ),
			item( 'a2', 'id-a' ),
		] );
		const settled = tick( dup.state, dup.applied );

		expect( settled.updates ).toEqual( [] );
	} );
} );

describe( 'uuid: undo/redo, moves and transforms never change an id', () => {
	it( 'undo of a duplicate removes the copy and leaves the original alone', () => {
		const loaded = tick( createInitialState(), [ item( 'a', 'id-a' ) ] );
		const dup = tick( loaded.state, [
			item( 'a', 'id-a' ),
			item( 'a2', 'id-a' ),
		] );
		// The fix-up merged into the duplicate's undo step; undo removes the copy.
		const undone = tick( dup.state, [ item( 'a', 'id-a' ) ] );

		expect( undone.updates ).toEqual( [] );
	} );

	it( 'redo of the duplicate brings back the fixed copy without another change', () => {
		const loaded = tick( createInitialState(), [ item( 'a', 'id-a' ) ] );
		const dup = tick( loaded.state, [
			item( 'a', 'id-a' ),
			item( 'a2', 'id-a' ),
		] );
		const undone = tick( dup.state, [ item( 'a', 'id-a' ) ] );
		const redone = tick( undone.state, dup.applied );

		expect( redone.updates ).toEqual( [] );
		expect( redone.idChecks ).toEqual( [] );
	} );

	it( 'deleting a block and undoing the delete (same clientId) is not a duplicate or a new block', () => {
		const loaded = tick( createInitialState(), [
			item( 'a', 'id-a' ),
			item( 'b', 'id-b' ),
		] );
		const deleted = tick( loaded.state, [ item( 'b', 'id-b' ) ] );
		const undone = tick( deleted.state, [
			item( 'a', 'id-a' ),
			item( 'b', 'id-b' ),
		] );

		expect( undone.updates ).toEqual( [] );
		expect( undone.idChecks ).toEqual( [] );
	} );

	it( 'moving a block (reordering) changes nothing', () => {
		const loaded = tick( createInitialState(), [
			item( 'a', 'id-a' ),
			item( 'b', 'id-b' ),
		] );
		const moved = tick( loaded.state, [
			item( 'b', 'id-b' ),
			item( 'a', 'id-a' ),
		] );

		expect( moved.updates ).toEqual( [] );
	} );

	it( 'moving a block into another container changes nothing', () => {
		const loaded = tick( createInitialState(), [ item( 'a', 'id-a' ) ] );
		const moved = tick( loaded.state, [
			item( 'a', 'id-a', [ form( 'f' ) ] ),
		] );

		expect( moved.updates ).toEqual( [] );
	} );

	it( 'a transform that moves the value to a new clientId keeps the value and skips the consumer check', () => {
		const loaded = tick( createInitialState(), [ item( 'a', 'id-a' ) ] );
		const transformed = tick( loaded.state, [ item( 'a-new', 'id-a' ) ] );

		expect( transformed.updates ).toEqual( [] );
		expect( transformed.idChecks ).toEqual( [] );
	} );
} );

describe( 'uuid: the isIdInUse hook is only asked about new blocks that kept a value', () => {
	it( 'reports a pasted block with a value nobody here owns', () => {
		const loaded = tick( createInitialState(), [ item( 'a', 'id-a' ) ] );
		const pasted = tick( loaded.state, [
			item( 'a', 'id-a' ),
			item( 'p', 'id-from-another-post' ),
		] );

		expect( pasted.updates ).toEqual( [] );
		expect( pasted.idChecks ).toEqual( [
			{
				clientId: 'p',
				blockName: 'acme/item',
				field: 'instanceId',
				value: 'id-from-another-post',
			},
		] );
	} );

	it( 'does not ask about a duplicate (it is regenerated anyway) or about loaded blocks', () => {
		const loaded = tick( createInitialState(), [ item( 'a', 'id-a' ) ] );
		expect( loaded.idChecks ).toEqual( [] );

		const dup = tick( loaded.state, [
			item( 'a', 'id-a' ),
			item( 'a2', 'id-a' ),
		] );
		expect( dup.idChecks ).toEqual( [] );
	} );

	it( 'asks only once: the next tick does not ask again', () => {
		const loaded = tick( createInitialState(), [ item( 'a', 'id-a' ) ] );
		const pasted = tick( loaded.state, [
			item( 'a', 'id-a' ),
			item( 'p', 'x' ),
		] );
		const again = tick( pasted.state, [
			item( 'a', 'id-a' ),
			item( 'p', 'x' ),
		] );

		expect( again.idChecks ).toEqual( [] );
	} );
} );

describe( 'unique: flags and auto-suffix', () => {
	const fieldIn = ( clientId, name, formId = 'f1' ) =>
		e( clientId, 'acme/field', { fieldId: `uid-${ clientId }`, name }, [
			form( formId ),
		] );

	it( 'flags loaded duplicates on every block except the oldest owner, without changing anything', () => {
		const result = tick( createInitialState(), [
			fieldIn( 'a', 'email' ),
			fieldIn( 'b', 'email' ),
			fieldIn( 'c', 'email' ),
		] );

		expect( result.updates ).toEqual( [] );
		expect( result.conflicts ).toEqual( {
			b: [ 'name' ],
			c: [ 'name' ],
		} );
	} );

	it( 'flags a block whose value is edited into a collision, but not the older one', () => {
		const loaded = tick( createInitialState(), [
			fieldIn( 'a', 'email' ),
			fieldIn( 'b', 'phone' ),
		] );
		const edited = tick( loaded.state, [
			fieldIn( 'a', 'email' ),
			fieldIn( 'b', 'email' ),
		] );

		expect( edited.updates ).toEqual( [] );
		expect( edited.conflicts ).toEqual( { b: [ 'name' ] } );
	} );

	it( 'the oldest block stays the owner even when a later block sits earlier in the document', () => {
		const loaded = tick( createInitialState(), [ fieldIn( 'a', 'email' ) ] );
		const edited = tick( loaded.state, [
			fieldIn( 'z', 'phone' ),
			fieldIn( 'a', 'email' ),
		] );
		const collide = tick( edited.state, [
			fieldIn( 'z', 'email' ),
			fieldIn( 'a', 'email' ),
		] );

		expect( collide.conflicts ).toEqual( { z: [ 'name' ] } );
	} );

	it( 'auto-suffixes a duplicated block with the first free -2, -3', () => {
		const loaded = tick( createInitialState(), [ fieldIn( 'a', 'email' ) ] );
		const dup1 = tick( loaded.state, [
			fieldIn( 'a', 'email' ),
			fieldIn( 'a2', 'email' ),
		] );

		expect( dup1.updates ).toEqual( [
			{ clientId: 'a2', attributes: { name: 'email-2' } },
		] );
		expect( dup1.conflicts ).toEqual( {} );

		const dup2 = tick( dup1.state, [
			...dup1.applied,
			fieldIn( 'a3', 'email' ),
		] );

		expect( dup2.updates ).toEqual( [
			{ clientId: 'a3', attributes: { name: 'email-3' } },
		] );
	} );

	it( 'never renames the original', () => {
		const loaded = tick( createInitialState(), [ fieldIn( 'a', 'email' ) ] );
		const dup = tick( loaded.state, [
			fieldIn( 'a2', 'email' ),
			fieldIn( 'a', 'email' ),
		] );

		expect( dup.updates.map( ( u ) => u.clientId ) ).toEqual( [ 'a2' ] );
	} );

	it( 'applies the field format when suffixing', () => {
		const loaded = tick( createInitialState(), [
			fieldIn( 'a', 'my-name' ),
		] );
		const dup = tick( loaded.state, [
			fieldIn( 'a', 'my-name' ),
			fieldIn( 'a2', 'My Name' ),
		] );

		expect( dup.updates ).toEqual( [
			{ clientId: 'a2', attributes: { name: 'my-name-2' } },
		] );
	} );

	it( 'a copy pasted into a different form is not a collision', () => {
		const loaded = tick( createInitialState(), [ fieldIn( 'a', 'email' ) ] );
		const pasted = tick( loaded.state, [
			fieldIn( 'a', 'email' ),
			fieldIn( 'p', 'email', 'f2' ),
		] );

		expect( pasted.updates ).toEqual( [] );
		expect( pasted.conflicts ).toEqual( {} );
	} );

	it( 'is stable after the suffix is applied', () => {
		const loaded = tick( createInitialState(), [ fieldIn( 'a', 'email' ) ] );
		const dup = tick( loaded.state, [
			fieldIn( 'a', 'email' ),
			fieldIn( 'a2', 'email' ),
		] );
		const settled = tick( dup.state, dup.applied );

		expect( settled.updates ).toEqual( [] );
		expect( settled.conflicts ).toEqual( {} );
	} );

	it( 'undo of an auto-suffixed duplicate leaves no flag and no change', () => {
		const loaded = tick( createInitialState(), [ fieldIn( 'a', 'email' ) ] );
		const dup = tick( loaded.state, [
			fieldIn( 'a', 'email' ),
			fieldIn( 'a2', 'email' ),
		] );
		const undone = tick( dup.state, [ fieldIn( 'a', 'email' ) ] );

		expect( undone.updates ).toEqual( [] );
		expect( undone.conflicts ).toEqual( {} );
	} );

	it( 'groups by key across block types and handles a whole-post rule', () => {
		const loaded = tick( createInitialState(), [
			e( 'p1', 'acme/plain', { handle: 'x' } ),
		] );
		const dup = tick( loaded.state, [
			e( 'p1', 'acme/plain', { handle: 'x' } ),
			e( 'p2', 'acme/plain', { handle: 'x' } ),
		] );

		expect( dup.updates ).toEqual( [
			{ clientId: 'p2', attributes: { handle: 'x-2' } },
		] );
	} );

	it( 'a uuid regenerate and a unique suffix on the same duplicated block both apply', () => {
		const loaded = tick( createInitialState(), [
			e( 'a', 'acme/field', { fieldId: 'u1', name: 'email' }, [ form( 'f' ) ] ),
		] );
		const dup = tick( loaded.state, [
			e( 'a', 'acme/field', { fieldId: 'u1', name: 'email' }, [ form( 'f' ) ] ),
			e( 'a2', 'acme/field', { fieldId: 'u1', name: 'email' }, [ form( 'f' ) ] ),
		] );

		expect( dup.updates ).toEqual( [
			{
				clientId: 'a2',
				attributes: { fieldId: 'gen-1', name: 'email-2' },
			},
		] );
	} );
} );

describe( 'collectInstances', () => {
	const tree = [
		{
			clientId: 'form1',
			name: 'acme/form',
			attributes: {},
			innerBlocks: [
				{
					clientId: 'g',
					name: 'core/group',
					attributes: {},
					innerBlocks: [
						{
							clientId: 'i1',
							name: 'acme/item',
							attributes: { instanceId: 'x' },
							innerBlocks: [],
						},
					],
				},
				{
					clientId: 'i2',
					name: 'acme/item',
					attributes: { instanceId: 'y' },
					innerBlocks: [],
				},
			],
		},
		{ clientId: 'ref', name: 'core/block', attributes: {}, innerBlocks: [] },
	];

	it( 'flattens nested blocks in document order with their ancestor chains', () => {
		const entries = collectInstances( tree, registry, null );

		expect( entries.map( ( x ) => x.clientId ) ).toEqual( [ 'i1', 'i2' ] );
		expect( entries[ 0 ].ancestors ).toEqual( [
			{ clientId: 'form1', blockName: 'acme/form' },
			{ clientId: 'g', blockName: 'core/group' },
		] );
		expect( entries[ 1 ].ancestors ).toEqual( [
			{ clientId: 'form1', blockName: 'acme/form' },
		] );
	} );

	it( 'follows controlled inner blocks (synced patterns) when innerBlocks is empty', () => {
		const getControlled = ( clientId ) =>
			'ref' === clientId
				? [
						{
							clientId: 'i3',
							name: 'acme/item',
							attributes: { instanceId: 'z' },
							innerBlocks: [],
						},
				  ]
				: null;
		const entries = collectInstances( tree, registry, getControlled );

		expect( entries.map( ( x ) => x.clientId ) ).toEqual( [
			'i1',
			'i2',
			'i3',
		] );
		expect( entries[ 2 ].ancestors[ 0 ].blockName ).toBe( 'core/block' );
	} );
} );

describe( 'registry', () => {
	const nodes = [
		{
			isContainer: true,
			type: 'panel',
			config: { name: 'p' },
			fields: [
				{
					isContainer: false,
					type: 'uuid',
					config: { name: 'instanceId' },
				},
				{
					isContainer: false,
					type: 'text',
					config: {
						name: 'name',
						format: 'slug',
						unique: { scope: 'post', key: 'name' },
					},
				},
				{
					isContainer: true,
					type: 'repeater',
					config: { name: 'rows' },
					fields: [
						{
							isContainer: false,
							type: 'uuid',
							config: { name: 'rowId' },
						},
					],
				},
			],
		},
	];

	it( 'collects uuid and unique fields, skipping repeater rows', () => {
		expect( collectTrackedFields( nodes ) ).toEqual( {
			uuid: [ 'instanceId' ],
			unique: [
				{
					name: 'name',
					rule: { scope: 'post', key: 'name' },
					format: 'slug',
				},
			],
		} );
	} );

	it( 'keeps only block types with something to track', () => {
		const built = buildRegistry( {
			blocks: {
				'acme/a': { name: 'acme/a', fields: nodes },
				'acme/b': {
					name: 'acme/b',
					fields: [
						{ isContainer: false, type: 'text', config: { name: 't' } },
					],
				},
			},
		} );

		expect( Object.keys( built ) ).toEqual( [ 'acme/a' ] );
	} );
} );

describe( 'performance', () => {
	it( 'reduces 300+ nested blocks well inside the time budget', () => {
		// 20 forms x (1 group x 8 items + 8 fields) = 20 x 17 = 340 tracked blocks.
		const build = ( duplicate ) => {
			const entries = [];
			for ( let f = 0; f < 20; f++ ) {
				const ancestors = [ form( `form-${ f }` ), { clientId: `g-${ f }`, blockName: 'core/group' } ];
				for ( let i = 0; i < 8; i++ ) {
					entries.push( item( `i-${ f }-${ i }`, `id-${ f }-${ i }`, ancestors ) );
					entries.push(
						e(
							`fld-${ f }-${ i }`,
							'acme/field',
							{ fieldId: `fid-${ f }-${ i }`, name: `n${ i }` },
							ancestors
						)
					);
				}
			}
			if ( duplicate ) {
				entries.push( item( 'dup', 'id-0-0', [ form( 'form-0' ) ] ) );
			}
			return entries;
		};

		const entries = build( false );
		expect( entries.length ).toBeGreaterThan( 300 );

		const loaded = reduceInstances( createInitialState(), entries, {
			registry,
			generateId,
		} );

		const start = performance.now();
		const unchanged = reduceInstances( loaded.state, entries, {
			registry,
			generateId,
		} );
		const withDuplicate = reduceInstances(
			unchanged.state,
			build( true ),
			{ registry, generateId }
		);
		const elapsed = performance.now() - start;

		expect( unchanged.updates ).toEqual( [] );
		expect( withDuplicate.updates ).toHaveLength( 1 );
		// Two full passes over 340 blocks; the budget is deliberately generous.
		expect( elapsed ).toBeLessThan( 50 );
	} );
} );
