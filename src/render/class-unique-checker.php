<?php
/**
 * Unique_Checker class for Cassette-CMF Blocks
 *
 * The pure "is this field value unique?" rule behind a text-like field's
 * "unique" setting. The editor runs the exact same rule in JavaScript
 * (src/assets/src/editor/unique/find-collisions.js); both are checked
 * against one shared fixture, tests/fixtures/unique.json, so a consumer can
 * re-run the editor's check server side on saved content (for example in a
 * REST validation hook) and get the same answer.
 *
 * The rule:
 *
 *  - Values are trimmed, then the field's "format" ("slug", "upper",
 *    "lower") is applied, then compared case-sensitively.
 *  - An empty value never collides.
 *  - Scope "post": every participating block in the content shares one
 *    group. Scope "ancestor": blocks are grouped by their nearest ancestor
 *    whose block name equals the rule's "block", at any depth; blocks with
 *    no such ancestor form one separate "unscoped" group.
 *
 * Which blocks and attributes take part is the caller's decision (the
 * "participants" argument), because the uniqueness group is a config concept
 * ("key"): every block type that declares a unique field with the same key
 * and the same scope rule is checked together.
 *
 * @package PedalCMS\CassetteCMFBlocks
 * @since 0.2.0
 */

namespace PedalCMS\CassetteCMFBlocks\Render;

/**
 * Class Unique_Checker
 */
class Unique_Checker {

	/**
	 * Scope: unique across the whole post content.
	 *
	 * @var string
	 */
	public const SCOPE_POST = 'post';

	/**
	 * Scope: unique among blocks sharing the nearest ancestor of a given block name.
	 *
	 * @var string
	 */
	public const SCOPE_ANCESTOR = 'ancestor';

	/**
	 * Control types a "unique" setting is allowed on (string-valued text inputs).
	 *
	 * @var string[]
	 */
	public const SUPPORTED_CONTROLS = [ 'text', 'email', 'url' ];

	/**
	 * Normalize and validate a field's "unique" setting.
	 *
	 * Accepted: false/null/absent (off); true (whole post, key = field name);
	 * or an array with "scope" ("post" default, or "ancestor"), "block" (the
	 * ancestor block name, required for and only valid with "ancestor"; given
	 * alone it implies the ancestor scope) and an optional "key".
	 *
	 * @api
	 * @param mixed  $unique     Raw "unique" value from a field config.
	 * @param string $field_name The field's name, the default uniqueness key.
	 * @return array{scope: string, block?: string, key: string}|null Canonical rule, or null when off.
	 * @throws \InvalidArgumentException On a malformed setting.
	 */
	public static function normalize_rule( $unique, string $field_name ): ?array {
		if ( null === $unique || false === $unique ) {
			return null;
		}

		if ( true === $unique ) {
			return [
				'scope' => self::SCOPE_POST,
				'key'   => $field_name,
			];
		}

		// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages don't need escaping.
		if ( ! is_array( $unique ) ) {
			throw new \InvalidArgumentException(
				sprintf( 'Field "%s": "unique" must be true, false or an object with "scope", "block" and "key".', $field_name )
			);
		}

		$unknown = array_diff( array_keys( $unique ), [ 'scope', 'block', 'key' ] );
		if ( ! empty( $unknown ) ) {
			throw new \InvalidArgumentException(
				sprintf( 'Field "%1$s": unknown "unique" setting(s): %2$s.', $field_name, implode( ', ', array_map( 'strval', $unknown ) ) )
			);
		}

		$scope = $unique['scope'] ?? ( isset( $unique['block'] ) ? self::SCOPE_ANCESTOR : self::SCOPE_POST );

		if ( ! in_array( $scope, [ self::SCOPE_POST, self::SCOPE_ANCESTOR ], true ) ) {
			throw new \InvalidArgumentException(
				sprintf( 'Field "%s": unique.scope must be "post" or "ancestor".', $field_name )
			);
		}

		$key = $unique['key'] ?? $field_name;
		if ( ! is_string( $key ) || '' === $key ) {
			throw new \InvalidArgumentException(
				sprintf( 'Field "%s": unique.key must be a non-empty string.', $field_name )
			);
		}

		$rule = [
			'scope' => $scope,
			'key'   => $key,
		];

		if ( self::SCOPE_ANCESTOR === $scope ) {
			$block = $unique['block'] ?? null;
			if ( ! is_string( $block ) || 1 !== preg_match( '#^[a-z][a-z0-9-]*/[a-z][a-z0-9-]*$#', $block ) ) {
				throw new \InvalidArgumentException(
					sprintf( 'Field "%s": unique.block must be a namespaced block name (e.g. "vendor/form") when scope is "ancestor".', $field_name )
				);
			}

			$rule = [
				'scope' => $scope,
				'block' => $block,
				'key'   => $key,
			];
		} elseif ( isset( $unique['block'] ) ) {
			throw new \InvalidArgumentException(
				sprintf( 'Field "%s": unique.block is only valid with scope "ancestor".', $field_name )
			);
		}
		// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped

		return $rule;
	}

