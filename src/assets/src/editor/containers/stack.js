import { Flex } from '@wordpress/components';
import { renderNode } from '../node-renderer';

/**
 * "stack" container -> a vertical Flex. Deliberately Flex (stable), not the
 * components package's VStack: VStack still only ships under the
 * experimental `__experimentalVStack` name, and Flex direction="column"
 * gives the same vertical layout without that risk.
 *
 * @param {Object} props      Component props.
 * @param {Object} props.node Resolved tree node (config.gap, fields).
 * @param {Object} props.ctx  Shared render context.
 * @return {JSX.Element} The container.
 */
export default function Stack( { node, ctx } ) {
	return (
		<Flex direction="column" gap={ node.config.gap ?? 2 }>
			{ ( node.fields || [] ).map( ( child, index ) =>
				renderNode( child, index, ctx )
			) }
		</Flex>
	);
}
