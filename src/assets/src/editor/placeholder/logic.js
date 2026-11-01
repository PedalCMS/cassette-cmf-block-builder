/**
 * Pure logic for the placeholder area and the variation picker: when a block counts as "unconfigured"
 * (so its `placeholder`-area fields show in a <Placeholder>), and when the
 * block variation picker is shown on an empty block.
 */

import { evaluate } from '../conditions/evaluate';

/**
 * Whether a value counts as "not filled in": undefined, null, '' or [].
 * `false` and `0` are values.
 *
 * @param {*} value Attribute value.
 * @return {boolean} Whether it is empty.
 */
export function isUnset( value ) {
	return (
		undefined === value ||
		null === value ||
		'' === value ||
		( Array.isArray( value ) && 0 === value.length )
	);
}

/**
 * Top-level nodes whose area is `placeholder`.
 *
 * @param {Array} nodes Resolved top-level tree nodes.
 * @return {Array} The placeholder-area nodes.
 */
export function collectPlaceholderNodes( nodes ) {
	return ( nodes || [] ).filter(
		( node ) => 'placeholder' === String( node.area ).split( '.' )[ 0 ]
	);
}

/**
 * Attribute-sourced, value-holding leaf fields below some nodes (container
 * children included, a repeater's row template excluded).
 *
 * @param {Array} nodes Resolved tree nodes.
 * @return {Array<Object>} Field configs.
 */
function leafConfigs( nodes ) {
	const configs = [];

	const walk = ( list ) => {
		( list || [] ).forEach( ( node ) => {
			if ( node.isContainer && 'repeater' !== node.type ) {
				walk( node.fields );
				return;
			}
			if (
				node.holdsValue &&
				node.config?.name &&
				( node.config.source || 'attribute' ) === 'attribute'
			) {
				configs.push( node.config );
			}
		} );
	};

	walk( nodes );

	return configs;
}

/**
 * Whether the block is still unconfigured: the placeholder shows.
 *
 * With `placeholder.when` (a normalized `{ relation, rules }`), the block is
 * unconfigured exactly when that condition is true for its attributes.
 * Without it: every required placeholder field is empty. When no
 * placeholder field is required, every placeholder field is empty. A block
 * with no placeholder-area field is never unconfigured.
 *
 * @param {Object|null} placeholder      The block's `placeholder` payload entry.
 * @param {Array}       placeholderNodes collectPlaceholderNodes() result.
 * @param {Object}      attributes       Attributes (plus context values).
 * @return {boolean} Whether to show the placeholder.
 */
export function isUnconfigured( placeholder, placeholderNodes, attributes ) {
	if ( ! placeholderNodes.length ) {
		return false;
	}

	if ( placeholder?.when ) {
		return evaluate( placeholder.when, attributes );
	}

	const configs = leafConfigs( placeholderNodes );
	if ( ! configs.length ) {
		return false;
	}

	const required = configs.filter( ( config ) => config.required );
	const checked = required.length ? required : configs;

	return checked.every( ( config ) => isUnset( attributes[ config.name ] ) );
}

/**
 * Split the top-level nodes for rendering. While unconfigured, the
 * placeholder-area nodes go into the <Placeholder> and are left out of the
 * rest; once configured they are shown in the inspector (area `inspector`),
 * so the values stay editable.
 *
 * @param {Array}   nodes        Resolved top-level tree nodes.
 * @param {boolean} unconfigured Result of isUnconfigured().
 * @return {{ placeholderNodes: Array, otherNodes: Array }} The split.
 */
export function splitPlaceholderNodes( nodes, unconfigured ) {
	const isPlaceholder = ( node ) =>
		'placeholder' === String( node.area ).split( '.' )[ 0 ];

	if ( unconfigured ) {
		return {
			placeholderNodes: nodes.filter( isPlaceholder ),
			otherNodes: nodes.filter( ( node ) => ! isPlaceholder( node ) ),
		};
	}

	return {
		placeholderNodes: [],
		otherNodes: nodes.map( ( node ) =>
			isPlaceholder( node ) ? { ...node, area: 'inspector' } : node
		),
	};
}

/**
 * Variations that opt into the block variation picker: those listing
 * `block` explicitly in `scope` (a variation without `scope` is for the
 * inserter / transforms and never opens the picker).
 *
 * @param {Array} variations All registered variations of the block.
 * @return {Array} The block-scope variations.
 */
