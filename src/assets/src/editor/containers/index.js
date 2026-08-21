import Panel from './panel';
import TabPanelContainer from './tab-panel';
import Tab from './tab';
import Group from './group';
import Row from './row';
import Stack from './stack';
import ToolbarGroupContainer from './toolbar-group';

/**
 * Container control type -> component. "repeater" is deliberately absent
 * from this map — it's a container that also holds a value (an array of
 * rows) and needs its own row-add/remove/reorder UI wired to
 * useFieldValue(), so node-renderer.js's renderNode() intercepts it before
 * ever consulting this registry; see node-renderer.js's RepeaterField.
 */
export const CONTAINER_COMPONENTS = {
	panel: Panel,
	metabox: Panel,
	tab_panel: TabPanelContainer,
	tab: Tab,
	group: Group,
	row: Row,
	stack: Stack,
	toolbar_group: ToolbarGroupContainer,
};
