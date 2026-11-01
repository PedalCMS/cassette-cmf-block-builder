import { __, sprintf } from '@wordpress/i18n';

/**
 * Move one repeater row.
 *
 * @param {Array}  rows Rows.
 * @param {number} from Current index.
 * @param {number} to   Target index.
 * @return {Array} A new array, or the same one when the move is a no-op or out of range.
 */
export function reorderRows( rows, from, to ) {
	if (
		from === to ||
		from < 0 ||
		to < 0 ||
		from >= rows.length ||
		to >= rows.length
	) {
		return rows;
	}

	const next = [ ...rows ];
	const [ moved ] = next.splice( from, 1 );
	next.splice( to, 0, moved );

	return next;
}

/**
 * Reducer over a repeater's rows. Actions: { type: 'move', from, to }.
 *
 * @param {Array}  rows   Rows.
 * @param {Object} action Action.
 * @return {Array} The next rows.
 */
export function rowsReducer( rows, action ) {
	if ( 'move' === action?.type ) {
		return reorderRows( rows, action.from, action.to );
	}

	return rows;
}

/**
 * The text announced to screen readers after a move.
 *
 * @param {number} index Zero-based new index.
 * @param {number} total Row count.
 * @return {string} E.g. "Row moved to position 3 of 5".
 */
export function moveAnnouncement( index, total ) {
	return sprintf(
		/* translators: 1: new position, 2: number of rows */
		__(
			'Row moved to position %1$d of %2$d',
			'cassette-cmf-block-builder'
		),
		index + 1,
		total
	);
}

/*
 * Drag state. One drag exists per document at a time, so a module-level
 * record (list id plus source index) is enough and keeps the row component
 * free of hooks.
 */
let drag = null;

/**
 * @param {string} listId Repeater instance id.
 * @param {number} index  Index of the dragged row.
 */
export function startDrag( listId, index ) {
	drag = { listId, index };
}

/**
 * @param {string} listId Repeater instance id.
 * @return {boolean} Whether a drag from this repeater is active.
 */
export function isDragging( listId ) {
	return Boolean( drag && drag.listId === listId );
}

/**
 * Finish the drag and return the source index.
 *
 * @param {string} listId Repeater instance id.
 * @return {number|null} The dragged row's index, or null when not from this list.
 */
export function takeDrag( listId ) {
	const from = drag && drag.listId === listId ? drag.index : null;
	drag = null;

	return from;
}

/**
 * Cancel any drag.
 */
export function endDrag() {
	drag = null;
}
