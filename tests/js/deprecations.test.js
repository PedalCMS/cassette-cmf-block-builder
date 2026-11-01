/**
 * deprecations.js test.
 */

import { readFileSync } from 'fs';
import { join } from 'path';
import {
	registerMigration,
	buildDeprecations,
} from '../../src/assets/src/editor/deprecations';
import {
	applyMigration,
	isEligible,
} from '../../src/assets/src/editor/migrate';

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

describe( 'dynamic-mode deprecations', () => {
	const fixtures = JSON.parse(
		readFileSync( join( __dirname, '../fixtures/deprecations.json' ), 'utf8' )
	);

	it.each(
		fixtures.cases.map( ( testCase ) => [ testCase.description, testCase ] )
	)( 'fixture parity: %s', ( description, testCase ) => {
		expect(
			isEligible( testCase.entry, testCase.current, testCase.attributes )
		).toBe( testCase.eligible );

		const spec =
			testCase.entry.migrate && 'object' === typeof testCase.entry.migrate
				? testCase.entry.migrate
				: {};
		expect( applyMigration( spec, testCase.attributes ) ).toEqual(
			testCase.migrated
		);
	} );

	it( 'does not mutate its input', () => {
		const attributes = { title: 'T' };
		applyMigration( { rename: { title: 'heading' } }, attributes );
		expect( attributes ).toEqual( { title: 'T' } );
	} );

	const dynamicDescriptor = {
		name: 'acme/dyn',
		settings: { attributes: { heading: { type: 'string' } } },
		render: {
			mode: 'dynamic',
			deprecated: [
				{
					attributes: { title: { type: 'string' } },
					migrate: { rename: { title: 'heading' } },
				},
			],
		},
	};

	it( 'builds a dynamic entry with save() null, isEligible and a declarative migrate', () => {
		const [ deprecated ] = buildDeprecations( dynamicDescriptor );

		expect( deprecated.save() ).toBeNull();
		expect( deprecated.attributes ).toEqual( { title: { type: 'string' } } );
		expect( deprecated.isEligible( { title: 'Old' } ) ).toBe( true );
		expect( deprecated.isEligible( { heading: 'New' } ) ).toBe( false );
		expect( deprecated.migrate( { title: 'Old' }, [] ) ).toEqual( [
			{ heading: 'Old' },
			[],
		] );
	} );

	it( 'does not add isEligible to a static entry', () => {
		const [ deprecated ] = buildDeprecations( {
			settings: {},
			render: {
				mode: 'static',
				deprecated: [ { markup: { tag: 'div' } } ],
			},
		} );

		expect( deprecated ).not.toHaveProperty( 'isEligible' );
	} );

	it( 'a declarative migrate also works on a static entry', () => {
		const [ deprecated ] = buildDeprecations( {
			settings: {},
			render: {
				mode: 'static',
				deprecated: [
					{
						markup: { tag: 'div' },
						migrate: { set: { v: 2 } },
					},
				],
			},
		} );

		expect( deprecated.migrate( {}, [] ) ).toEqual( [ { v: 2 }, [] ] );
	} );
} );
