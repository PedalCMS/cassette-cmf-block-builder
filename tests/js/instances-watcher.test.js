/**
 * instances/watcher.js — the wiring around the pure reducers: one
 * subscription, coalesced passes, non-persistent writes, the isIdInUse flow.
 */

import {
	isEditorReady,
	startInstanceWatcher,
} from '../../src/assets/src/editor/instances/watcher';
import {
	isIdInUse,
	registerIdInUseCheck,
	resetIdInUseChecks,
} from '../../src/assets/src/editor/instances/id-in-use';
import {
	hasConflict,
	setConflicts,
	subscribeConflicts,
} from '../../src/assets/src/editor/unique/conflicts';

const payload = {
	blocks: {
		'acme/item': {
			name: 'acme/item',
			fields: [
				{ isContainer: false, type: 'uuid', config: { name: 'instanceId' } },
			],
		},
		'acme/field': {
			name: 'acme/field',
			fields: [
				{
					isContainer: false,
					type: 'text',
					config: {
						name: 'name',
						unique: { scope: 'post', key: 'name' },
					},
				},
			],
		},
	},
};

/**
 * A fake block-editor registry whose `getBlocks()` returns a new array
 * reference only when the tree is replaced, like the real memoized selector.
 *
 * @param {Array} initial Initial root blocks.
 * @return {Object} Harness.
 */
function createHarness( initial ) {
	let blocks = initial;
	const listeners = [];
	const log = [];
	const frames = [];
	let ready = true;

	const store = {
		getBlocks: jest.fn( ( rootClientId ) =>
			rootClientId ? null : blocks
		),
		getBlockAttributes: ( clientId ) => {
			const find = ( list ) => {
				for ( const block of list ) {
					if ( block.clientId === clientId ) {
						return block.attributes;
					}
					const inner = find( block.innerBlocks || [] );
					if ( inner ) {
						return inner;
					}
				}
				return null;
			};
			return find( blocks );
		},
	};
	const actions = {
		__unstableMarkNextChangeAsNotPersistent: () =>
			log.push( 'not-persistent' ),
		updateBlockAttributes: ( clientId, attributes ) => {
			log.push( [ clientId, attributes ] );
			const patch = ( list ) =>
				list.map( ( block ) => ( {
					...block,
					attributes:
						block.clientId === clientId
							? { ...block.attributes, ...attributes }
							: block.attributes,
					innerBlocks: patch( block.innerBlocks || [] ),
				} ) );
			harness.setBlocks( patch( blocks ) );
		},
	};
	const harness = {
		log,
		store,
		select: ( name ) => {
			if ( 'core/block-editor' === name ) {
				return store;
			}
			return 'core/editor' === name
				? { __unstableIsEditorReady: () => ready }
				: undefined;
		},
		dispatch: () => actions,
		subscribe: ( listener ) => {
			listeners.push( listener );
			return () => listeners.splice( listeners.indexOf( listener ), 1 );
		},
		schedule: ( fn ) => frames.push( fn ),
		setReady: ( value ) => {
			ready = value;
		},
		setBlocks: ( next ) => {
			blocks = next;
			listeners.slice().forEach( ( l ) => l() );
		},
		flush: () => {
			while ( frames.length ) {
				frames.shift()();
			}
		},
		frames,
		listeners,
	};
	return harness;
}

const block = ( clientId, name, attributes = {}, innerBlocks = [] ) => ( {
	clientId,
	name,
	attributes,
	innerBlocks,
} );

let n = 0;
const generateId = () => `new-${ ++n }`;
const flushPromises = () => new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

beforeEach( () => {
	n = 0;
	resetIdInUseChecks();
	setConflicts( {} );
} );

