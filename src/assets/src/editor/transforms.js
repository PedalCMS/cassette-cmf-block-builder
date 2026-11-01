import { createBlock } from '@wordpress/blocks';
import { startRemoteTransform } from './remote-transform/remote';
import { buildRawMatcher, buildRawSchema } from './remote-transform/raw';

/**
 * Block-to-block transforms, built from a block's own top-level
 * "transforms" config — a sibling of "render"/"preview"/"inner_blocks",
 * never part of "args" (which is a verbatim block.json passthrough;
 * "transforms" here is NOT literal register_block_type() data, it needs
 * this module's own interpretation to become a real transform object).
 *
 * Three kinds of "from" entry:
 *  - "type: 'block'": a plain attribute remap, entirely client-side (also "to").
 *  - "type: 'shortcode'" ({ tag }): a real core shortcode transform. The
 *    PHP callback behind it runs on the server (remote-transform/remote.js).
 *  - "type: 'raw'" ({ selector }): a real core raw transform (pasted HTML),
 *    same server round trip.
 * Core's transform() must return a block synchronously, so the block is
 * created at once with a pending marker and its attributes are filled in when
 * the REST call returns; on failure a notice is shown and the original
 * content is kept (core/shortcode or core/html). "enter" and "prefix"
 * transforms still need a JS predicate and are ignored. See
 * docs/block-config-reference.md.
 *
 * @param {Object} descriptor One block's Editor_Payload entry: { name, transforms, ... }.
 * @param {Object} [options]  { restNamespace }: the REST namespace of the transform route (payload.restNamespace).
 * @return {Object|undefined} A registerBlockType()-ready "transforms" object, or undefined if none apply.
 */
export function buildTransforms( descriptor, options = {} ) {
	const raw = descriptor.transforms;

	if ( ! raw || 'object' !== typeof raw ) {
		return undefined;
	}

	const from = ( raw.from || [] )
		.map( ( entry, index ) =>
			'shortcode' === entry?.type || 'raw' === entry?.type
				? buildRemoteTransform(
						entry,
						index,
						descriptor.name,
						options.restNamespace
				  )
				: buildBlockTransform( entry, descriptor.name )
		)
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
 * Build one "shortcode" or "raw" transform backed by a PHP callback.
 *
 * @param {Object} entry         Payload entry: { type, tag | selector, remote }.
 * @param {number} index         Position in the block's "transforms.from" list (the REST route's key).
 * @param {string} blockName     The block this transform creates.
 * @param {string} restNamespace REST namespace.
 * @return {Object|null} A core transform object, or null when the entry is incomplete.
 */
function buildRemoteTransform( entry, index, blockName, restNamespace ) {
	if ( ! restNamespace ) {
		return null;
	}

	if ( 'shortcode' === entry.type ) {
		if ( ! entry.tag ) {
			return null;
		}

		return {
			type: 'shortcode',
			tag: entry.tag,
			transform: ( attrs, match ) =>
				startRemoteTransform( {
					blockName,
					index,
					restNamespace,
					input: flattenShortcodeAttrs( attrs ),
					fallback: () =>
						createBlock( 'core/shortcode', {
							text: match?.content ?? '[' + entry.tag + ']',
						} ),
				} ),
		};
	}

	const isMatch = buildRawMatcher( entry.selector );
	if ( ! isMatch ) {
		return null;
	}

	const transform = {
		type: 'raw',
		isMatch,
		transform: ( node ) =>
			startRemoteTransform( {
				blockName,
				index,
				restNamespace,
				input: node.outerHTML,
				fallback: () =>
					createBlock( 'core/html', { content: node.outerHTML } ),
			} ),
	};
	const schema = buildRawSchema( entry.selector );
	if ( schema ) {
		transform.schema = schema;
	}

	return transform;
}

/**
 * WordPress shortcode attrs ({ named, numeric }) as the flat object PHP's
 * shortcode_parse_atts() gives: named keys, plus positional values under
 * integer keys.
 *
 * @param {Object} attrs wp.shortcode attrs.
 * @return {Object} Flat attributes.
 */
export function flattenShortcodeAttrs( attrs ) {
	if ( ! attrs || 'object' !== typeof attrs ) {
		return {};
	}

	if ( ! attrs.named && ! attrs.numeric ) {
		return { ...attrs };
	}

	const flat = {};
	( attrs.numeric || [] ).forEach( ( value, position ) => {
		flat[ position ] = value;
	} );

	return { ...flat, ...( attrs.named || {} ) };
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
