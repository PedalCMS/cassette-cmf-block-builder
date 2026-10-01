<?php
/**
 * Area_Resolver test.
 *
 * @package PedalCMS\CassetteCMFBlocks\Tests\Unit
 */

use PedalCMS\CassetteCMFBlocks\Schema\Area_Resolver;

require_once __DIR__ . '/CassetteCMFBlocks_UnitTestCase.php';

/**
 * Class Test_Area_Resolver
 */
class Test_Area_Resolver extends CassetteCMFBlocks_UnitTestCase {

	/**
	 * null/empty should resolve to the default area.
	 */
	public function test_normalize_defaults_to_inspector(): void {
		$this->assertSame( 'inspector', Area_Resolver::normalize( null ) );
		$this->assertSame( 'inspector', Area_Resolver::normalize( '' ) );
	}

	/**
	 * A known area should be returned unchanged.
	 */
	public function test_normalize_passes_through_known_area(): void {
		$this->assertSame( 'inspector.styles', Area_Resolver::normalize( 'inspector.styles' ) );
		$this->assertSame( 'toolbar.block', Area_Resolver::normalize( 'toolbar.block' ) );
		$this->assertSame( 'canvas', Area_Resolver::normalize( 'canvas' ) );
	}

	/**
	 * An unknown area falls back to the default rather than fataling, and
	 * logs a _doing_it_wrong() (WP_UnitTestCase records it but does not fail
	 * the test unless the notice is unexpected — expect it explicitly here).
	 */
	public function test_unknown_area_falls_back_and_warns(): void {
		$this->setExpectedIncorrectUsage( 'PedalCMS\CassetteCMFBlocks\Schema\Area_Resolver::normalize' );

		$this->assertSame( 'inspector', Area_Resolver::normalize( 'not-a-real-area' ) );
	}

	/**
	 * parse() should split surface and group.
	 */
	public function test_parse_splits_surface_and_group(): void {
		$this->assertSame(
			[
				'surface' => 'inspector',
				'group'   => 'styles',
			],
			Area_Resolver::parse( 'inspector.styles' )
		);

		$this->assertSame(
			[
				'surface' => 'canvas',
				'group'   => null,
			],
			Area_Resolver::parse( 'canvas' )
		);
	}

	/**
	 * document/sidebar/more_menu surfaces require document_scope.
	 */
	public function test_requires_document_scope(): void {
		$this->assertTrue( Area_Resolver::requires_document_scope( 'document' ) );
		$this->assertTrue( Area_Resolver::requires_document_scope( 'document.status' ) );
		$this->assertTrue( Area_Resolver::requires_document_scope( 'sidebar' ) );
		$this->assertTrue( Area_Resolver::requires_document_scope( 'more_menu' ) );
		$this->assertFalse( Area_Resolver::requires_document_scope( 'inspector' ) );
		$this->assertFalse( Area_Resolver::requires_document_scope( 'canvas' ) );
	}
}
