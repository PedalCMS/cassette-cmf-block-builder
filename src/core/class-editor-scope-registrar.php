<?php
/**
 * Editor_Scope_Registrar class for Cassette-CMF Blocks
 *
 * Compiles every top-level "editor_scope" entry (a sibling of "blocks" —
 * see Block_Manager::get_editor_scopes()) into a single allowed_block_types_all
 * filter: "post_types" says which post types this scope restricts,
 * "allow_only" says which block names (glob patterns allowed, e.g.
 * "acme/*") may appear there. A post type not named by any scope is left
 * completely unrestricted — this only ever narrows the inserter, never
 * widens it beyond what WordPress/other plugins already allow.
 *
 * @package Pedalcms\CassetteCmfBlocks
 * @since 0.1.0
 */

namespace Pedalcms\CassetteCmfBlocks\Core;

/**
 * Class Editor_Scope_Registrar
 */
class Editor_Scope_Registrar {

	/**
	 * Register the allowed_block_types_all hook.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'allowed_block_types_all', [ self::class, 'filter_allowed_block_types' ], 10, 2 );
		}
	}

	/**
	 * Narrow the allowed block types for the current post type, if any
	 * registered "editor_scope" names it. The first matching scope wins —
	 * scopes aren't merged, since "post X is scoped by two different allow
	 * lists at once" has no sensible combined meaning.
	 *
	 * @param bool|string[]                $allowed_block_types  Array of block type names, or a bool to allow/deny everything.
	 * @param \WP_Block_Editor_Context|null $block_editor_context The current block editor context.
	 * @return bool|string[]
	 */
	public static function filter_allowed_block_types( $allowed_block_types, $block_editor_context ) {
		$post_type = $block_editor_context->post->post_type ?? null;

		if ( null === $post_type ) {
			return $allowed_block_types;
		}

		foreach ( Block_Manager::init()->get_editor_scopes() as $scope ) {
			$post_types = $scope['post_types'] ?? [];

			if ( ! is_array( $post_types ) || ! in_array( $post_type, $post_types, true ) ) {
				continue;
			}

			$patterns = is_array( $scope['allow_only'] ?? null ) ? $scope['allow_only'] : [];
			$universe = true === $allowed_block_types ? self::all_registered_block_names() : (array) $allowed_block_types;

			return array_values(
				array_filter(
					$universe,
					static function ( $block_name ) use ( $patterns ) {
						return self::matches_any_pattern( (string) $block_name, $patterns );
					}
				)
			);
		}

		return $allowed_block_types;
	}

	/**
	 * Every currently-registered block type name.
	 *
	 * @return string[]
	 */
	private static function all_registered_block_names(): array {
		if ( ! class_exists( \WP_Block_Type_Registry::class ) ) {
			return [];
		}

		return array_keys( \WP_Block_Type_Registry::get_instance()->get_all_registered() );
	}

	/**
	 * Whether a block name matches at least one glob-style pattern
	 * ("acme/*", "core/paragraph"). "*" is the only wildcard; every other
	 * character matches literally.
	 *
	 * @param string   $block_name Block name, e.g. "acme/callout".
	 * @param string[] $patterns   Glob-style patterns.
	 * @return bool
	 */
	private static function matches_any_pattern( string $block_name, array $patterns ): bool {
		foreach ( $patterns as $pattern ) {
			if ( ! is_string( $pattern ) ) {
				continue;
			}

			$regex = '/^' . str_replace( '\*', '.*', preg_quote( $pattern, '/' ) ) . '$/';

			if ( 1 === preg_match( $regex, $block_name ) ) {
				return true;
			}
		}

		return false;
	}
}
