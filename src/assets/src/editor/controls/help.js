/**
 * "help" control — display-only chrome, no attribute. Distinct from a value
 * control's own "description" prop: this is a standalone note that isn't
 * attached to any particular field.
 *
 * @param {Object} props       Component props.
 * @param {Object} props.field Raw field config: "text".
 * @return {JSX.Element} The control.
 */
export default function Help( { field } ) {
	return (
		<p className="components-base-control__help cassette-cmf-block-builder-help">
			{ field.text }
		</p>
	);
}
