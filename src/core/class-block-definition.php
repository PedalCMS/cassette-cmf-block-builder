<?php
/**
 * Block_Definition class for Cassette-CMF Blocks
 *
 * A value object built from one block's raw configuration array. Resolves
 * fields into a Field_Collection, maps them into a WP attribute schema, and
 * assembles the args register_block_type() expects.
 *
 * @package PedalCMS\CassetteCMFBlocks
 * @since 0.2.0
 */

namespace PedalCMS\CassetteCMFBlocks\Core;

use PedalCMS\CassetteCMFBlocks\Render\Attribute_Sanitizer;
use PedalCMS\CassetteCMFBlocks\Render\Block_Renderer;
use PedalCMS\CassetteCMFBlocks\Render\Deprecation_Migrator;
use PedalCMS\CassetteCMFBlocks\Schema\Attribute_Schema_Mapper;
use PedalCMS\CassetteCMFBlocks\Schema\Supports_Normalizer;
use PedalCMS\CassetteCMFBlocks\Schema\Transform_Config;

/**
 * Class Block_Definition
 */
class Block_Definition {

	/**
	 * block.json-style camelCase keys mapped to the snake_case property
	 * names WP_Block_Type's constructor actually reads.
	 *
	 * register_block_type($name, $args) — the call this library uses —
	 * goes straight to WP_Block_Type_Registry::register(), which never runs
	 * the camelCase-to-snake_case translation that
	 * register_block_type_from_metadata() performs via its own
	 * $property_mappings table (wp-includes/blocks.php). That translation
	 * only fires on the block.json-file registration path. Passing
	 * "allowedBlocks" (or any other camelCase key below) straight through
	 * to register_block_type() is silently ignored — WP_Block_Type has no
	 * such property — rather than erroring, so the mistake is invisible
	 * without reading the WP core source. This table replicates that
	 * translation so "args" can stay a genuine block.json passthrough as
	 * documented, while still reaching the right WP_Block_Type property.
	 *
	 * @var array<string, string>
	 */
	private const CAMEL_TO_SNAKE = [
		'apiVersion'      => 'api_version',
		'providesContext' => 'provides_context',
		'usesContext'     => 'uses_context',
		'allowedBlocks'   => 'allowed_blocks',
		'blockHooks'      => 'block_hooks',
		// title, category, parent, ancestor, icon, description, keywords,
		// selectors, supports, styles, variations, example, textdomain are
		// already the same string in both casings (or camelCase-free), so
		// no entry is needed — they pass through unchanged below.
	];

	/**
	 * First-class asset keys: block.json camelCase name => the
	 * register_block_type() key it maps to. Each takes a string or a list of
	 * strings (registered script/style handles, or script module IDs for
	 * "viewScriptModule"); block.json "file:" paths are not supported.
	 *
	 * @var array<string, string>
	 */
	public const ASSET_KEYS = [
		'editorScript'     => 'editor_script_handles',
		'editorStyle'      => 'editor_style_handles',
		'script'           => 'script_handles',
		'style'            => 'style_handles',
		'viewScript'       => 'view_script_handles',
		'viewStyle'        => 'view_style_handles',
		'viewScriptModule' => 'view_script_module_ids',
	];

	/**
	 * Every non-asset "args" key this library accepts. Anything outside this
	 * list and ASSET_KEYS is dropped with a _doing_it_wrong() notice.
	 *
	 * "variation_callback" is the one snake_case entry: a
	 * register_block_type()-only concept with no block.json equivalent.
	 *
	 * @var string[]
	 */
	private const ALLOWED_ARGS = [
		'apiVersion',
		'title',
		'category',
		'description',
		'icon',
		'keywords',
		'textdomain',
		'parent',
		'ancestor',
		'allowedBlocks',
		'blockHooks',
		'providesContext',
		'usesContext',
		'selectors',
		'supports',
		'styles',
		'variations',
		'variation_callback',
		'example',
	];

