import { Fragment } from '@wordpress/element';
import { renderNode } from '../node-renderer';

/**
 * "tab" container — one tab_panel's tab content. Renders bare (no chrome of
 * its own); tab-panel.js supplies the TabPanel shell and tab-switching UI,
 * this just dispatches the tab's own children the same way any other
 * container does.
 *
 * @param {Object} props      Component props.
 * @param {Object} props.node Resolved tree node.
 * @param {Object} props.ctx  Shared render context.
 * @return {JSX.Element} The container.
 */
export default function Tab( { node, ctx } ) {
	return (
		<Fragment>
			{ ( node.fields || [] ).map( ( child, index ) =>
				renderNode( child, index, ctx )
			) }
		</Fragment>
	);
}
