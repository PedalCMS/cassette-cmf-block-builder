<?php
/**
 * Plugin Name: Cassette-CMF Blocks E2E Fixture
 * Description: Registers a demo block set from a PHP config array only — no JS file exists in this plugin. Its absence is the E2E proof that consumers write zero JavaScript.
 * Version: 0.0.0
 * License: GPL-2.0-or-later
 *
 * @package PedalCMS\CassetteCMFBlocks\Tests\E2E\FixturePlugin
 */

require_once __DIR__ . '/vendor/autoload.php';

use PedalCMS\CassetteCMFBlocks\CassetteCMFBlocks;

add_action(
	'init',
	static function () {
		CassetteCMFBlocks::boot( [ 'prefix' => 'example' ] );
		CassetteCMFBlocks::register_from_json( __DIR__ . '/config/blocks.json' );

		// Blocks that need PHP callables or are easier to write as arrays.
		CassetteCMFBlocks::register_from_array(
			[
				'blocks' => [
					[
						'id'         => 'acme-e2e/card',
						'args'       => [
							'title' => 'E2E Card',
							'icon'  => 'id',
						],
						'fields'     => [
							[
								'name'  => 'title',
								'type'  => 'text',
								'label' => 'Title',
							],
							[
								'name'  => 'ref',
								'type'  => 'text',
								'label' => 'Reference',
							],
						],
						'transforms' => [
							'from' => [
								[
									'type'     => 'shortcode',
									'tag'      => 'e2e-card',
									'callback' => 'acme_e2e_card_from_shortcode',
								],
								[
									'type'     => 'raw',
									'selector' => 'div.e2e-legacy',
									'callback' => 'acme_e2e_card_from_html',
								],
							],
						],
						'render'     => [
							'mode'   => 'dynamic',
							'markup' => [
								'tag'      => 'div',
								'class'    => 'acme-e2e-card',
								'children' => [
									[
										'tag'  => 'span',
										'text' => '{{ attributes.title }} ({{ attributes.ref }})',
									],
								],
							],
						],
					],
					[
						'id'      => 'acme-e2e/gated',
						'args'    => [
							'title' => 'E2E Gated Preview',
							'icon'  => 'visibility',
						],
						'fields'  => [
							[
								'name'  => 'url',
								'type'  => 'url',
								'label' => 'Address',
							],
						],
						'preview' => [
							'when' => [
								'relation' => 'AND',
								'rules'    => [
									[
										'field'    => 'url',
										'operator' => 'not_empty',
									],
								],
							],
						],
						'render'  => [
							'mode'   => 'dynamic',
							'markup' => [
								'tag'      => 'p',
								'class'    => 'acme-e2e-gated',
								'children' => [
									[
										'tag'  => 'span',
										'text' => '{{ attributes.url }}',
									],
								],
							],
						],
					],
					[
						'id'     => 'acme-e2e/rows',
						'args'   => [
							'title' => 'E2E Rows',
							'icon'  => 'list-view',
						],
						'fields' => [
							[
								'name'   => 'items',
								'type'   => 'repeater',
								'label'  => 'Rows',
								'fields' => [
									[
										'name'  => 'title',
										'type'  => 'text',
										'label' => 'Title',
									],
								],
							],
						],
						'render' => [
							'mode'   => 'dynamic',
							'markup' => [
								'tag'   => 'div',
								'class' => 'acme-e2e-rows',
							],
						],
					],
				],
			]
		);
	},
	5
);

/**
 * Shortcode callback: [e2e-card id="7" title="Hello"] -> a card block.
 *
 * @param array<string|int, string> $atts    Shortcode attributes.
 * @param array<string, mixed>      $context Transform context.
 * @return array<string, mixed>|null
 */
function acme_e2e_card_from_shortcode( array $atts, array $context ): ?array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	if ( empty( $atts['id'] ) ) {
		return null;
	}

	return [
		'attributes' => [
			'title' => $atts['title'] ?? 'Untitled',
			'ref'   => $atts['id'],
		],
	];
}

/**
 * Raw callback: <div class="e2e-legacy">Title #7</div> -> a card block.
 *
 * The reference travels in the text, not in a data attribute: core's paste
 * cleanup keeps only the attributes the library's schema lists (the class here),
 * so a "data-ref" attribute would never reach this callback.
 *
 * @param string               $html    Pasted HTML.
 * @param array<string, mixed> $context Transform context.
 * @return array<string, mixed>|null
 */
function acme_e2e_card_from_html( string $html, array $context ): ?array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	$text = trim( wp_strip_all_tags( $html ) );

	if ( ! preg_match( '/^(.*?)\s*#(\d+)$/', $text, $match ) ) {
		return null;
	}

	return [
		'attributes' => [
			'title' => $match[1],
			'ref'   => $match[2],
		],
	];
}
