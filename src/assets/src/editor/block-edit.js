import { renderInspectorAreas } from './areas/inspector-controls';
import { renderToolbarAreas } from './areas/toolbar-controls';
import CanvasArea from './areas/canvas';
import PlaceholderArea from './areas/placeholder';
import PendingArea from './areas/pending';
import { isPending } from './remote-transform/remote';
import WithVariationPicker from './areas/variation-picker';
import {
	blockScopeVariations,
	collectPlaceholderNodes,
	isUnconfigured,
	splitPlaceholderNodes,
} from './placeholder/logic';
import {
	collectContextFields,
	collectFieldOptions,
} from './util/collect-fields';
import { mergeContextValues } from './util/scope';

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
 * "scopeAttributes" is the attributes plus the values of the block's
 * "source": "context" fields (read from the "context" prop). Conditions and
 * the markup preview evaluate against it, so a context field behaves like a
 * read-only attribute and updates live when the provider's attribute changes.
 * "clientId" rides along on "ctx" so a control can identify its block (the
 * "unique" conflict flag is keyed by it).
 *
 * @param {Object} descriptor One block's Editor_Payload entry: { name, settings, fields, render, preview }.
 * @return {Function} The block's edit() component.
 */
export function createEdit( descriptor ) {
	const fieldOptions = collectFieldOptions( descriptor.fields );
	const contextFields = collectContextFields( descriptor.fields );
	const placeholderNodes = collectPlaceholderNodes( descriptor.fields );
	const hasBlockVariations =
		blockScopeVariations( descriptor.settings?.variations ).length > 0;

	return function BlockEdit( {
		attributes,
		setAttributes,
		context,
		clientId,
	} ) {
		const scopeAttributes = mergeContextValues(
			attributes,
			contextFields,
			context
		);
		const ctx = {
			attributes,
			scopeAttributes,
			setAttributes,
			fieldOptions,
			context,
			clientId,
		};

		// A block just created by a shortcode / raw transform: loading state
		// until the server call returns.
		if ( isPending( attributes ) ) {
			return <PendingArea descriptor={ descriptor } />;
		}

		const unconfigured = isUnconfigured(
			descriptor.placeholder,
			placeholderNodes,
			scopeAttributes
		);
		const { placeholderNodes: shownInPlaceholder, otherNodes } =
			splitPlaceholderNodes( descriptor.fields, unconfigured );

		const content = (
			<>
				{ renderToolbarAreas( otherNodes, ctx ) }
				{ renderInspectorAreas( otherNodes, ctx ) }
				{ unconfigured ? (
					<PlaceholderArea
						descriptor={ descriptor }
						nodes={ shownInPlaceholder }
						ctx={ ctx }
					/>
				) : (
					<CanvasArea
						descriptor={ descriptor }
						attributes={ attributes }
						scopeAttributes={ scopeAttributes }
					/>
				) }
			</>
		);

		if ( ! hasBlockVariations ) {
			return content;
		}

		return (
			<WithVariationPicker
				descriptor={ descriptor }
				clientId={ clientId }
				attributes={ attributes }
				setAttributes={ setAttributes }
			>
				{ content }
			</WithVariationPicker>
		);
	};
}
