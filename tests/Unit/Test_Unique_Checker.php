<?php
/**
 * Unique_Checker test — the shared fixture (also run by Jest), rule
 * normalization and value normalization.
 *
 * @package PedalCMS\CassetteCMFBlocks\Tests\Unit
 */

use PedalCMS\CassetteCMFBlocks\Render\Unique_Checker;

require_once __DIR__ . '/CassetteCMFBlocks_UnitTestCase.php';

/**
 * Class Test_Unique_Checker
 */
class Test_Unique_Checker extends CassetteCMFBlocks_UnitTestCase {

	/**
	 * Cases from tests/fixtures/unique.json.
	 *
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public function fixture_provider(): array {
		$data  = json_decode( (string) file_get_contents( dirname( __DIR__ ) . '/fixtures/unique.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$cases = [];

		foreach ( $data['cases'] as $fixture_case ) {
			$cases[ $fixture_case['name'] ] = [ $fixture_case ];
		}

		return $cases;
	}

	/**
	 * Every shared fixture case gives the same answer as the JS implementation.
	 *
	 * @dataProvider fixture_provider
	 * @param array<string, mixed> $fixture_case Fixture case.
	 */
	public function test_shared_fixture( array $fixture_case ): void {
		$actual = array_map(
			static function ( array $collision ): array {
				return [
					'value' => $collision['value'],
					'paths' => array_map(
						static function ( array $entry ): array {
							return $entry['path'];
						},
						$collision['entries']
					),
				];
			},
			Unique_Checker::find_collisions( $fixture_case['blocks'], $fixture_case['participants'], $fixture_case['rule'] )
		);

		$this->assertSame( $fixture_case['expected'], $actual, $fixture_case['name'] );
	}

	/**
	 * Entries carry the block name and the raw (untrimmed) value.
	 */
	public function test_entries_describe_the_colliding_blocks(): void {
		$collisions = Unique_Checker::find_collisions(
			[
				[
					'blockName'   => 'vendor/field',
					'attrs'       => [ 'name' => ' a ' ],
					'innerBlocks' => [],
				],
				[
					'blockName'   => 'vendor/field',
					'attrs'       => [ 'name' => 'a' ],
					'innerBlocks' => [],
				],
			],
			[ 'vendor/field' => [ 'attribute' => 'name' ] ],
			[ 'scope' => 'post' ]
		);

		$this->assertSame(
			[
				[
					'path'      => [ 0 ],
					'blockName' => 'vendor/field',
					'value'     => ' a ',
				],
				[
					'path'      => [ 1 ],
					'blockName' => 'vendor/field',
					'value'     => 'a',
				],
			],
			$collisions[0]['entries']
		);
	}

	/**
	 * Works on real parse_blocks() output.
	 */
	public function test_works_on_parse_blocks_output(): void {
		$blocks = parse_blocks(
			'<!-- wp:vendor/form --><!-- wp:vendor/field {"name":"email"} /--><!-- wp:vendor/field {"name":"email"} /--><!-- /wp:vendor/form -->'
			. '<!-- wp:vendor/form --><!-- wp:vendor/field {"name":"email"} /--><!-- /wp:vendor/form -->'
		);
		$blocks = array_values(
			array_filter(
				$blocks,
				static function ( array $block ): bool {
					return null !== $block['blockName'];
				}
			)
		);

		$collisions = Unique_Checker::find_collisions(
			$blocks,
			[ 'vendor/field' => [ 'attribute' => 'name' ] ],
			[
				'scope' => 'ancestor',
				'block' => 'vendor/form',
			]
		);

		$this->assertCount( 1, $collisions );
		$this->assertSame( [ 0, 0 ], $collisions[0]['entries'][0]['path'] );
		$this->assertSame( [ 0, 1 ], $collisions[0]['entries'][1]['path'] );
	}

	/**
	 * Value normalization: trim, format, non-strings.
	 */
	public function test_normalize_value(): void {
		$this->assertSame( 'Hi', Unique_Checker::normalize_value( '  Hi  ' ) );
		$this->assertSame( 'hello-world', Unique_Checker::normalize_value( ' Hello World ', 'slug' ) );
		$this->assertSame( 'ABC', Unique_Checker::normalize_value( 'abc', 'upper' ) );
		$this->assertSame( 'abc', Unique_Checker::normalize_value( 'ABC', 'lower' ) );
		$this->assertSame( '', Unique_Checker::normalize_value( 5 ) );
		$this->assertSame( '', Unique_Checker::normalize_value( null, 'slug' ) );
	}

	/**
	 * Valid "unique" settings normalize to the canonical rule.
	 */
	public function test_normalize_rule_accepts_valid_settings(): void {
		$this->assertNull( Unique_Checker::normalize_rule( false, 'f' ) );
		$this->assertNull( Unique_Checker::normalize_rule( null, 'f' ) );
		$this->assertSame(
			[
				'scope' => 'post',
				'key'   => 'f',
			],
			Unique_Checker::normalize_rule( true, 'f' )
		);
		$this->assertSame(
			[
				'scope' => 'ancestor',
				'block' => 'vendor/form',
				'key'   => 'f',
			],
			Unique_Checker::normalize_rule(
				[
					'scope' => 'ancestor',
					'block' => 'vendor/form',
				],
				'f'
			)
		);
		$this->assertSame(
			[
				'scope' => 'ancestor',
				'block' => 'vendor/form',
				'key'   => 'shared',
			],
			Unique_Checker::normalize_rule(
				[
					'block' => 'vendor/form',
					'key'   => 'shared',
				],
				'f'
			),
			'a "block" alone implies the ancestor scope; "key" overrides the field name'
		);
		$this->assertSame(
			[
				'scope' => 'post',
				'key'   => 'shared',
			],
			Unique_Checker::normalize_rule( [ 'key' => 'shared' ], 'f' )
		);
	}

	/**
	 * Malformed settings are rejected.
	 *
	 * @dataProvider invalid_rule_provider
	 * @param mixed $unique Invalid setting.
	 */
	public function test_normalize_rule_rejects_invalid_settings( $unique ): void {
		$this->expectException( InvalidArgumentException::class );

		Unique_Checker::normalize_rule( $unique, 'f' );
	}

	/**
	 * Invalid "unique" settings.
	 *
	 * @return array<string, array<int, mixed>>
	 */
	public function invalid_rule_provider(): array {
		return [
			'a string'                    => [ 'yes' ],
			'an unknown scope'            => [ [ 'scope' => 'site' ] ],
			'ancestor without a block'    => [ [ 'scope' => 'ancestor' ] ],
			'ancestor with a bad block'   => [
				[
					'scope' => 'ancestor',
					'block' => 'Not A Block',
				],
			],
			'a block with the post scope' => [
				[
					'scope' => 'post',
					'block' => 'vendor/form',
				],
			],
			'an empty key'                => [ [ 'key' => '' ] ],
			'a non-string key'            => [ [ 'key' => 5 ] ],
			'an unknown setting'          => [ [ 'scoped' => true ] ],
		];
	}
}
