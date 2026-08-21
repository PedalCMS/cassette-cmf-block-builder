import ServerSideRender from '@wordpress/server-side-render';
import { useBlockProps } from '@wordpress/block-editor';

/**
 * Server-rendered canvas preview, for output that genuinely requires PHP
 * (queries, shortcodes, embeds) rather than the instant, network-free
 * markup preview (MarkupPreview.js). "preview.mode": "server" opts a block
 * into this.
 *
 * Deliberately a thin wrapper: @wordpress/server-side-render's
 * ServerSideRender component already debounces (500ms after the first
 * render), cancels in-flight requests on unmount/attribute change, and
 * shows a loading skeleton and error state — the design plan's
 * "ServerPreview.js wraps ServerSideRender with a 400ms debounce, a
 * skeleton, request de-duplication, an error boundary" is core's own
 * behaviour here, not something this file needs to reimplement.
 *
 * "preview.when" gating (skip the request entirely for an unconfigured
 * block) is not implemented yet — like markup mode's "when" support, it
 * needs the same not-yet-built client-side condition evaluator (see
 * markup/render.js's docblock).
 *
 * @param {Object} props            Component props.
 * @param {string} props.blockName  Block name, e.g. "acme/callout".
 * @param {Object} props.attributes Live block attributes.
 * @return {JSX.Element} The rendered preview.
 */
export default function ServerPreview( { blockName, attributes } ) {
	const blockProps = useBlockProps();

	return (
		<div { ...blockProps }>
			<ServerSideRender block={ blockName } attributes={ attributes } />
		</div>
	);
}
