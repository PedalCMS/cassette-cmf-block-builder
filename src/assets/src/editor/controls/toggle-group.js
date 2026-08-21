import { BaseControl, Button, ButtonGroup } from '@wordpress/components';

/**
 * "toggle_group" control — a segmented-button choice among field.options.
 *
 * Deliberately ButtonGroup + Button (both stable), not the components
 * package's ToggleGroupControl: that still only ships under the
 * experimental `__experimentalToggleGroupControl` name, and the eslint rule
 * against unstable WP APIs exists precisely because unstable-prefixed
 * exports can change without a deprecation path across WordPress versions.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    Raw field config, including "options".
 * @param {*}        props.value    Current attribute value.
 * @param {Function} props.onChange Called with the new value.
 * @return {JSX.Element} The control.
 */
export default function ToggleGroup( { field, value, onChange } ) {
	const options = Object.entries( field.options || {} );

	return (
		<BaseControl
			label={ field.label }
			help={ field.description }
			id="cassette-cmf-block-builder-toggle-group"
		>
			<ButtonGroup>
				{ options.map( ( [ optionValue, label ] ) => (
					<Button
						key={ optionValue }
						variant={
							value === optionValue ? 'primary' : 'secondary'
						}
						isPressed={ value === optionValue }
						onClick={ () => onChange( optionValue ) }
					>
						{ label }
					</Button>
				) ) }
			</ButtonGroup>
		</BaseControl>
	);
}
