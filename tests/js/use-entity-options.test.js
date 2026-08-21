/**
 * value/useEntityOptions.js test.
 */

import { useEntityOptions } from '../../src/assets/src/editor/value/useEntityOptions';
import { store as coreStore } from '@wordpress/core-data';
import { __setStoreSelectors, __resetStores } from '@wordpress/data';

beforeEach( () => {
	__resetStores();
} );

describe( 'useEntityOptions', () => {
	it( 'maps getEntityRecords() results to { value, label } using the title', () => {
		__setStoreSelectors( coreStore, {
			getEntityRecords: () => [
				{ id: 1, title: { rendered: 'Hello' } },
				{ id: 2, title: { rendered: 'World' } },
			],
			hasFinishedResolution: () => true,
		} );

		const { options, isResolving } = useEntityOptions( 'postType', 'post' );

		expect( options ).toEqual( [
			{ value: 1, label: 'Hello' },
			{ value: 2, label: 'World' },
		] );
		expect( isResolving ).toBe( false );
	} );

	it( 'falls back to "name" when a record has no "title" (e.g. a user record)', () => {
		__setStoreSelectors( coreStore, {
			getEntityRecords: () => [ { id: 5, name: 'Jamie' } ],
			hasFinishedResolution: () => true,
		} );

		const { options } = useEntityOptions( 'root', 'user' );

		expect( options ).toEqual( [ { value: 5, label: 'Jamie' } ] );
	} );

	it( 'falls back to "#<id>" when a record has neither "title" nor "name"', () => {
		__setStoreSelectors( coreStore, {
			getEntityRecords: () => [ { id: 9 } ],
			hasFinishedResolution: () => true,
		} );

		const { options } = useEntityOptions( 'postType', 'post' );

		expect( options ).toEqual( [ { value: 9, label: '#9' } ] );
	} );

	it( 'reports isResolving true while the query is still in flight', () => {
		__setStoreSelectors( coreStore, {
			getEntityRecords: () => null,
			hasFinishedResolution: () => false,
		} );

		const { options, isResolving } = useEntityOptions( 'postType', 'post' );

		expect( options ).toEqual( [] );
		expect( isResolving ).toBe( true );
	} );

	it( 'a custom getLabel() overrides the default title/name resolution', () => {
		__setStoreSelectors( coreStore, {
			getEntityRecords: () => [ { id: 1, slug: 'acme' } ],
			hasFinishedResolution: () => true,
		} );

		const { options } = useEntityOptions( 'taxonomy', 'category', {
			getLabel: ( record ) => `#${ record.slug }`,
		} );

		expect( options ).toEqual( [ { value: 1, label: '#acme' } ] );
	} );
} );
