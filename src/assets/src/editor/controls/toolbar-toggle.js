import { ToolbarButton } from '@wordpress/components';

/**
 * "toolbar_toggle" control — a boolean attribute rendered as a pressable
 * toolbar button.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    Raw field config (icon, label).
 * @param {*}        props.value    Current attribute value.
 * @param {Function} props.onChange Called with the new boolean value.
 * @return {JSX.Element} The control.
 */
export default function ToolbarToggle( { field, value, onChange } ) {
	return (
		<ToolbarButton
			icon={ field.icon }
			label={ field.label }
			text={ field.icon ? undefined : field.label }
			isPressed={ !! value }
			onClick={ () => onChange( ! value ) }
		/>
	);
}
