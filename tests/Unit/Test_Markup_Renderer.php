<?php
/**
 * Markup_Renderer test.
 *
 * @package Pedalcms\CassetteCmfBlocks\Tests\Unit
 */

use Pedalcms\CassetteCmfBlocks\Render\Markup_Renderer;

require_once __DIR__ . '/CassetteCmfBlocks_UnitTestCase.php';

/**
 * Class Test_Markup_Renderer
 */
class Test_Markup_Renderer extends CassetteCmfBlocks_UnitTestCase {

	/**
	 * A basic element with interpolated class and escaped text.
	 */
	public function test_renders_tag_class_and_text(): void {
		$html = Markup_Renderer::render(
			[
				'tag'   => 'p',
				'class' => 'acme-callout acme-callout--{{ attributes.tone }}',
				'text'  => '{{ attributes.heading }}',
			],
			[
				'attributes' => [
					'tone'    => 'warm',
					'heading' => 'Hi',
				],
			],
			[],
			'',
			false
		);

		$this->assertSame( '<p class="acme-callout acme-callout--warm">Hi</p>', $html );
	}

	/**
	 * "text" is escaped with esc_html() by default — a script tag in an
	 * attribute value must never reach the output unescaped.
	 */
	public function test_text_is_html_escaped_by_default(): void {
		$html = Markup_Renderer::render(
			[
				'tag'  => 'p',
				'text' => '{{ attributes.heading }}',
			],
			[ 'attributes' => [ 'heading' => '<script>alert(1)</script>' ] ],
			[],
			'',
			false
		);

		$this->assertSame( '<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>', $html );
	}

	/**
	 * "html" is passed through wp_kses_post() by default — disallowed tags
	 * are stripped (their inner text content survives, matching kses's own
	 * behaviour: it strips markup, not text), allowed tags survive whole.
	 */
	public function test_html_is_kses_filtered_by_default(): void {
		$html = Markup_Renderer::render(
			[
				'tag'  => 'div',
				'html' => '{{ attributes.body }}',
			],
			[ 'attributes' => [ 'body' => '<p>ok</p><script>bad()</script>' ] ],
			[],
			'',
			false
		);

		$this->assertSame( '<div><p>ok</p>bad()</div>', $html );
		$this->assertStringNotContainsString( '<script>', $html );
	}

	/**
	 * An explicit "escape": "raw" node bypasses all escaping — documented
	 * consumer-owned trust, the one opt-out.
	 */
	public function test_escape_raw_bypasses_escaping(): void {
		$html = Markup_Renderer::render(
			[
				'tag'    => 'div',
				'html'   => '{{ attributes.body }}',
				'escape' => 'raw',
			],
			[ 'attributes' => [ 'body' => '<script>ok()</script>' ] ],
			[],
			'',
			false
		);

		$this->assertSame( '<div><script>ok()</script></div>', $html );
	}

	/**
	 * "attrs" values are esc_attr()'d; href gets esc_url() instead.
	 */
	public function test_attrs_are_escaped_and_href_uses_esc_url(): void {
		$html = Markup_Renderer::render(
			[
				'tag'   => 'a',
				'attrs' => [
					'href'      => '{{ attributes.url }}',
					'data-open' => '{{ attributes.is_open }}',
				],
				'text'  => 'Link',
			],
			[
				'attributes' => [
					'url'     => 'javascript:alert(1)',
					'is_open' => true,
				],
			],
			[],
			'',
			false
		);

		// javascript: URLs are stripped by esc_url().
		$this->assertStringNotContainsString( 'javascript:', $html );
		$this->assertStringContainsString( 'data-open="1"', $html );
	}

	/**
	 * Class tokens are sanitized individually via sanitize_html_class(),
	 * which strips characters outside [A-Za-z0-9_-] per token — it can't
	 * remove an arbitrary word like "onmouseover" (that's not its job), but
	 * it does guarantee no quote/equals/paren character survives to break
	 * out of the class="..." attribute context.
	 */
	public function test_class_tokens_are_sanitized(): void {
		$html = Markup_Renderer::render(
			[
				'tag'   => 'div',
				'class' => 'ok" onmouseover="alert(1)',
			],
			[ 'attributes' => [] ],
			[],
			'',
			false
		);

		$this->assertMatchesRegularExpression( '/^<div class="[A-Za-z0-9_ -]*"><\/div>$/', $html );
	}

