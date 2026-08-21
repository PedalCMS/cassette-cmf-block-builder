<?php
/**
 * Field_Collection test.
 *
 * @package Pedalcms\CassetteCmfBlocks\Tests\Unit
 */

use Pedalcms\CassetteCmfBlocks\Core\Field_Collection;
use Pedalcms\CassetteCmfBlocks\Schema\Control_Catalog;

require_once __DIR__ . '/CassetteCmfBlocks_UnitTestCase.php';

/**
 * Class Test_Field_Collection
 */
class Test_Field_Collection extends CassetteCmfBlocks_UnitTestCase {

	/**
	 * Reset the catalog before each test.
	 */
	public function set_up(): void {
		parent::set_up();
		Control_Catalog::reset();
	}

	/**
	 * Flat, unnested fields should all appear as leaves with the default area.
	 */
	public function test_flat_fields_become_leaves(): void {
		$collection = new Field_Collection(
			[
				[
					'name' => 'label',
					'type' => 'text',
				],
				[
					'name' => 'required',
					'type' => 'toggle',
				],
			]
		);

		$leaves = $collection->get_leaves();
		$this->assertCount( 2, $leaves );
		$this->assertSame( 'label', $leaves[0]['config']['name'] );
		$this->assertSame( 'inspector', $leaves[0]['area'] );
	}

	/**
	 * A leaf inside a panel with no declared area should inherit the panel's area.
	 */
	public function test_child_inherits_parent_area(): void {
		$collection = new Field_Collection(
			[
				[
					'name'   => 'settings',
					'type'   => 'panel',
					'area'   => 'inspector.styles',
					'fields' => [
						[
							'name' => 'label',
							'type' => 'text',
						],
					],
				],
			]
		);

		$leaves = $collection->get_leaves();
		$this->assertSame( 'inspector.styles', $leaves[0]['area'] );
	}

	/**
	 * A child that declares its own area overrides inheritance.
	 */
	public function test_child_can_override_inherited_area(): void {
		$collection = new Field_Collection(
			[
				[
					'name'   => 'toolbar_actions',
					'type'   => 'toolbar_group',
					'area'   => 'toolbar.block',
					'fields' => [
						[
							'name' => 'align',
							'type' => 'toolbar_align',
							'area' => 'toolbar.other',
						],
					],
				],
			]
		);

		$this->assertSame( 'toolbar.other', $collection->get_leaves()[0]['area'] );
	}

	/**
	 * Containers do not produce their own leaf entries (except repeater, which holds a value).
	 */
	public function test_panel_itself_is_not_a_leaf(): void {
		$collection = new Field_Collection(
			[
				[
					'name'   => 'settings',
					'type'   => 'panel',
					'fields' => [
						[
							'name' => 'label',
							'type' => 'text',
						],
					],
				],
			]
		);

		$names = array_column( array_column( $collection->get_leaves(), 'config' ), 'name' );
		$this->assertSame( [ 'label' ], $names );
	}

	/**
	 * A repeater is a container that also holds its own (array) value.
	 */
	public function test_repeater_is_both_container_and_leaf(): void {
		$collection = new Field_Collection(
			[
				[
					'name'   => 'items',
					'type'   => 'repeater',
					'fields' => [
						[
							'name' => 'item_label',
							'type' => 'text',
						],
					],
				],
			]
		);

		$names = array_column( array_column( $collection->get_leaves(), 'config' ), 'name' );
		$this->assertSame( [ 'items', 'item_label' ], $names );
	}

	/**
	 * A toolbar_group may not contain an inspector-style container.
	 */
	public function test_toolbar_group_cannot_contain_panel(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessageMatches( '/toolbar_group may not contain/' );

		new Field_Collection(
			[
				[
					'name'   => 'actions',
					'type'   => 'toolbar_group',
					'fields' => [
						[
							'name'   => 'nested_panel',
							'type'   => 'panel',
							'fields' => [],
						],
					],
				],
			]
		);
	}

	/**
	 * An inspector-style container may not contain a toolbar_group.
	 */
	public function test_panel_cannot_contain_toolbar_group(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessageMatches( '/may not contain a toolbar_group/' );

		new Field_Collection(
			[
				[
					'name'   => 'settings',
					'type'   => 'panel',
					'fields' => [
						[
							'name'   => 'nested_toolbar',
							'type'   => 'toolbar_group',
							'fields' => [],
						],
					],
				],
			]
		);
	}

	/**
	 * A field resolving to the canvas area may not appear inside an
	 * inspector-style container.
	 */
	public function test_canvas_field_cannot_appear_inside_panel(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessageMatches( '/canvas-area field may not appear inside/' );

		new Field_Collection(
			[
				[
					'name'   => 'settings',
					'type'   => 'panel',
					'fields' => [
						[
							'name' => 'preview',
							'type' => 'text',
							'area' => 'canvas',
						],
					],
				],
			]
		);
	}

	/**
	 * A top-level canvas field (no enclosing container) is legal.
	 */
	public function test_canvas_field_is_legal_at_top_level(): void {
		$collection = new Field_Collection(
			[
				[
					'name' => 'preview',
					'type' => 'text',
					'area' => 'canvas',
				],
			]
		);

		$this->assertSame( 'canvas', $collection->get_leaves()[0]['area'] );
	}

	/**
	 * A repeater's direct sub-fields cannot declare "conditional" — ported
	 * from the parent's Repeater_Field, which rejects this for the same
	 * reason: a controller field outside a repeater row cannot address a
	 * value inside a specific row.
	 */
	public function test_repeater_subfield_cannot_be_conditional(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessageMatches( '/repeater sub-fields cannot define conditional/' );

		new Field_Collection(
			[
				[
					'name'   => 'items',
					'type'   => 'repeater',
					'fields' => [
						[
							'name'        => 'item_label',
							'type'        => 'text',
							'conditional' => [
								'rules' => [
									[
										'field'    => 'other',
										'operator' => '==',
										'value'    => '1',
									],
								],
							],
						],
					],
				],
			]
		);
	}

