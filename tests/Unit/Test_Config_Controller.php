<?php
/**
 * Config_Controller test.
 *
 * @package Pedalcms\CassetteCmfBlocks\Tests\Unit
 */

use Pedalcms\CassetteCmfBlocks\CassetteCmfBlocks;
use Pedalcms\CassetteCmfBlocks\Core\Block_Manager;
use Pedalcms\CassetteCmfBlocks\Rest\Config_Controller;
use Pedalcms\CassetteCmfBlocks\Schema\Control_Catalog;

require_once __DIR__ . '/CassetteCmfBlocks_UnitTestCase.php';

/**
 * Class Test_Config_Controller
 */
class Test_Config_Controller extends CassetteCmfBlocks_UnitTestCase {

	/**
	 * Reset library singletons before each test.
	 */
	public function set_up(): void {
		parent::set_up();
		Block_Manager::reset();
		Control_Catalog::reset();
	}

	/**
	 * Reset library singletons after each test.
	 */
	public function tear_down(): void {
		Block_Manager::reset();
		Control_Catalog::reset();
		parent::tear_down();
	}

	/**
	 * Register a fresh REST server and fire "rest_api_init" for real
	 * (rather than calling Config_Controller::register_routes() directly,
	 * which trips WordPress's own "routes must be registered on the
	 * rest_api_init action" _doing_it_wrong() — register_rest_route()
	 * checks doing_action( 'rest_api_init' ), so the route must actually be
	 * registered from inside a real dispatch of that action).
	 *
	 * @return void
	 */
	private function init_rest_server(): void {
		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		Config_Controller::register();
		do_action( 'rest_api_init', $wp_rest_server );
	}

	/**
	 * The route registers under the expected namespace.
	 */
	public function test_route_is_registered(): void {
		$this->init_rest_server();

		$routes = rest_get_server()->get_routes( 'cassette-cmf-block-builder/v1' );

		$this->assertArrayHasKey( '/cassette-cmf-block-builder/v1/config', $routes );
	}

	/**
	 * A logged-out request is denied.
	 */
	public function test_denies_logged_out_requests(): void {
		$this->init_rest_server();
		wp_set_current_user( 0 );

		$request  = new WP_REST_Request( 'GET', '/cassette-cmf-block-builder/v1/config' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 401, $response->get_status() );
	}

	/**
	 * A user who can "edit_posts" gets the current Editor_Payload shape.
	 */
	public function test_returns_the_editor_payload_for_an_authorized_user(): void {
		$user_id = self::factory()->user->create( [ 'role' => 'editor' ] );
		wp_set_current_user( $user_id );

		CassetteCmfBlocks::register_from_array(
			[
				'blocks' => [
					[
						'id'   => 'acme/callout',
						'args' => [ 'title' => 'Callout' ],
					],
				],
			]
		);

		$this->init_rest_server();

		$request  = new WP_REST_Request( 'GET', '/cassette-cmf-block-builder/v1/config' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayHasKey( 'blocks', $data );
		$this->assertArrayHasKey( 'acme/callout', $data['blocks'] );
	}
}
