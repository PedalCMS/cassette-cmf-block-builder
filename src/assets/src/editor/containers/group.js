/* eslint-disable @wordpress/no-unsafe-wp-apis --
 * ToolsPanel/ToolsPanelItem have no stable equivalent for "a set of controls
 * with a reset-to-default affordance" — WP core's own blocks (e.g.
 * core/group, core/cover) depend on these same experimental exports for the
 * identical reason. Accepted here with the same risk core itself carries:
 * if a future WordPress release renames or removes them, this container's
 * "resettable" mode (not the plain-fieldset default path) breaks until
 * updated. */
import {
	__experimentalToolsPanel as ToolsPanel,
	__experimentalToolsPanelItem as ToolsPanelItem,
} from '@wordpress/components';
/* eslint-enable @wordpress/no-unsafe-wp-apis */
import { renderNode } from '../node-renderer';

/**
 * "group" container -> ToolsPanel when config.resettable is true, else a
 * plain fieldset. The ToolsPanel path is a minimal implementation: every
 * item reports hasValue() => true and a no-op onDeselect, so the reset
 * affordance is present but doesn't yet actually clear a field back to its
 * default — wiring real per-field reset needs each control to expose its
 * own "clear" behaviour, deferred past this milestone.
 *
 * @param {Object} props      Component props.
 * @param {Object} props.node Resolved tree node (config.title, config.resettable, fields).
 * @param {Object} props.ctx  Shared render context.
 * @return {JSX.Element} The container.
 */
export default function Group( { node, ctx } ) {
	const children = node.fields || [];

	if ( node.config.resettable ) {
		return (
			<ToolsPanel label={ node.config.title || '' } resetAll={ () => {} }>
				{ children.map( ( child, index ) => (
					<ToolsPanelItem
						key={ child.config.name || index }
						hasValue={ () => true }
						label={
							child.config.label ||
							child.config.title ||
							child.config.name ||
							''
						}
						onDeselect={ () => {} }
						isShownByDefault
					>
						{ renderNode( child, index, ctx ) }
					</ToolsPanelItem>
				) ) }
			</ToolsPanel>
		);
	}

	return (
		<fieldset className="cassette-cmf-block-builder-group">
			{ node.config.title && <legend>{ node.config.title }</legend> }
			{ children.map( ( child, index ) =>
				renderNode( child, index, ctx )
			) }
		</fieldset>
	);
}
