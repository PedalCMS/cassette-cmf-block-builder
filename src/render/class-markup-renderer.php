<?php
/**
 * Markup_Renderer class for Cassette-CMF Blocks
 *
 * Renders one declarative markup node tree (see docs/markup-templates.md)
 * to an HTML string. This is the PHP half of "one declarative markup tree
 * drives three runtimes" — the same tree also drives markup/render.js (an
 * instant client-side preview) and, in a later milestone, static save().
 *
 * Escaping is per-node-kind and non-optional: "text" -> esc_html(),
 * "attrs" -> esc_attr() (esc_url() for href/src/action specifically),
 * "class" -> sanitize_html_class() per token, "html" -> wp_kses_post(). The
 * one opt-out is an explicit "escape" => "raw" on a node with "text"/"html"
 * content — documented as consumer-owned trust, never the default.
 *
 * @package Pedalcms\CassetteCmfBlocks
 * @since 0.1.0
 */

namespace Pedalcms\CassetteCmfBlocks\Render;

use Pedalcms\CassetteCmfBlocks\Compat\Cmf_Bridge;

/**
 * Class Markup_Renderer
 */
class Markup_Renderer {

	/**
	 * HTML5 void elements — self-closing, never get a closing tag or content.
	 *
	 * @var string[]
	 */
	private const VOID_ELEMENTS = [
		'area',
		'base',
		'br',
		'col',
		'embed',
		'hr',
		'img',
		'input',
		'link',
		'meta',
		'param',
		'source',
		'track',
		'wbr',
	];

	/**
	 * Attribute keys that get esc_url() instead of esc_attr().
	 *
	 * @var string[]
	 */
	private const URL_ATTRIBUTES = [ 'href', 'src', 'action' ];

	/**
	 * Render one markup node tree to an HTML string.
	 *
	 * @param array<string, mixed> $node             The markup node (typically the root of a block's "render.markup"/"preview.markup").
	 * @param array<string, mixed> $scope            Interpolation scope, e.g. [ 'attributes' => [...] ].
	 * @param array<string, mixed> $condition_context Field-name => value map "when" rules are evaluated against (typically the block's own attributes).
	 * @param string                $inner_content    The block's own already-rendered InnerBlocks content, for a "slot" node.
	 * @param bool                  $wrap_root        Whether to merge the root node's class/attrs through get_block_wrapper_attributes()
	 *                                                 so "supports" (color, spacing, align, ...) actually emit classes/styles.
	 * @return string
	 */
	public static function render( array $node, array $scope, array $condition_context, string $inner_content = '', bool $wrap_root = true ): string {
		return self::render_node( $node, $scope, $condition_context, $inner_content, $wrap_root );
	}

	/**
	 * Render one node (dispatches to repeat handling, then the element itself).
	 *
	 * @param array<string, mixed> $node              Markup node.
	 * @param array<string, mixed> $scope             Interpolation scope.
	 * @param array<string, mixed> $condition_context "when" evaluation context.
	 * @param string                $inner_content     InnerBlocks content for a "slot" node.
	 * @param bool                  $is_root           Whether this is the tree's root node.
	 * @return string
	 */
	private static function render_node( array $node, array $scope, array $condition_context, string $inner_content, bool $is_root ): string {
		if ( isset( $node['when'] ) && is_array( $node['when'] ) ) {
			$normalized = Cmf_Bridge::normalize_conditional( $node['when'] );

			if ( ! Conditional_Evaluator::evaluate( $normalized, $condition_context ) ) {
				return '';
			}
		}

		if ( isset( $node['repeat']['over'] ) ) {
			return self::render_repeated( $node, $scope, $condition_context, $inner_content, $is_root );
		}

		return self::render_element( $node, $scope, $condition_context, $inner_content, $is_root );
	}

