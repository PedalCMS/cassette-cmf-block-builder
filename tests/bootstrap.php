<?php
/**
 * Cassette-CMF Blocks Test Bootstrap
 *
 * Deliberately does NOT require a consumer plugin file (unlike the parent
 * library's bootstrap, which couples its test suite to cassette-cmf-example.php).
 * This suite tests the library in isolation.
 *
 * @package PedalCMS\CassetteCMFBlocks\Tests
 */

define( 'TESTS_PLUGIN_DIR', dirname( __DIR__ ) );

if ( ! getenv( 'WP_TESTS_DIR' ) ) {
	define( 'WP_TESTS_CONFIG_FILE_PATH', __DIR__ . '/wp-tests-config.php' );
}

if ( ! defined( 'CASSETTE_CMF_BLOCKS_TESTING' ) ) {
	define( 'CASSETTE_CMF_BLOCKS_TESTING', true );
}

$autoloader = dirname( __DIR__ ) . '/vendor/autoload.php';
if ( ! file_exists( $autoloader ) ) {
	echo 'Composer autoloader not found. Run `composer install` first.' . PHP_EOL;
	exit( 1 );
}
require_once $autoloader;

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_possible_dirs = [
		dirname( __DIR__ ) . '/vendor/wp-phpunit/wp-phpunit',
		rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib',
		'/tmp/wordpress-tests-lib',
	];

	foreach ( $_possible_dirs as $_dir ) {
		if ( file_exists( $_dir . '/includes/functions.php' ) ) {
			$_tests_dir = $_dir;
			break;
		}
	}
}

if ( ! $_tests_dir ) {
	echo 'WordPress test library not found.' . PHP_EOL;
	echo 'Option 1: Run bash bin/install-wp-tests.sh wordpress_test root root localhost latest' . PHP_EOL;
	echo 'Option 2: Set WP_TESTS_DIR environment variable.' . PHP_EOL;
	exit( 1 );
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output only.
	echo "Could not find {$_tests_dir}/includes/functions.php, have you run bin/install-wp-tests.sh?" . PHP_EOL;
	exit( 1 );
}

if ( ! defined( 'WP_CORE_DIR' ) ) {
	$_wp_core_dir = getenv( 'WP_CORE_DIR' );

	if ( ! $_wp_core_dir ) {
		$_possible_wp_dirs = [
			dirname( dirname( dirname( TESTS_PLUGIN_DIR ) ) ),
			rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress',
			'/tmp/wordpress',
		];

		foreach ( $_possible_wp_dirs as $_dir ) {
			if ( file_exists( $_dir . '/wp-includes/version.php' ) ) {
				$_wp_core_dir = $_dir;
				break;
			}
		}
	}

	if ( $_wp_core_dir ) {
		define( 'WP_CORE_DIR', $_wp_core_dir . '/' );
	}
}

require_once $_tests_dir . '/includes/functions.php';

/**
 * Load the library under test. No consumer plugin is loaded — this suite
 * exercises PedalCMS\CassetteCMFBlocks in isolation from any host plugin.
 */
function _manually_load_cassette_cmf_blocks() {
	if ( class_exists( \PedalCMS\CassetteCMFBlocks\CassetteCMFBlocks::class ) ) {
		\PedalCMS\CassetteCMFBlocks\CassetteCMFBlocks::init();
	}
}

tests_add_filter( 'muplugins_loaded', '_manually_load_cassette_cmf_blocks' );

require $_tests_dir . '/includes/bootstrap.php';

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output only.
echo 'WordPress test environment loaded from: ' . $_tests_dir . PHP_EOL;
