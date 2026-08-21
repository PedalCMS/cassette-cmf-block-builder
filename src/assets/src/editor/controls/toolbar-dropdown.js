import { ToolbarDropdownMenu } from '@wordpress/components';

/**
 * "toolbar_dropdown" control — one value from field.options, shown as an
 * icon-first dropdown menu (compact, for a small set of choices — e.g.
 * alignment-style pickers). See toolbar-menu.js for the free-form,
 * textual-menu sibling.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    Raw field config, including "options".
 * @param {*}        props.value    Current attribute value.
 * @param {Function} props.onChange Called with the new value.
 * @return {JSX.Element} The control.
 */
export default function ToolbarDropdown( { field, value, onChange } ) {
	const controls = Object.entries( field.options || {} ).map(
		( [ optionValue, label ] ) => ( {
			title: label,
			isActive: value === optionValue,
			onClick: () => onChange( optionValue ),
		} )
	);

	return (
		<ToolbarDropdownMenu
			icon={ field.icon }
			label={ field.label }
			controls={ controls }
		/>
	);
}
