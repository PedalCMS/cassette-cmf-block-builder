/**
 * Which registered block types need instance tracking, and for which fields.
 */

/**
 * Walk a block's resolved field tree and collect its "uuid" and "unique"
 * attribute-sourced fields. A repeater's row template is skipped (row values
 * live inside the repeater's own array attribute, not as block attributes).
 *
 * @param {Array} nodes Resolved field nodes (Field_Collection::to_editor_tree()).
 * @return {{uuid: string[], unique: Array<{name: string, rule: Object, format: (string|undefined)}>}} Tracked fields.
 */
export function collectTrackedFields( nodes ) {
	const tracked = { uuid: [], unique: [] };

	const walk = ( list ) => {
		( list || [] ).forEach( ( node ) => {
			if ( node.isContainer ) {
				if ( 'repeater' !== node.type ) {
					walk( node.fields );
				}
				return;
			}

			const config = node.config || {};
			if (
				! config.name ||
				'attribute' !== ( config.source || 'attribute' )
			) {
				return;
			}

			if ( 'uuid' === node.type ) {
				tracked.uuid.push( config.name );
			}

			if ( config.unique && 'object' === typeof config.unique ) {
				tracked.unique.push( {
					name: config.name,
					rule: config.unique,
					format: config.format || undefined,
				} );
			}
		} );
	};

	walk( nodes );

	return tracked;
}

/**
 * Build the block name -> tracked fields registry from the editor payload,
 * keeping only block types that have something to track.
 *
 * @param {Object} payload Editor_Payload: { blocks: { id: { name, fields } } }.
 * @return {Object} Block name -> { uuid, unique }.
 */
export function buildRegistry( payload ) {
	const registry = {};

	Object.values( payload?.blocks || {} ).forEach( ( descriptor ) => {
		const tracked = collectTrackedFields( descriptor.fields );

		if ( tracked.uuid.length || tracked.unique.length ) {
			registry[ descriptor.name ] = tracked;
		}
	} );

	return registry;
}
