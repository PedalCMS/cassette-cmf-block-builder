import { TextControl } from '@wordpress/components';
import { useEffect, useRef } from '@wordpress/element';
import { slugify } from '../util/slugify';

/**
 * Apply "format" to a value: "slug" (reuses the same slugify() used
 * elsewhere for a tab's derived name), "upper", or "lower". Any other
 * value (including unset) passes the value through unchanged.
 *
 * @param {string} value  Value to format.
 * @param {string} format One of "slug"/"upper"/"lower", or unset.
 * @return {string} The formatted value.
 */
export function applyFormat( value, format ) {
	if ( 'slug' === format ) {
		return slugify( value );
	}
	if ( 'upper' === format ) {
		return value.toUpperCase();
	}
	if ( 'lower' === format ) {
		return value.toLowerCase();
	}
	return value;
}

/**
 * "text" control — a single-line string attribute.
 *
 * "format" is applied on blur, not on every keystroke — formatting a
 * "slug"-style field live as the user types would fight them (e.g. they
 * can't type a space, since it would be replaced by a hyphen immediately),
 * the same UX WordPress's own post-slug field avoids by formatting on blur.
 *
 * "derive_from" continuously mirrors another field's (formatted) value
 * into this one — e.g. a "slug" field deriving from a "title" field, the
 * same auto-slug pattern WordPress's own post editor uses — until the user
 * types into this field themselves, at which point it stops mirroring.
 * "Has the user touched this field" lives in a ref (not state) precisely
 * so setting it doesn't itself trigger a re-render/effect loop.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    Raw field config (label, description, placeholder, format, derive_from).
 * @param {*}        props.value    Current attribute value.
 * @param {Function} props.onChange Called with the new value.
 * @param {Object}   [props.scope]  Shared render context; scope.attributes resolves "derive_from".
 * @return {JSX.Element} The control.
 */
export default function Text( { field, value, onChange, scope } ) {
	const touchedRef = useRef( !! value );
	const sourceValue = field.derive_from
		? scope?.attributes?.[ field.derive_from ]
		: undefined;

	useEffect( () => {
		if ( ! field.derive_from || touchedRef.current ) {
			return;
		}

		const derived = applyFormat( sourceValue || '', field.format );
		if ( derived !== ( value ?? '' ) ) {
			onChange( derived );
		}
		// Deliberately excludes onChange/value: including them would
		// re-run (and re-derive) on every keystroke this component itself
		// causes, not only when the SOURCE field actually changes.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ sourceValue, field.derive_from, field.format ] );

	return (
		<TextControl
			label={ field.label }
			help={ field.description }
			placeholder={ field.placeholder }
			value={ value ?? '' }
			onChange={ ( newValue ) => {
				touchedRef.current = true;
				onChange( newValue );
			} }
			onBlur={ () => {
				if ( field.format ) {
					onChange( applyFormat( value ?? '', field.format ) );
				}
			} }
		/>
	);
}
