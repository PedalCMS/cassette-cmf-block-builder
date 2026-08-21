<?php
/**
 * Simple array registration example.
 *
 * Demonstrates CassetteCmfBlocks::register_from_array() registering a real
 * block with a working inspector panel, toolbar toggle, and a declarative
 * "render.markup" tree — the same tree drives the front-end HTML (via
 * Render\Block_Renderer/Markup_Renderer) and the editor's instant canvas
 * preview (via MarkupPreview.js), with no consumer JS required for either.
 * See docs/markup-templates.md.
 *
 * The "dismiss_label" field's "conditional" (only shown once "Dismissible"
 * is toggled on) and the Dismiss button's own "when" both take effect live
 * in the inspector and the canvas preview as you edit — not just on the
 * front end. See docs/control-catalog.md's conditionals section.
 *
 * @package Pedalcms\CassetteCmfBlocks\Examples
 */

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

use Pedalcms\CassetteCmfBlocks\CassetteCmfBlocks;

/**
 * Register a single block's configuration.
 */
function cassette_cmf_blocks_simple_array_init() {
	CassetteCmfBlocks::register_from_array(
		[
			'blocks' => [
				[
					'id'     => 'acme/callout',
					'args'   => [
						'title'    => __( 'Callout', 'acme' ),
						'category' => 'common',
						'icon'     => 'megaphone',
					],
					'fields' => [
						[
							'name'    => 'is_featured',
							'type'    => 'toolbar_toggle',
							'area'    => 'toolbar',
							'label'   => __( 'Featured', 'acme' ),
							'icon'    => 'star-filled',
							'default' => false,
						],
						[
							'name'         => 'settings',
							'type'         => 'panel',
							'title'        => __( 'Callout settings', 'acme' ),
							'initial_open' => true,
							'fields'       => [
								[
									'name'     => 'heading',
									'type'     => 'text',
									'label'    => __( 'Heading', 'acme' ),
									'required' => true,
								],
								[
									'name'    => 'is_dismissible',
									'type'    => 'toggle',
									'label'   => __( 'Dismissible', 'acme' ),
									'default' => false,
								],
								[
									'name'        => 'dismiss_label',
									'type'        => 'text',
									'label'       => __( 'Dismiss button label', 'acme' ),
									'default'     => __( 'Dismiss', 'acme' ),
									'conditional' => [
										'field'    => 'is_dismissible',
										'operator' => 'equals',
										'value'    => true,
									],
								],
							],
						],
					],
					'render' => [
						'markup' => [
							'tag'      => 'div',
							'class'    => 'acme-callout',
							'children' => [
								[
									'tag'  => 'strong',
									'text' => '{{ attributes.heading | default:Callout }}',
								],
								[
									'tag'   => 'button',
									'attrs' => [ 'type' => 'button' ],
									'text'  => '{{ attributes.dismiss_label | default:Dismiss }}',
									'when'  => [
										'rules' => [
											[
												'field'    => 'is_dismissible',
												'operator' => '==',
												'value'    => true,
											],
										],
									],
								],
							],
						],
					],
				],
			],
		]
	);
}
add_action( 'init', 'cassette_cmf_blocks_simple_array_init', 5 );
