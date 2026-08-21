<?php
/**
 * Area_Resolver class for Cassette-CMF Blocks
 *
 * Parses and validates the dot-notation "area" string a field or panel can
 * declare (e.g. "inspector.styles", "toolbar.block", "canvas"). The set of
 * known inspector groups below was verified against the block-editor bundle
 * of the WordPress version installed in this environment (7.0.4), not
 * against this library's stated floor (6.8) — some entries may postdate
 * 6.8. The editor runtime (later milestone) must feature-detect a group's
 * existence client-side rather than trust this list blindly, because
 * filling a nonexistent group renders nothing and silently drops fields.
 *
 * @package Pedalcms\CassetteCmfBlocks
 * @since 0.1.0
 */

namespace Pedalcms\CassetteCmfBlocks\Schema;

/**
 * Class Area_Resolver
 */
class Area_Resolver {

	/**
	 * The default area used when none is declared.
	 *
	 * @var string
	 */
	public const DEFAULT_AREA = 'inspector';

	/**
	 * Known "<surface>[.<group>]" combinations.
	 *
	 * @var string[]
	 */
	private const KNOWN_AREAS = [
		'inspector',
		'inspector.styles',
		'inspector.advanced',
		'inspector.list',
		'inspector.color',
		'inspector.typography',
		'inspector.border',
		'inspector.dimensions',
		'inspector.position',
		'inspector.background',
		'inspector.bindings',
		'inspector.content',
		'inspector.effects',
		'inspector.filter',
		'inspector.allowed_blocks',
		'inspector.last_item',
		'toolbar',
		'toolbar.block',
		'toolbar.inline',
		'toolbar.other',
		'toolbar.parent',
		'canvas',
		'placeholder',
		'document',
		'document.status',
		'document.excerpt',
		'document.publish.pre',
		'document.publish.post',
		'sidebar',
		'more_menu',
	];

	/**
	 * Surfaces whose fields belong to the editor as a whole rather than to a
	 * single block instance. A field/panel declaring one of these areas is
	 * only legal when the block entry also declares "document_scope".
	 *
	 * @var string[]
	 */
	private const DOCUMENT_SCOPED_SURFACES = [ 'document', 'sidebar', 'more_menu' ];

	/**
	 * Normalize an area string, falling back to the default for an empty or
	 * unknown value. Never throws — an unknown area is a _doing_it_wrong(),
	 * not a fatal, so a typo in one field doesn't take down a whole block.
	 *
	 * @param string|null $area Declared area, or null/empty for the default.
	 * @return string
	 */
	public static function normalize( ?string $area ): string {
		if ( null === $area || '' === $area ) {
			return self::DEFAULT_AREA;
		}

		if ( ! self::is_known( $area ) ) {
			if ( function_exists( '_doing_it_wrong' ) ) {
				// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- _doing_it_wrong() messages are developer-facing debug notices, not page output.
				_doing_it_wrong(
					__METHOD__,
					sprintf(
						/* translators: 1: the unknown area string, 2: the area it was replaced with. */
						'Unknown block field area "%1$s"; falling back to "%2$s".',
						$area,
						self::DEFAULT_AREA
					),
					'0.1.0'
				);
				// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
			}

			return self::DEFAULT_AREA;
		}

		return $area;
	}

	/**
	 * Whether the given area string is one of the known "<surface>[.<group>]" values.
	 *
	 * @param string $area Area string.
	 * @return bool
	 */
	public static function is_known( string $area ): bool {
		return in_array( $area, self::KNOWN_AREAS, true );
	}

	/**
	 * Split a normalized area into its surface and group.
	 *
	 * @param string $area Normalized area string.
	 * @return array{surface: string, group: string|null}
	 */
	public static function parse( string $area ): array {
		$parts = explode( '.', $area, 2 );

		return [
			'surface' => $parts[0],
			'group'   => $parts[1] ?? null,
		];
	}

	/**
	 * Whether an area belongs to the editor as a whole (document/sidebar/more-menu)
	 * rather than to a single block instance, and therefore requires the
	 * block entry to declare "document_scope".
	 *
	 * @param string $area Normalized area string.
	 * @return bool
	 */
	public static function requires_document_scope( string $area ): bool {
		return in_array( self::parse( $area )['surface'], self::DOCUMENT_SCOPED_SURFACES, true );
	}

	/**
	 * All known areas, for schema/validator use.
	 *
	 * @return string[]
	 */
	public static function known_areas(): array {
		return self::KNOWN_AREAS;
	}
}
