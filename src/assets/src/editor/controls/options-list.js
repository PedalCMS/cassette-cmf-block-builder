import { BaseControl, Button, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * "options_list" control — a repeatable list of { value, label } pairs,
 * stored as an array (unlike "key_value"'s object) — for authoring a set
 * of options at content-time (e.g. the choices a "select" field on
 * ANOTHER block instance should offer), where declaration order and
 * duplicate values are both meaningful and worth preserving.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    Raw field config.
 * @param {Array}    props.value    Current value: an array of { value, label }.
 * @param {Function} props.onChange Called with the new array.
 * @return {JSX.Element} The control.
 */
export default function OptionsList( { field, value, onChange } ) {
	const rows = value || [];

	const setRow = ( index, patch ) => {
		const next = [ ...rows ];
		next[ index ] = { ...next[ index ], ...patch };
		onChange( next );
	};

	return (
		<BaseControl
			label={ field.label }
			help={ field.description }
			id="cassette-cmf-block-builder-options-list"
		>
			{ rows.map( ( row, index ) => (
				<div
					className="cassette-cmf-block-builder-options-list__row"
					key={ index }
				>
					<TextControl
						placeholder={ __(
							'Value',
							'cassette-cmf-block-builder'
						) }
						value={ row.value || '' }
						onChange={ ( v ) => setRow( index, { value: v } ) }
					/>
					<TextControl
						placeholder={ __(
							'Label',
							'cassette-cmf-block-builder'
						) }
						value={ row.label || '' }
						onChange={ ( v ) => setRow( index, { label: v } ) }
					/>
					<Button
						variant="link"
						isDestructive
						onClick={ () =>
							onChange( rows.filter( ( _, i ) => i !== index ) )
						}
					>
						{ __( 'Remove', 'cassette-cmf-block-builder' ) }
					</Button>
				</div>
			) ) }
			<Button
				variant="secondary"
				onClick={ () =>
					onChange( [ ...rows, { value: '', label: '' } ] )
				}
			>
				{ __( 'Add option', 'cassette-cmf-block-builder' ) }
			</Button>
		</BaseControl>
	);
}
