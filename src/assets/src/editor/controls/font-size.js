import { BaseControl, FontSizePicker } from '@wordpress/components';

/**
 * "font_size" control — FontSizePicker, a genuinely stable
 * `@wordpress/components` export (unlike several of its typography
 * siblings, which remain `__experimental`-prefixed — see
 * docs/control-catalog.md). `field.sizes` overrides the built-in preset
 * list; a block declaring no font-size presets of its own gets a small
 * sensible default rather than an empty, useless picker.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    Raw field config; `field.sizes` is an array of { name, size, slug }.
 * @param {string}   props.value    Current value (a CSS font-size, e.g. "16px").
 * @param {Function} props.onChange Called with the new value.
 * @return {JSX.Element} The control.
 */
export default function FontSize( { field, value, onChange } ) {
	const sizes = field.sizes || [
		{ name: 'Small', size: '13px', slug: 'small' },
		{ name: 'Medium', size: '16px', slug: 'medium' },
		{ name: 'Large', size: '20px', slug: 'large' },
		{ name: 'Extra large', size: '28px', slug: 'x-large' },
	];

	return (
		<BaseControl
			label={ field.label }
			help={ field.description }
			id="cassette-cmf-block-builder-font-size"
		>
			<FontSizePicker
				__next40pxDefaultSize
				fontSizes={ sizes }
				value={ value || undefined }
				onChange={ ( v ) => onChange( v || '' ) }
				withReset
			/>
		</BaseControl>
	);
}
