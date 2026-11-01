<?php
/**
 * Control_Catalog class for Cassette-CMF Blocks
 *
 * Static registry of block-field "control types", mirroring the parent
 * library's Field\Field_Factory (register_type / has_type /
 * get_registered_types / unregister_type / reset) plus a
 * cassette_cmf_blocks_control_catalog filter — the one extensibility gap
 * the parent's own Field_Factory does not have.
 *
 * Each entry describes a control declaratively so the PHP-side schema
 * mappers can work without the editor JS existing yet:
 *   - value_type:   the block-attribute JSON type this control produces,
 *                    or null for controls that never hold a value
 *                    (containers, display-only chrome).
 *   - cmf_type:      which of the parent's registered field types provides
 *                    sanitize()/validate() reuse via Compat\Cmf_Bridge, or
 *                    null when there is no honest equivalent.
 *   - is_container:  whether this control type may have a "fields" array
 *                    of children (panel, tab_panel, group, ...).
 *   - holds_value:   whether the control itself stores a value. False for
 *                    ordinary containers (children store their own values);
 *                    true for repeater, which is a container that also
 *                    holds an array value, matching the parent's
 *                    Repeater_Field semantics.
 *
 * @package PedalCMS\CassetteCMFBlocks
 * @since 0.2.0
 */

namespace PedalCMS\CassetteCMFBlocks\Schema;

/**
 * Class Control_Catalog
 */
class Control_Catalog {

	/**
	 * Registered control types.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private static array $types = [];

	/**
	 * Whether default control types have been registered.
	 *
	 * @var bool
	 */
	private static bool $defaults_registered = false;

	/**
	 * JSON types a control's value may have.
	 *
	 * @var string[]
	 */
	public const VALUE_TYPES = [ 'string', 'number', 'integer', 'boolean', 'array', 'object' ];

	/**
	 * Register a control type (the server half of a custom control; the
	 * editor half is registerControlType() on the bundle's payload global).
	 *
	 * Stable contract: only "value_type" is required. The type id must match
	 * ^[a-z][a-z0-9_]*$.
	 *
	 * @api
	 * @since 0.2.0
	 * @param string               $type Control type identifier.
	 * @param array<string, mixed> $spec {
	 *     @type string|null $value_type   Required. Block-attribute JSON type (string, number, integer, boolean, array, object), or null if this control never holds a value.
	 *     @type string|null $cmf_type     Optional, default null. cassette-cmf field type whose sanitize()/validate() to reuse, or null (value left as is).
	 *     @type bool        $is_container Optional, default false. Whether this control type may contain child fields.
	 *     @type bool        $holds_value  Optional, default "value_type is not null". Whether the control stores its own value (a container only if it is a repeater).
	 * }
	 * @return void
	 * @throws \InvalidArgumentException If the type id or the spec is malformed.
	 */
	public static function register_type( string $type, array $spec ): void {
		self::$types[ $type ] = self::normalize_spec( $type, $spec );
	}

	/**
	 * Validate a control type id and complete its spec with the defaults.
	 *
	 * @param string               $type Control type identifier.
	 * @param array<string, mixed> $spec Raw spec.
	 * @return array<string, mixed>
	 * @throws \InvalidArgumentException If the type id or the spec is malformed.
	 */
	private static function normalize_spec( string $type, array $spec ): array {
		// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages don't need escaping.
		if ( 1 !== preg_match( '/^[a-z][a-z0-9_]*$/', $type ) ) {
			throw new \InvalidArgumentException(
				sprintf( 'Control type "%s" is not a valid identifier (lowercase letters, digits and underscores, starting with a letter).', $type )
			);
		}

		if ( ! array_key_exists( 'value_type', $spec ) ) {
			throw new \InvalidArgumentException(
				sprintf( 'Control type "%s" spec must include "value_type".', $type )
			);
		}

		if ( null !== $spec['value_type'] && ! in_array( $spec['value_type'], self::VALUE_TYPES, true ) ) {
			throw new \InvalidArgumentException(
				sprintf( 'Control type "%1$s" has an invalid "value_type"; use null or one of: %2$s.', $type, implode( ', ', self::VALUE_TYPES ) )
			);
		}

		if ( isset( $spec['cmf_type'] ) && ! is_string( $spec['cmf_type'] ) ) {
			throw new \InvalidArgumentException(
				sprintf( 'Control type "%s" has an invalid "cmf_type"; use a field type name or null.', $type )
			);
		}
		// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped

		return [
			'value_type'   => $spec['value_type'],
			'cmf_type'     => $spec['cmf_type'] ?? null,
			'is_container' => (bool) ( $spec['is_container'] ?? false ),
			'holds_value'  => (bool) ( $spec['holds_value'] ?? ( null !== $spec['value_type'] ) ),
		];
	}

