<?php
/**
 * Block_Manager test.
 *
 * @package PedalCMS\CassetteCMFBlocks\Tests\Unit
 */

use PedalCMS\CassetteCMFBlocks\CassetteCMFBlocks;
use PedalCMS\CassetteCMFBlocks\Core\Block_Manager;

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
}
