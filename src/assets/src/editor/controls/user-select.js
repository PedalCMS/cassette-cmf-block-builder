import { EntityPicker } from './entity-select';

/**
 * "user_select" control — EntityPicker fixed to kind "root", name "user".
 * A user record's display label comes from its own "name" property
 * (useEntityOptions' default label resolver already falls back to
 * "record.name" when "record.title" isn't present, which is exactly a
 * user record's shape).
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    Raw field config.
 * @param {*}        props.value    Current value: a user ID.
 * @param {Function} props.onChange Called with the new ID.
 * @return {JSX.Element} The control.
 */
export default function UserSelect( { field, value, onChange } ) {
	return (
		<EntityPicker
			field={ field }
			kind="root"
			name="user"
			value={ value }
			onChange={ onChange }
		/>
	);
}