	/**
	 * Render a "repeat"-bearing node once per item in the resolved array,
	 * each time with the scope extended by "{as}" (and "{as}_index").
	 *
	 * @param array<string, mixed> $node              Markup node with a "repeat" key.
	 * @param array<string, mixed> $scope             Interpolation scope.
	 * @param array<string, mixed> $condition_context "when" evaluation context.
	 * @param string                $inner_content     InnerBlocks content for a "slot" node.
	 * @param bool                  $is_root           Whether this is the tree's root node.
	 * @return string
	 */
	private static function render_repeated( array $node, array $scope, array $condition_context, string $inner_content, bool $is_root ): string {
		$repeat = $node['repeat'];
		$items  = Expression::evaluate( (string) ( $repeat['over'] ?? '' ), $scope );
		$as     = (string) ( $repeat['as'] ?? 'item' );

		if ( ! is_array( $items ) ) {
			return '';
		}

		$node_without_repeat = $node;
		unset( $node_without_repeat['repeat'] );

		$html = '';

		foreach ( array_values( $items ) as $index => $item ) {
			$item_scope                   = $scope;
			$item_scope[ $as ]            = $item;
			$item_scope[ $as . '_index' ] = $index;
			$html                        .= self::render_element( $node_without_repeat, $item_scope, $condition_context, $inner_content, $is_root && 0 === $index );
		}

		return $html;
	}

	/**
	 * Render one element node: open tag, content, close tag.
	 *
	 * @param array<string, mixed> $node              Markup node.
	 * @param array<string, mixed> $scope             Interpolation scope.
	 * @param array<string, mixed> $condition_context "when" evaluation context.
	 * @param string                $inner_content     InnerBlocks content for a "slot" node.
	 * @param bool                  $is_root           Whether this is the tree's root node.
	 * @return string
	 */
	private static function render_element( array $node, array $scope, array $condition_context, string $inner_content, bool $is_root ): string {
		$tag     = (string) ( $node['tag'] ?? 'div' );
		$is_void = in_array( $tag, self::VOID_ELEMENTS, true );

		$class = isset( $node['class'] ) ? Expression::interpolate( (string) $node['class'], $scope ) : '';

		$attrs = [];
		foreach ( ( $node['attrs'] ?? [] ) as $attr_name => $attr_template ) {
			$attrs[ $attr_name ] = Expression::interpolate( (string) $attr_template, $scope );
		}

		$open_tag = $is_root
			? self::build_root_open_tag( $tag, $class, $attrs )
			: self::build_open_tag( $tag, $class, $attrs );

		if ( $is_void ) {
			return $open_tag;
		}

		$content = self::render_content( $node, $scope, $condition_context, $inner_content );

		return $open_tag . $content . '</' . $tag . '>';
	}

	/**
	 * Build the root node's open tag via get_block_wrapper_attributes(), so
	 * "supports" (color, spacing, align, anchor, ...) actually emit their
	 * classes/styles/id onto the block's real wrapper element — the same
	 * mechanism useBlockProps() provides client-side.
	 *
	 * Deliberately routes class/attrs through get_block_wrapper_attributes()'s
	 * own esc_attr()-everything behaviour rather than this class's own
	 * per-attribute-kind escaping (sanitize_html_class() per class token,
	 * esc_url() for href/src/action): that's WP core's own function, used
	 * for exactly this purpose, and duplicating a different escaping
	 * strategy on top of it would only add inconsistency, not safety.
	 *
	 * @param string                $tag         Tag name.
	 * @param string                $class_names Interpolated class string.
	 * @param array<string, string> $attrs       Interpolated attrs, name => value.
	 * @return string
	 */
	private static function build_root_open_tag( string $tag, string $class_names, array $attrs ): string {
		if ( ! function_exists( 'get_block_wrapper_attributes' ) ) {
			return self::build_open_tag( $tag, $class_names, $attrs );
		}

		$extra = $attrs;
		if ( '' !== $class_names ) {
			$extra['class'] = $class_names;
		}

		$attr_string = get_block_wrapper_attributes( $extra );

		return '<' . $tag . ( '' !== $attr_string ? ' ' . $attr_string : '' ) . '>';
	}

