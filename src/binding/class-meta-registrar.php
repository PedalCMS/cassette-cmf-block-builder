<?php
/**
 * Meta_Registrar class for Cassette-CMF Blocks
 *
 * Mechanism A of meta binding (per the design plan): for every field
 * declaring `'source' => 'meta'`, calls WordPress core's register_meta()
 * with a real `show_in_rest.schema` (via Schema\Meta_Schema_Mapper),
 * `sanitize_callback` routed through the parent library's own sanitize()
 * (Compat\Cmf_Bridge — the same reuse principle every other sanitize path
 * in this library follows), and an `auth_callback` from the field's
 * declared capability (or a sensible per-object-type default).
 *
 * This directly fixes a real gap in the parent library: cassette-cmf reads
 * and writes post/term meta via plain get_post_meta()/update_post_meta()
 * calls (src/core/class-manager.php:622, src/core/Handlers/class-existing-post-type-handler.php:335,
 * and the container fields' own load_field_value() methods) and never
 * calls register_meta() at all — so none of its own meta fields are
 * visible to the REST API, and @wordpress/core-data's useEntityProp() (the
 * hook value/useFieldValue.js reads/writes meta through) can neither read
 * nor write them. A `show_in_rest` with a real schema is mandatory for
 * that, not cosmetic.
 *
 * A custom post type must also declare `'supports' => [ 'custom-fields' ]`
 * (or `add_post_type_support( $post_type, 'custom-fields' )`) for its
 * registered meta to be reachable via REST at all — this class doesn't do
 * that for you, since auto-adding "custom-fields" support to an arbitrary
 * post type is a side effect this library has no business taking on
 * unprompted. See docs/meta-binding.md.
 *
 * @package Pedalcms\CassetteCmfBlocks
 * @since 0.1.0
 */

namespace Pedalcms\CassetteCmfBlocks\Binding;

use Pedalcms\CassetteCmfBlocks\Compat\Cmf_Bridge;
use Pedalcms\CassetteCmfBlocks\Compat\Requirements;
use Pedalcms\CassetteCmfBlocks\Core\Block_Manager;
use Pedalcms\CassetteCmfBlocks\Schema\Meta_Schema_Mapper;

/**
 * Class Meta_Registrar
 */
class Meta_Registrar {

	/**
	 * Default capability per object type, used when a field declares no
	 * explicit `meta.auth`. Each is a real WordPress meta-capability that
	 * accepts the object's own ID as current_user_can()'s second argument.
	 *
	 * @var array<string, string>
	 */
	private const DEFAULT_CAPABILITIES = [
		'post'    => 'edit_post',
		'term'    => 'edit_term',
		'user'    => 'edit_user',
		'comment' => 'edit_comment',
	];

	/**
	 * Register the init hook.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'init', [ self::class, 'register_meta_fields' ], 10 );
		}
	}

	/**
	 * register_meta() every "meta"-sourced field across every registered
	 * block. Compiles each block itself (Block_Manager::compile_block() is
	 * cached/idempotent) rather than reading an already-compiled registry,
	 * so this doesn't depend on running after whichever other "init"
	 * callback happens to compile blocks first.
	 *
	 * @return void
	 */
	public static function register_meta_fields(): void {
		$requirements = Requirements::check();

		if ( ! $requirements['ok'] || ! function_exists( 'register_meta' ) ) {
			return;
		}

		$manager = Block_Manager::init();

		foreach ( array_keys( $manager->get_blocks() ) as $id ) {
			try {
				$definition = $manager->compile_block( $id );
			} catch ( \InvalidArgumentException $e ) {
				// Already _doing_it_wrong()'d by Block_Manager::register_blocks();
				// a block that failed to compile has no meta fields to register here.
				continue;
			}

			foreach ( $definition->get_fields()->get_leaves() as $leaf ) {
				// A repeater row field has no meaning as a stand-alone
				// register_meta() call — its value lives inside one row of
				// the repeater's own array attribute, not at a fixed post
				// meta key. See Schema\Attribute_Schema_Mapper::from_fields()
				// for the equivalent guard on the attribute-schema side.
				if ( ! empty( $leaf['inside_repeater'] ) ) {
					continue;
				}

				if ( 'meta' === ( $leaf['config']['source'] ?? 'attribute' ) ) {
					self::register_one( $leaf );
				}
			}
		}
	}

	/**
	 * register_meta() one "meta"-sourced field.
	 *
	 * @param array<string, mixed> $leaf Field_Collection leaf entry.
	 * @return void
	 */
	private static function register_one( array $leaf ): void {
		$config      = $leaf['config'];
		$meta_config = is_array( $config['meta'] ?? null ) ? $config['meta'] : [];

		$meta_key = is_string( $meta_config['key'] ?? null ) && '' !== $meta_config['key']
			? $meta_config['key']
			: ( $config['name'] ?? null );

		if ( ! is_string( $meta_key ) || '' === $meta_key ) {
			return;
		}

		$object_type = is_string( $meta_config['object_type'] ?? null ) ? $meta_config['object_type'] : 'post';
		$cmf_type    = $leaf['cmf_type'];
		$schema      = Meta_Schema_Mapper::to_json_schema( $leaf );

		register_meta(
			$object_type,
			$meta_key,
			[
				'object_subtype'    => is_string( $meta_config['object_subtype'] ?? null ) ? $meta_config['object_subtype'] : '',
				'type'              => $schema['type'],
				'single'            => ! array_key_exists( 'single', $meta_config ) || (bool) $meta_config['single'],
				'default'           => $schema['default'],
				'show_in_rest'      => [ 'schema' => $schema ],
				'sanitize_callback' => null !== $cmf_type
					? static function ( $value ) use ( $cmf_type, $config ) {
						return Cmf_Bridge::sanitize( $cmf_type, $config, $value );
					}
					: null,
				'auth_callback'     => self::build_auth_callback( $object_type, $meta_config['auth'] ?? null ),
				'revisions_enabled' => 'post' === $object_type && ! empty( $meta_config['revisions'] ),
			]
		);
	}

	/**
	 * Build the auth_callback for one field: the field's declared
	 * capability/callable, or a sensible per-object-type default.
	 *
	 * @param string        $object_type One of 'post', 'term', 'user', 'comment'.
	 * @param callable|string|null $auth Declared `meta.auth` — a capability string, a callable, or unset.
	 * @return callable
	 */
	private static function build_auth_callback( string $object_type, $auth ): callable {
		return static function ( $allowed, $meta_key, $object_id ) use ( $object_type, $auth ) {
			if ( is_callable( $auth ) ) {
				return (bool) call_user_func( $auth, $object_id, $meta_key );
			}

			$capability = is_string( $auth ) && '' !== $auth ? $auth : ( self::DEFAULT_CAPABILITIES[ $object_type ] ?? 'edit_posts' );

			return current_user_can( $capability, $object_id );
		};
	}
}
