<?php
/**
 * Pattern_Registrar class for Cassette-CMF Blocks
 *
 * Registers the top-level "patterns"/"pattern_categories" config keys via
 * register_block_pattern()/register_block_pattern_category(). A pattern's
 * content comes from either a raw "content" markup string (the same
 * WordPress-comment-delimited HTML register_block_pattern() always
 * accepted) or a declarative "blocks" tree — { blockName, attrs,
 * innerBlocks } nodes, recursively converted into the parsed-block array
 * shape serialize_blocks() expects and serialized into that same HTML — so
 * a JSON config (which can't embed a raw HTML string containing its own
 * quoting headaches as comfortably as PHP can) has an equally first-class
 * way to author a pattern.
 *
 * @package Pedalcms\CassetteCmfBlocks
 * @since 0.1.0
 */

namespace Pedalcms\CassetteCmfBlocks\Core;

/**
 * Class Pattern_Registrar
 */
class Pattern_Registrar {

	/**
	 * Hook registration onto "init".
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'init', [ self::class, 'register_patterns' ], 10 );
		}
	}

	/**
	 * Register every accumulated pattern category, then every pattern.
	 * Categories first: a pattern naming a category that doesn't exist yet
	 * still registers fine (WordPress creates an implicit fallback), but
	 * registering categories first means a pattern's own declared category
	 * is always the real, described one when both come from this library.
	 *
	 * @return void
	 */
	public static function register_patterns(): void {
		if ( ! function_exists( 'register_block_pattern' ) ) {
			return;
		}

		$manager = Block_Manager::init();

		foreach ( $manager->get_pattern_categories() as $category ) {
			self::register_one_category( $category );
		}

		foreach ( $manager->get_patterns() as $pattern ) {
			self::register_one_pattern( $pattern );
		}
	}

	/**
	 * register_block_pattern_category() one category entry.
	 *
	 * @param array<string, mixed> $category Raw category config: slug, label.
	 * @return void
	 */
	private static function register_one_category( array $category ): void {
		if ( empty( $category['slug'] ) || ! function_exists( 'register_block_pattern_category' ) ) {
			return;
		}

		if ( \WP_Block_Pattern_Categories_Registry::get_instance()->is_registered( $category['slug'] ) ) {
			return;
		}

		register_block_pattern_category(
			$category['slug'],
			[ 'label' => $category['label'] ?? $category['slug'] ]
		);
	}

	/**
	 * register_block_pattern() one pattern entry.
	 *
	 * @param array<string, mixed> $pattern Raw pattern config.
	 * @return void
	 */
	private static function register_one_pattern( array $pattern ): void {
		if ( empty( $pattern['slug'] ) || empty( $pattern['title'] ) ) {
			if ( function_exists( '_doing_it_wrong' ) ) {
				_doing_it_wrong(
					__METHOD__,
					sprintf( 'A pattern missing "slug" or "title" was skipped: %s', wp_json_encode( $pattern ) ),
					'0.1.0'
				);
			}
			return;
		}

		if ( \WP_Block_Patterns_Registry::get_instance()->is_registered( $pattern['slug'] ) ) {
			return;
		}

		$content = self::resolve_content( $pattern );

		if ( null === $content ) {
			if ( function_exists( '_doing_it_wrong' ) ) {
				// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- _doing_it_wrong() messages are developer-facing debug notices, not page output.
				_doing_it_wrong(
					__METHOD__,
					sprintf( 'Pattern "%s" declares neither "content" nor "blocks"; it was skipped.', $pattern['slug'] ),
					'0.1.0'
				);
				// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			return;
		}

		$properties = [
			'title'   => $pattern['title'],
			'content' => $content,
		];

		if ( isset( $pattern['description'] ) ) {
			$properties['description'] = $pattern['description'];
		}
		if ( isset( $pattern['categories'] ) && is_array( $pattern['categories'] ) ) {
			$properties['categories'] = $pattern['categories'];
		}
		if ( isset( $pattern['keywords'] ) && is_array( $pattern['keywords'] ) ) {
			$properties['keywords'] = $pattern['keywords'];
		}
		if ( isset( $pattern['viewport_width'] ) ) {
			$properties['viewportWidth'] = $pattern['viewport_width'];
		}
		if ( isset( $pattern['block_types'] ) && is_array( $pattern['block_types'] ) ) {
			$properties['blockTypes'] = $pattern['block_types'];
		}
		if ( array_key_exists( 'inserter', $pattern ) ) {
			$properties['inserter'] = (bool) $pattern['inserter'];
		}

		register_block_pattern( $pattern['slug'], $properties );
	}

	/**
	 * Resolve a pattern's HTML content from either its raw "content" string
	 * (used verbatim) or its declarative "blocks" tree (serialized). An
	 * explicit "content" always wins when both are present, since it's
	 * unambiguous which one the consumer meant as authoritative.
	 *
	 * @param array<string, mixed> $pattern Raw pattern config.
	 * @return string|null
	 */
	private static function resolve_content( array $pattern ): ?string {
		if ( isset( $pattern['content'] ) && is_string( $pattern['content'] ) ) {
			return $pattern['content'];
		}

		if ( isset( $pattern['blocks'] ) && is_array( $pattern['blocks'] ) && function_exists( 'serialize_blocks' ) ) {
			return serialize_blocks( self::to_parsed_blocks( $pattern['blocks'] ) );
		}

		return null;
	}

	/**
	 * Recursively convert a declarative { blockName, attrs, innerBlocks }
	 * tree into the parsed-block array shape serialize_blocks() expects.
	 *
	 * "innerContent" is exactly one null per child block, in order, and
	 * nothing else — serialize_block() (wp-includes/blocks.php) walks
	 * "innerContent" consuming one "innerBlocks" entry per null it finds,
	 * so a leaf block (no children) needs "innerContent" to be [ '' ] (a
	 * single empty-string content chunk), NOT [ null ] — a lone null with
	 * no matching innerBlocks entry throws "Undefined array key 0". This
	 * converter never produces interstitial raw HTML between children, so
	 * a block with N children always gets exactly N nulls, never N+1.
	 *
	 * @param array<int, mixed> $blocks Declarative block nodes.
	 * @return array<int, array<string, mixed>>
	 */
	private static function to_parsed_blocks( array $blocks ): array {
		$parsed = [];

		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) || empty( $block['blockName'] ) ) {
				continue;
			}

			$inner_blocks = self::to_parsed_blocks( is_array( $block['innerBlocks'] ?? null ) ? $block['innerBlocks'] : [] );

			$parsed[] = [
				'blockName'    => $block['blockName'],
				'attrs'        => is_array( $block['attrs'] ?? null ) ? $block['attrs'] : [],
				'innerBlocks'  => $inner_blocks,
				'innerHTML'    => '',
				'innerContent' => $inner_blocks ? array_fill( 0, count( $inner_blocks ), null ) : [ '' ],
			];
		}

		return $parsed;
	}
}
