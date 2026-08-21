import { RadioControl } from '@wordpress/components';

/**
 * "radio" control — one value from field.options, radio-button style.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    Raw field config, including "options".
 * @param {*}        props.value    Current attribute value.
 * @param {Function} props.onChange Called with the new value.
 * @return {JSX.Element} The control.
 */
export default function Radio( { field, value, onChange } ) {
	const options = Object.entries( field.options || {} ).map(
		( [ optionValue, label ] ) => ( { value: optionValue, label } )
	);

	return (
		<RadioControl
			label={ field.label }
			help={ field.description }
			selected={ value ?? '' }
			options={ options }
			onChange={ onChange }
		/>
	);
}
