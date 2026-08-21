/**
 * A minimal slug helper for deriving a stable tab "name" (id) from its
 * "title" when a consumer doesn't supply one explicitly. Not a full i18n
 * transliteration — good enough for a client-side React key/identity, not
 * used for anything persisted.
 *
 * @param {string} value Source string.
 * @return {string} Lowercase, hyphenated slug; empty string if nothing usable.
 */
export function slugify( value ) {
	if ( ! value || 'string' !== typeof value ) {
		return '';
	}

	return value
		.toLowerCase()
		.trim()
		.replace( /[^a-z0-9]+/g, '-' )
		.replace( /^-+|-+$/g, '' );
}
