<?php
/**
 * Attribute_Sanitizer test.
 *
 * @package Pedalcms\CassetteCmfBlocks\Tests\Unit
 */

use Pedalcms\CassetteCmfBlocks\Core\Field_Collection;
use Pedalcms\CassetteCmfBlocks\Render\Attribute_Sanitizer;
use Pedalcms\CassetteCmfBlocks\Schema\Control_Catalog;

require_once __DIR__ . '/CassetteCmfBlocks_UnitTestCase.php';

/**
 * Class Test_Attribute_Sanitizer
 */
class Test_Attribute_Sanitizer extends CassetteCmfBlocks_UnitTestCase {

	/**
	 * Reset the catalog before each test.
	 */
	public function set_up(): void {
		parent::set_up();
		Control_Catalog::reset();
	}

	/**
	 * A top-level attribute-sourced field is sanitized via its cmf_type.
	 */
	public function test_sanitizes_attribute_sourced_fields(): void {
		$fields = new Field_Collection(
			[
				[
					'name' => 'email',
					'type' => 'email',
				],
			]
		);

		$result = Attribute_Sanitizer::sanitize_all( [ 'email' => '  person@example.com  ' ], $fields );

		$this->assertSame( 'person@example.com', $result['email'] );
	}

	/**
	 * A field with source "meta" is not present as a top-level attribute
	 * (that's the meta-binding milestone), so it must be left untouched.
	 */
	public function test_leaves_non_attribute_sourced_fields_untouched(): void {
		$fields = new Field_Collection(
			[
				[
					'name'   => 'internal_id',
					'type'   => 'text',
					'source' => 'meta',
				],
			]
		);

		$result = Attribute_Sanitizer::sanitize_all( [ 'unrelated' => 'value' ], $fields );

		$this->assertSame( [ 'unrelated' => 'value' ], $result );
	}

	/**
	 * A repeater's sub-fields are never iterated by this class directly —
	 * their "name" is a per-row key, not a top-level attribute, so
	 * array_key_exists() against the top-level $attributes is false for
	 * them (the loop only ever calls Cmf_Bridge::sanitize() for the
	 * repeater's own top-level "items" attribute). But the parent's own
	 * Repeater_Field::sanitize() already recurses into each row using the
	 * "fields" config it was built with, so row data still ends up
	 * correctly sanitized — for free, via that single top-level call.
	 */
	public function test_repeater_rows_are_sanitized_via_the_parents_own_recursion(): void {
		$fields = new Field_Collection(
			[
				[
					'name'   => 'items',
					'type'   => 'repeater',
					'fields' => [
						[
							'name' => 'item_label',
							'type' => 'text',
						],
					],
				],
			]
		);

		$result = Attribute_Sanitizer::sanitize_all( [ 'items' => [ [ 'item_label' => '  raw  ' ] ] ], $fields );

		$this->assertSame( 'raw', $result['items'][0]['item_label'] );
	}

	/**
	 * A control type with no honest cmf_type (e.g. "link") is left as-is —
	 * Markup_Renderer's output escaping remains the safeguard for those.
	 */
	public function test_fields_with_no_cmf_type_are_left_as_is(): void {
		$fields = new Field_Collection(
			[
				[
					'name' => 'destination',
					'type' => 'link',
				],
			]
		);

		$result = Attribute_Sanitizer::sanitize_all( [ 'destination' => [ 'url' => 'https://example.com' ] ], $fields );

		$this->assertSame( [ 'url' => 'https://example.com' ], $result['destination'] );
	}

	/**
	 * An attribute the field collection doesn't know about is left untouched.
	 */
	public function test_unknown_attributes_pass_through(): void {
		$fields = new Field_Collection( [] );

		$result = Attribute_Sanitizer::sanitize_all( [ 'lock' => [ 'remove' => true ] ], $fields );

		$this->assertSame( [ 'lock' => [ 'remove' => true ] ], $result );
	}
}
