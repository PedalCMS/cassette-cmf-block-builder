import { BaseControl, TextControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * The four CSS box-model sides, in the order every "box"/"spacing" value
 * object uses: { top, right, bottom, left }.
 */
export const BOX_SIDES = [ 'top', 'right', 'bottom', 'left' ];

/**
 * Shared four-side numeric input grid, backing both the "box" and
 * "spacing" controls (identical shape — margin vs. padding is purely a
 * labeling/semantic distinction the field's own `label`/`name` carries,
 * not a structural one). A "Link sides" toggle writes the same value to
 * all four sides at once, the same affordance WordPress's own core
 * BoxControl offers, without depending on that still-`__experimental`
 * component (see docs/control-catalog.md's note on experimental APIs).
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    Raw field config (label, description).
 * @param {Object}   props.value    Current value: { top, right, bottom, left } (each a CSS length string, e.g. "10px").
 * @param {Function} props.onChange Called with the new { top, right, bottom, left } object.
 * @return {JSX.Element} The control.
 */
export default function BoxSides( { field, value, onChange } ) {
	const sides = value || {};
	const linked =
		sides.top === sides.right &&
		sides.right === sides.bottom &&
		sides.bottom === sides.left;

	const setSide = ( side, sideValue ) => {
		if ( linked ) {
			onChange( {
				top: sideValue,
				right: sideValue,
				bottom: sideValue,
				left: sideValue,
			} );
			return;
		}

		onChange( { ...sides, [ side ]: sideValue } );
	};

	return (
		<BaseControl
			label={ field.label }
			help={ field.description }
			id="cassette-cmf-block-builder-box-sides"
		>
			<ToggleControl
				label={ __( 'Link sides', 'cassette-cmf-block-builder' ) }
				checked={ linked }
				onChange={ ( checked ) => {
					if ( checked ) {
						onChange( {
							top: sides.top || '',
							right: sides.top || '',
							bottom: sides.top || '',
							left: sides.top || '',
						} );
					}
				} }
			/>
			<div className="cassette-cmf-block-builder-box-sides__grid">
				{ BOX_SIDES.map( ( side ) => (
					<TextControl
						key={ side }
						label={ side }
						value={ sides[ side ] || '' }
						onChange={ ( sideValue ) => setSide( side, sideValue ) }
					/>
				) ) }
			</div>
		</BaseControl>
	);
}
