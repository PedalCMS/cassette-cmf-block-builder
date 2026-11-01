<?php
/**
 * Block bindings source: disclosure tests (integration, real block rendering).
 *
 * A core/paragraph with metadata.bindings.content pointing at the
 * "cassette-cmf/field" source must never reveal options or meta that were
 * not explicitly exposed (`bindings: true`) through cassette-cmf, and must
 * never reveal protected meta, secrets, or unreadable posts.
 *
 * @package PedalCMS\CassetteCMFBlocks\Tests\Unit
 */

use PedalCMS\CassetteCMFBlocks\Binding\Binding_Source_Registrar;
use PedalCMS\CassetteCMFBlocks\CassetteCMFBlocks;
use PedalCMS\CassetteCMFBlocks\Core\Block_Manager;
use PedalCMS\CassetteCMFBlocks\Core\Identity;

require_once __DIR__ . '/Bindings_UnitTestCase.php';

/**
 * Class Test_Bindings_Disclosure
 */
class Test_Bindings_Disclosure extends Bindings_UnitTestCase {

	/**
	 * Visitors to repeat the key refusal cases for.
	 *
	 * @return array<string, array<int, string>>
	 */
	public function visitor_provider(): array {
		return [
			'contributor' => [ 'contributor' ],
			'anonymous'   => [ '' ],
		];
	}

	/**
	 * Become the given visitor ('' = anonymous).
	 *
	 * @param string $role Role or ''.
	 */
	private function become( string $role ): void {
		if ( '' === $role ) {
			wp_set_current_user( 0 );
		} else {
			$this->login_as( $role );
		}
	}

	/**
	 * Arbitrary options ("mailserver" / "pass" -> mailserver_pass) are not readable.
	 *
	 * @dataProvider visitor_provider
	 * @param string $role Visitor role.
	 */
	public function test_arbitrary_option_is_not_readable( string $role ): void {
		update_option( 'mailserver_pass', 'LEAK-mail-password' );
		$this->become( $role );

		$html = $this->render_bound(
			[
				'context_type' => 'settings',
				'context'      => 'mailserver',
				'field'        => 'pass',
			]
		);

		$this->assertRefused( $html, 'LEAK-mail-password' );
	}

	/**
	 * Core-option style names ("admin" / "email" -> admin_email, "blog" / "name") are not readable.
	 *
	 * @dataProvider visitor_provider
	 * @param string $role Visitor role.
	 */
	public function test_core_style_options_are_not_readable( string $role ): void {
		update_option( 'blog_name', 'LEAK-blog-name' );
		update_option( 'admin_email', 'leak-admin@example.test' );
		$this->become( $role );

		$html = $this->render_bound(
			[
				'context_type' => 'settings',
				'context'      => 'blog',
				'field'        => 'name',
			]
		);
		$this->assertRefused( $html, 'LEAK-blog-name' );

		$html = $this->render_bound(
			[
				'context_type' => 'settings',
				'context'      => 'admin',
				'field'        => 'email',
			]
		);
		$this->assertRefused( $html, 'leak-admin@example.test' );
	}

	/**
	 * Protected meta is refused, even when someone registered the field and marked it.
	 *
	 * @dataProvider visitor_provider
	 * @param string $role Visitor role.
	 */
	public function test_protected_meta_is_refused_even_when_marked( string $role ): void {
		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		update_post_meta( $post_id, '_edit_lock', 'LEAK-edit-lock' );
		update_post_meta( $post_id, '_wp_attached_file', 'LEAK-attached-file' );

		// Unregistered: refused by rule 3.
		$this->become( $role );
		foreach ( [
			'_edit_lock'        => 'LEAK-edit-lock',
			'_wp_attached_file' => 'LEAK-attached-file',
		] as $key => $leak ) {
			$this->assertRefused( $this->render_bound( [ 'field' => $key ], $post_id ), $leak );
			$this->assertRefused(
				$this->render_bound(
					[
						'field'   => $key,
						'context' => $post_id,
					]
				),
				$leak
			);
		}

		// Registered and marked: refused by rule 4.
		$this->register_post_fields(
			[
				[
					'name'     => '_edit_lock',
					'type'     => 'text',
					'bindings' => true,
				],
				[
					'name'     => '_wp_attached_file',
					'type'     => 'text',
					'bindings' => true,
				],
			]
		);

		foreach ( [
			'_edit_lock'        => 'LEAK-edit-lock',
			'_wp_attached_file' => 'LEAK-attached-file',
		] as $key => $leak ) {
			$this->assertRefused( $this->render_bound( [ 'field' => $key ], $post_id ), $leak );
		}
	}

