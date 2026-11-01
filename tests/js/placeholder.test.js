/**
 * Placeholder visibility and the block variation picker logic.
 */

import {
	applyVariation,
	blockScopeVariations,
	collectPlaceholderNodes,
	collectUuidNames,
	isPristine,
	isUnconfigured,
	resolveVariationPicker,
	shouldShowVariationPicker,
	splitPlaceholderNodes,
} from '../../src/assets/src/editor/placeholder/logic';

const leaf = ( name, area, extra = {} ) => ( {
	type: 'text',
	area,
	holdsValue: true,
	isContainer: false,
	config: { name, ...extra },
} );

describe( 'placeholder visibility', () => {
	const nodes = [
		leaf( 'url', 'placeholder', { required: true } ),
		leaf( 'title', 'placeholder' ),
		leaf( 'color', 'inspector' ),
	];
	const placeholderNodes = collectPlaceholderNodes( nodes );

	it( 'collects only placeholder-area nodes', () => {
		expect( placeholderNodes.map( ( n ) => n.config.name ) ).toEqual( [ 'url', 'title' ] );
	} );

	it( 'default: shown while every required placeholder field is empty', () => {
		expect( isUnconfigured( null, placeholderNodes, {} ) ).toBe( true );
		expect( isUnconfigured( null, placeholderNodes, { url: '' } ) ).toBe( true );
		expect( isUnconfigured( null, placeholderNodes, { url: 'https://x.test' } ) ).toBe( false );
	} );

	it( 'default: a non-required field does not matter when a required one exists', () => {
		expect( isUnconfigured( null, placeholderNodes, { title: 'T' } ) ).toBe( true );
	} );

	it( 'default without required fields: shown while all placeholder fields are empty', () => {
		const optional = collectPlaceholderNodes( [ leaf( 'a', 'placeholder' ), leaf( 'b', 'placeholder' ) ] );
		expect( isUnconfigured( null, optional, {} ) ).toBe( true );
		expect( isUnconfigured( null, optional, { b: 'x' } ) ).toBe( false );
	} );

	it( 'false and 0 are values, not empty', () => {
		const toggle = collectPlaceholderNodes( [ leaf( 'on', 'placeholder', { required: true } ) ] );
		expect( isUnconfigured( null, toggle, { on: false } ) ).toBe( false );
		expect( isUnconfigured( null, toggle, { on: 0 } ) ).toBe( false );
	} );

	it( 'placeholder.when decides when set', () => {
		const when = { relation: 'AND', rules: [ { field: 'mode', operator: '==', value: 'new' } ] };
		expect( isUnconfigured( { when }, placeholderNodes, { mode: 'new', url: 'https://x.test' } ) ).toBe( true );
		expect( isUnconfigured( { when }, placeholderNodes, { mode: 'done' } ) ).toBe( false );
	} );

	it( 'a block without placeholder fields is never unconfigured', () => {
		expect( isUnconfigured( { when: { rules: [] } }, [], {} ) ).toBe( false );
	} );

	it( 'looks into containers for the fields', () => {
		const panel = {
			type: 'panel',
			area: 'placeholder',
			isContainer: true,
			holdsValue: false,
			config: {},
			fields: [ leaf( 'inner', 'placeholder', { required: true } ) ],
		};
		expect( isUnconfigured( null, [ panel ], {} ) ).toBe( true );
		expect( isUnconfigured( null, [ panel ], { inner: 'x' } ) ).toBe( false );
	} );

	it( 'unconfigured: placeholder nodes go into the placeholder; configured: they become inspector nodes', () => {
		const shown = splitPlaceholderNodes( nodes, true );
		expect( shown.placeholderNodes ).toHaveLength( 2 );
		expect( shown.otherNodes.map( ( n ) => n.config.name ) ).toEqual( [ 'color' ] );

		const done = splitPlaceholderNodes( nodes, false );
		expect( done.placeholderNodes ).toEqual( [] );
		expect( done.otherNodes.map( ( n ) => n.area ) ).toEqual( [ 'inspector', 'inspector', 'inspector' ] );
		expect( nodes[ 0 ].area ).toBe( 'placeholder' );
	} );
} );

