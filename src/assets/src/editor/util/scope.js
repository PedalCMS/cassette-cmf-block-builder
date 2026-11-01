/**
 * Merge a block's context-sourced field values into its attributes, under
 * each field's name: the one value scope the markup preview, conditions and
 * (server side) the templates all read from.
 *
 * @param {Object} attributes    Live block attributes.
 * @param {Object} contextFields Field name -> block-context key (collectContextFields()).
 * @param {Object} [context]     The block's context prop.
 * @return {Object} `attributes` itself when there are no context fields, else a merged copy.
 */
export function mergeContextValues( attributes, contextFields, context ) {
	const names = Object.keys( contextFields );

	if ( 0 === names.length ) {
		return attributes;
	}

	const merged = { ...attributes };

	names.forEach( ( name ) => {
		merged[ name ] = context?.[ contextFields[ name ] ];
	} );

	return merged;
}
