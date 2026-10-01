<?php
/**
 * Category_Registrar class for Cassette-CMF Blocks
 *
 * Registers custom block categories declared via the top-level
 * "block_categories" config key (a sibling of "blocks" — see
 * Block_Manager::get_block_categories()), through the block_categories_all
 * filter.
 *
 * @package PedalCMS\CassetteCMFBlocks
 * @since 0.1.0
 */

namespace PedalCMS\CassetteCMFBlocks\Core;

/**
 * Class Category_Registrar
 */
class Category_Registrar {

	/**
	 * Register the block_categories_all hook.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'block_categories_all', [ self::class, 'filter_categories' ], 10, 1 );
		}
	}

	/**
	 * Append every registered custom category. A category missing "slug"
	 * is skipped (WordPress itself requires one); a slug that collides
	 * with an already-present category (core's own, or another plugin's,
	 * registered earlier) is skipped too, so this never silently
	 * duplicates a category in the inserter's category list.
	 *
	 * @param array<int, array<string, mixed>> $categories Existing block categories.
	 * @return array<int, array<string, mixed>>
	 */
	public static function filter_categories( array $categories ): array {
		$existing_slugs = array_column( $categories, 'slug' );

		foreach ( Block_Manager::init()->get_block_categories() as $category ) {
			$slug = $category['slug'] ?? null;

			if ( ! is_string( $slug ) || '' === $slug || in_array( $slug, $existing_slugs, true ) ) {
				continue;
			}

			$categories[]     = $category;
			$existing_slugs[] = $slug;
		}

		return $categories;
	}
}
