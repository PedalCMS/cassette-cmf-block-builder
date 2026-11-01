<?php
/**
 * Coexistence test: two copies of the library on one site.
 *
 * Loads a Strauss-style namespace-prefixed copy of src/ (and of the parent
 * library, which the copy refers to) next to the plain library. The plain copy
 * is booted as "alpha", the prefixed copy as "beta".
 *
 * @package PedalCMS\CassetteCMFBlocks\Tests\Unit
 */

use PedalCMS\CassetteCMFBlocks\Binding\Binding_Source_Registrar;
use PedalCMS\CassetteCMFBlocks\Core\Asset_Loader;
use PedalCMS\CassetteCMFBlocks\Core\Block_Manager;
use PedalCMS\CassetteCMFBlocks\Core\Identity;
use PedalCMS\CassetteCMFBlocks\Rest\Config_Controller;
use PedalCMS\CassetteCMFBlocks\Schema\Control_Catalog;

require_once __DIR__ . '/CassetteCMFBlocks_UnitTestCase.php';
require_once dirname( __DIR__ ) . '/Support/class-prefixed-copy.php';

/**
 * Class Test_Coexistence
 */
class Test_Coexistence extends CassetteCMFBlocks_UnitTestCase {

	private const COPY_NS = 'CopyB\\Vendor\\PedalCMS\\CassetteCMFBlocks';

	/**
	 * Root of the generated copies.
	 *
	 * @var string
	 */
	private static $copy_dir = '';

	/**
	 * Generate the prefixed copies once and register their autoloaders.
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		self::$copy_dir = rtrim( sys_get_temp_dir(), '/' ) . '/cassette-cmf-blocks-copy-b-' . getmypid();

		$map = [
			'PedalCMS\\CassetteCMFBlocks' => self::COPY_NS,
			'PedalCMS\\CassetteCMF'       => 'CopyB\\Vendor\\PedalCMS\\CassetteCMF',
		];

		// The block builder, without its editor sources (only the build is used).
		Prefixed_Copy::generate( dirname( __DIR__, 2 ) . '/src', self::$copy_dir . '/blocks/src', $map, [ 'assets/src' ] );
		Prefixed_Copy::autoload( self::$copy_dir . '/blocks/src' );

		// The parent library the copy refers to, prefixed the same way.
		$cmf_src = (string) realpath( dirname( __DIR__, 2 ) . '/vendor/pedalcms/cassette-cmf/src' );
		Prefixed_Copy::generate( $cmf_src, self::$copy_dir . '/cmf/src', $map, [ 'assets' ] );
		Prefixed_Copy::autoload( self::$copy_dir . '/cmf/src' );
	}

	/**
	 * Remove the generated copies.
	 */
	public static function tear_down_after_class(): void {
		Prefixed_Copy::remove( self::$copy_dir );
		parent::tear_down_after_class();
	}

	/**
	 * Fully qualified name of a class in the prefixed copy.
	 *
	 * @param string $name Class name relative to the copy's root namespace.
	 * @return string
	 */
	private function copy( string $name ): string {
		return self::COPY_NS . '\\' . $name;
	}

	/**
	 * Reset both copies' singletons.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->reset_both();
		wp_deregister_script( 'alpha-blocks-editor' );
		wp_deregister_script( 'beta-blocks-editor' );
		wp_deregister_style( 'alpha-blocks-editor-style' );
		wp_deregister_style( 'beta-blocks-editor-style' );
	}

	/**
	 * Reset both copies' singletons.
	 */
	public function tear_down(): void {
		$this->reset_both();
		foreach ( [ 'alpha/field', 'beta/field' ] as $source ) {
			if ( null !== get_block_bindings_source( $source ) ) {
				unregister_block_bindings_source( $source );
			}
		}
		foreach ( [ 'alpha-test/card', 'beta-test/note' ] as $block ) {
			if ( WP_Block_Type_Registry::get_instance()->is_registered( $block ) ) {
				unregister_block_type( $block );
			}
		}
		parent::tear_down();
	}

