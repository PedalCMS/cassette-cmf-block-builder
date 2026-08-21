import { BaseControl, Button, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * "key_value" control — a repeatable list of key/value text pairs,
 * stored as a plain object. Rows are edited by array position (not
 * grouped by key), so two rows sharing the same key while both are being
 * edited is a real, accepted limitation — the last row written wins once
 * the object is assembled, same as any plain-object literal with a
 * duplicate key.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    Raw field config.
 * @param {Object}   props.value    Current value: a plain object.
 * @param {Function} props.onChange Called with the new object.
 * @return {JSX.Element} The control.
 */
export default function KeyValue( { field, value, onChange } ) {
	const rows = Object.entries( value || {} );

	const setRows = ( nextRows ) =>
		onChange(
			nextRows.reduce( ( acc, [ k, v ] ) => {
				if ( k ) {
					acc[ k ] = v;
				}
				return acc;
			}, {} )
		);

	return (
		<BaseControl
			label={ field.label }
			help={ field.description }
			id="cassette-cmf-block-builder-key-value"
		>
			{ rows.map( ( [ key, val ], index ) => (
				<div
					className="cassette-cmf-block-builder-key-value__row"
					key={ index }
				>
					<TextControl
						placeholder={ __(
							'Key',
							'cassette-cmf-block-builder'
						) }
						value={ key }
						onChange={ ( k ) => {
							const next = [ ...rows ];
							next[ index ] = [ k, val ];
							setRows( next );
						} }
					/>
					<TextControl
						placeholder={ __(
							'Value',
							'cassette-cmf-block-builder'
						) }
						value={ val }
						onChange={ ( v ) => {
							const next = [ ...rows ];
							next[ index ] = [ key, v ];
							setRows( next );
						} }
					/>
					<Button
						variant="link"
						isDestructive
						onClick={ () =>
							setRows( rows.filter( ( _, i ) => i !== index ) )
						}
					>
						{ __( 'Remove', 'cassette-cmf-block-builder' ) }
					</Button>
				</div>
			) ) }
			<Button
				variant="secondary"
				onClick={ () => setRows( [ ...rows, [ '', '' ] ] ) }
			>
				{ __( 'Add row', 'cassette-cmf-block-builder' ) }
			</Button>
		</BaseControl>
	);
}
