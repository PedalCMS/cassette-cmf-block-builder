import { TextControl } from '@wordpress/components';

/**
 * "date" control — a string attribute (Y-m-d), plain HTML5 date input. The
 * richer DatePicker (calendar popover) is deferred to the long-tail
 * milestone; this is the honest minimal equivalent for now.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    Raw field config.
 * @param {*}        props.value    Current attribute value.
 * @param {Function} props.onChange Called with the new value.
 * @return {JSX.Element} The control.
 */
export default function DateField( { field, value, onChange } ) {
	return (
		<TextControl
			type="date"
			label={ field.label }
			help={ field.description }
			value={ value ?? '' }
			onChange={ onChange }
		/>
	);
}
