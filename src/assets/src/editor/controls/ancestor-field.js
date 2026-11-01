import { ComboboxControl, SelectControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useMemo } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { buildAncestorOptions } from '../ancestor/options';

/** Above this many entries a searchable ComboboxControl replaces the SelectControl. */
export const COMBOBOX_THRESHOLD = 20;

/**
 * The options a picker shows (pure): the built entries plus the placeholder
 * entry and, for many blocks, whether to use the searchable combobox.
 *
 * @param {Array}  built       Output of buildAncestorOptions().
 * @param {number} [threshold] Entry count above which a combobox is used.
 * @return {{ useCombobox: boolean }} Picker mode.
 */
export function pickerMode( built, threshold = COMBOBOX_THRESHOLD ) {
	return { useCombobox: built.length > threshold };
}

/**
 * Find the nearest ancestor block of the given name and return its block
 * tree (the store memoizes `getBlock()`, so the object only changes when
 * something inside that subtree changes).
 *
 * @param {Function} select   The `select` function of useSelect().
 * @param {string}   clientId This block's clientId.
 * @param {string}   ancestor Ancestor block name.
 * @return {Object|null} The ancestor block, or null.
 */
export function selectAncestorBlock( select, clientId, ancestor ) {
	const store = select( 'core/block-editor' );
	if ( ! store?.getBlockParentsByBlockName || ! clientId || ! ancestor ) {
		return null;
	}
	const [ ancestorId ] = store.getBlockParentsByBlockName(
		clientId,
		ancestor,
		true
	);
	return ancestorId ? store.getBlock( ancestorId ) : null;
}

/**
 * Live options for one block: the subtree of its nearest `config.ancestor`
 * block, read from the block-editor store. The selector returns the
 * ancestor block object (memoized by the store, so it only changes when
 * something inside that subtree changes); there is no second global
 * subscription. Returns an empty list when the block is not inside the
 * ancestor.
 *
 * @param {string} clientId     This block's clientId.
 * @param {Object} config       Control options.
 * @param {string} currentValue Stored value, so a missing reference stays listed.
 * @return {Array} Picker options (see buildAncestorOptions()).
 */
export function useAncestorOptions( clientId, config, currentValue ) {
	const ancestorBlock = useSelect(
		( select ) => selectAncestorBlock( select, clientId, config?.ancestor ),
		[ clientId, config?.ancestor ]
	);

	return useMemo(
		() =>
			buildAncestorOptions(
				ancestorBlock?.innerBlocks,
				config,
				{ selfClientId: clientId, currentValue },
				/* translators: %s: the name a missing block used to have. */
				__( 'missing: %s', 'cassette-cmf-block-builder' )
			),
		[ ancestorBlock, config, clientId, currentValue ]
	);
}

/**
 * The picker itself, reusable (the `conditions` control embeds it).
 *
 * @param {Object}   props          Component props.
 * @param {string}   props.label    Label.
 * @param {string}   [props.help]   Help text.
 * @param {string}   props.value    Stored block name.
 * @param {Function} props.onChange Called with the new name.
 * @param {Object}   props.config   Ancestor options.
 * @param {string}   props.clientId This block's clientId.
 * @return {JSX.Element} The picker.
 */
export function AncestorFieldPicker( {
	label,
	help,
	value,
	onChange,
	config,
	clientId,
} ) {
	const entries = useAncestorOptions( clientId, config, value );
	const { useCombobox } = pickerMode( entries );

	const options = entries.map( ( entry ) => ( {
		value: entry.value,
		label: entry.missing
			? entry.label
			: sprintf(
					/* translators: 1: block label, 2: block name attribute value. */
					__( '%1$s (%2$s)', 'cassette-cmf-block-builder' ),
					entry.label,
					entry.value
			  ),
	} ) );

	if ( useCombobox ) {
		return (
			<ComboboxControl
				label={ label }
				help={ help }
				value={ value || null }
				options={ options }
				onChange={ ( next ) => onChange( next || '' ) }
			/>
		);
	}

	return (
		<SelectControl
			label={ label }
			help={ help }
			value={ value ?? '' }
			options={ [
				{
					value: '',
					label: __(
						'— Select a block —',
						'cassette-cmf-block-builder'
					),
				},
				...options,
			] }
			onChange={ onChange }
		/>
	);
}

/**
 * "ancestor_field" control: pick another block (inside the nearest
 * `ancestor` block) by its name attribute. Stores that name, a string.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.field    Raw field config (ancestor, blocks, has_attribute, ...).
 * @param {string}   props.value    Current value (a block's name).
 * @param {Function} props.onChange Called with the new value.
 * @param {Object}   props.scope    Shared render context; scope.clientId is this block.
 * @return {JSX.Element} The control.
 */
export default function AncestorField( { field, value, onChange, scope } ) {
	return (
		<AncestorFieldPicker
			label={ field.label }
			help={ field.description }
			value={ value }
			onChange={ onChange }
			config={ field }
			clientId={ scope?.clientId }
		/>
	);
}
