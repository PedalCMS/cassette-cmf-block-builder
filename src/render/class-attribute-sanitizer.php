<?php
/**
 * Attribute_Sanitizer class for Cassette-CMF Blocks
 *
 * Re-sanitizes a block's resolved attributes before they reach
 * Markup_Renderer/a render.callback/render.template, routing each
 * attribute-sourced field's value through the parent library's own
 * sanitize() via Compat\Cmf_Bridge — the same sanitizer the parent's own
 * CPT/settings fields use.
 *
 * WordPress's REST block-renderer endpoint already validates the shape of
 * incoming attributes against the registered attribute schema (types,
 * enums), so this isn't closing an unvalidated input channel; it's
 * defense-in-depth plus renormalisation for rendering (e.g. a "text" field
 * with format/derive_from rules gets trimmed/normalised the same way it
 * would be on save). The real XSS defence is Markup_Renderer's own
 * per-node-kind output escaping — this exists alongside it, not instead of it.
 *
 * @package PedalCMS\CassetteCMFBlocks
 * @since 0.2.0
 */

namespace PedalCMS\CassetteCMFBlocks\Render;

use PedalCMS\CassetteCMFBlocks\Compat\Cmf_Bridge;
use PedalCMS\CassetteCMFBlocks\Core\Field_Collection;
use PedalCMS\CassetteCMFBlocks\Schema\Control_Catalog;

/**
 * Class Attribute_Sanitizer
 */
class Attribute_Sanitizer {

	/**
	 * Accepted UUID shape: versions 1 to 8, RFC 4122 variant, lowercase or
	 * uppercase hex. Braces, URNs and anything else are rejected.
	 *
	 * @var string
	 */
	private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD';

	/**
	 * Sanitize a "uuid" field value: a valid UUID comes back lowercased,
	 * anything else (including non-strings) becomes an empty string. The
	 * library never generates one server side; consumers handle a missing ID.
	 *
	 * @api
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_uuid( $value ): string {
		if ( ! is_string( $value ) || 1 !== preg_match( self::UUID_PATTERN, $value ) ) {
			return '';
		}

		return strtolower( $value );
	}

	/**
	 * Sanitize every attribute-sourced, top-level field value in place.
	 *
	 * A repeater's own sub-fields are never iterated directly here: their
	 * "name" is a per-row key, not a top-level block attribute, so
	 * array_key_exists( $name, $attributes ) is false for them and the loop
	 * skips them — only the repeater's own top-level attribute (e.g.
	 * "items") gets a Cmf_Bridge::sanitize() call. That's sufficient,
	 * though: the parent library's own Repeater_Field::sanitize() already
	 * recurses into every row using the "fields" config it was built with
	 * (Field_Factory::create() on the repeater's own field config, which
	 * includes "fields"), so row data still ends up correctly sanitized —
	 * for free, via that single top-level call. See
	 * Test_Attribute_Sanitizer::test_repeater_rows_are_sanitized_via_the_parents_own_recursion().
	 *
	XX
	 *
	 * @param array<string, mixed> $attributes Resolved block attributes.
	 * @param Field_Collection     $fields     The block's flattened field collection.
	 * @return array<string, mixed>
	 */
	public static function sanitize_all( array $attributes, Field_Collection $fields ): array {
		foreach ( $fields->get_leaves() as $leaf ) {
			$config   = $leaf['config'];
			$name     = $config['name'] ?? null;
			$source   = $config['source'] ?? 'attribute';
			$cmf_type = $leaf['cmf_type'];

			if ( 'uuid' === $leaf['control_type'] && is_string( $name ) && 'attribute' === $source && array_key_exists( $name, $attributes ) ) {
				$attributes[ $name ] = self::sanitize_uuid( $attributes[ $name ] );
				continue;
			}

			if ( is_string( $name ) && 'attribute' === $source && null === $cmf_type && array_key_exists( $name, $attributes ) && self::has_control_sanitizer( $leaf['control_type'] ) ) {
				$attributes[ $name ] = self::sanitize_control_value( $leaf['control_type'], $attributes[ $name ], $config );
				continue;
			}

			if ( ! is_string( $name ) || 'attribute' !== $source || null === $cmf_type ) {
				continue;
			}

			if ( ! array_key_exists( $name, $attributes ) ) {
				continue;
			}

			$value_type = $config['value_type'] ?? ( Control_Catalog::get_type( $leaf['control_type'] )['value_type'] ?? null );

			$attributes[ $name ] = self::match_value_type(
				Cmf_Bridge::sanitize( $cmf_type, $config, $attributes[ $name ] ),
				$value_type,
				$config
			);
		}

		return $attributes;
	}

