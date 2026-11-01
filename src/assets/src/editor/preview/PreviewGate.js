import * as blockEditor from '@wordpress/block-editor';
import { Placeholder } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const { useBlockProps } = blockEditor;

/**
 * Shown instead of the preview while `preview.when` is false: the block's
 * own `placeholder` texts when it declares them, otherwise a plain
 * "Configure this block" prompt. The fields live in the inspector.
 *
 * @param {Object} props            Component props.
 * @param {Object} props.descriptor This block's Editor_Payload entry.
 * @return {JSX.Element} The placeholder.
 */
export default function PreviewGate( { descriptor } ) {
	const blockProps = useBlockProps();
	const config = descriptor.placeholder || {};
	const icon =
		config.icon ||
		( 'string' === typeof descriptor.settings.icon
			? descriptor.settings.icon
			: undefined );

	return (
		<div { ...blockProps }>
			<Placeholder
				icon={ icon }
				label={ config.label || descriptor.settings.title }
				instructions={
					config.instructions ||
					__( 'Configure this block.', 'cassette-cmf-block-builder' )
				}
				className="cassette-cmf-block-builder-preview-gate"
			/>
		</div>
	);
}
