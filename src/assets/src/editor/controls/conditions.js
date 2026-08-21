import { Button, SelectControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const OPERATORS = [
	{ value: '==', label: __( 'is', 'cassette-cmf-block-builder' ) },
	{ value: '!=', label: __( 'is not', 'cassette-cmf-block-builder' ) },
	{ value: '>', label: __( 'greater than', 'cassette-cmf-block-builder' ) },
	{
		value: '>=',
		label: __( 'greater than or equal to', 'cassette-cmf-block-builder' ),
	},
	{ value: '<', label: __( 'less than', 'cassette-cmf-block-builder' ) },
	{
		value: '<=',
		label: __( 'less than or equal to', 'cassette-cmf-block-builder' ),
	},
	{ value: 'in', label: __( 'is one of', 'cassette-cmf-block-builder' ) },
	{
		value: 'not_in',
		label: __( 'is not one of', 'cassette-cmf-block-builder' ),
	},
	{ value: 'empty', label: __( 'is empty', 'cassette-cmf-block-builder' ) },
	{
		value: 'not_empty',
		label: __( 'is not empty', 'cassette-cmf-block-builder' ),
	},
];

const VALUE_LESS_OPERATORS = [ 'empty', 'not_empty' ];
const MULTI_VALUE_OPERATORS = [ 'in', 'not_in' ];

/**
 * "conditions" control — a rule-set builder, editing a
 * { relation, rules[] } value (Control_Catalog's "conditions" entry:
 * value_type "object") using the same relation/operator vocabulary a
 * field's own "conditional" config and a markup node's "when" use. This is
 * for CONTENT-time rule authoring — a block whose own attributes hold a
 * ruleset the block's own render logic later interprets (e.g. a
 * form-builder block letting a site editor build "show field B when field
 * A is filled" without writing PHP) — distinct from a field's
 * "conditional", which the BLOCK'S OWN author declares once in PHP/JSON to
 * control inspector visibility.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    Raw field config.
 * @param {Object}   props.value    Current attribute value: { relation, rules[] } (or undefined).
 * @param {Function} props.onChange Called with the new { relation, rules[] } value.
 * @param {Object}   props.scope    Shared render context; scope.fieldOptions is this block's field picker options.
 * @return {JSX.Element} The control.
 */
export default function Conditions( { field, value, onChange, scope } ) {
	const relation = value?.relation || 'AND';
	const rules = Array.isArray( value?.rules ) ? value.rules : [];
	const fieldOptions = scope?.fieldOptions || [];

	const updateRules = ( newRules ) =>
		onChange( { relation, rules: newRules } );

	const updateRule = ( index, changes ) =>
		updateRules(
			rules.map( ( rule, i ) =>
				i === index ? { ...rule, ...changes } : rule
			)
		);

	const removeRule = ( index ) =>
		updateRules( rules.filter( ( rule, i ) => i !== index ) );

	const addRule = () =>
		updateRules( [
			...rules,
			{
				field: fieldOptions[ 0 ]?.value || '',
				operator: '==',
				value: '',
			},
		] );

	return (
		<div className="cassette-cmf-block-builder-conditions">
			{ field.label && (
				<p className="cassette-cmf-block-builder-conditions__label">
					{ field.label }
				</p>
			) }

			{ rules.length > 1 && (
				<SelectControl
					label={ __( 'Match', 'cassette-cmf-block-builder' ) }
					value={ relation }
					options={ [
						{
							value: 'AND',
							label: __(
								'All rules',
								'cassette-cmf-block-builder'
							),
						},
						{
							value: 'OR',
							label: __(
								'Any rule',
								'cassette-cmf-block-builder'
							),
						},
					] }
					onChange={ ( newRelation ) =>
						onChange( { relation: newRelation, rules } )
					}
				/>
			) }

			{ rules.map( ( rule, index ) => {
				const operator = rule.operator || '==';
				const isMultiValue = MULTI_VALUE_OPERATORS.includes( operator );
				const displayValue = Array.isArray( rule.value )
					? rule.value.join( ', ' )
					: rule.value ?? '';

				return (
					// eslint-disable-next-line react/no-array-index-key -- rules have no stable id of their own; index is stable enough for a small, linearly-edited list.
					<div
						className="cassette-cmf-block-builder-conditions__rule"
						key={ index }
					>
						<SelectControl
							label={ __(
								'Field',
								'cassette-cmf-block-builder'
							) }
							value={ rule.field || '' }
							options={ [
								{
									value: '',
									label: __(
										'— Select a field —',
										'cassette-cmf-block-builder'
									),
								},
								...fieldOptions,
							] }
							onChange={ ( newField ) =>
								updateRule( index, { field: newField } )
							}
						/>
						<SelectControl
							label={ __(
								'Condition',
								'cassette-cmf-block-builder'
							) }
							value={ operator }
							options={ OPERATORS }
							onChange={ ( newOperator ) =>
								updateRule( index, { operator: newOperator } )
							}
						/>
						{ ! VALUE_LESS_OPERATORS.includes( operator ) && (
							<TextControl
								label={ __(
									'Value',
									'cassette-cmf-block-builder'
								) }
								help={
									isMultiValue
										? __(
												'Comma-separated',
												'cassette-cmf-block-builder'
										  )
										: undefined
								}
								value={ displayValue }
								onChange={ ( newValue ) =>
									updateRule( index, {
										value: isMultiValue
											? newValue
													.split( ',' )
													.map( ( item ) =>
														item.trim()
													)
													.filter( Boolean )
											: newValue,
									} )
								}
							/>
						) }
						<Button
							variant="link"
							isDestructive
							onClick={ () => removeRule( index ) }
						>
							{ __(
								'Remove rule',
								'cassette-cmf-block-builder'
							) }
						</Button>
					</div>
				);
			} ) }

			<Button variant="secondary" onClick={ addRule }>
				{ __( 'Add rule', 'cassette-cmf-block-builder' ) }
			</Button>

			{ field.description && (
				<p className="components-base-control__help">
					{ field.description }
				</p>
			) }
		</div>
	);
}
