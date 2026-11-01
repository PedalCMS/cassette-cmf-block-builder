<?php
/**
 * First-class asset keys (args.editorScript ... args.viewScriptModule), the
 * args allow-list, the schema validator's view of both, and front-end
 * enqueueing of a view script module on a block theme and a classic theme.
 *
 * @package PedalCMS\CassetteCMFBlocks\Tests\Unit
 */

use PedalCMS\CassetteCMFBlocks\CassetteCMFBlocks;
use PedalCMS\CassetteCMFBlocks\Core\Block_Definition;
use PedalCMS\CassetteCMFBlocks\Core\Block_Manager;
use PedalCMS\CassetteCMFBlocks\Core\Identity;
use PedalCMS\CassetteCMFBlocks\Json\Block_Schema_Validator;
use PedalCMS\CassetteCMFBlocks\Schema\Control_Catalog;

require_once __DIR__ . '/CassetteCMFBlocks_UnitTestCase.php';

/**
 * Class Test_Block_Assets
 */
class Test_Block_Assets extends CassetteCMFBlocks_UnitTestCase {

	/**
	 * Block names registered by a test.
	 *
	 * @var string[]
	 */
	private array $registered = [];

	/**
	 * The stylesheet that was active before a theme test.
	 *
	 * @var string
	 */
	private string $original_theme = '';

	/**
	 * Script module IDs registered by a test. Each test uses its own IDs:
	 * newer WordPress remembers which modules it already printed, and
	 * dequeueing does not clear that.
	 *
	 * @var string[]
	 */
	private array $module_ids = [];

	/**
	 * Theme feature flags before a test (switch_theme() does not re-run
	 * after_setup_theme, so the test adds the defaults itself).
	 *
	 * @var array<string, mixed>
	 */
	private array $original_features = [];

	/**
	 * Reset library state.
	 */
	public function set_up(): void {
		parent::set_up();
		Control_Catalog::reset();
		Block_Manager::reset();
		$this->original_theme    = get_stylesheet();
		$this->original_features = isset( $GLOBALS['_wp_theme_features'] ) ? (array) $GLOBALS['_wp_theme_features'] : [];
	}

	/**
	 * Unregister test blocks, scripts and modules; restore the theme.
	 */
	public function tear_down(): void {
		foreach ( $this->registered as $name ) {
			if ( WP_Block_Type_Registry::get_instance()->is_registered( $name ) ) {
				unregister_block_type( $name );
			}
		}
		$this->registered = [];

		foreach ( $this->module_ids as $id ) {
			wp_dequeue_script_module( $id );
			wp_deregister_script_module( $id );
		}
		$this->module_ids = [];
		foreach ( [ 'bb-test-editor', 'bb-test-editor-2' ] as $handle ) {
			wp_dequeue_script( $handle );
			wp_deregister_script( $handle );
		}

		if ( get_stylesheet() !== $this->original_theme ) {
			switch_theme( $this->original_theme );
		}
		$this->unregister_theme_directory();
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restoring the snapshot taken in set_up().
		$GLOBALS['_wp_theme_features'] = $this->original_features;

		Block_Manager::reset();
		Control_Catalog::reset();
		parent::tear_down();
	}

	/**
	 * Register a one-block config.
	 *
	 * @param string               $id   Block name.
	 * @param array<string, mixed> $args Block "args".
	 * @return Block_Definition
	 */
	private function register( string $id, array $args ): Block_Definition {
		$this->registered[] = $id;

		CassetteCMFBlocks::register_from_array(
			[
				'blocks' => [
					[
						'id'     => $id,
						'args'   => $args,
						'fields' => [
							[
								'name' => 'label',
								'type' => 'text',
							],
						],
						'render' => [
							'markup' => [
								'tag'  => 'p',
								'text' => '{{ attributes.label }}',
							],
						],
					],
				],
			]
		);

		return CassetteCMFBlocks::get_definition( $id );
	}

