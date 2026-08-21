<?php
/**
 * Meta_Schema_Mapper class for Cassette-CMF Blocks
 *
 * Builds a real JSON Schema for a "meta"-sourced field's
 * register_post_meta()/register_term_meta()/register_user_meta() call —
 * specifically for the "show_in_rest" => [ "schema" => ... ] argument
 * Binding\Meta_Registrar passes. A real schema there is mandatory, not
 * cosmetic: without it, @wordpress/core-data's useEntityProp() (the
 * client-side hook Mechanism A's value/useFieldValue.js reads/writes meta
 * through) can neither read nor write the meta key at all — this is
 * exactly the gap the design plan calls out in the parent library, which
 * never calls register_post_meta() for its own meta fields at all (see
 * Binding\Meta_Registrar's docblock).
 *
 * @package Pedalcms\CassetteCmfBlocks
 * @since 0.1.0
 */

namespace Pedalcms\CassetteCmfBlocks\Schema;

/**
 * Class Meta_Schema_Mapper
 */
class Meta_Schema_Mapper {

	/**
	 * Build a JSON Schema for one "meta"-sourced field.
	 *
	 * Reuses Attribute_Schema_Mapper::map_one() for the type/default/enum
	 * resolution — the same fields, resolved the same way — but drops
	 * "role" (a WP block-attribute concept meaningless for meta) and adds
	 * "items" for an "array"-typed meta value, which JSON Schema requires
	 * but WP's own attribute schema doesn't strictly enforce.
	 *
	 * @param array<string, mixed> $leaf Field_Collection leaf entry (a "meta"-sourced field).
	 * @return array<string, mixed>
	 */
	public static function to_json_schema( array $leaf ): array {
		$schema = Attribute_Schema_Mapper::map_one( $leaf );
		$config = $leaf['config'];

		unset( $schema['role'] );

		if ( 'array' === ( $schema['type'] ?? null ) ) {
			// Every "array"-value control this library ships (e.g.
			// checkbox_group) holds an array of strings; a field can
			// override this fallback with its own explicit "items" schema.
			$schema['items'] = is_array( $config['items'] ?? null ) ? $config['items'] : [ 'type' => 'string' ];
		}

		return $schema;
	}
}
