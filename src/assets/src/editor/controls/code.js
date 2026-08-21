import { TextareaControl } from '@wordpress/components';

/**
 * "code" control — a plain monospace TextareaControl. Not a
 * syntax-highlighted editor (no CodeMirror/CodeEditor dependency is
 * bundled) — the goal is a safe, honest place to author a short snippet
 * (a CSS override, a small JSON blob for `field.default`, ...), not an
 * IDE-grade experience.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    Raw field config.
 * @param {string}   props.value    Current value.
 * @param {Function} props.onChange Called with the new value.
 * @return {JSX.Element} The control.
 */
export default function Code( { field, value, onChange } ) {
	return (
		<TextareaControl
			label={ field.label }
			help={ field.description }
			value={ value || '' }
			onChange={ onChange }
			rows={ field.rows || 6 }
			className="cassette-cmf-block-builder-code-control"
		/>
	);
}
