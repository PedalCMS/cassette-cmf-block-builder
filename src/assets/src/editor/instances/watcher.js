import { select as defaultSelect, dispatch, subscribe } from '@wordpress/data';
import { buildRegistry } from './registry';
import { collectInstances } from './collect';
import { createInitialState, reduceInstances } from './reducer';
import { generateUuid } from './uuid';
import { isIdInUse } from './id-in-use';
import { setConflicts } from '../unique/conflicts';

/**
 * Whether the editor has finished loading its content into the block-editor
 * store. Until then the watcher does nothing, so blocks that merely
 * arrive late with the initial content are never mistaken for new ones.
 *
 * @param {Function} select The `select` function of a data registry.
 * @return {boolean} True when it is safe to take the baseline.
 */
export function isEditorReady( select ) {
	const editor = select( 'core/editor' );

	if ( ! editor ) {
		return true;
	}

	if (
		'function' === typeof editor.__unstableIsEditorReady &&
		! editor.__unstableIsEditorReady()
	) {
		return false;
	}

	// The document can hold blocks before the block-editor store mirrors them.
	if ( 'function' === typeof editor.getEditorBlocks ) {
		const document = editor.getEditorBlocks();
		const mirrored = select( 'core/block-editor' ).getBlocks();

		if ( document?.length && ! mirrored?.length ) {
			return false;
		}
	}

	return true;
}

/**
 * Start the one library-wide instance watcher: a single subscription on the
 * block-editor store, coalesced to at most one pass per animation frame, that
 * handles "uuid" fields and "unique" fields for every registered block.
 *
 * Does nothing (not even a subscription) when no registered block has a
 * uuid or unique field.
 *
 * @param {Object}   payload           Editor_Payload.
 * @param {Object}   [deps]            Injected dependencies (tests).
 * @param {Function} [deps.select]     `select`.
 * @param {Function} [deps.dispatch]   `dispatch`.
 * @param {Function} [deps.subscribe]  `subscribe`.
 * @param {Function} [deps.schedule]   ( fn ) => void; defaults to requestAnimationFrame with a timeout fallback.
 * @param {Function} [deps.generateId] () => uuid.
 * @param {Function} [deps.idInUse]    ( id, blockName ) => Promise<boolean>.
 * @return {Function} Stop function (unsubscribes).
 */
export function startInstanceWatcher( payload, deps = {} ) {
	const registry = buildRegistry( payload );

	if ( 0 === Object.keys( registry ).length ) {
		return () => {};
	}

	const select = deps.select || defaultSelect;
	const dispatchFn = deps.dispatch || dispatch;
	const subscribeFn = deps.subscribe || subscribe;
	const generateId = deps.generateId || generateUuid;
	const idInUse = deps.idInUse || isIdInUse;
	const schedule =
		deps.schedule ||
		( ( fn ) =>
			'function' === typeof window.requestAnimationFrame
				? window.requestAnimationFrame( fn )
				: setTimeout( fn, 16 ) );

	let state = createInitialState();
	let lastBlocks = null;
	let scheduled = false;
	let stopped = false;

	const applyUpdates = ( updates ) => {
		const actions = dispatchFn( 'core/block-editor' );

		updates.forEach( ( { clientId, attributes } ) => {
			// Merge the fix-up into the user's own undo step.
			if (
				'function' ===
				typeof actions.__unstableMarkNextChangeAsNotPersistent
			) {
				actions.__unstableMarkNextChangeAsNotPersistent();
			}
			actions.updateBlockAttributes( clientId, attributes );
		} );
	};

	const regenerateIfStillInUse = ( check ) => {
		Promise.resolve( idInUse( check.value, check.blockName ) )
			.then( ( inUse ) => {
				if ( ! inUse || stopped ) {
					return;
				}

				const current = select(
					'core/block-editor'
				).getBlockAttributes( check.clientId );

				if ( current && current[ check.field ] === check.value ) {
					applyUpdates( [
						{
							clientId: check.clientId,
							attributes: { [ check.field ]: generateId() },
						},
					] );
				}
			} )
			.catch( () => {} );
	};

	const run = () => {
		scheduled = false;

		if ( stopped ) {
			return;
		}

		const store = select( 'core/block-editor' );
		const blocks = store.getBlocks();
		const ready = isEditorReady( select );

		const controlled =
			'function' === typeof store.areInnerBlocksControlled
				? ( clientId ) =>
						store.areInnerBlocksControlled( clientId )
							? store.getBlocks( clientId )
							: null
				: null;

		const result = reduceInstances(
			state,
			collectInstances( blocks, registry, controlled ),
			{ registry, generateId, ready }
		);

		state = result.state;
		setConflicts( result.conflicts );

		if ( result.updates.length ) {
			applyUpdates( result.updates );
		}

		result.idChecks.forEach( regenerateIfStillInUse );
	};

	const onChange = () => {
		if ( stopped || scheduled ) {
			return;
		}

		const store = select( 'core/block-editor' );
		const blocks = store.getBlocks();

		// Same tree reference and nothing pending: no work at all. (Until the
		// editor is ready the first pass must keep retrying.)
		if ( blocks === lastBlocks && state.initialized ) {
			return;
		}

		lastBlocks = blocks;
		scheduled = true;
		schedule( run );
	};

	const unsubscribe = subscribeFn( onChange );
	onChange();

	return () => {
		stopped = true;
		unsubscribe();
	};
}
