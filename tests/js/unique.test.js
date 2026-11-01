/**
 * unique/find-collisions.js against the fixture shared with PHP
 * (tests/fixtures/unique.json), plus the suffix generator.
 */

import fixture from '../fixtures/unique.json';
import {
	entriesFromTree,
	findCollisions,
	nextFreeValue,
	normalizeValue,
} from '../../src/assets/src/editor/unique/find-collisions';

describe( 'findCollisions against tests/fixtures/unique.json', () => {
	fixture.cases.forEach( ( testCase ) => {
		it( testCase.name, () => {
			const entries = entriesFromTree(
				testCase.blocks,
				testCase.participants
			);
			const actual = findCollisions( entries, testCase.rule ).map(
				( collision ) => ( {
					value: collision.value,
					paths: collision.ids.map( ( id ) =>
						id.split( '.' ).map( Number )
					),
				} )
			);

			expect( actual ).toEqual( testCase.expected );
		} );
	} );

	it( 'has cases for every behaviour the brief names', () => {
		const names = fixture.cases.map( ( c ) => c.name ).join( '\n' );
		[
			'whole post',
			'ancestor scope',
			'nested',
			'separate forms',
			'empty',
			'trimmed',
			'unscoped',
		].forEach( ( word ) => expect( names ).toContain( word ) );
	} );
} );

describe( 'normalizeValue', () => {
	it( 'trims, formats and ignores non-strings', () => {
		expect( normalizeValue( '  Hi  ', undefined ) ).toBe( 'Hi' );
		expect( normalizeValue( ' Hello World ', 'slug' ) ).toBe(
			'hello-world'
		);
		expect( normalizeValue( 'abc', 'upper' ) ).toBe( 'ABC' );
		expect( normalizeValue( 5, undefined ) ).toBe( '' );
		expect( normalizeValue( undefined, 'slug' ) ).toBe( '' );
	} );
} );

describe( 'nextFreeValue', () => {
	it( 'picks the first free -2, -3, ... suffix', () => {
		expect( nextFreeValue( 'name', undefined, new Set( [ 'name' ] ) ) ).toBe(
			'name-2'
		);
		expect(
			nextFreeValue(
				'name',
				undefined,
				new Set( [ 'name', 'name-2', 'name-3' ] )
			)
		).toBe( 'name-4' );
	} );

	it( 'fills a gap rather than always appending', () => {
		expect(
			nextFreeValue( 'name', undefined, new Set( [ 'name', 'name-3' ] ) )
		).toBe( 'name-2' );
	} );

	it( 'applies the format to the candidate', () => {
		expect(
			nextFreeValue( 'my-name', 'slug', new Set( [ 'my-name' ] ) )
		).toBe( 'my-name-2' );
		expect( nextFreeValue( 'ABC', 'upper', new Set( [ 'ABC' ] ) ) ).toBe(
			'ABC-2'
		);
	} );

	it( 'trims the base value', () => {
		expect(
			nextFreeValue( '  name  ', undefined, new Set( [ 'name' ] ) )
		).toBe( 'name-2' );
	} );
} );
