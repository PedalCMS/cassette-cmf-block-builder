import { PanelBody } from '@wordpress/components';
import { renderNode } from '../node-renderer';

/**
 * "panel"/"metabox" container -> PanelBody.
 *
 * @param {Object} props      Component props.
 * @param {Object} props.node Resolved tree node (config.title, config.initial_open, fields).
 * @param {Object} props.ctx  Shared render context (attributes, setAttributes).
 * @return {JSX.Element} The container.
 */
export default function Panel( { node, ctx } ) {
	return (
		<PanelBody
			title={ node.config.title }
			initialOpen={ false !== node.config.initial_open }
		>
			{ ( node.fields || [] ).map( ( child, index ) =>
				renderNode( child, index, ctx )
			) }
		</PanelBody>
	);
}
