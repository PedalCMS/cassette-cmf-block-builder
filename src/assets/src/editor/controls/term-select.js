import { EntityPicker } from './entity-select';

/**
 * "term_select" control — EntityPicker fixed to kind "taxonomy". Default
 * taxonomy "category"; `field.taxonomy` overrides it.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    Raw field config; `field.taxonomy` overrides the default.
 * @param {*}        props.value    Current value: a term ID.
 * @param {Function} props.onChange Called with the new ID.
 * @return {JSX.Element} The control.
 */
export default function TermSelect( { field, value, onChange } ) {
	return (
		<EntityPicker
			field={ field }
			kind="taxonomy"
			name={ field.taxonomy || 'category' }
			value={ value }
			onChange={ onChange }
		/>
	);
}