	/**
	 * A private post owned by someone else: refused for a contributor, resolved for the owner and an admin.
	 */
	public function test_private_post_meta_is_only_readable_by_those_who_can_read_it(): void {
		$owner_id = self::factory()->user->create( [ 'role' => 'author' ] );
		$post_id  = self::factory()->post->create(
			[
				'post_status' => 'private',
				'post_author' => $owner_id,
			]
		);
		update_post_meta( $post_id, 'internal_note', 'private-note-value' );

		$this->register_post_fields(
			[
				[
					'name'     => 'internal_note',
					'type'     => 'text',
					'bindings' => true,
				],
			]
		);

		// Contributor: refused via an explicit context ID and via the block's own context post.
		$this->login_as( 'contributor' );
		$this->assertRefused(
			$this->render_bound(
				[
					'field'   => 'internal_note',
					'context' => $post_id,
				]
			),
			'private-note-value'
		);
		$this->assertRefused( $this->render_bound( [ 'field' => 'internal_note' ], $post_id ), 'private-note-value' );

		// Anonymous: refused.
		wp_set_current_user( 0 );
		$this->assertRefused(
			$this->render_bound(
				[
					'field'   => 'internal_note',
					'context' => $post_id,
				]
			),
			'private-note-value'
		);

		// Owner and admin: resolved, via both routes.
		foreach ( [ $owner_id, self::factory()->user->create( [ 'role' => 'administrator' ] ) ] as $user_id ) {
			wp_set_current_user( $user_id );
			$this->assertStringContainsString(
				'private-note-value',
				$this->render_bound(
					[
						'field'   => 'internal_note',
						'context' => $post_id,
					]
				)
			);
			$this->assertStringContainsString( 'private-note-value', $this->render_bound( [ 'field' => 'internal_note' ], $post_id ) );
		}
	}

	/**
	 * A password field is never exposed, even when marked, on settings pages and posts.
	 *
	 * @dataProvider visitor_provider
	 * @param string $role Visitor role.
	 */
	public function test_password_field_is_never_resolved( string $role ): void {
		$this->register_settings_page(
			'acme-secrets',
			[
				[
					'name'     => 'api_pass',
					'type'     => 'password',
					'bindings' => true,
				],
			]
		);
		$this->register_post_fields(
			[
				[
					'name'     => 'post_pass',
					'type'     => 'password',
					'bindings' => true,
				],
			]
		);
		update_option( 'acme-secrets_api_pass', 'LEAK-option-password' );
		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		update_post_meta( $post_id, 'post_pass', 'LEAK-meta-password' );
		$this->become( $role );

		$html = $this->render_bound(
			[
				'context_type' => 'settings',
				'context'      => 'acme-secrets',
				'field'        => 'api_pass',
			]
		);
		$this->assertRefused( $html, 'LEAK-option-password' );
		$this->assertRefused( $this->render_bound( [ 'field' => 'post_pass' ], $post_id ), 'LEAK-meta-password' );
	}

	/**
	 * A marked settings-page field resolves for an anonymous visitor; unmarked and unregistered do not.
	 */
	public function test_marked_settings_field_resolves_for_anonymous_and_unmarked_does_not(): void {
		$this->register_settings_page(
			'acme-agency',
			[
				[
					'name'     => 'phone',
					'type'     => 'text',
					'bindings' => true,
				],
				[
					'name' => 'internal_code',
					'type' => 'text',
				],
			]
		);
		update_option( 'acme-agency_phone', '555-0100' );
		update_option( 'acme-agency_internal_code', 'LEAK-internal-code' );
		update_option( 'acme-agency_unregistered', 'LEAK-unregistered' );
		wp_set_current_user( 0 );

		$base = [
			'context_type' => 'settings',
			'context'      => 'acme-agency',
		];

		$this->assertStringContainsString( '555-0100', $this->render_bound( $base + [ 'field' => 'phone' ] ) );
		$this->assertRefused( $this->render_bound( $base + [ 'field' => 'internal_code' ] ), 'LEAK-internal-code' );
		$this->assertRefused( $this->render_bound( $base + [ 'field' => 'unregistered' ] ), 'LEAK-unregistered' );
	}

