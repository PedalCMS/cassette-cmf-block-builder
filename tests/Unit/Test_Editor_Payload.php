<?php
/**
 * Editor_Payload test.
 *
 * @package Pedalcms\CassetteCmfBlocks\Tests\Unit
 */

use Pedalcms\CassetteCmfBlocks\CassetteCmfBlocks;
use Pedalcms\CassetteCmfBlocks\Core\Block_Manager;
use Pedalcms\CassetteCmfBlocks\Core\Editor_Payload;
use Pedalcms\CassetteCmfBlocks\Render\Deprecation_Builder;
use Pedalcms\CassetteCmfBlocks\Schema\Control_Catalog;

require_once __DIR__ . '/CassetteCmfBlocks_UnitTestCase.php';

/**
 * Class Test_Editor_Payload
 */
class Test_Editor_Payload extends CassetteCmfBlocks_UnitTestCase {

	/**
	 * Reset library singletons before each test.
	 */
	public function set_up(): void {
		parent::set_up();
		Block_Manager::reset();
		Control_Catalog::reset();
	}

	/**
	 * Reset library singletons after each test.
	 */
	public function tear_down(): void {
		Block_Manager::reset();
		Control_Catalog::reset();
		parent::tear_down();
	}

	/**
	 * build() must ship "settings" in block.json's own camelCase (not
	 * to_block_type_args()'s snake_case) — that's what the client-side
	 * registerBlockType() call expects.
	 */
	public function test_settings_use_camel_case_for_the_client(): void {
		CassetteCmfBlocks::register_from_array(
			[
				'blocks' => [
					[
						'id'   => 'acme-test/payload-camel',
						'args' => [
							'title'       => 'Payload Camel',
							'usesContext' => [ 'postId' ],
						],
					],
				],
			]
		);

		$payload = Editor_Payload::build( Block_Manager::init(), 'abc123' );

		$settings = $payload['blocks']['acme-test/payload-camel']['settings'];
		$this->assertSame( 3, $settings['apiVersion'] );
		$this->assertSame( [ 'postId' ], $settings['usesContext'] );
		$this->assertArrayNotHasKey( 'api_version', $settings );
		$this->assertArrayNotHasKey( 'uses_context', $settings );
	}

	/**
	 * The resolved attribute schema must be merged into "settings" so the
	 * client-side registerBlockType() call sees the exact same attributes
	 * schema the server registered — the whole point of sharing one source.
	 */
	public function test_attributes_are_merged_into_settings(): void {
		CassetteCmfBlocks::register_from_array(
			[
				'blocks' => [
					[
						'id'     => 'acme-test/payload-attrs',
						'fields' => [
							[
								'name' => 'heading',
								'type' => 'text',
							],
						],
					],
				],
			]
		);

		$payload = Editor_Payload::build( Block_Manager::init() );

		$this->assertSame(
			'string',
			$payload['blocks']['acme-test/payload-attrs']['settings']['attributes']['heading']['type']
		);
	}

	/**
	 * The "fields" entry must be the nested editor tree (Field_Collection::to_editor_tree()),
	 * not the flattened leaf list — the editor needs the panel/tab nesting to render into.
	 */
	public function test_fields_is_the_nested_editor_tree(): void {
		CassetteCmfBlocks::register_from_array(
			[
				'blocks' => [
					[
						'id'     => 'acme-test/payload-tree',
						'fields' => [
							[
								'name'   => 'settings',
								'type'   => 'panel',
								'title'  => 'Settings',
								'fields' => [
									[
										'name' => 'heading',
										'type' => 'text',
									],
								],
							],
						],
					],
				],
			]
		);

		$payload = Editor_Payload::build( Block_Manager::init() );
		$fields  = $payload['blocks']['acme-test/payload-tree']['fields'];

		$this->assertSame( 'panel', $fields[0]['type'] );
		$this->assertCount( 1, $fields[0]['fields'] );
		$this->assertSame( 'heading', $fields[0]['fields'][0]['config']['name'] );
	}

