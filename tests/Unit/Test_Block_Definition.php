<?php
/**
 * Block_Definition test.
 *
 * @package PedalCMS\CassetteCMFBlocks\Tests\Unit
 */

use PedalCMS\CassetteCMFBlocks\Core\Block_Definition;
use PedalCMS\CassetteCMFBlocks\Schema\Control_Catalog;

require_once __DIR__ . '/CassetteCMFBlocks_UnitTestCase.php';

/**
 * Class Test_Block_Definition
 */
class Test_Block_Definition extends CassetteCMFBlocks_UnitTestCase {

	/**
	 * Reset the catalog before each test.
	 */
	public function set_up(): void {
		parent::set_up();
		Control_Catalog::reset();
	}

	/**
	 * A block config without "id" must throw.
	 */
	public function test_requires_id(): void {
		$this->expectException( InvalidArgumentException::class );

		new Block_Definition( [] );
	}

	/**
	 * get_name() should return the block's id.
	 */
	public function test_get_name(): void {
		$definition = new Block_Definition( [ 'id' => 'acme/callout' ] );

		$this->assertSame( 'acme/callout', $definition->get_name() );
	}

	/**
	 * to_block_type_args() must translate block.json-style camelCase keys
	 * to the snake_case properties WP_Block_Type's constructor actually
	 * reads, since register_block_type($name, $args) never runs the
	 * translation that register_block_type_from_metadata() performs.
	 */
	public function test_camel_case_keys_are_translated_to_snake_case(): void {
		$definition = new Block_Definition(
			[
				'id'   => 'acme/callout',
				'args' => [
					'usesContext'     => [ 'postId' ],
					'providesContext' => [ 'acme/tone' => 'tone' ],
					'allowedBlocks'   => [ 'core/paragraph' ],
				],
			]
		);

		$args = $definition->to_block_type_args();

		$this->assertSame( [ 'postId' ], $args['uses_context'] );
		$this->assertSame( [ 'acme/tone' => 'tone' ], $args['provides_context'] );
		$this->assertSame( [ 'core/paragraph' ], $args['allowed_blocks'] );
		$this->assertArrayNotHasKey( 'usesContext', $args );
		$this->assertArrayNotHasKey( 'providesContext', $args );
		$this->assertArrayNotHasKey( 'allowedBlocks', $args );
	}

	/**
	 * apiVersion should translate to api_version and default to 3.
	 */
	public function test_api_version_translates_and_defaults(): void {
		$definition = new Block_Definition( [ 'id' => 'acme/callout' ] );

		$args = $definition->to_block_type_args();

		$this->assertSame( 3, $args['api_version'] );
		$this->assertArrayNotHasKey( 'apiVersion', $args );
	}

	/**
	 * Keys that are already the same spelling in both casings (title,
	 * category, icon, ...) should pass through unchanged.
	 */
	public function test_unmapped_keys_pass_through_unchanged(): void {
		$definition = new Block_Definition(
			[
				'id'   => 'acme/callout',
				'args' => [
					'title'    => 'Callout',
					'category' => 'common',
					'icon'     => 'megaphone',
				],
			]
		);

		$args = $definition->to_block_type_args();

		$this->assertSame( 'Callout', $args['title'] );
		$this->assertSame( 'common', $args['category'] );
		$this->assertSame( 'megaphone', $args['icon'] );
	}

	/**
	 * blockHooks positions should translate camelCase to snake_case, same
	 * as register_block_type_from_metadata()'s own translation.
	 */
	public function test_block_hooks_positions_are_translated(): void {
		$definition = new Block_Definition(
			[
				'id'   => 'acme/callout',
				'args' => [
					'blockHooks' => [
						'core/paragraph' => 'firstChild',
						'core/heading'   => 'after',
					],
				],
			]
		);

		$args = $definition->to_block_type_args();

		$this->assertSame(
			[
				'core/paragraph' => 'first_child',
				'core/heading'   => 'after',
			],
			$args['block_hooks']
		);
	}

	/**
	 * A block hooking itself must be dropped (mirrors WP core's own guard
	 * in register_block_type_from_metadata()).
	 */
	public function test_block_hooks_self_reference_is_dropped(): void {
		$this->setExpectedIncorrectUsage( 'PedalCMS\CassetteCMFBlocks\Core\Block_Definition::translate_block_hooks' );

		$definition = new Block_Definition(
			[
				'id'   => 'acme/callout',
				'args' => [ 'blockHooks' => [ 'acme/callout' => 'before' ] ],
			]
		);

		$args = $definition->to_block_type_args();

		$this->assertSame( [], $args['block_hooks'] );
	}

	/**
	 * The resolved attributes schema must be present in the block-type args.
	 */
	public function test_attributes_are_included(): void {
		$definition = new Block_Definition(
			[
				'id'     => 'acme/callout',
				'fields' => [
					[
						'name' => 'heading',
						'type' => 'text',
					],
				],
			]
		);

		$args = $definition->to_block_type_args();

		$this->assertArrayHasKey( 'heading', $args['attributes'] );
		$this->assertSame( 'string', $args['attributes']['heading']['type'] );
	}

