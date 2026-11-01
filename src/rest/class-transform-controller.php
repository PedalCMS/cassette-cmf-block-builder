<?php
/**
 * Transform_Controller class for Cassette-CMF Blocks
 *
 * POST /{prefix}-blocks/v1/transform (see Core\Identity) with
 * { block, index, input }. Runs the PHP callback of a configured
 * `transforms.from` entry of type `shortcode` or `raw` and returns the
 * block to create: { attributes, innerBlocks? }.
 *
 * Safety:
 *  - the route runs only for users who can `edit_posts` (core REST cookie
 *    auth, so the X-WP-Nonce header is required for cookie sessions);
 *  - `block` + `index` must name a configured shortcode / raw entry with a
 *    callable callback; nothing from the request is ever used as a callable;
 *  - the returned attributes go through Block_Definition::sanitize_attributes()
 *    (the same path as rendering), inner blocks are checked against the block
 *    registry and sanitized the same way.
 *
 * @package PedalCMS\CassetteCMFBlocks
 * @since 0.2.0
 */

namespace PedalCMS\CassetteCMFBlocks\Rest;

use PedalCMS\CassetteCMFBlocks\CassetteCMFBlocks;
use PedalCMS\CassetteCMFBlocks\Core\Identity;
use PedalCMS\CassetteCMFBlocks\Schema\Transform_Config;

/**
 * Class Transform_Controller
 */
class Transform_Controller {

	/**
	 * Deepest level of nested inner blocks accepted from a callback.
	 */
	private const MAX_DEPTH = 8;

	/**
	 * Hook route registration onto "rest_api_init".
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
		}
	}

	/**
	 * Register the "/transform" route.
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		if ( ! function_exists( 'register_rest_route' ) ) {
			return;
		}

		register_rest_route(
			Identity::rest_namespace(),
			'/transform',
			[
				'methods'             => 'POST',
				'callback'            => [ self::class, 'transform' ],
				'permission_callback' => static function (): bool {
					return function_exists( 'current_user_can' ) && current_user_can( 'edit_posts' );
				},
				'args'                => [
					'block'   => [
						'type'     => 'string',
						'required' => true,
					],
					'index'   => [
						'type'     => 'integer',
						'required' => true,
						'minimum'  => 0,
					],
					'input'   => [
						'required'          => true,
						'validate_callback' => static function ( $value ): bool {
							return is_array( $value ) || is_string( $value );
						},
					],
					'post_id' => [
						'type'    => 'integer',
						'default' => 0,
						'minimum' => 0,
					],
				],
			]
		);
	}

	/**
	 * Route callback.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function transform( \WP_REST_Request $request ) {
		$block      = (string) $request->get_param( 'block' );
		$index      = (int) $request->get_param( 'index' );
		$input      = $request->get_param( 'input' );
		$post_id    = (int) $request->get_param( 'post_id' );
		$definition = CassetteCMFBlocks::get_definition( $block );
		$entry      = null === $definition ? null : Transform_Config::find_entry( $definition->get_raw_config(), $index );

		if ( null === $definition || null === $entry ) {
			return new \WP_Error( 'cassette_cmf_blocks_transform_not_found', __( 'No such transform.', 'cassette-cmf-block-builder' ), [ 'status' => 404 ] );
		}

		if ( $post_id > 0 && ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error( 'cassette_cmf_blocks_transform_forbidden', __( 'You are not allowed to edit this post.', 'cassette-cmf-block-builder' ), [ 'status' => rest_authorization_required_code() ] );
		}

		$is_shortcode = 'shortcode' === $entry['type'];

		if ( $is_shortcode ? ! is_array( $input ) : ! is_string( $input ) ) {
			return new \WP_Error(
				'cassette_cmf_blocks_transform_bad_input',
				$is_shortcode ? __( 'A shortcode transform needs "input" to be an object of attributes.', 'cassette-cmf-block-builder' ) : __( 'A raw transform needs "input" to be an HTML string.', 'cassette-cmf-block-builder' ),
				[ 'status' => 400 ]
			);
		}

		if ( $is_shortcode ) {
			$input = self::clean_shortcode_atts( $input );
		}

		$context = [
			'block'   => $block,
			'index'   => $index,
			'type'    => $entry['type'],
			'post_id' => $post_id,
		];

		$result = call_user_func( $entry['callback'], $input, $context );

		if ( ! is_array( $result ) || ! is_array( $result['attributes'] ?? null ) ) {
			return new \WP_Error( 'cassette_cmf_blocks_transform_no_match', __( 'The transform did not produce a block.', 'cassette-cmf-block-builder' ), [ 'status' => 422 ] );
		}

		$attributes = array_intersect_key( $definition->sanitize_attributes( $result['attributes'] ), $result['attributes'] );
		$body       = [ 'attributes' => $attributes ];

		if ( isset( $result['innerBlocks'] ) && is_array( $result['innerBlocks'] ) ) {
			$body['innerBlocks'] = self::sanitize_inner_blocks( $result['innerBlocks'], 1 );
		}

		return new \WP_REST_Response( $body, 200 );
	}

	/**
	 * Shortcode attributes as WordPress parses them: string values, named
	 * keys or positional integer keys.
	 *
	 * @param array<mixed> $atts Raw attributes from the request.
	 * @return array<string|int, string>
	 */
	private static function clean_shortcode_atts( array $atts ): array {
		$clean = [];

		foreach ( $atts as $key => $value ) {
			if ( is_scalar( $value ) ) {
				$clean[ $key ] = (string) $value;
			}
		}

		return $clean;
	}

