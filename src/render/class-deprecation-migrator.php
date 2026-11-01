<?php
/**
 * Deprecation_Migrator class for Cassette-CMF Blocks
 *
 * Server-side half of "render.deprecated" for `mode: 'dynamic'` blocks.
 * The editor migrates stored blocks through registerBlockType()'s
 * `deprecated` array; on the front end nothing re-saves the post, so the
 * same migration has to run on the attributes of stored (old-shape) markup
 * before the render pipeline sanitizes them. This class holds the pure
 * functions for that. The declarative migration and the eligibility rule are
 * mirrored 1:1 by src/assets/src/editor/migrate.js, and both are tested
 * against tests/fixtures/deprecations.json.
 *
 * Entry shape (the same keys static mode uses, see docs/static-save.md):
 *
 *     [
 *         'attributes' => [ ...old WP attribute schema... ],
 *         'migrate'    => [ 'rename' => [ old => new ], 'map' => [ key => [ from => to ] ],
 *                           'set' => [ key => value ], 'remove' => [ key, ... ] ]
 *                         | 'namedMigration',   // resolved in the editor only
 *         'migrate_php' => callable( array $attributes, array $entry ): array, // server only
 *     ]
 *
 * @package PedalCMS\CassetteCMFBlocks
 * @since 0.2.0
 */

namespace PedalCMS\CassetteCMFBlocks\Render;

/**
 * Class Deprecation_Migrator
 */
class Deprecation_Migrator {

	/**
	 * Keys a declarative migration may contain.
	 *
	 * @var string[]
	 */
	public const MIGRATE_KEYS = [ 'rename', 'map', 'set', 'remove' ];

	/**
	 * Apply a declarative migration to a set of attributes. Pure.
	 *
	 * Order: rename, map, set, remove. A rename overwrites the target even if
	 * it already holds a value (WordPress fills defaults before the render
	 * callback runs, so the target is usually present). `map` keys name the
	 * attribute after renaming; values are compared as strings (booleans as
	 * "true"/"false"), and an unmapped value is left alone.
	 *
	 * @api
	 * @param array<string, mixed> $spec       Declarative migration: rename / map / set / remove.
	 * @param array<string, mixed> $attributes Attributes in the old shape.
	 * @return array<string, mixed> Attributes in the new shape.
	 */
	public static function apply_declarative( array $spec, array $attributes ): array {
		foreach ( self::as_array( $spec['rename'] ?? null ) as $old => $new ) {
			if ( is_string( $new ) && array_key_exists( (string) $old, $attributes ) ) {
				$attributes[ $new ] = $attributes[ $old ];
				unset( $attributes[ $old ] );
			}
		}

		foreach ( self::as_array( $spec['map'] ?? null ) as $key => $table ) {
			$key = (string) $key;
			if ( ! is_array( $table ) || ! array_key_exists( $key, $attributes ) ) {
				continue;
			}
			$token = self::value_token( $attributes[ $key ] );
			if ( null !== $token && array_key_exists( $token, $table ) ) {
				$attributes[ $key ] = $table[ $token ];
			}
		}

		foreach ( self::as_array( $spec['set'] ?? null ) as $key => $value ) {
			$attributes[ (string) $key ] = $value;
		}

		foreach ( self::as_array( $spec['remove'] ?? null ) as $key ) {
			if ( is_string( $key ) ) {
				unset( $attributes[ $key ] );
			}
		}

		return $attributes;
	}

	/**
	 * Whether a deprecation entry applies to a set of attributes. Pure.
	 *
	 * An entry is eligible when the attributes still carry something of the
	 * old shape: a key that the entry's old schema (`attributes`), its
	 * `rename` sources or its `remove` list name but the current schema does
	 * not, or a value that one of its `map` tables would change. `set` alone
	 * never makes an entry eligible.
	 *
	 * @api
	 * @param array<string, mixed> $entry              The deprecation entry.
	 * @param string[]             $current_attributes Names in the block's current attribute schema.
	 * @param array<string, mixed> $attributes         The stored attributes.
	 * @return bool
	 */
	public static function is_eligible( array $entry, array $current_attributes, array $attributes ): bool {
		$old_keys = array_keys( self::as_array( $entry['attributes'] ?? null ) );
		$spec     = is_array( $entry['migrate'] ?? null ) ? $entry['migrate'] : [];

		foreach ( array_keys( self::as_array( $spec['rename'] ?? null ) ) as $source ) {
			$old_keys[] = $source;
		}
		foreach ( self::as_array( $spec['remove'] ?? null ) as $key ) {
			if ( is_string( $key ) ) {
				$old_keys[] = $key;
			}
		}

		foreach ( $old_keys as $key ) {
			$key = (string) $key;
			if ( ! in_array( $key, $current_attributes, true ) && array_key_exists( $key, $attributes ) ) {
				return true;
			}
		}

		foreach ( self::as_array( $spec['map'] ?? null ) as $key => $table ) {
			$key = (string) $key;
			if ( ! is_array( $table ) || ! array_key_exists( $key, $attributes ) ) {
				continue;
			}
			$token = self::value_token( $attributes[ $key ] );
			if ( null !== $token && array_key_exists( $token, $table ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Run every eligible entry, in declaration order, against the attributes.
	 *
	 * Per entry: the declarative `migrate` array (if any), then `migrate_php`
	 * (if callable). A string `migrate` is an editor-side name and is ignored
	 * here; use `migrate_php` for the server.
	 *
	 * @api
	 * @param array<int, mixed>    $entries            The block's `render.deprecated` entries.
	 * @param string[]             $current_attributes Names in the block's current attribute schema.
	 * @param array<string, mixed> $attributes         The stored attributes.
	 * @return array<string, mixed>
	 */
	public static function migrate( array $entries, array $current_attributes, array $attributes ): array {
		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) || ! self::is_eligible( $entry, $current_attributes, $attributes ) ) {
				continue;
			}

			if ( is_array( $entry['migrate'] ?? null ) ) {
				$attributes = self::apply_declarative( $entry['migrate'], $attributes );
			}

			if ( isset( $entry['migrate_php'] ) && is_callable( $entry['migrate_php'] ) ) {
				$result = call_user_func( $entry['migrate_php'], $attributes, $entry );
				if ( is_array( $result ) ) {
					$attributes = $result;
				}
			}
		}

		return $attributes;
	}

	/**
	 * String token a value is looked up by in a `map` table.
	 *
	 * @param mixed $value Attribute value.
	 * @return string|null Null for arrays/objects/null (never mapped).
	 */
	private static function value_token( $value ): ?string {
		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}

		return is_scalar( $value ) ? (string) $value : null;
	}

	/**
	 * Coerce a possibly missing / malformed config value to an array.
	 *
	 * @param mixed $value Config value.
	 * @return array<int|string, mixed>
	 */
	private static function as_array( $value ): array {
		return is_array( $value ) ? $value : [];
	}
}