	/**
	 * "render" ships only what the editor needs (mode, the declarative
	 * markup tree, a content hash, deprecated entries) — never "callback"
	 * (a PHP callable) or "template" (a server filesystem path), neither of
	 * which mean anything client-side.
	 */
	public function test_render_payload_ships_mode_and_markup_only(): void {
		CassetteCmfBlocks::register_from_array(
			[
				'blocks' => [
					[
						'id'     => 'acme-test/payload-render',
						'render' => [
							'callback' => 'strtoupper',
							'template' => '/some/path.php',
							'markup'   => [
								'tag'  => 'p',
								'text' => 'hi',
							],
						],
					],
				],
			]
		);

		$render = Editor_Payload::build( Block_Manager::init() )['blocks']['acme-test/payload-render']['render'];

		$this->assertSame( 'dynamic', $render['mode'] );
		$this->assertSame(
			[
				'tag'  => 'p',
				'text' => 'hi',
			],
			$render['markup']
		);
		$this->assertArrayNotHasKey( 'callback', $render );
		$this->assertArrayNotHasKey( 'template', $render );
	}

	/**
	 * A block with no "render" config at all still gets a well-shaped
	 * "render" entry (mode defaulted, markup null) rather than a missing key.
	 */
	public function test_render_payload_defaults_when_absent(): void {
		CassetteCmfBlocks::register_from_array( [ 'blocks' => [ [ 'id' => 'acme-test/payload-no-render' ] ] ] );

		$render = Editor_Payload::build( Block_Manager::init() )['blocks']['acme-test/payload-no-render']['render'];

		$this->assertSame( 'dynamic', $render['mode'] );
		$this->assertNull( $render['markup'] );
		$this->assertSame( [], $render['deprecated'] );
		$this->assertNotEmpty( $render['hash'] );
	}

	/**
	 * "hash" reflects Deprecation_Builder's content hash of the current
	 * markup + attribute schema, exposed for introspection/tooling.
	 */
	public function test_render_payload_ships_a_deprecation_builder_hash(): void {
		$markup = [
			'tag'  => 'p',
			'text' => 'hi',
		];

		CassetteCmfBlocks::register_from_array(
			[
				'blocks' => [
					[
						'id'     => 'acme-test/payload-hash',
						'render' => [ 'markup' => $markup ],
					],
				],
			]
		);

		$definition = Block_Manager::init()->compile_block( 'acme-test/payload-hash' );
		$render     = Editor_Payload::build( Block_Manager::init() )['blocks']['acme-test/payload-hash']['render'];

		$this->assertSame(
			Deprecation_Builder::hash( $markup, $definition->get_attributes() ),
			$render['hash']
		);
	}

	/**
	 * "deprecated" ships each render.deprecated[] entry's markup/attributes/migrate —
	 * an entry missing "markup" is dropped rather than shipped broken.
	 */
	public function test_render_payload_ships_deprecated_entries(): void {
		CassetteCmfBlocks::register_from_array(
			[
				'blocks' => [
					[
						'id'     => 'acme-test/payload-deprecated',
						'render' => [
							'markup'     => [ 'tag' => 'p' ],
							'deprecated' => [
								[
									'markup'     => [ 'tag' => 'div' ],
									'attributes' => [ 'heading' => [ 'type' => 'string' ] ],
									'migrate'    => 'acme_migrate_v1',
								],
								[ 'attributes' => [ 'heading' => [ 'type' => 'string' ] ] ],
							],
						],
					],
				],
			]
		);

		$render = Editor_Payload::build( Block_Manager::init() )['blocks']['acme-test/payload-deprecated']['render'];

		$this->assertCount( 1, $render['deprecated'] );
		$this->assertSame( [ 'tag' => 'div' ], $render['deprecated'][0]['markup'] );
		$this->assertSame( [ 'heading' => [ 'type' => 'string' ] ], $render['deprecated'][0]['attributes'] );
		$this->assertSame( 'acme_migrate_v1', $render['deprecated'][0]['migrate'] );
	}

	/**
	 * A deprecated entry with no "attributes"/"migrate" ships them as null —
	 * deprecations.js falls back to the block's current attribute schema
	 * and omits "migrate" entirely in that case.
	 */
	public function test_render_payload_deprecated_entry_defaults(): void {
		CassetteCmfBlocks::register_from_array(
			[
				'blocks' => [
					[
						'id'     => 'acme-test/payload-deprecated-minimal',
						'render' => [
							'deprecated' => [
								[ 'markup' => [ 'tag' => 'div' ] ],
							],
						],
					],
				],
			]
		);

		$render = Editor_Payload::build( Block_Manager::init() )['blocks']['acme-test/payload-deprecated-minimal']['render'];

		$this->assertNull( $render['deprecated'][0]['attributes'] );
		$this->assertNull( $render['deprecated'][0]['migrate'] );
	}

