import { ComboboxControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { useEntityOptions } from '../value/useEntityOptions';

/**
 * Shared entity-picker UI: a searchable ComboboxControl backed by
 * value/useEntityOptions.js. "entity_select" (this file's own default
 * export) uses it generically via `field.kind`/`field.name`;
 * post-select.js/term-select.js/user-select.js are thin wrappers fixing
 * those two to a specific entity, since a block author reaching for
 * "post_select" already knows they want posts and shouldn't have to
 * repeat `kind: 'postType', name: 'post'` in every field declaration.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    Raw field config (label, description).
 * @param {string}   props.kind     Entity kind, e.g. "postType".
 * @param {string}   props.name     Entity name, e.g. "post".
 * @param {*}        props.value    Current value: an entity ID.
 * @param {Function} props.onChange Called with the new ID, or null to clear.
 * @return {JSX.Element} The control.
 */
export function EntityPicker( { field, kind, name, value, onChange } ) {
	const [ search, setSearch ] = useState( '' );
	const { options } = useEntityOptions( kind, name, { search } );

	return (
		<ComboboxControl
			label={ field.label }
			help={ field.description }
			value={ value ?? null }
			options={ options }
			onFilterValueChange={ setSearch }
			onChange={ ( newValue ) =>
				onChange( null === newValue ? null : Number( newValue ) )
			}
		/>
	);
}

/**
 * "entity_select" control — a generic entity picker; `field.entity_kind`/
 * `field.entity_name` (e.g. "postType"/"page") pick which entity, for a
 * case post_select/term_select/user_select don't cover directly. Named
 * distinctly from the field's own `name` (its attribute name, a reserved
 * identity key — see docs/block-config-reference.md), which is not
 * available for this purpose.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    Raw field config; `field.entity_kind`/`field.entity_name` are required.
 * @param {*}        props.value    Current value: an entity ID.
 * @param {Function} props.onChange Called with the new ID.
 * @return {JSX.Element} The control.
 */
export default function EntitySelect( { field, value, onChange } ) {
	return (
		<EntityPicker
			field={ field }
			kind={ field.entity_kind || 'postType' }
			name={ field.entity_name || 'post' }
			value={ value }
			onChange={ onChange }
		/>
	);
}