	/**
	 * Key => [ args key, WP_Block_Type property ].
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function asset_key_provider(): array {
		return [
			'editorScript'     => [ 'editorScript', 'editor_script_handles' ],
			'editorStyle'      => [ 'editorStyle', 'editor_style_handles' ],
			'script'           => [ 'script', 'script_handles' ],
			'style'            => [ 'style', 'style_handles' ],
			'viewScript'       => [ 'viewScript', 'view_script_handles' ],
			'viewStyle'        => [ 'viewStyle', 'view_style_handles' ],
			'viewScriptModule' => [ 'viewScriptModule', 'view_script_module_ids' ],
		];
	}

	/**
	 * A string value reaches the WP_Block_Type as a one-item list.
	 *
	 * @dataProvider asset_key_provider
	 *
	 * @param string $key      The args key.
	 * @param string $property The WP_Block_Type property.
	 */
	public function test_string_form_reaches_the_block_type( string $key, string $property ): void {
		$this->register( 'acme-test/asset-string', [ $key => 'my-handle' ] );

		$type = WP_Block_Type_Registry::get_instance()->get_registered( 'acme-test/asset-string' );

		$this->assertSame( [ 'my-handle' ], $type->$property );
	}

	/**
	 * A list value reaches the WP_Block_Type unchanged (in order).
	 *
	 * @dataProvider asset_key_provider
	 *
	 * @param string $key      The args key.
	 * @param string $property The WP_Block_Type property.
	 */
	public function test_array_form_reaches_the_block_type( string $key, string $property ): void {
		$this->register( 'acme-test/asset-array', [ $key => [ 'first-handle', 'second-handle' ] ] );

		$type = WP_Block_Type_Registry::get_instance()->get_registered( 'acme-test/asset-array' );

		$this->assertSame( [ 'first-handle', 'second-handle' ], $type->$property );
	}

	/**
	 * Asset keys are server-side only: they are not shipped in the editor
	 * payload's registerBlockType() settings.
	 */
	public function test_asset_keys_are_not_in_the_client_args(): void {
		$definition = $this->register( 'acme-test/asset-client', [ 'viewScriptModule' => 'bb-test-view-module' ] );

		$this->assertArrayNotHasKey( 'viewScriptModule', $definition->get_args() );
		$this->assertArrayNotHasKey( 'view_script_module_ids', $definition->get_args() );
		$this->assertSame( [ 'bb-test-view-module' ], $definition->to_block_type_args()['view_script_module_ids'] );
	}

	/**
	 * Invalid asset values are dropped with a notice naming key and block.
	 *
	 * @dataProvider invalid_asset_values
	 *
	 * @param mixed $value Bad value.
	 */
	public function test_invalid_asset_value_is_dropped_with_a_notice( $value ): void {
		$this->setExpectedIncorrectUsage( 'PedalCMS\\CassetteCMFBlocks\\Core\\Block_Definition::__construct' );

		$definition = $this->register( 'acme-test/asset-invalid', [ 'viewScript' => $value ] );

		$this->assertArrayNotHasKey( 'view_script_handles', $definition->to_block_type_args() );
	}

	/**
	 * Values that are neither a handle nor a list of handles.
	 *
	 * @return array<string, array{0: mixed}>
	 */
	public function invalid_asset_values(): array {
		return [
			'empty string'  => [ '' ],
			'empty list'    => [ [] ],
			'number'        => [ 5 ],
			'bool'          => [ true ],
			'nested list'   => [ [ [ 'a' ] ] ],
			'list with int' => [ [ 'a', 3 ] ],
		];
	}

