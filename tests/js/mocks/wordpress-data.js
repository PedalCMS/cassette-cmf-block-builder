/**
 * A minimal test-only stand-in for @wordpress/data, wired via
 * jest.config.js's moduleNameMapper — see
 * tests/js/mocks/wordpress-block-editor.js's docblock for why a mock
 * rather than the real npm package (same reasoning: not a real project
 * dependency, and the real useSelect() needs a full registered-store
 * machinery this environment has no reason to stand up just for testing
 * THIS library's own hooks' dispatch logic).
 *
 * useSelect() here is a plain synchronous call-through: it calls the
 * mapper with a select() that returns whatever store the test configured
 * via __setStoreSelectors(). Good enough to test value/useEntityOptions.js
 * and controls/taxonomy-select.js's own logic (do they call the right
 * selector with the right args, do they map the result correctly), not to
 * verify real @wordpress/data resolution/caching behaviour.
 */

let storeSelectors = {};

export function useSelect( mapSelect ) {
	return mapSelect( ( storeKey ) => storeSelectors[ storeKey ] || {} );
}

/**
 * Test helper: register a store's selectors, keyed the same way a real
 * `select( storeKey )` call would be (e.g. the "core" store object
 * imported from @wordpress/core-data's own mock).
 *
 * @param {*}      storeKey  The store identifier a component calls select() with.
 * @param {Object} selectors Plain object of selector-name => function.
 */
export function __setStoreSelectors( storeKey, selectors ) {
	storeSelectors[ storeKey ] = selectors;
}

/**
 * Test helper: reset all registered store selectors between tests.
 */
export function __resetStores() {
	storeSelectors = {};
}