	/**
	 * Control types this class sanitizes itself, with the method that does it.
	 *
	 * @var array<string, string>
	 */
	private const CONTROL_SANITIZERS = [
		'rich_text'        => 'sanitize_rich_text',
		'code'             => 'sanitize_code',
		'icon'             => 'sanitize_icon',
		'link'             => 'sanitize_link',
		'box'              => 'sanitize_sides',
		'spacing'          => 'sanitize_sides',
		'border'           => 'sanitize_border',
		'border_box'       => 'sanitize_border_box',
		'shadow'           => 'sanitize_shadow',
		'angle'            => 'sanitize_angle',
		'alignment_matrix' => 'sanitize_alignment',
		'toolbar_align'    => 'sanitize_text_alignment',
		'unit'             => 'sanitize_unit',
		'letter_spacing'   => 'sanitize_unit',
		'font_size'        => 'sanitize_font_size',
		'font_family'      => 'sanitize_font_family',
		'font_appearance'  => 'sanitize_font_appearance',
		'line_height'      => 'sanitize_line_height',
		'duotone'          => 'sanitize_duotone',
		'image_size'       => 'sanitize_image_size',
		'key_value'        => 'sanitize_key_value',
		'options_list'     => 'sanitize_options_list',
		'media_gallery'    => 'sanitize_id_list',
		'entity_select'    => 'sanitize_entity_id',
		'post_select'      => 'sanitize_entity_id',
		'term_select'      => 'sanitize_entity_id',
		'user_select'      => 'sanitize_entity_id',
		'taxonomy_select'  => 'sanitize_slug',
		'sibling_field'    => 'sanitize_field_name',
		'conditions'       => 'sanitize_conditions',
		'query_builder'    => 'sanitize_query',
	];

	/**
	 * CSS length units the spacing, border and unit controls accept.
	 *
	 * @var string
	 */
	private const LENGTH_UNITS = 'px|em|rem|%|vw|vh|vmin|vmax|ch|ex|pt|pc|cm|mm|in';

	/**
	 * Whether this class has a sanitizer for a control type.
	 *
	 * @param string $control_type Control type.
	 * @return bool
	 */
	private static function has_control_sanitizer( string $control_type ): bool {
		return isset( self::CONTROL_SANITIZERS[ $control_type ] );
	}

	/**
	 * Sanitize the value of a control that has no parent-library type.
	 *
	 * The shape checked is the one the editor control stores. A value of the
	 * wrong shape, or one that fails its strict pattern, becomes the field's
	 * "default" (else the empty value of the control's JSON type). Inside a
	 * valid shape, invalid parts are cleaned or dropped, never passed on.
	 *
	 * @param string               $control_type Control type (a key of CONTROL_SANITIZERS).
	 * @param mixed                $value        Raw value.
	 * @param array<string, mixed> $config       Field config.
	 * @return mixed
	 */
	private static function sanitize_control_value( string $control_type, $value, array $config ) {
		$spec       = Control_Catalog::get_type( $control_type );
		$value_type = $config['value_type'] ?? ( $spec['value_type'] ?? 'string' );
		$empty      = [
			'string'  => '',
			'number'  => 0,
			'integer' => 0,
			'boolean' => false,
			'array'   => [],
			'object'  => [],
		][ $value_type ] ?? '';
		$default    = $config['default'] ?? $empty;
		$method     = self::CONTROL_SANITIZERS[ $control_type ];
		$clean      = call_user_func( [ self::class, $method ], $value, $config, $control_type );

		return null === $clean ? $default : $clean;
	}

	/**
	 * A string, or null when the value is not one.
	 *
	 * @param mixed $value Raw value.
	 * @return string|null
	 */
	private static function string_or_null( $value ): ?string {
		return is_string( $value ) ? $value : null;
	}

	/**
	 * Whether a value is a string-keyed array (a JSON object).
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	private static function is_object_like( $value ): bool {
		return is_array( $value ) && ( [] === $value || ! array_is_list( $value ) );
	}

	/**
	 * Rich text: the HTML allowed in post content.
	 *
	 * @param mixed $value Raw value.
	 * @return string|null
	 */
	private static function sanitize_rich_text( $value ): ?string {
		return is_string( $value ) ? wp_kses_post( $value ) : null;
	}

