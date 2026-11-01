/**
 * source: 'context' — useFieldValue(), the merged preview scope, the
 * conditions scope, and the control that renders nothing.
 */

import { useFieldValue } from '../../src/assets/src/editor/value/useFieldValue';
import { mergeContextValues } from '../../src/assets/src/editor/util/scope';
import { collectContextFields } from '../../src/assets/src/editor/util/collect-fields';
import MarkupPreview from '../../src/assets/src/editor/preview/MarkupPreview';
import { renderNode } from '../../src/assets/src/editor/node-renderer';
import { createEdit } from '../../src/assets/src/editor/block-edit';

const contextNode = ( name = 'formId', key = 'example/formId' ) => ( {
	type: 'text',
	isContainer: false,
	holdsValue: true,
	config: { name, source: 'context', context: key },
} );

describe( 'useFieldValue for source "context"', () => {
	it( 'reads ctx.context[ field.context ]', () => {
		const [ value ] = useFieldValue( contextNode(), {
			attributes: {},
			setAttributes: jest.fn(),
			context: { 'example/formId': 'form-1' },
		} );

		expect( value ).toBe( 'form-1' );
	} );

	it( 'is read-only: the setter writes nothing', () => {
		const setAttributes = jest.fn();
		const [ , setValue ] = useFieldValue( contextNode(), {
			attributes: {},
			setAttributes,
			context: { 'example/formId': 'form-1' },
		} );

		setValue( 'other' );
		expect( setAttributes ).not.toHaveBeenCalled();
	} );

	it( 'is undefined when the provider supplied nothing, or there is no context at all', () => {
		expect(
			useFieldValue( contextNode(), {
				attributes: {},
				setAttributes: jest.fn(),
				context: {},
			} )[ 0 ]
		).toBeUndefined();
		expect(
			useFieldValue( contextNode(), {
				attributes: {},
				setAttributes: jest.fn(),
			} )[ 0 ]
		).toBeUndefined();
	} );
} );

describe( 'collectContextFields / mergeContextValues', () => {
	const tree = [
		{
			isContainer: true,
			type: 'panel',
			config: { name: 'p' },
			fields: [
				contextNode( 'formId', 'example/formId' ),
				{
					isContainer: false,
					type: 'text',
					holdsValue: true,
					config: { name: 'label' },
				},
				{
					isContainer: true,
					type: 'repeater',
					config: { name: 'rows' },
					fields: [ contextNode( 'rowCtx', 'example/other' ) ],
				},
			],
		},
	];

	it( 'collects context fields at any container depth, but not a repeater row template', () => {
		expect( collectContextFields( tree ) ).toEqual( {
			formId: 'example/formId',
		} );
	} );

	it( 'merges context values into the attribute scope under the field name', () => {
		const merged = mergeContextValues(
			{ label: 'Name' },
			{ formId: 'example/formId' },
			{ 'example/formId': 'form-7' }
		);

		expect( merged ).toEqual( { label: 'Name', formId: 'form-7' } );
	} );

	it( 'returns the very same attributes object when there are no context fields', () => {
		const attributes = { label: 'Name' };

		expect( mergeContextValues( attributes, {}, { a: 1 } ) ).toBe(
			attributes
		);
	} );

	it( 'does not mutate the attributes', () => {
		const attributes = { label: 'Name' };
		mergeContextValues(
			attributes,
			{ formId: 'example/formId' },
			{ 'example/formId': 'x' }
		);

		expect( attributes ).toEqual( { label: 'Name' } );
	} );
} );

describe( 'markup preview scope', () => {
	it( 'interpolates a context-sourced value and updates when the provider changes', () => {
		const markup = {
			tag: 'span',
			text: 'Form {{ attributes.formId }}: {{ attributes.label }}',
		};
		const contextFields = { formId: 'example/formId' };
		const render = ( formId ) =>
			MarkupPreview( {
				markup,
				attributes: mergeContextValues(
					{ label: 'Name' },
					contextFields,
					{ 'example/formId': formId }
				),
			} );

		expect( render( 'a' ).props.children ).toBe( 'Form a: Name' );
		expect( render( 'b' ).props.children ).toBe( 'Form b: Name' );
	} );

	it( 'evaluates a markup "when" against the merged scope', () => {
		const element = MarkupPreview( {
			markup: {
				tag: 'div',
				text: 'shown',
				when: {
					relation: 'AND',
					rules: [ { field: 'formId', operator: 'not_empty' } ],
				},
			},
			attributes: mergeContextValues(
				{},
				{ formId: 'example/formId' },
				{ 'example/formId': 'a' }
			),
		} );

		expect( element.props.children ).toBe( 'shown' );
	} );
} );

describe( 'inspector rendering', () => {
	it( 'a context-sourced field renders nothing in the inspector', () => {
		// FieldValueBridge is the element's type; render it as a function.
		const element = renderNode( contextNode(), 0, {
			attributes: {},
			setAttributes: jest.fn(),
			context: { 'example/formId': 'x' },
		} );

		expect( element.type.name ).toBe( 'FieldValueBridge' );
		expect( element.type( element.props ) ).toBeNull();
	} );

	it( 'a uuid field renders no control and no "unsupported" notice', () => {
		const element = renderNode(
			{
				type: 'uuid',
				isContainer: false,
				holdsValue: true,
				config: { name: 'instance_id' },
			},
			0,
			{ attributes: { instance_id: '' }, setAttributes: jest.fn() }
		);

		expect( element.type.name ).toBe( 'FieldValueBridge' );
		expect( element.type( element.props ) ).toBeNull();
	} );

	it( 'a "conditional" can reference a context field through ctx.scopeAttributes', () => {
		const node = {
			type: 'text',
			isContainer: false,
			holdsValue: true,
			config: {
				name: 'detail',
				conditional: {
					relation: 'AND',
					rules: [ { field: 'formId', operator: '==', value: 'a' } ],
				},
			},
		};

		expect(
			renderNode( node, 0, {
				attributes: {},
				scopeAttributes: { formId: 'a' },
			} )
		).not.toBeNull();
		expect(
			renderNode( node, 0, {
				attributes: {},
				scopeAttributes: { formId: 'b' },
			} )
		).toBeNull();
	} );
} );

describe( 'createEdit', () => {
	it( 'passes clientId and the merged scope to the canvas', () => {
		const descriptor = {
			name: 'example/field',
			fields: [ { ...contextNode(), area: 'canvas' } ],
			render: {
				markup: { tag: 'span', text: '{{ attributes.formId }}' },
			},
			preview: {},
		};
		const Edit = createEdit( descriptor );
		const tree = Edit( {
			attributes: {},
			setAttributes: jest.fn(),
			context: { 'example/formId': 'live' },
			clientId: 'c1',
		} );
		const canvas = tree.props.children[ 2 ];

		expect( canvas.props.scopeAttributes ).toEqual( { formId: 'live' } );
		expect( canvas.props.attributes ).toEqual( {} );
	} );
} );
