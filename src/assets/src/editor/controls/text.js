import { TextControl } from '@wordpress/components';
import { useEffect, useRef } from '@wordpress/element';
import { applyFormat } from '../util/apply-format';
import { useUniqueHelp } from './unique-help';

export { applyFormat };

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
	const lastDerivedRef = useRef( null );
	const { help, hasError } = useUniqueHelp( field, scope );
	const sourceValue = field.derive_from
		? scope?.attributes?.[ field.derive_from ]
		: undefined;

	useEffect( () => {
		if ( ! field.derive_from || touchedRef.current ) {
			return;
		}

		const derived = applyFormat( sourceValue || '', field.format );
		if ( derived !== ( value ?? '' ) ) {
			lastDerivedRef.current = derived;
			onChange( derived );
		}
		// Deliberately excludes onChange/value: including them would
		// re-run (and re-derive) on every keystroke this component itself
		// causes, not only when the SOURCE field actually changes.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ sourceValue, field.derive_from, field.format ] );

	// A value that changed without this control deriving it (for example the
	// "unique" auto-suffix on a duplicated block) counts as a user edit, so
	// derivation stops overwriting it.
	useEffect( () => {
		if ( value && value !== lastDerivedRef.current ) {
			touchedRef.current = true;
		}
	}, [ value ] );

	return (
		<TextControl
			label={ field.label }
			help={ help }
			aria-invalid={ hasError ? 'true' : undefined }
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
