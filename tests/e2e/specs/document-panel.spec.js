/**
 * E2E: a "document_scope.when_present: true" panel appears only once at
 * least one instance of the owning block exists in the post — not on
 * every post of a matching post type unconditionally.
 *
 * Fixture: acme-e2e/document-panel (document_scope.post_types: ["post"],
 * when_present: true), one "sidebar"-area meta-sourced field.
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'acme-e2e/document-panel — when_present gating', () => {
	test( 'the sidebar panel is absent until a block instance is inserted, then appears', async ( {
		admin,
		editor,
		page,
	} ) => {
		await admin.createNewPost();

		const optionsButton = page
			.getByRole( 'region', { name: 'Editor top bar' } )
			.getByRole( 'button', { name: 'Options', exact: true } );
		const menu = page.getByRole( 'menu' );
		// Plugin sidebar items are listed in the Options menu (in a
		// "Plugins" group that only exists once a plugin contributes one).
		const panelItem = menu.locator( '[role^="menuitem"]', {
			hasText: 'E2E Document Panel',
		} );

		await optionsButton.click();
		// Positive control: the menu really is open.
		await expect(
			menu.getByRole( 'menuitem', { name: 'Preferences' } )
		).toBeVisible();
		await expect( panelItem ).toHaveCount( 0 );

		await page.keyboard.press( 'Escape' );
		await expect(
			menu.getByRole( 'menuitem', { name: 'Preferences' } )
		).toHaveCount( 0 );

		await editor.insertBlock( { name: 'acme-e2e/document-panel' } );

		await optionsButton.click();
		await expect( panelItem ).toBeVisible();
	} );
} );
