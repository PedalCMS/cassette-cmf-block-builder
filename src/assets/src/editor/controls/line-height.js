import { TextControl } from '@wordpress/components';

/**
 * "line_height" control — a plain numeric input for a unitless CSS
 * line-height (e.g. "1.5"), rather than the block-editor package's own
 * `__experimentalLineHeightControl`. Line-height is conventionally
 * unitless, so unlike "unit"/"letter_spacing" this needs no unit picker.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    Raw field config.
 * @param {string}   props.value    Current value, e.g. "1.5".
 * @param {Function} props.onChange Called with the new value.
 * @return {JSX.Element} The control.
 */
export default function LineHeight( { field, value, onChange } ) {
	return (
		<TextControl
			label={ field.label }
			help={ field.description }
			type="number"
			step={ 0.1 }
			min={ 0 }
			value={ value ?? '' }
			onChange={ onChange }
		/>
	);
}
