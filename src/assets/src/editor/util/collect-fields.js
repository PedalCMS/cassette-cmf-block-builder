/**
 * Recursively collect { value, label } options for every attribute-sourced,
 * value-bearing leaf in a block's resolved field tree (Field_Collection::to_editor_tree()) —
 * the block's own flat attribute namespace, container nesting flattened
 * away. Used to populate a "sibling_field" control's picker and a
 * "conditions" control's rule-builder field picker: both need to offer
 * "which other field in this block", not walk the tree themselves.
 *
 * A "repeater" node's own "fields" is a row TEMPLATE, not part of the
 * block's flat attribute namespace (see
 * Schema\Attribute_Schema_Mapper::from_fields()'s "inside_repeater" guard,
 * the server-side equivalent of this same rule) — a repeater row's field
 * names aren't real top-level fields a "conditional"/"conditions" rule
 * could ever reference, so recursion stops at a repeater rather than
 * listing its row fields as if they were.
 *
 * @param {Array} nodes Resolved tree nodes (a block's "fields", or any node's own "fields").
 * @return {Array<{value: string, label: string}>} Field options, in declaration order.
 */
export function collectFieldOptions( nodes ) {
	const options = [];

	const walk = ( list ) => {
		( list || [] ).forEach( ( node ) => {
			if ( node.isContainer ) {
				if ( 'repeater' !== node.type ) {
					walk( node.fields );
				}
				return;
			}

			if ( ! node.holdsValue ) {
				return;
			}

			const source = node.config.source || 'attribute';
			if ( 'attribute' !== source || ! node.config.name ) {
				return;
			}

			options.push( {
				value: node.config.name,
				label: node.config.label || node.config.name,
			} );
		} );
	};

	walk( nodes );

	return options;
}
