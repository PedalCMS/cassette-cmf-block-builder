<?php
/**
 * Settings_Binding_Source class for Cassette-CMF Blocks
 *
 * The get_value_callback for the "cassette-cmf/field" block bindings
 * source (Binding_Source_Registrar) — Mechanism B of meta binding. Routes
 * through the parent library's own public CassetteCmf::get_field() facade,
 * so a block can bind to ANY parent-library value: post meta, term meta,
 * or a settings-page option, not just its own block's attributes.
 *
 * @package Pedalcms\CassetteCmfBlocks
 * @since 0.1.0
 */

namespace Pedalcms\CassetteCmfBlocks\Binding;

use Pedalcms\CassetteCmf\CassetteCmf;

/**
 * Class Settings_Binding_Source
 */
class Settings_Binding_Source {

	/**
	 * Resolve one binding's value.
	 *
	 * @param array<string, mixed> $source_args    The block's own `metadata.bindings.{attr}.args` —
	 *                                              { field, context?, context_type? }.
	 * @param \WP_Block             $block_instance The block instance being rendered.
	 * @return mixed The resolved value, or null when unresolvable — never '', so WordPress falls back
	 *               to the block's own static content instead of overwriting it with an empty value.
	 */
	public static function get_value( array $source_args, \WP_Block $block_instance ) {
		if ( ! class_exists( CassetteCmf::class ) ) {
			return null;
		}

		$field        = $source_args['field'] ?? null;
		$context_type = is_string( $source_args['context_type'] ?? null ) ? $source_args['context_type'] : 'post';
		$context      = self::resolve_context( $source_args, $context_type, $block_instance );

		if ( ! is_string( $field ) || '' === $field || null === $context ) {
			return null;
		}

		$value = CassetteCmf::get_field( $field, $context, $context_type, null );

		return null === $value ? null : $value;
	}

	/**
	 * Resolve the binding's context: an explicit `source_args.context`
	 * always wins; a 'post' binding with none given falls back to the
	 * current block's own postId context (uses_context declares this — see
	 * Binding_Source_Registrar). 'term'/'settings' have no such natural
	 * fallback (a block has no standard "current term" context), so they
	 * require an explicit context.
	 *
	 * @param array<string, mixed> $source_args  Raw source args.
	 * @param string                $context_type One of 'post', 'term', 'settings'.
	 * @param \WP_Block             $block_instance The block instance being rendered.
	 * @return int|string|null
	 */
	private static function resolve_context( array $source_args, string $context_type, \WP_Block $block_instance ) {
		if ( isset( $source_args['context'] ) && '' !== $source_args['context'] ) {
			return $source_args['context'];
		}

		if ( 'post' === $context_type ) {
			$post_id = $block_instance->context['postId'] ?? null;

			return null === $post_id ? null : (int) $post_id;
		}

		return null;
	}
}