describe( 'variation picker', () => {
	const variations = [
		{ name: 'one', title: 'One', scope: [ 'block' ] },
		{ name: 'two', title: 'Two', scope: [ 'inserter' ] },
		{ name: 'three', title: 'Three' },
		{ name: 'four', title: 'Four', scope: [ 'block', 'inserter' ] },
	];
	const base = {
		variations: [ variations[ 0 ] ],
		hasInnerBlocks: false,
		pristine: true,
		hasActive: false,
		dismissed: false,
	};

	it( 'only an explicit block scope opens the picker', () => {
		expect( blockScopeVariations( variations ).map( ( v ) => v.name ) ).toEqual( [ 'one', 'four' ] );
		expect( blockScopeVariations( undefined ) ).toEqual( [] );
	} );

	it( 'shows on an empty, pristine block', () => {
		expect( shouldShowVariationPicker( base ) ).toBe( true );
	} );

	it.each( [
		[ 'no block-scope variation', { variations: [] } ],
		[ 'inner blocks exist', { hasInnerBlocks: true } ],
		[ 'attributes were changed', { pristine: false } ],
		[ 'a variation is active', { hasActive: true } ],
		[ 'the user skipped or chose', { dismissed: true } ],
	] )( 'hidden when %s', ( description, change ) => {
		expect( shouldShowVariationPicker( { ...base, ...change } ) ).toBe( false );
	} );

	it( 'pristine compares to defaults and ignores uuid fields', () => {
		const schema = { tone: { default: 'warm' }, text: {}, id: {} };
		expect( isPristine( { tone: 'warm' }, schema ) ).toBe( true );
		expect( isPristine( {}, schema ) ).toBe( true );
		expect( isPristine( { tone: 'cool' }, schema ) ).toBe( false );
		expect( isPristine( { text: 'x' }, schema ) ).toBe( false );
		expect( isPristine( { id: 'abc' }, schema ) ).toBe( false );
		expect( isPristine( { id: 'abc' }, schema, [ 'id' ] ) ).toBe( true );
		expect( collectUuidNames( [ { type: 'uuid', config: { name: 'id' } } ] ) ).toEqual( [ 'id' ] );
	} );

	it( 'choosing a variation applies its attributes and innerBlocks', () => {
		const setAttributes = jest.fn();
		const replaceInnerBlocks = jest.fn();
		const createBlocks = jest.fn( ( templates ) => templates.map( ( [ name ] ) => ( { name } ) ) );

		applyVariation(
			{
				attributes: { columns: 2 },
				innerBlocks: [ [ 'core/paragraph' ], [ 'core/paragraph' ] ],
			},
			{ setAttributes, replaceInnerBlocks, createBlocks, clientId: 'me' }
		);

		expect( setAttributes ).toHaveBeenCalledWith( { columns: 2 } );
		expect( replaceInnerBlocks ).toHaveBeenCalledWith( 'me', [ { name: 'core/paragraph' }, { name: 'core/paragraph' } ], true );
	} );

	it( 'a variation with neither does nothing', () => {
		const setAttributes = jest.fn();
		const replaceInnerBlocks = jest.fn();
		applyVariation( { name: 'blank' }, { setAttributes, replaceInnerBlocks, createBlocks: jest.fn(), clientId: 'me' } );
		expect( setAttributes ).not.toHaveBeenCalled();
		expect( replaceInnerBlocks ).not.toHaveBeenCalled();
	} );

	it( 'prefers the stable picker export, falls back to the experimental one', () => {
		const stable = () => null;
		const experimental = () => null;
		expect( resolveVariationPicker( { BlockVariationPicker: stable, __experimentalBlockVariationPicker: experimental } ) ).toBe( stable );
		expect( resolveVariationPicker( { __experimentalBlockVariationPicker: experimental } ) ).toBe( experimental );
		expect( resolveVariationPicker( {} ) ).toBeUndefined();
	} );
} );
