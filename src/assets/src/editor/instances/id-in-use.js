/**
 * The "is this id already used somewhere else?" extension point.
 *
 * A consumer registers one or more checks with
 * `registerIdInUseCheck( fn )`, where `fn( id, blockName )` returns a
 * boolean or a Promise of one (for example a lookup against its own
 * server-side index). The watcher consults it only for blocks that appeared
 * in the editor (inserted or pasted) and kept their uuid value; a true
 * answer makes the library regenerate that copy's id.
 */

const checks = [];
let warned = false;

/**
 * Register an id-in-use check.
 *
 * @param {Function} check ( id, blockName ) => boolean | Promise<boolean>.
 */
export function registerIdInUseCheck( check ) {
	if ( 'function' === typeof check ) {
		checks.push( check );
	}
}

/**
 * Remove every registered check (tests).
 */
export function resetIdInUseChecks() {
	checks.length = 0;
	warned = false;
}

/**
 * Whether any registered check reports the id as in use. Errors and
 * rejections are ignored (logged once outside production builds).
 *
 * @param {string} id        The uuid value.
 * @param {string} blockName The block it belongs to.
 * @return {Promise<boolean>} True when some check says the id is taken.
 */
export async function isIdInUse( id, blockName ) {
	const warn = ( error ) => {
		if ( ! warned && 'production' !== process.env.NODE_ENV ) {
			warned = true;
			// eslint-disable-next-line no-console
			console.warn(
				'Cassette-CMF Blocks: an isIdInUse check failed and was ignored.',
				error
			);
		}
	};

	const results = await Promise.all(
		checks.map( async ( check ) => {
			try {
				return Boolean( await check( id, blockName ) );
			} catch ( error ) {
				warn( error );
				return false;
			}
		} )
	);

	return results.includes( true );
}
