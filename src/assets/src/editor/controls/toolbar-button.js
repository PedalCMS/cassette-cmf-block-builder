import { ToolbarButton } from '@wordpress/components';

/**
 * "toolbar_button" control — display-only chrome, no attribute (see
 * Control_Catalog: holds_value is false for this type). It renders as a
 * real toolbar button so the declared UI is visible and testable, but has
 * no wired action yet: a click currently does nothing. Giving it a
 * meaningful action (e.g. opening a popover of further declared controls)
 * needs infrastructure this milestone doesn't build (Placeholder/Popover
 * areas land later). Logged once under SCRIPT_DEBUG so this limitation
 * isn't silently mistaken for a bug.
 *
 * @param {Object} props       Component props.
 * @param {Object} props.field Raw field config (icon, label).
 * @return {JSX.Element} The control.
 */
export default function ToolbarButtonControl( { field } ) {
	return (
		<ToolbarButton
			icon={ field.icon }
			label={ field.label }
			text={ field.icon ? undefined : field.label }
			onClick={ () => {
				if ( process.env.NODE_ENV !== 'production' ) {
					// eslint-disable-next-line no-console
					console.warn(
						`[cassette-cmf-block-builder] toolbar_button "${ field.name }" has no configured action yet.`
					);
				}
			} }
		/>
	);
}
