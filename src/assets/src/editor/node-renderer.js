import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { CONTAINER_COMPONENTS } from './containers';
import { getControlComponent } from './controls/registry';
import Unsupported from './controls/unsupported';
import { evaluate } from './conditions/evaluate';
import { useFieldValue } from './value/useFieldValue';

/**
 * Dispatch a single resolved tree node (from Field_Collection::to_editor_tree())
 * to a container or leaf-control component. The one function every
 * container calls to render its children, and the one place a node's
 * "isContainer"/"type" decides what it becomes — kept out of BlockEdit
 * itself so containers can recurse into it without importing BlockEdit.
 *
 * A node's "conditional" (already normalized server-side — see
 * Core\Field_Collection::to_editor_tree()) is evaluated against the
 * block's own attributes before rendering either a container or a leaf: a
 * failing condition hides the whole node, container children included.
 * Field-level scope resolution is deliberately just "the block's own
 * attributes" for now — resolving a rule's field against ancestor
 * providesContext or sibling blocks (useConditionScope's fuller job) is
 * scoped to a later milestone; see conditions/useConditionScope.js.
 *
 * @param {Object} node  Resolved tree node: { type, area, cmfType, holdsValue, isContainer, config, fields? }.
 * @param {number} index Position among siblings, used as a React key fallback.
 * @param {Object} ctx   Shared render context: { attributes, setAttributes, context, fieldOptions }.
 * @return {JSX.Element|null} The rendered node, or null if nothing renders (a failing "conditional").
 */
export function renderNode( node, index, ctx ) {
	if (
		node.config.conditional &&
		! evaluate( node.config.conditional, ctx.attributes )
	) {
		return null;
	}

	if ( node.isContainer ) {
		if ( 'repeater' === node.type ) {
			return (
				<RepeaterField
					key={ node.config.name || index }
					node={ node }
					ctx={ ctx }
				/>
			);
		}

		const Container = CONTAINER_COMPONENTS[ node.type ];

		if ( ! Container ) {
			return (
				<Unsupported
					key={ node.config.name || index }
					field={ node.config }
					type={ node.type }
				/>
			);
		}

		return (
			<Container
				key={ node.config.name || index }
				node={ node }
				ctx={ ctx }
			/>
		);
	}

	return (
		<FieldValueBridge
			key={ node.config.name || index }
			node={ node }
			ctx={ ctx }
		/>
	);
}

/**
 * Resolve a leaf node's value/setter (useFieldValue() — "attribute" or
 * "meta", dispatched by the field's own "source") and render its control.
 * Its own component, not inlined into renderNode(), specifically so
 * useFieldValue()'s hook call happens inside a real component instance —
 * one per leaf, always calling the same hooks in the same order on every
 * render, satisfying React's rules of hooks even though renderNode() itself
 * is a plain function called conditionally/recursively, not a component.
 *
 * A "context"-sourced field (or any other source useFieldValue() doesn't
 * implement) falls back to the same Unsupported notice an unrendered
 * control type gets, rather than silently rendering an inert control the
 * user can type into with no effect — useFieldValue() is still called
 * first regardless, so this check never skips a hook call.
 *
 * "ctx.documentScoped" (set by areas/document.js for editor-wide document/
 * sidebar/more_menu panels, which have no single block instance to read
 * attributes from) additionally treats "attribute" as unsupported here —
 * only "meta" has a real read/write path outside a block instance; without
 * this check an "attribute"-sourced field would silently read/write a
 * throwaway ctx.attributes object that never persists anywhere.
 *
 * @param {Object} props      Component props.
 * @param {Object} props.node Resolved leaf tree node.
 * @param {Object} props.ctx  Shared render context.
 * @return {JSX.Element} The rendered control.
 */
function FieldValueBridge( { node, ctx } ) {
	const [ value, setValue ] = useFieldValue( node, ctx );
	const source = node.config.source || 'attribute';
	const isUnsupported =
		( 'attribute' !== source && 'meta' !== source ) ||
		( ctx.documentScoped && 'attribute' === source );

	if ( isUnsupported ) {
		return (
			<Unsupported
				field={ node.config }
				type={ `${ node.type } (source: "${ source }")` }
			/>
		);
	}

	const Control = getControlComponent( node.type );

	return (
		<Control
			field={ node.config }
			type={ node.type }
			value={ value }
			scope={ ctx }
			onChange={ setValue }
			area={ node.area }
		/>
	);
}

