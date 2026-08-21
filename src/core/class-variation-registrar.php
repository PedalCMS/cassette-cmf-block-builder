<?php
/**
 * Variation_Registrar class for Cassette-CMF Blocks
 *
 * "variations" is one of the block.json-style keys "args" already passes
 * straight through to register_block_type() (Core\Block_Definition never
 * strips it — see that class's docblock), and WP_Block_Type's own JS
 * consumer (wp.blocks.getBlockVariations()) reads a block's declared
 * variations as plain data with zero further wiring: title/description/
 * icon/attributes/innerBlocks/example/scope are all JSON-safe, and
 * "isActive" can be declared as a plain array of attribute names (WP
 * itself compares those attributes for equality) rather than a function —
 * so a purely declarative "variations" array already works with no
 * additional runtime code.
 *
 * What this class actually adds: validation (a variation missing "name" or
 * "title" is dropped with a _doing_it_wrong() instead of silently reaching
 * register_block_type() and corrupting the inserter) and the
 * cassette_cmf_blocks_block_variations[_{id}] filter pair, mirroring every
 * other id-suffixed filter in this library (see docs/block-config-reference.md).
 *
 * "variation_callback" (a real PHP callable, for variations WordPress
 * itself computes dynamically — e.g. per registered post type) is NOT
 * handled here: it isn't a per-variation entry, it's a separate top-level
 * "args" key that already passes through unchanged to register_block_type()
 * as-is (register_block_type()'s own "variation_callback" arg is already
 * snake_case, so Block_Definition::CAMEL_TO_SNAKE needs no entry for it).
 * A PHP callable obviously only ever makes sense when "args" comes from a
 * real PHP array (register_from_array()), never from JSON. See
 * Core\Editor_Payload's own guard against shipping it to the client.
 *
 * @package Pedalcms\CassetteCmfBlocks
 * @since 0.1.0
 */

namespace Pedalcms\CassetteCmfBlocks\Core;

/**
 * Class Variation_Registrar
 */
class Variation_Registrar {

	/**
	 * Validate and filter a block's declared "variations".
	 *
	 * @param array<int, mixed> $variations Raw "variations" entries.
	 * @param string            $block_id   The owning block's name, for the id-suffixed filter and _doing_it_wrong() messages.
	 * @return array<int, array<string, mixed>>
	 */
	public static function normalize( array $variations, string $block_id ): array {
		$valid = [];

		foreach ( $variations as $variation ) {
			if ( ! is_array( $variation ) || empty( $variation['name'] ) || empty( $variation['title'] ) ) {
				if ( function_exists( '_doing_it_wrong' ) ) {
					// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- _doing_it_wrong() messages are developer-facing debug notices, not page output.
					_doing_it_wrong(
						__METHOD__,
						sprintf(
							'Block "%s" declares a variation missing "name" or "title"; it was skipped.',
							$block_id
						),
						'0.1.0'
					);
					// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				continue;
			}

			$valid[] = $variation;
		}

		if ( function_exists( 'apply_filters' ) ) {
			$normalized_id = (string) str_replace( [ '/', '-' ], '_', $block_id );

			/**
			 * Filters a block's resolved variations.
			 *
			 * @param array<int, array<string, mixed>> $valid    Valid variation entries.
			 * @param string                            $block_id The block's name.
			 */
			$valid = apply_filters( 'cassette_cmf_blocks_block_variations', $valid, $block_id );
			$valid = apply_filters( "cassette_cmf_blocks_block_variations_{$normalized_id}", $valid );
		}

		return array_values( $valid );
	}
}
