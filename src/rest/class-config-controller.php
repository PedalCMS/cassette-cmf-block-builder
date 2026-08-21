<?php
/**
 * Config_Controller class for Cassette-CMF Blocks
 *
 * GET /cassette-cmf-block-builder/v1/config — the same Editor_Payload::build()
 * shape the inline bootstrap script ships, exposed as a real REST route
 * for debugging and WP-CLI introspection ("wp eval-file"/"wp rest" style
 * inspection without reading browser devtools). Never consulted on the
 * registration path itself — Core\Editor_Payload's own inline-script
 * delivery (Core\Asset_Loader) is the only thing the editor's synchronous
 * initial parse of post_content can depend on; a REST round-trip here
 * would reintroduce exactly the race the design plan rejected a
 * REST-based bridge for in the first place (see that plan's "PHP↔JS
 * bridge" section).
 *
 * @package Pedalcms\CassetteCmfBlocks
 * @since 0.1.0
 */

namespace Pedalcms\CassetteCmfBlocks\Rest;

use Pedalcms\CassetteCmfBlocks\Core\Block_Manager;
use Pedalcms\CassetteCmfBlocks\Core\Editor_Payload;

/**
 * Class Config_Controller
 */
class Config_Controller {

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
	 * Register the "/config" route.
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		if ( ! function_exists( 'register_rest_route' ) ) {
			return;
		}

		register_rest_route(
			'cassette-cmf-block-builder/v1',
			'/config',
			[
				'methods'             => 'GET',
				'callback'            => [ self::class, 'get_config' ],
				'permission_callback' => static function (): bool {
					return function_exists( 'current_user_can' ) && current_user_can( 'edit_posts' );
				},
			]
		);
	}

	/**
	 * Route callback: the current Editor_Payload, same shape as the inline
	 * bootstrap script (window.cassetteCmfBlocks) ships.
	 *
	 * @return \WP_REST_Response
	 */
	public static function get_config(): \WP_REST_Response {
		$payload = Editor_Payload::build( Block_Manager::init() );

		return new \WP_REST_Response( $payload, 200 );
	}
}
