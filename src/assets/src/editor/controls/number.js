import { TextControl } from '@wordpress/components';

/**
 * "number" control — a numeric attribute.
 *
 * Deliberately TextControl (type="number"), not the components package's
 * NumberControl: NumberControl still only ships under the experimental
 * `__experimentalNumberControl` name, and the eslint rule against unstable
 * WP APIs exists precisely because unstable-prefixed exports can change
 * without a deprecation path across WordPress versions — a real risk for a
 * library meant to keep working across many host sites' WordPress versions.
 * TextControl spreads unrecognized props straight onto the underlying
 * <input>, so min/max/step reach the native number input unchanged.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    Raw field config (min, max, step).
 * @param {*}        props.value    Current attribute value.
 * @param {Function} props.onChange Called with the new numeric value.
 * @return {JSX.Element} The control.
 */
export default function NumberField( { field, value, onChange } ) {
	return (
		<TextControl
			type="number"
			label={ field.label }
			help={ field.description }
			min={ field.min }
			max={ field.max }
			step={ field.step }
			value={ value ?? '' }
			onChange={ ( newValue ) =>
				onChange( '' === newValue ? undefined : Number( newValue ) )
			}
		/>
	);
}
