<?php
/**
 * Expression class for Cassette-CMF Blocks
 *
 * Evaluates the mustache-lite `{{ path | filter1 | filter2:arg }}` tokens
 * used inside a markup node's "class"/"attrs"/"text"/"html" values.
 * Deliberately a closed, fixed filter set (see FILTERS below) — no
 * arbitrary evaluation, no PHP callables reachable from config. This class
 * and markup/expr.js are kept honest against the same fixture file
 * (tests/fixtures/markup.json) precisely because a filter behaving
 * differently between the two languages would silently desync the front-end
 * render from the editor preview.
 *
 * @package PedalCMS\CassetteCMFBlocks
 * @since 0.1.0
 */

namespace PedalCMS\CassetteCMFBlocks\Render;

/**
 * Class Expression
 */
class Expression {

	/**
	 * Matches one or more "{{ ... }}" tokens within a template string.
	 *
	 * @var string
	 */
	private const TOKEN_PATTERN = '/\{\{\s*(.+?)\s*\}\}/s';

	/**
	 * Interpolate every "{{ ... }}" token in a template string against a
	 * scope, returning the fully-resolved plain string. This is the entry
	 * point Markup_Renderer calls for "class"/"attrs"/"text"/"html" values;
	 * the caller is responsible for escaping the result appropriately for
	 * where it lands (see Markup_Renderer's per-node-kind escaping).
	 *
	 * @param string               $template Template string, e.g. "acme-callout acme-callout--{{ attributes.tone }}".
	 * @param array<string, mixed> $scope    Value scope, e.g. [ 'attributes' => [...], 'item' => [...] ].
	 * @return string
	 */
	public static function interpolate( string $template, array $scope ): string {
		return (string) preg_replace_callback(
			self::TOKEN_PATTERN,
			static function ( array $matches ) use ( $scope ) {
				return self::stringify( self::evaluate( $matches[1], $scope ) );
			},
			$template
		);
	}

	/**
	 * Evaluate a single "path | filter1 | filter2:arg" expression (the
	 * inside of one "{{ ... }}" token) against a scope, returning the raw
	 * (not yet stringified) resulting value. Exposed publicly, not just via
	 * interpolate(), because Markup_Renderer's "repeat.over" needs the raw
	 * array value a path resolves to, not a stringified one.
	 *
	 * @param string               $expression e.g. "attributes.tone | default:neutral | upper".
	 * @param array<string, mixed> $scope      Value scope.
	 * @return mixed
	 */
	public static function evaluate( string $expression, array $scope ) {
		$segments = array_map( 'trim', explode( '|', $expression ) );
		$path     = array_shift( $segments );
		$value    = self::resolve_path( (string) $path, $scope );

		foreach ( $segments as $filter_expression ) {
			if ( '' === $filter_expression ) {
				continue;
			}

			$value = self::apply_filter( $filter_expression, $value );
		}

		return $value;
	}

	/**
	 * Resolve a dot-notation path (e.g. "attributes.tone") against a scope.
	 * A missing path resolves to null rather than throwing — a typo in an
	 * expression shouldn't take down the whole render.
	 *
	 * @param string               $path  Dot-notation path.
	 * @param array<string, mixed> $scope Value scope.
	 * @return mixed
	 */
	private static function resolve_path( string $path, array $scope ) {
		$current = $scope;

		foreach ( explode( '.', $path ) as $segment ) {
			if ( is_array( $current ) && array_key_exists( $segment, $current ) ) {
				$current = $current[ $segment ];
			} else {
				return null;
			}
		}

		return $current;
	}

