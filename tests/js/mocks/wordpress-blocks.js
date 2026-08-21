/**
 * A minimal test-only stand-in for @wordpress/blocks, wired via
 * jest.config.js's moduleNameMapper — see
 * tests/js/mocks/wordpress-block-editor.js's docblock for why a mock
 * rather than the real npm package (same reasoning: not a real project
 * dependency).
 *
 * createBlock() here doesn't validate the block name against a real
 * registry or resolve real default attributes — it just returns
 * { name, attributes } verbatim, enough to verify transforms.js's own
 * dispatch logic (does it call createBlock with the right block name and
 * the right mapped attributes) without a real block-type registry.
 */

export function createBlock( name, attributes = {} ) {
	return { name, attributes };
}

export function registerBlockType( name, settings ) {
	return { name, ...settings };
}
