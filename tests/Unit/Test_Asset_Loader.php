<?php
/**
 * Asset_Loader test.
 *
 * @package PedalCMS\CassetteCMFBlocks\Tests\Unit
 */

use PedalCMS\CassetteCMFBlocks\CassetteCMFBlocks;
use PedalCMS\CassetteCMFBlocks\Core\Asset_Loader;
use PedalCMS\CassetteCMFBlocks\Core\Block_Manager;
use PedalCMS\CassetteCMFBlocks\Schema\Control_Catalog;
use PedalCMS\CassetteCMFBlocks\Core\Identity;

require_once __DIR__ . '/CassetteCMFBlocks_UnitTestCase.php';

/**
 * Class Test_Asset_Loader
 */
class Test_Asset_Loader extends CassetteCMFBlocks_UnitTestCase {

	/**
	 * Reset library singletons and the script registry before each test.
	 */
	public function set_up(): void {
		parent::set_up();
		Block_Manager::reset();
		Control_Catalog::reset();
		wp_deregister_script( Identity::editor_script_handle() );
		wp_deregister_style( Identity::editor_style_handle() );
	}

	/**
	 * Reset library singletons and the script registry after each test.
	 */
	public function tear_down(): void {
		wp_deregister_script( Identity::editor_script_handle() );
		wp_deregister_style( Identity::editor_style_handle() );
		remove_all_filters( 'cassette_cmf_blocks_assets_url' );
		Block_Manager::reset();
		Control_Catalog::reset();
		parent::tear_down();
	}

	/**
	 * In this repository's own layout — assets/ nested under WP_CONTENT_DIR
	 * (a normally-installed plugin) — get_assets_url() must resolve through
	 * content_url(), the common case the realpath comparison exists for.
	 */
	public function test_get_assets_url_resolves_under_wp_content(): void {
		$url = Asset_Loader::get_assets_url();

		$this->assertStringStartsWith( content_url(), $url );
		$this->assertStringEndsWith( 'cassette-cmf-block-builder/src/assets/', $url );
	}

	/**
	 * The cassette_cmf_blocks_assets_url filter must take priority over the
	 * realpath-based default resolution.
	 */
	public function test_assets_url_filter_takes_priority(): void {
		add_filter(
			'cassette_cmf_blocks_assets_url',
			static function () {
				return 'https://cdn.example.com/cassette-cmf-block-builder-assets';
			}
		);

		$this->assertSame( 'https://cdn.example.com/cassette-cmf-block-builder-assets/', Asset_Loader::get_assets_url() );
	}

	/**
	 * enqueue() must register the editor script, using editor.asset.php's
	 * own dependency list and version, and inline the payload ahead of it
	 * containing the registered block's name.
	 */
	public function test_enqueue_registers_script_and_inlines_payload(): void {
		CassetteCMFBlocks::register_from_array(
			[
				'blocks' => [
					[
						'id'   => 'acme-test/asset-loader',
						'args' => [ 'title' => 'Asset Loader Test' ],
					],
				],
			]
		);

		Asset_Loader::enqueue();

		$scripts = wp_scripts();
		$this->assertTrue( isset( $scripts->registered[ Identity::editor_script_handle() ] ), 'Editor script should be registered.' );

		$inline = $scripts->get_data( Identity::editor_script_handle(), 'before' );
		$this->assertNotEmpty( $inline, 'Inline payload script should be present.' );

		$inline_js = implode( "\n", (array) $inline );
		$this->assertStringContainsString( 'window.cassetteCmfBlocksBoot = "' . Identity::js_global() . '";', $inline_js );
		$this->assertStringContainsString( 'window["' . Identity::js_global() . '"] = {', $inline_js );
		// wp_json_encode() escapes "/" as "\/" by default (no JSON_UNESCAPED_SLASHES).
		$this->assertStringContainsString( 'acme-test\\/asset-loader', $inline_js );
	}

	/**
	 * The inline script installs queueing stubs for every JS extension point
	 * on the payload global, so a consumer script that runs before the bundle
	 * can call them.
	 */
	public function test_inline_script_installs_extension_point_stubs(): void {
		CassetteCMFBlocks::register_from_array(
			[
				'blocks' => [
					[
						'id'   => 'acme-test/stubs',
						'args' => [ 'title' => 'Stubs' ],
					],
				],
			]
		);

		Asset_Loader::enqueue();

		$inline_js = implode( "\n", (array) wp_scripts()->get_data( Identity::editor_script_handle(), 'before' ) );

		$this->assertSame(
			[ 'registerControlType', 'registerMigration', 'registerIdInUseCheck' ],
			Asset_Loader::EXTENSION_POINTS
		);

		foreach ( Asset_Loader::EXTENSION_POINTS as $point ) {
			$this->assertStringContainsString( '"' . $point . '"', $inline_js );
		}
		$this->assertStringContainsString( 'p.' . Asset_Loader::PENDING_KEY . '=[]', $inline_js );

		// Where Node is available, run the script and check what the stubs record.
		$node = trim( (string) shell_exec( 'command -v node 2>/dev/null' ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec
		if ( '' === $node ) {
			$this->markTestIncomplete( 'Node is not available; the stub script was only checked as text.' );
		}

		$script = 'var window = {};' . $inline_js
			. 'var g = window[' . wp_json_encode( Identity::js_global() ) . '];'
			. 'g.registerMigration("m", 1); g.registerControlType("t", 2);'
			. 'console.log(JSON.stringify(g.' . Asset_Loader::PENDING_KEY . '));';
		$file   = tempnam( sys_get_temp_dir(), 'ccb' );
		file_put_contents( $file, $script ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		$output = (string) shell_exec( escapeshellarg( $node ) . ' ' . escapeshellarg( $file ) . ' 2>&1' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec
		unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink

		$this->assertSame( '[["registerMigration",["m",1]],["registerControlType",["t",2]]]', trim( $output ) );
	}

	/**
	 * enqueue() must actually enqueue (not just register) the script, so a
	 * consumer never has to remember to call wp_enqueue_script() themselves.
	 */
	public function test_enqueue_actually_enqueues(): void {
		Asset_Loader::enqueue();

		$this->assertTrue( wp_script_is( Identity::editor_script_handle(), 'enqueued' ) );
	}

	/**
	 * enqueue_style() must register and enqueue the stylesheet on its own
	 * handle — deliberately hooked on enqueue_block_assets rather than
	 * enqueue_block_editor_assets (see Asset_Loader's class docblock): a
	 * style enqueued only via enqueue_block_editor_assets never reaches the
	 * block editor's iframed canvas, which is where a block's own
	 * render.markup — and therefore any chrome class it uses — actually
	 * renders.
	 */
	public function test_enqueue_style_registers_and_enqueues_stylesheet(): void {
		Asset_Loader::enqueue_style();

		$this->assertTrue( wp_style_is( Identity::editor_style_handle(), 'enqueued' ) );

		$styles = wp_styles();
		$this->assertTrue( isset( $styles->registered[ Identity::editor_style_handle() ] ) );
		$this->assertStringEndsWith( 'build/editor.css', $styles->registered[ Identity::editor_style_handle() ]->src );
	}

	/**
	 * The committed build ships an "editor-rtl.css" twin alongside
	 * "editor.css"; enqueue_style() must tell WordPress core to swap to it
	 * automatically for an RTL locale.
	 */
	public function test_enqueue_style_adds_rtl_data_when_rtl_file_exists(): void {
		Asset_Loader::enqueue_style();

		$styles = wp_styles();
		$this->assertSame( 'replace', $styles->get_data( Identity::editor_style_handle(), 'rtl' ) );
	}
}
