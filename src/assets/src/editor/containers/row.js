import { Flex, FlexItem } from '@wordpress/components';
import { renderNode } from '../node-renderer';

/**
 * "row" container -> Flex, one FlexItem per child.
 *
 * @param {Object} props      Component props.
 * @param {Object} props.node Resolved tree node (config.gap, config.align, config.justify, fields).
 * @param {Object} props.ctx  Shared render context.
 * @return {JSX.Element} The container.
 */
export default function Row( { node, ctx } ) {
	return (
		<Flex
			gap={ node.config.gap ?? 2 }
			align={ node.config.align }
			justify={ node.config.justify }
		>
			{ ( node.fields || [] ).map( ( child, index ) => (
				<FlexItem key={ child.config.name || index }>
					{ renderNode( child, index, ctx ) }
				</FlexItem>
			) ) }
		</Flex>
	);
}
