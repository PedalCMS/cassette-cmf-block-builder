<?php
/**
 * Public server-side attribute API: Block_Definition::sanitize_attributes(),
 * ::attribute_schema() and CassetteCMFBlocks::get_definition(). The central
 * claim: sanitize_attributes() returns exactly what a real render hands the
 * render callback for the same input, for every value-bearing control type.
 *
 * @package PedalCMS\CassetteCMFBlocks\Tests\Unit
 */

use PedalCMS\CassetteCMFBlocks\CassetteCMFBlocks;
use PedalCMS\CassetteCMFBlocks\Core\Block_Definition;
use PedalCMS\CassetteCMFBlocks\Core\Block_Manager;
use PedalCMS\CassetteCMFBlocks\Schema\Control_Catalog;

require_once __DIR__ . '/CassetteCMFBlocks_UnitTestCase.php';

/**
 * Class Test_Attribute_Api
 */
class Test_Attribute_Api extends CassetteCMFBlocks_UnitTestCase {

	/**
	 * Block name used by the all-types block.
	 */
	private const BLOCK = 'acme-test/all-types';

	/**
	 * What the render callback last received.
	 *
	 * @var array<string, mixed>|null
	 */
	private static ?array $received = null;

	/**
	 * Reset library state.
	 */
	public function set_up(): void {
		parent::set_up();
		Control_Catalog::reset();
		Block_Manager::reset();
		self::$received = null;
	}

	/**
	 * Unregister test blocks and reset state.
	 */
	public function tear_down(): void {
		if ( WP_Block_Type_Registry::get_instance()->is_registered( self::BLOCK ) ) {
			unregister_block_type( self::BLOCK );
		}
		Block_Manager::reset();
		Control_Catalog::reset();
		parent::tear_down();
	}

	/**
	 * Render callback that records its attributes.
	 *
	 * @param array<string, mixed> $attributes Sanitized attributes.
	 * @return string
	 */
	public static function capture( array $attributes ): string {
		self::$received = $attributes;

		return '<p>captured</p>';
	}

	/**
	 * Extra field config for control types that need more than name + type.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function type_overrides(): array {
		$options = [
			'a' => 'Option A',
			'b' => 'Option B',
		];

		return [
			'select'          => [ 'options' => $options ],
			'radio'           => [ 'options' => $options ],
			'checkbox_group'  => [ 'options' => $options ],
			'toggle_group'    => [ 'options' => $options ],
			'font_family'     => [ 'options' => $options ],
			'text'            => [ 'format' => 'slug' ],
			'number'          => [
				'min' => 0,
				'max' => 10,
			],
			'repeater'        => [
				'fields' => [
					[
						'name' => 'item',
						'type' => 'text',
					],
				],
			],
			'taxonomy_select' => [ 'taxonomy' => 'category' ],
			'ancestor_field'  => [ 'ancestor' => 'acme-test/form' ],
			'entity_select'   => [
				'entity' => [
					'kind' => 'postType',
					'name' => 'post',
				],
			],
		];
	}

	/**
	 * The value-bearing, attribute-sourced control types.
	 *
	 * @return string[]
	 */
	private function value_types(): array {
		$types = [];

		foreach ( Control_Catalog::get_registered_types() as $type => $spec ) {
			if ( ! empty( $spec['holds_value'] ) ) {
				$types[] = $type;
			}
		}

		return $types;
	}

	/**
	 * Register one block with a field of every value-bearing type, named
	 * f_{type}, plus a "source": "context" field (which has no attribute).
	 *
	 * @return Block_Definition
	 */
	private function register_all_types(): Block_Definition {
		$overrides = $this->type_overrides();
		$fields    = [];

		foreach ( $this->value_types() as $type ) {
			$fields[] = array_merge(
				[
					'name' => 'f_' . $type,
					'type' => $type,
				],
				$overrides[ $type ] ?? []
			);
		}

		$fields[] = [
			'name'    => 'ctx',
			'type'    => 'text',
			'source'  => 'context',
			'context' => 'acme-test/key',
		];

		CassetteCMFBlocks::register_from_array(
			[
				'blocks' => [
					[
						'id'     => self::BLOCK,
						'fields' => $fields,
						'render' => [ 'callback' => [ self::class, 'capture' ] ],
					],
				],
			]
		);

		$definition = CassetteCMFBlocks::get_definition( self::BLOCK );
		$this->assertInstanceOf( Block_Definition::class, $definition );

		return $definition;
	}

