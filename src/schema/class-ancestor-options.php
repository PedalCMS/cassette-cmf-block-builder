<?php
/**
 * Ancestor_Options class for Cassette-CMF Blocks
 *
 * Validates and canonicalizes the options that tell the editor which blocks
 * a field may reference by name: the config of the `ancestor_field` control
 * and of the `conditions:` setting of a `conditions` control (the same
 * options, so both pickers behave identically).
 *
 * Options (snake_case in PHP/JSON, shipped to the editor as given):
 *
 *   ancestor        string, required. Block name of the ancestor whose
 *                   descendants are listed, e.g. "vendor/form".
 *   blocks          string[], optional allow-list of block names.
 *   has_attribute   string, optional. Only blocks whose attribute of that
 *                   name is non-empty are listed.
 *   name_attribute  string, default "name". The attribute that holds a
 *                   block's reference name.
 *   label_attribute string, default "label". The attribute shown as label.
 *   exclude_self    bool, default true. Leave the block itself out.
 *
 * @package PedalCMS\CassetteCMFBlocks
 * @since 0.2.0
 */

namespace PedalCMS\CassetteCMFBlocks\Schema;

/**
 * Class Ancestor_Options
 */
class Ancestor_Options {

	/**
	 * Canonicalize raw options.
	 *
	 * @api
	 * @param mixed  $raw        Raw options (an array).
	 * @param string $field_name The owning field's name, for error messages.
	 * @param string $where      Where the options sit ("ancestor_field" or "conditions"), for error messages.
	 * @return array{ancestor: string, blocks: string[], has_attribute: string, name_attribute: string, label_attribute: string, exclude_self: bool}
	 * @throws \InvalidArgumentException On a missing or malformed option.
	 */
	public static function normalize( $raw, string $field_name, string $where ): array {
		$prefix = sprintf( 'Field "%1$s" (%2$s)', $field_name, $where );

		if ( ! is_array( $raw ) ) {
			throw new \InvalidArgumentException( sprintf( '%s: options must be an object with at least "ancestor".', $prefix ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$ancestor = $raw['ancestor'] ?? null;
		if ( ! is_string( $ancestor ) || ! preg_match( '#^[a-z][a-z0-9-]*/[a-z][a-z0-9-]*$#', $ancestor ) ) {
			throw new \InvalidArgumentException( sprintf( '%s: "ancestor" is required and must be a block name such as "vendor/form".', $prefix ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$blocks = $raw['blocks'] ?? [];
		if ( ! is_array( $blocks ) || array_filter( $blocks, 'is_string' ) !== $blocks ) {
			throw new \InvalidArgumentException( sprintf( '%s: "blocks" must be a list of block names.', $prefix ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$strings = [];
		foreach ( [
			'has_attribute'   => '',
			'name_attribute'  => 'name',
			'label_attribute' => 'label',
		] as $key => $default ) {
			$value = $raw[ $key ] ?? $default;
			if ( ! is_string( $value ) || ( '' === $value && 'has_attribute' !== $key ) ) {
				throw new \InvalidArgumentException( sprintf( '%1$s: "%2$s" must be an attribute name.', $prefix, $key ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
			$strings[ $key ] = $value;
		}

		return [
			'ancestor'        => $ancestor,
			'blocks'          => array_values( $blocks ),
			'has_attribute'   => $strings['has_attribute'],
			'name_attribute'  => $strings['name_attribute'],
			'label_attribute' => $strings['label_attribute'],
			'exclude_self'    => ! array_key_exists( 'exclude_self', $raw ) || (bool) $raw['exclude_self'],
		];
	}
}
