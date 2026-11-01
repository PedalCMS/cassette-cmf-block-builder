/**
 * Pure deprecation helpers, the JS mirror of Render\Deprecation_Migrator
 * (PHP). Both are tested against tests/fixtures/deprecations.json, so the
 * editor (registerBlockType()'s `deprecated`) and the server render path
 * (Block_Definition::sanitize_attributes()) migrate stored attributes the
 * same way.
 */

/**
 * Keys a declarative migration may contain.
 */
export const MIGRATE_KEYS = [ 'rename', 'map', 'set', 'remove' ];

const has = ( object, key ) =>
	Object.prototype.hasOwnProperty.call( object, key );

const asObject = ( value ) =>
	value && 'object' === typeof value && ! Array.isArray( value ) ? value : {};

/**
 * The string a value is looked up by in a `map` table (booleans as
 * "true"/"false"); null for values that are never mapped.
 *
 * @param {*} value Attribute value.
 * @return {string|null} The lookup token.
 */
function valueToken( value ) {
	if ( 'boolean' === typeof value ) {
		return value ? 'true' : 'false';
	}

	return 'string' === typeof value || 'number' === typeof value
		? String( value )
		: null;
}

/**
 * Apply a declarative migration. Pure; returns a new object.
 *
 * Order: rename, map, set, remove. A rename overwrites the target even when it
 * already holds a value. `map` keys name the attribute after renaming.
 *
 * @param {Object} spec       { rename?, map?, set?, remove? }.
 * @param {Object} attributes Attributes in the old shape.
 * @return {Object} Attributes in the new shape.
 */
export function applyMigration( spec, attributes ) {
	const result = { ...attributes };
	const migration = asObject( spec );

	Object.entries( asObject( migration.rename ) ).forEach(
		( [ from, to ] ) => {
			if ( 'string' === typeof to && has( result, from ) ) {
				result[ to ] = result[ from ];
				delete result[ from ];
			}
		}
	);

	Object.entries( asObject( migration.map ) ).forEach( ( [ key, table ] ) => {
		if ( ! has( result, key ) ) {
			return;
		}
		const token = valueToken( result[ key ] );
		const lookup = asObject( table );
		if ( null !== token && has( lookup, token ) ) {
			result[ key ] = lookup[ token ];
		}
	} );

	Object.entries( asObject( migration.set ) ).forEach( ( [ key, value ] ) => {
		result[ key ] = value;
	} );

	( Array.isArray( migration.remove ) ? migration.remove : [] ).forEach(
		( key ) => {
			if ( 'string' === typeof key ) {
				delete result[ key ];
			}
		}
	);

	return result;
}

/**
 * Whether a deprecation entry applies to stored attributes: they still carry
 * a key that the entry's old schema, `rename` sources or `remove` list name
 * but the current schema does not, or a value one of its `map` tables would
 * change. `set` alone never makes an entry eligible.
 *
 * @param {Object}   entry             The deprecation entry: { attributes?, migrate? }.
 * @param {string[]} currentAttributes Names in the block's current attribute schema.
 * @param {Object}   attributes        The stored attributes.
 * @return {boolean} Whether the entry is eligible.
 */
export function isEligible( entry, currentAttributes, attributes ) {
	const stored = asObject( attributes );
	const spec =
		entry && 'object' === typeof entry.migrate && entry.migrate
			? asObject( entry.migrate )
			: {};

	const oldKeys = [
		...Object.keys( asObject( entry?.attributes ) ),
		...Object.keys( asObject( spec.rename ) ),
		...( Array.isArray( spec.remove )
			? spec.remove.filter( ( key ) => 'string' === typeof key )
			: [] ),
	];

	if (
		oldKeys.some(
			( key ) => ! currentAttributes.includes( key ) && has( stored, key )
		)
	) {
		return true;
	}

	return Object.entries( asObject( spec.map ) ).some( ( [ key, table ] ) => {
		if ( ! has( stored, key ) ) {
			return false;
		}
		const token = valueToken( stored[ key ] );
		return null !== token && has( asObject( table ), token );
	} );
}