	/**
	 * "preview" defaults mode to "markup" and markup to null when unset —
	 * CanvasArea falls back to render.markup itself when preview.markup is null.
	 */
	public function test_preview_payload_defaults(): void {
		CassetteCmfBlocks::register_from_array( [ 'blocks' => [ [ 'id' => 'acme-test/payload-preview' ] ] ] );

		$preview = Editor_Payload::build( Block_Manager::init() )['blocks']['acme-test/payload-preview']['preview'];

		$this->assertSame( 'markup', $preview['mode'] );
		$this->assertNull( $preview['markup'] );
	}

	/**
	 * A declared preview.markup ships through as-is, distinct from render.markup.
	 */
	public function test_preview_payload_ships_its_own_markup(): void {
		CassetteCmfBlocks::register_from_array(
			[
				'blocks' => [
					[
						'id'      => 'acme-test/payload-preview-markup',
						'render'  => [
							'markup' => [
								'tag'  => 'div',
								'text' => 'full',
							],
						],
						'preview' => [
							'markup' => [
								'tag'  => 'div',
								'text' => 'preview-only',
							],
						],
					],
				],
			]
		);

		$blocks = Editor_Payload::build( Block_Manager::init() )['blocks']['acme-test/payload-preview-markup'];

		$this->assertSame(
			[
				'tag'  => 'div',
				'text' => 'full',
			],
			$blocks['render']['markup']
		);
		$this->assertSame(
			[
				'tag'  => 'div',
				'text' => 'preview-only',
			],
			$blocks['preview']['markup']
		);
	}

	/**
	 * A block whose config fails to compile must be skipped, not fatal —
	 * matching Block_Manager::register_blocks()'s own per-block isolation.
	 */
	public function test_uncompilable_block_is_skipped(): void {
		CassetteCmfBlocks::register_from_array(
			[
				'blocks' => [
					[
						'id'     => 'acme-test/payload-bad',
						'fields' => [
							[
								'name' => 'x',
								'type' => 'text',
							],
							[
								'name' => 'x',
								'type' => 'textarea',
							],
						],
					],
				],
			]
		);

		$payload = Editor_Payload::build( Block_Manager::init() );

		$this->assertArrayNotHasKey( 'acme-test/payload-bad', $payload['blocks'] );
	}

	/**
	 * The given asset version is passed through verbatim as "version".
	 */
	public function test_version_is_the_given_asset_version(): void {
		$payload = Editor_Payload::build( Block_Manager::init(), 'edd1234' );

		$this->assertSame( 'edd1234', $payload['version'] );
	}

	/**
	 * A markup node's "when" — at any depth, including inside "children" —
	 * must be normalized before shipping, the markup-tree counterpart of
	 * "fields"' "conditional" normalization.
	 */
	public function test_render_payload_normalizes_when_recursively(): void {
		CassetteCmfBlocks::register_from_array(
			[
				'blocks' => [
					[
						'id'     => 'acme-test/payload-when',
						'render' => [
							'markup' => [
								'tag'      => 'div',
								'children' => [
									[
										'tag'  => 'button',
										'when' => [
											'field'    => 'is_open',
											'operator' => 'equals',
											'value'    => true,
										],
									],
								],
							],
						],
					],
				],
			]
		);

		$markup = Editor_Payload::build( Block_Manager::init() )['blocks']['acme-test/payload-when']['render']['markup'];

		$this->assertSame(
			[
				'relation' => 'AND',
				'rules'    => [
					[
						'field'    => 'is_open',
						'operator' => '==',
						'value'    => true,
					],
				],
			],
			$markup['children'][0]['when']
		);
	}

