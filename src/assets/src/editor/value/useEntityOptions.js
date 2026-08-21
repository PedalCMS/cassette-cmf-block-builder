import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';

/**
 * Resolve a live, searchable { value, label } option list for an
 * `@wordpress/core-data`-backed entity kind — the shared lookup behind
 * "entity_select"/"post_select"/"term_select"/"user_select". Always calls
 * useSelect() unconditionally (rules-of-hooks compliant — every control
 * using this hook is its own component instance, same reasoning as
 * value/useFieldValue.js's unconditional useEntityProp() call).
 *
 * @param {string}   kind            Entity kind, e.g. "postType", "taxonomy", "root".
 * @param {string}   name            Entity name, e.g. "post", "category", "user".
 * @param {Object}   [args]          Options.
 * @param {string}   [args.search]   Search string, forwarded to getEntityRecords()'s own "search" query arg.
 * @param {number}   [args.perPage]  Max records to fetch. Default 20.
 * @param {Function} [args.getLabel] Maps one record to its display label. Default: record.title?.rendered ?? record.name ?? `#${record.id}`.
 * @return {{options: Array<{value: number, label: string}>, isResolving: boolean}} The current options and whether the query is still in flight.
 */
export function useEntityOptions(
	kind,
	name,
	{ search = '', perPage = 20, getLabel } = {}
) {
	const { records, isResolving } = useSelect(
		( select ) => {
			const query = { per_page: perPage, orderby: 'title', order: 'asc' };
			if ( search ) {
				query.search = search;
			}

			const coreSelect = select( coreStore );

			return {
				records: coreSelect.getEntityRecords( kind, name, query ) || [],
				isResolving: ! coreSelect.hasFinishedResolution(
					'getEntityRecords',
					[ kind, name, query ]
				),
			};
		},
		[ kind, name, search, perPage ]
	);

	const label =
		getLabel ||
		( ( record ) =>
			record.title?.rendered ?? record.name ?? `#${ record.id }` );

	return {
		options: records.map( ( record ) => ( {
			value: record.id,
			label: label( record ),
		} ) ),
		isResolving,
	};
}
