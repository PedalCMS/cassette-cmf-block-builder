import { evaluate } from '../conditions/evaluate';

/**
 * Whether the canvas preview may render: a block without `preview.when`
 * always previews; otherwise the rule decides (the same evaluator and shape
 * as every other condition, so `{ source: 'block', ref }` rules are ignored
 * and a rule set of only those is true).
 *
 * @param {Object|undefined} preview         The descriptor's `preview` entry.
 * @param {Object}           scopeAttributes Attributes plus context-field values.
 * @return {boolean} True when the preview should render.
 */
export function isPreviewReady( preview, scopeAttributes ) {
	if ( ! preview || ! preview.when ) {
		return true;
	}

	return evaluate( preview.when, scopeAttributes || {} );
}
