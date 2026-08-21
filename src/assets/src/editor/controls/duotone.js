import { BaseControl, DuotonePicker } from '@wordpress/components';

const DEFAULT_PALETTE = [
	{ colors: [ '#000000', '#ffffff' ], slug: 'grayscale', name: 'Grayscale' },
	{
		colors: [ '#000097', '#ffffff' ],
		slug: 'blue-white',
		name: 'Blue and white',
	},
	{
		colors: [ '#8c0000', '#fcff41' ],
		slug: 'red-yellow',
		name: 'Red and yellow',
	},
];

/**
 * "duotone" control — DuotonePicker, a stable `@wordpress/components`
 * export (unlike the color-filter machinery around it in core blocks,
 * which leans on `__experimental` theme.json plumbing this milestone
 * doesn't build). `field.palette` overrides the built-in preset list.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    Raw field config; `field.palette` is an array of { colors, slug, name }.
 * @param {string[]} props.value    Current value: a [ shadowColor, highlightColor ] pair, or undefined for none.
 * @param {Function} props.onChange Called with the new value.
 * @return {JSX.Element} The control.
 */
export default function Duotone( { field, value, onChange } ) {
	const palette = field.palette || DEFAULT_PALETTE;

	return (
		<BaseControl
			label={ field.label }
			help={ field.description }
			id="cassette-cmf-block-builder-duotone"
		>
			<DuotonePicker
				duotonePalette={ palette }
				colorPalette={ [] }
				value={ value }
				onChange={ onChange }
			/>
		</BaseControl>
	);
}
