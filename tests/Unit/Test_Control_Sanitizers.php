<?php
/**
 * Server sanitizers for the controls that have no cassette-cmf field type.
 *
 * @package PedalCMS\CassetteCMFBlocks\Tests\Unit
 */

use PedalCMS\CassetteCMFBlocks\CassetteCMFBlocks;
use PedalCMS\CassetteCMFBlocks\Core\Block_Manager;
use PedalCMS\CassetteCMFBlocks\Core\Field_Collection;
use PedalCMS\CassetteCMFBlocks\Render\Attribute_Sanitizer;
use PedalCMS\CassetteCMFBlocks\Schema\Control_Catalog;

require_once __DIR__ . '/CassetteCMFBlocks_UnitTestCase.php';

/**
 * Class Test_Control_Sanitizers
 */
class Test_Control_Sanitizers extends CassetteCMFBlocks_UnitTestCase {

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
		Block_Manager::reset();
		Control_Catalog::reset();
		parent::tear_down();
	}

	/**
	 * Sanitize one raw value for a field of the given type.
	 *
	 * @param string               $type  Control type.
	 * @param mixed                $value Raw value.
	 * @param array<string, mixed> $extra Extra field config.
	 * @return mixed
	 */
	private function clean( string $type, $value, array $extra = [] ) {
		$fields = new Field_Collection(
			[
				array_merge(
					[
						'name' => 'f',
						'type' => $type,
					],
					$extra
				),
			]
		);

		return Attribute_Sanitizer::sanitize_all( [ 'f' => $value ], $fields )['f'];
	}

	/**
	 * Every value-bearing control type is sanitized: by the parent library, by uuid, or by this class.
	 */
	public function test_every_value_type_has_a_sanitizer(): void {
		$constant = new ReflectionClassConstant( Attribute_Sanitizer::class, 'CONTROL_SANITIZERS' );
		$own      = array_keys( $constant->getValue() );
		$missing  = [];

		foreach ( Control_Catalog::get_registered_types() as $type => $spec ) {
			if ( empty( $spec['holds_value'] ) || 'repeater' === $type || 'uuid' === $type ) {
				continue;
			}

			if ( null === $spec['cmf_type'] && ! in_array( $type, $own, true ) ) {
				$missing[] = $type;
			}
		}

		$this->assertSame( [], $missing, 'Control types with no server sanitizer.' );
	}

	/**
	 * Valid, junk and hostile values for every type that has its own sanitizer.
	 *
	 * @dataProvider provide_cases
	 *
	 * @param string               $type     Control type.
	 * @param mixed                $input    Raw value.
	 * @param mixed                $expected Expected result.
	 * @param array<string, mixed> $extra    Extra field config.
	 */
	public function test_sanitizer_output( string $type, $input, $expected, array $extra = [] ): void {
		$this->assertSame( $expected, $this->clean( $type, $input, $extra ) );
	}

	/**
	 * Cases: type, input, expected output, extra config.
	 *
	 * @return array<string, array<int, mixed>>
	 */
	public function provide_cases(): array {
		$xss = '<script>alert(1)</script>';

		return [
			// rich_text.
			'rich_text valid'                => [ 'rich_text', '<strong>Hi</strong> <a href="https://example.com">x</a>', '<strong>Hi</strong> <a href="https://example.com">x</a>' ],
			// kses removes the tags and attributes; the text between <script> tags stays as inert text.
			'rich_text strips script'        => [ 'rich_text', 'a<script>alert(1)</script><b onclick="x()">b</b>', 'aalert(1)<b>b</b>' ],
			'rich_text wrong shape'          => [ 'rich_text', [ 'x' ], '' ],

			// code.
			'code keeps markup'              => [ 'code', '<b>x</b> if ($a < 1) { echo "y"; }', '<b>x</b> if ($a < 1) { echo "y"; }' ],
			'code wrong shape'               => [ 'code', [ 'x' ], '' ],
			'code wrong shape with default'  => [ 'code', 5, 'none', [ 'default' => 'none' ] ],

			// icon.
			'icon valid'                     => [ 'icon', 'dashicons-admin-home', 'dashicons-admin-home' ],
			'icon namespaced'                => [ 'icon', 'core/arrow-right', 'core/arrow-right' ],
			'icon xss'                       => [ 'icon', '"><img src=x onerror=alert(1)>', '' ],
			'icon wrong shape'               => [ 'icon', [ 'a' ], '' ],

			// link.
			'link valid'                     => [
				'link',
				[
					'url'           => 'https://example.com/a b',
					'title'         => 'Hello',
					'opensInNewTab' => true,
					'id'            => 12,
					'type'          => 'post',
					'kind'          => 'post-type',
				],
				[
					'url'           => 'https://example.com/a%20b',
					'title'         => 'Hello',
					'opensInNewTab' => true,
					'id'            => 12,
					'type'          => 'post',
					'kind'          => 'post-type',
				],
			],
			'link xss'                       => [
				'link',
				[
					'url'     => 'javascript:alert(1)',
					'title'   => $xss . 'T',
					'onclick' => 'x()',
				],
				[
					'url'   => '',
					'title' => 'T',
				],
			],
			'link wrong types in keys'       => [
				'link',
				[
					'url'           => [ 'x' ],
					'opensInNewTab' => 'yes',
					'id'            => '12abc',
				],
				[
					'url'           => '',
					'opensInNewTab' => false,
					'id'            => 0,
				],
			],
			'link wrong shape'               => [ 'link', 'https://example.com', [] ],

			// box and spacing.
			'box valid'                      => [
				'box',
				[
					'top'    => '10px',
					'right'  => '1.5rem',
					'bottom' => '0',
					'left'   => '-2em',
				],
				[
					'top'    => '10px',
					'right'  => '1.5rem',
					'bottom' => '0',
					'left'   => '-2em',
				],
			],
			'box preset and auto'            => [
				'spacing',
				[
					'top'  => 'var:preset|spacing|40',
					'left' => 'auto',
				],
				[
					'top'  => 'var:preset|spacing|40',
					'left' => 'auto',
				],
			],
			'box css injection'              => [
				'box',
				[
					'top'    => '10px; background:url(//evil)',
					'right'  => 'expression(alert(1))',
					'bottom' => 5,
					'left'   => '1px',
					'extra'  => '1px',
				],
				[
					'top'    => '',
					'right'  => '',
					'bottom' => '',
					'left'   => '1px',
				],
			],
			'box wrong shape'                => [ 'box', '10px', [] ],

			// border.
			'border valid'                   => [
				'border',
				[
					'width' => '2px',
					'style' => 'dashed',
					'color' => '#ff0000',
				],
				[
					'width' => '2px',
					'style' => 'dashed',
					'color' => '#ff0000',
				],
			],
			'border junk parts'              => [
				'border',
				[
					'width' => '2px solid red',
					'style' => 'wavy',
					'color' => 'red; x:y',
				],
				[
					'width' => '',
					'style' => '',
					'color' => '',
				],
			],
			'border color forms'             => [
				'border',
				[ 'color' => 'rgba(0, 0, 0, 0.5)' ],
				[ 'color' => 'rgba(0, 0, 0, 0.5)' ],
			],
			'border wrong shape'             => [ 'border', 'solid', [] ],

			// border_box.
			'border_box valid'               => [
				'border_box',
				[
					'top'  => [
						'width' => '1px',
						'style' => 'solid',
						'color' => 'var:preset|color|primary',
					],
					'left' => [ 'width' => '3px' ],
				],
				[
					'top'  => [
						'width' => '1px',
						'style' => 'solid',
						'color' => 'var:preset|color|primary',
					],
					'left' => [ 'width' => '3px' ],
				],
			],
			'border_box hostile'             => [
				'border_box',
				[
					'top'    => [ 'width' => '<script>' ],
					'right'  => 'solid',
					'bottom' => [],
					'other'  => [ 'width' => '1px' ],
				],
				[
					'top'    => [ 'width' => '' ],
					'right'  => [],
					'bottom' => [],
				],
			],
			'border_box wrong shape'         => [ 'border_box', 3, [] ],

			// shadow.
			'shadow valid'                   => [ 'shadow', '0 4px 8px rgba(0,0,0,0.25)', '0 4px 8px rgba(0,0,0,0.25)' ],
			'shadow inset with color'        => [ 'shadow', 'inset 0 1px 2px #333', 'inset 0 1px 2px #333' ],
			'shadow preset'                  => [ 'shadow', 'var:preset|shadow|natural', 'var:preset|shadow|natural' ],
			'shadow empty'                   => [ 'shadow', '', '' ],
			'shadow injection'               => [ 'shadow', '0 0 1px red; background:url(x)', '' ],
			'shadow url'                     => [ 'shadow', '0 0 url(x)', '' ],
			'shadow wrong shape'             => [ 'shadow', [ 'x' ], '' ],
			'shadow from presets list'       => [
				'shadow',
				'drop-shadow 3px 3px',
				'drop-shadow 3px 3px',
				[
					'presets' => [
						[
							'value' => 'drop-shadow 3px 3px',
							'label' => 'Drop',
						],
					],
				],
			],
			'font_size from sizes list'      => [
				'font_size',
				'1.25em',
				'1.25em',
				[
					'sizes' => [
						[
							'name' => 'X',
							'size' => '1.25em',
							'slug' => 'x',
						],
					],
				],
			],

			// angle.
			'angle int'                      => [ 'angle', 90, 90 ],
			'angle numeric string'           => [ 'angle', '45', 45 ],
			'angle clamped'                  => [ 'angle', 720, 360 ],
			'angle negative'                 => [ 'angle', -10, 0 ],
			'angle junk'                     => [ 'angle', 'abc', 0 ],
			'angle junk with default'        => [ 'angle', [ 1 ], 180, [ 'default' => 180 ] ],

			// alignment_matrix.
			'alignment valid'                => [ 'alignment_matrix', 'top right', 'top right' ],
			'alignment invalid'              => [ 'alignment_matrix', 'middle middle', '' ],
			'alignment xss'                  => [ 'alignment_matrix', $xss, '' ],
			'alignment with default'         => [ 'alignment_matrix', 'zz', 'center center', [ 'default' => 'center center' ] ],

			// unit and letter_spacing.
			'unit valid'                     => [ 'unit', '12px', '12px' ],
			'unit decimal'                   => [ 'unit', '1.5rem', '1.5rem' ],
			'unit not an allowed unit'       => [ 'unit', '12pt', '' ],
			'unit configured units'          => [ 'unit', '12pt', '12pt', [ 'units' => [ 'pt', 'px' ] ] ],
			'unit injection'                 => [ 'unit', '12px;color:red', '' ],
			'unit wrong shape'               => [ 'unit', 12, '' ],
			'letter_spacing valid'           => [ 'letter_spacing', '0.05em', '0.05em' ],
			'letter_spacing wrong unit'      => [ 'letter_spacing', '5%', '' ],

			// font_size.
			'font_size length'               => [ 'font_size', '16px', '16px' ],
			'font_size preset'               => [ 'font_size', 'var(--wp--preset--font-size--large)', 'var(--wp--preset--font-size--large)' ],
			'font_size clamp'                => [ 'font_size', 'clamp(1rem, 2vw, 2rem)', 'clamp(1rem, 2vw, 2rem)' ],
			'font_size nested function'      => [ 'font_size', 'calc(1px + url(x))', '' ],
			'font_size injection'            => [ 'font_size', '16px; color:red', '' ],
			'font_size wrong shape'          => [ 'font_size', 16, '' ],

			// font_family.
			'font_family stack'              => [ 'font_family', '"Open Sans", sans-serif', '"Open Sans", sans-serif' ],
			'font_family from options'       => [
				'font_family',
				'Georgia, serif',
				'Georgia, serif',
				[
					'options' => [
						'Georgia, serif' => 'Serif',
					],
				],
			],
			'font_family not in options'     => [
				'font_family',
				'Arial',
				'',
				[
					'options' => [
						'Georgia, serif' => 'Serif',
					],
				],
			],
			'font_family injection'          => [ 'font_family', 'Arial; background:url(x)', '' ],
			'font_family wrong shape'        => [ 'font_family', [ 'Arial' ], '' ],

			// font_appearance.
			'font_appearance valid'          => [ 'font_appearance', 'italic 700', 'italic 700' ],
			'font_appearance junk'           => [ 'font_appearance', 'italic 750', '' ],
			'font_appearance xss'            => [ 'font_appearance', $xss, '' ],

			// line_height.
			'line_height unitless'           => [ 'line_height', '1.5', '1.5' ],
			'line_height length'             => [ 'line_height', '24px', '24px' ],
			'line_height junk'               => [ 'line_height', '1.5; x', '' ],
			'line_height negative'           => [ 'line_height', '-1', '' ],
			'line_height number'             => [ 'line_height', 1.5, '' ],

			// duotone.
			'duotone valid'                  => [ 'duotone', [ '#000000', '#ffffff' ], [ '#000000', '#ffffff' ] ],
			'duotone preset colors'          => [ 'duotone', [ 'var:preset|color|a', 'rgb(1,2,3)' ], [ 'var:preset|color|a', 'rgb(1,2,3)' ] ],
			'duotone bad color'              => [ 'duotone', [ '#000', 'red;x' ], [] ],
			'duotone one color'              => [ 'duotone', [ '#000' ], [] ],
			'duotone wrong shape'            => [ 'duotone', 'unset', [] ],
			'duotone map'                    => [ 'duotone', [ 'a' => '#000' ], [] ],

			// image_size.
			'image_size full'                => [ 'image_size', 'full', 'full' ],
			'image_size registered'          => [ 'image_size', 'thumbnail', 'thumbnail' ],
			'image_size unknown'             => [ 'image_size', 'huge-nope', '' ],
			'image_size from config'         => [
				'image_size',
				'banner',
				'banner',
				[
					'sizes' => [
						'banner' => 'Banner',
					],
				],
			],
			'image_size not in config'       => [
				'image_size',
				'thumbnail',
				'',
				[
					'sizes' => [
						'banner' => 'Banner',
					],
				],
			],
			'image_size xss'                 => [ 'image_size', $xss, '' ],

			// key_value (a map of strings).
			'key_value valid'                => [
				'key_value',
				[
					'data-a' => 'one',
					'b'      => 'two',
				],
				[
					'data-a' => 'one',
					'b'      => 'two',
				],
			],
			'key_value hostile'              => [
				'key_value',
				[
					'<b>k</b>' => $xss . 'v',
					''         => 'dropped',
					'nested'   => [ 'x' ],
					'num'      => 5,
				],
				[
					'k'   => 'v',
					'num' => '5',
				],
			],
			'key_value wrong shape'          => [ 'key_value', 'a=b', [] ],

			// options_list.
			'options_list valid'             => [
				'options_list',
				[
					[
						'value' => 'a',
						'label' => 'A',
					],
				],
				[
					[
						'value' => 'a',
						'label' => 'A',
					],
				],
			],
			'options_list hostile'           => [
				'options_list',
				[
					[
						'value' => $xss . 'a',
						'label' => '<b>B</b>',
						'extra' => 'x',
					],
					'not a row',
					[ 'value' => [ 'x' ] ],
				],
				[
					[
						'value' => 'a',
						'label' => 'B',
					],
					[ 'value' => '' ],
				],
			],
			'options_list wrong shape'       => [ 'options_list', 'a', [] ],

			// media_gallery.
			'media_gallery valid'            => [ 'media_gallery', [ 3, '4', 5 ], [ 3, 4, 5 ] ],
			'media_gallery drops junk items' => [ 'media_gallery', [ 3, 'x', -1, 0, [ 1 ], '7abc', 9 ], [ 3, 9 ] ],
			'media_gallery wrong shape'      => [ 'media_gallery', 5, [] ],

			// Entity pickers: integers.
			'entity_select int'              => [ 'entity_select', 12, 12 ],
			'post_select numeric string'     => [ 'post_select', '34', 34 ],
			'term_select negative'           => [ 'term_select', -5, 0 ],
			'user_select junk'               => [ 'user_select', '12abc', 0 ],
			'user_select array'              => [ 'user_select', [ 1 ], 0 ],
			'user_select float'              => [ 'user_select', 1.5, 0 ],
			'entity_select junk default'     => [ 'entity_select', 'x', 8, [ 'default' => 8 ] ],
			'entity_select null'             => [ 'entity_select', null, 0 ],
			'post_select list when array'    => [ 'post_select', [ 4, '5', 'x', 0 ], [ 4, 5 ], [ 'value_type' => 'array' ] ],

			// taxonomy_select.
			'taxonomy_select valid'          => [ 'taxonomy_select', 'post_tag', 'post_tag' ],
			'taxonomy_select empty'          => [ 'taxonomy_select', '', '' ],
			'taxonomy_select xss'            => [ 'taxonomy_select', $xss, '' ],
			'taxonomy_select uppercase'      => [ 'taxonomy_select', 'Category', '' ],

			// toolbar_align.
			'toolbar_align valid'            => [ 'toolbar_align', 'justify', 'justify' ],
			'toolbar_align junk'             => [ 'toolbar_align', 'diagonal', '' ],
			'toolbar_align xss'              => [ 'toolbar_align', $xss, '' ],

			// sibling_field.
			'sibling_field valid'            => [ 'sibling_field', 'email_2', 'email_2' ],
			'sibling_field xss'              => [ 'sibling_field', $xss, '' ],
			'sibling_field wrong shape'      => [ 'sibling_field', [ 'a' ], '' ],

			// conditions.
			'conditions valid'               => [
				'conditions',
				[
					'relation' => 'OR',
					'rules'    => [
						[
							'field'    => 'kind',
							'operator' => 'in',
							'value'    => [ 'a', 'b' ],
						],
						[
							'source'   => 'block',
							'ref'      => 'email',
							'operator' => 'not_empty',
						],
					],
				],
				[
					'relation' => 'OR',
					'rules'    => [
						[
							'field'    => 'kind',
							'operator' => 'in',
							'value'    => [ 'a', 'b' ],
						],
						[
							'ref'      => 'email',
							'source'   => 'block',
							'operator' => 'not_empty',
						],
					],
				],
			],
			'conditions hostile'             => [
				'conditions',
				[
					'relation' => 'XOR',
					'rules'    => [
						[
							'field'    => $xss . 'kind',
							'operator' => 'DROP TABLE',
							'value'    => '<b>v</b>',
							'extra'    => 'x',
						],
						'not a rule',
					],
				],
				[
					'relation' => 'AND',
					'rules'    => [
						[
							'field'    => 'kind',
							'operator' => '==',
							'value'    => 'v',
						],
					],
				],
			],
			'conditions wrong shape'         => [ 'conditions', 'a', [] ],

			// query_builder.
			'query_builder valid'            => [
				'query_builder',
				[
					'relation' => 'AND',
					'rules'    => [
						[
							'key'     => 'price',
							'compare' => '>=',
							'value'   => '10',
						],
					],
				],
				[
					'relation' => 'AND',
					'rules'    => [
						[
							'key'     => 'price',
							'compare' => '>=',
							'value'   => '10',
						],
					],
				],
			],
			'query_builder hostile'          => [
				'query_builder',
				[
					'relation' => 'or',
					'rules'    => [
						[
							'key'     => '<i>k</i>',
							'compare' => '; DROP',
							'value'   => $xss,
						],
					],
				],
				[
					'relation' => 'AND',
					'rules'    => [
						[
							'key'     => 'k',
							'compare' => '=',
							'value'   => '',
						],
					],
				],
			],
			'query_builder wrong shape'      => [ 'query_builder', 'a', [] ],
		];
	}

	/**
	 * Unknown parts of a valid shape are dropped, not passed on.
	 */
	public function test_unknown_keys_are_dropped(): void {
		$link = $this->clean(
			'link',
			[
				'url'  => 'https://example.com',
				'rel'  => 'x',
				'<b>'  => 'y',
				'data' => [ 'z' ],
			]
		);

		$this->assertSame( [ 'url' => 'https://example.com' ], $link );
	}

	/**
	 * A custom control registered without a cmf_type stays untouched.
	 */
	public function test_custom_control_without_a_sanitizer_is_left_alone(): void {
		Control_Catalog::register_type( 'acme_custom', [ 'value_type' => 'string' ] );

		$this->assertSame( '<b>x</b>', $this->clean( 'acme_custom', '<b>x</b>' ) );
	}

	/**
	 * Through the whole render pipeline: hostile values never reach the render callback.
	 */
	public function test_pipeline_cleans_hostile_values(): void {
		CassetteCMFBlocks::register_from_array(
			[
				'blocks' => [
					[
						'id'     => 'acme-sanitizers/pipeline',
						'args'   => [ 'title' => 'Pipeline' ],
						'fields' => [
							[
								'name' => 'body',
								'type' => 'rich_text',
							],
							[
								'name' => 'cta',
								'type' => 'link',
							],
							[
								'name' => 'pad',
								'type' => 'box',
							],
						],
					],
				],
			]
		);

		$result = CassetteCMFBlocks::get_definition( 'acme-sanitizers/pipeline' )->sanitize_attributes(
			[
				'body' => '<p>ok</p><script>alert(1)</script>',
				'cta'  => [
					'url'   => 'javascript:alert(1)',
					'title' => '<img src=x onerror=alert(1)>',
				],
				'pad'  => [
					'top'  => 'url(//evil)',
					'left' => '4px',
				],
			]
		);

		$this->assertSame( '<p>ok</p>alert(1)', $result['body'] );
		$this->assertSame(
			[
				'url'   => '',
				'title' => '',
			],
			$result['cta']
		);
		$this->assertSame(
			[
				'top'  => '',
				'left' => '4px',
			],
			$result['pad']
		);
	}
}
