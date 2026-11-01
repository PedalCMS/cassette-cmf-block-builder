<?php
/**
 * Settings_Binding_Source class for Cassette-CMF Blocks
 *
 * The get_value_callback for the "{prefix}/field" block bindings
 * source (Binding_Source_Registrar) — Mechanism B of meta binding. Routes
 * through the parent library's own public CassetteCMF::get_field() facade,
 * so a block can bind to a parent-library value: post meta, term meta, or a
 * settings-page option.
 *
 * Secure by default. A value resolves only when ALL of these hold, and
 * every refusal returns null (so core keeps the block's own saved content):
 *
 *   1. The parent library has CassetteCMF::is_field_public().
 *   2. `field` is a non-empty string, `context_type` is post|term|settings,
 *      and the context resolves.
 *   3. The field is registered with cassette-cmf and marked `bindings: true`
 *      (CassetteCMF::is_field_public()). Nothing is built from raw args.
 *   4. For post (and term) meta: the key is not protected meta; for a post that is not
 *      publicly viewable, the current user can `read_post` it.
 *   5. The `cassette_cmf_blocks_binding_allowed` filter, which can only refuse,
 *      returns true.
 *
 * @package PedalCMS\CassetteCMFBlocks
 * @since 0.2.0
 */

namespace PedalCMS\CassetteCMFBlocks\Binding;

use PedalCMS\CassetteCMF\CassetteCMF;

/**
 * Class Settings_Binding_Source
 */
class Settings_Binding_Source {

	/**
	 * Context types a binding may use.
	 *
	 * @var string[]
	 */
	private const CONTEXT_TYPES = [ 'post', 'term', 'settings' ];

	/**
	 * Resolve one binding's value.
	 *
	 * @param array<string, mixed> $source_args    The block's own `metadata.bindings.{attr}.args` —
	 *                                              { field, context?, context_type? }.
	 * @param \WP_Block             $block_instance The block instance being rendered.
	 * @return mixed The resolved value, or null when unresolvable or refused — never '' and never
	 *               WP_Error, so WordPress falls back to the block's own static content.
	 */
	public static function get_value( array $source_args, \WP_Block $block_instance ) {
		// Rule 1: the parent library must provide the visibility API.
		if ( ! static::cmf_supports_visibility() ) {
			return null;
		}

		// Rule 2: well-formed args and a resolvable context.
		$field        = $source_args['field'] ?? null;
		$context_type = $source_args['context_type'] ?? 'post';

		if ( ! is_string( $field ) || '' === $field || ! is_string( $context_type ) || ! in_array( $context_type, self::CONTEXT_TYPES, true ) ) {
			return null;
		}

		$context = self::resolve_context( $source_args, $context_type, $block_instance );

		if ( null === $context ) {
			return null;
		}

		// Rule 3: only fields registered with cassette-cmf and marked `bindings: true`.
		if ( ! CassetteCMF::is_field_public( $field, $context, $context_type ) ) {
			return null;
		}

		// Rule 4: meta protection and read access.
		if ( ! self::meta_readable( $field, $context, $context_type ) ) {
			return null;
		}

		// Rule 5: the narrowing filter. Consulted only after rules 1-4 passed.
		/**
		 * Filters whether a block binding may resolve. It can only refuse: returning
		 * false falls back to the block's saved content; returning true never widens
		 * the built-in rules.
		 *
		 * @param bool                 $allowed      Whether the binding is allowed. Default true.
		 * @param array<string, mixed> $source_args  The binding's args (field, context, context_type).
		 * @param \WP_Block            $block        The block being rendered.
		 */
		if ( ! apply_filters( 'cassette_cmf_blocks_binding_allowed', true, $source_args, $block_instance ) ) {
			return null;
		}

		$value = CassetteCMF::get_field( $field, $context, $context_type, null );

		return ( null === $value || '' === $value ) ? null : $value;
	}

	/**
	 * Whether the loaded parent library provides the field-visibility API.
	 * Protected so a test can simulate an older parent library.
	 *
	 * @return bool
	 */
	protected static function cmf_supports_visibility(): bool {
		// A parent library older than 0.2.0 lacks the method; static analysis sees the newest one.
		return class_exists( CassetteCMF::class ) && method_exists( CassetteCMF::class, 'is_field_public' ); // @phpstan-ignore function.alreadyNarrowedType
	}

	/**
	 * Resolve the binding's context: an explicit `source_args.context`
	 * always wins; a 'post' binding with none given falls back to the
	 * current block's own postId context (uses_context declares this — see
	 * Binding_Source_Registrar). 'term'/'settings' have no such natural
	 * fallback, so they require an explicit context.
	 *
	 * Post and term contexts must be positive integer IDs; a settings context a
	 * non-empty scalar page ID.
	 *
	 * @param array<string, mixed> $source_args    Raw source args.
	 * @param string                $context_type   One of 'post', 'term', 'settings'.
	 * @param \WP_Block             $block_instance The block instance being rendered.
	 * @return int|string|null
	 */
	private static function resolve_context( array $source_args, string $context_type, \WP_Block $block_instance ) {
		$context = $source_args['context'] ?? null;

		if ( null === $context || '' === $context ) {
			$context = 'post' === $context_type ? ( $block_instance->context['postId'] ?? null ) : null;
		}

		if ( ! is_scalar( $context ) || is_bool( $context ) ) {
			return null;
		}

		if ( 'settings' === $context_type ) {
			return '' === (string) $context ? null : (string) $context;
		}

		if ( is_int( $context ) ) {
			return $context > 0 ? $context : null;
		}

		return ctype_digit( (string) $context ) && (int) $context > 0 ? (int) $context : null;
	}

	/**
	 * Rule 4. Settings options have no meta key or post to check.
	 *
	 * @param string     $field        Field name, which CassetteCMF reads verbatim as the meta key.
	 * @param int|string $context      Resolved context.
	 * @param string     $context_type post|term|settings.
	 * @return bool
	 */
	private static function meta_readable( string $field, $context, string $context_type ): bool {
		if ( 'settings' === $context_type ) {
			return true;
		}

		if ( 'term' === $context_type ) {
			return ! is_protected_meta( $field, 'term' );
		}

		$post_id = (int) $context;

		if ( is_protected_meta( $field, 'post' ) ) {
			return false;
		}

		$post = get_post( $post_id );

		if ( ! $post instanceof \WP_Post ) {
			return false;
		}

		if ( post_password_required( $post ) ) {
			return false;
		}

		return is_post_publicly_viewable( $post ) || current_user_can( 'read_post', $post_id );
	}
}
