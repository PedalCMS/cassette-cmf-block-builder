import { BaseControl, SelectControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * The width/style/color trio a single CSS border is made of — shared by
 * the "border" control (one border) and the "border_box" control (one of
 * these per side). A plain SelectControl for "style" and TextControl for
 * "width"/"color" rather than a color-swatch picker: the "color" control
 * already covers rich color picking for anyone who needs it on its own
 * field, and duplicating that UI here would be a second, separate
 * maintenance surface for a value that's still just a CSS color string.
 *
 * @param {Object}   props          Component props.
 * @param {string}   [props.label]  Optional label above the trio (omitted for a single "border" control with its own field.label already shown).
 * @param {Object}   props.value    Current value: { width, style, color }.
 * @param {Function} props.onChange Called with the new { width, style, color } object.
 * @return {JSX.Element} The control.
 */
export default function BorderFields( { label, value, onChange } ) {
	const border = value || {};

	const setField = ( key, fieldValue ) =>
		onChange( { ...border, [ key ]: fieldValue } );

	return (
		<BaseControl
			label={ label }
			id="cassette-cmf-block-builder-border-fields"
		>
			<div className="cassette-cmf-block-builder-border-fields__grid">
				<TextControl
					label={ __( 'Width', 'cassette-cmf-block-builder' ) }
					value={ border.width || '' }
					onChange={ ( v ) => setField( 'width', v ) }
				/>
				<SelectControl
					label={ __( 'Style', 'cassette-cmf-block-builder' ) }
					value={ border.style || 'solid' }
					options={ [
						{
							value: 'none',
							label: __( 'None', 'cassette-cmf-block-builder' ),
						},
						{
							value: 'solid',
							label: __( 'Solid', 'cassette-cmf-block-builder' ),
						},
						{
							value: 'dashed',
							label: __( 'Dashed', 'cassette-cmf-block-builder' ),
						},
						{
							value: 'dotted',
							label: __( 'Dotted', 'cassette-cmf-block-builder' ),
						},
					] }
					onChange={ ( v ) => setField( 'style', v ) }
				/>
				<TextControl
					label={ __( 'Color', 'cassette-cmf-block-builder' ) }
					value={ border.color || '' }
					onChange={ ( v ) => setField( 'color', v ) }
				/>
			</div>
		</BaseControl>
	);
}
