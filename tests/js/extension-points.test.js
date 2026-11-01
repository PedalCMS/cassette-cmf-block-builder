/**
 * The public JS extension points (registerControlType, registerMigration,
 * registerIdInUseCheck): calls queued by a consumer script that ran before the
 * bundle are replayed before any block is registered; calls made afterwards go
 * straight to the real functions.
 */

const EDITOR = '../../src/assets/src/editor';
const GLOBAL = 'cassetteCmfBlocks_ext';

/**
 * Evaluate the editor entry point as a fresh bundle instance.
 *
 * @param {Object}   payload      Payload, possibly with pendingExtensions.
 * @param {Function} onRegister   Called with the migration registry state at registerBlocks() time.
 * @return {Object} The bundle's registries.
 */
function loadBundle( payload, onRegister ) {
	window.cassetteCmfBlocksBoot = GLOBAL;
	window[ GLOBAL ] = payload;

	let instance;

	jest.isolateModules( () => {
		instance = {
			controls: require( `${ EDITOR }/controls/registry` ),
			deprecations: require( `${ EDITOR }/deprecations` ),
			idInUse: require( `${ EDITOR }/instances/id-in-use` ),
		};

		jest.doMock( `${ EDITOR }/register-block`, () => ( {
			registerBlocks: () => onRegister( instance ),
		} ) );
		jest.doMock( `${ EDITOR }/areas/document`, () => ( {
			registerDocumentAreas: () => {},
		} ) );
		jest.doMock( `${ EDITOR }/instances/watcher`, () => ( {
			startInstanceWatcher: () => {},
		} ) );

		require( `${ EDITOR }/index.js` );
	} );

	return instance;
}

describe( 'extension points', () => {
	afterEach( () => {
		delete window[ GLOBAL ];
		delete window.cassetteCmfBlocksBoot;
	} );

	it( 'replays calls queued before the bundle ran, ahead of registerBlocks()', () => {
		const Stars = () => null;
		const migrate = ( attributes ) => attributes;
		const check = () => false;
		let seenAtRegistration;

		const instance = loadBundle(
			{
				blocks: {},
				pendingExtensions: [
					[ 'registerControlType', [ 'star_rating', Stars ] ],
					[ 'registerMigration', [ 'acme_v1', migrate ] ],
					[ 'registerIdInUseCheck', [ check ] ],
					[ 'somethingElse', [ 1 ] ],
				],
			},
			( bundle ) => {
				seenAtRegistration = {
					control:
						bundle.controls.getControlComponent( 'star_rating' ),
				};
			}
		);

		expect( seenAtRegistration.control ).toBe( Stars );
		expect( instance.controls.getControlComponent( 'star_rating' ) ).toBe(
			Stars
		);
		expect( window[ GLOBAL ].pendingExtensions ).toBeUndefined();
	} );

	it( 'a migration queued before the bundle is resolved when deprecations are built', () => {
		const migrate = jest.fn( ( attributes ) => ( { ...attributes, ok: 1 } ) );

		const instance = loadBundle(
			{
				blocks: {},
				pendingExtensions: [
					[ 'registerMigration', [ 'acme_v1', migrate ] ],
				],
			},
			() => {}
		);

		const [ entry ] = instance.deprecations.buildDeprecations( {
			settings: { attributes: {} },
			fields: [],
			render: {
				mode: 'dynamic',
				deprecated: [
					{ attributes: { old: { type: 'string' } }, migrate: 'acme_v1' },
				],
			},
		} );

		expect( entry.migrate( { old: 'x' } ) ).toEqual( { old: 'x', ok: 1 } );
	} );

	it( 'replaces the stubs with the real functions for scripts that run later', () => {
		const payload = { blocks: {}, pendingExtensions: [] };
		const instance = loadBundle( payload, () => {} );
		const Later = () => null;

		window[ GLOBAL ].registerControlType( 'later_type', Later );

		expect( instance.controls.getControlComponent( 'later_type' ) ).toBe(
			Later
		);
		expect( typeof window[ GLOBAL ].registerMigration ).toBe( 'function' );
		expect( typeof window[ GLOBAL ].registerIdInUseCheck ).toBe(
			'function'
		);
	} );

	it( 'works without any queue', () => {
		expect( () => loadBundle( { blocks: {} }, () => {} ) ).not.toThrow();
	} );

	it( 'ignores malformed registrations and prototype-polluting type ids', () => {
		const instance = loadBundle( { blocks: {} }, () => {} );
		const { registerControlType, getControlComponent } = instance.controls;

		registerControlType( '__proto__', () => null );
		registerControlType( 'Bad-Type', () => null );
		registerControlType( 'ok_type', 'not a function' );

		expect( getControlComponent( 'constructor' ) ).not.toBe( Object );
		expect( getControlComponent( '__proto__' ) ).toBe(
			getControlComponent( 'definitely_unknown' )
		);
		expect( getControlComponent( 'ok_type' ) ).toBe(
			getControlComponent( 'definitely_unknown' )
		);
	} );
} );
