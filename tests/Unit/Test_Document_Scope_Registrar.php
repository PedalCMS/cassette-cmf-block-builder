<?php
/**
 * Document_Scope_Registrar test.
 *
 * @package PedalCMS\CassetteCMFBlocks\Tests\Unit
 */

use PedalCMS\CassetteCMFBlocks\Core\Document_Scope_Registrar;

require_once __DIR__ . '/CassetteCMFBlocks_UnitTestCase.php';

/**
 * Class Test_Document_Scope_Registrar
 */
class Test_Document_Scope_Registrar extends CassetteCMFBlocks_UnitTestCase {

	/**
	 * A valid "document_scope" normalizes with its declared post_types,
	 * when_present, and target.
	 */
	public function test_normalizes_a_valid_scope(): void {
		$result = Document_Scope_Registrar::normalize(
			[
				'post_types'   => [ 'post', 'page' ],
				'when_present' => true,
				'target'       => 'acme-panel',
			],
			'acme/callout'
		);

		$this->assertSame(
			[
				'postTypes'   => [ 'post', 'page' ],
				'whenPresent' => true,
				'target'      => 'acme-panel',
			],
			$result
		);
	}

	/**
	 * "when_present" defaults to false, and "target" defaults to the
	 * block's own name (with "/" replaced, since registerPlugin()/
	 * PluginSidebar's "name" must be a valid SlotFill identifier).
	 */
	public function test_defaults_when_present_and_target(): void {
		$result = Document_Scope_Registrar::normalize(
			[ 'post_types' => [ 'post' ] ],
			'acme/callout'
		);

		$this->assertSame(
			[
				'postTypes'   => [ 'post' ],
				'whenPresent' => false,
				'target'      => 'acme-callout',
			],
			$result
		);
	}

	/**
	 * Missing "document_scope" entirely returns null and logs.
	 */
	public function test_null_input_returns_null_and_logs(): void {
		$this->setExpectedIncorrectUsage( 'PedalCMS\CassetteCMFBlocks\Core\Document_Scope_Registrar::normalize' );

		$this->assertNull( Document_Scope_Registrar::normalize( null, 'acme/callout' ) );
	}

	/**
	 * An empty "post_types" array is invalid — there's nothing to gate on.
	 */
	public function test_empty_post_types_returns_null_and_logs(): void {
		$this->setExpectedIncorrectUsage( 'PedalCMS\CassetteCMFBlocks\Core\Document_Scope_Registrar::normalize' );

		$this->assertNull(
			Document_Scope_Registrar::normalize( [ 'post_types' => [] ], 'acme/callout' )
		);
	}
}
