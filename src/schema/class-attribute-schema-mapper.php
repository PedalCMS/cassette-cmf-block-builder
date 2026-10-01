<?php
/**
 * Attribute_Schema_Mapper class for Cassette-CMF Blocks
 *
 * Maps a Field_Collection's value-bearing leaves — plus a block's optional
 * top-level "attributes" passthrough for pure-data attributes with no
 * control — into the "attributes" array register_block_type() expects.
 *
 * Only fields whose "source" is "attribute" (the default) or unset are
 * mapped here. "meta"-sourced fields are handled by the meta-binding
 * milestone (register_post_meta(), not a block attribute); "context"-sourced
 * fields read from block context and are never declared as attributes;
 * "none" means the field carries no persisted value at all (e.g. a
 * display-only control that isn't already excluded by holds_value).
 *
 * @package PedalCMS\CassetteCMFBlocks
 * @since 0.1.0
 */

namespace PedalCMS\CassetteCMFBlocks\Schema;

use PedalCMS\CassetteCMFBlocks\Core\Field_Collection;

/**
 * Class Attribute_Schema_Mapper
 */
class Attribute_Schema_Mapper {

	/**
	 * Sensible zero-value defaults per WP attribute JSON type, used only
	 * when a field declares no explicit "default".
	 *
	 * @var array<string, mixed>
	 */
	private const TYPE_DEFAULTS = [
		'string'  => '',
		'number'  => 0,
		'integer' => 0,
		'boolean' => false,
		'array'   => [],
		'object'  => [],
	];

	/**
	 * Build the full "attributes" array for a block: mapped fields merged
	 * with the block's own top-level "attributes" passthrough.
	 *
	 * @param Field_Collection      $fields          The block's flattened field collection.
	 * @param array<string, mixed>  $extra_attributes Top-level "attributes" passthrough: name => WP attribute schema.
	 * @return array<string, array<string, mixed>>
	 * @throws \InvalidArgumentException If a field-derived attribute name collides with an "attributes" passthrough entry.
	 */
	public static function build( Field_Collection $fields, array $extra_attributes = [] ): array {
		$mapped = self::from_fields( $fields );

		foreach ( $extra_attributes as $name => $schema ) {
			if ( isset( $mapped[ $name ] ) ) {
				// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages don't need escaping.
				throw new \InvalidArgumentException(
					sprintf( 'Attribute "%s" is declared both by a field and in the top-level "attributes" passthrough.', $name )
				);
				// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}

			$mapped[ $name ] = $schema;
		}

		return $mapped;
	}

	/**
	 * Map a Field_Collection's attribute-sourced leaves to WP attribute schemas.
	 *
	 * A leaf nested inside a "repeater" is deliberately excluded: its value
	 * lives inside the repeater's own array attribute (one object per row),
	 * so mapping it as an independent top-level attribute too would both
	 * duplicate the row data as a second, disconnected attribute and leak a
	 * row's own field name into the block's flat attribute namespace.
	 *
	 * @param Field_Collection $fields The block's flattened field collection.
	 * @return array<string, array<string, mixed>>
	 */
	public static function from_fields( Field_Collection $fields ): array {
		$attributes = [];

		foreach ( $fields->get_leaves() as $leaf ) {
			if ( ! empty( $leaf['inside_repeater'] ) ) {
				continue;
			}

			$config = $leaf['config'];
			$source = $config['source'] ?? 'attribute';

			if ( 'attribute' !== $source ) {
				continue;
			}

			$attributes[ $config['name'] ] = self::map_one( $leaf );
		}

		return $attributes;
	}

	/**
	 * Map a single leaf entry to a WP attribute schema. Public (not just
	 * used by from_fields() above): Schema\Meta_Schema_Mapper reuses this
	 * for the type/default/enum resolution its own JSON Schema needs too —
	 * the two shapes overlap on everything except "role" (a WP block
	 * attribute concept with no meta-schema equivalent, which
	 * Meta_Schema_Mapper strips back out).
	 *
	 * @param array<string, mixed> $leaf Field_Collection leaf entry.
	 * @return array<string, mixed>
	 */
	public static function map_one( array $leaf ): array {
		$config = $leaf['config'];
		$type   = $config['value_type'] ?? self::spec_value_type( $leaf );

		$schema = [
			'type'    => $type,
			'default' => $config['default'] ?? ( self::TYPE_DEFAULTS[ $type ] ?? null ),
		];

		if ( isset( $config['options'] ) && is_array( $config['options'] ) ) {
			$schema['enum'] = array_keys( $config['options'] );
		} elseif ( isset( $config['enum'] ) && is_array( $config['enum'] ) ) {
			$schema['enum'] = $config['enum'];
		}

		if ( isset( $config['role'] ) ) {
			$schema['role'] = $config['role'];
		}

		return $schema;
	}

	/**
	 * Resolve a leaf's value_type from the control catalog when the leaf
	 * entry itself doesn't carry a pre-resolved spec (Field_Collection
	 * stores control_type/cmf_type but not the full spec array).
	 *
	 * @param array<string, mixed> $leaf Field_Collection leaf entry.
	 * @return string
	 */
	private static function spec_value_type( array $leaf ): string {
		$spec = Control_Catalog::get_type( $leaf['control_type'] );

		return $spec['value_type'] ?? 'string';
	}
}
