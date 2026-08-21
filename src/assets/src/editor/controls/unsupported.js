import { Notice } from '@wordpress/components';
import { sprintf, __ } from '@wordpress/i18n';

/**
 * Fallback rendered for any control type without a JS component — a
 * consumer's own custom control type registered with Control_Catalog
 * server-side but never wired up client-side via registerControlType(),
 * for instance. An unknown/not-yet-implemented control must never crash
 * the whole editor or silently vanish; this makes the gap visible and
 * named instead.
 *
 * @param {Object} props       Component props.
 * @param {Object} props.field Raw field config.
 * @param {string} props.type  The unresolved control type.
 * @return {JSX.Element} The control.
 */
export default function Unsupported( { field, type } ) {
	return (
		<Notice status="warning" isDismissible={ false }>
			{ sprintf(
				/* translators: 1: field label or name, 2: control type. */
				__(
					'"%1$s" uses control type "%2$s", which the editor doesn’t render yet.',
					'cassette-cmf-block-builder'
				),
				field.label || field.name || '',
				type
			) }
		</Notice>
	);
}
