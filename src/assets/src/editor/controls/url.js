import { TextControl } from '@wordpress/components';

/**
 * "url" control — a string attribute, plain HTML5 URL input. The richer
 * URLInput (link-suggestion autocomplete) is deferred to the "link" control
 * in a later milestone; this is the honest minimal equivalent for now.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    Raw field config.
 * @param {*}        props.value    Current attribute value.
 * @param {Function} props.onChange Called with the new value.
 * @return {JSX.Element} The control.
 */
export default function Url( { field, value, onChange } ) {
	return (
		<TextControl
			type="url"
			label={ field.label }
			help={ field.description }
			placeholder={ field.placeholder }
			value={ value ?? '' }
			onChange={ onChange }
		/>
	);
}