	/**
	 * Code: any string, untouched (it is code, not markup to clean).
	 *
	 * @param mixed $value Raw value.
	 * @return string|null
	 */
	private static function sanitize_code( $value ): ?string {
		return self::string_or_null( $value );
	}

	/**
	 * Icon: an icon name (letters, digits, dash, underscore, dot, colon, slash).
	 *
	 * @param mixed $value Raw value.
	 * @return string|null
	 */
	private static function sanitize_icon( $value ): ?string {
		return is_string( $value ) && 1 === preg_match( '/^[A-Za-z0-9_\-.:\/]{0,100}$/D', $value ) ? $value : null;
	}

	/**
	 * Link: { url, title, opensInNewTab, id, type, kind }, no other key.
	 *
	 * @param mixed $value Raw value.
	 * @return array<string, mixed>|null
	 */
	private static function sanitize_link( $value ): ?array {
		if ( ! self::is_object_like( $value ) ) {
			return null;
		}

		$link = [];

		foreach ( $value as $key => $item ) {
			switch ( $key ) {
				case 'url':
					$link['url'] = is_string( $item ) ? esc_url_raw( $item ) : '';
					break;
				case 'title':
					$link['title'] = is_string( $item ) ? sanitize_text_field( $item ) : '';
					break;
				case 'opensInNewTab':
					$link['opensInNewTab'] = true === $item || 1 === $item || '1' === $item;
					break;
				case 'id':
					$link['id'] = self::positive_id( $item ) ?? 0;
					break;
				case 'type':
				case 'kind':
					$link[ $key ] = is_string( $item ) ? sanitize_key( $item ) : '';
					break;
			}
		}

		return $link;
	}

	/**
	 * Whether a string is a CSS length (a number with a unit, or 0), optionally negative.
	 *
	 * @param string $value Candidate.
	 * @return bool
	 */
	private static function is_css_length( string $value ): bool {
		return 1 === preg_match( '/^-?(?:\d+|\d*\.\d+)(?:' . self::LENGTH_UNITS . ')$/iD', $value ) || '0' === $value;
	}

	/**
	 * Whether a string is a WordPress preset reference for a given kind.
	 *
	 * @param string $value Candidate.
	 * @param string $kind  Preset kind: spacing, color, font-size, font-family or shadow.
	 * @return bool
	 */
	private static function is_preset_reference( string $value, string $kind ): bool {
		return 1 === preg_match( '/^var:preset\|' . preg_quote( $kind, '/' ) . '\|[a-z0-9-]+$/D', $value )
			|| 1 === preg_match( '/^var\(--wp--preset--' . preg_quote( $kind, '/' ) . '--[a-z0-9-]+\)$/D', $value );
	}

	/**
	 * Whether a string is a CSS color: hex, rgb()/hsl() family, a keyword, or a color preset.
	 *
	 * @param string $value Candidate.
	 * @return bool
	 */
	private static function is_css_color( string $value ): bool {
		return 1 === preg_match( '/^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/iD', $value )
			|| 1 === preg_match( '/^(?:rgb|rgba|hsl|hsla)\(\s*[0-9a-z.%\s,\/+-]+\)$/iD', $value )
			|| 1 === preg_match( '/^[a-z]{3,30}$/iD', $value )
			|| self::is_preset_reference( $value, 'color' );
	}

	/**
	 * Box / spacing: { top, right, bottom, left }, each a CSS length, a spacing preset, "auto" or "".
	 *
	 * @param mixed $value Raw value.
	 * @return array<string, string>|null
	 */
	private static function sanitize_sides( $value ): ?array {
		if ( ! self::is_object_like( $value ) ) {
			return null;
		}

		$sides = [];

		foreach ( [ 'top', 'right', 'bottom', 'left' ] as $side ) {
			if ( ! array_key_exists( $side, $value ) ) {
				continue;
			}

			$item = $value[ $side ];
			$ok   = is_string( $item ) && ( '' === $item || 'auto' === $item || self::is_css_length( $item ) || self::is_preset_reference( $item, 'spacing' ) );

			$sides[ $side ] = $ok ? $item : '';
		}

		return $sides;
	}