	/**
	 * get_args() must stay in block.json's own camelCase — the client-side
	 * registerBlockType() call needs the same casing block.json uses, the
	 * mirror image of to_block_type_args()'s snake_case translation for the
	 * PHP-side register_block_type() call. Shipping the snake_case form to
	 * the editor bundle would silently misconfigure the block there too.
	 */
	public function test_get_args_stays_camel_case(): void {
		$definition = new Block_Definition(
			[
				'id'   => 'acme/callout',
				'args' => [ 'usesContext' => [ 'postId' ] ],
			]
		);

		$args = $definition->get_args();

		$this->assertSame( 3, $args['apiVersion'] );
		$this->assertSame( [ 'postId' ], $args['usesContext'] );
		$this->assertArrayNotHasKey( 'api_version', $args );
		$this->assertArrayNotHasKey( 'uses_context', $args );
	}

	/**
	 * get_fields() and get_attributes() should expose the compiled state.
	 */
	public function test_exposes_fields_and_attributes(): void {
		$definition = new Block_Definition(
			[
				'id'     => 'acme/callout',
				'fields' => [
					[
						'name' => 'heading',
						'type' => 'text',
					],
				],
			]
		);

		$this->assertCount( 1, $definition->get_fields()->get_leaves() );
		$this->assertArrayHasKey( 'heading', $definition->get_attributes() );
	}

	/**
	 * A dynamic-mode block with a "render.markup" declared must get a
	 * callable "render_callback" in to_block_type_args().
	 */
	public function test_render_callback_is_wired_for_dynamic_markup(): void {
		$definition = new Block_Definition(
			[
				'id'     => 'acme/callout',
				'render' => [
					'markup' => [ 'tag' => 'p' ],
				],
			]
		);

		$args = $definition->to_block_type_args();

		$this->assertArrayHasKey( 'render_callback', $args );
		$this->assertIsCallable( $args['render_callback'] );
	}

	/**
	 * A block with no "render" config at all gets no render_callback —
	 * unchanged behaviour from before render support existed, rather than
	 * silently changing what every already-registered block does.
	 */
	public function test_no_render_callback_without_render_config(): void {
		$definition = new Block_Definition( [ 'id' => 'acme/callout' ] );

		$args = $definition->to_block_type_args();

		$this->assertArrayNotHasKey( 'render_callback', $args );
	}

	/**
	 * A "static"-mode block gets no render_callback — its output comes
	 * from save() instead.
	 */
	public function test_no_render_callback_for_static_mode(): void {
		$definition = new Block_Definition(
			[
				'id'     => 'acme/callout',
				'render' => [
					'mode'   => 'static',
					'markup' => [ 'tag' => 'p' ],
				],
			]
		);

		$args = $definition->to_block_type_args();

		$this->assertArrayNotHasKey( 'render_callback', $args );
	}

	/**
	 * A block with a "meta"-sourced field (default object_type "post")
	 * automatically gains "postId"/"postType" in "usesContext" —
	 * value/useFieldValue.js's "meta" source needs "postType" to call
	 * useEntityProp(), and it has no other way to reach it.
	 */
	public function test_meta_sourced_field_adds_post_context(): void {
		$definition = new Block_Definition(
			[
				'id'     => 'acme/callout',
				'fields' => [
					[
						'name'   => 'internal_note',
						'type'   => 'text',
						'source' => 'meta',
					],
				],
			]
		);

		$this->assertSame( [ 'postId', 'postType' ], $definition->get_args()['usesContext'] );
	}

	/**
	 * A meta field explicitly targeting a non-"post" object type doesn't
	 * add post context — there's no post to read it from.
	 */
	public function test_meta_sourced_field_for_a_different_object_type_adds_no_context(): void {
		$definition = new Block_Definition(
			[
				'id'     => 'acme/callout',
				'fields' => [
					[
						'name'   => 'category_color',
						'type'   => 'color',
						'source' => 'meta',
						'meta'   => [ 'object_type' => 'term' ],
					],
				],
			]
		);

		$this->assertArrayNotHasKey( 'usesContext', $definition->get_args() );
	}

	/**
	 * A block with no "meta"-sourced field at all gains no auto-added context.
	 */
	public function test_no_meta_fields_adds_no_context(): void {
		$definition = new Block_Definition(
			[
				'id'     => 'acme/callout',
				'fields' => [
					[
						'name' => 'heading',
						'type' => 'text',
					],
				],
			]
		);

		$this->assertArrayNotHasKey( 'usesContext', $definition->get_args() );
	}

	/**
	 * An already-declared "usesContext" is preserved (merged with, not
	 * overwritten by) the auto-added "postId"/"postType".
	 */
	public function test_meta_context_merges_with_an_existing_uses_context(): void {
		$definition = new Block_Definition(
			[
				'id'     => 'acme/callout',
				'args'   => [ 'usesContext' => [ 'acme/tone' ] ],
				'fields' => [
					[
						'name'   => 'internal_note',
						'type'   => 'text',
						'source' => 'meta',
					],
				],
			]
		);

		$this->assertSame(
			[ 'acme/tone', 'postId', 'postType' ],
			$definition->get_args()['usesContext']
		);
	}
}
