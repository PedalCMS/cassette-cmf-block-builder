<?php
/**
 * Settings_Binding_Source test (unit level: args, context, rule order).
 *
 * The end-to-end disclosure cases through real block rendering live in
 * Test_Bindings_Disclosure.
 *
 * @package PedalCMS\CassetteCMFBlocks\Tests\Unit
 */

use PedalCMS\CassetteCMFBlocks\Binding\Settings_Binding_Source;

require_once __DIR__ . '/Bindings_UnitTestCase.php';

/**
 * Class Test_Settings_Binding_Source
 */
class Test_Settings_Binding_Source extends Bindings_UnitTestCase {

	/**
	 * Build a bare WP_Block instance with the given block context.
	 *
	 * WP_Block only exposes the subset of $available_context its own block
	 * type actually declares via "usesContext" — a real consuming block
	 * must declare "usesContext" => [ 'postId' ] itself for the postId
	 * fallback to ever see anything, exactly as this fixture does.
	 *
	 * @param array<string, mixed> $context Block context, e.g. [ 'postId' => 123 ].
	 * @return WP_Block
	 */
	private function make_block( array $context = [] ): WP_Block {
		$name = 'acme-test/binding-source-block';
		if ( ! WP_Block_Type_Registry::get_instance()->is_registered( $name ) ) {
			register_block_type( $name, [ 'uses_context' => [ 'postId' ] ] );
		}

		return new WP_Block(
			[
				'blockName' => $name,
				'attrs'     => [],
			],
			$context
		);
	}

	/**
	 * Unregister the test block type after each test.
	 */
	public function tear_down(): void {
		$name = 'acme-test/binding-source-block';
		if ( WP_Block_Type_Registry::get_instance()->is_registered( $name ) ) {
			unregister_block_type( $name );
		}
		parent::tear_down();
	}

	/**
	 * Register a marked field and a public post carrying a value.
	 *
	 * @return int The post ID.
	 */
	private function marked_post(): int {
		$this->register_post_fields(
			[
				[
					'name'     => 'agency_phone',
					'type'     => 'text',
					'bindings' => true,
				],
			]
		);
		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		update_post_meta( $post_id, 'agency_phone', '555-0100' );

		return $post_id;
	}

	/**
	 * A missing "field" resolves to null.
	 */
	public function test_missing_field_resolves_to_null(): void {
		$post_id = $this->marked_post();

		$this->assertNull( Settings_Binding_Source::get_value( [ 'context' => $post_id ], $this->make_block() ) );
		$this->assertNull( Settings_Binding_Source::get_value( [ 'field' => '' ], $this->make_block( [ 'postId' => $post_id ] ) ) );
		$this->assertNull( Settings_Binding_Source::get_value( [ 'field' => [ 'agency_phone' ] ], $this->make_block( [ 'postId' => $post_id ] ) ) );
	}

	/**
	 * A 'post' binding with no explicit "context" falls back to the block's own "postId" context.
	 */
	public function test_post_binding_falls_back_to_block_context_post_id(): void {
		$post_id = $this->marked_post();

		$this->assertSame(
			'555-0100',
			Settings_Binding_Source::get_value( [ 'field' => 'agency_phone' ], $this->make_block( [ 'postId' => $post_id ] ) )
		);
	}

	/**
	 * An explicit "context" overrides the block's own context.
	 */
	public function test_explicit_context_overrides_block_context(): void {
		$post_id = $this->marked_post();

		$this->assertSame(
			'555-0100',
			Settings_Binding_Source::get_value(
				[
					'field'   => 'agency_phone',
					'context' => $post_id,
				],
				$this->make_block( [ 'postId' => 999999 ] )
			)
		);
		$this->assertSame(
			'555-0100',
			Settings_Binding_Source::get_value(
				[
					'field'   => 'agency_phone',
					'context' => (string) $post_id,
				],
				$this->make_block()
			)
		);
	}

	/**
	 * Neither an explicit context nor a postId context: null.
	 */
	public function test_post_binding_with_no_context_resolves_to_null(): void {
		$this->marked_post();

		$this->assertNull( Settings_Binding_Source::get_value( [ 'field' => 'agency_phone' ], $this->make_block() ) );
	}