	/**
	 * Normalize a raw value for comparison: trim, then apply the field's format.
	 *
	 * @api
	 * @param mixed       $value  Raw attribute value.
	 * @param string|null $format Field "format": "slug", "upper", "lower" or null.
	 * @return string The comparable value; '' for empty or non-string input.
	 */
	public static function normalize_value( $value, ?string $format = null ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$value = trim( $value );

		if ( 'slug' === $format ) {
			return trim( (string) preg_replace( '/[^a-z0-9]+/', '-', strtolower( $value ) ), '-' );
		}

		if ( 'upper' === $format ) {
			return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $value ) : strtoupper( $value );
		}

		if ( 'lower' === $format ) {
			return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value ) : strtolower( $value );
		}

		return $value;
	}

	/**
	 * Find the colliding values in a parsed block tree.
	 *
	 * Groups are returned in document order of their first entry; entries
	 * inside a group are in document order. By convention the first entry is
	 * the one a consumer keeps, and the rest are the duplicates.
	 *
	 * @api
	 * @param array<int, array<string, mixed>>                                $blocks       parse_blocks() output (or any tree with "blockName", "attrs", "innerBlocks").
	 * @param array<string, array{attribute: string, format?: string|null}>   $participants Block name => the attribute holding the value and its optional format.
	 * @param array{scope: string, block?: string}                            $rule         Canonical rule (see normalize_rule()); only "scope" and "block" are read.
	 * @return array<int, array{value: string, entries: array<int, array{path: int[], blockName: string, value: string}>}>
	 */
	public static function find_collisions( array $blocks, array $participants, array $rule ): array {
		$scope_block = self::SCOPE_ANCESTOR === ( $rule['scope'] ?? self::SCOPE_POST ) ? ( $rule['block'] ?? null ) : null;
		$by_scope    = [];
		$counter     = 0;

		self::collect( $blocks, [], [], $participants, $scope_block, $by_scope, $counter );

		$collisions = [];

		foreach ( $by_scope as $values ) {
			foreach ( $values as $normalized => $entries ) {
				if ( count( $entries ) < 2 ) {
					continue;
				}

				$collisions[] = [
					'order'   => $entries[0]['order'],
					'value'   => (string) $normalized,
					'entries' => array_map(
						static function ( array $entry ): array {
							return [
								'path'      => $entry['path'],
								'blockName' => $entry['blockName'],
								'value'     => $entry['value'],
							];
						},
						$entries
					),
				];
			}
		}

		usort(
			$collisions,
			static function ( array $a, array $b ): int {
				return $a['order'] <=> $b['order'];
			}
		);

		return array_map(
			static function ( array $group ): array {
				unset( $group['order'] );
				return $group;
			},
			$collisions
		);
	}

	/**
	 * Depth-first walk collecting participating, non-empty values per scope group.
	 *
	 * @param array<int, array<string, mixed>>   $blocks       Sibling blocks.
	 * @param int[]                              $path         Index path of the parent.
	 * @param array<int, array{name: string, path: int[]}> $ancestors Ancestor stack, outermost first.
	 * @param array<string, array<string, mixed>> $participants Participants map.
	 * @param string|null                        $scope_block  Ancestor block name for the ancestor scope, null for the post scope.
	 * @param array<string, array<string, array<int, array<string, mixed>>>> $by_scope Output: scope id => normalized value => entries.
	 * @param int                                $counter      Running document-order counter.
	 * @return void
	 */
	private static function collect( array $blocks, array $path, array $ancestors, array $participants, ?string $scope_block, array &$by_scope, int &$counter ): void {
		foreach ( array_values( $blocks ) as $index => $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$block_path = array_merge( $path, [ $index ] );
			$name       = is_string( $block['blockName'] ?? null ) ? $block['blockName'] : '';

			if ( '' !== $name && isset( $participants[ $name ]['attribute'] ) ) {
				$participant = $participants[ $name ];
				$raw         = is_array( $block['attrs'] ?? null ) ? ( $block['attrs'][ $participant['attribute'] ] ?? '' ) : '';
				$normalized  = self::normalize_value( $raw, $participant['format'] ?? null );

				if ( '' !== $normalized ) {
					$scope_id = self::scope_id( $ancestors, $scope_block );

					$by_scope[ $scope_id ][ $normalized ][] = [
						'order'     => $counter,
						'path'      => $block_path,
						'blockName' => $name,
						'value'     => is_string( $raw ) ? $raw : '',
					];
				}
			}

			++$counter;

			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$next = $ancestors;
				if ( '' !== $name ) {
					$next[] = [
						'name' => $name,
						'path' => $block_path,
					];
				}

				self::collect( $block['innerBlocks'], $block_path, $next, $participants, $scope_block, $by_scope, $counter );
			}
		}
	}

	/**
	 * The scope-group id for a block: "post" for the whole-post scope, the
	 * nearest matching ancestor's path for the ancestor scope, or a shared
	 * "unscoped" id when no such ancestor exists.
	 *
	 * @param array<int, array{name: string, path: int[]}> $ancestors   Ancestor stack, outermost first.
	 * @param string|null                                  $scope_block Ancestor block name, or null for the post scope.
	 * @return string
	 */
	private static function scope_id( array $ancestors, ?string $scope_block ): string {
		if ( null === $scope_block ) {
			return 'post';
		}

		for ( $i = count( $ancestors ) - 1; $i >= 0; $i-- ) {
			if ( $ancestors[ $i ]['name'] === $scope_block ) {
				return 'ancestor:' . implode( '.', $ancestors[ $i ]['path'] );
			}
		}

		return 'unscoped';
	}
}
