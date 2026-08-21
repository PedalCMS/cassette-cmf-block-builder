<?php
/**
 * Cassette-CMF Blocks Unit Test Case
 *
 * Base test case class for Cassette-CMF Blocks tests that handles WordPress
 * block registry notices. Copied from the parent library's base test case
 * (cassette-cmf/tests/Unit/CassetteCmf_UnitTestCase.php) because this library
 * registers far more block types per test run than the parent ever does.
 *
 * @package Pedalcms\CassetteCmfBlocks\Tests\Unit
 */

/**
 * Class CassetteCmfBlocks_UnitTestCase
 *
 * Base test case that ignores WordPress block/bindings registry notices which
 * may occur during repeated register_block_type() calls across test methods.
 */
abstract class CassetteCmfBlocks_UnitTestCase extends WP_UnitTestCase {

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();
	}

	/**
	 * Assert post-conditions after each test.
	 *
	 * Override to ignore block registry notices that may occur in WordPress 6.5+,
	 * plus Block_Manager's own "registered after init already fired" notice.
	 *
	 * That notice is a genuine production behaviour (see
	 * Block_Manager::register_blocks()) meant to warn a consumer who calls
	 * register_from_array()/register_from_json() too late for the editor to
	 * see the block. But wp-phpunit's bootstrap fires the real "init" action
	 * exactly once, before any test method runs, and then every test method
	 * body executes entirely outside any hook dispatch — so from
	 * did_action()/doing_action()'s perspective, EVERY register_from_array()
	 * call made directly inside a test method looks "late", even though it
	 * is that test's first and only registration attempt. This is a
	 * structural artifact of the test harness, not a bug under test, so it
	 * is ignored here exactly like the two WP-core block-registry notices
	 * above — for the same reason the parent library's base test case
	 * ignores those.
	 */
	public function assert_post_conditions(): void {
		$ignored_notices = [
			'WP_Block_Type_Registry::register'     => true,
			'WP_Block_Bindings_Registry::register' => true,
			'Pedalcms\CassetteCmfBlocks\Core\Block_Manager::register_blocks' => true,
		];

		$this->caught_doing_it_wrong = array_diff_key( $this->caught_doing_it_wrong, $ignored_notices );

		parent::assert_post_conditions();
	}
}
