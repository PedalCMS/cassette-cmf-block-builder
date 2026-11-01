import { __ } from '@wordpress/i18n';
import { useUniqueConflict } from '../unique/conflicts';

/**
 * Shared "unique" wiring for text-like controls: whether the field is
 * flagged as a duplicate, and the help node to show (the error replaces the
 * field's own description while it applies).
 *
 * @param {Object} field Raw field config (label, description, unique, name).
 * @param {Object} scope Shared render context; scope.clientId identifies the block.
 * @return {{help: *, hasError: boolean}} The help node and the error flag.
 */
export function useUniqueHelp( field, scope ) {
	const hasError = useUniqueConflict(
		scope?.clientId,
		field.name,
		Boolean( field.unique )
	);

	return {
		hasError,
		help: hasError ? (
			<span className="cassette-cmf-block-builder-unique-error">
				{ __(
					'This value is already used elsewhere in this content. Choose a different one.',
					'cassette-cmf-block-builder'
				) }
			</span>
		) : (
			field.description
		),
	};
}
