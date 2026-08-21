import BoxSides from '../util/box-sides';

/**
 * "box" control — a generic four-side CSS box-model value (e.g. margin).
 * See "spacing" for the identically-shaped padding-flavored twin; both
 * share util/box-sides.js's grid, since the only real difference between
 * them is which CSS property a consumer's render.markup expression ends
 * up applying the value to.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    Raw field config.
 * @param {Object}   props.value    Current value: { top, right, bottom, left }.
 * @param {Function} props.onChange Called with the new value.
 * @return {JSX.Element} The control.
 */
export default function Box( { field, value, onChange } ) {
	return <BoxSides field={ field } value={ value } onChange={ onChange } />;
}