	/**
	 * Deprecation_Builder::hash() is computed from the raw markup, before
	 * "when" normalization — normalizing shouldn't change the hash, since
	 * normalization is a pure function of the raw config.
	 */
	public function test_hash_is_computed_before_when_normalization(): void {
		$markup = [
			'tag'  => 'p',
			'when' => [
				'field'    => 'is_open',
				'operator' => 'equals',
				'value'    => true,
			],
		];

		CassetteCmfBlocks::register_from_array(
			[
				'blocks' => [
					[
						'id'     => 'acme-test/payload-hash-when',
						'render' => [ 'markup' => $markup ],
					],
				],
			]
		);

		$definition = Block_Manager::init()->compile_block( 'acme-test/payload-hash-when' );
		$render     = Editor_Payload::build( Block_Manager::init() )['blocks']['acme-test/payload-hash-when']['render'];

		$this->assertSame(
			Deprecation_Builder::hash( $markup, $definition->get_attributes() ),
			$render['hash']
		);
	}

	/**
	 * A block with no "inner_blocks" config at all ships "enabled": false.
	 */
	public function test_inner_blocks_payload_disabled_by_default(): void {
		CassetteCmfBlocks::register_from_array( [ 'blocks' => [ [ 'id' => 'acme-test/payload-no-inner-blocks' ] ] ] );

		$inner_blocks = Editor_Payload::build( Block_Manager::init() )['blocks']['acme-test/payload-no-inner-blocks']['innerBlocks'];

		$this->assertSame( [ 'enabled' => false ], $inner_blocks );
	}

	/**
	 * Declaring "inner_blocks" implies "enabled": true unless explicitly
	 * set to false, and ships allowed/template/templateLock/orientation.
	 */
	public function test_inner_blocks_payload_ships_full_config(): void {
		CassetteCmfBlocks::register_from_array(
			[
				'blocks' => [
					[
						'id'           => 'acme-test/payload-inner-blocks',
						'inner_blocks' => [
							'allowed'       => [ 'core/paragraph', 'core/heading' ],
							'template'      => [ [ 'core/paragraph', [ 'placeholder' => 'Text...' ] ] ],
							'template_lock' => 'all',
							'orientation'   => 'horizontal',
						],
					],
				],
			]
		);

		$inner_blocks = Editor_Payload::build( Block_Manager::init() )['blocks']['acme-test/payload-inner-blocks']['innerBlocks'];

		$this->assertTrue( $inner_blocks['enabled'] );
		$this->assertSame( [ 'core/paragraph', 'core/heading' ], $inner_blocks['allowed'] );
		$this->assertSame( [ [ 'core/paragraph', [ 'placeholder' => 'Text...' ] ] ], $inner_blocks['template'] );
		$this->assertSame( 'all', $inner_blocks['templateLock'] );
		$this->assertSame( 'horizontal', $inner_blocks['orientation'] );
	}

	/**
	 * An explicit "enabled": false overrides the implied-true default.
	 */
	public function test_inner_blocks_payload_explicit_disabled(): void {
		CassetteCmfBlocks::register_from_array(
			[
				'blocks' => [
					[
						'id'           => 'acme-test/payload-inner-blocks-off',
						'inner_blocks' => [
							'enabled' => false,
							'allowed' => [ 'core/paragraph' ],
						],
					],
				],
			]
		);

		$inner_blocks = Editor_Payload::build( Block_Manager::init() )['blocks']['acme-test/payload-inner-blocks-off']['innerBlocks'];

		$this->assertSame( [ 'enabled' => false ], $inner_blocks );
	}

	/**
	 * A block's top-level "transforms" config ships verbatim to the client —
	 * transforms.js does the actual interpretation, not this class.
	 */
	public function test_transforms_ship_verbatim(): void {
		CassetteCmfBlocks::register_from_array(
			[
				'blocks' => [
					[
						'id'         => 'acme-test/payload-transforms',
						'transforms' => [
							'from' => [
								[
									'type'   => 'block',
									'blocks' => [ 'core/paragraph' ],
									'map'    => [ 'text' => 'content' ],
								],
							],
						],
					],
				],
			]
		);

		$transforms = Editor_Payload::build( Block_Manager::init() )['blocks']['acme-test/payload-transforms']['transforms'];

		$this->assertSame(
			[
				'from' => [
					[
						'type'   => 'block',
						'blocks' => [ 'core/paragraph' ],
						'map'    => [ 'text' => 'content' ],
					],
				],
			],
			$transforms
		);
	}

	/**
	 * A block with no "transforms" config ships null, not an empty array —
	 * transforms.js treats both the same, but null is the more honest "not
	 * declared" signal for payload introspection/debugging.
	 */
	public function test_transforms_default_to_null(): void {
		CassetteCmfBlocks::register_from_array(
			[ 'blocks' => [ [ 'id' => 'acme-test/payload-no-transforms' ] ] ]
		);

		$transforms = Editor_Payload::build( Block_Manager::init() )['blocks']['acme-test/payload-no-transforms']['transforms'];

		$this->assertNull( $transforms );
	}

