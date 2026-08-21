import BorderFields from '../util/border-fields';

/**
 * "border" control — a single CSS border (width/style/color). See
 * "border_box" for the per-side (top/right/bottom/left) equivalent.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    Raw field config.
 * @param {Object}   props.value    Current value: { width, style, color }.
 * @param {Function} props.onChange Called with the new value.
 * @return {JSX.Element} The control.
 */
export default function Border( { field, value, onChange } ) {
	return (
		<BorderFields
			label={ field.label }
			value={ value }
			onChange={ onChange }
		/>
	);
}
