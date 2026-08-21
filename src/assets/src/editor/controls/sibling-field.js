import { SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * "sibling_field" control — pick another attribute-sourced field's name
 * from the same block. Options come from scope.fieldOptions
 * (block-edit.js collects this once per block, from the same resolved
 * tree the rest of the editor renders from) — the field itself is
 * excluded from its own options list, since a field can't sensibly
 * reference itself.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    Raw field config.
 * @param {*}        props.value    Current attribute value (another field's name).
 * @param {Function} props.onChange Called with the new value.
 * @param {Object}   props.scope    Shared render context; scope.fieldOptions is this block's field picker options.
 * @return {JSX.Element} The control.
 */
export default function SiblingField( { field, value, onChange, scope } ) {
	const options = ( scope?.fieldOptions || [] ).filter(
		( option ) => option.value !== field.name
	);

	return (
		<SelectControl
			label={ field.label }
			help={ field.description }
			value={ value ?? '' }
			options={ [
				{
					value: '',
					label: __(
						'— Select a field —',
						'cassette-cmf-block-builder'
					),
				},
				...options,
			] }
			onChange={ onChange }
		/>
	);
}
