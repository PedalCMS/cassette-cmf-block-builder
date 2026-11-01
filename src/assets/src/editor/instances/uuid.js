/**
 * UUID helpers for the "uuid" field type.
 */

const UUID_PATTERN =
	/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

/**
 * Whether a value is a well-formed UUID (versions 1-8, RFC 4122 variant).
 *
 * @param {*} value Candidate value.
 * @return {boolean} True for a valid UUID string.
 */
export function isValidUuid( value ) {
	return 'string' === typeof value && UUID_PATTERN.test( value );
}

/**
 * Build a v4 UUID from 16 random bytes.
 *
 * @param {Uint8Array|number[]} bytes 16 random bytes; mutated to set the version and variant bits.
 * @return {string} A lowercase v4 UUID.
 */
export function uuidFromBytes( bytes ) {
	/* eslint-disable no-bitwise -- setting the UUID version and variant bits. */
	bytes[ 6 ] = ( bytes[ 6 ] & 0x0f ) | 0x40;
	bytes[ 8 ] = ( bytes[ 8 ] & 0x3f ) | 0x80;
	/* eslint-enable no-bitwise */

	const hex = Array.from( bytes, ( byte ) =>
		byte.toString( 16 ).padStart( 2, '0' )
	).join( '' );

	return [
		hex.slice( 0, 8 ),
		hex.slice( 8, 12 ),
		hex.slice( 12, 16 ),
		hex.slice( 16, 20 ),
		hex.slice( 20, 32 ),
	].join( '-' );
}

/**
 * Generate a lowercase v4 UUID.
 *
 * Uses crypto.randomUUID() (secure contexts only), then
 * crypto.getRandomValues() (works on plain http), and Math.random() only
 * when neither exists.
 *
 * @param {Object} [cryptoImpl] The crypto object to use; defaults to window.crypto. Injectable for tests.
 * @return {string} A lowercase v4 UUID.
 */
export function generateUuid( cryptoImpl = window.crypto ) {
	if ( cryptoImpl && 'function' === typeof cryptoImpl.randomUUID ) {
		return cryptoImpl.randomUUID().toLowerCase();
	}

	const bytes = new Uint8Array( 16 );

	if ( cryptoImpl && 'function' === typeof cryptoImpl.getRandomValues ) {
		cryptoImpl.getRandomValues( bytes );
	} else {
		for ( let i = 0; i < 16; i++ ) {
			bytes[ i ] = Math.floor( Math.random() * 256 );
		}
	}

	return uuidFromBytes( bytes );
}
