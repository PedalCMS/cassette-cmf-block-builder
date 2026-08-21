<?php
/**
 * Block_Renderer test.
 *
 * @package Pedalcms\CassetteCmfBlocks\Tests\Unit
 */

use Pedalcms\CassetteCmfBlocks\Core\Block_Definition;
use Pedalcms\CassetteCmfBlocks\Render\Block_Renderer;
use Pedalcms\CassetteCmfBlocks\Schema\Control_Catalog;

require_once __DIR__ . '/CassetteCmfBlocks_UnitTestCase.php';

/**
 * Class Test_Block_Renderer
 */
class Test_Block_Renderer extends CassetteCmfBlocks_UnitTestCase {

	/**
	 * Reset the catalog before each test.
	 */
	public function set_up(): void {
		parent::set_up();
		Control_Catalog::reset();
	}

	/**
	 * Build a minimal, real WP_Block instance for render() to receive.
	 * WP_Block's constructor needs a registered block type and a parsed
	 * block array; a bare instance with no context/inner blocks is enough
	 * for these tests, none of which touch $block itself.
	 *
	 * @param string $name Block name, only used to build the parsed-block array.
	 * @return WP_Block
	 */
	private function make_block( string $name ): WP_Block {
		if ( ! WP_Block_Type_Registry::get_instance()->is_registered( $name ) ) {
			register_block_type( $name, [ 'attributes' => [] ] );
		}

		return new WP_Block(
			[
				'blockName' => $name,
				'attrs'     => [],
			]
		);
	}

	/**
	 * Unregister test block types after each test.
	 */
	public function tear_down(): void {
		foreach ( [ 'acme-test/renderer-markup', 'acme-test/renderer-callback', 'acme-test/renderer-template', 'acme-test/renderer-none' ] as $name ) {
			if ( WP_Block_Type_Registry::get_instance()->is_registered( $name ) ) {
				unregister_block_type( $name );
			}
		}
		Control_Catalog::reset();
		parent::tear_down();
	}

	/**
	 * render.markup produces HTML built from the block's (sanitized) attributes.
	 */
	public function test_renders_from_markup(): void {
		$definition = new Block_Definition(
			[
				'id'     => 'acme-test/renderer-markup',
				'fields' => [
					[
						'name' => 'email',
						'type' => 'email',
					],
				],
				'render' => [
					'markup' => [
						'tag'  => 'p',
						'text' => '{{ attributes.email }}',
					],
				],
			]
		);

		$html = Block_Renderer::render(
			[ 'email' => '  person@example.com  ' ],
			'',
			$this->make_block( 'acme-test/renderer-markup' ),
			$definition
		);

		// Sanitized (trimmed) by Attribute_Sanitizer before rendering.
		$this->assertStringContainsString( 'person@example.com', $html );
		$this->assertStringNotContainsString( '  person@example.com  ', $html );
	}

	/**
	 * render.callback wins over render.markup when both are set.
	 */
	public function test_callback_takes_precedence_over_markup(): void {
		$definition = new Block_Definition(
			[
				'id'     => 'acme-test/renderer-callback',
				'render' => [
					// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- signature matches the real render.callback contract ($attributes, $content, $block); this test only needs $attributes.
					'callback' => static function ( $attributes, $content, $block ) {
						return 'from-callback:' . ( $attributes['label'] ?? '' );
					},
					'markup'   => [
						'tag'  => 'p',
						'text' => 'from-markup',
					],
				],
			]
		);

		$html = Block_Renderer::render(
			[ 'label' => 'x' ],
			'',
			$this->make_block( 'acme-test/renderer-callback' ),
			$definition
		);

		$this->assertSame( 'from-callback:x', $html );
	}

	/**
	 * render.template is require()'d in an output buffer with
	 * $attributes/$content/$block available in its local scope.
	 */
	public function test_renders_from_template(): void {
		$template_path = tempnam( sys_get_temp_dir(), 'cassette-cmf-block-builder-template-' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test-only fixture file in the system temp dir, not a WP_Filesystem-relevant path.
		file_put_contents( $template_path, '<?php echo "tmpl:" . esc_html( $attributes["label"] ) . ":" . esc_html( $content );' );

		$definition = new Block_Definition(
			[
				'id'     => 'acme-test/renderer-template',
				'render' => [ 'template' => $template_path ],
			]
		);

		$html = Block_Renderer::render(
			[ 'label' => 'x' ],
			'inner',
			$this->make_block( 'acme-test/renderer-template' ),
			$definition
		);

		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test-only cleanup of a system-temp-dir fixture file.
		unlink( $template_path );

		$this->assertSame( 'tmpl:x:inner', $html );
	}

	/**
	 * No render config at all renders an empty string, not a fatal.
	 */
	public function test_no_render_config_renders_empty_string(): void {
		$definition = new Block_Definition( [ 'id' => 'acme-test/renderer-none' ] );

		$html = Block_Renderer::render( [], '', $this->make_block( 'acme-test/renderer-none' ), $definition );

		$this->assertSame( '', $html );
	}

	/**
	 * The cassette_cmf_blocks_render and id-suffixed filters both fire, in order.
	 */
	public function test_render_filters_fire(): void {
		$definition = new Block_Definition(
			[
				'id'     => 'acme-test/renderer-markup',
				'render' => [
					'markup' => [
						'tag'  => 'p',
						'text' => 'hi',
					],
				],
			]
		);

		add_filter(
			'cassette_cmf_blocks_render',
			static function ( $html ) {
				return $html . '|general';
			}
		);
		add_filter(
			'cassette_cmf_blocks_render_acme_test_renderer_markup',
			static function ( $html ) {
				return $html . '|specific';
			}
		);

		$html = Block_Renderer::render( [], '', $this->make_block( 'acme-test/renderer-markup' ), $definition );

		remove_all_filters( 'cassette_cmf_blocks_render' );
		remove_all_filters( 'cassette_cmf_blocks_render_acme_test_renderer_markup' );

		$this->assertSame( '<p>hi</p>|general|specific', $html );
	}
}
