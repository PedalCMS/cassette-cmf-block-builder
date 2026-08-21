<?php
/**
 * Binding_Source_Registrar class for Cassette-CMF Blocks
 *
 * Registers the "cassette-cmf/field" Block Bindings source (Mechanism B of
 * meta binding) — a block can bind any attribute to a value from the
 * parent library's own public CassetteCmf::get_field() facade: post meta,
 * term meta, or a settings-page option. This is the strongest argument for
 * the two libraries existing together: a site-wide setting authored on a
 * CMF settings page can render inside a *core* block (e.g. core/paragraph)
 * without either library needing to know about the other beyond this one
 * public facade call.
 *
 * ```php
 * 'binding' => [
 *     'source' => 'cassette-cmf/field',
 *     'args'   => [ 'field' => 'agency_phone', 'context' => 'agency-settings', 'context_type' => 'settings' ],
 * ],
 * ```
 *
 * Use Meta_Registrar (Mechanism A) for editable, revisioned values;
 * this for read-only/derived and cross-entity references.
 *
 * @package Pedalcms\CassetteCmfBlocks
 * @since 0.1.0
 */

namespace Pedalcms\CassetteCmfBlocks\Binding;

use Pedalcms\CassetteCmfBlocks\Compat\Requirements;

/**
 * Class Binding_Source_Registrar
 */
class Binding_Source_Registrar {

	/**
	 * The block bindings source name.
	 *
	 * @var string
	 */
	public const SOURCE_NAME = 'cassette-cmf/field';

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
	 * Register the "cassette-cmf/field" block bindings source, guarded by
	 * the same requirements check every other registration surface in this
	 * library uses — the source's own get_value_callback already no-ops
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

		$requirements = Requirements::check();
		if ( ! $requirements['ok'] ) {
			return;
		}

		register_block_bindings_source(
			self::SOURCE_NAME,
			[
				'label'              => __( 'Cassette-CMF field', 'cassette-cmf-block-builder' ),
				'get_value_callback' => [ Settings_Binding_Source::class, 'get_value' ],
				'uses_context'       => [ 'postId', 'postType' ],
			]
		);
	}
}