	/**
	 * Register the built-in control types. Merges back any custom types
	 * registered before defaults, so a consumer registering a custom type
	 * early (before this runs) is not overwritten — same pattern as the
	 * parent's Field_Factory::register_defaults().
	 *
	 * @internal Not part of the stable API.
	 * @return void
	 */
	public static function register_defaults(): void {
		if ( self::$defaults_registered ) {
			return;
		}

		$custom_types = self::$types;

		self::$types = [
			// --- Value controls mapped 1:1 to a parent field type. ---
			'text'             => [
				'value_type'   => 'string',
				'cmf_type'     => 'text',
				'is_container' => false,
				'holds_value'  => true,
			],
			'textarea'         => [
				'value_type'   => 'string',
				'cmf_type'     => 'textarea',
				'is_container' => false,
				'holds_value'  => true,
			],
			'select'           => [
				'value_type'   => 'string',
				'cmf_type'     => 'select',
				'is_container' => false,
				'holds_value'  => true,
			],
			'toggle'           => [
				'value_type'   => 'boolean',
				'cmf_type'     => 'checkbox',
				'is_container' => false,
				'holds_value'  => true,
			],
			'checkbox_group'   => [
				'value_type'   => 'array',
				'cmf_type'     => 'checkbox',
				'is_container' => false,
				'holds_value'  => true,
			],
			'radio'            => [
				'value_type'   => 'string',
				'cmf_type'     => 'radio',
				'is_container' => false,
				'holds_value'  => true,
			],
			'number'           => [
				'value_type'   => 'number',
				'cmf_type'     => 'number',
				'is_container' => false,
				'holds_value'  => true,
			],
			'email'            => [
				'value_type'   => 'string',
				'cmf_type'     => 'email',
				'is_container' => false,
				'holds_value'  => true,
			],
			'url'              => [
				'value_type'   => 'string',
				'cmf_type'     => 'url',
				'is_container' => false,
				'holds_value'  => true,
			],
			'date'             => [
				'value_type'   => 'string',
				'cmf_type'     => 'date',
				'is_container' => false,
				'holds_value'  => true,
			],
			'color'            => [
				'value_type'   => 'string',
				'cmf_type'     => 'color',
				'is_container' => false,
				'holds_value'  => true,
			],
			'media'            => [
				'value_type'   => 'integer',
				'cmf_type'     => 'upload',
				'is_container' => false,
				'holds_value'  => true,
			],

			// --- Value controls with no honest parent equivalent (see docs/control-catalog.md). ---
			// "uuid" renders no control at all; the editor generates the value, and
			// Render\Attribute_Sanitizer validates it itself (there is no parent type).
			'uuid'             => [
				'value_type'   => 'string',
				'cmf_type'     => null,
				'is_container' => false,
				'holds_value'  => true,
			],
			'rich_text'        => [
				'value_type'   => 'string',
				'cmf_type'     => null,
				'is_container' => false,
				'holds_value'  => true,
			],
			'media_gallery'    => [
				'value_type'   => 'array',
				'cmf_type'     => null,
				'is_container' => false,
				'holds_value'  => true,
			],
			'link'             => [
				'value_type'   => 'object',
				'cmf_type'     => null,
				'is_container' => false,
				'holds_value'  => true,
			],
			'options_list'     => [
				'value_type'   => 'array',
				'cmf_type'     => null,
				'is_container' => false,
				'holds_value'  => true,
			],
			'entity_select'    => [
				'value_type'   => 'integer',
				'cmf_type'     => null,
				'is_container' => false,
				'holds_value'  => true,
			],
			'post_select'      => [
				'value_type'   => 'integer',
				'cmf_type'     => null,
				'is_container' => false,
				'holds_value'  => true,
			],
			'term_select'      => [
				'value_type'   => 'integer',
				'cmf_type'     => null,
				'is_container' => false,
				'holds_value'  => true,
			],
			'user_select'      => [
				'value_type'   => 'integer',
				'cmf_type'     => null,
				'is_container' => false,
				'holds_value'  => true,
			],
			'taxonomy_select'  => [
				'value_type'   => 'string',
				'cmf_type'     => null,
				'is_container' => false,
				'holds_value'  => true,
			],
			'sibling_field'    => [
				'value_type'   => 'string',
				'cmf_type'     => null,
				'is_container' => false,
				'holds_value'  => true,
			],
			// Stores the referenced block's "name" attribute value (never a clientId).
			// The server cannot verify the reference; consumers validate it.
			'ancestor_field'   => [
				'value_type'   => 'string',
				'cmf_type'     => 'text',
				'is_container' => false,
				'holds_value'  => true,
			],
			'conditions'       => [
				'value_type'   => 'object',
				'cmf_type'     => null,
				'is_container' => false,
				'holds_value'  => true,
			],
			'unit'             => [
				'value_type'   => 'string',
				'cmf_type'     => null,
				'is_container' => false,
				'holds_value'  => true,
			],
			'box'              => [
				'value_type'   => 'object',
				'cmf_type'     => null,
				'is_container' => false,
				'holds_value'  => true,
			],
			'border'           => [
				'value_type'   => 'object',
				'cmf_type'     => null,
				'is_container' => false,
				'holds_value'  => true,
			],
			'border_box'       => [
				'value_type'   => 'object',
				'cmf_type'     => null,
				'is_container' => false,
				'holds_value'  => true,
			],
			'spacing'          => [
				'value_type'   => 'object',
				'cmf_type'     => null,
				'is_container' => false,
				'holds_value'  => true,
			],
			'shadow'           => [
				'value_type'   => 'string',
				'cmf_type'     => null,
				'is_container' => false,
				'holds_value'  => true,
			],
			'angle'            => [
				'value_type'   => 'number',
				'cmf_type'     => null,
				'is_container' => false,
				'holds_value'  => true,
			],
			'alignment_matrix' => [
				'value_type'   => 'string',
				'cmf_type'     => null,
				'is_container' => false,
				'holds_value'  => true,
			],
			'font_size'        => [
				'value_type'   => 'string',
				'cmf_type'     => null,
				'is_container' => false,
				'holds_value'  => true,
			],
			'font_family'      => [
				'value_type'   => 'string',
				'cmf_type'     => null,
				'is_container' => false,
				'holds_value'  => true,
			],
			'font_appearance'  => [
				'value_type'   => 'string',
				'cmf_type'     => null,
				'is_container' => false,
				'holds_value'  => true,
			],
			'line_height'      => [
				'value_type'   => 'string',
				'cmf_type'     => null,
				'is_container' => false,
				'holds_value'  => true,
			],
			'letter_spacing'   => [
				'value_type'   => 'string',
				'cmf_type'     => null,
				'is_container' => false,
				'holds_value'  => true,
			],
			'icon'             => [
				'value_type'   => 'string',
				'cmf_type'     => null,
				'is_container' => false,
				'holds_value'  => true,
			],
			'code'             => [
				'value_type'   => 'string',
				'cmf_type'     => null,
				'is_container' => false,
				'holds_value'  => true,
			],
			'key_value'        => [
				'value_type'   => 'object',
				'cmf_type'     => null,
				'is_container' => false,
				'holds_value'  => true,
			],
			'toggle_group'     => [
				'value_type'   => 'string',
				'cmf_type'     => 'radio',
				'is_container' => false,
				'holds_value'  => true,
			],
			'duotone'          => [
				'value_type'   => 'array',
				'cmf_type'     => null,
				'is_container' => false,
				'holds_value'  => true,
			],
			'image_size'       => [
				'value_type'   => 'string',
				'cmf_type'     => null,
				'is_container' => false,
				'holds_value'  => true,
			],
			'query_builder'    => [
				'value_type'   => 'object',
				'cmf_type'     => null,
				'is_container' => false,
				'holds_value'  => true,
			],

			// --- Display-only chrome: no attribute, not a container. ---
			'notice'           => [
				'value_type'   => null,
				'cmf_type'     => 'custom_html',
				'is_container' => false,
				'holds_value'  => false,
			],
			'static_html'      => [
				'value_type'   => null,
				'cmf_type'     => 'custom_html',
				'is_container' => false,
				'holds_value'  => false,
			],
			'separator'        => [
				'value_type'   => null,
				'cmf_type'     => null,
				'is_container' => false,
				'holds_value'  => false,
			],
			'heading'          => [
				'value_type'   => null,
				'cmf_type'     => null,
				'is_container' => false,
				'holds_value'  => false,
			],
			'help'             => [
				'value_type'   => null,
				'cmf_type'     => null,
				'is_container' => false,
				'holds_value'  => false,
			],
			'server_preview'   => [
				'value_type'   => null,
				'cmf_type'     => null,
				'is_container' => false,
				'holds_value'  => false,
			],

			// --- Containers: hold children, not values (except repeater). ---
			'panel'            => [
				'value_type'   => null,
				'cmf_type'     => 'metabox',
				'is_container' => true,
				'holds_value'  => false,
			],
			'metabox'          => [
				'value_type'   => null,
				'cmf_type'     => 'metabox',
				'is_container' => true,
				'holds_value'  => false,
			],
			'tab_panel'        => [
				'value_type'   => null,
				'cmf_type'     => 'tabs',
				'is_container' => true,
				'holds_value'  => false,
			],
			'tab'              => [
				'value_type'   => null,
				'cmf_type'     => null,
				'is_container' => true,
				'holds_value'  => false,
			],
			'group'            => [
				'value_type'   => null,
				'cmf_type'     => 'group',
				'is_container' => true,
				'holds_value'  => false,
			],
			'row'              => [
				'value_type'   => null,
				'cmf_type'     => null,
				'is_container' => true,
				'holds_value'  => false,
			],
			'stack'            => [
				'value_type'   => null,
				'cmf_type'     => null,
				'is_container' => true,
				'holds_value'  => false,
			],
			'toolbar_group'    => [
				'value_type'   => null,
				'cmf_type'     => null,
				'is_container' => true,
				'holds_value'  => false,
			],
			'repeater'         => [
				'value_type'   => 'array',
				'cmf_type'     => 'repeater',
				'is_container' => true,
				'holds_value'  => true,
			],

			// --- Toolbar-only controls. ---
			'toolbar_button'   => [
				'value_type'   => null,
				'cmf_type'     => null,
				'is_container' => false,
				'holds_value'  => false,
			],
			'toolbar_toggle'   => [
				'value_type'   => 'boolean',
				'cmf_type'     => 'checkbox',
				'is_container' => false,
				'holds_value'  => true,
			],
			'toolbar_dropdown' => [
				'value_type'   => 'string',
				'cmf_type'     => 'select',
				'is_container' => false,
				'holds_value'  => true,
			],
			'toolbar_menu'     => [
				'value_type'   => 'string',
				'cmf_type'     => 'select',
				'is_container' => false,
				'holds_value'  => true,
			],
			'toolbar_link'     => [
				'value_type'   => null,
				'cmf_type'     => null,
				'is_container' => false,
				'holds_value'  => false,
			],
			'toolbar_align'    => [
				'value_type'   => 'string',
				'cmf_type'     => null,
				'is_container' => false,
				'holds_value'  => true,
			],
		];

		self::$types = array_merge( self::$types, $custom_types );

		self::$defaults_registered = true;

		if ( function_exists( 'apply_filters' ) ) {
			/**
			 * Filters the whole control-type catalog after defaults are registered.
			 *
			 * Deliberately fixes a gap in the parent library: Field_Factory has
			 * no equivalent filter over its type registry.
			 *
			 * @param array<string, array<string, mixed>> $types Control type specs, keyed by type name.
			 */
			$filtered = apply_filters( 'cassette_cmf_blocks_control_catalog', self::$types );

			if ( is_array( $filtered ) ) {
				self::$types = self::normalize_filtered( $filtered );
			}
		}
	}

