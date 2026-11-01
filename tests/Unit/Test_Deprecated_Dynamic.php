<?php
/**
 * render.deprecated for dynamic blocks: the pure migration against the
 * shared fixture, and a server render of stored old-shape markup.
 *
 * @package PedalCMS\CassetteCMFBlocks\Tests\Unit
 */

use PedalCMS\CassetteCMFBlocks\CassetteCMFBlocks;
use PedalCMS\CassetteCMFBlocks\Core\Block_Manager;
use PedalCMS\CassetteCMFBlocks\Core\Editor_Payload;
use PedalCMS\CassetteCMFBlocks\Render\Deprecation_Migrator;
use PedalCMS\CassetteCMFBlocks\Schema\Control_Catalog;

require_once __DIR__ . '/CassetteCMFBlocks_UnitTestCase.php';

/**
 * Class Test_Deprecated_Dynamic
 */
class Test_Deprecated_Dynamic extends CassetteCMFBlocks_UnitTestCase {

	/**
	 * Block names registered by this test.
	 *
	 * @var string[]
	 */
	private const BLOCKS = [ 'acme-test/dyn-dep', 'acme-test/static-dep' ];

	/**
	 * Reset library state.
	 */
	public function set_up(): void {
		parent::set_up();
		Control_Catalog::reset();
		Block_Manager::reset();
	}

	/**
	 * Unregister test blocks and reset state.
	 */
	public function tear_down(): void {
		foreach ( self::BLOCKS as $name ) {
			if ( WP_Block_Type_Registry::get_instance()->is_registered( $name ) ) {
				unregister_block_type( $name );
			}
		}
		Block_Manager::reset();
		Control_Catalog::reset();
		parent::tear_down();
	}

	/**
	 * Every case in tests/fixtures/deprecations.json (also run by Jest).
	 */
	public function test_fixture_cases(): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$fixtures = json_decode( (string) file_get_contents( dirname( __DIR__ ) . '/fixtures/deprecations.json' ), true );
		$this->assertNotEmpty( $fixtures['cases'] );

