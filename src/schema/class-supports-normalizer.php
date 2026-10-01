<?php
/**
 * Supports_Normalizer class for Cassette-CMF Blocks
 *
 * Fills in defaults for a block's "args" passthrough that WordPress itself
 * would otherwise apply inconsistently or not at all.
 *
 * @package PedalCMS\CassetteCMFBlocks
 * @since 0.1.0
 */

namespace PedalCMS\CassetteCMFBlocks\Schema;

/**
 * Class Supports_Normalizer
 */
class Supports_Normalizer {

	/**
	 * Normalize a block's "args" passthrough.
	 *
	 * WP_Block_Type::$api_version defaults to 1 when unset
	 * (wp-includes/class-wp-block-type.php), which silently loses
	 * useBlockProps()-era semantics (wrapper attribute handling, several
	 * "supports" keys). This library never relies on that legacy default.
	 *
	 * "supports.html" defaults to false for non-static blocks: a fully
	 * dynamic block's saved markup is never rendered to the visitor, so
	 * offering "Edit as HTML" in the block toolbar edits dead code. A
	 * consumer can still opt back in explicitly.
	 *
	 * @param array<string, mixed> $args        Raw "args" passthrough from the block config.
	 * @param bool                 $is_dynamic  Whether the block's render mode is dynamic (vs. static).
	 * @return array<string, mixed>
	 */
	public static function normalize( array $args, bool $is_dynamic = true ): array {
		if ( ! isset( $args['apiVersion'] ) ) {
			$args['apiVersion'] = 3;
		}

		if ( $is_dynamic ) {
			$args['supports'] = $args['supports'] ?? [];
			if ( ! array_key_exists( 'html', $args['supports'] ) ) {
				$args['supports']['html'] = false;
			}
		}

		return $args;
	}
}
