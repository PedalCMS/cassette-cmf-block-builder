/**
 * Client-side feature-detected group whitelists for InspectorControls and
 * BlockControls.
 *
 * The PHP-side Area_Resolver::KNOWN_AREAS list was verified against the
 * WordPress version this library was developed against, explicitly NOT
 * against its stated 6.8 floor — some groups may not exist on an older (or
 * even a differently-built) WordPress, and some listed there
 * ("allowed_blocks", "last_item") turned out to correspond to *private*
 * SlotFills with no public "group" prop value at all. Filling a group that
 * doesn't exist renders nothing and silently drops the fields inside it —
 * the worst possible failure mode for a declarative config — so this file
 * is the client-side source of truth for what's actually safe to pass as
 * group=, independent of what the PHP payload claims. An area whose group
 * isn't in these sets falls back to the surface's default group rather
 * than being dropped.
 */

/**
 * Public InspectorControls group= values, read from the block-editor
 * package's own groups registry
 * (packages/block-editor/src/components/inspector-controls/groups.js).
 */
export const INSPECTOR_GROUPS = new Set( [
	'advanced',
	'background',
	'bindings',
	'border',
	'color',
	'content',
	'dimensions',
	'effects',
	'elements',
	'filter',
	'layout',
	'list',
	'position',
	'styles',
	'typography',
] );

/**
 * Public BlockControls group= values, read from the block-editor package's
 * own groups registry (packages/block-editor/src/components/block-controls/groups.js).
 */
export const TOOLBAR_GROUPS = new Set( [
	'block',
	'inline',
	'other',
	'parent',
] );

/**
 * Resolve an area's "<surface>[.group]" string to the group= value to pass
 * to InspectorControls/BlockControls — undefined (the default group) when
 * no group was declared, or when the declared group isn't in the
 * whitelist for this surface.
 *
 * @param {string} area        Normalized area string, e.g. "inspector.styles".
 * @param {Set}    knownGroups INSPECTOR_GROUPS or TOOLBAR_GROUPS.
 * @return {string|undefined} The group to pass, or undefined for the default group.
 */
export function resolveGroup( area, knownGroups ) {
	const [ , group ] = area.split( '.' );

	if ( ! group ) {
		return undefined;
	}

	if ( knownGroups.has( group ) ) {
		return group;
	}

	if ( process.env.NODE_ENV !== 'production' ) {
		// eslint-disable-next-line no-console
		console.warn(
			`[cassette-cmf-block-builder] area "${ area }" isn't a group this WordPress version exposes publicly; falling back to the default group.`
		);
	}

	return undefined;
}
