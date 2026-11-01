import {
	convertRule,
	isCrossBlockRule,
	newBlockRule,
	newOwnRule,
} from '../../src/assets/src/editor/conditions/rules';

describe( 'conditions/rules', () => {
	it( 'tells the two rule kinds apart', () => {
		expect( isCrossBlockRule( newOwnRule( 'a' ) ) ).toBe( false );
		expect( isCrossBlockRule( newBlockRule( 'email' ) ) ).toBe( true );
		expect( isCrossBlockRule( { ref: 'x', operator: '==' } ) ).toBe( true );
	} );

	it( 'keeps an own-attribute rule exactly as it was', () => {
		expect( newOwnRule( 'tone' ) ).toEqual( { field: 'tone', operator: '==', value: '' } );
	} );

	it( 'converts own -> block -> own keeping operator and value', () => {
		const own = { field: 'a', operator: 'in', value: [ 'x' ] };
		const block = convertRule( own, 'block' );
		expect( block ).toEqual( { source: 'block', ref: '', operator: 'in', value: [ 'x' ] } );
		expect( convertRule( block, 'own', 'a' ) ).toEqual( own );
	} );

	it( 'returns the same rule when it already has the requested kind', () => {
		const own = newOwnRule( 'a' );
		const block = newBlockRule( 'b' );
		expect( convertRule( own, 'own' ) ).toBe( own );
		expect( convertRule( block, 'block' ) ).toBe( block );
	} );
} );