	/**
	 * "blockHooks" position values map camelCase (block.json) to snake_case
	 * (WP_Block_Type::$block_hooks), same as the metadata registration path.
	 *
	 * @var array<string, string>
	 */
	private const BLOCK_HOOKS_POSITIONS = [
		'before'     => 'before',
		'after'      => 'after',
		'firstChild' => 'first_child',
		'lastChild'  => 'last_child',
	];

	/**
	 * Block name, e.g. "acme/callout".
	 *
	 * @var string
	 */
	private string $name;

	/**
	 * Raw configuration as supplied by the consumer.
	 *
	 * @var array<string, mixed>
	 */
	private array $raw_config;

	/**
	 * Flattened, validated field collection.
	 *
	 * @var Field_Collection
	 */
	private Field_Collection $fields;

	/**
	 * Resolved WP attribute schema for this block.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $attributes;

	/**
	 * Normalized "args" passthrough (apiVersion defaulted, supports.html defaulted).
	 *
	 * @var array<string, mixed>
	 */
	private array $args;

	/**
	 * Normalized asset keys: register_block_type() key => list of handles / module IDs.
	 *
	 * @var array<string, string[]>
	 */
	private array $assets = [];

	/**
	 * Cached WP_Block_Type used only to run WordPress's own attribute
	 * preparation (validation and defaults) in sanitize_attributes().
	 *
	 * @var \WP_Block_Type|null
	 */
	private ?\WP_Block_Type $attribute_type = null;

	/**
	 * Build a definition from one block's raw configuration.
	 *
	 * @param array<string, mixed> $config Raw block config: id, args, fields, attributes, render, ...
	 * @throws \InvalidArgumentException If "id" is missing.
	 */
	public function __construct( array $config ) {
		if ( empty( $config['id'] ) ) {
			throw new \InvalidArgumentException( 'Block config must include "id".' );
		}

		$this->name       = (string) $config['id'];
		$this->raw_config = $config;
		Transform_Config::validate( $config['transforms'] ?? null, $this->name );
		$this->fields     = new Field_Collection( $config['fields'] ?? [] );
		$this->attributes = Attribute_Schema_Mapper::build( $this->fields, $config['attributes'] ?? [] );

		$is_dynamic   = 'static' !== ( $config['render']['mode'] ?? 'dynamic' );
		$args         = self::filter_args( is_array( $config['args'] ?? null ) ? $config['args'] : [], $this->name );
		$this->args   = Supports_Normalizer::normalize( $args['args'], $is_dynamic );
		$this->assets = $args['assets'];
		$this->args   = self::add_meta_context( $this->args, $this->fields );
		$this->args   = self::add_field_context( $this->args, $this->fields );

		if ( isset( $this->args['variations'] ) && is_array( $this->args['variations'] ) ) {
			$this->args['variations'] = Variation_Registrar::normalize( $this->args['variations'], $this->name );
		}
	}

	/**
	 * Split a block's raw "args" into the allow-listed passthrough keys and
	 * the normalized asset keys. Unknown keys (including snake_case
	 * spellings such as "view_script_module_ids") get a _doing_it_wrong()
	 * naming the key and block, and are dropped; so does an asset key whose
	 * value is not a string or a list of non-empty strings.
	 *
	 * @param array<string, mixed> $raw_args   Raw "args" from the block config.
	 * @param string               $block_name Block name, for the notices.
	 * @return array{args: array<string, mixed>, assets: array<string, string[]>}
	 */
	private static function filter_args( array $raw_args, string $block_name ): array {
		$args   = [];
		$assets = [];

		foreach ( $raw_args as $key => $value ) {
			if ( is_string( $key ) && isset( self::ASSET_KEYS[ $key ] ) ) {
				$list = self::normalize_asset_value( $value );
				if ( null === $list ) {
					self::doing_it_wrong(
						sprintf( 'Block "%1$s": args.%2$s must be a handle (string) or a list of handles; the value is ignored.', $block_name, $key )
					);
					continue;
				}
				$assets[ self::ASSET_KEYS[ $key ] ] = $list;
				continue;
			}

			if ( is_string( $key ) && in_array( $key, self::ALLOWED_ARGS, true ) ) {
				$args[ $key ] = $value;
				continue;
			}

			self::doing_it_wrong(
				sprintf(
					'Block "%1$s": unknown args key "%2$s" is ignored. Allowed keys: %3$s.',
					$block_name,
					(string) $key,
					implode( ', ', self::allowed_arg_keys() )
				)
			);
		}

		return [
			'args'   => $args,
			'assets' => $assets,
		];
	}

