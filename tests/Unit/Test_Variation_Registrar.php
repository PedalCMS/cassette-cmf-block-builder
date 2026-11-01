<?php
/**
 * Variation_Registrar test.
 *
 * @package PedalCMS\CassetteCMFBlocks\Tests\Unit
 */

use PedalCMS\CassetteCMFBlocks\Core\Block_Definition;
use PedalCMS\CassetteCMFBlocks\Core\Variation_Registrar;
use PedalCMS\CassetteCMFBlocks\Schema\Control_Catalog;

require_once __DIR__ . '/CassetteCMFBlocks_UnitTestCase.php';

/**
 * Class Test_Variation_Registrar
 */
class Test_Variation_Registrar extends CassetteCMFBlocks_UnitTestCase {

	/**
	 * Reset the catalog before each test.
	 */
	public function set_up(): void {
		parent::set_up();
		Control_Catalog::reset();
	}

	/**
	 * A valid variation (with "name" and "title") passes through unchanged.
	 */
	public function test_valid_variations_pass_through(): void {
		$variations = [
			[
				'name'       => 'outline',
				'title'      => 'Outline',
				'attributes' => [ 'style' => 'outline' ],
				'isActive'   => [ 'style' ],
			],
		];

		$this->assertSame(
			$variations,
			Variation_Registrar::normalize( $variations, 'acme/callout' )
		);
	}

	/**
	 * A variation missing "name" is dropped, with a _doing_it_wrong().
	 */
	public function test_a_variation_missing_name_is_dropped(): void {
		$this->setExpectedIncorrectUsage( 'PedalCMS\CassetteCMFBlocks\Core\Variation_Registrar::normalize' );

		$result = Variation_Registrar::normalize(
			[ [ 'title' => 'Outline' ] ],
			'acme/callout'
		);

		$this->assertSame( [], $result );
	}

	/**
	 * A variation missing "title" is dropped, with a _doing_it_wrong().
	 */
	public function test_a_variation_missing_title_is_dropped(): void {
		$this->setExpectedIncorrectUsage( 'PedalCMS\CassetteCMFBlocks\Core\Variation_Registrar::normalize' );

		$result = Variation_Registrar::normalize(
			[ [ 'name' => 'outline' ] ],
			'acme/callout'
		);

		$this->assertSame( [], $result );
	}

	/**
	 * A valid variation is kept alongside an invalid one that's dropped,
	 * rather than one bad entry discarding the whole list.
	 */
	public function test_a_valid_variation_survives_alongside_an_invalid_one(): void {
		$this->setExpectedIncorrectUsage( 'PedalCMS\CassetteCMFBlocks\Core\Variation_Registrar::normalize' );

		$result = Variation_Registrar::normalize(
			[
				[
					'name'  => 'outline',
					'title' => 'Outline',
				],
				[ 'title' => 'No name' ],
			],
			'acme/callout'
		);

		$this->assertSame(
			[
				[
					'name'  => 'outline',
					'title' => 'Outline',
				],
			],
			$result
		);
	}

	/**
	 * Block_Definition wires "args.variations" through Variation_Registrar
	 * automatically — an invalid variation there is dropped the same way.
	 */
	public function test_block_definition_normalizes_its_own_variations(): void {
		$this->setExpectedIncorrectUsage( 'PedalCMS\CassetteCMFBlocks\Core\Variation_Registrar::normalize' );

		$definition = new Block_Definition(
			[
				'id'   => 'acme/callout',
				'args' => [
					'variations' => [
						[
							'name'  => 'outline',
							'title' => 'Outline',
						],
						[ 'title' => 'No name' ],
					],
				],
			]
		);

		$this->assertSame(
			[
				[
					'name'  => 'outline',
					'title' => 'Outline',
				],
			],
			$definition->get_args()['variations']
		);
	}

	/**
	 * The cassette_cmf_blocks_block_variations filter can add/remove
	 * variations after validation.
	 */
	public function test_block_variations_filter(): void {
		add_filter(
			'cassette_cmf_blocks_block_variations',
			static function ( array $variations ): array {
				$variations[] = [
					'name'  => 'from-filter',
					'title' => 'From filter',
				];
				return $variations;
			}
		);

		$result = Variation_Registrar::normalize( [], 'acme/callout' );

		remove_all_filters( 'cassette_cmf_blocks_block_variations' );

		$this->assertSame(
			[
				[
					'name'  => 'from-filter',
					'title' => 'From filter',
				],
			],
			$result
		);
	}

	/**
	 * "scope" and "innerBlocks" reach the registered block type unchanged (the variation picker reads them).
	 */
	public function test_scope_and_inner_blocks_pass_through(): void {
		$variations = [
			[
				'name'        => 'two-columns',
				'title'       => 'Two columns',
				'scope'       => [ 'block' ],
				'attributes'  => [ 'columns' => 2 ],
				'innerBlocks' => [
					[ 'core/paragraph', [ 'content' => 'A' ] ],
					[ 'core/paragraph', [ 'content' => 'B' ] ],
				],
			],
		];

		$this->assertSame( $variations, Variation_Registrar::normalize( $variations, 'acme/cols' ) );
	}

	/**
	 * An unknown scope or a non-list innerBlocks is dropped with a notice, the variation is kept.
	 */
	public function test_malformed_scope_and_inner_blocks_are_dropped(): void {
		$this->setExpectedIncorrectUsage( Variation_Registrar::class );

		$result = Variation_Registrar::normalize(
			[
				[
					'name'        => 'x',
					'title'       => 'X',
					'scope'       => [ 'block', 'nowhere' ],
					'innerBlocks' => 'nope',
				],
			],
			'acme/cols'
		);

		$this->assertSame(
			[
				[
					'name'  => 'x',
					'title' => 'X',
				],
			],
			$result
		);
	}
}
