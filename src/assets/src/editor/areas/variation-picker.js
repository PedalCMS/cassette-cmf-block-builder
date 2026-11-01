import * as blockEditor from '@wordpress/block-editor';
import { createBlocksFromInnerBlocksTemplate } from '@wordpress/blocks';
import { useDispatch, useSelect } from '@wordpress/data';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	applyVariation,
	blockScopeVariations,
	collectUuidNames,
	isPristine,
	resolveVariationPicker,
	shouldShowVariationPicker,
} from '../placeholder/logic';

const { useBlockProps } = blockEditor;

/**
 * The picker, shown with the block's own wrapper (the block stays
 * selectable). Choosing a variation applies its attributes and
 * `innerBlocks`; "Skip" keeps the empty block.
 *
 * @param {Object}   props            Component props.
 * @param {Object}   props.descriptor This block's Editor_Payload entry.
 * @param {Array}    props.variations Block-scope variations.
 * @param {Function} props.onSelect   Called with the chosen variation.
 * @param {Function} props.onSkip     Called when the user skips.
 * @return {JSX.Element|null} The picker.
 */
function VariationPickerView( { descriptor, variations, onSelect, onSkip } ) {
	const blockProps = useBlockProps();
	const Picker = resolveVariationPicker( blockEditor );

	if ( ! Picker ) {
		return null;
	}

	return (
		<div { ...blockProps }>
			<Picker
				icon={
					'string' === typeof descriptor.settings.icon
						? descriptor.settings.icon
						: undefined
				}
				label={ descriptor.settings.title }
				instructions={ __(
					'Select a variation to start with.',
					'cassette-cmf-block-builder'
				) }
				variations={ variations }
				onSelect={ onSelect }
				allowSkip
				onSkip={ onSkip }
			/>
		</div>
	);
}

/**
 * Wraps a block's normal editor UI: on an empty block (no inner blocks,
 * pristine attributes, no active variation) it shows the block variation
 * picker instead, like core Columns / Query; everything else renders the
 * children. Only mounted for blocks that declare a `scope: ['block']`
 * variation (see block-edit.js).
 *
 * @param {Object}      props               Component props.
 * @param {Object}      props.descriptor    This block's Editor_Payload entry.
 * @param {string}      props.clientId      This block's clientId.
 * @param {Object}      props.attributes    Block attributes.
 * @param {Function}    props.setAttributes The block's setAttributes.
 * @param {JSX.Element} props.children      The normal editor UI.
 * @return {JSX.Element} The picker or the children.
 */
export default function WithVariationPicker( {
	descriptor,
	clientId,
	attributes,
	setAttributes,
	children,
} ) {
	const [ dismissed, setDismissed ] = useState( false );
	const { replaceInnerBlocks } = useDispatch( 'core/block-editor' );

	const { variations, hasInnerBlocks, hasActive } = useSelect(
		( select ) => {
			const blocksStore = select( 'core/blocks' );
			const all =
				blocksStore?.getBlockVariations?.( descriptor.name ) || [];

			return {
				variations: blockScopeVariations( all ),
				hasInnerBlocks:
					( select( 'core/block-editor' )?.getBlockCount?.(
						clientId
					) || 0 ) > 0,
				hasActive: Boolean(
					blocksStore?.getActiveBlockVariation?.(
						descriptor.name,
						attributes,
						'block'
					)
				),
			};
		},
		[ descriptor.name, clientId, attributes ]
	);

	const show = shouldShowVariationPicker( {
		variations,
		hasInnerBlocks,
		pristine: isPristine(
			attributes,
			descriptor.settings.attributes,
			collectUuidNames( descriptor.fields )
		),
		hasActive,
		dismissed,
	} );

	if ( ! show ) {
		return children;
	}

	return (
		<VariationPickerView
			descriptor={ descriptor }
			variations={ variations }
			onSkip={ () => setDismissed( true ) }
			onSelect={ ( variation ) => {
				setDismissed( true );
				applyVariation( variation, {
					setAttributes,
					replaceInnerBlocks,
					createBlocks: createBlocksFromInnerBlocksTemplate,
					clientId,
				} );
			} }
		/>
	);
}