	/**
	 * Reset both copies.
	 *
	 * @return void
	 */
	private function reset_both(): void {
		Block_Manager::reset();
		Control_Catalog::reset();
		call_user_func( [ $this->copy( 'Core\\Block_Manager' ), 'reset' ] );
		call_user_func( [ $this->copy( 'Schema\\Control_Catalog' ), 'reset' ] );
	}

	/**
	 * Boot both copies (plain as "alpha", prefixed as "beta") and register one distinct block in each.
	 *
	 * @param bool $bindings Whether to enable the bindings source in both.
	 * @return void
	 */
	private function boot_both( bool $bindings = false ): void {
		$facade_b = $this->copy( 'CassetteCMFBlocks' );

		// The prefixed copy registers late (after "init"); that notice is the harness, not the subject.
		$this->setExpectedIncorrectUsage( $this->copy( 'Core\\Block_Manager' ) . '::register_blocks' );

		\PedalCMS\CassetteCMFBlocks\CassetteCMFBlocks::boot(
			[
				'prefix'          => 'alpha',
				'bindings_source' => $bindings,
			]
		);
		$facade_b::boot(
			[
				'prefix'          => 'beta',
				'bindings_source' => $bindings,
			]
		);

		\PedalCMS\CassetteCMFBlocks\CassetteCMFBlocks::register_from_array(
			[
				'blocks' => [
					[
						'id'     => 'alpha-test/card',
						'args'   => [ 'title' => 'Alpha card' ],
						'fields' => [
							[
								'name'  => 'heading',
								'type'  => 'text',
								'label' => 'Heading',
							],
						],
					],
				],
			]
		);
		$facade_b::register_from_array(
			[
				'blocks' => [
					[
						'id'     => 'beta-test/note',
						'args'   => [ 'title' => 'Beta note' ],
						'fields' => [
							[
								'name'  => 'body',
								'type'  => 'text',
								'label' => 'Body',
							],
						],
					],
				],
			]
		);
	}

	/**
	 * Both copies register their own block, with their own identifiers.
	 */
	public function test_both_copies_register_their_own_blocks(): void {
		$this->boot_both();

		$registry = WP_Block_Type_Registry::get_instance();
		$this->assertTrue( $registry->is_registered( 'alpha-test/card' ) );
		$this->assertTrue( $registry->is_registered( 'beta-test/note' ) );

		$beta = call_user_func( [ $this->copy( 'Core\\Block_Manager' ), 'init' ] );
		$this->assertTrue( Block_Manager::init()->has_block( 'alpha-test/card' ) );
		$this->assertFalse( Block_Manager::init()->has_block( 'beta-test/note' ) );
		$this->assertTrue( $beta->has_block( 'beta-test/note' ) );
		$this->assertFalse( $beta->has_block( 'alpha-test/card' ) );

		$identity_b = $this->copy( 'Core\\Identity' );
		$this->assertNotSame( Identity::editor_script_handle(), $identity_b::editor_script_handle() );
		$this->assertSame( 'alpha-blocks-editor', Identity::editor_script_handle() );
		$this->assertSame( 'beta-blocks-editor', $identity_b::editor_script_handle() );
		$this->assertSame( 'cassetteCmfBlocks_alpha', Identity::js_global() );
		$this->assertSame( 'cassetteCmfBlocks_beta', $identity_b::js_global() );
	}

