<?php
/**
 * Cassette-CMF Blocks Main Entry Point
 *
 * The primary facade class for Cassette-CMF Blocks, mirroring the parent
 * library's PedalCMS\CassetteCMF\CassetteCMF facade.
 *
 * @package PedalCMS\CassetteCMFBlocks
 * @since 0.1.0
 */

namespace PedalCMS\CassetteCMFBlocks;

use PedalCMS\CassetteCMFBlocks\Core\Block_Manager;

/**
 * Class CassetteCMFBlocks
 *
 * Usage:
 *   use PedalCMS\CassetteCMFBlocks\CassetteCMFBlocks;
 *
 *   CassetteCMFBlocks::register_from_array( $config );
 *   CassetteCMFBlocks::register_from_json( $json_file );
 */
class CassetteCMFBlocks {

	/**
	 * Get the Block_Manager singleton instance.
	 *
	 * @return Block_Manager
	 */
	public static function init(): Block_Manager {
		return Block_Manager::init();
	}

	/**
	 * Register block configuration from a PHP array.
	 *
	 * @param array<string, mixed> $config Configuration array with a 'blocks' key.
	 * @return Block_Manager
	 */
	public static function register_from_array( array $config ): Block_Manager {
		return Block_Manager::init()->register_from_array( $config );
	}

	/**
	 * Register block configuration from a JSON file or string.
	 *
	 * @param string $json_path_or_string Path to JSON file or JSON string.
	 * @param bool   $validate            Whether to validate against the block schema. Default true.
	 * @return Block_Manager
	 */
	public static function register_from_json( string $json_path_or_string, bool $validate = true ): Block_Manager {
		return Block_Manager::init()->register_from_json( $json_path_or_string, $validate );
	}
}