	/**
	 * Complete the specs a catalog filter returned; a malformed entry is
	 * dropped with a notice instead of breaking every block.
	 *
	 * @param array<mixed> $filtered Filtered catalog.
	 * @return array<string, array<string, mixed>>
	 */
	private static function normalize_filtered( array $filtered ): array {
		$types = [];

		foreach ( $filtered as $type => $spec ) {
			try {
				$types[ (string) $type ] = self::normalize_spec( (string) $type, is_array( $spec ) ? $spec : [] );
			} catch ( \InvalidArgumentException $e ) {
				_doing_it_wrong( 'cassette_cmf_blocks_control_catalog', esc_html( $e->getMessage() ), '0.2.0' );
			}
		}

		return $types;
	}

	/**
	 * Get a control type's spec.
	 *
	 * @api
	 * @since 0.2.0
	 * @param string $type Control type identifier.
	 * @return array<string, mixed>|null
	 */
	public static function get_type( string $type ): ?array {
		self::maybe_register_defaults();

		return self::$types[ $type ] ?? null;
	}

	/**
	 * Whether a control type is registered.
	 *
	 * @api
	 * @since 0.2.0
	 * @param string $type Control type identifier.
	 * @return bool
	 */
	public static function has_type( string $type ): bool {
		self::maybe_register_defaults();

		return isset( self::$types[ $type ] );
	}

	/**
	 * Get all registered control type specs.
	 *
	 * @api
	 * @since 0.2.0
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_registered_types(): array {
		self::maybe_register_defaults();

		return self::$types;
	}

	/**
	 * Unregister a control type.
	 *
	 * @internal Not part of the stable API.
	 * @param string $type Control type identifier.
	 * @return void
	 */
	public static function unregister_type( string $type ): void {
		unset( self::$types[ $type ] );
	}

	/**
	 * Reset the catalog. Test-only.
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$types               = [];
		self::$defaults_registered = false;
	}

	/**
	 * Register defaults on first use, mirroring Field_Factory::create()'s
	 * lazy-registration pattern.
	 *
	 * @return void
	 */
	private static function maybe_register_defaults(): void {
		if ( ! self::$defaults_registered ) {
			self::register_defaults();
		}
	}
}
