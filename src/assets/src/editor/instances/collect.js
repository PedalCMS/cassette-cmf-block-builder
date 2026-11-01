/**
 * Flatten the block tree into the entries the reducer works on, in one
 * O(n) pass, keeping only block types the library tracks.
 *
 * Inner blocks are followed through `block.innerBlocks`. A block whose
 * inner blocks are "controlled" (synced patterns, template parts) may
 * report none there, so `getControlledBlocks( clientId )` is consulted for
 * blocks that have no inner blocks listed; it returns the controlled blocks
 * held in the same block-editor store, or a falsy value.
 *
 * @param {Array}         blocks                Root blocks (`getBlocks()`).
 * @param {Object}        registry              Block name -> tracked fields.
 * @param {Function|null} [getControlledBlocks] ( clientId ) => Array|null for controlled inner blocks.
 * @return {Array<{clientId: string, blockName: string, attributes: Object, ancestors: Array<{clientId: string, blockName: string}>}>} Entries in document order.
 */
export function collectInstances( blocks, registry, getControlledBlocks ) {
	const entries = [];

	const visit = ( list, ancestors ) => {
		list.forEach( ( block ) => {
			if ( registry[ block.name ] ) {
				entries.push( {
					clientId: block.clientId,
					blockName: block.name,
					attributes: block.attributes || {},
					ancestors,
				} );
			}

			let children = block.innerBlocks;

			if ( ( ! children || ! children.length ) && getControlledBlocks ) {
				children = getControlledBlocks( block.clientId ) || children;
			}

			if ( children && children.length ) {
				// One shared, immutable ancestor chain per parent: children reuse it.
				visit(
					children,
					ancestors.concat( {
						clientId: block.clientId,
						blockName: block.name,
					} )
				);
			}
		} );
	};

	visit( blocks || [], [] );

	return entries;
}
