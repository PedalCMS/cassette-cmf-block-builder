import { useEffect, useState } from '@wordpress/element';

/**
 * A tiny external store holding which block fields currently collide with
 * an older owner of the same "unique" value. It lives outside the block
 * attributes on purpose: a flag is derived state, and writing it into
 * attributes would dirty the post and pollute undo history.
 *
 * The instance watcher recomputes it after every tree change and calls
 * setConflicts(); text-like controls read it with useUniqueConflict().
 */

let conflicts = {};
const listeners = new Set();

/**
 * Shallow-compare two { clientId: string[] } maps.
 *
 * @param {Object} a First map.
 * @param {Object} b Second map.
 * @return {boolean} True when equal.
 */
function sameConflicts( a, b ) {
	const aKeys = Object.keys( a );
	if ( aKeys.length !== Object.keys( b ).length ) {
		return false;
	}

	return aKeys.every(
		( key ) =>
			b[ key ] &&
			a[ key ].length === b[ key ].length &&
			a[ key ].every( ( name, index ) => name === b[ key ][ index ] )
	);
}

/**
 * Replace the conflict map; listeners are notified only on a real change.
 *
 * @param {Object} next Map of clientId -> field names that collide.
 */
export function setConflicts( next ) {
	if ( sameConflicts( conflicts, next ) ) {
		return;
	}

	conflicts = next;
	listeners.forEach( ( listener ) => listener() );
}

/**
 * Whether one block field is currently flagged.
 *
 * @param {string} clientId  Block client id.
 * @param {string} fieldName Field name.
 * @return {boolean} True when flagged.
 */
export function hasConflict( clientId, fieldName ) {
	return Boolean( conflicts[ clientId ]?.includes( fieldName ) );
}

/**
 * Subscribe to conflict changes.
 *
 * @param {Function} listener Called after every change.
 * @return {Function} Unsubscribe.
 */
export function subscribeConflicts( listener ) {
	listeners.add( listener );

	return () => listeners.delete( listener );
}

/**
 * React hook: is this field flagged as a duplicate?
 *
 * @param {string}  clientId  Block client id (undefined outside a block instance).
 * @param {string}  fieldName Field name.
 * @param {boolean} enabled   Whether the field declares "unique" at all.
 * @return {boolean} True when flagged.
 */
export function useUniqueConflict( clientId, fieldName, enabled ) {
	const [ flagged, setFlagged ] = useState( () =>
		enabled ? hasConflict( clientId, fieldName ) : false
	);

	useEffect( () => {
		if ( ! enabled || ! clientId ) {
			setFlagged( false );
			return undefined;
		}

		const update = () => setFlagged( hasConflict( clientId, fieldName ) );
		update();

		return subscribeConflicts( update );
	}, [ clientId, fieldName, enabled ] );

	return flagged;
}
