/**
 * Coexistence: two copies of the editor bundle on one page.
 *
 * The bundle learns the name of its payload global from a one-shot hand-over
 * (window.cassetteCmfBlocksBoot), then reads window[ name ]. Each bundle load
 * evaluates its own copy of every module, which jest.isolateModules() models:
 * the same built file loaded twice gives two independent module instances.
 */

const EDITOR = '../../src/assets/src/editor';

/**
 * Evaluate the editor entry point once, as a fresh bundle instance, after
 * publishing a payload the way Asset_Loader's inline script does.
 *
 * @param {string} globalName Payload global name.
 * @param {Object} payload    Payload.
 * @param {Object} recorder   Collects what the instance registered.
 * @return {Object} The instance's registries.
 */
function loadBundle( globalName, payload, recorder ) {
	window.cassetteCmfBlocksBoot = globalName;
	window[ globalName ] = payload;

	let instance;

	jest.isolateModules( () => {
		jest.doMock( `${ EDITOR }/register-block`, () => ( {
			registerBlocks: ( p ) =>
				recorder.registered.push(
					Object.values( p.blocks ).map( ( d ) => d.name )
				),
		} ) );
		jest.doMock( `${ EDITOR }/areas/document`, () => ( {
			registerDocumentAreas: () => {},
		} ) );
		jest.doMock( `${ EDITOR }/instances/watcher`, () => ( {
			startInstanceWatcher: ( p ) =>
				recorder.watched.push(
					Object.values( p.blocks ).map( ( d ) => d.name )
				),
		} ) );

		require( `${ EDITOR }/index.js` );

		instance = {
			controls: require( `${ EDITOR }/controls/registry` ),
			deprecations: require( `${ EDITOR }/deprecations` ),
			idInUse: require( `${ EDITOR }/instances/id-in-use` ),
		};
	} );

	return instance;
}

const block = ( name ) => ( {
	name,
	settings: { title: name },
	fields: [],
	render: { mode: 'dynamic' },
} );

describe( 'editor bootstrap hand-over', () => {
	let recorder;

	beforeEach( () => {
		recorder = { registered: [], watched: [] };
		jest.resetModules();
	} );

	afterEach( () => {
		[ 'alpha', 'beta', 'solo' ].forEach( ( key ) => {
			delete window[ `cassetteCmfBlocks_${ key }` ];
		} );
		delete window.cassetteCmfBlocksBoot;
	} );

	it( 'reads the payload named by the hand-over and deletes the hand-over', () => {
		loadBundle(
			'cassetteCmfBlocks_solo',
			{ blocks: { a: block( 'solo/one' ) } },
			recorder
		);

		expect( recorder.registered ).toEqual( [ [ 'solo/one' ] ] );
		expect( 'cassetteCmfBlocksBoot' in window ).toBe( false );
	} );

	it( 'does nothing, and does not throw, without a hand-over', () => {
		window.cassetteCmfBlocks_solo = { blocks: { a: block( 'solo/one' ) } };

		jest.isolateModules( () => {
			jest.doMock( `${ EDITOR }/register-block`, () => ( {
				registerBlocks: ( p ) => recorder.registered.push( p ),
			} ) );
			jest.doMock( `${ EDITOR }/areas/document`, () => ( {
				registerDocumentAreas: () => {},
			} ) );
			jest.doMock( `${ EDITOR }/instances/watcher`, () => ( {
				startInstanceWatcher: () => {},
			} ) );

			expect( () => require( `${ EDITOR }/index.js` ) ).not.toThrow();
		} );

		expect( recorder.registered ).toEqual( [] );
	} );

	it( 'two bundles with two payloads each register only their own blocks', () => {
		loadBundle(
			'cassetteCmfBlocks_alpha',
			{ blocks: { a: block( 'alpha/card' ) } },
			recorder
		);
		loadBundle(
			'cassetteCmfBlocks_beta',
			{ blocks: { b: block( 'beta/note' ), c: block( 'beta/list' ) } },
			recorder
		);

		expect( recorder.registered ).toEqual( [
			[ 'alpha/card' ],
			[ 'beta/note', 'beta/list' ],
		] );
		// Each bundle's instance watcher is started with its own payload only.
		expect( recorder.watched ).toEqual( [
			[ 'alpha/card' ],
			[ 'beta/note', 'beta/list' ],
		] );
	} );

	it( 'the extension points hang off each bundle’s own payload global', () => {
		loadBundle(
			'cassetteCmfBlocks_alpha',
			{ blocks: { a: block( 'alpha/card' ) } },
			recorder
		);
		loadBundle(
			'cassetteCmfBlocks_beta',
			{ blocks: { b: block( 'beta/note' ) } },
			recorder
		);

		[ 'alpha', 'beta' ].forEach( ( key ) => {
			const global = window[ `cassetteCmfBlocks_${ key }` ];

			expect( typeof global.registerControlType ).toBe( 'function' );
			expect( typeof global.registerMigration ).toBe( 'function' );
			expect( typeof global.registerIdInUseCheck ).toBe( 'function' );
		} );

		expect( window.cassetteCmfBlocks_alpha.registerControlType ).not.toBe(
			window.cassetteCmfBlocks_beta.registerControlType
		);
		expect( window.cassetteCmfBlocks ).toBeUndefined();
	} );

	it( 'registries are per bundle instance', async () => {
		const alpha = loadBundle(
			'cassetteCmfBlocks_alpha',
			{ blocks: { a: block( 'alpha/card' ) } },
			recorder
		);
		const beta = loadBundle(
			'cassetteCmfBlocks_beta',
			{ blocks: { b: block( 'beta/note' ) } },
			recorder
		);

		// Control registry: a control registered on alpha's global is not seen by beta.
		const AlphaOnly = () => null;
		window.cassetteCmfBlocks_alpha.registerControlType(
			'alpha_only',
			AlphaOnly
		);

		expect( alpha.controls.getControlComponent( 'alpha_only' ) ).toBe(
			AlphaOnly
		);
		expect( beta.controls.getControlComponent( 'alpha_only' ) ).not.toBe(
			AlphaOnly
		);

		// Migration registry: the same goes for named migrations.
		const migrate = jest.fn( ( attributes ) => attributes );
		window.cassetteCmfBlocks_alpha.registerMigration( 'm1', migrate );
		const markup = { tag: 'p' };
		const entry = {
			render: { deprecated: [ { markup, migrate: 'm1' } ] },
			settings: {},
		};
		const alphaDeprecated =
			alpha.deprecations.buildDeprecations( entry );
		const betaDeprecated = beta.deprecations.buildDeprecations( entry );

		expect( alphaDeprecated[ 0 ].migrate ).toBeDefined();
		expect( betaDeprecated[ 0 ].migrate ).toBeUndefined();

		// id-in-use checks: a check registered by alpha never vetoes beta's ids.
		window.cassetteCmfBlocks_alpha.registerIdInUseCheck( () => true );
		await expect(
			alpha.idInUse.isIdInUse( 'x', 'alpha/card' )
		).resolves.toBe( true );
		await expect(
			beta.idInUse.isIdInUse( 'x', 'beta/note' )
		).resolves.toBe( false );
	} );
} );
