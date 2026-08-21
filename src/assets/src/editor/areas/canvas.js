import { useBlockProps } from '@wordpress/block-editor';
import MarkupPreview from '../preview/MarkupPreview';
import ServerPreview from '../preview/ServerPreview';

/**
 * The M3-era placeholder — still valid, and used whenever a block declares
 * no markup yet: something selectable in the canvas, not a crash or a
 * blank void, until the block gets a "render"/"preview" declaration. Its
 * own component (rather than a branch inside CanvasArea calling
 * useBlockProps() conditionally) because React's Rules of Hooks require a
 * hook to run unconditionally within the component that calls it —
 * CanvasArea itself conditionally renders one of three different
 * components instead, each free to call its own hooks unconditionally.
 *
 * @param {Object} props            Component props.
 * @param {Object} props.descriptor This block's Editor_Payload entry.
 * @return {JSX.Element} The placeholder.
 */
function CanvasPlaceholder( { descriptor } ) {
	const blockProps = useBlockProps( {
		className: 'cassette-cmf-block-builder-canvas-placeholder',
	} );

	return (
		<div { ...blockProps }>
			{ descriptor.settings.title || descriptor.name }
		</div>
	);
}

/**
 * Render a block's canvas area: an instant markup preview (default), a
 * server-rendered preview ("preview.mode": "server"), or the placeholder
 * above for a block that declares no markup at all yet.
 *
 * "preview.markup" overrides "render.markup" for the editor specifically
 * when set; falls back to "render.markup" otherwise — "one declarative
 * markup tree drives three runtimes" by default, with an explicit escape
 * hatch for a simplified editor-only preview.
 *
 * @param {Object} props            Component props.
 * @param {Object} props.descriptor This block's Editor_Payload entry: { name, settings, fields, render, preview }.
 * @param {Object} props.attributes Live block attributes.
 * @return {JSX.Element} The rendered canvas.
 */
export default function CanvasArea( { descriptor, attributes } ) {
	const render = descriptor.render || {};
	const preview = descriptor.preview || {};
	const markup = preview.markup || render.markup;

	if ( markup && 'server' !== preview.mode ) {
		return (
			<MarkupPreview
				markup={ markup }
				attributes={ attributes }
				innerBlocksConfig={ descriptor.innerBlocks }
			/>
		);
	}

	if ( 'server' === preview.mode ) {
		return (
			<ServerPreview
				blockName={ descriptor.name }
				attributes={ attributes }
			/>
		);
	}

	return <CanvasPlaceholder descriptor={ descriptor } />;
}
