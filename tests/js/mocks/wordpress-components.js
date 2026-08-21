/**
 * A minimal test-only stand-in for @wordpress/components, wired via
 * jest.config.js's moduleNameMapper — see
 * tests/js/mocks/wordpress-block-editor.js's docblock for why a mock
 * rather than the real npm package. Each export here is a plain function
 * component that returns null and exposes the props it received as
 * `.mock.calls` would via a spy — good enough to test THIS library's own
 * controls' logic (what value/onChange/props they pass down), not to
 * verify what the real WordPress components render.
 */

function makeStub( name ) {
	function Stub() {
		return null;
	}
	Stub.displayName = name;
	return Stub;
}

export const TextControl = makeStub( 'TextControl' );
export const TextareaControl = makeStub( 'TextareaControl' );
export const SelectControl = makeStub( 'SelectControl' );
export const Button = makeStub( 'Button' );
