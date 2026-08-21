<?php
/**
 * Field_Collection class for Cassette-CMF Blocks
 *
 * Recursively flattens a block's raw "fields" array (panels/tabs/groups
 * nesting leaf controls, mirroring the parent library's nested-field
 * flattening in Core\Traits\Field_Registration_Trait) into a flat list of
 * value-bearing leaf entries with a resolved area and control-type
 * resolution, while validating:
 *
 *   - Unknown or banned control types (via Control_Mapper::resolve()).
 *   - Nesting: a toolbar_group may not contain an inspector-style container
 *     (panel/tab_panel/group/row/stack/repeater) and vice versa.
 *   - A tab_panel's direct children must all be "tab" containers, and a
 *     "tab" may only appear as a direct child of a tab_panel — unlike every
 *     other container, tab_panel cannot hold leaf controls directly, since
 *     there would be no tab to render them under.
 *   - A field may not resolve to the "canvas" area while nested inside an
 *     inspector-style container.
 *   - None of a repeater's sub-fields, at any nesting depth, may declare
 *     "conditional" — ported from the parent's Repeater_Field
 *     (assert_no_conditional_sub_fields(), which recurses the same way),
 *     rejected for the same reason: repeater rows are dynamic, so a
 *     controller field outside the row cannot address one specific row's
 *     sibling value.
 *   - Field name uniqueness among all value-bearing leaves, since they all
 *     become block attributes sharing one flat namespace.
 *
 * @package Pedalcms\CassetteCmfBlocks
 * @since 0.1.0
 */

namespace Pedalcms\CassetteCmfBlocks\Core;

use Pedalcms\CassetteCmfBlocks\Compat\Cmf_Bridge;
use Pedalcms\CassetteCmfBlocks\Schema\Area_Resolver;
use Pedalcms\CassetteCmfBlocks\Schema\Control_Mapper;

/**
 * Class Field_Collection
 */
class Field_Collection {

	/**
	 * Container control types that behave like an inspector panel for
	 * nesting-rule purposes.
	 *
	 * @var string[]
	 */
	private const INSPECTOR_STYLE_CONTAINERS = [ 'panel', 'metabox', 'tab_panel', 'tab', 'group', 'row', 'stack', 'repeater' ];

	/**
	 * The raw, unmodified "fields" array as supplied by the consumer.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $raw_fields;

	/**
	 * Flattened value-bearing leaf entries. Each entry:
	 * {
	 *   config:          array<string, mixed>  Original field config.
	 *   area:            string                Resolved "<surface>[.<group>]".
	 *   control_type:    string                Resolved control type.
	 *   cmf_type:        string|null           Parent field type for sanitize()/validate() reuse.
	 *   path:            string[]              Ancestor field names, outermost first.
	 *   inside_repeater: bool                  Whether any ancestor container is a "repeater" — a row field's
	 *                                          value lives inside the repeater's own array attribute, so
	 *                                          Schema\Attribute_Schema_Mapper::from_fields() must skip these
	 *                                          when building the block's top-level attribute schema, rather
	 *                                          than mapping each row field as its own independent attribute.
	 * }
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $leaves = [];

	/**
	 * Build a collection from a block's raw "fields" array.
	 *
	 * @param array<int, array<string, mixed>> $raw_fields Raw field/panel entries.
	 * @throws \InvalidArgumentException On an unknown/banned control type, an illegal nesting, or a duplicate name.
	 */
	public function __construct( array $raw_fields ) {
		$this->raw_fields = $raw_fields;
		$this->walk( $raw_fields, null, null, false, [], null );
		$this->validate_unique_names();
	}

	/**
	 * The original, unflattened field/panel tree.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_tree(): array {
		return $this->raw_fields;
	}

	/**
	 * All flattened value-bearing leaf entries.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_leaves(): array {
		return $this->leaves;
	}

	/**
	 * Leaf entries whose control type is display-only (holds_value === false)
	 * are excluded from get_leaves(); this returns their count for tests/introspection.
	 *
	 * @return int
	 */
	public function count_value_bearing_leaves(): int {
		return count( $this->leaves );
	}

