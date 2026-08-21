import { BaseControl } from '@wordpress/components';
import BorderFields from '../util/border-fields';
import { BOX_SIDES } from '../util/box-sides';

/**
 * "border_box" control — one independent { width, style, color } border
 * per side, reusing util/border-fields.js's trio four times rather than
 * building a second, near-identical width/style/color widget.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    Raw field config.
 * @param {Object}   props.value    Current value: { top, right, bottom, left }, each { width, style, color }.
 * @param {Function} props.onChange Called with the new value.
 * @return {JSX.Element} The control.
 */
export default function BorderBox( { field, value, onChange } ) {
	const sides = value || {};

	return (
		<BaseControl
			label={ field.label }
			help={ field.description }
			id="cassette-cmf-block-builder-border-box"
		>
			{ BOX_SIDES.map( ( side ) => (
				<BorderFields
					key={ side }
					label={ side }
					value={ sides[ side ] }
					onChange={ ( sideValue ) =>
						onChange( { ...sides, [ side ]: sideValue } )
					}
				/>
			) ) }
		</BaseControl>
	);
}
