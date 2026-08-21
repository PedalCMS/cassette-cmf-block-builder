import { CheckboxControl } from '@wordpress/components';

/**
 * "checkbox_group" control — an array attribute, one CheckboxControl per
 * field.options entry. Deliberately hand-rolled rather than wrapped in
 * BaseControl, to avoid coupling to a specific @wordpress/components
 * version's BaseControl prop surface for a purely cosmetic wrapper.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    Raw field config, including "options".
 * @param {*}        props.value    Current attribute value (array).
 * @param {Function} props.onChange Called with the new array value.
 * @return {JSX.Element} The control.
 */
export default function CheckboxGroup( { field, value, onChange } ) {
	const selected = Array.isArray( value ) ? value : [];
	const options = Object.entries( field.options || {} );

	const toggleOption = ( optionValue, checked ) => {
		onChange(
			checked
				? [ ...selected, optionValue ]
				: selected.filter( ( existing ) => existing !== optionValue )
		);
	};

	return (
		<div className="cassette-cmf-block-builder-checkbox-group">
			{ field.label && (
				<p className="cassette-cmf-block-builder-checkbox-group__label">
					{ field.label }
				</p>
			) }
			{ options.map( ( [ optionValue, label ] ) => (
				<CheckboxControl
					key={ optionValue }
					label={ label }
					checked={ selected.includes( optionValue ) }
					onChange={ ( checked ) =>
						toggleOption( optionValue, checked )
					}
				/>
			) ) }
			{ field.description && (
				<p className="components-base-control__help">
					{ field.description }
				</p>
			) }
		</div>
	);
}