	/**
	 * A marked field on a public post resolves for an anonymous visitor; an unmarked one does not.
	 */
	public function test_marked_post_meta_resolves_for_anonymous_and_unmarked_does_not(): void {
		$this->register_post_fields(
			[
				[
					'name'     => 'subtitle',
					'type'     => 'text',
					'bindings' => true,
				],
				[
					'name' => 'hidden_note',
					'type' => 'text',
				],
			]
		);
		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		update_post_meta( $post_id, 'subtitle', 'A public subtitle' );
		update_post_meta( $post_id, 'hidden_note', 'LEAK-hidden-note' );
		wp_set_current_user( 0 );

		$this->assertStringContainsString( 'A public subtitle', $this->render_bound( [ 'field' => 'subtitle' ], $post_id ) );
		$this->assertStringContainsString(
			'A public subtitle',
			$this->render_bound(
				[
					'field'   => 'subtitle',
					'context' => $post_id,
				]
			)
		);
		$this->assertRefused( $this->render_bound( [ 'field' => 'hidden_note' ], $post_id ), 'LEAK-hidden-note' );
	}

	/**
	 * A password-protected post is not disclosed through a binding.
	 */
	public function test_password_protected_post_is_refused(): void {
		$this->register_post_fields(
			[
				[
					'name'     => 'subtitle',
					'type'     => 'text',
					'bindings' => true,
				],
			]
		);
		$post_id = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_password' => 'hunter2',
			]
		);
		update_post_meta( $post_id, 'subtitle', 'LEAK-protected-subtitle' );
		wp_set_current_user( 0 );

		$this->assertRefused( $this->render_bound( [ 'field' => 'subtitle' ], $post_id ), 'LEAK-protected-subtitle' );
	}

	/**
	 * The narrowing filter can refuse, but cannot widen, and is not consulted when rules fail.
	 */
	public function test_filter_only_narrows(): void {
		$this->register_settings_page(
			'acme-agency',
			[
				[
					'name'     => 'phone',
					'type'     => 'text',
					'bindings' => true,
				],
				[
					'name' => 'internal_code',
					'type' => 'text',
				],
			]
		);
		update_option( 'acme-agency_phone', '555-0100' );
		update_option( 'acme-agency_internal_code', 'LEAK-internal-code' );

		$base = [
			'context_type' => 'settings',
			'context'      => 'acme-agency',
		];

		// Filter returning false refuses a legitimate binding.
		add_filter( 'cassette_cmf_blocks_binding_allowed', '__return_false' );
		$this->assertRefused( $this->render_bound( $base + [ 'field' => 'phone' ] ), '555-0100' );
		remove_filter( 'cassette_cmf_blocks_binding_allowed', '__return_false' );

		// Filter returning true does not widen: an unmarked field stays refused.
		add_filter( 'cassette_cmf_blocks_binding_allowed', '__return_true' );
		$this->assertRefused( $this->render_bound( $base + [ 'field' => 'internal_code' ] ), 'LEAK-internal-code' );
		remove_filter( 'cassette_cmf_blocks_binding_allowed', '__return_true' );

		// Not consulted when a rule already failed; consulted once with the right arguments when they pass.
		$calls    = [];
		$observer = static function ( $allowed, $args, $block ) use ( &$calls ) {
			$calls[] = [ $allowed, $args, $block ];
			return $allowed;
		};
		add_filter( 'cassette_cmf_blocks_binding_allowed', $observer, 10, 3 );

		$this->render_bound( $base + [ 'field' => 'internal_code' ] );
		$this->assertSame( [], $calls );

		$this->assertStringContainsString( '555-0100', $this->render_bound( $base + [ 'field' => 'phone' ] ) );
		$this->assertCount( 1, $calls );
		$this->assertTrue( $calls[0][0] );
		$this->assertSame( 'phone', $calls[0][1]['field'] );
		$this->assertInstanceOf( WP_Block::class, $calls[0][2] );
		remove_filter( 'cassette_cmf_blocks_binding_allowed', $observer, 10 );
	}

	/**
	 * Without the bindings_source option the source does not exist and nothing resolves.
	 */
	public function test_source_is_not_registered_without_the_option(): void {
		unregister_block_bindings_source( Identity::binding_source_name() );
		Block_Manager::reset();
		CassetteCMFBlocks::boot( [ 'prefix' => 'test' ] );

		$this->register_settings_page(
			'acme-agency',
			[
				[
					'name'     => 'phone',
					'type'     => 'text',
					'bindings' => true,
				],
			]
		);
		update_option( 'acme-agency_phone', '555-0100' );

		Binding_Source_Registrar::register_source();

		$this->assertNull( get_block_bindings_source( Identity::binding_source_name() ) );
		$this->assertArrayNotHasKey(
			Identity::binding_source_name(),
			WP_Block_Bindings_Registry::get_instance()->get_all_registered()
		);

		$html = $this->render_bound(
			[
				'context_type' => 'settings',
				'context'      => 'acme-agency',
				'field'        => 'phone',
			]
		);
		$this->assertRefused( $html, '555-0100' );
	}
}