	/**
	 * Malformed contexts and context types resolve to null.
	 */
	public function test_malformed_context_and_type_resolve_to_null(): void {
		$post_id = $this->marked_post();
		$block   = $this->make_block( [ 'postId' => $post_id ] );

		foreach ( [ -5, 0, '1e3', '12abc', 'abc', true, [ $post_id ] ] as $context ) {
			$this->assertNull(
				Settings_Binding_Source::get_value(
					[
						'field'   => 'agency_phone',
						'context' => $context,
					],
					$block
				),
				'context ' . wp_json_encode( $context )
			);
		}

		foreach ( [ 'user', 'comment', '', 5, [ 'post' ] ] as $type ) {
			$this->assertNull(
				Settings_Binding_Source::get_value(
					[
						'field'        => 'agency_phone',
						'context_type' => $type,
					],
					$block
				),
				'context_type ' . wp_json_encode( $type )
			);
		}
	}

	/**
	 * 'term'/'settings' bindings require an explicit "context".
	 */
	public function test_term_and_settings_bindings_need_an_explicit_context(): void {
		$block = $this->make_block( [ 'postId' => $this->marked_post() ] );

		foreach ( [ 'term', 'settings' ] as $type ) {
			$this->assertNull(
				Settings_Binding_Source::get_value(
					[
						'field'        => 'agency_phone',
						'context_type' => $type,
					],
					$block
				)
			);
		}
	}

	/**
	 * A marked term-meta field resolves; protected term meta never does.
	 */
	public function test_term_binding(): void {
		$this->register_taxonomy_fields(
			'category',
			[
				[
					'name'     => 'color',
					'type'     => 'text',
					'bindings' => true,
				],
				[
					'name'     => '_hidden_color',
					'type'     => 'text',
					'bindings' => true,
				],
			]
		);
		$term_id = self::factory()->category->create();
		update_term_meta( $term_id, 'color', 'teal' );
		update_term_meta( $term_id, '_hidden_color', 'LEAK' );

		$args = [
			'context_type' => 'term',
			'context'      => $term_id,
		];

		$this->assertSame( 'teal', Settings_Binding_Source::get_value( $args + [ 'field' => 'color' ], $this->make_block() ) );
		$this->assertNull( Settings_Binding_Source::get_value( $args + [ 'field' => '_hidden_color' ], $this->make_block() ) );
	}

	/**
	 * A settings binding resolves through CassetteCMF::get_field() once the field is marked.
	 */
	public function test_settings_binding_resolves_via_cassette_cmf(): void {
		$this->register_settings_page(
			'agency-settings',
			[
				[
					'name'     => 'theme_color',
					'type'     => 'text',
					'bindings' => true,
				],
			]
		);
		update_option( 'agency-settings_theme_color', '#336699' );

		$this->assertSame(
			'#336699',
			Settings_Binding_Source::get_value(
				[
					'field'        => 'theme_color',
					'context'      => 'agency-settings',
					'context_type' => 'settings',
				],
				$this->make_block()
			)
		);
	}

	/**
	 * An empty value resolves to null, never '', so core keeps the block's content.
	 */
	public function test_empty_and_unresolvable_values_are_null_not_empty_string(): void {
		$post_id = $this->marked_post();
		update_post_meta( $post_id, 'agency_phone', '' );

		$this->assertNull( Settings_Binding_Source::get_value( [ 'field' => 'agency_phone' ], $this->make_block( [ 'postId' => $post_id ] ) ) );
		$this->assertNull( Settings_Binding_Source::get_value( [ 'field' => 'nonexistent_field' ], $this->make_block( [ 'postId' => $post_id ] ) ) );
	}

	/**
	 * A parent library without is_field_public() resolves nothing at all.
	 *
	 * The guard is a protected static seam (cmf_supports_visibility()), so an
	 * older parent library is simulated with a subclass.
	 */
	public function test_older_parent_library_resolves_nothing(): void {
		$post_id = $this->marked_post();
		$old_cmf = new class() extends Settings_Binding_Source {
			/**
			 * Pretend the parent library has no is_field_public().
			 *
			 * @return bool
			 */
			protected static function cmf_supports_visibility(): bool {
				return false;
			}
		};

		$this->assertNull( $old_cmf::get_value( [ 'field' => 'agency_phone' ], $this->make_block( [ 'postId' => $post_id ] ) ) );
		// Sanity: the real class resolves the same input.
		$this->assertSame( '555-0100', Settings_Binding_Source::get_value( [ 'field' => 'agency_phone' ], $this->make_block( [ 'postId' => $post_id ] ) ) );
	}

	/**
	 * A nonexistent post resolves to null.
	 */
	public function test_missing_post_resolves_to_null(): void {
		$this->marked_post();

		$this->assertNull( Settings_Binding_Source::get_value( [ 'field' => 'agency_phone' ], $this->make_block( [ 'postId' => 987654 ] ) ) );
	}
}
