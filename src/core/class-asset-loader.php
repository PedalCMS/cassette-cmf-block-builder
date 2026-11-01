<?php
/**
 * Asset_Loader class for Cassette-CMF Blocks
 *
 * Registers and enqueues the library's one editor script bundle, and
 * inlines the Editor_Payload payload ahead of it. The script is
 * editor-only, hooked on enqueue_block_editor_assets. The stylesheet is
 * enqueued separately, on enqueue_block_assets — because the block
 * editor's canvas is an iframed document, a style enqueued only via
 * enqueue_block_editor_assets reaches the top-level admin page (the
 * inspector, the toolbar) but never the iframe a block's own markup
 * actually renders inside; enqueue_block_assets is what WordPress core's
 * iframe copies into that document (see
 * _wp_get_iframed_editor_assets() in wp-includes/block-editor.php). That
 * hook also fires on the front end, which is exactly right here: a
 * block's render.markup is the same tree in both places ("one declarative
 * markup tree drives three runtimes"), so its chrome classes need to
 * resolve identically wherever that markup renders.
 *
 * @package PedalCMS\CassetteCMFBlocks
 * @since 0.2.0
 */

namespace PedalCMS\CassetteCMFBlocks\Core;

use PedalCMS\CassetteCMFBlocks\Compat\Requirements;

/**
 * Class Asset_Loader
 */
class Asset_Loader {

	/**
	 * Register the enqueue_block_editor_assets and enqueue_block_assets
	 * hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'enqueue_block_editor_assets', [ self::class, 'enqueue' ] );
			add_action( 'enqueue_block_assets', [ self::class, 'enqueue_style' ] );
		}
	}

	/**
	 * Enqueue the editor bundle and inline the payload ahead of it.
	 *
	 * Guarded by Requirements::check() for the same reason
	 * Block_Manager::register_blocks() is: if the parent library or the
	 * running WordPress version doesn't meet this library's floor, nothing
	 * registered server-side, so a client bundle with no matching blocks
	 * would only add noise (and a client-side registerBlockType() call for
	 * a name WordPress never registered server-side produces its own
	 * confusing warnings).
	 *
	 * @return void
	 */
	public static function enqueue(): void {
		$requirements = Requirements::check();
		if ( ! $requirements['ok'] ) {
			return;
		}

		$asset_path = self::build_path() . '/editor.asset.php';
		if ( ! file_exists( $asset_path ) ) {
			return;
		}

		$asset = require $asset_path;

		$handle = Identity::editor_script_handle();

		// No "strategy" (defer/async) on purpose, only "in_footer": the payload
		// hand-over below (window.cassetteCmfBlocksBoot) is a one-shot global.
		// A blocking script runs right after its own inline "before" script, so
		// two copies of the bundle on one page can never interleave and read
		// each other's hand-over. Do not add a loading strategy here.
		wp_register_script(
			$handle,
			self::get_assets_url() . 'build/editor.js',
			$asset['dependencies'] ?? [],
			$asset['version'] ?? false,
			true
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( $handle, Identity::TEXT_DOMAIN, dirname( __DIR__, 2 ) . '/languages' );
		}

		$payload = Editor_Payload::build_cached( Block_Manager::init(), (string) ( $asset['version'] ?? '' ) );
		$global  = Identity::js_global();

		// First tell the bundle which global holds its payload, then publish
		// the payload there; the bundle reads and deletes the hand-over.
		// The stubs let a consumer script that depends on this handle (so it
		// runs before the bundle) call the extension points; see
		// extension_stubs().
		wp_add_inline_script(
			$handle,
			'window.' . Identity::JS_BOOT_KEY . ' = ' . wp_json_encode( $global ) . '; window[' . wp_json_encode( $global ) . '] = ' . wp_json_encode( $payload ) . '; ' . self::extension_stubs( $global ),
			'before'
		);

		wp_enqueue_script( $handle );
	}

	/**
	 * Payload key that holds the calls queued by the stubs until the bundle runs.
	 *
	 * @var string
	 */
	public const PENDING_KEY = 'pendingExtensions';

	/**
	 * JS extension points a consumer script may call on the payload global.
	 * Keep in sync with src/assets/src/editor/index.js.
	 *
	 * @var string[]
	 */
	public const EXTENSION_POINTS = [ 'registerControlType', 'registerMigration', 'registerIdInUseCheck' ];

	/**
	 * Inline JS that installs queueing stubs for the extension points on the
	 * payload global. A consumer script that lists the editor handle as a
	 * dependency runs after this inline script but before the bundle, when the
	 * real functions do not exist yet; the stubs record the calls and the
	 * bundle replays them (in order) before it registers any block, then
	 * replaces the stubs with the real functions.
	 *
	 * @param string $global_name Payload global name.
	 * @return string
	 */
	private static function extension_stubs( string $global_name ): string {
		return '(function(p){var q=p.' . self::PENDING_KEY . '=[];' . wp_json_encode( self::EXTENSION_POINTS ) . '.forEach(function(m){p[m]=function(){q.push([m,Array.prototype.slice.call(arguments)]);};});})(window[' . wp_json_encode( $global_name ) . ']);';
	}

