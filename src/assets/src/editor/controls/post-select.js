import { EntityPicker } from './entity-select';

/**
 * "post_select" control — EntityPicker fixed to kind "postType". Named
 * post_type default "post"; `field.post_type` overrides it (e.g. "page").
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    Raw field config; `field.post_type` overrides the default.
 * @param {*}        props.value    Current value: a post ID.
 * @param {Function} props.onChange Called with the new ID.
 * @return {JSX.Element} The control.
 */
export default function PostSelect( { field, value, onChange } ) {
	return (
		<EntityPicker
			field={ field }
			kind="postType"
			name={ field.post_type || 'post' }
			value={ value }
			onChange={ onChange }
		/>
	);
}
