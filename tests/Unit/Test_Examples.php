<?php
/**
 * Bundled example configs test.
 *
 * Every JSON config shipped in examples/ (and the E2E fixture) must pass the library's own schema
 * validation and compile into block definitions.
 *
 * @package PedalCMS\CassetteCMFBlocks\Tests\Unit
 */

use PedalCMS\CassetteCMFBlocks\Core\Block_Manager;
use PedalCMS\CassetteCMFBlocks\Json\Block_Schema_Validator;

require_once __DIR__ . '/CassetteCMFBlocks_UnitTestCase.php';

/**
 * Class Test_Examples
 */
class Test_Examples extends CassetteCMFBlocks_UnitTestCase {

	/**
	 * Every examples/<dir>/config*.json file, and the E2E fixture plugin's config.
	 *
	 * @return array<string, array<int, string>>
	 */
	public function example_config_provider(): array {
		$files = array_merge(
			(array) glob( dirname( __DIR__, 2 ) . '/examples/*/config*.json' ),
			(array) glob( dirname( __DIR__, 2 ) . '/tests/e2e/fixture-plugin/config/*.json' )
		);
		$cases = [];

		foreach ( $files as $file ) {
			$cases[ basename( dirname( $file, 2 ) ) . '/' . basename( dirname( $file ) ) . '/' . basename( $file ) ] = [ $file ];
		}

		return $cases;
	}

	/**
	 * The provider must find the bundled examples (guards against a silent empty glob).
	 */
	public function test_provider_finds_examples(): void {
		$this->assertGreaterThanOrEqual( 2, count( $this->example_config_provider() ) );
	}

	/**
	 * A bundled example config validates against the block schema and compiles.
	 *
	 * @dataProvider example_config_provider
	 * @param string $file Absolute path of the config.
	 */
	public function test_example_config_is_valid( string $file ): void {
		$config = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		$this->assertIsArray( $config );

		$validator = new Block_Schema_Validator();
		$this->assertTrue( $validator->validate( $config ), $validator->get_error_message() );

		Block_Manager::reset();
		$manager = Block_Manager::init()->register_from_json( $file );

		$this->assertNotEmpty( $manager->get_blocks() );
		foreach ( array_keys( $manager->get_blocks() ) as $id ) {
			$manager->compile_block( $id );
		}
	}

	/**
	 * The array example (03) registers its parent/child pair with the context
	 * wiring in place: the parent provides the key, the child reads it.
	 */
	public function test_array_example_wires_context_between_parent_and_child(): void {
		// The plugin bootstrap already loaded the example; build its config again on a clean manager.
		Block_Manager::reset();
		require_once dirname( __DIR__, 2 ) . '/examples/03-advanced-array/example.php';
		cassette_cmf_forms_init();

		$parent = Block_Manager::init()->compile_block( 'example/form' );
		$child  = Block_Manager::init()->compile_block( 'example/field' );

		$this->assertSame( [ 'example/formId' => 'formId' ], $parent->get_args()['providesContext'] );
		$this->assertSame( [ 'example/formId' ], $child->get_args()['usesContext'] );
		$this->assertArrayNotHasKey( 'formId', $child->get_attributes() );
		$this->assertSame( 'string', $child->get_attributes()['instanceId']['type'] );

		$registered = WP_Block_Type_Registry::get_instance()->get_registered( 'example/field' );
		$this->assertSame( [ 'example/formId' ], $registered->uses_context );
	}
}
