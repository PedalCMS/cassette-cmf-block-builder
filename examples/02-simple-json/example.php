<?php
/**
 * Simple JSON registration example — the same block-registration capability
 * as 01-simple-array/example.php, but validated against Json\Block_Schema_Validator
 * first (register_from_json()'s default $validate = true).
 *
 * @package PedalCMS\CassetteCMFBlocks\Examples
 */

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

use PedalCMS\CassetteCMFBlocks\CassetteCMFBlocks;

/**
 * Register block configuration from the JSON file in this directory.
 */
function cassette_cmf_blocks_simple_json_init() {
	CassetteCMFBlocks::register_from_json( __DIR__ . '/config.json' );
}
add_action( 'init', 'cassette_cmf_blocks_simple_json_init', 5 );
