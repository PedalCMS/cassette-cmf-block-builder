import { InspectorControls } from '@wordpress/block-editor';
import { renderNode } from '../node-renderer';
import { groupNodesBySurface } from './group-nodes';
import { INSPECTOR_GROUPS } from './groups';

/**
 * Render every top-level "inspector"/"inspector.<group>" node, one
 * <InspectorControls group="..."> per distinct group actually used.
 *
 * @param {Array}  nodes Top-level resolved tree nodes for this block.
 * @param {Object} ctx   Shared render context: { attributes, setAttributes }.
 * @return {JSX.Element[]} One <InspectorControls> element per group in use.
 */
export function renderInspectorAreas( nodes, ctx ) {
	const groups = groupNodesBySurface( nodes, 'inspector', INSPECTOR_GROUPS );

	return Array.from( groups.entries() ).map( ( [ group, groupNodes ] ) => (
		<InspectorControls key={ group || 'default' } group={ group }>
			{ groupNodes.map( ( node, index ) =>
				renderNode( node, index, ctx )
			) }
		</InspectorControls>
	) );
}
