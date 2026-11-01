<?php
/**
 * Transform_Controller: shortcode / raw transforms with a PHP callback.
 *
 * @package PedalCMS\CassetteCMFBlocks\Tests\Unit
 */

use PedalCMS\CassetteCMFBlocks\CassetteCMFBlocks;
use PedalCMS\CassetteCMFBlocks\Core\Block_Manager;
use PedalCMS\CassetteCMFBlocks\Core\Editor_Payload;
use PedalCMS\CassetteCMFBlocks\Core\Identity;
use PedalCMS\CassetteCMFBlocks\Rest\Transform_Controller;
use PedalCMS\CassetteCMFBlocks\Schema\Control_Catalog;

require_once __DIR__ . '/CassetteCMFBlocks_UnitTestCase.php';

/**
 * Class Test_Transform_Controller
 */
class Test_Transform_Controller extends CassetteCMFBlocks_UnitTestCase {

	/**
	 * Every call the callbacks received: [ input, context ].
	 *
	 * @var array<int, array<int, mixed>>
	 */
	public static array $calls = [];

	/**
	 * What the callbacks return next.
	 *
	 * @var mixed
	 */
	public static $result = null;

	/**
	 * Reset library state and register the fixture blocks.
	 */
	public function set_up(): void {
		parent::set_up();
		Block_Manager::reset();
		Control_Catalog::reset();
		CassetteCMFBlocks::boot( [ 'prefix' => 'acme' ] );
		self::$calls  = [];
		self::$result = null;

		CassetteCMFBlocks::register_from_array(
			[
				'blocks' => [
					[
						'id'         => 'acme-test/embed',
						'fields'     => [
							[
								'name' => 'title',
								'type' => 'text',
							],
							[
								'name' => 'count',
								'type' => 'number',
							],
						],
						'transforms' => [
							'from' => [
								[
									'type'   => 'block',
									'blocks' => [ 'core/paragraph' ],
									'map'    => [ 'title' => 'content' ],
								],
								[
									'type'     => 'shortcode',
									'tag'      => 'embed-tag',
									'callback' => [ self::class, 'run_callback' ],
								],
								[
									'type'     => 'raw',
									'selector' => 'div.legacy',
									'callback' => [ self::class, 'run_callback' ],
								],
							],
						],
					],
					[
						'id'     => 'acme-test/item',
						'fields' => [
							[
								'name' => 'label',
								'type' => 'text',
							],
						],
					],
				],
			]
		);

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		Transform_Controller::register();
		do_action( 'rest_api_init', $wp_rest_server );
	}

	/**
	 * Reset library state.
	 */
	public function tear_down(): void {
		foreach ( [ 'acme-test/embed', 'acme-test/item' ] as $name ) {
			if ( WP_Block_Type_Registry::get_instance()->is_registered( $name ) ) {
				unregister_block_type( $name );
			}
		}
		Block_Manager::reset();
		Control_Catalog::reset();
		parent::tear_down();
	}

	/**
	 * The transform callback under test.
	 *
	 * @param mixed                $input   Shortcode atts or HTML.
	 * @param array<string, mixed> $context Context.
	 * @return mixed
	 */
	public static function run_callback( $input, array $context ) {
		self::$calls[] = [ $input, $context ];

		return self::$result;
	}

	/**
	 * Dispatch a transform request.
	 *
	 * @param array<string, mixed> $body Request body.
	 * @return WP_REST_Response
	 */
	private function post( array $body ): WP_REST_Response {
		$request = new WP_REST_Request( 'POST', '/' . Identity::rest_namespace() . '/transform' );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( (string) wp_json_encode( $body ) );

		return rest_get_server()->dispatch( $request );
	}