	/**
	 * Border: { width, style, color }.
	 *
	 * @param mixed $value Raw value.
	 * @return array<string, string>|null
	 */
	private static function sanitize_border( $value ): ?array {
		if ( ! self::is_object_like( $value ) ) {
			return null;
		}

		$border = [];

		if ( array_key_exists( 'width', $value ) ) {
			$width           = $value['width'];
			$border['width'] = is_string( $width ) && ( '' === $width || ( self::is_css_length( $width ) && '-' !== $width[0] ) ) ? $width : '';
		}

		if ( array_key_exists( 'style', $value ) ) {
			$style           = $value['style'];
			$border['style'] = is_string( $style ) && in_array( $style, [ '', 'none', 'hidden', 'solid', 'dashed', 'dotted', 'double', 'groove', 'ridge', 'inset', 'outset' ], true ) ? $style : '';
		}

		if ( array_key_exists( 'color', $value ) ) {
			$color           = $value['color'];
			$border['color'] = is_string( $color ) && ( '' === $color || self::is_css_color( $color ) ) ? $color : '';
		}

		return $border;
	}

	/**
	 * Border box: { top, right, bottom, left }, each a border.
	 *
	 * @param mixed $value Raw value.
	 * @return array<string, array<string, string>>|null
	 */
	private static function sanitize_border_box( $value ): ?array {
		if ( ! self::is_object_like( $value ) ) {
			return null;
		}

		$sides = [];

		foreach ( [ 'top', 'right', 'bottom', 'left' ] as $side ) {
			if ( array_key_exists( $side, $value ) ) {
				$sides[ $side ] = self::sanitize_border( $value[ $side ] ) ?? [];
			}
		}

		return $sides;
	}

	/**
	 * Shadow: a CSS box-shadow built from numbers, units, colors and "inset", a shadow preset,
	 * or a value from the field's own "presets" list.
	 *
	 * @param mixed                $value  Raw value.
	 * @param array<string, mixed> $config Field config.
	 * @return string|null
	 */
	private static function sanitize_shadow( $value, array $config ): ?string {
		if ( ! is_string( $value ) ) {
			return null;
		}

		if ( '' === $value || self::is_preset_reference( $value, 'shadow' ) ) {
			return $value;
		}

		// A value the field's own "presets" list offers is the developer's choice.
		foreach ( is_array( $config['presets'] ?? null ) ? $config['presets'] : [] as $preset ) {
			if ( is_array( $preset ) && isset( $preset['value'] ) && $value === $preset['value'] ) {
				return $value;
			}
		}

		$plain = 1 === preg_match( '/^[A-Za-z0-9\s.,()#%\-]{1,200}$/D', $value )
			&& 0 === preg_match( '/url\s*\(|expression|image/i', $value );

		return $plain ? $value : null;
	}

	/**
	 * Angle: a number from 0 to 360.
	 *
	 * @param mixed $value Raw value.
	 * @return int|float|null
	 */
	private static function sanitize_angle( $value ) {
		if ( is_string( $value ) && is_numeric( $value ) ) {
			$value += 0;
		}

		if ( ! is_int( $value ) && ! ( is_float( $value ) && is_finite( $value ) ) ) {
			return null;
		}

		return max( 0, min( 360, $value ) );
	}

	/**
	 * Alignment matrix: one of the nine "vertical horizontal" positions.
	 *
	 * @param mixed $value Raw value.
	 * @return string|null
	 */
	private static function sanitize_alignment( $value ): ?string {
		$allowed = [
			'top left',
			'top center',
			'top right',
			'center left',
			'center center',
			'center right',
			'bottom left',
			'bottom center',
			'bottom right',
		];

		return is_string( $value ) && ( '' === $value || in_array( $value, $allowed, true ) ) ? $value : null;
	}

	/**
	 * Toolbar alignment: left, center, right or justify ("" for none).
	 *
	 * @param mixed $value Raw value.
	 * @return string|null
	 */
	private static function sanitize_text_alignment( $value ): ?string {
		return is_string( $value ) && in_array( $value, [ '', 'left', 'center', 'right', 'justify' ], true ) ? $value : null;
	}

