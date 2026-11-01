import {
	groupEntries,
	nextFreeValue,
	normalizeValue,
} from '../unique/find-collisions';

/**
 * Pure reducers for the instance watcher: given the flat list of tracked
 * blocks in the editor and what the previous tick saw, decide which
 * attributes to write ("uuid" generation and regeneration, "unique"
 * auto-suffixing), which freshly pasted ids to ask the consumer about, and
 * which "unique" fields to flag. No store access, no timers, no randomness
 * of its own (the id generator is injected), so every rule is unit tested.
 *
 * Vocabulary:
 *  - "loaded": present on the first tick after the editor finished loading.
 *    Never touched, so opening an old post never marks it dirty.
 *  - "new": a clientId never seen on an earlier tick (inserted, duplicated,
 *    pasted, or created by a transform).
 *
 * A clientId that reappears through undo/redo was seen before, so it is
 * never "new" and its value is never treated as a duplicate.
 */

const MAX_SEEN = 10000;

/**
 * The state before the first tick.
 *
 * @return {Object} Initial state.
 */
export function createInitialState() {
	return {
		initialized: false,
		/** clientId -> sequence number of first sight (age order). */
		seen: new Map(),
		/** clientIds tracked on the previous tick. */
		present: new Set(),
		/** uuid value -> clientId that owns it. */
		owners: new Map(),
		counter: 0,
	};
}

/**
 * Run one tick.
 *
 * @param {Object}   state                Previous state (not mutated).
 * @param {Array}    entries              Tracked blocks in document order: { clientId, blockName, attributes, ancestors }.
 * @param {Object}   options              Options.
 * @param {Object}   options.registry     Block name -> { uuid: string[], unique: [ { name, rule, format } ] }.
 * @param {Function} options.generateId   () => string, a fresh UUID.
 * @param {boolean}  [options.ready=true] False until the editor has finished loading; nothing happens (and nothing is remembered) while false.
 * @return {{state: Object, updates: Array<{clientId: string, attributes: Object}>, idChecks: Array<{clientId: string, blockName: string, field: string, value: string}>, conflicts: Object}} The next state and what to do.
 */
export function reduceInstances( state, entries, options ) {
	const { registry, generateId, ready = true } = options;

	if ( ! ready ) {
		return { state, updates: [], idChecks: [], conflicts: {} };
	}

	const loadedTick = ! state.initialized;
	const seen = new Map( state.seen );
	let counter = state.counter;
	const isNew = new Set();

	entries.forEach( ( entry ) => {
		if ( ! seen.has( entry.clientId ) ) {
			seen.set( entry.clientId, counter++ );
			if ( ! loadedTick ) {
				isNew.add( entry.clientId );
			}
		}
	} );

	const currentIds = new Set( entries.map( ( entry ) => entry.clientId ) );
	const patches = new Map(); // clientId -> attribute patch
	const patch = ( clientId, attributes ) =>
		patches.set( clientId, { ...patches.get( clientId ), ...attributes } );

	const owners = new Map( state.owners );
	const uuid = reduceUuids( {
		entries,
		registry,
		isNew,
		currentIds,
		owners,
		previousPresent: state.present,
		loadedTick,
		generateId,
	} );
	uuid.patches.forEach( ( attributes, clientId ) =>
		patch( clientId, attributes )
	);

	// Effective entries: what the tree will look like once the patches apply.
	const effective = entries.map( ( entry ) =>
		patches.has( entry.clientId )
			? {
					...entry,
					attributes: {
						...entry.attributes,
						...patches.get( entry.clientId ),
					},
			  }
			: entry
	);

	const unique = reduceUnique( {
		entries: effective,
		registry,
		isNew,
		seen,
		loadedTick,
	} );
	unique.patches.forEach( ( attributes, clientId ) =>
		patch( clientId, attributes )
	);

	// Drop owners whose block is gone, and bound the age map.
	owners.forEach( ( clientId, value ) => {
		if ( ! currentIds.has( clientId ) ) {
			owners.delete( value );
		}
	} );
	while ( seen.size > MAX_SEEN ) {
		seen.delete( seen.keys().next().value );
	}

	return {
		state: {
			initialized: true,
			seen,
			present: currentIds,
			owners,
			counter,
		},
		updates: Array.from( patches, ( [ clientId, attributes ] ) => ( {
			clientId,
			attributes,
		} ) ),
		idChecks: uuid.idChecks,
		conflicts: unique.conflicts,
	};
}

/**
 * The uuid half of a tick. Mutates `owners` (a copy owned by the caller).
 *
 * @param {Object} args Arguments (see reduceInstances()).
 * @return {{patches: Map, idChecks: Array}} Attribute patches and ids to ask the consumer about.
 */