	/**
	 * A block with no document/sidebar/more_menu-area field ships
	 * "documentScope": null — nothing to gate, so areas/document.js's
	 * registerDocumentAreas() skips it without even consulting
	 * "document_scope".
	 */
	public function test_document_scope_is_null_when_no_field_needs_it(): void {
		CassetteCmfBlocks::register_from_array(
			[ 'blocks' => [ [ 'id' => 'acme-test/payload-no-doc-scope' ] ] ]
		);

		$scope = Editor_Payload::build( Block_Manager::init() )['blocks']['acme-test/payload-no-doc-scope']['documentScope'];

		$this->assertNull( $scope );
	}

	/**
	 * A block with a "document"-area field and a valid "document_scope"
	 * ships the normalized scope.
	 */
	public function test_document_scope_is_normalized_when_valid(): void {
		CassetteCmfBlocks::register_from_array(
			[
				'blocks' => [
					[
						'id'             => 'acme-test/payload-doc-scope',
						'document_scope' => [ 'post_types' => [ 'post' ] ],
						'fields'         => [
							[
								'name'   => 'note',
								'type'   => 'text',
								'area'   => 'document',
								'source' => 'meta',
							],
						],
					],
				],
			]
		);

		$scope = Editor_Payload::build( Block_Manager::init() )['blocks']['acme-test/payload-doc-scope']['documentScope'];

		$this->assertSame(
			[
				'postTypes'   => [ 'post' ],
				'whenPresent' => false,
				'target'      => 'acme-test-payload-doc-scope',
			],
			$scope
		);
	}

	/**
	 * A block with a "document"-area field but no "document_scope" ships
	 * "documentScope": null and logs — Document_Scope_Registrar's own
	 * behaviour, exercised here through the full build() path.
	 */
	public function test_document_scope_is_null_when_invalid(): void {
		$this->setExpectedIncorrectUsage( 'Pedalcms\CassetteCmfBlocks\Core\Document_Scope_Registrar::normalize' );

		CassetteCmfBlocks::register_from_array(
			[
				'blocks' => [
					[
						'id'     => 'acme-test/payload-doc-scope-invalid',
						'fields' => [
							[
								'name'   => 'note',
								'type'   => 'text',
								'area'   => 'document',
								'source' => 'meta',
							],
						],
					],
				],
			]
		);

		$scope = Editor_Payload::build( Block_Manager::init() )['blocks']['acme-test/payload-doc-scope-invalid']['documentScope'];

		$this->assertNull( $scope );
	}

	/**
	 * "settings" must never carry "variation_callback" (a real PHP
	 * callable) to the client — wp_json_encode() on a Closure fails
	 * outright, which would break this block's entire inline payload
	 * script, not just the one offending key.
	 */
	public function test_variation_callback_is_stripped_from_client_settings(): void {
		CassetteCmfBlocks::register_from_array(
			[
				'blocks' => [
					[
						'id'   => 'acme-test/payload-variation-callback',
						'args' => [
							'title'              => 'Callout',
							'variation_callback' => static function () {
								return [];
							},
						],
					],
				],
			]
		);

		$settings = Editor_Payload::build( Block_Manager::init() )['blocks']['acme-test/payload-variation-callback']['settings'];

		$this->assertArrayNotHasKey( 'variation_callback', $settings );
		$this->assertSame( 'Callout', $settings['title'] );
	}

	/**
	 * build_cached() skips caching entirely under WP_DEBUG (real in this
	 * test environment — wp-tests-config.php defines it, and PHP constants
	 * can't be un-defined) — so a poisoned wp_cache_set() entry under the
	 * exact key build_cached() would use is never returned; every call
	 * recomputes fresh, same as calling build() directly.
	 */
	public function test_build_cached_skips_the_cache_under_wp_debug(): void {
		CassetteCmfBlocks::register_from_array(
			[
				'blocks' => [
					[
						'id'   => 'acme-test/payload-cache-debug',
						'args' => [ 'title' => 'Real' ],
					],
				],
			]
		);

		$result = Editor_Payload::build_cached( Block_Manager::init() );

		$this->assertSame( 'Real', $result['blocks']['acme-test/payload-cache-debug']['settings']['title'] );
	}