	/**
	 * Each copy's editor payload, handle and inline script contain only its own block.
	 */
	public function test_each_editor_payload_contains_only_its_own_block(): void {
		$this->boot_both();

		Asset_Loader::enqueue();
		call_user_func( [ $this->copy( 'Core\\Asset_Loader' ), 'enqueue' ] );

		$scripts = wp_scripts();
		$this->assertTrue( isset( $scripts->registered['alpha-blocks-editor'] ) );
		$this->assertTrue( isset( $scripts->registered['beta-blocks-editor'] ) );
		$this->assertNotSame( $scripts->registered['alpha-blocks-editor']->src, $scripts->registered['beta-blocks-editor']->src );

		$alpha = implode( "\n", (array) $scripts->get_data( 'alpha-blocks-editor', 'before' ) );
		$beta  = implode( "\n", (array) $scripts->get_data( 'beta-blocks-editor', 'before' ) );

		// The JSON encoder escapes the slash character.
		$this->assertStringContainsString( 'window.cassetteCmfBlocksBoot = "cassetteCmfBlocks_alpha";', $alpha );
		$this->assertStringContainsString( 'window["cassetteCmfBlocks_alpha"] = {', $alpha );
		$this->assertStringContainsString( 'alpha-test\\/card', $alpha );
		$this->assertStringNotContainsString( 'beta-test', $alpha );
		$this->assertStringNotContainsString( 'cassetteCmfBlocks_beta', $alpha );

		$this->assertStringContainsString( 'window.cassetteCmfBlocksBoot = "cassetteCmfBlocks_beta";', $beta );
		$this->assertStringContainsString( 'window["cassetteCmfBlocks_beta"] = {', $beta );
		$this->assertStringContainsString( 'beta-test\\/note', $beta );
		$this->assertStringNotContainsString( 'alpha-test', $beta );
		$this->assertStringNotContainsString( 'cassetteCmfBlocks_alpha', $beta );

		// Neither inline script publishes the old fixed global.
		$this->assertStringNotContainsString( 'window.cassetteCmfBlocks =', $alpha . $beta );

		// Blocking scripts: no loading strategy, so the bundles cannot interleave.
		$this->assertEmpty( $scripts->get_data( 'alpha-blocks-editor', 'strategy' ) );
		$this->assertEmpty( $scripts->get_data( 'beta-blocks-editor', 'strategy' ) );
	}

	/**
	 * The two copies' style handles differ and both enqueue.
	 */
	public function test_style_handles_differ(): void {
		$this->boot_both();

		Asset_Loader::enqueue_style();
		call_user_func( [ $this->copy( 'Core\\Asset_Loader' ), 'enqueue_style' ] );

		$this->assertTrue( wp_style_is( 'alpha-blocks-editor-style', 'enqueued' ) );
		$this->assertTrue( wp_style_is( 'beta-blocks-editor-style', 'enqueued' ) );
	}

	/**
	 * Each copy's REST namespace serves only its own config.
	 */
	public function test_rest_namespaces_serve_only_their_own_config(): void {
		$this->boot_both();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init', $wp_rest_server );

		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/alpha-blocks/v1/config', $routes );
		$this->assertArrayHasKey( '/beta-blocks/v1/config', $routes );
		$this->assertArrayNotHasKey( '/cassette-cmf-block-builder/v1/config', $routes );

		$alpha = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/alpha-blocks/v1/config' ) );
		$beta  = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/beta-blocks/v1/config' ) );

		$this->assertSame( 200, $alpha->get_status() );
		$this->assertSame( 200, $beta->get_status() );
		$this->assertSame( [ 'alpha-test/card' ], array_keys( $alpha->get_data()['blocks'] ) );
		$this->assertSame( [ 'beta-test/note' ], array_keys( $beta->get_data()['blocks'] ) );
	}

	/**
	 * With bindings enabled in both, both sources exist under their own names.
	 */
	public function test_both_binding_sources_are_registered(): void {
		$this->boot_both( true );

		Binding_Source_Registrar::register_source();
		call_user_func( [ $this->copy( 'Binding\\Binding_Source_Registrar' ), 'register_source' ] );

		$this->assertNotNull( get_block_bindings_source( 'alpha/field' ) );
		$this->assertNotNull( get_block_bindings_source( 'beta/field' ) );
		$this->assertNull( get_block_bindings_source( 'cassette-cmf/field' ) );
	}

	/**
	 * The REST controller hooks of both copies are distinct callbacks.
	 */
	public function test_copies_are_separate_classes(): void {
		$this->assertNotSame( Config_Controller::class, $this->copy( 'Rest\\Config_Controller' ) );
		$this->assertTrue( class_exists( $this->copy( 'Rest\\Config_Controller' ) ) );
		$this->assertTrue( class_exists( 'CopyB\\Vendor\\PedalCMS\\CassetteCMF\\Field\\Field_Factory' ) );
	}
}
