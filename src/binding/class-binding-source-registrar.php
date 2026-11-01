<?php
/**
 * Binding_Source_Registrar class for Cassette-CMF Blocks
 *
 * Registers the "{prefix}/field" Block Bindings source (Mechanism B of
 * meta binding) — a block can bind any attribute to a value from the
 * parent library's own public CassetteCMF::get_field() facade: post meta,
 * term meta, or a settings-page option. The source is opt-in: it exists only
 * when the consumer booted the library with `bindings_source => true`, and
 * it resolves only fields marked `bindings: true` in their cassette-cmf
 * config (see Settings_Binding_Source). This is the strongest argument for
 * the two libraries existing together: a site-wide setting authored on a
 * CMF settings page can render inside a *core* block (e.g. core/paragraph)
 * without either library needing to know about the other beyond this one
 * public facade call.
 *
 * ```php
 * // Block content (post_content), on any block:
 * 'metadata' => [ 'bindings' => [ 'content' => [
 *     'source' => 'acme/field', // "{prefix}/field" with prefix "acme"
 *     'args'   => [ 'field' => 'agency_phone', 'context' => 'agency-settings', 'context_type' => 'settings' ],
 * ] ] ],
 * ```
 *
 * Use Meta_Registrar (Mechanism A) for editable, revisioned values;
 * this for read-only/derived and cross-entity references.
 *
 * @package PedalCMS\CassetteCMFBlocks
 * @since 0.2.0
 */

namespace PedalCMS\CassetteCMFBlocks\Binding;

use PedalCMS\CassetteCMFBlocks\Compat\Requirements;
use PedalCMS\CassetteCMFBlocks\Core\Block_Manager;
use PedalCMS\CassetteCMFBlocks\Core\Identity;

/**
 * Class Binding_Source_Registrar
 */
class Binding_Source_Registrar {

	/**
	 * Register the init hook.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'init', [ self::class, 'register_source' ] );
		}
	}

	/**
	 * Register the "{prefix}/field" block bindings source (the name comes from
	 * Identity::binding_source_name()), guarded by
	 * the same requirements check every other registration surface in this
	 * library uses, and only when the consumer opted in with the
	 * `bindings_source` boot option (default false). The source's own get_value_callback already no-ops
	 * safely when the parent library isn't loaded, but registering a
	 * source that can never resolve anything is still just noise.
	 *
	 * "uses_context" declares "postId" (Settings_Binding_Source's context
	 * fallback for a 'post'-type binding with no explicit "context") and
	 * "postType" (available for a consumer's own get_value_callback
	 * override to use, even though this source doesn't read it itself).
	 *
	 * @return void
	 */
	public static function register_source(): void {
		if ( ! function_exists( 'register_block_bindings_source' ) ) {
			return;
		}

		if ( true !== Block_Manager::get_option( 'bindings_source' ) ) {
			return;
		}

		$requirements = Requirements::check();
		if ( ! $requirements['ok'] ) {
			return;
		}

		register_block_bindings_source(
			Identity::binding_source_name(),
			[
				'label'              => __( 'Cassette-CMF field', 'cassette-cmf-block-builder' ),
				'get_value_callback' => [ Settings_Binding_Source::class, 'get_value' ],
				'uses_context'       => [ 'postId', 'postType' ],
			]
		);
	}
}
