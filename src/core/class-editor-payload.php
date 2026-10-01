<?php
/**
 * Editor_Payload class for Cassette-CMF Blocks
 *
 * Builds the single JSON payload the editor bootstrap reads from
 * window.cassetteCmfBlocks. This is the whole PHP-to-JS bridge: one
 * consolidated inline script rather than a REST round-trip (which would
 * make every block "core/missing" during the editor's synchronous initial
 * parse of post_content) or a generated block.json on disk (vendor/ is
 * read-only on many deploys).
 *
 * @package PedalCMS\CassetteCMFBlocks
 * @since 0.1.0
 */

namespace PedalCMS\CassetteCMFBlocks\Core;

use PedalCMS\CassetteCMFBlocks\Compat\Cmf_Bridge;
use PedalCMS\CassetteCMFBlocks\Compat\Requirements;
use PedalCMS\CassetteCMFBlocks\Render\Deprecation_Builder;
use PedalCMS\CassetteCMFBlocks\Schema\Area_Resolver;

/**
 * Class Editor_Payload
 */
class Editor_Payload {

	/**
	 * Object-cache group and transient-key prefix for the cached payload.
	 *
	 * @var string
	 */
	private const CACHE_GROUP = 'cassette_cmf_blocks';

	/**
	 * How long the transient fallback (no persistent object cache) keeps a
	 * cached payload before recomputing. A day is generous but bounded —
	 * this is a fallback for the "no persistent cache" case, not the
	 * primary invalidation mechanism (the cache key itself already changes
	 * whenever the config, either library's version, or the locale does).
	 *
	 * @var int
	 */
	private const TRANSIENT_TTL = DAY_IN_SECONDS;

	/**
	 * build(), memoized in the object cache (with a transient fallback for a
	 * site with no persistent object cache), keyed on everything that could
	 * change the payload: the raw block config, this library's own asset
	 * version, the parent library's version (its sanitize()/conditional-
	 * normalisation behaviour can change between versions), and the current
	 * locale (a Spanish admin must never see labels cached from an English
	 * request). Skipped entirely under WP_DEBUG/SCRIPT_DEBUG, so a developer
	 * iterating on block config always sees a fresh payload.
	 *
	 * Asset_Loader::enqueue() is the only caller — building this fresh is
	 * genuinely non-trivial work (compiling every block, walking every
	 * field tree) that has no reason to repeat on every single block-editor
	 * page load once a site's config has settled.
	 *
	 * @param Block_Manager $manager       See build().
	 * @param string        $asset_version See build().
	 * @return array<string, mixed> See build().
	 */
	public static function build_cached( Block_Manager $manager, string $asset_version = '' ): array {
		if ( self::debug_mode_active() ) {
			return self::build( $manager, $asset_version );
		}

		$key = self::cache_key( $manager, $asset_version );

		$cached = wp_cache_get( $key, self::CACHE_GROUP );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$transient_key = 'ccb_payload_' . $key;
		$cached        = get_transient( $transient_key );
		if ( is_array( $cached ) ) {
			wp_cache_set( $key, $cached, self::CACHE_GROUP );

			return $cached;
		}

		$payload = self::build( $manager, $asset_version );

		wp_cache_set( $key, $payload, self::CACHE_GROUP );
		set_transient( $transient_key, $payload, self::TRANSIENT_TTL );

		return $payload;
	}

	/**
	 * Whether debug mode is active, in which case caching is skipped
	 * entirely so a developer editing block config always sees a fresh
	 * payload rather than a stale cached one.
	 *
	 * Filterable: mainly so this class's own test suite can exercise the
	 * caching path despite the WP core test bootstrap defining WP_DEBUG
	 * (a real PHP constant, which — unlike everything else this library
	 * reads — can't be un-defined once set), but equally usable by a real
	 * site that wants caching on during debugging (e.g. load-testing a
	 * staging environment with SCRIPT_DEBUG still enabled) or forced off in
	 * production for some other reason.
	 *
	 * @return bool
	 */
	private static function debug_mode_active(): bool {
		$debug = ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG );

