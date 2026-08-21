import { LinkControl } from '@wordpress/block-editor';
import { BaseControl, Button, Dropdown } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * "link" control — the block-editor package's own `LinkControl` (URL
 * autocomplete against post/page search, "open in new tab", ...), opened
 * from a button in a Dropdown rather than shown inline — LinkControl is
 * designed to be shown transiently, not to sit permanently expanded in an
 * inspector panel. The value shape mirrors LinkControl's own: { url,
 * opensInNewTab, ... }.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    Raw field config.
 * @param {Object}   props.value    Current value: { url, opensInNewTab } (or undefined).
 * @param {Function} props.onChange Called with the new value.
 * @return {JSX.Element} The control.
 */
export default function Link( { field, value, onChange } ) {
	return (
		<BaseControl
			label={ field.label }
			help={ field.description }
			id="cassette-cmf-block-builder-link"
		>
			<Dropdown
				renderToggle={ ( { isOpen, onToggle } ) => (
					<Button
						variant="secondary"
						onClick={ onToggle }
						aria-expanded={ isOpen }
					>
						{ value?.url
							? value.url
							: __( 'Add link', 'cassette-cmf-block-builder' ) }
					</Button>
				) }
				renderContent={ () => (
					<LinkControl
						value={ value || undefined }
						onChange={ ( newValue ) => onChange( newValue ) }
					/>
				) }
			/>
		</BaseControl>
	);
}
