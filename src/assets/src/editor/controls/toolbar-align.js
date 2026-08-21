import { AlignmentToolbar } from '@wordpress/block-editor';

/**
 * "toolbar_align" control — the built-in left/center/right/justify picker.
 *
 * @param {Object}   props          Component props.
 * @param {*}        props.value    Current attribute value.
 * @param {Function} props.onChange Called with the new alignment value.
 * @return {JSX.Element} The control.
 */
export default function ToolbarAlign( { value, onChange } ) {
	return <AlignmentToolbar value={ value } onChange={ onChange } />;
}
