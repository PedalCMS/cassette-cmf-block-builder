import { ToolbarButton } from '@wordpress/components';

/**
 * "toolbar_link" control — display-only chrome, no attribute (see
 * Control_Catalog: holds_value is false for this type), same honest gap
 * as "toolbar_button": renders a real toolbar button so the declared UI
 * is visible, but wiring it to actually open a LinkControl popover (the
 * "link" control's own approach, which needs a value to edit) needs a
 * declared target field this control's own config doesn't carry.
 *
 * @param {Object} props       Component props.
 * @param {Object} props.field Raw field config (icon, label).
 * @return {JSX.Element} The control.
 */
export default function ToolbarLink( { field } ) {
	return (
		<ToolbarButton
			icon={ field.icon || 'admin-links' }
			label={ field.label }
			text={ field.icon ? undefined : field.label }
			onClick={ () => {
				if ( process.env.NODE_ENV !== 'production' ) {
					// eslint-disable-next-line no-console
					console.warn(
						`[cassette-cmf-block-builder] toolbar_link "${ field.name }" has no configured action yet.`
					);
				}
			} }
		/>
	);
}