	/**
	 * Void elements self-close and never get a closing tag or content.
	 */
	public function test_void_elements_self_close(): void {
		$html = Markup_Renderer::render(
			[
				'tag'   => 'img',
				'attrs' => [ 'src' => '{{ attributes.src }}' ],
				'text'  => 'ignored',
			],
			[ 'attributes' => [ 'src' => 'https://example.com/x.png' ] ],
			[],
			'',
			false
		);

		$this->assertSame( '<img src="https://example.com/x.png">', $html );
	}

	/**
	 * Children render recursively inside their parent.
	 */
	public function test_children_render_recursively(): void {
		$html = Markup_Renderer::render(
			[
				'tag'      => 'div',
				'children' => [
					[
						'tag'  => 'span',
						'text' => 'A',
					],
					[
						'tag'  => 'span',
						'text' => 'B',
					],
				],
			],
			[ 'attributes' => [] ],
			[],
			'',
			false
		);

		$this->assertSame( '<div><span>A</span><span>B</span></div>', $html );
	}

	/**
	 * slot: inner_blocks inserts the block's own already-rendered content
	 * verbatim (never escaped — it's WP-trusted rendered block markup).
	 */
	public function test_slot_inner_blocks_inserts_content_verbatim(): void {
		$html = Markup_Renderer::render(
			[
				'tag'  => 'div',
				'slot' => 'inner_blocks',
			],
			[ 'attributes' => [] ],
			[],
			'<p>child block</p>',
			false
		);

		$this->assertSame( '<div><p>child block</p></div>', $html );
	}

	/**
	 * repeat renders the node once per array item, with the scope extended
	 * by "{as}".
	 */
	public function test_repeat_renders_once_per_item(): void {
		$html = Markup_Renderer::render(
			[
				'tag'    => 'li',
				'text'   => '{{ item.label }}',
				'repeat' => [
					'over' => 'attributes.items',
					'as'   => 'item',
				],
			],
			[ 'attributes' => [ 'items' => [ [ 'label' => 'One' ], [ 'label' => 'Two' ] ] ] ],
			[],
			'',
			false
		);

		$this->assertSame( '<li>One</li><li>Two</li>', $html );
	}

	/**
	 * repeat over a non-array value renders nothing rather than fataling.
	 */
	public function test_repeat_over_non_array_renders_nothing(): void {
		$html = Markup_Renderer::render(
			[
				'tag'    => 'li',
				'repeat' => [ 'over' => 'attributes.items' ],
			],
			[ 'attributes' => [ 'items' => 'not-an-array' ] ],
			[],
			'',
			false
		);

		$this->assertSame( '', $html );
	}

	/**
	 * A node whose "when" condition fails against the condition context
	 * renders nothing.
	 */
	public function test_when_false_renders_nothing(): void {
		$html = Markup_Renderer::render(
			[
				'tag'  => 'div',
				'text' => 'shown',
				'when' => [
					'rules' => [
						[
							'field'    => 'is_open',
							'operator' => '==',
							'value'    => true,
						],
					],
				],
			],
			[ 'attributes' => [] ],
			[ 'is_open' => false ],
			'',
			false
		);

		$this->assertSame( '', $html );
	}

	/**
	 * A node whose "when" condition passes renders normally.
	 */
	public function test_when_true_renders_normally(): void {
		$html = Markup_Renderer::render(
			[
				'tag'  => 'div',
				'text' => 'shown',
				'when' => [
					'rules' => [
						[
							'field'    => 'is_open',
							'operator' => '==',
							'value'    => true,
						],
					],
				],
			],
			[ 'attributes' => [] ],
			[ 'is_open' => true ],
			'',
			false
		);

		$this->assertSame( '<div>shown</div>', $html );
	}

	/**
	 * The root node's class/attrs merge through get_block_wrapper_attributes()
	 * when $wrap_root is true (the default) — its own escaping handles
	 * output safety, so the result is still safe even for a hostile class value.
	 */
	public function test_root_wraps_via_block_wrapper_attributes(): void {
		$html = Markup_Renderer::render(
			[
				'tag'   => 'div',
				'class' => 'acme-callout',
				'attrs' => [ 'data-open' => '{{ attributes.is_open }}' ],
				'text'  => 'Hi',
			],
			[ 'attributes' => [ 'is_open' => true ] ],
			[],
			'',
			true
		);

		$this->assertStringContainsString( 'class="acme-callout"', $html );
		$this->assertStringContainsString( 'data-open="1"', $html );
		$this->assertStringContainsString( '>Hi</div>', $html );
	}
}
