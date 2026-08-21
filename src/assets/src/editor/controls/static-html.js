import { RawHTML } from '@wordpress/element';

/**
 * "static_html" control — display-only chrome, no attribute. Renders
 * consumer-authored HTML verbatim (e.g. instructions in the inspector).
 * This is block-author config, not third-party input: it ships in the same
 * PHP array/JSON as the field's own "type" and "name", authored by whoever
 * already has edit_posts and can load the block editor in the first place —
 * the same trust boundary WordPress itself draws around block registration.
 *
 * @param {Object} props       Component props.
 * @param {Object} props.field Raw field config: "html".
 * @return {JSX.Element} The control.
 */
export default function StaticHtml( { field } ) {
	return <RawHTML>{ field.html || '' }</RawHTML>;
}
