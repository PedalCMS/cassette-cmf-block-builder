/**
 * "heading" control — display-only chrome, no attribute.
 *
 * @param {Object} props       Component props.
 * @param {Object} props.field Raw field config: "text" or "title".
 * @return {JSX.Element} The control.
 */
export default function Heading( { field } ) {
	return (
		<h3 className="cassette-cmf-block-builder-heading">
			{ field.text || field.title }
		</h3>
	);
}
