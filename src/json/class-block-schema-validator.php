<?php
/**
 * Block_Schema_Validator class for Cassette-CMF Blocks
 *
 * A hand-written validator in the same style as the parent library's
 * Json\Schema_Validator: it collects every error found rather than throwing
 * on the first one, so a consumer's JSON config can be fixed in one pass.
 * Deliberately not a subclass of the parent's validator — that class is
 * hardcoded to "cpts"/"settings_pages" semantics and assumes CPT/settings
 * field shapes.
 *
 * Reads valid control types from Control_Catalog::get_registered_types()
 * rather than a hardcoded list, mirroring the exact trick the parent's
 * Schema_Validator uses against Field_Factory::get_registered_types() — a
 * custom control type registered before validation runs is accepted
 * automatically.
 *
 * @package PedalCMS\CassetteCMFBlocks
 * @since 0.2.0
 */

namespace PedalCMS\CassetteCMFBlocks\Json;

use PedalCMS\CassetteCMFBlocks\Core\Block_Definition;
use PedalCMS\CassetteCMFBlocks\Render\Deprecation_Migrator;
use PedalCMS\CassetteCMFBlocks\Render\Unique_Checker;
use PedalCMS\CassetteCMFBlocks\Schema\Area_Resolver;
use PedalCMS\CassetteCMFBlocks\Schema\Control_Catalog;

/**
 * Class Block_Schema_Validator
 */
class Block_Schema_Validator {

	/**
	 * Errors collected by the most recent validate() call.
	 *
	 * @var string[]
	 */
	private array $errors = [];

	/**
	 * Validate a top-level blocks configuration array.
	 *
	 * @param array<string, mixed> $config Configuration array, as passed to register_from_array().
	 * @return bool True when no errors were found.
	 */
	public function validate( array $config ): bool {
		$this->errors = [];

		if ( isset( $config['blocks'] ) ) {
			if ( ! is_array( $config['blocks'] ) ) {
				$this->errors[] = 'blocks must be an array';
			} else {
				foreach ( $config['blocks'] as $index => $block ) {
					$this->validate_block( $block, "blocks[{$index}]" );
				}
			}
		}

		return ! $this->has_errors();
	}

	/**
	 * All errors found by the most recent validate() call.
	 *
	 * @return string[]
	 */
	public function get_errors(): array {
		return $this->errors;
	}

	/**
	 * Whether the most recent validate() call found any errors.
	 *
	 * @return bool
	 */
	public function has_errors(): bool {
		return ! empty( $this->errors );
	}

	/**
	 * All errors found, joined into one message.
	 *
	 * @return string
	 */
	public function get_error_message(): string {
		return implode( "\n", $this->errors );
	}

	/**
	 * Validate a single block entry.
	 *
	 * @param mixed  $block Block entry, expected to be an array.
	 * @param string $path  Error-message path prefix, e.g. "blocks[0]".
	 * @return void
	 */
	private function validate_block( $block, string $path ): void {
		if ( ! is_array( $block ) ) {
			$this->errors[] = "{$path} must be an object";
			return;
		}

		if ( empty( $block['id'] ) ) {
			$this->errors[] = "{$path} missing required field 'id'";
		} elseif ( ! is_string( $block['id'] ) || ! preg_match( '#^[a-z][a-z0-9-]*/[a-z][a-z0-9-]*$#', $block['id'] ) ) {
			$this->errors[] = "{$path}.id must be a namespaced block name matching ^[a-z][a-z0-9-]*/[a-z][a-z0-9-]*$, e.g. \"acme/callout\"";
		}

		if ( isset( $block['args'] ) && ! is_array( $block['args'] ) ) {
			$this->errors[] = "{$path}.args must be an object";
		} elseif ( isset( $block['args'] ) ) {
			$this->validate_asset_keys( $block['args'], "{$path}.args" );
		}

		if ( isset( $block['render']['deprecated'] ) ) {
			$this->validate_deprecated( $block['render']['deprecated'], "{$path}.render.deprecated" );
		}

		if ( isset( $block['fields'] ) ) {
			if ( ! is_array( $block['fields'] ) ) {
				$this->errors[] = "{$path}.fields must be an array";
			} else {
				foreach ( $block['fields'] as $index => $field ) {
					$this->validate_field( $field, "{$path}.fields[{$index}]" );
				}
			}
		}
	}

