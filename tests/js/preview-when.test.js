/**
 * preview.when gating: the pure rule and the canvas switch.
 */

import { isPreviewReady } from '../../src/assets/src/editor/preview/gate';
import CanvasArea from '../../src/assets/src/editor/areas/canvas';
import PreviewGate from '../../src/assets/src/editor/preview/PreviewGate';
import ServerPreview from '../../src/assets/src/editor/preview/ServerPreview';
import MarkupPreview from '../../src/assets/src/editor/preview/MarkupPreview';

const WHEN = {
	relation: 'AND',
	rules: [ { field: 'url', operator: '!=', value: '' } ],
};

describe( 'isPreviewReady', () => {
	it( 'is true without a rule', () => {
		expect( isPreviewReady( undefined, {} ) ).toBe( true );
		expect( isPreviewReady( { mode: 'markup' }, {} ) ).toBe( true );
		expect( isPreviewReady( { when: null }, {} ) ).toBe( true );
	} );

	it( 'follows the rule', () => {
		expect( isPreviewReady( { when: WHEN }, { url: '' } ) ).toBe( false );
		expect( isPreviewReady( { when: WHEN }, { url: 'https://x' } ) ).toBe( true );
	} );

	it( 'supports OR relations and the shared operators', () => {
		const when = {
			relation: 'OR',
			rules: [
				{ field: 'a', operator: '==', value: '1' },
				{ field: 'b', operator: 'not_empty' },
			],
		};

		expect( isPreviewReady( { when }, { a: '0', b: '' } ) ).toBe( false );
		expect( isPreviewReady( { when }, { a: '0', b: 'x' } ) ).toBe( true );
	} );

	it( 'ignores cross-block rules like every evaluator', () => {
		const when = {
			relation: 'AND',
			rules: [
				{ source: 'block', ref: 'other', operator: '==', value: 'x' },
				{ field: 'url', operator: '!=', value: '' },
			],
		};

		expect( isPreviewReady( { when }, { url: 'u' } ) ).toBe( true );
		expect( isPreviewReady( { when }, { url: '' } ) ).toBe( false );
		expect(
			isPreviewReady(
				{ when: { relation: 'AND', rules: [ when.rules[ 0 ] ] } },
				{}
			)
		).toBe( true );
	} );
} );

describe( 'CanvasArea gating', () => {
	const base = {
		name: 'acme/embed',
		settings: { title: 'Embed' },
		render: { markup: { tag: 'div' } },
	};

	it( 'shows the gate while the rule is false, for markup previews', () => {
		const out = CanvasArea( {
			descriptor: { ...base, preview: { when: WHEN } },
			attributes: { url: '' },
		} );

		expect( out.type ).toBe( PreviewGate );
	} );

	it( 'shows the gate instead of issuing a server preview request', () => {
		const out = CanvasArea( {
			descriptor: { ...base, preview: { mode: 'server', when: WHEN } },
			attributes: { url: '' },
			scopeAttributes: { url: '' },
		} );

		expect( out.type ).toBe( PreviewGate );
	} );

	it( 'renders the preview once the rule holds', () => {
		const markup = CanvasArea( {
			descriptor: { ...base, preview: { when: WHEN } },
			attributes: { url: 'u' },
		} );
		const server = CanvasArea( {
			descriptor: { ...base, preview: { mode: 'server', when: WHEN } },
			attributes: { url: 'u' },
		} );

		expect( markup.type ).toBe( MarkupPreview );
		expect( server.type ).toBe( ServerPreview );
	} );

	it( 'evaluates against the scope attributes (context fields) when given', () => {
		const out = CanvasArea( {
			descriptor: { ...base, preview: { when: WHEN } },
			attributes: { url: '' },
			scopeAttributes: { url: 'from-context' },
		} );

		expect( out.type ).toBe( MarkupPreview );
	} );
} );
