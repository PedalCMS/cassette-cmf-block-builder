import BoxSides from '../util/box-sides';

/**
 * "spacing" control — a generic four-side CSS box-model value (e.g.
 * padding). See "box" for the identically-shaped margin-flavored twin;
 * see util/box-sides.js's docblock for why the two share one grid
 * component.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    Raw field config.
 * @param {Object}   props.value    Current value: { top, right, bottom, left }.
 * @param {Function} props.onChange Called with the new value.
 * @return {JSX.Element} The control.
 */
export default function Spacing( { field, value, onChange } ) {
	return <BoxSides field={ field } value={ value } onChange={ onChange } />;
}