	/**
	 * Validate "render.deprecated": a list of entries, each with optional
	 * "attributes" (object), "markup" (object) and "migrate" (a name, or a
	 * declarative { rename: {old: new}, map: {key: {from: to}}, set: {...},
	 * remove: [keys] } object). "migrate_php" is a PHP callable and cannot
	 * appear in JSON, so it is not checked here.
	 *
	 * @param mixed  $deprecated Value of render.deprecated.
	 * @param string $path       Error-message path prefix.
	 * @return void
	 */
	private function validate_deprecated( $deprecated, string $path ): void {
		if ( ! is_array( $deprecated ) ) {
			$this->errors[] = "{$path} must be an array";
			return;
		}

		foreach ( $deprecated as $index => $entry ) {
			$entry_path = "{$path}[{$index}]";

			if ( ! is_array( $entry ) ) {
				$this->errors[] = "{$entry_path} must be an object";
				continue;
			}

			foreach ( [ 'attributes', 'markup' ] as $key ) {
				if ( isset( $entry[ $key ] ) && ! is_array( $entry[ $key ] ) ) {
					$this->errors[] = "{$entry_path}.{$key} must be an object";
				}
			}

			if ( ! isset( $entry['migrate'] ) || is_string( $entry['migrate'] ) ) {
				continue;
			}

			if ( ! is_array( $entry['migrate'] ) ) {
				$this->errors[] = "{$entry_path}.migrate must be a migration name or an object";
				continue;
			}

			foreach ( array_keys( $entry['migrate'] ) as $key ) {
				if ( ! in_array( $key, Deprecation_Migrator::MIGRATE_KEYS, true ) ) {
					$this->errors[] = "{$entry_path}.migrate has an unknown key '{$key}' (allowed: " . implode( ', ', Deprecation_Migrator::MIGRATE_KEYS ) . ')';
				}
			}

			$migrate = $entry['migrate'];
			foreach ( [ 'rename', 'set' ] as $key ) {
				if ( isset( $migrate[ $key ] ) && ! is_array( $migrate[ $key ] ) ) {
					$this->errors[] = "{$entry_path}.migrate.{$key} must be an object";
				}
			}
			if ( isset( $migrate['rename'] ) && is_array( $migrate['rename'] ) ) {
				foreach ( $migrate['rename'] as $old => $new ) {
					if ( ! is_string( $new ) || '' === $new ) {
						$this->errors[] = "{$entry_path}.migrate.rename.{$old} must be a non-empty attribute name";
					}
				}
			}
			if ( isset( $migrate['map'] ) ) {
				if ( ! is_array( $migrate['map'] ) ) {
					$this->errors[] = "{$entry_path}.migrate.map must be an object";
				} else {
					foreach ( $migrate['map'] as $name => $table ) {
						if ( ! is_array( $table ) ) {
							$this->errors[] = "{$entry_path}.migrate.map.{$name} must be an object of old value => new value";
						}
					}
				}
			}
			if ( isset( $migrate['remove'] ) ) {
				if ( ! is_array( $migrate['remove'] ) || array_filter( $migrate['remove'], 'is_string' ) !== $migrate['remove'] ) {
					$this->errors[] = "{$entry_path}.migrate.remove must be a list of attribute names";
				}
			}
		}
	}

	/**
	 * Validate the first-class asset keys of a block's "args": each must be a
	 * non-empty string or a non-empty list of non-empty strings.
	 *
	 * @param array<string, mixed> $args Block "args".
	 * @param string               $path Error-message path prefix.
	 * @return void
	 */
	private function validate_asset_keys( array $args, string $path ): void {
		foreach ( array_keys( Block_Definition::ASSET_KEYS ) as $key ) {
			if ( ! array_key_exists( $key, $args ) ) {
				continue;
			}

			$value = $args[ $key ];
			$valid = is_string( $value ) && '' !== trim( $value );

			if ( is_array( $value ) && ! empty( $value ) && array_keys( $value ) === range( 0, count( $value ) - 1 ) ) {
				$valid = true;
				foreach ( $value as $item ) {
					if ( ! is_string( $item ) || '' === trim( $item ) ) {
						$valid = false;
						break;
					}
				}
			}

			if ( ! $valid ) {
				$this->errors[] = "{$path}.{$key} must be a handle (non-empty string) or a non-empty list of handles";
			}
		}
	}