	/**
	 * Unknown args keys, including the old snake_case spellings that used to
	 * "work" by accident, get a notice and never reach register_block_type().
	 */
	public function test_unknown_args_keys_are_dropped_with_a_notice(): void {
		$this->setExpectedIncorrectUsage( 'PedalCMS\\CassetteCMFBlocks\\Core\\Block_Definition::__construct' );

		$definition = $this->register(
			'acme-test/unknown-args',
			[
				'title'                  => 'Unknown args',
				'view_script_module_ids' => [ 'bb-test-view-module' ],
				'uses_context'           => [ 'postId' ],
				'somethingElse'          => true,
			]
		);

		$type = WP_Block_Type_Registry::get_instance()->get_registered( 'acme-test/unknown-args' );

		$this->assertSame( 'Unknown args', $type->title );
		$this->assertEmpty( $type->view_script_module_ids );
		$this->assertSame( [], $type->uses_context );
		$this->assertArrayNotHasKey( 'view_script_module_ids', $definition->to_block_type_args() );
		$this->assertArrayNotHasKey( 'somethingElse', $definition->get_args() );
	}

	/**
	 * The notice names the offending key and the block.
	 */
	public function test_unknown_key_notice_names_key_and_block(): void {
		$messages = [];
		add_action(
			'doing_it_wrong_run',
			static function ( $function_name, $message ) use ( &$messages ) {
				$messages[] = (string) $message;
			},
			10,
			2
		);
		$this->setExpectedIncorrectUsage( 'PedalCMS\\CassetteCMFBlocks\\Core\\Block_Definition::__construct' );

		$this->register( 'acme-test/notice-names', [ 'bogusKey' => 1 ] );

		$joined = implode( "\n", $messages );
		$this->assertStringContainsString( 'bogusKey', $joined );
		$this->assertStringContainsString( 'acme-test/notice-names', $joined );
	}

	/**
	 * Every key BB documents or maps keeps working through the allow-list.
	 */
	public function test_every_documented_args_key_is_accepted(): void {
		$definition = $this->register(
			'acme-test/allowed-args',
			[
				'apiVersion'         => 3,
				'title'              => 'Allowed',
				'category'           => 'widgets',
				'description'        => 'Desc',
				'icon'               => 'megaphone',
				'keywords'           => [ 'one' ],
				'textdomain'         => 'acme',
				'parent'             => [ 'acme-test/wrapper' ],
				'ancestor'           => [ 'acme-test/wrapper' ],
				'allowedBlocks'      => [ 'core/paragraph' ],
				'blockHooks'         => [ 'core/post-content' => 'after' ],
				'providesContext'    => [ 'acme-test/key' => 'label' ],
				'usesContext'        => [ 'postId' ],
				'selectors'          => [ 'root' => '.wp-block-acme' ],
				'supports'           => [ 'anchor' => true ],
				'styles'             => [
					[
						'name'  => 'fancy',
						'label' => 'Fancy',
					],
				],
				'variations'         => [
					[
						'name'  => 'v1',
						'title' => 'V1',
					],
				],
				'variation_callback' => '__return_empty_array',
				'example'            => [ 'attributes' => [ 'label' => 'x' ] ],
			]
		);

		$sent = array_keys( $definition->get_args() );
		foreach ( Block_Definition::allowed_arg_keys() as $key ) {
			if ( isset( Block_Definition::ASSET_KEYS[ $key ] ) ) {
				continue;
			}
			$this->assertContains( $key, $sent, "args.$key should survive the allow-list" );
		}

		$type = WP_Block_Type_Registry::get_instance()->get_registered( 'acme-test/allowed-args' );
		$this->assertSame( [ 'core/paragraph' ], $type->allowed_blocks );
		$this->assertSame( [ 'core/post-content' => 'after' ], array_intersect_key( [ 'core/post-content' => 'after' ], (array) $type->block_hooks ) );
		$this->assertSame( 'Allowed', $type->title );
	}

