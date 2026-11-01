/**
 * Cassette-CMF Blocks — editor bootstrap.
 *
 * Reads the PHP-built payload (Editor_Payload, inlined ahead of this script by
 * Asset_Loader) and registers each block descriptor. The payload lives in a
 * per-prefix global (window.cassetteCmfBlocks_<prefix>, see Core\Identity),
 * so two copies of this bundle on one page, each with its own payload, never
 * touch each other's blocks. The name of the global arrives through a one-shot
 * hand-over: the inline "before" script sets window.cassetteCmfBlocksBoot to
 * it right before publishing the payload; this script reads and deletes it
 * immediately. Scripts load blocking (no defer/async strategy), so two bundles
 * cannot interleave between the hand-over and the read.
 *
 * Every module-level registry below (controls, migrations, id-in-use checks,
 * unique conflicts, the instance watcher's state) belongs to this bundle
 * instance, because each bundle load evaluates its own copy of the modules.
 * The watcher only acts on this payload's own block names.
 */

import './chrome.scss';
import { registerBlocks } from './register-block';
import { registerControlType } from './controls/registry';
import { registerMigration } from './deprecations';
import { registerDocumentAreas } from './areas/document';
import { registerIdInUseCheck } from './instances/id-in-use';
import { startInstanceWatcher } from './instances/watcher';

/**
 * Read (and clear) the one-shot hand-over, then fetch this bundle's payload.
 *
 * @param {Object} win The window object.
 * @return {Object|undefined} The payload, when the hand-over and global exist.
 */
function takePayload( win ) {
	const globalName = win.cassetteCmfBlocksBoot;

	try {
		delete win.cassetteCmfBlocksBoot;
	} catch ( error ) {
		win.cassetteCmfBlocksBoot = undefined;
	}

	return 'string' === typeof globalName && globalName
		? win[ globalName ]
		: undefined;
}

const payload = takePayload( window );

if ( payload && payload.blocks ) {
	// The extension points: registerControlType(), registerMigration() and
	// registerIdInUseCheck() on this bundle's payload global
	// (window.cassetteCmfBlocks_<prefix>). They are public and stable
	// (docs/STABILITY.md).
	//
	// A consumer script that depends on the editor handle runs BEFORE this
	// bundle, so the inline "before" script (Asset_Loader::extension_stubs())
	// installs queueing stubs on the payload; the queued calls are replayed
	// here, before registerBlocks() runs, and the stubs are then replaced by
	// the real functions for scripts that run later. Order matters for
	// registerMigration(): buildDeprecations() runs once, synchronously, in
	// registerBlocks(), and bakes the migration into registerBlockType()'s
	// immutable "deprecated" array, so a migration registered afterwards would
	// never be found. registerControlType() and registerIdInUseCheck() are
	// looked up lazily and work at any time.
	const extensionPoints = {
		// ( type, Component ): Component receives { field, value, onChange, type }.
		registerControlType,
		// ( name, fn ): fn is WordPress's deprecated migrate( attributes, innerBlocks ).
		registerMigration,
		// ( id, blockName ) => boolean | Promise<boolean>: lets a consumer veto
		// a "uuid" value on a block that was just inserted or pasted (for
		// example against its own server-side index). See docs/control-catalog.md.
		registerIdInUseCheck,
	};
	const pending = Array.isArray( payload.pendingExtensions )
		? payload.pendingExtensions
		: [];

	delete payload.pendingExtensions;
	Object.assign( payload, extensionPoints );

	pending.forEach( ( [ method, args ] ) => {
		if (
			Object.prototype.hasOwnProperty.call( extensionPoints, method ) &&
			Array.isArray( args )
		) {
			extensionPoints[ method ]( ...args );
		}
	} );

	registerBlocks( payload );
	registerDocumentAreas( payload );
	// One subscription for the whole library: "uuid" generation and "unique" checks.
	startInstanceWatcher( payload );
}
