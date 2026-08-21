import { MediaUpload, MediaUploadCheck } from '@wordpress/block-editor';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * "media" control — an attachment ID attribute.
 *
 * Deliberately minimal: no thumbnail preview (that needs an attachment
 * lookup via @wordpress/core-data, out of scope for this milestone), just
 * select/replace/remove. A thumbnail is a pure enhancement for a later
 * milestone, not a functional gap.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    Raw field config.
 * @param {*}        props.value    Current attribute value (attachment ID).
 * @param {Function} props.onChange Called with the new attachment ID, or 0 to clear.
 * @return {JSX.Element} The control.
 */
export default function Media( { field, value, onChange } ) {
	return (
		<div className="cassette-cmf-block-builder-media-control">
			{ field.label && (
				<p className="cassette-cmf-block-builder-media-control__label">
					{ field.label }
				</p>
			) }
			<MediaUploadCheck>
				<MediaUpload
					onSelect={ ( media ) => onChange( media?.id ?? 0 ) }
					allowedTypes={ field.allowed_types || [ 'image' ] }
					value={ value }
					render={ ( { open } ) => (
						<>
							<Button variant="secondary" onClick={ open }>
								{ value
									? __(
											'Replace media',
											'cassette-cmf-block-builder'
									  )
									: __(
											'Select media',
											'cassette-cmf-block-builder'
									  ) }
							</Button>
							{ !! value && (
								<Button
									variant="link"
									isDestructive
									onClick={ () => onChange( 0 ) }
								>
									{ __(
										'Remove',
										'cassette-cmf-block-builder'
									) }
								</Button>
							) }
						</>
					) }
				/>
			</MediaUploadCheck>
			{ field.description && (
				<p className="components-base-control__help">
					{ field.description }
				</p>
			) }
		</div>
	);
}
