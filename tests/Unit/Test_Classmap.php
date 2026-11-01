<?php
/**
 * Composer classmap test.
 *
 * Guards against case-sensitive namespace mismatches (a class declared as
 * Foo\Bar in a file the classmap lists as Foo\bar, or an import with the
 * wrong case) by loading every class, interface and trait this repository's
 * own src/ contributes to the Composer classmap.
 *
 * @package PedalCMS\CassetteCMFBlocks\Tests\Unit
 */

require_once __DIR__ . '/CassetteCMFBlocks_UnitTestCase.php';

/**
 * Class Test_Classmap
 */
class Test_Classmap extends CassetteCMFBlocks_UnitTestCase {

	/**
	 * Classmap entries (symbol => file) whose file is under this repo's src/.
	 *
	 * @return array<string, string>
	 */
	private function own_classmap(): array {
		$map_file = TESTS_PLUGIN_DIR . '/vendor/composer/autoload_classmap.php';
		$this->assertFileExists( $map_file );

		$classmap = require $map_file;
		$src_dir  = realpath( TESTS_PLUGIN_DIR . '/src' ) . DIRECTORY_SEPARATOR;
		$own      = [];

		foreach ( $classmap as $symbol => $file ) {
			$real = realpath( $file );
			if ( false !== $real && 0 === strpos( $real, $src_dir ) ) {
				$own[ $symbol ] = $real;
			}
		}

		return $own;
	}

	/**
	 * The classmap must list this library's classes at all.
	 */
	public function test_classmap_is_not_empty(): void {
		$this->assertNotEmpty( $this->own_classmap() );
	}

	/**
	 * Every mapped symbol must be loadable as the kind of symbol it is.
	 */
	public function test_every_mapped_symbol_loads(): void {
		foreach ( $this->own_classmap() as $symbol => $file ) {
			$this->assertTrue(
				class_exists( $symbol ) || interface_exists( $symbol ) || trait_exists( $symbol ),
				sprintf( '%1$s (%2$s) is mapped by the classmap but cannot be loaded.', $symbol, $file )
			);
		}
	}
}
