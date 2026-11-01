import apiFetch from '@wordpress/api-fetch';
import { createBlock } from '@wordpress/blocks';
import { dispatch, select } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

/**
 * Key inside a block's generic `metadata` attribute that marks a block whose
 * attributes are still being fetched from the server (see requestTransform()).
 */
export const PENDING_KEY = 'cassetteCmfPendingTransform';

/**
 * Ids of the requests still in flight. A marker found on a block that is not
 * listed here is stale (for example from a post saved mid-request) and is
 * ignored by the loading state.
 */
const inFlight = new Set();

let counter = 0;

/**
 * Whether a block's attributes carry the marker of a request still running.
 *
 * @param {Object} attributes Block attributes.
 * @return {boolean} True while the server call for this block is in flight.
 */
export function isPending( attributes ) {
	const id = attributes?.metadata?.[ PENDING_KEY ];

	return Boolean( id && inFlight.has( id ) );
}

/**
 * Build real blocks from the `innerBlocks` list a transform callback returned.
 *
 * @param {Array} list [ { name, attributes?, innerBlocks? } ]
 * @return {Array} Blocks ready for replaceInnerBlocks().
 */
export function buildInnerBlocks( list ) {
	return ( Array.isArray( list ) ? list : [] ).map( ( item ) =>
		createBlock(
			item.name,
			item.attributes || {},
			buildInnerBlocks( item.innerBlocks )
		)
	);
}

/**
 * Find a block in a tree by clientId or by its pending marker.
 *
 * @param {Array}  blocks   Block tree.
 * @param {string} clientId Preferred match.
 * @param {string} id       Marker id.
 * @return {Object|null} The block, or null.
 */
function findBlock( blocks, clientId, id ) {
	let byMarker = null;

	const visit = ( list ) => {
		for ( const block of list ) {
			if ( block.clientId === clientId ) {
				return block;
			}
			if (
				! byMarker &&
				block.attributes?.metadata?.[ PENDING_KEY ] === id
			) {
				byMarker = block;
			}
			const found = visit( block.innerBlocks || [] );
			if ( found ) {
				return found;
			}
		}
		return null;
	};

	return visit( blocks ) || byMarker;
}

/**
 * Metadata without the pending marker (undefined when nothing is left).
 *
 * @param {Object} metadata Block metadata.
 * @return {Object|undefined} Cleaned metadata.
 */
function withoutMarker( metadata ) {
	const rest = { ...( metadata || {} ) };
	delete rest[ PENDING_KEY ];

	return Object.keys( rest ).length ? rest : undefined;
}

/**
 * POST the transform input to the library's REST route.
 *
 * Core's REST middleware adds the nonce header (X-WP-Nonce) in the editor, so
 * the call is authenticated like any other editor request.
 *
 * @param {Object}        args
 * @param {string}        args.restNamespace REST namespace from the payload.
 * @param {string}        args.block         Block name.
 * @param {number}        args.index         Position in `transforms.from`.
 * @param {Object|string} args.input         Shortcode attributes, or the HTML.
 * @param {number}        [args.postId]      Current post, when known.
 * @return {Promise<Object>} { attributes, innerBlocks? }
 */
export function requestTransform( {
	restNamespace,
	block,
	index,
	input,
	postId = 0,
} ) {
	return apiFetch( {
		path: `/${ restNamespace }/transform`,
		method: 'POST',
		data: { block, index, input, post_id: postId },
	} );
}

/**
 * Create the block for a shortcode / raw transform right away (core's
 * transform() must return synchronously), marked as pending, and resolve its
 * attributes through the REST route in the background.
 *
 * - On success the attributes (and inner blocks) are applied and the marker is
 *   removed.
 * - On failure an error notice is shown and the block is replaced by the
 *   fallback block (core/shortcode or core/html) holding the original content.
 *
 * @param {Object}   args
 * @param {string}   args.blockName     Block to create.
 * @param {number}   args.index         Position in `transforms.from`.
 * @param {*}        args.input         Request input.
 * @param {Function} args.fallback      () => block holding the original content.
 * @param {string}   args.restNamespace REST namespace.
 * @return {Object} The (pending) block.
 */
export function startRemoteTransform( {
	blockName,
	index,
	input,
	fallback,
	restNamespace,
} ) {
	counter += 1;
	const id = `cmf-transform-${ counter }-${ Date.now().toString( 36 ) }`;
	const block = createBlock( blockName, {
		metadata: { [ PENDING_KEY ]: id },
	} );

	inFlight.add( id );

	let postId = 0;
	try {
		postId = Number( select( 'core/editor' )?.getCurrentPostId?.() ) || 0;
	} catch ( error ) {
		postId = 0;
	}

	Promise.resolve()
		.then( () =>
			requestTransform( {
				restNamespace,
				block: blockName,
				index,
				input,
				postId,
			} )
		)
		.then(
			( result ) => applyResult( id, block.clientId, result ),
			( error ) => applyFailure( id, block.clientId, error, fallback )
		)
		.catch( ( error ) =>
			applyFailure( id, block.clientId, error, fallback )
		);

	return block;
}

/**
 * Apply a successful response to the pending block.
 *
 * @param {string} id       Marker id.
 * @param {string} clientId Block clientId at creation.
 * @param {Object} result   { attributes, innerBlocks? }
 */
function applyResult( id, clientId, result ) {
	inFlight.delete( id );

	const editor = select( 'core/block-editor' );
	const block = findBlock( editor.getBlocks(), clientId, id );

	// The user removed the block while the request ran: nothing to update.
	if ( ! block ) {
		return;
	}

	const actions = dispatch( 'core/block-editor' );

	actions.updateBlockAttributes( block.clientId, {
		...( result?.attributes || {} ),
		metadata: withoutMarker( block.attributes?.metadata ),
	} );

	if ( Array.isArray( result?.innerBlocks ) && result.innerBlocks.length ) {
		actions.replaceInnerBlocks(
			block.clientId,
			buildInnerBlocks( result.innerBlocks )
		);
	}
}

/**
 * Handle a failed request: notice, then the raw content as a fallback block.
 *
 * @param {string}   id       Marker id.
 * @param {string}   clientId Block clientId at creation.
 * @param {Object}   error    The apiFetch error ({ message, code }).
 * @param {Function} fallback () => block.
 */
function applyFailure( id, clientId, error, fallback ) {
	inFlight.delete( id );

	const message =
		error?.message ||
		__(
			'The content could not be converted.',
			'cassette-cmf-block-builder'
		);

	dispatch( 'core/notices' )?.createErrorNotice?.( message, {
		type: 'snackbar',
		isDismissible: true,
	} );

	const block = findBlock(
		select( 'core/block-editor' ).getBlocks(),
		clientId,
		id
	);

	if ( block && 'function' === typeof fallback ) {
		dispatch( 'core/block-editor' ).replaceBlock(
			block.clientId,
			fallback()
		);
	}
}