	/**
	 * A valid-looking and a junk value for a control type, by value type.
	 *
	 * @param string $type Control type.
	 * @return array{valid: mixed, junk: mixed, dirty: mixed}
	 */
	private function sample_values( string $type ): array {
		$spec  = Control_Catalog::get_type( $type );
		$vtype = $spec['value_type'];

		$by_type = [
			'string'  => [
				'valid' => 'a',
				'junk'  => [ 'not', 'a', 'string' ],
				'dirty' => "  <script>alert(1)</script> Hello  \n",
			],
			'boolean' => [
				'valid' => true,
				'junk'  => 'maybe',
				'dirty' => 1,
			],
			'number'  => [
				'valid' => 5,
				'junk'  => 'abc',
				'dirty' => '7.5',
			],
			'integer' => [
				'valid' => 12,
				'junk'  => 'abc',
				'dirty' => '12',
			],
			'array'   => [
				'valid' => [ 'a' ],
				'junk'  => 'scalar',
				'dirty' => [ 'a', 'zzz', 3 ],
			],
			'object'  => [
				'valid' => [ 'k' => 'v' ],
				'junk'  => 'scalar',
				'dirty' => [ 'k' => '<b>v</b>' ],
			],
		];

		$sample = $by_type[ $vtype ];

		if ( 'uuid' === $type ) {
			$sample['valid'] = '123e4567-e89b-42d3-a456-426614174000';
			$sample['dirty'] = '123E4567-E89B-42D3-A456-426614174000';
		}
		if ( 'repeater' === $type ) {
			$sample['valid'] = [ [ 'item' => 'One' ] ];
			$sample['dirty'] = [
				[ 'item' => '<b>Two</b>' ],
				[ 'item' => "  spaced \n" ],
			];
		}
		if ( in_array( $type, [ 'select', 'radio', 'toggle_group', 'font_family' ], true ) ) {
			$sample['junk']  = 'zzz-not-an-option';
			$sample['dirty'] = 'b';
		}

		return $sample;
	}

	/**
	 * Render one instance with the given raw attributes and return what the
	 * render callback received.
	 *
	 * @param array<string, mixed> $raw Raw attributes.
	 * @return array<string, mixed>
	 */
	private function render_and_capture( array $raw ): array {
		self::$received = null;

		$html = do_blocks( '<!-- wp:' . self::BLOCK . ' ' . wp_json_encode( (object) $raw ) . ' /-->' );

		$this->assertStringContainsString( 'captured', $html );
		$this->assertIsArray( self::$received, 'The render callback should have run.' );

		return self::$received;
	}

	/**
	 * Remove the context field, which sanitize_attributes() does not cover,
	 * and sort by key: WordPress prepares the attributes once before the
	 * render callback and sanitize_attributes() prepares them again, which
	 * can move a schema default (one that fails its own enum) to the end of
	 * the array. The values are what counts, not the key order.
	 *
	 * @param array<string, mixed> $attributes Attributes the callback received.
	 * @return array<string, mixed>
	 */
	private function without_context( array $attributes ): array {
		unset( $attributes['ctx'] );

		return $this->sorted( $attributes );
	}

	/**
	 * Sort an attribute array by key (top level only).
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @return array<string, mixed>
	 */
	private function sorted( array $attributes ): array {
		ksort( $attributes );

		return $attributes;
	}

