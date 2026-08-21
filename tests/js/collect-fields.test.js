/**
 * util/collect-fields.js test.
 */

import { collectFieldOptions } from '../../src/assets/src/editor/util/collect-fields';

describe( 'collectFieldOptions', () => {
	it( 'collects top-level attribute-sourced leaves', () => {
		const nodes = [
			{
				isContainer: false,
				holdsValue: true,
				config: { name: 'heading', label: 'Heading' },
			},
			{
				isContainer: false,
				holdsValue: true,
				config: { name: 'is_open' },
			},
		];

		expect( collectFieldOptions( nodes ) ).toEqual( [
			{ value: 'heading', label: 'Heading' },
			{ value: 'is_open', label: 'is_open' },
		] );
	} );

	it( 'recurses into containers', () => {
		const nodes = [
			{
				isContainer: true,
				fields: [
					{
						isContainer: false,
						holdsValue: true,
						config: { name: 'heading' },
					},
				],
			},
		];

		expect( collectFieldOptions( nodes ) ).toEqual( [
			{ value: 'heading', label: 'heading' },
		] );
	} );

	it( 'excludes display-only nodes (holdsValue: false)', () => {
		const nodes = [
			{ isContainer: false, holdsValue: false, config: { name: 'notice' } },
		];

		expect( collectFieldOptions( nodes ) ).toEqual( [] );
	} );

	it( 'excludes non-attribute-sourced fields', () => {
		const nodes = [
			{
				isContainer: false,
				holdsValue: true,
				config: { name: 'internal_id', source: 'meta' },
			},
		];

		expect( collectFieldOptions( nodes ) ).toEqual( [] );
	} );

	it( 'returns an empty array for an empty/undefined tree', () => {
		expect( collectFieldOptions( [] ) ).toEqual( [] );
		expect( collectFieldOptions( undefined ) ).toEqual( [] );
	} );
} );