	/**
	 * Unit / letter spacing: a number and one of the control's units, or "".
	 *
	 * @param mixed                $value        Raw value.
	 * @param array<string, mixed> $config       Field config.
	 * @param string               $control_type Control type.
	 * @return string|null
	 */
	private static function sanitize_unit( $value, array $config, string $control_type ): ?string {
		if ( ! is_string( $value ) ) {
			return null;
		}

		if ( '' === $value ) {
			return '';
		}

		$units = [ 'px', 'em', 'rem', '%', 'vw', 'vh' ];

		if ( 'letter_spacing' === $control_type ) {
			$units = [ 'px', 'em', 'rem' ];
		}

		if ( isset( $config['units'] ) && is_array( $config['units'] ) ) {
			$units = array_values( array_filter( $config['units'], 'is_string' ) );
		}

		if ( 1 !== preg_match( '/^(-?(?:\d+|\d*\.\d+))([a-z%]*)$/iD', $value, $match ) ) {
			return null;
		}

		return in_array( $match[2], $units, true ) ? $value : null;
	}

	/**
	 * Font size: a CSS length, a font-size preset, a calc/min/max/clamp expression,
	 * or a size from the field's own "sizes" list.
	 *
	 * @param mixed                $value  Raw value.
	 * @param array<string, mixed> $config Field config.
	 * @return string|null
	 */
	private static function sanitize_font_size( $value, array $config ): ?string {
		if ( ! is_string( $value ) ) {
			return null;
		}

		// A size the field's own "sizes" list offers is the developer's choice.
		foreach ( is_array( $config['sizes'] ?? null ) ? $config['sizes'] : [] as $size ) {
			if ( is_array( $size ) && isset( $size['size'] ) && $value === (string) $size['size'] ) {
				return $value;
			}
		}

		if ( '' === $value || self::is_css_length( $value ) || self::is_preset_reference( $value, 'font-size' ) ) {
			return $value;
		}

		$expression = 1 === preg_match( '/^(?:calc|min|max|clamp)\([0-9a-z.,%+\-*\/\s()]{1,150}\)$/iD', $value );

		if ( $expression ) {
			preg_match_all( '/([a-z]+)\s*\(/i', $value, $functions );

			foreach ( $functions[1] as $function ) {
				if ( ! in_array( strtolower( $function ), [ 'calc', 'min', 'max', 'clamp' ], true ) ) {
					return null;
				}
			}

			return $value;
		}

		return null;
	}

	/**
	 * Font family: a key of the field's "options" when it has any, otherwise a CSS font stack.
	 *
	 * @param mixed                $value  Raw value.
	 * @param array<string, mixed> $config Field config.
	 * @return string|null
	 */
	private static function sanitize_font_family( $value, array $config ): ?string {
		if ( ! is_string( $value ) ) {
			return null;
		}

		if ( isset( $config['options'] ) && is_array( $config['options'] ) ) {
			return in_array( $value, array_map( 'strval', array_keys( $config['options'] ) ), true ) ? $value : null;
		}

		if ( '' === $value || self::is_preset_reference( $value, 'font-family' ) ) {
			return $value;
		}

		return 1 === preg_match( '/^[A-Za-z0-9\s,\-_.\'"]{1,200}$/D', $value ) ? $value : null;
	}

	/**
	 * Font appearance: "<style> <weight>", for example "italic 700".
	 *
	 * @param mixed $value Raw value.
	 * @return string|null
	 */
	private static function sanitize_font_appearance( $value ): ?string {
		return is_string( $value ) && ( '' === $value || 1 === preg_match( '/^(?:normal|italic|oblique) (?:[1-9]00|normal|bold)$/D', $value ) ) ? $value : null;
	}

	/**
	 * Line height: a unitless number or a CSS length, as a string.
	 *
	 * @param mixed $value Raw value.
	 * @return string|null
	 */
	private static function sanitize_line_height( $value ): ?string {
		if ( ! is_string( $value ) ) {
			return null;
		}

		return '' === $value || 1 === preg_match( '/^(?:\d+|\d*\.\d+)$/D', $value ) || ( self::is_css_length( $value ) && '-' !== $value[0] ) ? $value : null;
	}

	/**
	 * Duotone: a list of at least two CSS colors; one bad color rejects the whole value.
	 *
	 * @param mixed $value Raw value.
	 * @return string[]|null
	 */
	private static function sanitize_duotone( $value ): ?array {
		if ( ! is_array( $value ) || ! array_is_list( $value ) ) {
			return null;
		}

		if ( [] === $value ) {
			return [];
		}

		foreach ( $value as $color ) {
			if ( ! is_string( $color ) || ! self::is_css_color( $color ) ) {
				return null;
			}
		}

		return count( $value ) >= 2 ? $value : null;
	}