	/**
	 * Log in as a role.
	 *
	 * @param string $role Role.
	 */
	private function login( string $role ): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => $role ] ) );
	}

	/**
	 * The route is registered under the prefix namespace.
	 */
	public function test_route_is_registered(): void {
		$this->assertArrayHasKey( '/acme-blocks/v1/transform', rest_get_server()->get_routes( Identity::rest_namespace() ) );
	}

	/**
	 * Logged-out requests are denied, so are users who cannot edit posts.
	 */
	public function test_permissions(): void {
		wp_set_current_user( 0 );
		$this->assertSame(
			401,
			$this->post(
				[
					'block' => 'acme-test/embed',
					'index' => 1,
					'input' => [],
				]
			)->get_status()
		);

		$this->login( 'subscriber' );
		$this->assertSame(
			403,
			$this->post(
				[
					'block' => 'acme-test/embed',
					'index' => 1,
					'input' => [],
				]
			)->get_status()
		);
		$this->assertSame( [], self::$calls );
	}

	/**
	 * Unknown block, unknown index, a block-type entry and a missing index are all 404 / 400, and no callable runs.
	 */
	public function test_only_configured_remote_entries_are_reachable(): void {
		$this->login( 'editor' );
		self::$result = [ 'attributes' => [ 'title' => 'x' ] ];

		$this->assertSame(
			404,
			$this->post(
				[
					'block' => 'acme-test/none',
					'index' => 1,
					'input' => [],
				]
			)->get_status()
		);
		$this->assertSame(
			404,
			$this->post(
				[
					'block' => 'acme-test/embed',
					'index' => 9,
					'input' => [],
				]
			)->get_status()
		);
		$this->assertSame(
			404,
			$this->post(
				[
					'block' => 'acme-test/embed',
					'index' => 0,
					'input' => [],
				]
			)->get_status()
		);
		$this->assertSame(
			404,
			$this->post(
				[
					'block' => 'acme-test/item',
					'index' => 1,
					'input' => [],
				]
			)->get_status()
		);
		$this->assertSame(
			400,
			$this->post(
				[
					'block' => 'acme-test/embed',
					'input' => [],
				]
			)->get_status()
		);
		$this->assertSame(
			400,
			$this->post(
				[
					'block' => 'acme-test/embed',
					'index' => -1,
					'input' => [],
				]
			)->get_status()
		);
		$this->assertSame( [], self::$calls );
	}

	/**
	 * The input kind must fit the transform type.
	 */
	public function test_input_type_must_match(): void {
		$this->login( 'editor' );

		$this->assertSame(
			400,
			$this->post(
				[
					'block' => 'acme-test/embed',
					'index' => 1,
					'input' => '<div>',
				]
			)->get_status()
		);
		$this->assertSame(
			400,
			$this->post(
				[
					'block' => 'acme-test/embed',
					'index' => 2,
					'input' => [ 'a' => 'b' ],
				]
			)->get_status()
		);
		$this->assertSame(
			400,
			$this->post(
				[
					'block' => 'acme-test/embed',
					'index' => 1,
					'input' => 5,
				]
			)->get_status()
		);
		$this->assertSame( [], self::$calls );
	}

	/**
	 * A shortcode callback gets cleaned attributes and a context; the result is sanitised and limited to returned keys.
	 */
	public function test_shortcode_callback_result_is_sanitised(): void {
		$this->login( 'editor' );
		self::$result = [
			'attributes' => [
				'title' => '<b>Hi</b> <script>alert(1)</script>',
				'count' => '7',
			],
		];

		$response = $this->post(
			[
				'block'   => 'acme-test/embed',
				'index'   => 1,
				'input'   => [
					'id'  => 12,
					'0'   => 'pos',
					'bad' => [ 'nested' ],
				],
				'post_id' => 0,
			]
		);

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( [ 'title', 'count' ], array_keys( $data['attributes'] ) );
		$this->assertStringNotContainsString( '<', $data['attributes']['title'] );
		$this->assertStringNotContainsString( 'alert', $data['attributes']['title'] );
		$this->assertSame( 7, $data['attributes']['count'] );

		$this->assertSame(
			[
				'id' => '12',
				0    => 'pos',
			],
			self::$calls[0][0]
		);
		$this->assertSame(
			[
				'block'   => 'acme-test/embed',
				'index'   => 1,
				'type'    => 'shortcode',
				'post_id' => 0,
			],
			self::$calls[0][1]
		);
	}

	/**
	 * A raw callback gets the HTML string.
	 */
	public function test_raw_callback_gets_the_html(): void {
		$this->login( 'editor' );
		self::$result = [ 'attributes' => [ 'title' => 'T' ] ];

		$response = $this->post(
			[
				'block' => 'acme-test/embed',
				'index' => 2,
				'input' => '<div class="legacy">x</div>',
			]
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( '<div class="legacy">x</div>', self::$calls[0][0] );
		$this->assertSame( 'raw', self::$calls[0][1]['type'] );
	}

	/**
	 * A null (or malformed) callback result is 422.
	 */
	public function test_null_result_is_unprocessable(): void {
		$this->login( 'editor' );
		$body = [
			'block' => 'acme-test/embed',
			'index' => 1,
			'input' => [],
		];

		self::$result = null;
		$this->assertSame( 422, $this->post( $body )->get_status() );

		self::$result = [ 'innerBlocks' => [] ];
		$this->assertSame( 422, $this->post( $body )->get_status() );

		self::$result = 'text';
		$this->assertSame( 422, $this->post( $body )->get_status() );
		$this->assertCount( 3, self::$calls );
	}

	/**
	 * Inner blocks: unknown blocks dropped, library blocks sanitised, nesting kept.
	 */
	public function test_inner_blocks_are_checked_and_sanitised(): void {
		$this->login( 'editor' );
		self::$result = [
			'attributes'  => [ 'title' => 'T' ],
			'innerBlocks' => [
				[
					'name'        => 'acme-test/item',
					'attributes'  => [ 'label' => '<i>One</i>' ],
					'innerBlocks' => [
						[
							'name'       => 'acme-test/item',
							'attributes' => [ 'label' => 'Deep' ],
						],
					],
				],
				[
					'name'       => 'acme-test/unregistered',
					'attributes' => [ 'x' => 1 ],
				],
				'junk',
			],
		];

		$data = $this->post(
			[
				'block' => 'acme-test/embed',
				'index' => 1,
				'input' => [],
			]
		)->get_data();

		$this->assertCount( 1, $data['innerBlocks'] );
		$this->assertSame( 'acme-test/item', $data['innerBlocks'][0]['name'] );
		$this->assertSame( 'One', $data['innerBlocks'][0]['attributes']['label'] );
		$this->assertSame( 'Deep', $data['innerBlocks'][0]['innerBlocks'][0]['attributes']['label'] );
	}

	/**
	 * Attributes of blocks the library did not register are limited to declared keys and filtered for users without unfiltered_html.
	 */
	public function test_foreign_inner_block_attributes_are_filtered(): void {
		$this->login( 'contributor' );
		self::$result = [
			'attributes'  => [ 'title' => 'T' ],
			'innerBlocks' => [
				[
					'name'       => 'core/paragraph',
					'attributes' => [
						'content'    => 'ok <script>alert(1)</script><em>fine</em>',
						'undeclared' => 'dropped',
					],
				],
			],
		];

		$data  = $this->post(
			[
				'block' => 'acme-test/embed',
				'index' => 1,
				'input' => [],
			]
		)->get_data();
		$attrs = $data['innerBlocks'][0]['attributes'];

		$this->assertSame( [ 'content' ], array_keys( $attrs ) );
		$this->assertStringNotContainsString( '<script', $attrs['content'] );
		$this->assertStringContainsString( '<em>fine</em>', $attrs['content'] );
	}

	/**
	 * A post_id the user cannot edit is refused before the callback runs.
	 */
	public function test_post_id_needs_edit_rights(): void {
		$this->login( 'contributor' );
		$post_id      = self::factory()->post->create( [ 'post_author' => 1 ] );
		self::$result = [ 'attributes' => [ 'title' => 'T' ] ];

		$response = $this->post(
			[
				'block'   => 'acme-test/embed',
				'index'   => 1,
				'input'   => [],
				'post_id' => $post_id,
			]
		);

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( [], self::$calls );
	}

	/**
	 * Callbacks never reach the editor payload; positions and the REST namespace do.
	 */
	public function test_payload_has_no_callbacks(): void {
		$payload = Editor_Payload::build( Block_Manager::init() );
		$from    = $payload['blocks']['acme-test/embed']['transforms']['from'];

		$this->assertSame( 'acme-blocks/v1', $payload['restNamespace'] );
		$this->assertCount( 3, $from );
		$this->assertArrayNotHasKey( 'remote', $from[0] );
		$this->assertTrue( $from[1]['remote'] );
		$this->assertSame( 'embed-tag', $from[1]['tag'] );
		$this->assertSame( 'div.legacy', $from[2]['selector'] );
		$this->assertArrayNotHasKey( 'callback', $from[1] );
		$this->assertArrayNotHasKey( 'callback', $from[2] );
		$this->assertIsString( wp_json_encode( $payload ) );
	}

	/**
	 * Malformed shortcode / raw entries are rejected when the block compiles.
	 *
	 * @dataProvider bad_entries
	 *
	 * @param array<string, mixed> $entry Entry.
	 */
	public function test_bad_entries_are_rejected( array $entry ): void {
		$this->expectException( InvalidArgumentException::class );
		new PedalCMS\CassetteCMFBlocks\Core\Block_Definition(
			[
				'id'         => 'acme-test/bad',
				'transforms' => [ 'from' => [ $entry ] ],
			]
		);
	}

	/**
	 * Bad entries.
	 *
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public function bad_entries(): array {
		$cb = [ self::class, 'run_callback' ];

		return [
			'shortcode without callback' => [
				[
					'type' => 'shortcode',
					'tag'  => 'x',
				],
			],
			'shortcode not callable'     => [
				[
					'type'     => 'shortcode',
					'tag'      => 'x',
					'callback' => 'no_such_fn_here',
				],
			],
			'shortcode without tag'      => [
				[
					'type'     => 'shortcode',
					'callback' => $cb,
				],
			],
			'shortcode bad tag'          => [
				[
					'type'     => 'shortcode',
					'tag'      => 'a b',
					'callback' => $cb,
				],
			],
			'raw without selector'       => [
				[
					'type'     => 'raw',
					'callback' => $cb,
				],
			],
			'raw empty rule'             => [
				[
					'type'     => 'raw',
					'selector' => [],
					'callback' => $cb,
				],
			],
			'raw unknown rule key'       => [
				[
					'type'     => 'raw',
					'selector' => [
						'tag' => 'div',
						'foo' => 1,
					],
					'callback' => $cb,
				],
			],
		];
	}

	/**
	 * A raw selector rule object is accepted.
	 */
	public function test_raw_rule_object_is_accepted(): void {
		$definition = new PedalCMS\CassetteCMFBlocks\Core\Block_Definition(
			[
				'id'         => 'acme-test/ok',
				'transforms' => [
					'from' => [
						[
							'type'     => 'raw',
							'selector' => [
								'tag'   => 'div',
								'class' => [ 'a' ],
								'attrs' => [ 'data-x' => '1' ],
							],
							'callback' => [ self::class, 'run_callback' ],
						],
					],
				],
			]
		);

		$this->assertSame( 'acme-test/ok', $definition->get_name() );
	}
}
