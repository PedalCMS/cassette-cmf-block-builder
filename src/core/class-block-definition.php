<?php
/**
 * Block_Definition class for Cassette-CMF Blocks
 *
 * A value object built from one block's raw configuration array. Resolves
 * fields into a Field_Collection, maps them into a WP attribute schema, and
 * assembles the args register_block_type() expects.
 *
 * @package PedalCMS\CassetteCMFBlocks
 * @since 0.1.0
 */

namespace PedalCMS\CassetteCMFBlocks\Core;

use PedalCMS\CassetteCMFBlocks\Render\Block_Renderer;
use PedalCMS\CassetteCMFBlocks\Schema\Attribute_Schema_Mapper;
use PedalCMS\CassetteCMFBlocks\Schema\Supports_Normalizer;

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
		$this->fields     = new Field_Collection( $config['fields'] ?? [] );
		$this->attributes = Attribute_Schema_Mapper::build( $this->fields, $config['attributes'] ?? [] );

		$is_dynamic = 'static' !== ( $config['render']['mode'] ?? 'dynamic' );
		$this->args = Supports_Normalizer::normalize( $config['args'] ?? [], $is_dynamic );
		$this->args = self::add_meta_context( $this->args, $this->fields );

		if ( isset( $this->args['variations'] ) && is_array( $this->args['variations'] ) ) {
			$this->args['variations'] = Variation_Registrar::normalize( $this->args['variations'], $this->name );
		}
	}

	/**
	 * Auto-add "postId"/"postType" to "usesContext" whenever this block has
	 * at least one field sourced from post meta (the default "meta.object_type",
	 * when a "meta"-sourced field doesn't declare one explicitly) — both are
	 * needed client-side: value/useFieldValue.js's "meta" source reads
	 * ctx.context.postType to call useEntityProp('postType', postType, 'meta'),
	 * and Binding\Settings_Binding_Source's own "postId" context fallback
	 * needs the CONSUMING block (this one, when it also uses the
	 * "cassette-cmf/field" bindings source) to declare it too. A consumer's
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
					_doing_it_wrong( __METHOD__, 'Cannot hook block to itself.', '0.1.0' );
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
