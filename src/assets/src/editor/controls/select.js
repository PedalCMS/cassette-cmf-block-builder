import { SelectControl } from '@wordpress/components';

/**
 * "select" control — one value from field.options (value => label).
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    Raw field config, including "options".
 * @param {*}        props.value    Current attribute value.
 * @param {Function} props.onChange Called with the new value.
 * @return {JSX.Element} The control.
 */
export default function Select( { field, value, onChange } ) {
	const options = Object.entries( field.options || {} ).map(
		( [ optionValue, label ] ) => ( { value: optionValue, label } )
	);

	return (
		<SelectControl
			label={ field.label }
			help={ field.description }
			value={ value ?? '' }
			options={ options }
			onChange={ onChange }
		/>
	);
}
