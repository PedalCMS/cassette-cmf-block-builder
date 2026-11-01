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
 * @package PedalCMS\CassetteCMFBlocks
 * @since 0.2.0
 */

namespace PedalCMS\CassetteCMFBlocks\Core;

/**
 * Class Variation_Registrar
 */
class Variation_Registrar {

	/**
	 * The scopes a variation may declare.
	 *
	 * "block" makes the variation appear in the block variation picker shown
	 * on an empty block (like Columns / Query); "inserter" lists it in the
	 * inserter; "transform" in the block transform menu. WordPress treats a
	 * variation without "scope" as available in every scope, so this library
	 * opens the picker only for variations that list "block" explicitly.
	 *
	 * @var string[]
	 */
	public const SCOPES = [ 'block', 'inserter', 'transform' ];

	/**
	 * Check the variation keys the picker relies on. "scope" (a list drawn
	 * from SCOPES) and "innerBlocks" (a list of [ name, attributes, children ]
	 * templates) pass through untouched when well formed; a malformed value is
	 * dropped with a _doing_it_wrong() rather than reaching the client.
	 *
	 * @param array<string, mixed> $variation A variation with name and title.
	 * @param string               $block_id  The owning block's name.
	 * @return array<string, mixed>
	 */
	private static function normalize_variation( array $variation, string $block_id ): array {
		if ( array_key_exists( 'scope', $variation ) ) {
			$scope = $variation['scope'];
			if ( ! is_array( $scope ) || array_diff( $scope, self::SCOPES ) || array_filter( $scope, 'is_string' ) !== $scope ) {
				self::notice( sprintf( 'Block "%1$s" variation "%2$s": "scope" must be a list drawn from %3$s; it was dropped.', $block_id, (string) $variation['name'], implode( ', ', self::SCOPES ) ) );
				unset( $variation['scope'] );
			} else {
				$variation['scope'] = array_values( $scope );
			}
		}

		if ( array_key_exists( 'innerBlocks', $variation ) && ! is_array( $variation['innerBlocks'] ) ) {
			self::notice( sprintf( 'Block "%1$s" variation "%2$s": "innerBlocks" must be a list of block templates; it was dropped.', $block_id, (string) $variation['name'] ) );
			unset( $variation['innerBlocks'] );
		}

		return $variation;
	}

	/**
	 * Raise a developer notice.
	 *
	 * @param string $message Message.
	 * @return void
	 */
	private static function notice( string $message ): void {
		if ( function_exists( '_doing_it_wrong' ) ) {
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- _doing_it_wrong() messages are developer-facing debug notices, not page output.
			_doing_it_wrong( __CLASS__, $message, '0.2.0' );
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

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
						'0.2.0'
					);
					// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				continue;
			}

			$valid[] = self::normalize_variation( $variation, $block_id );
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
