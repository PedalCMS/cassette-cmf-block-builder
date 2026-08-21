<?php
/**
 * Control_Mapper class for Cassette-CMF Blocks
 *
 * Resolves a single field config's declared "type" against the
 * Control_Catalog, applying any inline "cmf_type" override, and rejecting
 * control types that have no honest block-editor equivalent.
 *
 * @package Pedalcms\CassetteCmfBlocks
 * @since 0.1.0
 */

namespace Pedalcms\CassetteCmfBlocks\Schema;

/**
 * Class Control_Mapper
 */
class Control_Mapper {

	/**
	 * Control types explicitly rejected, with the reason surfaced in the
	 * exception. These are parent-library field types that never got a
	 * Control_Catalog entry on purpose (see docs/control-catalog.md).
	 *
	 * @var array<string, string>
	 */
	private const BANNED_TYPES = [
		'password' => 'Block attributes live in post_content, which is readable via the REST API and often the front end. There is no configuration under which storing a secret there is acceptable. Keep the secret in a parent-library settings-page field and reference it from a block via a binding source instead.',
	];

	/**
	 * Resolve a field config's control type against the catalog.
	 *
	 * @param array<string, mixed> $field_config Field configuration ("type", optionally "cmf_type").
	 * @return array{type: string, spec: array<string, mixed>, cmf_type: string|null}
	 * @throws \InvalidArgumentException If the type is unknown or explicitly banned.
	 */
	public static function resolve( array $field_config ): array {
		if ( empty( $field_config['type'] ) ) {
			throw new \InvalidArgumentException( 'Field config must include "type".' );
		}

		$type = (string) $field_config['type'];

		if ( isset( self::BANNED_TYPES[ $type ] ) ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages don't need escaping.
			throw new \InvalidArgumentException(
				sprintf( 'Control type "%1$s" is not supported: %2$s', $type, self::BANNED_TYPES[ $type ] )
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$spec = Control_Catalog::get_type( $type );

		if ( null === $spec ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages don't need escaping.
			throw new \InvalidArgumentException(
				sprintf( 'Unknown control type "%s". Register it with Control_Catalog::register_type().', $type )
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$cmf_type = $field_config['cmf_type'] ?? $spec['cmf_type'];

		return [
			'type'     => $type,
			'spec'     => $spec,
			'cmf_type' => $cmf_type,
		];
	}
}
