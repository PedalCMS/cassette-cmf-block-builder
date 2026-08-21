import { createSave } from './save';

/**
 * Registered attribute-migration functions, by name — the undocumented JS
 * escape hatch a "render.deprecated[].migrate" config value resolves
 * against. A PHP/JSON config can only ever supply a migration by NAME (it
 * has no way to embed a real function), so a consumer who needs an actual
 * attribute transformation across a deprecation registers it here, the same
 * pattern controls/registry.js's registerControlType() uses. Never
 * documented publicly — the library's stated contract is PHP/JSON-only,
 * zero consumer JS.
 *
 * @type {Object<string, Function>}
 */
const migrations = {};

/**
 * Register (or override) a named attribute-migration function, referenced
 * from PHP config as `'migrate' => 'name'`.
 *
 * @param {string}   name Migration name, matching a "render.deprecated[].migrate" value.
 * @param {Function} fn   WordPress's own deprecated-block migrate() signature:
 *                        ( attributes, innerBlocks ) => newAttributes | [ newAttributes, newInnerBlocks ].
 */
export function registerMigration( name, fn ) {
	migrations[ name ] = fn;
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

	return entries.map( ( entry ) => {
		const deprecated = {
			attributes: entry.attributes || descriptor.settings.attributes,
			save: createSave( entry.markup ),
		};

		const migrate = entry.migrate ? migrations[ entry.migrate ] : undefined;
		if ( migrate ) {
			deprecated.migrate = migrate;
		}

		return deprecated;
	} );
}
