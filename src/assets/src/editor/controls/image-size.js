import { SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * "image_size" control — a plain SelectControl over the four WordPress
 * built-in image size slugs, rather than a live lookup of the site's own
 * registered sizes (that needs a `@wordpress/core-data` settings query
 * this control doesn't make — `field.sizes` lets a consumer supply the
 * site's real registered sizes explicitly instead). This is a documented
 * limitation, not a silent gap — see docs/control-catalog.md.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    Raw field config; `field.sizes` is `value => label`.
 * @param {string}   props.value    Current value, an image size slug.
 * @param {Function} props.onChange Called with the new value.
 * @return {JSX.Element} The control.
 */
export default function ImageSize( { field, value, onChange } ) {
	const sizes = field.sizes || {
		thumbnail: __( 'Thumbnail', 'cassette-cmf-block-builder' ),
		medium: __( 'Medium', 'cassette-cmf-block-builder' ),
		large: __( 'Large', 'cassette-cmf-block-builder' ),
		full: __( 'Full size', 'cassette-cmf-block-builder' ),
	};

	return (
		<SelectControl
			label={ field.label }
			help={ field.description }
			value={ value || 'full' }
			options={ Object.entries( sizes ).map( ( [ v, label ] ) => ( {
				value: v,
				label,
			} ) ) }
			onChange={ onChange }
		/>
	);
}