	/**
	 * Build the JSON-safe, nested tree the editor runtime renders from:
	 * every node's "type"/"area"/cmf type resolved the same way walk() does
	 * (area inheritance, control-type/cmf_type resolution via
	 * Control_Mapper), but preserving container nesting instead of
	 * flattening to leaves — Editor_Payload ships this verbatim as a
	 * block's "fields" entry. No validation happens here; that already ran
	 * in the constructor, so this is a pure read.
	 *
	 * A node's "conditional" — reachable at $node['config']['conditional'] —
	 * is normalized (via Compat\Cmf_Bridge::normalize_conditional(), still
	 * reusing the parent library verbatim) before shipping, exactly as the
	 * design plan specifies: "the payload ships the already-normalised
	 * {relation, rules[]} shape, so JS never sees 'equals' or 'contains'
	 * and only the evaluator (not the alias table) is duplicated"
	 * (conditions/evaluate.js). A raw "conditional" that normalizes to
	 * nothing (no real rules) is omitted entirely, so the editor's own
	 * "does this node have a condition" check is a plain isset().
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function to_editor_tree(): array {
		return $this->build_tree( $this->raw_fields, null );
	}

	/**
	 * Recursive helper for to_editor_tree().
	 *
	 * @param array<int, array<string, mixed>> $fields         Fields at this level.
	 * @param string|null                      $inherited_area Normalized area inherited from the nearest ancestor that declared one.
	 * @return array<int, array<string, mixed>>
	 */
	private function build_tree( array $fields, ?string $inherited_area ): array {
		$nodes = [];

		foreach ( $fields as $field_config ) {
			if ( ! is_array( $field_config ) ) {
				continue;
			}

			$resolved = Control_Mapper::resolve( $field_config );
			$area     = Area_Resolver::normalize( $field_config['area'] ?? $inherited_area );
			$config   = $field_config;
			unset( $config['fields'] );

			if ( isset( $config['conditional'] ) && is_array( $config['conditional'] ) ) {
				$normalized = Cmf_Bridge::normalize_conditional( $config['conditional'] );

				if ( empty( $normalized ) ) {
					unset( $config['conditional'] );
				} else {
					$config['conditional'] = $normalized;
				}
			}

			$node = [
				'type'        => $resolved['type'],
				'area'        => $area,
				'cmfType'     => $resolved['cmf_type'],
				'holdsValue'  => $resolved['spec']['holds_value'],
				'isContainer' => $resolved['spec']['is_container'],
				'config'      => $config,
			];

			if ( $resolved['spec']['is_container'] ) {
				$node['fields'] = $this->build_tree( $field_config['fields'] ?? [], $area );
			}

			$nodes[] = $node;
		}

		return $nodes;
	}

