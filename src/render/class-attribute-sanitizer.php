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
 * @since 0.1.0
 */

namespace PedalCMS\CassetteCMFBlocks\Render;

use PedalCMS\CassetteCMFBlocks\Compat\Cmf_Bridge;
use PedalCMS\CassetteCMFBlocks\Core\Field_Collection;

/**
 * Class Attribute_Sanitizer
 */
class Attribute_Sanitizer {

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
	 * A field with no "cmf_type" (no honest parent-library sanitizer — see
	 * Control_Catalog) is left as-is; Markup_Renderer's output escaping
	 * remains the safeguard for those.
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

			if ( ! is_string( $name ) || 'attribute' !== $source || null === $cmf_type ) {
				continue;
			}

			if ( ! array_key_exists( $name, $attributes ) ) {
				continue;
			}

			$attributes[ $name ] = Cmf_Bridge::sanitize( $cmf_type, $config, $attributes[ $name ] );
		}

		return $attributes;
	}
}