function reduceUuids( args ) {
	const {
		entries,
		registry,
		isNew,
		currentIds,
		owners,
		previousPresent,
		loadedTick,
		generateId,
	} = args;

	const patches = new Map();
	const idChecks = [];
	const holders = new Map(); // value -> [ { entry, field } ]
	const taken = new Set();
	const pending = []; // { entry, field } needing a fresh id

	entries.forEach( ( entry ) => {
		( registry[ entry.blockName ]?.uuid || [] ).forEach( ( field ) => {
			const value = entry.attributes[ field ];

			if ( 'string' !== typeof value || '' === value ) {
				if ( isNew.has( entry.clientId ) ) {
					pending.push( { entry, field } );
				}
				return;
			}

			taken.add( value );
			if ( ! holders.has( value ) ) {
				holders.set( value, [] );
			}
			holders.get( value ).push( { entry, field } );
		} );
	} );

	holders.forEach( ( list, value ) => {
		let keeper = list[ 0 ];

		if ( list.length > 1 ) {
			const ownerId = owners.get( value );
			keeper =
				list.find( ( item ) => item.entry.clientId === ownerId ) ||
				list.find( ( item ) => ! isNew.has( item.entry.clientId ) ) ||
				list[ 0 ];

			list.forEach( ( item ) => {
				if ( item !== keeper ) {
					pending.push( item );
				}
			} );
		}

		const { entry, field } = keeper;
		const previousOwner = owners.get( value );

		// A brand-new block that kept a value nobody else holds may be a paste
		// from another post: let the consumer veto it. Not when the previous
		// owner vanished on this very tick (a transform or replace moved the
		// value to a new clientId), and never for loaded content.
		const moved =
			previousOwner &&
			previousOwner !== entry.clientId &&
			previousPresent.has( previousOwner ) &&
			! currentIds.has( previousOwner );

		if ( ! loadedTick && isNew.has( entry.clientId ) && ! moved ) {
			idChecks.push( {
				clientId: entry.clientId,
				blockName: entry.blockName,
				field,
				value,
			} );
		}

		owners.set( value, entry.clientId );
	} );

	pending.forEach( ( { entry, field } ) => {
		let fresh = generateId();
		while ( taken.has( fresh ) ) {
			fresh = generateId();
		}
		taken.add( fresh );
		owners.set( fresh, entry.clientId );
		patches.set( entry.clientId, {
			...patches.get( entry.clientId ),
			[ field ]: fresh,
		} );
	} );

	return { patches, idChecks };
}

/**
 * The "unique" half of a tick: auto-suffix new colliding blocks, then flag
 * everything that still collides with an older owner.
 *
 * @param {Object} args Arguments: { entries (effective), registry, isNew, seen, loadedTick }.
 * @return {{patches: Map, conflicts: Object}} Attribute patches and the conflict map (clientId -> field names).
 */
function reduceUnique( args ) {
	const { entries, registry, isNew, seen, loadedTick } = args;
	const patches = new Map();
	const rules = new Map(); // group id -> { rule, items }

	entries.forEach( ( entry ) => {
		( registry[ entry.blockName ]?.unique || [] ).forEach(
			( { name, rule, format } ) => {
				const groupId = `${ rule.key }|${ rule.scope }|${
					rule.block || ''
				}`;

				if ( ! rules.has( groupId ) ) {
					rules.set( groupId, { rule, items: [] } );
				}

				rules.get( groupId ).items.push( {
					id: entry.clientId,
					field: name,
					blockName: entry.blockName,
					value: entry.attributes[ name ],
					format,
					ancestors: entry.ancestors.map( ( a ) => ( {
						id: a.clientId,
						blockName: a.blockName,
					} ) ),
				} );
			}
		);
	} );

	const age = ( id ) => seen.get( id ) ?? Number.MAX_SAFE_INTEGER;
	const conflicts = {};

	rules.forEach( ( { rule, items } ) => {
		const groups = groupEntries( items, rule );

		groups.forEach( ( values ) => {
			const takenValues = new Set( values.keys() );

			values.forEach( ( list ) => {
				if ( list.length < 2 ) {
					return;
				}

				// The oldest owner keeps the value; ties go to document order.
				const owner = list.reduce(
					( oldest, item ) =>
						age( item.id ) < age( oldest.id ) ? item : oldest,
					list[ 0 ]
				);

				list.forEach( ( item ) => {
					if ( item === owner ) {
						return;
					}

					if ( ! loadedTick && isNew.has( item.id ) ) {
						const suffixed = nextFreeValue(
							item.value,
							item.format,
							takenValues
						);
						takenValues.add(
							normalizeValue( suffixed, item.format )
						);
						patches.set( item.id, {
							...patches.get( item.id ),
							[ item.field ]: suffixed,
						} );
						return;
					}

					conflicts[ item.id ] = (
						conflicts[ item.id ] || []
					).concat( item.field );
				} );
			} );
		} );
	} );

	return { patches, conflicts };
}
