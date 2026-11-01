import * as blockEditor from '@wordpress/block-editor';
import { Placeholder, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const { useBlockProps } = blockEditor;

/**
 * Loading state of a block created by a shortcode / raw transform whose
 * attributes are still being fetched from the server: a core Placeholder with
 * a spinner. Replaced by the normal editor as soon as the attributes arrive.
 *
 * @param {Object} props            Component props.
 * @param {Object} props.descriptor This block's Editor_Payload entry.
 * @return {JSX.Element} The loading placeholder.
 */
export default function PendingArea( { descriptor } ) {
	const blockProps = useBlockProps( { 'aria-busy': 'true' } );

	return (
		<div { ...blockProps }>
			<Placeholder
				label={ descriptor.settings.title }
				className="cassette-cmf-block-builder-pending"
			>
				<Spinner />
				<span className="screen-reader-text">
					{ __( 'Converting…', 'cassette-cmf-block-builder' ) }
				</span>
			</Placeholder>
		</div>
	);
}
