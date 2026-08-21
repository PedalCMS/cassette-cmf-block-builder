import { DropdownMenu } from '@wordpress/components';

/**
 * "toolbar_menu" control — one value from field.options, shown as a
 * free-form textual dropdown menu (not necessarily nested in a
 * toolbar_group). See toolbar-dropdown.js for the compact,
 * icon-first sibling meant for small option sets inside a toolbar.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    Raw field config, including "options".
 * @param {*}        props.value    Current attribute value.
 * @param {Function} props.onChange Called with the new value.
 * @return {JSX.Element} The control.
 */
export default function ToolbarMenu( { field, value, onChange } ) {
	const controls = Object.entries( field.options || {} ).map(
		( [ optionValue, label ] ) => ( {
			title: label,
			isActive: value === optionValue,
			onClick: () => onChange( optionValue ),
		} )
	);

	return (
		<DropdownMenu
			icon={ field.icon }
			label={ field.label }
			text={ field.icon ? undefined : field.label }
			controls={ controls }
		/>
	);
}