	/**
	 * Every scenario, for every type, in one block: what sanitize_attributes()
	 * returns is what a real render passes to the callback.
	 */
	public function test_sanitize_attributes_equals_what_the_render_callback_receives(): void {
		$definition = $this->register_all_types();
		$types      = $this->value_types();
		$this->assertContains( 'uuid', $types );
		$this->assertContains( 'repeater', $types );
		$this->assertGreaterThan( 25, count( $types ) );

		$scenarios = [ 'valid', 'junk', 'dirty' ];
		$inputs    = [ 'missing' => [] ];

		foreach ( $scenarios as $scenario ) {
			$input = [];
			foreach ( $types as $type ) {
				$input[ 'f_' . $type ] = $this->sample_values( $type )[ $scenario ];
			}
			$inputs[ $scenario ] = $input;
		}

		// Mixed: alternate valid and junk values, and add unknown keys WordPress passes along.
		$mixed = [];
		foreach ( $types as $index => $type ) {
			$mixed[ 'f_' . $type ] = $this->sample_values( $type )[ 0 === $index % 2 ? 'valid' : 'junk' ];
		}
		$mixed['className'] = 'is-style-x';
		$mixed['lock']      = [ 'move' => true ];
		$inputs['mixed']    = $mixed;

		foreach ( $inputs as $label => $raw ) {
			// Attribute values as the parser would hand them over: JSON round-tripped.
			$round_tripped = json_decode( (string) wp_json_encode( (object) $raw ), true );
			$expected      = $this->sorted( $definition->sanitize_attributes( $round_tripped ) );
			$received      = $this->without_context( $this->render_and_capture( $raw ) );

			$this->assertSame( $expected, $received, "sanitize_attributes() and the render callback disagree for scenario \"$label\"" );
		}
	}

	/**
	 * Per-type check with a precise failure message: for each type, the three
	 * scenarios agree, and the result honours the schema (defaults for
	 * missing, default or absence for junk).
	 */
	public function test_each_type_agrees_individually(): void {
		$definition = $this->register_all_types();

		foreach ( $this->value_types() as $type ) {
			$name   = 'f_' . $type;
			$sample = $this->sample_values( $type );

			foreach ( [ 'valid', 'junk', 'dirty' ] as $scenario ) {
				$raw      = [ $name => $sample[ $scenario ] ];
				$expected = $this->sorted( $definition->sanitize_attributes( json_decode( (string) wp_json_encode( (object) $raw ), true ) ) );
				$received = $this->without_context( $this->render_and_capture( $raw ) );

				$this->assertSame( $expected, $received, "type $type, scenario $scenario" );
			}

			$expected = $this->sorted( $definition->sanitize_attributes( [] ) );
			$received = $this->without_context( $this->render_and_capture( [] ) );
			$this->assertSame( $expected, $received, "type $type, missing" );
		}
	}

	/**
	 * Defaults: a missing key gets the schema default; junk reverts to it.
	 */
	public function test_defaults_fill_missing_keys_and_replace_junk(): void {
		CassetteCMFBlocks::register_from_array(
			[
				'blocks' => [
					[
						'id'     => self::BLOCK,
						'fields' => [
							[
								'name'    => 'title',
								'type'    => 'text',
								'default' => 'Untitled',
							],
							[
								'name'    => 'count',
								'type'    => 'number',
								'default' => 3,
							],
							[
								'name'    => 'on',
								'type'    => 'toggle',
								'default' => true,
							],
							[
								'name' => 'note',
								'type' => 'text',
							],
						],
						'render' => [ 'callback' => [ self::class, 'capture' ] ],
					],
				],
			]
		);
		$definition = CassetteCMFBlocks::get_definition( self::BLOCK );

		$missing = $definition->sanitize_attributes( [] );
		$this->assertSame( 'Untitled', $missing['title'] );
		$this->assertEquals( 3, $missing['count'] );
		$this->assertEquals( true, $missing['on'] );

		$junk = $definition->sanitize_attributes(
			[
				'title' => [ 'array' ],
				'count' => 'many',
				'on'    => 'perhaps',
			]
		);
		$this->assertSame( 'Untitled', $junk['title'] );
		$this->assertEquals( 3, $junk['count'] );
		$this->assertEquals( true, $junk['on'] );

		$kept = $definition->sanitize_attributes(
			[
				'title' => '  Kept  ',
				'count' => 9,
				'on'    => false,
			]
		);
		$this->assertEquals( false, $kept['on'] );
		$this->assertEquals( 9, $kept['count'] );

		$this->assertSame(
			$this->sorted( $junk ),
			$this->sorted(
				$this->render_and_capture(
					[
						'title' => [ 'array' ],
						'count' => 'many',
						'on'    => 'perhaps',
					]
				)
			)
		);
		$this->assertSame( $this->sorted( $missing ), $this->sorted( $this->render_and_capture( [] ) ) );
	}

