/**
 * deprecations.js test.
 */

import {
	registerMigration,
	buildDeprecations,
} from '../../src/assets/src/editor/deprecations';

describe( 'buildDeprecations', () => {
	it( 'returns an empty array when render.deprecated is absent', () => {
		expect( buildDeprecations( { settings: {}, render: {} } ) ).toEqual( [] );
	} );

	it( 'builds one deprecated entry per render.deprecated item', () => {
		const descriptor = {
			settings: { attributes: { heading: { type: 'string' } } },
			render: {
				deprecated: [
					{ markup: { tag: 'div' } },
					{ markup: { tag: 'span' } },
				],
			},
		};

		const deprecated = buildDeprecations( descriptor );

		expect( deprecated ).toHaveLength( 2 );
		expect( typeof deprecated[ 0 ].save ).toBe( 'function' );
		expect( typeof deprecated[ 1 ].save ).toBe( 'function' );
	} );

	it( 'falls back to the block\'s current attribute schema when an entry declares none', () => {
		const currentAttributes = { heading: { type: 'string' } };
		const descriptor = {
			settings: { attributes: currentAttributes },
			render: { deprecated: [ { markup: { tag: 'div' } } ] },
		};

		const [ deprecated ] = buildDeprecations( descriptor );

		expect( deprecated.attributes ).toBe( currentAttributes );
	} );

	it( 'uses an entry\'s own declared attribute schema when given', () => {
		const oldAttributes = { heading: { type: 'string' } };
		const descriptor = {
			settings: { attributes: { heading: { type: 'string' }, tone: { type: 'string' } } },
			render: {
				deprecated: [
					{ markup: { tag: 'div' }, attributes: oldAttributes },
				],
			},
		};

		const [ deprecated ] = buildDeprecations( descriptor );

		expect( deprecated.attributes ).toBe( oldAttributes );
	} );

	it( 'resolves "migrate" by name against a registered migration', () => {
		const migrateFn = ( attributes ) => ( { ...attributes, migrated: true } );
		registerMigration( 'acme_migrate_v1', migrateFn );

		const descriptor = {
			settings: {},
			render: {
				deprecated: [
					{ markup: { tag: 'div' }, migrate: 'acme_migrate_v1' },
				],
			},
		};

		const [ deprecated ] = buildDeprecations( descriptor );

		expect( deprecated.migrate ).toBe( migrateFn );
	} );

	it( 'omits "migrate" entirely when the named migration isn\'t registered', () => {
		const descriptor = {
			settings: {},
			render: {
				deprecated: [
					{ markup: { tag: 'div' }, migrate: 'nonexistent_migration' },
				],
			},
		};

		const [ deprecated ] = buildDeprecations( descriptor );

		expect( deprecated ).not.toHaveProperty( 'migrate' );
	} );

	it( 'omits "migrate" entirely when the entry declares none', () => {
		const descriptor = {
			settings: {},
			render: { deprecated: [ { markup: { tag: 'div' } } ] },
		};

		const [ deprecated ] = buildDeprecations( descriptor );

		expect( deprecated ).not.toHaveProperty( 'migrate' );
	} );
} );
