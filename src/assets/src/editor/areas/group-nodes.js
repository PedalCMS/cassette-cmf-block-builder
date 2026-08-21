import { resolveGroup } from './groups';

/**
 * Group top-level tree nodes by their resolved area's group, for a given
 * surface ("inspector" or "toolbar"). Nodes for other surfaces (canvas,
 * placeholder, document, sidebar, more_menu — none of which this milestone
 * renders) are simply not included in the result. A declared group not in
 * knownGroups collapses into the default (undefined) group via
 * resolveGroup(), rather than being passed through as an invalid group=
 * value that would silently render nothing.
 *
 * @param {Array}  nodes       Top-level resolved tree nodes (Block_Definition's "fields").
 * @param {string} surface     "inspector" or "toolbar".
 * @param {Set}    knownGroups INSPECTOR_GROUPS or TOOLBAR_GROUPS.
 * @return {Map<string|undefined, Array>} Nodes keyed by their group (undefined for the default group), in declaration order.
 */
export function groupNodesBySurface( nodes, surface, knownGroups ) {
	const groups = new Map();

	for ( const node of nodes ) {
		const [ nodeSurface ] = node.area.split( '.' );

		if ( nodeSurface !== surface ) {
			continue;
		}

		const key = resolveGroup( node.area, knownGroups );

		if ( ! groups.has( key ) ) {
			groups.set( key, [] );
		}

		groups.get( key ).push( node );
	}

	return groups;
}