	/**
	 * With the WP_DEBUG-derived default overridden (via the
	 * cassette_cmf_blocks_payload_cache_skip filter — the only way this
	 * test suite can exercise the real caching path despite WP_DEBUG being
	 * unavoidably true here), a second build_cached() call for UNCHANGED
	 * config returns a cached value straight from the object cache rather
	 * than recomputing — proven by poisoning the object cache, under the
	 * exact key build_cached() itself computes (via Reflection — there is
	 * no public accessor, deliberately: exposing the key format isn't a
	 * real need outside this test), with a sentinel value: a real cache hit
	 * returns the sentinel verbatim; a coincidentally-identical
	 * recomputation would never produce it.
	 */
	public function test_build_cached_reuses_a_cache_hit(): void {
		add_filter( 'cassette_cmf_blocks_payload_cache_skip', '__return_false' );

		CassetteCmfBlocks::register_from_array(
			[
				'blocks' => [
					[
						'id'   => 'acme-test/payload-cache-hit-a',
						'args' => [ 'title' => 'A' ],
					],
				],
			]
		);
		$manager = Block_Manager::init();

		$key_method = new ReflectionMethod( Editor_Payload::class, 'cache_key' );
		$key_method->setAccessible( true );
		$key = $key_method->invoke( null, $manager, '' );

		$sentinel = [
			'version' => '',
			'blocks'  => [ 'sentinel' => true ],
		];
		wp_cache_set( $key, $sentinel, 'cassette_cmf_blocks' );

		$result = Editor_Payload::build_cached( $manager );

		remove_filter( 'cassette_cmf_blocks_payload_cache_skip', '__return_false' );

		$this->assertSame( $sentinel, $result );
	}

	/**
	 * A different config (a real cache-key input) produces a genuinely
	 * different cache entry — this is a cache, not a single global memo.
	 */
	public function test_build_cached_a_different_config_is_a_cache_miss(): void {
		add_filter( 'cassette_cmf_blocks_payload_cache_skip', '__return_false' );

		CassetteCmfBlocks::register_from_array(
			[
				'blocks' => [
					[
						'id'   => 'acme-test/payload-cache-miss-a',
						'args' => [ 'title' => 'A' ],
					],
				],
			]
		);
		$first = Editor_Payload::build_cached( Block_Manager::init() );

		Block_Manager::reset();

		CassetteCmfBlocks::register_from_array(
			[
				'blocks' => [
					[
						'id'   => 'acme-test/payload-cache-miss-b',
						'args' => [ 'title' => 'B' ],
					],
				],
			]
		);
		$second = Editor_Payload::build_cached( Block_Manager::init() );

		remove_filter( 'cassette_cmf_blocks_payload_cache_skip', '__return_false' );

		$this->assertArrayHasKey( 'acme-test/payload-cache-miss-a', $first['blocks'] );
		$this->assertArrayHasKey( 'acme-test/payload-cache-miss-b', $second['blocks'] );
		$this->assertArrayNotHasKey( 'acme-test/payload-cache-miss-b', $first['blocks'] );
	}

	/**
	 * A cache hit found only in the transient fallback (persistent object
	 * cache empty, e.g. after wp_cache_flush() with no persistent backend)
	 * is honoured too, not just an object-cache hit.
	 */
	public function test_build_cached_falls_back_to_the_transient(): void {
		add_filter( 'cassette_cmf_blocks_payload_cache_skip', '__return_false' );

		CassetteCmfBlocks::register_from_array(
			[
				'blocks' => [
					[
						'id'   => 'acme-test/payload-cache-transient',
						'args' => [ 'title' => 'A' ],
					],
				],
			]
		);
		Editor_Payload::build_cached( Block_Manager::init() );

		// Simulate a persistent-object-cache miss (e.g. a differently-scaled
		// web server that never saw the first call) with the DB-backed
		// transient still present.
		wp_cache_flush();

		$result = Editor_Payload::build_cached( Block_Manager::init() );

		remove_filter( 'cassette_cmf_blocks_payload_cache_skip', '__return_false' );

		$this->assertArrayHasKey( 'acme-test/payload-cache-transient', $result['blocks'] );
	}
}
