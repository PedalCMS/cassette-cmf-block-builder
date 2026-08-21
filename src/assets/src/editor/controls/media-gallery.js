import { MediaUpload, MediaUploadCheck } from '@wordpress/block-editor';
import { BaseControl, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * "media_gallery" control — an array of attachment IDs, via MediaUpload's
 * `multiple`/`gallery` mode. Same deliberate scope as "media": no
 * thumbnail grid (needs an attachment lookup via `@wordpress/core-data`,
 * out of scope for this control), just select/replace/clear — see
 * controls/media.js's docblock for the same reasoning.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    Raw field config.
 * @param {number[]} props.value    Current value: an array of attachment IDs.
 * @param {Function} props.onChange Called with the new array.
 * @return {JSX.Element} The control.
 */
export default function MediaGallery( { field, value, onChange } ) {
	const ids = value || [];

	return (
		<BaseControl
			label={ field.label }
			help={ field.description }
			id="cassette-cmf-block-builder-media-gallery"
		>
			<MediaUploadCheck>
				<MediaUpload
					multiple
					gallery
					onSelect={ ( media ) =>
						onChange( ( media || [] ).map( ( item ) => item.id ) )
					}
					allowedTypes={ field.allowed_types || [ 'image' ] }
					value={ ids }
					render={ ( { open } ) => (
						<>
							<Button variant="secondary" onClick={ open }>
								{ ids.length
									? __(
											'Edit gallery',
											'cassette-cmf-block-builder'
									  )
									: __(
											'Select images',
											'cassette-cmf-block-builder'
									  ) }
							</Button>
							{ !! ids.length && (
								<Button
									variant="link"
									isDestructive
									onClick={ () => onChange( [] ) }
								>
									{ __(
										'Clear',
										'cassette-cmf-block-builder'
									) }
								</Button>
							) }
						</>
					) }
				/>
			</MediaUploadCheck>
		</BaseControl>
	);
}
