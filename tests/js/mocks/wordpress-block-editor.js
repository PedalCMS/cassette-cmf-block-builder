/**
 * A minimal test-only stand-in for @wordpress/block-editor, wired via
 * jest.config.js's moduleNameMapper.
 *
 * The real package isn't a project dependency (it's a webpack externals
 * mapping to window.wp.blockEditor at runtime — see webpack.config.js and
 * the design plan's note on why @wordpress/* packages aren't installed),
 * and pulling in the real npm package just for tests drags in a large,
 * fast-moving dependency tree with transitive ESM packages Jest's default
 * transform can't parse. This mock covers only what save.js/deprecations.js/
 * MarkupPreview.js actually use: useBlockProps()/useBlockProps.save() (a
 * plain prop-merge, not a hook, in the .save() context), InnerBlocks (the
 * editable placeholder component) and InnerBlocks.Content (the marker
 * component WP expands during serialization); plus a plain stub for
 * RichText (controls/rich-text.js), matching the same
 * "returns null, exposes the props it received" convention
 * tests/js/mocks/wordpress-components.js's stubs use. It intentionally
 * does NOT try to replicate real block-supports merging behaviour — that's
 * WP core's own tested code, not this library's.
 */

export function useBlockProps( props = {} ) {
	return props;
}
useBlockProps.save = ( props = {} ) => props;

export function InnerBlocksContent() {
	return null;
}
InnerBlocksContent.displayName = 'InnerBlocks.Content';

export function InnerBlocks() {
	return null;
}
InnerBlocks.Content = InnerBlocksContent;

export function RichText() {
	return null;
}
RichText.displayName = 'RichText';
