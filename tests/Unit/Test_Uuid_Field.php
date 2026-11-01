<?php
/**
 * "uuid" field type test: catalog entry, attribute schema, sanitizer.
 *
 * @package PedalCMS\CassetteCMFBlocks\Tests\Unit
 */

use PedalCMS\CassetteCMFBlocks\CassetteCMFBlocks;
use PedalCMS\CassetteCMFBlocks\Core\Block_Definition;
use PedalCMS\CassetteCMFBlocks\Core\Block_Manager;
use PedalCMS\CassetteCMFBlocks\Core\Editor_Payload;
use PedalCMS\CassetteCMFBlocks\Core\Field_Collection;
use PedalCMS\CassetteCMFBlocks\Render\Attribute_Sanitizer;
use PedalCMS\CassetteCMFBlocks\Schema\Control_Catalog;

require_once __DIR__ . '/CassetteCMFBlocks_UnitTestCase.php';

/**
 * Class Test_Uuid_Field
 */
class Test_Uuid_Field extends CassetteCMFBlocks_UnitTestCase {

	/**
	 * Reset library state.
	 */
	public function set_up(): void {
		parent::set_up();
		Control_Catalog::reset();
		Block_Manager::reset();
	}

	/**
	 * Reset library state.
	 */
	public function tear_down(): void {
		Control_Catalog::reset();
		Block_Manager::reset();
		parent::tear_down();
	}

	/**
	 * The catalog knows "uuid": a string value, no parent type, not a container.
	 */
	public function test_catalog_entry(): void {
		$spec = Control_Catalog::get_type( 'uuid' );

		$this->assertSame( 'string', $spec['value_type'] );
		$this->assertNull( $spec['cmf_type'] );
		$this->assertFalse( $spec['is_container'] );
		$this->assertTrue( $spec['holds_value'] );
	}

	/**
	 * The attribute schema is a plain string with an empty default.
	 */
	public function test_attribute_schema(): void {
		$definition = new Block_Definition(
			[
				'id'     => 'acme-test/uuid-schema',
				'fields' => [
					[
						'name' => 'instanceId',
						'type' => 'uuid',
					],
				],
			]
		);

		$this->assertSame(
			[
				'type'    => 'string',
				'default' => '',
			],
			$definition->get_attributes()['instanceId']
		);
	}

	/**
	 * The editor payload carries the field as a "uuid" node (the editor renders no control for it).
	 */
	public function test_payload_ships_the_uuid_node(): void {
		CassetteCMFBlocks::register_from_array(
			[
				'blocks' => [
					[
						'id'     => 'acme-test/uuid-payload',
						'fields' => [
							[
								'name' => 'instanceId',
								'type' => 'uuid',
							],
						],
					],
				],
			]
		);

		$fields = Editor_Payload::build( Block_Manager::init() )['blocks']['acme-test/uuid-payload']['fields'];

		$this->assertSame( 'uuid', $fields[0]['type'] );
		$this->assertSame( 'instanceId', $fields[0]['config']['name'] );

		unregister_block_type( 'acme-test/uuid-payload' );
	}

	/**
	 * Values the sanitizer accepts and what it returns for them.
	 *
	 * @return array<string, array<int, mixed>>
	 */
	public function sanitize_provider(): array {
		return [
			'valid v4'                => [ '3b241101-e2bb-4255-8caf-4136c566a962', '3b241101-e2bb-4255-8caf-4136c566a962' ],
			'uppercase is lowercased' => [ '3B241101-E2BB-4255-8CAF-4136C566A962', '3b241101-e2bb-4255-8caf-4136c566a962' ],
			'mixed case'              => [ '3b241101-E2BB-4255-8caf-4136C566a962', '3b241101-e2bb-4255-8caf-4136c566a962' ],
			'v1'                      => [ '6ba7b810-9dad-11d1-80b4-00c04fd430c8', '6ba7b810-9dad-11d1-80b4-00c04fd430c8' ],
			'v7'                      => [ '018f2d9a-4b3c-7a11-9c3e-5d7c1e2f9a10', '018f2d9a-4b3c-7a11-9c3e-5d7c1e2f9a10' ],
			'braces'                  => [ '{3b241101-e2bb-4255-8caf-4136c566a962}', '' ],
			'urn prefix'              => [ 'urn:uuid:3b241101-e2bb-4255-8caf-4136c566a962', '' ],
			'no dashes'               => [ '3b241101e2bb42558caf4136c566a962', '' ],
			'bad version digit'       => [ '3b241101-e2bb-0255-8caf-4136c566a962', '' ],
			'bad variant digit'       => [ '3b241101-e2bb-4255-cdaf-4136c566a962', '' ],
			'trailing whitespace'     => [ '3b241101-e2bb-4255-8caf-4136c566a962 ', '' ],
			'trailing newline'        => [ "3b241101-e2bb-4255-8caf-4136c566a962\n", '' ],
			'garbage'                 => [ 'not-a-uuid', '' ],
			'markup'                  => [ '<script>alert(1)</script>', '' ],
			'empty string'            => [ '', '' ],
			'integer'                 => [ 5, '' ],
			'array'                   => [ [ '3b241101-e2bb-4255-8caf-4136c566a962' ], '' ],
			'null'                    => [ null, '' ],
			'boolean'                 => [ true, '' ],
		];
	}

	/**
	 * Attribute_Sanitizer::sanitize_uuid().
	 *
	 * @dataProvider sanitize_provider
	 * @param mixed  $input    Raw value.
	 * @param string $expected Expected result.
	 */
	public function test_sanitize_uuid( $input, string $expected ): void {
		$this->assertSame( $expected, Attribute_Sanitizer::sanitize_uuid( $input ) );
	}

	/**
	 * sanitize_all() routes uuid fields through the library's own sanitizer
	 * (there is no parent field type for them).
	 */
	public function test_sanitize_all_cleans_uuid_fields(): void {
		$fields = new Field_Collection(
			[
				[
					'name' => 'instanceId',
					'type' => 'uuid',
				],
				[
					'name' => 'label',
					'type' => 'text',
				],
			]
		);

		$result = Attribute_Sanitizer::sanitize_all(
			[
				'instanceId' => '3B241101-E2BB-4255-8CAF-4136C566A962',
				'label'      => ' hi ',
			],
			$fields
		);

		$this->assertSame( '3b241101-e2bb-4255-8caf-4136c566a962', $result['instanceId'] );
		$this->assertSame( 'hi', $result['label'] );

		$bad = Attribute_Sanitizer::sanitize_all( [ 'instanceId' => 'garbage' ], $fields );
		$this->assertSame( '', $bad['instanceId'] );
	}

	/**
	 * The server never generates an id: a block rendered without one stays empty.
	 */
	public function test_server_never_generates_an_id(): void {
		$fields = new Field_Collection(
			[
				[
					'name' => 'instanceId',
					'type' => 'uuid',
				],
			]
		);

		$result = Attribute_Sanitizer::sanitize_all( [ 'instanceId' => '' ], $fields );

		$this->assertSame( '', $result['instanceId'] );
	}
}
