import { createElement, RawHTML } from '@wordpress/element';
import { interpolate, evaluate as evaluateExpression } from './expr';
import { evaluate as evaluateCondition } from '../conditions/evaluate';

const VOID_ELEMENTS = new Set( [
	'area',
	'base',
	'br',
	'col',
	'embed',
	'hr',
	'img',
	'input',
	'link',
	'meta',
	'param',
	'source',
	'track',
	'wbr',
] );

/**
 * Render one markup node tree to a React element — the JS half of "one
 * declarative markup tree drives three runtimes". Used both for the
 * instant, network-free editor canvas preview (preview/MarkupPreview.js)
 * and for a static block's save() output (save.js); the front-end
 * equivalent (for a dynamic block) is Render\Markup_Renderer (PHP).
 *
 * Escaping works differently here than in the PHP renderer, not because
 * the rules are different but because React already IS the escaping layer:
 * a plain string child is always safely rendered by React, so "text"
 * content needs no manual esc_html() equivalent. The one place this
 * milestone can't offer a real equivalent to PHP's wp_kses_post() is
 * "html" content and an explicit "escape": "raw" node — both use RawHTML
 * (React's dangerouslySetInnerHTML wrapper) unfiltered. This is an
 * accepted editor-only limitation: the block editor is already restricted
 * to users who can edit_posts, the same trust boundary this library's
 * "static_html" control already relies on (see controls/static-html.js),
 * and the real front-end output goes through Render\Markup_Renderer's full
 * wp_kses_post() regardless of what the editor preview shows.
 *
 * A node's "when" (already normalized server-side — see
 * Core\Editor_Payload::normalize_markup_conditions()) is evaluated against
 * scope.attributes; a failing condition renders nothing for that node
 * (children included) — the same rule Render\Markup_Renderer applies
 * front-end, via the shared conditions/evaluate.js <-> Render\Conditional_Evaluator
 * parity (tests/fixtures/conditions.json).
 *
 * @param {Object}        node                 Markup node.
 * @param {Object}        scope                Interpolation scope, e.g. { attributes: {...} }.
 * @param {string}        innerContent         The block's own already-rendered InnerBlocks content, for a "slot"
 *                                             node (ignored when options.renderSlot is given).
 * @param {string|number} key                  React key for this node among its siblings.
 * @param {Object}        [options]            Rendering options.
 * @param {Function}      [options.renderSlot] Called with the slot's React key when a "slot" node is reached,
 *                                             instead of wrapping innerContent in RawHTML — save.js uses this
 *                                             to emit <InnerBlocks.Content /> instead of a literal HTML string,
 *                                             since save() has no rendered inner content to inject (WP expands
 *                                             <InnerBlocks.Content /> itself when serializing).
 * @return {*} A React element, an array of them (for "repeat"), or null.
 */
export function renderNode( node, scope, innerContent, key, options = {} ) {
	if (
		node.when &&
		! evaluateCondition( node.when, scope.attributes || {} )
	) {
		return null;
	}

	if ( node.repeat && node.repeat.over ) {
		return renderRepeated( node, scope, innerContent, key, options );
	}

	const tag = node.tag || 'div';
	const props = { key };

	if ( node.class ) {
		props.className = interpolate( node.class, scope );
	}

	Object.entries( node.attrs || {} ).forEach( ( [ name, template ] ) => {
		props[ name ] = interpolate( String( template ), scope );
	} );

	if ( VOID_ELEMENTS.has( tag ) ) {
		return createElement( tag, props );
	}

	return createElement(
		tag,
		props,
		...renderNodeContent( node, scope, innerContent, options )
	);
}

/**
 * Render a node's content (slot, then text/html, then children) as an
 * array of React children. Exposed separately from renderNode() because
 * the tree's root node needs its content rendered the same way but its own
 * open tag built from useBlockProps() instead of a plain className prop
 * (see preview/MarkupPreview.js). Does NOT re-check the root node's own
 * "when" (renderNode() already did, before calling into here) — only
 * each child's, via the recursive renderNode() call below.
 *
 * @param {Object} node         Markup node.
 * @param {Object} scope        Interpolation scope.
 * @param {string} innerContent InnerBlocks content for a "slot" node.
 * @param {Object} [options]    See renderNode()'s "options" param.
 * @return {Array} React children.
 */
export function renderNodeContent( node, scope, innerContent, options = {} ) {
	const output = [];

	if ( 'inner_blocks' === node.slot || 'content' === node.slot ) {
		output.push(
			options.renderSlot
				? options.renderSlot( 'slot' )
				: createElement( RawHTML, { key: 'slot' }, innerContent || '' )
		);
	}

	if ( undefined !== node.text ) {
		const value = interpolate( String( node.text ), scope );
		output.push(
			'raw' === node.escape
				? createElement( RawHTML, { key: 'text' }, value )
				: value
		);
	} else if ( undefined !== node.html ) {
		output.push(
			createElement(
				RawHTML,
				{ key: 'html' },
				interpolate( String( node.html ), scope )
			)
		);
	}

	( node.children || [] ).forEach( ( child, index ) => {
		output.push( renderNode( child, scope, innerContent, index, options ) );
	} );

	return output;
}

/**
 * Render a "repeat"-bearing node once per item in the resolved array, each
 * time with the scope extended by "{as}" (and "{as}_index").
 *
 * @param {Object}        node         Markup node with a "repeat" key.
 * @param {Object}        scope        Interpolation scope.
 * @param {string}        innerContent InnerBlocks content for a "slot" node.
 * @param {string|number} key          React key prefix for the repeated elements.
 * @param {Object}        [options]    See renderNode()'s "options" param.
 * @return {Array|null} One rendered element per item, or null when "repeat.over" isn't an array.
 */
function renderRepeated( node, scope, innerContent, key, options ) {
	const items = evaluateExpression( node.repeat.over, scope );
	const as = node.repeat.as || 'item';

	if ( ! Array.isArray( items ) ) {
		return null;
	}

	// "when" already gated the whole repeated group in renderNode() above
	// (it doesn't vary per item — it only reads scope.attributes, not the
	// repeat item), so it's dropped here to avoid a harmless but wasteful
	// re-check on every single item.
	const nodeWithoutRepeat = { ...node };
	delete nodeWithoutRepeat.repeat;
	delete nodeWithoutRepeat.when;

	return items.map( ( item, index ) =>
		renderNode(
			nodeWithoutRepeat,
			{ ...scope, [ as ]: item, [ `${ as }_index` ]: index },
			innerContent,
			`${ key }-${ index }`,
			options
		)
	);
}