		foreach ( $fixtures['cases'] as $case ) {
			$this->assertSame(
				$case['eligible'],
				Deprecation_Migrator::is_eligible( $case['entry'], $case['current'], $case['attributes'] ),
				'eligible: ' . $case['description']
			);

			$spec = is_array( $case['entry']['migrate'] ?? null ) ? $case['entry']['migrate'] : [];
			$this->assertEquals(
				$case['migrated'],
				Deprecation_Migrator::apply_declarative( $spec, $case['attributes'] ),
				'migrated: ' . $case['description']
			);
		}
	}

	/**
	 * migrate_php runs after the declarative part, only for eligible entries.
	 */
	public function test_migrate_php_runs_after_declarative_for_eligible_entries(): void {
		$entries = [
			[
				'attributes'  => [ 'title' => [ 'type' => 'string' ] ],
				'migrate'     => [ 'rename' => [ 'title' => 'heading' ] ],
				'migrate_php' => static function ( array $attributes ): array {
					$attributes['heading'] = strtoupper( $attributes['heading'] );
					return $attributes;
				},
			],
		];

		$this->assertSame(
			[ 'heading' => 'HI' ],
			Deprecation_Migrator::migrate( $entries, [ 'heading' ], [ 'title' => 'hi' ] )
		);
		$this->assertSame(
			[ 'heading' => 'hi' ],
			Deprecation_Migrator::migrate( $entries, [ 'heading' ], [ 'heading' => 'hi' ] ),
			'new-shape attributes are left alone'
		);
	}

	/**
	 * A string migrate is an editor-side name; without migrate_php nothing runs on the server.
	 */
	public function test_named_migration_needs_migrate_php_on_the_server(): void {
		$entries = [
			[
				'attributes' => [ 'title' => [ 'type' => 'string' ] ],
				'migrate'    => 'acme_named',
			],
		];

		$this->assertSame( [ 'title' => 'x' ], Deprecation_Migrator::migrate( $entries, [ 'heading' ], [ 'title' => 'x' ] ) );
	}

	/**
	 * Register the dynamic test block.
	 *
	 * @param array<int, array<string, mixed>> $deprecated Deprecated entries.
	 */
	private function register_dynamic( array $deprecated ): void {
		CassetteCMFBlocks::register_from_array(
			[
				'blocks' => [
					[
						'id'     => 'acme-test/dyn-dep',
						'fields' => [
							[
								'name' => 'heading',
								'type' => 'text',
							],
							[
								'name'    => 'tone',
								'type'    => 'select',
								'options' => [
									'warm' => 'Warm',
									'cool' => 'Cool',
								],
							],
						],
						'render' => [
							'markup'     => [
								'tag'      => 'div',
								'class'    => 'dep-{{ attributes.tone }}',
								'children' => [
									[
										'tag'  => 'h2',
										'text' => '{{ attributes.heading }}',
									],
								],
							],
							'deprecated' => $deprecated,
						],
					],
				],
			]
		);
	}

	/**
	 * Stored old-shape markup renders with the new output (declarative + migrate_php),
	 * and new-shape markup is untouched.
	 */
	public function test_stored_old_shape_markup_renders_the_new_output(): void {
		$this->register_dynamic(
			[
				[
					'attributes'  => [
						'title' => [ 'type' => 'string' ],
						'kind'  => [ 'type' => 'string' ],
					],
					'migrate'     => [
						'rename' => [
							'title' => 'heading',
							'kind'  => 'tone',
						],
						'map'    => [ 'tone' => [ 'hot' => 'warm' ] ],
					],
					'migrate_php' => static function ( array $attributes ): array {
						$attributes['heading'] .= '!';
						return $attributes;
					},
				],
			]
		);

		$old = do_blocks( '<!-- wp:acme-test/dyn-dep {"title":"Hello","kind":"hot"} /-->' );
		$new = do_blocks( '<!-- wp:acme-test/dyn-dep {"heading":"Hello","tone":"warm"} /-->' );

		$this->assertStringContainsString( 'dep-warm', $old );
		$this->assertStringContainsString( '<h2>Hello!</h2>', $old );
		$this->assertStringContainsString( '<h2>Hello</h2>', $new );
		$this->assertStringContainsString( 'dep-warm', $new );
	}

	/**
	 * Migration runs before sanitizing: public sanitize_attributes() sees the migrated shape.
	 */
	public function test_sanitize_attributes_migrates_first(): void {
		$this->register_dynamic(
			[
				[
					'attributes' => [ 'title' => [ 'type' => 'string' ] ],
					'migrate'    => [ 'rename' => [ 'title' => 'heading' ] ],
				],
			]
		);

		$definition = CassetteCMFBlocks::get_definition( 'acme-test/dyn-dep' );
		$result     = $definition->sanitize_attributes( [ 'title' => '<b>Hi</b>' ] );

		$this->assertSame( 'Hi', $result['heading'] );
		$this->assertArrayNotHasKey( 'title', $result );
	}

	/**
	 * Static mode keeps working: its deprecated entries (markup based) reach the editor payload
	 * unchanged and the server never migrates static blocks.
	 */
	public function test_static_mode_deprecated_is_unchanged(): void {
		CassetteCMFBlocks::register_from_array(
			[
				'blocks' => [
					[
						'id'     => 'acme-test/static-dep',
						'fields' => [
							[
								'name' => 'heading',
								'type' => 'text',
							],
						],
						'render' => [
							'mode'       => 'static',
							'markup'     => [ 'tag' => 'div' ],
							'deprecated' => [
								[
									'markup'  => [ 'tag' => 'span' ],
									'migrate' => 'acme_named',
								],
								[
									'attributes' => [ 'title' => [ 'type' => 'string' ] ],
									'migrate'    => [ 'rename' => [ 'title' => 'heading' ] ],
								],
							],
						],
					],
				],
			]
		);

		$payload = Editor_Payload::build( Block_Manager::init() );
		$entries = $payload['blocks']['acme-test/static-dep']['render']['deprecated'];

		$this->assertCount( 1, $entries, 'a static entry without markup is still skipped' );
		$this->assertSame( 'acme_named', $entries[0]['migrate'] );
	}

	/**
	 * The editor payload carries markup-less dynamic entries (declarative migrate as an object)
	 * and never the PHP callable.
	 */
	public function test_payload_carries_dynamic_entries_without_the_php_callable(): void {
		$this->register_dynamic(
			[
				[
					'attributes'  => [ 'title' => [ 'type' => 'string' ] ],
					'migrate'     => [ 'rename' => [ 'title' => 'heading' ] ],
					'migrate_php' => 'strtoupper',
				],
			]
		);

		$payload = Editor_Payload::build( Block_Manager::init() );
		$entries = $payload['blocks']['acme-test/dyn-dep']['render']['deprecated'];

		$this->assertCount( 1, $entries );
		$this->assertSame( [ 'rename' => [ 'title' => 'heading' ] ], $entries[0]['migrate'] );
		$this->assertArrayNotHasKey( 'migrate_php', $entries[0] );
		$this->assertNull( $entries[0]['markup'] );
	}
}
