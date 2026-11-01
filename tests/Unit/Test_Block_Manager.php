<?php
/**
 * Block_Manager test.
 *
 * @package PedalCMS\CassetteCMFBlocks\Tests\Unit
 */

use PedalCMS\CassetteCMFBlocks\CassetteCMFBlocks;
use PedalCMS\CassetteCMFBlocks\Core\Block_Manager;
use PedalCMS\CassetteCMFBlocks\Core\Identity;

require_once __DIR__ . '/CassetteCMFBlocks_UnitTestCase.php';

/**
 * Class Test_Block_Manager
 */
class Test_Block_Manager extends CassetteCMFBlocks_UnitTestCase {

	/**
	 * Reset the singleton before each test so tests don't leak state.
	 */
	public function set_up(): void {
		parent::set_up();
		Block_Manager::reset();
	}

	/**
	 * init() should always return the same instance.
	 */
	public function test_init_returns_singleton(): void {
		$this->assertSame( Block_Manager::init(), Block_Manager::init() );
	}

	/**
	 * register_from_array() should store a block config keyed by its id.
	 */
	public function test_register_from_array_stores_block_by_id(): void {
		$manager = CassetteCMFBlocks::register_from_array(
			[
				'blocks' => [
					[
						'id'   => 'acme/callout',
						'args' => [ 'title' => 'Callout' ],
					],
				],
			]
		);

		$this->assertTrue( $manager->has_block( 'acme/callout' ) );
		$this->assertSame(
			[ 'title' => 'Callout' ],
			$manager->get_block_config( 'acme/callout' )['args']
		);
	}

	/**
	 * A block entry without an "id" must throw, mirroring the parent's
	 * validation of required CPT/settings-page keys.
	 */
	public function test_register_from_array_requires_id(): void {
		$this->expectException( InvalidArgumentException::class );

		Block_Manager::init()->register_from_array(
			[ 'blocks' => [ [ 'args' => [] ] ] ]
		);
	}

	/**
	 * register_from_json() should accept a JSON file path.
	 */
	public function test_register_from_json_accepts_file_path(): void {
		$path = TESTS_PLUGIN_DIR . '/tests/fixtures/simple-block.json';

		$manager = Block_Manager::init()->register_from_json( $path );

		$this->assertTrue( $manager->has_block( 'acme/callout' ) );
	}

	/**
	 * register_from_json() should accept a raw JSON string.
	 */
	public function test_register_from_json_accepts_json_string(): void {
		$json = wp_json_encode( [ 'blocks' => [ [ 'id' => 'acme/inline' ] ] ] );

		$manager = Block_Manager::init()->register_from_json( $json );

		$this->assertTrue( $manager->has_block( 'acme/inline' ) );
	}

	/**
	 * Malformed JSON should throw rather than silently registering nothing.
	 */
	public function test_register_from_json_rejects_invalid_json(): void {
		$this->expectException( InvalidArgumentException::class );

		Block_Manager::init()->register_from_json( '{not valid json' );
	}

	/**
	 * The cassette_cmf_blocks_register_config filter should be able to mutate
	 * the whole config before registration, mirroring the parent's
	 * cassette_cmf_register_config filter.
	 */
	public function test_register_config_filter_can_mutate_config(): void {
		add_filter(
			'cassette_cmf_blocks_register_config',
			function ( $config ) {
				$config['blocks'][0]['id'] = 'acme/filtered';
				return $config;
			}
		);

		$manager = Block_Manager::init()->register_from_array(
			[ 'blocks' => [ [ 'id' => 'acme/original' ] ] ]
		);

		$this->assertFalse( $manager->has_block( 'acme/original' ) );
		$this->assertTrue( $manager->has_block( 'acme/filtered' ) );
	}

	/**
	 * The id-suffixed cassette_cmf_blocks_block_config_{id} filter should fire
	 * with slashes and hyphens normalized to underscores.
	 */
	public function test_block_config_id_filter_uses_normalized_tag(): void {
		$fired = false;

		add_filter(
			'cassette_cmf_blocks_block_config_acme_field_select',
			function ( $config ) use ( &$fired ) {
				$fired = true;
				return $config;
			}
		);

		Block_Manager::init()->register_from_array(
			[ 'blocks' => [ [ 'id' => 'acme/field-select' ] ] ]
		);

		$this->assertTrue( $fired );
	}

