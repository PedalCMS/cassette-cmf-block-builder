<?php
/**
 * Pattern_Registrar test.
 *
 * @package Pedalcms\CassetteCmfBlocks\Tests\Unit
 */

use Pedalcms\CassetteCmfBlocks\CassetteCmfBlocks;
use Pedalcms\CassetteCmfBlocks\Core\Block_Manager;
use Pedalcms\CassetteCmfBlocks\Core\Pattern_Registrar;
use Pedalcms\CassetteCmfBlocks\Schema\Control_Catalog;

require_once __DIR__ . '/CassetteCmfBlocks_UnitTestCase.php';

/**
 * Class Test_Pattern_Registrar
 */
class Test_Pattern_Registrar extends CassetteCmfBlocks_UnitTestCase {

	/**
	 * Reset library singletons before each test.
	 */
	public function set_up(): void {
		parent::set_up();
		Block_Manager::reset();
		Control_Catalog::reset();
	}

	/**
	 * Unregister test patterns/categories and reset singletons after each test.
	 */
	public function tear_down(): void {
		if ( \WP_Block_Patterns_Registry::get_instance()->is_registered( 'acme/raw-pattern' ) ) {
			unregister_block_pattern( 'acme/raw-pattern' );
		}
		if ( \WP_Block_Patterns_Registry::get_instance()->is_registered( 'acme/blocks-pattern' ) ) {
			unregister_block_pattern( 'acme/blocks-pattern' );
		}
		if ( \WP_Block_Pattern_Categories_Registry::get_instance()->is_registered( 'acme-test-category' ) ) {
			unregister_block_pattern_category( 'acme-test-category' );
		}
		Block_Manager::reset();
		Control_Catalog::reset();
		parent::tear_down();
	}

	/**
	 * A pattern category registers via register_block_pattern_category().
	 */
	public function test_registers_a_pattern_category(): void {
		CassetteCmfBlocks::register_from_array(
			[
				'pattern_categories' => [
					[
						'slug'  => 'acme-test-category',
						'label' => 'Acme',
					],
				],
			]
		);

		Pattern_Registrar::register_patterns();

		$this->assertTrue(
			\WP_Block_Pattern_Categories_Registry::get_instance()->is_registered( 'acme-test-category' )
		);
	}

	/**
	 * A pattern with raw "content" registers that content verbatim.
	 */
	public function test_registers_a_pattern_from_raw_content(): void {
		CassetteCmfBlocks::register_from_array(
			[
				'patterns' => [
					[
						'slug'    => 'acme/raw-pattern',
						'title'   => 'Raw pattern',
						'content' => "<!-- wp:paragraph -->\n<p>Hi</p>\n<!-- /wp:paragraph -->",
					],
				],
			]
		);

		Pattern_Registrar::register_patterns();

		$registered = \WP_Block_Patterns_Registry::get_instance()->get_registered( 'acme/raw-pattern' );

		$this->assertNotNull( $registered );
		$this->assertStringContainsString( '<p>Hi</p>', $registered['content'] );
	}

	/**
	 * A pattern declaring a declarative "blocks" tree instead of raw
	 * "content" is serialized into the same WordPress comment-delimited
	 * HTML register_block_pattern() always accepted.
	 */
	public function test_registers_a_pattern_from_a_declarative_blocks_tree(): void {
		CassetteCmfBlocks::register_from_array(
			[
				'patterns' => [
					[
						'slug'   => 'acme/blocks-pattern',
						'title'  => 'Blocks pattern',
						'blocks' => [
							[
								'blockName' => 'core/paragraph',
								'attrs'     => [ 'content' => 'Hello there' ],
							],
						],
					],
				],
			]
		);

		Pattern_Registrar::register_patterns();

		$registered = \WP_Block_Patterns_Registry::get_instance()->get_registered( 'acme/blocks-pattern' );

		$this->assertNotNull( $registered );
		$this->assertStringContainsString( 'wp:paragraph', $registered['content'] );
		$this->assertStringContainsString( 'Hello there', $registered['content'] );
	}

	/**
	 * A pattern missing "slug" or "title" is skipped, with a _doing_it_wrong().
	 */
	public function test_a_pattern_missing_slug_is_skipped(): void {
		$this->setExpectedIncorrectUsage( 'Pedalcms\CassetteCmfBlocks\Core\Pattern_Registrar::register_one_pattern' );

		CassetteCmfBlocks::register_from_array(
			[
				'patterns' => [
					[
						'title'   => 'No slug',
						'content' => '<p>Hi</p>',
					],
				],
			]
		);

		Pattern_Registrar::register_patterns();

		// No exception, no fatal — just skipped. Nothing further to assert.
		$this->assertTrue( true );
	}

	/**
	 * A pattern with neither "content" nor "blocks" is skipped, with a
	 * _doing_it_wrong().
	 */
	public function test_a_pattern_with_no_content_source_is_skipped(): void {
		$this->setExpectedIncorrectUsage( 'Pedalcms\CassetteCmfBlocks\Core\Pattern_Registrar::register_one_pattern' );

		CassetteCmfBlocks::register_from_array(
			[
				'patterns' => [
					[
						'slug'  => 'acme/raw-pattern',
						'title' => 'No content',
					],
				],
			]
		);

		Pattern_Registrar::register_patterns();

		$this->assertFalse(
			\WP_Block_Patterns_Registry::get_instance()->is_registered( 'acme/raw-pattern' )
		);
	}
}
