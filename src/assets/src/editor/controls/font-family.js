import { SelectControl } from '@wordpress/components';

/**
 * "font_family" control — a plain SelectControl fed by `field.options`
 * (or a small generic default), rather than the block-editor package's
 * own `__experimentalFontFamilyControl` (which also depends on a
 * theme.json font-family lookup this milestone doesn't build). A
 * declarative `field.options` list already covers "pick one of the
 * theme's font families" once a consumer supplies its own theme.json-
 * derived list, without this control depending on an unstable API to get
 * there.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    Raw field config; `field.options` is `value => label`.
 * @param {string}   props.value    Current value (a CSS font-family stack).
 * @param {Function} props.onChange Called with the new value.
 * @return {JSX.Element} The control.
 */
export default function FontFamily( { field, value, onChange } ) {
	const options = field.options || {
		'system-ui, sans-serif': 'Sans-serif',
		'Georgia, serif': 'Serif',
		'Menlo, monospace': 'Monospace',
	};

	return (
		<SelectControl
			label={ field.label }
			help={ field.description }
			value={ value || '' }
			options={ Object.entries( options ).map( ( [ v, label ] ) => ( {
				value: v,
				label,
			} ) ) }
			onChange={ onChange }
		/>
	);
}