	/**
	 * boot() returns the manager and reads back its options.
	 */
	public function test_boot_stores_options(): void {
		$this->assertFalse( Block_Manager::get_option( 'bindings_source' ) );

		$manager = CassetteCMFBlocks::boot(
			[
				'prefix'          => 'test',
				'bindings_source' => true,
			]
		);

		$this->assertSame( $manager, Block_Manager::init() );
		$this->assertTrue( Block_Manager::get_option( 'bindings_source' ) );
		$this->assertSame( 'test', Block_Manager::get_option( 'prefix' ) );
		$this->assertNull( Block_Manager::get_option( 'nope' ) );
	}

	/**
	 * register_from_*() before boot() auto-boots with the defaults; a later
	 * boot() with different options is ignored with a notice.
	 */
	public function test_auto_boot_then_conflicting_boot_is_ignored(): void {
		CassetteCMFBlocks::register_from_array( [ 'blocks' => [] ] );
		$this->setExpectedIncorrectUsage( 'PedalCMS\\CassetteCMFBlocks\\Core\\Block_Manager::boot' );

		CassetteCMFBlocks::boot(
			[
				'prefix'          => 'test',
				'bindings_source' => true,
			]
		);

		$this->assertFalse( Block_Manager::get_option( 'bindings_source' ) );
	}

	/**
	 * A second boot() with the same options is silent; a different one is ignored.
	 */
	public function test_second_boot(): void {
		CassetteCMFBlocks::boot(
			[
				'prefix'          => 'test',
				'bindings_source' => true,
			]
		);
		CassetteCMFBlocks::boot(
			[
				'prefix'          => 'test',
				'bindings_source' => true,
			]
		);
		$this->assertTrue( Block_Manager::get_option( 'bindings_source' ) );

		$this->setExpectedIncorrectUsage( 'PedalCMS\\CassetteCMFBlocks\\Core\\Block_Manager::boot' );
		CassetteCMFBlocks::boot(
			[
				'prefix'          => 'test',
				'bindings_source' => false,
			]
		);
		$this->assertTrue( Block_Manager::get_option( 'bindings_source' ) );
	}

	/**
	 * Unknown option keys and wrongly-typed values trigger _doing_it_wrong() and are ignored.
	 */
	public function test_unknown_and_mistyped_options(): void {
		$this->setExpectedIncorrectUsage( 'PedalCMS\\CassetteCMFBlocks\\Core\\Block_Manager::resolve_options' );

		CassetteCMFBlocks::boot(
			[
				'prefix'          => 'acme',
				'bindings_source' => 'yes',
				'prefx'           => 'acme',
			]
		);

		$this->assertFalse( Block_Manager::get_option( 'bindings_source' ) );
		$this->assertSame( 'acme', Block_Manager::get_option( 'prefix' ) );
	}

	/**
	 * A missing prefix raises _doing_it_wrong() and uses the fallback prefix.
	 */
	public function test_missing_prefix_uses_the_fallback(): void {
		$this->setExpectedIncorrectUsage( 'PedalCMS\\CassetteCMFBlocks\\Core\\Block_Manager::resolve_options' );

		CassetteCMFBlocks::boot( [ 'bindings_source' => true ] );

		$this->assertSame( 'cassette-cmf', Identity::prefix() );
		$this->assertSame( 'cassette-cmf-blocks-editor', Identity::editor_script_handle() );
		$this->assertSame( 'cassette-cmf-blocks-editor-style', Identity::editor_style_handle() );
		$this->assertSame( 'cassette-cmf-blocks/v1', Identity::rest_namespace() );
		$this->assertSame( 'cassette-cmf/field', Identity::binding_source_name() );
	}

	/**
	 * Auto-boot (no boot() call at all) also raises the notice and falls back.
	 */
	public function test_auto_boot_without_prefix_uses_the_fallback(): void {
		$this->setExpectedIncorrectUsage( 'PedalCMS\\CassetteCMFBlocks\\Core\\Block_Manager::init' );

		CassetteCMFBlocks::register_from_array( [ 'blocks' => [] ] );

		$this->assertSame( 'cassette-cmf', Identity::prefix() );
	}

	/**
	 * Invalid prefixes raise _doing_it_wrong() and use the fallback; valid ones are kept.
	 *
	 * @dataProvider invalid_prefixes
	 *
	 * @param mixed $prefix Candidate.
	 */
	public function test_invalid_prefix_uses_the_fallback( $prefix ): void {
		$this->setExpectedIncorrectUsage( 'PedalCMS\\CassetteCMFBlocks\\Core\\Block_Manager::resolve_options' );

		CassetteCMFBlocks::boot( [ 'prefix' => $prefix ] );

		$this->assertSame( 'cassette-cmf', Identity::prefix() );
	}