export function blockScopeVariations( variations ) {
	return ( variations || [] ).filter(
		( variation ) =>
			Array.isArray( variation.scope ) &&
			variation.scope.includes( 'block' )
	);
}

/**
 * Names of the block's `uuid` fields. The editor fills those in on insert, so
 * they must not count as "the user has configured the block".
 *
 * @param {Array} nodes Resolved tree nodes.
 * @return {string[]} Attribute names.
 */
export function collectUuidNames( nodes ) {
	const names = [];

	const walk = ( list ) => {
		( list || [] ).forEach( ( node ) => {
			if ( 'uuid' === node.type && node.config?.name ) {
				names.push( node.config.name );
			}
			if ( node.isContainer && 'repeater' !== node.type ) {
				walk( node.fields );
			}
		} );
	};

	walk( nodes );

	return names;
}

/**
 * Whether the attributes are still at their schema defaults (nothing typed
 * or chosen yet). An attribute without a default counts as pristine when
 * unset.
 *
 * @param {Object}   attributes Block attributes.
 * @param {Object}   schema     The block's attribute schema (name => { default? }).
 * @param {string[]} [ignore]   Attribute names to leave out of the check (uuid fields).
 * @return {boolean} Whether the block is pristine.
 */
export function isPristine( attributes, schema, ignore = [] ) {
	return Object.keys( schema || {} )
		.filter( ( name ) => ! ignore.includes( name ) )
		.every( ( name ) => {
			const value = attributes?.[ name ];
			const hasDefault = undefined !== schema[ name ]?.default;

			if ( ! hasDefault ) {
				return isUnset( value );
			}

			return (
				undefined === value ||
				JSON.stringify( value ) ===
					JSON.stringify( schema[ name ].default )
			);
		} );
}

/**
 * Whether the variation picker shows: the block has block-scope variations,
 * no inner blocks, pristine attributes, no active variation and the user did
 * not skip or pick one in this session.
 *
 * @param {Object}  state
 * @param {Array}   state.variations     Block-scope variations.
 * @param {boolean} state.hasInnerBlocks Whether the block has inner blocks.
 * @param {boolean} state.pristine       isPristine() result.
 * @param {boolean} state.hasActive      Whether a variation is currently active.
 * @param {boolean} state.dismissed      Skipped / chosen in this session.
 * @return {boolean} Whether to show the picker.
 */
export function shouldShowVariationPicker( {
	variations,
	hasInnerBlocks,
	pristine,
	hasActive,
	dismissed,
} ) {
	return (
		variations.length > 0 &&
		! hasInnerBlocks &&
		pristine &&
		! hasActive &&
		! dismissed
	);
}

/**
 * Apply a chosen variation to the block: its attributes, then its
 * `innerBlocks` templates (turned into blocks by the caller's factory).
 *
 * @param {Object}   variation               The chosen variation (or the default one).
 * @param {Object}   deps
 * @param {Function} deps.setAttributes      The block's setAttributes.
 * @param {Function} deps.replaceInnerBlocks ( clientId, blocks, updateSelection ) => void.
 * @param {Function} deps.createBlocks       Template list => blocks (createBlocksFromInnerBlocksTemplate).
 * @param {string}   deps.clientId           This block's clientId.
 */
export function applyVariation(
	variation,
	{ setAttributes, replaceInnerBlocks, createBlocks, clientId }
) {
	if ( ! variation ) {
		return;
	}

	if ( variation.attributes && Object.keys( variation.attributes ).length ) {
		setAttributes( variation.attributes );
	}

	if (
		Array.isArray( variation.innerBlocks ) &&
		variation.innerBlocks.length
	) {
		replaceInnerBlocks(
			clientId,
			createBlocks( variation.innerBlocks ),
			true
		);
	}
}

/**
 * The picker component to use: the stable `BlockVariationPicker` export when
 * the installed @wordpress/block-editor has one, else the experimental one
 * (the only export in WordPress 6.8 and 7.1).
 *
 * @param {Object} blockEditor The @wordpress/block-editor module namespace.
 * @return {Function|undefined} The picker component.
 */
export function resolveVariationPicker( blockEditor ) {
	return (
		blockEditor?.BlockVariationPicker ||
		blockEditor?.__experimentalBlockVariationPicker
	);
}