	/**
	 * Image size: a key of the field's "sizes" when it has any, otherwise "full" or a registered size.
	 *
	 * @param mixed                $value  Raw value.
	 * @param array<string, mixed> $config Field config.
	 * @return string|null
	 */
	private static function sanitize_image_size( $value, array $config ): ?string {
		if ( ! is_string( $value ) ) {
			return null;
		}

		if ( isset( $config['sizes'] ) && is_array( $config['sizes'] ) ) {
			$allowed = array_map( 'strval', array_keys( $config['sizes'] ) );
		} else {
			$allowed = array_merge( [ 'full' ], get_intermediate_image_sizes() );
		}

		return '' === $value || in_array( $value, $allowed, true ) ? $value : null;
	}

	/**
	 * Key / value: a map of non-empty keys to string values. Keys and values are cleaned as text.
	 *
	 * @param mixed $value Raw value.
	 * @return array<string, string>|null
	 */
	private static function sanitize_key_value( $value ): ?array {
		if ( ! is_array( $value ) ) {
			return null;
		}

		$pairs = [];

		foreach ( $value as $key => $item ) {
			if ( ! is_scalar( $item ) ) {
				continue;
			}

			$clean_key = sanitize_text_field( (string) $key );

			if ( '' !== $clean_key ) {
				$pairs[ $clean_key ] = sanitize_text_field( (string) $item );
			}
		}

		return $pairs;
	}

	/**
	 * Options list: a list of { value, label } text pairs.
	 *
	 * @param mixed $value Raw value.
	 * @return array<int, array<string, string>>|null
	 */
	private static function sanitize_options_list( $value ): ?array {
		if ( ! is_array( $value ) ) {
			return null;
		}

		$rows = [];

		foreach ( $value as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$clean = [];

			foreach ( [ 'value', 'label' ] as $key ) {
				if ( array_key_exists( $key, $row ) ) {
					$clean[ $key ] = is_scalar( $row[ $key ] ) ? sanitize_text_field( (string) $row[ $key ] ) : '';
				}
			}

			$rows[] = $clean;
		}

		return $rows;
	}

	/**
	 * A positive integer ID from an int or a digit string, else null.
	 *
	 * @param mixed $value Raw value.
	 * @return int|null
	 */
	private static function positive_id( $value ): ?int {
		if ( is_string( $value ) && ctype_digit( $value ) ) {
			$value = (int) $value;
		}

		return is_int( $value ) && $value > 0 ? $value : null;
	}

	/**
	 * Media gallery (and any array-valued picker): a list of positive integer IDs. Other items are dropped.
	 *
	 * @param mixed $value Raw value.
	 * @return int[]|null
	 */
	private static function sanitize_id_list( $value ): ?array {
		if ( ! is_array( $value ) ) {
			return null;
		}

		$ids = [];

		foreach ( $value as $item ) {
			$id = self::positive_id( $item );

			if ( null !== $id ) {
				$ids[] = $id;
			}
		}

		return $ids;
	}

	/**
	 * Entity pickers (post, term, user, generic entity): an integer ID, or a list of IDs when the field's value_type is "array".
	 *
	 * @param mixed                $value  Raw value.
	 * @param array<string, mixed> $config Field config.
	 * @return int|int[]|null
	 */
	private static function sanitize_entity_id( $value, array $config ) {
		if ( 'array' === ( $config['value_type'] ?? null ) ) {
			return self::sanitize_id_list( $value );
		}

		return self::positive_id( $value );
	}

	/**
	 * Taxonomy slug: lowercase letters, digits, dash and underscore.
	 *
	 * @param mixed $value Raw value.
	 * @return string|null
	 */
	private static function sanitize_slug( $value ): ?string {
		return is_string( $value ) && 1 === preg_match( '/^[a-z0-9_\-]{0,32}$/D', $value ) ? $value : null;
	}

	/**
	 * Sibling field: the name of another field (letters, digits, dash, underscore, dot).
	 *
	 * @param mixed $value Raw value.
	 * @return string|null
	 */
	private static function sanitize_field_name( $value ): ?string {
		return is_string( $value ) && 1 === preg_match( '/^[A-Za-z0-9_\-.]{0,100}$/D', $value ) ? $value : null;
	}

