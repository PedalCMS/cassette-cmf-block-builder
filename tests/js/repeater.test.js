/**
 * node-renderer.js's RepeaterField test — the "repeater" container that
 * also holds its own value, see that file's docblock for the design.
 */

import { renderNode } from '../../src/assets/src/editor/node-renderer';
import { __setMockMeta, __resetMock } from '@wordpress/core-data';

/**
 * Flatten a React element's children into a single array, dropping falsy
 * entries — see conditions-control.test.js's identical helper for why.
 *
 * @param {*} children A children value: an element, an array (possibly nested), or falsy.
 * @return {Array} Flattened, non-falsy children.
 */
function flattenChildren( children ) {
	return ( Array.isArray( children ) ? children : [ children ] )
		.flat( Infinity )
		.filter( Boolean );
}

/**
 * Find every descendant (including the root) matching a className.
 *
 * @param {*}      element   A React element.
 * @param {string} className className to match.
 * @return {Array} Matching elements.
 */
function findByClassName( element, className ) {
	const matches = [];

	const visit = ( node ) => {
		if ( ! node || 'object' !== typeof node || ! node.props ) {
			return;
		}
		if ( node.props.className === className ) {
			matches.push( node );
		}
		flattenChildren( node.props.children ).forEach( visit );
	};

	visit( element );

	return matches;
}

const REPEATER_NODE = {
	type: 'repeater',
	area: 'inspector',
	isContainer: true,
	holdsValue: true,
	config: { name: 'items', label: 'Items' },
	fields: [
		{
			type: 'text',
			area: 'inspector',
			isContainer: false,
			holdsValue: true,
			config: { name: 'title' },
		},
	],
};

/**
 * renderNode() dispatches a "repeater" node to RepeaterField — a local,
 * unexported component in node-renderer.js — as an unexecuted
 * `<RepeaterField .../>` element. Calling `element.type( element.props )`
 * directly invokes it as a plain function (the same "bare function call"
 * trick every other hook-using control in this suite uses), since
 * RepeaterField's only real hook (useFieldValue -> useEntityProp) is
 * mocked as a plain non-hook function in tests/js/mocks/wordpress-core-data.js.
 *
 * @param {Object} ctx Shared render context.
 * @return {*} The rendered RepeaterField element tree.
 */
function renderRepeater( ctx ) {
	const element = renderNode( REPEATER_NODE, 0, ctx );
	return element.type( element.props );
}

beforeEach( () => {
	__resetMock();
} );

describe( 'RepeaterField', () => {
	it( 'renders one row per array item, each with its own sub-field values', () => {
		const ctx = {
			attributes: { items: [ { title: 'A' }, { title: 'B' } ] },
			setAttributes: jest.fn(),
			context: {},
			fieldOptions: [],
		};

		const rendered = renderRepeater( ctx );
		const rows = findByClassName( rendered, 'cassette-cmf-block-builder-repeater__row' );

		expect( rows ).toHaveLength( 2 );
	} );

	it( '"Add row" appends an empty row object', () => {
		const setAttributes = jest.fn();
		const ctx = {
			attributes: { items: [ { title: 'A' } ] },
			setAttributes,
			context: {},
			fieldOptions: [],
		};

		const rendered = renderRepeater( ctx );
		const addButton = flattenChildren( rendered.props.children ).find(
			( child ) => 'Add row' === child.props.children
		);
		addButton.props.onClick();

		expect( setAttributes ).toHaveBeenCalledWith( {
			items: [ { title: 'A' }, {} ],
		} );
	} );

	it( '"Remove" drops that one row, leaving the others untouched', () => {
		const setAttributes = jest.fn();
		const ctx = {
			attributes: { items: [ { title: 'A' }, { title: 'B' } ] },
			setAttributes,
			context: {},
			fieldOptions: [],
		};

		const rendered = renderRepeater( ctx );
		const [ firstRow ] = findByClassName(
			rendered,
			'cassette-cmf-block-builder-repeater__row'
		);
		const [ actions ] = findByClassName(
			firstRow,
			'cassette-cmf-block-builder-repeater__row-actions'
		);
		const removeButton = flattenChildren( actions.props.children ).find(
			( child ) => 'Remove' === child.props.children
		);
		removeButton.props.onClick();

		expect( setAttributes ).toHaveBeenCalledWith( {
			items: [ { title: 'B' } ],
		} );
	} );

	it( '"Move down" swaps a row with its next sibling', () => {
		const setAttributes = jest.fn();
		const ctx = {
			attributes: { items: [ { title: 'A' }, { title: 'B' } ] },
			setAttributes,
			context: {},
			fieldOptions: [],
		};

		const rendered = renderRepeater( ctx );
		const [ firstRow ] = findByClassName(
			rendered,
			'cassette-cmf-block-builder-repeater__row'
		);
		const [ actions ] = findByClassName(
			firstRow,
			'cassette-cmf-block-builder-repeater__row-actions'
		);
		const moveDownButton = flattenChildren( actions.props.children ).find(
			( child ) => 'Move down' === child.props.children
		);
		moveDownButton.props.onClick();

		expect( setAttributes ).toHaveBeenCalledWith( {
			items: [ { title: 'B' }, { title: 'A' } ],
		} );
	} );

	it( "a row's own field writes only that row's own data, not the whole block's attributes", () => {
		const setAttributes = jest.fn();
		const ctx = {
			attributes: { items: [ { title: 'A' }, { title: 'B' } ] },
			setAttributes,
			context: {},
			fieldOptions: [],
		};

		const rendered = renderRepeater( ctx );
		const [ , secondRow ] = findByClassName(
			rendered,
			'cassette-cmf-block-builder-repeater__row'
		);

		// The row's own TextControl is itself an unexecuted FieldValueBridge
		// element (same reasoning as RepeaterField above) — invoke it the
		// same way to reach the real TextControl's onChange.
		const [ fieldBridge ] = flattenChildren( secondRow.props.children );
		const textControl = fieldBridge.type( fieldBridge.props );
		textControl.props.onChange( 'Updated' );

		expect( setAttributes ).toHaveBeenCalledWith( {
			items: [ { title: 'A' }, { title: 'Updated' } ],
		} );
	} );

	it( 'defaults to an empty row list when no value is set yet', () => {
		const ctx = {
			attributes: {},
			setAttributes: jest.fn(),
			context: {},
			fieldOptions: [],
		};

		const rendered = renderRepeater( ctx );

		expect(
			findByClassName( rendered, 'cassette-cmf-block-builder-repeater__row' )
		).toHaveLength( 0 );
	} );
} );