	/**
	 * Build a non-root node's open tag with this class's own escaping:
	 * class tokens through sanitize_html_class(), href/src/action through
	 * esc_url(), everything else through esc_attr().
	 *
	 * @param string                $tag         Tag name.
	 * @param string                $class_names Interpolated class string.
	 * @param array<string, string> $attrs       Interpolated attrs, name => value.
	 * @return string
	 */
	private static function build_open_tag( string $tag, string $class_names, array $attrs ): string {
		$pieces = [ '<' . $tag ];

		if ( '' !== $class_names ) {
			$pieces[] = 'class="' . esc_attr( self::sanitize_class_tokens( $class_names ) ) . '"';
		}

		foreach ( $attrs as $attr_name => $attr_value ) {
			$escaped_value = in_array( $attr_name, self::URL_ATTRIBUTES, true )
				? esc_url( $attr_value )
				: esc_attr( $attr_value );

			$pieces[] = esc_attr( (string) $attr_name ) . '="' . $escaped_value . '"';
		}

		return implode( ' ', $pieces ) . '>';
	}

	/**
	 * Sanitize each whitespace-separated class token individually — a
	 * single bad token (e.g. from user-influenced data) shouldn't be able
	 * to smuggle characters into the class attribute.
	 *
	 * @param string $class_names Space-separated class string.
	 * @return string
	 */
	private static function sanitize_class_tokens( string $class_names ): string {
		$parts  = preg_split( '/\s+/', $class_names );
		$tokens = array_filter( array_map( 'sanitize_html_class', false !== $parts ? $parts : [] ) );

		return implode( ' ', $tokens );
	}

	/**
	 * Render a node's content: slot, then text/html, then children, concatenated.
	 *
	 * @param array<string, mixed> $node              Markup node.
	 * @param array<string, mixed> $scope             Interpolation scope.
	 * @param array<string, mixed> $condition_context "when" evaluation context.
	 * @param string                $inner_content     InnerBlocks content for a "slot" node.
	 * @return string
	 */
	private static function render_content( array $node, array $scope, array $condition_context, string $inner_content ): string {
		$output = '';

		if ( isset( $node['slot'] ) && in_array( $node['slot'], [ 'inner_blocks', 'content' ], true ) ) {
			// Already-rendered, WP-trusted HTML (WP renders InnerBlocks content
			// before calling render_callback) — never escaped, same as core's
			// own dynamic-block render_callback pattern of echoing $content directly.
			$output .= $inner_content;
		}

		if ( array_key_exists( 'text', $node ) ) {
			$mode    = (string) ( $node['escape'] ?? 'html' );
			$output .= self::escape_text_or_html( Expression::interpolate( (string) $node['text'], $scope ), $mode );
		} elseif ( array_key_exists( 'html', $node ) ) {
			$mode    = (string) ( $node['escape'] ?? 'kses' );
			$output .= self::escape_text_or_html( Expression::interpolate( (string) $node['html'], $scope ), $mode );
		}

		foreach ( ( $node['children'] ?? [] ) as $child ) {
			if ( ! is_array( $child ) ) {
				continue;
			}

			$output .= self::render_node( $child, $scope, $condition_context, $inner_content, false );
		}

		return $output;
	}

	/**
	 * Escape a node's interpolated "text"/"html" content according to its
	 * escape mode. "raw" is the one opt-out, and only ever applies here —
	 * "attrs"/"class"/href-src-action escaping in build_open_tag() is
	 * always applied and cannot be turned off via a node's "escape" key.
	 *
	 * @param string $value Interpolated content.
	 * @param string $mode  One of "html" (default for "text"), "kses" (default for "html"), "attr", "url", "raw".
	 * @return string
	 */
	private static function escape_text_or_html( string $value, string $mode ): string {
		switch ( $mode ) {
			case 'raw':
				return $value;

			case 'attr':
				return esc_attr( $value );

			case 'url':
				return esc_url( $value );

			case 'kses':
				return wp_kses_post( $value );

			case 'html':
			default:
				return esc_html( $value );
		}
	}
}
