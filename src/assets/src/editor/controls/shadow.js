import { SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * "shadow" control — a preset box-shadow value, picked from a fixed list
 * (a real shadow-authoring widget with an offset/blur/spread/color editor
 * is a further-milestone enhancement; a preset list already covers the
 * common "give this block a drop shadow" case honestly, without inventing
 * a value shape no consumer render.markup expression has agreed on yet).
 * `field.presets` overrides the built-in list with { value, label } pairs.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    Raw field config.
 * @param {string}   props.value    Current value: a raw CSS box-shadow string, or "" for none.
 * @param {Function} props.onChange Called with the new value.
 * @return {JSX.Element} The control.
 */
export default function Shadow( { field, value, onChange } ) {
	const presets = field.presets || [
		{ value: '', label: __( 'None', 'cassette-cmf-block-builder' ) },
		{
			value: '0 1px 2px rgba(0,0,0,0.2)',
			label: __( 'Small', 'cassette-cmf-block-builder' ),
		},
		{
			value: '0 4px 8px rgba(0,0,0,0.25)',
			label: __( 'Medium', 'cassette-cmf-block-builder' ),
		},
		{
			value: '0 10px 20px rgba(0,0,0,0.3)',
			label: __( 'Large', 'cassette-cmf-block-builder' ),
		},
	];

	return (
		<SelectControl
			label={ field.label }
			help={ field.description }
			value={ value || '' }
			options={ presets }
			onChange={ onChange }
		/>
	);
}
