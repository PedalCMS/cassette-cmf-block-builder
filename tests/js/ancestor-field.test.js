/**
 * ancestor_field: the pure option builder and the ancestor selector.
 */

import {
	buildAncestorOptions,
	normalizeAncestorConfig,
} from '../../src/assets/src/editor/ancestor/options';
import {
	pickerMode,
	selectAncestorBlock,
	COMBOBOX_THRESHOLD,
} from '../../src/assets/src/editor/controls/ancestor-field';

const field = ( clientId, name, attrs = {}, innerBlocks = [], blockName = 'acme/field' ) => ( {
	clientId,
	name: blockName,
	attributes: attrs,
	innerBlocks,
} );

const tree = [
	field( 'a', 'acme/field', { name: 'email', label: 'Email' } ),
	field( 'g', 'acme/group', { name: '' }, [
		field( 'b', 'acme/field', { name: 'phone', label: '' } ),
		field( 'c', 'acme/field', { name: 'city', label: 'City', required: true }, [
			field( 'd', 'acme/field', { name: 'deep', label: 'Deep' } ),
		] ),
	] ),
	field( 'e', 'acme/other', { name: 'note', label: 'Note' }, [], 'acme/other' ),
	field( 'f', 'acme/field', { label: 'No name' } ),
];

describe( 'normalizeAncestorConfig', () => {
	it( 'fills the defaults', () => {
		expect( normalizeAncestorConfig( { ancestor: 'acme/form' } ) ).toEqual( {
			ancestor: 'acme/form',
			blocks: [],
			hasAttribute: '',
			nameAttribute: 'name',
			labelAttribute: 'label',
			excludeSelf: true,
		} );
	} );
} );

describe( 'buildAncestorOptions', () => {
	const config = { ancestor: 'acme/form' };

	it( 'lists named descendants at any depth, in document order, with value = name', () => {
		const options = buildAncestorOptions( tree, config );

		expect( options.map( ( o ) => o.value ) ).toEqual( [ 'email', 'phone', 'city', 'deep', 'note' ] );
		expect( options[ 0 ] ).toEqual( {
			value: 'email',
			label: 'Email',
			blockName: 'acme/field',
			clientId: 'a',
		} );
	} );

	it( 'falls back to the name when the label is empty', () => {
		expect( buildAncestorOptions( tree, config )[ 1 ].label ).toBe( 'phone' );
	} );

	it( 'filters by block names', () => {
		const options = buildAncestorOptions( tree, { ...config, blocks: [ 'acme/other' ] } );
		expect( options.map( ( o ) => o.value ) ).toEqual( [ 'note' ] );
	} );

	it( 'filters by attribute presence', () => {
		const options = buildAncestorOptions( tree, { ...config, has_attribute: 'required' } );
		expect( options.map( ( o ) => o.value ) ).toEqual( [ 'city' ] );
	} );

	it( 'reads other name / label attributes', () => {
		const blocks = [ field( 'x', '', { key: 'k1', title: 'T1' } ) ];
		const options = buildAncestorOptions( blocks, { ...config, name_attribute: 'key', label_attribute: 'title' } );
		expect( options ).toEqual( [ { value: 'k1', label: 'T1', blockName: 'acme/field', clientId: 'x' } ] );
	} );

	it( 'excludes the block itself by default and keeps it with exclude_self: false', () => {
		expect( buildAncestorOptions( tree, config, { selfClientId: 'a' } ).map( ( o ) => o.value ) ).not.toContain( 'email' );
		expect(
			buildAncestorOptions( tree, { ...config, exclude_self: false }, { selfClientId: 'a' } ).map( ( o ) => o.value )
		).toContain( 'email' );
	} );

	it( 'reflects a rename and a removal on the next build', () => {
		const renamed = [ field( 'a', 'acme/field', { name: 'mail', label: 'Email' } ) ];
		expect( buildAncestorOptions( renamed, config ).map( ( o ) => o.value ) ).toEqual( [ 'mail' ] );
		expect( buildAncestorOptions( [], config ) ).toEqual( [] );
	} );

	it( 'keeps a stored value that no longer exists as "missing: name"', () => {
		const options = buildAncestorOptions( tree, config, { currentValue: 'gone' } );
		expect( options[ options.length - 1 ] ).toEqual( {
			value: 'gone',
			label: 'missing: gone',
			blockName: '',
			clientId: '',
			missing: true,
		} );
	} );

	it( 'does not flag a stored value that exists', () => {
		const options = buildAncestorOptions( tree, config, { currentValue: 'email' } );
		expect( options.some( ( o ) => o.missing ) ).toBe( false );
	} );

	it( 'lists a duplicate name once', () => {
		const blocks = [ field( '1', '', { name: 'x' } ), field( '2', '', { name: 'x' } ) ];
		expect( buildAncestorOptions( blocks, config ) ).toHaveLength( 1 );
	} );
} );

describe( 'pickerMode', () => {
	it( 'switches to a combobox above the threshold', () => {
		expect( pickerMode( new Array( COMBOBOX_THRESHOLD ).fill( 0 ) ).useCombobox ).toBe( false );
		expect( pickerMode( new Array( COMBOBOX_THRESHOLD + 1 ).fill( 0 ) ).useCombobox ).toBe( true );
	} );
} );

describe( 'selectAncestorBlock', () => {
	it( 'asks for the nearest ancestor (ascending) and returns its block tree', () => {
		const getBlockParentsByBlockName = jest.fn( () => [ 'near', 'far' ] );
		const getBlock = jest.fn( ( id ) => ( { clientId: id } ) );
		const select = () => ( { getBlockParentsByBlockName, getBlock } );

		expect( selectAncestorBlock( select, 'me', 'acme/form' ) ).toEqual( { clientId: 'near' } );
		expect( getBlockParentsByBlockName ).toHaveBeenCalledWith( 'me', 'acme/form', true );
	} );

	it( 'returns null outside the ancestor or without a store', () => {
		expect( selectAncestorBlock( () => ( { getBlockParentsByBlockName: () => [], getBlock: jest.fn() } ), 'me', 'acme/form' ) ).toBeNull();
		expect( selectAncestorBlock( () => ( {} ), 'me', 'acme/form' ) ).toBeNull();
	} );
} );
