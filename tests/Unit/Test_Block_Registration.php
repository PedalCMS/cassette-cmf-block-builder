<?php
/**
 * End-to-end block registration test — the milestone deliverable: a PHP
 * config produces registered block types with correct schemas.
 *
 * @package Pedalcms\CassetteCmfBlocks\Tests\Unit
 */

use Pedalcms\CassetteCmfBlocks\CassetteCmfBlocks;
use Pedalcms\CassetteCmfBlocks\Core\Block_Manager;
use Pedalcms\CassetteCmfBlocks\Schema\Control_Catalog;

require_once __DIR__ . '/CassetteCmfBlocks_UnitTestCase.php';

/**
 * Class Test_Block_Registration
 */
class Test_Block_Registration extends CassetteCmfBlocks_UnitTestCase {

	/**
	 * Block names this test registered, unregistered in tear_down() so
	 * later test files never see them as already-registered.
	 *
	 * @var string[]
	 */
	private array $registered_in_test = [];

	/**
	 * Reset library singletons before each test.
	 */
	public function set_up(): void {
		parent::set_up();
		Block_Manager::reset();
		Control_Catalog::reset();
	}

	/**
	 * Unregister any block types this test registered, and reset singletons.
	 */
	public function tear_down(): void {
		foreach ( $this->registered_in_test as $name ) {
			unregister_block_type( $name );
		}
		$this->registered_in_test = [];

		Block_Manager::reset();
		Control_Catalog::reset();
		parent::tear_down();
	}

	/**
	 * A block with a realistic mix of controls (text, toggle, select with
	 * options, a panel container, a conditional field) should register with
	 * WordPress and expose a correctly-shaped attribute schema.
	 */
	public function test_full_config_produces_a_registered_block_with_correct_schema(): void {
		$name                       = 'acme-test/full-registration';
		$this->registered_in_test[] = $name;

		CassetteCmfBlocks::register_from_array(
			[
				'blocks' => [
					[
						'id'     => $name,
						'args'   => [
							'title'    => 'Full Registration',
							'category' => 'common',
							'icon'     => 'megaphone',
							'parent'   => [ 'acme-test/wrapper' ],
							'supports' => [ 'anchor' => true ],
						],
						'fields' => [
							[
								'name'         => 'settings',
								'type'         => 'panel',
								'title'        => 'Settings',
								'initial_open' => true,
								'fields'       => [
									[
										'name'     => 'heading',
										'type'     => 'text',
										'required' => true,
									],
									[
										'name'    => 'is_open',
										'type'    => 'toggle',
										'default' => false,
									],
									[
										'name'    => 'tone',
										'type'    => 'select',
										'options' => [
											'neutral' => 'Neutral',
											'warm'    => 'Warm',
										],
									],
									[
										'name'        => 'reason',
										'type'        => 'text',
										'conditional' => [
											'rules' => [
												[
													'field'    => 'is_open',
													'operator' => '==',
													'value'    => true,
												],
											],
										],
									],
								],
							],
						],
					],
				],
			]
		);

		Block_Manager::init()->register_blocks();

		$registry = WP_Block_Type_Registry::get_instance();
		$this->assertTrue( $registry->is_registered( $name ) );

		$block_type = $registry->get_registered( $name );

		// apiVersion default (3) and dynamic-block html-support default (false).
		$this->assertSame( 3, $block_type->api_version );
		$this->assertFalse( $block_type->supports['html'] );

		// Non-attribute args passed through.
		$this->assertSame( 'Full Registration', $block_type->title );
		$this->assertSame( [ 'acme-test/wrapper' ], $block_type->parent );
		$this->assertTrue( $block_type->supports['anchor'] );

		// Every value-bearing leaf became an attribute with the right shape.
		$this->assertSame( 'string', $block_type->attributes['heading']['type'] );
		$this->assertSame( 'boolean', $block_type->attributes['is_open']['type'] );
		$this->assertFalse( $block_type->attributes['is_open']['default'] );
		$this->assertSame( [ 'neutral', 'warm' ], $block_type->attributes['tone']['enum'] );
		$this->assertArrayHasKey( 'reason', $block_type->attributes );

		// The panel container itself never became an attribute.
		$this->assertArrayNotHasKey( 'settings', $block_type->attributes );
	}

	/**
	 * The same config supplied as JSON, through the validating path, should
	 * produce an identical registration.
	 */
	public function test_json_config_produces_a_registered_block(): void {
		$name                       = 'acme-test/json-registration';
		$this->registered_in_test[] = $name;

		$json = wp_json_encode(
			[
				'blocks' => [
					[
						'id'     => $name,
						'args'   => [ 'title' => 'JSON Registration' ],
						'fields' => [
							[
								'name' => 'headline',
								'type' => 'text',
							],
						],
					],
				],
			]
		);

		CassetteCmfBlocks::register_from_json( $json );
		Block_Manager::init()->register_blocks();

		$this->assertTrue( WP_Block_Type_Registry::get_instance()->is_registered( $name ) );
	}

	/**
	 * register_blocks() must be idempotent: calling it twice must not
	 * attempt to re-register an already-registered block (WP_Block_Type_Registry
	 * refuses a second registration of the same name and _doing_it_wrong()s).
	 */
	public function test_register_blocks_is_idempotent(): void {
		$name                       = 'acme-test/idempotent';
		$this->registered_in_test[] = $name;

		CassetteCmfBlocks::register_from_array( [ 'blocks' => [ [ 'id' => $name ] ] ] );

		$manager = Block_Manager::init();
		$manager->register_blocks();
		$manager->register_blocks();

		$this->assertTrue( WP_Block_Type_Registry::get_instance()->is_registered( $name ) );
	}

	/**
	 * A block whose field config fails to compile (e.g. a duplicate field
	 * name) must be skipped with a _doing_it_wrong(), not fatal, and must
	 * not prevent other blocks in the same batch from registering.
	 */
	public function test_invalid_block_is_skipped_without_blocking_others(): void {
		$good_name                  = 'acme-test/valid-sibling';
		$bad_name                   = 'acme-test/invalid-sibling';
		$this->registered_in_test[] = $good_name;

		// Block_Manager::register_blocks() notices (both the "could not be
		// registered" one this test triggers, and the "registered late" one
		// every register_from_array() call triggers in this test harness)
		// are ignored by CassetteCmfBlocks_UnitTestCase::assert_post_conditions().
		CassetteCmfBlocks::register_from_array(
			[
				'blocks' => [
					[ 'id' => $good_name ],
					[
						'id'     => $bad_name,
						'fields' => [
							[
								'name' => 'x',
								'type' => 'text',
							],
							[
								'name' => 'x',
								'type' => 'textarea',
							],
						],
					],
				],
			]
		);

		Block_Manager::init()->register_blocks();

		$registry = WP_Block_Type_Registry::get_instance();
		$this->assertTrue( $registry->is_registered( $good_name ) );
		$this->assertFalse( $registry->is_registered( $bad_name ) );
	}
}
