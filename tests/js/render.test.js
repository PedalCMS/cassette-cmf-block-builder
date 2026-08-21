/**
 * markup/render.js test.
 *
 * Asserts against the React element tree renderNode() returns directly
 * (createElement() returns a plain { type, props } object) rather than a
 * rendered DOM/string — see markup/expr.js's docblock for why this file
 * doesn't try to byte-compare against Render\Markup_Renderer's (PHP) HTML
 * string output.
 */

import { readFileSync } from 'fs';
import { join } from 'path';
import { renderNode } from '../../src/assets/src/editor/markup/render';

describe( 'renderNode', () => {
	it( 'renders a tag with an interpolated className and text child', () => {
		const element = renderNode(
			{
				tag: 'p',
				class: 'acme-callout acme-callout--{{ attributes.tone }}',
				text: '{{ attributes.heading }}',
			},
			{ attributes: { tone: 'warm', heading: 'Hi' } },
			'',
			0
		);

		expect( element.type ).toBe( 'p' );
		expect( element.props.className ).toBe( 'acme-callout acme-callout--warm' );
		expect( element.props.children ).toBe( 'Hi' );
	} );

	it( 'passes interpolated attrs through as props', () => {
		const element = renderNode(
			{
				tag: 'div',
				attrs: { 'data-open': '{{ attributes.is_open }}' },
			},
			{ attributes: { is_open: true } },
			'',
			0
		);

		expect( element.props[ 'data-open' ] ).toBe( '1' );
	} );

	it( 'renders void elements with no children', () => {
		const element = renderNode(
			{ tag: 'img', attrs: { src: '{{ attributes.src }}' } },
			{ attributes: { src: 'https://example.com/x.png' } },
			'',
			0
		);

		expect( element.type ).toBe( 'img' );
		expect( element.props.src ).toBe( 'https://example.com/x.png' );
		expect( element.props.children ).toBeUndefined();
	} );

	it( 'renders children recursively', () => {
		const element = renderNode(
			{
				tag: 'div',
				children: [
					{ tag: 'span', text: 'A' },
					{ tag: 'span', text: 'B' },
				],
			},
			{ attributes: {} },
			'',
			0
		);

		expect( element.props.children ).toHaveLength( 2 );
		expect( element.props.children[ 0 ].type ).toBe( 'span' );
		expect( element.props.children[ 0 ].props.children ).toBe( 'A' );
		expect( element.props.children[ 1 ].props.children ).toBe( 'B' );
	} );

	it( 'renders a "repeat" node once per item, with the scope extended by "as"', () => {
		const elements = renderNode(
			{
				tag: 'li',
				text: '{{ item.label }}',
				repeat: { over: 'attributes.items', as: 'item' },
			},
			{ attributes: { items: [ { label: 'One' }, { label: 'Two' } ] } },
			'',
			'list'
		);

		expect( elements ).toHaveLength( 2 );
		expect( elements[ 0 ].props.children ).toBe( 'One' );
		expect( elements[ 1 ].props.children ).toBe( 'Two' );
	} );

	it( 'renders nothing for a "repeat" over a non-array value', () => {
		const result = renderNode(
			{ tag: 'li', repeat: { over: 'attributes.items' } },
			{ attributes: { items: 'not-an-array' } },
			'',
			0
		);

		expect( result ).toBeNull();
	} );

	it( 'a "slot": "inner_blocks" node inserts the raw inner content', () => {
		const element = renderNode(
			{ tag: 'div', slot: 'inner_blocks' },
			{ attributes: {} },
			'<p>child block</p>',
			0
		);

		expect( element.props.children.props.children ).toBe( '<p>child block</p>' );
	} );

	it( 'a node whose "when" fails renders nothing', () => {
		const result = renderNode(
			{
				tag: 'button',
				text: 'Dismiss',
				when: {
					relation: 'AND',
					rules: [
						{ field: 'is_dismissible', operator: '==', value: true },
					],
				},
			},
			{ attributes: { is_dismissible: false } },
			'',
			0
		);

		expect( result ).toBeNull();
	} );

	it( 'a node whose "when" passes renders normally', () => {
		const element = renderNode(
			{
				tag: 'button',
				text: 'Dismiss',
				when: {
					relation: 'AND',
					rules: [
						{ field: 'is_dismissible', operator: '==', value: true },
					],
				},
			},
			{ attributes: { is_dismissible: true } },
			'',
			0
		);

		expect( element.props.children ).toBe( 'Dismiss' );
	} );

	it( 'a "when"-gated child inside "children" is individually hidden', () => {
		const element = renderNode(
			{
				tag: 'div',
				children: [
					{ tag: 'span', text: 'always' },
					{
						tag: 'span',
						text: 'conditional',
						when: {
							relation: 'AND',
							rules: [ { field: 'flag', operator: '==', value: true } ],
						},
					},
				],
			},
			{ attributes: { flag: false } },
			'',
			0
		);

		expect( element.props.children ).toHaveLength( 2 );
		expect( element.props.children[ 0 ].props.children ).toBe( 'always' );
		expect( element.props.children[ 1 ] ).toBeNull();
	} );
} );

/**
 * Flatten a React element (as returned by renderNode()) into the shared
 * { tag, className, attrs, text, children } plain-object shape
 * Test_Markup_Fixtures.php's flatten_element() (PHP) also produces from its
 * own renderer's HTML string output — the two are compared against the
 * same shape, never against each other's raw representation.
 *
 * @param {*} element A React element, or a plain string/number child.
 * @return {Object} The flattened structure.
 */
function flattenElement( element ) {
	const children = Array.isArray( element.props.children )
		? element.props.children
		: [ element.props.children ].filter( ( child ) => undefined !== child );

	const elementChildren = children.filter(
		( child ) => child && 'object' === typeof child && child.type
	);
	const textChildren = children.filter(
		( child ) => 'string' === typeof child || 'number' === typeof child
	);

	const { className, children: _children, ...attrs } = element.props;

	return {
		tag: element.type,
		className: className || null,
		attrs,
		text: elementChildren.length ? null : textChildren.join( '' ) || null,
		children: elementChildren.map( flattenElement ),
	};
}

describe( 'renderNode fixture parity (markup.json "nodes")', () => {
	const fixtures = JSON.parse(
		readFileSync( join( __dirname, '../fixtures/markup.json' ), 'utf8' )
	);

	it.each( fixtures.nodes.map( ( testCase ) => [ testCase.description, testCase ] ) )(
		'%s',
		( description, testCase ) => {
			const result = renderNode( testCase.node, testCase.scope, '', 0 );
			const roots = Array.isArray( result ) ? result : [ result ];

			expect( roots.map( flattenElement ) ).toEqual( testCase.expected );
		}
	);
} );
