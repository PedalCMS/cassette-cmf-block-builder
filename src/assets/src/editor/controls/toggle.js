import { ToggleControl } from '@wordpress/components';

/**
 * "toggle" control — a boolean attribute.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    Raw field config.
 * @param {*}        props.value    Current attribute value.
 * @param {Function} props.onChange Called with the new value.
 * @return {JSX.Element} The control.
 */
export default function Toggle( { field, value, onChange } ) {
	return (
		<ToggleControl
			label={ field.label }
			help={ field.description }
			checked={ !! value }
			onChange={ onChange }
		/>
	);
}
