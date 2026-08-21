/**
 * A minimal test-only stand-in for @wordpress/server-side-render, wired via
 * jest.config.js's moduleNameMapper — see
 * tests/js/mocks/wordpress-block-editor.js's docblock for why a mock
 * rather than the real npm package (not a real project dependency; a
 * webpack externals mapping to window.wp.serverSideRender at runtime).
 *
 * Only exists so anything that transitively imports preview/ServerPreview.js
 * (block-edit.js -> areas/canvas.js -> register-block.js, for instance) is
 * importable under Jest at all — no test exercises real server-rendering
 * behaviour through this stub.
 */

export default function ServerSideRender() {
	return null;
}
