import { TextControl } from '@wordpress/components';
import { useUniqueHelp } from './unique-help';

/**
 * "email" control — a string attribute, HTML5 email input for basic
 * client-side format hinting. Real validation stays server-side
 * (Compat\Cmf_Bridge -> the parent's "email" field sanitizer).
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    Raw field config.
 * @param {*}        props.value    Current attribute value.
 * @param {Function} props.onChange Called with the new value.
 * @param {Object}   [props.scope]  Shared render context (identifies the block for "unique").
 * @return {JSX.Element} The control.
 */
export default function Email( { field, value, onChange, scope } ) {
	const { help, hasError } = useUniqueHelp( field, scope );

	return (
		<TextControl
			type="email"
			label={ field.label }
			help={ help }
			aria-invalid={ hasError ? 'true' : undefined }
			placeholder={ field.placeholder }
			value={ value ?? '' }
			onChange={ onChange }
		/>
	);
}
