<?php
/**
 * Deprecation_Builder class for Cassette-CMF Blocks
 *
 * Computes a content hash of a block's current "render.markup" tree plus
 * its attribute schema, shipped in the editor payload
 * (Core\Editor_Payload) for introspection and tooling — a schema/markup
 * change between two requests is detectable by comparing this hash.
 *
 * Deliberately does NOT auto-synthesize a "deprecated" entry from a history
 * of previously-shipped hashes: that needs a persistence design (where
 * would old markup trees themselves be stored, across environments and
 * deploys, not just their hashes?) this milestone doesn't specify. The
 * explicit `render.deprecated` mechanism (see deprecations.js's
 * buildDeprecations()) already fully covers WordPress's actual
 * requirement — a block author who changes markup must supply the old
 * version, or existing saved content shows validation errors when the
 * editor reopens it. That's true of hand-written core blocks too; no core
 * block auto-detects this either. This class is the building block a
 * future auto-synthesis tool (or a consumer's own migration tooling) would
 * need, not that tool itself.
 *
 * @package Pedalcms\CassetteCmfBlocks
 * @since 0.1.0
 */

namespace Pedalcms\CassetteCmfBlocks\Render;

/**
 * Class Deprecation_Builder
 */
class Deprecation_Builder {

	/**
	 * Compute a content hash of a block's markup tree and attribute schema.
	 *
	 * @param array<string, mixed>               $markup     The "render.markup" tree (or a "render.deprecated[].markup" tree).
	 * @param array<string, array<string, mixed>> $attributes The block's resolved WP attribute schema.
	 * @return string An md5 hash, stable for identical (markup, attributes) pairs regardless of PHP array key order.
	 */
	public static function hash( array $markup, array $attributes ): string {
		return md5(
			(string) wp_json_encode(
				self::normalize(
					[
						'markup'     => $markup,
						'attributes' => $attributes,
					]
				)
			)
		);
	}

	/**
	 * Recursively sort every associative array's keys, so two arrays built
	 * in different insertion order (e.g. by different code paths that
	 * assemble the same logical config) hash identically — PHP array key
	 * order is otherwise incidental to a config's meaning, and
	 * wp_json_encode() alone preserves insertion order rather than
	 * normalizing it.
	 *
	 * List arrays (sequential integer keys, e.g. a block's "children") are
	 * left in their original order — order is meaningful there (it's
	 * render order), not incidental.
	 *
	 * @param array<int|string, mixed> $value Value to normalize.
	 * @return array<int|string, mixed>
	 */
	private static function normalize( array $value ): array {
		$is_list = array_is_list( $value );

		foreach ( $value as $key => $item ) {
			if ( is_array( $item ) ) {
				$value[ $key ] = self::normalize( $item );
			}
		}

		if ( ! $is_list ) {
			ksort( $value );
		}

		return $value;
	}
}