	/**
	 * Enqueue the editor stylesheet — on enqueue_block_assets, not
	 * enqueue_block_editor_assets; see the class docblock for why. Runs on
	 * every front-end request too, guarded by the same Requirements::check()
	 * as enqueue(); a stylesheet with nothing to style is still harmless to
	 * skip outright rather than requesting an asset no page will use.
	 *
	 * @return void
	 */
	public static function enqueue_style(): void {
		$requirements = Requirements::check();
		if ( ! $requirements['ok'] ) {
			return;
		}

		$style_path = self::build_path() . '/editor.css';
		if ( ! file_exists( $style_path ) ) {
			return;
		}

		$style_handle = Identity::editor_style_handle();

		wp_enqueue_style(
			$style_handle,
			self::get_assets_url() . 'build/editor.css',
			[],
			(string) filemtime( $style_path )
		);

		// @wordpress/scripts builds an "editor-rtl.css" twin alongside
		// "editor.css" whenever the source stylesheet is present; this
		// tells WordPress core to swap to it automatically for an RTL
		// locale, the same as any block.json-declared editorStyle.
		if ( file_exists( self::build_path() . '/editor-rtl.css' ) ) {
			wp_style_add_data( $style_handle, 'rtl', 'replace' );
		}
	}

	/**
	 * Resolve the "assets/" directory's public URL.
	 *
	 * This library is normally consumed as a Composer dependency nested
	 * inside a host plugin's vendor/ directory, not as its own plugin — so
	 * naively string-replacing ABSPATH (the parent library's
	 * Abstract_Handler::get_assets_url() approach) breaks under a symlinked
	 * vendor/, a WP_CONTENT_DIR outside ABSPATH, or subdirectory multisite.
	 * Resolution order:
	 *   1. The CASSETTE_CMF_BLOCKS_ASSETS_URL constant, for a host that
	 *      copies/symlinks the built assets somewhere of its own choosing.
	 *      (A constant is global, so it applies to every copy of the library
	 *      on the site; use the filter below when two copies are loaded.)
	 *   2. The cassette_cmf_blocks_assets_url filter, for the same case
	 *      without requiring a constant defined before this file loads.
	 *   3. A realpath comparison against WP_CONTENT_DIR, mapped through
	 *      content_url() — correct for the overwhelmingly common case of a
	 *      normally-installed plugin/theme/mu-plugin, symlinked or not.
	 *   4. plugins_url() as a last resort, which performs a similar
	 *      comparison against WP_PLUGIN_DIR/WPMU_PLUGIN_DIR.
	 *
	 * @return string Trailing-slashed URL to this library's "assets/" directory.
	 */
	public static function get_assets_url(): string {
		if ( defined( 'CASSETTE_CMF_BLOCKS_ASSETS_URL' ) && CASSETTE_CMF_BLOCKS_ASSETS_URL ) {
			return trailingslashit( CASSETTE_CMF_BLOCKS_ASSETS_URL );
		}

		if ( function_exists( 'apply_filters' ) ) {
			/**
			 * Filters this library's assets/ directory URL, before the
			 * default realpath-based resolution runs.
			 *
			 * @param string $url    Empty string by default.
			 * @param string $prefix The boot prefix of the copy asking, so a site running two copies can answer for each.
			 */
			$filtered = (string) apply_filters( 'cassette_cmf_blocks_assets_url', '', Identity::prefix() );
			if ( '' !== $filtered ) {
				return trailingslashit( $filtered );
			}
		}

		$assets_dir  = realpath( self::assets_path() );
		$content_dir = defined( 'WP_CONTENT_DIR' ) ? realpath( WP_CONTENT_DIR ) : false;

		if ( $assets_dir && $content_dir && 0 === strpos( $assets_dir, $content_dir ) ) {
			$relative = ltrim( substr( $assets_dir, strlen( $content_dir ) ), '/\\' );

			return trailingslashit( content_url( $relative ) );
		}

		// dirname( __DIR__ ) is "src/" — plugins_url()'s $file argument must
		// sit in the same directory as "assets/" itself, not this class's
		// own "src/core/" subdirectory, or the relative path resolves one
		// level too deep.
		return trailingslashit( plugins_url( 'assets', dirname( __DIR__ ) . '/class-cassettecmfblocks.php' ) );
	}

	/**
	 * Filesystem path to this library's "assets/" directory.
	 *
	 * @return string
	 */
	private static function assets_path(): string {
		return dirname( __DIR__ ) . '/assets';
	}

	/**
	 * Filesystem path to the committed build output.
	 *
	 * @return string
	 */
	private static function build_path(): string {
		return self::assets_path() . '/build';
	}
}
