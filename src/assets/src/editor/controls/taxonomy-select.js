import { SelectControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';

/**
 * "taxonomy_select" control — picks a TAXONOMY itself (a slug, e.g.
 * "category"), not a term within one — the distinction from "term_select"
 * (which picks a term ID within a fixed taxonomy). Reads the site's own
 * registered taxonomies via getTaxonomies() directly rather than
 * value/useEntityOptions.js, since a taxonomy isn't an
 * getEntityRecords()-shaped entity the way posts/terms/users are.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    Raw field config.
 * @param {string}   props.value    Current value: a taxonomy slug.
 * @param {Function} props.onChange Called with the new value.
 * @return {JSX.Element} The control.
 */
export default function TaxonomySelect( { field, value, onChange } ) {
	const taxonomies = useSelect(
		( select ) =>
			select( coreStore ).getTaxonomies( { per_page: -1 } ) || [],
		[]
	);

	return (
		<SelectControl
			label={ field.label }
			help={ field.description }
			value={ value || '' }
			options={ [
				{ value: '', label: '—' },
				...taxonomies.map( ( taxonomy ) => ( {
					value: taxonomy.slug,
					label: taxonomy.name,
				} ) ),
			] }
			onChange={ onChange }
		/>
	);
}