	/**
	 * Apply one filter ("name" or "name:arg") to a value. Unknown filter
	 * names pass the value through unchanged — a typo in a filter name is a
	 * silent no-op, not a fatal, matching Area_Resolver's/Control_Mapper's
	 * "fail soft on config typos" convention elsewhere in this library.
	 *
	 * @param string $filter_expression e.g. "default:neutral" or "upper".
	 * @param mixed  $value             Value to filter.
	 * @return mixed
	 */
	private static function apply_filter( string $filter_expression, $value ) {
		$colon_position = strpos( $filter_expression, ':' );
		$name           = trim( false === $colon_position ? $filter_expression : substr( $filter_expression, 0, $colon_position ) );
		$arg            = false === $colon_position ? null : substr( $filter_expression, $colon_position + 1 );

		switch ( $name ) {
			case 'default':
				return self::is_empty( $value ) ? $arg : $value;

			case 'upper':
				return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( (string) $value ) : strtoupper( (string) $value );

			case 'lower':
				return function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $value ) : strtolower( (string) $value );

			case 'slug':
				return function_exists( 'sanitize_title' ) ? sanitize_title( (string) $value ) : self::fallback_slug( (string) $value );

			case 'esc_url':
				return function_exists( 'esc_url' ) ? esc_url( (string) $value ) : (string) $value;

			case 'date':
				return self::filter_date( $value, $arg );

			case 'number':
				return number_format( (float) $value, null !== $arg ? (int) $arg : 0 );

			case 'join':
				return is_array( $value ) ? implode( null !== $arg ? $arg : ', ', $value ) : $value;

			case 'count':
				return is_countable( $value ) ? count( $value ) : 0;

			case 'not':
				return self::truthy( $value ) ? '' : '1';

			default:
				return $value;
		}
	}

	/**
	 * The "date" filter: format a Unix timestamp (int/numeric string) or a
	 * strtotime()-parseable date string.
	 *
	 * @param mixed       $value  Timestamp or date string.
	 * @param string|null $format PHP date() format string; defaults to "Y-m-d".
	 * @return string
	 */
	private static function filter_date( $value, ?string $format ): string {
		$format    = null !== $format ? $format : 'Y-m-d';
		$timestamp = is_numeric( $value ) ? (int) $value : strtotime( (string) $value );

		if ( false === $timestamp ) {
			return '';
		}

		return function_exists( 'date_i18n' ) ? date_i18n( $format, $timestamp ) : gmdate( $format, $timestamp );
	}

	/**
	 * ASCII fallback for the "slug" filter when sanitize_title() (WordPress)
	 * isn't loaded — used only by standalone unit tests of this class.
	 *
	 * @param string $value Value to slugify.
	 * @return string
	 */
	private static function fallback_slug( string $value ): string {
		$slug = strtolower( $value );
		$slug = (string) preg_replace( '/[^a-z0-9]+/', '-', $slug );

		return trim( $slug, '-' );
	}

	/**
	 * Whether a value counts as "empty" for the "default" filter — null,
	 * empty string, or empty array. Deliberately excludes 0/false: those
	 * are meaningful non-missing values, not gaps to fill in.
	 *
	 * @param mixed $value Value to check.
	 * @return bool
	 */
	private static function is_empty( $value ): bool {
		return null === $value || '' === $value || [] === $value;
	}

	/**
	 * Boolean-ish truthiness for the "not" filter.
	 *
	 * @param mixed $value Value to check.
	 * @return bool
	 */
	private static function truthy( $value ): bool {
		if ( is_array( $value ) ) {
			return ! empty( $value );
		}

		return (bool) $value;
	}

	/**
	 * Stringify a resolved value for interpolation into a template.
	 * Booleans stringify as "1"/"" (PHP's own (string) cast), matching how
	 * a boolean attribute value would naturally serialize in a
	 * `data-x="{{ attributes.flag }}"` template.
	 *
	 * @param mixed $value Value to stringify.
	 * @return string
	 */
	private static function stringify( $value ): string {
		if ( is_array( $value ) ) {
			return '';
		}

		if ( is_bool( $value ) ) {
			return $value ? '1' : '';
		}

		return (string) ( $value ?? '' );
	}
}
