<?php
/**
 * Document_Scope_Registrar class for Cassette-CMF Blocks
 *
 * Validates a block's top-level "document_scope" config — required
 * whenever the block declares at least one field resolving to the
 * "document"/"sidebar"/"more_menu" areas (Schema\Area_Resolver::requires_document_scope()),
 * since those areas belong to the editor as a whole rather than to a
 * single block instance: they can be relevant with zero instances of the
 * block in the post, so there is no per-instance "attributes" to bind
 * them to the way inspector/toolbar/canvas fields are. Per the design
 * plan: "Without it the library refuses to mount and logs."
 *
 * @package PedalCMS\CassetteCMFBlocks
 * @since 0.1.0
 */

namespace PedalCMS\CassetteCMFBlocks\Core;

/**
 * Class Document_Scope_Registrar
 */
class Document_Scope_Registrar {

	/**
	 * Validate and normalize a block's raw "document_scope" config.
	 *
	 * @param mixed  $raw      Raw "document_scope" value (expected: an array with a non-empty "post_types").
	 * @param string $block_id The owning block's name, for the log message and the default "target".
	 * @return array{postTypes: string[], whenPresent: bool, target: string}|null Null when invalid or absent — the caller must not mount the document-scoped fields.
	 */
	public static function normalize( $raw, string $block_id ): ?array {
		if ( ! is_array( $raw ) || empty( $raw['post_types'] ) || ! is_array( $raw['post_types'] ) ) {
			if ( function_exists( '_doing_it_wrong' ) ) {
				// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- _doing_it_wrong() messages are developer-facing debug notices, not page output.
				_doing_it_wrong(
					__METHOD__,
					sprintf(
						'Block "%s" declares a field in the "document"/"sidebar"/"more_menu" area but no valid "document_scope" (a non-empty "post_types" array is required). Its document-level fields will not be shown.',
						$block_id
					),
					'0.1.0'
				);
				// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			return null;
		}

		$target = is_string( $raw['target'] ?? null ) && '' !== $raw['target'] ? $raw['target'] : $block_id;

		return [
			'postTypes'   => array_values( array_map( 'strval', $raw['post_types'] ) ),
			'whenPresent' => ! empty( $raw['when_present'] ),
			'target'      => (string) str_replace( '/', '-', $target ),
		];
	}
}
