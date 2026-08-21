import { ToolbarGroup } from '@wordpress/components';
import { renderNode } from '../node-renderer';

/**
 * "toolbar_group" container -> ToolbarGroup.
 *
 * @param {Object} props      Component props.
 * @param {Object} props.node Resolved tree node.
 * @param {Object} props.ctx  Shared render context.
 * @return {JSX.Element} The container.
 */
export default function ToolbarGroupContainer( { node, ctx } ) {
	return (
		<ToolbarGroup>
			{ ( node.fields || [] ).map( ( child, index ) =>
				renderNode( child, index, ctx )
			) }
		</ToolbarGroup>
	);
}
