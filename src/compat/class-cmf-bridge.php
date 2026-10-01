<?php
/**
 * Cmf_Bridge class for Cassette-CMF Blocks
 *
 * The single point of contact with pedalcms/cassette-cmf. Every other class
 * in this library that needs the parent's sanitize()/validate()/conditional
 * logic goes through here rather than calling Field_Factory directly, so the
 * parent-coupling surface stays auditable in one file.
 *
 * Deliberately reuses only sanitize(), validate(), and conditional
 * normalisation/evaluation — never render(), which produces jQuery-oriented
 * admin HTML unsuited to the block editor.
 *
 * @package PedalCMS\CassetteCMFBlocks
 * @since 0.1.0
 */

namespace PedalCMS\CassetteCMFBlocks\Compat;

use PedalCMS\CassetteCMF\Field\Field_Factory;
use PedalCMS\CassetteCMF\Field\Field_Interface;
use PedalCMS\CassetteCMF\Field\Abstract_Field;

/**
 * Class Cmf_Bridge
 */
class Cmf_Bridge {

	/**
	 * Sanitize a value using one of the parent library's field types.
	 *
	 * @param string               $cmf_type     One of the parent's registered field types (e.g. "text", "number").
	 * @param array<string, mixed> $field_config Field configuration understood by the parent (name, options, etc.).
	 * @param mixed                $value        Raw value to sanitize.
	 * @return mixed
	 */
	public static function sanitize( string $cmf_type, array $field_config, $value ) {
		return self::make_field( $cmf_type, $field_config )->sanitize( $value );
	}

	/**
	 * Validate a value using one of the parent library's field types.
	 *
	 * @param string               $cmf_type     One of the parent's registered field types.
	 * @param array<string, mixed> $field_config Field configuration understood by the parent.
	 * @param mixed                $value        Value to validate.
	 * @return array{valid: bool, errors: string[]}
	 */
	public static function validate( string $cmf_type, array $field_config, $value ): array {
		return self::make_field( $cmf_type, $field_config )->validate( $value );
	}

	/**
	 * Normalize a raw conditional-logic config into the parent's canonical
	 * { relation, rules[] } shape (operator aliases resolved, "value"
	 * stripped from empty/not_empty rules).
	 *
	 * Reached through get_schema()['conditional'], which is public on
	 * Field_Interface — see Field_Interface::get_schema() and
	 * Abstract_Field::get_schema() (returns get_conditional_config()).
	 * No upstream change to the parent library is required for this.
	 *
	 * @param array<string, mixed> $raw_conditional Raw conditional config, parent's shorthand or full shape.
	 * @return array<string, mixed> Normalized { relation, rules[] }, or [] when there is nothing to evaluate.
	 */
	public static function normalize_conditional( array $raw_conditional ): array {
		$field = self::make_field( 'text', [ 'conditional' => $raw_conditional ] );

		return $field->get_schema()['conditional'] ?? [];
	}

	/**
	 * Evaluate a raw conditional-logic config against a value context.
	 *
	 * @param array<string, mixed> $raw_conditional Raw conditional config.
	 * @param array<string, mixed> $context         Field-name => value map to evaluate against.
	 * @return bool True when the field should be considered visible/active.
	 */
	public static function is_condition_met( array $raw_conditional, array $context ): bool {
		$field = self::make_field( 'text', [ 'conditional' => $raw_conditional ] );

		if ( ! $field instanceof Abstract_Field ) {
			// A custom Field_Interface implementation that doesn't extend
			// Abstract_Field has no is_condition_met(); treat as always visible
			// rather than fatal.
			return true;
		}

		return $field->is_condition_met( $context );
	}

	/**
	 * Whether the given type is registered with the parent's Field_Factory.
	 *
	 * @param string $cmf_type Field type identifier.
	 * @return bool
	 */
	public static function has_cmf_type( string $cmf_type ): bool {
		return Field_Factory::has_type( $cmf_type );
	}

	/**
	 * Get every field type registered with the parent's Field_Factory.
	 *
	 * @return array<string, string> Map of type name to class name.
	 */
	public static function get_cmf_types(): array {
		return Field_Factory::get_registered_types();
	}

	/**
	 * Build a throwaway parent-library field instance.
	 *
	 * The parent's Field_Factory::create() requires "name" and "type"; a
	 * fixed placeholder name is fine here because these instances are never
	 * rendered and never persisted — they exist only to reuse sanitize(),
	 * validate(), and conditional normalisation/evaluation.
	 *
	 * @param string               $cmf_type     Field type identifier.
	 * @param array<string, mixed> $field_config Field configuration (without name/type).
	 * @return Field_Interface
	 */
	private static function make_field( string $cmf_type, array $field_config ): Field_Interface {
		$field_config['name'] = $field_config['name'] ?? '_cassette_cmf_blocks_bridge';
		$field_config['type'] = $cmf_type;

		return Field_Factory::create( $field_config );
	}
}
