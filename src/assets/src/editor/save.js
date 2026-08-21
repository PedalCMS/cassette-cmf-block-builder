import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';
import { createElement } from '@wordpress/element';
import { interpolate } from './markup/expr';
import { renderNodeContent } from './markup/render';
import { evaluate as evaluateCondition } from './conditions/evaluate';

/**
 * Build a save() function that serializes a markup tree — the third
 * runtime "one declarative markup tree drives three runtimes" refers to
 * (the other two: Render\Markup_Renderer for the front end, markup/render.js
 * via MarkupPreview for the editor canvas). Used both for a static block's
 * current save() and for each of its render.deprecated entries' own save()
 * (see deprecations.js) — a deprecated entry's save() only exists to
 * validate/upgrade previously-saved content, using the markup tree that was
 * current when that content was saved.
 *
 * Mirrors preview/MarkupPreview.js's root-node handling (its own class/attrs
 * merged through a wrapper-props call rather than this module's normal
 * per-node attrs), with two differences specific to save() context:
 *   - useBlockProps.save() instead of the useBlockProps() hook — save() is a
 *     plain function called during serialization, not a rendered component,
 *     so there's no hook to call.
 *   - a "slot": "inner_blocks"/"content" node emits <InnerBlocks.Content />
 *     instead of RawHTML-wrapping a content string: save() has no rendered
 *     inner content to inject a string of — WP itself expands
 *     <InnerBlocks.Content /> when serializing the block.
 *
 * @param {Object|null} markup The markup tree to serialize, or null/undefined
 *                             for a block with nothing to save (returns a
 *                             save() that always returns null).
 * @return {Function} A save() component.
 */
export function createSave( markup ) {
	if ( ! markup ) {
		return function Save() {
			return null;
		};
	}

	return function Save( { attributes } ) {
		const scope = { attributes };
		const tag = markup.tag || 'div';

		const extraProps = {};
		if ( markup.class ) {
			extraProps.className = interpolate( markup.class, scope );
		}
		Object.entries( markup.attrs || {} ).forEach(
			( [ name, template ] ) => {
				extraProps[ name ] = interpolate( String( template ), scope );
			}
		);

		const blockProps = useBlockProps.save( extraProps );

		// Matches MarkupPreview.js's root "when" handling — see its
		// docblock for why an empty wrapper, not a bare null, is the
		// right "renders nothing" shape here too.
		if ( markup.when && ! evaluateCondition( markup.when, attributes ) ) {
			return createElement( tag, blockProps );
		}

		const options = {
			renderSlot: ( key ) =>
				createElement( InnerBlocks.Content, { key } ),
		};

		return createElement(
			tag,
			blockProps,
			...renderNodeContent( markup, scope, '', options )
		);
	};
}
