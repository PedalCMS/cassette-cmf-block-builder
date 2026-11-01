/**
 * Pure option builder for the `ancestor_field` control (and the block picker
 * of the `conditions` control).
 *
 * Given the ancestor block's subtree it lists every descendant that has a
 * reference name, as { value, label, blockName, clientId }. `value` is the
 * block's `name` attribute value, never a clientId.
 */

/**
 * Normalize raw control options (snake_case, as shipped by the payload) and
 * fill the defaults, so callers can pass either a canonical or a partial
 * object.
 *
 * @param {Object} config Raw options: { ancestor, blocks?, has_attribute?, name_attribute?, label_attribute?, exclude_self? }.
 * @return {Object} Options with every key present.
 */
export function normalizeAncestorConfig( config ) {
	const source = config || {};

	return {
		ancestor: source.ancestor || '',
		blocks: Array.isArray( source.blocks ) ? source.blocks : [],
		hasAttribute: source.has_attribute || '',
		nameAttribute: source.name_attribute || 'name',
		labelAttribute: source.label_attribute || 'label',
		excludeSelf: false !== source.exclude_self,
	};
}

/**
 * Whether an attribute value counts as empty.
 *
 * @param {*} value Attribute value.
 * @return {boolean} True for undefined, null, '' and [].
 */
function isEmpty( value ) {
	return (
		undefined === value ||
		null === value ||
		'' === value ||
		( Array.isArray( value ) && 0 === value.length )
	);
}

/**
 * Build the picker options from an ancestor's subtree.
 *
 * Descendants are collected at any depth, in document order. A block is listed
 * when its name attribute is a non-empty string, it passes the `blocks`
 * allow-list and `has_attribute` presence filters, and it is not the block
 * the control sits on (`exclude_self`). The first block wins for a duplicate
 * name. A current value that matches no listed block is appended as
 * `missing: <name>` (flagged `missing: true`) so it is shown and kept.
 *
 * @param {Array}  innerBlocks  The ancestor block's `innerBlocks` (a block tree).
 * @param {Object} config       Raw control options (see normalizeAncestorConfig()).
 * @param {Object} [state]      { selfClientId?: string, currentValue?: string }.
 * @param {string} [missingFmt] Label format for a missing value; `%s` is replaced by the value.
 * @return {Array<{value: string, label: string, blockName: string, clientId: string, missing?: boolean}>} Options.
 */
export function buildAncestorOptions(
	innerBlocks,
	config,
	state = {},
	missingFmt = 'missing: %s'
) {
	const options = normalizeAncestorConfig( config );
	const { selfClientId, currentValue } = state;
	const result = [];
	const seen = new Set();

	const visit = ( blocks ) => {
		( blocks || [] ).forEach( ( block ) => {
			const attributes = block.attributes || {};
			const name = attributes[ options.nameAttribute ];

			const listed =
				'string' === typeof name &&
				'' !== name &&
				! seen.has( name ) &&
				( 0 === options.blocks.length ||
					options.blocks.includes( block.name ) ) &&
				( ! options.hasAttribute ||
					! isEmpty( attributes[ options.hasAttribute ] ) ) &&
				! ( options.excludeSelf && block.clientId === selfClientId );

			if ( listed ) {
				seen.add( name );
				const label = attributes[ options.labelAttribute ];
				result.push( {
					value: name,
					label:
						'string' === typeof label && '' !== label
							? label
							: name,
					blockName: block.name,
					clientId: block.clientId,
				} );
			}

			visit( block.innerBlocks );
		} );
	};

	visit( innerBlocks );

	if (
		'string' === typeof currentValue &&
		'' !== currentValue &&
		! seen.has( currentValue )
	) {
		result.push( {
			value: currentValue,
			label: missingFmt.replace( '%s', currentValue ),
			blockName: '',
			clientId: '',
			missing: true,
		} );
	}

	return result;
}
