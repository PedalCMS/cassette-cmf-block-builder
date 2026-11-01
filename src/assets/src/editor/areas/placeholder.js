import * as blockEditor from '@wordpress/block-editor';
import { Placeholder } from '@wordpress/components';
import { renderNode } from '../node-renderer';

const { useBlockProps } = blockEditor;

/**
 * The editor canvas of an unconfigured block: a core <Placeholder> holding
 * the block's `placeholder`-area fields (label, instructions and icon from
 * the block's `placeholder` setting, falling back to the block's title and
 * icon).
 *
 * @param {Object} props            Component props.
 * @param {Object} props.descriptor This block's Editor_Payload entry.
 * @param {Array}  props.nodes      The placeholder-area nodes.
 * @param {Object} props.ctx        Shared render context.
 * @return {JSX.Element} The placeholder.
 */
export default function PlaceholderArea( { descriptor, nodes, ctx } ) {
	const blockProps = useBlockProps();
	const config = descriptor.placeholder || {};
	const icon =
		config.icon ||
		( 'string' === typeof descriptor.settings.icon
			? descriptor.settings.icon
			: undefined );

	return (
		<div { ...blockProps }>
			<Placeholder
				icon={ icon }
				label={ config.label || descriptor.settings.title }
				instructions={ config.instructions }
				className="cassette-cmf-block-builder-placeholder"
			>
				{ nodes.map( ( node, index ) =>
					renderNode( node, index, ctx )
				) }
			</Placeholder>
		</div>
	);
}
