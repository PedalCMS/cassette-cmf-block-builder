<?php
/**
 * Control_Mapper test.
 *
 * @package Pedalcms\CassetteCmfBlocks\Tests\Unit
 */

use Pedalcms\CassetteCmfBlocks\Schema\Control_Catalog;
use Pedalcms\CassetteCmfBlocks\Schema\Control_Mapper;

require_once __DIR__ . '/CassetteCmfBlocks_UnitTestCase.php';

/**
 * Class Test_Control_Mapper
 */
class Test_Control_Mapper extends CassetteCmfBlocks_UnitTestCase {

	/**
	 * Reset the catalog before each test.
	 */
	public function set_up(): void {
		parent::set_up();
		Control_Catalog::reset();
	}

	/**
	 * resolve() should return the catalog spec and its declared cmf_type
	 * when the field config doesn't override it.
	 */
	public function test_resolve_returns_catalog_cmf_type(): void {
		$resolved = Control_Mapper::resolve(
			[
				'name' => 'headline',
				'type' => 'text',
			]
		);

		$this->assertSame( 'text', $resolved['type'] );
		$this->assertSame( 'text', $resolved['cmf_type'] );
		$this->assertIsArray( $resolved['spec'] );
	}

	/**
	 * An inline "cmf_type" should override the catalog's default, as used
	 * by custom controls that want to reuse a specific parent sanitizer.
	 */
	public function test_inline_cmf_type_overrides_catalog_default(): void {
		$resolved = Control_Mapper::resolve(
			[
				'name'     => 'rating',
				'type'     => 'number',
				'cmf_type' => 'text',
			]
		);

		$this->assertSame( 'text', $resolved['cmf_type'] );
	}

	/**
	 * "password" must be rejected with a security-specific message, not a
	 * generic "unknown type" error.
	 */
	public function test_password_is_rejected_with_security_rationale(): void {
		try {
			Control_Mapper::resolve(
				[
					'name' => 'secret',
					'type' => 'password',
				]
			);
			$this->fail( 'Expected InvalidArgumentException.' );
		} catch ( InvalidArgumentException $e ) {
			$this->assertStringContainsString( 'post_content', $e->getMessage() );
		}
	}

	/**
	 * An unregistered type should throw with a message pointing at the
	 * extension mechanism.
	 */
	public function test_unknown_type_throws(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessageMatches( '/Unknown control type/' );

		Control_Mapper::resolve(
			[
				'name' => 'x',
				'type' => 'does_not_exist',
			]
		);
	}

	/**
	 * A field config without "type" should throw.
	 */
	public function test_missing_type_throws(): void {
		$this->expectException( InvalidArgumentException::class );

		Control_Mapper::resolve( [ 'name' => 'x' ] );
	}
}
