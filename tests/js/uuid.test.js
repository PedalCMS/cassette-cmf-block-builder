/**
 * instances/uuid.js test.
 */

import {
	generateUuid,
	isValidUuid,
	uuidFromBytes,
} from '../../src/assets/src/editor/instances/uuid';

const V4 =
	/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/;

describe( 'generateUuid', () => {
	it( 'uses crypto.randomUUID() when available and lowercases it', () => {
		const id = generateUuid( {
			randomUUID: () => '3B241101-E2BB-4255-8CAF-4136C566A962',
		} );

		expect( id ).toBe( '3b241101-e2bb-4255-8caf-4136c566a962' );
	} );

	it( 'falls back to crypto.getRandomValues() and builds a v4', () => {
		const getRandomValues = jest.fn( ( bytes ) => {
			bytes.fill( 0xff );
			return bytes;
		} );
		const id = generateUuid( { getRandomValues } );

		expect( getRandomValues ).toHaveBeenCalled();
		expect( id ).toMatch( V4 );
		expect( id ).toBe( 'ffffffff-ffff-4fff-bfff-ffffffffffff' );
	} );

	it( 'falls back to Math.random() when there is no crypto at all', () => {
		const random = jest.spyOn( Math, 'random' ).mockReturnValue( 0 );
		const id = generateUuid( null );
		random.mockRestore();

		expect( id ).toMatch( V4 );
		expect( id ).toBe( '00000000-0000-4000-8000-000000000000' );
	} );

	it( 'produces valid, distinct v4 ids by default', () => {
		const ids = new Set();
		for ( let i = 0; i < 200; i++ ) {
			const id = generateUuid( {} );
			expect( id ).toMatch( V4 );
			ids.add( id );
		}
		expect( ids.size ).toBe( 200 );
	} );
} );

describe( 'uuidFromBytes', () => {
	it( 'sets the version and variant bits', () => {
		const id = uuidFromBytes( new Uint8Array( 16 ).fill( 0 ) );
		expect( id ).toBe( '00000000-0000-4000-8000-000000000000' );
	} );
} );

describe( 'isValidUuid', () => {
	it( 'accepts a v4 in either case and rejects everything else', () => {
		expect( isValidUuid( '3b241101-e2bb-4255-8caf-4136c566a962' ) ).toBe(
			true
		);
		expect( isValidUuid( '3B241101-E2BB-4255-8CAF-4136C566A962' ) ).toBe(
			true
		);
		expect( isValidUuid( '{3b241101-e2bb-4255-8caf-4136c566a962}' ) ).toBe(
			false
		);
		expect( isValidUuid( 'not-a-uuid' ) ).toBe( false );
		expect( isValidUuid( '' ) ).toBe( false );
		expect( isValidUuid( 5 ) ).toBe( false );
		expect( isValidUuid( null ) ).toBe( false );
	} );
} );
