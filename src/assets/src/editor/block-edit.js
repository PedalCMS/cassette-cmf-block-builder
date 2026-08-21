import { renderInspectorAreas } from './areas/inspector-controls';
import { renderToolbarAreas } from './areas/toolbar-controls';
import CanvasArea from './areas/canvas';
import { collectFieldOptions } from './util/collect-fields';

/**
 * Build the edit() function for one block descriptor.
 *
 * A closure over "descriptor" rather than a component that receives it as a
 * prop: registerBlockType()'s "edit" slot only ever receives WordPress's own
 * standard edit props (attributes, setAttributes, clientId, ...), so each
 * block gets its own edit() built at registration time in register-block.js.
 *
 * "fieldOptions" (this block's own attribute-sourced field names/labels,
 * flattened once per block rather than re-walked on every render) rides
 * along on "ctx" for the "sibling_field" and "conditions" controls' field
 * pickers — the one thing every control receives is { field, value,
 * onChange, scope }, and "scope" is this ctx object.
 *
 * "context" (WordPress's own block-context prop, only populated for
 * whatever this block declares "usesContext" for) rides along too —
 * value/useFieldValue.js's "meta" source reads ctx.context.postType from
 * it. Core\Block_Definition auto-adds "postId"/"postType" to a block's own
 * "usesContext" whenever it has a "meta"-sourced field, specifically so
 * this is populated when needed.
 *
 * @param {Object} descriptor One block's Editor_Payload entry: { name, settings, fields, render, preview }.
 * @return {Function} The block's edit() component.
 */
export function createEdit( descriptor ) {
	const fieldOptions = collectFieldOptions( descriptor.fields );

	return function BlockEdit( { attributes, setAttributes, context } ) {
		const ctx = { attributes, setAttributes, fieldOptions, context };

		return (
			<>
				{ renderToolbarAreas( descriptor.fields, ctx ) }
				{ renderInspectorAreas( descriptor.fields, ctx ) }
				<CanvasArea
					descriptor={ descriptor }
					attributes={ attributes }
				/>
			</>
		);
	};
}
