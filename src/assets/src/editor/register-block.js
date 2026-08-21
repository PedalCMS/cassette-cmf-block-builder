import { createElement } from '@wordpress/element';
import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks } from '@wordpress/block-editor';
import { createEdit } from './block-edit';
import { createSave } from './save';
import { buildDeprecations } from './deprecations';
import { buildTransforms } from './transforms';

/**
 * Register every block described in the Editor_Payload payload.
 *
 * "settings" is deliberately the exact same shape (title, category,
 * attributes, supports, ...) register_block_type() received server-side —
 * Block_Definition::get_args() ships still in block.json's own camelCase
 * for this reason, so there's one source of truth for a block's schema and
 * no server/client attribute drift.
 *
 * save() renders the block's markup tree for "render.mode": "static"
 * (Core\Block_Definition never wires a render_callback for a static block,
 * so its saved content IS the front-end output — that's the whole point of
 * static mode). A **dynamic** block's own save() output is likewise
 * discarded at render time by render_callback — but its *child* blocks are
 * not: WordPress derives $content (what render_callback receives) from
 * whatever save() actually serialized into post_content, so a dynamic block
 * with "inner_blocks" enabled must still emit <InnerBlocks.Content /> from
 * save(), or every child block a user adds is silently dropped from
 * post_content the moment the post is saved — not merely hidden from the
 * front end, genuinely never persisted. Only a block with InnerBlocks
 * disabled entirely (dynamic, "none", or an inner_blocks-less block) can
 * safely return null (with supports.html defaulted to false — see
 * Schema\Supports_Normalizer) — there is nothing of its own to persist.
 *
 * @param {Object} payload Editor_Payload::build()'s payload: { version, blocks }.
 */
export function registerBlocks( payload ) {
	Object.values( payload.blocks || {} ).forEach( ( descriptor ) => {
		const render = descriptor.render || {};
		const isStatic = 'static' === render.mode;
		const innerBlocksEnabled = Boolean( descriptor.innerBlocks?.enabled );

		let save;
		if ( isStatic ) {
			save = createSave( render.markup );
		} else if ( innerBlocksEnabled ) {
			save = function Save() {
				return createElement( InnerBlocks.Content );
			};
		} else {
			save = () => null;
		}

		registerBlockType( descriptor.name, {
			...descriptor.settings,
			edit: createEdit( descriptor ),
			save,
			deprecated: buildDeprecations( descriptor ),
			transforms: buildTransforms( descriptor ),
		} );
	} );
}
