/**
 * Extends @wordpress/scripts' default jest-unit config with
 * moduleNameMappers for @wordpress/block-editor and @wordpress/components —
 * see tests/js/mocks/wordpress-block-editor.js's docblock for why.
 */

const defaultConfig = require( '@wordpress/scripts/config/jest-unit.config.js' );

module.exports = {
	...defaultConfig,
	moduleNameMapper: {
		...defaultConfig.moduleNameMapper,
		'^@wordpress/blocks$': '<rootDir>/tests/js/mocks/wordpress-blocks.js',
		'^@wordpress/block-editor$':
			'<rootDir>/tests/js/mocks/wordpress-block-editor.js',
		'^@wordpress/components$':
			'<rootDir>/tests/js/mocks/wordpress-components.js',
		'^@wordpress/core-data$':
			'<rootDir>/tests/js/mocks/wordpress-core-data.js',
		'^@wordpress/data$': '<rootDir>/tests/js/mocks/wordpress-data.js',
		'^@wordpress/server-side-render$':
			'<rootDir>/tests/js/mocks/wordpress-server-side-render.js',
	},
};
