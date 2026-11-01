import { TextControl } from '@wordpress/components';
import { useUniqueHelp } from './unique-help';

/**
 * "url" control — a string attribute, plain HTML5 URL input. The richer
 * URLInput (link-suggestion autocomplete) is deferred to the "link" control
 * in a later milestone; this is the honest minimal equivalent for now.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    Raw field config.
 * @param {*}        props.value    Current attribute value.
 * @param {Function} props.onChange Called with the new value.
 * @param {Object}   [props.scope]  Shared render context (identifies the block for "unique").
 * @return {JSX.Element} The control.
 */
export default function Url( { field, value, onChange, scope } ) {
	const { help, hasError } = useUniqueHelp( field, scope );

	return (
		<TextControl
			type="url"
			label={ field.label }
			help={ help }
			aria-invalid={ hasError ? 'true' : undefined }
			placeholder={ field.placeholder }
			value={ value ?? '' }
			onChange={ onChange }
		/>
	);
}
