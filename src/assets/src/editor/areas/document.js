import { registerPlugin } from '@wordpress/plugins';
import { useSelect } from '@wordpress/data';
import {
	PluginDocumentSettingPanel,
	PluginPostStatusInfo,
	PluginPostExcerpt,
	PluginPrePublishPanel,
	PluginPostPublishPanel,
	PluginSidebar,
	PluginSidebarMoreMenuItem,
} from '@wordpress/editor';
import { renderNode } from '../node-renderer';

/**
 * Editor-wide document/sidebar/more_menu areas — registered ONCE per
 * block globally (via registerPlugin()), not per block instance the way
 * inspector/toolbar/canvas areas are (BlockEdit only ever runs while an
 * instance is selected; these panels can be relevant with zero instances
 * in the post, e.g. "always show this settings panel on this post type").
 *
 * A block only gets a plugin registered here when Editor_Payload shipped
 * a non-null "documentScope" — Core\Document_Scope_Registrar already
 * validated it server-side (a block with document/sidebar/more_menu-area
 * fields but no valid "document_scope" gets documentScope: null and is
 * skipped here, per the design plan: "the library refuses to mount and
 * logs" — the log already happened server-side via _doing_it_wrong()).
 *
 * @param {Object} payload Editor_Payload::build()'s payload: { version, blocks }.
 */
export function registerDocumentAreas( payload ) {
	Object.values( payload.blocks || {} ).forEach( ( descriptor ) => {
		if ( ! descriptor.documentScope ) {
			return;
		}

		const nodes = ( descriptor.fields || [] ).filter( ( node ) =>
			[ 'document', 'sidebar', 'more_menu' ].includes(
				node.area.split( '.' )[ 0 ]
			)
		);

		if ( ! nodes.length ) {
			return;
		}

		registerPlugin( descriptor.documentScope.target, {
			render: () => (
				<DocumentPlugin descriptor={ descriptor } nodes={ nodes } />
			),
		} );
	} );
}

/**
 * One block's document-scoped UI: gated on the current post type (always)
 * and, when "when_present" is set, on at least one instance of this block
 * existing in the post — then renders each area group into its matching
 * Plugin* SlotFill.
 *
 * Field values come from post meta ("source: 'meta'"), not block
 * attributes — there is no single block instance to read attributes from
 * here. A document-scoped ctx carries "documentScoped: true", which
 * node-renderer.js's FieldValueBridge checks to treat an "attribute"-
 * sourced field as unsupported here (rather than silently reading/writing
 * a throwaway object) — only "meta"-sourced fields have a real read/write
 * path in a document-level context.
 *
 * @param {Object} props            Component props.
 * @param {Object} props.descriptor This block's Editor_Payload entry.
 * @param {Array}  props.nodes      This block's document/sidebar/more_menu-area top-level nodes.
 * @return {JSX.Element|null} The rendered panels, or null while gated out.
 */
function DocumentPlugin( { descriptor, nodes } ) {
	const { postType, blockCount } = useSelect(
		( select ) => ( {
			postType: select( 'core/editor' )?.getCurrentPostType?.(),
			blockCount: select( 'core/block-editor' )?.getGlobalBlockCount?.(
				descriptor.name
			),
		} ),
		[ descriptor.name ]
	);

	const scope = descriptor.documentScope;
	const inScope = scope.postTypes.includes( postType );
	const isPresent = ! scope.whenPresent || blockCount > 0;

	if ( ! inScope || ! isPresent ) {
		return null;
	}

	const ctx = {
		attributes: {},
		setAttributes: () => {},
		context: { postType },
		documentScoped: true,
		fieldOptions: [],
	};

	const byArea = ( area ) => nodes.filter( ( node ) => node.area === area );

	const sidebarNodes = [ ...byArea( 'sidebar' ), ...byArea( 'more_menu' ) ];

	return (
		<>
			{ byArea( 'document' ).length > 0 && (
				<PluginDocumentSettingPanel
					name={ `${ scope.target }-panel` }
					title={ descriptor.settings.title || descriptor.name }
				>
					{ byArea( 'document' ).map( ( node, index ) =>
						renderNode( node, index, ctx )
					) }
				</PluginDocumentSettingPanel>
			) }

			{ byArea( 'document.status' ).map( ( node, index ) => (
				<PluginPostStatusInfo key={ node.config.name || index }>
					{ renderNode( node, index, ctx ) }
				</PluginPostStatusInfo>
			) ) }

			{ byArea( 'document.excerpt' ).map( ( node, index ) => (
				<PluginPostExcerpt key={ node.config.name || index }>
					{ renderNode( node, index, ctx ) }
				</PluginPostExcerpt>
			) ) }

			{ byArea( 'document.publish.pre' ).length > 0 && (
				<PluginPrePublishPanel
					name={ `${ scope.target }-pre-publish` }
					title={ descriptor.settings.title || descriptor.name }
				>
					{ byArea( 'document.publish.pre' ).map( ( node, index ) =>
						renderNode( node, index, ctx )
					) }
				</PluginPrePublishPanel>
			) }

			{ byArea( 'document.publish.post' ).length > 0 && (
				<PluginPostPublishPanel
					name={ `${ scope.target }-post-publish` }
					title={ descriptor.settings.title || descriptor.name }
				>
					{ byArea( 'document.publish.post' ).map( ( node, index ) =>
						renderNode( node, index, ctx )
					) }
				</PluginPostPublishPanel>
			) }

			{ sidebarNodes.length > 0 && (
				<>
					<PluginSidebarMoreMenuItem target={ scope.target }>
						{ descriptor.settings.title || descriptor.name }
					</PluginSidebarMoreMenuItem>
					<PluginSidebar
						name={ scope.target }
						title={ descriptor.settings.title || descriptor.name }
					>
						{ sidebarNodes.map( ( node, index ) =>
							renderNode( node, index, ctx )
						) }
					</PluginSidebar>
				</>
			) }
		</>
	);
}
