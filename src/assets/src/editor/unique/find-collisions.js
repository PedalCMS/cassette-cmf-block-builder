import { applyFormat } from '../util/apply-format';

/**
 * The pure "is this value unique?" rule behind a text-like field's
 * "unique" setting. PHP twin: Render\Unique_Checker. Both are checked
 * against tests/fixtures/unique.json.
 *
 * Rule: values are trimmed, then the field's "format" is applied, then
 * compared case-sensitively; an empty value never collides. Scope "post"
 * puts every participating block in one group; scope "ancestor" groups
 * blocks by their nearest ancestor whose block name equals rule.block (any
 * depth), with all blocks that have no such ancestor forming one separate
 * "unscoped" group.
 */

/**
 * Normalize a raw value for comparison: trim, then apply the format.
 *
 * @param {*}      value  Raw attribute value.
 * @param {string} format Field format ("slug", "upper", "lower") or unset.
 * @return {string} The comparable value; '' for empty or non-string input.
 */
export function normalizeValue( value, format ) {
	if ( 'string' !== typeof value ) {
		return '';
	}

	return applyFormat( value.trim(), format );
}

/**
 * The scope-group id an entry falls into under a rule.
 *
 * @param {Object} entry Entry: { ancestors: [ { id, blockName } ] } (outermost first).
 * @param {Object} rule  Canonical rule: { scope, block? }.
 * @return {string} Group id.
 */
export function scopeId( entry, rule ) {
	if ( 'ancestor' !== rule.scope ) {
		return 'post';
	}

	const ancestors = entry.ancestors || [];

	for ( let i = ancestors.length - 1; i >= 0; i-- ) {
		if ( ancestors[ i ].blockName === rule.block ) {
			return 'ancestor:' + ancestors[ i ].id;
		}
	}

	return 'unscoped';
}

/**
 * Bucket entries by scope group, then by normalized value. Empty values are
 * skipped. Insertion order of every Map follows the entries' order.
 *
 * @param {Array}  entries Flat entries: { id, blockName, value, format?, ancestors? }, in document order.
 * @param {Object} rule    Canonical rule: { scope, block? }.
 * @return {Map<string, Map<string, Array>>} scope id -> normalized value -> entries (each with `normalized` added).
 */
export function groupEntries( entries, rule ) {
	const groups = new Map();

	entries.forEach( ( entry ) => {
		const normalized = normalizeValue( entry.value, entry.format );

		if ( '' === normalized ) {
			return;
		}

		const id = scopeId( entry, rule );
		let values = groups.get( id );

		if ( ! values ) {
			values = new Map();
			groups.set( id, values );
		}

		const list = values.get( normalized );
		const item = { ...entry, normalized };

		if ( list ) {
			list.push( item );
		} else {
			values.set( normalized, [ item ] );
		}
	} );

	return groups;
}

/**
 * Find colliding values.
 *
 * @param {Array}  entries Flat entries in document order: { id, blockName, value, format?, ancestors? }.
 *                         `ancestors` is [ { id, blockName } ], outermost first (only read for scope "ancestor").
 * @param {Object} rule    Canonical rule: { scope: 'post'|'ancestor', block? }.
 * @return {Array<{value: string, ids: Array}>} One item per colliding value and scope group, ordered by first
 *                                              occurrence; `ids` lists the colliding entries' ids in document order.
 */
export function findCollisions( entries, rule ) {
	const order = new Map(
		entries.map( ( entry, index ) => [ entry.id, index ] )
	);
	const collisions = [];

	groupEntries( entries, rule ).forEach( ( values ) => {
		values.forEach( ( list, value ) => {
			if ( list.length > 1 ) {
				collisions.push( {
					value,
					ids: list.map( ( entry ) => entry.id ),
				} );
			}
		} );
	} );

	return collisions.sort(
		( a, b ) => order.get( a.ids[ 0 ] ) - order.get( b.ids[ 0 ] )
	);
}

/**
 * The first free "-2", "-3", ... suffix for a value inside a set of taken
 * (normalized) values, applying the field format.
 *
 * @param {string}      value  The colliding value (trimmed before use).
 * @param {string}      format Field format or unset.
 * @param {Set<string>} taken  Normalized values already used in the scope.
 * @return {string} The suffixed, formatted value (not yet added to `taken`).
 */
export function nextFreeValue( value, format, taken ) {
	const base = ( value || '' ).trim();

	let n = 2;
	let candidate = applyFormat( `${ base }-${ n }`, format );

	while ( taken.has( normalizeValue( candidate, format ) ) ) {
		n++;
		candidate = applyFormat( `${ base }-${ n }`, format );
	}

	return candidate;
}

/**
 * Flatten a parse_blocks()-shaped tree (blockName / attrs / innerBlocks,
 * or name / attributes / innerBlocks) into entries for findCollisions().
 * Ids are the index path joined by dots, e.g. "0.2.1". Used by the shared
 * fixture test and handy for running the rule on saved content.
 *
 * @param {Array}  blocks       Block tree.
 * @param {Object} participants Block name -> { attribute, format? }.
 * @return {Array} Entries in document order.
 */
export function entriesFromTree( blocks, participants ) {
	const entries = [];

	const visit = ( list, path, ancestors ) => {
		list.forEach( ( block, index ) => {
			const blockPath = [ ...path, index ];
			const id = blockPath.join( '.' );
			const name = block.blockName ?? block.name;
			const participant = participants[ name ];

			if ( participant ) {
				const attrs = block.attrs ?? block.attributes ?? {};
				entries.push( {
					id,
					blockName: name,
					value: attrs[ participant.attribute ],
					format: participant.format || undefined,
					ancestors,
				} );
			}

			if ( block.innerBlocks && block.innerBlocks.length ) {
				visit(
					block.innerBlocks,
					blockPath,
					name ? [ ...ancestors, { id, blockName: name } ] : ancestors
				);
			}
		} );
	};

	visit( blocks, [], [] );

	return entries;
}