		if ( function_exists( 'apply_filters' ) ) {
			/**
			 * Filters whether Editor_Payload::build_cached() skips its cache
			 * (recomputing on every call), overriding the WP_DEBUG/SCRIPT_DEBUG
			 * default.
			 *
			 * @param bool $debug The default WP_DEBUG/SCRIPT_DEBUG-derived value.
			 */
			$debug = (bool) apply_filters( 'cassette_cmf_blocks_payload_cache_skip', $debug );
		}

		return $debug;
	}

	/**
	 * Build the cache key: a config hash plus every other input that could
	 * change the payload without changing the config itself.
	 *
	 * @param Block_Manager $manager       Whose raw config to hash.
	 * @param string        $asset_version This library's own asset version.
	 * @return string
	 */
	private static function cache_key( Block_Manager $manager, string $asset_version ): string {
		$config_hash = md5( (string) wp_json_encode( $manager->get_blocks() ) );
		$cmf_version = Requirements::cmf_version() ?? '';
		$locale      = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();

		return md5( $config_hash . '|' . $asset_version . '|' . $cmf_version . '|' . $locale );
	}

	/**
	 * Build the payload for one registered block, "settings" shaped for a
	 * client-side registerBlockType() call.
	 *
	 * Deliberately built from Block_Definition::get_args() (still
	 * block.json's camelCase), never to_block_type_args() (translated to
	 * the snake_case WP_Block_Type's PHP constructor expects) — the client
	 * bundle's registerBlockType() reads the same camelCase keys block.json
	 * uses, so shipping the snake_case form would silently misconfigure the
	 * block in the editor even though the server-side registration is
	 * correct.
	 *
	 * @param Block_Manager $manager       The block manager whose registered raw configs to compile and describe.
	 * @param string        $asset_version The editor script's own version (Asset_Loader's @wordpress/scripts content
	 *                                     hash from editor.asset.php), included for payload debugging only — this
	 *                                     class deliberately doesn't read composer.json for a version, since this
	 *                                     library's own composer.json omits "version" by design (see CHANGELOG).
	 * @return array<string, mixed> {
	 *     @type string                                                             $version Editor script version, for debugging only.
	 *     @type array<string, array{name: string, settings: array, fields: array}> $blocks  Keyed by block name.
	 * }
	 */
	public static function build( Block_Manager $manager, string $asset_version = '' ): array {
		$blocks = [];

		foreach ( array_keys( $manager->get_blocks() ) as $id ) {
			try {
				$definition = $manager->compile_block( $id );
			} catch ( \InvalidArgumentException $e ) {
				// Already _doing_it_wrong()'d by Block_Manager::register_blocks();
				// a block that failed to compile has nothing to describe here.
				continue;
			}

			$raw_config  = $definition->get_raw_config();
			$editor_tree = $definition->get_fields()->to_editor_tree();

			$blocks[ $id ] = [
				'name'          => $definition->get_name(),
				'settings'      => array_merge(
					self::client_safe_args( $definition->get_args() ),
					[ 'attributes' => $definition->get_attributes() ]
				),
				'fields'        => $editor_tree,
				'render'        => self::render_payload( $raw_config['render'] ?? [], $definition->get_attributes() ),
				'preview'       => self::preview_payload( $raw_config['preview'] ?? [] ),
				'innerBlocks'   => self::inner_blocks_payload( is_array( $raw_config['inner_blocks'] ?? null ) ? $raw_config['inner_blocks'] : [] ),
				'transforms'    => is_array( $raw_config['transforms'] ?? null ) ? $raw_config['transforms'] : null,
				'documentScope' => self::document_scope_payload( $editor_tree, $raw_config['document_scope'] ?? null, $id ),
			];
		}

		return [
			'version' => $asset_version,
			'blocks'  => $blocks,
		];
	}

	/**
	 * Strip PHP-only, non-JSON-safe entries out of a block's "args" before
	 * they reach the client payload. "variation_callback" (Core\Variation_Registrar's
	 * own docblock explains why it's a real PHP callable, never normalized
	 * data) is the one args key known to carry one; any other Closure value
	 * a consumer's own config happens to smuggle into "args" is dropped
	 * defensively too — wp_json_encode() on a Closure fails outright, which
	 * would otherwise break this block's entire inline payload script, not
	 * just the one offending key.
	 *
	 * @param array<string, mixed> $args Normalized "args" passthrough.
	 * @return array<string, mixed>
	 */
	private static function client_safe_args( array $args ): array {
		unset( $args['variation_callback'] );

		return array_filter(
			$args,
			static function ( $value ): bool {
				return ! ( $value instanceof \Closure );
			}
		);
	}

	/**
	 * Resolve a block's "documentScope" payload entry: null when the block
	 * has no document/sidebar/more_menu-area top-level field at all
	 * (nothing to gate), null (with a Document_Scope_Registrar-logged
	 * _doing_it_wrong()) when it has one but no valid "document_scope"
	 * config, or the normalized scope otherwise. areas/document.js only
	 * ever registers a plugin for a block when this is non-null.
	 *
	 * @param array<int, array<string, mixed>> $editor_tree      This block's resolved top-level field tree.
	 * @param mixed                            $raw_scope        Raw "document_scope" config.
	 * @param string                           $block_id         The block's name.
	 * @return array{postTypes: string[], whenPresent: bool, target: string}|null
	 */
	private static function document_scope_payload( array $editor_tree, $raw_scope, string $block_id ): ?array {
		$needs_scope = false;

		foreach ( $editor_tree as $node ) {
			if ( Area_Resolver::requires_document_scope( $node['area'] ) ) {
				$needs_scope = true;
				break;
			}
		}

		if ( ! $needs_scope ) {
			return null;
		}

		return Document_Scope_Registrar::normalize( $raw_scope, $block_id );
	}

	/**
	 * The subset of a block's "render" config the editor needs: the
	 * declarative "markup" tree (for CanvasArea's client-side preview and,
	 * for a "static"-mode block, save()), a content hash of that markup
	 * plus the current attribute schema (Render\Deprecation_Builder — for
	 * introspection/tooling, not consumed by this milestone's own code),
	 * and "deprecated" (the consumer-declared markup/schema history —
	 * deprecations.js's buildDeprecations() turns this into
	 * registerBlockType()'s own "deprecated" array). Never "callback" (a
	 * PHP callable has no meaning client-side) or "template" (a server
	 * filesystem path).
	 *
	 * @param array<string, mixed>               $render     Raw "render" config.
	 * @param array<string, array<string, mixed>> $attributes The block's resolved WP attribute schema.
	 * @return array{mode: string, markup: array<string, mixed>|null, hash: string, deprecated: array<int, array<string, mixed>>}
	 */
	private static function render_payload( array $render, array $attributes ): array {
		// The hash is computed from the RAW markup, before "when" normalization —
		// normalization is a pure function of the raw config, so this doesn't
		// weaken Deprecation_Builder's "did the config actually change" signal,
		// and hashing before normalizing means the hash is unaffected by any
		// future change to how normalization itself works.
		$raw_markup = is_array( $render['markup'] ?? null ) ? $render['markup'] : null;

		return [
			'mode'       => $render['mode'] ?? 'dynamic',
			'markup'     => self::normalize_markup_conditions( $raw_markup ),
			'hash'       => Deprecation_Builder::hash( $raw_markup ?? [], $attributes ),
			'deprecated' => self::deprecated_payload( is_array( $render['deprecated'] ?? null ) ? $render['deprecated'] : [] ),
		];
	}

	/**
	 * Recursively normalize every "when" in a markup tree (via
	 * Compat\Cmf_Bridge::normalize_conditional(), still reusing the parent
	 * library verbatim), the markup-node counterpart of
	 * Field_Collection::to_editor_tree()'s "conditional" normalization —
	 * see that method's docblock for why: conditions/evaluate.js should
	 * never see an operator alias like "equals", only the canonical form.
	 * A "when" that normalizes to nothing (no real rules) is omitted, so
	 * the editor's own "does this node have a condition" check is a plain
	 * isset().
	 *
	 * @param array<string, mixed>|null $node A markup node (or null for "no markup declared").
	 * @return array<string, mixed>|null
	 */
	private static function normalize_markup_conditions( ?array $node ): ?array {
		if ( null === $node ) {
			return null;
		}

		if ( isset( $node['when'] ) && is_array( $node['when'] ) ) {
			$normalized = Cmf_Bridge::normalize_conditional( $node['when'] );

			if ( empty( $normalized ) ) {
				unset( $node['when'] );
			} else {
				$node['when'] = $normalized;
			}
		}

		if ( isset( $node['children'] ) && is_array( $node['children'] ) ) {
			foreach ( $node['children'] as $index => $child ) {
				if ( is_array( $child ) ) {
					$node['children'][ $index ] = self::normalize_markup_conditions( $child );
				}
			}
		}

		return $node;
	}

	/**
	 * The subset of each "render.deprecated[]" entry the editor needs:
	 * "markup" (required — an entry with none is meaningless, so it's
	 * skipped rather than shipped broken), "attributes" (the old WP
	 * attribute schema; omitted lets deprecations.js fall back to the
	 * block's current schema, the common case for a markup-only change),
	 * and "migrate" (a plain string name — deprecations.js resolves it
	 * against whatever the consumer registered via the JS escape hatch;
	 * PHP config has no way to embed a real function).
	 *
	 * @param array<int, mixed> $deprecated Raw "render.deprecated" entries.
	 * @return array<int, array{markup: array<string, mixed>, attributes: array<string, mixed>|null, migrate: string|null}>
	 */
	private static function deprecated_payload( array $deprecated ): array {
		$entries = [];

		foreach ( $deprecated as $entry ) {
			if ( ! is_array( $entry ) || ! is_array( $entry['markup'] ?? null ) ) {
				continue;
			}

			$entries[] = [
				'markup'     => self::normalize_markup_conditions( $entry['markup'] ),
				'attributes' => is_array( $entry['attributes'] ?? null ) ? $entry['attributes'] : null,
				'migrate'    => is_string( $entry['migrate'] ?? null ) ? $entry['migrate'] : null,
			];
		}

		return $entries;
	}

	/**
	 * The subset of a block's top-level "inner_blocks" config the editor
	 * needs to render a real, editable <InnerBlocks /> in place of a
	 * "slot": "inner_blocks" markup node — see areas/canvas.js's
	 * "renderSlot" wiring. Declaring the "inner_blocks" key at all implies
	 * "enabled" unless explicitly set to false, so a consumer doesn't have
	 * to redundantly say both "here's my inner_blocks config" and
	 * "enabled: true".
	 *
	 * @param array<string, mixed> $inner_blocks Raw top-level "inner_blocks" config.
	 * @return array{enabled: bool, allowed?: array<int, string>|null, template?: array<int, mixed>|null, templateLock?: string|false|null, orientation?: string|null}
	 */
	private static function inner_blocks_payload( array $inner_blocks ): array {
		if ( empty( $inner_blocks ) ) {
			return [ 'enabled' => false ];
		}

		$enabled = ! array_key_exists( 'enabled', $inner_blocks ) || (bool) $inner_blocks['enabled'];

		if ( ! $enabled ) {
			return [ 'enabled' => false ];
		}

		$template_lock = $inner_blocks['template_lock'] ?? null;
		if ( ! is_string( $template_lock ) && false !== $template_lock ) {
			$template_lock = null;
		}

		return [
			'enabled'      => true,
			'allowed'      => is_array( $inner_blocks['allowed'] ?? null ) ? array_values( $inner_blocks['allowed'] ) : null,
			'template'     => is_array( $inner_blocks['template'] ?? null ) ? $inner_blocks['template'] : null,
			'templateLock' => $template_lock,
			'orientation'  => is_string( $inner_blocks['orientation'] ?? null ) ? $inner_blocks['orientation'] : null,
		];
	}

	/**
	 * The subset of a block's "preview" config the editor needs. "markup"
	 * overrides render.markup for the editor preview specifically when set;
	 * CanvasArea falls back to render.markup when it isn't (the plan's "one
	 * declarative markup tree drives three runtimes" default).
	 *
	 * @param array<string, mixed> $preview Raw "preview" config.
	 * @return array{mode: string, markup: array<string, mixed>|null}
	 */
	private static function preview_payload( array $preview ): array {
		return [
			'mode'   => $preview['mode'] ?? 'markup',
			'markup' => self::normalize_markup_conditions( is_array( $preview['markup'] ?? null ) ? $preview['markup'] : null ),
		];
	}
}
