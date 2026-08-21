/**
 * Extends @wordpress/scripts' default Playwright config (browser, storage
 * state, wp-env webServer) to point testDir at tests/e2e/specs instead of
 * the default ./specs relative to this file.
 */
const path = require( 'path' );
const { defineConfig } = require( '@playwright/test' );
const baseConfig = require( '@wordpress/scripts/config/playwright.config' );

module.exports = defineConfig( {
	...baseConfig,
	testDir: path.resolve( __dirname, 'tests/e2e/specs' ),
} );