	/**
	 * Validate a single field/panel entry, recursing into "fields" for containers.
	 *
	 * @param mixed  $field Field entry, expected to be an array.
	 * @param string $path  Error-message path prefix.
	 * @return void
	 */
	private function validate_field( $field, string $path ): void {
		if ( ! is_array( $field ) ) {
			$this->errors[] = "{$path} must be an object";
			return;
		}

		if ( empty( $field['name'] ) ) {
			$this->errors[] = "{$path} missing required field 'name'";
		}

		if ( empty( $field['type'] ) ) {
			$this->errors[] = "{$path} missing required field 'type'";
		} elseif ( ! is_string( $field['type'] ) ) {
			$this->errors[] = "{$path}.type must be a string";
		} else {
			$valid_types = array_keys( Control_Catalog::get_registered_types() );
			if ( ! in_array( $field['type'], $valid_types, true ) ) {
				$this->errors[] = "{$path}.type must be one of: " . implode( ', ', $valid_types );
			}
		}

		if ( isset( $field['area'] ) ) {
			if ( ! is_string( $field['area'] ) ) {
				$this->errors[] = "{$path}.area must be a string";
			} elseif ( ! Area_Resolver::is_known( $field['area'] ) ) {
				$this->errors[] = "{$path}.area must be one of: " . implode( ', ', Area_Resolver::known_areas() );
			}
		}

		if ( isset( $field['conditional'] ) ) {
			$this->validate_conditional( $field['conditional'], "{$path}.conditional" );
		}

		if ( 'context' === ( $field['source'] ?? null ) && ( ! is_string( $field['context'] ?? null ) || '' === $field['context'] ) ) {
			$this->errors[] = "{$path}.context is required (a block-context key such as \"example/formId\") when source is \"context\"";
		}

		if ( isset( $field['unique'] ) ) {
			try {
				Unique_Checker::normalize_rule( $field['unique'], is_string( $field['name'] ?? null ) ? $field['name'] : '' );
			} catch ( \InvalidArgumentException $e ) {
				$this->errors[] = "{$path}.unique is invalid: " . $e->getMessage();
			}
		}

		if ( isset( $field['fields'] ) ) {
			if ( ! is_array( $field['fields'] ) ) {
				$this->errors[] = "{$path}.fields must be an array";
			} else {
				foreach ( $field['fields'] as $index => $child ) {
					$this->validate_field( $child, "{$path}.fields[{$index}]" );
				}
			}
		}
	}

	/**
	 * Validate a conditional-logic config: { relation?, rules: [...] }.
	 *
	 * @param mixed  $conditional Conditional config, expected to be an array.
	 * @param string $path        Error-message path prefix.
	 * @return void
	 */
	private function validate_conditional( $conditional, string $path ): void {
		if ( ! is_array( $conditional ) ) {
			$this->errors[] = "{$path} must be an object";
			return;
		}

		if ( isset( $conditional['relation'] ) && ! in_array( strtoupper( (string) $conditional['relation'] ), [ 'AND', 'OR' ], true ) ) {
			$this->errors[] = "{$path}.relation must be AND or OR";
		}

		if ( ! isset( $conditional['rules'] ) || ! is_array( $conditional['rules'] ) ) {
			$this->errors[] = "{$path}.rules is required and must be an array";
			return;
		}

		foreach ( $conditional['rules'] as $index => $rule ) {
			if ( ! is_array( $rule ) ) {
				$this->errors[] = "{$path}.rules[{$index}] must be an object";
				continue;
			}

			if ( empty( $rule['field'] ) ) {
				$this->errors[] = "{$path}.rules[{$index}] missing required field 'field'";
			}
		}
	}
}
