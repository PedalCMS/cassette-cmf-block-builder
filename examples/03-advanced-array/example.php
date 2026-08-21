<?php
/**
 * Advanced array example — a small set of form-field blocks demonstrating
 * a canvas preview built from real (disabled) HTML form controls, so a
 * block reads as a native WordPress admin element in the editor rather
 * than plain unstyled text. Each block also demonstrates the label→slug
 * auto-derive behaviour ("derive_from"/"format: 'slug'" on the "slug"
 * field — see docs/control-catalog.md's "text" control section) and a
 * few different control kinds: a plain input, a textarea, a dropdown with
 * author-defined options ("options_list"), and a checkbox with its own
 * label/value pair.
 *
 * @package Pedalcms\CassetteCmfBlocks\Examples
 */

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

use Pedalcms\CassetteCmfBlocks\CassetteCmfBlocks;

/**
 * The label/slug field pair every field block shares.
 *
 * @param string $default_label Default value for the "label" field.
 * @param string $default_slug  Default value for the "slug" field.
 * @return array<int, array<string, mixed>>
 */
function cassette_cmf_forms_common_fields( string $default_label, string $default_slug ): array {
	return [
		[
			'name'    => 'label',
			'type'    => 'text',
			'label'   => __( 'Label', 'acme' ),
			'default' => $default_label,
		],
		[
			'name'        => 'slug',
			'type'        => 'text',
			'label'       => __( 'Field name (slug)', 'acme' ),
			'description' => __( 'Used as the entry column name.', 'acme' ),
			'default'     => $default_slug,
			'derive_from' => 'label',
			'format'      => 'slug',
		],
	];
}

/**
 * The help-text + required pair every field block shares, always the last
 * two controls in the panel.
 *
 * @return array<int, array<string, mixed>>
 */
function cassette_cmf_forms_help_and_required_fields(): array {
	return [
		[
			'name'  => 'help_text',
			'type'  => 'textarea',
			'label' => __( 'Help text', 'acme' ),
		],
		[
			'name'    => 'required',
			'type'    => 'toggle',
			'label'   => __( 'Required', 'acme' ),
			'default' => false,
		],
	];
}

/**
 * The bordered "field preview" card every field block's canvas renders —
 * a label row (label text, a "*" when required, a type badge) plus
 * whichever control node(s) the caller supplies, plus a help-text
 * paragraph shown only once help text is filled in. Real, disabled HTML
 * form controls (not plain text) so the canvas preview inherits WP
 * admin's own forms.css the same way a real field would look — see
 * src/assets/src/editor/chrome.scss for the card/label-row/badge chrome.
 *
 * @param string                $type_label   Badge text, e.g. "Text".
 * @param array<string, mixed>  $control_node The type-specific control markup node.
 * @return array<string, mixed>
 */
function cassette_cmf_forms_field_preview_markup( string $type_label, array $control_node ): array {
	return [
		'tag'      => 'div',
		'class'    => 'cassette-cmf-block-builder-field-preview',
		'children' => [
			[
				'tag'      => 'div',
				'class'    => 'cassette-cmf-block-builder-field-preview__label-row',
				'children' => [
					[
						'tag'  => 'strong',
						'text' => '{{ attributes.label }}',
					],
					[
						'tag'   => 'span',
						'class' => 'cassette-cmf-block-builder-field-preview__required',
						'text'  => '*',
						'when'  => [
							'rules' => [
								[
									'field'    => 'required',
									'operator' => '==',
									'value'    => true,
								],
							],
						],
					],
					[
						'tag'   => 'span',
						'class' => 'cassette-cmf-block-builder-field-preview__badge',
						'text'  => $type_label,
					],
				],
			],
			$control_node,
			[
				'tag'   => 'p',
				'class' => 'cassette-cmf-block-builder-field-preview__help',
				'text'  => '{{ attributes.help_text }}',
				'when'  => [
					'rules' => [
						[
							'field'    => 'help_text',
							'operator' => 'not_empty',
						],
					],
				],
			],
		],
	];
}

