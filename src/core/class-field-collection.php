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
 *   - A "source": "context" field names its block-context key in "context".
 *   - A "unique" setting is well formed and sits on a text-like control.
 *
 * It also normalizes a field's "options" once, up front, into the canonical
 * value => label map (a list of { value, label } objects is accepted too), so
 * the attribute schema, the sanitizer and the editor payload all see one shape.
 *
 * @package PedalCMS\CassetteCMFBlocks
 * @since 0.2.0
 */

namespace PedalCMS\CassetteCMFBlocks\Core;

use PedalCMS\CassetteCMFBlocks\Compat\Cmf_Bridge;
use PedalCMS\CassetteCMFBlocks\Render\Unique_Checker;
use PedalCMS\CassetteCMFBlocks\Schema\Area_Resolver;
use PedalCMS\CassetteCMFBlocks\Schema\Ancestor_Options;
use PedalCMS\CassetteCMFBlocks\Schema\Control_Mapper;

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
		$this->raw_fields = self::normalize_fields( $raw_fields );
		$raw_fields       = $this->raw_fields;
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
	 * Context-sourced fields: field name => block-context key.
	 *
	 * Excludes repeater row fields (a row has no block context of its own).
	 * These fields have no attribute; their value is read from the block's
	 * context at render time.
	 *
	 * @return array<string, string>
	 */
	public function get_context_fields(): array {
		$fields = [];

		foreach ( $this->leaves as $leaf ) {
			$config = $leaf['config'];

			if ( ! empty( $leaf['inside_repeater'] ) || 'context' !== ( $config['source'] ?? 'attribute' ) ) {
				continue;
			}

			$fields[ (string) $config['name'] ] = (string) $config['context'];
		}

		return $fields;
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

			if ( $spec['holds_value'] && 'context' === ( $field_config['source'] ?? 'attribute' ) && ( ! is_string( $field_config['context'] ?? null ) || '' === $field_config['context'] ) ) {
				throw new \InvalidArgumentException(
					sprintf( 'Field "%s": source "context" requires "context" to name a block-context key, e.g. "example/formId".', $field_config['name'] ?? '' )
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
	 * Recursively normalize a raw field tree: "options" into the canonical
	 * value => label map, and "unique" into its canonical rule.
	 *
	 * @param array<int, mixed> $fields Raw fields at one level.
	 * @return array<int, mixed>
	 * @throws \InvalidArgumentException On a malformed or misplaced "unique".
	 */
	private static function normalize_fields( array $fields ): array {
		foreach ( $fields as $index => $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			if ( array_key_exists( 'options', $field ) ) {
				$options = self::normalize_options( $field['options'], (string) ( $field['name'] ?? '' ) );

				if ( null === $options ) {
					unset( $field['options'] );
				} else {
					$field['options'] = $options;
				}
			}

			if ( array_key_exists( 'unique', $field ) ) {
				$field = self::normalize_unique( $field );
			}

			$field = self::normalize_ancestor_options( $field );

			if ( isset( $field['fields'] ) && is_array( $field['fields'] ) ) {
				$field['fields'] = self::normalize_fields( $field['fields'] );
			}

			$fields[ $index ] = $field;
		}

		return $fields;
	}

	/**
	 * Canonicalize the ancestor options of an "ancestor_field" control
	 * (required: "ancestor") and of a "conditions" control's optional
	 * "conditions" setting.
	 *
	 * @param array<string, mixed> $field Raw field config.
	 * @return array<string, mixed>
	 * @throws \InvalidArgumentException On missing or malformed options.
	 */
	private static function normalize_ancestor_options( array $field ): array {
		$type = (string) ( $field['type'] ?? '' );
		$name = (string) ( $field['name'] ?? '' );

		if ( 'ancestor_field' === $type ) {
			$options = Ancestor_Options::normalize( $field, $name, 'ancestor_field' );
			// Keep the field's own keys (name, label, ...), replace the option keys with their canonical form.
			return array_merge( $field, $options );
		}

		if ( 'conditions' === $type && array_key_exists( 'conditions', $field ) ) {
			$field['conditions'] = Ancestor_Options::normalize( $field['conditions'], $name, 'conditions' );
		}

		return $field;
	}

	/**
	 * Normalize one field's "unique" setting in place of the raw value.
	 *
	 * @param array<string, mixed> $field Raw field config containing "unique".
	 * @return array<string, mixed>
	 * @throws \InvalidArgumentException On a malformed setting or an unsupported control type.
	 */
	private static function normalize_unique( array $field ): array {
		$name = (string) ( $field['name'] ?? '' );
		$rule = Unique_Checker::normalize_rule( $field['unique'], $name );

		if ( null === $rule ) {
			unset( $field['unique'] );

			return $field;
		}

		$type = (string) ( $field['type'] ?? '' );
		if ( ! in_array( $type, Unique_Checker::SUPPORTED_CONTROLS, true ) ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages don't need escaping.
			throw new \InvalidArgumentException(
				sprintf( 'Field "%1$s": "unique" is only supported on %2$s controls, not "%3$s".', $name, implode( '/', Unique_Checker::SUPPORTED_CONTROLS ), $type )
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$field['unique'] = $rule;

		return $field;
	}

	/**
	 * Normalize a field's "options" into the canonical value => label map.
	 *
	 * Accepts the map form as is, and a list of { value, label } objects
	 * ("label" defaults to the value). Anything else raises
	 * _doing_it_wrong() and yields null (the options are dropped).
	 *
	 * @param mixed  $options    Raw "options" value.
	 * @param string $field_name The field's name, for the notice.
	 * @return array<string|int, mixed>|null
	 */
	private static function normalize_options( $options, string $field_name ): ?array {
		if ( ! is_array( $options ) ) {
			self::options_notice( $field_name, 'must be a value => label map or a list of { value, label } objects.' );

			return null;
		}

		$is_list = [] === $options || array_keys( $options ) === range( 0, count( $options ) - 1 );

		$has_objects = false;
		foreach ( $options as $item ) {
			if ( is_array( $item ) ) {
				$has_objects = true;
				break;
			}
		}

		if ( ! $is_list || ! $has_objects ) {
			return $options;
		}

		$map = [];

		foreach ( $options as $item ) {
			$value = is_array( $item ) ? ( $item['value'] ?? null ) : null;

			if ( ! is_string( $value ) && ! is_int( $value ) && ! is_float( $value ) ) {
				self::options_notice( $field_name, 'list items must be { value, label } objects with a string or number "value".' );

				return null;
			}

			if ( array_key_exists( (string) $value, $map ) ) {
				self::options_notice( $field_name, sprintf( 'has the value "%s" more than once.', $value ) );

				continue;
			}

			$label                  = $item['label'] ?? (string) $value;
			$map[ (string) $value ] = is_scalar( $label ) ? (string) $label : (string) $value;
		}

		return $map;
	}

	/**
	 * Raise a developer notice about a malformed "options" value.
	 *
	 * @param string $field_name Field name.
	 * @param string $problem    What is wrong.
	 * @return void
	 */
	private static function options_notice( string $field_name, string $problem ): void {
		if ( function_exists( '_doing_it_wrong' ) ) {
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- _doing_it_wrong() messages are developer-facing debug notices, not page output.
			_doing_it_wrong(
				__CLASS__,
				sprintf( 'Field "%1$s": "options" %2$s', $field_name, $problem ),
				'0.2.0'
			);
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
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
