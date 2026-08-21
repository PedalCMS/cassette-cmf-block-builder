import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';
import { createElement } from '@wordpress/element';
import { interpolate } from '../markup/expr';
import { renderNodeContent } from '../markup/render';
import { evaluate as evaluateCondition } from '../conditions/evaluate';

/**
 * Instant, network-free canvas preview: renders a block's declarative
 * markup tree directly against its live attributes, with the SAME markup
 * tree the front end renders from (Render\Markup_Renderer, PHP) — "one
 * declarative markup tree drives three runtimes".
 *
 * The root node is handled specially, unlike every other node in the tree:
 * its class/attrs merge through useBlockProps() (the client-side
 * equivalent of Render\Markup_Renderer's get_block_wrapper_attributes()
 * call for the root), so block "supports" (color, spacing, align, ...)
 * actually apply in the editor the same way they do on the front end. That
 * needs a React hook, so it has to happen in a component body — the reason
 * this is its own component rather than just another renderNode() call
 * (renderNode() checks a node's own "when" internally; the root's own
 * "when" is checked here instead, for the same reason).
 *
 * A "slot": "inner_blocks"/"content" node renders a real, editable
 * <InnerBlocks /> when innerBlocksConfig.enabled (Core\Editor_Payload's
 * "innerBlocks" entry, built from a block's top-level "inner_blocks"
 * config) — the same renderSlot mechanism save.js uses for
 * <InnerBlocks.Content />, applied here instead to the editable component.
 * Without it, a slot node falls back to markup/render.js's default (an
 * empty RawHTML placeholder — there's no rendered inner content string in
 * the editor to inject anyway).
 *
 * @param {Object} props                     Component props.
 * @param {Object} props.markup              The block's declarative markup tree (root node).
 * @param {Object} props.attributes          Live block attributes.
 * @param {string} [props.innerContent]      InnerBlocks content for a "slot" node when innerBlocksConfig is
 *                                           disabled; always "" in the editor (there's nothing pre-rendered here).
 * @param {Object} [props.innerBlocksConfig] Core\Editor_Payload's "innerBlocks" entry: { enabled, allowed?, template?, templateLock?, orientation? }.
 * @return {JSX.Element} The rendered preview.
 */
export default function MarkupPreview( {
	markup,
	attributes,
	innerContent = '',
	innerBlocksConfig,
} ) {
	const scope = { attributes };
	const tag = markup.tag || 'div';

	const extraProps = {};
	if ( markup.class ) {
		extraProps.className = interpolate( markup.class, scope );
	}
	Object.entries( markup.attrs || {} ).forEach( ( [ name, template ] ) => {
		extraProps[ name ] = interpolate( String( template ), scope );
	} );

	const blockProps = useBlockProps( extraProps );

	// A root whose own "when" fails still needs *something* selectable in
	// the canvas (unlike Render\Markup_Renderer, which can just return "",
	// since the front end has no equivalent "keep it clickable" concern) —
	// an empty wrapper, matching "this block currently renders nothing"
	// as closely as an interactive editor reasonably can.
	if ( markup.when && ! evaluateCondition( markup.when, attributes ) ) {
		return createElement( tag, blockProps );
	}

	const options = innerBlocksConfig?.enabled
		? {
				renderSlot: ( key ) =>
					createElement( InnerBlocks, {
						key,
						allowedBlocks: innerBlocksConfig.allowed || undefined,
						template: innerBlocksConfig.template || undefined,
						templateLock:
							innerBlocksConfig.templateLock ?? undefined,
						orientation: innerBlocksConfig.orientation || undefined,
					} ),
		  }
		: {};

	return createElement(
		tag,
		blockProps,
		...renderNodeContent( markup, scope, innerContent, options )
	);
}