describe( 'startInstanceWatcher', () => {
	it( 'does not subscribe at all when no block has a uuid or unique field', () => {
		const h = createHarness( [] );
		const stop = startInstanceWatcher(
			{ blocks: { 'acme/x': { name: 'acme/x', fields: [] } } },
			h
		);

		expect( h.listeners ).toHaveLength( 0 );
		expect( () => stop() ).not.toThrow();
	} );

	it( 'uses exactly one subscription for the whole library', () => {
		const h = createHarness( [] );
		startInstanceWatcher( payload, { ...h, generateId } );

		expect( h.listeners ).toHaveLength( 1 );
	} );

	it( 'leaves loaded content untouched, then generates an id for an inserted block in the user\'s undo step', () => {
		const h = createHarness( [ block( 'a', 'acme/item', { instanceId: '' } ) ] );
		startInstanceWatcher( payload, { ...h, generateId } );
		h.flush();

		expect( h.log ).toEqual( [] );

		h.setBlocks( [
			block( 'a', 'acme/item', { instanceId: '' } ),
			block( 'b', 'acme/item', { instanceId: '' } ),
		] );
		h.flush();

		expect( h.log ).toEqual( [
			'not-persistent',
			[ 'b', { instanceId: 'new-1' } ],
		] );
	} );

	it( 'regenerates the id of a duplicate and does not loop afterwards', () => {
		const h = createHarness( [ block( 'a', 'acme/item', { instanceId: 'x' } ) ] );
		startInstanceWatcher( payload, { ...h, generateId } );
		h.flush();

		h.setBlocks( [
			block( 'a', 'acme/item', { instanceId: 'x' } ),
			block( 'a2', 'acme/item', { instanceId: 'x' } ),
		] );
		h.flush();
		h.flush();

		expect( h.log ).toEqual( [
			'not-persistent',
			[ 'a2', { instanceId: 'new-1' } ],
		] );
	} );

	it( 'does no work when the block tree reference has not changed, and coalesces bursts', () => {
		const h = createHarness( [ block( 'a', 'acme/item', { instanceId: 'x' } ) ] );
		startInstanceWatcher( payload, { ...h, generateId } );
		h.flush();

		const scheduledBefore = h.frames.length;
		// Many unrelated store changes, same tree reference.
		for ( let i = 0; i < 50; i++ ) {
			h.listeners.forEach( ( l ) => l() );
		}
		expect( h.frames.length ).toBe( scheduledBefore );

		// A burst of tree changes before the frame runs: one pass.
		h.setBlocks( [ block( 'a', 'acme/item', { instanceId: 'x' } ) ] );
		h.setBlocks( [ block( 'a', 'acme/item', { instanceId: 'x' } ) ] );
		h.setBlocks( [ block( 'a', 'acme/item', { instanceId: 'x' } ) ] );
		expect( h.frames ).toHaveLength( 1 );
	} );

	it( 'waits for the editor to be ready before taking the baseline', () => {
		const h = createHarness( [] );
		h.setReady( false );
		startInstanceWatcher( payload, { ...h, generateId } );
		h.flush();

		// Content arrives while not ready: still loaded content afterwards.
		h.setBlocks( [ block( 'a', 'acme/item', { instanceId: '' } ) ] );
		h.flush();
		expect( h.log ).toEqual( [] );

		h.setReady( true );
		h.setBlocks( [ block( 'a', 'acme/item', { instanceId: '' } ) ] );
		h.flush();
		expect( h.log ).toEqual( [] );

		h.setBlocks( [
			block( 'a', 'acme/item', { instanceId: '' } ),
			block( 'b', 'acme/item', { instanceId: '' } ),
		] );
		h.flush();
		expect( h.log ).toEqual( [ 'not-persistent', [ 'b', { instanceId: 'new-1' } ] ] );
	} );

	it( 'publishes unique conflicts to the store used by the controls', () => {
		const h = createHarness( [
			block( 'a', 'acme/field', { name: 'x' } ),
			block( 'b', 'acme/field', { name: 'x' } ),
		] );
		const listener = jest.fn();
		const off = subscribeConflicts( listener );
		startInstanceWatcher( payload, { ...h, generateId } );
		h.flush();

		expect( hasConflict( 'b', 'name' ) ).toBe( true );
		expect( hasConflict( 'a', 'name' ) ).toBe( false );
		expect( listener ).toHaveBeenCalledTimes( 1 );

		// Re-publishing an identical map does not notify again.
		setConflicts( { b: [ 'name' ] } );
		expect( listener ).toHaveBeenCalledTimes( 1 );
		off();
	} );

	it( 'stops cleanly', () => {
		const h = createHarness( [] );
		const stop = startInstanceWatcher( payload, { ...h, generateId } );
		stop();

		expect( h.listeners ).toHaveLength( 0 );
	} );
} );