	/**
	 * Invalid prefix values.
	 *
	 * @return array<string, array{0: mixed}>
	 */
	public function invalid_prefixes(): array {
		return [
			'too short'       => [ 'ab' ],
			'uppercase'       => [ 'Acme' ],
			'leading digit'   => [ '1acme' ],
			'trailing hyphen' => [ 'acme-' ],
			'underscore'      => [ 'acme_shop' ],
			'slash'           => [ 'acme/shop' ],
			'too long'        => [ str_repeat( 'a', 33 ) ],
			'not a string'    => [ 42 ],
			'empty'           => [ '' ],
		];
	}

	/**
	 * Valid prefixes at the edges of the pattern.
	 */
	public function test_valid_prefix_edges(): void {
		foreach ( [ 'abc', 'a-b', 'acme-shop-2', str_repeat( 'a', 32 ) ] as $prefix ) {
			Block_Manager::reset();
			CassetteCMFBlocks::boot( [ 'prefix' => $prefix ] );
			$this->assertSame( $prefix, Identity::prefix() );
		}
	}

	/**
	 * Every identifier is derived from the prefix in one place.
	 */
	public function test_identity_derives_every_name(): void {
		CassetteCMFBlocks::boot( [ 'prefix' => 'my-shop' ] );

		$this->assertSame( 'my-shop-blocks-editor', Identity::editor_script_handle() );
		$this->assertSame( 'my-shop-blocks-editor-style', Identity::editor_style_handle() );
		$this->assertSame( 'my-shop-blocks/v1', Identity::rest_namespace() );
		$this->assertSame( 'my-shop/field', Identity::binding_source_name() );
		$this->assertSame( 'cassetteCmfBlocks_myShop', Identity::js_global() );
		$this->assertSame( 'cassette_cmf_blocks_my_shop', Identity::payload_cache_group() );

		// A word that starts with a digit keeps its separator, so "a-1b" and "a1b" differ.
		Block_Manager::reset();
		CassetteCMFBlocks::boot( [ 'prefix' => 'acme-2go' ] );
		$this->assertSame( 'cassetteCmfBlocks_acme_2go', Identity::js_global() );
	}

	/**
	 * reset() also resets the boot state.
	 */
	public function test_reset_clears_boot_state(): void {
		CassetteCMFBlocks::boot(
			[
				'prefix'          => 'test',
				'bindings_source' => true,
			]
		);
		Block_Manager::reset();

		$this->assertFalse( Block_Manager::get_option( 'bindings_source' ) );

		CassetteCMFBlocks::boot(
			[
				'prefix'          => 'test',
				'bindings_source' => false,
			]
		);
		$this->assertFalse( Block_Manager::get_option( 'bindings_source' ) );
	}

	/**
	 * A field that binds to this library's source while it is disabled warns at compile time.
	 */
	public function test_disabled_binding_source_warns_at_compile_time(): void {
		$this->setExpectedIncorrectUsage( 'PedalCMS\\CassetteCMFBlocks\\Core\\Block_Manager::check_disabled_binding_source' );

		$manager = CassetteCMFBlocks::register_from_array( $this->bound_block_config() );
		$manager->compile_block( 'acme/bound' );
	}

	/**
	 * No warning when the source is enabled.
	 */
	public function test_enabled_binding_source_does_not_warn(): void {
		CassetteCMFBlocks::boot(
			[
				'prefix'          => 'test',
				'bindings_source' => true,
			]
		);
		$manager = CassetteCMFBlocks::register_from_array( $this->bound_block_config() );
		$manager->compile_block( 'acme/bound' );

		$this->assertTrue( $manager->has_block( 'acme/bound' ) );
	}

	/**
	 * A block with a (nested) field bound to this library's source.
	 *
	 * @return array<string, mixed>
	 */
	private function bound_block_config(): array {
		return [
			'blocks' => [
				[
					'id'     => 'acme/bound',
					'fields' => [
						[
							'name'   => 'settings',
							'type'   => 'panel',
							'fields' => [
								[
									'name'    => 'phone',
									'type'    => 'text',
									'binding' => [
										'source' => Identity::binding_source_name(),
										'args'   => [ 'field' => 'agency_phone' ],
									],
								],
							],
						],
					],
				],
			],
		];
	}
}