	/**
	 * Check and sanitize inner blocks returned by a callback. An entry needs
	 * the name of a registered block; unknown blocks are dropped.
	 *
	 * @param array<mixed> $blocks Raw list of { name, attributes?, innerBlocks? }.
	 * @param int          $depth  Current depth (1 = top level).
	 * @return array<int, array<string, mixed>>
	 */
	private static function sanitize_inner_blocks( array $blocks, int $depth ): array {
		$clean = [];

		if ( $depth > self::MAX_DEPTH ) {
			return $clean;
		}

		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) || ! is_string( $block['name'] ?? null ) ) {
				continue;
			}

			$type = \WP_Block_Type_Registry::get_instance()->get_registered( $block['name'] );
			if ( null === $type ) {
				continue;
			}

			$raw        = is_array( $block['attributes'] ?? null ) ? $block['attributes'] : [];
			$definition = CassetteCMFBlocks::get_definition( $block['name'] );

			if ( null !== $definition ) {
				$attributes = array_intersect_key( $definition->sanitize_attributes( $raw ), $raw );
			} else {
				$attributes = self::sanitize_foreign_attributes( $type, $raw );
			}

			$entry = [
				'name'       => $block['name'],
				'attributes' => $attributes,
			];

			if ( is_array( $block['innerBlocks'] ?? null ) ) {
				$entry['innerBlocks'] = self::sanitize_inner_blocks( $block['innerBlocks'], $depth + 1 );
			}

			$clean[] = $entry;
		}

		return $clean;
	}

	/**
	 * Attributes for a block this library did not register: only the keys the
	 * block type declares (their types are not checked: such blocks validate
	 * their own attributes when the editor loads them), and strings filtered
	 * with wp_kses_post() unless the user may post unfiltered HTML.
	 *
	 * @param \WP_Block_Type       $type Block type.
	 * @param array<string, mixed> $raw  Raw attributes.
	 * @return array<string, mixed>
	 */
	private static function sanitize_foreign_attributes( \WP_Block_Type $type, array $raw ): array {
		$prepared = array_intersect_key( $raw, (array) $type->attributes );

		if ( current_user_can( 'unfiltered_html' ) ) {
			return $prepared;
		}

		array_walk_recursive(
			$prepared,
			static function ( &$value ): void {
				if ( is_string( $value ) ) {
					$value = wp_kses_post( $value );
				}
			}
		);

		return $prepared;
	}
}
