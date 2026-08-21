import Unit from './unit';

/**
 * "letter_spacing" control — a CSS length value (e.g. "0.05em"), same
 * number+unit shape as the generic "unit" control, restricted to the
 * units letter-spacing actually accepts.
 *
 * @param {Object} props       Component props, forwarded to Unit.
 * @param {Object} props.field Raw field config.
 * @return {JSX.Element} The control.
 */
export default function LetterSpacing( { field, ...rest } ) {
	return (
		<Unit
			field={ { ...field, units: field.units || [ 'px', 'em', 'rem' ] } }
			{ ...rest }
		/>
	);
}