	/**
	 * Recursively walk the field tree, validating nesting rules and
	 * collecting value-bearing leaves.
	 *
	 * @param array<int, array<string, mixed>> $fields           Fields at this level.
	 * @param string|null                      $container_kind   'toolbar'|'inspector_style'|null for the immediately enclosing container.
	 * @param string|null                      $inherited_area   Normalized area inherited from the nearest ancestor that declared one, or null.
	 * @param bool                             $inside_repeater  Whether any ancestor container is a repeater (tracked separately from
	 *                                                           $container_kind, which only distinguishes toolbar vs. inspector-style).
	 * @param string[]                         $path             Ancestor field names, outermost first.
	 * @param string|null                      $parent_type      The immediately enclosing container's own control type (e.g. "tab_panel"),
	 *                                                           distinct from $container_kind's toolbar/inspector_style bucket — needed to
	 *                                                           enforce the tab_panel/tab pairing, which is stricter than the bucket-level rules.
	 * @return void
	 */
	private function walk( array $fields, ?string $container_kind, ?string $inherited_area, bool $inside_repeater, array $path, ?string $parent_type ): void {
		foreach ( $fields as $field_config ) {
			if ( ! is_array( $field_config ) ) {
				continue;
			}

			$resolved               = Control_Mapper::resolve( $field_config );
			$type                   = $resolved['type'];
			$spec                   = $resolved['spec'];
			$cmf_type               = $resolved['cmf_type'];
			$area                   = Area_Resolver::normalize( $field_config['area'] ?? $inherited_area );
			$is_toolbar_container   = 'toolbar_group' === $type;
			$is_inspector_container = in_array( $type, self::INSPECTOR_STYLE_CONTAINERS, true );

			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages don't need escaping.
			if ( 'toolbar' === $container_kind && $is_inspector_container ) {
				throw new \InvalidArgumentException(
					sprintf( 'Field "%s": a toolbar_group may not contain an inspector-style container ("%s").', $field_config['name'] ?? '', $type )
				);
			}

			if ( 'inspector_style' === $container_kind && $is_toolbar_container ) {
				throw new \InvalidArgumentException(
					sprintf( 'Field "%s": an inspector-style container may not contain a toolbar_group.', $field_config['name'] ?? '' )
				);
			}

			if ( 'inspector_style' === $container_kind && 'canvas' === Area_Resolver::parse( $area )['surface'] ) {
				throw new \InvalidArgumentException(
					sprintf( 'Field "%s": a canvas-area field may not appear inside an inspector-style container.', $field_config['name'] ?? '' )
				);
			}

			if ( 'tab_panel' === $parent_type && 'tab' !== $type ) {
				throw new \InvalidArgumentException(
					sprintf( 'Field "%s": a tab_panel\'s direct children must all be "tab" containers, got "%s".', $field_config['name'] ?? '', $type )
				);
			}

			if ( 'tab' === $type && 'tab_panel' !== $parent_type ) {
				throw new \InvalidArgumentException(
					sprintf( 'Field "%s": a "tab" may only appear as a direct child of a "tab_panel".', $field_config['name'] ?? '' )
				);
			}

			if ( $inside_repeater && ! empty( $field_config['conditional'] ) ) {
				throw new \InvalidArgumentException(
					sprintf(
						'Field "%s": repeater sub-fields cannot define conditional visibility. Conditional logic on a repeater row cannot address a specific row from outside it.',
						$field_config['name'] ?? ''
					)
				);
			}
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped

			$current_path = array_merge( $path, [ (string) ( $field_config['name'] ?? '' ) ] );

			if ( $spec['holds_value'] ) {
				$this->leaves[] = [
					'config'          => $field_config,
					'area'            => $area,
					'control_type'    => $type,
					'cmf_type'        => $cmf_type,
					'path'            => $path,
					'inside_repeater' => $inside_repeater,
				];
			}

			if ( $spec['is_container'] ) {
				$child_kind = $is_toolbar_container ? 'toolbar' : 'inspector_style';
				$this->walk( $field_config['fields'] ?? [], $child_kind, $area, $inside_repeater || 'repeater' === $type, $current_path, $type );
			}
		}
	}

	/**
	 * Ensure every value-bearing leaf has a unique "name" — they all become
	 * block attributes sharing one flat namespace.
	 *
	 * @return void
	 * @throws \InvalidArgumentException On a duplicate name.
	 */
	private function validate_unique_names(): void {
		$seen = [];

		foreach ( $this->leaves as $leaf ) {
			$name = $leaf['config']['name'] ?? null;

			if ( empty( $name ) ) {
				throw new \InvalidArgumentException( 'Every field must include "name".' );
			}

			if ( isset( $seen[ $name ] ) ) {
				// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages don't need escaping.
				throw new \InvalidArgumentException(
					sprintf( 'Duplicate field name "%s". Field names must be unique within a block.', $name )
				);
				// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}

			$seen[ $name ] = true;
		}
	}
}
