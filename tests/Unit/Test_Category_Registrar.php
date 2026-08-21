<?php
/**
 * Category_Registrar test.
 *
 * @package Pedalcms\CassetteCmfBlocks\Tests\Unit
 */

use Pedalcms\CassetteCmfBlocks\CassetteCmfBlocks;
use Pedalcms\CassetteCmfBlocks\Core\Block_Manager;
use Pedalcms\CassetteCmfBlocks\Core\Category_Registrar;
use Pedalcms\CassetteCmfBlocks\Schema\Control_Catalog;

require_once __DIR__ . '/CassetteCmfBlocks_UnitTestCase.php';

/**
 * Class Test_Category_Registrar
 */
class Test_Category_Registrar extends CassetteCmfBlocks_UnitTestCase {

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
	 * A registered "block_categories" entry is appended to the existing list.
	 */
	public function test_filter_categories_appends_registered_categories(): void {
		CassetteCmfBlocks::register_from_array(
			[
				'block_categories' => [
					[
						'slug'  => 'acme-test-category',
						'title' => 'Acme',
					],
				],
			]
		);

		$result = Category_Registrar::filter_categories(
			[
				[
					'slug'  => 'text',
					'title' => 'Text',
				],
			]
		);

		$this->assertSame(
			[
				[
					'slug'  => 'text',
					'title' => 'Text',
				],
				[
					'slug'  => 'acme-test-category',
					'title' => 'Acme',
				],
			],
			$result
		);
	}

	/**
	 * A category with no "slug" is skipped — WordPress itself requires one.
	 */
	public function test_filter_categories_skips_entries_without_a_slug(): void {
		CassetteCmfBlocks::register_from_array( [ 'block_categories' => [ [ 'title' => 'No slug' ] ] ] );

		$result = Category_Registrar::filter_categories( [] );

		$this->assertSame( [], $result );
	}

	/**
	 * A slug that collides with an already-present category is skipped,
	 * never duplicated.
	 */
	public function test_filter_categories_skips_duplicate_slugs(): void {
		CassetteCmfBlocks::register_from_array(
			[
				'block_categories' => [
					[
						'slug'  => 'text',
						'title' => 'Duplicate',
					],
				],
			]
		);

		$result = Category_Registrar::filter_categories(
			[
				[
					'slug'  => 'text',
					'title' => 'Text',
				],
			]
		);

		$this->assertSame(
			[
				[
					'slug'  => 'text',
					'title' => 'Text',
				],
			],
			$result
		);
	}
}
