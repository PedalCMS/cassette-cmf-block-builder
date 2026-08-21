import { useEntityProp } from '@wordpress/core-data';

/**
 * Resolve a leaf node's current value and setter, dispatching on its
 * "source" — "attribute" (the default, reading/writing ctx.attributes
 * directly) or "meta" (Mechanism A of meta binding: useEntityProp('postType', ...)
 * against the whole post's "meta" object, the same hook WordPress's own
 * meta-bound controls use, backed by Binding\Meta_Registrar's
 * register_meta() call — see that class's docblock for why a real
 * show_in_rest schema is mandatory for this to work at all).
 *
 * "context"-sourced fields (reading from an ancestor block's
 * providesContext) are not implemented yet — this returns a no-op
 * value/setter for those, same as "none".
 *
 * Always calls useEntityProp() (a real hook) unconditionally, even for a
 * non-meta field — React's rules of hooks require every call to a
 * component to invoke the same hooks in the same order every render, and
 * this is called from FieldValueBridge (node-renderer.js), one component
 * instance per leaf, specifically so this can happen safely. Passing an
 * empty-string postType (see below) makes the underlying useEntityId()
 * resolve nothing, so the call is cheap and inert for a non-meta field.
 *
 * @param {Object} node Resolved leaf tree node: { config: { name, source, meta? }, ... }.
 * @param {Object} ctx  Shared render context: { attributes, setAttributes, context }. "context" is this
 *                      block's own block-context props (only populated when the block declares
 *                      "usesContext" — Core\Block_Definition auto-adds "postId"/"postType" for any
 *                      block with a "meta"-sourced field, specifically so this hook can reach them).
 * @return {[*, Function]} A [ value, setValue ] pair, matching useState()'s own shape.
 */
export function useFieldValue( node, ctx ) {
	const source = node.config.source || 'attribute';
	const postType = 'meta' === source ? ctx.context?.postType : undefined;

	const [ meta, setMeta ] = useEntityProp(
		'postType',
		postType || '',
		'meta'
	);

	if ( 'attribute' === source ) {
		const name = node.config.name;
		return [
			ctx.attributes[ name ],
			( newValue ) => ctx.setAttributes( { [ name ]: newValue } ),
		];
	}

	if ( 'meta' === source ) {
		if ( ! postType ) {
			return [ undefined, noop ];
		}

		const metaKey = node.config.meta?.key || node.config.name;

		return [
			meta?.[ metaKey ],
			( newValue ) => setMeta( { ...meta, [ metaKey ]: newValue } ),
		];
	}

	return [ undefined, noop ];
}

/**
 * A no-op setter for a field this hook can't (yet, or ever) write to.
 */
function noop() {}
