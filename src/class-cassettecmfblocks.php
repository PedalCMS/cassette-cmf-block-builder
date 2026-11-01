<?php
/**
 * Cassette-CMF Blocks Main Entry Point
 *
 * The primary facade class for Cassette-CMF Blocks, mirroring the parent
 * library's PedalCMS\CassetteCMF\CassetteCMF facade.
 *
 * @package PedalCMS\CassetteCMFBlocks
 * @since 0.2.0
 */

namespace PedalCMS\CassetteCMFBlocks;

use PedalCMS\CassetteCMFBlocks\Core\Block_Definition;
use PedalCMS\CassetteCMFBlocks\Core\Block_Manager;

/**
 * Class CassetteCMFBlocks
 *
 * Usage:
 *   use PedalCMS\CassetteCMFBlocks\CassetteCMFBlocks;
 *
 *   CassetteCMFBlocks::boot( [ 'prefix' => 'acme' ] ); // required, before registering
 *   CassetteCMFBlocks::register_from_array( $config );
 *   CassetteCMFBlocks::register_from_json( $json_file );
 */
class CassetteCMFBlocks {

	/**
	 * Boot the library with options, before any register_from_*() call.
	 *
	 * Options: "prefix" (string, required) derives every handle, the REST
	 * namespace, the binding source name and the JS global (see
	 * Core\Identity); "bindings_source" (bool, default false) registers the
	 * "{prefix}/field" block bindings source. If register_from_*() or
	 * init() ran first the library has already auto-booted with the defaults,
	 * and a later boot() with different options is ignored with a
	 * _doing_it_wrong() notice. Unknown option keys also trigger one.
	 *
	 * @api
	 * @since 0.2.0
	 * @param array<string, mixed> $options Boot options.
	 * @return Block_Manager
	 */
	public static function boot( array $options = [] ): Block_Manager {
		return Block_Manager::boot( $options );
	}

	/**
	 * Get a compiled block definition by its full block name.
	 *
	 * Returns null when the library has not been booted / used yet, when no
	 * block with that name was registered through it, or when its config
	 * does not compile.
	 *
	 * @api
	 * @since 0.2.0
	 * @param string $block_name Full block name, e.g. "acme/callout".
	 * @return Block_Definition|null
	 */
	public static function get_definition( string $block_name ): ?Block_Definition {
		$manager = Block_Manager::get_instance();

		if ( null === $manager || ! $manager->has_block( $block_name ) ) {
			return null;
		}

		try {
			return $manager->compile_block( $block_name );
		} catch ( \InvalidArgumentException $e ) {
			return null;
		}
	}

	/**
	 * Get the Block_Manager singleton instance.
	 *
	 * @api
	 * @since 0.2.0
	 * @return Block_Manager
	 */
	public static function init(): Block_Manager {
		return Block_Manager::init();
	}

	/**
	 * Register block configuration from a PHP array.
	 *
	 * @api
	 * @since 0.2.0
	 * @param array<string, mixed> $config Configuration array with a 'blocks' key.
	 * @return Block_Manager
	 */
	public static function register_from_array( array $config ): Block_Manager {
		return Block_Manager::init()->register_from_array( $config );
	}

	/**
	 * Register block configuration from a JSON file or string.
	 *
	 * @api
	 * @since 0.2.0
	 * @param string $json_path_or_string Path to JSON file or JSON string.
	 * @param bool   $validate            Whether to validate against the block schema. Default true.
	 * @return Block_Manager
	 */
	public static function register_from_json( string $json_path_or_string, bool $validate = true ): Block_Manager {
		return Block_Manager::init()->register_from_json( $json_path_or_string, $validate );
	}
}
