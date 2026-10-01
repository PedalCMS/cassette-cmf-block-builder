<?php
/**
 * Plugin Name: Cassette-CMF Blocks E2E Fixture
 * Description: Registers a demo block set from a PHP config array only — no JS file exists in this plugin. Its absence is the E2E proof that consumers write zero JavaScript.
 * Version: 0.0.0
 * License: GPL-2.0-or-later
 *
 * @package PedalCMS\CassetteCMFBlocks\Tests\E2E\FixturePlugin
 */

require_once __DIR__ . '/vendor/autoload.php';

use PedalCMS\CassetteCMFBlocks\CassetteCMFBlocks;

add_action(
	'init',
	static function () {
		CassetteCMFBlocks::register_from_json( __DIR__ . '/config/blocks.json' );
	},
	5
);
