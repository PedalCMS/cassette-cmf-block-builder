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
		wp_deregister_script( Asset_Loader::HANDLE );
		wp_deregister_style( Asset_Loader::STYLE_HANDLE );
	}

	/**
	 * Reset library singletons and the script registry after each test.
	 */
	public function tear_down(): void {
		wp_deregister_script( Asset_Loader::HANDLE );
		wp_deregister_style( Asset_Loader::STYLE_HANDLE );
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
		$this->assertTrue( isset( $scripts->registered[ Asset_Loader::HANDLE ] ), 'Editor script should be registered.' );

		$inline = $scripts->get_data( Asset_Loader::HANDLE, 'before' );
		$this->assertNotEmpty( $inline, 'Inline payload script should be present.' );

		$inline_js = implode( "\n", (array) $inline );
		$this->assertStringContainsString( 'window.cassetteCmfBlocks', $inline_js );
		// wp_json_encode() escapes "/" as "\/" by default (no JSON_UNESCAPED_SLASHES).
		$this->assertStringContainsString( 'acme-test\\/asset-loader', $inline_js );
	}

	/**
	 * enqueue() must actually enqueue (not just register) the script, so a
	 * consumer never has to remember to call wp_enqueue_script() themselves.
	 */
	public function test_enqueue_actually_enqueues(): void {
		Asset_Loader::enqueue();

		$this->assertTrue( wp_script_is( Asset_Loader::HANDLE, 'enqueued' ) );
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

		$this->assertTrue( wp_style_is( Asset_Loader::STYLE_HANDLE, 'enqueued' ) );

		$styles = wp_styles();
		$this->assertTrue( isset( $styles->registered[ Asset_Loader::STYLE_HANDLE ] ) );
		$this->assertStringEndsWith( 'build/editor.css', $styles->registered[ Asset_Loader::STYLE_HANDLE ]->src );
	}

	/**
	 * The committed build ships an "editor-rtl.css" twin alongside
	 * "editor.css"; enqueue_style() must tell WordPress core to swap to it
	 * automatically for an RTL locale.
	 */
	public function test_enqueue_style_adds_rtl_data_when_rtl_file_exists(): void {
		Asset_Loader::enqueue_style();

		$styles = wp_styles();
		$this->assertSame( 'replace', $styles->get_data( Asset_Loader::STYLE_HANDLE, 'rtl' ) );
	}
}
