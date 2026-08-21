import { BaseControl, Dashicon, TextControl } from '@wordpress/components';

/**
 * "icon" control — a dashicon slug (e.g. "star-filled"), typed as plain
 * text with a live preview via the stable `Dashicon` component, rather
 * than an icon-picker grid (which would need a bundled icon list this
 * milestone doesn't ship). A block author already knows dashicon slugs
 * from `args.icon`/toolbar controls elsewhere in this same config, so
 * typing one here is consistent with the rest of the catalog rather than
 * a new interaction pattern.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    Raw field config.
 * @param {string}   props.value    Current value, a dashicon slug.
 * @param {Function} props.onChange Called with the new value.
 * @return {JSX.Element} The control.
 */
export default function Icon( { field, value, onChange } ) {
	return (
		<BaseControl
			label={ field.label }
			help={ field.description }
			id="cassette-cmf-block-builder-icon"
		>
			<div className="cassette-cmf-block-builder-icon__row">
				{ !! value && <Dashicon icon={ value } /> }
				<TextControl value={ value || '' } onChange={ onChange } />
			</div>
		</BaseControl>
	);
}
