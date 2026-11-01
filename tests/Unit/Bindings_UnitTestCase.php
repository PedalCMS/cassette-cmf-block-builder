<?php
/**
 * Shared fixtures for the block bindings source tests.
 *
 * @package PedalCMS\CassetteCMFBlocks\Tests\Unit
 */

use PedalCMS\CassetteCMF\Core\Manager;
use PedalCMS\CassetteCMF\Field\Field_Factory;
use PedalCMS\CassetteCMFBlocks\Binding\Binding_Source_Registrar;
use PedalCMS\CassetteCMFBlocks\CassetteCMFBlocks;
use PedalCMS\CassetteCMFBlocks\Core\Block_Manager;
use PedalCMS\CassetteCMFBlocks\Core\Identity;

require_once __DIR__ . '/CassetteCMFBlocks_UnitTestCase.php';

/**
 * Class Bindings_UnitTestCase
 *
 * Boots the library with the bindings source enabled, gives each test a
 * fresh cassette-cmf Manager, and offers helpers to register CMF fields and
 * to render a core/paragraph bound to the source.
 */
abstract class Bindings_UnitTestCase extends CassetteCMFBlocks_UnitTestCase {

	/**
	 * Original paragraph text; a refused binding must leave it untouched.
	 */
	protected const ORIGINAL = 'original text';

	/**
	 * Set up: fresh library boot with the source enabled, fresh CMF manager.
	 */
	public function set_up(): void {
		parent::set_up();

		Block_Manager::reset();
		CassetteCMFBlocks::boot(
			[
				'prefix'          => 'test',
				'bindings_source' => true,
			]
		);
		$this->reset_cmf_manager();
		Binding_Source_Registrar::register_source();
	}

	/**
	 * Tear down: unregister the source, drop the CMF manager, reset the boot state.
	 */
	public function tear_down(): void {
		if ( function_exists( 'unregister_block_bindings_source' ) && null !== get_block_bindings_source( Identity::binding_source_name() ) ) {
			unregister_block_bindings_source( Identity::binding_source_name() );
		}
		$this->reset_cmf_manager();
		Block_Manager::reset();
		wp_set_current_user( 0 );
		unset( $GLOBALS['post'] );
		parent::tear_down();
	}

	/**
	 * Replace the cassette-cmf Manager singleton with a fresh one.
	 *
	 * @return Manager
	 */
	protected function reset_cmf_manager(): Manager {
		$property = ( new ReflectionClass( Manager::class ) )->getProperty( 'instance' );
		$property->setAccessible( true );
		$property->setValue( null, null );
		Field_Factory::reset();

		return Manager::init();
	}

	/**
	 * Register fields on an existing post type ("post").
	 *
	 * @param array<int, array<string, mixed>> $fields Field configs.
	 */
	protected function register_post_fields( array $fields ): void {
		Manager::init()->get_existing_cpt_handler()->add_fields( 'post', $fields );
	}

	/**
	 * Register fields on an existing taxonomy.
	 *
	 * @param string                           $taxonomy Taxonomy.
	 * @param array<int, array<string, mixed>> $fields   Field configs.
	 */
	protected function register_taxonomy_fields( string $taxonomy, array $fields ): void {
		Manager::init()->get_existing_taxonomy_handler()->add_fields( $taxonomy, $fields );
	}

	/**
	 * Register a cassette-cmf settings page.
	 *
	 * @param string                           $page_id Page ID.
	 * @param array<int, array<string, mixed>> $fields  Field configs.
	 */
	protected function register_settings_page( string $page_id, array $fields ): void {
		Manager::init()->register_from_array(
			[
				'settings_pages' => [
					[
						'id'         => $page_id,
						'page_title' => $page_id,
						'menu_title' => $page_id,
						'capability' => 'manage_options',
						'menu_slug'  => $page_id,
						'fields'     => $fields,
					],
				],
			]
		);
	}

	/**
	 * Render a core/paragraph whose content is bound to the source.
	 *
	 * @param array<string, mixed> $args    Binding args.
	 * @param int|null             $post_id Global post to render in (block context postId), or null.
	 * @return string The rendered HTML.
	 */
	protected function render_bound( array $args, ?int $post_id = null ): string {
		if ( null !== $post_id ) {
			$GLOBALS['post'] = get_post( $post_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		$attrs = [
			'metadata' => [
				'bindings' => [
					'content' => [
						'source' => Identity::binding_source_name(),
						'args'   => $args,
					],
				],
			],
		];

		$markup = '<!-- wp:paragraph ' . wp_json_encode( $attrs ) . ' --><p>' . self::ORIGINAL . '</p><!-- /wp:paragraph -->';

		return do_blocks( $markup );
	}

	/**
	 * Assert the render kept the block's saved content and leaked nothing.
	 *
	 * @param string $html   Rendered HTML.
	 * @param string $secret A value that must not appear.
	 */
	protected function assertRefused( string $html, string $secret = '' ): void {
		$this->assertStringContainsString( self::ORIGINAL, $html );
		if ( '' !== $secret ) {
			$this->assertStringNotContainsString( $secret, $html );
		}
	}

	/**
	 * Create a user with a role and make it the current user.
	 *
	 * @param string $role Role.
	 * @return int User ID.
	 */
	protected function login_as( string $role ): int {
		$user_id = self::factory()->user->create( [ 'role' => $role ] );
		wp_set_current_user( $user_id );

		return $user_id;
	}
}
