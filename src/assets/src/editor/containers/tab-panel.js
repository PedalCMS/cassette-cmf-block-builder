import { TabPanel } from '@wordpress/components';
import { renderNode } from '../node-renderer';
import { slugify } from '../util/slugify';

/**
 * "tab_panel" container -> TabPanel. Field_Collection guarantees every
 * direct child here is a "tab" container (validated at compile time), so
 * this only has to build the { name, title, icon } descriptor TabPanel
 * needs and dispatch the active tab's own node back through renderNode().
 *
 * @param {Object} props      Component props.
 * @param {Object} props.node Resolved tree node whose "fields" are all "tab" nodes.
 * @param {Object} props.ctx  Shared render context.
 * @return {JSX.Element} The container.
 */
export default function TabPanelContainer( { node, ctx } ) {
	const tabNodes = node.fields || [];
	const tabs = tabNodes.map( ( tabNode, index ) => ( {
		name:
			tabNode.config.name ||
			slugify( tabNode.config.title ) ||
			`tab-${ index }`,
		title: tabNode.config.title || `Tab ${ index + 1 }`,
		icon: tabNode.config.icon,
	} ) );

	return (
		<TabPanel tabs={ tabs }>
			{ ( tab ) => {
				const index = tabs.findIndex(
					( candidate ) => candidate.name === tab.name
				);
				const tabNode = tabNodes[ index ];

				return tabNode ? renderNode( tabNode, index, ctx ) : null;
			} }
		</TabPanel>
	);
}
