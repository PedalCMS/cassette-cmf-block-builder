import { RichText } from '@wordpress/block-editor';
import { TextareaControl } from '@wordpress/components';

/**
 * "rich_text" control ("wysiwyg" in the design plan's vocabulary) — a real
 * `RichText` only when declared in the "canvas" area, where it belongs:
 * RichText is an inline, in-canvas editing experience, not an inspector
 * widget. Declared in any other area (inspector, toolbar, ...) it
 * degrades to a plain TextareaControl and logs once under a non-production
 * build — a silent downgrade would leave a block author thinking they have
 * rich text when they don't. See docs/control-catalog.md.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    Raw field config.
 * @param {string}   props.value    Current value (HTML).
 * @param {Function} props.onChange Called with the new value.
 * @param {string}   [props.area]   This node's resolved area, e.g. "canvas" or "inspector".
 * @return {JSX.Element} The control.
 */
export default function RichTextControl( { field, value, onChange, area } ) {
	if ( 'canvas' === area ) {
		return (
			<RichText
				tagName={ field.tag || 'div' }
				value={ value || '' }
				onChange={ onChange }
				placeholder={ field.placeholder }
			/>
		);
	}

	if ( process.env.NODE_ENV !== 'production' ) {
		// eslint-disable-next-line no-console
		console.warn(
			`[cassette-cmf-block-builder] "rich_text" field "${ field.name }" is declared outside the "canvas" area; degrading to a plain textarea. Rich text editing only makes sense inline in the block's own canvas.`
		);
	}

	return (
		<TextareaControl
			label={ field.label }
			help={ field.description }
			value={ value || '' }
			onChange={ onChange }
		/>
	);
}
