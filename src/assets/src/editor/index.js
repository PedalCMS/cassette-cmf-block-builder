/**
 * Cassette-CMF Blocks — editor bootstrap.
 *
 * Reads the PHP-built payload from window.cassetteCmfBlocks (Editor_Payload,
 * inlined ahead of this script by Asset_Loader) and registers each block
 * descriptor. One global, one loop, no consumer JS required — the whole
 * point of the design.
 */

import './chrome.scss';
import { registerBlocks } from './register-block';
import { registerControlType } from './controls/registry';
import { registerMigration } from './deprecations';
import { registerDocumentAreas } from './areas/document';

const payload = window.cassetteCmfBlocks;

if ( payload && payload.blocks ) {
	// The undocumented JS escape hatch (design plan): a consumer willing to
	// read the source can register their own control component or a named
	// attribute migration the same way the library's own controls are wired
	// in (controls/registry.js, deprecations.js). Never mentioned in public
	// docs — the stated public contract is PHP/JSON-only, zero consumer JS.
	//
	// Assigned BEFORE registerBlocks() runs, not after: buildDeprecations()
	// (called synchronously by registerBlocks(), once, at registration time)
	// resolves a "render.deprecated[].migrate" name against this registry
	// immediately and bakes the result into registerBlockType()'s immutable
	// "deprecated" array — a migration registered afterward would never be
	// found. registerControlType() has no such ordering requirement (a
	// control component is looked up fresh on every render, not cached at
	// registration time), but is assigned here too for one consistent
	// contract: read window.cassetteCmfBlocks.register*() are all valid
	// only up to (and not after) this script's own execution.
	payload.registerControlType = registerControlType;
	payload.registerMigration = registerMigration;

	registerBlocks( payload );
	registerDocumentAreas( payload );
}
