<?php
/**
 * Settings_Binding_Source test.
 *
 * @package Pedalcms\CassetteCmfBlocks\Tests\Unit
 */

use Pedalcms\CassetteCmfBlocks\Binding\Settings_Binding_Source;

require_once __DIR__ . '/CassetteCmfBlocks_UnitTestCase.php';

/**
 * Class Test_Settings_Binding_Source
 */
class Test_Settings_Binding_Source extends CassetteCmfBlocks_UnitTestCase {

	/**
	 * Build a bare WP_Block instance with the given block context.
	 *
	 * WP_Block only exposes the subset of $available_context its own block
	 * type actually declares via "usesContext" — a real consuming block
	 * (like Binding_Source_Registrar's own docblock example) must declare
	 * "usesContext" => [ 'postId' ] itself for Settings_Binding_Source's
	 * postId fallback to ever see anything, exactly as this fixture does.
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
	 * A missing "field" resolves to null.
	 */
	public function test_missing_field_resolves_to_null(): void {
		$result = Settings_Binding_Source::get_value(
			[
				'context'      => 1,
				'context_type' => 'post',
			],
			$this->make_block()
		);

		$this->assertNull( $result );
	}

	/**
	 * A 'post' binding with no explicit "context" falls back to the
	 * block's own "postId" context.
	 */
	public function test_post_binding_falls_back_to_block_context_post_id(): void {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'agency_phone', '555-0100' );

		$result = Settings_Binding_Source::get_value(
			[ 'field' => 'agency_phone' ],
			$this->make_block( [ 'postId' => $post_id ] )
		);

		$this->assertSame( '555-0100', $result );
	}

	/**
	 * An explicit "context" overrides the block's own context.
	 */
	public function test_explicit_context_overrides_block_context(): void {
		$other_post_id = self::factory()->post->create();
		update_post_meta( $other_post_id, 'agency_phone', '555-0200' );

		$result = Settings_Binding_Source::get_value(
			[
				'field'   => 'agency_phone',
				'context' => $other_post_id,
			],
			$this->make_block( [ 'postId' => 999999 ] )
		);

		$this->assertSame( '555-0200', $result );
	}

	/**
	 * A 'post' binding with neither an explicit "context" nor a "postId"
	 * block context resolves to null — nothing to resolve against.
	 */
	public function test_post_binding_with_no_context_resolves_to_null(): void {
		$result = Settings_Binding_Source::get_value( [ 'field' => 'agency_phone' ], $this->make_block() );

		$this->assertNull( $result );
	}

	/**
	 * 'term'/'settings' bindings require an explicit "context" — there's no
	 * standard "current term"/"current settings page" block context to
	 * fall back to.
	 */
	public function test_term_binding_with_no_explicit_context_resolves_to_null(): void {
		$result = Settings_Binding_Source::get_value(
			[
				'field'        => 'category_color',
				'context_type' => 'term',
			],
			$this->make_block()
		);

		$this->assertNull( $result );
	}

	/**
	 * A resolvable value round-trips through CassetteCmf::get_field()
	 * correctly for a 'settings' binding.
	 */
	public function test_settings_binding_resolves_via_cassette_cmf(): void {
		update_option( 'agency-settings_theme_color', '#336699' );

		$result = Settings_Binding_Source::get_value(
			[
				'field'        => 'theme_color',
				'context'      => 'agency-settings',
				'context_type' => 'settings',
			],
			$this->make_block()
		);

		$this->assertSame( '#336699', $result );

		delete_option( 'agency-settings_theme_color' );
	}

	/**
	 * An unresolvable value resolves to null, never '' — so WordPress
	 * falls back to the block's own static content instead of overwriting
	 * it with an empty value.
	 */
	public function test_unresolvable_value_is_null_not_empty_string(): void {
		$post_id = self::factory()->post->create();

		$result = Settings_Binding_Source::get_value(
			[ 'field' => 'nonexistent_field' ],
			$this->make_block( [ 'postId' => $post_id ] )
		);

		$this->assertNull( $result );
	}
}
