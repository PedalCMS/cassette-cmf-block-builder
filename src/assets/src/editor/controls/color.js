import { ColorPicker } from '@wordpress/components';

/**
 * "color" control — a CSS color string attribute.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    Raw field config.
 * @param {*}        props.value    Current attribute value.
 * @param {Function} props.onChange Called with the new value.
 * @return {JSX.Element} The control.
 */
export default function Color( { field, value, onChange } ) {
	return (
		<div className="cassette-cmf-block-builder-color-control">
			{ field.label && (
				<p className="cassette-cmf-block-builder-color-control__label">
					{ field.label }
				</p>
			) }
			<ColorPicker
				color={ value || undefined }
				onChange={ onChange }
				enableAlpha
			/>
			{ field.description && (
				<p className="components-base-control__help">
					{ field.description }
				</p>
			) }
		</div>
	);
}