/**
 * Register the field blocks.
 */
function cassette_cmf_forms_init() {
	CassetteCmfBlocks::register_from_array(
		[
			'block_categories' => [
				[
					'slug'  => 'cassette-cmf-forms',
					'title' => __( 'Form fields', 'acme' ),
					'icon'  => 'feedback',
				],
			],
			'blocks'           => [

				/**
				 * A plain text input.
				 */
				[
					'id'     => 'cassette-cmf-forms/field-text',
					'args'   => [
						'title'    => __( 'Text', 'acme' ),
						'category' => 'cassette-cmf-forms',
						'icon'     => 'editor-textcolor',
					],
					'fields' => [
						[
							'name'         => 'settings',
							'type'         => 'panel',
							'title'        => __( 'Text Field', 'acme' ),
							'initial_open' => true,
							'fields'       => array_merge(
								cassette_cmf_forms_common_fields( __( 'Text field', 'acme' ), 'text_field' ),
								[
									[
										'name'  => 'placeholder',
										'type'  => 'text',
										'label' => __( 'Placeholder', 'acme' ),
									],
								],
								cassette_cmf_forms_help_and_required_fields()
							),
						],
					],
					'render' => [
						'markup' => cassette_cmf_forms_field_preview_markup(
							__( 'Text', 'acme' ),
							[
								'tag'   => 'input',
								'class' => 'cassette-cmf-block-builder-field-preview__control',
								'attrs' => [
									'type'        => 'text',
									'disabled'    => 'disabled',
									'placeholder' => '{{ attributes.placeholder }}',
								],
							]
						),
					],
				],

				/**
				 * A multi-line textarea.
				 */
				[
					'id'     => 'cassette-cmf-forms/field-textarea',
					'args'   => [
						'title'    => __( 'Textarea', 'acme' ),
						'category' => 'cassette-cmf-forms',
						'icon'     => 'editor-alignleft',
					],
					'fields' => [
						[
							'name'         => 'settings',
							'type'         => 'panel',
							'title'        => __( 'Textarea Field', 'acme' ),
							'initial_open' => true,
							'fields'       => array_merge(
								cassette_cmf_forms_common_fields( __( 'Textarea field', 'acme' ), 'textarea_field' ),
								[
									[
										'name'  => 'placeholder',
										'type'  => 'text',
										'label' => __( 'Placeholder', 'acme' ),
									],
								],
								cassette_cmf_forms_help_and_required_fields()
							),
						],
					],
					'render' => [
						'markup' => cassette_cmf_forms_field_preview_markup(
							__( 'Textarea', 'acme' ),
							[
								'tag'   => 'textarea',
								'class' => 'cassette-cmf-block-builder-field-preview__control',
								'attrs' => [
									'disabled'    => 'disabled',
									'placeholder' => '{{ attributes.placeholder }}',
									'rows'        => '3',
								],
							]
						),
					],
				],

				/**
				 * An email input.
				 */
				[
					'id'     => 'cassette-cmf-forms/field-email',
					'args'   => [
						'title'    => __( 'Email', 'acme' ),
						'category' => 'cassette-cmf-forms',
						'icon'     => 'email',
					],
					'fields' => [
						[
							'name'         => 'settings',
							'type'         => 'panel',
							'title'        => __( 'Email Field', 'acme' ),
							'initial_open' => true,
							'fields'       => array_merge(
								cassette_cmf_forms_common_fields( __( 'Email field', 'acme' ), 'email_field' ),
								[
									[
										'name'  => 'placeholder',
										'type'  => 'text',
										'label' => __( 'Placeholder', 'acme' ),
									],
								],
								cassette_cmf_forms_help_and_required_fields()
							),
						],
					],
					'render' => [
						'markup' => cassette_cmf_forms_field_preview_markup(
							__( 'Email', 'acme' ),
							[
								'tag'   => 'input',
								'class' => 'cassette-cmf-block-builder-field-preview__control',
								'attrs' => [
									'type'        => 'email',
									'disabled'    => 'disabled',
									'placeholder' => '{{ attributes.placeholder }}',
								],
							]
						),
					],
				],

				/**
				 * A dropdown of author-defined options ("options_list" —
				 * structured, repeatable value/label rows, rather than a
				 * hand-parsed "Label|value" text blob).
				 */
				[
					'id'     => 'cassette-cmf-forms/field-select',
					'args'   => [
						'title'    => __( 'Select', 'acme' ),
						'category' => 'cassette-cmf-forms',
						'icon'     => 'menu',
					],
					'fields' => [
						[
							'name'         => 'settings',
							'type'         => 'panel',
							'title'        => __( 'Select Field', 'acme' ),
							'initial_open' => true,
							'fields'       => array_merge(
								cassette_cmf_forms_common_fields( __( 'Select field', 'acme' ), 'select_field' ),
								[
									[
										'name'    => 'options',
										'type'    => 'options_list',
										'label'   => __( 'Options', 'acme' ),
										'default' => [
											[
												'value' => 'option_1',
												'label' => __( 'Option 1', 'acme' ),
											],
											[
												'value' => 'option_2',
												'label' => __( 'Option 2', 'acme' ),
											],
										],
									],
								],
								cassette_cmf_forms_help_and_required_fields()
							),
						],
					],
					'render' => [
						'markup' => cassette_cmf_forms_field_preview_markup(
							__( 'Select', 'acme' ),
							[
								'tag'      => 'select',
								'class'    => 'cassette-cmf-block-builder-field-preview__control',
								'attrs'    => [ 'disabled' => 'disabled' ],
								'children' => [
									[
										'tag'    => 'option',
										'text'   => '{{ opt.label }}',
										'repeat' => [
											'over' => 'attributes.options',
											'as'   => 'opt',
										],
									],
								],
							]
						),
					],
				],

				/**
				 * A single checkbox with its own label and checked-value
				 * text, distinct from the block's own "label" (shown in
				 * the label row like every other field).
				 */
				[
					'id'     => 'cassette-cmf-forms/field-checkbox',
					'args'   => [
						'title'    => __( 'Checkbox', 'acme' ),
						'category' => 'cassette-cmf-forms',
						'icon'     => 'yes-alt',
					],
					'fields' => [
						[
							'name'         => 'settings',
							'type'         => 'panel',
							'title'        => __( 'Checkbox Field', 'acme' ),
							'initial_open' => true,
							'fields'       => array_merge(
								cassette_cmf_forms_common_fields( __( 'Checkbox field', 'acme' ), 'checkbox_field' ),
								[
									[
										'name'    => 'checkbox_label',
										'type'    => 'text',
										'label'   => __( 'Checkbox label', 'acme' ),
										'default' => __( 'I agree to the terms.', 'acme' ),
									],
									[
										'name'    => 'checked_value',
										'type'    => 'text',
										'label'   => __( 'Checked value', 'acme' ),
										'default' => 'yes',
									],
								],
								cassette_cmf_forms_help_and_required_fields()
							),
						],
					],
					'render' => [
						'markup' => cassette_cmf_forms_field_preview_markup(
							__( 'Checkbox', 'acme' ),
							[
								'tag'      => 'label',
								'class'    => 'cassette-cmf-block-builder-field-preview__checkbox-label',
								'children' => [
									[
										'tag'   => 'input',
										'attrs' => [
											'type'     => 'checkbox',
											'disabled' => 'disabled',
										],
									],
									[
										'tag'  => 'span',
										'text' => '{{ attributes.checkbox_label }}',
									],
								],
							]
						),
					],
				],
			],
		]
	);
}
add_action( 'init', 'cassette_cmf_forms_init', 5 );