describe( 'isIdInUse flow', () => {
	const setup = () => {
		const h = createHarness( [ block( 'a', 'acme/item', { instanceId: 'x' } ) ] );
		const idInUse = jest.fn( isIdInUse );
		startInstanceWatcher( payload, { ...h, generateId, idInUse } );
		h.flush();
		h.setBlocks( [
			block( 'a', 'acme/item', { instanceId: 'x' } ),
			block( 'p', 'acme/item', { instanceId: 'pasted' } ),
		] );
		h.flush();
		return { h, idInUse };
	};

	it( 'a synchronous true regenerates the pasted copy', async () => {
		registerIdInUseCheck( () => true );
		const { h, idInUse } = setup();
		await flushPromises();

		expect( idInUse ).toHaveBeenCalledWith( 'pasted', 'acme/item' );
		expect( h.log ).toEqual( [
			'not-persistent',
			[ 'p', { instanceId: 'new-1' } ],
		] );
	} );

	it( 'an asynchronous true regenerates the pasted copy', async () => {
		registerIdInUseCheck( () => Promise.resolve( true ) );
		const { h } = setup();
		await flushPromises();

		expect( h.log ).toEqual( [
			'not-persistent',
			[ 'p', { instanceId: 'new-1' } ],
		] );
	} );

	it( 'false keeps the value', async () => {
		registerIdInUseCheck( () => Promise.resolve( false ) );
		const { h } = setup();
		await flushPromises();

		expect( h.log ).toEqual( [] );
	} );

	it( 'a check that throws, or rejects, is ignored (and logged once)', async () => {
		const warn = jest.spyOn( console, 'warn' ).mockImplementation( () => {} );
		registerIdInUseCheck( () => {
			throw new Error( 'boom' );
		} );
		registerIdInUseCheck( () => Promise.reject( new Error( 'nope' ) ) );
		const { h } = setup();
		await flushPromises();

		expect( h.log ).toEqual( [] );
		expect( warn ).toHaveBeenCalledTimes( 1 );
		warn.mockRestore();
	} );

	it( 'does not regenerate when the value changed before the answer arrived', async () => {
		let answer;
		registerIdInUseCheck(
			() =>
				new Promise( ( resolve ) => {
					answer = resolve;
				} )
		);
		const { h } = setup();
		h.setBlocks( [
			block( 'a', 'acme/item', { instanceId: 'x' } ),
			block( 'p', 'acme/item', { instanceId: 'changed' } ),
		] );
		answer( true );
		await flushPromises();

		expect( h.log ).toEqual( [] );
	} );
} );

describe( 'isEditorReady', () => {
	const make = ( editor, blockEditorBlocks = [] ) => ( name ) =>
		'core/editor' === name
			? editor
			: { getBlocks: () => blockEditorBlocks };

	it( 'is true when there is no core/editor store', () => {
		expect( isEditorReady( () => undefined ) ).toBe( true );
	} );

	it( 'follows __unstableIsEditorReady()', () => {
		expect(
			isEditorReady( make( { __unstableIsEditorReady: () => false } ) )
		).toBe( false );
		expect(
			isEditorReady( make( { __unstableIsEditorReady: () => true } ) )
		).toBe( true );
	} );

	it( 'waits until the block-editor store mirrors a non-empty document', () => {
		const editor = {
			__unstableIsEditorReady: () => true,
			getEditorBlocks: () => [ {} ],
		};

		expect( isEditorReady( make( editor, [] ) ) ).toBe( false );
		expect( isEditorReady( make( editor, [ {} ] ) ) ).toBe( true );
	} );

	it( 'is ready for an empty document', () => {
		expect(
			isEditorReady(
				make( {
					__unstableIsEditorReady: () => true,
					getEditorBlocks: () => [],
				} )
			)
		).toBe( true );
	} );
} );

describe( 'two bundles watching one block-editor store', () => {
	const otherPayload = {
		blocks: {
			'beta/item': {
				name: 'beta/item',
				fields: [
					{
						isContainer: false,
						type: 'uuid',
						config: { name: 'instanceId' },
					},
				],
			},
		},
	};

	it( 'each watcher touches only the block names of its own payload', () => {
		const h = createHarness( [] );
		const stopA = startInstanceWatcher( payload, { ...h, generateId } );
		const stopB = startInstanceWatcher( otherPayload, {
			...h,
			generateId: () => 'beta-id',
		} );
		h.flush();

		// One subscription per bundle; both see every change.
		expect( h.listeners ).toHaveLength( 2 );

		// A block of the other bundle appears: only its own watcher writes.
		h.setBlocks( [ block( 'b', 'beta/item', { instanceId: '' } ) ] );
		h.flush();

		expect( h.log ).toEqual( [
			'not-persistent',
			[ 'b', { instanceId: 'beta-id' } ],
		] );

		// And a block of this bundle is left alone by the other watcher.
		h.log.length = 0;
		h.setBlocks( [
			block( 'b', 'beta/item', { instanceId: 'beta-id' } ),
			block( 'a', 'acme/item', { instanceId: '' } ),
		] );
		h.flush();

		expect( h.log ).toEqual( [
			'not-persistent',
			[ 'a', { instanceId: 'new-1' } ],
		] );

		stopA();
		stopB();
	} );
} );
