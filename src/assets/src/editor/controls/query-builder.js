import { Button, SelectControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const COMPARES = [
	'=',
	'!=',
	'>',
	'>=',
	'<',
	'<=',
	'LIKE',
	'NOT LIKE',
	'IN',
	'NOT IN',
	'EXISTS',
	'NOT EXISTS',
];

const VALUE_LESS_COMPARES = [ 'EXISTS', 'NOT EXISTS' ];

/**
 * "query_builder" control — a rule-set builder for a WP_Query-shaped
 * filter (e.g. a meta_query clause list): { relation, rules: [ { key,
 * compare, value } ] }, using WP_Query's own comparison vocabulary
 * ("=", "LIKE", "EXISTS", ...) rather than "conditions"'s field-conditional
 * operators ("is", "is not", ...) — deliberately a separate control from
 * "conditions" even though the shape looks similar: this control's
 * "key" is a free-text meta/query key (not one of this block's own
 * declared fields, so there's no scope.fieldOptions picker here), and its
 * operator set is WP_Query's, not Render\Conditional_Evaluator's. This
 * control only *authors* the rule shape; a consumer's own render logic
 * (PHP) is responsible for turning it into a real WP_Query call.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    Raw field config.
 * @param {Object}   props.value    Current value: { relation, rules[] } (or undefined).
 * @param {Function} props.onChange Called with the new value.
 * @return {JSX.Element} The control.
 */
export default function QueryBuilder( { field, value, onChange } ) {
	const relation = value?.relation || 'AND';
	const rules = Array.isArray( value?.rules ) ? value.rules : [];

	const updateRules = ( newRules ) =>
		onChange( { relation, rules: newRules } );

	const updateRule = ( index, changes ) =>
		updateRules(
			rules.map( ( rule, i ) =>
				i === index ? { ...rule, ...changes } : rule
			)
		);

	return (
		<div className="cassette-cmf-block-builder-query-builder">
			{ field.label && (
				<p className="cassette-cmf-block-builder-query-builder__label">
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
				const compare = rule.compare || '=';

				return (
					// eslint-disable-next-line react/no-array-index-key -- rules have no stable id of their own; index is stable enough for a small, linearly-edited list.
					<div
						className="cassette-cmf-block-builder-query-builder__rule"
						key={ index }
					>
						<TextControl
							label={ __( 'Key', 'cassette-cmf-block-builder' ) }
							value={ rule.key || '' }
							onChange={ ( key ) => updateRule( index, { key } ) }
						/>
						<SelectControl
							label={ __(
								'Compare',
								'cassette-cmf-block-builder'
							) }
							value={ compare }
							options={ COMPARES.map( ( c ) => ( {
								value: c,
								label: c,
							} ) ) }
							onChange={ ( newCompare ) =>
								updateRule( index, { compare: newCompare } )
							}
						/>
						{ ! VALUE_LESS_COMPARES.includes( compare ) && (
							<TextControl
								label={ __(
									'Value',
									'cassette-cmf-block-builder'
								) }
								value={ rule.value ?? '' }
								onChange={ ( v ) =>
									updateRule( index, { value: v } )
								}
							/>
						) }
						<Button
							variant="link"
							isDestructive
							onClick={ () =>
								updateRules(
									rules.filter( ( _, i ) => i !== index )
								)
							}
						>
							{ __(
								'Remove rule',
								'cassette-cmf-block-builder'
							) }
						</Button>
					</div>
				);
			} ) }

			<Button
				variant="secondary"
				onClick={ () =>
					updateRules( [
						...rules,
						{ key: '', compare: '=', value: '' },
					] )
				}
			>
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
