<?php
/**
 * Block_Registry class for Cassette-CMF Blocks
 *
 * Holds compiled Block_Definition instances, keyed by block name. Separate
 * from Block_Manager's raw-config store: the registry only ever contains
 * definitions that have already passed Field_Collection/Attribute_Schema_Mapper
 * validation.
 *
 * @package Pedalcms\CassetteCmfBlocks
 * @since 0.1.0
 */

namespace Pedalcms\CassetteCmfBlocks\Core;

/**
 * Class Block_Registry
 */
class Block_Registry {

	/**
	 * Compiled definitions, keyed by block name.
	 *
	 * @var array<string, Block_Definition>
	 */
	private array $definitions = [];

	/**
	 * Add a compiled definition to the registry.
	 *
	 * @param Block_Definition $definition Compiled block definition.
	 * @return void
	 */
	public function add( Block_Definition $definition ): void {
		$this->definitions[ $definition->get_name() ] = $definition;
	}

	/**
	 * Get a single definition by block name.
	 *
	 * @param string $name Block name, e.g. "acme/callout".
	 * @return Block_Definition|null
	 */
	public function get( string $name ): ?Block_Definition {
		return $this->definitions[ $name ] ?? null;
	}

	/**
	 * Whether a definition is registered for the given block name.
	 *
	 * @param string $name Block name.
	 * @return bool
	 */
	public function has( string $name ): bool {
		return isset( $this->definitions[ $name ] );
	}

	/**
	 * All compiled definitions, keyed by block name.
	 *
	 * @return array<string, Block_Definition>
	 */
	public function all(): array {
		return $this->definitions;
	}
}