	/**
	 * The library's own editor script/style stay separate from consumer
	 * editorScript / editorStyle, and both are enqueued in the editor.
	 */
	public function test_library_editor_handle_and_consumer_editor_script_coexist(): void {
		wp_register_script( 'bb-test-editor', 'https://example.org/editor.js', [], '1.0', true );
		wp_register_script( 'bb-test-editor-2', 'https://example.org/editor-2.js', [], '1.0', true );

		CassetteCMFBlocks::boot( [ 'prefix' => 'asset-test' ] );
		$this->register( 'acme-test/editor-both', [ 'editorScript' => [ 'bb-test-editor', 'bb-test-editor-2' ] ] );

		$type = WP_Block_Type_Registry::get_instance()->get_registered( 'acme-test/editor-both' );
		$this->assertSame( [ 'bb-test-editor', 'bb-test-editor-2' ], $type->editor_script_handles );
		$this->assertNotContains( Identity::editor_script_handle(), $type->editor_script_handles );
		$this->assertSame( 'asset-test-blocks-editor', Identity::editor_script_handle() );

		set_current_screen( 'post' );
		get_current_screen()->is_block_editor( true );
		do_action( 'enqueue_block_editor_assets' );

		$this->assertTrue( wp_script_is( 'asset-test-blocks-editor', 'enqueued' ), 'library editor script' );
		$this->assertTrue( wp_script_is( 'bb-test-editor', 'enqueued' ), 'consumer editor script' );
		$this->assertTrue( wp_script_is( 'bb-test-editor-2', 'enqueued' ), 'second consumer editor script' );

		wp_dequeue_script( 'asset-test-blocks-editor' );
		wp_deregister_script( 'asset-test-blocks-editor' );
		set_current_screen( 'front' );
	}

	/**
	 * The validator accepts the string and list shapes and rejects the rest.
	 */
	public function test_validator_accepts_and_rejects_asset_shapes(): void {
		$validator = new Block_Schema_Validator();

		$valid = [
			'blocks' => [
				[
					'id'   => 'acme-test/valid',
					'args' => [
						'editorScript'     => 'a',
						'editorStyle'      => [ 'a', 'b' ],
						'script'           => 'a',
						'style'            => 'a',
						'viewScript'       => [ 'a' ],
						'viewStyle'        => 'a',
						'viewScriptModule' => [ 'a', 'b' ],
					],
				],
			],
		];
		$this->assertTrue( $validator->validate( $valid ), $validator->get_error_message() );

		foreach ( [ '', [], 5, [ 'a', 3 ], [ 'k' => 'a' ], [ [ 'a' ] ], true ] as $bad ) {
			$config                                   = $valid;
			$config['blocks'][0]['args']['viewStyle'] = $bad;

			$this->assertFalse( $validator->validate( $config ), 'should reject ' . wp_json_encode( $bad ) );
			$this->assertStringContainsString( 'args.viewStyle', $validator->get_error_message() );
		}
	}

	/**
	 * The JSON schema file describes the same asset keys.
	 */
	public function test_block_schema_json_lists_the_asset_keys(): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
		$schema = json_decode( (string) file_get_contents( dirname( __DIR__, 2 ) . '/block-schema.json' ), true );
		$props  = $schema['definitions']['block']['properties']['args']['properties'] ?? [];