	/**
	 * Normalize an asset value to a list of non-empty strings.
	 *
	 * @param mixed $value String or list of strings.
	 * @return string[]|null Null when the shape is invalid.
	 */
	private static function normalize_asset_value( $value ): ?array {
		if ( is_string( $value ) ) {
			$value = [ $value ];
		}

		if ( ! is_array( $value ) || empty( $value ) ) {
			return null;
		}

		$list = [];
		foreach ( $value as $item ) {
			if ( ! is_string( $item ) || '' === trim( $item ) ) {
				return null;
			}
			$list[] = $item;
		}

		return array_values( array_unique( $list ) );
	}

	/**
	 * Raise a _doing_it_wrong() notice when WordPress is loaded.
	 *
	 * @param string $message Notice text.
	 * @return void
	 */
	private static function doing_it_wrong( string $message ): void {
		if ( function_exists( '_doing_it_wrong' ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- _doing_it_wrong() messages are developer-facing debug notices, not page output.
			_doing_it_wrong( __CLASS__ . '::__construct', $message, '0.2.0' );
		}
	}

	/**
	 * Every key a block's "args" accepts: the passthrough keys plus the
	 * asset keys.
	 *
	 * @api
	 * @return string[]
	 */
	public static function allowed_arg_keys(): array {
		return array_merge( self::ALLOWED_ARGS, array_keys( self::ASSET_KEYS ) );
	}

	/**
	 * Auto-add "postId"/"postType" to "usesContext" whenever this block has
	 * at least one field sourced from post meta (the default "meta.object_type",
	 * when a "meta"-sourced field doesn't declare one explicitly) — both are
	 * needed client-side: value/useFieldValue.js's "meta" source reads
	 * ctx.context.postType to call useEntityProp('postType', postType, 'meta'),
	 * and Binding\Settings_Binding_Source's own "postId" context fallback
	 * needs the CONSUMING block (this one, when it also uses the
	 * "{prefix}/field" bindings source) to declare it too. A consumer's
	 * own explicitly-declared "usesContext" entries are preserved, never
	 * overwritten — this only ever adds, never removes.
	 *
	 * @param array<string, mixed> $args   Normalized "args" passthrough (still block.json's camelCase).
	 * @param Field_Collection     $fields This block's flattened field collection.
	 * @return array<string, mixed>
	 */
	private static function add_meta_context( array $args, Field_Collection $fields ): array {
		$needs_post_context = false;

		foreach ( $fields->get_leaves() as $leaf ) {
			$config      = $leaf['config'];
			$meta_config = is_array( $config['meta'] ?? null ) ? $config['meta'] : [];
			$object_type = is_string( $meta_config['object_type'] ?? null ) ? $meta_config['object_type'] : 'post';

			if ( 'meta' === ( $config['source'] ?? 'attribute' ) && 'post' === $object_type ) {
				$needs_post_context = true;
				break;
			}
		}

		if ( ! $needs_post_context ) {
			return $args;
		}

		$existing            = is_array( $args['usesContext'] ?? null ) ? $args['usesContext'] : [];
		$args['usesContext'] = array_values( array_unique( array_merge( $existing, [ 'postId', 'postType' ] ) ) );

		return $args;
	}

	/**
	 * Add every block-context key a "source": "context" field names to
	 * "usesContext", merged with whatever the block declares explicitly and
	 * de-duplicated. Without it WordPress would not hand the provider's value
	 * to this block, server or client side.
	 *
	 * @param array<string, mixed> $args   Normalized "args" passthrough (still block.json's camelCase).
	 * @param Field_Collection     $fields This block's flattened field collection.
	 * @return array<string, mixed>
	 */
	private static function add_field_context( array $args, Field_Collection $fields ): array {
		$keys = array_values( $fields->get_context_fields() );

		if ( empty( $keys ) ) {
			return $args;
		}

		$existing            = is_array( $args['usesContext'] ?? null ) ? $args['usesContext'] : [];
		$args['usesContext'] = array_values( array_unique( array_merge( $existing, $keys ) ) );

		return $args;
	}

	/**
	 * Block name, e.g. "acme/callout".
	 *
	 * @return string
	 */
	public function get_name(): string {
		return $this->name;
	}

	/**
	 * The block's flattened, validated field collection.
	 *
	 * @return Field_Collection
	 */
	public function get_fields(): Field_Collection {
		return $this->fields;
	}

	/**
	 * The block's resolved WP attribute schema.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_attributes(): array {
		return $this->attributes;
	}

	/**
	 * The attribute schema passed to register_block_type() as "attributes":
	 * attribute name => WP attribute schema ({type, default, enum, ...}).
	 *
	 * @api
	 * @since 0.2.0
	 * @return array<string, array<string, mixed>>
	 */
	public function attribute_schema(): array {
		return $this->attributes;
	}

	/**
	 * Run raw attributes through the pipeline the render callback uses:
	 * the dynamic block's `render.deprecated` migration (attributes stored
	 * in an old shape are brought up to date first), WordPress's own attribute preparation (invalid values dropped, schema
	 * defaults filled in for missing ones), then the field sanitizers.
	 *
	 * The render callback calls exactly this method, so the result equals
	 * the attributes a render callback / template / markup receives for the
	 * same input. Values of "source": "context" fields are not included
	 * (they have no attribute; they come from the rendered block's context
	 * at render time).
	 *
	 * @api
	 * @since 0.2.0
	 * @param array<string, mixed> $attributes Raw block attributes.
	 * @return array<string, mixed>
	 */
	public function sanitize_attributes( array $attributes ): array {
		$attributes = $this->migrate_deprecated_attributes( $attributes );

		if ( null === $this->attribute_type ) {
			$this->attribute_type = new \WP_Block_Type( $this->name, [ 'attributes' => $this->attributes ] );
		}

		$prepared = $this->attribute_type->prepare_attributes_for_render( $attributes );

		return Attribute_Sanitizer::sanitize_all( $prepared, $this->fields );
	}

	/**
	 * First step of the render path: bring attributes stored in an old shape
	 * up to date using the dynamic block's `render.deprecated` entries
	 * (Render\Deprecation_Migrator). A no-op for static blocks (their stored
	 * markup is migrated by the editor) and when nothing is declared.
	 *
	 * @param array<string, mixed> $attributes Raw block attributes.
	 * @return array<string, mixed>
	 */
	private function migrate_deprecated_attributes( array $attributes ): array {
		$render = $this->raw_config['render'] ?? [];

		if ( ! is_array( $render ) || 'static' === ( $render['mode'] ?? 'dynamic' ) || empty( $render['deprecated'] ) || ! is_array( $render['deprecated'] ) ) {
			return $attributes;
		}

		return Deprecation_Migrator::migrate( $render['deprecated'], array_map( 'strval', array_keys( $this->attributes ) ), $attributes );
	}

	/**
	 * The raw configuration this definition was built from.
	 *
	 * @return array<string, mixed>
	 */
	public function get_raw_config(): array {
		return $this->raw_config;
	}

	/**
	 * The normalized "args" passthrough — apiVersion defaulted, supports.html
	 * defaulted for dynamic blocks — but still in block.json's own camelCase,
	 * unlike to_block_type_args(). This is what the client-side
	 * registerBlockType() call needs: WP's JS block-editor bundle expects the
	 * same camelCase keys block.json uses (apiVersion, usesContext, ...),
	 * the mirror image of why to_block_type_args() translates to snake_case
	 * for the PHP-side register_block_type() call. Shipping the snake_case
	 * form to the client would silently misconfigure the block there too —
	 * WP_Block_Type::$api_version defaults to 1 server-side for the same
	 * reason (see the CAMEL_TO_SNAKE docblock above).
	 *
	 * @return array<string, mixed>
	 */
	public function get_args(): array {
		return $this->args;
	}

	/**
	 * Build the args array for register_block_type( $this->get_name(), ... ).
	 *
	 * @return array<string, mixed>
	 */
	public function to_block_type_args(): array {
		$args = [];

		foreach ( $this->args as $key => $value ) {
			if ( 'blockHooks' === $key ) {
				$args['block_hooks'] = $this->translate_block_hooks( $value );
				continue;
			}

			$mapped_key          = self::CAMEL_TO_SNAKE[ $key ] ?? $key;
			$args[ $mapped_key ] = $value;
		}

		foreach ( $this->assets as $register_key => $list ) {
			$args[ $register_key ] = $list;
		}

		$args['attributes'] = $this->attributes;

		$render_callback = $this->get_render_callback();
		if ( null !== $render_callback ) {
			$args['render_callback'] = $render_callback;
		}

		return $args;
	}

	/**
	 * Build this block's render_callback closure, or null when there is
	 * nothing to render.
	 *
	 * Only "dynamic"-mode blocks (the default) ever get a render_callback:
	 * "static" mode's output comes from the block's own save() instead (a
	 * later milestone), and "none" mode is an intentionally output-less
	 * block (e.g. toolbar-only chrome with nothing on the front end). A
	 * dynamic block with no "render.callback"/"render.template"/"render.markup"
	 * declared also gets no render_callback — Block_Renderer would produce
	 * an empty string anyway, and omitting it here keeps a block with no
	 * render config behaving exactly as it did before this milestone
	 * (nothing on the front end) rather than silently changing behaviour
	 * for every already-registered block the moment this code ships.
	 *
	 * @return callable|null
	 */
	private function get_render_callback(): ?callable {
		$render_config = $this->raw_config['render'] ?? [];
		$mode          = $render_config['mode'] ?? 'dynamic';

		if ( 'dynamic' !== $mode ) {
			return null;
		}

		$has_renderable = ! empty( $render_config['callback'] )
			|| ! empty( $render_config['template'] )
			|| ! empty( $render_config['markup'] );

		if ( ! $has_renderable ) {
			return null;
		}

		$definition = $this;

		return static function ( array $attributes, string $content, \WP_Block $block ) use ( $definition ): string {
			return Block_Renderer::render( $attributes, $content, $block, $definition );
		};
	}

	/**
	 * Translate blockHooks position values from block.json's camelCase to
	 * WP_Block_Type's snake_case, and guard against a block hooking itself.
	 *
	 * @param array<string, string> $block_hooks Anchor block name => camelCase position.
	 * @return array<string, string>
	 */
	private function translate_block_hooks( array $block_hooks ): array {
		$translated = [];

		foreach ( $block_hooks as $anchor_block_name => $position ) {
			if ( $this->name === $anchor_block_name ) {
				if ( function_exists( '_doing_it_wrong' ) ) {
					_doing_it_wrong( __METHOD__, 'Cannot hook block to itself.', '0.2.0' );
				}
				continue;
			}

			if ( ! isset( self::BLOCK_HOOKS_POSITIONS[ $position ] ) ) {
				continue;
			}

			$translated[ $anchor_block_name ] = self::BLOCK_HOOKS_POSITIONS[ $position ];
		}

		return $translated;
	}
}
