/**
 * controls/conditions.js test.
 */

import Conditions from '../../src/assets/src/editor/controls/conditions';

/**
 * Flatten a React element's children into a single array, dropping falsy
 * entries ({ cond && <X/> } leaves a literal `false` in the children array
 * when cond is false) — createElement() never flattens/filters children
 * itself, React only does that at actual render time.
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

describe( 'Conditions', () => {
	it( 'adding a rule appends one with the first field option and "=="', () => {
		const changes = [];
		const element = Conditions( {
			field: { name: 'rules' },
			value: undefined,
			onChange: ( value ) => changes.push( value ),
			scope: { fieldOptions: [ { value: 'is_open', label: 'Open?' } ] },
		} );

		const addButton = flattenChildren( element.props.children ).find(
			( child ) => 'Add rule' === child.props.children
		);
		addButton.props.onClick();

		expect( changes ).toHaveLength( 1 );
		expect( changes[ 0 ] ).toEqual( {
			relation: 'AND',
			rules: [ { field: 'is_open', operator: '==', value: '' } ],
		} );
	} );

	it( 'removing a rule drops it from the array', () => {
		const changes = [];
		const value = {
			relation: 'AND',
			rules: [
				{ field: 'a', operator: '==', value: '1' },
				{ field: 'b', operator: '==', value: '2' },
			],
		};

		const element = Conditions( {
			field: { name: 'rules' },
			value,
			onChange: ( newValue ) => changes.push( newValue ),
			scope: { fieldOptions: [] },
		} );

		const ruleRows = findByClassName(
			element,
			'cassette-cmf-block-builder-conditions__rule'
		);
		expect( ruleRows ).toHaveLength( 2 );

		const removeButton = flattenChildren( ruleRows[ 0 ].props.children ).find(
			( child ) => 'Remove rule' === child.props.children
		);
		removeButton.props.onClick();

		expect( changes[ 0 ].rules ).toEqual( [
			{ field: 'b', operator: '==', value: '2' },
		] );
	} );

	it( 'a multi-value operator ("in") splits comma-separated input into an array', () => {
		const changes = [];
		const value = {
			relation: 'AND',
			rules: [ { field: 'a', operator: 'in', value: [] } ],
		};

		const element = Conditions( {
			field: { name: 'rules' },
			value,
			onChange: ( newValue ) => changes.push( newValue ),
			scope: { fieldOptions: [] },
		} );

		const [ ruleRow ] = findByClassName(
			element,
			'cassette-cmf-block-builder-conditions__rule'
		);
		const valueControl = flattenChildren( ruleRow.props.children ).find(
			( child ) => 'Value' === child.props.label
		);
		valueControl.props.onChange( 'x, y, z' );

		expect( changes[ 0 ].rules[ 0 ].value ).toEqual( [ 'x', 'y', 'z' ] );
	} );

	it( 'a value-less operator ("empty") renders no value control', () => {
		const value = {
			relation: 'AND',
			rules: [ { field: 'a', operator: 'empty' } ],
		};

		const element = Conditions( {
			field: { name: 'rules' },
			value,
			onChange: () => {},
			scope: { fieldOptions: [] },
		} );

		const [ ruleRow ] = findByClassName(
			element,
			'cassette-cmf-block-builder-conditions__rule'
		);
		const valueControl = flattenChildren( ruleRow.props.children ).find(
			( child ) => 'Value' === child.props.label
		);

		expect( valueControl ).toBeUndefined();
	} );
} );
