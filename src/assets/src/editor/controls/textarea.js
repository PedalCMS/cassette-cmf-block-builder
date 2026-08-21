import { TextareaControl } from '@wordpress/components';

/**
 * "textarea" control — a multi-line string attribute.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    Raw field config.
 * @param {*}        props.value    Current attribute value.
 * @param {Function} props.onChange Called with the new value.
 * @return {JSX.Element} The control.
 */
export default function Textarea( { field, value, onChange } ) {
	return (
		<TextareaControl
			label={ field.label }
			help={ field.description }
			placeholder={ field.placeholder }
			value={ value ?? '' }
			onChange={ onChange }
		/>
	);
}