		$this->assertSame( array_keys( Block_Definition::ASSET_KEYS ), array_keys( $props ) );
		$this->assertArrayHasKey( 'assetHandles', $schema['definitions'] );
	}

	/**
	 * Point WordPress at the fixture themes directory.
	 *
	 * @return string The fixtures directory.
	 */
	private function register_theme_directory(): string {
		$dir = dirname( __DIR__ ) . '/fixtures/themes';
		register_theme_directory( $dir );
		wp_clean_themes_cache();

		return $dir;
	}

	/**
	 * Remove the fixture theme directory registration.
	 *
	 * @return void
	 */
	private function unregister_theme_directory(): void {
		global $wp_theme_directories;

		$dir = dirname( __DIR__ ) . '/fixtures/themes';
		if ( is_array( $wp_theme_directories ) ) {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Undoing this test's register_theme_directory().
			$wp_theme_directories = array_values( array_diff( $wp_theme_directories, [ $dir ] ) );
		}
		wp_clean_themes_cache();
	}

	/**
	 * Render a singular view through the theme's real template, capturing
	 * the head and footer output (which is where modules are printed).
	 *
	 * @param int $post_id Post to show.
	 * @return string
	 */
	private function render_front_end( int $post_id ): string {
		global $_wp_current_template_content, $_wp_current_template_id;

		$_wp_current_template_content = null;
		$_wp_current_template_id      = null;

		$this->go_to( get_permalink( $post_id ) );

		// The same fallback chain template-loader.php uses for a singular view.
		$template = get_single_template();
		if ( ! $template ) {
			$template = get_index_template();
		}
		$this->assertNotEmpty( $template, 'The theme should resolve a template.' );
		if ( wp_is_block_theme() ) {
			$this->assertStringEndsWith( 'template-canvas.php', $template, 'A block theme renders through the template canvas.' );
		}

		ob_start();
		include $template;

		return (string) ob_get_clean();
	}

	/**
	 * Register the test modules and a block that lists one in viewScriptModule.
	 *
	 * @param string $view_id  ID of the module the block lists.
	 * @param string $other_id ID of a registered module the block does not list.
	 * @return void
	 */
	private function register_module_block( string $view_id, string $other_id ): void {
		$this->module_ids = [ $view_id, $other_id ];

		wp_register_script_module( $view_id, 'https://example.org/view-module.js', [], '1.0' );
		wp_register_script_module( $other_id, 'https://example.org/other-module.js', [], '1.0' );

		$this->register( 'acme-test/module-block', [ 'viewScriptModule' => $view_id ] );
	}

	/**
	 * Assert the module shows up only on a page that contains the block.
	 *
	 * @param string $theme Theme directory name.
	 * @return void
	 */
	private function assert_module_only_with_block( string $theme ): void {
		$this->register_theme_directory();
		switch_theme( $theme );
		$this->assertSame( $theme, get_stylesheet() );
		// What after_setup_theme would have done on a real request (block-templates support for block themes).
		_add_default_theme_supports();
		wp_enable_block_templates();

		$view_id  = 'bb-test-view-' . $theme;
		$other_id = 'bb-test-other-' . $theme;
		$this->register_module_block( $view_id, $other_id );

		$plain = self::factory()->post->create(
			[
				'post_title'   => 'Without',
				'post_content' => '<!-- wp:paragraph --><p>No custom block here.</p><!-- /wp:paragraph -->',
			]
		);
		$with  = self::factory()->post->create(
			[
				'post_title'   => 'With',
				'post_content' => '<!-- wp:acme-test/module-block {"label":"Hello"} /-->',
			]
		);

		$html = $this->render_front_end( $plain );
		$this->assertStringContainsString( 'No custom block here.', $html );
		$this->assertStringNotContainsString( $view_id, $html, 'No module without the block.' );

		wp_dequeue_script_module( $view_id );

		$html = $this->render_front_end( $with );
		$this->assertStringContainsString( 'Hello', $html );
		$this->assertStringContainsString( 'id="' . $view_id . '-js-module"', $html, 'The module is printed with the block.' );
		$this->assertStringContainsString( 'https://example.org/view-module.js', $html );
		$this->assertStringNotContainsString( $other_id, $html, 'Only the listed module.' );
	}

	/**
	 * Block theme: the view module is enqueued only when the block renders.
	 */
	public function test_view_script_module_only_on_pages_with_the_block_block_theme(): void {
		$this->assert_module_only_with_block( 'bb-test-block-theme' );
		$this->assertTrue( wp_is_block_theme() );
	}

	/**
	 * Classic theme: the view module is enqueued only when the block renders.
	 */
	public function test_view_script_module_only_on_pages_with_the_block_classic_theme(): void {
		$this->assert_module_only_with_block( 'bb-test-classic-theme' );
		$this->assertFalse( wp_is_block_theme() );
	}
}
