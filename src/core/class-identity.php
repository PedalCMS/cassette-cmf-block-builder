<?php
/**
 * Identity class for Cassette-CMF Blocks
 *
 * The one place every identifier that must not collide between two copies of
 * this library on one site is derived from the boot-time `prefix` option.
 * Nothing else in the library spells a handle, REST namespace, binding source
 * name or JS global by hand.
 *
 * With `{p}` the prefix and `{P}` its camelCase form:
 *
 * | Identifier                 | Value                          |
 * |----------------------------|--------------------------------|
 * | editor script handle       | `{p}-blocks-editor`            |
 * | editor style handle        | `{p}-blocks-editor-style`      |
 * | REST namespace             | `{p}-blocks/v1`                |
 * | block bindings source      | `{p}/field`                    |
 * | JS payload global          | `cassetteCmfBlocks_{P}`        |
 * | payload object-cache group | `cassette_cmf_blocks_{p_snake}` |
 *
 * Shared on purpose (not derived): the text domain (this library's own
 * translations) and the `cassette_cmf_blocks_*` hooks.
 *
 * @package PedalCMS\CassetteCMFBlocks
 * @since 0.2.0
 */

namespace PedalCMS\CassetteCMFBlocks\Core;

/**
 * Class Identity
 *
 * @api
 */
class Identity {

	/**
	 * Prefix used when none, or an invalid one, was given to boot().
	 *
	 * @var string
	 */
	public const FALLBACK_PREFIX = 'cassette-cmf';

	/**
	 * Allowed prefix shape: 3-32 characters, lowercase letters, digits and
	 * hyphens, starting with a letter and not ending with a hyphen.
	 *
	 * @var string
	 */
	public const PREFIX_PATTERN = '/^[a-z][a-z0-9-]{1,30}[a-z0-9]$/';

	/**
	 * Name of the one-shot `window` property that tells the editor bundle
	 * which global holds its payload. The bundle reads and deletes it.
	 *
	 * @var string
	 */
	public const JS_BOOT_KEY = 'cassetteCmfBlocksBoot';

	/**
	 * Text domain. This library's own, deliberately shared by every copy.
	 *
	 * @var string
	 */
	public const TEXT_DOMAIN = 'cassette-cmf-block-builder';

	/**
	 * Whether a value is an acceptable prefix.
	 *
	 * @param mixed $prefix Candidate.
	 * @return bool
	 */
	public static function is_valid_prefix( $prefix ): bool {
		return is_string( $prefix ) && 1 === preg_match( self::PREFIX_PATTERN, $prefix );
	}

	/**
	 * The active prefix (the boot option, or the fallback).
	 *
	 * @return string
	 */
	public static function prefix(): string {
		$prefix = Block_Manager::get_option( 'prefix' );

		return self::is_valid_prefix( $prefix ) ? $prefix : self::FALLBACK_PREFIX;
	}

	/**
	 * Editor script handle.
	 *
	 * @return string
	 */
	public static function editor_script_handle(): string {
		return self::prefix() . '-blocks-editor';
	}

	/**
	 * Editor style handle.
	 *
	 * @return string
	 */
	public static function editor_style_handle(): string {
		return self::prefix() . '-blocks-editor-style';
	}

	/**
	 * REST namespace.
	 *
	 * @return string
	 */
	public static function rest_namespace(): string {
		return self::prefix() . '-blocks/v1';
	}

	/**
	 * Block bindings source name.
	 *
	 * @return string
	 */
	public static function binding_source_name(): string {
		return self::prefix() . '/field';
	}

	/**
	 * Name of the `window` property holding this copy's editor payload.
	 *
	 * Hyphen-separated words are camel-cased ("my-shop" gives "myShop"). A word
	 * that starts with a digit keeps an underscore in front of it, so that
	 * "a-1b" ("a_1b") never collides with the prefix "a1b".
	 *
	 * @return string
	 */
	public static function js_global(): string {
		$words = explode( '-', self::prefix() );
		$name  = array_shift( $words );

		foreach ( $words as $word ) {
			$name .= ctype_digit( $word[0] ) ? '_' . $word : ucfirst( $word );
		}

		return 'cassetteCmfBlocks_' . $name;
	}

	/**
	 * Object-cache group of the cached editor payload (also keys the
	 * transient fallback, through the cache key).
	 *
	 * @return string
	 */
	public static function payload_cache_group(): string {
		return 'cassette_cmf_blocks_' . str_replace( '-', '_', self::prefix() );
	}
}
