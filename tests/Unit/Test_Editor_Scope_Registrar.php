<?php
/**
 * Editor_Scope_Registrar test.
 *
 * @package Pedalcms\CassetteCmfBlocks\Tests\Unit
 */

use Pedalcms\CassetteCmfBlocks\CassetteCmfBlocks;
use Pedalcms\CassetteCmfBlocks\Core\Block_Manager;
use Pedalcms\CassetteCmfBlocks\Core\Editor_Scope_Registrar;
use Pedalcms\CassetteCmfBlocks\Schema\Control_Catalog;

require_once __DIR__ . '/CassetteCmfBlocks_UnitTestCase.php';

/**
 * Class Test_Editor_Scope_Registrar
 */
class Test_Editor_Scope_Registrar extends CassetteCmfBlocks_UnitTestCase {

	/**
	 * Reset library singletons before each test.
	 */
	public function set_up(): void {
		parent::set_up();
		Block_Manager::reset();
		Control_Catalog::reset();
	}

	/**
	 * Reset library singletons after each test.
	 */
	public function tear_down(): void {
		Block_Manager::reset();
		Control_Catalog::reset();
		parent::tear_down();
	}

	/**
	 * Build a WP_Block_Editor_Context for a post of the given post type.
	 *
	 * @param string $post_type Post type.
	 * @return WP_Block_Editor_Context
	 */
	private function make_context( string $post_type ): WP_Block_Editor_Context {
		$post = self::factory()->post->create_and_get( [ 'post_type' => $post_type ] );

		return new WP_Block_Editor_Context( [ 'post' => $post ] );
	}

	/**
	 * A post type not named by any editor_scope is left completely
	 * unrestricted.
	 */
	public function test_unscoped_post_type_is_unrestricted(): void {
		CassetteCmfBlocks::register_from_array(
			[
				'editor_scope' => [
					'post_types' => [ 'page' ],
					'allow_only' => [ 'core/paragraph' ],
				],
			]
		);

		$result = Editor_Scope_Registrar::filter_allowed_block_types( true, $this->make_context( 'post' ) );

		$this->assertTrue( $result );
	}

	/**
	 * A scoped post type is narrowed to only the glob-matching block names.
	 */
	public function test_scoped_post_type_is_narrowed_to_matching_patterns(): void {
		register_block_type( 'acme-test/scope-a', [] );
		register_block_type( 'acme-test/scope-b', [] );

		CassetteCmfBlocks::register_from_array(
			[
				'editor_scope' => [
					'post_types' => [ 'page' ],
					'allow_only' => [ 'acme-test/*', 'core/paragraph' ],
				],
			]
		);

		$result = Editor_Scope_Registrar::filter_allowed_block_types( true, $this->make_context( 'page' ) );

		unregister_block_type( 'acme-test/scope-a' );
		unregister_block_type( 'acme-test/scope-b' );

		$this->assertContains( 'acme-test/scope-a', $result );
		$this->assertContains( 'acme-test/scope-b', $result );
		$this->assertContains( 'core/paragraph', $result );
		$this->assertNotContains( 'core/heading', $result );
	}

	/**
	 * When $allowed_block_types is already an array (e.g. another plugin
	 * narrowed it first), scoping filters that array rather than starting
	 * over from every registered block.
	 */
	public function test_scoped_post_type_filters_an_existing_array(): void {
		CassetteCmfBlocks::register_from_array(
			[
				'editor_scope' => [
					'post_types' => [ 'page' ],
					'allow_only' => [ 'acme-test/*' ],
				],
			]
		);

		$result = Editor_Scope_Registrar::filter_allowed_block_types(
			[ 'acme-test/scope-a', 'core/paragraph' ],
			$this->make_context( 'page' )
		);

		$this->assertSame( [ 'acme-test/scope-a' ], $result );
	}

	/**
	 * No registered editor_scope leaves every post type unrestricted.
	 */
	public function test_no_scopes_leaves_allowed_block_types_untouched(): void {
		$result = Editor_Scope_Registrar::filter_allowed_block_types( true, $this->make_context( 'post' ) );

		$this->assertTrue( $result );
	}
}
