import { BlockControls } from '@wordpress/block-editor';
import { renderNode } from '../node-renderer';
import { groupNodesBySurface } from './group-nodes';
import { TOOLBAR_GROUPS } from './groups';

/**
 * Render every top-level "toolbar"/"toolbar.<group>" node, one
 * <BlockControls group="..."> per distinct group actually used.
 *
 * @param {Array}  nodes Top-level resolved tree nodes for this block.
 * @param {Object} ctx   Shared render context: { attributes, setAttributes }.
 * @return {JSX.Element[]} One <BlockControls> element per group in use.
 */
export function renderToolbarAreas( nodes, ctx ) {
	const groups = groupNodesBySurface( nodes, 'toolbar', TOOLBAR_GROUPS );

	return Array.from( groups.entries() ).map( ( [ group, groupNodes ] ) => (
		<BlockControls key={ group || 'default' } group={ group }>
			{ groupNodes.map( ( node, index ) =>
				renderNode( node, index, ctx )
			) }
		</BlockControls>
	) );
}
