<?php
/**
 * Advanced JSON example — the same 5 field blocks as
 * examples/03-advanced-array/example.php, registered from an external JSON
 * file instead of a PHP array, and validated against
 * Json\Block_Schema_Validator first (register_from_json()'s default
 * $validate = true). See that file's own doc comments for what each block
 * demonstrates; see this directory's README.md for what had to change
 * moving from PHP to JSON — no __(), and no PHP helper functions, so the
 * label/slug/help/required field set each block shares is written out in
 * full per block instead of assembled from a shared function call.
 *
 * @package PedalCMS\CassetteCMFBlocks\Examples
 */

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

use PedalCMS\CassetteCMFBlocks\CassetteCMFBlocks;

/**
 * Register block configuration from the JSON file in this directory.
 */
function cassette_cmf_blocks_advanced_json_init() {
	CassetteCMFBlocks::register_from_json( __DIR__ . '/config.json' );
}
add_action( 'init', 'cassette_cmf_blocks_advanced_json_init', 5 );
