import { createElement } from '@wordpress/element';
import { InnerBlocks } from '@wordpress/block-editor';
import { createSave } from './save';
import { applyMigration, isEligible } from './migrate';

/**
 * Registered attribute-migration functions, by name: what a
 * "render.deprecated[].migrate" config value resolves against. A PHP/JSON
 * config can only ever supply a migration by NAME (it has no way to embed a
 * real function), so a consumer who needs an actual attribute transformation
 * across a deprecation registers it with registerMigration(), a public and
 * stable extension point (docs/STABILITY.md), the same pattern as
 * controls/registry.js's registerControlType().
 *
 * @type {Object<string, Function>}
 */
const migrations = Object.create( null );

/**
 * Register (or override) a named attribute-migration function, referenced
 * from PHP config as `'migrate' => 'name'`.
 *
 * @param {string}   name Migration name, matching a "render.deprecated[].migrate" value.
 * @param {Function} fn   WordPress's own deprecated-block migrate() signature:
 *                        ( attributes, innerBlocks ) => newAttributes | [ newAttributes, newInnerBlocks ].
 */
export function registerMigration( name, fn ) {
	if ( 'string' === typeof name && name && 'function' === typeof fn ) {
		migrations[ name ] = fn;
	}
}

/**
 * Build the WordPress "deprecated" array (registerBlockType()'s own
 * deprecated-versions mechanism) from a block's render.deprecated config —
 * the CONSUMER-DECLARED history the design plan calls for. Each entry is a
 * complete "what this block used to look like" record: the old markup tree
 * (rendered through its own save(), created fresh via createSave() so a
 * later markup change never affects an earlier deprecation's own save()),
 * the old attribute schema (falling back to the block's current schema,
 * since a markup-only change — the common case — never touches it), and an
 * optional named migration.
 *
 * Auto-synthesizing a deprecation from a stored history of previously-
 * shipped markup hashes (Deprecation_Builder's hash, PHP) is deliberately
 * NOT built here: that needs a persistence design (where do old markup
 * trees get stored, across environments/deploys?) this milestone doesn't
 * specify. The explicit render.deprecated mechanism already fully covers
 * WordPress's actual requirement — a block author who changes markup must
 * supply the old version or existing content shows validation errors, true
 * of hand-written core blocks too, no core block auto-detects this either.
 *
 * @param {Object} descriptor One block's Editor_Payload entry: { settings, render, ... }.
 * @return {Array<Object>} The "deprecated" array for registerBlockType(), possibly empty.
 */
export function buildDeprecations( descriptor ) {
	const entries = ( descriptor.render && descriptor.render.deprecated ) || [];
	const isStatic = 'static' === descriptor.render?.mode;
	const currentAttributes = Object.keys(
		descriptor.settings?.attributes || {}
	);

	return entries.map( ( entry ) => {
		const deprecated = {
			attributes: entry.attributes || descriptor.settings.attributes,
			save: isStatic
				? createSave( entry.markup )
				: createDynamicSave( descriptor ),
		};

		if ( ! isStatic ) {
			// A dynamic block has no saved markup that would fail validation, so WordPress
			// only tries a deprecation when told the stored attributes are old-shaped. This is
			// the same rule Render\Deprecation_Migrator::is_eligible() applies on the server.
			deprecated.isEligible = ( attributes ) =>
				isEligible( entry, currentAttributes, attributes );
		}

		const migrate = resolveMigrate( entry.migrate );
		if ( migrate ) {
			deprecated.migrate = migrate;
		}

		return deprecated;
	} );
}

/**
 * Resolve an entry's "migrate": a declarative { rename, map, set, remove }
 * object becomes a pure function (shared with the server fixtures), a string
 * is looked up in the named registry. Nothing registered under a name means
 * no migrate (WordPress then carries the attributes over unchanged).
 *
 * @param {string|Object|undefined} migrate The entry's migrate value.
 * @return {Function|undefined} WordPress's migrate( attributes, innerBlocks ).
 */
function resolveMigrate( migrate ) {
	if ( migrate && 'object' === typeof migrate ) {
		return ( attributes, innerBlocks ) => [
			applyMigration( migrate, attributes ),
			innerBlocks,
		];
	}

	return migrate ? migrations[ migrate ] : undefined;
}

/**
 * The save() of a dynamic block (also used for its deprecated versions: a
 * dynamic block persists nothing of its own but its inner blocks).
 *
 * @param {Object} descriptor One block's Editor_Payload entry.
 * @return {Function} A save() component.
 */
export function createDynamicSave( descriptor ) {
	if ( descriptor.innerBlocks?.enabled ) {
		return function Save() {
			return createElement( InnerBlocks.Content );
		};
	}

	return () => null;
}
