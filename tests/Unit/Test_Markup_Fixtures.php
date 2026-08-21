<?php
/**
 * Expression <-> markup/expr.js parity fixture test.
 *
 * Reads tests/fixtures/markup.json — the same file markup/expr.test.js
 * reads — and asserts Render\Expression::interpolate() against every case.
 * Each language's test suite asserts its own evaluator against the same
 * { template, scope, expected } cases, so a filter behaving differently
 * between the two languages shows up as a failure in both, rather than
 * drifting silently apart. See Render\Expression's class docblock and
 * markup/expr.js's for why this tests expression *values*, not full markup
 * output (an HTML string and a React element tree aren't directly comparable).
 *
 * @package Pedalcms\CassetteCmfBlocks\Tests\Unit
 */

use Pedalcms\CassetteCmfBlocks\Render\Expression;
use Pedalcms\CassetteCmfBlocks\Render\Markup_Renderer;

require_once __DIR__ . '/CassetteCmfBlocks_UnitTestCase.php';

/**
 * Class Test_Markup_Fixtures
 */
class Test_Markup_Fixtures extends CassetteCmfBlocks_UnitTestCase {

	/**
	 * Every case in tests/fixtures/markup.json must interpolate to its
	 * expected string.
	 */
	public function test_expression_fixtures(): void {
		$fixtures = self::load_fixtures();

		$this->assertNotEmpty( $fixtures['expressions'], 'markup.json must contain at least one expression case.' );

		foreach ( $fixtures['expressions'] as $case ) {
			$result = Expression::interpolate( $case['template'], $case['scope'] );

			$this->assertSame(
				$case['expected'],
				$result,
				sprintf( 'Fixture case "%s" (template: %s)', $case['description'], $case['template'] )
			);
		}
	}

	/**
	 * Every case in markup.json's "nodes" — the same cases render.test.js
	 * reads — must render to the expected *structure* (tag/className/attrs/
	 * text/children), not a byte-identical string: this is where PHP
	 * (Markup_Renderer, producing an HTML string) and JS (markup/render.js,
	 * producing a React element tree) are compared, and those are two
	 * different representations of the same thing, not directly diffable.
	 * Each language's own test flattens its own renderer's real output into
	 * this shared plain-object shape before comparing — see
	 * render.test.js's parity block for the JS side.
	 *
	 * Deliberately excludes "when" and "slot" cases: "when" isn't evaluated
	 * in the JS preview yet (a later milestone, see docs/control-catalog.md),
	 * and "slot" behaves too differently by design between an HTML string
	 * (verbatim insertion) and a React tree (a RawHTML-wrapped element) to
	 * usefully flatten into one shared shape — both are covered by each
	 * language's own dedicated (non-fixture) tests instead
	 * (Test_Markup_Renderer.php, render.test.js).
	 */
	public function test_node_fixtures(): void {
		$fixtures = self::load_fixtures();

		$this->assertNotEmpty( $fixtures['nodes'], 'markup.json must contain at least one node case.' );

		foreach ( $fixtures['nodes'] as $case ) {
			$html   = Markup_Renderer::render( $case['node'], $case['scope'], [], '', false );
			$actual = self::flatten_html( $html );

			$this->assertSame( $case['expected'], $actual, sprintf( 'Fixture case "%s"', $case['description'] ) );
		}
	}

	/**
	 * Decode tests/fixtures/markup.json.
	 *
	 * @return array<string, mixed>
	 */
	private static function load_fixtures(): array {
		$path = dirname( __DIR__ ) . '/fixtures/markup.json';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$fixtures = json_decode( (string) file_get_contents( $path ), true );

		self::assertIsArray( $fixtures, 'markup.json must decode to an array.' );

		return $fixtures;
	}

	/**
	 * Parse a rendered HTML string into an array of flattened top-level
	 * element structures — an array even for a single root, so a "repeat"
	 * case's multiple concatenated root elements compare uniformly with a
	 * single-root case's one-element array.
	 *
	 * @param string $html Rendered HTML (Markup_Renderer::render() output).
	 * @return array<int, array<string, mixed>>
	 */
	private static function flatten_html( string $html ): array {
		$doc = new \DOMDocument();

		$previous_setting = libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="utf-8" ?><cassette-cmf-block-builder-fixture-root>' . $html . '</cassette-cmf-block-builder-fixture-root>' );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous_setting );

		$root = $doc->getElementsByTagName( 'cassette-cmf-block-builder-fixture-root' )->item( 0 );

		$elements = [];
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- native DOMNode properties, not renamable.
		foreach ( $root->childNodes as $child ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- native DOMNode property, not renamable.
			if ( XML_ELEMENT_NODE === $child->nodeType ) {
				$elements[] = self::flatten_element( $child );
			}
		}

		return $elements;
	}

	/**
	 * Flatten one DOM element into the shared { tag, className, attrs, text, children } shape.
	 *
	 * @param \DOMElement $element Element to flatten.
	 * @return array<string, mixed>
	 */
	private static function flatten_element( \DOMElement $element ): array {
		$attrs = [];
		foreach ( $element->attributes as $attribute ) {
			if ( 'class' === $attribute->name ) {
				continue;
			}
			$attrs[ $attribute->name ] = $attribute->value;
		}

		$children = [];
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- native DOMNode properties, not renamable.
		foreach ( $element->childNodes as $child ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- native DOMNode property, not renamable.
			if ( XML_ELEMENT_NODE === $child->nodeType ) {
				$children[] = self::flatten_element( $child );
			}
		}

		$text = null;
		if ( empty( $children ) ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- native DOMNode property, not renamable.
			$text_content = trim( $element->textContent );
			$text         = '' === $text_content ? null : $text_content;
		}

		return [
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- native DOMElement property, not renamable.
			'tag'       => strtolower( $element->tagName ),
			'className' => $element->hasAttribute( 'class' ) ? $element->getAttribute( 'class' ) : null,
			'attrs'     => $attrs,
			'text'      => $text,
			'children'  => $children,
		];
	}
}
