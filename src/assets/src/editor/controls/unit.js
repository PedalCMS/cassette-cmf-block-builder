import { SelectControl, TextControl, BaseControl } from '@wordpress/components';

/**
 * "unit" control — a single CSS length value (e.g. "10px", "1.5em"),
 * edited as a plain numeric input plus a unit dropdown rather than the
 * components package's own `__experimentalUnitControl` (see
 * docs/control-catalog.md's note on experimental APIs) — the two together
 * cover the same real need (a number and a unit) with zero functional
 * loss and no dependency on an unstable-prefixed export.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    Raw field config; `field.units` overrides the default unit list.
 * @param {string}   props.value    Current value, e.g. "10px".
 * @param {Function} props.onChange Called with the new value.
 * @return {JSX.Element} The control.
 */
export default function Unit( { field, value, onChange } ) {
	const units = field.units || [ 'px', 'em', 'rem', '%', 'vw', 'vh' ];
	const match = /^(-?[\d.]*)(\D*)$/.exec( value || '' );
	const [ , number, unit ] = match || [ '', '', units[ 0 ] ];

	const emit = ( nextNumber, nextUnit ) =>
		onChange( '' === nextNumber ? '' : `${ nextNumber }${ nextUnit }` );

	return (
		<BaseControl
			label={ field.label }
			help={ field.description }
			id="cassette-cmf-block-builder-unit"
		>
			<div className="cassette-cmf-block-builder-unit__row">
				<TextControl
					type="number"
					value={ number }
					onChange={ ( v ) => emit( v, unit || units[ 0 ] ) }
				/>
				<SelectControl
					value={ unit || units[ 0 ] }
					options={ units.map( ( u ) => ( { value: u, label: u } ) ) }
					onChange={ ( u ) => emit( number, u ) }
				/>
			</div>
		</BaseControl>
	);
}