	/**
	 * The uuid sanitizer runs: a valid uuid is lowercased, junk becomes ''.
	 */
	public function test_uuid_goes_through_the_sanitizer(): void {
		$definition = $this->register_all_types();

		$ok = $definition->sanitize_attributes( [ 'f_uuid' => '123E4567-E89B-42D3-A456-426614174000' ] );
		$this->assertSame( '123e4567-e89b-42d3-a456-426614174000', $ok['f_uuid'] );

		$bad = $definition->sanitize_attributes( [ 'f_uuid' => 'not-a-uuid' ] );
		$this->assertSame( '', $bad['f_uuid'] );
	}

	/**
	 * The comparison is not vacuous: sanitizers and WordPress's preparation
	 * really change input.
	 */
	public function test_pipeline_actually_transforms_input(): void {
		$definition = $this->register_all_types();

		$out = $definition->sanitize_attributes(
			[
				'f_text'   => '  <b>Hello World</b>  ',
				'f_select' => 'zzz-not-an-option',
				'f_number' => 'abc',
				'f_url'    => 'javascript:alert(1)',
			]
		);

		$this->assertSame( 'Hello World', $out['f_text'] );
		$this->assertSame( '', $out['f_select'] );
		$this->assertSame( 0, $out['f_number'] );
		$this->assertStringNotContainsString( 'javascript', $out['f_url'] );
		$this->assertSame(
			$this->sorted( $out ),
			$this->without_context(
				$this->render_and_capture(
					[
						'f_text'   => '  <b>Hello World</b>  ',
						'f_select' => 'zzz-not-an-option',
						'f_number' => 'abc',
						'f_url'    => 'javascript:alert(1)',
					]
				)
			)
		);
	}

	/**
	 * A context field has no attribute: it is absent from the schema and from
	 * sanitize_attributes(), and present in what the callback receives.
	 */
	public function test_context_fields_have_no_attribute(): void {
		$definition = $this->register_all_types();

		$this->assertArrayNotHasKey( 'ctx', $definition->attribute_schema() );
		$this->assertArrayNotHasKey( 'ctx', $definition->sanitize_attributes( [] ) );
		$this->assertArrayHasKey( 'ctx', $this->render_and_capture( [] ) );
	}

	/**
	 * attribute_schema() is the exact "attributes" array registered with WordPress.
	 */
	public function test_attribute_schema_is_what_register_block_type_got(): void {
		$definition = $this->register_all_types();
		$type       = WP_Block_Type_Registry::get_instance()->get_registered( self::BLOCK );

		// WordPress adds its own "lock" and "metadata" attributes on top.
		$this->assertSame(
			$definition->attribute_schema(),
			array_diff_key(
				$type->attributes,
				[
					'lock'     => 1,
					'metadata' => 1,
				]
			)
		);
		$this->assertSame( $definition->attribute_schema(), $definition->to_block_type_args()['attributes'] );
		$this->assertSame( $definition->get_attributes(), $definition->attribute_schema() );
		$this->assertSame( 'string', $definition->attribute_schema()['f_text']['type'] );
	}

	/**
	 * get_definition(): by full block name, null for unknown names, and
	 * null before anything was booted.
	 */
	public function test_get_definition(): void {
		$this->assertNull( CassetteCMFBlocks::get_definition( self::BLOCK ), 'Nothing booted yet.' );

		$definition = $this->register_all_types();

		$this->assertSame( $definition, CassetteCMFBlocks::get_definition( self::BLOCK ) );
		$this->assertSame( self::BLOCK, CassetteCMFBlocks::get_definition( self::BLOCK )->get_name() );
		$this->assertNull( CassetteCMFBlocks::get_definition( 'acme-test/does-not-exist' ) );
	}

	/**
	 * The new methods are marked @api in their docblocks.
	 */
	public function test_api_docblocks(): void {
		foreach ( [
			[ Block_Definition::class, 'sanitize_attributes' ],
			[ Block_Definition::class, 'attribute_schema' ],
			[ CassetteCMFBlocks::class, 'get_definition' ],
		] as $pair ) {
			$doc = ( new ReflectionMethod( $pair[0], $pair[1] ) )->getDocComment();
			$this->assertStringContainsString( '@api', (string) $doc, $pair[0] . '::' . $pair[1] );
		}
	}
}
