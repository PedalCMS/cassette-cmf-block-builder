<?php
/**
 * source: 'context' — usesContext merge, validation, server render through a
 * real parent block, conditions, and the providesContext/usesContext
 * passthrough to register_block_type() and the JS payload.
 *
 * @package PedalCMS\CassetteCMFBlocks\Tests\Unit
 */

use PedalCMS\CassetteCMFBlocks\CassetteCMFBlocks;
use PedalCMS\CassetteCMFBlocks\Core\Block_Definition;
use PedalCMS\CassetteCMFBlocks\Core\Block_Manager;
use PedalCMS\CassetteCMFBlocks\Core\Editor_Payload;
use PedalCMS\CassetteCMFBlocks\Core\Field_Collection;
use PedalCMS\CassetteCMFBlocks\Json\Block_Schema_Validator;
use PedalCMS\CassetteCMFBlocks\Schema\Control_Catalog;

require_once __DIR__ . '/CassetteCMFBlocks_UnitTestCase.php';

/**
 * Class Test_Context_Source
 */
class Test_Context_Source extends CassetteCMFBlocks_UnitTestCase {

	/**
	 * Block names registered by a test.
	 *
	 * @var string[]
	 */
	private array $registered = [];

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
		foreach ( $this->registered as $name ) {
			if ( WP_Block_Type_Registry::get_instance()->is_registered( $name ) ) {
				unregister_block_type( $name );
			}
		}
		$this->registered = [];
		Block_Manager::reset();
		Control_Catalog::reset();
		parent::tear_down();
	}

	/**
	 * A provider block and a child reading one of its values.
	 *
	 * @param array<string, mixed> $child_overrides Merged over the child block config.
	 * @return array<string, mixed>
	 */
	private function form_config( array $child_overrides = [] ): array {
		$this->registered = [ 'acme-test/ctx-form', 'acme-test/ctx-field' ];

		return [
			'blocks' => [
				[
					'id'     => 'acme-test/ctx-form',
					'args'   => [
						'providesContext' => [ 'acme-test/formId' => 'formId' ],
					],
					'fields' => [
						[
							'name' => 'formId',
							'type' => 'text',
						],
					],
					'render' => [
						'markup' => [
							'tag'  => 'form',
							'slot' => 'inner_blocks',
						],
					],
				],
				array_replace_recursive(
					[
						'id'     => 'acme-test/ctx-field',
						'args'   => [
							'ancestor' => [ 'acme-test/ctx-form' ],
						],
						'fields' => [
							[
								'name'    => 'formId',
								'type'    => 'text',
								'source'  => 'context',
								'context' => 'acme-test/formId',
							],
							[
								'name' => 'label',
								'type' => 'text',
							],
						],
						'render' => [
							'markup' => [
								'tag'  => 'span',
								'text' => 'form={{ attributes.formId }};label={{ attributes.label }}',
							],
						],
					],
					$child_overrides
				),
			],
		];
	}

	/**
	 * Serialized parent + child.
	 *
	 * @param string $form_id Parent formId attribute.
	 * @return string
	 */
	private function content( string $form_id ): string {
		return '<!-- wp:acme-test/ctx-form {"formId":"' . $form_id . '"} -->'
			. '<!-- wp:acme-test/ctx-field {"label":"Name"} /-->'
			. '<!-- /wp:acme-test/ctx-form -->';
	}

	/**
	 * The child rendered inside a real parent contains the parent's value.
	 */
	public function test_server_render_reads_the_parents_value_through_do_blocks(): void {
		CassetteCMFBlocks::register_from_array( $this->form_config() );

		$html = do_blocks( $this->content( 'F-42' ) );

		$this->assertStringContainsString( 'form=F-42;label=Name', $html );
	}

	/**
	 * A different parent value shows up in the child (not a cached/stale one).
	 */
	public function test_server_render_follows_the_parent_value(): void {
		CassetteCMFBlocks::register_from_array( $this->form_config() );

		$this->assertStringContainsString( 'form=A;', do_blocks( $this->content( 'A' ) ) );
		$this->assertStringContainsString( 'form=B;', do_blocks( $this->content( 'B' ) ) );
	}

	/**
	 * Outside a provider the field is simply empty (no attribute exists either).
	 */
	public function test_without_a_provider_the_value_is_empty(): void {
		CassetteCMFBlocks::register_from_array( $this->form_config() );

		$html = do_blocks( '<!-- wp:acme-test/ctx-field {"label":"Solo"} /-->' );

		$this->assertStringContainsString( 'form=;label=Solo', $html );
	}

	/**
	 * Stored attributes never carry the context field; a stored value is ignored.
	 */
	public function test_no_attribute_is_created_and_nothing_is_stored(): void {
		CassetteCMFBlocks::register_from_array( $this->form_config() );

		$type = WP_Block_Type_Registry::get_instance()->get_registered( 'acme-test/ctx-field' );

		$this->assertArrayNotHasKey( 'formId', $type->attributes );
		$this->assertArrayHasKey( 'label', $type->attributes );

		// A hand-edited attribute in post_content cannot override the provider's value.
		$html = do_blocks(
			'<!-- wp:acme-test/ctx-form {"formId":"real"} --><!-- wp:acme-test/ctx-field {"formId":"forged"} /--><!-- /wp:acme-test/ctx-form -->'
		);
		$this->assertStringContainsString( 'form=real;', $html );
	}

	/**
	 * A PHP template sees the value in $attributes under the field's name.
	 */
	public function test_php_template_sees_the_context_value(): void {
		$template = wp_tempnam( 'ctx-template' );
		file_put_contents( $template, '<p class="tpl"><?php echo esc_html( $attributes[\'formId\'] . "|" . $block->context[\'acme-test/formId\'] ); ?></p>' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		$config = $this->form_config();
		unset( $config['blocks'][1]['render']['markup'] );
		$config['blocks'][1]['render'] = [ 'template' => $template ];
		CassetteCMFBlocks::register_from_array( $config );

		$html = do_blocks( $this->content( 'T-9' ) );
		unlink( $template ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink

		$this->assertStringContainsString( '<p class="tpl">T-9|T-9</p>', $html );
	}

	/**
	 * A render callback gets the value from $block->context (and in $attributes).
	 */
	public function test_render_callback_reads_block_context(): void {
		$config                        = $this->form_config();
		$config['blocks'][1]['render'] = [
			'callback' => static function ( $attributes, $content, $block ) {
				return '<i>' . esc_html( (string) $block->context['acme-test/formId'] ) . '</i>';
			},
		];
		CassetteCMFBlocks::register_from_array( $config );

		$this->assertStringContainsString( '<i>CB</i>', do_blocks( $this->content( 'CB' ) ) );
	}

	/**
	 * A markup "when" and a condition can reference the context field like an attribute.
	 */
	public function test_markup_conditions_can_reference_the_context_field(): void {
		$config                                  = $this->form_config();
		$config['blocks'][1]['render']['markup'] = [
			'tag'      => 'div',
			'children' => [
				[
					'tag'  => 'b',
					'text' => 'special',
					'when' => [
						'rules' => [
							[
								'field'    => 'formId',
								'operator' => '==',
								'value'    => 'S',
							],
						],
					],
				],
				[
					'tag'  => 'i',
					'text' => 'always',
				],
			],
		];
		CassetteCMFBlocks::register_from_array( $config );

		$special = do_blocks( $this->content( 'S' ) );
		$other   = do_blocks( $this->content( 'X' ) );

		$this->assertStringContainsString( '<b>special</b>', $special );
		$this->assertStringNotContainsString( '<b>special</b>', $other );
		$this->assertStringContainsString( '<i>always</i>', $other );
	}

	/**
	 * usesContext gains the field's key, merged with explicit entries and de-duplicated.
	 */
	public function test_uses_context_is_merged_and_deduplicated(): void {
		$definition = new Block_Definition(
			[
				'id'     => 'acme-test/ctx-merge',
				'args'   => [ 'usesContext' => [ 'postId', 'acme-test/formId' ] ],
				'fields' => [
					[
						'name'    => 'a',
						'type'    => 'text',
						'source'  => 'context',
						'context' => 'acme-test/formId',
					],
					[
						'name'    => 'b',
						'type'    => 'text',
						'source'  => 'context',
						'context' => 'acme-test/other',
					],
					[
						'name'    => 'c',
						'type'    => 'text',
						'source'  => 'context',
						'context' => 'acme-test/formId',
					],
				],
			]
		);

		$this->assertSame(
			[ 'postId', 'acme-test/formId', 'acme-test/other' ],
			$definition->get_args()['usesContext']
		);
		$this->assertSame(
			[ 'postId', 'acme-test/formId', 'acme-test/other' ],
			$definition->to_block_type_args()['uses_context']
		);
		$this->assertSame( [], $definition->get_attributes() );
	}

	/**
	 * usesContext also merges alongside the automatic meta context.
	 */
	public function test_uses_context_merges_with_meta_context(): void {
		$definition = new Block_Definition(
			[
				'id'     => 'acme-test/ctx-meta',
				'fields' => [
					[
						'name'   => 'm',
						'type'   => 'text',
						'source' => 'meta',
					],
					[
						'name'    => 'c',
						'type'    => 'text',
						'source'  => 'context',
						'context' => 'acme-test/formId',
					],
				],
			]
		);

		$this->assertSame(
			[ 'postId', 'postType', 'acme-test/formId' ],
			$definition->get_args()['usesContext']
		);
	}

	/**
	 * Without context fields usesContext is untouched.
	 */
	public function test_uses_context_is_absent_without_context_fields(): void {
		$definition = new Block_Definition(
			[
				'id'     => 'acme-test/ctx-none',
				'fields' => [
					[
						'name' => 'a',
						'type' => 'text',
					],
				],
			]
		);

		$this->assertArrayNotHasKey( 'usesContext', $definition->get_args() );
	}

	/**
	 * A context field must name its key.
	 *
	 * @dataProvider missing_context_provider
	 * @param array<string, mixed> $field Field config.
	 */
	public function test_missing_context_key_is_rejected( array $field ): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'requires "context"' );

		new Field_Collection( [ $field ] );
	}

	/**
	 * Field configs with a missing or invalid "context".
	 *
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public function missing_context_provider(): array {
		$base = [
			'name'   => 'formId',
			'type'   => 'text',
			'source' => 'context',
		];

		return [
			'absent'     => [ $base ],
			'empty'      => [ $base + [ 'context' => '' ] ],
			'non-string' => [ $base + [ 'context' => [ 'x' ] ] ],
		];
	}

	/**
	 * Through the manager the invalid block is reported with _doing_it_wrong and not registered.
	 */
	public function test_manager_reports_a_missing_context_key(): void {
		// The _doing_it_wrong() raised by Block_Manager::register_blocks() is ignored by the base test case.
		CassetteCMFBlocks::register_from_array(
			[
				'blocks' => [
					[
						'id'     => 'acme-test/ctx-bad',
						'fields' => [
							[
								'name'   => 'formId',
								'type'   => 'text',
								'source' => 'context',
							],
						],
					],
				],
			]
		);

		$this->assertFalse( WP_Block_Type_Registry::get_instance()->is_registered( 'acme-test/ctx-bad' ) );
	}

	/**
	 * The JSON validator flags it too.
	 */
	public function test_schema_validator_flags_a_missing_context_key(): void {
		$validator = new Block_Schema_Validator();

		$valid = $validator->validate(
			[
				'blocks' => [
					[
						'id'     => 'acme/x',
						'fields' => [
							[
								'name'   => 'a',
								'type'   => 'text',
								'source' => 'context',
							],
						],
					],
				],
			]
		);

		$this->assertFalse( $valid );
		$this->assertStringContainsString( 'context is required', $validator->get_error_message() );
	}

	/**
	 * providesContext / usesContext reach register_block_type() and the JS payload.
	 */
	public function test_provides_and_uses_context_reach_the_registry_and_the_payload(): void {
		CassetteCMFBlocks::register_from_array( $this->form_config() );

		$registry = WP_Block_Type_Registry::get_instance();
		$this->assertSame(
			[ 'acme-test/formId' => 'formId' ],
			$registry->get_registered( 'acme-test/ctx-form' )->provides_context
		);
		$this->assertSame(
			[ 'acme-test/formId' ],
			$registry->get_registered( 'acme-test/ctx-field' )->uses_context
		);

		$payload = Editor_Payload::build( Block_Manager::init() );
		$this->assertSame(
			[ 'acme-test/formId' => 'formId' ],
			$payload['blocks']['acme-test/ctx-form']['settings']['providesContext']
		);
		$this->assertSame(
			[ 'acme-test/formId' ],
			$payload['blocks']['acme-test/ctx-field']['settings']['usesContext']
		);

		$field = $payload['blocks']['acme-test/ctx-field']['fields'][0]['config'];
		$this->assertSame( 'context', $field['source'] );
		$this->assertSame( 'acme-test/formId', $field['context'] );
	}

	/**
	 * Field names stay unique within a block, so a context field can never shadow an attribute.
	 */
	public function test_a_context_field_cannot_share_a_name_with_an_attribute(): void {
		$this->expectException( InvalidArgumentException::class );

		new Field_Collection(
			[
				[
					'name' => 'formId',
					'type' => 'text',
				],
				[
					'name'    => 'formId',
					'type'    => 'text',
					'source'  => 'context',
					'context' => 'acme-test/formId',
				],
			]
		);
	}
}
