import { BaseControl, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const STYLES = [ 'normal', 'italic' ];
const WEIGHTS = [
	'100',
	'200',
	'300',
	'400',
	'500',
	'600',
	'700',
	'800',
	'900',
];

/**
 * "font_appearance" control — combined font-style + font-weight, stored as
 * a single "<style> <weight>" string (e.g. "italic 700") rather than the
 * block-editor package's own `__experimentalFontAppearanceControl`
 * (which also depends on theme.json font-family "fontFace" data this
 * milestone doesn't build). Two plain SelectControls edit the same value.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    Raw field config.
 * @param {string}   props.value    Current value, e.g. "italic 700".
 * @param {Function} props.onChange Called with the new value.
 * @return {JSX.Element} The control.
 */
export default function FontAppearance( { field, value, onChange } ) {
	const [ style, weight ] = ( value || 'normal 400' ).split( ' ' );

	const emit = ( nextStyle, nextWeight ) =>
		onChange( `${ nextStyle } ${ nextWeight }` );

	return (
		<BaseControl
			label={ field.label }
			help={ field.description }
			id="cassette-cmf-block-builder-font-appearance"
		>
			<div className="cassette-cmf-block-builder-font-appearance__row">
				<SelectControl
					label={ __( 'Style', 'cassette-cmf-block-builder' ) }
					value={ style || 'normal' }
					options={ STYLES.map( ( s ) => ( {
						value: s,
						label: s,
					} ) ) }
					onChange={ ( s ) => emit( s, weight || '400' ) }
				/>
				<SelectControl
					label={ __( 'Weight', 'cassette-cmf-block-builder' ) }
					value={ weight || '400' }
					options={ WEIGHTS.map( ( w ) => ( {
						value: w,
						label: w,
					} ) ) }
					onChange={ ( w ) => emit( style || 'normal', w ) }
				/>
			</div>
		</BaseControl>
	);
}
