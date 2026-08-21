import { TextControl } from '@wordpress/components';

/**
 * "email" control — a string attribute, HTML5 email input for basic
 * client-side format hinting. Real validation stays server-side
 * (Compat\Cmf_Bridge -> the parent's "email" field sanitizer).
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    Raw field config.
 * @param {*}        props.value    Current attribute value.
 * @param {Function} props.onChange Called with the new value.
 * @return {JSX.Element} The control.
 */
export default function Email( { field, value, onChange } ) {
	return (
		<TextControl
			type="email"
			label={ field.label }
			help={ field.description }
			placeholder={ field.placeholder }
			value={ value ?? '' }
			onChange={ onChange }
		/>
	);
}
