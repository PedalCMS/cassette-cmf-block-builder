/**
 * Repeater drag and drop and keyboard moves: the reorder reducer, the move
 * announcements and the row wiring.
 */

import { speak } from '@wordpress/a11y';
import { renderNode } from '../../src/assets/src/editor/node-renderer';
import {
	endDrag,
	moveAnnouncement,
	reorderRows,
	rowsReducer,
	startDrag,
} from '../../src/assets/src/editor/repeater/reorder';
import { __resetMock } from '@wordpress/core-data';

const NODE = {
	type: 'repeater',
	area: 'inspector',
	isContainer: true,
	holdsValue: true,
	config: { name: 'items', label: 'Items' },
	fields: [],
};

const flat = ( children ) =>
	( Array.isArray( children ) ? children : [ children ] )
		.flat( Infinity )
		.filter( Boolean );

const find = ( element, predicate, out = [] ) => {
	if ( element && 'object' === typeof element && element.props ) {
		if ( predicate( element ) ) {
			out.push( element );
		}
		flat( element.props.children ).forEach( ( child ) =>
			find( child, predicate, out )
		);
	}
	return out;
};

const render = ( items, setAttributes ) => {
	const element = renderNode( NODE, 0, {
		attributes: { items },
		setAttributes,
		context: {},
		fieldOptions: [],
		clientId: 'c1',
	} );
	return element.type( element.props );
};

const rowsOf = ( tree ) =>
	find( tree, ( el ) => 'cassette-cmf-block-builder-repeater__row' === el.props.className );

beforeEach( () => {
	__resetMock();
	speak.mockClear();
	endDrag();
} );

describe( 'reorder reducer', () => {
	it( 'moves a row forward and backward without mutating', () => {
		const rows = [ 'a', 'b', 'c', 'd' ];

		expect( reorderRows( rows, 0, 2 ) ).toEqual( [ 'b', 'c', 'a', 'd' ] );
		expect( reorderRows( rows, 3, 1 ) ).toEqual( [ 'a', 'd', 'b', 'c' ] );
		expect( rows ).toEqual( [ 'a', 'b', 'c', 'd' ] );
	} );

	it( 'returns the same array for no-op or out-of-range moves', () => {
		const rows = [ 'a', 'b' ];

		expect( reorderRows( rows, 1, 1 ) ).toBe( rows );
		expect( reorderRows( rows, 0, 2 ) ).toBe( rows );
		expect( reorderRows( rows, -1, 0 ) ).toBe( rows );
		expect( rowsReducer( rows, { type: 'other' } ) ).toBe( rows );
		expect( rowsReducer( rows, { type: 'move', from: 0, to: 1 } ) ).toEqual( [ 'b', 'a' ] );
	} );

	it( 'announces the new 1-based position', () => {
		expect( moveAnnouncement( 2, 5 ) ).toBe( 'Row moved to position 3 of 5' );
	} );
} );

describe( 'repeater rows', () => {
	it( 'gives every row an id for the drag image and a drag handle', () => {
		const tree = render( [ { t: 'A' }, { t: 'B' } ], jest.fn() );
		const rows = rowsOf( tree );

		expect( rows.map( ( r ) => r.props.id ) ).toEqual( [
			'cassette-cmf-repeater-c1-items-row-0',
			'cassette-cmf-repeater-c1-items-row-1',
		] );
		expect(
			find( tree, ( el ) => 'function' === typeof el.props.children && el.props.elementId )
		).toHaveLength( 2 );
	} );

	it( 'drops a dragged row onto another row and announces it', () => {
		const setAttributes = jest.fn();
		const tree = render( [ { t: 'A' }, { t: 'B' }, { t: 'C' } ], setAttributes );
		const [ first, , third ] = rowsOf( tree );
		const draggable = find( first, ( el ) => el.props.onDragStart && el.props.elementId )[ 0 ];

		draggable.props.onDragStart();
		const event = { preventDefault: jest.fn() };
		third.props.onDragOver( event );
		expect( event.preventDefault ).toHaveBeenCalledTimes( 1 );
		third.props.onDrop( event );

		expect( setAttributes ).toHaveBeenCalledWith( {
			items: [ { t: 'B' }, { t: 'C' }, { t: 'A' } ],
		} );
		expect( speak ).toHaveBeenCalledWith( 'Row moved to position 3 of 3', 'assertive' );
	} );

	it( 'ignores a drop with no drag in progress', () => {
		const setAttributes = jest.fn();
		const [ first ] = rowsOf( render( [ { t: 'A' }, { t: 'B' } ], setAttributes ) );

		first.props.onDrop( { preventDefault: jest.fn() } );

		expect( setAttributes ).not.toHaveBeenCalled();
	} );

	it( 'ignores a drag that started in another repeater', () => {
		const setAttributes = jest.fn();
		const [ first ] = rowsOf( render( [ { t: 'A' }, { t: 'B' } ], setAttributes ) );
		startDrag( 'other-list', 1 );

		first.props.onDrop( { preventDefault: jest.fn() } );

		expect( setAttributes ).not.toHaveBeenCalled();
	} );

	it( 'the Move buttons reorder and announce too', () => {
		const setAttributes = jest.fn();
		const [ first ] = rowsOf( render( [ { t: 'A' }, { t: 'B' } ], setAttributes ) );
		const down = find( first, ( el ) => 'Move down' === el.props.children )[ 0 ];

		down.props.onClick();

		expect( setAttributes ).toHaveBeenCalledWith( { items: [ { t: 'B' }, { t: 'A' } ] } );
		expect( speak ).toHaveBeenCalledWith( 'Row moved to position 2 of 2', 'assertive' );
	} );
} );
