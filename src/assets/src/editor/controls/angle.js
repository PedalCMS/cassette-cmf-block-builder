import { TextControl } from '@wordpress/components';

/**
 * "angle" control — a degree value (0-360), edited as a bounded number
 * input rather than the components package's own `AnglePickerControl`
 * dial widget. A plain number is functionally complete (both produce the
 * same numeric attribute) and keeps this control consistent with
 * "number"'s own TextControl(type="number") choice elsewhere in this
 * catalog, rather than a one-off dependency on a different picker widget
 * for what's structurally the same kind of value.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    Raw field config.
 * @param {number}   props.value    Current value, degrees.
 * @param {Function} props.onChange Called with the new value.
 * @return {JSX.Element} The control.
 */
export default function Angle( { field, value, onChange } ) {
	return (
		<TextControl
			label={ field.label }
			help={ field.description }
			type="number"
			min={ 0 }
			max={ 360 }
			step={ 1 }
			value={ value ?? '' }
			onChange={ ( v ) => onChange( '' === v ? '' : Number( v ) ) }
		/>
	);
}
