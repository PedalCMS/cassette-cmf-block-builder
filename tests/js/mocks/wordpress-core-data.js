/**
 * A minimal test-only stand-in for @wordpress/core-data, wired via
 * jest.config.js's moduleNameMapper — see
 * tests/js/mocks/wordpress-block-editor.js's docblock for why a mock
 * rather than the real npm package (same reasoning: not a real project
 * dependency, and useEntityProp() pulls in @wordpress/data's full
 * useSelect()/useDispatch() machinery, which needs a real registered
 * store to behave meaningfully — out of scope to fake accurately here).
 *
 * This mock doesn't track real entity state at all: it always returns the
 * SAME value/setter pair, configurable per test via __setMockMeta()/
 * __getLastSetMetaCall(), enough to verify value/useFieldValue.js's own
 * dispatch logic (does it call useEntityProp with the right args, does it
 * read/write the right meta key) without simulating a real WP data store.
 */

/**
 * The "core" store identifier — a plain sentinel string is enough here,
 * since tests/js/mocks/wordpress-data.js's useSelect() mock keys its
 * registered selectors by whatever value a component passes to select(),
 * and value/useEntityOptions.js/controls/taxonomy-select.js both import
 * this same "store" export to do so, exactly like the real
 * @wordpress/core-data package.
 */
export const store = 'core';

let mockMeta = {};
let lastSetMetaCall = null;
let lastCallArgs = null;

export function useEntityProp( kind, name, prop ) {
	lastCallArgs = { kind, name, prop };

	const setMeta = ( newValue ) => {
		lastSetMetaCall = newValue;
	};

	return [ mockMeta, setMeta, mockMeta ];
}

/**
 * Test helper: set the meta object useEntityProp() returns.
 *
 * @param {Object} meta The meta object to return.
 */
export function __setMockMeta( meta ) {
	mockMeta = meta;
}

/**
 * Test helper: the most recent useEntityProp() call's (kind, name, prop) args.
 *
 * @return {Object|null} The last call's args, or null if never called.
 */
export function __getLastCallArgs() {
	return lastCallArgs;
}

/**
 * Test helper: the value most recently passed to the mocked setMeta().
 *
 * @return {*} The last setMeta() argument, or null if never called.
 */
export function __getLastSetMetaCall() {
	return lastSetMetaCall;
}

/**
 * Test helper: reset all mock state between tests.
 */
export function __resetMock() {
	mockMeta = {};
	lastSetMetaCall = null;
	lastCallArgs = null;
}
