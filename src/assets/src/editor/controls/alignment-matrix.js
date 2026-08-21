import { SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const POSITIONS = [
	[ 'top left', __( 'Top left', 'cassette-cmf-block-builder' ) ],
	[ 'top center', __( 'Top center', 'cassette-cmf-block-builder' ) ],
	[ 'top right', __( 'Top right', 'cassette-cmf-block-builder' ) ],
	[ 'center left', __( 'Center left', 'cassette-cmf-block-builder' ) ],
	[ 'center center', __( 'Center', 'cassette-cmf-block-builder' ) ],
	[ 'center right', __( 'Center right', 'cassette-cmf-block-builder' ) ],
	[ 'bottom left', __( 'Bottom left', 'cassette-cmf-block-builder' ) ],
	[ 'bottom center', __( 'Bottom center', 'cassette-cmf-block-builder' ) ],
	[ 'bottom right', __( 'Bottom right', 'cassette-cmf-block-builder' ) ],
];

/**
 * "alignment_matrix" control — one of the nine standard 3x3 alignment
 * positions, edited as a SelectControl rather than the components
 * package's own `AlignmentMatrixControl` interactive grid. Same value
 * shape (a "<vertical> <horizontal>" string), so a consumer's
 * render.markup expression reads it identically either way — only the
 * editing widget differs.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    Raw field config.
 * @param {string}   props.value    Current value, e.g. "center center".
 * @param {Function} props.onChange Called with the new value.
 * @return {JSX.Element} The control.
 */
export default function AlignmentMatrix( { field, value, onChange } ) {
	return (
		<SelectControl
			label={ field.label }
			help={ field.description }
			value={ value || 'center center' }
			options={ POSITIONS.map( ( [ v, label ] ) => ( {
				value: v,
				label,
			} ) ) }
			onChange={ onChange }
		/>
	);
}
