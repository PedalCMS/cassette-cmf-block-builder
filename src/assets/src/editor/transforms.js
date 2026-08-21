import { createBlock } from '@wordpress/blocks';

/**
 * Block-to-block transforms, built from a block's own top-level
 * "transforms" config — a sibling of "render"/"preview"/"inner_blocks",
 * never part of "args" (which is a verbatim block.json passthrough;
 * "transforms" here is NOT literal register_block_type() data, it needs
 * this module's own interpretation to become a real transform object).
 *
 * Deliberately a bounded, honest subset of WordPress's own transforms
 * API: only "type: 'block'" from/to transforms, declared as a plain
 * attribute-name remap (a { thisBlockAttr: otherBlockAttr } object) —
 * zero consumer JS, per the design plan's stated contract. "raw" (pasted
 * HTML), "shortcode", "enter", and "prefix" transforms all fundamentally
 * need a real predicate/transform FUNCTION (a regex test, an HTML-node
 * inspector, ...) that no JSON-safe declarative shape can express
 * honestly — those are NOT implemented, and a "transforms" config
 * declaring one is silently ignored rather than guessed at. See
 * docs/block-config-reference.md.
 *
 * @param {Object} descriptor One block's Editor_Payload entry: { name, transforms, ... }.
 * @return {Object|undefined} A registerBlockType()-ready "transforms" object, or undefined if none apply.
 */
export function buildTransforms( descriptor ) {
	const raw = descriptor.transforms;

	if ( ! raw || 'object' !== typeof raw ) {
		return undefined;
	}

	const from = ( raw.from || [] )
		.map( ( entry ) => buildBlockTransform( entry, descriptor.name ) )
		.filter( Boolean );

	const to = ( raw.to || [] )
		.map( ( entry ) => buildBlockTransform( entry, null, true ) )
		.filter( Boolean );

	if ( ! from.length && ! to.length ) {
		return undefined;
	}

	const transforms = {};
	if ( from.length ) {
		transforms.from = from;
	}
	if ( to.length ) {
		transforms.to = to;
	}

	return transforms;
}

/**
 * Build one "type: 'block'" transform entry.
 *
 * @param {Object}  entry           Raw declarative entry: { type, blocks, map }.
 * @param {string}  [thisBlockName] This block's own name — the "from" transform's creation target.
 * @param {boolean} [isTo]          Whether this is a "to" transform (creates entry.blocks[0] instead of thisBlockName).
 * @return {Object|null} A real transform object, or null if the entry isn't a supported shape.
 */
function buildBlockTransform( entry, thisBlockName, isTo = false ) {
	if (
		! entry ||
		'block' !== entry.type ||
		! Array.isArray( entry.blocks ) ||
		! entry.blocks.length
	) {
		return null;
	}

	const targetBlockName = isTo ? entry.blocks[ 0 ] : thisBlockName;
	const map = entry.map || {};

	return {
		type: 'block',
		blocks: entry.blocks,
		transform: ( attributes ) =>
			createBlock( targetBlockName, mapAttributes( attributes, map ) ),
	};
}

/**
 * Remap { targetAttr: sourceAttr } — source is whatever block the
 * transform function receives (the matched block for "from", this block's
 * own attributes for "to"); target is the block being created.
 *
 * @param {Object} source Source attributes.
 * @param {Object} map    { targetAttr: sourceAttr } pairs.
 * @return {Object} The mapped attributes for createBlock().
 */
function mapAttributes( source, map ) {
	const mapped = {};

	Object.entries( map ).forEach( ( [ targetAttr, sourceAttr ] ) => {
		if ( undefined !== source[ sourceAttr ] ) {
			mapped[ targetAttr ] = source[ sourceAttr ];
		}
	} );

	return mapped;
}
