<?php
/**
 * Block_Renderer class for Cassette-CMF Blocks
 *
 * The front-end render pipeline for a "dynamic"-mode block: sanitize, then
 * render.callback -> render.template -> render.markup (first match wins),
 * then filter. Bound per-block as a closure captured at registration — see
 * Core\Block_Definition::get_render_callback().
 *
 * @package PedalCMS\CassetteCMFBlocks
 * @since 0.1.0
 */

namespace PedalCMS\CassetteCMFBlocks\Render;

use PedalCMS\CassetteCMFBlocks\Core\Block_Definition;

/**
 * Class Block_Renderer
 */
class Block_Renderer {

	/**
	 * Render one block instance.
	 *
	 * @param array<string, mixed> $attributes Resolved block attributes (WP has already merged attribute-schema defaults).
	 * @param string                $content    The block's own already-rendered InnerBlocks content.
	 * @param \WP_Block             $block      The block instance being rendered.
	 * @param Block_Definition      $definition This block's compiled definition.
	 * @return string
	 */
	public static function render( array $attributes, string $content, \WP_Block $block, Block_Definition $definition ): string {
		$sanitized_attributes = Attribute_Sanitizer::sanitize_all( $attributes, $definition->get_fields() );
		$render_config        = $definition->get_raw_config()['render'] ?? [];

		$html = self::render_body( $render_config, $sanitized_attributes, $content, $block );

		if ( function_exists( 'apply_filters' ) ) {
			/**
			 * Filters a block's rendered HTML.
			 *
			 * @param string                $html       Rendered HTML.
			 * @param string                $block_name Block name, e.g. "acme/callout".
			 * @param array<string, mixed>  $attributes Sanitized attributes.
			 * @param \WP_Block             $block      The block instance.
			 */
			$html = apply_filters( 'cassette_cmf_blocks_render', $html, $definition->get_name(), $sanitized_attributes, $block );
			$html = apply_filters( 'cassette_cmf_blocks_render_' . self::normalize_filter_id( $definition->get_name() ), $html, $sanitized_attributes, $block );
		}

		return $html;
	}

	/**
	 * Resolve which of callback/template/markup produces the output.
	 *
	 * @param array<string, mixed> $render_config Raw "render" config for this block.
	 * @param array<string, mixed> $attributes    Sanitized attributes.
	 * @param string                $content       InnerBlocks content.
	 * @param \WP_Block             $block         The block instance.
	 * @return string
	 */
	private static function render_body( array $render_config, array $attributes, string $content, \WP_Block $block ): string {
		if ( ! empty( $render_config['callback'] ) && is_callable( $render_config['callback'] ) ) {
			return (string) call_user_func( $render_config['callback'], $attributes, $content, $block );
		}

		if ( ! empty( $render_config['template'] ) && is_string( $render_config['template'] ) ) {
			return self::render_template( $render_config['template'], $attributes, $content, $block );
		}

		if ( ! empty( $render_config['markup'] ) && is_array( $render_config['markup'] ) ) {
			$wrap  = $render_config['wrapper']['use_block_wrapper_attributes'] ?? true;
			$scope = [ 'attributes' => $attributes ];

			return Markup_Renderer::render( $render_config['markup'], $scope, $attributes, $content, (bool) $wrap );
		}

		return '';
	}

	/**
	 * Render a PHP template file: require()'d inside an output buffer, with
	 * $attributes/$content/$block available in its local scope.
	 *
	 * @param string                $template_path Absolute path to a PHP file.
	 * @param array<string, mixed>  $attributes    Sanitized attributes, available to the template as $attributes.
	 * @param string                $content       InnerBlocks content, available to the template as $content.
	 * @param \WP_Block             $block         The block instance, available to the template as $block.
	 * @return string
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- $attributes/$content/$block are consumed by the required template via PHP's scope-sharing require(), not referenced directly in this method's own body.
	private static function render_template( string $template_path, array $attributes, string $content, \WP_Block $block ): string {
		if ( ! file_exists( $template_path ) ) {
			return '';
		}

		ob_start();
		require $template_path;

		return (string) ob_get_clean();
	}

	/**
	 * Normalize a block name into a hook-tag-safe id, matching
	 * Block_Manager::normalize_filter_id()'s convention.
	 *
	 * @param string $id Block name.
	 * @return string
	 */
	private static function normalize_filter_id( string $id ): string {
		return (string) str_replace( [ '/', '-' ], '_', $id );
	}
}