/**
 * "repeater" — a container that also holds its own value (an array of row
 * objects), matching the parent library's Repeater_Field semantics (see
 * Schema\Control_Catalog's docblock). Its own component rather than a
 * plain CONTAINER_COMPONENTS entry for two reasons: it needs
 * useFieldValue() (containers otherwise never call it — only leaves do,
 * via FieldValueBridge), and each row needs its own "row-scoped" render
 * context so its "node.fields" sub-tree (a shared row TEMPLATE, not one
 * tree per existing row) reads/writes that row's own data instead of the
 * block's attributes — reusing renderNode()/FieldValueBridge/
 * useFieldValue() unmodified: a sub-field's "source: attribute" already
 * means "read ctx.attributes[name]", and a row-scoped ctx's "attributes"
 * IS that one row's data object, so no new dispatch logic is needed inside
 * useFieldValue() itself for this to work.
 *
 * No drag-and-drop reordering (would need a DnD dependency this milestone
 * doesn't add) — "move up"/"move down" buttons cover the same need.
 *
 * @param {Object} props      Component props.
 * @param {Object} props.node Resolved tree node: repeater's own config, plus "fields" (the row template).
 * @param {Object} props.ctx  Shared render context.
 * @return {JSX.Element} The control.
 */
function RepeaterField( { node, ctx } ) {
	const [ value, setValue ] = useFieldValue( node, ctx );
	const rows = Array.isArray( value ) ? value : [];
	const rowFields = node.fields || [];

	const setRows = ( nextRows ) => setValue( nextRows );

	const addRow = () => setRows( [ ...rows, {} ] );
	const removeRow = ( index ) =>
		setRows( rows.filter( ( _, i ) => i !== index ) );
	const moveRow = ( index, delta ) => {
		const target = index + delta;
		if ( target < 0 || target >= rows.length ) {
			return;
		}
		const next = [ ...rows ];
		[ next[ index ], next[ target ] ] = [ next[ target ], next[ index ] ];
		setRows( next );
	};

	return (
		<div className="cassette-cmf-block-builder-repeater">
			{ node.config.label && (
				<p className="cassette-cmf-block-builder-repeater__label">
					{ node.config.label }
				</p>
			) }

			{ rows.map( ( row, index ) => {
				const rowCtx = {
					...ctx,
					attributes: row,
					setAttributes: ( patch ) =>
						setRows(
							rows.map( ( r, i ) =>
								i === index ? { ...r, ...patch } : r
							)
						),
				};

				return (
					// eslint-disable-next-line react/no-array-index-key -- rows have no stable id of their own; index is stable enough for a small, linearly-edited list.
					<div
						className="cassette-cmf-block-builder-repeater__row"
						key={ index }
					>
						{ rowFields.map( ( child, childIndex ) =>
							renderNode( child, childIndex, rowCtx )
						) }
						<div className="cassette-cmf-block-builder-repeater__row-actions">
							<Button
								variant="tertiary"
								disabled={ 0 === index }
								onClick={ () => moveRow( index, -1 ) }
							>
								{ __(
									'Move up',
									'cassette-cmf-block-builder'
								) }
							</Button>
							<Button
								variant="tertiary"
								disabled={ index === rows.length - 1 }
								onClick={ () => moveRow( index, 1 ) }
							>
								{ __(
									'Move down',
									'cassette-cmf-block-builder'
								) }
							</Button>
							<Button
								variant="link"
								isDestructive
								onClick={ () => removeRow( index ) }
							>
								{ __( 'Remove', 'cassette-cmf-block-builder' ) }
							</Button>
						</div>
					</div>
				);
			} ) }

			<Button variant="secondary" onClick={ addRow }>
				{ __( 'Add row', 'cassette-cmf-block-builder' ) }
			</Button>
		</div>
	);
}