	/**
	 * Clean a condition or query operand: text, a number, or a list of those.
	 *
	 * @param mixed $value Raw operand.
	 * @return string|int|float|bool|array<int, string|int|float|bool>|null
	 */
	private static function sanitize_operand( $value ) {
		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) ) {
			return sanitize_text_field( $value );
		}

		if ( is_array( $value ) && array_is_list( $value ) ) {
			$items = [];

			foreach ( $value as $item ) {
				if ( is_scalar( $item ) ) {
					$items[] = self::sanitize_operand( $item );
				}
			}

			return $items;
		}

		return null;
	}

	/**
	 * Conditions: { relation: AND|OR, rules: [ { field | source + ref, operator, value } ] }.
	 *
	 * @param mixed $value Raw value.
	 * @return array<string, mixed>|null
	 */
	private static function sanitize_conditions( $value ): ?array {
		if ( ! self::is_object_like( $value ) ) {
			return null;
		}

		if ( [] === $value ) {
			return [];
		}

		$operators = [ '==', '!=', '>', '>=', '<', '<=', 'in', 'not_in', 'empty', 'not_empty' ];
		$rules     = [];

		foreach ( is_array( $value['rules'] ?? null ) ? $value['rules'] : [] as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			$clean = [];

			foreach ( [ 'field', 'ref' ] as $key ) {
				if ( isset( $rule[ $key ] ) && is_string( $rule[ $key ] ) ) {
					$clean[ $key ] = sanitize_text_field( $rule[ $key ] );
				}
			}

			if ( isset( $rule['source'] ) && 'block' === $rule['source'] ) {
				$clean['source'] = 'block';
			}

			$clean['operator'] = isset( $rule['operator'] ) && in_array( $rule['operator'], $operators, true ) ? $rule['operator'] : '==';

			if ( array_key_exists( 'value', $rule ) ) {
				$operand = self::sanitize_operand( $rule['value'] );

				if ( null !== $operand ) {
					$clean['value'] = $operand;
				}
			}

			$rules[] = $clean;
		}

		return [
			'relation' => 'OR' === ( $value['relation'] ?? null ) ? 'OR' : 'AND',
			'rules'    => $rules,
		];
	}

	/**
	 * Query builder: { relation: AND|OR, rules: [ { key, compare, value } ] }.
	 *
	 * @param mixed $value Raw value.
	 * @return array<string, mixed>|null
	 */
	private static function sanitize_query( $value ): ?array {
		if ( ! self::is_object_like( $value ) ) {
			return null;
		}

		if ( [] === $value ) {
			return [];
		}

		$compares = [ '=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'EXISTS', 'NOT EXISTS' ];
		$rules    = [];

		foreach ( is_array( $value['rules'] ?? null ) ? $value['rules'] : [] as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			$clean = [
				'key'     => isset( $rule['key'] ) && is_string( $rule['key'] ) ? sanitize_text_field( $rule['key'] ) : '',
				'compare' => isset( $rule['compare'] ) && in_array( $rule['compare'], $compares, true ) ? $rule['compare'] : '=',
			];

			if ( array_key_exists( 'value', $rule ) ) {
				$operand = self::sanitize_operand( $rule['value'] );

				if ( null !== $operand ) {
					$clean['value'] = $operand;
				}
			}

			$rules[] = $clean;
		}

		return [
			'relation' => 'OR' === ( $value['relation'] ?? null ) ? 'OR' : 'AND',
			'rules'    => $rules,
		];
	}

	/**
	 * Give a sanitized value the JSON type its attribute schema declares.
	 *
	 * The cassette-cmf sanitizers were written for form posts: a single
	 * checkbox returns the strings "1" / "0" and an empty upload returns "".
	 * The block attribute is a boolean or an integer, so a "boolean" value is
	 * returned as true / false and an "integer" one as an int (the field's
	 * default, else 0, when nothing usable is left). Other types are returned
	 * unchanged.
	 *
	 * @param mixed                $value      Sanitized value.
	 * @param string|null          $value_type The attribute's JSON type.
	 * @param array<string, mixed> $config     Field config.
	 * @return mixed
	 */
	private static function match_value_type( $value, ?string $value_type, array $config ) {
		if ( 'boolean' === $value_type ) {
			return true === $value || 1 === $value || '1' === $value;
		}

		if ( 'integer' === $value_type && ! is_int( $value ) ) {
			return is_numeric( $value ) ? (int) $value : (int) ( $config['default'] ?? 0 );
		}

		return $value;
	}
}
