<?php
/**
 * Transform_Config class for Cassette-CMF Blocks
 *
 * Validates the `shortcode` and `raw` entries of a block's `transforms.from`
 * list (the entries that call a PHP callback through the REST route
 * `Rest\Transform_Controller`) and builds their client-safe payload form.
 *
 * Entries:
 *
 *   { type: 'shortcode', tag: 'some-tag', callback: callable }
 *   { type: 'raw', selector: 'div.some-class' | { tag?, class?, attrs? }, callback: callable }
 *
 * `type: 'block'` entries are untouched (pure JS remaps). An entry's identity
 * for the REST route is its position (index) in `transforms.from`; the
 * payload keeps every position, so the editor and the route always agree.
 *
 * @package PedalCMS\CassetteCMFBlocks
 * @since 0.2.0
 */

namespace PedalCMS\CassetteCMFBlocks\Schema;

/**
 * Class Transform_Config
 */
class Transform_Config {

	/**
	 * Transform types that call a PHP callback.
	 */
	public const REMOTE_TYPES = [ 'shortcode', 'raw' ];

	/**
	 * Validate the remote entries of a block's raw `transforms` config.
	 *
	 * @param mixed  $transforms Raw `transforms` value.
	 * @param string $block_name Block name, for error messages.
	 * @return void
	 * @throws \InvalidArgumentException When a shortcode / raw entry is malformed.
	 */
	public static function validate( $transforms, string $block_name ): void {
		if ( ! is_array( $transforms ) || ! is_array( $transforms['from'] ?? null ) ) {
			return;
		}

		foreach ( $transforms['from'] as $index => $entry ) {
			if ( ! is_array( $entry ) || ! in_array( $entry['type'] ?? null, self::REMOTE_TYPES, true ) ) {
				continue;
			}

			$where = "Block \"{$block_name}\" transforms.from[{$index}]";

			if ( ! isset( $entry['callback'] ) || ! is_callable( $entry['callback'] ) ) {
				throw new \InvalidArgumentException( "{$where} ({$entry['type']}) needs a PHP-callable \"callback\"." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}

			if ( 'shortcode' === $entry['type'] ) {
				if ( ! isset( $entry['tag'] ) || ! is_string( $entry['tag'] ) || ! preg_match( '/^[A-Za-z0-9_-]+$/', $entry['tag'] ) ) {
					throw new \InvalidArgumentException( "{$where} (shortcode) needs a \"tag\" made of letters, digits, \"_\" and \"-\"." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				}
				continue;
			}

			$selector = $entry['selector'] ?? null;
			if ( is_string( $selector ) && '' !== trim( $selector ) ) {
				continue;
			}
			if ( is_array( $selector ) && self::is_match_rule( $selector ) ) {
				continue;
			}

			throw new \InvalidArgumentException( "{$where} (raw) needs a \"selector\": a CSS selector string, or a rule { tag?, class?, attrs? } with at least one key." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
	}

	/**
	 * Whether a raw selector rule object is well formed:
	 * { tag?: string, class?: string|string[], attrs?: { name: string } }, not empty.
	 *
	 * @param array<mixed> $rule Rule.
	 * @return bool
	 */
	private static function is_match_rule( array $rule ): bool {
		$known = array_intersect_key( $rule, array_flip( [ 'tag', 'class', 'attrs' ] ) );
		if ( empty( $known ) || count( $known ) !== count( $rule ) ) {
			return false;
		}

		if ( isset( $rule['tag'] ) && ( ! is_string( $rule['tag'] ) || ! preg_match( '/^[A-Za-z][A-Za-z0-9-]*$/', $rule['tag'] ) ) ) {
			return false;
		}

		if ( isset( $rule['class'] ) ) {
			$classes = (array) $rule['class'];
			foreach ( $classes as $class ) {
				if ( ! is_string( $class ) || ! preg_match( '/^[A-Za-z0-9_-]+$/', $class ) ) {
					return false;
				}
			}
		}

		if ( isset( $rule['attrs'] ) ) {
			if ( ! is_array( $rule['attrs'] ) ) {
				return false;
			}
			foreach ( $rule['attrs'] as $name => $value ) {
				if ( ! is_string( $name ) || ! is_scalar( $value ) ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * The editor payload form of a block's `transforms`: callbacks are
	 * removed (they are PHP callables) and each shortcode / raw entry is
	 * marked `remote: true`. Positions are preserved.
	 *
	 * @param mixed $transforms Raw `transforms` value.
	 * @return array<string, mixed>|null
	 */
	public static function to_payload( $transforms ): ?array {
		if ( ! is_array( $transforms ) ) {
			return null;
		}

		foreach ( [ 'from', 'to' ] as $direction ) {
			if ( ! isset( $transforms[ $direction ] ) || ! is_array( $transforms[ $direction ] ) ) {
				continue;
			}

			$entries = [];
			foreach ( $transforms[ $direction ] as $entry ) {
				if ( is_array( $entry ) && in_array( $entry['type'] ?? null, self::REMOTE_TYPES, true ) ) {
					unset( $entry['callback'] );
					$entry['remote'] = true;
				}
				$entries[] = $entry;
			}
			$transforms[ $direction ] = $entries;
		}

		return $transforms;
	}

	/**
	 * Find the configured remote entry at a position of `transforms.from`.
	 *
	 * @param array<string, mixed> $raw_config A block's raw config.
	 * @param int                  $index      Position in `transforms.from`.
	 * @return array<string, mixed>|null The entry, or null when the position is not a shortcode / raw entry with a callable callback.
	 */
	public static function find_entry( array $raw_config, int $index ): ?array {
		$from = $raw_config['transforms']['from'] ?? null;

		if ( ! is_array( $from ) || ! isset( $from[ $index ] ) || ! is_array( $from[ $index ] ) ) {
			return null;
		}

		$entry = $from[ $index ];

		if ( ! in_array( $entry['type'] ?? null, self::REMOTE_TYPES, true ) || ! isset( $entry['callback'] ) || ! is_callable( $entry['callback'] ) ) {
			return null;
		}

		return $entry;
	}
}