	/**
	 * Duplicate field names among value-bearing leaves must be rejected —
	 * they would collide as block attributes.
	 */
	public function test_duplicate_names_are_rejected(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessageMatches( '/Duplicate field name/' );

		new Field_Collection(
			[
				[
					'name' => 'label',
					'type' => 'text',
				],
				[
					'name' => 'label',
					'type' => 'textarea',
				],
			]
		);
	}

	/**
	 * A tab_panel whose direct children are all "tab" containers is legal,
	 * and fields nested inside a tab still become leaves with the
	 * tab_panel's inherited area.
	 */
	public function test_tab_panel_with_tab_children_is_legal(): void {
		$collection = new Field_Collection(
			[
				[
					'name'   => 'settings',
					'type'   => 'tab_panel',
					'area'   => 'inspector.styles',
					'fields' => [
						[
							'name'   => 'general',
							'type'   => 'tab',
							'title'  => 'General',
							'fields' => [
								[
									'name' => 'heading',
									'type' => 'text',
								],
							],
						],
					],
				],
			]
		);

		$leaves = $collection->get_leaves();
		$this->assertCount( 1, $leaves );
		$this->assertSame( 'heading', $leaves[0]['config']['name'] );
		$this->assertSame( 'inspector.styles', $leaves[0]['area'] );
	}

	/**
	 * A tab_panel's direct children must all be "tab" containers — a bare
	 * leaf control directly under a tab_panel has no tab to render under.
	 */
	public function test_tab_panel_rejects_non_tab_children(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessageMatches( '/direct children must all be "tab" containers/' );

		new Field_Collection(
			[
				[
					'name'   => 'settings',
					'type'   => 'tab_panel',
					'fields' => [
						[
							'name' => 'heading',
							'type' => 'text',
						],
					],
				],
			]
		);
	}

	/**
	 * A "tab" container may only appear as a direct child of a tab_panel.
	 */
	public function test_tab_outside_tab_panel_is_rejected(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessageMatches( '/may only appear as a direct child of a "tab_panel"/' );

		new Field_Collection(
			[
				[
					'name'   => 'general',
					'type'   => 'tab',
					'title'  => 'General',
					'fields' => [],
				],
			]
		);
	}

	/**
	 * to_editor_tree() must preserve container nesting (unlike get_leaves(),
	 * which flattens), while still resolving each node's area/type/cmfType
	 * and stripping "fields" out of a container's own "config" (it's
	 * re-exposed as the node's separate "fields" key instead).
	 */
	public function test_to_editor_tree_preserves_nesting_and_resolves_nodes(): void {
		$collection = new Field_Collection(
			[
				[
					'name'   => 'settings',
					'type'   => 'panel',
					'title'  => 'Settings',
					'area'   => 'inspector.styles',
					'fields' => [
						[
							'name' => 'heading',
							'type' => 'text',
						],
					],
				],
			]
		);

		$tree = $collection->to_editor_tree();

		$this->assertCount( 1, $tree );
		$panel_node = $tree[0];
		$this->assertSame( 'panel', $panel_node['type'] );
		$this->assertSame( 'inspector.styles', $panel_node['area'] );
		$this->assertTrue( $panel_node['isContainer'] );
		$this->assertFalse( $panel_node['holdsValue'] );
		$this->assertArrayNotHasKey( 'fields', $panel_node['config'] );
		$this->assertSame( 'Settings', $panel_node['config']['title'] );

		$this->assertCount( 1, $panel_node['fields'] );
		$field_node = $panel_node['fields'][0];
		$this->assertSame( 'text', $field_node['type'] );
		// Inherited from the enclosing panel, since the leaf declares no area of its own.
		$this->assertSame( 'inspector.styles', $field_node['area'] );
		$this->assertTrue( $field_node['holdsValue'] );
		$this->assertArrayNotHasKey( 'fields', $field_node );
	}

	/**
	 * to_editor_tree() must normalize a node's "conditional" — operator
	 * aliases resolved, the single-rule shorthand wrapped into
	 * { relation, rules[] } — so conditions/evaluate.js (JS) never sees a
	 * raw alias like "equals".
	 */
	public function test_to_editor_tree_normalizes_conditional(): void {
		$collection = new Field_Collection(
			[
				[
					'name'        => 'reason',
					'type'        => 'text',
					'conditional' => [
						'field'    => 'is_open',
						'operator' => 'equals',
						'value'    => true,
					],
				],
			]
		);

		$conditional = $collection->to_editor_tree()[0]['config']['conditional'];

		$this->assertSame(
			[
				'relation' => 'AND',
				'rules'    => [
					[
						'field'    => 'is_open',
						'operator' => '==',
						'value'    => true,
					],
				],
			],
			$conditional
		);
	}

	/**
	 * A node with no "conditional" must not gain one in the editor tree.
	 */
	public function test_to_editor_tree_omits_conditional_when_absent(): void {
		$collection = new Field_Collection(
			[
				[
					'name' => 'heading',
					'type' => 'text',
				],
			]
		);

		$this->assertArrayNotHasKey( 'conditional', $collection->to_editor_tree()[0]['config'] );
	}

	/**
	 * get_tree() should return the original, unflattened structure.
	 */
	public function test_get_tree_returns_original_structure(): void {
		$raw        = [
			[
				'name' => 'label',
				'type' => 'text',
			],
		];
		$collection = new Field_Collection( $raw );

		$this->assertSame( $raw, $collection->get_tree() );
	}
}
